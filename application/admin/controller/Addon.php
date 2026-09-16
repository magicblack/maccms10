<?php
namespace app\admin\controller;

use think\addons\AddonException;
use think\addons\Service;
use think\Cache;
use think\Config;
use think\Exception;
use app\common\util\AddonSecureInstaller;
use app\common\util\Dir;

class Addon extends Base
{
    public function __construct()
    {
        parent::__construct();
        // 铁律：后台新功能只维护 view_new
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function index()
    {
        $cloud = new \app\common\util\AddonCloudService();
        $this->assign('title', lang('admin/addon/title'));
        $this->assign('addon_cloud_enabled', $cloud->isEnabled() ? 1 : 0);
        $this->assign('addon_audit', $cloud->recentAudit(5));
        return $this->fetch('addon/index');
    }

    public function config()
    {
        $name = $this->addonNameFromInput();
        if ($name === '') {
            return $this->error(lang('param_err'));
        }
        if (!is_dir(ADDON_PATH . $name)) {
            return $this->error(lang('get_dir_err'));
        }

        $info = get_addon_info($name);
        $config = get_addon_fullconfig($name);
        if (!$info) {
            return $this->error(lang('get_addon_info_err'));
        }
        if (!is_array($config)) {
            $config = [];
        }

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($post)) {
                $t = \think\Request::instance()->token('__token__');
                return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
            }

            $params = $this->request->post('row/a');
            if (!is_array($params)) {
                $params = [];
            }

            $validated = $this->validateAddonConfigRows($config, $params);
            if ($validated['ok'] !== true) {
                $t = \think\Request::instance()->token('__token__');
                return $this->error($validated['msg'], null, ['__token__' => $t]);
            }
            $config = $validated['config'];

            try {
                set_addon_fullconfig($name, $config);
                // 热更新钩子表写回 application/extra/addons.php
                Service::refresh();
                Cache::rm('hooks');
                Cache::rm('addons');
                $t = \think\Request::instance()->token('__token__');
                return $this->success(lang('admin/addon/config_save_ok'), null, ['__token__' => $t]);
            } catch (Exception $e) {
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/op_fail'), null, ['__token__' => $t]);
            }
        }

