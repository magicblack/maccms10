<?php
namespace app\common\util;

use app\common\view\TwigGuard;

/**
 * 将 page JSON（regions 或兼容 homepage.page.blocks）编译为完整 *.twig（保存/发布时生成，非运行时解析）。
 */
class TwigPageCompiler
{
    /** @var string */
    private $compileRoute = 'index/index';
    private $kit;
    private $componentMeta = array();
    private $previewMarkers = false;

    /**
     * PB 组件 id 合法性（TWIG-COMPILE-01）：kit 内所有 id 均为字母/数字/下划线/连字符。
     * 编译器把 id 直接拼进 include 路径，这里挡住路径穿越与非法构造。
     */
    private static function isValidComponentName($comp)
    {
        return is_string($comp) && $comp !== '' && preg_match('/^[a-z0-9_-]+$/i', $comp) === 1;
    }


    public function __construct()
    {
        $this->kit = new PageBuilderKit();
        $this->loadKitComponentMeta();
    }

    /**
     * @param string $route 如 index/index、vod/type
     * @param array  $page  含 regions[] 或兼容 blocks[]/chrome[]
     * @return string
     */
    public function compilePageTwig($route, array $page, $previewMarkers = false, array $layoutOpts = array())
    {
        $this->compileRoute = PageRegistry::normalizeRoute($route);
        $this->previewMarkers = (bool) $previewMarkers;
        $page = $this->normalizePageDefinition($page);
        $page = $this->patchNp2CompilePage($page, $layoutOpts);

        $isNp2MineIndex = !empty($page['np2_mine_layout']) && $this->compileRoute === 'user/index';
        $np2ChannelHome = isset($page['np2_channel_home_layout']) ? (string) $page['np2_channel_home_layout'] : '';
        $isNp2ArtIndex = $np2ChannelHome === 'art' && $this->compileRoute === 'art/index';
        $isNp2MangaIndex = $np2ChannelHome === 'manga' && $this->compileRoute === 'manga/index';
        $isNp2ChannelHome = $isNp2ArtIndex || $isNp2MangaIndex;

        $isImmersive = class_exists('\app\common\util\PageRegistry')
            && PageRegistry::isImmersiveRoute($this->compileRoute);
        $isAuthRoute = (class_exists('\app\common\util\PageRegistry')
                && PageRegistry::isAuthPageRoute($this->compileRoute))
            || (isset($page['layout']) && (string) $page['layout'] === 'auth')
            || (isset($page['layout_mod']) && (string) $page['layout_mod'] === 'auth');

        $hasSiteHeader = !$isImmersive && !empty($layoutOpts['site_header']);
        $hasChannel = !$isImmersive && !empty($layoutOpts['channel']);
        $hasHero = !$isImmersive && !empty($layoutOpts['hero']);
        $hasFooter = !$isImmersive && !$isAuthRoute && !empty($layoutOpts['footer']);
        if ($isNp2MineIndex) {
            $hasSiteHeader = false;
            $hasFooter = false;
        }
        if ($isNp2ChannelHome) {
            $hasFooter = false;
        }
        $chromeFallbacks = !$isImmersive
            && (!isset($layoutOpts['chrome_fallbacks']) || !empty($layoutOpts['chrome_fallbacks']));
        $shellLeftNav = !empty($layoutOpts['shell_left_nav']);

        if ($hasFooter) {
            $page['regions']['footer'] = $this->kit->normalizeFooterBlocks($page['regions']['footer']);
        }
        $regions = $page['regions'];
        $chrome = $hasSiteHeader ? $this->resolveChrome($page) : array();
        $footerBlock = $hasFooter ? $this->resolveFooter($page) : array();

        $allBlocks = $this->collectAllBlocksFromPage($page);
        if ($isNp2ChannelHome) {
            $headAssets = $this->emitPbSharedAssetTags() . $this->emitNp2ChannelHomeHeadAssets($this->compileRoute);
        } else {
            $headAssets = $this->emitPbSharedAssetTags() . $this->compileAssetTagsForBlocks($allBlocks, $chrome, $footerBlock);
        }
        if ($this->blocksContainComponent($allBlocks, 'np2_play_shell')
            || $this->blocksContainComponent($allBlocks, 'vod_player')) {
            $headAssets .= $this->emitNp2PlayShellHeadTags();
        }
        $needsBanner = $this->needsBannerScript(isset($regions['content']) ? $regions['content'] : array());

        $layoutMod = isset($page['layout_mod']) ? (string) $page['layout_mod'] : '';
        // layout_mod 会被逐字拼进生成的 .twig（{% block layout_mod %}），必须白名单收口，
        // 否则 base.twig" %}{{ 注入 }} 之类的值可越狱出模板标签（SSTI）。非法即丢弃。
        if ($layoutMod !== '' && !preg_match('/^[a-z0-9_-]+$/i', $layoutMod)) {
            $layoutMod = '';
        }
        $pageType = isset($page['page_type']) ? strtolower((string) $page['page_type']) : '';
        if ($layoutMod === '' && $pageType !== '' && $pageType !== 'home') {
            $layoutMod = $pageType;
        } elseif ($layoutMod === '' && $this->compileRoute === 'index/index') {
            $layoutMod = 'home';
        }

        $layoutName = isset($page['layout']) ? (string) $page['layout'] : 'base';
        // 同 layout_mod：layout 会拼进 {% extends "layout/<name>.twig" %}，白名单收口防 SSTI。
        if (!preg_match('/^[a-z0-9_-]+$/i', $layoutName)) {
            $layoutName = 'base';
        }
        $isAuthPage = class_exists('\app\common\util\PageRegistry')
            && (PageRegistry::isAuthPageRoute($this->compileRoute)
                || $layoutName === 'auth'
                || $layoutMod === 'auth');
        if ($isAuthPage) {
            $layoutName = 'auth';
            $layoutMod = 'auth';
            // auth 整页：禁 chrome 槽位，避免误注入顶栏/页脚资源与 page-member 样式
            $hasSiteHeader = false;
            $hasChannel = false;
            $hasHero = false;
            $hasFooter = false;
            $chromeFallbacks = false;
            $chrome = array();
            $footerBlock = array();
            $allBlocks = $this->collectAllBlocksFromPage($page);
            $headAssets = $this->emitPbSharedAssetTags() . $this->compileAssetTagsForBlocks($allBlocks, array(), array());
        }
        $out = "{# Generated by Theme Design Page Builder — do not edit manually #}\n";
        $out .= "{% extends \"layout/" . $layoutName . ".twig\" %}\n";
        if ($layoutMod !== '') {
            $out .= "{% block layout_mod %} " . $layoutMod . "{% endblock %}\n";
        }
        $shellClass = '';
        if ($shellLeftNav) {
            $shellClass .= ' is-left-nav';
        }
        if ($shellClass !== '') {
            $out .= "{% block shell_class %}" . $shellClass . "{% endblock %}\n";
        }
        // page_class 只能写一次；沉浸式路由修饰符并入同一 block，避免 Twig 重复定义导致空白页
        $pageClass = '';
        if (!empty($layoutOpts['components_only'])) {
            $pageClass = 'pb-components-only';
            if ($isAuthPage) {
                $pageClass .= ' page-auth';
            } elseif ($layoutMod === 'home' || $this->compileRoute === 'index/index') {
                $pageClass .= ' page-home';
            }
            if (!$isAuthPage && $layoutMod === 'novel-home') {
                $pageClass .= ' page-novel-home';
            }
            if (!$isAuthPage && ($layoutMod === 'member' || $pageType === 'member')) {
                $pageClass .= ' page-member';
                $pageClass .= $this->memberPageModifierClass($this->compileRoute);
            }
            if (!$isAuthPage && $layoutMod === 'manga-home') {
                $pageClass .= ' page-manga-home';
            }
            if (!$isAuthPage && $layoutMod === 'detail') {
                if ($this->compileRoute === 'vod/detail') {
                    $pageClass .= ' page-vod-detail';
                } elseif ($this->compileRoute === 'manga/detail') {
                    $pageClass .= ' page-manga-detail';
                } elseif ($this->compileRoute === 'art/detail') {
                    $pageClass .= ' page-art-detail';
                }
            }
        }
        if ($isImmersive) {
            if ($this->compileRoute === 'art/read') {
                $pageClass .= ($pageClass !== '' ? ' ' : '') . 'page-novel-read';
            } elseif ($this->compileRoute === 'manga/play') {
                $pageClass .= ($pageClass !== '' ? ' ' : '') . 'page-manga-play';
            }
        }
        $extraPageClass = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if ($extraPageClass !== '' && preg_match('/^[a-z0-9_ -]+$/i', $extraPageClass)) {
            $pageClass = trim($pageClass . ' ' . $extraPageClass);
        }
        if ($pageClass !== '') {
            $out .= "{% block page_class %} " . $pageClass . "{% endblock %}\n";
        }
        if (isset($page['twig_sets']) && is_array($page['twig_sets'])) {
            foreach ($page['twig_sets'] as $varName => $varVal) {
                $varName = (string) $varName;
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $varName)) {
                    continue;
                }
                $out .= "{% set " . $varName . " = " . $this->exportTwigSetLiteral($varVal) . " %}\n";
            }
        }
        if (!empty($layoutOpts['components_only']) && !$isAuthPage) {
            $out .= "{% block theme_styles %}\n";
            $out .= $this->emitPbThemeShellTags(isset($layoutOpts['theme_dir']) ? (string) $layoutOpts['theme_dir'] : '');
            $out .= "{% endblock %}\n";
        }
        $out .= "\n";
        $out .= $this->compileTitleBlock($page);
        $out .= "\n";
        $out .= "{% block head %}\n";
        if ($isNp2MineIndex) {
            $out .= $this->emitNp2MineUserIndexHeadAssets();
        } elseif ($isNp2ChannelHome) {
            // emitNp2ChannelHomeHeadAssets 已在上方写入 $headAssets
        } elseif (!$isAuthPage && ($layoutMod === 'member' || $pageType === 'member')) {
            $out .= $this->emitSharedMemberAssetTags($this->compileRoute);
        }
        $out .= $headAssets;
        if ($needsBanner) {
            $out .= $this->bannerScriptContent();
        }
        $out .= "{% endblock %}\n\n";

        $pathOffset = 0;
        if ($isImmersive) {
            $out .= "{% block site_header %}{% endblock %}\n\n";
            $out .= "{% block hero %}{% endblock %}\n\n";
            $out .= "{% block channel %}{% endblock %}\n\n";
        }
        if ($isNp2MineIndex) {
            $out .= "{% block site_header %}\n";
            $out .= "<div class=\"np2-mine-pc-only\">\n";
            $out .= "  {% include 'page-builder-components/app_top_chrome/template.twig' with {props: { tab: 'mine', embed_search: false }} %}\n";
            $out .= "</div>\n";
            $out .= "{% endblock %}\n\n";
        }
        if ($shellLeftNav) {
            $out .= "{% block shell_nav %}\n";
            $sh = isset($regions['site_header']) ? $regions['site_header'] : array();
            $navRendered = false;
            if (!empty($sh)) {
                foreach ($sh as $blk) {
                    if (is_array($blk) && isset($blk['component']) && $blk['component'] === 'site_nav') {
                        $out .= $this->compileRegionBlocks(array($blk), 1, 'sn', 0);
                        $navRendered = true;
                        break;
                    }
                }
            }
            if (!$navRendered && $chromeFallbacks && $hasSiteHeader) {
                $out .= $this->compileChromeInclude($chrome, 'site_nav', 1, 'sn/0');
            }
            $out .= "{% endblock %}\n\n";
        }
        if ($hasSiteHeader) {
            $out .= "{% block site_header %}\n";
            if ($isNp2ChannelHome) {
                $out .= $this->compileNp2ChannelHomeSiteHeader();
            } else {
            $sh = isset($regions['site_header']) ? $regions['site_header'] : array();
            if ($shellLeftNav) {
                $filtered = array();
                foreach ($sh as $blk) {
                    if (is_array($blk) && isset($blk['component']) && $blk['component'] === 'site_nav') {
                        continue;
                    }
                    $filtered[] = $blk;
                }
                if (!empty($filtered)) {
                    $out .= $this->compileRegionBlocks($filtered, 1, 'sh', $pathOffset);
                }
            } elseif (!empty($sh)) {
                $out .= $this->compileRegionBlocks($sh, 1, 'sh', $pathOffset);
            } elseif ($chromeFallbacks) {
                $out .= $this->compileChromeInclude($chrome, 'site_nav', 1, 'sh/0');
            }
            }
            $out .= "{% endblock %}\n\n";
            $pathOffset += 10;
        }

        if ($hasHero) {
            $out .= "{% block hero %}\n";
            if ($isNp2ArtIndex) {
                $out .= $this->compileNp2ArtIndexHero();
            } elseif ($isNp2MangaIndex) {
                $out .= $this->compileNp2MangaIndexHero();
            } else {
            $hero = isset($regions['hero']) ? $regions['hero'] : array();
            if (!empty($hero)) {
                $out .= $this->compileRegionBlocks($hero, 1, 'hero', $pathOffset);
            } elseif ($chromeFallbacks && $this->shouldCompileChromeFallback($page, 'header_decor')) {
                $out .= $this->compileChromeInclude($chrome, 'header_decor', 1, 'hero/0');
            }
            }
            $out .= "{% endblock %}\n\n";
            $pathOffset += 10;
        }

        if ($hasChannel) {
            $out .= "{% block channel %}\n";
            $channelBlocks = isset($regions['channel']) ? $regions['channel'] : array();
            if (!empty($channelBlocks)) {
                $out .= $this->compileRegionBlocks($channelBlocks, 1, 'ch', $pathOffset);
            } elseif ($chromeFallbacks && $this->shouldCompileChromeFallback($page, 'channel_strip')) {
                $out .= $this->compileChromeInclude($chrome, 'channel_strip', 1, 'ch/0');
            }
            $out .= "{% endblock %}\n\n";
            $pathOffset += 10;
        }

        $contentBlocks = isset($regions['content']) ? $regions['content'] : array();
        $out .= "{% block content %}\n";
        if ($isNp2MineIndex) {
            $out .= $this->compileNp2MineUserIndexContent();
        } elseif ($isNp2ArtIndex) {
            $out .= $this->compileNp2ArtIndexContent();
        } elseif ($isNp2MangaIndex) {
            $out .= $this->compileNp2MangaIndexContent();
        } else {
        if ($chromeFallbacks && !$hasSiteHeader && !$hasHero && !$hasChannel) {
            $out .= $this->compileChromeInclude($chrome, 'site_nav', 1, 'sh/0');
            $out .= $this->compileChromeInclude($chrome, 'header_decor', 1, 'hero/0');
            $out .= $this->compileChromeInclude($chrome, 'channel_strip', 1, 'ch/0');
        }
        // 内容区路径与 page-builder.js assignRegionPaths 一致：0-based 索引，不带区域前缀。
        $out .= $this->compileRegionBlocks($contentBlocks, 0, '', 0);
        }
        $out .= "{% endblock %}\n\n";

        $sidebarBlocks = isset($regions['sidebar']) ? $regions['sidebar'] : array();
        $out .= "{% block sidebar %}\n";
        $out .= $this->compileRegionBlocks($sidebarBlocks, 1, 'sb', $pathOffset + 100);
        $out .= "{% endblock %}\n\n";

        if ($isImmersive) {
            $out .= "{% block footer %}{% endblock %}\n\n";
        } elseif ($hasFooter) {
            $footerBlocks = isset($regions['footer']) ? $regions['footer'] : array();
            $out .= "{% block footer %}\n";
            if (!empty($footerBlocks)) {
                $out .= $this->compileRegionBlocks($footerBlocks, 1, 'ft', $pathOffset + 200);
            } elseif (!empty($footerBlock)) {
                $out .= $this->compileFooterInclude($footerBlock, $pathOffset + 200);
            }
            $out .= "{% endblock %}\n\n";
        }

        return $out;
    }

    /**
     * @param array $page 含 blocks[]
     * @return string
     */
    public function compileIndexTwig(array $page, $previewMarkers = false, array $layoutOpts = array())
    {
        return $this->compilePageTwig('index/index', $page, $previewMarkers, $layoutOpts);
    }

    /**
     * @param array  $page
     * @param string $themeDir 主题根目录（含 trailing slash）
     * @param bool   $isDraft
     * @return bool
     */
    public function writeIndexTwig(array $page, $themeDir, $isDraft = false, array $layoutOpts = array())
    {
        return $this->writePageTwig('index/index', $page, $themeDir, $isDraft, $layoutOpts);
    }

    /**
     * 页面是否应由编译器写出非空 Twig（含 preset-np2 固定布局页）。
     *
     * @param array  $page
     * @param string $route
     * @return bool
     */
    public static function pageNeedsCompile(array $page, $route)
    {
        $route = PageRegistry::normalizeRoute($route);
        if ($route === 'index/index') {
            return true;
        }
        if (!empty($page['np2_mine_layout']) && $route === 'user/index') {
            return true;
        }
        $np2 = isset($page['np2_channel_home_layout']) ? (string) $page['np2_channel_home_layout'] : '';
        if ($np2 === 'art' && $route === 'art/index') {
            return true;
        }
        if ($np2 === 'manga' && $route === 'manga/index') {
            return true;
        }
        $compiler = new self();
        $normalized = $compiler->normalizePageDefinition($page);
        return !empty($normalized['regions']['content'])
            || !empty($normalized['regions']['site_header'])
            || !empty($normalized['regions']['sidebar']);
    }

    /**
     * @param string $route  如 vod/type
     * @param array  $page
     * @param string $themeDir
     * @param bool   $isDraft
     * @return bool
     */
    public function writePageTwig($route, array $page, $themeDir, $isDraft = false, array $layoutOpts = array())
    {
        $route = PageRegistry::normalizeRoute($route);
        // 路径段白名单：normalizeRoute 不剥 `..`，而批量发布（writeArtifacts）遍历
        // cfg['pages'] 的键时不过 isEditableRoute，故在写盘 sink 处统一收口，
        // 任一段含 `..`/`.`/`/` 之外的字符即拒绝，防止把 .twig 写到主题目录之外。
        $segs = explode('/', $route);
        foreach ($segs as $seg) {
            if (!preg_match('/^[a-z0-9_-]+$/i', $seg)) {
                return false;
            }
        }
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return false;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir === '' || !is_dir($themeDir)) {
            return false;
        }
        $pageDir = $themeDir . 'html/' . $parts[0] . '/';
        if (!is_dir($pageDir)) {
            if (!@mkdir($pageDir, 0755, true) && !is_dir($pageDir)) {
                return false;
            }
        }
        $base = $parts[1];
        $file = $pageDir . ($isDraft ? $base . '.draft.twig' : $base . '.twig');
        $twig = $this->compilePageTwig($route, $page, $isDraft, $layoutOpts);
        return @file_put_contents($file, $twig) !== false;
    }

    /**
     * 将 regions 或 legacy blocks/chrome 归一为 regions 六槽。
     * @param array $page
     * @return array
     */
    public function normalizePageDefinition(array $page)
    {
        $regions = array(
            'site_header' => array(),
            'hero' => array(),
            'channel' => array(),
            'content' => array(),
            'sidebar' => array(),
            'footer' => array(),
        );

        if (isset($page['regions']) && is_array($page['regions'])) {
            foreach ($regions as $name => $empty) {
                if (isset($page['regions'][$name]) && is_array($page['regions'][$name])) {
                    $regions[$name] = $page['regions'][$name];
                }
            }
            $page['regions'] = $regions;
            if (!isset($page['blocks']) && !empty($regions['content'])) {
                $page['blocks'] = $regions['content'];
            }
            return $page;
        }

        $chrome = isset($page['chrome']) && is_array($page['chrome']) ? $page['chrome'] : array();
        foreach ($chrome as $c) {
            if (!is_array($c) || empty($c['component'])) {
                continue;
            }
            $comp = (string) $c['component'];
            if ($comp === 'site_nav') {
                $regions['site_header'][] = $c;
            } elseif ($comp === 'header_decor') {
                $regions['hero'][] = $c;
            } elseif ($comp === 'channel_strip') {
                $regions['channel'][] = $c;
            }
        }
        $regions['content'] = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : array();
        if (isset($page['footer']) && is_array($page['footer']) && !empty($page['footer']['component'])) {
            $regions['footer'][] = $page['footer'];
        }
        $page['regions'] = $regions;
        if (!isset($page['blocks'])) {
            $page['blocks'] = $regions['content'];
        }
        if (!isset($page['chrome'])) {
            $page['chrome'] = $chrome;
        }
        return $page;
    }

    /**
     * preset-np2 编译期补丁：分类/筛选/搜索列表强制分页，避免旧 page JSON 漏开 paging。
     *
     * @param array $page
     * @param array $layoutOpts
     * @return array
     */
    private function patchNp2CompilePage(array $page, array $layoutOpts = array())
    {
        if (!class_exists('\app\common\util\ThemePreset')) {
            return $page;
        }
        $themeDir = isset($layoutOpts['theme_dir']) ? (string) $layoutOpts['theme_dir'] : '';
        if ($themeDir === '' || !\app\common\util\ThemePreset::isNp2Preset(\app\common\util\ThemePreset::readPbMeta($themeDir)['pb_preset'])) {
            return $page;
        }
        if (!preg_match('#^(vod|art|manga)/(type|show|search)$#', $this->compileRoute, $m)) {
            return $page;
        }
        $module = $m[1];
        $action = $m[2];
        $listComps = array('vod_list', 'art_list', 'manga_list', 'content_feed');
        $walker = function (&$blocks) use (&$walker, $listComps, $module, $action) {
            if (!is_array($blocks)) {
                return;
            }
            foreach ($blocks as &$block) {
                if (!is_array($block)) {
                    continue;
                }
                if (!empty($block['component']) && in_array((string) $block['component'], $listComps, true)) {
                    if (!isset($block['props']) || !is_array($block['props'])) {
                        $block['props'] = array();
                    }
                    $block['props']['paging'] = true;
                    $block['props']['pageurl'] = $module . '/' . $action;
                }
                if (!empty($block['slots']) && is_array($block['slots'])) {
                    foreach ($block['slots'] as &$slotBlocks) {
                        $walker($slotBlocks);
                    }
                    unset($slotBlocks);
                }
            }
            unset($block);
        };
        if (isset($page['regions']) && is_array($page['regions'])) {
            foreach ($page['regions'] as &$region) {
                $walker($region);
            }
            unset($region);
        }
        if (isset($page['blocks']) && is_array($page['blocks'])) {
            $walker($page['blocks']);
        }
        return $page;
    }

    private function compileRegionBlocks(array $blocks, $depth, $pathPrefix, $pathOffset)
    {
        if (empty($blocks)) {
            return '';
        }
        return $this->compileBlocks($blocks, $depth, $pathPrefix, $pathOffset);
    }

    private function compileTitleBlock(array $page)
    {
        if ($this->compileRoute === 'index/index') {
            return "{% block title %}{% if maccms.site_title %}{{ maccms.site_title }}{% else %}{{ maccms.site_name }}{% endif %}{% endblock %}";
        }
        if (!empty($page['title_twig'])) {
            // title_twig 是原样注入编译产物的 Twig 代码（TWIG-COMPILE-01）：
            // 写入前过安全扫描，命中即弃用并回落默认标题，宁缺勿滥。
            $hits = TwigGuard::violations((string) $page['title_twig']);
            if (empty($hits)) {
                return '{% block title %}' . $page['title_twig'] . '{% endblock %}';
            }
        }
        return "{% block title %}{{ maccms.page_title|default(maccms.site_name) }}{% endblock %}";
    }

    private function collectAllBlocksFromPage(array $page)
    {
        $page = $this->normalizePageDefinition($page);
        $all = array();
        foreach ($page['regions'] as $blocks) {
            if (is_array($blocks)) {
                foreach ($blocks as $b) {
                    $all[] = $b;
                }
            }
        }
        return $all;
    }

    private function compileAssetTagsForBlocks(array $allBlocks, array $chrome, $footerBlock)
    {
        $out = $this->compileChromeAssetTags($chrome);
        $out .= $this->compileContentAssetTags($allBlocks);
        // 页脚常经 resolveFooter 注入，不在 content blocks 里，须单独输出 CSS/JS
        if (!empty($footerBlock)) {
            $out .= $this->emitPbComponentAssetTags('site_footer');
        }
        // 铃铛嵌在 site_header_bar 内，不在 chrome JSON；有顶栏就带上样式（JS 由 chrome 宏全局加载）
        if ($this->chromeHasComponent($chrome, 'site_nav')) {
            $out .= $this->emitPbComponentStyleTags('notify_bell');
        }
        return $out;
    }

    /**
     * 首页等页面显式关闭 chrome_opts 时，空 hero/channel 不再回退注入默认块。
     *
     * @param array  $page
     * @param string $component header_decor|channel_strip
     * @return bool
     */
    private function shouldCompileChromeFallback(array $page, $component)
    {
        $component = (string) $component;
        $opts = isset($page['chrome_opts']) && is_array($page['chrome_opts']) ? $page['chrome_opts'] : array();
        if (array_key_exists($component, $opts)) {
            return !empty($opts[$component]);
        }
        return true;
    }

    /**
     * @param array $chrome
     * @param string $id
     * @return bool
     */
    private function chromeHasComponent(array $chrome, $id)
    {
        $id = (string) $id;
        foreach ($chrome as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * kit/shared 资源版本号（内容哈希）：发布/重置时随编译写入 ?v=，改动后浏览器不再沿用旧缓存。
     *
     * @param string $rel 相对 template/_shared/kit/shared/ 的路径
     * @return string
     */
    private function sharedAssetVer($rel)
    {
        $file = ROOT_PATH . 'template/_shared/kit/shared/' . ltrim((string) $rel, '/');
        $hash = is_file($file) ? @md5_file($file) : false;
        return $hash ? '?v=' . substr($hash, 0, 8) : '';
    }

    private function emitPbSharedAssetTags()
    {
        return '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/_shared/css/peers.css' . $this->sharedAssetVer('css/peers.css') . '">' . "\n"
            . '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/_shared/css/comment.css' . $this->sharedAssetVer('css/comment.css') . '">' . "\n"
            . '<script src="{{ maccms.path_tpl }}page-builder-components/_shared/js/scroll-row.js' . $this->sharedAssetVer('js/scroll-row.js') . '" defer></script>' . "\n";
    }

    /**
     * 播放进度记忆 / 续播（kit/shared/js/vod-play-progress.js）；跨域线路按可见时长估算。
     * 片长：durationSec（mac_vod_duration_seconds 解析 vod_duration），解析不出时前端读 probe 里同集 m3u8 累加 EXTINF。
     */
    private function emitNp2PlayShellHeadTags()
    {
        return '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/_shared/css/vod-play-progress.css' . $this->sharedAssetVer('css/vod-play-progress.css') . '">' . "\n"
            . '<script type="application/json" id="vod-play-progress-config">{"enabled":true,"apiBase":"{{ maccms.path|trim(\'/\') }}/api.php","vodId":{{ obj.vod_id|default(0) }},"sid":{{ param.sid|default(1) }},"nid":{{ param.nid|default(1) }},"vodDuration":{{ obj.vod_duration|default(\'\')|json_encode|raw }},{% set _vpm = mac_vod_progress_meta(obj, param.sid|default(1), param.nid|default(1)) %}"durationSec":{{ _vpm.durationSec|default(0) }},"probe":{{ _vpm.probe|default([])|json_encode|raw }},"userId":{{ user.user_id|default(0) }}}</script>' . "\n"
            . '<script defer src="{{ maccms.path_tpl }}page-builder-components/_shared/js/vod-play-progress.js' . $this->sharedAssetVer('js/vod-play-progress.js') . '"></script>' . "\n";
    }

    /**
     * @param array  $blocks
     * @param string $compId
     * @return bool
     */
    private function blocksContainComponent(array $blocks, $compId)
    {
        $compId = (string) $compId;
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            if ((string) $b['component'] === $compId) {
                return true;
            }
            if (isset($b['slots']) && is_array($b['slots'])) {
                foreach ($b['slots'] as $children) {
                    if (is_array($children) && $this->blocksContainComponent($children, $compId)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Page Builder 预览页仍需主题 token / 覆盖色 / 壳层布局，否则组件 CSS 变量失效。
     */
    private function emitPbThemeShellTags($themeDir = '')
    {
        if (!class_exists('\app\common\util\ThemePreset', false)) {
            require_once __DIR__ . '/ThemePreset.php';
        }
        return \app\common\util\ThemePreset::emitShellTagsForCompile($themeDir);
    }

    private function emitPbComponentStyleTags($id)
    {
        if (!self::isValidComponentName($id)) {
            return '';
        }
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
        if ($id === '') {
            return '';
        }
        return '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/' . $id . '/style.css">' . "\n";
    }

    private function emitPbComponentAssetTags($id)
    {
        if (!self::isValidComponentName($id)) {
            return '';
        }
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
        if ($id === '') {
            return '';
        }
        return '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/' . $id . '/style.css">' . "\n"
            . '<script src="{{ maccms.path_tpl }}page-builder-components/' . $id . '/script.js" defer></script>' . "\n";
    }

    /**
     * 组件 peer_styles（如 shared/css/entity_detail.css → _shared/css/entity_detail.css）须单独输出 link。
     * @param array $peerStyles
     * @return string
     */
    private function emitPeerStyleTags(array $peerStyles)
    {
        $out = '';
        foreach ($peerStyles as $ps) {
            $ps = $this->normalizePeerStylePath($ps);
            if ($ps === '') {
                continue;
            }
            $out .= '<link rel="stylesheet" href="{{ maccms.path_tpl }}page-builder-components/' . $ps . '">' . "\n";
        }
        return $out;
    }

    /**
     * component.json 中 peer_styles 用 shared/ 前缀，主题目录为 _shared/。
     * @param string $ps
     * @return string
     */
    private function normalizePeerStylePath($ps)
    {
        $ps = str_replace('\\', '/', (string) $ps);
        if ($ps === '' || strpos($ps, '..') !== false) {
            return '';
        }
        if (strpos($ps, 'shared/') === 0) {
            $ps = '_shared/' . substr($ps, 7);
        }
        return $ps;
    }

    private function compileBlocks(array $blocks, $depth, $pathPrefix, $pathOffset = 0)
    {
        $out = '';
        foreach ($blocks as $i => $block) {
            if (!is_array($block)) {
                continue;
            }
            if ($pathPrefix === '') {
                $path = (string) ((int) $pathOffset + (int) $i);
            } else {
                $path = $pathPrefix . '/' . $i;
            }
            // 共享碎片：{ type: include, path: page-builder-components/_shared/... }
            if (isset($block['type']) && (string) $block['type'] === 'include') {
                $out .= $this->compilePathInclude($block, $depth, $path);
                continue;
            }
            if (empty($block['component'])) {
                continue;
            }
            $out .= $this->compileBlock($block, $depth, $path);
        }
        return $out;
    }

    /**
     * 编译共享碎片 include（非 PB 组件，如 pay_confirm.twig）。
     *
     * @param array  $block
     * @param int    $depth
     * @param string $path
     * @return string
     */
    private function compilePathInclude(array $block, $depth, $path)
    {
        $rel = str_replace('\\', '/', (string) (isset($block['path']) ? $block['path'] : ''));
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        // 仅允许主题内 page-builder-components / html 相对路径
        if (strpos($rel, 'page-builder-components/') !== 0 && strpos($rel, 'html/') !== 0) {
            return '';
        }
        $pad = str_repeat('  ', $depth);
        $esc = str_replace("'", "\\'", $rel);
        $inner = $pad . "{% include '{$esc}' %}\n";
        if ($this->previewMarkers && $path !== '') {
            return $this->wrapPreviewHost($inner, 'include', $path, $depth);
        }
        return $inner;
    }

    private function compileBlock(array $block, $depth, $path)
    {
        $comp = (string) $block['component'];
        $props = isset($block['props']) && is_array($block['props']) ? $block['props'] : array();
        $slots = isset($block['slots']) && is_array($block['slots']) ? $block['slots'] : array();

        if ($comp === 'section') {
            $inner = $this->compileSection($props, $slots, $depth, $path);
        } elseif ($comp === 'filmstrip_layout') {
            $inner = $this->compileFilmstripLayout($slots, $depth, $path);
        } elseif ($comp === 'carousel-filmstrip') {
            $inner = $this->compileLegacyCarouselFilmstrip($props, $depth, $path);
        } elseif ($comp === 'columns') {
            $inner = $this->compileColumns($props, $slots, $depth, $path);
        } elseif ($comp === 'grid') {
            $inner = $this->compileGrid($props, $slots, $depth, $path);
        } elseif ($comp === 'content_feed') {
            // 走 content_feed 自身模板（含「换一换」壳），勿直接展开为 vod_list 等
            $inner = $this->compileLeafInclude('content_feed', $props, $depth);
        } elseif ($comp === 'rank_feed') {
            $style = isset($props['style']) ? (string) $props['style'] : 'panel';
            $innerComp = $style === 'inline' ? 'rank_inline' : 'rank_list';
            $inner = $this->compileLeafInclude($innerComp, $props, $depth);
        } elseif ($comp === 'rank_card') {
            $pad = str_repeat('  ', $depth);
            $inner = $pad . $this->includeRankCard($props) . "\n";
        } elseif (!empty($slots)) {
            $inner = $this->compileSlottedInclude($comp, $props, $slots, $depth, $path);
        } else {
            $inner = $this->compileLeafInclude($comp, $props, $depth);
        }

        if ($this->previewMarkers && $path !== '') {
            return $this->wrapPreviewHost($inner, $comp, $path, $depth);
        }
        return $inner;
    }

    private function wrapPreviewHost($inner, $comp, $path, $depth)
    {
        $pad = str_repeat('  ', $depth);
        $escPath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        $escComp = htmlspecialchars($comp, ENT_QUOTES, 'UTF-8');
        return $pad . '<div class="pb-block-host" data-pb-path="' . $escPath . '" data-pb-component="' . $escComp . '">' . "\n"
            . $inner
            . $pad . '</div>' . "\n";
    }

    private function compileSection(array $props, array $slots, $depth, $path)
    {
        $pad = str_repeat('  ', $depth);
        $variant = isset($props['variant']) ? (string) $props['variant'] : 'plain';
        $title = isset($props['title']) ? (string) $props['title'] : '';
        $titleMode = isset($props['title_mode']) ? (string) $props['title_mode'] : 'custom';
        $titleKey = '';
        if (isset($props['title_lang_key'])) {
            $titleKey = (string) $props['title_lang_key'];
        } elseif (isset($props['title_key'])) {
            $titleKey = (string) $props['title_key'];
        }
        $more = isset($props['more_url']) ? (string) $props['more_url'] : '';
        $headSort = isset($props['head_sort']) ? (string) $props['head_sort'] : '';
        $sortRoute = isset($props['sort_route']) ? (string) $props['sort_route'] : 'show';
        $icon = isset($props['icon']) ? (string) $props['icon'] : '';
        if ($titleMode === 'type_name') {
            $secId = 'pb-type';
        } else {
            $secSlug = $title !== '' ? $title : ($titleKey !== '' ? $titleKey : 'sec');
            $secId = 'pb-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $secSlug));
        }
        $titleExpr = $this->compileSectionTitleExpr($props);
        $ico = $this->sectionIcon($icon);
        $cardClass = $variant === 'card' ? ' bili-sec--card' : '';
        $body = isset($slots['body']) && is_array($slots['body']) ? $slots['body'] : array();
        $body = $this->markNestedSelfTitleHidden($body);

        $out = $pad . "<section class=\"bili-sec{$cardClass}\" aria-labelledby=\"{$secId}\">\n";
        if ($headSort === 'vod_show' || $headSort === 'vod_index' || $headSort === 'content_show') {
            $out .= $this->compileSectionShowSortLinks($pad . '  ', $headSort, $sortRoute);
        }
        $out .= $pad . "  {% include \"page-builder-components/_shared/sec_head.twig\" with {\n";
        $out .= $pad . "    id: '{$secId}',\n";
        $out .= $pad . "    title: " . $titleExpr . ",\n";
        $out .= $pad . "    lang: " . ($titleKey !== '' ? $this->twigString($titleKey) : "''") . ",\n";
        if ($headSort === 'vod_show' || $headSort === 'vod_index' || $headSort === 'content_show') {
            $out .= $pad . "    more_url: '',\n";
            $out .= $pad . "    sort_links: sort_links,\n";
            $out .= $pad . "    sort_aria: '排序',\n";
        } else {
            $out .= $pad . "    more_url: " . $this->compileSectionMoreUrlExpr($body, $more) . ",\n";
        }
        $out .= $pad . "    icon_mod: " . $this->twigString($ico['mod']) . ",\n";
        $out .= $pad . "    icon_name: " . $this->twigString($ico['name']) . ",\n";
        $out .= $pad . "    show_icon: " . ($icon !== '' ? 'true' : 'false') . "\n";
        $out .= $pad . "  } %}\n";
        $out .= $pad . "  <div class=\"bili-sec__body\">\n";
        $out .= $this->compileBlocks($body, $depth + 2, $path . '/body');
        $out .= $pad . "  </div>\n";
        $out .= $pad . "</section>\n";
        return $out;
    }

    private function compileSectionShowSortLinks($pad, $headSort, $sortRoute = 'show')
    {
        if ($headSort === 'vod_index') {
            $sortDefs = array(
                array('value' => 'hits_week', 'label' => '周热度', 'lang' => 'string_sort_hits_week', 'icon' => 'sort-hits-week'),
                array('value' => 'hits_month', 'label' => '月热度', 'lang' => 'string_sort_hits_month', 'icon' => 'sort-hits-month'),
                array('value' => 'duration', 'label' => '时长排序', 'lang' => 'string_sort_duration', 'icon' => 'sort-duration'),
            );
        } else {
        $sortDefs = array(
            array('value' => 'hits_week', 'label' => '周热度', 'lang' => 'string_sort_hits_week', 'icon' => 'sort-hits-week'),
            array('value' => 'hits_month', 'label' => '月热度', 'lang' => 'string_sort_hits_month', 'icon' => 'sort-hits-month'),
            array('value' => 'hits', 'label' => '总热度', 'lang' => 'string_sort_hits', 'icon' => 'sort-hits'),
        );
        if ($headSort === 'vod_show') {
            $sortDefs[] = array('value' => 'duration', 'label' => '时长排序', 'lang' => 'string_sort_duration', 'icon' => 'sort-duration');
        }
        }
        $out = $pad . "{% set _cur_by_raw = param.by|default('') %}\n";
        $out .= $pad . "{% set _cur_by = (_cur_by_raw is iterable) ? '' : _cur_by_raw|trim %}\n";
        $out .= $pad . "{% set _cur_order_raw = param.order|default('') %}\n";
        $out .= $pad . "{% set _cur_order = (_cur_order_raw is iterable) ? '' : _cur_order_raw|trim|lower %}\n";
        $out .= $pad . "{% if _cur_order != 'asc' and _cur_order != 'desc' %}{% set _cur_order = 'desc' %}{% endif %}\n";
        $out .= $pad . "{% set _sort_carry = {\n";
        $out .= $pad . "  state: param.state|default(''),\n";
        $out .= $pad . "  area: param.area|default(''),\n";
        $out .= $pad . "  year: param.year|default(''),\n";
        $out .= $pad . "  lang: param.lang|default(''),\n";
        $out .= $pad . "  letter: param.letter|default(''),\n";
        $out .= $pad . "  class: param.class|default(''),\n";
        $out .= $pad . "  version: param.version|default(''),\n";
        $out .= $pad . "  wd: param.wd|default(''),\n";
        $out .= $pad . "  type: param.type|default(''),\n";
        $out .= $pad . "  cate: param.cate|default(''),\n";
        $out .= $pad . "  page: 1\n";
        $out .= $pad . "} %}\n";
        $out .= $pad . "{% set _type_for_url = {\n";
        $out .= $pad . "  type_id: obj.type_id|default(0),\n";
        $out .= $pad . "  type_mid: obj.type_mid|default(maccms.mid|default(1))\n";
        $out .= $pad . "} %}\n";
        $out .= $pad . "{% set sort_links = [\n";
        foreach ($sortDefs as $def) {
            $value = $def['value'];
            $label = $def['label'];
            $lang = $def['lang'];
            $icon = $def['icon'];
            $orderExpr = "(_cur_by == " . $this->twigString($value) . " and _cur_order == 'desc') ? 'asc' : 'desc'";
            if ($sortRoute === 'index') {
                $hrefExpr = "mac_url('vod/index', _sort_carry|merge({by: " . $this->twigString($value) . ", order: {$orderExpr}}))";
            } else {
                $hrefExpr = "mac_url_type(_type_for_url, _sort_carry|merge({by: " . $this->twigString($value) . ", order: {$orderExpr}}), " . $this->twigString($sortRoute) . ")";
            }
            $out .= $pad . "  {\n";
            $out .= $pad . "    href: {$hrefExpr},\n";
            $out .= $pad . "    label: " . $this->twigString($label) . ",\n";
            $out .= $pad . "    lang: " . $this->twigString($lang) . ",\n";
            $out .= $pad . "    icon: " . $this->twigString($icon) . ",\n";
            $out .= $pad . "    icon_component: 'section',\n";
            $out .= $pad . "    active: _cur_by == " . $this->twigString($value) . ",\n";
            $out .= $pad . "    order: (_cur_by == " . $this->twigString($value) . ") ? _cur_order : ''\n";
            $out .= $pad . "  },\n";
        }
        $out .= $pad . "] %}\n";
        return $out;
    }

    private function compileSectionTitleExpr(array $props)
    {
        $title = isset($props['title']) ? (string) $props['title'] : '';
        $titleMode = isset($props['title_mode']) ? (string) $props['title_mode'] : 'custom';
        if ($titleMode === 'type_name') {
            $fallback = $title !== '' ? $title : '分类内容';
            return 'obj.type_name|default(' . $this->twigString($fallback) . ')';
        }
        return $this->twigString($title);
    }

    /**
     * 与 kit section 模板 / pb_section_more_url 一致：优先按 body 内列表组件 by 推导「更多」。
     */
    private function compileSectionMoreUrlExpr(array $body, $fallbackMore)
    {
        if (!empty($body[0]) && is_array($body[0]) && isset($body[0]['component'])) {
            $child = $body[0];
            $comp = (string) $child['component'];
            $p = isset($child['props']) && is_array($child['props']) ? $child['props'] : array();
            $typeRaw = isset($p['type']) ? $p['type'] : 0;
            $typeId = $typeRaw === 'current' ? 0 : (int) $typeRaw;
            $by = isset($p['by']) ? (string) $p['by'] : 'time';
            $order = isset($p['order']) ? (string) $p['order'] : 'desc';
            if ($comp === 'rank_list' || $comp === 'rank_feed' || $comp === 'rank_inline') {
                return "mac_url('label/rank')";
            }
            if ($comp === 'topic_list') {
                return "mac_url('topic/index')";
            }
            if ($comp === 'live_list') {
                return "mac_url('live/index')";
            }
            if ($comp === 'website_list') {
                return "mac_url('website/index')";
            }
            if ($comp === 'gbook_list') {
                return "mac_url('gbook/index')";
            }
            if ($comp === 'actor_list') {
                return "mac_url('actor/index', {by: " . $this->twigString($by) . ", order: " . $this->twigString($order) . "})";
            }
            if ($comp === 'content_feed') {
                $bind = isset($p['bind']) ? (string) $p['bind'] : 'vod';
                if (in_array($bind, array('topic', 'live', 'website', 'gbook'), true)) {
                    return "mac_url('" . $bind . "/index')";
                }
                if ($bind === 'art' || $bind === 'manga') {
                    $mid = $bind === 'art' ? 2 : 12;
                    return $typeId > 0
                        ? "mac_url_type({type_id: {$typeId}, type_mid: {$mid}})"
                        : "mac_url('" . $bind . "/index')";
                }
                return $this->compileVodMoreUrlExpr($typeId, $by, $order);
            }
            if ($comp === 'art_list') {
                // 无指定分类时落到小说顶级分类 show 页，与分类链接一致（带子分类筛选导航），勿用裸 art/show
                if ($typeId > 0) {
                    return "mac_url_type({type_id: {$typeId}, type_mid: 2}, {by: " . $this->twigString($by) . "}, 'show')";
                }
                return $this->compileArtTopShowUrlExpr($by);
            }
            if ($comp === 'manga_list') {
                return $typeId > 0
                    ? "mac_url_type({type_id: {$typeId}, type_mid: 12})"
                    : "mac_url('manga/index')";
            }
            if ($comp === 'vod_list') {
                return $this->compileVodMoreUrlExpr($typeId, $by, $order);
            }
        }
        if ($fallbackMore === 'art/show' || $fallbackMore === 'art/type') {
            return $this->compileArtTopShowUrlExpr('time');
        }
        if ($fallbackMore !== '') {
            return 'mac_url(' . $this->twigString($fallbackMore) . ')';
        }
        return "''";
    }

    private function compileVodMoreUrlExpr($typeId, $by, $order)
    {
        if ($typeId > 0) {
            return "mac_url_type({type_id: {$typeId}, type_mid: 1}, {by: " . $this->twigString($by) . ", order: " . $this->twigString($order) . "}, 'show')";
        }
        if ($by === 'hits_month') {
            return "mac_url('label/rank') ~ '#month'";
        }
        if ($by === 'hits_week') {
            return "mac_url('label/rank') ~ '#week'";
        }
        if ($by === 'hits_day') {
            return "mac_url('label/rank') ~ '#day'";
        }
        return "mac_url('vod/index', {by: " . $this->twigString($by) . ", order: " . $this->twigString($order) . "})";
    }

    /** 小说顶级分类 show 页（与 pb_section_more_url art_list 分支一致） */
    private function compileArtTopShowUrlExpr($by)
    {
        return "(mac_top_types(2, 1)|first ? mac_url_type(mac_top_types(2, 1)|first, {by: " . $this->twigString($by) . "}, 'show') : mac_url('art/search'))";
    }

    private function compileColumns(array $props, array $slots, $depth, $path)
    {
        $pad = str_repeat('  ', $depth);
        $cols = isset($props['cols']) && is_array($props['cols']) && count($props['cols']) ? $props['cols'] : array(8, 4);
        if (count($cols) < 2) {
            $cols = array(8, 4);
        }
        $parts = array();
        foreach ($cols as $c) {
            $parts[] = (int) $c . 'fr';
        }
        $colsCss = implode(' ', $parts);
        if ($colsCss === '') {
            $colsCss = '8fr 4fr';
        }

        $out = $pad . "<div class=\"bili-columns\" style=\"--pb-cols: {$colsCss}; --pb-stack-at: " . (isset($props['stack_at']) ? (int) $props['stack_at'] : 720) . "px;\">\n";
        $colCount = count($cols);
        if ($colCount < 1) {
            $colCount = 2;
            $cols = array(8, 4);
        }
        for ($i = 0; $i < $colCount; $i++) {
            $key = (string) $i;
            $children = isset($slots[$key]) && is_array($slots[$key]) ? $slots[$key] : array();
            $out .= $pad . "  <div class=\"bili-columns__col\">\n";
            $out .= $this->compileBlocks($children, $depth + 2, $path . '/' . $i);
            $out .= $pad . "  </div>\n";
        }
        $out .= $pad . "</div>\n";
        return $out;
    }

    private function compileGrid(array $props, array $slots, $depth, $path)
    {
        $pad = str_repeat('  ', $depth);
        $variant = isset($props['variant']) ? (string) $props['variant'] : 'auto';
        $minW = isset($props['min']) ? (string) $props['min'] : '280px';
        // min 被直插进 style 属性（--pb-grid-min），编译层自行过滤，勿依赖上游 sanitizer：
        // 只保留 CSS 长度合法字符，堵住引号/分号闭合属性造成的存储型 XSS。
        $minW = preg_replace('/[^0-9a-z%.\-]/i', '', $minW);
        if ($minW === '') {
            $minW = '280px';
        }
        $children = isset($slots['children']) && is_array($slots['children']) ? $slots['children'] : array();

        if ($variant === 'rankmods') {
            $out = $pad . "<div class=\"bili-rankmods pb-grid--rankmods\" style=\"--pb-grid-min: {$minW};\">\n";
            foreach ($children as $j => $child) {
                $out .= $pad . "  <div class=\"bili-rankmods__item\">\n";
                $out .= $this->compileBlock($child, $depth + 2, $path . '/children/' . $j);
                $out .= $pad . "  </div>\n";
            }
            $out .= $pad . "</div>\n";
            return $out;
        }

        $out = $pad . "<div class=\"pb-grid--auto\" style=\"--pb-grid-min: {$minW};\">\n";
        $out .= $this->compileBlocks($children, $depth + 1, $path . '/children');
        $out .= $pad . "</div>\n";
        return $out;
    }

    private function compileFilmstripLayout(array $slots, $depth, $path)
    {
        $pad = str_repeat('  ', $depth);
        $banner = isset($slots['banner']) && is_array($slots['banner']) ? $slots['banner'] : array();
        $feed = isset($slots['feed']) && is_array($slots['feed']) ? $slots['feed'] : array();
        $out = $pad . "<section class=\"bili-feed pb-cf\" aria-label=\"推荐\" data-pb-surround-layout>\n";
        if ($this->previewMarkers) {
            $out .= $pad . "  <div class=\"pb-surround-slot pb-surround-slot--banner\" data-pb-surround-slot=\"banner\">\n";
            $out .= $this->compileBlocks($banner, $depth + 2, $path . '/banner');
            $out .= $pad . "  </div>\n";
            $out .= $pad . "  <div class=\"pb-surround-slot pb-surround-slot--feed\" data-pb-surround-slot=\"feed\">\n";
            $out .= $this->compileBlocks($feed, $depth + 2, $path . '/feed');
            $out .= $pad . "  </div>\n";
        } else {
            $out .= $this->compileBlocks($banner, $depth + 1, $path . '/banner');
            $out .= $this->compileBlocks($feed, $depth + 1, $path . '/feed');
        }
        $out .= $pad . "</section>\n";
        return $out;
    }

    private function compileLegacyCarouselFilmstrip(array $props, $depth, $path)
    {
        $type = isset($props['type']) ? $props['type'] : 0;
        $slots = array(
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
                    ),
                ),
            ),
        );
        return $this->compileFilmstripLayout($slots, $depth, $path);
    }

    private function compileLeafInclude($comp, array $props, $depth)
    {
        if (!self::isValidComponentName($comp)) {
            // 纵深防御（TWIG-COMPILE-01）：正常路径的块树已过 sanitizeHomepagePage
            // 白名单，这里兜底拦住未经消毒的 draft.json / 手改配置进入 include 路径。
            return '';
        }
        $pad = str_repeat('  ', $depth);
        $path = 'page-builder-components/' . $comp . '/template.twig';
        $propsTwig = $this->twigHash($props);
        return $pad . "{% include '{$path}' with {props: {$propsTwig}} %}\n";
    }

    /**
     * 带 slots 的组件（如 member_shell）：预编译 body 为 member_body，避免运行时依赖 page_builder/block.twig。
     *
     * @param string $comp
     * @param array  $props
     * @param array  $slots
     * @param int    $depth
     * @param string $path
     * @return string
     */
    private function compileSlottedInclude($comp, array $props, array $slots, $depth, $path)
    {
        if (!self::isValidComponentName($comp)) {
            return '';
        }
        $pad = str_repeat('  ', $depth);
        $tpl = 'page-builder-components/' . $comp . '/template.twig';
        $propsTwig = $this->twigHash($props);
        $body = isset($slots['body']) && is_array($slots['body']) ? $slots['body'] : array();
        $bodyTwig = $this->compileBlocks($body, $depth + 1, $path !== '' ? ($path . '/body') : 'body');

        $out = $pad . "{% set _pb_slot_body %}\n";
        $out .= $bodyTwig;
        $out .= $pad . "{% endset %}\n";
        $out .= $pad . "{% include '{$tpl}' with {props: {$propsTwig}, member_body: _pb_slot_body} %}\n";
        return $out;
    }

    /**
     * 会员子页 body class 修饰符（对齐 twigspike page-member--*）。
     *
     * @param string $route
     * @return string
     */
    private function memberPageModifierClass($route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $map = array(
            'user/upgrade' => ' page-member--upgrade body-buy-upgrade-combo',
            'user/buy' => ' page-member--upgrade body-buy-upgrade-combo',
            'user/orders' => ' page-member--orders',
            'user/pay' => ' page-member--pay',
            'user/cards' => ' page-member--cards',
            'user/dynamics' => ' page-member--dynamics page-user-social',
            'user/notify' => ' page-member--notify',
            'user/benefits' => ' page-member--benefits',
            'user/favs' => ' page-member--favs',
            'user/plays' => ' page-member--plays',
            'user/checkin' => ' page-member--checkin mac-checkin-wrap user-checkin-page',
            'user/invite' => ' page-member--invite',
            'user/plog' => ' page-member--plog',
            'user/mall' => ' page-member--mall user-mall-page',
            'user/mall_orders' => ' page-member--mall user-mall-page',
            'user/bind' => ' page-member--bind user-bind-page-wrap',
            'user/unbind' => ' page-member--unbind',
            'user/cash' => ' page-member--cash',
            'user/info' => ' page-member--info',
            'user/index' => ' page-member--index',
        );
        return isset($map[$route]) ? $map[$route] : '';
    }

    /**
     * preset-np2 个人中心首页：H5 np2_mine_hub + PC member_shell_sheet + user_member_hub。
     *
     * @return string
     */
    private function compileNp2MineUserIndexContent()
    {
        $out = "<div class=\"np2-mine-h5-only\">\n";
        $out .= "  {% include 'lib/np2_mine_hub.twig' %}\n";
        $out .= "</div>\n";
        $out .= "<div class=\"np2-mine-pc-only\">\n";
        $out .= "  {% set _pb_slot_body %}\n";
        $out .= "    {% include 'page-builder-components/user_member_hub/template.twig' with {props: {}} %}\n";
        $out .= "  {% endset %}\n";
        $out .= "  {% include 'page-builder-components/member_shell_sheet/template.twig' with {props: { active: 'index', title: '个人中心', show_hub: false, require_login: true }, member_body: _pb_slot_body} %}\n";
        $out .= "</div>\n";
        return $out;
    }

    /**
     * preset-np2 个人中心首页 head 资源。
     *
     * @return string
     */
    private function emitNp2MineUserIndexHeadAssets()
    {
        $base = '{{ maccms.path_tpl|default(\'\') }}page-builder-components/_shared/css/';
        $path = '{{ maccms.path_tpl }}page-builder-components/';
        $out = '<link rel="stylesheet" href="' . $base . 'member.css">' . "\n";
        $out .= '<link rel="stylesheet" href="' . $base . 'member-mobile.css">' . "\n";
        $comps = array('app_top_chrome', 'member_shell_sheet', 'member_profile_card', 'member_nav', 'user_member_hub');
        foreach ($comps as $comp) {
            $out .= '<link rel="stylesheet" href="' . $path . $comp . '/style.css">' . "\n";
            $out .= '<script src="' . $path . $comp . '/script.js" defer></script>' . "\n";
        }
        return $out;
    }

    /**
     * preset-np2 小说/漫画频道顶栏 + 广告宫格。
     *
     * @return string
     */
    private function compileNp2ChannelHomeSiteHeader()
    {
        $out = "  {% include \"lib/np2_home_header.twig\" %}\n";
        $out .= "  {% include \"lib/np2_home_ads.twig\" %}\n";
        return $out;
    }

    /**
     * preset-np2 小说首页 hero：轮播 banner。
     *
     * @return string
     */
    private function compileNp2ArtIndexHero()
    {
        return "{% include 'page-builder-components/novel_home_banner/template.twig' with {props: { bind: 'art', num: 6, by: 'hits_week', cta: '立即阅读' }} %}\n";
    }

    /**
     * preset-np2 漫画首页 hero：精选 spotlight。
     *
     * @return string
     */
    private function compileNp2MangaIndexHero()
    {
        return "{% include 'page-builder-components/manga_home_spotlight/template.twig' with {props: { title: '精选推荐', num: 7, by: 'hits_week', bind: 'manga', subtitle: '今日值得追', cta: '立即阅读' }} %}\n";
    }

    /**
     * preset-np2 小说首页正文区块（与 template/np2/html/art/index.twig 一致）。
     *
     * @return string
     */
    private function compileNp2ArtIndexContent()
    {
        $out = "{% set _nv_banner = mac_art({num: 6, order: 'desc', by: 'hits_week'}).list|default([]) %}\n";
        $out .= "{% set _nv_ex = [] %}\n";
        $out .= "{% for a in _nv_banner %}\n";
        $out .= "  {% set _nv_ex = _nv_ex|merge([a.art_id]) %}\n";
        $out .= "{% endfor %}\n";
        $out .= "{% include 'page-builder-components/novel_home_grid/template.twig' with {props: { title: '猜你喜欢', subtitle: '为你精选好书', title_art: true, num: 9, by: 'hits_month', exclude_ids: _nv_ex }} %}\n";
        $out .= "{% include 'page-builder-components/novel_home_zone/template.twig' with {props: { type_num: 3, row_num: 10 }} %}\n";
        $out .= "{% include 'page-builder-components/novel_home_rank/template.twig' with {props: { num: 6 }} %}\n";
        $out .= "{% include 'page-builder-components/novel_home_row/template.twig' with {props: { preset: 'new', num: 12 }} %}\n";
        $out .= "{% include 'page-builder-components/novel_home_row/template.twig' with {props: { preset: 'finish', num: 12 }} %}\n";
        return $out;
    }

    /**
     * preset-np2 漫画首页正文区块（与 template/np2/html/manga/index.twig 一致）。
     *
     * @return string
     */
    private function compileNp2MangaIndexContent()
    {
        $out = "{% include 'page-builder-components/manga_home_row/template.twig' with {props: { by: 'hits_week', title: '人气热门', subtitle: '', num: 12 }} %}\n";
        $out .= "{% include 'page-builder-components/manga_home_row/template.twig' with {props: { by: 'time', title: '最近更新', subtitle: '', num: 12 }} %}\n";
        $out .= "{% include 'page-builder-components/manga_home_rank/template.twig' with {props: { num: 8, title: '排行榜' }} %}\n";
        return $out;
    }

    /**
     * preset-np2 小说/漫画首页 head 资源（不含 site_nav / bottom_nav 等通用壳组件）。
     *
     * @param string $route
     * @return string
     */
    private function emitNp2ChannelHomeHeadAssets($route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $path = '{{ maccms.path_tpl }}page-builder-components/';
        $out = '<script src="' . $path . '_shared/js/scroll-row.js' . $this->sharedAssetVer('js/scroll-row.js') . '" defer></script>' . "\n";
        if ($route === 'art/index') {
            $comps = array('novel_home_banner', 'novel_home_grid', 'novel_home_zone', 'novel_home_rank', 'novel_home_row');
        } elseif ($route === 'manga/index') {
            $comps = array('manga_home_spotlight', 'manga_home_row', 'manga_home_rank');
        } else {
            return '';
        }
        foreach ($comps as $comp) {
            $out .= '<link rel="stylesheet" href="' . $path . $comp . '/style.css">' . "\n";
            $out .= '<script src="' . $path . $comp . '/script.js" defer></script>' . "\n";
        }
        return $out;
    }

    private function emitSharedMemberAssetTags($route)
    {
        $route = PageRegistry::normalizeRoute($route);
        $base = '{{ maccms.path_tpl|default(\'\') }}page-builder-components/_shared/css/';
        $out = '<link rel="stylesheet" href="' . $base . 'member.css">' . "\n";
        $out .= '<link rel="stylesheet" href="' . $base . 'member-mobile.css">' . "\n";
        if ($route === 'user/favs' || $route === 'user/plays') {
            $out .= '<link rel="stylesheet" href="' . $base . 'member-favs.css">' . "\n";
        }
        if (in_array($route, array('user/upgrade', 'user/buy', 'user/orders', 'user/cards', 'user/pay'), true)) {
            $out .= '<link rel="stylesheet" href="' . $base . 'pay.css">' . "\n";
            $out .= '<link rel="stylesheet" href="' . $base . 'coupon.css">' . "\n";
        }
        if ($route === 'user/pay') {
            $out .= '<link rel="stylesheet" href="' . $base . 'dialogs.css">' . "\n";
        }
        if ($route === 'user/orders' || $route === 'user/cards' || $route === 'user/plog'
            || $route === 'user/invite' || $route === 'user/cash') {
            $out .= '<link rel="stylesheet" href="' . $base . 'records.css">' . "\n";
        }
        if ($route === 'user/upgrade' || $route === 'user/buy') {
            $out .= '<link rel="stylesheet" href="' . $base . 'upgrade.css">' . "\n";
        }
        if ($route === 'user/notify') {
            $out .= '<link rel="stylesheet" href="' . $base . 'notify.css">' . "\n";
        }
        return $out;
    }

    private function includeRankCard(array $props)
    {
        $typeId = isset($props['type']) ? (int) $props['type'] : (isset($props['type_id']) ? (int) $props['type_id'] : 0);
        $num = isset($props['num']) ? (int) $props['num'] : 5;
        $title = isset($props['title']) ? (string) $props['title'] : '';
        $hideTitle = !empty($props['hide_title']);
        return '{% include "page-builder-components/rank_card/template.twig" with {'
            . 'type_id: ' . $typeId . ', '
            . 'num: ' . $num . ', '
            . 'title: ' . $this->twigString($title) . ', '
            . 'hide_title: ' . ($hideTitle ? 'true' : 'false')
            . '} %}';
    }

    private function compileChromeAssetTags(array $chrome)
    {
        $components = array();
        $peerStyles = array();
        $this->walkAssets($chrome, $components, $peerStyles);

        $out = '';
        foreach ($components as $cid) {
            $out .= $this->emitPbComponentAssetTags($cid);
        }
        $out .= $this->emitPeerStyleTags($peerStyles);
        return $out;
    }

    private function compileContentAssetTags(array $blocks)
    {
        $components = array();
        $peerStyles = array();
        $this->walkAssets($blocks, $components, $peerStyles);

        $out = '';
        foreach ($components as $cid) {
            $out .= $this->emitPbComponentAssetTags($cid);
        }
        $out .= $this->emitPeerStyleTags($peerStyles);
        return $out;
    }

    private function compileAssetTags(array $blocks, array $chrome = array())
    {
        $components = array();
        $peerStyles = array();
        $this->walkAssets($chrome, $components, $peerStyles);
        $this->walkAssets($blocks, $components, $peerStyles);

        $out = $this->emitPbSharedAssetTags();
        foreach ($components as $cid) {
            $out .= $this->emitPbComponentAssetTags($cid);
        }
        $out .= $this->emitPeerStyleTags($peerStyles);
        return $out;
    }

    private function walkAssets(array $blocks, &$components, &$peerStyles)
    {
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            $props = isset($b['props']) && is_array($b['props']) ? $b['props'] : array();

            if ($comp === 'content_feed') {
                $this->registerComponent('content_feed', $components, $peerStyles);
                $bind = isset($props['bind']) ? (string) $props['bind'] : 'vod';
                $this->registerComponent($this->bindToListComponent($bind), $components, $peerStyles);
            } elseif ($comp === 'rank_feed') {
                $style = isset($props['style']) ? (string) $props['style'] : 'panel';
                $inner = $style === 'inline' ? 'rank_inline' : 'rank_list';
                $this->registerComponent($inner, $components, $peerStyles);
            } elseif ($comp === 'rank_card') {
                $this->registerComponent('rank_card', $components, $peerStyles);
            } else {
                $this->registerComponent($comp, $components, $peerStyles);
            }

            if ($comp === 'actor_detail' || $comp === 'role_detail') {
                $this->registerComponent('vod_list', $components, $peerStyles);
            }

            // member_shell 模板内硬 include 侧栏/菜单组件，须一并加载样式
            if ($comp === 'member_shell') {
                $this->registerComponent('member_nav', $components, $peerStyles);
                $this->registerComponent('member_hub_menu', $components, $peerStyles);
                $this->registerComponent('member_profile_card', $components, $peerStyles);
            }
            if ($comp === 'np2_play_shell') {
                $this->registerComponent('vod_player', $components, $peerStyles);
            }

            // list/filter 等模板内 {% include %} 的隐式依赖（paging、empty_state、filter_panel…）
            // 以前只同步文件到主题，编译 head 时漏掉 CSS/JS，预览里分页会退回浏览器默认蓝链样式。
            foreach ($this->kit->componentDependencyIds($comp, $props) as $dep) {
                $this->registerComponent($dep, $components, $peerStyles);
            }

            if (isset($b['slots']) && is_array($b['slots'])) {
                foreach ($b['slots'] as $children) {
                    if (is_array($children)) {
                        $this->walkAssets($children, $components, $peerStyles);
                    }
                }
            }
        }
    }

    private function registerComponent($comp, &$components, &$peerStyles)
    {
        $comp = (string) $comp;
        if ($comp === '') {
            return;
        }
        if (!isset($components[$comp])) {
            $components[$comp] = $comp;
            $meta = isset($this->componentMeta[$comp]) ? $this->componentMeta[$comp] : array();
            if (isset($meta['peer_styles']) && is_array($meta['peer_styles'])) {
                foreach ($meta['peer_styles'] as $ps) {
                    $ps = (string) $ps;
                    if ($ps !== '') {
                        $peerStyles[$ps] = $ps;
                    }
                }
            }
        }
    }

    private function needsBannerScript(array $blocks)
    {
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $c = (string) $b['component'];
            if ($c === 'vod_carousel' || $c === 'carousel-filmstrip' || $c === 'carousel-fullbleed') {
                return true;
            }
            if (isset($b['slots']) && is_array($b['slots'])) {
                foreach ($b['slots'] as $children) {
                    if (is_array($children) && $this->needsBannerScript($children)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function bindToListComponent($bind)
    {
        $map = array(
            'vod' => 'vod_list',
            'manga' => 'manga_list',
            'art' => 'art_list',
            'topic' => 'topic_list',
            'live' => 'live_list',
            'website' => 'website_list',
            'gbook' => 'gbook_list',
            'actor' => 'actor_list',
            'role' => 'role_list',
        );
        $bind = (string) $bind;
        return isset($map[$bind]) ? $map[$bind] : 'vod_list';
    }

    /**
     * section 已渲染 sec_head 时，压制自带标题的子块（rank_list / rank_card），避免双标题。
     *
     * @param array $blocks
     * @return array
     */
    private function markNestedSelfTitleHidden(array $blocks)
    {
        $selfTitleComps = array(
            'rank_list',
            'rank_card',
            'rank_feed',
            'gbook_list',
        );
        $out = array();
        foreach ($blocks as $b) {
            if (!is_array($b)) {
                $out[] = $b;
                continue;
            }
            $comp = isset($b['component']) ? (string) $b['component'] : '';
            if ($comp !== '' && in_array($comp, $selfTitleComps, true)) {
                if (!isset($b['props']) || !is_array($b['props'])) {
                    $b['props'] = array();
                }
                $b['props']['hide_title'] = true;
            }
            $out[] = $b;
        }
        return $out;
    }

    private function sectionIcon($icon)
    {
        $icon = (string) $icon;
        if ($icon === 'fire') {
            return array('name' => 'star', 'mod' => 'hot');
        }
        if ($icon === 'book') {
            return array('name' => 'channel-manga', 'mod' => 'art');
        }
        if ($icon === 'collection') {
            return array('name' => 'channel-topic', 'mod' => 'c2');
        }
        if ($icon === 'bar-chart') {
            return array('name' => 'channel-rank', 'mod' => 'hot');
        }
        if ($icon === 'newspaper') {
            return array('name' => 'channel-gbook', 'mod' => 'art');
        }
        if ($icon === 'broadcast') {
            return array('name' => 'channel-live', 'mod' => 'hot');
        }
        return array('name' => 'film', 'mod' => 'type');
    }

    private function resolveChrome(array $page)
    {
        $chrome = isset($page['chrome']) && is_array($page['chrome']) ? $page['chrome'] : array();
        $opts = isset($page['chrome_opts']) && is_array($page['chrome_opts']) ? $page['chrome_opts'] : array();
        return $this->kit->normalizeChrome($chrome, array(), $opts);
    }

    private function resolveFooter(array $page)
    {
        $footer = isset($page['footer']) && is_array($page['footer']) ? $page['footer'] : array();
        return $this->kit->normalizeFooter($footer);
    }

    private function compileFooterInclude(array $footerBlock, $path)
    {
        $props = isset($footerBlock['props']) && is_array($footerBlock['props']) ? $footerBlock['props'] : array();
        $inner = $this->compileLeafInclude('site_footer', $props, 0);
        if ($this->previewMarkers) {
            return $this->wrapPreviewHost($inner, 'site_footer', (string) $path, 0);
        }
        return $inner;
    }

    private function compileChromeInclude(array $chrome, $componentId, $depth, $markerPath = '')
    {
        foreach ($chrome as $idx => $block) {
            if (!is_array($block) || empty($block['component'])) {
                continue;
            }
            if ((string) $block['component'] !== $componentId) {
                continue;
            }
            $props = isset($block['props']) && is_array($block['props']) ? $block['props'] : array();
            $inner = $this->compileLeafInclude($componentId, $props, $depth);
            if ($this->previewMarkers) {
                $path = $markerPath !== '' ? (string) $markerPath : (string) $idx;
                return $this->wrapPreviewHost($inner, $componentId, $path, $depth);
            }
            return $inner;
        }
        return '';
    }

    private function loadKitComponentMeta()
    {
        $componentDirs = $this->kit->discoverKitComponentDirs();
        if ($componentDirs === array()) {
            return;
        }
        foreach ($componentDirs as $id => $path) {
            $file = $path . '/component.json';
            if (!is_file($file)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                continue;
            }
            $arr = json_decode($raw, true);
            if (is_array($arr)) {
                $this->componentMeta[$id] = $arr;
            }
        }
    }

    private function twigString($value)
    {
        $value = str_replace('\\', '\\\\', (string) $value);
        $value = str_replace("'", "\\'", $value);
        return "'" . $value . "'";
    }

    private function twigInlineArray(array $list)
    {
        $rows = array();
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cells = array();
            foreach ($row as $ck => $cv) {
                if (is_int($cv) || is_float($cv)) {
                    $cells[] = $ck . ': ' . $cv;
                } elseif (is_bool($cv)) {
                    $cells[] = $ck . ': ' . ($cv ? 'true' : 'false');
                } else {
                    $cells[] = $ck . ': ' . $this->twigString((string) $cv);
                }
            }
            if (!empty($cells)) {
                $rows[] = '{ ' . implode(', ', $cells) . ' }';
            }
        }
        return '[' . implode(', ', $rows) . ']';
    }

    private function twigHash(array $arr)
    {
        if (empty($arr)) {
            return '{}';
        }
        $parts = array();
        foreach ($arr as $k => $v) {
            if (!is_string($k) && !is_int($k)) {
                continue;
            }
            if (is_array($v)) {
                if ($k === 'type_ids') {
                    $ids = array();
                    foreach ($v as $id) {
                        $id = (int) $id;
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                    $parts[] = $k . ': [' . implode(', ', $ids) . ']';
                } elseif ($k === 'items' || $k === 'rails') {
                    $parts[] = $k . ': ' . $this->twigInlineArray($v);
                }
                continue;
            }
            if (is_bool($v)) {
                $parts[] = $k . ': ' . ($v ? 'true' : 'false');
            } elseif ($k === 'height' && is_numeric($v)) {
                $parts[] = $k . ': ' . (int) $v;
            } elseif (is_int($v) || is_float($v)) {
                $parts[] = $k . ': ' . $v;
            } else {
                $parts[] = $k . ': ' . $this->twigString((string) $v);
            }
        }
        return '{ ' . implode(', ', $parts) . ' }';
    }

    private function escapeAttr($s)
    {
        return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s);
    }

    private function bannerScriptContent()
    {
        return "<script>\n"
            . "(function () {\n"
            . "  function initBanner(root) {\n"
            . "    if (!root) return;\n"
            . "    var slides = root.querySelectorAll('.bili-feed__slide, .pb-cf__slide');\n"
            . "    var dots = root.querySelectorAll('.bili-feed__dot, .pb-cf__dot');\n"
            . "    var btns = root.querySelectorAll('.bili-feed__btn, .pb-cf__btn');\n"
            . "    if (!slides.length) return;\n"
            . "    var i = 0, timer = null, n = slides.length;\n"
            . "    function go(to) {\n"
            . "      i = (to + n) % n;\n"
            . "      slides.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });\n"
            . "      dots.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });\n"
            . "    }\n"
            . "    function next(d) { go(i + (d || 1)); }\n"
            . "    function arm() {\n"
            . "      clearInterval(timer);\n"
            . "      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;\n"
            . "      timer = setInterval(function () { next(1); }, 4500);\n"
            . "    }\n"
            . "    dots.forEach(function (d) {\n"
            . "      d.addEventListener('click', function () {\n"
            . "        go(parseInt(d.getAttribute('data-i'), 10) || 0);\n"
            . "        arm();\n"
            . "      });\n"
            . "    });\n"
            . "    btns.forEach(function (b) {\n"
            . "      b.addEventListener('click', function () {\n"
            . "        next(parseInt(b.getAttribute('data-dir'), 10) || 1);\n"
            . "        arm();\n"
            . "      });\n"
            . "    });\n"
            . "    root.addEventListener('mouseenter', function () { clearInterval(timer); });\n"
            . "    root.addEventListener('mouseleave', arm);\n"
            . "    arm();\n"
            . "  }\n"
            . "  document.addEventListener('DOMContentLoaded', function () {\n"
            . "    document.querySelectorAll('[data-bili-banner], [data-pb-vc-root], [data-pb-carousel-fullbleed]').forEach(function (root) {\n"
            . "      var inner = root.matches('[data-bili-banner]') ? root : root.querySelector('[data-pb-cfb-root]') || root;\n"
            . "      initBanner(inner);\n"
            . "    });\n"
            . "  });\n"
            . "})();\n"
            . "</script>\n";
    }

    /**
     * @param mixed $val
     * @return string
     */
    private function exportTwigSetLiteral($val)
    {
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }

        return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $val) . "'";
    }
}
