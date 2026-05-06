<?php
namespace app\common\util;

/**
 * Manga (AniList GraphQL) and books (Google Books REST) links for AI chat related links.
 */
class AiCatalogLinkFetcher
{
    /**
     * @param string $keyword
     * @param array  $aiCfg config('maccms.ai_search')
     * @return array<int, array<string, string>>
     */
    public static function fetchAll($keyword, array $aiCfg)
    {
        $keyword = trim((string)$keyword);
        if ($keyword === '' || !is_array($aiCfg)) {
            return [];
        }
        $timeout = max(3, intval(isset($aiCfg['timeout']) ? $aiCfg['timeout'] : 12));
        $per = max(1, min(8, intval(isset($aiCfg['catalog_per_source']) ? $aiCfg['catalog_per_source'] : 3)));

        $out = [];
        if ((string)(isset($aiCfg['anilist_enabled']) ? $aiCfg['anilist_enabled'] : '0') === '1') {
            $out = array_merge($out, self::fetchAnilistManga($keyword, $per, $timeout));
        }
        if ((string)(isset($aiCfg['google_books_enabled']) ? $aiCfg['google_books_enabled'] : '0') === '1') {
            $key = trim((string)(isset($aiCfg['google_books_api_key']) ? $aiCfg['google_books_api_key'] : ''));
            if ($key !== '') {
                $out = array_merge($out, self::fetchGoogleBooks($keyword, $key, $per, $timeout));
            }
        }
        return self::dedupeByUrl($out);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function fetchAnilistManga($keyword, $limit, $timeout)
    {
        $query = 'query ($search: String, $perPage: Int) {
  Page(page: 1, perPage: $perPage) {
    media(search: $search, type: MANGA, sort: SEARCH_MATCH) {
      id
      siteUrl
      title { romaji english native userPreferred }
    }
  }
}';
        $body = json_encode([
            'query' => $query,
            'variables' => [
                'search' => $keyword,
                'perPage' => $limit,
            ],
        ], JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $resp = HttpClient::curlPostWithTimeout(
            'https://graphql.anilist.co',
            $body,
            $headers,
            $timeout,
            true
        );
        if ($resp === false || $resp === '') {
            return [];
        }
        $json = json_decode((string)$resp, true);
        if (!is_array($json) || !empty($json['errors'])) {
            return [];
        }
        $media = [];
        if (!empty($json['data']['Page']['media']) && is_array($json['data']['Page']['media'])) {
            $media = $json['data']['Page']['media'];
        }
        $links = [];
        foreach ($media as $m) {
            if (!is_array($m)) {
                continue;
            }
            $url = isset($m['siteUrl']) ? trim((string)$m['siteUrl']) : '';
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $title = self::anilistPickTitle($m);
            if ($title === '') {
                $title = lang('index/catalog_manga_fallback', [strval(intval(isset($m['id']) ? $m['id'] : 0))]);
            }
            $links[] = [
                'title' => lang('index/catalog_link_anilist', [$title]),
                'url' => $url,
                'source' => 'external',
                'provider' => 'anilist',
            ];
            if (count($links) >= $limit) {
                break;
            }
        }
        return $links;
    }

    private static function anilistPickTitle(array $m)
    {
        $t = isset($m['title']) && is_array($m['title']) ? $m['title'] : [];
        foreach (['native', 'english', 'romaji', 'userPreferred'] as $k) {
            if (!empty($t[$k]) && trim((string)$t[$k]) !== '') {
                return trim((string)$t[$k]);
            }
        }
        return '';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function fetchGoogleBooks($keyword, $apiKey, $limit, $timeout)
    {
        $q = http_build_query([
            'q' => $keyword,
            'maxResults' => $limit,
            'key' => $apiKey,
        ], '', '&', PHP_QUERY_RFC3986);
        $url = 'https://www.googleapis.com/books/v1/volumes?'.$q;
        $headers = ['Accept: application/json'];
        $resp = HttpClient::curlPostWithTimeout($url, '', $headers, $timeout, false);
        if ($resp === false || $resp === '') {
            return [];
        }
        $json = json_decode((string)$resp, true);
        if (!is_array($json) || empty($json['items']) || !is_array($json['items'])) {
            return [];
        }
        $links = [];
        foreach ($json['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $info = isset($item['volumeInfo']) && is_array($item['volumeInfo']) ? $item['volumeInfo'] : [];
            $title = isset($info['title']) ? trim((string)$info['title']) : '';
            $link = '';
            if (!empty($info['infoLink'])) {
                $link = trim((string)$info['infoLink']);
            } elseif (!empty($item['selfLink'])) {
                $link = trim((string)$item['selfLink']);
            }
            if ($title === '' || $link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            $links[] = [
                'title' => lang('index/catalog_link_google_books', [$title]),
                'url' => $link,
                'source' => 'external',
                'provider' => 'google_books',
            ];
            if (count($links) >= $limit) {
                break;
            }
        }
        return $links;
    }

    private static function dedupeByUrl(array $rows)
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['url'])) {
                continue;
            }
            $k = strtolower(trim((string)$row['url']));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = 1;
            $out[] = $row;
        }
        return $out;
    }
}
