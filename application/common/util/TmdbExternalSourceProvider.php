<?php
namespace app\common\util;

class TmdbExternalSourceProvider implements ExternalSourceProviderInterface
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getCode()
    {
        return 'tmdb';
    }

    public function getLabel()
    {
        return 'TMDB';
    }

    public function search($keyword, array $options = [])
    {
        $keyword = trim((string)$keyword);
        if ($keyword === '' || !$this->isEnabled()) {
            return [];
        }
        $limit = max(1, min(20, intval(isset($options['limit']) ? $options['limit'] : 8)));
        $params = [
            'query' => $keyword,
            'page' => 1,
            'include_adult' => 'false',
            'language' => $this->getLanguage(),
            'region' => $this->getRegion(),
        ];
        $rows = $this->request('/search/multi', $params);
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($this->normalizeList($rows), 0, $limit);
    }

    public function fetchRecent(array $options = [])
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $limit = max(1, min(50, intval(isset($options['limit']) ? $options['limit'] : 20)));
        $params = [
            'language' => $this->getLanguage(),
        ];
        $rows = $this->request('/trending/all/day', $params);
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($this->normalizeList($rows), 0, $limit);
    }

    private function isEnabled()
    {
        return (string)$this->get('enabled', '0') === '1' && $this->getApiKey() !== '';
    }

    private function get($key, $default = '')
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    private function getApiKey()
    {
        return trim((string)$this->get('api_key', ''));
    }

    private function getBaseUrl()
    {
        $url = rtrim(trim((string)$this->get('base_url', 'https://api.themoviedb.org/3')), '/');
        return $url === '' ? 'https://api.themoviedb.org/3' : $url;
    }

    private function getImageBaseUrl()
    {
        $url = rtrim(trim((string)$this->get('image_base_url', 'https://image.tmdb.org/t/p/w500')), '/');
        return $url === '' ? 'https://image.tmdb.org/t/p/w500' : $url;
    }

    private function getLanguage()
    {
        $lang = trim((string)$this->get('language', 'zh-CN'));
        return $lang === '' ? 'zh-CN' : $lang;
    }

    private function getRegion()
    {
        return trim((string)$this->get('region', 'CN'));
    }

    private function request($path, array $params)
    {
        $params['api_key'] = $this->getApiKey();
        $url = $this->getBaseUrl() . $path . '?' . http_build_query($params);
        $headers = ['Accept: application/json'];
        $resp = HttpClient::curlPostWithTimeout($url, '', $headers, 10, false);
        if ($resp === false || $resp === '') {
            return [];
        }
        $json = json_decode((string)$resp, true);
        if (!is_array($json) || empty($json['results']) || !is_array($json['results'])) {
            return [];
        }
        return $json['results'];
    }

    private function normalizeList(array $rows)
    {
        $out = [];
        foreach ($rows as $row) {
            $mediaType = strtolower((string)(isset($row['media_type']) ? $row['media_type'] : ''));
            if ($mediaType === 'person') {
                continue;
            }
            $id = intval(isset($row['id']) ? $row['id'] : 0);
            if ($id <= 0) {
                continue;
            }
            $title = trim((string)(isset($row['title']) ? $row['title'] : (isset($row['name']) ? $row['name'] : '')));
            if ($title === '') {
                continue;
            }
            $releaseDate = trim((string)(isset($row['release_date']) ? $row['release_date'] : (isset($row['first_air_date']) ? $row['first_air_date'] : '')));
            $overview = trim((string)(isset($row['overview']) ? $row['overview'] : ''));
            $vote = floatval(isset($row['vote_average']) ? $row['vote_average'] : 0);
            if ($overview === '') {
                $bits = array_filter([
                    $mediaType !== '' ? strtoupper($mediaType) : '',
                    $releaseDate !== '' ? $releaseDate : '',
                    $vote > 0 ? ('TMDB ★ ' . round($vote, 1)) : '',
                ]);
                $overview = implode(' · ', $bits);
            }
            $coverPath = trim((string)(isset($row['poster_path']) ? $row['poster_path'] : ''));
            $cover = $coverPath === '' ? '' : $this->getImageBaseUrl() . $coverPath;
            $itemKey = $mediaType . '_' . $id;
            $out[] = [
                'provider_code' => 'tmdb',
                'item_key' => $itemKey,
                'item_mid' => $mediaType === 'tv' ? 1 : 1,
                'item_title' => $title,
                'item_subtitle' => strtoupper($mediaType === '' ? 'mixed' : $mediaType),
                'item_snippet' => $overview,
                'item_url' => 'https://www.themoviedb.org/' . ($mediaType === 'tv' ? 'tv' : 'movie') . '/' . $id,
                'item_cover' => $cover,
                'item_score' => $vote,
                'item_release_date' => $releaseDate,
                'item_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
            ];
        }
        return $out;
    }
}