        $this->assign('info', $info);
        $this->assign('config', $config);
        $this->assign('title', lang('admin/addon/config_title'));
        return $this->fetch('addon/config');
    }

    public function info()
    {
    }

    public function downloaded()
    {
        $offset = (int)$this->request->get('offset');
        $limit = (int)$this->request->get('limit');
        $search = $this->request->get('search');
        $search = htmlspecialchars(strip_tags((string)$search));
        if ($search === '' || $search === null) {
            $wd = input('wd/s', '');
            $search = htmlspecialchars(strip_tags((string)$wd));
        }

        // 旧 api.maccms.com：只读并存，仅用于丰富本地列表展示（价格/封面等），不提供未签名安装
        $onlineaddons = $this->fetchLegacyOnlineCatalog();

        $addons = get_addon_list();
        $list = [];
        foreach ($addons as $k => $v) {
            if (!is_array($v)) {
                continue;
            }
            if ($search && stripos($v['name'], $search) === false
                && stripos((string)(isset($v['intro']) ? $v['intro'] : ''), $search) === false
                && stripos((string)(isset($v['title']) ? $v['title'] : ''), $search) === false) {
                continue;
            }
            if (isset($onlineaddons[$v['name']]) && is_array($onlineaddons[$v['name']])) {
                // 仅补空展示字段，不覆盖本地 title/state 等
                $meta = $onlineaddons[$v['name']];
                foreach (['title', 'intro', 'author', 'image', 'price'] as $mk) {
                    if (empty($v[$mk]) && !empty($meta[$mk])) {
                        $v[$mk] = $meta[$mk];
                    }
                }
            }
            if (!isset($v['image'])) {
                $v['image'] = '';
            } else {
                $v['image'] = $this->sanitizeLegacyImageUrl($v['image']);
            }
            if (!isset($v['price'])) {
                $v['price'] = '0.00';
            }
            if (!isset($v['author'])) {
                $v['author'] = '';
            }
            $v['url'] = function_exists('addon_url') ? addon_url($v['name']) : '';
            $v['createtime'] = is_dir(ADDON_PATH . $v['name']) ? filemtime(ADDON_PATH . $v['name']) : 0;
            $v['install'] = '1';
            $v['legacy_meta'] = isset($onlineaddons[$v['name']]) ? 1 : 0;
            $list[] = $v;
        }
        $total = count($list);
        if ($limit) {
            $list = array_slice($list, $offset, $limit);
        }
        return json(['total' => $total, 'rows' => $list, 'legacy_catalog' => !empty($onlineaddons) ? 1 : 0]);
    }

    /**
     * 旧远程安装入口保持关闭（只读并存）；装包请走 cloudInstall 或本地 zip
     */
    public function install()
    {
        return $this->error(lang('admin/addon/cloud_use_catalog'));
    }

    /**
     * 拉取旧版在线目录（只读缓存；失败返回空数组，不影响本机列表）
     * @return array name => row
     */
    protected function fetchLegacyOnlineCatalog()
    {
        $cfg = isset($GLOBALS['config']['addon_cloud']) && is_array($GLOBALS['config']['addon_cloud'])
            ? $GLOBALS['config']['addon_cloud'] : [];
        // 默认开启只读并存；显式 legacy_catalog=0 可关
        if (isset($cfg['legacy_catalog']) && (string)$cfg['legacy_catalog'] === '0') {
            return [];
        }

        $flag = isset($GLOBALS['config']['app']['cache_flag']) ? $GLOBALS['config']['app']['cache_flag'] : 'mac';
        $key = $flag . '_onlineaddons_v2';
        $onlineaddons = Cache::get($key);
        if (is_array($onlineaddons)) {
            return $onlineaddons;
        }

        $onlineaddons = [];
        // 固定白名单主机；安全 GET（禁跟随跳转）
        // 仅 HTTPS，避免 HTTP 回退被 MITM 污染只读元数据
        $raw = $this->fetchLegacyCatalogBody('https://api.maccms.com/addon/index');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (!empty($json['rows']) && is_array($json['rows'])) {
                $n = 0;
                foreach ($json['rows'] as $row) {
                    if ($n >= 2000) {
                        break;
                    }
                    if (!is_array($row) || empty($row['name'])) {
                        continue;
                    }
                    $name = strtolower(trim((string)$row['name']));
                    if (!AddonSecureInstaller::isValidName($name)) {
                        continue;
                    }
                    $onlineaddons[$name] = [
                        'name' => $name,
                        'title' => $this->sanitizeLegacyText(isset($row['title']) ? $row['title'] : $name, 120),
                        'intro' => $this->sanitizeLegacyText(isset($row['intro']) ? $row['intro'] : '', 500),
                        'author' => $this->sanitizeLegacyText(isset($row['author']) ? $row['author'] : '', 64),
                        'version' => $this->sanitizeLegacyText(isset($row['version']) ? $row['version'] : '', 32),
                        'image' => $this->sanitizeLegacyImageUrl(isset($row['image']) ? $row['image'] : ''),
                        'price' => $this->sanitizeLegacyText(isset($row['price']) ? $row['price'] : '0.00', 16),
                        'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
                    ];
                    $n++;
                }
            }
        }
        Cache::set($key, $onlineaddons, 600);
        return $onlineaddons;
    }

    /**
     * 旧目录专用 GET：主机白名单 + 禁跳转 + 体积上限
     * @param string $url
     * @return string|false
     */
    protected function fetchLegacyCatalogBody($url)
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        // 仅允许官方旧目录主机（与产品决策「只读并存」对齐）
        if ($host !== 'api.maccms.com') {
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MacCMS-AddonLegacyCatalog/1.0');
        if ($scheme === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if (defined('CURLOPT_MAXFILESIZE')) {
            curl_setopt($ch, CURLOPT_MAXFILESIZE, 2097152);
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);

        if ($errno !== 0 || $body === false || $httpCode < 200 || $httpCode >= 300) {
            return false;
        }
        if ($primaryIp !== '' && !$this->legacyIpIsPublic($primaryIp)) {
            return false;
        }
        if (!is_string($body) || strlen($body) > 2097152) {
            return false;
        }
        return $body;
    }

    /**
     * @param string $ip
     * @return bool
     */
    protected function legacyIpIsPublic($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (strpos($ip, '169.254.') === 0) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param mixed $text
     * @param int $max
     * @return string
     */
    protected function sanitizeLegacyText($text, $max = 200)
    {
        $s = trim(strip_tags((string)$text));
        $s = str_replace(["\0", "\r", "\n"], '', $s);
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }
        return substr($s, 0, $max);
    }

    /**
     * @param mixed $url
     * @return string
     */
    protected function sanitizeLegacyImageUrl($url)
    {
        $u = trim((string)$url);
        if ($u === '') {
            return '';
        }
        // 仅 http(s) 或站点内单斜杠路径；拒绝协议相对 //evil
        if (preg_match('#^https?://#i', $u)) {
            if (strlen($u) > 500 || preg_match('#^(javascript|data|vbscript):#i', $u)) {
                return '';
            }
            return $u;
        }
        if (isset($u[0]) && $u[0] === '/' && !(isset($u[1]) && $u[1] === '/')) {
            if (strlen($u) > 500 || preg_match('#^(javascript|data|vbscript):#i', $u)) {
                return '';
            }
            return $u;
        }
        return '';
    }

    /**
     * 云目录列表（已验签 + approved；mock 模式可空目录骨架）
     */
    public function cloudCatalog()
    {
        $cloud = new \app\common\util\AddonCloudService();
        if (!$cloud->isEnabled()) {
            return json(['total' => 0, 'rows' => [], 'enabled' => 0, 'error' => 'disabled', 'mock' => 0]);
        }
        // GET 禁止 force，避免刷远程目录；刷新走 POST cloudRefresh
        $res = $cloud->fetchCatalog(false);
        $items = $cloud->enrichItems($res['items']);
        if (count($items) > 500) {
            $items = array_slice($items, 0, 500);
        }
        $search = htmlspecialchars(strip_tags((string)$this->request->get('search', '')));
        if ($search !== '') {
            $items = array_values(array_filter($items, function ($v) use ($search) {
                if (!is_array($v)) {
                    return false;
                }
                return stripos((string)$v['name'], $search) !== false
                    || stripos((string)(isset($v['title']) ? $v['title'] : ''), $search) !== false
                    || stripos((string)(isset($v['intro']) ? $v['intro'] : ''), $search) !== false;
            }));
        }
        // 不下发 package_url/hash 到前端，安装仅认目录 id
        foreach ($items as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($items[$i]['package_url'], $items[$i]['package_hash'], $items[$i]['signature']);
            if (isset($items[$i]['image'])) {
                $items[$i]['image'] = $this->sanitizeLegacyImageUrl($items[$i]['image']);
            }
        }
        $items = array_values($items);
        $isMock = (isset($res['error']) && $res['error'] === 'mock') ? 1 : 0;
        return json([
            'total' => count($items),
            'rows' => $items,
            'enabled' => 1,
            'error' => $res['error'],
            'mock' => $isMock,
        ]);
    }

    /**
     * 强制刷新签目录缓存
     */
    public function cloudRefresh()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $post = $this->request->post();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($post)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }
        $cloud = new \app\common\util\AddonCloudService();
        if (!$cloud->isEnabled()) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/cloud_disabled'), null, ['__token__' => $t]);
        }
        $adminId = !empty($this->_admin['admin_id']) ? (int)$this->_admin['admin_id'] : 0;
        $adminIp = $this->request->ip();
        $rl = $cloud->checkRateLimit($adminId, $adminIp);
        if ($rl['ok'] !== true) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($rl['msg'], null, ['__token__' => $t]);
        }
        $cloud->hitRateLimit($adminId, $adminIp);
        $res = $cloud->fetchCatalog(true);
        $t = \think\Request::instance()->token('__token__');
        if ($res['error'] !== '' && empty($res['items'])) {
            return $this->error(lang('admin/addon/cloud_refresh_fail', [$res['error']]), null, ['__token__' => $t]);
        }
        return $this->success(lang('admin/addon/cloud_refresh_ok', [count($res['items'])]), null, [
            '__token__' => $t,
            'count' => count($res['items']),
            'error' => $res['error'],
        ]);
    }

    /**
     * 从签目录安装 / 升级
     */
    public function cloudInstall()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $post = $this->request->post();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($post)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }
        $id = input('post.id/s', '');
        $adminId = 0;
        if (!empty($this->_admin['admin_id'])) {
            $adminId = (int)$this->_admin['admin_id'];
        }
        $cloud = new \app\common\util\AddonCloudService();
        $res = $cloud->installById($id, $adminId, $this->request->ip());
        $t = \think\Request::instance()->token('__token__');
        if (empty($res['code'])) {
            return $this->error(isset($res['msg']) ? $res['msg'] : lang('admin/addon/op_fail'), null, ['__token__' => $t]);
        }
        return $this->success($res['msg'], null, array_merge(is_array($res['data']) ? $res['data'] : [], ['__token__' => $t]));
    }

    /**
     * 卸载（先备份，失败可回滚目录）
     */
    public function uninstall()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $post = $this->request->post();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($post)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }
        $name = $this->addonNameFromInput();
        if ($name === '') {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/path_err'), null, ['__token__' => $t]);
        }
        $force = (int)input('force/d', 0);
        $bak = AddonSecureInstaller::backupAddon($name);
        if ($bak === false) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/uninstall_backup_fail'), null, ['__token__' => $t]);
        }
        try {
            Service::uninstall($name, $force);
            $t = \think\Request::instance()->token('__token__');
            return $this->success(lang('uninstall_ok'), null, ['__token__' => $t]);
        } catch (AddonException $e) {
            if ($bak && !is_dir(ADDON_PATH . $name)) {
                AddonSecureInstaller::restoreAddon($name, $bak);
            }
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/uninstall_fail'), null, ['__token__' => $t]);
        } catch (Exception $e) {
            if ($bak && !is_dir(ADDON_PATH . $name)) {
                AddonSecureInstaller::restoreAddon($name, $bak);
            }
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/uninstall_fail'), null, ['__token__' => $t]);
        }
    }

    /**
     * 禁用启用
     */
    public function state()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $post = $this->request->post();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($post)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }
        $name = $this->addonNameFromInput();
        if ($name === '') {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/path_err'), null, ['__token__' => $t]);
        }
        $action = input('action/s', '');
        $force = (int)input('force/d', 0);
        if ($action !== 'enable' && $action !== 'disable') {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('param_err'), null, ['__token__' => $t]);
        }
        try {
            if ($action === 'enable') {
                Service::enable($name, $force);
            } else {
                Service::disable($name, $force);
            }
            Cache::rm('__menu__');
            $t = \think\Request::instance()->token('__token__');
            return $this->success(lang('opt_ok'), null, ['__token__' => $t]);
        } catch (AddonException $e) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/op_fail'), null, ['__token__' => $t]);
        } catch (Exception $e) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/op_fail'), null, ['__token__' => $t]);
        }
    }

    /**
     * 本地上传安装（加固后重新开放）
     */
    public function local()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $param = input();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($param)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }

        $file = $this->request->file('file');
        if (empty($file)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/upload_empty'), null, ['__token__' => $t]);
        }

        $addonTmpDir = RUNTIME_PATH . 'addons' . DS;
        if (!is_dir($addonTmpDir)) {
            @mkdir($addonTmpDir, 0755, true);
        }

        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'zip'])->move($addonTmpDir);
        if (!$info) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($file->getError() ?: lang('admin/addon/upload_fail'), null, ['__token__' => $t]);
        }

        $tmpFile = $addonTmpDir . $info->getSaveName();
        $stageDir = $addonTmpDir . 'stage_' . pathinfo($info->getFilename(), PATHINFO_FILENAME);
        if (is_dir($stageDir)) {
            AddonSecureInstaller::purgeDir($stageDir);
        }

        try {
            $extracted = AddonSecureInstaller::extractZipSafe($tmpFile, $stageDir);
            @unlink($tmpFile);
            if (empty($extracted['ok'])) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error($extracted['msg'], null, ['__token__' => $t]);
            }

            $infoFile = $stageDir . DS . 'info.ini';
            if (!is_file($infoFile)) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/lack_config_err'), null, ['__token__' => $t]);
            }

            $ini = Config::parse($infoFile, '', 'addon-local-stage');
            $name = isset($ini['name']) ? (string)$ini['name'] : '';
            if ($name === '' || !AddonSecureInstaller::isValidName($name)) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/name_empty_err'), null, ['__token__' => $t]);
            }

            $sig = AddonSecureInstaller::verifyPackageSignature($stageDir);
            if (empty($sig['ok'])) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error($sig['msg'], null, ['__token__' => $t]);
            }

            // 主类必须存在
            $mainClassFile = $stageDir . DS . ucfirst($name) . '.php';
            if (!is_file($mainClassFile)) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/lack_class_err'), null, ['__token__' => $t]);
            }

            $newAddonDir = ADDON_PATH . $name . DS;
            if (is_dir($newAddonDir)) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/haved_err'), null, ['__token__' => $t]);
            }

            $cloud = new \app\common\util\AddonCloudService();
            $lock = $cloud->acquireInstallLock($name);
            if ($lock === false) {
                AddonSecureInstaller::purgeDir($stageDir);
                $t = \think\Request::instance()->token('__token__');
                return $this->error(lang('admin/addon/cloud_busy'), null, ['__token__' => $t]);
            }

            try {
                if (!@rename($stageDir, $newAddonDir)) {
                    Dir::copyDir($stageDir, $newAddonDir);
                    AddonSecureInstaller::purgeDir($stageDir);
                    if (!is_dir($newAddonDir) || !is_file($newAddonDir . 'info.ini')) {
                        if (is_dir($newAddonDir)) {
                            AddonSecureInstaller::purgeDir($newAddonDir);
                        }
                        $t = \think\Request::instance()->token('__token__');
                        return $this->error(lang('admin/addon/extract_dir_fail'), null, ['__token__' => $t]);
                    }
                }

                try {
                    Service::check($name);
                    $addonInfo = get_addon_info($name);
                    if (!empty($addonInfo['state'])) {
                        $addonInfo['state'] = 0;
                        set_addon_info($name, $addonInfo);
                    }

                    $class = get_addon_class($name);
                    if ($class && class_exists($class)) {
                        $addon = new $class();
                        $addon->install();
                    }
                    Service::importsql($name);
                    Service::refresh();

                    $addonInfo = get_addon_info($name);
                    $addonInfo['config'] = get_addon_config($name) ? 1 : 0;
                    $t = \think\Request::instance()->token('__token__');
                    $addonInfo['__token__'] = $t;
                    return $this->success(lang('install_ok'), null, $addonInfo);
                } catch (Exception $e) {
                    if (is_dir($newAddonDir)) {
                        AddonSecureInstaller::purgeDir($newAddonDir);
                    }
                    $t = \think\Request::instance()->token('__token__');
                    return $this->error(lang('admin/addon/install_rollback'), null, ['__token__' => $t]);
                }
            } finally {
                $cloud->releaseInstallLock($lock);
            }
        } catch (Exception $e) {
            @unlink($tmpFile);
            if (is_dir($stageDir)) {
                AddonSecureInstaller::purgeDir($stageDir);
            }
            $t = \think\Request::instance()->token('__token__');
            return $this->error(lang('admin/addon/op_fail'), null, ['__token__' => $t]);
        }
    }

    public function add()
    {
        $this->assign('title', lang('local_setup'));
        return $this->fetch('addon/add');
    }

    /**
     * 更新插件：仅允许走云签目录（id）
     */
    public function upgrade()
    {
        if (!$this->request->isPost()) {
            return $this->error(lang('param_err'));
        }
        $post = $this->request->post();
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($post)) {
            $t = \think\Request::instance()->token('__token__');
            return $this->error($validate->getError() ?: lang('param_err'), null, ['__token__' => $t]);
        }
        $id = input('post.id/s', '');
        if ($id === '') {
            return $this->ajaxErrorWithFreshToken(lang('admin/addon/cloud_use_catalog'));
        }
        $adminId = 0;
        if (!empty($this->_admin['admin_id'])) {
            $adminId = (int)$this->_admin['admin_id'];
        }
        $cloud = new \app\common\util\AddonCloudService();
        $res = $cloud->installById($id, $adminId, $this->request->ip());
        $t = \think\Request::instance()->token('__token__');
        if (empty($res['code'])) {
            return $this->error(isset($res['msg']) ? $res['msg'] : lang('admin/addon/op_fail'), null, ['__token__' => $t]);
        }
        return $this->success($res['msg'], null, array_merge(is_array($res['data']) ? $res['data'] : [], ['__token__' => $t]));
    }

    /**
     * 从请求中取插件名并做白名单校验，非法返回空串
     * @return string
     */
    protected function addonNameFromInput()
    {
        $name = input('name/s', '');
        $name = strtolower(trim($name));
        if (!AddonSecureInstaller::isValidName($name)) {
            return '';
        }
        return $name;
    }

    /**
     * 校验并合并插件配置项（类型 / required / 选项白名单）
     * @param array $config get_addon_fullconfig 结果
     * @param array $params POST row
     * @return array
     */
    protected function validateAddonConfigRows($config, $params)
    {
        $allowedTypes = [
            'string', 'text', 'number', 'datetime', 'array',
            'checkbox', 'radio', 'select', 'selects',
            'image', 'images', 'file', 'files', 'bool',
        ];

        foreach ($config as $k => $v) {
            if (!is_array($v) || empty($v['name'])) {
                return ['ok' => false, 'msg' => lang('admin/addon/config_schema_err')];
            }
            $field = (string)$v['name'];
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                return ['ok' => false, 'msg' => lang('admin/addon/config_schema_err')];
            }
            $type = isset($v['type']) ? (string)$v['type'] : 'string';
            if (!in_array($type, $allowedTypes, true)) {
                return ['ok' => false, 'msg' => lang('admin/addon/config_type_err', [$type])];
            }
            $label = (isset($v['title']) && (string)$v['title'] !== '') ? (string)$v['title'] : $field;

            $rule = isset($v['rule']) ? (string)$v['rule'] : '';
            $required = (strpos($rule, 'required') !== false);
            $hasParam = array_key_exists($field, $params);

            // checkbox / selects 未选时可能不传字段
            if (!$hasParam) {
                if ($required) {
                    return ['ok' => false, 'msg' => lang('admin/addon/config_required', [$label])];
                }
                if (in_array($type, ['checkbox', 'selects'], true)) {
                    $config[$k]['value'] = '';
                }
                continue;
            }

            $raw = $params[$field];
            if ($type === 'array') {
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($raw)) {
                    return ['ok' => false, 'msg' => lang('admin/addon/config_invalid', [$label])];
                }
                $config[$k]['value'] = $raw;
                continue;
            }

            if (is_array($raw)) {
                // checkbox / selects
                $vals = [];
                foreach ($raw as $one) {
                    $one = is_scalar($one) ? (string)$one : '';
                    if ($one === '') {
                        continue;
                    }
                    $vals[] = $one;
                }
                if ($required && empty($vals)) {
                    return ['ok' => false, 'msg' => lang('admin/addon/config_required', [$label])];
                }
                if (in_array($type, ['checkbox', 'selects', 'radio', 'select'], true)) {
                    $options = isset($v['content']) && is_array($v['content']) ? $v['content'] : [];
                    foreach ($vals as $one) {
                        if (!array_key_exists($one, $options)) {
                            return ['ok' => false, 'msg' => lang('admin/addon/config_option_err', [$label])];
                        }
                    }
                }
                $config[$k]['value'] = implode(',', $vals);
                continue;
            }

            $value = (string)$raw;
            if ($required && trim($value) === '') {
                return ['ok' => false, 'msg' => lang('admin/addon/config_required', [$label])];
            }

            if ($type === 'number' && $value !== '' && !is_numeric($value)) {
                return ['ok' => false, 'msg' => lang('admin/addon/config_invalid', [$label])];
            }
            if ($type === 'bool') {
                $value = ($value === '1' || $value === 'true' || $value === 'on') ? '1' : '0';
            }
            if (in_array($type, ['select', 'radio'], true) && $value !== '') {
                $options = isset($v['content']) && is_array($v['content']) ? $v['content'] : [];
                if (!array_key_exists($value, $options)) {
                    return ['ok' => false, 'msg' => lang('admin/addon/config_option_err', [$label])];
                }
            }
            // 简单长度上限，防异常超大 POST
            if (strlen($value) > 65535) {
                return ['ok' => false, 'msg' => lang('admin/addon/config_invalid', [$label])];
            }
            $config[$k]['value'] = $value;
        }

        return ['ok' => true, 'msg' => 'ok', 'config' => $config];
    }
}
