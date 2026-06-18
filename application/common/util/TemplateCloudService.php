<?php

namespace app\common\util;

use think\Cache;

/**
 * AI 模板云端市场（完整主题包）
 * 与 ResourceHub 资源站采集独立：独立 URL、缓存键、RS256 验签公钥。
 */
class TemplateCloudService
{
    const CACHE_CATALOG = 'template_market_catalog';
    const CACHE_CATALOG_HASH = 'template_market_catalog_hash';
    const CACHE_CATALOG_BACKUP = 'template_market_catalog_backup';

    /**
     * 内置验签公钥（与 application/data/template_market_cloud/catalog_public.pem 一致）
     * 轮换密钥时同步更新此常量与 PEM 文件。
     */
    const CATALOG_PUBLIC_KEY_PEM = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtTvyr02aeVgtmMGllloL
6L3oqv9A2UHFewwhVXewwOfK5ArVEIVSWgU/YrCBHvv8bAsDPL06VJPKwtouyMLS
0cgj2zWmNG/DnfpH3lC/ycLqwZLzN+ZL3C53eRHZ5W7Ueo9sTj4gK5O056Q6l+IP
FM+4YCPUF9lj76d1Z53eZtUDxSraAShKvJI+Irt6uTNIYnB2191/hFukoabfjwaE
r3gytub7Cp91/w5DeEVTYg27w0BWb4yWgqlypKpgYYEz0aLLfwNtLvzXXlF1TZ0w
5C6h4bvYGnSqukqZkbPcHzs5So1MQ/ZeGSjy4qNhdXNMitDOASLBgPP5ihee1lw2
2QIDAQAB
-----END PUBLIC KEY-----
PEM;

    /** @var array */
    protected $config;

    public function __construct()
    {
        $cfg = config('maccms.template_cloud');
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $this->config = array_merge([
            'status' => 0,
            'catalog_url' => 'https://api.maccms.ai/templates/catalog.json',
            'cache_ttl' => 10800,
        ], $cfg);
    }

    public function isEnabled()
    {
        return (int) ($this->config['status'] ?? 0) === 1;
    }

