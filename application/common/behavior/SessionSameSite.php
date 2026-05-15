<?php
namespace app\common\behavior;

/**
 * 在 Session 真正启动前应用 PHP 7.3+ 的 SameSite Cookie 参数（读取 config.session.samesite）。
 * 逻辑从内核迁出，避免改动 thinkphp/library/think/Session.php。
 */
class SessionSameSite
{
    public function run(&$params)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (PHP_VERSION_ID < 70300) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = function_exists('config') ? config('session') : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $ss = isset($cfg['samesite']) ? trim((string)$cfg['samesite']) : '';
        if ($ss === '' || $ss === '0') {
            return;
        }

        $lifetime = 0;
        if (isset($cfg['expire'])) {
            $lifetime = (int)$cfg['expire'];
        }
        if ($lifetime < 1) {
            $lifetime = (int)ini_get('session.cookie_lifetime');
        }

        $path = '/';
        $p = ini_get('session.cookie_path');
        if ($p !== false && $p !== '') {
            $path = (string)$p;
        }

        $domain = '';
        if (isset($cfg['domain'])) {
            $domain = (string)$cfg['domain'];
        } else {
            $d = ini_get('session.cookie_domain');
            if ($d !== false && $d !== '') {
                $domain = (string)$d;
            }
        }

        $secure = isset($cfg['secure']) ? (bool)$cfg['secure'] : (bool)ini_get('session.cookie_secure');
        $httponly = array_key_exists('httponly', $cfg) ? (bool)$cfg['httponly'] : (bool)ini_get('session.cookie_httponly');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $ss,
        ]);
    }
}
