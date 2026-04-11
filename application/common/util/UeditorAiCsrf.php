<?php
namespace app\common\util;

/**
 * Session CSRF for UEditor AI proxy (same idea as addons\aicontent\Aicontent::generateCsrfToken).
 */
class UeditorAiCsrf
{
    private const SESSION_KEY = 'ueditor_ai_csrf_token';

    public static function token(): string
    {
        $t = session(self::SESSION_KEY);
        if ($t === null || $t === '') {
            $t = bin2hex(random_bytes(16));
            session(self::SESSION_KEY, $t);
        }

        $t = (string) $t;
        /* 对话框与内容页不同 window 时 Cookie + 服务端比对；HTTPS 下需 Secure 否则部分浏览器不发送 */
        if (!headers_sent()) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
            if (\PHP_VERSION_ID >= 70300) {
                setcookie('ueditor_ai_csrf', $t, [
                    'expires'  => time() + 7200,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('ueditor_ai_csrf', $t, time() + 7200, '/', '', $secure, false);
            }
        }

        return $t;
    }

    /**
     * 同时接受 JSON 内 _csrf_token 与 Cookie ueditor_ai_csrf（任一与 session 一致即通过），避免 body 陈旧而 Cookie 已刷新。
     */
    public static function validate($submitted): bool
    {
        $expected = session(self::SESSION_KEY);
        if ($expected === null || $expected === '') {
            return false;
        }
        $expected = (string) $expected;
        $fromBody = is_string($submitted) ? $submitted : '';
        $fromCookie = (!empty($_COOKIE['ueditor_ai_csrf']) && is_string($_COOKIE['ueditor_ai_csrf']))
            ? (string) $_COOKIE['ueditor_ai_csrf']
            : '';
        foreach ([$fromBody, $fromCookie] as $cand) {
            if ($cand === '') {
                continue;
            }
            if (strlen($expected) === strlen($cand) && hash_equals($expected, $cand)) {
                return true;
            }
        }

        return false;
    }
}
