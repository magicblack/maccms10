<?php
namespace app\common\behavior;

use app\common\util\RequestXssSanitizer;

/**
 * 请求 XSS 过滤：在 app_init 中于 Init 之后执行，确保在路由 / 调试日志合并参数前清洗 $_GET、$_POST。
 */
class RequestSecurity
{
    public function run(&$params)
    {
        $app = isset($GLOBALS['config']['app']) && is_array($GLOBALS['config']['app'])
            ? $GLOBALS['config']['app']
            : [];

        if (empty($app['security_xss_input']) || (string)$app['security_xss_input'] === '0') {
            return;
        }

        if (defined('ENTRANCE')) {
            if (ENTRANCE === 'install') {
                return;
            }
            if (ENTRANCE === 'admin') {
                $adminOn = isset($app['security_xss_admin']) && (string)$app['security_xss_admin'] === '1';
                if (!$adminOn) {
                    return;
                }
            }
        }

        $skipJson = !isset($app['security_xss_skip_json']) || (string)$app['security_xss_skip_json'] !== '0';
        if ($skipJson && !empty($_SERVER['CONTENT_TYPE'])
            && stripos((string)$_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $_GET = RequestXssSanitizer::sanitizeDeep($_GET);

            return;
        }

        $_GET  = RequestXssSanitizer::sanitizeDeep($_GET);
        $_POST = RequestXssSanitizer::sanitizeDeep($_POST);
    }
}
