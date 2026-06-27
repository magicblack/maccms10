<?php
namespace app\common\util;

/**
 * 后台「安全体检」：检测安全开关、cache_flag、crypto_secret、site_url 等项。
 */
class SecurityHealthCheck
{
    const WEAK_CACHE_FLAGS = ['', 'maccms', 'mac'];

    /**
     * @return array{items: list<array<string,mixed>>, summary: array<string,mixed>}
     */
    public static function run(array $config = null)
    {
        if ($config === null) {
            $config = function_exists('config') ? config('maccms') : [];
        }
        if (!is_array($config)) {
            $config = [];
        }
        $app = isset($config['app']) && is_array($config['app']) ? $config['app'] : [];
        $site = isset($config['site']) && is_array($config['site']) ? $config['site'] : [];

        $items = [
            self::checkToggle($app, 'security_csrf_admin', 'red', 'enable_csrf'),
            self::checkToggle($app, 'admin_audit_enabled', 'yellow', 'enable_audit'),
            self::checkToggle($app, 'anti_scrape_api_enabled', 'yellow', 'enable_anti_scrape_api'),
            self::checkToggle($app, 'anti_scrape_index_enabled', 'yellow', 'enable_anti_scrape_index'),
            self::checkToggle($app, 'security_headers_base', 'yellow', 'enable_headers'),
            self::checkCacheFlag($app),
            self::checkCryptoSecret($app),
            self::checkSiteUrl($site),
        ];

        return [
            'items'   => $items,
            'summary' => self::summarize($items),
        ];
    }

    /**
     * @param array<string,mixed> $app
     */
    protected static function checkToggle(array $app, $key, $failLevel, $fixAction)
    {
        $enabled = isset($app[$key]) && (string)$app[$key] === '1';
        $langKey = 'admin/safety/checkup/item_' . $key;

        return [
            'id'          => $key,
            'level'       => $enabled ? 'green' : $failLevel,
            'title'       => lang($langKey),
            'detail'      => lang($enabled ? 'admin/safety/checkup/status_on' : 'admin/safety/checkup/status_off'),
            'suggestion'  => $enabled ? '' : lang('admin/safety/checkup/suggest_' . $key),
            'fix_action'  => $enabled ? '' : $fixAction,
            'manual_url'  => '',
        ];
    }

    /**
     * @param array<string,mixed> $app
     */
    protected static function checkCacheFlag(array $app)
    {
        $flag = isset($app['cache_flag']) ? strtolower(trim((string)$app['cache_flag'])) : '';
        $weak = in_array($flag, self::WEAK_CACHE_FLAGS, true);

        return [
            'id'          => 'cache_flag',
            'level'       => $weak ? 'yellow' : 'green',
            'title'       => lang('admin/safety/checkup/item_cache_flag'),
            'detail'      => $flag === '' ? lang('admin/safety/checkup/cache_flag_empty') : $flag,
            'suggestion'  => $weak ? lang('admin/safety/checkup/suggest_cache_flag') : lang('admin/safety/checkup/cache_flag_ok'),
            'fix_action'  => $weak ? 'regenerate_cache_flag' : '',
            'manual_url'  => $weak ? url('system/config') : '',
        ];
    }

    /**
     * @param array<string,mixed> $app
     */
    protected static function checkCryptoSecret(array $app)
    {
        $secret = isset($app['admin_audit_crypto_secret']) ? trim((string)$app['admin_audit_crypto_secret']) : '';
        $auditOn = isset($app['admin_audit_enabled']) && (string)$app['admin_audit_enabled'] === '1';
        $encryptOn = isset($app['admin_audit_encrypt']) && (string)$app['admin_audit_encrypt'] === '1';

        if ($secret !== '') {
            $level = 'green';
        } elseif ($auditOn && $encryptOn) {
            $level = 'red';
        } elseif ($auditOn) {
            $level = 'yellow';
        } else {
            $level = 'yellow';
        }

        return [
            'id'          => 'admin_audit_crypto_secret',
            'level'       => $level,
            'title'       => lang('admin/safety/checkup/item_crypto_secret'),
            'detail'      => $secret !== '' ? lang('admin/safety/checkup/crypto_configured') : lang('admin/safety/checkup/crypto_missing'),
            'suggestion'  => $secret !== '' ? lang('admin/safety/checkup/crypto_ok') : lang('admin/safety/checkup/suggest_crypto_secret'),
            'fix_action'  => $secret === '' ? 'generate_crypto_secret' : '',
            'manual_url'  => url('system/config'),
        ];
    }

    /**
     * @param array<string,mixed> $site
     */
    protected static function checkSiteUrl(array $site)
    {
        $url = isset($site['site_url']) ? trim((string)$site['site_url']) : '';
        $local = self::isLocalSiteUrl($url);

        return [
            'id'          => 'site_url',
            'level'       => $local ? 'red' : ($url === '' ? 'yellow' : 'green'),
            'title'       => lang('admin/safety/checkup/item_site_url'),
            'detail'      => $url === '' ? lang('admin/safety/checkup/site_url_empty') : $url,
            'suggestion'  => $local ? lang('admin/safety/checkup/suggest_site_url') : lang('admin/safety/checkup/site_url_ok'),
            'fix_action'  => '',
            'manual_url'  => $local || $url === '' ? url('system/config') : '',
        ];
    }

