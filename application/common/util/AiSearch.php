<?php
namespace app\common\util;

use think\Db;
use think\Log;

class AiSearch
{
    private const DEFAULT_INTERNAL_RESULT_LIMIT = 8;

    public static function buildForSearch($module, array $param = [])
    {
        $cfg = self::getConfig();
        $module = strtolower((string)$module);
        $wd = trim((string)(isset($param['wd']) ? $param['wd'] : ''));
        if ($wd === '') {
            return self::emptyPayload($wd);
        }
        if (!self::isEnabled($cfg, $module)) {
            return self::emptyPayload($wd);
        }
        if (mb_strlen($wd, 'UTF-8') < intval($cfg['min_query_len'])) {
            return self::emptyPayload($wd);
        }

        $expansion = self::expandTerms($cfg, $module, $wd);
        $queryMerged = self::mergeQuery($wd, $expansion);
        $internal = self::buildInternalResources($wd, $module);
        $external = self::buildExternalResources($cfg, $wd);

        return [
            'enabled' => true,
            'query_original' => $wd,
            'query_merged' => $queryMerged,
            'expanded_terms' => $expansion,
            'internal_resources' => $internal,
            'external_resources' => $external,
        ];
    }

    private static function getConfig()
    {
        $cfg = config('maccms.ai_search');
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $cfg = array_merge([
            'enabled' => '0',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_base' => 'https://api.openai.com/v1',
            'api_key' => '',
            'timeout' => '12',
            'max_terms' => '4',
            'min_query_len' => '2',
            'debug_log' => '0',
            'external_enabled' => '0',
            'external_domains' => 'wikipedia.org,imdb.com,douban.com',
            'external_max_links' => '3',
            'semantic_enabled' => '0',
            'embedding_model' => 'text-embedding-3-small',
            'semantic_weight' => '0.45',
            'semantic_candidates' => '40',
            'rate_limit_enabled' => '1',
            'rate_limit_window' => '60',
            'rate_limit_max' => '20',
            'max_question_chars' => '800',
            'internal_result_limit' => (string)self::DEFAULT_INTERNAL_RESULT_LIMIT,
            'module' => [
                'vod' => '1',
                'art' => '1',
                'topic' => '0',
                'actor' => '0',
                'role' => '0',
                'plot' => '0',
                'website' => '0',
            ],
        ], $cfg);
        if (!isset($cfg['module']) || !is_array($cfg['module'])) {
            $cfg['module'] = [];
        }
        $cfg['module'] = array_merge([
            'vod' => '1',
            'art' => '1',
            'topic' => '0',
            'actor' => '0',
            'role' => '0',
            'plot' => '0',
            'website' => '0',
        ], $cfg['module']);
        return $cfg;
    }

    private static function isEnabled($cfg, $module)
    {
        if ((string)$cfg['enabled'] !== '1') {
            return false;
        }
        return isset($cfg['module'][$module]) && (string)$cfg['module'][$module] === '1';
    }

    private static function emptyPayload($wd)
    {
        return [
            'enabled' => false,
            'query_original' => $wd,
            'query_merged' => $wd,
            'expanded_terms' => [],
            'internal_resources' => [],
            'external_resources' => [],
        ];
    }

    private static function mergeQuery($wd, array $expansion)
    {
        if (empty($expansion)) {
            return $wd;
        }
        $all = array_unique(array_filter(array_merge([$wd], $expansion)));
        return implode(' ', $all);
    }

