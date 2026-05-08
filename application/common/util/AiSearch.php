<?php
namespace app\common\util;

use think\Db;
use think\Log;

class AiSearch
{
    private const DEFAULT_INTERNAL_RESULT_LIMIT = 8;

    /**
     * Meili 内联资源：表名、主键、展示字段、详情 URL；与 Meilisearch 文档 id 前缀一致。
     * return_null_when_empty：true 时无任何 DB 命中返回 null（走 MySQL 回退）；vod/art/manga 保持原样返回数组（可为空）。
     *
     * @var array<string, array<string, mixed>>
     */
    private static $meiliResourceMeta = [
        'vod' => [
            'table' => 'Vod',
            'pk' => 'vod_id',
            'status' => 'vod_status',
            'recycle' => 'vod_recycle_time',
            'fields' => 'vod_id,vod_name,vod_pic',
            'title_key' => 'vod_name',
            'pic_key' => 'vod_pic',
            'return_null_when_empty' => false,
        ],
        'art' => [
            'table' => 'Art',
            'pk' => 'art_id',
            'status' => 'art_status',
            'recycle' => 'art_recycle_time',
            'fields' => 'art_id,art_name,art_pic',
            'title_key' => 'art_name',
            'pic_key' => 'art_pic',
            'return_null_when_empty' => false,
        ],
        'manga' => [
            'table' => 'Manga',
            'pk' => 'manga_id',
            'status' => 'manga_status',
            'recycle' => 'manga_recycle_time',
            'fields' => 'manga_id,manga_name,manga_pic',
            'title_key' => 'manga_name',
            'pic_key' => 'manga_pic',
            'return_null_when_empty' => false,
        ],
        'topic' => [
            'table' => 'Topic',
            'pk' => 'topic_id',
            'status' => 'topic_status',
            'recycle' => null,
            'fields' => 'topic_id,topic_name,topic_pic',
            'title_key' => 'topic_name',
            'pic_key' => 'topic_pic',
            'return_null_when_empty' => true,
        ],
        'actor' => [
            'table' => 'Actor',
            'pk' => 'actor_id',
            'status' => 'actor_status',
            'recycle' => null,
            'fields' => 'actor_id,actor_name,actor_pic',
            'title_key' => 'actor_name',
            'pic_key' => 'actor_pic',
            'return_null_when_empty' => true,
        ],
        'role' => [
            'table' => 'Role',
            'pk' => 'role_id',
            'status' => 'role_status',
            'recycle' => null,
            'fields' => 'role_id,role_name,role_pic',
            'title_key' => 'role_name',
            'pic_key' => 'role_pic',
            'return_null_when_empty' => true,
        ],
        'website' => [
            'table' => 'Website',
            'pk' => 'website_id',
            'status' => 'website_status',
            'recycle' => null,
            'fields' => 'website_id,website_name,website_pic',
            'title_key' => 'website_name',
            'pic_key' => 'website_pic',
            'return_null_when_empty' => true,
        ],
    ];

    /** @var array<string, array> 单次请求内相同 module+wd 只构建一次，避免 AI 聊天等场景重复 expand / Meili */
    private static $buildForSearchMemo = [];

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
        $memoKey = $module . "\x1e" . mb_strtolower($wd, 'UTF-8');
        if (isset(self::$buildForSearchMemo[$memoKey])) {
            return self::$buildForSearchMemo[$memoKey];
        }

        $expansion = self::expandTerms($cfg, $module, $wd);
        $queryMerged = self::mergeQuery($wd, $expansion);
        $internal = self::buildInternalResources($wd, $module, $queryMerged);
        $external = self::buildExternalResources($cfg, $wd);

        $out = [
            'enabled' => true,
            'query_original' => $wd,
            'query_merged' => $queryMerged,
            'expanded_terms' => $expansion,
            'internal_resources' => $internal,
            'external_resources' => $external,
        ];
        self::$buildForSearchMemo[$memoKey] = $out;

        return $out;
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
                'manga' => '1',
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
            'manga' => '1',
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
            'query_merged' => (string)$wd,
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

    private static function buildInternalResources($wd, $module, $queryMerged = null)
    {
        $kw = '%' . addcslashes($wd, '%_') . '%';
        $meiliQ = trim((string)($queryMerged !== null && $queryMerged !== '' ? $queryMerged : $wd));
        if ($module === 'vod') {
            return self::queryVodResources($kw, $meiliQ);
        }
        if ($module === 'art') {
            return self::queryArtResources($kw, $meiliQ);
        }
        if ($module === 'manga') {
            return self::queryMangaResources($kw, $meiliQ);
        }
        if ($module === 'topic') {
            return self::queryTopicResources($kw, $meiliQ);
        }
        if ($module === 'actor') {
            return self::queryActorResources($kw, $meiliQ);
        }
        if ($module === 'role') {
            return self::queryRoleResources($kw, $meiliQ);
        }
        if ($module === 'website') {
            return self::queryWebsiteResources($kw, $meiliQ);
        }
        if ($module === 'plot') {
            return self::queryPlotResources($kw, $meiliQ);
        }
        return [];
    }

    private static function buildExternalResources($cfg, $wd)
    {
        // Legacy helper used to emit Google site: links titled "Search \"…\" on domain".
        // Those are low-value in the AI chat UI; real externals come from federation (TMDB, etc.).
        return [];
    }

