<?php
namespace app\common\behavior;

use app\common\model\AdminAuditLog;
use app\common\util\SensitiveDataCrypto;
use think\Request;
use think\Response;

/**
 * 后台操作审计：在 app_end 写入一条记录（默认仅记录 POST/PUT/PATCH/DELETE）。
 */
class AdminAudit
{
    public function run(&$response)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!defined('ENTRANCE') || ENTRANCE !== 'admin') {
            return;
        }
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];
        if (empty($app['admin_audit_enabled']) || (string)$app['admin_audit_enabled'] !== '1') {
            return;
        }
        if (session('admin_auth') !== '1') {
            return;
        }
        $admin = session('admin_info');
        if (!is_array($admin) || empty($admin['admin_id'])) {
            return;
        }

        $req = Request::instance();
        $method = strtoupper($req->method());
        $logGet = !empty($app['admin_audit_get']) && (string)$app['admin_audit_get'] === '1';
        if (!$logGet && !in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $ctl = strtolower((string)$req->controller());
        $act = strtolower((string)$req->action());
        $route = $ctl . '/' . $act;

        $skip = [
            'index/login',
            'upload/upload',
            'upload/ueditorai',
            'upload/ueditor_ai',
            'assistant/chat',
        ];
        if (in_array($route, $skip, true)) {
            return;
        }
        if ($ctl === 'adminaudit') {
            return;
        }

        $denyContains = self::buildDenyContainsList($app);
        $payload = self::sanitizePayload(array_merge($req->param(), $req->post()), $denyContains);
        $json = '';
        if ($payload !== []) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (strlen($json) > 16384) {
                $json = substr($json, 0, 16300) . '…(truncated)';
            }
            if (!empty($app['admin_audit_encrypt']) && (string)$app['admin_audit_encrypt'] === '1' && $json !== '') {
                $enc = SensitiveDataCrypto::encryptString($json, $app);
                if (SensitiveDataCrypto::isEncryptedPayload($enc)) {
                    $json = $enc;
                }
            }
        }

        $code = ($response instanceof Response) ? (int)$response->getCode() : 0;
        if ($code < 100 || $code > 599) {
            $code = 0;
        }

        AdminAuditLog::insertRow([
            'admin_id'       => (int)$admin['admin_id'],
            'admin_name'     => isset($admin['admin_name']) ? (string)$admin['admin_name'] : '',
            'audit_time'     => time(),
            'audit_ip'       => (string)mac_get_client_ip(),
            'audit_method'   => $method,
            'audit_route'    => $route,
            'audit_uri'      => substr((string)$req->url(true), 0, 2048),
            'audit_http_code'=> $code,
            'audit_payload'  => $json,
        ]);
    }

    /**
     * 内置「参数名包含即脱敏」片段（小写比对）；站长可在 app.admin_audit_extra_redact 中追加，逗号/空格/竖线分隔。
     *
     * @param array<string,mixed> $app
     *
     * @return list<string>
     */
    private static function buildDenyContainsList(array $app)
    {
        $denyContains = [
            'secret', 'apikey', 'api_key', 'token', 'access_key', 'private_key',
        ];
        $extra = isset($app['admin_audit_extra_redact']) ? trim((string)$app['admin_audit_extra_redact']) : '';
        if ($extra !== '') {
            foreach (preg_split('/[\s,|]+/', $extra, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $w = strtolower(trim((string)$word));
                if ($w !== '' && strlen($w) <= 64) {
                    $denyContains[] = $w;
                }
            }
        }
        $denyContains = array_values(array_unique($denyContains));

        return $denyContains;
    }

    /**
     * @param array<string,mixed>        $data
     * @param list<string> $denyContains 小写关键字，参数名 strtolower 后包含即脱敏
     *
     * @return array<string,mixed>
     */
    private static function sanitizePayload(array $data, array $denyContains)
    {
        $denyExact = [
            'admin_pwd', 'user_pwd', 'user_pwd2', 'password', 'verify',
            '__token__', 'user_check', 'admin_check', 'sql',
        ];
        $out = [];
        foreach ($data as $k => $v) {
            $lk = strtolower((string)$k);
            if (in_array($lk, $denyExact, true)
                || substr($lk, -4) === '_pwd'
                || substr($lk, -8) === '_password') {
                $out[$k] = '[redacted]';
                continue;
            }
            foreach ($denyContains as $kw) {
                if ($kw !== '' && strpos($lk, $kw) !== false) {
                    $out[$k] = '[redacted]';
                    continue 2;
                }
            }
            if (is_array($v)) {
                $out[$k] = self::sanitizePayload($v, $denyContains);
            } elseif (is_string($v) && strlen($v) > 2000) {
                $out[$k] = substr($v, 0, 2000) . '…';
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
