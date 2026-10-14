<?php
namespace app\common\util;

/**
 * 主题设计预览运行时：draft 模板解析、站点主题回退、自动脚手架。
 */
class ThemePreviewRuntime
{
    /**
     * 当前激活主题的 template 目录绝对路径。
     *
     * @return string
     */
    public static function themeTemplateDir()
    {
        $tpl = isset($GLOBALS['config']['site']['template_dir'])
            ? (string) $GLOBALS['config']['site']['template_dir'] : '';
        if ($tpl === '' || !defined('ROOT_PATH')) {
            return '';
        }

        return ROOT_PATH . 'template/' . $tpl . '/';
    }

    /**
     * 预览模式下若存在编译草稿 twig，则返回带 .draft 后缀的模板名。
     *
     * @param string $tpl 如 vod/type、index/index
     * @return string
     */
    public static function resolveTemplate($tpl)
    {
        $tpl = str_replace('\\', '/', trim((string) $tpl));
        if ($tpl === '' || !ThemePreviewAuth::isActive()) {
            return $tpl;
        }
        if (substr($tpl, -6) === '.draft') {
            return $tpl;
        }
        $dir = self::themeTemplateDir();
        if ($dir === '') {
            return $tpl;
        }
        $parts = explode('/', $tpl, 2);
        if (count($parts) < 2) {
            return $tpl;
        }
        $file = $dir . 'html/' . $parts[0] . '/' . $parts[1] . '.draft.twig';
        if (is_file($file)) {
            return $tpl . '.draft';
        }

        return $tpl;
    }

    /**
     * 预览主题是否包含某页面模板（.twig 或 .draft.twig）。
     *
     * @param string $themeDir 主题目录名
     * @param string $tpl 如 vod/show、vod/play.draft
     * @param string $htmlDir
     * @return bool
     */
    public static function themeHasPageTpl($themeDir, $tpl, $htmlDir = 'html')
    {
        $themeDir = trim((string) $themeDir);
        $tpl = str_replace('\\', '/', trim((string) $tpl));
        $htmlDir = trim((string) $htmlDir);
        if ($themeDir === '' || $tpl === '' || $htmlDir === '' || !defined('ROOT_PATH')) {
            return false;
        }
        $parts = explode('/', $tpl, 2);
        if (count($parts) < 2) {
            return false;
        }
        $base = ROOT_PATH . 'template/' . $themeDir . '/' . $htmlDir . '/' . $parts[0] . '/' . $parts[1];
        if (substr($base, -6) === '.draft') {
            return is_file($base . '.twig');
        }

        return is_file($base . '.twig') || is_file($base . '.draft.twig');
    }

    /**
     * 预览主题未脚手架某页时，回退到站点激活主题渲染（避免白屏）。
     *
     * @param string $tpl 已解析 draft 后缀的模板名
     * @return bool
     */
    public static function shouldUseSiteTheme($tpl)
    {
        if (!ThemePreviewAuth::isActive()) {
            return false;
        }
        $preview = isset($GLOBALS['config']['site']['template_dir'])
            ? (string) $GLOBALS['config']['site']['template_dir'] : '';
        $site = isset($GLOBALS['mac_td_site_template_dir'])
            ? (string) $GLOBALS['mac_td_site_template_dir'] : '';
        if ($preview === '' || $site === '' || $preview === $site) {
            return false;
        }
        $htmlDir = isset($GLOBALS['mac_td_site_html_dir'])
            ? (string) $GLOBALS['mac_td_site_html_dir'] : 'html';
        $check = preg_replace('/\.draft$/', '', (string) $tpl);
        if (self::themeHasPageTpl($preview, $check, 'html')) {
            return false;
        }

        return self::themeHasPageTpl($site, $check, $htmlDir);
    }

    /**
     * 站点激活主题的 view_path（相对项目根）。
     *
     * @return string
     */
    public static function siteThemeViewPath()
    {
        $site = isset($GLOBALS['mac_td_site_template_dir'])
            ? (string) $GLOBALS['mac_td_site_template_dir'] : '';
        $htmlDir = isset($GLOBALS['mac_td_site_html_dir'])
            ? (string) $GLOBALS['mac_td_site_html_dir'] : 'html';
        if ($site === '') {
            return '';
        }

        return 'template/' . $site . '/' . $htmlDir . '/';
    }

    /**
     * 预览模式下为缺失页面自动生成 JSON 种子并编译 draft.twig。
     *
     * @param string $tpl 如 vod/show
     * @return bool 是否已有或成功脚手架
     */
    public static function ensurePageScaffold($tpl)
    {
        static $done = array();
        if (!ThemePreviewAuth::isActive()) {
            return false;
        }
        $tpl = preg_replace('/\.draft$/', '', str_replace('\\', '/', trim((string) $tpl)));
        if ($tpl === '') {
            return false;
        }
        if (isset($done[$tpl])) {
            return $done[$tpl];
        }
        $preview = isset($GLOBALS['config']['site']['template_dir'])
            ? (string) $GLOBALS['config']['site']['template_dir'] : '';
        $site = isset($GLOBALS['mac_td_site_template_dir'])
            ? (string) $GLOBALS['mac_td_site_template_dir'] : '';
        if ($preview === '' || ($site !== '' && $preview === $site)) {
            $done[$tpl] = false;
            return false;
        }
        $route = PageRegistry::normalizeRoute($tpl);
        if (!PageRegistry::isEditableRoute($route)) {
            $done[$tpl] = false;
            return false;
        }
        $themeDir = self::themeTemplateDir();
        if ($themeDir === '') {
            $done[$tpl] = false;
            return false;
        }
        if (self::themeHasPageTpl($preview, $route, 'html')) {
            $done[$tpl] = true;
            return true;
        }
        $scaffolder = new PageScaffolder();
        $ok = $scaffolder->ensureScaffolded($themeDir, $route);
        $done[$tpl] = $ok;

        return $ok;
    }

    /**
     * 页面 Twig 模板名：预览且存在编译草稿时用 {route}.draft。
     *
     * @param string $route 如 index/index、vod/type
     * @return string
     */
    public static function pageView($route)
    {
        $route = str_replace('\\', '/', trim((string) $route, '/'));
        $route = PageRegistry::normalizeRoute($route);
        if (!ThemePreviewAuth::isActive()) {
            return $route;
        }

        return self::resolveTemplate($route);
    }

    /**
     * 首页 Twig 模板名。
     *
     * @return string
     */
    public static function homepageIndexView()
    {
        return self::pageView('index/index');
    }
}
