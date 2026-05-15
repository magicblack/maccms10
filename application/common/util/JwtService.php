<?php
namespace app\common\util;

use think\Request;

/**
 * HS256 JWT（签名防篡改；payload 为 Base64 非加密，敏感数据勿写入 claims）。
 */
class JwtService
{
    /**
     * 是否配置了足够长的 JWT 对称密钥（启用 JWT 的前置条件）。
     */
    public static function hasStrongSecret()
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        $s = isset($app['api_jwt_secret']) ? trim((string)$app['api_jwt_secret']) : '';

        return strlen($s) >= 32;
    }

    /**
     * 仅返回已配置的强密钥；不再使用 cache_flag/表前缀派生（避免可预测签名密钥）。
     */
    public static function getSecret()
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        $s = isset($app['api_jwt_secret']) ? trim((string)$app['api_jwt_secret']) : '';
        if (strlen($s) >= 32) {
            return $s;
        }

        return '';
    }

    public static function getTtl()
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        $t = isset($app['api_jwt_ttl']) ? (int)$app['api_jwt_ttl'] : 7200;

        return max(300, min(86400 * 30, $t));
    }

    public static function getIss()
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        $iss = isset($app['api_jwt_iss']) ? trim((string)$app['api_jwt_iss']) : '';

        return $iss !== '' ? $iss : 'maccms';
    }

    public static function isEnabled()
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        if (!array_key_exists('api_jwt_enabled', $app)) {
            return false;
        }

        return (string)$app['api_jwt_enabled'] === '1' && self::hasStrongSecret();
    }

    public static function bearerFromRequest(Request $req = null)
    {
        $req = $req ?: Request::instance();
        $h = $req->header('Authorization');
        if ($h === null || $h === '') {
            return '';
        }
        if (preg_match('/^\s*Bearer\s+(\S+)/i', (string)$h, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * @param int    $userId
     * @param string $userRandom 当前 user_random，登出/改密后变更即可使旧令牌失效
     *
     * @return string
     */
    public static function encode($userId, $userRandom)
    {
        $userId = (int)$userId;
        if ($userId < 1 || $userRandom === '') {
            return '';
        }
        if (!self::isEnabled()) {
            return '';
        }
        $secret = self::getSecret();
        if ($secret === '') {
            return '';
        }
        $now = time();
        $payload = [
            'iss' => self::getIss(),
            'iat' => $now,
            'exp' => $now + self::getTtl(),
            'sub' => (string)$userId,
            'rnd' => (string)$userRandom,
        ];
        $head = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_UNESCAPED_SLASHES));
        $body = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = self::b64url(hash_hmac('sha256', $head . '.' . $body, $secret, true));

        return $head . '.' . $body . '.' . $sig;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function decodeAndVerify($jwt)
    {
        if (!self::hasStrongSecret()) {
            return null;
        }
        if (!is_string($jwt) || $jwt === '') {
            return null;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        list($h, $p, $s) = $parts;
        $secret = self::getSecret();
        if ($secret === '') {
            return null;
        }
        $expect = self::b64url(hash_hmac('sha256', $h . '.' . $p, $secret, true));
        if (!hash_equals($expect, $s)) {
            return null;
        }
        $json = self::b64urlDecode($p);
        if ($json === '' || $json === false) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }
        if (empty($payload['exp']) || (int)$payload['exp'] < time()) {
            return null;
        }
        if (empty($payload['sub']) || empty($payload['rnd'])) {
            return null;
        }

        return $payload;
    }

    private static function b64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode($data)
    {
        $b64 = strtr($data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($b64, true);
    }
}
