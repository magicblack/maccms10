<?php
namespace app\common\util;

/**
 * Page Builder 风格包（preset）加载与应用。
 */
class ThemePreset
{
    const DEFAULT_ID = 'preset-clean';

    /** @var array<int,string> */
    private static $catalog = array('preset-clean', 'preset-sidebar', 'preset-np2');

    /** @var array<string,array> */
    private static $cache = array();

    /**
     * @return array<int,string>
     */
    public static function catalog()
    {
        return self::$catalog;
    }

    /**
     * @param string $id
     * @return string
     */
    public static function normalizeId($id)
    {
        $id = strtolower(trim((string) $id));
        if ($id === '' || !in_array($id, self::$catalog, true)) {
            return self::DEFAULT_ID;
        }
        return $id;
    }

    /**
     * @param string $id
     * @return array|null
     */
    public static function load($id)
    {
        $id = self::normalizeId($id);
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }
        $dir = self::presetsRoot() . $id . '/';
        $file = $dir . 'preset.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        if (empty($data['id'])) {
            $data['id'] = $id;
        }
        if (empty($data['scaffold']) || !is_array($data['scaffold'])) {
            $data['scaffold'] = array();
        }
        $data['scaffold'] = self::normalizeScaffold($data['scaffold']);
        if (empty($data['assets']) || !is_array($data['assets'])) {
            $data['assets'] = array('shell_css' => 'pb-shell.css');
        }
        self::$cache[$id] = $data;
        return $data;
    }

    /**
     * @param string $themeDir
     * @return array{pb_enabled:bool,pb_shell:string,pb_preset:string}
     */
    public static function readPbMeta($themeDir)
    {
        $out = array(
            'pb_enabled' => false,
            'pb_shell' => '',
            'pb_preset' => '',
        );
        $ini = self::readIniMap($themeDir);
        if (isset($ini['pb_enabled'])) {
            $out['pb_enabled'] = in_array(strtolower((string) $ini['pb_enabled']), array('1', 'true', 'yes', 'on'), true);
        }
        if (isset($ini['pb_shell'])) {
            $out['pb_shell'] = strtolower(trim((string) $ini['pb_shell']));
        }
        if (isset($ini['pb_preset'])) {
            $out['pb_preset'] = self::normalizeId($ini['pb_preset']);
        }
        // 兼容：pb-sandbox 在未写 pb_* 前仍视为 thin 壳
        if (!$out['pb_enabled'] && basename(rtrim((string) $themeDir, '/')) === 'pb-sandbox') {
            $out['pb_enabled'] = true;
            $out['pb_shell'] = 'thin';
            if ($out['pb_preset'] === '') {
                $out['pb_preset'] = self::DEFAULT_ID;
            }
        }
        return $out;
    }

    /**
     * @param string $themeDir
     * @return array|null
     */
    public static function fromThemeDir($themeDir)
    {
        $meta = self::readPbMeta($themeDir);
        if (!$meta['pb_enabled']) {
            return null;
        }
        return self::load($meta['pb_preset']);
    }

    /**
     * @param string $themeDir
     * @return array
     */
    public static function scaffoldFlags($themeDir)
    {
        $preset = self::fromThemeDir($themeDir);
        if ($preset === null) {
            return self::normalizeScaffold(array());
        }
        return isset($preset['scaffold']) && is_array($preset['scaffold'])
            ? $preset['scaffold'] : self::normalizeScaffold(array());
    }

    /**
     * @param string $themeDir
     * @return bool
     */
    public static function isThinShell($themeDir)
    {
        $meta = self::readPbMeta($themeDir);
        if (!$meta['pb_enabled']) {
            return false;
        }
        if ($meta['pb_shell'] === 'native') {
            return false;
        }
        return $meta['pb_shell'] === 'thin' || $meta['pb_shell'] === '';
    }

    /**
     * @param string $themeDir
     * @return bool
     */
    public static function isPbEnabled($themeDir)
    {
        $meta = self::readPbMeta($themeDir);
        return !empty($meta['pb_enabled']);
    }

    /**
     * NP2 splash 首页/频道默认 10 宫格广告项（PB items 种子）。
     *
     * @return array<int, array<string, string>>
     */
    public static function np2SplashAdItems()
    {
        $labels = array(
            '电影', '连续剧', '综艺', '动漫', '短剧',
            '纪录片', '排行', '专题', '更新', '搜索',
        );
        $out = array();
        for ($i = 0; $i < 10; $i++) {
            $n = $i + 1;
            $out[] = array(
                'id' => 'ad_' . $n,
                'label' => $labels[$i],
                'href' => '',
                'cover' => '',
            );
        }
        return $out;
    }

    /**
     * 风格包首页种子路径；缺文件时回退 default-homepage.json。
     * @param string $presetId
     * @return string
     */
    public static function homepageSeedFile($presetId)
    {
        $presetId = self::normalizeId($presetId);
        $file = self::presetsRoot() . $presetId . '/homepage.json';
        if (is_file($file)) {
            return $file;
        }
        return self::presetsRoot() . 'default-homepage.json';
    }

    /**
     * 单页种子路径：presets/{preset}/{route-slug}.json → presets/_shared/{route-slug}.json
     *
     * @param string $route    如 art/index
     * @param string $presetId
     * @return string 空串表示无种子
     */
    public static function routeSeedFile($route, $presetId = '')
    {
        if (!class_exists('\app\common\util\PageRegistry')) {
            return '';
        }
        $route = \app\common\util\PageRegistry::normalizeRoute($route);
        if ($route === '') {
            return '';
        }
        $slug = str_replace('/', '-', $route);
        $presetId = self::normalizeId($presetId);
        if ($presetId !== '') {
            $file = self::presetsRoot() . $presetId . '/' . $slug . '.json';
            if (is_file($file)) {
                return $file;
            }
        }
        $shared = self::presetsRoot() . '_shared/' . $slug . '.json';
        return is_file($shared) ? $shared : '';
    }

    /**
     * @param string $presetId
     * @param string $themeDir
     * @return bool
     */
    public static function applyPresetToTheme($presetId, $themeDir)
    {
        $presetId = self::normalizeId($presetId);
        $preset = self::load($presetId);
        if ($preset === null) {
            return false;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if (!is_dir($themeDir)) {
            return false;
        }
        if (!self::mergeShellCss($presetId, $themeDir)) {
            return false;
        }
        if (self::isNp2Preset($presetId)) {
            self::syncNp2StarterShell($themeDir);
            self::syncNp2PresetAssetsToTheme($themeDir);
        }
        $assets = isset($preset['assets']) && is_array($preset['assets']) ? $preset['assets'] : array();
        if (!empty($assets['tokens_css'])) {
            $src = self::presetsRoot() . $presetId . '/' . $assets['tokens_css'];
            $dest = $themeDir . 'asset/css/pb-tokens-overlay.css';
            if (is_file($src)) {
                self::ensureDir(dirname($dest));
                @copy($src, $dest);
            }
        } else {
            $overlay = $themeDir . 'asset/css/pb-tokens-overlay.css';
            if (is_file($overlay)) {
                @unlink($overlay);
            }
        }
        return true;
    }

    /**
     * @param string $presetId
     * @param string $themeDir
     * @return bool
     */
    public static function mergeShellCss($presetId, $themeDir)
    {
        $presetId = self::normalizeId($presetId);
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $base = self::presetsRoot() . '_shared/pb-shell-base.css';
        $preset = self::presetsRoot() . $presetId . '/pb-shell.css';
        if (!is_file($base) || !is_file($preset)) {
            return false;
        }
        $css = (string) @file_get_contents($base);
        $css .= "\n/* preset: " . $presetId . " */\n";
        $css .= (string) @file_get_contents($preset);
        $dest = $themeDir . 'asset/css/pb-shell.css';
        self::ensureDir(dirname($dest));
        return @file_put_contents($dest, $css) !== false;
    }

    /**
     * thin 壳主题确保 asset/css/pb-shell.css 存在。
     * @param string $themeDir
     * @return bool
     */
    public static function ensureShellAssets($themeDir)
    {
        if (!self::isThinShell($themeDir)) {
            return true;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $shell = $themeDir . 'asset/css/pb-shell.css';
        if (is_file($shell)) {
            return true;
        }
        $meta = self::readPbMeta($themeDir);
        return self::applyPresetToTheme($meta['pb_preset'], $themeDir);
    }

    /**
     * 编译 thin 页时注入的 theme_styles 片段（Twig 源文本）。
     * @param string $themeDir 绝对路径
     * @return string
     */
    public static function emitShellTagsForCompile($themeDir)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        self::ensureShellAssets($themeDir);
        $shellFile = $themeDir . 'asset/css/pb-shell.css';
        $overlayFile = $themeDir . 'asset/css/pb-tokens-overlay.css';
        $out = '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/tokens.css">' . "\n"
            . '{% set _pb_td_override = mac_theme_override_css_href() %}' . "\n"
            . '{% if _pb_td_override %}<link rel="stylesheet" href="{{ _pb_td_override }}">' . "\n{% endif %}\n";
        if (is_file($overlayFile)) {
            $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/pb-tokens-overlay.css">' . "\n";
        }
        if (is_file($shellFile)) {
            $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/pb-shell.css">' . "\n";
        }
        if (self::isNp2Preset(self::readPbMeta($themeDir)['pb_preset'])) {
            if (is_file($themeDir . 'asset/css/np2-app.css')) {
                $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/np2-app.css">' . "\n";
            }
            if (is_file($themeDir . 'asset/css/np2-pb-skin.css')) {
                $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/np2-pb-skin.css">' . "\n";
            }
        }
        if (!is_file($shellFile) && is_file($themeDir . 'asset/css/sandbox.css')) {
            $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}asset/css/sandbox.css">' . "\n";
        }
        return $out;
    }

    /**
     * @param string $themeDir
     * @param string $presetId
     * @param array  $baseIni 可选 name/version 等
     * @return bool
     */
    public static function writePbIni($themeDir, $presetId, array $baseIni = array())
    {
        $presetId = self::normalizeId($presetId);
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $file = $themeDir . 'info.ini';
        $map = self::readIniMap($themeDir);
        foreach ($baseIni as $k => $v) {
            $map[$k] = $v;
        }
        $map['engine'] = isset($map['engine']) ? $map['engine'] : 'twig';
        $map['pb_enabled'] = '1';
        $map['pb_shell'] = 'thin';
        $map['pb_preset'] = $presetId;
        $lines = array();
        foreach ($map as $k => $v) {
            $lines[] = $k . "\t= " . str_replace(array("\r", "\n"), ' ', (string) $v);
        }
        return @file_put_contents($file, implode("\n", $lines) . "\n") !== false;
    }

    /**
     * @param string $presetId
     * @return string
     */
    public static function defaultSchemeForPreset($presetId)
    {
        $preset = self::load($presetId);
        if ($preset === null || empty($preset['default_scheme'])) {
            return 'indigo';
        }
        return (string) $preset['default_scheme'];
    }

    /**
     * @param string $presetId
     * @return bool
     */
    public static function isNp2Preset($presetId)
    {
        return self::normalizeId($presetId) === 'preset-np2';
    }

    /**
     * NP2 等：顶栏组件放在 site_header 区（非 content 首部）。
     *
     * @param array $scaffold
     * @return bool
     */
    public static function usesSiteHeaderAppChrome(array $scaffold)
    {
        return isset($scaffold['site_header_chrome'])
            && (string) $scaffold['site_header_chrome'] === 'app_top_chrome';
    }

    /**
     * 非 NP2 风格包的建站复制源（_shared/starter）。
     * preset-np2 须走 materializeNp2ThemeSkeleton()，不得经此路径。
     *
     * @param string $presetId
     * @return string
     */
    public static function starterDirForPreset($presetId)
    {
        $presetId = self::normalizeId($presetId);
        if (!defined('ROOT_PATH') || self::isNp2Preset($presetId)) {
            return '';
        }
        $starter = ROOT_PATH . 'template/_shared/starter';
        return is_dir($starter) ? $starter : '';
    }

    /**
     * preset-np2 静态图在 kit 预设内（进主库）。
     *
     * @return string 绝对路径，带尾斜杠；不可用返回空串
     */
    public static function np2KitAssetsImgDir()
    {
        if (!defined('ROOT_PATH')) {
            return '';
        }
        $dir = self::presetsRoot() . 'preset-np2/assets/img/';
        return is_dir($dir) ? $dir : '';
    }

    /**
     * preset-np2 NP2 壳层片段（layout/lib/css/js），建站复制源缺失时兜底。
     *
     * @return string
     */
    public static function np2KitStarterDir()
    {
        if (!defined('ROOT_PATH')) {
            return '';
        }
        $dir = self::presetsRoot() . 'preset-np2/starter/';
        return is_dir($dir) ? $dir : '';
    }

    /**
     * preset-np2：从 _shared/starter + kit preset-np2/starter 直接物化到新主题目录。
     *
     * @param string $destDir 目标主题根（须不存在或为空）
     * @return bool
     */
    public static function materializeNp2ThemeSkeleton($destDir)
    {
        if (!defined('ROOT_PATH')) {
            return false;
        }
        $destDir = rtrim((string) $destDir, '/') . '/';
        $shared = ROOT_PATH . 'template/_shared/starter/';
        if (!is_dir($shared)) {
            return false;
        }
        if (is_dir($destDir)) {
            $dh = @opendir($destDir);
            if ($dh) {
                while (($name = readdir($dh)) !== false) {
                    if ($name !== '.' && $name !== '..') {
                        closedir($dh);
                        return false;
                    }
                }
                closedir($dh);
            }
        } else {
            self::ensureDir($destDir);
        }
        if (!class_exists('\app\common\util\Dir')) {
            return false;
        }
        \app\common\util\Dir::copyDir($shared, rtrim($destDir, '/'));
        if (!is_file($destDir . 'html/layout/base.twig')) {
            return false;
        }
        $kitStarter = self::np2KitStarterDir();
        if ($kitStarter !== '') {
            self::copyKitStarterOverlay($kitStarter, $destDir);
        }
        self::syncNp2ShellFilesToDir($destDir);
        $kitImg = self::np2KitAssetsImgDir();
        if ($kitImg !== '') {
            self::syncNp2StarterImgTree($kitImg, $destDir . 'asset/img/');
        }
        $ref = self::np2ReferenceThemeDir();
        if ($ref !== '' && is_dir($ref . 'asset/img/')) {
            self::syncNp2StarterImgTree($ref . 'asset/img/', $destDir . 'asset/img/', true);
        }
        return true;
    }

    /**
     * 将 preset-np2 kit 静态图同步到主题 asset/img/（仅补缺）。
     *
     * @param string $themeDir
     * @return bool
     */
    public static function syncNp2PresetAssetsToTheme($themeDir)
    {
        $kitImg = self::np2KitAssetsImgDir();
        if ($kitImg === '') {
            return false;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';

        return self::syncNp2StarterImgTree($kitImg, $themeDir . 'asset/img/');
    }

    /**
     * np2 建站必备静态图（相对 asset/img/）。
     *
     * @return array<int,string>
     */
    public static function np2StarterRequiredImageFiles()
    {
        return array(
            'banners/vip.webp',
            'dialogs/crown.webp',
            'ui/empty.webp',
            'icons/vip-crown.webp',
            'icons/gift.webp',
            'icons/search.webp',
            'logo.webp',
        );
    }

    /**
     * 开发参考主题 template/np2（不进主库，本地兜底源）。
     *
     * @return string
     */
    public static function np2ReferenceThemeDir()
    {
        if (!defined('ROOT_PATH')) {
            return '';
        }
        $dir = ROOT_PATH . 'template/np2/';
        return is_dir($dir) ? $dir : '';
    }

    /**
     * @return array<int,string>
     */
    public static function np2ShellRelativeFiles()
    {
        return array(
            'html/layout/base.twig',
            'html/layout/auth.twig',
            'html/lib/np2_mine_hub.twig',
            'html/lib/np2_home_header.twig',
            'html/lib/np2_home_ads.twig',
            'html/lib/np2_member_icon.twig',
            'html/label/short.twig',
            'html/user/buy.twig',
            'html/user/buy.draft.twig',
            'html/user/buy.json',
            'asset/css/np2-app.css',
            'asset/css/np2-pb-skin.css',
            'asset/js/np2-app.js',
            'asset/js/np2-play.js',
            'asset/js/np2-age.js',
        );
    }

    /**
     * preset-np2：从 kit 同步壳层 layout 与 NP2 皮 CSS（建站复制后兜底，避免落到通用 starter 薄壳）。
     *
     * @param string $themeDir 主题根目录（绝对路径，可带尾斜杠）
     * @return bool
     */
    public static function syncNp2StarterShell($themeDir)
    {
        if (!defined('ROOT_PATH')) {
            return false;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if (!is_dir($themeDir)) {
            return false;
        }
        $ok = self::syncNp2ShellFilesToDir($themeDir);
        if (!self::syncNp2StarterPbComponentsToTheme($themeDir)) {
            $ok = false;
        }
        $imgDest = $themeDir . 'asset/img/';
        if (!self::syncNp2PresetAssetsToTheme($themeDir)) {
            $ok = false;
        }
        $ref = self::np2ReferenceThemeDir();
        if ($ref !== '' && is_dir($ref . 'asset/img/')) {
            if (!self::syncNp2StarterImgTree($ref . 'asset/img/', $imgDest, true)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * preset-np2：将 starter 内 NP2 专用 PB 组件覆写同步到活主题（member_nav webp 图标、user_member_hub 等）。
     *
     * @param string $themeDir
     * @return bool
     */
    public static function syncNp2StarterPbComponentsToTheme($themeDir)
    {
        $kitStarter = self::np2KitStarterDir();
        if ($kitStarter === '') {
            return true;
        }
        $srcRoot = $kitStarter . 'page-builder-components/';
        if (!is_dir($srcRoot)) {
            return true;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $destRoot = $themeDir . 'page-builder-components/';
        $ok = true;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($srcRoot));
            $dest = $destRoot . $rel;
            if ($item->isDir()) {
                self::ensureDir($dest);
                continue;
            }
            // preset 专用 PB 覆写必须盖过 kit syncToTheme 的默认组件，不做体积保护。
            self::ensureDir(dirname($dest));
            if (@copy($item->getPathname(), $dest) === false) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * 将 NP2 壳层 twig/css/js 同步到目标目录（空文件或缺失才写入，避免盖站长改过的壳）。
     *
     * @param string $destDir 主题根目录
     * @return bool
     */
    public static function syncNp2ShellFilesToDir($destDir)
    {
        $destDir = rtrim((string) $destDir, '/') . '/';
        if (!is_dir($destDir)) {
            return false;
        }
        $ok = true;
        foreach (self::np2ShellRelativeFiles() as $rel) {
            $src = self::resolveNp2ShellSource($rel);
            if ($src === '') {
                continue;
            }
            $dest = $destDir . $rel;
            if (!self::shouldWriteNp2ShellFile($dest, $src)) {
                continue;
            }
            self::ensureDir(dirname($dest));
            if (@copy($src, $dest) === false) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * @param string $rel 相对主题根的路径
     * @return string 绝对路径；无可用源返回空串
     */
    private static function resolveNp2ShellSource($rel)
    {
        $rel = ltrim((string) $rel, '/');
        $candidates = array();
        $kit = self::np2KitStarterDir();
        if ($kit !== '') {
            $candidates[] = $kit . $rel;
        }
        $ref = self::np2ReferenceThemeDir();
        if ($ref !== '') {
            $candidates[] = $ref . $rel;
        }
        foreach ($candidates as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @param string $dest
     * @param string $src
     * @return bool
     */
    private static function shouldWriteNp2ShellFile($dest, $src)
    {
        if (!is_file($dest)) {
            return true;
        }
        $destSize = @filesize($dest);
        if ($destSize === false || (int) $destSize === 0) {
            return true;
        }
        $srcSize = @filesize($src);
        if ($srcSize !== false && (int) $srcSize > (int) $destSize) {
            return true;
        }

        return false;
    }

    /**
     * 将 kit preset-np2/starter 下的文件覆盖进主题目录（仅补缺 shell 文件）。
     *
     * @param string $kitStarter
     * @param string $starterDest
     * @return bool
     */
    private static function copyKitStarterOverlay($kitStarter, $starterDest)
    {
        $kitStarter = rtrim((string) $kitStarter, '/') . '/';
        $starterDest = rtrim((string) $starterDest, '/') . '/';
        if (!is_dir($kitStarter) || !is_dir($starterDest)) {
            return false;
        }
        $ok = true;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($kitStarter, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($kitStarter));
            $dest = $starterDest . $rel;
            if ($item->isDir()) {
                self::ensureDir($dest);
                continue;
            }
            if (is_file($dest) && !self::shouldWriteNp2ShellFile($dest, $item->getPathname())) {
                continue;
            }
            self::ensureDir(dirname($dest));
            if (@copy($item->getPathname(), $dest) === false) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * 递归同步 preset-np2 静态图到主题 asset/img/（含 icons/、ads/ 等子目录），仅补缺不覆盖已有文件。
     *
     * @param string $srcDir
     * @param string $destDir
     * @param bool   $replaceEmpty 为 true 时覆盖 0 字节占位文件
     * @return bool
     */
    private static function syncNp2StarterImgTree($srcDir, $destDir, $replaceEmpty = false)
    {
        $srcDir = rtrim((string) $srcDir, '/') . '/';
        $destDir = rtrim((string) $destDir, '/') . '/';
        if (!is_dir($srcDir)) {
            return false;
        }
        self::ensureDir($destDir);
        $ok = true;
        $dh = @opendir($srcDir);
        if (!$dh) {
            return false;
        }
        while (($name = readdir($dh)) !== false) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $from = $srcDir . $name;
            $to = $destDir . $name;
            if (is_dir($from)) {
                if (!self::syncNp2StarterImgTree($from, $to)) {
                    $ok = false;
                }
                continue;
            }
            if (!is_file($from)) {
                continue;
            }
            if (is_file($to)) {
                if (!$replaceEmpty) {
                    continue;
                }
                $sz = @filesize($to);
                if ($sz !== false && (int) $sz > 0) {
                    continue;
                }
            }
            if (@copy($from, $to) === false) {
                $ok = false;
            }
        }
        closedir($dh);
        return $ok;
    }

    private static function normalizeScaffold(array $scaffold)
    {
        $chrome = isset($scaffold['site_header_chrome'])
            ? trim((string) $scaffold['site_header_chrome']) : 'site_nav';
        if ($chrome !== 'app_top_chrome') {
            $chrome = 'site_nav';
        }
        return array(
            'list_sidebar_rank' => !empty($scaffold['list_sidebar_rank']),
            'detail_sidebar_rank' => !empty($scaffold['detail_sidebar_rank']),
            'home_sidebar_rank' => !empty($scaffold['home_sidebar_rank']),
            'shell_left_nav' => !empty($scaffold['shell_left_nav']),
            'site_header_chrome' => $chrome,
            'hide_pb_footer' => !empty($scaffold['hide_pb_footer']),
            'hide_pb_bottom_nav' => !empty($scaffold['hide_pb_bottom_nav']),
            'use_np2_tabbar' => !empty($scaffold['use_np2_tabbar']),
            'manga_home_spotlight_show_hero' => array_key_exists('manga_home_spotlight_show_hero', $scaffold)
                ? !empty($scaffold['manga_home_spotlight_show_hero'])
                : true,
        );
    }

    /**
     * @param string $themeDir
     * @return array<string,string>
     */
    private static function readIniMap($themeDir)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $file = $themeDir . 'info.ini';
        $map = array();
        if (!is_file($file)) {
            return $map;
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return $map;
        }
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($k, $v) = explode('=', $line, 2);
            $map[trim($k)] = trim($v);
        }
        return $map;
    }

    private static function presetsRoot()
    {
        return ROOT_PATH . 'template/_shared/kit/presets/';
    }

    /**
     * @param string $dir
     * @return bool
     */
    private static function ensureDir($dir)
    {
        if (is_dir($dir)) {
            return true;
        }
        return @mkdir($dir, 0755, true) || is_dir($dir);
    }
}