    public static function isLocalSiteUrl($url)
    {
        $url = strtolower(trim((string)$url));
        if ($url === '') {
            return false;
        }
        $host = $url;
        if (strpos($host, '://') !== false) {
            $parsed = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsed) ? $parsed : $host;
        }
        $host = preg_replace('#/.*$#', '', $host);
        $host = preg_replace('#:\d+$#', '', $host);
        if ($host === '') {
            return false;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return true;
        }
        if (preg_match('/\.(local|localhost|test|lan|internal|home|corp)$/i', $host)) {
            return true;
        }
        if (preg_match('/^(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})(:\d+)?$/', $host)) {
            return true;
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    protected static function summarize(array $items)
    {
        $counts = ['green' => 0, 'yellow' => 0, 'red' => 0];
        foreach ($items as $item) {
            $lv = isset($item['level']) ? (string)$item['level'] : 'yellow';
            if (!isset($counts[$lv])) {
                $counts[$lv] = 0;
            }
            $counts[$lv]++;
        }
        $overall = 'green';
        if ($counts['red'] > 0) {
            $overall = 'red';
        } elseif ($counts['yellow'] > 0) {
            $overall = 'yellow';
        }

        return [
            'overall' => $overall,
            'counts'  => $counts,
            'total'   => count($items),
        ];
    }

    /**
     * 一键修复：仅修改 app 块内允许的安全项，不覆盖 site 等其它配置。
     *
     * @return array{ok: bool, msg: string, changed: list<string>}
     */
    public static function applyFix($action, array $config = null)
    {
        if ($config === null) {
            $cfgFile = APP_PATH . 'extra/maccms.php';
            $config = is_file($cfgFile) ? include $cfgFile : [];
        }
        if (!is_array($config)) {
            return ['ok' => false, 'msg' => lang('admin/safety/checkup/fix_config_err'), 'changed' => []];
        }
        if (!isset($config['app']) || !is_array($config['app'])) {
            $config['app'] = [];
        }

        $action = strtolower(trim((string)$action));
        $allowed = [
            'enable_csrf'              => ['security_csrf_admin' => '1'],
            'enable_audit'             => ['admin_audit_enabled' => '1'],
            'enable_anti_scrape_api'   => ['anti_scrape_api_enabled' => '1'],
            'enable_anti_scrape_index' => ['anti_scrape_index_enabled' => '1'],
            'enable_headers'           => ['security_headers_base' => '1'],
            'regenerate_cache_flag'    => ['cache_flag' => substr(md5(uniqid('', true) . microtime(true)), 0, 10)],
            'generate_crypto_secret'   => ['admin_audit_crypto_secret' => mac_get_rndstr(32)],
        ];
        $batch = [
            'enable_all_security' => [
                'security_csrf_admin'       => '1',
                'admin_audit_enabled'       => '1',
                'anti_scrape_api_enabled'   => '1',
                'anti_scrape_index_enabled' => '1',
                'security_headers_base'     => '1',
            ],
        ];

        $patch = [];
        if ($action === 'fix_all_recommended') {
            $scan = self::run($config);
            foreach ($scan['items'] as $item) {
                if (empty($item['fix_action'])) {
                    continue;
                }
                $fa = (string)$item['fix_action'];
                if (isset($allowed[$fa])) {
                    $patch = array_merge($patch, $allowed[$fa]);
                }
            }
        } elseif (isset($batch[$action])) {
            $patch = $batch[$action];
            if ($action === 'enable_all_security') {
                if (trim((string)($config['app']['admin_audit_crypto_secret'] ?? '')) === '') {
                    $patch['admin_audit_crypto_secret'] = mac_get_rndstr(32);
                }
                $flag = isset($config['app']['cache_flag']) ? strtolower(trim((string)$config['app']['cache_flag'])) : '';
                if (in_array($flag, self::WEAK_CACHE_FLAGS, true)) {
                    $patch['cache_flag'] = substr(md5(uniqid('', true) . microtime(true)), 0, 10);
                }
            }
        } elseif (isset($allowed[$action])) {
            $patch = $allowed[$action];
        } else {
            return ['ok' => false, 'msg' => lang('param_err'), 'changed' => []];
        }

        if ($patch === []) {
            return ['ok' => true, 'msg' => lang('admin/safety/checkup/fix_none'), 'changed' => []];
        }

        $changed = [];
        foreach ($patch as $k => $v) {
            if (!isset($config['app'][$k]) || (string)$config['app'][$k] !== (string)$v) {
                $changed[] = $k;
            }
            $config['app'][$k] = $v;
        }

        if ($changed === []) {
            return ['ok' => true, 'msg' => lang('admin/safety/checkup/fix_none'), 'changed' => []];
        }

        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        if ($res === false) {
            return ['ok' => false, 'msg' => lang('save_err'), 'changed' => []];
        }

        return ['ok' => true, 'msg' => lang('admin/safety/checkup/fix_ok'), 'changed' => $changed];
    }
}
