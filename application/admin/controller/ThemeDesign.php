<?php
namespace app\admin\controller;

use app\common\util\ThemeDesignDraftMigrator;
use app\common\util\MemberPreviewMock;
use app\common\util\PageBuilderKit;
use app\common\util\PageRegistry;
use app\common\util\PageScaffolder;
use app\common\util\ThemePreset;
use app\common\util\ThemePreviewAuth;
use app\common\util\TwigPageCompiler;

/**
 * 主题设计控制器
 *
 * 针对当前激活的前台主题（prism）的「草稿 → 预览 → 发布」可视化设计器。
 * 设计原则：
 *   1. 编辑只写入草稿（draft），永不直接修改线上主题；
 *   2. 预览展示草稿效果；
 *   3. 发布把草稿应用到线上主题，并快照历史以便回滚。
 * 线上主题的 .html / tokens.css / base.css 永不被修改 —— 仅生成一个
 * 覆盖样式（override css）+ 一层 theme-design.json 配置叠加在其上。
 */
class ThemeDesign extends Base
{
    /** 默认主题（无法解析时回退） */
    const THEME = 'prism';

    /** 当前正在设计的主题（可由 ?theme= 选择，默认=站点激活主题/prism） */
    private $theme = 'prism';

    public function __construct()
    {
        parent::__construct();
        // admin 模块统一使用新版后台模板目录 view_new/。
        // Init 会把 template.view_path 指向前台模板目录，模块相对 fetch 需显式指定，否则会白屏。
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');

        $this->theme = $this->resolveTheme();
    }

    /**
     * 解析当前设计主题：
     *   1) 合法的 ?theme= 请求参数；
     *   2) 站点激活主题（site.template_dir，若为有效前台主题）；
     *   3) prism；
     *   4) 首个可用前台主题。
     */
    private function resolveTheme()
    {
        $themes = $this->availableThemes();
        $valid = array();
        foreach ($themes as $t) {
            $valid[$t['dir']] = true;
        }
        $req = trim((string)$this->request->param('theme', ''));
        if ($req !== '' && isset($valid[$req])) {
            return $req;
        }
        $active = isset($GLOBALS['config']['site']['template_dir']) ? (string)$GLOBALS['config']['site']['template_dir'] : '';
        if ($active !== '' && isset($valid[$active])) {
            return $active;
        }
        if (isset($valid['prism'])) {
            return 'prism';
        }
        return !empty($themes) ? $themes[0]['dir'] : self::THEME;
    }

    /**
     * 可设计的前台主题列表（template/<dir>/html 存在者）。
     * 每项：dir / label / active(站点激活) / designable(含 asset/css 令牌样式) / selected(当前设计中)。
     */
    private function availableThemes()
    {
        $base = ROOT_PATH . 'template/';
        $active = isset($GLOBALS['config']['site']['template_dir']) ? (string)$GLOBALS['config']['site']['template_dir'] : '';
        $list = array();
        if (is_dir($base)) {
            foreach (glob($base . '*', GLOB_ONLYDIR) as $dir) {
                $name = basename($dir);
                if (!is_dir($dir . '/html')) {
                    continue; // 仅前台主题
                }
                $list[] = array(
                    'dir'        => $name,
                    'label'      => $this->themeLabel($name),
                    'active'     => ($name === $active),
                    'designable' => is_dir($dir . '/asset/css'),
                    'selected'   => ($name === $this->theme),
                );
            }
        }
        return $list;
    }

    /** 主题目录名 -> 展示名 */
    private function themeLabel($dir)
    {
        $map = array('prism' => 'Prism', 'default' => 'Default');
        return isset($map[$dir]) ? $map[$dir] : ucfirst($dir);
    }

    // ───────────────────────── 路径辅助 ─────────────────────────

    /** 主题设计数据目录（草稿/发布/历史） */
    private function dataDir()
    {
        return ROOT_PATH . 'application/data/theme_design/' . $this->theme . '/';
    }

    /** 草稿配置文件 */
    private function draftFile()
    {
        return $this->dataDir() . 'draft.json';
    }

    /** 已发布配置文件 */
    private function publishedFile()
    {
        return $this->dataDir() . 'published.json';
    }

    /** 历史快照目录 */
    private function historyDir()
    {
        return $this->dataDir() . 'history/';
    }

    /** prism 主题根目录 */
    private function themeDir()
    {
        return ROOT_PATH . 'template/' . $this->theme . '/';
    }

    /**
     * 建站复制源目录名，禁止作为站长主题实例目录发布。
     *
     * @param string $dir
     * @return bool
     */
    private function isReservedThemeDir($dir)
    {
        $dir = strtolower(trim((string) $dir));
        $reserved = array('np2-starter', 'pb-starter', 'starter');
        return in_array($dir, $reserved, true);
    }

    /** 当前设计主题的首页 page 默认树（优先统一组件库 presets） */
    private function homepageDefaultPage($presetId = '')
    {
        $kit = new PageBuilderKit();
        if ($presetId === '') {
            $meta = ThemePreset::readPbMeta($this->themeDir());
            if (!empty($meta['pb_preset'])) {
                $presetId = $meta['pb_preset'];
            }
        }
        $page = $kit->defaultPage($presetId);
        if (!empty($page['blocks'])) {
            return $page;
        }
        if (!empty($page['regions']) && is_array($page['regions'])) {
            return $page;
        }

        $empty = array(
            'template' => 'index/index',
            'layout' => 'base',
            'blocks' => array(),
        );
        $file = $this->themeDir() . 'html/index/index.json';
        if (!is_file($file)) {
            return $empty;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $empty;
        }
        $arr = json_decode($raw, true);
        if (!is_array($arr)) {
            return $empty;
        }
        return array(
            'template' => isset($arr['template']) ? (string) $arr['template'] : 'index/index',
            'layout' => isset($arr['layout']) ? (string) $arr['layout'] : 'base',
            'blocks' => isset($arr['blocks']) && is_array($arr['blocks']) ? $arr['blocks'] : array(),
        );
    }

    /**
     * 打开搭建器时按需同步 kit → 主题 page-builder-components。
     * 版本落后时只补缺失文件，禁止 upgrade 覆盖主题已改的 style.css / script.js / template.twig。
     * （全量覆盖仅允许在发布 writeArtifacts 时显式 upgrade=true。）
     */
    private function ensurePageBuilderKitSynced($upgrade = false, array $page = null)
    {
        // Think/Smarty 主题（如 prism）不走 PB kit，避免污染 page-builder-components/
        if (!$this->isTwigTheme()) {
            return true;
        }
        $kit = new PageBuilderKit();
        $themeDir = $this->themeDir();
        $onlyIds = null;
        if ($kit->kitVersion() > $kit->themeSyncedVersion($themeDir)) {
            // 补全新组件 / 缺文件；绝不因版本号强制 upgrade 盖掉主题定制
            $onlyIds = null;
            $upgrade = false;
        } elseif (is_array($page)) {
            $onlyIds = $kit->collectPageComponentIds($page);
        }
        return $kit->syncToTheme($themeDir, $upgrade, $onlyIds);
    }

    /** 非 Twig 主题：用 homepage.sections 搭建（prism 等） */
    private function isLegacySectionsTheme()
    {
        return !$this->isTwigTheme();
    }

    /** 首页 sections 默认（目录顺序，全部开启） */
    private function homepageDefaultSections()
    {
        $sections = array();
        foreach ($this->sectionCatalog() as $key => $label) {
            $sections[] = array('key' => $key, 'enabled' => true);
        }
        return $sections;
    }

    /**
     * 从主题根 theme-design[.draft].json 读取遗留 sections（无 draft.json 时播种）。
     * @return array|null
     */
    private function loadLegacyDesignSections()
    {
        foreach (array('theme-design.draft.json', 'theme-design.json') as $name) {
            $file = $this->themeDir() . $name;
            if (!is_file($file)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                continue;
            }
            $arr = json_decode($raw, true);
            if (!is_array($arr) || empty($arr['homepage']['sections']) || !is_array($arr['homepage']['sections'])) {
                continue;
            }
            $sections = $this->sanitizeGeneratedSections($arr['homepage']['sections']);
            if (!empty($sections)) {
                return $sections;
            }
        }
        return null;
    }

    /** prism 主题 css 目录 */
    private function cssDir()
    {
        return $this->themeDir() . 'asset/css/';
    }