    private static function expandTerms($cfg, $module, $wd)
    {
        $provider = strtolower((string)$cfg['provider']);
        $apiKey = trim((string)$cfg['api_key']);
        if ($provider !== 'openai' || $apiKey === '') {
            return [];
        }

        $maxTerms = max(1, intval($cfg['max_terms']));
        $apiBase = rtrim((string)$cfg['api_base'], '/');
        if ($apiBase === '') {
            $apiBase = 'https://api.openai.com/v1';
        }
        $url = $apiBase . '/chat/completions';
        $post = [
            'model' => (string)$cfg['model'],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'Return strict JSON with key terms as an array of concise search terms.'],
                ['role' => 'user', 'content' => "Module: {$module}\nQuery: {$wd}\nReturn 1-{$maxTerms} related terms in the same language as the query."],
            ],
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $respBody = HttpClient::curlPostWithTimeout($url, json_encode($post, JSON_UNESCAPED_UNICODE), $headers, max(3, intval($cfg['timeout'])));
        if ($respBody === false || $respBody === '') {
            self::debugLog($cfg, 'ai_search empty expansion response');
            return [];
        }
        $json = json_decode((string)$respBody, true);
        $content = isset($json['choices'][0]['message']['content']) ? (string)$json['choices'][0]['message']['content'] : '';
        if ($content === '') {
            self::debugLog($cfg, 'ai_search missing completion content');
            return [];
        }
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            return [];
        }
        $terms = [];
        if (isset($parsed['terms']) && is_array($parsed['terms'])) {
            $terms = $parsed['terms'];
        } elseif (isset($parsed['keywords']) && is_array($parsed['keywords'])) {
            $terms = $parsed['keywords'];
        } elseif (isset($parsed['key']) && is_array($parsed['key'])) {
            $terms = $parsed['key'];
        }
        $out = [];
        foreach ($terms as $term) {
            $term = trim(strip_tags((string)$term));
            if ($term === '' || $term === $wd) {
                continue;
            }
            $out[] = $term;
        }
        $out = array_values(array_unique($out));
        return array_slice($out, 0, $maxTerms);
    }

    private static function buildInternalResources($wd, $module)
    {
        $kw = '%' . addcslashes($wd, '%_') . '%';
        if ($module === 'vod') {
            return self::queryVodResources($kw);
        }
        if ($module === 'art') {
            return self::queryArtResources($kw);
        }
        if ($module === 'topic') {
            return self::queryTopicResources($kw);
        }
        if ($module === 'actor') {
            return self::queryActorResources($kw);
        }
        if ($module === 'role') {
            return self::queryRoleResources($kw);
        }
        if ($module === 'website') {
            return self::queryWebsiteResources($kw);
        }
        if ($module === 'plot') {
            return self::queryPlotResources($kw);
        }
        return [];
    }

    private static function buildExternalResources($cfg, $wd)
    {
        if ((string)$cfg['external_enabled'] !== '1') {
            return [];
        }
        $maxLinks = max(1, intval($cfg['external_max_links']));
        $domains = array_filter(array_map('trim', explode(',', (string)$cfg['external_domains'])));
        $domains = array_values(array_unique($domains));
        $domains = array_slice($domains, 0, $maxLinks);
        $query = rawurlencode($wd);
        $out = [];
        foreach ($domains as $domain) {
            $domain = preg_replace('/[^a-z0-9\.\-]/i', '', strtolower($domain));
            if ($domain === '') {
                continue;
            }
            $out[] = [
                'title' => 'Search "' . $wd . '" on ' . $domain,
                'url' => 'https://www.google.com/search?q=' . $query . '+site%3A' . rawurlencode($domain),
                'domain' => $domain,
            ];
        }
        return $out;
    }

    private static function queryVodResources($kw)
    {
        $fromMeili = self::queryVodResourcesByMeilisearch($kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
        $rows = Db::name('Vod')
            ->field('vod_id,vod_name,vod_pic')
            ->where('vod_status', 1)
            ->where('vod_name|vod_sub|vod_actor|vod_tag', 'like', $kw)
            ->order('vod_hits desc,vod_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['vod_name'],
                'url' => mac_url_vod_detail($row),
                'pic' => (string)$row['vod_pic'],
                'type' => 'vod',
            ];
        }
        return $result;
    }

    private static function queryArtResources($kw)
    {
        $fromMeili = self::queryArtResourcesByMeilisearch($kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
        $rows = Db::name('Art')
            ->field('art_id,art_name,art_pic')
            ->where('art_status', 1)
            ->where('art_name|art_sub|art_tag', 'like', $kw)
            ->order('art_hits desc,art_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['art_name'],
                'url' => mac_url_art_detail($row),
                'pic' => (string)$row['art_pic'],
                'type' => 'art',
            ];
        }
        return $result;
    }

    private static function queryVodResourcesByMeilisearch($kw)
    {
        $wd = self::extractKeywordFromLike($kw);
        if ($wd === '' || !MeilisearchService::enabled()) {
            return null;
        }
        $limit = self::getInternalResultLimit();
        $sr = MeilisearchService::search($wd, 'kind = "vod" AND recycle = 0 AND status = 1', $limit, 0);
        if (empty($sr['ok']) || empty($sr['hits']) || !is_array($sr['hits'])) {
            return null;
        }
        $ids = [];
        foreach ($sr['hits'] as $hit) {
            if (!empty($hit['id']) && is_string($hit['id']) && preg_match('/^vod_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return null;
        }
        $rows = Db::name('Vod')
            ->field('vod_id,vod_name,vod_pic')
            ->where('vod_status', 1)
            ->where('vod_recycle_time', 0)
            ->where('vod_id', 'in', implode(',', $ids))
            ->select();
        if (!is_array($rows)) {
            return null;
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['vod_id']] = $row;
        }
        $result = [];
        foreach ($ids as $id) {
            if (empty($map[$id])) {
                continue;
            }
            $row = $map[$id];
            $result[] = [
                'title' => (string)$row['vod_name'],
                'url' => mac_url_vod_detail($row),
                'pic' => (string)$row['vod_pic'],
                'type' => 'vod',
            ];
        }
        return $result;
    }

    private static function queryArtResourcesByMeilisearch($kw)
    {
        $wd = self::extractKeywordFromLike($kw);
        if ($wd === '' || !MeilisearchService::enabled()) {
            return null;
        }
        $limit = self::getInternalResultLimit();
        $sr = MeilisearchService::search($wd, 'kind = "art" AND recycle = 0 AND status = 1', $limit, 0);
        if (empty($sr['ok']) || empty($sr['hits']) || !is_array($sr['hits'])) {
            return null;
        }
        $ids = [];
        foreach ($sr['hits'] as $hit) {
            if (!empty($hit['id']) && is_string($hit['id']) && preg_match('/^art_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return null;
        }
        $rows = Db::name('Art')
            ->field('art_id,art_name,art_pic')
            ->where('art_status', 1)
            ->where('art_recycle_time', 0)
            ->where('art_id', 'in', implode(',', $ids))
            ->select();
        if (!is_array($rows)) {
            return null;
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['art_id']] = $row;
        }
        $result = [];
        foreach ($ids as $id) {
            if (empty($map[$id])) {
                continue;
            }
            $row = $map[$id];
            $result[] = [
                'title' => (string)$row['art_name'],
                'url' => mac_url_art_detail($row),
                'pic' => (string)$row['art_pic'],
                'type' => 'art',
            ];
        }
        return $result;
    }

    private static function extractKeywordFromLike($kw)
    {
        $wd = trim((string)$kw);
        if ($wd === '') {
            return '';
        }
        if (substr($wd, 0, 1) === '%') {
            $wd = substr($wd, 1);
        }
        if (substr($wd, -1) === '%') {
            $wd = substr($wd, 0, -1);
        }
        return str_replace(['\\%', '\\_'], ['%', '_'], $wd);
    }

    private static function getInternalResultLimit()
    {
        $cfg = self::getConfig();
        return max(1, intval($cfg['internal_result_limit']));
    }

    private static function queryTopicResources($kw)
    {
        $rows = Db::name('Topic')
            ->field('topic_id,topic_name,topic_en,topic_pic')
            ->where('topic_status', 1)
            ->where('topic_name|topic_sub|topic_tag|topic_blurb', 'like', $kw)
            ->order('topic_hits desc,topic_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['topic_name'],
                'url' => mac_url_topic_detail($row),
                'pic' => (string)$row['topic_pic'],
                'type' => 'topic',
            ];
        }
        return $result;
    }

    private static function queryActorResources($kw)
    {
        $rows = Db::name('Actor')
            ->field('actor_id,actor_name,actor_en,actor_pic')
            ->where('actor_status', 1)
            ->where('actor_name|actor_alias|actor_tag|actor_works', 'like', $kw)
            ->order('actor_hits desc,actor_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['actor_name'],
                'url' => mac_url_actor_detail($row),
                'pic' => (string)$row['actor_pic'],
                'type' => 'actor',
            ];
        }
        return $result;
    }

    private static function queryRoleResources($kw)
    {
        $rows = Db::name('Role')
            ->field('role_id,role_name,role_en,role_pic')
            ->where('role_status', 1)
            ->where('role_name|role_actor|role_remarks', 'like', $kw)
            ->order('role_hits desc,role_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['role_name'],
                'url' => mac_url_role_detail($row),
                'pic' => (string)$row['role_pic'],
                'type' => 'role',
            ];
        }
        return $result;
    }

    private static function queryWebsiteResources($kw)
    {
        $rows = Db::name('Website')
            ->field('website_id,website_name,website_en,website_pic')
            ->where('website_status', 1)
            ->where('website_name|website_sub|website_tag|website_blurb', 'like', $kw)
            ->order('website_hits desc,website_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['website_name'],
                'url' => mac_url_website_detail($row),
                'pic' => (string)$row['website_pic'],
                'type' => 'website',
            ];
        }
        return $result;
    }

    private static function queryPlotResources($kw)
    {
        $rows = Db::name('Vod')
            ->field('vod_id,vod_name,vod_pic')
            ->where('vod_status', 1)
            ->where('vod_plot_name|vod_plot_detail', 'like', $kw)
            ->order('vod_hits desc,vod_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['vod_name'],
                'url' => mac_url_vod_detail($row),
                'pic' => (string)$row['vod_pic'],
                'type' => 'plot',
            ];
        }
        return $result;
    }

    private static function debugLog($cfg, $msg)
    {
        if ((string)$cfg['debug_log'] !== '1') {
            return;
        }
        Log::record('[ai_search] ' . $msg, 'notice');
    }
}
