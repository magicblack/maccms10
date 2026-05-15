<?php
namespace app\common\util;

/**
 * 按 IP 的滑动窗口请求计数（文件 + flock），用于防爬虫/API 滥用。
 */
class SlidingWindowIpLimiter
{
    /**
     * @param string $ip
     * @param string $scope  逻辑名，仅允许 [a-z0-9_]+
     * @param int    $windowSec 窗口秒数
     * @param int    $maxHits   窗口内最大命中次数
     * @param string $runtimeSubdir RUNTIME_PATH 下子目录名
     *
     * @return array{allowed:bool,retry_after:int}
     */
    public static function checkHit($ip, $scope, $windowSec, $maxHits, $runtimeSubdir = 'anti_scrape_rl')
    {
        $windowSec = max(5, (int)$windowSec);
        $maxHits = max(1, (int)$maxHits);
        $scope = strtolower(preg_replace('/[^a-z0-9_]+/', '_', (string)$scope));
        if ($scope === '') {
            $scope = 'default';
        }

        $dir = rtrim(RUNTIME_PATH, '/\\') . DIRECTORY_SEPARATOR . $runtimeSubdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $scope . '_' . self::ipFileKey($ip) . '.json';
        $now = time();

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['allowed' => true, 'retry_after' => 0];
        }

        rewind($fp);
        $raw = stream_get_contents($fp);
        $hits = json_decode((string)$raw, true);
        if (!is_array($hits)) {
            $hits = [];
        }

        $hits = array_values(array_filter($hits, function ($t) use ($now, $windowSec) {
            return ($now - (int)$t) < $windowSec;
        }));

        if (count($hits) >= $maxHits) {
            $oldest = (int)min($hits);
            $retry = max(1, $windowSec - ($now - $oldest));
            flock($fp, LOCK_UN);
            fclose($fp);
            return ['allowed' => false, 'retry_after' => $retry];
        }

        $hits[] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return ['allowed' => true, 'retry_after' => 0];
    }

    private static function ipFileKey($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '' || strlen($ip) > 64) {
            $ip = 'unknown';
        }

        return hash('sha256', $ip);
    }
}
