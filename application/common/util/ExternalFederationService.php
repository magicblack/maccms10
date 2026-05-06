<?php
namespace app\common\util;

class ExternalFederationService
{
    private $repo;
    private $registry;

    public function __construct()
    {
        $this->repo = new ExternalSourceRepository();
        $this->registry = new ExternalSourceProviderRegistry();
    }

    public function searchAndStore($keyword, $module, array $options = [])
    {
        $cfg = $this->getExtConfig();
        if ((string)$cfg['enabled'] !== '1') {
            return [];
        }
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return [];
        }
        $mid = $this->moduleToMid($module);
        $cacheTtl = max(60, intval($cfg['cache_ttl']));
        $limit = max(1, min(12, intval(isset($options['limit']) ? $options['limit'] : $cfg['merge_limit'])));
        $cacheKey = sha1('ext|' . $mid . '|' . mb_strtolower($keyword, 'UTF-8'));

        if ((string)$cfg['use_cache'] === '1') {
            $cached = $this->repo->getSearchCache($cacheKey);
            if (!empty($cached)) {
                return array_slice($cached, 0, $limit);
            }
        }
        if ((string)$cfg['use_live'] !== '1') {
            return [];
        }

        $providers = $this->registry->listEnabledProviders($cfg);
        $out = [];
        foreach ($providers as $code => $provider) {
            $providerConf = isset($cfg['sources'][$code]) && is_array($cfg['sources'][$code]) ? $cfg['sources'][$code] : [];
            $this->repo->saveProviderSnapshot($code, $provider->getLabel(), $providerConf);
            $rows = $provider->search($keyword, ['limit' => $limit]);
            if (empty($rows)) {
                continue;
            }
            $this->repo->saveItems($code, $rows);
            foreach ($rows as $row) {
                $title = trim((string)(isset($row['item_title']) ? $row['item_title'] : ''));
                $url = trim((string)(isset($row['item_url']) ? $row['item_url'] : ''));
                if ($title === '' || $url === '') {
                    continue;
                }
                $out[] = [
                    'title' => $title,
                    'url' => $url,
                    'snippet' => $this->composePublicSnippet($row),
                    'img' => (string)(isset($row['item_cover']) ? $row['item_cover'] : ''),
                    'source' => 'external',
                    'provider' => $code,
                    'source_type' => 'database',
                    'resource_links' => $this->buildResourceLinks($row),
                ];
            }
        }
        $out = $this->dedupeByUrl($out);
        if (!empty($out)) {
            $this->repo->saveSearchCache($cacheKey, $keyword, $mid, 'mixed', $out, $cacheTtl);
        }
        return array_slice($out, 0, $limit);
    }

    private function getExtConfig()
    {
        $cfg = config('maccms.ai_search');
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $ext = isset($cfg['external_sources']) && is_array($cfg['external_sources']) ? $cfg['external_sources'] : [];
        if (!isset($ext['sources']) || !is_array($ext['sources'])) {
            $ext['sources'] = [];
        }
        $ext = array_merge([
            'enabled' => '0',
            'use_live' => '1',
            'use_cache' => '1',
            'cache_ttl' => '21600',
            'merge_limit' => '4',
            'sync_interval' => '21600',
        ], $ext);
        if (!isset($ext['sources']['tmdb']) || !is_array($ext['sources']['tmdb'])) {
            $ext['sources']['tmdb'] = [];
        }
        $ext['sources']['tmdb'] = array_merge([
            'enabled' => '0',
            'api_key' => '',
            'base_url' => 'https://api.themoviedb.org/3',
            'image_base_url' => 'https://image.tmdb.org/t/p/w500',
            'language' => 'zh-CN',
            'region' => 'CN',
        ], $ext['sources']['tmdb']);
        if (!isset($ext['sources']['douban']) || !is_array($ext['sources']['douban'])) {
            $ext['sources']['douban'] = [];
        }
        $ext['sources']['douban'] = array_merge([
            'enabled' => '0',
            'search_url' => 'https://movie.douban.com/j/subject_suggest?q=__query__',
            'recent_url' => 'https://movie.douban.com/j/search_subjects?type=movie&tag=%E7%83%AD%E9%97%A8&page_limit=__limit__&page_start=0',
            'referer' => 'https://movie.douban.com/',
            'user_agent' => 'Mozilla/5.0',
        ], $ext['sources']['douban']);
        if (!isset($ext['sources']['imdb']) || !is_array($ext['sources']['imdb'])) {
            $ext['sources']['imdb'] = [];
        }
        $ext['sources']['imdb'] = array_merge([
            'enabled' => '0',
            'api_key' => '',
            'search_url' => 'https://v3.sg.media-imdb.com/suggestion/__prefix__/__query__.json',
            'recent_seed_query' => 'popular',
            'user_agent' => 'Mozilla/5.0',
        ], $ext['sources']['imdb']);
        return $ext;
    }

    /**
     * Short description for UI: prefer provider snippet, else subtitle/date/score/host.
     */
    private function composePublicSnippet(array $row)
    {
        $s = trim(strip_tags((string)(isset($row['item_snippet']) ? $row['item_snippet'] : '')));
        if ($s !== '') {
            return $this->truncateSnippetText($s, 360);
        }
        $parts = [];
        $sub = trim((string)(isset($row['item_subtitle']) ? $row['item_subtitle'] : ''));
        if ($sub !== '') {
            $parts[] = $sub;
        }
        $y = trim((string)(isset($row['item_release_date']) ? $row['item_release_date'] : ''));
        if ($y !== '') {
            $parts[] = $y;
        }
        $score = floatval(isset($row['item_score']) ? $row['item_score'] : 0);
        if ($score > 0) {
            $parts[] = '★ ' . round($score, 1);
        }
        $url = trim((string)(isset($row['item_url']) ? $row['item_url'] : ''));
        if ($parts === [] && $url !== '') {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host !== null && $host !== '') {
                $parts[] = (string)$host;
            }
        }
        $out = implode(' · ', array_filter($parts));
        return $this->truncateSnippetText($out, 360);
    }

    private function truncateSnippetText($text, $max)
    {
        $text = (string)$text;
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        return mb_substr($text, 0, max(1, $max - 1), 'UTF-8') . '…';
    }

    private function moduleToMid($module)
    {
        $map = ['vod' => 1, 'art' => 2, 'topic' => 3, 'actor' => 8, 'role' => 9, 'website' => 11, 'plot' => 12];
        return isset($map[$module]) ? $map[$module] : 0;
    }

    private function dedupeByUrl(array $items)
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $url = strtolower(trim((string)$item['url']));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = 1;
            $out[] = $item;
        }
        return $out;
    }

    private function buildResourceLinks(array $row)
    {
        $links = [];
        $mainUrl = trim((string)(isset($row['item_url']) ? $row['item_url'] : ''));
        if ($mainUrl !== '') {
            $links[] = ['title' => $this->localizeLinkTitle('detail'), 'url' => $mainUrl];
        }
        $payload = [];
        if (!empty($row['item_payload'])) {
            $decoded = json_decode((string)$row['item_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        foreach (['share_url', 'alt', 'mobile_url', 'url'] as $key) {
            if (empty($payload[$key])) {
                continue;
            }
            $u = trim((string)$payload[$key]);
            if ($u === '' || $u === $mainUrl) {
                continue;
            }
            $links[] = ['title' => $this->localizeLinkTitle($key), 'url' => $u];
        }
        return array_slice($links, 0, 4);
    }

    private function localizeLinkTitle($key)
    {
        $lang = strtolower((string)config('maccms.app.lang'));
        $isZh = strpos($lang, 'zh') === 0;
        $mapZh = [
            'detail' => '详情',
            'share_url' => '分享页',
            'alt' => '备用页',
            'mobile_url' => '移动端',
            'url' => '链接',
        ];
        $mapEn = [
            'detail' => 'Detail',
            'share_url' => 'Share',
            'alt' => 'Alt',
            'mobile_url' => 'Mobile',
            'url' => 'Link',
        ];
        $key = strtolower(trim((string)$key));
        if ($isZh) {
            return isset($mapZh[$key]) ? $mapZh[$key] : '链接';
        }
        return isset($mapEn[$key]) ? $mapEn[$key] : 'Link';
    }
}

