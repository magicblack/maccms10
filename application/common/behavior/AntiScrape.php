<?php
namespace app\common\behavior;

use app\common\util\SlidingWindowIpLimiter;
use think\exception\HttpResponseException;
use think\Request;

/**
 * 防爬虫：开放 API 与前台 Ajax 的 IP 频率限制（验证码仍使用系统原有搜索/筛选验证码配置）。
 */
class AntiScrape
{
    public function run(&$dispatch)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        $req = Request::instance();

        if (defined('ENTRANCE') && ENTRANCE === 'index') {
            $this->runIndexAjax($req, $app);
        }
        if (defined('ENTRANCE') && ENTRANCE === 'api') {
            $this->runApi($req, $app);
        }
    }

    private function runIndexAjax(Request $req, array $app)
    {
        if (empty($app['anti_scrape_index_enabled']) || (string)$app['anti_scrape_index_enabled'] !== '1') {
            return;
        }
        $pi = strtolower(trim((string)$req->pathinfo(), '/'));
        if ($pi !== '' && strpos($pi, 'install') === 0) {
            return;
        }
        list($c, $a) = $this->pathControllerAction($req);
        if ($c !== 'ajax') {
            return;
        }
        $hits = ['suggest', 'data', 'search_hot', 'search_history'];
        if (!in_array($a, $hits, true)) {
            return;
        }
        $window = isset($app['anti_scrape_index_window_sec']) ? (int)$app['anti_scrape_index_window_sec'] : 60;
        $max = isset($app['anti_scrape_index_max']) ? (int)$app['anti_scrape_index_max'] : 90;
        $ip = (string)mac_get_client_ip();
        $r = SlidingWindowIpLimiter::checkHit($ip, 'ix_' . $a, $window, $max);
        if (!$r['allowed']) {
            $this->denyIndex($r['retry_after']);
        }
    }

    private function runApi(Request $req, array $app)
    {
        if (empty($app['anti_scrape_api_enabled']) || (string)$app['anti_scrape_api_enabled'] !== '1') {
            return;
        }
        list($c, $a) = $this->pathControllerAction($req);
        $route = $c . '/' . $a;
        if ($route === '/' || $c === '') {
            return;
        }

        $exempt = ['timming/index'];
        $extra = isset($app['anti_scrape_api_exempt']) ? trim((string)$app['anti_scrape_api_exempt']) : '';
        if ($extra !== '') {
            foreach (explode(',', $extra) as $one) {
                $one = strtolower(trim(str_replace('\\', '/', $one)));
                if ($one !== '') {
                    $exempt[] = $one;
                }
            }
        }
        if (in_array($route, array_unique($exempt), true)) {
            return;
        }

        $ip = (string)mac_get_client_ip();
        $gw = isset($app['anti_scrape_api_window_sec']) ? (int)$app['anti_scrape_api_window_sec'] : 60;
        $gm = isset($app['anti_scrape_api_max']) ? (int)$app['anti_scrape_api_max'] : 120;
        $r1 = SlidingWindowIpLimiter::checkHit($ip, 'api_all', $gw, $gm);
        if (!$r1['allowed']) {
            $this->denyApi($r1['retry_after']);
        }

        if ($this->apiSearchHeavy($req)) {
            $sw = isset($app['anti_scrape_api_search_window_sec']) ? (int)$app['anti_scrape_api_search_window_sec'] : 60;
            $sm = isset($app['anti_scrape_api_search_max']) ? (int)$app['anti_scrape_api_search_max'] : 30;
            $r2 = SlidingWindowIpLimiter::checkHit($ip, 'api_search', $sw, $sm);
            if (!$r2['allowed']) {
                $this->denyApi($r2['retry_after']);
            }
        }
    }

    /**
     * @return array{0:string,1:string} controller, action lower
     */
    private function pathControllerAction(Request $req)
    {
        $pi = strtolower(trim((string)$req->pathinfo(), '/'));
        $pi = preg_replace('/\.(html|htm)$/i', '', $pi);
        if ($pi === '') {
            return ['', ''];
        }
        $parts = array_values(array_filter(explode('/', $pi), static function ($p) {
            return $p !== '';
        }));
        if (count($parts) >= 2) {
            return [strtolower($parts[0]), strtolower($parts[1])];
        }
        if (count($parts) === 1) {
            return [strtolower($parts[0]), 'index'];
        }

        return ['', ''];
    }

    private function apiSearchHeavy(Request $req)
    {
        if (trim((string)$req->param('wd', '')) !== '') {
            return true;
        }
        $keys = [
            'vod_name', 'art_name', 'actor_name', 'website_name', 'manga_name', 'topic_name',
            'vod_actor', 'vod_director', 'vod_blurb', 'art_tag', 'vod_tag',
        ];
        foreach ($keys as $k) {
            if (trim((string)$req->param($k, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function denyApi($retryAfter)
    {
        $retryAfter = max(1, (int)$retryAfter);
        $msg = function_exists('lang')
            ? sprintf(lang('anti_scrape/rate_limited'), $retryAfter)
            : ('Too many requests, retry after ' . $retryAfter . ' seconds');
        throw new HttpResponseException(json([
            'code' => 100429,
            'msg'  => $msg,
            'data' => ['retry_after' => $retryAfter],
        ], 429, ['Retry-After' => (string)$retryAfter]));
    }

    private function denyIndex($retryAfter)
    {
        $retryAfter = max(1, (int)$retryAfter);
        $msg = function_exists('lang')
            ? sprintf(lang('anti_scrape/rate_limited'), $retryAfter)
            : ('Too many requests, retry after ' . $retryAfter . ' seconds');
        throw new HttpResponseException(json([
            'code' => 100429,
            'msg'  => $msg,
            'data' => ['retry_after' => $retryAfter],
        ], 429, ['Retry-After' => (string)$retryAfter]));
    }
}
