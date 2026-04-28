<?php
namespace app\common\util;

/**
 * Sliding-window request limiter per client IP for AI chat (file + flock).
 */
class AiChatRateLimit
{
    /**
     * @param string $ip
     * @param array $cfg maccms.ai_search subset
     * @return array{allowed:bool,retry_after:int}
     */
    public static function checkHit($ip, array $cfg)
    {
        $enabled = isset($cfg['rate_limit_enabled']) ? (string)$cfg['rate_limit_enabled'] : '1';
        if ($enabled !== '1') {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $window = max(10, intval(isset($cfg['rate_limit_window']) ? $cfg['rate_limit_window'] : 60));
        $maxReq = max(1, intval(isset($cfg['rate_limit_max']) ? $cfg['rate_limit_max'] : 20));

        $dir = RUNTIME_PATH . 'ai_chat_rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . self::ipFileKey($ip) . '.json';
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

        $hits = array_values(array_filter($hits, function ($t) use ($now, $window) {
            return ($now - (int)$t) < $window;
        }));

        if (count($hits) >= $maxReq) {
            $oldest = (int)min($hits);
            $retry = max(1, $window - ($now - $oldest));
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
