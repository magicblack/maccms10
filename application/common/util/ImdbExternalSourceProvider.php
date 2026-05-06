<?php
namespace app\common\util;

class ImdbExternalSourceProvider implements ExternalSourceProviderInterface
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getCode()
    {
        return 'imdb';
    }

    public function getLabel()
    {
        return 'IMDb';
    }

    public function search($keyword, array $options = [])
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return [];
        }
        $limit = max(1, min(20, intval(isset($options['limit']) ? $options['limit'] : 8)));
        $urlTpl = trim((string)$this->get('search_url', 'https://v3.sg.media-imdb.com/suggestion/__prefix__/__query__.json'));
        if ($urlTpl === '') {
            return [];
        }
        $prefix = strtolower(substr($keyword, 0, 1));
        if (!preg_match('/[a-z0-9]/', $prefix)) {
            $prefix = 'x';
        }
        $url = str_replace(
            ['__query__', '__prefix__', '__limit__'],
            [rawurlencode($keyword), $prefix, (string)$limit],
            $urlTpl
        );
        $json = $this->requestJson($url);
        if (!is_array($json) || empty($json['d']) || !is_array($json['d'])) {
            return [];
        }
        return array_slice($this->normalizeRows($json['d']), 0, $limit);
    }

    public function fetchRecent(array $options = [])
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $limit = max(1, min(50, intval(isset($options['limit']) ? $options['limit'] : 20)));
        $seed = trim((string)$this->get('recent_seed_query', 'popular'));
        return $this->search($seed, ['limit' => $limit]);
    }

    private function isEnabled()
    {
        return (string)$this->get('enabled', '0') === '1';
    }

    private function get($key, $default = '')
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    private function requestJson($url)
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: '.trim((string)$this->get('user_agent', 'Mozilla/5.0')),
        ];
        $resp = HttpClient::curlPostWithTimeout($url, '', $headers, 12, false);
        if ($resp === false || $resp === '') {
            return [];
        }
        $json = json_decode((string)$resp, true);
        return is_array($json) ? $json : [];
    }

    private function normalizeRows(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)(isset($row['id']) ? $row['id'] : ''));
            $title = trim((string)(isset($row['l']) ? $row['l'] : ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $year = isset($row['y']) ? (string)$row['y'] : '';
            $cast = isset($row['s']) ? (string)$row['s'] : '';
            $snippet = $cast;
            if ($year !== '') {
                $snippet .= ($snippet === '' ? '' : ' | ') . $year;
            }
            $cover = '';
            if (!empty($row['i']) && is_array($row['i']) && !empty($row['i']['imageUrl'])) {
                $cover = (string)$row['i']['imageUrl'];
            }
            $out[] = [
                'provider_code' => 'imdb',
                'item_key' => $id,
                'item_mid' => 1,
                'item_title' => $title,
                'item_subtitle' => 'IMDb',
                'item_snippet' => $snippet,
                'item_url' => 'https://www.imdb.com/title/'.$id.'/',
                'item_cover' => $cover,
                'item_score' => 0,
                'item_release_date' => $year,
                'item_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ];
        }
        return $out;
    }
}