    /** 确保目录存在 */
    private function ensureDir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_dir($dir);
    }

    // ───────────────────────── 预设数据 ─────────────────────────

    /**
     * 6 套配色预设：key => [name, colors(accent/accent_ink/bg/surface/surface_2/text/text_muted/border)]
     */
    private function schemePresets()
    {
        return array(
            'indigo' => array('name' => lang('admin/themedesign/scheme_indigo'), 'colors' => array(
                'accent' => '#4f46e5', 'accent_ink' => '#ffffff', 'bg' => '#f4f6f9', 'surface' => '#ffffff',
                'surface_2' => '#eef1f6', 'text' => '#1a1d23', 'text_muted' => '#6b7280', 'border' => '#e5e8ee',
            )),
            'ocean' => array('name' => lang('admin/themedesign/scheme_ocean'), 'colors' => array(
                'accent' => '#0ea5e9', 'accent_ink' => '#ffffff', 'bg' => '#f3f7fb', 'surface' => '#ffffff',
                'surface_2' => '#eaf2f9', 'text' => '#0f2233', 'text_muted' => '#5b7488', 'border' => '#dbe7f1',
            )),
            'sunset' => array('name' => lang('admin/themedesign/scheme_sunset'), 'colors' => array(
                'accent' => '#f97316', 'accent_ink' => '#ffffff', 'bg' => '#fbf6f2', 'surface' => '#ffffff',
                'surface_2' => '#f6ece3', 'text' => '#2a1d14', 'text_muted' => '#8a7160', 'border' => '#ecdfd3',
            )),
            'forest' => array('name' => lang('admin/themedesign/scheme_forest'), 'colors' => array(
                'accent' => '#16a34a', 'accent_ink' => '#ffffff', 'bg' => '#f3f8f4', 'surface' => '#ffffff',
                'surface_2' => '#e9f2ec', 'text' => '#14241a', 'text_muted' => '#5c7466', 'border' => '#d7e7dc',
            )),
            'rose' => array('name' => lang('admin/themedesign/scheme_rose'), 'colors' => array(
                'accent' => '#e11d48', 'accent_ink' => '#ffffff', 'bg' => '#fbf3f5', 'surface' => '#ffffff',
                'surface_2' => '#f6e7ec', 'text' => '#2a141b', 'text_muted' => '#8a6070', 'border' => '#ecd5dd',
            )),
            'slate' => array('name' => lang('admin/themedesign/scheme_slate'), 'colors' => array(
                'accent' => '#475569', 'accent_ink' => '#ffffff', 'bg' => '#f4f5f7', 'surface' => '#ffffff',
                'surface_2' => '#eceef2', 'text' => '#1a1d23', 'text_muted' => '#6b7280', 'border' => '#e2e5ea',
            )),
            'midnight' => array('name' => lang('admin/themedesign/scheme_midnight'), 'colors' => array(
                'accent' => '#e50914', 'accent_ink' => '#ffffff', 'bg' => '#0f1115', 'surface' => '#171a21',
                'surface_2' => '#1e222b', 'text' => '#e8eaed', 'text_muted' => '#9aa0a8', 'border' => '#2a2f3a',
            )),
        );
    }

    /** 5 个字体选项 value => label */
    private function fontOptions()
    {
        return array(
            "Inter, 'Noto Sans SC', system-ui, sans-serif" => lang('admin/themedesign/font_inter'),
            "'Noto Sans SC', system-ui, sans-serif" => lang('admin/themedesign/font_noto'),
            "'Microsoft YaHei','微软雅黑', system-ui, sans-serif" => lang('admin/themedesign/font_yahei'),
            "Georgia,'Noto Serif SC', serif" => lang('admin/themedesign/font_serif'),
            "KaiTi,'楷体',STKaiti,'华文楷体',KaiTi_GB2312,serif" => lang('admin/themedesign/font_kaiti'),
            "system-ui,-apple-system,'Segoe UI', sans-serif" => lang('admin/themedesign/font_system'),
        );
    }

    /** 首页区块目录 key => label（顺序即展示顺序） */
    private function sectionCatalog()
    {
        return array(
            'hero' => lang('admin/themedesign/sec_hero'),
            'vod_hot' => lang('admin/themedesign/sec_vod_hot'),
            'vod_latest' => lang('admin/themedesign/sec_vod_latest'),
            'rank_week' => lang('admin/themedesign/sec_rank_week'),
            'art_list' => lang('admin/themedesign/sec_art_list'),
            'topic_list' => lang('admin/themedesign/sec_topic_list'),
            'live_list' => lang('admin/themedesign/sec_live_list'),
            'manga_list' => lang('admin/themedesign/sec_manga_list'),
            'rank_modules' => lang('admin/themedesign/sec_rank_modules'),
            'website_list' => lang('admin/themedesign/sec_website_list'),
            'gbook_list' => lang('admin/themedesign/sec_gbook_list'),
        );
    }

    /** Page Builder 可插入的顶层 block 预设（来自默认 index.json 顶层块） */
    private function blockCatalog()
    {
        $labels = $this->sectionCatalog();
        $page = $this->homepageDefaultPage();
        $blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : array();
        $out = array();
        foreach ($blocks as $i => $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            $label = $comp;
            if ($comp === 'hero') {
                $label = $labels['hero'];
            } elseif ($comp === 'columns') {
                $label = $labels['vod_latest'] . ' + ' . $labels['rank_week'];
            } elseif ($comp === 'section') {
                if (isset($b['props']['title']) && $b['props']['title'] !== '') {
                    $label = (string) $b['props']['title'];
                } elseif (isset($b['props']['title_key'])) {
                    $sk = isset($b['props']['section_key']) ? (string) $b['props']['section_key'] : '';
                    if ($sk !== '' && isset($labels[$sk])) {
                        $label = $labels[$sk];
                    }
                }
            } elseif (isset($labels[$comp])) {
                $label = $labels[$comp];
            }
            $out[] = array(
                'id' => $comp . '_' . $i,
                'label' => $label,
                'block' => $b,
            );
        }
        return $out;
    }

    /**
     * 可视化搭建器左侧组件库（template/_shared/kit/presets/palette.json）。
     * @return array
     */
    private function blockPalette()
    {
        $kit = new PageBuilderKit();
        return $kit->componentPalette(function ($key) {
            return lang($key);
        });
    }

    /** Page Builder 允许的 component 名（与 kit manifest 对齐） */
    private function blockComponentNames()
    {
        $kit = new PageBuilderKit();
        $names = $kit->componentNames();
        if (!empty($names)) {
            return $names;
        }
        return array(
            'section', 'columns', 'grid', 'spacer',
            'content_feed', 'rank_feed',
            'vod_list', 'art_list', 'manga_list', 'website_list', 'topic_list',
            'live_list', 'gbook_list', 'rank_list', 'rank_card', 'rank_inline',
        );
    }

    /** content_feed.bind 默认更多链接 */
    private function contentFeedMoreUrl($bind)
    {
        $map = array(
            'vod' => 'vod/index',
            'manga' => 'manga/index',
            'art' => 'art/index',
            'topic' => 'topic/index',
            'live' => 'live/show',
            'website' => 'website/index',
            'gbook' => 'gbook/index',
        );
        return isset($map[$bind]) ? $map[$bind] : 'vod/index';
    }

    /** content_feed 分区默认标题与图标 */
    private function contentFeedSectionMeta($bind)
    {
        $labels = $this->sectionCatalog();
        $map = array(
            'vod' => array('title' => $labels['vod_latest'], 'icon' => ''),
            'manga' => array('title' => $labels['manga_list'], 'icon' => 'book'),
            'art' => array('title' => $labels['art_list'], 'icon' => 'newspaper'),
            'topic' => array('title' => $labels['topic_list'], 'icon' => 'collection'),
            'live' => array('title' => $labels['live_list'], 'icon' => 'broadcast'),
            'website' => array('title' => $labels['website_list'], 'icon' => 'broadcast'),
            'gbook' => array('title' => $labels['gbook_list'], 'icon' => 'newspaper'),
        );
        if (isset($map[$bind])) {
            return $map[$bind];
        }
        return array('title' => $labels['vod_latest'], 'icon' => '');
    }

    /** 读取主题内 html/{route}.json 作为默认 page 定义 */
    private function pageDefaultForRoute($route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return array();
        }
        $file = $this->themeDir() . 'html/' . $parts[0] . '/' . $parts[1] . '.json';
        if (!is_file($file)) {
            if ($route === 'index/index') {
                return $this->homepageDefaultPage();
            }
            $scaffolder = new PageScaffolder();
            return $scaffolder->defaultPageForRoute($route, $this->themeDir());
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return array();
        }
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : array();
    }

    /** 从配置取某路由 page，含 homepage 兼容 */
    private function getPageFromConfig(array $cfg, $route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $cfg = PageRegistry::migrateConfigPages($cfg);
        $configRoute = PageRegistry::authPageConfigRoute($route);
        if (isset($cfg['pages'][$configRoute]) && is_array($cfg['pages'][$configRoute])) {
            return $cfg['pages'][$configRoute];
        }
        if (isset($cfg['pages'][$route]) && is_array($cfg['pages'][$route])) {
            return $cfg['pages'][$route];
        }
        if ($route === 'index/index' && isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            return $cfg['homepage']['page'];
        }
        return $this->pageDefaultForRoute($route);
    }

    /** 校验任意路由 page（regions 或 legacy blocks） */
    private function sanitizePage($route, $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        if ($route === 'index/index' && (!isset($page['regions']) || !is_array($page['regions']))) {
            return $this->sanitizeHomepagePage($page);
        }
        if (!is_array($page)) {
            $page = array();
        }
        if (isset($page['regions']) && is_array($page['regions'])) {
            $regions = array();
            foreach (PageRegistry::regionNames() as $name) {
                $blocks = isset($page['regions'][$name]) && is_array($page['regions'][$name])
                    ? $page['regions'][$name] : array();
                $regions[$name] = $this->sanitizeBlockTree($blocks);
            }
            if (PageRegistry::isImmersiveRoute($route)) {
                $regions = PageRegistry::applyImmersivePageRegions($regions);
            }
            $meta = PageRegistry::get($route);
            $footerBlocks = $regions['footer'];
            $footerLegacy = array();
            if (!empty($footerBlocks) && is_array($footerBlocks[0])) {
                $footerLegacy = $this->sanitizeFooterBlock($footerBlocks[0]);
            }
            $layoutMod = isset($page['layout_mod']) ? (string) $page['layout_mod'] : '';
            if (PageRegistry::isImmersiveRoute($route)) {
                $layoutMod = 'solo';
            }
            $sanitized = array(
                'template' => isset($page['template']) ? (string) $page['template'] : $route,
                'layout' => isset($page['layout']) ? (string) $page['layout'] : 'base',
                'layout_mod' => $layoutMod,
                'page_type' => $meta ? $meta['page_type'] : '',
                'regions' => $regions,
                'blocks' => $regions['content'],
                'chrome' => array_merge($regions['site_header'], $regions['hero'], $regions['channel']),
                'footer' => $footerLegacy,
            );
            if (isset($page['chrome_opts']) && is_array($page['chrome_opts'])) {
                $sanitized['chrome_opts'] = $page['chrome_opts'];
            } elseif ($route === 'index/index') {
                if ($this->homepageUsesAppTopChrome(array('regions' => $regions))
                    && $this->homepageChromeFallbacksDisabled($page)) {
                    $sanitized['chrome_opts'] = array(
                        'channel_strip' => false,
                        'header_decor' => false,
                    );
                } else {
                    // 首页：由 channel / hero 区域是否含对应 chrome 组件推导
                    $hasChannel = false;
                    $hasDecor = false;
                    foreach ($regions['channel'] as $b) {
                        if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'channel_strip') {
                            $hasChannel = true;
                            break;
                        }
                    }
                    foreach ($regions['hero'] as $b) {
                        if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'header_decor') {
                            $hasDecor = true;
                            break;
                        }
                    }
                    $sanitized['chrome_opts'] = array(
                        'channel_strip' => $hasChannel,
                        'header_decor' => $hasDecor,
                    );
                }
            }
            return $this->applyPageBuilderMeta($this->finalizeAppTopChromePage($route, $this->stripAppTopChromeManagedRegions(
                $this->sanitizeMemberPage($route, $sanitized)
            )), $page);
        }
        return $this->sanitizeHomepagePage($page);
    }

    /**
     * 保留 Page Builder 页面级元数据（layout_compile / extra_page_class / twig_sets）。
     * 编辑器 collectPage 与 sanitize 均不得把这些字段丢掉，否则 np2 首页会丢失 np2-home-page 等样式钩子。
     */
    private function applyPageBuilderMeta(array $sanitized, array $page)
    {
        if (isset($page['layout_compile']) && is_array($page['layout_compile'])) {
            $allowed = array('chrome_fallbacks', 'site_header', 'hero', 'channel', 'footer');
            $lc = array();
            foreach ($allowed as $key) {
                if (array_key_exists($key, $page['layout_compile'])) {
                    $lc[$key] = (bool) $page['layout_compile'][$key];
                }
            }
            if (!empty($lc)) {
                $sanitized['layout_compile'] = $lc;
            }
        }
        if (isset($page['extra_page_class'])) {
            $cls = trim((string) $page['extra_page_class']);
            if ($cls !== '' && preg_match('/^[a-z0-9_ -]+$/i', $cls)) {
                $sanitized['extra_page_class'] = $cls;
            }
        }
        if (isset($page['twig_sets']) && is_array($page['twig_sets'])) {
            $sets = array();
            foreach ($page['twig_sets'] as $varName => $varVal) {
                $varName = (string) $varName;
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $varName)) {
                    continue;
                }
                if (is_scalar($varVal) || $varVal === null) {
                    $sets[$varName] = $varVal === null ? '' : (string) $varVal;
                }
            }
            if (!empty($sets)) {
                $sanitized['twig_sets'] = $sets;
            }
        }
        return $sanitized;
    }

    /** NP2 首页顶栏在 content 区（app_top_chrome），不走全局 site_nav/channel_strip */
    private function homepageUsesAppTopChrome(array $page)
    {
        $content = isset($page['regions']['content']) && is_array($page['regions']['content'])
            ? $page['regions']['content'] : array();
        if (empty($content) && isset($page['blocks']) && is_array($page['blocks'])) {
            $content = $page['blocks'];
        }
        foreach ($content as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'app_top_chrome') {
                return true;
            }
        }
        return false;
    }

    /** 首页已用 app_top_chrome 时，全站其它页也走同一顶栏策略 */
    private function themeUsesAppTopChrome(array $cfg = array())
    {
        if (empty($cfg)) {
            $cfg = $this->currentDraft();
        }
        return (new PageBuilderKit())->themeConfigUsesAppTopChrome($cfg);
    }

    /** layout_compile 显式关闭 chrome 回退注入 */
    private function homepageChromeFallbacksDisabled(array $page)
    {
        if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
            return $this->homepageUsesAppTopChrome($page);
        }
        return array_key_exists('chrome_fallbacks', $page['layout_compile'])
            && $page['layout_compile']['chrome_fallbacks'] === false;
    }

    /**
     * NP2 等自管顶栏首页：清空 site_header/hero/channel，并写入 layout_compile 元数据。
     *
     * @param array $page
     * @return array
     */
    private function stripAppTopChromeManagedRegions(array $page)
    {
        if (!$this->homepageUsesAppTopChrome($page)) {
            return $page;
        }
        if (!isset($page['regions']) || !is_array($page['regions'])) {
            return $page;
        }
        $page['regions']['site_header'] = array();
        $page['regions']['hero'] = array();
        $page['regions']['channel'] = array();
        $page['chrome'] = array();
        $page['chrome_opts'] = array(
            'header_decor' => false,
            'channel_strip' => false,
        );
        $lc = isset($page['layout_compile']) && is_array($page['layout_compile']) ? $page['layout_compile'] : array();
        $page['layout_compile'] = array(
            'chrome_fallbacks' => false,
            'site_header' => false,
            'hero' => false,
            'channel' => false,
            'footer' => isset($lc['footer']) ? (bool) $lc['footer'] : false,
        );
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if ($extra === '' || strpos($extra, 'np2-home-page') === false) {
            $page['extra_page_class'] = trim($extra . ' np2-home-page');
        }
        if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
            $page['twig_sets'] = array('np2_tab' => 'home');
        } elseif (!isset($page['twig_sets']['np2_tab'])) {
            $page['twig_sets']['np2_tab'] = 'home';
        }
        return $page;
    }

    /** 自管顶栏主题：保存/编译前统一 content 首部 app_top_chrome，并清空旧 chrome 区 */
    private function finalizeAppTopChromePage($route, array $page)
    {
        if (!$this->themeUsesAppTopChrome()) {
            return $page;
        }
        $kit = new PageBuilderKit();
        return $kit->applyAppTopChromeLayout($route, $page, $this->themeDir());
    }

    /**
     * 站点 chrome 步骤：全站顶栏在 site_nav 与 app_top_chrome 间切换。
     *
     * @param array  $cfg
     * @param string $headerChrome site_nav|app_top_chrome
     */
    private function applyHeaderChromeToDraftPages(array &$cfg, $headerChrome)
    {
        $headerChrome = ($headerChrome === 'app_top_chrome') ? 'app_top_chrome' : 'site_nav';
        $cfg = PageRegistry::migrateConfigPages($cfg);
        if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
            $cfg['pages'] = array();
        }
        $kit = new PageBuilderKit();
        $scaffolder = new PageScaffolder();
        $themeDir = $this->themeDir();
        foreach (PageRegistry::routes() as $meta) {
            if (!PageRegistry::isEditableRoute($meta['route'])) {
                continue;
            }
            $route = $meta['route'];
            if (PageRegistry::isAuthModalVirtualRoute($route)) {
                continue;
            }
            $page = isset($cfg['pages'][$route]) && is_array($cfg['pages'][$route])
                ? $cfg['pages'][$route]
                : $scaffolder->defaultPageForRoute($route, $themeDir);
            if ($headerChrome === 'app_top_chrome') {
                if (PageRegistry::isImmersiveRoute($route) || PageRegistry::isAuthPageRoute($route)) {
                    $cfg['pages'][$route] = $page;
                    continue;
                }
                $page = $kit->applyAppTopChromeLayout($route, $page, $themeDir);
            } else {
                $page = $this->switchPageToSiteNavHeader($route, $page, $cfg);
            }
            $cfg['pages'][$route] = $page;
        }
        if (isset($cfg['pages']['index/index']) && is_array($cfg['pages']['index/index'])) {
            $cfg['homepage'] = array('page' => $cfg['pages']['index/index']);
        }
    }

    /**
     * @param string $route
     * @param array  $page
     * @param array  $cfg
     * @return array
     */
    private function switchPageToSiteNavHeader($route, array $page, array $cfg)
    {
        if (!isset($page['regions']) || !is_array($page['regions'])) {
            $page['regions'] = array();
        }
        $content = isset($page['regions']['content']) && is_array($page['regions']['content'])
            ? $page['regions']['content'] : array();
        $rest = array();
        foreach ($content as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'app_top_chrome') {
                continue;
            }
            $rest[] = $b;
        }
        $page['regions']['content'] = $rest;
        $page['blocks'] = $rest;
        $global = $this->globalChromeRegions($cfg, $page);
        $page['regions']['site_header'] = isset($global['site_header']) ? $global['site_header'] : array();
        if (isset($page['layout_compile']) && is_array($page['layout_compile'])) {
            $page['layout_compile']['site_header'] = false;
            $page['layout_compile']['chrome_fallbacks'] = true;
        }
        return $page;
    }

    /** 会员/认证路由：结构对齐 scaffold，仅保留 props 差异 */
    private function sanitizeMemberPage($route, array $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        if (!PageRegistry::isMemberShellRoute($route) && !PageRegistry::isAuthPageRoute($route)) {
            return $page;
        }
        $scaffolder = new PageScaffolder();
        $merged = $scaffolder->mergeMemberPageWithScaffold($route, $this->themeDir(), $page);
        if (!isset($merged['regions']) || !is_array($merged['regions'])) {
            return $merged;
        }
        foreach (PageRegistry::regionNames() as $name) {
            $blocks = isset($merged['regions'][$name]) && is_array($merged['regions'][$name])
                ? $merged['regions'][$name] : array();
            $merged['regions'][$name] = $this->sanitizeBlockTree($blocks);
        }
        $merged['blocks'] = $merged['regions']['content'];
        return $merged;
    }

    /** 归一化 page（导航/页脚 props 等） */
    private function enrichPage($route, array $page, array $cfg = array())
    {
        $route = PageRegistry::normalizeRoute($route);
        $page = $this->sanitizePage($route, $page);
        if ($route === 'index/index') {
            return $this->enrichHomepagePage($page, $cfg);
        }
        $kit = new PageBuilderKit();
        if ($kit->themeConfigUsesAppTopChrome($cfg)) {
            if (PageRegistry::isImmersiveRoute($route)) {
                if (isset($page['regions']) && is_array($page['regions'])) {
                    $page['regions'] = PageRegistry::applyImmersivePageRegions($page['regions']);
                }
                $page['layout_mod'] = 'solo';
                $page['chrome'] = array();
                $page['footer'] = array();
                return $page;
            }
            $page = $kit->applyAppTopChromeLayout($route, $page, $this->themeDir());
            return $this->injectGlobalFooterOnly($page, $cfg);
        }
        if (PageRegistry::isImmersiveRoute($route)) {
            if (isset($page['regions']) && is_array($page['regions'])) {
                $page['regions'] = PageRegistry::applyImmersivePageRegions($page['regions']);
            }
            $page['layout_mod'] = 'solo';
            $page['chrome'] = array();
            $page['footer'] = array();
            return $page;
        }
        return $this->injectGlobalSiteChrome($page, $cfg);
    }

    /**
     * 全站顶栏/页脚以 draft.navbar / draft.attributes 为准，覆盖各页 PB 中的 chrome 区域。
     */
    private function injectGlobalSiteChrome(array $page, array $cfg = array())
    {
        $global = $this->globalChromeRegions($cfg, $page);
        if (!isset($page['regions']) || !is_array($page['regions'])) {
            $page['regions'] = array();
        }
        // manga/novel 首页的 hero 由页面自己管（可为空），不要被全局 header_decor 盖回去，
        // 否则编辑器里删不掉那条灰 Hero 装饰带。
        $pageOwnsHero = $this->pageOwnsHeroRegion($page);
        $pageOwnsChannel = $this->pageOwnsChannelRegion($page);
        foreach (array('site_header', 'hero', 'channel', 'footer') as $region) {
            if ($region === 'hero' && $pageOwnsHero) {
                if (!isset($page['regions']['hero']) || !is_array($page['regions']['hero'])) {
                    $page['regions']['hero'] = array();
                }
                continue;
            }
            // 漫画/小说首页：频道栏仍走全局；站点首页保留页面自己的 channel
            if ($region === 'channel' && $pageOwnsHero && !$pageOwnsChannel) {
                $page['regions']['channel'] = array();
                continue;
            }
            if ($region === 'channel' && $pageOwnsChannel) {
                if (!isset($page['regions']['channel']) || !is_array($page['regions']['channel'])) {
                    $page['regions']['channel'] = array();
                }
                $opts = isset($page['chrome_opts']) && is_array($page['chrome_opts']) ? $page['chrome_opts'] : array();
                if (array_key_exists('channel_strip', $opts)) {
                    if (empty($opts['channel_strip'])) {
                        $page['regions']['channel'] = array();
                    } elseif (empty($page['regions']['channel']) && !empty($global['channel'])) {
                        $page['regions']['channel'] = $global['channel'];
                    }
                } elseif (empty($page['regions']['channel']) && !empty($global['channel'])) {
                    $page['regions']['channel'] = $global['channel'];
                }
                continue;
            }
            $page['regions'][$region] = isset($global[$region]) ? $global[$region] : array();
        }
        $page['chrome'] = array_merge(
            $page['regions']['site_header'],
            $pageOwnsHero ? array() : $page['regions']['hero'],
            $page['regions']['channel']
        );
        if (!empty($page['regions']['footer'][0]) && is_array($page['regions']['footer'][0])) {
            $page['footer'] = $page['regions']['footer'][0];
        }
        return $page;
    }

    /** app_top_chrome 主题：仅同步全站页脚，不再注入 site_nav / channel_strip */
    private function injectGlobalFooterOnly(array $page, array $cfg = array())
    {
        $lc = isset($page['layout_compile']) && is_array($page['layout_compile']) ? $page['layout_compile'] : array();
        if (array_key_exists('footer', $lc) && $lc['footer'] === false) {
            if (!isset($page['regions']) || !is_array($page['regions'])) {
                $page['regions'] = array();
            }
            $page['regions']['footer'] = array();
            return $page;
        }
        $global = $this->globalChromeRegions($cfg, $page);
        if (!isset($page['regions']) || !is_array($page['regions'])) {
            $page['regions'] = array();
        }
        if (!empty($global['footer'])) {
            $page['regions']['footer'] = $global['footer'];
            if (!empty($global['footer'][0]) && is_array($global['footer'][0])) {
                $page['footer'] = $global['footer'][0];
            }
        }
        return $page;
    }

    /** 站点/漫画/小说首页：hero 区归属页面，允许清空，不注入全局顶栏装饰 */
    private function pageOwnsHeroRegion(array $page)
    {
        $mod = isset($page['layout_mod']) ? (string) $page['layout_mod'] : '';
        if ($mod === 'home' || $mod === 'manga-home' || $mod === 'novel-home') {
            return true;
        }
        $tpl = isset($page['template']) ? PageRegistry::normalizeRoute((string) $page['template']) : '';
        return $tpl === 'index/index' || $tpl === 'manga/index' || $tpl === 'art/index';
    }

    /** 站点首页：频道条由页面自己管（可拖入/删除） */
    private function pageOwnsChannelRegion(array $page)
    {
        $mod = isset($page['layout_mod']) ? (string) $page['layout_mod'] : '';
        if ($mod === 'home') {
            return true;
        }
        $tpl = isset($page['template']) ? PageRegistry::normalizeRoute((string) $page['template']) : '';
        return $tpl === 'index/index';
    }

    /**
     * 首页 chrome 种子（含 header_decor.image）。
     * 其它路由 injectGlobalSiteChrome 必须用这份，不能 normalizeChrome([])，否则装饰图永远被默认空图盖掉。
     */
    private function homepageChromeSeed(array $cfg)
    {
        $home = null;
        if (isset($cfg['pages']['index/index']) && is_array($cfg['pages']['index/index'])) {
            $home = $cfg['pages']['index/index'];
        } elseif (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $home = $cfg['homepage']['page'];
        }
        if (!is_array($home)) {
            return array('chrome' => array(), 'chrome_opts' => array());
        }
        $chrome = isset($home['chrome']) && is_array($home['chrome']) ? $home['chrome'] : array();
        $hero = isset($home['regions']['hero']) && is_array($home['regions']['hero'])
            ? $home['regions']['hero'] : array();
        if (!empty($hero)) {
            $chrome = $this->mergeRegionBlockIntoChrome($chrome, $hero, 'header_decor');
        }
        $opts = isset($home['chrome_opts']) && is_array($home['chrome_opts']) ? $home['chrome_opts'] : array();
        return array('chrome' => $chrome, 'chrome_opts' => $opts);
    }

    /** 由全局 navbar / attributes 生成 chrome 分区块 */
    private function globalChromeRegions(array $cfg, array $page = array())
    {
        $navbar = isset($cfg['navbar']) && is_array($cfg['navbar']) ? $cfg['navbar'] : array();
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $type_ids = isset($navbar['type_ids']) && is_array($navbar['type_ids']) ? $navbar['type_ids'] : array();
        $clean_ids = array();
        foreach ($type_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        $kit = new PageBuilderKit();
        $homeSeed = $this->homepageChromeSeed($cfg);
        $chromeOpts = array();
        if (isset($page['chrome_opts']) && is_array($page['chrome_opts'])) {
            $chromeOpts = $page['chrome_opts'];
        } elseif (!empty($homeSeed['chrome_opts'])) {
            $chromeOpts = $homeSeed['chrome_opts'];
        } elseif (isset($cfg['homepage']['page']['chrome_opts']) && is_array($cfg['homepage']['page']['chrome_opts'])) {
            $chromeOpts = $cfg['homepage']['page']['chrome_opts'];
        }
        // 必须带上首页已保存的 header_decor（含背景图），禁止空数组播种把 image 冲掉
        $chrome = $kit->normalizeChrome($homeSeed['chrome'], $clean_ids, $chromeOpts);
        $chrome = $this->applyGlobalAttributesToChrome($chrome, $attr);
        $byComp = array();
        foreach ($chrome as $c) {
            if (is_array($c) && !empty($c['component'])) {
                $byComp[(string) $c['component']] = $c;
            }
        }

        $footerOpts = array();
        if (isset($attr['footer_copyright'])) {
            $footerOpts['copyright'] = (string) $attr['footer_copyright'];
        }
        if (isset($attr['logo'])) {
            $footerOpts['logo'] = (string) $attr['logo'];
        }
        $home = null;
        if (isset($cfg['pages']['index/index']) && is_array($cfg['pages']['index/index'])) {
            $home = $cfg['pages']['index/index'];
        } elseif (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $home = $cfg['homepage']['page'];
        }
        $savedFooterBlocks = is_array($home)
            && isset($home['regions']['footer'])
            && is_array($home['regions']['footer'])
            ? $home['regions']['footer'] : array();
        $footerBlocks = $kit->normalizeFooterBlocks($savedFooterBlocks, $footerOpts);

        return array(
            'site_header' => isset($byComp['site_nav']) ? array($byComp['site_nav']) : array(),
            'hero' => isset($byComp['header_decor']) ? array($byComp['header_decor']) : array(),
            'channel' => isset($byComp['channel_strip']) ? array($byComp['channel_strip']) : array(),
            'footer' => $footerBlocks,
        );
    }

    /** 收集需要编译的 pages[route] */
    private function collectCompiledPages(array $cfg)
    {
        $cfg = PageRegistry::migrateConfigPages($cfg);
        $pages = isset($cfg['pages']) && is_array($cfg['pages']) ? $cfg['pages'] : array();
        if (!isset($pages['index/index'])) {
            $home = isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])
                ? $cfg['homepage']['page'] : $this->homepageDefaultPage();
            if (!empty($home)) {
                $pages['index/index'] = $home;
            }
        }
        foreach (PageRegistry::AUTH_PAGE_CONFIG_ALIASES as $aliasRoute => $baseRoute) {
            if (isset($pages[$baseRoute]) && is_array($pages[$baseRoute])) {
                $pages[$aliasRoute] = $pages[$baseRoute];
            }
        }
        return $pages;
    }

    /** 编译认证整页：共享草稿配置，panel 由目标路由决定 */
    private function applyAuthPageRoutePanelOverride($route, array $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        $panel = PageRegistry::authPagePanelForRoute($route);
        if ($panel === '' || !isset($page['regions']['content']) || !is_array($page['regions']['content'])) {
            return $page;
        }
        foreach ($page['regions']['content'] as $idx => $block) {
            if (!is_array($block) || !isset($block['component']) || (string) $block['component'] !== 'auth_page') {
                continue;
            }
            if (!isset($block['props']) || !is_array($block['props'])) {
                $block['props'] = array();
            }
            $block['props']['panel'] = $panel;
            $page['regions']['content'][$idx] = $block;
        }
        $page['blocks'] = $page['regions']['content'];
        $page['template'] = $route;
        return $page;
    }

    /** 草稿 twig 绝对路径（如 html/index/index.draft.twig） */
    private function draftTwigFile($route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return '';
        }
        return $this->themeDir() . 'html/' . $parts[0] . '/' . $parts[1] . '.draft.twig';
    }

    /**
     * 打开搭建器时：草稿 twig 仍新于 draft.json 则跳过重复编译（预览走 td_preview + *.draft.twig）。
     */
    private function compilePageTwigForRouteIfStale($route, array $page, array $cfg = array())
    {
        if (!$this->isTwigTheme()) {
            return true;
        }
        $file = $this->draftTwigFile($route);
        $updated = isset($cfg['updated_at']) ? (int) $cfg['updated_at'] : 0;
        if ($file !== '' && is_file($file)) {
            if ($updated <= 0 || @filemtime($file) >= $updated - 1) {
                return true;
            }
        }
        return $this->compilePageTwigForRoute($route, $page, true, $cfg);
    }

    private function compilePageTwigForRoute($route, array $page, $isDraft, array $cfg = array())
    {
        if (!$this->isTwigTheme()) {
            return true;
        }
        $route = PageRegistry::normalizeRoute($route);
        $page = $this->enrichPage($route, $page, $cfg);
        if (PageRegistry::isAuthPageRoute($route)) {
            $page = $this->applyAuthPageRoutePanelOverride($route, $page);
        }
        $compiler = new TwigPageCompiler();
        if (!TwigPageCompiler::pageNeedsCompile($page, $route)) {
            return true;
        }
        $layoutOpts = PageRegistry::layoutOptsForCompile($this->themeDir(), $route, $page);
        if (\app\common\util\ThemePreset::isThinShell($this->themeDir())) {
            $layoutOpts['components_only'] = true;
            $layoutOpts['theme_dir'] = $this->themeDir();
            $flags = \app\common\util\ThemePreset::scaffoldFlags($this->themeDir());
            if (!empty($flags['shell_left_nav'])) {
                $layoutOpts['shell_left_nav'] = true;
            }
        }
        return $compiler->writePageTwig($route, $page, $this->themeDir(), $isDraft, $layoutOpts);
    }

    /** 校验 homepage.page.blocks 树 */
    private function sanitizeHomepagePage($page)
    {
        if (!is_array($page)) {
            $page = array();
        }
        $blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : array();
        $chrome = isset($page['chrome']) && is_array($page['chrome']) ? $page['chrome'] : array();
        $kit = new PageBuilderKit();
        $chromeOpts = isset($page['chrome_opts']) && is_array($page['chrome_opts']) ? $page['chrome_opts'] : array();
        $sanitizedChrome = $this->sanitizeChrome($chrome);
        $sanitizedFooter = $this->sanitizeFooterBlock(isset($page['footer']) ? $page['footer'] : array());
        return array(
            'template' => 'index/index',
            'layout' => 'base',
            'chrome' => $kit->normalizeChrome($sanitizedChrome, array(), $chromeOpts),
            'chrome_opts' => $chromeOpts,
            'blocks' => $this->sanitizeBlockTree($blocks),
            'footer' => $kit->normalizeFooter($sanitizedFooter),
        );
    }

    private function sanitizeFooterBlock($footer)
    {
        if (!is_array($footer) || empty($footer['component'])) {
            return array('component' => 'site_footer', 'props' => array());
        }
        $comp = (string) $footer['component'];
        if ($comp !== 'site_footer') {
            return array('component' => 'site_footer', 'props' => array());
        }
        $allowed = $this->blockComponentNames();
        if (!in_array($comp, $allowed, true)) {
            return array('component' => 'site_footer', 'props' => array());
        }
        $item = array('component' => $comp);
        if (isset($footer['props']) && is_array($footer['props'])) {
            $item['props'] = $this->sanitizeBlockProps($comp, $footer['props']);
        }
        return $item;
    }

    private function sanitizeChrome($chrome)
    {
        $allowed = $this->blockComponentNames();
        $out = array();
        foreach ($chrome as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            if ($comp !== 'site_nav' && $comp !== 'channel_strip' && $comp !== 'header_decor') {
                continue;
            }
            if (!in_array($comp, $allowed, true)) {
                continue;
            }
            $item = array('component' => $comp);
            if (isset($b['props']) && is_array($b['props'])) {
                $item['props'] = $this->sanitizeBlockProps($comp, $b['props']);
            }
            $out[] = $item;
        }
        return $out;
    }

    private function sanitizeBlockTree($blocks)
    {
        $allowed = $this->blockComponentNames();
        $kit = new PageBuilderKit();
        $shellIds = $kit->implicitShellComponentIds();
        $out = array();
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            if (in_array($comp, $shellIds, true)) {
                continue;
            }
            if ($comp === 'carousel-filmstrip') {
                $props = isset($b['props']) && is_array($b['props']) ? $b['props'] : array();
                $type = isset($props['type']) ? $props['type'] : 0;
                $b = array(
                    'component' => 'filmstrip_layout',
                    'slots' => array(
                        'banner' => array(
                            array(
                                'component' => 'vod_carousel',
                                'props' => $props,
                            ),
                        ),
                        'feed' => array(
                            array(
                                'component' => 'vod_list',
                                'props' => array(
                                    'num' => 12,
                                    'by' => 'hits_day',
                                    'order' => 'desc',
                                    'type' => $type,
                                    'render' => 'filmstrip',
                                    'card' => 'poster-h',
                                    'ratio' => '69-40',
                                ),
                            ),
                        ),
                    ),
                );
                $comp = 'filmstrip_layout';
            }
            if (!in_array($comp, $allowed, true)) {
                continue;
            }
            $item = array('component' => $comp);
            if (isset($b['props']) && is_array($b['props'])) {
                $item['props'] = $this->sanitizeBlockProps($comp, $b['props']);
            }
            if (isset($b['slots']) && is_array($b['slots'])) {
                $slots = array();
                foreach ($b['slots'] as $sk => $children) {
                    if (!is_array($children)) {
                        continue;
                    }
                    $clean = $this->sanitizeBlockTree($children);
                    if (!empty($clean)) {
                        $slots[(string) $sk] = $clean;
                    }
                }
                if (!empty($slots)) {
                    $item['slots'] = $slots;
                }
            }
            if ($comp === 'columns') {
                if (!isset($item['props'])) {
                    $item['props'] = array();
                }
                // 一侧有内容、另一侧为空时绝不能收成单列 12fr，否则预览只剩一列，
                // 列表会铺满双栏，后续只能拖到双栏上下。
                if (empty($item['props']['cols']) || !is_array($item['props']['cols']) || count($item['props']['cols']) < 2) {
                    $item['props']['cols'] = array(8, 4);
                }
                if (empty($item['props']['stack_at'])) {
                    $item['props']['stack_at'] = 720;
                }
                if (!isset($item['slots']) || !is_array($item['slots'])) {
                    $item['slots'] = array();
                }
                $colCount = count($item['props']['cols']);
                for ($ci = 0; $ci < $colCount; $ci++) {
                    $ck = (string) $ci;
                    if (!isset($item['slots'][$ck]) || !is_array($item['slots'][$ck])) {
                        $item['slots'][$ck] = array();
                    }
                }
                $item['props'] = $this->sanitizeBlockProps($comp, $item['props']);
            }
            if ($comp === 'filmstrip_layout') {
                $item = $this->normalizeFilmstripLayoutBlock($item);
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * 对齐 twigspike 初版：banner 仅 vod_carousel，feed 仅一条 vod_list（filmstrip + 69-40 横卡）。
     */
    private function normalizeFilmstripLayoutBlock(array $block)
    {
        $slots = isset($block['slots']) && is_array($block['slots']) ? $block['slots'] : array();
        $bannerIn = isset($slots['banner']) && is_array($slots['banner']) ? $slots['banner'] : array();
        $feedIn = isset($slots['feed']) && is_array($slots['feed']) ? $slots['feed'] : array();
        $bannerOut = array();
        foreach ($bannerIn as $child) {
            if (!is_array($child) || empty($child['component']) || $child['component'] !== 'vod_carousel') {
                continue;
            }
            if (!empty($bannerOut)) {
                break;
            }
            $bannerOut[] = $child;
        }
        $feedOut = array();
        foreach ($feedIn as $child) {
            if (!is_array($child) || empty($child['component'])) {
                continue;
            }
            $feedComp = (string) $child['component'];
            if ($feedComp !== 'vod_list' && $feedComp !== 'content_feed') {
                continue;
            }
            if (!empty($feedOut)) {
                break;
            }
            $props = isset($child['props']) && is_array($child['props']) ? $child['props'] : array();
            $props['render'] = 'filmstrip';
            if (empty($props['card'])) {
                $props['card'] = 'poster-h';
            }
            if (empty($props['ratio']) || (string) $props['ratio'] === 'default') {
                $props['ratio'] = '69-40';
            }
            if (!isset($props['num'])) {
                $props['num'] = 12;
            }
            if (!isset($props['by'])) {
                $props['by'] = 'hits_day';
            }
            if ($feedComp === 'content_feed' && empty($props['bind'])) {
                $props['bind'] = 'vod';
            }
            $child['props'] = $this->sanitizeBlockProps($feedComp, $props);
            $feedOut[] = $child;
        }
        $block['slots'] = array(
            'banner' => $bannerOut,
            'feed' => $feedOut,
        );
        return $block;
    }

    private function coverRatioPresets()
    {
        return array('default', '16-9', '4-3', '3-4', '2-3', '69-40', '20-29', '1-1');
    }

    private function sanitizeCoverRatioValue($ratio)
    {
        $ratio = (string) $ratio;
        if (in_array($ratio, $this->coverRatioPresets(), true)) {
            return $ratio;
        }
        if (preg_match('/^(\d{1,3})-(\d{1,3})$/', $ratio, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ($w >= 1 && $w <= 999 && $h >= 1 && $h <= 999) {
                return $w . '-' . $h;
            }
        }
        return null;
    }

    private function sanitizeCoverRatioWh($w, $h)
    {
        $w = (int) $w;
        $h = (int) $h;
        if ($w >= 1 && $w <= 999 && $h >= 1 && $h <= 999) {
            return array($w, $h);
        }
        return null;
    }

    private function sanitizeBlockProps($comp, $props)
    {
        $out = array();
        $layouts = array('slider', 'grid', 'list', 'filmstrip');
        $sorts = array('hits', 'hits_month', 'hits_week', 'hits_day', 'time', 'score');
        $periods = array('day', 'week', 'month', 'all');
        if (isset($props['title'])) {
            $out['title'] = mb_substr(mac_filter_xss((string) $props['title']), 0, 80, 'UTF-8');
        }
        // title 与 title_key 并非互斥：PageScaffolder 会同时下发字面默认标题 + i18n key，
        // 前者用于首次渲染文案，后者驱动前台按语言 data-lang 运行时替换，两者都要保留。
        if (isset($props['title_key'])) {
            $out['title_key'] = preg_replace('/[^a-z0-9_]/i', '', (string) $props['title_key']);
        }
        // title_lang: 与 title_key 同一目的（驱动前台 data-lang 运行时替换），但用在
        // sec_heading / novel_home_rank / manga_home_rank 等一大批组件而非 section。此前
        // 只有 media_related_panel 单独收了这个字段，其余组件即使 PageScaffolder 已下发
        // title_lang，也会在这里被整体丢棄，导致切换语言时标题一律不生效。改成通用收下。
        if (isset($props['title_lang'])) {
            $tl = preg_replace('/[^a-z0-9_]/i', '', (string) $props['title_lang']);
            if ($tl !== '') {
                $out['title_lang'] = $tl;
            }
        }
        if (isset($props['more_url'])) {
            $out['more_url'] = preg_replace('/[^a-z0-9_\/]/i', '', (string) $props['more_url']);
        }
        if (isset($props['variant'])) {
            $v = (string) $props['variant'];
            if ($v === 'card' || $v === 'plain') {
                $out['variant'] = $v;
            }
        }
        if (isset($props['icon'])) {
            $out['icon'] = preg_replace('/[^a-z0-9_-]/i', '', (string) $props['icon']);
        }
        if (isset($props['num'])) {
            $n = (int) $props['num'];
            if ($n > 0 && $n <= 50) {
                $out['num'] = $n;
            }
        }
        if (isset($props['type'])) {
            if ($props['type'] === 'current') {
                $out['type'] = 'current';
            } else {
                $t = (int) $props['type'];
                if ($t >= 0) {
                    $out['type'] = $t;
                }
            }
        }
        if (isset($props['type_id'])) {
            $t = (int) $props['type_id'];
            if ($t >= 0) {
                $out['type_id'] = $t;
            }
        }
        if (isset($props['render'])) {
            $r = (string) $props['render'];
            if (in_array($r, $layouts, true)) {
                $out['render'] = $r;
            }
        }
        if (isset($props['by'])) {
            $by = (string) $props['by'];
            if (in_array($by, $sorts, true)) {
                $out['by'] = $by;
            }
        }
        if (isset($props['order'])) {
            $o = (string) $props['order'];
            if ($o === 'asc' || $o === 'desc') {
                $out['order'] = $o;
            }
        }
        if (isset($props['period'])) {
            $p = (string) $props['period'];
            if (in_array($p, $periods, true)) {
                $out['period'] = $p;
            }
        }
        $carouselBindComps = array(
            'content_feed',
            'vod_carousel',
            'carousel-compact',
            'carousel-fullbleed',
            'manga_home_spotlight',
            'manga_mosaic_hero',
            'novel_home_banner',
        );
        if (in_array($comp, $carouselBindComps, true) && isset($props['bind'])) {
            if ($comp === 'content_feed') {
                $binds = array('vod', 'manga', 'art', 'topic', 'live', 'website', 'gbook', 'actor', 'role');
            } else {
                $binds = array('vod', 'manga', 'art');
            }
            $b = (string) $props['bind'];
            if (in_array($b, $binds, true)) {
                $out['bind'] = $b;
            }
        }
        $coverListComps = array('content_feed', 'vod_list', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'role_list');
        $listPagingComps = array('content_feed', 'vod_list', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'role_list', 'gbook_list');
        $skipCover = ($comp === 'content_feed' && isset($props['bind']) && (string) $props['bind'] === 'gbook');
        if (in_array($comp, $coverListComps, true) && !$skipCover && isset($props['card'])) {
            $card = (string) $props['card'];
            if ($card === 'poster-v' || $card === 'poster-h') {
                $out['card'] = $card;
            }
        }
        $ratioPresets = $this->coverRatioPresets();
        if (in_array($comp, $coverListComps, true) && !$skipCover && isset($props['ratio'])) {
            $ratio = $this->sanitizeCoverRatioValue($props['ratio']);
            if ($ratio !== null) {
                $out['ratio'] = $ratio;
            }
        }
        if (in_array($comp, $coverListComps, true) && !$skipCover) {
            $wh = $this->sanitizeCoverRatioWh(
                isset($props['ratio_w']) ? $props['ratio_w'] : 0,
                isset($props['ratio_h']) ? $props['ratio_h'] : 0
            );
            if ($wh !== null) {
                $out['ratio_w'] = $wh[0];
                $out['ratio_h'] = $wh[1];
            }
        }
        if (in_array($comp, $listPagingComps, true)) {
            if (isset($props['paging'])) {
                $out['paging'] = !empty($props['paging']);
            }
            if (isset($props['relation'])) {
                $out['relation'] = !empty($props['relation']);
            }
            if (isset($props['pageurl'])) {
                $out['pageurl'] = preg_replace('/[^a-z0-9_\/]/i', '', (string) $props['pageurl']);
            }
        }
        if ($comp === 'content_feed' && isset($props['refresh'])) {
            $out['refresh'] = !empty($props['refresh']);
        }
        if ($comp === 'gbook_form' && isset($props['show_hint'])) {
            $out['show_hint'] = !empty($props['show_hint']);
        }
        if ($comp === 'breadcrumb') {
            if (isset($props['auto'])) {
                $out['auto'] = !empty($props['auto']);
            }
            if (isset($props['sep'])) {
                $out['sep'] = mb_substr(mac_filter_xss((string) $props['sep']), 0, 8, 'UTF-8');
            }
        }
        if ($comp === 'spacer' && isset($props['size'])) {
            $size = (string) $props['size'];
            if (in_array($size, array('sm', 'md', 'lg'), true)) {
                $out['size'] = $size;
            }
        }
        if ($comp === 'filter_chips' && isset($props['flag'])) {
            $flag = (string) $props['flag'];
            if ($flag === 'show' || $flag === 'type') {
                $out['flag'] = $flag;
            }
        }
        if ($comp === 'filter_panel' && isset($props['preset'])) {
            $preset = (string) $props['preset'];
            if (preg_match('/^[a-z0-9_]+$/', $preset)) {
                $out['preset'] = $preset;
            }
        }
        if ($comp === 'filter_panel' && isset($props['show_letter'])) {
            $out['show_letter'] = !empty($props['show_letter']);
        }
        if ($comp === 'novel_home_grid' && isset($props['title_art'])) {
            $out['title_art'] = !empty($props['title_art']);
        }
        if ($comp === 'novel_home_grid' && isset($props['subtitle'])) {
            $out['subtitle'] = mb_substr(mac_filter_xss((string) $props['subtitle']), 0, 120, 'UTF-8');
        }
        if ($comp === 'section' && isset($props['head_sort'])) {
            $head_sort = (string) $props['head_sort'];
            if ($head_sort === '' || in_array($head_sort, array('vod_show', 'content_show', 'vod_index'), true)) {
                $out['head_sort'] = $head_sort;
            }
        }
        if ($comp === 'section' && isset($props['sort_route'])) {
            $sort_route = (string) $props['sort_route'];
            if (in_array($sort_route, array('show', 'type', 'index'), true)) {
                $out['sort_route'] = $sort_route;
            }
        }
        if ($comp === 'section' && isset($props['title_mode'])) {
            $title_mode = (string) $props['title_mode'];
            if ($title_mode === 'type_name') {
                $out['title_mode'] = $title_mode;
            }
        }
        if ($comp === 'section' && isset($props['show_more'])) {
            $out['show_more'] = (bool) $props['show_more'];
        }
        if ($comp === 'empty_state' && isset($props['preset'])) {
            $preset = (string) $props['preset'];
            if (preg_match('/^[a-z0-9_-]+$/', $preset)) {
                $out['preset'] = $preset;
            }
        }
        if ($comp === 'empty_state') {
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['hint'])) {
                $out['hint'] = mac_filter_xss((string) $props['hint']);
            }
        }
        if ($comp === 'member_shell') {
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['show_hub'])) {
                $out['show_hub'] = !empty($props['show_hub']);
            }
            if (isset($props['require_login'])) {
                $out['require_login'] = !empty($props['require_login']);
            }
            if (isset($props['active'])) {
                $active = preg_replace('/[^a-z0-9_]/', '', (string) $props['active']);
                if ($active !== '') {
                    $out['active'] = $active;
                }
            }
        }
        if ($comp === 'plays_list' && isset($props['show_clear'])) {
            $out['show_clear'] = !empty($props['show_clear']);
        }
        if ($comp === 'pay_shell' && isset($props['default_tab'])) {
            $tab = (string) $props['default_tab'];
            if ($tab === 'recharge' || $tab === 'upgrade') {
                $out['default_tab'] = $tab;
            }
        }
        if (in_array($comp, array('pay_shell', 'benefits_panel'), true) && isset($props['variant'])) {
            $v = (string) $props['variant'];
            if ($v === 'default' || $v === 'minimal') {
                $out['variant'] = $v;
            }
        }
        if ($comp === 'auth_page') {
            if (isset($props['panel'])) {
                $panel = (string) $props['panel'];
                if (in_array($panel, array('login', 'findpass', 'findpass_msg'), true)) {
                    $out['panel'] = $panel;
                }
            }
            if (isset($props['expand_invite'])) {
                $out['expand_invite'] = !empty($props['expand_invite']);
            }
            if (isset($props['hero_title'])) {
                $out['hero_title'] = mac_filter_xss((string) $props['hero_title']);
            }
            if (isset($props['hero_desc'])) {
                $out['hero_desc'] = mac_filter_xss((string) $props['hero_desc']);
            }
            if (isset($props['hero_image'])) {
                $out['hero_image'] = preg_replace('/[^a-zA-Z0-9_\\/.\\-~%]/', '', (string) $props['hero_image']);
            }
            if (isset($props['hero_image_mobile'])) {
                $out['hero_image_mobile'] = preg_replace('/[^a-zA-Z0-9_\\/.\\-~%]/', '', (string) $props['hero_image_mobile']);
            }
            if (isset($props['show_hero'])) {
                $out['show_hero'] = !empty($props['show_hero']);
            }
            if (isset($props['layout'])) {
                $layout = (string) $props['layout'];
                if (in_array($layout, array('split', 'center', 'minimal'), true)) {
                    $out['layout'] = $layout;
                }
            }
        }
        if ($comp === 'vod_detail') {
            if (isset($props['show_intro'])) {
                $out['show_intro'] = !empty($props['show_intro']);
            }
            if (isset($props['card'])) {
                $card = (string) $props['card'];
                if ($card === 'poster-v' || $card === 'poster-h') {
                    $out['card'] = $card;
                }
            }
            if (isset($props['ratio'])) {
                $ratio = $this->sanitizeCoverRatioValue($props['ratio']);
                if ($ratio !== null) {
                    $out['ratio'] = $ratio;
                }
            }
            $wh = $this->sanitizeCoverRatioWh(
                isset($props['ratio_w']) ? $props['ratio_w'] : 0,
                isset($props['ratio_h']) ? $props['ratio_h'] : 0
            );
            if ($wh !== null) {
                $out['ratio_w'] = $wh[0];
                $out['ratio_h'] = $wh[1];
            }
        }
        if ($comp === 'art_detail') {
            if (isset($props['show_toc'])) {
                $out['show_toc'] = !empty($props['show_toc']);
            }
            if (isset($props['show_intro'])) {
                $out['show_intro'] = !empty($props['show_intro']);
            }
            if (isset($props['card'])) {
                $card = (string) $props['card'];
                if ($card === 'poster-v' || $card === 'poster-h') {
                    $out['card'] = $card;
                }
            }
            if (isset($props['ratio'])) {
                $ratio = $this->sanitizeCoverRatioValue($props['ratio']);
                if ($ratio !== null) {
                    $out['ratio'] = $ratio;
                }
            }
            $wh = $this->sanitizeCoverRatioWh(
                isset($props['ratio_w']) ? $props['ratio_w'] : 0,
                isset($props['ratio_h']) ? $props['ratio_h'] : 0
            );
            if ($wh !== null) {
                $out['ratio_w'] = $wh[0];
                $out['ratio_h'] = $wh[1];
            }
        }
        if ($comp === 'manga_detail') {
            if (isset($props['show_chapters'])) {
                $out['show_chapters'] = !empty($props['show_chapters']);
            }
            if (isset($props['show_intro'])) {
                $out['show_intro'] = !empty($props['show_intro']);
            }
            if (isset($props['card'])) {
                $card = (string) $props['card'];
                if ($card === 'poster-v' || $card === 'poster-h') {
                    $out['card'] = $card;
                }
            }
            if (isset($props['ratio'])) {
                $ratio = $this->sanitizeCoverRatioValue($props['ratio']);
                if ($ratio !== null) {
                    $out['ratio'] = $ratio;
                }
            }
            $wh = $this->sanitizeCoverRatioWh(
                isset($props['ratio_w']) ? $props['ratio_w'] : 0,
                isset($props['ratio_h']) ? $props['ratio_h'] : 0
            );
            if ($wh !== null) {
                $out['ratio_w'] = $wh[0];
                $out['ratio_h'] = $wh[1];
            }
        }
        if ($comp === 'novel_player') {
            if (isset($props['show_settings'])) {
                $out['show_settings'] = !empty($props['show_settings']);
            }
            if (isset($props['show_share'])) {
                $out['show_share'] = !empty($props['show_share']);
            }
            if (isset($props['show_catalog_btn'])) {
                $out['show_catalog_btn'] = !empty($props['show_catalog_btn']);
            }
            if (isset($props['show_fav'])) {
                $out['show_fav'] = !empty($props['show_fav']);
            }
        }
        if ($comp === 'novel_chapter_catalog') {
            if (isset($props['show_chapter_no'])) {
                $out['show_chapter_no'] = !empty($props['show_chapter_no']);
            }
        }
        if ($comp === 'manga_mosaic_hero') {
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 6 && $n <= 36) {
                    $out['num'] = $n;
                }
            }
            if (isset($props['by'])) {
                $by = (string) $props['by'];
                if (preg_match('/^[a-z_]+$/', $by)) {
                    $out['by'] = $by;
                }
            }
        }
        if ($comp === 'manga_genre_nav') {
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 4 && $n <= 24) {
                    $out['num'] = $n;
                }
            }
            if (isset($props['more_url'])) {
                $url = (string) $props['more_url'];
                if (preg_match('/^[a-z0-9_\\/]+$/', $url)) {
                    $out['more_url'] = $url;
                }
            }
        }
        if ($comp === 'manga_home_spotlight') {
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['subtitle'])) {
                $out['subtitle'] = mac_filter_xss((string) $props['subtitle']);
            }
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 3 && $n <= 12) {
                    $out['num'] = $n;
                }
            }
            if (isset($props['by'])) {
                $by = (string) $props['by'];
                if (preg_match('/^[a-z_]+$/', $by)) {
                    $out['by'] = $by;
                }
            }
        }
        if ($comp === 'manga_home_row') {
            if (isset($props['preset'])) {
                $preset = (string) $props['preset'];
                if ($preset === 'hot' || $preset === 'buzz' || $preset === 'score') {
                    $out['preset'] = $preset;
                }
            }
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['subtitle'])) {
                $out['subtitle'] = mac_filter_xss((string) $props['subtitle']);
            }
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 4 && $n <= 20) {
                    $out['num'] = $n;
                }
            }
            if (isset($props['by'])) {
                $by = (string) $props['by'];
                if (preg_match('/^[a-z_]+$/', $by)) {
                    $out['by'] = $by;
                }
            }
        }
        if ($comp === 'manga_home_rank') {
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 3 && $n <= 12) {
                    $out['num'] = $n;
                }
            }
        }
        if ($comp === 'media_related_panel') {
            if (isset($props['title'])) {
                $out['title'] = mac_filter_xss((string) $props['title']);
            }
            if (isset($props['title_lang'])) {
                $tl = (string) $props['title_lang'];
                if (preg_match('/^[a-z0-9_]+$/', $tl)) {
                    $out['title_lang'] = $tl;
                }
            }
            if (isset($props['num'])) {
                $n = (int) $props['num'];
                if ($n >= 4 && $n <= 24) {
                    $out['num'] = $n;
                }
            }
            if (isset($props['by'])) {
                $by = (string) $props['by'];
                if (preg_match('/^[a-z_]+$/', $by)) {
                    $out['by'] = $by;
                }
            }
            if (isset($props['relation'])) {
                $out['relation'] = !empty($props['relation']);
            }
            if (isset($props['context'])) {
                $ctx = (string) $props['context'];
                if ($ctx === 'detail' || $ctx === 'play') {
                    $out['context'] = $ctx;
                }
            }
            if (isset($props['bind'])) {
                $bind = (string) $props['bind'];
                if ($bind === 'vod' || $bind === 'art' || $bind === 'manga') {
                    $out['bind'] = $bind;
                }
            }
            if (isset($props['layout'])) {
                $layout = (string) $props['layout'];
                if ($layout === 'grid' || $layout === 'rows') {
                    $out['layout'] = $layout;
                }
            }
            if (isset($props['card'])) {
                $card = (string) $props['card'];
                if ($card === 'poster-v' || $card === 'poster-h') {
                    $out['card'] = $card;
                }
            }
            if (isset($props['ratio'])) {
                $ratio = $this->sanitizeCoverRatioValue($props['ratio']);
                if ($ratio !== null) {
                    $out['ratio'] = $ratio;
                }
            }
            $wh = $this->sanitizeCoverRatioWh(
                isset($props['ratio_w']) ? $props['ratio_w'] : 0,
                isset($props['ratio_h']) ? $props['ratio_h'] : 0
            );
            if ($wh !== null) {
                $out['ratio_w'] = $wh[0];
                $out['ratio_h'] = $wh[1];
            }
        }
        if ($comp === 'rank_feed' && isset($props['style'])) {
            $st = (string) $props['style'];
            if ($st === 'panel' || $st === 'inline') {
                $out['style'] = $st;
            }
        }
        if (isset($props['min'])) {
            $out['min'] = preg_replace('/[^0-9a-z%]/i', '', (string) $props['min']);
        }
        if (isset($props['cols']) && is_array($props['cols'])) {
            $cols = array();
            foreach ($props['cols'] as $c) {
                $n = (int) $c;
                if ($n > 0 && $n <= 12) {
                    $cols[] = $n;
                }
            }
            if (!empty($cols)) {
                $out['cols'] = $cols;
            }
        }
        if (isset($props['stack_at'])) {
            $st = (int) $props['stack_at'];
            if ($st >= 320 && $st <= 1600) {
                $out['stack_at'] = $st;
            }
        }
        if ($comp === 'site_nav') {
            if (isset($props['max'])) {
                $m = (int) $props['max'];
                if ($m >= 1 && $m <= 20) {
                    $out['max'] = $m;
                }
            }
            if (isset($props['show_search'])) {
                $out['show_search'] = (bool) $props['show_search'];
            }
            if (isset($props['type_ids']) && is_array($props['type_ids'])) {
                $ids = array();
                foreach ($props['type_ids'] as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
                $out['type_ids'] = $ids;
            }
            if (isset($props['logo'])) {
                $logo = trim(mac_filter_xss((string) $props['logo']));
                if ($logo === '') {
                    $out['logo'] = '';
                } elseif (preg_match('#^(https?:)?//#i', $logo) || $logo[0] === '/' || $logo[0] === '.') {
                    $out['logo'] = mb_substr($logo, 0, 500, 'UTF-8');
                } else {
                    $out['logo'] = mb_substr(preg_replace('/[^a-z0-9_\\/.\\-]/i', '', $logo), 0, 500);
                }
            }
        }
        if ($comp === 'header_decor') {
            if (isset($props['width'])) {
                $w = preg_replace('/[^0-9a-z%]/i', '', (string) $props['width']);
                $out['width'] = $w !== '' ? mb_substr($w, 0, 20, 'UTF-8') : '100%';
            }
            if (isset($props['height'])) {
                $h = (int) $props['height'];
                if ($h >= 0 && $h <= 800) {
                    $out['height'] = $h;
                }
            }
            if (isset($props['image'])) {
                $image = trim(mac_filter_xss((string) $props['image']));
                if ($image === '') {
                    $out['image'] = '';
                } elseif (preg_match('#^(https?:)?//#i', $image) || $image[0] === '/' || $image[0] === '.') {
                    $out['image'] = mb_substr($image, 0, 500, 'UTF-8');
                } else {
                    $out['image'] = mb_substr(preg_replace('/[^a-z0-9_\\/.\\-]/i', '', $image), 0, 500);
                }
            }
        }
        if ($comp === 'channel_strip') {
            if (isset($props['compact'])) {
                $out['compact'] = (bool) $props['compact'];
            }
            if (isset($props['chip_limit'])) {
                $cl = (int) $props['chip_limit'];
                if ($cl >= 3 && $cl <= 20) {
                    $out['chip_limit'] = $cl;
                }
            }
            if (isset($props['show_icons'])) {
                $out['show_icons'] = (bool) $props['show_icons'];
            }
            if (isset($props['show_more'])) {
                $out['show_more'] = (bool) $props['show_more'];
            }
            if (isset($props['type_ids']) && is_array($props['type_ids'])) {
                $ids = array();
                foreach ($props['type_ids'] as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
                $out['type_ids'] = $ids;
            }
        }
        if ($comp === 'site_footer') {
            if (isset($props['variant'])) {
                $v = (string) $props['variant'];
                if ($v === 'default' || $v === 'compact') {
                    $out['variant'] = $v;
                }
            }
            if (isset($props['copyright'])) {
                $out['copyright'] = mb_substr(mac_filter_xss((string) $props['copyright']), 0, 500, 'UTF-8');
            }
            if (isset($props['show_nav'])) {
                $out['show_nav'] = (bool) $props['show_nav'];
            }
            if (isset($props['show_friends'])) {
                $out['show_friends'] = (bool) $props['show_friends'];
            }
            if (isset($props['logo'])) {
                $logo = trim(mac_filter_xss((string) $props['logo']));
                if ($logo === '') {
                    $out['logo'] = '';
                } elseif (preg_match('#^(https?:)?//#i', $logo) || $logo[0] === '/' || $logo[0] === '.') {
                    $out['logo'] = mb_substr($logo, 0, 500, 'UTF-8');
                } else {
                    $out['logo'] = mb_substr(preg_replace('/[^a-z0-9_\\/.\\-]/i', '', $logo), 0, 500);
                }
            }
        }
        if ($comp === 'bottom_nav') {
            $kit = new PageBuilderKit();
            return $kit->normalizeBottomNavProps(is_array($props) ? $props : array());
        }
        if ($comp === 'ads_grid') {
            $kit = new PageBuilderKit();
            return $kit->normalizeAdsGridProps(is_array($props) ? $props : array());
        }
        if ($comp === 'vod_list_np2') {
            $kit = new PageBuilderKit();
            return $kit->normalizeVodListNp2Props(is_array($props) ? $props : array());
        }
        return $out;
    }

    /** 补全首页 chrome（导航/顶栏装饰/频道条）与页脚组件，并与 navbar / attributes 同步 */
    private function enrichHomepagePage(array $page, array $cfg = array())
    {
        $page = $this->stripAppTopChromeManagedRegions($page);
        $hasRegions = isset($page['regions']) && is_array($page['regions']);
        if (!$hasRegions) {
            $page = $this->sanitizeHomepagePage($page);
        }
        $navbar = isset($cfg['navbar']) && is_array($cfg['navbar']) ? $cfg['navbar'] : array();
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $type_ids = isset($navbar['type_ids']) && is_array($navbar['type_ids']) ? $navbar['type_ids'] : array();
        $clean_ids = array();
        foreach ($type_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        $kit = new PageBuilderKit();
        $chromeOpts = isset($page['chrome_opts']) && is_array($page['chrome_opts']) ? $page['chrome_opts'] : array();
        if ($hasRegions) {
            $heroBlocks = isset($page['regions']['hero']) && is_array($page['regions']['hero'])
                ? $page['regions']['hero'] : array();
            $legacyChrome = isset($page['chrome']) && is_array($page['chrome']) ? $page['chrome'] : array();
            $hasDecor = false;
            foreach ($heroBlocks as $b) {
                if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'header_decor') {
                    $hasDecor = true;
                    break;
                }
            }
            // hero 空但 chrome 仍有 header_decor（含 image）：保留装饰，勿把 chrome_opts 打成 false 后被 normalize 裁掉
            if (!$hasDecor) {
                foreach ($legacyChrome as $b) {
                    if (!is_array($b) || empty($b['component']) || (string) $b['component'] !== 'header_decor') {
                        continue;
                    }
                    $hasDecor = true;
                    $page['regions']['hero'] = array($b);
                    $heroBlocks = $page['regions']['hero'];
                    break;
                }
            }
            if (!$hasDecor && !empty($chromeOpts['header_decor'])) {
                $hasDecor = true;
            }
            $chromeOpts['header_decor'] = $hasDecor;
            if (!$hasDecor) {
                $page['regions']['hero'] = array();
            }
            if (empty($legacyChrome)) {
                $legacyChrome = array();
                if ($hasDecor) {
                    foreach ($heroBlocks as $b) {
                        if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'header_decor') {
                            $legacyChrome[] = $b;
                        }
                    }
                }
                $channelBlocks = isset($page['regions']['channel']) && is_array($page['regions']['channel'])
                    ? $page['regions']['channel'] : array();
                foreach ($channelBlocks as $b) {
                    if (is_array($b) && !empty($b['component'])) {
                        $legacyChrome[] = $b;
                    }
                }
            } else {
                $channelBlocks = isset($page['regions']['channel']) && is_array($page['regions']['channel'])
                    ? $page['regions']['channel'] : array();
                $legacyChrome = $this->mergeRegionBlockIntoChrome($legacyChrome, $heroBlocks, 'header_decor');
                $legacyChrome = $this->mergeRegionBlockIntoChrome($legacyChrome, $channelBlocks, 'channel_strip');
            }
            $page['chrome'] = $legacyChrome;
        }
        if ($this->homepageUsesAppTopChrome($page) && $this->homepageChromeFallbacksDisabled($page)) {
            $chromeOpts['header_decor'] = false;
            $chromeOpts['channel_strip'] = false;
            if ($hasRegions) {
                $page['regions']['hero'] = array();
                $page['regions']['channel'] = array();
                $page['regions']['site_header'] = array();
            }
            $page['chrome'] = array();
        } else {
            $page['chrome'] = $kit->normalizeChrome($page['chrome'], $clean_ids, $chromeOpts);
        }
        $page['chrome'] = $this->applyGlobalAttributesToChrome($page['chrome'], $attr);
        $page = $this->syncHeroDecorRegionFromChrome($page);
        $page['chrome_opts'] = $chromeOpts;
        $footerOpts = array();
        if (isset($attr['footer_copyright'])) {
            $footerOpts['copyright'] = (string) $attr['footer_copyright'];
        }
        if (isset($attr['logo'])) {
            $footerOpts['logo'] = (string) $attr['logo'];
        }
        $page['footer'] = $kit->normalizeFooter(isset($page['footer']) ? $page['footer'] : array(), $footerOpts);
        if ($hasRegions) {
            $footerBlocks = isset($page['regions']['footer']) && is_array($page['regions']['footer'])
                ? $page['regions']['footer'] : array();
            array_unshift($footerBlocks, $page['footer']);
            $page['regions']['footer'] = $kit->normalizeFooterBlocks($footerBlocks, $footerOpts);
        }

        foreach ($page['chrome'] as $i => $c) {
            if (!is_array($c) || empty($c['component'])) {
                continue;
            }
            $comp = (string) $c['component'];
            if ($comp !== 'site_nav' && $comp !== 'channel_strip') {
                continue;
            }
            if (!isset($page['chrome'][$i]['props']) || !is_array($page['chrome'][$i]['props'])) {
                $page['chrome'][$i]['props'] = array();
            }
            $navIds = isset($page['chrome'][$i]['props']['type_ids']) ? $page['chrome'][$i]['props']['type_ids'] : array();
            if (empty($navIds) && !empty($clean_ids)) {
                $page['chrome'][$i]['props']['type_ids'] = $clean_ids;
            }
        }
        if ($this->homepageUsesAppTopChrome($page)) {
            $page = $this->stripAppTopChromeManagedRegions($page);
        }
        return $page;
    }

    /** 用 regions 内最新块覆盖 legacy chrome 中同名组件（保留 props 如 header_decor.image） */
    private function mergeRegionBlockIntoChrome(array $chrome, array $regionBlocks, $compId)
    {
        $compId = (string) $compId;
        $regionBlock = null;
        foreach ($regionBlocks as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === $compId) {
                $regionBlock = $b;
                break;
            }
        }
        if ($regionBlock === null) {
            return $chrome;
        }
        $out = array();
        $replaced = false;
        foreach ($chrome as $c) {
            if (!is_array($c) || empty($c['component'])) {
                continue;
            }
            if ((string) $c['component'] === $compId) {
                $out[] = $regionBlock;
                $replaced = true;
            } else {
                $out[] = $c;
            }
        }
        if (!$replaced) {
            $out[] = $regionBlock;
        }
        return $out;
    }

    /** 编译前把 normalize 后的 header_decor props（含 image）写回 regions.hero，避免 chrome/hero 双轨不一致 */
    private function syncHeroDecorRegionFromChrome(array $page)
    {
        if (!isset($page['regions']['hero']) || !is_array($page['regions']['hero'])) {
            return $page;
        }
        $decorProps = null;
        if (isset($page['chrome']) && is_array($page['chrome'])) {
            foreach ($page['chrome'] as $c) {
                if (!is_array($c) || empty($c['component']) || (string) $c['component'] !== 'header_decor') {
                    continue;
                }
                $decorProps = isset($c['props']) && is_array($c['props']) ? $c['props'] : array();
                break;
            }
        }
        if ($decorProps === null) {
            return $page;
        }
        foreach ($page['regions']['hero'] as $idx => $block) {
            if (!is_array($block) || empty($block['component']) || (string) $block['component'] !== 'header_decor') {
                continue;
            }
            $prev = isset($block['props']) && is_array($block['props']) ? $block['props'] : array();
            $page['regions']['hero'][$idx]['props'] = array_merge($prev, $decorProps);
            break;
        }
        return $page;
    }

    /** 全站 Logo 写入 site_nav（与 theme-design.json 的 logo 字段双通道一致） */
    private function applyGlobalAttributesToChrome(array $chrome, array $attr)
    {
        $logo = isset($attr['logo']) ? trim((string) $attr['logo']) : '';
        if ($logo === '') {
            return $chrome;
        }
        foreach ($chrome as $i => $c) {
            if (!is_array($c) || empty($c['component']) || (string) $c['component'] !== 'site_nav') {
                continue;
            }
            if (!isset($chrome[$i]['props']) || !is_array($chrome[$i]['props'])) {
                $chrome[$i]['props'] = array();
            }
            $chrome[$i]['props']['logo'] = $logo;
        }
        return $chrome;
    }

    /** 站点框架 Logo / 头像路径校验（与 site_nav props.logo 一致） */
    private function sanitizeGlobalLogo($logo)
    {
        $logo = trim(mac_filter_xss((string) $logo));
        if ($logo === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $logo) || $logo[0] === '/' || $logo[0] === '.') {
            return mb_substr($logo, 0, 500, 'UTF-8');
        }
        return mb_substr(preg_replace('/[^a-z0-9_\\/.\\-]/i', '', $logo), 0, 500);
    }

    /**
     * 设计向导非 PB 步骤的数据消毒（attributes / navbar / footer）。
     *
     * site_chrome 分支自带白名单，这里让 else 兜底入口对齐同一套规则：
     * attributes 只收 logo / footer_copyright / default_avatar；navbar 只收整型化
     * type_ids；footer 收 type_ids + 链接列表（文案过滤 XSS、URL 走 logo 同款校验）。
     * 未知步骤保守丢弃。
     */
    private function sanitizeWizardStep($step, $data)
    {
        if (!is_array($data)) {
            return array();
        }
        if ($step === 'attributes') {
            $out = array();
            $allowedAttr = array('logo', 'footer_copyright', 'default_avatar');
            foreach ($allowedAttr as $ak) {
                if (array_key_exists($ak, $data)) {
                    if ($ak === 'footer_copyright') {
                        $out[$ak] = mb_substr(mac_filter_xss((string) $data[$ak]), 0, 500, 'UTF-8');
                    } else {
                        $out[$ak] = $this->sanitizeGlobalLogo($data[$ak]);
                    }
                }
            }
            $presets = $this->schemePresets();
            if (isset($data['color_scheme'])) {
                $scheme = trim((string) $data['color_scheme']);
                if ($scheme === 'custom') {
                    $out['color_scheme'] = 'custom';
                } elseif (isset($presets[$scheme])) {
                    $out['color_scheme'] = $scheme;
                    $out['colors'] = $presets[$scheme]['colors'];
                }
            }
            if (isset($data['colors']) && is_array($data['colors'])) {
                $defColors = $presets['indigo']['colors'];
                $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
                $colors = array();
                foreach ($keys as $k) {
                    $v = isset($data['colors'][$k]) ? $data['colors'][$k] : '';
                    if ($this->validHex($v)) {
                        $colors[$k] = $v;
                    }
                }
                if (!empty($colors)) {
                    $out['colors'] = array_merge($defColors, $colors);
                    if (!isset($out['color_scheme'])) {
                        $out['color_scheme'] = 'custom';
                    }
                }
            }
            $fontOpts = array_keys($this->fontOptions());
            if (isset($data['font_family'])) {
                $f = trim((string) $data['font_family']);
                if ($f !== '' && in_array($f, $fontOpts, true)) {
                    $out['font_family'] = $f;
                }
            }
            if (isset($data['font_size'])) {
                $fs = intval($data['font_size']);
                if ($fs >= 12 && $fs <= 22) {
                    $out['font_size'] = $fs;
                }
            }
            return $out;
        }
        if ($step === 'navbar') {
            return array('type_ids' => $this->sanitizeTypeIds(isset($data['type_ids']) ? $data['type_ids'] : array()));
        }
        if ($step === 'footer') {
            $links = array();
            if (isset($data['links']) && is_array($data['links'])) {
                foreach ($data['links'] as $l) {
                    if (!is_array($l)) {
                        continue;
                    }
                    $text = mb_substr(mac_filter_xss((string) (isset($l['text']) ? $l['text'] : '')), 0, 100, 'UTF-8');
                    $url = $this->sanitizeGlobalLogo(isset($l['url']) ? $l['url'] : '');
                    if ($text === '' && $url === '') {
                        continue;
                    }
                    $links[] = array('text' => $text, 'url' => $url);
                }
            }
            return array(
                'type_ids' => $this->sanitizeTypeIds(isset($data['type_ids']) ? $data['type_ids'] : array()),
                'links' => $links,
            );
        }
        return array();
    }

    /** 分类 id 列表整型化（navbar/footer 共用） */
    private function sanitizeTypeIds($ids)
    {
        if (!is_array($ids)) {
            return array();
        }
        $out = array();
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    // ───────────────────────── 配置构建 ─────────────────────────

    /** 顶级分类（用于导航栏默认 type_ids 及视图渲染） */
    private function topTypeTree()
    {
        $list = model('Type')
            ->where(array('type_status' => 1, 'type_pid' => 0))
            ->order('type_sort asc')
            ->field('type_id,type_name,type_mid')
            ->select();
        $tree = array();
        if (!empty($list)) {
            foreach ($list as $v) {
                $tree[] = array(
                    'id' => (int)$v['type_id'],
                    'name' => $v['type_name'],
                    'mid' => (int)$v['type_mid'],
                );
            }
        }
        return $tree;
    }

    /** 完整分类树（顶级 + 各自子分类），用于导航栏步骤的树形选择 */
    private function typeTreeFull()
    {
        $list = model('Type')
            ->where(array('type_status' => 1))
            ->order('type_sort asc')
            ->field('type_id,type_name,type_mid,type_pid')
            ->select();

        $tops = array();
        $children = array();
        if (!empty($list)) {
            foreach ($list as $v) {
                $node = array(
                    'id'  => (int)$v['type_id'],
                    'name' => $v['type_name'],
                    'mid' => (int)$v['type_mid'],
                    'pid' => (int)$v['type_pid'],
                );
                if ((int)$v['type_pid'] === 0) {
                    $node['children'] = array();
                    $tops[(int)$v['type_id']] = $node;
                } else {
                    $children[(int)$v['type_pid']][] = $node;
                }
            }
        }
        foreach ($children as $pid => $kids) {
            if (isset($tops[$pid])) {
                $tops[$pid]['children'] = $kids;
            }
        }
        return array_values($tops);
    }

    /** 扁平分类列表（用于板块属性中的「分类」下拉），子分类带缩进前缀 */
    private function catsFlat()
    {
        $flat = array();
        foreach ($this->typeTreeFull() as $p) {
            $flat[] = array('id' => (int)$p['id'], 'name' => $p['name'], 'mid' => (int)$p['mid']);
            if (!empty($p['children'])) {
                foreach ($p['children'] as $c) {
                    $flat[] = array('id' => (int)$c['id'], 'name' => '　├ ' . $c['name'], 'mid' => (int)$c['mid']);
                }
            }
        }
        return $flat;
    }

    /** 预览 URL（真实前台 + td_preview=1 + td_theme=所选主题 + 签名 token） */
    private function previewUrl($route = null, array $opts = array())
    {
        if ($route === null || $route === '') {
            $route = 'index/index';
        }
        $route = PageRegistry::normalizeRoute($route);
        if (PageRegistry::isAuthModalVirtualRoute($route)) {
            $url = $this->siteHome();
        } elseif ($route === 'index/index') {
            $url = $this->siteHome();
        } else {
            $url = $this->sitePageUrl($route);
        }
        $url = ThemePreviewAuth::appendQuery($url, $this->theme);
        if (PageRegistry::isAuthModalVirtualRoute($route)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'open_auth_modal=1';
        } elseif (PageRegistry::isMemberShellRoute($route)) {
            $mock = isset($opts['member_mock']) ? (string) $opts['member_mock'] : 'member';
            if (!in_array($mock, MemberPreviewMock::MODES, true)) {
                $mock = 'member';
            }
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'td_member_mock=' . rawurlencode($mock);
        }
        return $url;
    }

    /** 内页预览绝对 URL */
    private function sitePageUrl($route)
    {
        $path = PageRegistry::previewPath($route);
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        $scheme = $this->request->scheme();
        $host = $this->request->host();
        $base = defined('MAC_PATH') ? (string) MAC_PATH : '/';
        if ($base === '') {
            $base = '/';
        }
        if (substr($base, -1) !== '/') {
            $base .= '/';
        }
        return $scheme . '://' . $host . $base . ltrim($path, '/');
    }

    /** 选定主题自带的 logo 资源 URL（空态预览展示其内置 logo）；没有则空 */
    private function themeLogoUrl()
    {
        $cands = array('asset/img/logo.svg', 'asset/img/logo.png', 'asset/img/logo_black.png', 'asset/img/logo.jpg');
        foreach ($cands as $rel) {
            $r = 'template/' . $this->theme . '/' . $rel;
            if (is_file(ROOT_PATH . $r)) {
                return MAC_PATH . $r;
            }
        }
        return '';
    }

    /** 选定主题的默认头像 URL（主题自带优先，否则全局 static_new 头像） */
    private function themeAvatarUrl()
    {
        $r = 'template/' . $this->theme . '/asset/img/touxiang.png';
        if (is_file(ROOT_PATH . $r)) {
            return MAC_PATH . $r;
        }
        return MAC_PATH . 'static_new/images/touxiang.png';
    }

    /**
     * 主题在「未配置版权」时实际显示的默认版权文案，
     * 与 prism footer.html 的回退逻辑一致：© 年份 站点URL [E-Mail] [备案]。
     * 仅用于 wizard 的输入占位提示（留空即用主题内置版权）。
     */
    private function defaultFooterCopyright()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $url = isset($site['site_url']) ? trim((string)$site['site_url']) : '';
        $copy = '© ' . date('Y') . ($url !== '' ? ' ' . $url : '');
        $email = isset($site['site_email']) ? trim((string)$site['site_email']) : '';
        if ($email !== '') {
            $copy .= '  E-Mail：' . $email;
        }
        $icp = isset($site['site_icp']) ? trim((string)$site['site_icp']) : '';
        if ($icp !== '') {
            $copy .= '  ' . $icp;
        }
        return $copy;
    }

    /**
     * 默认配置（indigo 预设 + Inter 字体 + 16px + 站点 logo/版权 + 全部顶级分类 + 全部区块）
     */
    private function buildDefaults()
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $presets = $this->schemePresets();

        // 默认 logo 留空：prism 主题在未配置 logo 时使用内置 SVG 标识，
        // 不应预填站点 logo.jpg（那会覆盖主题自带标识）。留空 = 用主题默认。
        $logo = '';

        // 默认版权留空：使用 prism 主题内置版权（© 年份 站点URL [E-Mail/备案]），
        // 与前台实际显示一致；不再预填站点名（那会与主题真正显示的版权不符）。
        $copyright = '';

        // 导航默认全部顶级分类
        $type_ids = array();
        foreach ($this->topTypeTree() as $t) {
            $type_ids[] = (int)$t['id'];
        }

        // Twig/PB：page.blocks；Think 遗留主题（prism）：homepage.sections
        if ($this->isLegacySectionsTheme()) {
            $sections = $this->loadLegacyDesignSections();
            if ($sections === null) {
                $sections = $this->homepageDefaultSections();
            }
            return array(
                'theme' => $this->theme,
                'updated_at' => time(),
                'attributes' => array(
                    'color_scheme' => 'indigo',
                    'colors' => $presets['indigo']['colors'],
                    'font_family' => "Inter, 'Noto Sans SC', system-ui, sans-serif",
                    'font_size' => 16,
                    'logo' => $logo,
                    'footer_copyright' => $copyright,
                    'default_avatar' => '',
                ),
                'navbar' => array(
                    'type_ids' => $type_ids,
                ),
                'footer' => array(
                    'links' => array(),
                ),
                'homepage' => array(
                    'sections' => $sections,
                ),
            );
        }

        $homepagePage = $this->homepageDefaultPage();

        return array(
            'theme' => $this->theme,
            'updated_at' => time(),
            'attributes' => array(
                'color_scheme' => 'indigo',
                'colors' => $presets['indigo']['colors'],
                'font_family' => "Inter, 'Noto Sans SC', system-ui, sans-serif",
                'font_size' => 16,
                'logo' => $logo,
                'footer_copyright' => $copyright,
                'default_avatar' => '',
            ),
            'navbar' => array(
                'type_ids' => $type_ids,
            ),
            'footer' => array(
                'links' => array(),
            ),
            'homepage' => array(
                'page' => $homepagePage,
            ),
            'pages' => array(
                'index/index' => $homepagePage,
            ),
        );
    }

    /** 读取 JSON 文件为数组，失败返回 null */
    private function readJson($file)
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : null;
    }

    /** 写入 JSON 文件 */
    private function writeJson($file, $data)
    {
        $dir = dirname($file);
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // JSON_UNESCAPED_UNICODE 遇到非法 UTF-8 字节会让 json_encode 直接返回 false（不抛异常/警告），
            // 导致整次保存在磁盘上无声无息地消失。先修复非法字节再重试一次。
            $json = json_encode($this->sanitizeUtf8($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return false;
            }
        }
        return @file_put_contents($file, $json) !== false;
    }

    /** 递归修复字符串中的非法 UTF-8 字节，避免 json_encode 静默失败 */
    private function sanitizeUtf8($data)
    {
        if (is_array($data)) {
            $out = array();
            foreach ($data as $k => $v) {
                $out[$k] = $this->sanitizeUtf8($v);
            }
            return $out;
        }
        if (is_string($data) && $data !== '' && !mb_check_encoding($data, 'UTF-8')) {
            $fixed = function_exists('iconv') ? @iconv('UTF-8', 'UTF-8//IGNORE', $data) : false;
            if ($fixed === false || $fixed === null) {
                $fixed = @mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            return ($fixed !== false && $fixed !== null) ? $fixed : '';
        }
        return $data;
    }

    /** 取当前草稿配置（无草稿则从已发布初始化，再无则用默认） */
    private function currentDraft()
    {
        $cfg = $this->readJson($this->draftFile());
        if (is_array($cfg)) {
            $cfg = PageRegistry::migrateConfigPages($cfg);
            $migrated = ThemeDesignDraftMigrator::migrate($cfg);
            $cfg = $migrated['cfg'];
            if (!empty($migrated['changed']) && is_file($this->draftFile())) {
                $this->writeJson($this->draftFile(), $cfg);
                $this->writeArtifacts($cfg, true);
            }
            return $cfg;
        }
        $cfg = $this->readJson($this->publishedFile());
        if (is_array($cfg)) {
            return PageRegistry::migrateConfigPages($cfg);
        }
        return $this->buildDefaults();
    }

    // ───────────────────────── 产物生成（APPLY） ─────────────────────────

    /**
     * 把配置写成主题预览/线上产物。
     * @param array $cfg     设计配置
     * @param bool  $isDraft true=草稿产物（.draft），false=线上产物
     * @param array $opts    ['sync_kit'=>bool,'compile_pages'=>bool] 是否同步 page-builder-components；
     *                       compile_pages 默认与 sync_kit 或发布一致；init_page / 新建主题等需显式开启
     * @return bool
     */
    private function writeArtifacts($cfg, $isDraft, $opts = array())
    {
        if (!is_array($opts)) {
            $opts = array();
        }
        $cfg = PageRegistry::migrateConfigPages($cfg);
        $cssOk = $this->writeOverrideCss($cfg, $isDraft);
        $jsonOk = $this->writeDesignJson($cfg, $isDraft);

        // 非 Twig 主题只写 override CSS + theme-design.json（sections），不同步 kit / 不编译 Twig
        if ($this->isLegacySectionsTheme()) {
            return $cssOk && $jsonOk;
        }

        $syncKit = array_key_exists('sync_kit', $opts) ? (bool) $opts['sync_kit'] : !$isDraft;
        $compilePages = array_key_exists('compile_pages', $opts)
            ? (bool) $opts['compile_pages']
            : ($syncKit || !$isDraft);

        $pages = $this->collectCompiledPages($cfg);
        $kit = new PageBuilderKit();
        $allIds = array();
        foreach ($pages as $route => $page) {
            if (!is_array($page)) {
                continue;
            }
            $enriched = $this->enrichPage($route, $page, $cfg);
            $ids = $kit->collectPageComponentIds($enriched);
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));
        $kitOk = true;
        if ($syncKit) {
            if (!$isDraft) {
                // 发布必须全量同步。upgrade=true + onlyIds 会裁掉草稿 JSON 未列出的组件
                // （顶栏嵌套的 notify_bell、layout 的 auth_modals、其它路由的播放器），前台找不到 Twig 即白屏。
                $kitOk = $kit->syncToTheme($this->themeDir(), true, null);
            } elseif (!empty($allIds)) {
                $kitOk = $kit->syncToTheme($this->themeDir(), false, $allIds);
            }
        }

        $compileOk = true;
        // 仅在需要 kit 的步骤（或发布 / 显式 compile_pages）时编译页面 Twig，避免空跑
        if ($compilePages) {
            foreach ($pages as $route => $page) {
                if (!is_array($page)) {
                    continue;
                }
                $compileOk = $this->compilePageTwigForRoute($route, $page, $isDraft, $cfg) && $compileOk;
            }
        }
        return $cssOk && $jsonOk && $kitOk && $compileOk;
    }

    /** Twig 主题：将 homepage.page.blocks 编译为完整 index.twig（或预览用 index.draft.twig） */
    private function compileHomepageIndexTwig($cfg, $isDraft)
    {
        $page = $this->getPageFromConfig($cfg, 'index/index');
        return $this->compilePageTwigForRoute('index/index', $page, $isDraft, $cfg);
    }

    /**
     * 主题 layout/base.twig 是否声明了 Page Builder 用的 Twig 区块。
     * 未声明时编译器会把 chrome 插入 content 前，避免子模板 block 被静默丢弃。
     */
    private function themeLayoutBlockMap()
    {
        $map = array(
            'site_header' => false,
            'channel' => false,
            'hero' => false,
            'footer' => false,
        );
        $base = $this->themeDir() . 'html/layout/base.twig';
        if (!is_file($base)) {
            return $map;
        }
        $raw = @file_get_contents($base);
        if ($raw === false || $raw === '') {
            return $map;
        }
        $map['site_header'] = (strpos($raw, 'block site_header') !== false);
        $map['channel'] = (strpos($raw, 'block channel') !== false);
        $map['hero'] = (strpos($raw, 'block hero') !== false);
        $map['footer'] = (strpos($raw, 'block footer') !== false);
        return $map;
    }

    private function isTwigTheme()
    {
        $ini = $this->themeDir() . 'info.ini';
        if (is_file($ini)) {
            $parsed = @parse_ini_file($ini);
            if (is_array($parsed)) {
                $engine = strtolower(trim(isset($parsed['engine']) ? (string) $parsed['engine'] : ''));
                if ($engine === 'twig') {
                    return true;
                }
                if ($engine !== '' && $engine !== 'twig') {
                    return false;
                }
            }
        }
        return is_file($this->themeDir() . 'html/index/index.twig');
    }

    /** 校验十六进制颜色 */
    private function validHex($v)
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v);
    }

    /** 主色派生 token（显式 rgba，避免 CSS color-mix 在暗色下不可控） */
    private function accentDerivativeTokens($accentHex)
    {
        $hex = $this->normalizeHex($accentHex);
        if (!preg_match('/^#([0-9a-f]{6})$/', $hex, $m)) {
            return array(
                'hover' => '#ff85a8',
                'soft' => 'rgba(251, 114, 153, 0.12)',
                'tint' => 'rgba(251, 114, 153, 0.28)',
                'glow' => 'rgba(251, 114, 153, 0.25)',
                'border' => 'rgba(251, 114, 153, 0.18)',
                'border_strong' => 'rgba(251, 114, 153, 0.35)',
                'focus_ring' => 'rgba(251, 114, 153, 0.55)',
            );
        }
        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));
        $hoverR = (int)round($r * 0.88 + 255 * 0.12);
        $hoverG = (int)round($g * 0.88 + 255 * 0.12);
        $hoverB = (int)round($b * 0.88 + 255 * 0.12);
        return array(
            'hover' => sprintf('#%02x%02x%02x', $hoverR, $hoverG, $hoverB),
            'soft' => sprintf('rgba(%d, %d, %d, 0.12)', $r, $g, $b),
            'tint' => sprintf('rgba(%d, %d, %d, 0.28)', $r, $g, $b),
            'glow' => sprintf('rgba(%d, %d, %d, 0.25)', $r, $g, $b),
            'border' => sprintf('rgba(%d, %d, %d, 0.18)', $r, $g, $b),
            'border_strong' => sprintf('rgba(%d, %d, %d, 0.35)', $r, $g, $b),
            'focus_ring' => sprintf('rgba(%d, %d, %d, 0.55)', $r, $g, $b),
        );
    }

    private function accentDerivativeCssFragment($accentHex)
    {
        $d = $this->accentDerivativeTokens($accentHex);
        return '--c-accent-hover:' . $d['hover'] . ';'
            . '--c-accent-soft:' . $d['soft'] . ';'
            . '--c-accent-tint:' . $d['tint'] . ';'
            . '--c-accent-glow:' . $d['glow'] . ';'
            . '--c-accent-border:' . $d['border'] . ';'
            . '--c-accent-border-strong:' . $d['border_strong'] . ';'
            . '--c-accent-focus-ring:' . $d['focus_ring'] . ';';
    }

    /** 生成覆盖样式 css */
    private function writeOverrideCss($cfg, $isDraft)
    {
        $defaults = $this->schemePresets();
        $defColors = $defaults['indigo']['colors'];

        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $colors = isset($attr['colors']) && is_array($attr['colors']) ? $attr['colors'] : array();

        // 逐项校验颜色，非法回退默认（永不写出畸形 css）
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $c = array();
        foreach ($keys as $k) {
            $v = isset($colors[$k]) ? $colors[$k] : '';
            $c[$k] = $this->validHex($v) ? $v : $defColors[$k];
        }

        // 字体栈：仅允许常见字符，否则回退默认
        $font = isset($attr['font_family']) ? (string)$attr['font_family'] : '';
        if ($font === '' || preg_match('/[<>{};]/', $font)) {
            $font = "Inter, 'Noto Sans SC', system-ui, sans-serif";
        }

        // 字号 12-22，否则回退 16
        $fontSize = isset($attr['font_size']) ? (int)$attr['font_size'] : 16;
        if ($fontSize < 12 || $fontSize > 22) {
            $fontSize = 16;
        }

        $css = ':root{'
            . '--c-accent:' . $c['accent'] . ';'
            . '--c-accent-ink:' . $c['accent_ink'] . ';'
            . $this->accentDerivativeCssFragment($c['accent'])
            . '--c-bg:' . $c['bg'] . ';'
            . '--c-surface:' . $c['surface'] . ';'
            . '--c-surface-2:' . $c['surface_2'] . ';'
            . '--c-text:' . $c['text'] . ';'
            . '--c-text-muted:' . $c['text_muted'] . ';'
            . '--c-border:' . $c['border'] . ';'
            . '--font-base:' . $font . ';'
            . '--font-head:' . $font . ';'
            . '}' . "\n"
            . 'html{font-size:' . $fontSize . 'px}' . "\n"
            . '.bstem{--c-accent:' . $c['accent'] . ';--c-accent-ink:' . $c['accent_ink'] . ';' . $this->accentDerivativeCssFragment($c['accent']) . '}' . "\n";

        $dir = $this->cssDir();
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $file = $dir . ($isDraft ? 'theme-override.draft.css' : 'theme-override.css');
        return @file_put_contents($file, $css) !== false;
    }

    /** 生成 theme-design json 配置层 */
    private function writeDesignJson($cfg, $isDraft)
    {
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $data = array(
            'navbar' => isset($cfg['navbar']) ? $cfg['navbar'] : array('type_ids' => array()),
            'footer' => isset($cfg['footer']) ? $cfg['footer'] : array('links' => array()),
            'homepage' => isset($cfg['homepage']) ? $cfg['homepage'] : (
                $this->isLegacySectionsTheme()
                    ? array('sections' => $this->homepageDefaultSections())
                    : array('page' => $this->homepageDefaultPage())
            ),
            'logo' => isset($attr['logo']) ? (string)$attr['logo'] : '',
            'footer_copyright' => isset($attr['footer_copyright']) ? (string)$attr['footer_copyright'] : '',
            'default_avatar' => isset($attr['default_avatar']) ? (string)$attr['default_avatar'] : '',
        );

        $dir = $this->themeDir();
        if (!$this->ensureDir($dir)) {
            return false;
        }
        $file = $dir . ($isDraft ? 'theme-design.draft.json' : 'theme-design.json');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($file, $json) !== false;
    }

    /**
     * 发布专属：把默认头像同步到 static_new/images/touxiang.png（带备份）。
     * 全程 is_file 保护，任何失败都不致命。
     */
    private function applyDefaultAvatar($cfg)
    {
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $avatar = isset($attr['default_avatar']) ? (string)$attr['default_avatar'] : '';
        if ($avatar === '') {
            return;
        }

        // 只处理本地上传路径（相对站点根的图片），去掉可能的前导斜杠
        $rel = ltrim(str_replace('\\', '/', $avatar), '/');
        // 安全：禁止目录穿越
        if (strpos($rel, '..') !== false) {
            return;
        }
        $src = ROOT_PATH . $rel;
        if (!is_file($src)) {
            return;
        }
        // 仅接受图片扩展名
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (!in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp'), true)) {
            return;
        }

        $destDir = ROOT_PATH . 'static_new/images/';
        if (!is_dir($destDir)) {
            return;
        }
        $dest = $destDir . 'touxiang.png';

        // 首次替换前备份原图
        if (is_file($dest)) {
            $bak = $destDir . 'touxiang.original.bak.png';
            if (!is_file($bak)) {
                @copy($dest, $bak);
            }
        }
        @copy($src, $dest);
    }

    // ───────────────────────── 动作 ─────────────────────────

    /** 入口 -> 跳转到设计页 */
    public function index()
    {
        return $this->redirect(url('theme_design/design'));
    }

    /** 设计页 */
    public function design()
    {
        $cfg = $this->currentDraft();
        $hp = isset($cfg['homepage']) && is_array($cfg['homepage']) ? $cfg['homepage'] : array();

        if ($this->isLegacySectionsTheme()) {
            // Think 主题：保证 homepage.sections，绝不强制写入 PB page.blocks / 同步 kit
            $sections = (isset($hp['sections']) && is_array($hp['sections'])) ? $hp['sections'] : array();
            if (empty($sections)) {
                $seeded = $this->loadLegacyDesignSections();
                $sections = $seeded !== null ? $seeded : $this->homepageDefaultSections();
            } else {
                $sections = $this->sanitizeGeneratedSections($sections);
            }
            if (!isset($cfg['homepage']) || !is_array($cfg['homepage'])) {
                $cfg['homepage'] = array();
            }
            $cfg['homepage']['sections'] = $sections;
            // 去掉误种的 page.blocks，避免前端/产物歧义
            unset($cfg['homepage']['page']);
            if (isset($cfg['pages']['index/index'])) {
                unset($cfg['pages']['index/index']);
            }
        } else {
            // Twig/PB 主题：打开设计页只读配置，不主动 sync kit（避免未点「下一步」就污染主题目录）
            // kit 同步延后到 page_builder / 保存 homepage|page|preset / 发布
        }

        // 迁移：早期默认把站点 logo（site_logo）预填进了设计，导致显示成
        // 通用/默认模板的 logo。若 logo 恰为站点 logo，视为「未自定义」→ 清空，
        // 让前台与预览回退到所选主题自带的 logo。
        $migrated = false;
        $site_logo = isset($GLOBALS['config']['site']['site_logo']) ? (string)$GLOBALS['config']['site']['site_logo'] : '';
        if ($site_logo !== '' && isset($cfg['attributes']['logo']) && (string)$cfg['attributes']['logo'] === $site_logo) {
            $cfg['attributes']['logo'] = '';
            $migrated = true;
        }
        // 同样迁移：早期默认把「© 年份 站点名」预填进版权，与主题实际显示不符 → 清空。
        $site_name = isset($GLOBALS['config']['site']['site_name']) ? (string)$GLOBALS['config']['site']['site_name'] : '';
        $copy = isset($cfg['attributes']['footer_copyright']) ? (string)$cfg['attributes']['footer_copyright'] : '';
        if ($site_name !== '' && $copy !== ''
            && preg_match('/^©\s*\d{4}\s+(.+)$/u', $copy, $mm) && trim($mm[1]) === $site_name) {
            $cfg['attributes']['footer_copyright'] = '';
            $migrated = true;
        }
        // 持久化迁移，使预览产物（draft）也丢弃错误值
        if ($migrated && is_file($this->draftFile())) {
            $this->writeJson($this->draftFile(), $cfg);
            $this->writeArtifacts($cfg, true);
        }

        $hp = isset($cfg['homepage']) && is_array($cfg['homepage']) ? $cfg['homepage'] : array();
        if (!$this->isLegacySectionsTheme()) {
            $pageBlocks = (isset($hp['page']) && is_array($hp['page']) && isset($hp['page']['blocks'])) ? $hp['page']['blocks'] : null;
            if (!is_array($pageBlocks) || empty($pageBlocks)) {
                if (!isset($cfg['homepage']) || !is_array($cfg['homepage'])) {
                    $cfg['homepage'] = array();
                }
                $cfg['homepage']['page'] = $this->homepageDefaultPage();
            }
        }

        // 若尚无草稿，用 published 或 defaults 初始化（仅用于展示，不落盘）
        $defaults = $this->buildDefaults();

        // 配色预设列表
        $schemes = array();
        foreach ($this->schemePresets() as $key => $p) {
            $schemes[] = array('key' => $key, 'name' => $p['name'], 'colors' => $p['colors']);
        }

        // 字体列表
        $fonts = array();
        foreach ($this->fontOptions() as $value => $label) {
            $fonts[] = array('value' => $value, 'label' => $label);
        }

        // 区块目录
        $section_catalog = array();
        foreach ($this->sectionCatalog() as $key => $label) {
            $section_catalog[] = array('key' => $key, 'label' => $label);
        }

        $block_catalog = $this->isLegacySectionsTheme() ? array() : $this->blockCatalog();
        $default_homepage_page = $this->isLegacySectionsTheme() ? array() : $this->homepageDefaultPage();

        $this->assign('cfg', $cfg);
        $this->assign('defaults', $defaults);
        $this->assign('schemes', $schemes);
        $this->assign('fonts', $fonts);
        $this->assign('section_catalog', $section_catalog);
        $this->assign('block_catalog', $block_catalog);
        $this->assign('default_homepage_page', $default_homepage_page);
        $this->assign('type_tree', $this->topTypeTree());
        $this->assign('type_tree_full', $this->typeTreeFull());
        $this->assign('cats_flat', $this->catsFlat());
        $this->assign('themes', $this->availableThemes());
        $this->assign('cur_theme', $this->theme);
        $this->assign('preview_url', $this->previewUrl());
        // 主题自带 logo / 默认头像（字段留空时用于预览回退展示选定主题自己的资源）
        $this->assign('theme_logo_url', $this->themeLogoUrl());
        $this->assign('default_avatar_url', $this->themeAvatarUrl());
        // 版权占位：展示「留空时主题实际显示的默认版权」
        $this->assign('footer_copyright_ph', $this->defaultFooterCopyright());
        $this->assign('has_published', is_file($this->publishedFile()));
        $this->assign('title', lang('menu/theme_design'));
        $this->assign('page_builder_url', url('theme_design/page_builder', array('theme' => $this->theme)));
        $pbMeta = ThemePreset::readPbMeta($this->themeDir());
        $curPreset = $pbMeta['pb_preset'] !== '' ? $pbMeta['pb_preset'] : ThemePreset::DEFAULT_ID;
        if (isset($cfg['attributes']['preset_id']) && (string) $cfg['attributes']['preset_id'] !== '') {
            $curPreset = ThemePreset::normalizeId($cfg['attributes']['preset_id']);
        }
        $this->assign('pb_presets', $this->pbPresetsForView($curPreset));
        $this->assign('cur_pb_preset', $curPreset);
        // 遗留 Think 主题强制走 sections 模式（隐藏可视化搭建），即使误标 pb_enabled
        $pbEnabled = !$this->isLegacySectionsTheme()
            && (ThemePreset::isThinShell($this->themeDir()) || ThemePreset::isPbEnabled($this->themeDir()));
        $this->assign('pb_theme_enabled', $pbEnabled);
        $siteTpl = (string) config('maccms.site')['template_dir'];
        $this->assign('site_template_dir', $siteTpl);
        $this->assign('theme_is_site_active', $siteTpl !== '' && $siteTpl === $this->theme);

        // Task B: draft vs published preset/shell status
        $pubCfg = $this->readJson($this->publishedFile());
        $publishedPreset = '';
        if (is_array($pubCfg) && isset($pubCfg['attributes']['preset_id']) && (string) $pubCfg['attributes']['preset_id'] !== '') {
            $publishedPreset = ThemePreset::normalizeId($pubCfg['attributes']['preset_id']);
        }
        $draftShell = isset($pbMeta['pb_shell']) && $pbMeta['pb_shell'] !== '' ? $pbMeta['pb_shell'] : 'thin';
        $publishedShell = $draftShell;
        if (is_array($pubCfg) && isset($pubCfg['attributes']['pb_shell']) && (string) $pubCfg['attributes']['pb_shell'] !== '') {
            $publishedShell = strtolower(trim((string) $pubCfg['attributes']['pb_shell']));
        }
        $presetMismatch = ($publishedPreset !== '' && $publishedPreset !== $curPreset);
        $this->assign('draft_pb_preset', $curPreset);
        $this->assign('published_pb_preset', $publishedPreset);
        $this->assign('draft_pb_shell', $draftShell);
        $this->assign('published_pb_shell', $publishedShell);
        $this->assign('preset_mismatch', $presetMismatch);


        return $this->fetch('themedesign/design');
    }

    /** 全屏可视化页面搭建（GrapesJS，多路由） */
    public function page_builder()
    {
        // Think/sections 主题无 Twig 编译面，禁止进入可视化搭建
        if ($this->isLegacySectionsTheme()) {
            return $this->redirect(url('theme_design/design', array('theme' => $this->theme, 'step' => 3)));
        }
        $route = input('route', 'index/index');
        $route = PageRegistry::normalizeRoute($route);
        if (!PageRegistry::isEditableRoute($route)) {
            $aliasRoute = PageRegistry::authPageConfigRoute($route);
            if ($aliasRoute !== $route && PageRegistry::isEditableRoute($aliasRoute)) {
                $route = $aliasRoute;
            } else {
                $route = 'index/index';
            }
        }
        $isAuthModalVirtual = PageRegistry::isAuthModalVirtualRoute($route);
        $pageMode = PageRegistry::pageBuilderMode($route);
        $meta = PageRegistry::get($route);
        $pageType = $isAuthModalVirtual ? 'auth_modal' : ($meta ? $meta['page_type'] : 'home');

        $scaffolder = new PageScaffolder();
        $cfg = $this->currentDraft();
        $kit = new PageBuilderKit();

        if (!$isAuthModalVirtual) {
            $scaffolder->ensureScaffolded($this->themeDir(), $route);
            $page = $this->getPageFromConfig($cfg, $route);
            $page = $this->enrichPage($route, $page, $cfg);
            $this->ensurePageBuilderKitSynced(false, $page);
            $this->compilePageTwigForRouteIfStale($route, $page, $cfg);
        } else {
            $page = array(
                'template' => PageRegistry::AUTH_MODAL_VIRTUAL_ROUTE,
                'layout' => 'base',
                'page_type' => 'auth_modal',
                'regions' => array(),
            );
        }

        $authModal = $kit->mergeAuthModalConfig(isset($cfg['auth_modal']) ? $cfg['auth_modal'] : array());

        $navbar = isset($cfg['navbar']) && is_array($cfg['navbar']) ? $cfg['navbar'] : array();
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        $this->assign('navbar_type_ids', isset($navbar['type_ids']) && is_array($navbar['type_ids']) ? $navbar['type_ids'] : array());
        $this->assign('theme_logo_url', $this->themeLogoUrl());
        $this->assign('global_logo', isset($attr['logo']) ? (string) $attr['logo'] : '');
        $this->assign('page', $page);
        $this->assign('current_route', $route);
        $this->assign('page_type', $pageType);
        $this->assign('page_mode', $pageMode);
        $this->assign('route_label', PageRegistry::pickerLabel($route));
        $this->assign('auth_modal_config', $authModal);
        $this->assign('page_registry', PageRegistry::groupedForPicker());
        $regionNames = PageRegistry::editorRegionNames($route);
        $usesAppChrome = $this->themeUsesAppTopChrome($cfg);
        $siteHeaderMode = false;
        if ($usesAppChrome) {
            $kit = new PageBuilderKit();
            $siteHeaderMode = $kit->usesSiteHeaderChromeMode($page, $route, $this->themeDir());
        }
        if ($usesAppChrome && !$siteHeaderMode) {
            $regionNames = array_values(array_filter($regionNames, function ($name) {
                return !in_array($name, array('site_header', 'hero', 'channel'), true);
            }));
        }
        $this->assign('region_names', $regionNames);
        $this->assign('pb_app_top_chrome_theme', $usesAppChrome ? 1 : 0);
        $this->assign('pb_site_header_chrome', $siteHeaderMode ? 1 : 0);
        $this->assign('pb_immersive', PageRegistry::isImmersiveRoute($route) ? 1 : 0);
        $paletteType = ($pageMode === 'member' || $pageMode === 'auth' || $pageMode === 'auth_modal') ? 'member' : $pageType;
        $paletteModule = ($meta && !empty($meta['module'])) ? (string) $meta['module'] : '';
        if ($isAuthModalVirtual || $paletteType === 'member') {
            $paletteModule = 'user';
        }
        $this->assign('block_palette', $kit->paletteForPageType($paletteType, function ($key) {
            return lang($key);
        }, $paletteModule));
        $this->assign('pb_component_catalog', $kit->componentCatalog());
        $this->assign('cats_flat', $this->catsFlat());
        $this->assign('type_tree_full', $this->typeTreeFull());
        $this->assign('cur_theme', $this->theme);
        $this->assign('preview_url', $this->previewUrl($route));
        $returnStep = max(1, min(5, intval(input('return_step/d', 4))));
        $pbEmbed = intval(input('embed/d', 0)) === 1 ? 1 : 0;
        $this->assign('pb_embed', $pbEmbed);
        $this->assign('pb_return_step', $returnStep);
        $this->assign('design_url', url('theme_design/design', array('theme' => $this->theme, 'step' => 4)));
        $this->assign('page_builder_url', url('theme_design/page_builder', array('theme' => $this->theme)));
        $this->assign('title', lang('admin/themedesign/page_builder'));
        return $this->fetch('themedesign/page_builder');
    }

    /** Page Builder Kit 组件 catalog（机器契约聚合） */
    public function pb_component_catalog()
    {
        $kit = new PageBuilderKit();
        return json(array(
            'code' => 1,
            'data' => $kit->componentCatalog(),
        ));
    }

    /** 加载当前配置（JSON） */
    public function load()
    {
        $draft = $this->currentDraft();
        $published = $this->readJson($this->publishedFile());
        $meta = ThemePreset::readPbMeta($this->themeDir());
        $draftPreset = ThemePreset::DEFAULT_ID;
        if (isset($draft['attributes']['preset_id']) && (string) $draft['attributes']['preset_id'] !== '') {
            $draftPreset = ThemePreset::normalizeId($draft['attributes']['preset_id']);
        } elseif (!empty($meta['pb_preset'])) {
            $draftPreset = $meta['pb_preset'];
        }
        $publishedPreset = '';
        if (is_array($published) && isset($published['attributes']['preset_id']) && (string) $published['attributes']['preset_id'] !== '') {
            $publishedPreset = ThemePreset::normalizeId($published['attributes']['preset_id']);
        }
        return json(array(
            'code' => 1,
            'draft' => $draft,
            'published' => $published,
            'has_published' => is_file($this->publishedFile()),
            'defaults' => $this->buildDefaults(),
            'pb_status' => array(
                'draft_preset' => $draftPreset,
                'published_preset' => $publishedPreset,
                'draft_shell' => isset($meta['pb_shell']) ? $meta['pb_shell'] : '',
                'mismatch' => ($publishedPreset !== '' && $publishedPreset !== $draftPreset),
            ),
            'matrix_report' => $this->pbMatrixReportInfo(),
        ));
    }

    /**
     * 保存某一步骤到草稿，并刷新草稿预览产物。
     * POST step=attributes|navbar|footer|homepage, data=<JSON string>
     */
    public function save_step()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $param = input('post.');
        $step = isset($param['step']) ? trim($param['step']) : '';
        $dataRaw = isset($param['data']) ? $param['data'] : '';

        $allowed = array('attributes', 'navbar', 'footer', 'site_chrome', 'homepage', 'page', 'preset', 'preset_reset', 'auth_modal');
        if (!in_array($step, $allowed, true)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }

        $data = json_decode($dataRaw, true);
        if (!is_array($data)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }

        if ($step === 'preset_reset') {
            $presetId = ThemePreset::normalizeId(isset($data['preset_id']) ? $data['preset_id'] : '');
            $result = $this->rebootstrapDraftFromPreset($presetId, true);
            if ((int) $result['code'] !== 1) {
                return json($result);
            }
            return json(array(
                'code' => 1,
                'msg' => lang('admin/themedesign/preset_reset_ok'),
                'preview_url' => $this->previewUrl('index/index'),
                'preset_id' => $presetId,
                'draft' => $result['cfg'],
            ));
        }

        // 草稿不存在则先用默认播种
        $cfg = $this->readJson($this->draftFile());
        if (!is_array($cfg)) {
            $cfg = $this->currentDraft();
            if (!isset($cfg['theme'])) {
                $cfg = $this->buildDefaults();
            }
        }

        // 合并对应步骤
        if ($step === 'homepage' && is_array($data)) {
            // 遗留 Think 主题：只接受 sections，写入后去掉 page.blocks
            if ($this->isLegacySectionsTheme() && isset($data['sections']) && is_array($data['sections'])) {
                $cfg['homepage'] = array(
                    'sections' => $this->sanitizeGeneratedSections($data['sections']),
                );
                if (isset($cfg['pages']['index/index'])) {
                    unset($cfg['pages']['index/index']);
                }
            } elseif (isset($data['page']) && is_array($data['page'])) {
                $data['page'] = $this->sanitizeHomepagePage($data['page']);
                if (isset($data['page']['chrome']) && is_array($data['page']['chrome'])) {
                    $navIds = array();
                    foreach ($data['page']['chrome'] as $c) {
                        if (!is_array($c)) {
                            continue;
                        }
                        $comp = (string) ($c['component'] ?? '');
                        if ($comp !== 'site_nav' && $comp !== 'channel_strip') {
                            continue;
                        }
                        if (isset($c['props']['type_ids']) && is_array($c['props']['type_ids']) && !empty($c['props']['type_ids'])) {
                            $navIds = $c['props']['type_ids'];
                            if ($comp === 'site_nav') {
                                break;
                            }
                        }
                    }
                    if (!empty($navIds)) {
                        $cfg['navbar'] = array('type_ids' => $navIds);
                    }
                }
                $prev = isset($cfg['homepage']) && is_array($cfg['homepage']) ? $cfg['homepage'] : array();
                $cfg['homepage'] = array_merge($prev, $data);
                if (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
                    if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
                        $cfg['pages'] = array();
                    }
                    $cfg['pages']['index/index'] = $cfg['homepage']['page'];
                }
            } elseif (isset($data['sections']) && is_array($data['sections'])) {
                // Twig 主题偶发收到 sections：保留并尝试转 page（AI 兼容）
                $sections = $this->sanitizeGeneratedSections($data['sections']);
                $prev = isset($cfg['homepage']) && is_array($cfg['homepage']) ? $cfg['homepage'] : array();
                $cfg['homepage'] = array_merge($prev, array('sections' => $sections));
            }
        } elseif ($step === 'page') {
            $route = isset($param['route']) ? PageRegistry::normalizeRoute($param['route']) : '';
            if ($route === '') {
                return json(array('code' => 0, 'msg' => lang('param_err')));
            }
            if (!PageRegistry::isEditableRoute($route)
                || PageRegistry::isAuthModalVirtualRoute($route)) {
                return json(array('code' => 0, 'msg' => lang('param_err')));
            }
            $page = $this->sanitizePage($route, $data);
            if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
                $cfg['pages'] = array();
            }
            $cfg = PageRegistry::migrateConfigPages($cfg);
            $cfg['pages'][$route] = $page;
            if ($route === 'index/index') {
                $cfg['homepage'] = array('page' => $page);
            }
        } elseif ($step === 'auth_modal') {
            $kit = new PageBuilderKit();
            $cfg['auth_modal'] = $kit->mergeAuthModalConfig(is_array($data) ? $data : array());
        } elseif ($step === 'preset') {
            $presetId = ThemePreset::normalizeId(isset($data['preset_id']) ? $data['preset_id'] : '');
            $themeDir = $this->themeDir();
            if (!ThemePreset::isThinShell($themeDir) && !ThemePreset::isPbEnabled($themeDir)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/preset_not_pb_theme')));
            }
            // Always merge shell CSS + tokens (Task C)
            if (!ThemePreset::applyPresetToTheme($presetId, $themeDir)) {
                return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
            }
            ThemePreset::writePbIni($themeDir, $presetId);
            $this->mergePbDraftAttributes($cfg, $presetId);

            // Task D: optional homepage seed (keep_homepage / keep_draft default true)
            $keepRaw = null;
            if (array_key_exists('keep_homepage', $param)) {
                $keepRaw = $param['keep_homepage'];
            } elseif (is_array($data) && array_key_exists('keep_homepage', $data)) {
                $keepRaw = $data['keep_homepage'];
            } elseif (array_key_exists('keep_draft', $param)) {
                $keepRaw = $param['keep_draft'];
            } elseif (is_array($data) && array_key_exists('keep_draft', $data)) {
                $keepRaw = $data['keep_draft'];
            }
            $keepHomepage = true;
            if ($keepRaw !== null) {
                $keepHomepage = !in_array(strtolower((string) $keepRaw), array('0', 'false', 'no', 'off', ''), true)
                    && $keepRaw !== 0 && $keepRaw !== false;
            }
            if (!$keepHomepage) {
                $page = $this->homepageDefaultPage($presetId);
                if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
                    $cfg['pages'] = array();
                }
                $cfg['pages']['index/index'] = $page;
                $cfg['homepage'] = array('page' => $page);
            }
        } elseif ($step === 'site_chrome') {
            if (isset($data['attributes']) && is_array($data['attributes'])) {
                $prevAttr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
                $allowedAttr = array('logo', 'footer_copyright', 'default_avatar', 'header_chrome');
                foreach ($allowedAttr as $ak) {
                    if (array_key_exists($ak, $data['attributes'])) {
                        if ($ak === 'logo') {
                            $prevAttr[$ak] = $this->sanitizeGlobalLogo($data['attributes'][$ak]);
                        } elseif ($ak === 'footer_copyright') {
                            $prevAttr[$ak] = mb_substr(mac_filter_xss((string) $data['attributes'][$ak]), 0, 500, 'UTF-8');
                        } elseif ($ak === 'header_chrome') {
                            $v = trim((string) $data['attributes'][$ak]);
                            $prevAttr[$ak] = ($v === 'app_top_chrome') ? 'app_top_chrome' : 'site_nav';
                        } else {
                            $prevAttr[$ak] = $this->sanitizeGlobalLogo($data['attributes'][$ak]);
                        }
                    }
                }
                $cfg['attributes'] = $prevAttr;
            }
            $headerChrome = isset($cfg['attributes']['header_chrome'])
                ? trim((string) $cfg['attributes']['header_chrome']) : 'site_nav';
            if ($headerChrome !== 'app_top_chrome') {
                $headerChrome = 'site_nav';
            }
            $cfg['attributes']['header_chrome'] = $headerChrome;
            $this->applyHeaderChromeToDraftPages($cfg, $headerChrome);
            if (isset($data['navbar']) && is_array($data['navbar'])) {
                $cfg['navbar'] = $this->sanitizeWizardStep('navbar', $data['navbar']);
            }
            if (isset($data['footer']) && is_array($data['footer'])) {
                $cfg['footer'] = $this->sanitizeWizardStep('footer', $data['footer']);
            }
        } elseif ($step === 'attributes') {
            $prevAttr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
            $cfg['attributes'] = array_merge($prevAttr, $this->sanitizeWizardStep('attributes', $data));
        } elseif (in_array($step, array('navbar', 'footer'), true)) {
            $cfg[$step] = $this->sanitizeWizardStep($step, $data);
        }
        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }

        // 刷新草稿预览产物。仅内容相关步骤同步 kit，避免「未点下一步」写 page-builder-components
        $syncKit = in_array($step, array('homepage', 'page', 'preset', 'auth_modal', 'site_chrome'), true);
        $this->writeArtifacts($cfg, true, array('sync_kit' => $syncKit));

        $resp = array('code' => 1, 'msg' => lang('save_ok'));
        if ($step === 'page') {
            $saveRoute = isset($param['route']) ? PageRegistry::normalizeRoute($param['route']) : 'index/index';
            $resp['preview_url'] = $this->previewUrl($saveRoute);
            if (isset($cfg['pages'][$saveRoute]) && is_array($cfg['pages'][$saveRoute])) {
                $resp['page'] = $cfg['pages'][$saveRoute];
            } elseif ($saveRoute === 'index/index' && isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
                $resp['page'] = $cfg['homepage']['page'];
            }
        }
        if ($step === 'auth_modal') {
            $resp['preview_url'] = $this->previewUrl(PageRegistry::AUTH_MODAL_VIRTUAL_ROUTE);
        }
        if ($step === 'preset') {
            $resp['msg'] = lang('admin/themedesign/preset_applied');
            $resp['preview_url'] = $this->previewUrl('index/index');
            $resp['preset_id'] = isset($presetId) ? $presetId : ThemePreset::DEFAULT_ID;
        }
        return json($resp);
    }

    /** 预览：刷新草稿产物并返回带 td_preview 标记的页面 URL */
    public function preview()
    {
        $cfg = $this->currentDraft();
        // 确保草稿已落盘（首次预览也能生效）
        if (!is_file($this->draftFile())) {
            $this->writeJson($this->draftFile(), $cfg);
        }
        $this->writeArtifacts($cfg, true);

        $route = input('route', 'index/index');
        $route = PageRegistry::normalizeRoute($route);
        $url = $this->previewUrl($route);

        return json(array('code' => 1, 'url' => $url));
    }

    /**
     * 将当前路由重置为 page_type 默认脚手架，写入主题 JSON + draft 并更新草稿配置。
     * POST route=vod/detail
     */
    public function init_page()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $route = PageRegistry::normalizeRoute(input('route', ''));
        if ($route === '' || !PageRegistry::isEditableRoute($route)
            || PageRegistry::isAuthModalVirtualRoute($route)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }

        $scaffolder = new PageScaffolder();
        if (!$scaffolder->ensureScaffolded($this->themeDir(), $route, true)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/pb_init_fail')));
        }

        $page = $scaffolder->defaultPageForRoute($route, $this->themeDir());
        $cfg = $this->currentDraft();
        $page = $this->enrichPage($route, $page, $cfg);
        if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
            $cfg['pages'] = array();
        }
        $cfg['pages'][$route] = $this->sanitizePage($route, $page);
        if ($route === 'index/index') {
            $cfg['homepage'] = array('page' => $cfg['pages'][$route]);
        }
        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();
        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }
        $this->writeArtifacts($cfg, true, array('compile_pages' => true, 'sync_kit' => true));

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/pb_init_ok'),
            'page' => $cfg['pages'][$route],
            'preview_url' => $this->previewUrl($route),
        ));
    }

    /** 站点首页 URL（site_url 为空则用请求 host 拼） */
    private function siteHome()
    {
        // 预览始终用「后台当前访问的 scheme + host + 安装路径」构建，刻意忽略 site_url：
        // site_url 可能指向后台浏览器无法访问的域名（如 test.cn），会导致预览空白。
        // 这样预览与后台同源、同主机，无论 site_url 设成什么都能正常加载。
        $scheme = $this->request->scheme();
        $host   = $this->request->host();            // 含端口（如有）
        $path   = defined('MAC_PATH') ? (string)MAC_PATH : '/';
        if ($path === '') {
            $path = '/';
        }
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        return $scheme . '://' . $host . $path . 'index.php/index.html';
    }

    /**
     * 发布：草稿 -> 已发布 + 历史快照 + 生成线上产物。
     * mode=update（默认）：更新当前主题；
     * mode=new：复制当前主题为一个新主题并把设计作为其线上产物（不动原主题）。
     */
    public function publish()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $cfg = $this->readJson($this->draftFile());
        if (!is_array($cfg)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/no_draft')));
        }

        $mode = trim((string)input('post.mode', 'update'));
        if ($mode === 'new') {
            return $this->publishAsNew($cfg);
        }

        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        // Task C: force merge shell CSS from draft preset before going live
        $pubPresetId = ThemePreset::DEFAULT_ID;
        if (isset($cfg['attributes']['preset_id']) && (string) $cfg['attributes']['preset_id'] !== '') {
            $pubPresetId = ThemePreset::normalizeId($cfg['attributes']['preset_id']);
        } else {
            $metaNow = ThemePreset::readPbMeta($this->themeDir());
            if (!empty($metaNow['pb_preset'])) {
                $pubPresetId = $metaNow['pb_preset'];
            }
        }
        if (ThemePreset::isThinShell($this->themeDir()) || ThemePreset::isPbEnabled($this->themeDir())) {
            ThemePreset::applyPresetToTheme($pubPresetId, $this->themeDir());
            ThemePreset::writePbIni($this->themeDir(), $pubPresetId);
            $this->mergePbDraftAttributes($cfg, $pubPresetId);
            if (!isset($cfg['attributes']) || !is_array($cfg['attributes'])) {
                $cfg['attributes'] = array();
            }
            $metaShell = ThemePreset::readPbMeta($this->themeDir());
            $cfg['attributes']['pb_shell'] = isset($metaShell['pb_shell']) && $metaShell['pb_shell'] !== ''
                ? $metaShell['pb_shell'] : 'thin';
        }

        // 写已发布
        if (!$this->writeJson($this->publishedFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }

        // 历史快照
        $ts = time();
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . $ts . '.json', $cfg);

        // 生成线上产物
        $this->writeArtifacts($cfg, false);
        // 发布专属：默认头像同步
        $this->applyDefaultAvatar($cfg);

        // 仅保留最新 ~20 份历史
        $this->trimHistory(20);

        return json(array('code' => 1, 'msg' => lang('admin/themedesign/publish_done')));
    }

    /**
     * 从风格包创建全新 PB 主题（复制 _shared/starter + 套版式 + 铺默认页）。
     * 不修改当前正在编辑的主题。POST preset_id / new_name / new_dir / new_intro / activate。
     */
    public function create_from_preset()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $presetId = ThemePreset::normalizeId(input('post.preset_id', ''));
        $dir = strtolower(trim((string)input('post.new_dir', '')));
        $name = trim((string)input('post.new_name', ''));
        $intro = trim((string)input('post.new_intro', ''));
        $activate = (int) input('post.activate', 0);

        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_invalid')));
        }
        if ($name === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_name_required')));
        }

        if ($this->isReservedThemeDir($dir)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_reserved')));
        }

        $dest = ROOT_PATH . 'template/' . $dir;
        if (is_dir($dest)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_exists')));
        }

        @set_time_limit(180);
        if (ThemePreset::isNp2Preset($presetId)) {
            if (!ThemePreset::materializeNp2ThemeSkeleton($dest)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_copy_failed')));
            }
        } else {
            $src = $this->resolveNewThemeSourceDir($presetId);
            if (!is_dir($src)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_src_missing')));
            }
            if (!$this->copyDir($src, $dest)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_copy_failed')));
            }
        }

        $kit = new PageBuilderKit();
        $kit->syncToTheme($dest, true);
        ThemePreset::writePbIni($dest, $presetId, array(
            'name' => $name,
            'lastdate' => date('Y-m-d'),
            'version' => 'V1.0',
            'author' => 'Theme Designer',
            'intro' => $intro,
        ));
        ThemePreset::applyPresetToTheme($presetId, $dest);

        $cfg = $this->buildDefaults();
        $this->mergePbDraftAttributes($cfg, $presetId);
        $this->bootstrapPbPagesConfig($dest, $presetId, $cfg, true);

        $oldTheme = $this->theme;
        $this->theme = $dir;
        $cfg['theme'] = $dir;
        $cfg['updated_at'] = time();
        $this->writeJson($this->draftFile(), $cfg);
        $this->writeJson($this->publishedFile(), $cfg);
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . time() . '.json', $cfg);
        $artifactOpts = array('compile_pages' => true, 'sync_kit' => true);
        $this->writeArtifacts($cfg, true, $artifactOpts);
        $this->writeArtifacts($cfg, false);
        $this->applyDefaultAvatar($cfg);
        $preview = $this->previewUrl('index/index');
        $this->theme = $oldTheme;

        $designUrl = url('theme_design/design');
        $designUrl .= (strpos($designUrl, '?') === false ? '?' : '&') . 'theme=' . urlencode($dir);

        $activated = 0;
        if ($activate === 1) {
            $svc = new \app\common\util\TemplateCloudService();
            $act = $svc->activateTemplate($dir);
            if (is_array($act) && isset($act['code']) && (int) $act['code'] === 1) {
                $activated = 1;
            }
        }

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/preset_created_ok'),
            'new_theme' => $dir,
            'preview_url' => $preview,
            'design_url' => $designUrl,
            'activated' => $activated,
        ));
    }

    /**
     * 将指定主题设为站点前台模板。
     */
    public function activate_site_theme()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $dir = strtolower(trim((string) input('post.theme_dir', '')));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }
        $svc = new \app\common\util\TemplateCloudService();
        return json($svc->activateTemplate($dir));
    }

    /**
     * 以「新建主题」方式发布：复制当前主题目录为新主题，写入 info.ini，
     * 并把当前草稿作为新主题的线上产物。原主题完全不受影响。
     */
    private function publishAsNew($cfg)
    {
        @set_time_limit(180);

        $dir   = strtolower(trim((string)input('post.new_dir', '')));
        $name  = trim((string)input('post.new_name', ''));
        $intro = trim((string)input('post.new_intro', ''));
        $base  = strtolower(trim((string)input('post.base', 'preset')));
        if ($base !== 'draft' && $base !== 'preset') {
            $base = 'preset';
        }

        // 目录名校验：字母开头，2-32 位字母/数字/下划线/连字符
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_invalid')));
        }
        if ($this->isReservedThemeDir($dir)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_reserved')));
        }
        if ($name === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_name_required')));
        }

        // Task E: base=draft → copy current theme + keep draft pages;
        // base=preset → starter + seed scaffold (legacy). Never rewrite current theme info.ini.
        if ($base === 'draft') {
            $src = ROOT_PATH . 'template/' . $this->theme;
            $presetId = ThemePreset::DEFAULT_ID;
            if (isset($cfg['attributes']['preset_id']) && (string) $cfg['attributes']['preset_id'] !== '') {
                $presetId = ThemePreset::normalizeId($cfg['attributes']['preset_id']);
            } else {
                $metaCur = ThemePreset::readPbMeta($this->themeDir());
                if (!empty($metaCur['pb_preset'])) {
                    $presetId = $metaCur['pb_preset'];
                }
            }
            // allow override from post.preset_id when provided
            $postPreset = trim((string) input('post.preset_id', ''));
            if ($postPreset !== '') {
                $presetId = ThemePreset::normalizeId($postPreset);
            }
        } else {
            $presetId = ThemePreset::normalizeId(input('post.preset_id', ''));
        }
        $dest = ROOT_PATH . 'template/' . $dir;
        if (is_dir($dest)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_dir_exists')));
        }

        // Snapshot current theme ini so we can assert no drift after work
        $srcIni = $this->themeDir() . 'info.ini';
        $srcIniBefore = is_file($srcIni) ? (string) @file_get_contents($srcIni) : null;

        // 1) 复制主题目录或从 preset 物化
        if ($base === 'preset' && ThemePreset::isNp2Preset($presetId)) {
            if (!ThemePreset::materializeNp2ThemeSkeleton($dest)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_copy_failed')));
            }
        } else {
            if ($base === 'draft') {
                $src = ROOT_PATH . 'template/' . $this->theme;
            } else {
                $src = $this->resolveNewThemeSourceDir($presetId);
            }
            if (!is_dir($src)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_src_missing')));
            }
            if (!$this->copyDir($src, $dest)) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/new_copy_failed')));
            }
        }

        $kit = new PageBuilderKit();
        $kit->syncToTheme($dest, true);

        ThemePreset::writePbIni($dest, $presetId, array(
            'name' => $name,
            'lastdate' => date('Y-m-d'),
            'version' => 'V1.0',
            'author' => 'Theme Designer',
            'intro' => $intro,
        ));
        ThemePreset::applyPresetToTheme($presetId, $dest);
        $this->mergePbDraftAttributes($cfg, $presetId);
        if ($base === 'preset') {
            $this->bootstrapPbPagesConfig($dest, $presetId, $cfg, false);
        }
        // base=draft: keep $cfg homepage/pages from current draft

        // 3) 把当前草稿作为新主题的线上产物（临时把目标主题指向新目录）
        $oldTheme = $this->theme;
        $this->theme = $dir;

        $cfg['theme'] = $dir;
        $cfg['updated_at'] = time();
        $this->writeJson($this->draftFile(), $cfg);
        $this->writeJson($this->publishedFile(), $cfg);
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . time() . '.json', $cfg);
        $this->writeArtifacts($cfg, false);
        $this->applyDefaultAvatar($cfg);

        $this->theme = $oldTheme;

        // Task E: do not drift site pb_preset after publish new
        if ($srcIniBefore !== null && is_file($srcIni)) {
            $after = (string) @file_get_contents($srcIni);
            if ($after !== $srcIniBefore) {
                @file_put_contents($srcIni, $srcIniBefore);
            }
        }

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'new_theme' => $dir,
            'new_path' => 'template/' . $dir,
            'base' => $base,
            'preset_id' => $presetId,
        ));
    }

    /** 递归复制目录（用于「新建主题」） */
    private function copyDir($src, $dst)
    {
        if (!is_dir($src)) {
            return false;
        }
        if (!$this->ensureDir($dst)) {
            return false;
        }
        $dh = @opendir($src);
        if (!$dh) {
            return false;
        }
        $ok = true;
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $s = $src . '/' . $f;
            $d = $dst . '/' . $f;
            if (is_dir($s)) {
                if (!$this->copyDir($s, $d)) {
                    $ok = false;
                    break;
                }
            } else {
                if (!@copy($s, $d)) {
                    $ok = false;
                    break;
                }
            }
        }
        closedir($dh);
        return $ok;
    }

    /** 保留最新 $keep 份历史快照，删除更旧的 */
    private function trimHistory($keep)
    {
        $dir = $this->historyDir();
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '*.json');
        if ($files === false || count($files) <= $keep) {
            return;
        }
        // 按文件名（时间戳）升序，旧的在前
        $items = array();
        foreach ($files as $f) {
            $ts = (int)pathinfo($f, PATHINFO_FILENAME);
            $items[] = array('ts' => $ts, 'file' => $f);
        }
        usort($items, function ($a, $b) {
            return $a['ts'] - $b['ts'];
        });
        $removeCount = count($items) - $keep;
        for ($i = 0; $i < $removeCount; $i++) {
            @unlink($items[$i]['file']);
        }
    }

    /** 历史列表（新 -> 旧） */
    public function history()
    {
        $dir = $this->historyDir();
        $list = array();
        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            if (is_array($files)) {
                foreach ($files as $f) {
                    $ts = (int)pathinfo($f, PATHINFO_FILENAME);
                    if ($ts <= 0) {
                        continue;
                    }
                    $list[] = array('ts' => $ts, 'time' => date('Y-m-d H:i:s', $ts));
                }
            }
        }
        // 新 -> 旧
        usort($list, function ($a, $b) {
            return $b['ts'] - $a['ts'];
        });
        return json(array('code' => 1, 'list' => $list));
    }

    /**
     * 回滚：把某历史快照恢复为草稿（不自动发布），刷新草稿预览产物。
     * POST ts=<int>
     */
    public function rollback()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $ts = (int)input('post.ts/d', 0);
        if ($ts <= 0) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }
        $file = $this->historyDir() . $ts . '.json';
        $cfg = $this->readJson($file);
        if (!is_array($cfg)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/history_missing')));
        }

        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return json(array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败'));
        }
        $this->writeArtifacts($cfg, true);

        return json(array('code' => 1, 'msg' => lang('admin/themedesign/rollback_done')));
    }

    /** 图片上传（logo / default_avatar），复用 Upload 模型 */
    public function upload()
    {
        $res = model('Upload')->upload(array('input' => 'file', 'flag' => 'images'));
        // 后台(ENTRANCE=admin)下 Upload 返回 {code,msg,data:{file}}，前端读取 res.file，这里统一归一化为顶层 file 并补全为可用 URL
        $file = '';
        if (isset($res['file'])) {
            $file = $res['file'];
        } elseif (isset($res['data']['file'])) {
            $file = $res['data']['file'];
        }
        if ($file !== '' && !preg_match('#^(https?:)?//#', $file) && substr($file, 0, 1) !== '/') {
            $file = MAC_PATH . $file;
        }
        return json(array(
            'code' => isset($res['code']) ? $res['code'] : 0,
            'msg'  => isset($res['msg']) ? $res['msg'] : '',
            'file' => $file,
        ));
    }

    // ───────────────────────── AI 生成 ─────────────────────────

    /** 主题AI 配置（System → Theme AI，独立于后台助手） */
    private function themeAiConfig()
    {
        $cfg = config('maccms');
        return isset($cfg['theme_ai']) && is_array($cfg['theme_ai']) ? $cfg['theme_ai'] : array();
    }

    /**
     * 调用主题AI。provider=openai → {base}/chat/completions（Bearer）；
     * provider=claude → {base}/messages（x-api-key + anthropic-version）。
     * 二者都由所配代理（newapi）支持。api_base 形如 https://host/v1。
     * @return array ['ok'=>bool,'text'=>string,'error'=>string]
     */
    private function themeAiChat($system, $user, $images = array())
    {
        $cfg = $this->themeAiConfig();
        if ((string)(isset($cfg['enabled']) ? $cfg['enabled'] : '0') !== '1') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_disabled'));
        }
        $key = trim((string)(isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        $base = rtrim(trim((string)(isset($cfg['api_base']) ? $cfg['api_base'] : '')), '/');
        if ($key === '' || $base === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_no_key'));
        }
        $provider = strtolower(trim((string)(isset($cfg['provider']) ? $cfg['provider'] : 'openai')));
        $model = trim((string)(isset($cfg['model']) ? $cfg['model'] : 'claude-opus-4-8'));
        if ($model === '') {
            $model = 'claude-opus-4-8';
        }
        $timeout = max(10, min(300, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 60)));
        $maxTokens = max(500, min(8000, intval(isset($cfg['max_tokens']) ? $cfg['max_tokens'] : 4000)));

        $hasImages = is_array($images) && !empty($images);
        $data = null;
        $text = '';
        if ($provider === 'claude' || $provider === 'anthropic') {
            $url = $base . '/messages';
            if ($hasImages) {
                // 视觉输入：user content 为块数组（文本 + base64 图片）。需模型支持视觉。
                $content = array(array('type' => 'text', 'text' => $user));
                foreach ($images as $img) {
                    $content[] = array(
                        'type' => 'image',
                        'source' => array(
                            'type' => 'base64',
                            'media_type' => isset($img['media_type']) ? $img['media_type'] : 'image/png',
                            'data' => isset($img['data']) ? $img['data'] : '',
                        ),
                    );
                }
                $messages = array(array('role' => 'user', 'content' => $content));
            } else {
                $messages = array(array('role' => 'user', 'content' => $user));
            }
            $payload = array(
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => $messages,
            );
            $headers = array('Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01');
            $resp = \app\common\util\HttpClient::curlPostWithTimeout($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
            $data = json_decode((string)$resp, true);
            if (is_array($data) && isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $blk) {
                    if (is_array($blk) && isset($blk['text'])) {
                        $text .= (string)$blk['text'];
                    }
                }
            }
        } else {
            $url = $base . '/chat/completions';
            if ($hasImages) {
                // OpenAI 兼容视觉：user content 为块数组（text + image_url data-uri）。
                $uc = array(array('type' => 'text', 'text' => $user));
                foreach ($images as $img) {
                    $mt = isset($img['media_type']) ? $img['media_type'] : 'image/png';
                    $uc[] = array(
                        'type' => 'image_url',
                        'image_url' => array('url' => 'data:' . $mt . ';base64,' . (isset($img['data']) ? $img['data'] : '')),
                    );
                }
                $userMsg = array('role' => 'user', 'content' => $uc);
            } else {
                $userMsg = array('role' => 'user', 'content' => $user);
            }
            $payload = array(
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => array(
                    array('role' => 'system', 'content' => $system),
                    $userMsg,
                ),
            );
            $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $key);
            $resp = \app\common\util\HttpClient::curlPostWithTimeout($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
            $data = json_decode((string)$resp, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $text = (string)$data['choices'][0]['message']['content'];
            }
        }

        if (trim($text) === '') {
            $emsg = '';
            if (is_array($data)) {
                if (isset($data['error']['message'])) {
                    $emsg = (string)$data['error']['message'];
                } elseif (isset($data['error']) && is_string($data['error'])) {
                    $emsg = $data['error'];
                } elseif (isset($data['message'])) {
                    $emsg = (string)$data['message'];
                }
            }
            if ($emsg !== '') {
                // API 明确返回了错误（限流/额度/鉴权等）——直接透传，避免误报“无法解析”。
                $low = strtolower($emsg);
                $rate = strpos($low, 'rate limit') !== false || strpos($low, 'rate_limit') !== false
                    || strpos($low, 'too many requests') !== false || strpos($low, '429') !== false;
                $hint = $rate ? ' ' . lang('admin/themedesign/gen_rate_limit') : '';
                return array('ok' => false, 'error' => mb_substr($emsg, 0, 240) . $hint);
            }
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_bad_response'));
        }
        return array('ok' => true, 'text' => $text);
    }

    /** 扁平分类（去缩进前缀），供 prompt 使用 */
    private function catsForPrompt()
    {
        $out = array();
        foreach ($this->catsFlat() as $c) {
            $out[] = array(
                'id' => (int)$c['id'],
                'name' => preg_replace('/^[\s　├]+/u', '', (string)$c['name']),
                'mid' => (int)$c['mid'],
            );
        }
        return $out;
    }

    /**
     * 把「分类引用」解析为有效的 type_id：接受数字 **ID** 或分类**名称**（不区分大小写、忽略缩进前缀）。
     * 无效 / 找不到返回 0。让主题在收到分类 ID 或名称时都能正确按分类构建。
     */
    private function resolveCategoryId($ref)
    {
        static $byId = null, $byName = null;
        if ($byId === null) {
            $byId = array();
            $byName = array();
            foreach ($this->catsFlat() as $c) {
                $id = (int)$c['id'];
                if ($id <= 0) {
                    continue;
                }
                $byId[$id] = $id;
                $nm = strtolower(trim(preg_replace('/^[\s　├]+/u', '', (string)$c['name'])));
                if ($nm !== '' && !isset($byName[$nm])) {
                    $byName[$nm] = $id;
                }
            }
        }
        if (is_array($ref)) {
            return 0;
        }
        $s = trim((string)$ref);
        if ($s === '') {
            return 0;
        }
        if (ctype_digit($s)) {
            $id = (int)$s;
            return isset($byId[$id]) ? $id : 0;
        }
        $nm = strtolower($s);
        return isset($byName[$nm]) ? $byName[$nm] : 0;
    }

    /** 生成用的 system 提示（内置严格 JSON 契约 + 允许的词汇表） */
    private function buildGeneratePromptSystem()
    {
        $sectionKeys = array_keys($this->sectionCatalog());
        $fonts = array_keys($this->fontOptions());

        $sys = "You are a theme configuration generator for a MacCMS video/article/manga CMS front-end (theme 'prism').\n"
            . "Read the user's requirements (freeform text OR a filled Markdown request form) and output ONE JSON object ONLY — no prose, no markdown fences.\n\n"
            . "Output schema:\n"
            . "{\n"
            . "  \"design\": {\n"
            . "    \"attributes\": {\n"
            . "      \"colors\": {\"accent\":\"#hex\",\"accent_ink\":\"#hex\",\"bg\":\"#hex\",\"surface\":\"#hex\",\"surface_2\":\"#hex\",\"text\":\"#hex\",\"text_muted\":\"#hex\",\"border\":\"#hex\"},\n"
            . "      \"font_family\": \"<one exact string from FONTS>\",\n"
            . "      \"font_size\": <integer 12..22>\n"
            . "    },\n"
            . "    \"navbar\": {\"type_ids\": [<category ids from CATEGORIES>]},\n"
            . "    \"homepage\": {\"sections\": [ {\"key\":\"<SECTION_KEYS item or 'custom'>\",\"enabled\":true,\"num\":12,\"layout\":\"slider|grid|list|''\",\"by\":\"hits|time|score (hero only)\",\"period\":\"day|week|month|all (rank_week only)\",\"type_id\":<optional category id>,\"content_type\":\"vod|art|manga|rank (custom only)\",\"title\":\"<custom only>\"} ] }\n"
            . "  },\n"
            . "  \"theme_name\": \"<short human theme name — from the form's Basics if given, else a fitting name>\",\n"
            . "  \"theme_dir\": \"<lowercase Latin folder name, 2-32 chars, starts with a letter — from the form's Basics if given, else derived from theme_name>\",\n"
            . "  \"coverage\": [ {\"requirement\":\"<short label>\",\"status\":\"implemented|partial|skipped\",\"note\":\"<short reason>\"} ]\n"
            . "}\n\n"
            . "Rules:\n"
            . "- colors MUST be 3- or 6-digit hex. For a dark theme set bg/surface/surface_2 dark and text light. accent is used in BOTH light and dark modes.\n"
            . "- Keep readable contrast: text on bg, and accent_ink on accent.\n"
            . "- font_family MUST be copied verbatim from FONTS. font_size is an integer 12..22.\n"
            . "- Only use section keys from SECTION_KEYS (extra blocks use key \"custom\"). The sections array order = top-to-bottom on the page. Disable unwanted sections with enabled:false or omit them.\n"
            . "- navbar.type_ids MUST be ids from CATEGORIES only, in the desired order.\n"
            . "- A category may be referenced by its numeric id OR its exact name; ALWAYS output the numeric id from CATEGORIES (for navbar.type_ids entries and for a section's type_id). To limit a section to one category, set that section's type_id. Omit any category we cannot match.\n"
            . "- coverage: list EVERY distinct requirement the user asked for and whether you implemented it. Things this theme system cannot do via config (custom radius/spacing/shadows, animations, a specific logo image, brand-new components) must be marked \"skipped\" with a brief note.\n\n"
            . "FONTS = " . json_encode($fonts, JSON_UNESCAPED_UNICODE) . "\n"
            . "SECTION_KEYS = " . json_encode($sectionKeys, JSON_UNESCAPED_UNICODE) . "\n"
            . "COMPONENT_CATALOG = " . json_encode((new PageBuilderKit())->componentCatalogSummaryForAi('home'), JSON_UNESCAPED_UNICODE) . "\n"
            . "CATEGORIES = " . json_encode($this->catsForPrompt(), JSON_UNESCAPED_UNICODE) . "\n";
        return $sys;
    }

    /** 解析模型输出：剥离 ``` 围栏，抽取最外层 {...} */
    private function parseGenerated($text)
    {
        $text = (string)$text;
        $text = preg_replace('/```[a-zA-Z0-9_-]*/', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $json = substr($text, $start, $end - $start + 1);
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            // 常见修复：去掉对象/数组结尾的多余逗号（模型偶发）后重试
            $repaired = preg_replace('/,(\s*[}\]])/', '$1', $json);
            $arr = json_decode($repaired, true);
        }
        return is_array($arr) ? $arr : null;
    }

    /**
     * 把生成结果安全地合并进当前草稿（白名单校验，永不信任模型直出）。
     * @return array [$cfg, $applied]
     */
    private function sanitizeGeneratedIntoDraft($gen)
    {
        $base = $this->currentDraft();
        if (!isset($base['attributes']) || !is_array($base['attributes'])) {
            $base['attributes'] = array();
        }
        $design = isset($gen['design']) && is_array($gen['design']) ? $gen['design'] : $gen;
        $applied = array('colors' => 0, 'font' => 0, 'font_size' => 0, 'sections' => 0, 'nav' => 0);

        $presets = $this->schemePresets();
        $defColors = $presets['indigo']['colors'];
        $attr = isset($design['attributes']) && is_array($design['attributes']) ? $design['attributes'] : array();

        // colors
        $genColors = isset($attr['colors']) && is_array($attr['colors']) ? $attr['colors'] : array();
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $colors = isset($base['attributes']['colors']) && is_array($base['attributes']['colors']) ? $base['attributes']['colors'] : $defColors;
        $hits = 0;
        foreach ($keys as $k) {
            if (isset($genColors[$k]) && $this->validHex($genColors[$k])) {
                $colors[$k] = $genColors[$k];
                $hits++;
            } elseif (!isset($colors[$k]) || !$this->validHex($colors[$k])) {
                $colors[$k] = $defColors[$k];
            }
        }
        if ($hits > 0) {
            $base['attributes']['colors'] = $colors;
            $base['attributes']['color_scheme'] = 'custom';
        }
        $applied['colors'] = $hits;

        // font family (must be one of the allowed stacks)
        $fontOpts = array_keys($this->fontOptions());
        if (isset($attr['font_family'])) {
            $f = trim((string)$attr['font_family']);
            if ($f !== '' && in_array($f, $fontOpts, true)) {
                $base['attributes']['font_family'] = $f;
                $applied['font'] = 1;
            }
        }

        // font size
        if (isset($attr['font_size'])) {
            $fs = intval($attr['font_size']);
            if ($fs >= 12 && $fs <= 22) {
                $base['attributes']['font_size'] = $fs;
                $applied['font_size'] = $fs;
            }
        }

        // navbar
        if (isset($design['navbar']['type_ids']) && is_array($design['navbar']['type_ids'])) {
            $ids = array();
            foreach ($design['navbar']['type_ids'] as $ref) {
                $rid = $this->resolveCategoryId($ref); // 接受数字 ID 或分类名称
                if ($rid > 0 && !in_array($rid, $ids, true)) {
                    $ids[] = $rid;
                }
            }
            if (!empty($ids)) {
                $base['navbar'] = array('type_ids' => $ids);
                $applied['nav'] = count($ids);
            }
        }

        // homepage：PB page.blocks 或 legacy sections → PB blocks
        $presetId = ThemePreset::normalizeId(
            isset($base['attributes']['preset_id']) ? $base['attributes']['preset_id'] : input('post.preset_id', '')
        );
        if (isset($design['homepage']['page']) && is_array($design['homepage']['page'])) {
            $page = $this->sanitizeHomepagePage($design['homepage']['page']);
            if (!empty($page['blocks'])) {
                $base['homepage'] = array('page' => $page);
                $applied['blocks'] = count($page['blocks']);
            }
        } elseif (isset($design['homepage']['sections']) && is_array($design['homepage']['sections'])) {
            $sections = $this->sanitizeGeneratedSections($design['homepage']['sections']);
            if (!empty($sections)) {
                if ($this->isLegacySectionsTheme()) {
                    $base['homepage'] = array('sections' => $sections);
                    $enabledCount = 0;
                    foreach ($sections as $sec) {
                        if (!empty($sec['enabled'])) {
                            $enabledCount++;
                        }
                    }
                    $applied['sections'] = $enabledCount;
                } else {
                    $page = $this->homepagePageFromSections($sections, $presetId);
                    if (!empty($page['blocks'])) {
                        $base['homepage'] = array(
                            'sections' => $sections,
                            'page' => $page,
                        );
                        $enabledCount = 0;
                        foreach ($sections as $sec) {
                            if (!empty($sec['enabled'])) {
                                $enabledCount++;
                            }
                        }
                        $applied['sections'] = $enabledCount;
                        $applied['blocks'] = count($page['blocks']);
                    }
                }
            }
        }

        $base['theme'] = $this->theme;
        $base['updated_at'] = time();
        return array($base, $applied);
    }

    /** 校验首页区块数组（安全边界） */
    private function sanitizeGeneratedSections($list)
    {
        $catalogKeys = array_keys($this->sectionCatalog());
        $layouts = array('slider', 'grid', 'list', 'filmstrip');
        $sorts = array('hits', 'time', 'score');
        $periods = array('day', 'week', 'month', 'all');
        $ctypes = array('vod', 'art', 'manga', 'rank');

        $out = array();
        $seen = array();
        $customSeq = 0;
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            $key = isset($s['key']) ? (string)$s['key'] : '';
            $custom = (isset($s['custom']) && ($s['custom'] === true || $s['custom'] === 1 || $s['custom'] === '1'))
                || $key === 'custom' || preg_match('/^custom_\d+$/', $key);
            if (!$custom) {
                if (!in_array($key, $catalogKeys, true) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            } else {
                $customSeq++;
                $key = 'custom_' . $customSeq;
            }
            $enabled = !(isset($s['enabled']) && ($s['enabled'] === false || $s['enabled'] === 0 || $s['enabled'] === '0' || $s['enabled'] === 'false'));
            $item = array('key' => $key, 'enabled' => $enabled);
            // 分类：接受数字 ID 或分类名称；兼容模型可能用的多种字段名
            $catRef = null;
            foreach (array('type_id', 'category_id', 'category', 'cat', 'category_name') as $ck) {
                if (isset($s[$ck]) && $s[$ck] !== '' && $s[$ck] !== null && !is_array($s[$ck])) {
                    $catRef = $s[$ck];
                    break;
                }
            }
            if ($catRef !== null) {
                $t = $this->resolveCategoryId($catRef);
                if ($t > 0) {
                    $item['type_id'] = $t;
                }
            }
            if (isset($s['num'])) {
                $n = intval($s['num']);
                if ($n >= 1 && $n <= 30) {
                    $item['num'] = $n;
                }
            }
            if (isset($s['layout'])) {
                $l = strtolower(trim((string)$s['layout']));
                if (in_array($l, $layouts, true)) {
                    $item['layout'] = $l;
                }
            }
            if (isset($s['by'])) {
                $b = strtolower(trim((string)$s['by']));
                if (in_array($b, $sorts, true)) {
                    $item['by'] = $b;
                }
            }
            if (isset($s['period'])) {
                $p = strtolower(trim((string)$s['period']));
                if (in_array($p, $periods, true)) {
                    $item['period'] = $p;
                }
            }
            if ($custom) {
                $item['custom'] = true;
                $ct = isset($s['content_type']) ? strtolower(trim((string)$s['content_type'])) : 'vod';
                $item['content_type'] = in_array($ct, $ctypes, true) ? $ct : 'vod';
                $title = isset($s['title']) ? trim(strip_tags((string)$s['title'])) : '';
                $item['title'] = mb_substr($title, 0, 60);
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * 将 Theme AI / 旧版 homepage.sections 转为 Page Builder 首页 page 树。
     * 顺序与 prism index.html 一致；vod_latest + rank_week 合并为 columns。
     *
     * @param array  $sections sanitizeGeneratedSections 产物
     * @param string $presetId
     * @return array
     */
    private function homepagePageFromSections(array $sections, $presetId = '')
    {
        $base = $this->homepageDefaultPage($presetId);
        $blocks = array();

        $enabledKeys = array();
        foreach ($sections as $s) {
            if (!empty($s['enabled']) && !empty($s['key'])) {
                $enabledKeys[(string) $s['key']] = true;
            }
        }
        $colMain = !empty($enabledKeys['vod_latest']);
        $colSide = !empty($enabledKeys['rank_week']);
        $colAnchor = '';
        foreach ($sections as $s) {
            if (empty($s['enabled'])) {
                continue;
            }
            $k = isset($s['key']) ? (string) $s['key'] : '';
            if ($k === 'vod_latest' || $k === 'rank_week') {
                $colAnchor = $k;
                break;
            }
        }

        foreach ($sections as $s) {
            if (empty($s['enabled'])) {
                continue;
            }
            $key = isset($s['key']) ? (string) $s['key'] : '';
            if ($key === '') {
                continue;
            }

            if (!empty($s['custom']) || preg_match('/^custom_\d+$/', $key)) {
                $customBlock = $this->pbBlockFromCustomSection($s);
                if ($customBlock !== null) {
                    $blocks[] = $customBlock;
                }
                continue;
            }

            if ($key === 'vod_latest' || $key === 'rank_week') {
                if ($colAnchor !== '' && $key === $colAnchor && ($colMain || $colSide)) {
                    $latestSec = $this->findSectionByKey($sections, 'vod_latest');
                    $rankSec = $this->findSectionByKey($sections, 'rank_week');
                    $blocks[] = $this->pbBlockColumnsLatestRank($latestSec, $rankSec, $colMain, $colSide);
                }
                continue;
            }

            $block = $this->pbBlockFromSectionKey($key, $s);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        $base['blocks'] = $blocks;
        return $this->sanitizeHomepagePage($base);
    }

    /** @return array|null */
    private function pbBlockFromSectionKey($key, array $s)
    {
        switch ($key) {
            case 'hero':
                return $this->pbBlockHero($s);
            case 'vod_hot':
                return $this->pbBlockVodHot($s);
            case 'art_list':
                return $this->pbBlockArtList($s);
            case 'topic_list':
                return $this->pbBlockTopicList($s);
            case 'live_list':
                return $this->pbBlockLiveList($s);
            case 'manga_list':
                return $this->pbBlockMangaList($s);
            case 'rank_modules':
                return $this->pbBlockRankModules($s);
            case 'website_list':
                return $this->pbBlockWebsiteList($s);
            case 'gbook_list':
                return $this->pbBlockGbookList($s);
            default:
                return null;
        }
    }

    private function findSectionByKey(array $sections, $key)
    {
        foreach ($sections as $s) {
            if (isset($s['key']) && (string) $s['key'] === (string) $key) {
                return $s;
            }
        }
        return array('key' => $key, 'enabled' => true);
    }

    private function sectionNum(array $s, $default)
    {
        if (isset($s['num'])) {
            $n = intval($s['num']);
            if ($n >= 1 && $n <= 30) {
                return $n;
            }
        }
        return (int) $default;
    }

    private function sectionTypeId(array $s, $default = 0)
    {
        if (isset($s['type_id'])) {
            $t = intval($s['type_id']);
            if ($t >= 0) {
                return $t;
            }
        }
        return (int) $default;
    }

    private function sectionBy(array $s, $default)
    {
        if (isset($s['by'])) {
            $b = strtolower(trim((string) $s['by']));
            if (in_array($b, array('hits', 'time', 'score'), true)) {
                return $b;
            }
        }
        return $default;
    }

    private function sectionPeriod(array $s, $default)
    {
        if (isset($s['period'])) {
            $p = strtolower(trim((string) $s['period']));
            if (in_array($p, array('day', 'week', 'month', 'all'), true)) {
                return $p;
            }
        }
        return $default;
    }

    private function sectionRender(array $s, $default)
    {
        if (isset($s['layout'])) {
            $l = strtolower(trim((string) $s['layout']));
            if (in_array($l, array('slider', 'grid', 'list', 'filmstrip'), true)) {
                return $l;
            }
        }
        return $default;
    }

    private function pbSectionShell($title, $icon, $moreUrl, $titleKey, array $bodyBlocks)
    {
        return array(
            'component' => 'section',
            'props' => array(
                'variant' => 'plain',
                'title' => $title,
                'icon' => $icon,
                'more_url' => $moreUrl,
                'title_key' => $titleKey,
            ),
            'slots' => array(
                'body' => $bodyBlocks,
            ),
        );
    }

    private function pbBlockHero(array $s)
    {
        $type = $this->sectionTypeId($s, 0);
        return array(
            'component' => 'filmstrip_layout',
            'slots' => array(
                'banner' => array(
                    array(
                        'component' => 'vod_carousel',
                        'props' => array(
                            'num' => $this->sectionNum($s, 6),
                            'by' => $this->sectionBy($s, 'hits'),
                            'type' => $type,
                        ),
                    ),
                ),
                'feed' => array(
                    array(
                        'component' => 'vod_list',
                        'props' => array(
                            'num' => 12,
                            'by' => 'hits_day',
                            'order' => 'desc',
                            'type' => $type,
                            'render' => 'filmstrip',
                            'card' => 'poster-h',
                            'ratio' => '69-40',
                        ),
                    ),
                ),
            ),
        );
    }

    private function pbBlockVodHot(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_vod_hot'),
            'fire',
            'vod/index',
            'string_sec_hot',
            array(
                array(
                    'component' => 'vod_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 12),
                        'by' => $this->sectionBy($s, 'hits_month'),
                        'order' => 'desc',
                        'type' => $this->sectionTypeId($s, 0),
                        'render' => $this->sectionRender($s, 'slider'),
                    ),
                ),
            )
        );
    }

    private function pbBlockColumnsLatestRank(array $latestSec, array $rankSec, $colMain, $colSide)
    {
        $cols = array();
        if ($colMain) {
            $cols['0'] = array(
                $this->pbSectionShell(
                    lang('admin/themedesign/sec_vod_latest'),
                    '',
                    'vod/index',
                    'string_sec_latest',
                    array(
                        array(
                            'component' => 'vod_list',
                            'props' => array(
                                'num' => $this->sectionNum($latestSec, 12),
                                'by' => $this->sectionBy($latestSec, 'time'),
                                'order' => 'desc',
                                'type' => $this->sectionTypeId($latestSec, 0),
                                'render' => $this->sectionRender($latestSec, 'grid'),
                            ),
                        ),
                    )
                ),
            );
        }
        if ($colSide) {
            $cols['1'] = array(
                array(
                    'component' => 'section',
                    'props' => array(
                        'variant' => 'card',
                        'title' => lang('admin/themedesign/sec_rank_week'),
                        'icon' => 'bar-chart',
                        'more_url' => '',
                        'title_key' => 'string_sec_rank',
                    ),
                    'slots' => array(
                        'body' => array(
                            array(
                                'component' => 'rank_list',
                                'props' => array(
                                    'period' => $this->sectionPeriod($rankSec, 'week'),
                                    'num' => $this->sectionNum($rankSec, 10),
                                    'type' => $this->sectionTypeId($rankSec, 0),
                                ),
                            ),
                        ),
                    ),
                ),
            );
        }
        return array(
            'component' => 'columns',
            'props' => array('cols' => array(8, 4), 'stack_at' => 720),
            'slots' => $cols,
        );
    }

    private function pbBlockArtList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_art_list'),
            'newspaper',
            'art/index',
            'string_sec_news',
            array(
                array(
                    'component' => 'art_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 8),
                        'by' => $this->sectionBy($s, 'time'),
                        'order' => 'desc',
                        'type' => $this->sectionTypeId($s, 0),
                        'render' => $this->sectionRender($s, 'grid'),
                    ),
                ),
            )
        );
    }

    private function pbBlockTopicList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_topic_list'),
            'collection',
            'topic/index',
            'string_sec_topic',
            array(
                array(
                    'component' => 'topic_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 6),
                        'by' => $this->sectionBy($s, 'hits'),
                        'order' => 'desc',
                    ),
                ),
            )
        );
    }

    private function pbBlockLiveList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_live_list'),
            'broadcast',
            'live/show',
            'string_sec_live',
            array(
                array(
                    'component' => 'live_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 8),
                    ),
                ),
            )
        );
    }

    private function pbBlockMangaList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_manga_list'),
            'book',
            'manga/index',
            'string_sec_manga',
            array(
                array(
                    'component' => 'manga_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 12),
                        'by' => $this->sectionBy($s, 'time'),
                        'order' => 'desc',
                        'type' => $this->sectionTypeId($s, 0),
                        'render' => $this->sectionRender($s, 'slider'),
                    ),
                ),
            )
        );
    }

    private function pbBlockWebsiteList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_website_list'),
            'broadcast',
            'website/index',
            'string_sec_channels',
            array(
                array(
                    'component' => 'website_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 8),
                        'type' => $this->sectionTypeId($s, 0),
                        'by' => $this->sectionBy($s, 'hits'),
                        'order' => 'desc',
                    ),
                ),
            )
        );
    }

    private function pbBlockGbookList(array $s)
    {
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_gbook_list'),
            'chat-dots',
            'gbook/index',
            'string_sec_gbook',
            array(
                array(
                    'component' => 'gbook_list',
                    'props' => array(
                        'num' => $this->sectionNum($s, 8),
                    ),
                ),
            )
        );
    }

    private function pbBlockRankModules(array $s)
    {
        $num = $this->sectionNum($s, 5);
        $cards = array();
        $types = array(1, 2, 3, 4, 35);
        for ($i = 0; $i < min($num, count($types)); $i++) {
            $cards[] = array(
                'component' => 'rank_card',
                'props' => array(
                    'title' => lang('admin/themedesign/sec_rank_modules') . ' ' . ($i + 1),
                    'num' => 5,
                    'type' => $types[$i],
                ),
            );
        }
        return $this->pbSectionShell(
            lang('admin/themedesign/sec_rank_modules'),
            'bar-chart',
            'label/rank',
            'string_sec_rankmod',
            array(
                array(
                    'component' => 'grid',
                    'props' => array('variant' => 'auto', 'cols' => '', 'min' => '320px'),
                    'slots' => array(
                        'children' => $cards,
                    ),
                ),
            )
        );
    }

    /** @return array|null */
    private function pbBlockFromCustomSection(array $s)
    {
        $title = isset($s['title']) ? trim(strip_tags((string) $s['title'])) : '';
        if ($title === '') {
            $title = lang('admin/themedesign/homepage_sections');
        }
        $title = mb_substr($title, 0, 60);
        $ct = isset($s['content_type']) ? strtolower(trim((string) $s['content_type'])) : 'vod';
        $num = $this->sectionNum($s, 12);
        $type = $this->sectionTypeId($s, 0);
        $render = $this->sectionRender($s, 'grid');

        if ($ct === 'rank') {
            $period = $this->sectionPeriod($s, 'week');
            return $this->pbSectionShell($title, 'bar-chart', '', '', array(
                array(
                    'component' => 'rank_list',
                    'props' => array(
                        'period' => $period,
                        'num' => $num,
                        'type' => $type,
                    ),
                ),
            ));
        }

        $comp = 'vod_list';
        $more = 'vod/index';
        if ($ct === 'art') {
            $comp = 'art_list';
            $more = 'art/index';
        } elseif ($ct === 'manga') {
            $comp = 'manga_list';
            $more = 'manga/index';
        }

        return $this->pbSectionShell($title, '', $more, '', array(
            array(
                'component' => $comp,
                'props' => array(
                    'num' => $num,
                    'by' => $this->sectionBy($s, 'time'),
                    'order' => 'desc',
                    'type' => $type,
                    'render' => $render,
                ),
            ),
        ));
    }

    /** 校验模型的自评「覆盖情况」列表 */
    private function sanitizeCoverage($gen)
    {
        $out = array();
        $cov = isset($gen['coverage']) && is_array($gen['coverage']) ? $gen['coverage'] : array();
        $allowed = array('implemented', 'partial', 'skipped');
        foreach ($cov as $c) {
            if (!is_array($c)) {
                continue;
            }
            $req = isset($c['requirement']) ? trim(strip_tags((string)$c['requirement'])) : '';
            if ($req === '') {
                continue;
            }
            $st = isset($c['status']) ? strtolower(trim((string)$c['status'])) : 'implemented';
            if (!in_array($st, $allowed, true)) {
                $st = 'partial';
            }
            $note = isset($c['note']) ? trim(strip_tags((string)$c['note'])) : '';
            $out[] = array(
                'requirement' => mb_substr($req, 0, 120),
                'status' => $st,
                'note' => mb_substr($note, 0, 200),
            );
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** 由主题名派生一个合法目录名（小写字母数字），失败回退 'ai'+时间戳 */
    private function slugTheme($name)
    {
        $s = strtolower((string)$name);
        $s = preg_replace('/[^a-z0-9]+/', '', $s);
        $s = substr($s, 0, 32);
        if ($s === '' || !preg_match('/^[a-z]/', $s)) {
            $s = 'ai' . time();
        }
        return substr($s, 0, 32);
    }

    /**
     * 新建 PB 主题复制源：template/_shared/starter（线上主题如 pb-starter 为独立副本）。
     * @return string
     */
    private function pbStarterDir()
    {
        return ROOT_PATH . 'template/_shared/starter';
    }

    /**
     * @param string $presetId
     * @return string
     */
    private function resolveNewThemeSourceDir($presetId)
    {
        $presetId = ThemePreset::normalizeId(trim((string) $presetId));
        $starter = ThemePreset::starterDirForPreset($presetId);
        if ($starter !== '') {
            return $starter;
        }
        return $this->pbStarterDir();
    }

    /**
     * @param string $curPreset
     * @return array<int,array<string,mixed>>
     */

    /**
     * Soft matrix gate: last report under tools/pb-matrix/out (Task F).
     * @return array{exists:bool,time:string,rel:string,mtime:int}
     */
    private function pbMatrixReportInfo()
    {
        $candidates = array(
            ROOT_PATH . 'tools/pb-matrix/out/run_all-summary.md',
            ROOT_PATH . 'tools/pb-matrix/out/REPORT.md',
        );
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $mtime = (int) @filemtime($file);
                return array(
                    'exists' => true,
                    'mtime' => $mtime,
                    'time' => $mtime > 0 ? date('Y-m-d H:i', $mtime) : '',
                    'rel' => str_replace(ROOT_PATH, '', $file),
                );
            }
        }
        return array('exists' => false, 'mtime' => 0, 'time' => '', 'rel' => '');
    }

    private function pbPresetsForView($curPreset = '')
    {
        $curPreset = ThemePreset::normalizeId($curPreset);
        $out = array();
        foreach (ThemePreset::catalog() as $id) {
            $preset = ThemePreset::load($id);
            if ($preset === null) {
                continue;
            }
            $key = isset($preset['name_key']) ? (string) $preset['name_key'] : '';
        $slug = str_replace('-', '_', preg_replace('/^preset-/', '', $id));
        $descKey = 'admin/themedesign/preset_' . $slug . '_desc';
            $out[] = array(
                'id' => $id,
                'label' => $key !== '' ? lang($key) : $id,
                'desc' => $descKey !== '' && lang($descKey) !== $descKey ? lang($descKey) : '',
                'default_scheme' => ThemePreset::defaultSchemeForPreset($id),
                'swatch' => ($id === 'preset-sidebar') ? 'nav' : (($id === 'preset-np2') ? 'np2' : preg_replace('/^preset-/', '', $id)),
                'selected' => ($id === $curPreset),
            );
        }
        return $out;
    }

    /**
     * @param array  $cfg
     * @param string $presetId
     */
    private function mergePbDraftAttributes(array &$cfg, $presetId)
    {
        $presetId = ThemePreset::normalizeId($presetId);
        if (!isset($cfg['attributes']) || !is_array($cfg['attributes'])) {
            $cfg['attributes'] = array();
        }
        // 版式只改布局标记，不覆盖站长已选配色
        $cfg['attributes']['preset_id'] = $presetId;
        $meta = ThemePreset::readPbMeta($this->themeDir());
        $cfg['attributes']['pb_shell'] = isset($meta['pb_shell']) && $meta['pb_shell'] !== ''
            ? $meta['pb_shell'] : 'thin';
        $schemeId = ThemePreset::defaultSchemeForPreset($presetId);
        if ($schemeId !== '' && $schemeId !== 'indigo') {
            $cur = isset($cfg['attributes']['color_scheme'])
                ? trim((string) $cfg['attributes']['color_scheme']) : '';
            if ($cur === '' || $cur === 'indigo') {
                $presets = $this->schemePresets();
                if (isset($presets[$schemeId]['colors']) && is_array($presets[$schemeId]['colors'])) {
                    $cfg['attributes']['color_scheme'] = $schemeId;
                    $cfg['attributes']['colors'] = $presets[$schemeId]['colors'];
                }
            }
        }
    }

    /**
     * @param string $themeDir
     * @param string $presetId
     * @param array  $cfg
     * @param bool   $force
     */
    /**
     * 以版式全量重置当前主题草稿：备份旧草稿、全量 kit 同步、重建全部可编辑页。
     * 不写入 published.json / 线上 Twig。
     *
     * @param string $presetId
     * @param bool   $backupCurrentDraft
     * @return array{code:int,msg:string,cfg?:array}
     */
    private function rebootstrapDraftFromPreset($presetId, $backupCurrentDraft = true)
    {
        $presetId = ThemePreset::normalizeId($presetId);
        $themeDir = $this->themeDir();
        if (!ThemePreset::isThinShell($themeDir) && !ThemePreset::isPbEnabled($themeDir)) {
            return array('code' => 0, 'msg' => lang('admin/themedesign/preset_not_pb_theme'));
        }

        @set_time_limit(180);

        if ($backupCurrentDraft) {
            $old = $this->readJson($this->draftFile());
            if (is_array($old)) {
                $this->ensureDir($this->historyDir());
                $this->writeJson($this->historyDir() . time() . '.json', $old);
            }
        }

        $kit = new PageBuilderKit();
        if (!$kit->syncToTheme($themeDir, true)) {
            return array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败');
        }
        if (!ThemePreset::applyPresetToTheme($presetId, $themeDir)) {
            return array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败');
        }
        ThemePreset::writePbIni($themeDir, $presetId);

        $cfg = $this->buildDefaults();
        $this->bootstrapPbPagesConfig($themeDir, $presetId, $cfg, true);
        $cfg['auth_modal'] = $kit->mergeAuthModalConfig(array());
        $cfg['theme'] = $this->theme;
        $cfg['updated_at'] = time();

        if (!$this->writeJson($this->draftFile(), $cfg)) {
            return array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败');
        }
        if (!$this->writeArtifacts($cfg, true, array('sync_kit' => false, 'compile_pages' => true))) {
            return array('code' => 0, 'msg' => lang('write_err_config') ?: '写入失败');
        }

        return array('code' => 1, 'msg' => lang('admin/themedesign/preset_reset_ok'), 'cfg' => $cfg);
    }

    private function bootstrapPbPagesConfig($themeDir, $presetId, array &$cfg, $force = false)
    {
        $scaffolder = new PageScaffolder();
        $scaffolder->bootstrapEditablePages($themeDir, $force);
        $pages = array();
        foreach (PageRegistry::routes() as $meta) {
            if (!PageRegistry::isEditableRoute($meta['route'])) {
                continue;
            }
            $route = $meta['route'];
            $page = $scaffolder->defaultPageForRoute($route, $themeDir);
            if (!empty($page)) {
                $pages[$route] = $page;
            }
        }
        if (!empty($pages)) {
            $cfg['pages'] = $pages;
            if (isset($pages['index/index'])) {
                $cfg['homepage'] = array('page' => $pages['index/index']);
            }
        }
        $this->mergePbDraftAttributes($cfg, $presetId);
    }

    /**
     * AI 生成写入的 homepage（sections / page.blocks）在 bootstrap 预设页面前暂存。
     *
     * @param array $cfg
     * @return array|null
     */
    private function extractAiHomepageConfig(array $cfg)
    {
        if (!isset($cfg['homepage']) || !is_array($cfg['homepage'])) {
            return null;
        }
        $hp = $cfg['homepage'];
        $hasSections = !empty($hp['sections']) && is_array($hp['sections']);
        $hasBlocks = !empty($hp['page']['blocks']) && is_array($hp['page']['blocks']);
        if (!$hasSections && !$hasBlocks) {
            return null;
        }
        return $hp;
    }

    /**
     * bootstrap 后恢复 AI 首页配置，并同步 pages.index/index。
     *
     * @param array      $cfg
     * @param array|null $aiHomepage
     */
    private function restoreAiHomepageConfig(array &$cfg, $aiHomepage)
    {
        if (!is_array($aiHomepage) || empty($aiHomepage)) {
            return;
        }
        $cfg['homepage'] = $aiHomepage;
        if (!empty($aiHomepage['page']) && is_array($aiHomepage['page'])) {
            if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
                $cfg['pages'] = array();
            }
            $cfg['pages']['index/index'] = $aiHomepage['page'];
        }
    }

    /**
     * 用生成的配置创建（或更新）一个新主题：首次从基底主题克隆整个目录 + 写 info.ini，
     * 然后把配置作为新主题的「线上 + 草稿」产物写入。**基底主题（prism）完全不受影响。**
     * 已存在同名目录则只更新其产物（便于「重新生成」幂等覆盖，不重复克隆）。
     * @return array ['ok'=>bool,'dir'=>string,'created'=>bool,'error'=>string]
     */
    private function createOrUpdateNewTheme($cfg, $dir, $name, $intro)
    {
        @set_time_limit(300);
        $dir = strtolower(trim((string)$dir));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $dir)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/new_dir_invalid'));
        }
        if ($this->isReservedThemeDir($dir)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/new_dir_reserved'));
        }
        $src = ROOT_PATH . 'template/' . $this->theme;
        $dest = ROOT_PATH . 'template/' . $dir;
        $presetId = ThemePreset::normalizeId(input('post.preset_id', ''));
        $created = false;
        if (!is_dir($dest)) {
            if (ThemePreset::isNp2Preset($presetId)) {
                if (!ThemePreset::materializeNp2ThemeSkeleton($dest)) {
                    return array('ok' => false, 'error' => lang('admin/themedesign/new_copy_failed'));
                }
            } else {
                $starter = $this->resolveNewThemeSourceDir($presetId);
                if (!is_dir($starter)) {
                    return array('ok' => false, 'error' => lang('admin/themedesign/new_src_missing'));
                }
                $src = $starter;
                if (!$this->copyDir($src, $dest)) {
                    return array('ok' => false, 'error' => lang('admin/themedesign/new_copy_failed'));
                }
            }
            $created = true;
            ThemePreset::writePbIni($dest, $presetId, array(
                'name' => $name,
                'lastdate' => date('Y-m-d'),
                'version' => 'V1.0',
                'author' => 'Theme AI',
                'intro' => $intro,
            ));
            ThemePreset::applyPresetToTheme($presetId, $dest);
            $kit = new PageBuilderKit();
            $kit->syncToTheme($dest, true);
            $this->mergePbDraftAttributes($cfg, $presetId);
            $aiHomepage = $this->extractAiHomepageConfig($cfg);
            $this->bootstrapPbPagesConfig($dest, $presetId, $cfg, false);
            $this->restoreAiHomepageConfig($cfg, $aiHomepage);
        }

        // 以新主题为目标写产物（草稿供预览 + 线上），随后恢复基底主题指针
        $oldTheme = $this->theme;
        $this->theme = $dir;
        $cfg['theme'] = $dir;
        $cfg['updated_at'] = time();
        $this->writeJson($this->draftFile(), $cfg);
        $this->writeJson($this->publishedFile(), $cfg);
        $this->ensureDir($this->historyDir());
        $this->writeJson($this->historyDir() . time() . '.json', $cfg);
        $artifactOpts = array('compile_pages' => true, 'sync_kit' => true);
        $this->writeArtifacts($cfg, true, $artifactOpts);   // 草稿产物（供 td_preview 预览）
        $this->writeArtifacts($cfg, false);  // 线上产物
        $this->applyDefaultAvatar($cfg);
        $this->theme = $oldTheme;

        return array('ok' => true, 'dir' => $dir, 'created' => $created);
    }

    /**
     * AI 生成：读取需求（prompt 或表单文本）→ 调 AI → 解析 → 校验 → **直接创建（或更新）一个新主题**
     * （克隆基底主题目录 + 写产物，基底主题不受影响）。POST spec / new_name / new_dir。
     * 返回 applied 摘要 + coverage 覆盖情况 + new_theme 目录。
     */
    public function generate()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        // 每管理员限流（外部 AI 调用，防刷）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_rate_limit')));
        }
        $spec = trim(mac_filter_xss((string)input('post.spec', '')));
        if ($spec === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_empty_spec')));
        }
        if (mb_strlen($spec) > 12000) {
            $spec = mb_substr($spec, 0, 12000);
        }

        @set_time_limit(320);
        $system = $this->buildGeneratePromptSystem();
        $user = "User requirements:\n\n" . $spec;

        $res = $this->themeAiChat($system, $user);
        if (empty($res['ok'])) {
            return json(array('code' => 0, 'msg' => isset($res['error']) ? $res['error'] : lang('admin/themedesign/gen_bad_response')));
        }
        $gen = $this->parseGenerated($res['text']);
        if (!is_array($gen)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        list($cfg, $applied) = $this->sanitizeGeneratedIntoDraft($gen);
        $blocksApplied = isset($applied['blocks']) ? intval($applied['blocks']) : 0;
        if ($applied['colors'] === 0 && $applied['sections'] === 0 && $blocksApplied === 0 && $applied['nav'] === 0 && $applied['font'] === 0 && $applied['font_size'] === 0) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        // 解析新主题名/目录：UI 输入优先，其次 AI 从表单提取，最后由名称派生
        $uiName = trim((string)input('post.new_name', ''));
        $uiDir  = strtolower(trim((string)input('post.new_dir', '')));
        $aiName = isset($gen['theme_name']) ? trim(strip_tags((string)$gen['theme_name'])) : '';
        $aiDir  = isset($gen['theme_dir']) ? strtolower(trim((string)$gen['theme_dir'])) : '';
        $name = $uiName !== '' ? $uiName : ($aiName !== '' ? $aiName : 'AI Theme');
        $name = mb_substr($name, 0, 60);
        $dir = $uiDir !== '' ? $uiDir : ($aiDir !== '' ? $aiDir : $this->slugTheme($name));
        $dir = preg_replace('/[^a-z0-9_-]/', '', $dir);
        if ($dir === '' || !preg_match('/^[a-z]/', $dir)) {
            $dir = 'ai' . time();
        }
        $dir = substr($dir, 0, 32);

        // 生成 = 直接创建（或更新）一个新主题：克隆基底主题目录 + 写产物，基底主题不受影响
        $new = $this->createOrUpdateNewTheme($cfg, $dir, $name, 'Generated by Theme AI');
        if (empty($new['ok'])) {
            return json(array('code' => 0, 'msg' => isset($new['error']) ? $new['error'] : lang('admin/themedesign/new_copy_failed')));
        }
        $dir = $new['dir'];

        $home = $this->siteHome();
        $preview = ThemePreviewAuth::appendQuery($home, $dir);
        $designUrl = url('theme_design/design');
        $designUrl .= (strpos($designUrl, '?') === false ? '?' : '&') . 'theme=' . urlencode($dir);

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'applied' => $applied,
            'coverage' => $this->sanitizeCoverage($gen),
            'new_theme' => $dir,
            'new_created' => !empty($new['created']),
            'preview' => $preview,
            'design_url' => $designUrl,
        ));
    }

    // ───────────────────── AI 探测（截图 / 网址 → 主题元素） ─────────────────────

    /**
     * 探测主题元素：输入截图 / 网址 / 文本 → 调 AI → 返回「可编辑的检测结果」
     * （8 个配色令牌 + 字体 + 字号 + 主题名），**不创建主题**。供前端审阅/修改后再应用。
     * POST mode=text|image|url, spec(可选), image_data/image_type(截图), url(网址)。
     */
    public function detect()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        // 每管理员限流（外部 AI 调用，防刷）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_rate_limit')));
        }
        $mode = strtolower(trim((string)input('post.mode', 'text')));
        if (!in_array($mode, array('text', 'image', 'url'), true)) {
            $mode = 'text';
        }
        $spec = trim(mac_filter_xss((string)input('post.spec', '')));
        if (mb_strlen($spec) > 6000) {
            $spec = mb_substr($spec, 0, 6000);
        }

        $images = array();
        $evidence = '';
        $suggestName = '';

        if ($mode === 'image') {
            $itype = strtolower(trim((string)input('post.image_type', '')));
            $idata = (string)input('post.image_data', '');
            $idata = preg_replace('#^data:[^,]+,#', '', $idata);
            $idata = trim($idata);
            $allow = array('image/png', 'image/jpeg', 'image/webp', 'image/gif');
            // base64 合法 + 体积上限（约 6MB 解码后）
            if (!in_array($itype, $allow, true) || $idata === '' || base64_decode($idata, true) === false || strlen($idata) > 8 * 1024 * 1024) {
                return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_detect_bad_image')));
            }
            $images[] = array('media_type' => $itype, 'data' => $idata);
        } elseif ($mode === 'url') {
            $ev = $this->fetchUrlEvidence((string)input('post.url', ''));
            if (empty($ev['ok'])) {
                return json(array('code' => 0, 'msg' => isset($ev['error']) ? $ev['error'] : lang('admin/themedesign/ai_detect_failed')));
            }
            $evidence = (string)$ev['evidence'];
            $suggestName = isset($ev['title']) ? (string)$ev['title'] : '';
        }

        $user = '';
        if ($spec !== '') {
            $user .= "Additional user description:\n" . $spec . "\n\n";
        }
        if ($evidence !== '') {
            $user .= "Website design evidence (extracted from the page HTML/CSS):\n" . $evidence . "\n\n";
        }
        if ($mode === 'image') {
            $user .= "A screenshot of the target design is attached. Detect its color palette and typography from the image.\n";
        }
        if (trim($user) === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/ai_detect_empty')));
        }

        @set_time_limit(200);
        $system = $this->buildDetectPromptSystem();
        $res = $this->themeAiChat($system, $user, $images);
        if (empty($res['ok'])) {
            return json(array('code' => 0, 'msg' => isset($res['error']) ? $res['error'] : lang('admin/themedesign/ai_detect_failed')));
        }
        $gen = $this->parseGenerated($res['text']);
        if (!is_array($gen)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }
        $detected = $this->sanitizeDetected($gen);
        if ($suggestName !== '' && $detected['theme_name'] === '') {
            $detected['theme_name'] = mb_substr($suggestName, 0, 60);
        }
        return json(array('code' => 1, 'detected' => $detected, 'mode' => $mode));
    }

    /**
     * 应用「已审阅的检测结果」→ **直接创建（或更新）一个新主题**（不再调 AI，
     * 所见即所得）。POST colors(JSON) / font_family / font_size / new_name / new_dir。
     */
    public function generate_apply()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $colors = json_decode((string)input('post.colors', ''), true);
        if (!is_array($colors)) {
            $colors = array();
        }
        $font = trim((string)input('post.font_family', ''));
        $size = (int)input('post.font_size/d', 16);

        // 复用 generate 的白名单校验：构造 gen 形状后走 sanitizeGeneratedIntoDraft
        $gen = array('design' => array('attributes' => array(
            'colors' => $colors,
            'font_family' => $font,
            'font_size' => $size,
        )));
        list($cfg, $applied) = $this->sanitizeGeneratedIntoDraft($gen);
        if ($applied['colors'] === 0 && $applied['font'] === 0 && $applied['font_size'] === 0) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }

        $uiName = trim((string)input('post.new_name', ''));
        $uiDir  = strtolower(trim((string)input('post.new_dir', '')));
        $name = $uiName !== '' ? $uiName : 'AI Theme';
        $name = mb_substr($name, 0, 60);
        $dir = $uiDir !== '' ? $uiDir : $this->slugTheme($name);
        $dir = preg_replace('/[^a-z0-9_-]/', '', $dir);
        if ($dir === '' || !preg_match('/^[a-z]/', $dir)) {
            $dir = 'ai' . time();
        }
        $dir = substr($dir, 0, 32);

        $new = $this->createOrUpdateNewTheme($cfg, $dir, $name, 'Generated by Theme AI (detected from screenshot/URL)');
        if (empty($new['ok'])) {
            return json(array('code' => 0, 'msg' => isset($new['error']) ? $new['error'] : lang('admin/themedesign/new_copy_failed')));
        }
        $dir = $new['dir'];

        $home = $this->siteHome();
        $preview = ThemePreviewAuth::appendQuery($home, $dir);
        $designUrl = url('theme_design/design');
        $designUrl .= (strpos($designUrl, '?') === false ? '?' : '&') . 'theme=' . urlencode($dir);

        return json(array(
            'code' => 1,
            'msg' => lang('admin/themedesign/new_created'),
            'applied' => $applied,
            'new_theme' => $dir,
            'new_created' => !empty($new['created']),
            'preview' => $preview,
            'design_url' => $designUrl,
        ));
    }

    /** 探测用 system 提示：只输出配色令牌 + 字体 + 字号 + 主题名 + notes（无区块/导航/coverage）。 */
    private function buildDetectPromptSystem()
    {
        $fonts = array_keys($this->fontOptions());
        $sys = "You are a visual theme extractor for a MacCMS video/article/manga CMS front-end (theme 'prism').\n"
            . "From the provided input (a screenshot image, and/or colors/fonts extracted from a website, and/or a text description), detect the design's color palette and typography. Output ONE JSON object ONLY — no prose, no markdown fences.\n\n"
            . "Output schema:\n"
            . "{\n"
            . "  \"colors\": {\"accent\":\"#hex\",\"accent_ink\":\"#hex\",\"bg\":\"#hex\",\"surface\":\"#hex\",\"surface_2\":\"#hex\",\"text\":\"#hex\",\"text_muted\":\"#hex\",\"border\":\"#hex\"},\n"
            . "  \"font_family\": \"<one exact string from FONTS>\",\n"
            . "  \"font_size\": <integer 12..22>,\n"
            . "  \"is_dark\": <true|false>,\n"
            . "  \"theme_name\": \"<short human theme name fitting the look>\",\n"
            . "  \"notes\": [\"<short observation>\"]\n"
            . "}\n\n"
            . "Map the design's REAL colors onto these 8 semantic tokens:\n"
            . "- accent: primary brand / call-to-action color.\n"
            . "- accent_ink: text color placed ON accent (usually #ffffff or #111111 for contrast).\n"
            . "- bg: page background.\n"
            . "- surface: card / panel background.\n"
            . "- surface_2: secondary or subtle surface (slightly different from surface).\n"
            . "- text: primary body text color.\n"
            . "- text_muted: secondary / muted text color.\n"
            . "- border: hairline / divider color.\n\n"
            . "Rules:\n"
            . "- All colors MUST be 3- or 6-digit hex (#rgb or #rrggbb). Never output rgb() or color names.\n"
            . "- If the design is dark, set bg/surface/surface_2 to dark values and text/text_muted to light values, and is_dark=true. Keep readable contrast (text on bg, accent_ink on accent).\n"
            . "- font_family MUST be copied verbatim from FONTS — pick the closest match to the detected typography.\n"
            . "- font_size = detected body text size in px, integer 12..22 (use 16 if unsure).\n"
            . "- notes: 1 to 5 very short strings on what you detected (palette source, dark/light, font guess).\n\n"
            . "FONTS = " . json_encode($fonts, JSON_UNESCAPED_UNICODE) . "\n";
        return $sys;
    }

    /** 把 3/4/8 位十六进制颜色规整为 6 位小写（供前端 <input type=color> 使用）。 */
    private function normalizeHex($v)
    {
        $v = strtolower(trim((string)$v));
        if (!preg_match('/^#([0-9a-f]{3,8})$/', $v, $m)) {
            return $v;
        }
        $h = $m[1];
        $len = strlen($h);
        if ($len === 3 || $len === 4) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        } elseif ($len >= 6) {
            $h = substr($h, 0, 6);
        }
        return '#' . $h;
    }

    /** 校验/规整探测结果（白名单，永不信任模型直出）。 */
    private function sanitizeDetected($gen)
    {
        $design = is_array($gen) ? $gen : array();
        if (isset($design['design']) && is_array($design['design'])) {
            $design = $design['design'];
        }
        $isDark = isset($design['is_dark'])
            && ($design['is_dark'] === true || $design['is_dark'] === 1 || $design['is_dark'] === '1' || $design['is_dark'] === 'true');

        $presets = $this->schemePresets();
        $lightDef = $presets['indigo']['colors'];
        $darkDef = array(
            'accent' => '#6366f1', 'accent_ink' => '#ffffff', 'bg' => '#0f1115', 'surface' => '#171a21',
            'surface_2' => '#1f2330', 'text' => '#f3f4f6', 'text_muted' => '#9aa0ac', 'border' => '#2a2f3a',
        );
        $def = $isDark ? $darkDef : $lightDef;

        $genColors = isset($design['colors']) && is_array($design['colors']) ? $design['colors'] : array();
        $keys = array('accent', 'accent_ink', 'bg', 'surface', 'surface_2', 'text', 'text_muted', 'border');
        $colors = array();
        foreach ($keys as $k) {
            $v = isset($genColors[$k]) ? $genColors[$k] : '';
            $colors[$k] = $this->validHex($v) ? $this->normalizeHex($v) : $def[$k];
        }

        $fontOpts = array_keys($this->fontOptions());
        $font = isset($design['font_family']) ? trim((string)$design['font_family']) : '';
        if ($font === '' || !in_array($font, $fontOpts, true)) {
            $font = $fontOpts[0];
        }

        $size = isset($design['font_size']) ? intval($design['font_size']) : 16;
        if ($size < 12 || $size > 22) {
            $size = 16;
        }

        $name = isset($design['theme_name']) ? trim(strip_tags((string)$design['theme_name'])) : '';
        $name = mb_substr($name, 0, 60);

        $notes = array();
        if (isset($design['notes']) && is_array($design['notes'])) {
            foreach ($design['notes'] as $n) {
                $s = trim(strip_tags((string)$n));
                if ($s !== '') {
                    $notes[] = mb_substr($s, 0, 120);
                }
                if (count($notes) >= 5) {
                    break;
                }
            }
        }

        return array(
            'colors' => $colors,
            'font_family' => $font,
            'font_size' => $size,
            'is_dark' => $isDark ? 1 : 0,
            'theme_name' => $name,
            'notes' => $notes,
        );
    }

    /**
     * 判断主机是否为「公网可访问」——阻断 localhost / 私有 / 保留 / 回环 / 链路本地地址（SSRF 防护）。
     * 解析失败或落在内网范围一律拒绝（fail-closed）。
     */
    /**
     * 把主机名解析为「确认为公网」的 IP；内网/保留/回环/解析失败一律回 ''（fail-closed）。
     * 返回具体 IP 是为了让调用方用 CURLOPT_RESOLVE 把连接钉在这个 IP 上，
     * 避免校验与连接之间 DNS 被重绑（rebinding）。
     */
    private function resolvePublicIp($host)
    {
        $host = strtolower(trim((string)$host));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
            return '';
        }
        $ip = $host;
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = @gethostbyname($host); // 仅解析 IPv4；失败时原样返回主机名
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return '';
            }
        }
        // IPv4：排除私有段 + 保留段
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : '';
        }
        // IPv6：排除回环 / 链路本地 / 唯一本地
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $low = strtolower($ip);
            if ($low === '::1' || strpos($low, 'fe80') === 0 || strpos($low, 'fc') === 0 || strpos($low, 'fd') === 0) {
                return '';
            }
            return $ip;
        }
        return '';
    }

    /**
     * 由 parse_url 结果 + 已校验 IP 构造 CURLOPT_RESOLVE 条目（host:port:ip）。
     * IP 为空回空数组（curl 照常按 URL 自行解析，但此时调用方已在别处拒绝）。
     */
    private function pinResolve($parts, $ip)
    {
        if (empty($parts['host']) || $ip === '') {
            return array();
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'http' ? 80 : 443);
        return array($parts['host'] . ':' . $port . ':' . $ip);
    }

    /** 把 CSS 里的相对/根相对/协议相对链接解析为绝对 URL。 */
    private function absoluteUrl($href, $baseParts)
    {
        $href = trim((string)$href);
        if ($href === '' || strpos($href, 'data:') === 0) {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $scheme = isset($baseParts['scheme']) ? $baseParts['scheme'] : 'https';
        $host = isset($baseParts['host']) ? $baseParts['host'] : '';
        if ($host === '') {
            return '';
        }
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        if (strpos($href, '//') === 0) {
            return $scheme . ':' . $href;
        }
        if (strpos($href, '/') === 0) {
            return $scheme . '://' . $host . $port . $href;
        }
        $path = isset($baseParts['path']) ? $baseParts['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if ($dir === '') {
            $dir = '/';
        }
        return $scheme . '://' . $host . $port . $dir . $href;
    }

    /**
     * 抓取网址并抽取「设计证据」：页面 HTML + 内联 <style> + 至多 3 个外链 CSS，
     * 从中提取高频颜色 / CSS 颜色变量 / font-family / <title> / meta theme-color。
     * 全程 SSRF 校验 + 不跟随重定向。@return array ['ok','evidence','title','error']
     */
    private function fetchUrlEvidence($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_empty'));
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url; // 裸域名默认 https
        }
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if (!$parts || empty($parts['host']) || !in_array($scheme, array('http', 'https'), true)) {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_bad_url'));
        }
        $pinIp = $this->resolvePublicIp($parts['host']);
        if ($pinIp === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_bad_url'));
        }

        $html = (string)\app\common\util\HttpClient::curlGetNoRedirect($url, 15, array('Accept: text/html,*/*'), $this->pinResolve($parts, $pinIp));
        if (trim($html) === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_fetch_failed'));
        }
        $html = substr($html, 0, 400000);

        $css = '';
        if (preg_match_all('#<style[^>]*>(.*?)</style>#is', $html, $sm)) {
            $css .= implode("\n", $sm[1]);
        }
        if (preg_match_all('#<link[^>]+rel=["\']?stylesheet["\']?[^>]*>#i', $html, $lm)) {
            $count = 0;
            foreach ($lm[0] as $tag) {
                if ($count >= 3) {
                    break;
                }
                if (!preg_match('#href=["\']([^"\']+)["\']#i', $tag, $hm)) {
                    continue;
                }
                $href = $this->absoluteUrl($hm[1], $parts);
                if ($href === '') {
                    continue;
                }
                $hp = parse_url($href);
                if (!$hp || empty($hp['host'])) {
                    continue;
                }
                $cssIp = $this->resolvePublicIp($hp['host']);
                if ($cssIp === '') {
                    continue;
                }
                $cssResp = (string)\app\common\util\HttpClient::curlGetNoRedirect($href, 10, array(), $this->pinResolve($hp, $cssIp));
                if (trim($cssResp) !== '') {
                    $css .= "\n" . substr($cssResp, 0, 200000);
                    $count++;
                }
            }
        }

        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
            $title = mb_substr($title, 0, 60);
        }
        $themeColor = '';
        if (preg_match('#<meta[^>]+name=["\']theme-color["\'][^>]*content=["\']([^"\']+)["\']#i', $html, $cm)) {
            $themeColor = trim($cm[1]);
        }

        $evidence = $this->extractDesignEvidence($css . "\n" . $html, $themeColor);
        if (trim($evidence) === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/ai_detect_no_evidence'));
        }
        return array('ok' => true, 'evidence' => $evidence, 'title' => $title);
    }

    /** 从 CSS/HTML 文本抽取紧凑的颜色/字体证据（限长），交给 AI 归纳成 8 令牌调色板。 */
    private function extractDesignEvidence($text, $themeColor)
    {
        $text = (string)$text;

        $colors = array();
        if (preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $text, $hm)) {
            foreach ($hm[0] as $c) {
                $c = strtolower($c);
                $colors[$c] = isset($colors[$c]) ? $colors[$c] + 1 : 1;
            }
        }
        if (preg_match_all('/rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*[\d.]+\s*)?\)/i', $text, $rm)) {
            foreach ($rm[0] as $c) {
                $c = strtolower(preg_replace('/\s+/', '', $c));
                $colors[$c] = isset($colors[$c]) ? $colors[$c] + 1 : 1;
            }
        }
        arsort($colors);
        $topColors = array_slice($colors, 0, 24, true);

        $vars = array();
        if (preg_match_all('/(--[a-z0-9_-]+)\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/i', $text, $vm, PREG_SET_ORDER)) {
            foreach ($vm as $v) {
                $vars[$v[1]] = trim($v[2]);
                if (count($vars) >= 24) {
                    break;
                }
            }
        }

        $fonts = array();
        if (preg_match_all('/font-family\s*:\s*([^;{}]+)[;}]/i', $text, $fm)) {
            foreach ($fm[1] as $f) {
                $f = trim(preg_replace('/\s+/', ' ', $f));
                if ($f !== '' && !isset($fonts[$f])) {
                    $fonts[$f] = true;
                }
                if (count($fonts) >= 10) {
                    break;
                }
            }
        }

        $lines = array();
        if ($themeColor !== '') {
            $lines[] = 'meta theme-color: ' . $themeColor;
        }
        if (!empty($topColors)) {
            $parts = array();
            foreach ($topColors as $c => $n) {
                $parts[] = $c . ' (x' . $n . ')';
            }
            $lines[] = 'Frequent colors: ' . implode(', ', $parts);
        }
        if (!empty($vars)) {
            $parts = array();
            foreach ($vars as $k => $v) {
                $parts[] = $k . '=' . $v;
            }
            $lines[] = 'CSS color variables: ' . implode('; ', $parts);
        }
        if (!empty($fonts)) {
            $lines[] = 'font-family declarations: ' . implode(' | ', array_keys($fonts));
        }
        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    // ───────────────────── AI 生成图片（logo / 头像） ─────────────────────

    /**
     * AI 生成 logo / 头像：复用 主题AI 的 api_base + api_key，调 OpenAI 兼容
     * `/images/generations`（图像模型默认 gpt-image-1，可由 theme_ai.image_model 覆盖）。
     * 支持用户附加提示词。生成图落地到 upload/theme/，返回值与 upload 契约一致：{code,msg,file}。
     * POST kind=logo|avatar, message=<可选附加提示词>。
     */
    public function gen_image()
    {
        if (!$this->request->isPost()) {
            return json(array('code' => 0, 'msg' => lang('illegal_request')));
        }
        $kind = strtolower(trim((string)input('post.kind', '')));
        if (!in_array($kind, array('logo', 'avatar'), true)) {
            return json(array('code' => 0, 'msg' => lang('param_err')));
        }
        $message = (string)input('post.message', '');

        // 每管理员限流（复用图片生成限流：5/分、50/时）
        $adminId = intval(isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : 0);
        if (!\app\common\util\VodAiCover::consumeGenerateRateLimit($adminId)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_rate_limit')));
        }

        // 图像凭据独立于「主题AI」文本凭据解析：文本可以是 Claude（不支持绘图），
        // 图像必须走支持图像模型的 OpenAI 兼容端点（ai_cover 专用配置，或 theme_ai 的 image_* 覆盖）。
        $ic = $this->resolveImageAiConfig();
        if (empty($ic['ok'])) {
            return json(array('code' => 0, 'msg' => isset($ic['error']) ? $ic['error'] : lang('admin/themedesign/gen_no_key')));
        }
        $key = $ic['key'];
        $base = $ic['base'];
        $model = $ic['model'];
        $timeout = $ic['timeout'];

        $post = array(
            'model' => $model,
            'prompt' => $this->buildImagePrompt($kind, $message),
            'n' => 1,
            'size' => '1024x1024',
        );
        $ml = strtolower($model);
        if (strpos($ml, 'gpt-image') !== false) {
            $post['quality'] = 'medium';
            if ($kind === 'logo') {
                $post['background'] = 'transparent'; // gpt-image 支持透明底，logo 更实用
            }
        } elseif (strpos($ml, 'dall-e-3') !== false) {
            $post['quality'] = 'standard';
        }

        @set_time_limit(320);
        $headers = array('Content-Type: application/json', 'Authorization: Bearer ' . $key);
        $resp = \app\common\util\HttpClient::curlPostWithTimeout(
            $base . '/images/generations',
            json_encode($post, JSON_UNESCAPED_UNICODE),
            $headers,
            $timeout
        );
        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/gen_bad_response')));
        }
        if (!empty($json['error']['message'])) {
            return json(array('code' => 0, 'msg' => mb_substr((string)$json['error']['message'], 0, 240)));
        }

        $raw = null;
        if (!empty($json['data'][0]['b64_json'])) {
            $raw = base64_decode((string)$json['data'][0]['b64_json'], true);
            if ($raw === false) {
                $raw = null;
            }
        } elseif (!empty($json['data'][0]['url'])) {
            $raw = $this->downloadImageSafe((string)$json['data'][0]['url'], min(120, $timeout));
        }
        if ($raw === null || $raw === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_failed')));
        }
        // 返回内容来自第三方端点（b64 或下载地址），落盘到 web 可访问目录前必须确认确实是图片，
        // 扩展名按实际类型取，不信任端点声明
        $imgInfo = @getimagesizefromstring($raw);
        $imgExt = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', 18 => 'webp', IMAGETYPE_GIF => 'gif'); // 18 = IMAGETYPE_WEBP（PHP 7.1+ 才有该常量）
        if (!is_array($imgInfo) || !isset($imgExt[$imgInfo[2]]) || $imgInfo[0] < 1 || $imgInfo[1] < 1) {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_failed')));
        }

        $rel = $this->saveThemeImage($raw, $kind, $imgExt[$imgInfo[2]]);
        if ($rel === '') {
            return json(array('code' => 0, 'msg' => lang('admin/themedesign/img_ai_failed')));
        }
        $file = (defined('MAC_PATH') ? MAC_PATH : '/') . $rel;
        return json(array('code' => 1, 'msg' => lang('admin/themedesign/img_ai_done'), 'file' => $file));
    }

    /**
     * 解析「图像生成」凭据（独立于文本凭据）。优先级：
     *   1) ai_cover 专用图像配置（AI 封面同款，OpenAI Images，后台可视化可配）；
     *   2) theme_ai 的 image_api_base / image_api_key / image_model 覆盖（可指向独立图像端点）；
     *      未设置则回退 theme_ai 的文本端点——仅当该代理确实提供图像模型时才可用。
     * @return array{ok:bool,key?:string,base?:string,model?:string,timeout?:int,error?:string}
     */
    private function resolveImageAiConfig()
    {
        $config = config('maccms');

        $cover = isset($config['ai_cover']) && is_array($config['ai_cover']) ? $config['ai_cover'] : array();
        if ((string)(isset($cover['enabled']) ? $cover['enabled'] : '0') === '1'
            && trim((string)(isset($cover['api_key']) ? $cover['api_key'] : '')) !== '') {
            $base = trim((string)(isset($cover['api_base']) ? $cover['api_base'] : ''));
            if ($base === '') {
                $base = 'https://api.openai.com/v1';
            }
            $model = trim((string)(isset($cover['model']) ? $cover['model'] : ''));
            return array(
                'ok' => true,
                'key' => trim((string)$cover['api_key']),
                'base' => rtrim($base, '/'),
                'model' => $model !== '' ? $model : 'gpt-image-1',
                'timeout' => max(30, min(300, intval(isset($cover['timeout']) ? $cover['timeout'] : 120))),
            );
        }

        $ta = isset($config['theme_ai']) && is_array($config['theme_ai']) ? $config['theme_ai'] : array();
        if ((string)(isset($ta['enabled']) ? $ta['enabled'] : '0') !== '1') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_disabled'));
        }
        $base = trim((string)(isset($ta['image_api_base']) && $ta['image_api_base'] !== '' ? $ta['image_api_base'] : (isset($ta['api_base']) ? $ta['api_base'] : '')));
        $key = trim((string)(isset($ta['image_api_key']) && $ta['image_api_key'] !== '' ? $ta['image_api_key'] : (isset($ta['api_key']) ? $ta['api_key'] : '')));
        if ($base === '' || $key === '') {
            return array('ok' => false, 'error' => lang('admin/themedesign/gen_no_key'));
        }
        $model = trim((string)(isset($ta['image_model']) ? $ta['image_model'] : ''));
        return array(
            'ok' => true,
            'key' => $key,
            'base' => rtrim($base, '/'),
            'model' => $model !== '' ? $model : 'gpt-image-1',
            'timeout' => max(30, min(300, intval(isset($ta['timeout']) ? $ta['timeout'] : 120))),
        );
    }

    /** 构建 logo / 头像的绘图提示：站点名 + 当前草稿主色 + 用户附加提示。 */
    private function buildImagePrompt($kind, $message)
    {
        $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : array();
        $siteName = isset($site['site_name']) ? trim(strip_tags((string)$site['site_name'])) : '';
        $draft = $this->currentDraft();
        $accent = '';
        if (isset($draft['attributes']['colors']['accent']) && $this->validHex($draft['attributes']['colors']['accent'])) {
            $accent = (string)$draft['attributes']['colors']['accent'];
        }
        $extra = mb_substr(trim(mac_filter_xss((string)$message)), 0, 600);

        if ($kind === 'logo') {
            $p = 'Design a clean, modern website logo';
            if ($siteName !== '') {
                $p .= ' for a site named "' . mb_substr($siteName, 0, 60) . '"';
            }
            $p .= '. Flat vector style, simple and memorable, centered composition, transparent background, high contrast, no photorealism, no stock-photo look, no watermark, no border.';
            if ($accent !== '') {
                $p .= ' Use ' . $accent . ' as the primary brand color.';
            }
        } else {
            $p = 'Design a friendly, generic default user avatar (profile picture). Flat illustrative style, simple, single centered subject, works well as a small circular avatar, subtle solid background, no text, no watermark.';
            if ($accent !== '') {
                $p .= ' Accent color ' . $accent . '.';
            }
        }
        if ($extra !== '') {
            $p .= "\n\nAdditional requirements: " . $extra;
        }
        return mb_substr($p, 0, 2000);
    }

    /** 安全下载图片（仅 https + 公网主机；不跟随重定向）。@return string|null 二进制内容 */
    private function downloadImageSafe($url, $timeout)
    {
        $url = trim((string)$url);
        if ($url === '' || strncasecmp($url, 'https://', 8) !== 0) {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }
        $imgIp = $this->resolvePublicIp($parts['host']);
        if ($imgIp === '') {
            return null;
        }
        $bin = \app\common\util\HttpClient::curlGetNoRedirect($url, $timeout, array(), $this->pinResolve($parts, $imgIp));
        return ($bin === false || $bin === '') ? null : (string)$bin;
    }

    /** 保存生成图到 upload/theme/<日期>/，返回站点相对路径（无前导斜杠）；失败返回 ''。 */
    private function saveThemeImage($raw, $kind, $ext)
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $ext));
        $allowed = array('png', 'jpg', 'jpeg', 'webp', 'gif');
        if (!in_array($ext, $allowed, true)) {
            $ext = 'png';
        }
        $sub = 'upload/theme/' . date('Ymd') . '/';
        if (!$this->ensureDir(ROOT_PATH . $sub)) {
            return '';
        }
        $rel = $sub . md5(microtime(true) . '_' . $kind . '_' . mt_rand()) . '.' . strtolower($ext);
        if (@file_put_contents(ROOT_PATH . $rel, $raw) === false) {
            return '';
        }
        return $rel;
    }
}
