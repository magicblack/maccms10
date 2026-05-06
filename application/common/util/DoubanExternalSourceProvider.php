<?php
namespace app\common\util;

class DoubanExternalSourceProvider implements ExternalSourceProviderInterface
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getCode()
    {
        return 'douban';
    }

    public function getLabel()
    {
        return 'Douban';
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
        $urlTpl = trim((string)$this->get('search_url', 'https://movie.douban.com/j/subject_suggest?q=__query__'));
        if ($urlTpl === '') {
            return [];
        }
        $url = str_replace(
            ['{query}', '{limit}', '__query__', '__limit__'],
            [rawurlencode($keyword), (string)$limit, rawurlencode($keyword), (string)$limit],
            $urlTpl
        );
        $json = $this->requestJson($url);
        if (empty($json)) {
            return [];
        }
        return array_slice($this->normalizeSearchRows($json), 0, $limit);
    }

    public function fetchRecent(array $options = [])
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $limit = max(1, min(50, intval(isset($options['limit']) ? $options['limit'] : 20)));
        $urlTpl = trim((string)$this->get('recent_url', 'https://movie.douban.com/j/search_subjects?type=movie&tag=%E7%83%AD%E9%97%A8&page_limit=__limit__&page_start=0'));
        if ($urlTpl === '') {
            return [];
        }
        $url = str_replace(['{limit}', '__limit__'], (string)$limit, $urlTpl);
        $json = $this->requestJson($url);
        if (!is_array($json)) {
            return [];
        }
        $rows = isset($json['subjects']) && is_array($json['subjects']) ? $json['subjects'] : [];
        return array_slice($this->normalizeRecentRows($rows), 0, $limit);
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
            'Referer: '.trim((string)$this->get('referer', 'https://movie.douban.com/')),
            'User-Agent: '.trim((string)$this->get('user_agent', 'Mozilla/5.0')),
        ];
        $resp = HttpClient::curlPostWithTimeout($url, '', $headers, 12, false);
        if ($resp === false || $resp === '') {
            return [];
        }
        $json = json_decode((string)$resp, true);
        return is_array($json) ? $json : [];
    }

    private function normalizeSearchRows(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)(isset($row['id']) ? $row['id'] : ''));
            $title = trim((string)(isset($row['title']) ? $row['title'] : ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $url = trim((string)(isset($row['url']) ? $row['url'] : ''));
            if ($url === '') {
                $url = 'https://movie.douban.com/subject/'.$id.'/';
            }
            $subTitle = trim((string)(isset($row['sub_title']) ? $row['sub_title'] : ''));
            $year = trim((string)(isset($row['year']) ? $row['year'] : ''));
            $rate = floatval(isset($row['rate']) ? $row['rate'] : 0);
            $snippet = $subTitle;
            if ($year !== '') {
                $snippet .= ($snippet === '' ? '' : ' | ') . $year;
            }
            if ($rate > 0) {
                $snippet .= ($snippet === '' ? '' : ' | ') . '★ ' . round($rate, 1);
            }
            $out[] = [
                'provider_code' => 'douban',
                'item_key' => 'subject_'.$id,
                'item_mid' => 1,
                'item_title' => $title,
                'item_subtitle' => $subTitle,
                'item_snippet' => $snippet,
                'item_url' => $url,
                'item_cover' => (string)(isset($row['cover']) ? $row['cover'] : ''),
                'item_score' => $rate,
                'item_release_date' => $year,
                'item_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ];
        }
        return $out;
    }

    private function normalizeRecentRows(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string)(isset($row['id']) ? $row['id'] : ''));
            $title = trim((string)(isset($row['title']) ? $row['title'] : ''));
            $url = trim((string)(isset($row['url']) ? $row['url'] : ''));
            if ($id === '' || $title === '') {
                continue;
            }
            if ($url === '') {
                $url = 'https://movie.douban.com/subject/'.$id.'/';
            }
            $rate = floatval(isset($row['rate']) ? $row['rate'] : 0);
            $snippet = '';
            if ($rate > 0) {
                $snippet = '★ ' . round($rate, 1);
            }
            $out[] = [
                'provider_code' => 'douban',
                'item_key' => 'subject_'.$id,
                'item_mid' => 1,
                'item_title' => $title,
                'item_subtitle' => 'Douban',
                'item_snippet' => $snippet,
                'item_url' => $url,
                'item_cover' => (string)(isset($row['cover']) ? $row['cover'] : ''),
                'item_score' => $rate,
                'item_release_date' => '',
                'item_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ];
        }
        return $out;
    }
}