    private static function queryVodResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('vod', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
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

    private static function queryArtResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('art', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
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

    /**
     * Meilisearch 内联资源：统一 filter、解析 id、DB 回填、构造 title/url/pic/type。
     *
     * @param string      $kind          与 Meilisearch 文档 id 前缀一致：vod|art|manga|topic|actor|role|website
     * @param string      $kw            LIKE 包壳或明文（经 extractKeywordFromLike）
     * @param string|null $typeOverride  返回项的 type；null 则等于 $kind（如 plot 走 vod 数据但 type=plot）
     *
     * @return array<int, array{title:string,url:string,pic:string,type:string}>|null
     */
    private static function queryResourcesByMeilisearch($kind, $kw, $typeOverride = null)
    {
        $k = strtolower((string)$kind);
        if (!isset(self::$meiliResourceMeta[$k])) {
            return null;
        }
        $meta = self::$meiliResourceMeta[$k];
        $wd = self::extractKeywordFromLike($kw);
        if ($wd === '' || !MeilisearchService::enabled()) {
            return null;
        }
        $limit = self::getInternalResultLimit();
        $filter = MeilisearchService::filterPublishedKind($k);
        if ($filter === '') {
            return null;
        }
        $sr = MeilisearchService::search($wd, $filter, $limit, 0);
        if (empty($sr['ok']) || empty($sr['hits']) || !is_array($sr['hits'])) {
            return null;
        }
        $re = '/^' . preg_quote($k, '/') . '_(\d+)$/';
        $ids = [];
        foreach ($sr['hits'] as $hit) {
            if (!empty($hit['id']) && is_string($hit['id']) && preg_match($re, $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return null;
        }
        $table = $meta['table'];
        $pk = $meta['pk'];
        $st = $meta['status'];
        $rc = isset($meta['recycle']) ? $meta['recycle'] : null;
        $fields = $meta['fields'];
        try {
            $q = Db::name($table)->field($fields)->where($st, 1)->where($pk, 'in', implode(',', $ids));
            if ($rc !== null && $rc !== '') {
                $q->where($rc, 0);
            }
            $rows = $q->select();
        } catch (\Throwable $e) {
            if ($rc !== null && $rc !== '') {
                $rows = Db::name($table)->field($fields)->where($st, 1)->where($pk, 'in', implode(',', $ids))->select();
            } else {
                return null;
            }
        }
        if (!is_array($rows)) {
            return null;
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row[$pk]] = $row;
        }
        $typeItem = ($typeOverride !== null && $typeOverride !== '') ? (string)$typeOverride : $k;
        $titleKey = $meta['title_key'];
        $picKey = $meta['pic_key'];
        $result = [];
        foreach ($ids as $id) {
            if (empty($map[$id])) {
                continue;
            }
            $row = $map[$id];
            $result[] = [
                'title' => (string)$row[$titleKey],
                'url' => self::resourceDetailUrl($k, $row),
                'pic' => (string)$row[$picKey],
                'type' => $typeItem,
            ];
        }
        if (!empty($meta['return_null_when_empty']) && $result === []) {
            return null;
        }

        return $result;
    }

    /**
     * @param string               $kind Meilisearch / 表逻辑 kind（非 type 覆盖值）
     * @param array<string, mixed> $row
     */
    private static function resourceDetailUrl($kind, array $row)
    {
        switch ($kind) {
            case 'vod':
                return mac_url_vod_detail($row);
            case 'art':
                return mac_url_art_detail($row);
            case 'manga':
                return mac_url_manga_detail($row);
            case 'topic':
                return mac_url_topic_detail($row);
            case 'actor':
                return mac_url_actor_detail($row);
            case 'role':
                return mac_url_role_detail($row);
            case 'website':
                return mac_url_website_detail($row);
            default:
                return '';
        }
    }

    private static function queryMangaResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('manga', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
        $rows = Db::name('Manga')
            ->field('manga_id,manga_name,manga_en,manga_pic,manga_author')
            ->where('manga_status', 1)
            ->where('manga_name|manga_sub|manga_tag|manga_blurb|manga_author', 'like', $kw)
            ->order('manga_hits desc,manga_id desc')
            ->limit(8)
            ->select();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'title' => (string)$row['manga_name'],
                'url' => mac_url_manga_detail($row),
                'pic' => (string)$row['manga_pic'],
                'type' => 'manga',
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

    private static function queryTopicResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('topic', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
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

    private static function queryActorResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('actor', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
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

    private static function queryRoleResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('role', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
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

    private static function queryWebsiteResources($kw, $meiliQuery = null)
    {
        $fromMeili = self::queryResourcesByMeilisearch('website', $meiliQuery !== null && $meiliQuery !== '' ? $meiliQuery : $kw);
        if ($fromMeili !== null) {
            return $fromMeili;
        }
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

    private static function queryPlotResources($kw, $meiliQuery = null)
    {
        $meiliPass = ($meiliQuery !== null && trim((string)$meiliQuery) !== '') ? trim((string)$meiliQuery) : $kw;
        $fromMeili = self::queryResourcesByMeilisearch('vod', $meiliPass, 'plot');
        if ($fromMeili !== null) {
            return $fromMeili;
        }
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
