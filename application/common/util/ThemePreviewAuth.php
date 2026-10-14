<?php
namespace app\common\util;

/**
 * 主题设计预览 URL 鉴权（HMAC + 过期时间）。
 * 前台 ?td_preview=1 须附带有效 td_pt，或同源已登录后台管理员会话（见 verifyAdminSessionPreview）。
 */
class ThemePreviewAuth
{
    const DEFAULT_TTL = 7200;

    /**
     * 签发预览 token（格式 exp.theme.sig）。
     *
     * @param string $themeDir
     * @param int|null $ttl
     * @return string
     */
    public static function issueToken($themeDir, $ttl = null)
    {
        $themeDir = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $themeDir);
        if ($themeDir === '') {
            return '';
        }
        $ttl = $ttl === null ? self::DEFAULT_TTL : (int) $ttl;
        $ttl = max(300, min(86400, $ttl));
        $exp = time() + $ttl;
        $payload = $exp . '.' . $themeDir;
        $sig = substr(hash_hmac('sha256', $payload, self::secret()), 0, 32);

        return $payload . '.' . $sig;
    }

    /**
     * 校验 token；$themeDir 非空时须与 token 内主题一致。
     *
     * @param string $token
     * @param string|null $themeDir
     * @return bool
     */
    public static function verifyToken($token, $themeDir = null)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $parts = explode('.', $token, 3);
        if (count($parts) !== 3) {
            return false;
        }
        $exp = $parts[0];
        $theme = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $parts[1]);
        $sig = (string) $parts[2];
        if (!ctype_digit($exp) || (int) $exp < time() || $theme === '' || strlen($sig) !== 32) {
            return false;
        }
        if ($themeDir !== null && $themeDir !== '') {
            $expect = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $themeDir);
            if ($expect !== '' && $theme !== $expect) {
                return false;
            }
        }
        $payload = $exp . '.' . $theme;
        $expectSig = substr(hash_hmac('sha256', $payload, self::secret()), 0, 32);

        return hash_equals($expectSig, $sig);
    }

    /**
     * 已登录后台管理员在同源前台预览：td_preview=1 + td_theme 即可（iframe 共享 PHPSESSID）。
     *
     * @param string|null $themeDir
     * @return bool
     */
    public static function verifyAdminSessionPreview($themeDir = null)
    {
        if (empty($_GET['td_preview'])) {
            return false;
        }
        if (!function_exists('session') || session('admin_auth') !== '1') {
            return false;
        }
        $theme = isset($_GET['td_theme']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['td_theme']) : '';
        if ($theme === '') {
            return false;
        }
        if ($themeDir !== null && $themeDir !== '') {
            $expect = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $themeDir);
            if ($expect !== '' && $theme !== $expect) {
                return false;
            }
        }

        return true;
    }

    /**
     * 当前请求是否通过预览鉴权（td_preview=1 且 td_pt 有效，或后台管理员会话）。
     *
     * @param string|null $themeDir
     * @return bool
     */
    public static function verifyRequest($themeDir = null)
    {
        if (empty($_GET['td_preview'])) {
            return false;
        }
        if ($themeDir === null && isset($_GET['td_theme'])) {
            $themeDir = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['td_theme']);
        }
        if (self::verifyAdminSessionPreview($themeDir)) {
            return true;
        }
        $token = isset($_GET['td_pt']) ? (string) $_GET['td_pt'] : '';

        return self::verifyToken($token, $themeDir);
    }

    /**
     * 是否处于主题设计预览模式（与 mac_td_preview_mode 等价）。
     *
     * @return bool
     */
    public static function isActive()
    {
        static $on = null;
        if ($on !== null) {
            return $on;
        }
        $on = false;
        if (function_exists('request')) {
            $req = request();
            if (is_object($req) && !empty($req->param('td_preview'))) {
                $on = self::verifyRequest();
            }
        } elseif (!empty($_GET['td_preview'])) {
            $on = self::verifyRequest();
        }

        return $on;
    }

    /**
     * 为 URL 追加 td_preview / td_theme / td_pt 查询参数。
     *
     * @param string $url
     * @param string $themeDir
     * @param int|null $ttl
     * @return string
     */
    public static function appendQuery($url, $themeDir, $ttl = null)
    {
        $themeDir = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $themeDir);
        if ($themeDir === '') {
            return (string) $url;
        }
        $token = self::issueToken($themeDir, $ttl);
        if ($token === '') {
            return (string) $url;
        }
        $sep = strpos($url, '?') === false ? '?' : '&';

        return (string) $url . $sep . 'td_preview=1&td_theme=' . urlencode($themeDir) . '&td_pt=' . urlencode($token);
    }

    /**
     * @return string
     */
    private static function secret()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        // 须用 config()：Init 在 $GLOBALS['config'] 赋值前就会 verifyRequest()，
        // 若只读 JwtService/$GLOBALS，线上有 api_jwt_secret 时会签发用 JWT、校验走库哈希，必失败。
        $jwt = '';
        if (function_exists('config')) {
            $app = config('maccms.app');
            if (is_array($app) && isset($app['api_jwt_secret'])) {
                $jwt = trim((string) $app['api_jwt_secret']);
            }
        }
        if (strlen($jwt) < 32 && class_exists('\app\common\util\JwtService')) {
            $jwt = JwtService::getSecret();
        }
        if (strlen($jwt) >= 32) {
            $cached = 'td_preview|' . $jwt;
            return $cached;
        }
        $db = array();
        if (function_exists('config')) {
            $cfg = \config('database');
            if (is_array($cfg)) {
                $db = $cfg;
            }
        } elseif (defined('ROOT_PATH') && is_file(ROOT_PATH . 'application/database.php')) {
            $cfg = include ROOT_PATH . 'application/database.php';
            if (is_array($cfg)) {
                $db = $cfg;
            }
        }
        $parts = array(
            defined('ROOT_PATH') ? ROOT_PATH : '',
            isset($db['hostname']) ? (string) $db['hostname'] : '',
            isset($db['database']) ? (string) $db['database'] : '',
            isset($db['password']) ? (string) $db['password'] : '',
            isset($db['prefix']) ? (string) $db['prefix'] : '',
        );
        $cached = hash('sha256', implode('|', $parts) . '|maccms_td_preview_v1');

        return $cached;
    }
}