    /**
     * 拉取云端主题目录（RS256 验签 + 缓存）
     * @param bool $force 忽略缓存强制拉取
     * @return array{items:array,error:string}
     */
    public function fetchCatalog($force = false)
    {
        if (!$this->isEnabled()) {
            return ['items' => [], 'error' => 'disabled'];
        }

        $cacheKey = self::CACHE_CATALOG;
        $hashKey = self::CACHE_CATALOG_HASH;
        $ttl = max(60, (int) ($this->config['cache_ttl'] ?? 10800));

        if (!$force) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached) && is_array($cached)) {
                return ['items' => $cached, 'error' => ''];
            }
        }

        $url = trim((string) ($this->config['catalog_url'] ?? ''));
        if ($url === '' || !$this->validateRemoteUrl($url)) {
            return ['items' => $this->fallbackCatalog($cacheKey), 'error' => 'url'];
        }

        try {
            $raw = $this->fetchRemoteSecure($url, 30);
            if ($raw === false || $raw === '') {
                return ['items' => $this->fallbackCatalog($cacheKey), 'error' => 'fetch'];
            }

            $items = $this->parseVerifiedCatalog($raw);
            if ($items === null) {
                return ['items' => $this->fallbackCatalog($cacheKey), 'error' => 'signature'];
            }
            if (!$this->validateCatalogFormat($items)) {
                return ['items' => $this->fallbackCatalog($cacheKey), 'error' => 'format'];
            }

            $newHash = hash('sha256', $raw);
            $oldHash = Cache::get($hashKey);
            $cached = Cache::get($cacheKey);
            if ($oldHash === $newHash && !empty($cached) && is_array($cached)) {
                Cache::set($cacheKey, $cached, $ttl);
                return ['items' => $cached, 'error' => ''];
            }

            Cache::set($cacheKey, $items, $ttl);
            Cache::set(self::CACHE_CATALOG_BACKUP, $items, $ttl * 10);
            Cache::set($hashKey, $newHash, $ttl * 3);

            return ['items' => $items, 'error' => ''];
        } catch (\Exception $e) {
            return ['items' => $this->fallbackCatalog($cacheKey), 'error' => 'exception'];
        }
    }

    /**
     * 下载并安装完整主题包到 template/{dir}/
     * @param array $item 目录项（须来自已验签目录）
     * @return array{code:int,msg:string,data:array}
     */
    public function installPackage(array $item)
    {
        if (!class_exists('ZipArchive')) {
            return ['code' => 0, 'msg' => lang('admin/template_market/need_zip'), 'data' => []];
        }

        $id = $this->sanitizeId($item['id'] ?? '');
        $dir = $this->sanitizeDir($item['dir'] ?? '');
        $packageUrl = trim((string) ($item['package_url'] ?? ''));
        $expectedHash = strtolower(trim((string) ($item['package_hash'] ?? '')));

        if ($id === '' || $dir === '' || $packageUrl === '') {
            return ['code' => 0, 'msg' => lang('param_err'), 'data' => []];
        }
        if (!$this->isValidPackageHash($expectedHash)) {
            return ['code' => 0, 'msg' => lang('admin/template_market/hash_required'), 'data' => []];
        }
        if (!$this->validateRemoteUrl($packageUrl)) {
            return ['code' => 0, 'msg' => lang('admin/template_market/invalid_package_url'), 'data' => []];
        }

        $workDir = RUNTIME_PATH . 'template_market' . DS;
        if (!is_dir($workDir)) {
            mac_mkdirss($workDir);
        }

        $zipPath = $workDir . $id . '.zip';
        $bin = $this->fetchRemoteSecure($packageUrl, 120);
        if ($bin === false || $bin === '') {
            return ['code' => 0, 'msg' => lang('admin/template_market/download_fail'), 'data' => []];
        }

        $actual = hash('sha256', $bin);
        $expected = preg_replace('/^sha256:/i', '', $expectedHash);
        if (!hash_equals(strtolower($expected), $actual)) {
            return ['code' => 0, 'msg' => lang('admin/template_market/hash_mismatch'), 'data' => []];
        }

        if (@file_put_contents($zipPath, $bin) === false) {
            return ['code' => 0, 'msg' => lang('admin/template_market/save_fail'), 'data' => []];
        }

        $extractDir = $workDir . 'extract_' . $id . '_' . time();
        mac_mkdirss($extractDir);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->cleanupPath($extractDir);
            @unlink($zipPath);
            return ['code' => 0, 'msg' => lang('admin/template_market/zip_open_fail'), 'data' => []];
        }

        if (!$this->extractZipSafely($zip, $extractDir)) {
            $zip->close();
            $this->cleanupPath($extractDir);
            @unlink($zipPath);
            return ['code' => 0, 'msg' => lang('admin/template_market/unsafe_package'), 'data' => []];
        }
        $zip->close();
        @unlink($zipPath);

        $sourceRoot = $this->resolvePackageRoot($extractDir, $dir);
        if ($sourceRoot === '' || !is_dir($sourceRoot . DS . 'html')) {
            $this->cleanupPath($extractDir);
            return ['code' => 0, 'msg' => lang('admin/template_market/invalid_structure'), 'data' => []];
        }

        if (!$this->scanDirectorySafe($sourceRoot)) {
            $this->cleanupPath($extractDir);
            return ['code' => 0, 'msg' => lang('admin/template_market/unsafe_package'), 'data' => []];
        }

        $target = ROOT_PATH . 'template' . DS . $dir;
        $backupPath = $this->relocateExistingTemplate($target, $dir);
        if ($backupPath === false) {
            $this->cleanupPath($extractDir);
            return ['code' => 0, 'msg' => lang('admin/template_market/save_fail'), 'data' => []];
        }

        if (!@rename($sourceRoot, $target)) {
            $this->copyDirectory($sourceRoot, $target);
            $this->cleanupPath($extractDir);
        } else {
            $parent = dirname($sourceRoot);
            if ($parent !== $extractDir) {
                $this->cleanupPath($extractDir);
            } else {
                @rmdir($extractDir);
            }
        }

        if (!is_dir($target . DS . 'html')) {
            $this->cleanupPath($target);
            $this->restoreTemplateBackup($backupPath, $target);
            return ['code' => 0, 'msg' => lang('admin/template_market/invalid_structure'), 'data' => []];
        }

        $this->discardTemplateBackup($backupPath);

        $this->recordInstall($id, $dir, $item);
        $this->mergeThemeDefaults($target);

        return [
            'code' => 1,
            'msg' => lang('admin/template_market/install_ok'),
            'data' => ['dir' => $dir, 'id' => $id],
        ];
    }

    /**
     * 切换站点当前 PC 模板
     */
    public function activateTemplate($dir)
    {
        $dir = $this->sanitizeDir($dir);
        if ($dir === '') {
            return ['code' => 0, 'msg' => lang('param_err')];
        }

        $path = ROOT_PATH . 'template' . DS . $dir;
        if (!is_dir($path) || !is_dir($path . DS . 'html')) {
            return ['code' => 0, 'msg' => lang('admin/template_market/not_installed')];
        }

        $configFile = APP_PATH . 'extra' . DS . 'maccms.php';
        $config = config('maccms');
        if (!is_array($config)) {
            $config = include $configFile;
        }
        if (!isset($config['site']) || !is_array($config['site'])) {
            $config['site'] = [];
        }

        $config['site']['template_dir'] = $dir;
        if (empty($config['site']['html_dir'])) {
            $config['site']['html_dir'] = 'html';
        }

        $res = mac_arr2file($configFile, $config);
        if ($res === false) {
            return ['code' => 0, 'msg' => lang('save_err')];
        }

        return ['code' => 1, 'msg' => lang('admin/template_market/activate_ok')];
    }

    /**
     * 已安装模板目录（含本地自带与云端安装）
     */
    public function listLocalTemplates()
    {
        $dirs = [];
        $root = ROOT_PATH . 'template';
        if (!is_dir($root)) {
            return $dirs;
        }

        foreach (glob($root . DS . '*', GLOB_ONLYDIR) ?: [] as $path) {
            $name = basename($path);
            if (!$this->isValidDirName($name)) {
                continue;
            }
            if (!is_dir($path . DS . 'html')) {
                continue;
            }
            $info = $this->readTemplateInfo($path);
            $record = $this->getInstallRecord($name);
            $dirs[$name] = [
                'dir' => $name,
                'name' => $info['name'] ?: $name,
                'version' => $info['version'] ?: ($record['version'] ?? ''),
                'market_id' => $record['id'] ?? '',
                'installed_at' => $record['installed_at'] ?? 0,
            ];
        }

        return $dirs;
    }

    public function getActiveTemplateDir()
    {
        $site = config('maccms.site');
        return is_array($site) ? (string) ($site['template_dir'] ?? '') : '';
    }

    /**
     * 解析并验签目录；失败返回 null
     * @return array|null
     */
    protected function parseVerifiedCatalog($raw)
    {
        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
            return null;
        }
        if (!$this->verifyCatalogSignature($payload)) {
            return null;
        }
        return $payload['items'];
    }

    protected function verifyCatalogSignature(array $payload)
    {
        if (empty($payload['signature']) || empty($payload['items']) || !is_array($payload['items'])) {
            return false;
        }
        $alg = strtoupper((string) ($payload['sig_alg'] ?? 'RS256'));
        if ($alg !== 'RS256') {
            return false;
        }

        $signPayload = $this->catalogSigningPayload($payload['items']);
        $pubKey = openssl_pkey_get_public($this->getCatalogPublicKeyPem());
        if ($pubKey === false) {
            return false;
        }

        $sig = base64_decode((string) $payload['signature'], true);
        if ($sig === false || $sig === '') {
            return false;
        }

        return openssl_verify($signPayload, $sig, $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function catalogSigningPayload(array $items)
    {
        return json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function getCatalogPublicKeyPem()
    {
        $pemFile = APP_PATH . 'data' . DS . 'template_market_cloud' . DS . 'catalog_public.pem';
        if (is_readable($pemFile)) {
            $pem = trim((string) file_get_contents($pemFile));
            if ($pem !== '') {
                return $pem;
            }
        }
        return trim(self::CATALOG_PUBLIC_KEY_PEM);
    }

    protected function validateCatalogFormat($items)
    {
        if (!is_array($items) || empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                return false;
            }
            foreach (['id', 'name', 'dir', 'package_url', 'package_hash'] as $field) {
                if (empty($item[$field])) {
                    return false;
                }
            }
            if (!$this->isValidDirName($this->sanitizeDir($item['dir']))) {
                return false;
            }
            if (!preg_match('#^https?://#i', $item['package_url'])) {
                return false;
            }
            if (!$this->isValidPackageHash($item['package_hash'])) {
                return false;
            }
        }

        return true;
    }

    protected function isValidPackageHash($hash)
    {
        $hash = strtolower(trim((string) $hash));
        return (bool) preg_match('/^sha256:[a-f0-9]{64}$/', $hash);
    }

    /**
     * 安全远端 GET：禁止跟随重定向，复核最终 URL，仅允许公网目标
     * @return string|false
     */
    protected function fetchRemoteSecure($url, $timeout = 30)
    {
        if (!$this->validateRemoteUrl($url)) {
            return false;
        }
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(5, (int) $timeout));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MacCMS-TemplateMarket/1.0');

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            return false;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }
        if ($effectiveUrl !== '' && $effectiveUrl !== $url) {
            if (!$this->validateRemoteUrl($effectiveUrl)) {
                return false;
            }
        }

        return $body;
    }

    protected function fallbackCatalog($cacheKey)
    {
        $old = Cache::get(self::CACHE_CATALOG_BACKUP);
        if (!empty($old) && is_array($old)) {
            $ttl = max(60, (int) ($this->config['cache_ttl'] ?? 10800));
            Cache::set($cacheKey, $old, $ttl);
            return $old;
        }
        return [];
    }

    protected function validateRemoteUrl($url)
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        if (empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || $host === '0.0.0.0') {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (strpos($ip, '169.254.') === 0) {
            return false;
        }

        return true;
    }

    protected function extractZipSafely(\ZipArchive $zip, $destDir)
    {
        $destDir = rtrim(str_replace(['/', '\\'], DS, $destDir), DS) . DS;
        $count = $zip->numFiles;
        for ($i = 0; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                return false;
            }
            $name = str_replace('\\', '/', $name);
            if ($name === '' || strpos($name, "\0") !== false) {
                return false;
            }
            if (preg_match('#(^|/)\.\.(/|$)#', $name)) {
                return false;
            }
            if (preg_match('#\.(php|phtml|phar|inc)$#i', $name)) {
                return false;
            }

            $target = $destDir . str_replace('/', DS, $name);
            if (substr($name, -1) === '/') {
                mac_mkdirss(rtrim($target, DS));
                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent)) {
                mac_mkdirss($parent);
            }
            $stream = $zip->getStream($zip->getNameIndex($i));
            if ($stream === false) {
                return false;
            }
            $out = fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                return false;
            }
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
        }

        return true;
    }

    protected function resolvePackageRoot($extractDir, $expectedDir)
    {
        $extractDir = rtrim($extractDir, DS . '/\\');
        if (is_dir($extractDir . DS . 'html')) {
            return $extractDir;
        }

        $children = glob($extractDir . DS . '*', GLOB_ONLYDIR) ?: [];
        if (count($children) === 1) {
            $only = $children[0];
            if (is_dir($only . DS . 'html')) {
                return $only;
            }
        }

        foreach ($children as $child) {
            $base = basename($child);
            if ($base === $expectedDir && is_dir($child . DS . 'html')) {
                return $child;
            }
        }

        return '';
    }

    protected function scanDirectorySafe($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'phtml', 'phar', 'inc'], true)) {
                return false;
            }
        }
        return true;
    }

    protected function copyDirectory($src, $dst)
    {
        mac_mkdirss($dst);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $sub = $dst . DS . $iterator->getSubPathName();
            if ($item->isDir()) {
                mac_mkdirss($sub);
            } else {
                @copy((string) $item, $sub);
            }
        }
    }

    protected function cleanupPath($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    /**
     * 将已有模板移出 web 目录至 runtime 备份区；清理旧版留在 template/ 下的 .bak 目录
     * @return string|false 无旧模板返回 ''；成功返回备份路径；失败返回 false
     */
    protected function relocateExistingTemplate($target, $dir)
    {
        $this->purgeLegacyWebBackups($dir);

        if (!is_dir($target)) {
            return '';
        }

        $backupRoot = RUNTIME_PATH . 'template_market' . DS . 'backup' . DS;
        mac_mkdirss($backupRoot);
        $backupPath = $backupRoot . $dir . '_' . date('YmdHis');
        if (!@rename($target, $backupPath)) {
            return false;
        }
        return $backupPath;
    }

    /**
     * 删除历史版本在 template/ 下生成的 .bak.YmdHis 目录（web 可访问）
     */
    protected function purgeLegacyWebBackups($dir)
    {
        $pattern = ROOT_PATH . 'template' . DS . $dir . '.bak.*';
        foreach (glob($pattern, GLOB_ONLYDIR) ?: [] as $legacy) {
            $this->cleanupPath($legacy);
        }
    }

    protected function restoreTemplateBackup($backupPath, $target)
    {
        if ($backupPath === '' || !is_string($backupPath)) {
            return;
        }
        if (is_dir($backupPath) && !is_dir($target)) {
            @rename($backupPath, $target);
        }
    }

    protected function discardTemplateBackup($backupPath)
    {
        if ($backupPath !== '' && is_dir($backupPath)) {
            $this->cleanupPath($backupPath);
        }
    }

    protected function mergeThemeDefaults($templateDir)
    {
        $defaultsFile = rtrim($templateDir, DS . '/\\') . DS . 'config.defaults.json';
        if (!is_file($defaultsFile)) {
            return;
        }

        $raw = @file_get_contents($defaultsFile);
        $defaults = json_decode($raw, true);
        if (!is_array($defaults)) {
            return;
        }

        $patch = isset($defaults['theme']) && is_array($defaults['theme']) ? $defaults['theme'] : $defaults;
        if (empty($patch)) {
            return;
        }

        $mcthemeFile = APP_PATH . 'extra' . DS . 'mctheme.php';
        $current = is_file($mcthemeFile) ? include $mcthemeFile : [];
        if (!is_array($current)) {
            $current = [];
        }
        if (!isset($current['theme']) || !is_array($current['theme'])) {
            $current['theme'] = [];
        }

        $current['theme'] = $this->arrayMergeRecursiveDistinct($current['theme'], $patch);
        mac_arr2file($mcthemeFile, $current);
    }

    protected function arrayMergeRecursiveDistinct(array $base, array $patch)
    {
        foreach ($patch as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $value);
            } elseif (!array_key_exists($key, $base)) {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    protected function recordInstall($id, $dir, array $item)
    {
        $file = APP_PATH . 'extra' . DS . 'template_market_installed.php';
        $data = [];
        if (is_file($file)) {
            $loaded = include $file;
            if (is_array($loaded)) {
                $data = $loaded;
            }
        }
        $data[$dir] = [
            'id' => $id,
            'dir' => $dir,
            'name' => $item['name'] ?? '',
            'version' => $item['version'] ?? '',
            'installed_at' => time(),
        ];
        mac_arr2file($file, $data);
    }

    protected function getInstallRecord($dir)
    {
        $file = APP_PATH . 'extra' . DS . 'template_market_installed.php';
        if (!is_file($file)) {
            return [];
        }
        $data = include $file;
        if (!is_array($data) || !isset($data[$dir])) {
            return [];
        }
        return is_array($data[$dir]) ? $data[$dir] : [];
    }

    protected function readTemplateInfo($path)
    {
        $ini = $path . DS . 'info.ini';
        $out = ['name' => '', 'version' => ''];
        if (!is_file($ini)) {
            return $out;
        }
        $lines = @file($ini, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return $out;
        }
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($k, $v) = array_map('trim', explode('=', $line, 2));
            if ($k === 'name') {
                $out['name'] = $v;
            } elseif ($k === 'version') {
                $out['version'] = $v;
            }
        }
        return $out;
    }

    protected function sanitizeId($id)
    {
        $id = strtolower(trim((string) $id));
        return preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $id) ? $id : '';
    }

    protected function sanitizeDir($dir)
    {
        $dir = trim((string) $dir);
        return $this->isValidDirName($dir) ? $dir : '';
    }

    protected function isValidDirName($dir)
    {
        return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $dir);
    }
}
