<?php
namespace app\common\util;

/**
 * 统一首页 Page Builder 组件库（template/_shared/kit/）。
 *
 * v2：components/{category}/{id}/ 整包（template.twig + style.css + script.js + component.json）
 * 发布时同步到 template/{theme}/page-builder-components/{id}/（主题侧仍扁平）
 */
class PageBuilderKit
{
    /** 规范路径：template/_shared/kit */
    const KIT_DIR = '_shared/kit';
    const VERSION_FILE = '.page-builder-kit-version';
    const THEME_COMPONENTS_DIR = 'page-builder-components';

    /** @var string */
    private $rootPath;

    /**
     * 当前 syncToTheme 调用的主题保护配置（null=未加载）。
     * @var array|null
     */
    private $syncProtect = null;

    public function __construct($rootPath = null)
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? ROOT_PATH : '';
        }
        $this->rootPath = rtrim((string) $rootPath, '/') . '/';
    }

    public function kitDir()
    {
        return $this->rootPath . 'template/' . self::KIT_DIR . '/';
    }

    public function kitComponentsDir()
    {
        return $this->kitDir() . 'components/';
    }

    public function themeComponentsDir($themeDir)
    {
        return rtrim((string) $themeDir, '/') . '/' . self::THEME_COMPONENTS_DIR . '/';
    }

    public function kitMeta()
    {
        $file = $this->kitDir() . 'kit.json';
        if (!is_file($file)) {
            return array('version' => 0);
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return array('version' => 0);
        }
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : array('version' => 0);
    }

    public function kitVersion()
    {
        $meta = $this->kitMeta();
        return isset($meta['version']) ? (int) $meta['version'] : 0;
    }

    /**
     * 扫描 kit 内组件目录，返回 component id => 源目录绝对路径。
     * 支持 components/{category}/{id}/ 与遗留的 components/{id}/ 扁平结构。
     *
     * @return array<string, string>
     */
    public function discoverKitComponentDirs()
    {
        $dir = $this->kitComponentsDir();
        if (!is_dir($dir)) {
            return array();
        }
        $out = array();
        $patterns = array('*/*/component.json', '*/component.json');
        foreach ($patterns as $pattern) {
            $files = glob($dir . $pattern);
            if (!is_array($files)) {
                continue;
            }
            foreach ($files as $jsonFile) {
                $compDir = dirname($jsonFile);
                $meta = $this->readJsonFile($jsonFile);
                $id = (is_array($meta) && !empty($meta['id']))
                    ? (string) $meta['id']
                    : basename($compDir);
                if ($id === '' || isset($out[$id])) {
                    continue;
                }
                $out[$id] = $compDir;
            }
        }
        return $out;
    }

    /**
     * kit/components 下分类目录顺序（与物理目录一致）。
     *
     * @return string[]
     */
    public function kitCategoryOrder()
    {
        $dir = $this->kitComponentsDir();
        if (!is_dir($dir)) {
            return array();
        }
        $preferred = array('carousel', 'chrome', 'detail', 'layout', 'list', 'play', 'rank');
        $found = array();
        foreach (glob($dir . '*', GLOB_ONLYDIR) ?: array() as $catDir) {
            $catId = basename($catDir);
            if ($catId === '' || $catId[0] === '.' || $catId === 'pb_responsive') {
                continue;
            }
            $hasComponent = false;
            foreach (glob($catDir . '/*', GLOB_ONLYDIR) ?: array() as $compDir) {
                if (is_file($compDir . '/component.json')) {
                    $hasComponent = true;
                    break;
                }
            }
            if ($hasComponent) {
                $found[] = $catId;
            }
        }
        $out = array();
        foreach ($preferred as $catId) {
            if (in_array($catId, $found, true)) {
                $out[] = $catId;
            }
        }
        foreach ($found as $catId) {
            if (!in_array($catId, $out, true)) {
                $out[] = $catId;
            }
        }
        return $out;
    }

    /**
     * 从组件源目录路径解析分类 id（components/{category}/{id}/）。
     *
     * @param string     $compDir
     * @param array|null $meta
     * @return string
     */
    private function kitComponentCategoryId($compDir, $meta = null)
    {
        $componentsDir = rtrim($this->kitComponentsDir(), '/') . '/';
        $rel = str_replace($componentsDir, '', rtrim((string) $compDir, '/') . '/');
        $parts = array_values(array_filter(explode('/', trim($rel, '/'))));
        if (count($parts) >= 2) {
            return (string) $parts[0];
        }
        if (is_array($meta) && !empty($meta['category'])) {
            return (string) $meta['category'];
        }
        return 'layout';
    }

    /** kit 内已定义的打包组件 id 列表 */
    public function packagedComponentIds()
    {
        return array_keys($this->discoverKitComponentDirs());
    }

    /** 允许的 component 名（manifest + 打包组件 + 遗留） */
    public function componentNames()
    {
        $file = $this->kitDir() . 'manifest.json';
        $legacy = array();
        if (is_file($file)) {
            $arr = $this->readJsonFile($file);
            if (is_array($arr) && isset($arr['components']) && is_array($arr['components'])) {
                foreach ($arr['components'] as $name) {
                    $name = (string) $name;
                    if ($name !== '') {
                        $legacy[] = $name;
                    }
                }
            }
        }
        $packaged = $this->packagedComponentIds();
        $merged = array_merge($packaged, $legacy);
        $seen = array();
        $out = array();
        foreach ($merged as $name) {
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $out[] = $name;
            }
        }
        return $out;
    }

    /**
     * 版式包单页种子（如 art/index）；无文件时返回空数组。
     *
     * @param string $route
     * @param string $presetId
     * @return array
     */
    public function defaultRoutePage($route, $presetId = '')
    {
        if (!class_exists('\app\common\util\ThemePreset')) {
            return array();
        }
        $file = \app\common\util\ThemePreset::routeSeedFile($route, $presetId);
        if ($file === '' || !is_file($file)) {
            return array();
        }
        $arr = $this->readJsonFile($file);
        if (!is_array($arr)) {
            return array();
        }
        $out = array(
            'template' => isset($arr['template']) ? (string) $arr['template'] : (string) $route,
            'layout' => isset($arr['layout']) ? (string) $arr['layout'] : 'base',
        );
        if (!empty($arr['page_type'])) {
            $out['page_type'] = (string) $arr['page_type'];
        }
        if (!empty($arr['layout_mod'])) {
            $out['layout_mod'] = (string) $arr['layout_mod'];
        }
        if (!empty($arr['regions']) && is_array($arr['regions'])) {
            $out['regions'] = $arr['regions'];
        }
        if (isset($arr['layout_compile']) && is_array($arr['layout_compile'])) {
            $out['layout_compile'] = $arr['layout_compile'];
        }
        if (isset($arr['twig_sets']) && is_array($arr['twig_sets'])) {
            $out['twig_sets'] = $arr['twig_sets'];
        }
        if (!empty($arr['extra_page_class'])) {
            $out['extra_page_class'] = (string) $arr['extra_page_class'];
        }
        return $out;
    }

    public function defaultPage($presetId = '')
    {
        $empty = array(
            'template' => 'index/index',
            'layout' => 'base',
            'chrome' => array(),
            'chrome_opts' => array(),
            'blocks' => array(),
        );
        $file = $this->kitDir() . 'presets/default-homepage.json';
        if (class_exists('\app\common\util\ThemePreset')) {
            $seed = \app\common\util\ThemePreset::homepageSeedFile($presetId);
            if ($seed !== '' && is_file($seed)) {
                $file = $seed;
            }
        }
        if (!is_file($file)) {
            return $empty;
        }
        $arr = $this->readJsonFile($file);
        if (!is_array($arr)) {
            return $empty;
        }
        $out = array(
            'template' => isset($arr['template']) ? (string) $arr['template'] : 'index/index',
            'layout' => isset($arr['layout']) ? (string) $arr['layout'] : 'base',
            'chrome' => isset($arr['chrome']) && is_array($arr['chrome']) ? $arr['chrome'] : array(),
            'chrome_opts' => isset($arr['chrome_opts']) && is_array($arr['chrome_opts']) ? $arr['chrome_opts'] : array(),
            'blocks' => isset($arr['blocks']) && is_array($arr['blocks']) ? $arr['blocks'] : array(),
        );
        if (!empty($arr['regions']) && is_array($arr['regions'])) {
            $out['regions'] = $arr['regions'];
        }
        if (!empty($arr['layout_mod'])) {
            $out['layout_mod'] = (string) $arr['layout_mod'];
        }
        if (!empty($arr['page_type'])) {
            $out['page_type'] = (string) $arr['page_type'];
        }
        if (isset($arr['layout_compile']) && is_array($arr['layout_compile'])) {
            $out['layout_compile'] = $arr['layout_compile'];
        }
        if (isset($arr['twig_sets']) && is_array($arr['twig_sets'])) {
            $out['twig_sets'] = $arr['twig_sets'];
        }
        if (!empty($arr['extra_page_class'])) {
            $out['extra_page_class'] = (string) $arr['extra_page_class'];
        }
        return $out;
    }

    /**
     * 读取当前模板 html/index/index.json 作为首页 page 默认定义。
     *
     * @param string|null $templateDir 主题目录名，空则读站点 template_dir
     * @return array{template:string,layout:string,blocks:array}
     */
    public function defaultHomepagePage($templateDir = null)
    {
        $empty = array(
            'template' => 'index/index',
            'layout' => 'base',
            'blocks' => array(),
        );
        $page = $this->defaultPage();
        if (!empty($page['blocks'])) {
            return array(
                'template' => isset($page['template']) ? (string) $page['template'] : 'index/index',
                'layout' => isset($page['layout']) ? (string) $page['layout'] : 'base',
                'blocks' => $page['blocks'],
            );
        }
        if ($templateDir === null || $templateDir === '') {
            $templateDir = isset($GLOBALS['config']['site']['template_dir'])
                ? (string) $GLOBALS['config']['site']['template_dir'] : '';
        }
        if ($templateDir === '' || !defined('ROOT_PATH')) {
            return $empty;
        }
        $file = $this->rootPath . 'template/' . $templateDir . '/html/index/index.json';
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
     * 当前激活主题是否已同步某打包组件。
     *
     * @param string $componentId
     * @return bool
     */
    public function isPackagedInActiveTheme($componentId)
    {
        $dir = ThemePreviewRuntime::themeTemplateDir();
        if ($dir === '') {
            return false;
        }

        return $this->isPackagedInTheme($dir, $componentId);
    }

    /**
     * 扫描 blocks，收集当前激活主题上的组件与 peer 样式。
     *
     * @param array $blocks
     * @return array{components:array,peer_styles:array}
     */
    public function collectBlockAssetsForActiveTheme($blocks)
    {
        $dir = ThemePreviewRuntime::themeTemplateDir();
        if ($dir === '') {
            return array('components' => array(), 'peer_styles' => array());
        }

        return $this->collectBlockAssets($dir, is_array($blocks) ? $blocks : array());
    }

    public function palette($lang)
    {
        $file = $this->kitDir() . 'presets/palette.json';
        if (!is_file($file)) {
            return array();
        }
        $data = $this->readJsonFile($file);
        if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])) {
            return array();
        }

        $out = array();
        foreach ($data['categories'] as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $catId = isset($cat['id']) ? (string) $cat['id'] : '';
            $catLabelKey = isset($cat['label_key']) ? (string) $cat['label_key'] : '';
            $catLabel = $catLabelKey !== '' ? (string) $lang($catLabelKey) : $catId;
            $presets = isset($cat['presets']) && is_array($cat['presets']) ? $cat['presets'] : array();
            $blocks = array();
            foreach ($presets as $preset) {
                if (!is_array($preset) || empty($preset['id']) || !isset($preset['block'])) {
                    continue;
                }
                $labelKey = isset($preset['label_key']) ? (string) $preset['label_key'] : '';
                $label = $labelKey !== '' ? (string) $lang($labelKey) : (string) $preset['id'];
                $kind = isset($preset['kind']) ? (string) $preset['kind'] : '';
                $block = $this->hydrateBlockLangKeys($preset['block'], $lang);
                $blocks[] = array(
                    'id' => (string) $preset['id'],
                    'label' => $label,
                    'kind' => $kind,
                    'block' => $block,
                );
            }
            if (empty($blocks)) {
                continue;
            }
            $out[] = array(
                'id' => $catId,
                'label' => $catLabel,
                'blocks' => $blocks,
            );
        }
        return $out;
    }

    /**
     * 左侧组件库：扫描 kit/components 下各组件的 component.json。
     * @param callable $lang
     * @return array
     */
    public function componentPalette($lang)
    {
        $catLabels = array(
            'carousel' => 'admin/themedesign/pb_cat_carousel',
            'chrome' => 'admin/themedesign/pb_cat_chrome',
            'detail' => 'admin/themedesign/pb_cat_detail',
            'layout' => 'admin/themedesign/pb_cat_layout',
            'list' => 'admin/themedesign/pb_cat_list',
            'play' => 'admin/themedesign/pb_cat_play',
            'rank' => 'admin/themedesign/pb_cat_rank',
        );
        $catOrder = $this->kitCategoryOrder();
        $cats = array();
        foreach ($catOrder as $catId) {
            $labelKey = isset($catLabels[$catId]) ? $catLabels[$catId] : '';
            $cats[$catId] = array(
                'id' => $catId,
                'label' => $labelKey !== '' ? (string) $lang($labelKey) : $catId,
                'blocks' => array(),
            );
        }

        $componentDirs = $this->discoverKitComponentDirs();
        if ($componentDirs === array()) {
            return array();
        }
        foreach ($componentDirs as $id => $path) {
            if ($id === '' || !is_file($path . '/component.json')) {
                continue;
            }
            $meta = $this->readJsonFile($path . '/component.json');
            if (!is_array($meta)) {
                continue;
            }
            if (isset($meta['palette']) && $meta['palette'] === false) {
                continue;
            }
            $selectOnly = false;
            $catId = $this->kitComponentCategoryId($path, $meta);
            if (!isset($cats[$catId])) {
                $labelKey = isset($catLabels[$catId]) ? $catLabels[$catId] : '';
                $cats[$catId] = array(
                    'id' => $catId,
                    'label' => $labelKey !== '' ? (string) $lang($labelKey) : $catId,
                    'blocks' => array(),
                );
                if (!in_array($catId, $catOrder, true)) {
                    $catOrder[] = $catId;
                }
            }
            $labelKey = isset($meta['label_key']) ? (string) $meta['label_key'] : '';
            $label = $labelKey !== '' ? (string) $lang($labelKey) : $id;
            $kind = isset($meta['kind']) ? (string) $meta['kind'] : $id;
            if ($id === 'filmstrip_layout') {
                $blockDef = array(
                    'component' => 'filmstrip_layout',
                    'slots' => array(
                        'banner' => array(
                            array(
                                'component' => 'vod_carousel',
                                'props' => $this->defaultPropsForComponent('vod_carousel'),
                            ),
                        ),
                        'feed' => array(
                            array(
                                'component' => 'vod_list',
                                'props' => array_merge(
                                    $this->defaultPropsForComponent('vod_list'),
                                    array(
                                        'render' => 'filmstrip',
                                        'by' => 'hits_day',
                                        'num' => 12,
                                        'card' => 'poster-h',
                                        'ratio' => '69-40',
                                    )
                                ),
                            ),
                        ),
                    ),
                );
            } elseif ($id === 'columns') {
                $blockDef = $this->defaultColumnsPaletteBlock();
            } else {
                $blockDef = array(
                    'component' => $id,
                    'props' => $this->defaultPropsForComponent($id),
                );
            }
            $paletteBlock = array(
                'id' => 'comp_' . $id,
                'label' => $label,
                'kind' => $kind,
                'block' => $blockDef,
            );
            if ($selectOnly) {
                $paletteBlock['select_only'] = true;
            }
            $cats[$catId]['blocks'][] = $paletteBlock;
        }

        $out = array();
        foreach ($catOrder as $catId) {
            if (!empty($cats[$catId]['blocks'])) {
                $out[] = $cats[$catId];
            }
        }
        foreach ($cats as $catId => $cat) {
            if (in_array($catId, $catOrder, true)) {
                continue;
            }
            if (!empty($cat['blocks'])) {
                $out[] = $cat;
            }
        }
        return $out;
    }

    /**
     * 按 page_type + 内容模块过滤左侧组件面板。
     * @param string   $pageType home|list|detail|play|read|member|rank|gbook|map|gate|special
     * @param callable $lang
     * @param string   $module  路由模块 vod|manga|art|...
     * @return array
     */
    public function paletteForPageType($pageType, $lang, $module = '')
    {
        $pageType = strtolower(trim((string) $pageType));
        $module = strtolower(trim((string) $module));
        $full = $this->componentPalette($lang);
        $allowed = $this->allowedComponentsForPageType($pageType, $module);
        if ($allowed === null) {
            return $full;
        }
        $allowedMap = array_flip($allowed);
        $out = array();
        foreach ($full as $cat) {
            if (!is_array($cat) || empty($cat['blocks'])) {
                continue;
            }
            $blocks = array();
            foreach ($cat['blocks'] as $b) {
                if (!is_array($b) || empty($b['block']['component'])) {
                    continue;
                }
                $compId = (string) $b['block']['component'];
                if (isset($allowedMap[$compId])) {
                    $blocks[] = $b;
                }
            }
            if (!empty($blocks)) {
                $row = $cat;
                $row['blocks'] = $blocks;
                $out[] = $row;
            }
        }

        $present = array();
        foreach ($out as $cat) {
            if (empty($cat['blocks']) || !is_array($cat['blocks'])) {
                continue;
            }
            foreach ($cat['blocks'] as $b) {
                if (is_array($b) && !empty($b['block']['component'])) {
                    $present[(string) $b['block']['component']] = true;
                }
            }
        }
        $missing = array();
        foreach ($allowed as $compId) {
            $compId = (string) $compId;
            if ($compId !== '' && !isset($present[$compId])) {
                $missing[] = $compId;
            }
        }
        if (!empty($missing)) {
            $out = $this->injectPaletteComponents($out, $missing, $lang);
        }

        // 首页频道条属 chrome「频道」区：不可拖入正文，但需在组件库提供「选中编辑」入口
        if ($pageType === 'home') {
            $out = $this->injectSelectOnlyPaletteBlocks($out, array('channel_strip'), $lang);
        }

        return $out;
    }

    /**
     * 注入仅选中、不可拖拽的 chrome 块（如首页频道条）。
     *
     * @param array    $palette
     * @param string[] $componentIds
     * @param callable $lang
     * @return array
     */
    private function injectSelectOnlyPaletteBlocks(array $palette, array $componentIds, $lang)
    {
        $palette = $this->injectPaletteComponents($palette, $componentIds, $lang);
        $want = array_flip(array_map('strval', $componentIds));
        foreach ($palette as $ci => $cat) {
            if (empty($cat['blocks']) || !is_array($cat['blocks'])) {
                continue;
            }
            foreach ($cat['blocks'] as $bi => $b) {
                if (!is_array($b) || empty($b['block']['component'])) {
                    continue;
                }
                $compId = (string) $b['block']['component'];
                if (isset($want[$compId])) {
                    $palette[$ci]['blocks'][$bi]['select_only'] = true;
                }
            }
        }
        return $palette;
    }

    /**
     * 注入 palette:false 但当前路由允许的组件（如播放页播放器）。
     *
     * @param array    $palette
     * @param string[] $componentIds
     * @param callable $lang
     * @return array
     */
    private function injectPaletteComponents(array $palette, array $componentIds, $lang)
    {
        $componentIds = array_values(array_unique(array_filter(array_map('strval', $componentIds))));
        if ($componentIds === array()) {
            return $palette;
        }

        $catLabels = array(
            'carousel' => 'admin/themedesign/pb_cat_carousel',
            'chrome' => 'admin/themedesign/pb_cat_chrome',
            'detail' => 'admin/themedesign/pb_cat_detail',
            'layout' => 'admin/themedesign/pb_cat_layout',
            'list' => 'admin/themedesign/pb_cat_list',
            'play' => 'admin/themedesign/pb_cat_play',
            'rank' => 'admin/themedesign/pb_cat_rank',
            'member' => 'admin/themedesign/pb_cat_member',
        );
        $catOrder = $this->kitCategoryOrder();
        $byCat = array();
        foreach ($palette as $cat) {
            if (!is_array($cat) || empty($cat['id'])) {
                continue;
            }
            $byCat[(string) $cat['id']] = $cat;
        }

        $dirs = $this->discoverKitComponentDirs();
        foreach ($componentIds as $compId) {
            if (!isset($dirs[$compId]) || !is_file($dirs[$compId] . '/component.json')) {
                continue;
            }
            $meta = $this->readJsonFile($dirs[$compId] . '/component.json');
            if (!is_array($meta)) {
                continue;
            }
            // 分页/筛选等 palette:false 的内置依赖不进可拖面板；播放器等可设 palette_inject
            if (isset($meta['palette']) && $meta['palette'] === false
                && empty($meta['palette_inject'])) {
                continue;
            }
            $catId = $this->kitComponentCategoryId($dirs[$compId], $meta);
            $labelKey = isset($meta['label_key']) ? (string) $meta['label_key'] : '';
            $label = $labelKey !== '' ? (string) $lang($labelKey) : $compId;
            $kind = isset($meta['kind']) ? (string) $meta['kind'] : $compId;
            $entry = array(
                'id' => 'comp_' . $compId,
                'label' => $label,
                'kind' => $kind,
                'block' => array(
                    'component' => $compId,
                    'props' => $this->defaultPropsForComponent($compId),
                ),
            );
            if (!isset($byCat[$catId])) {
                $catLabelKey = isset($catLabels[$catId]) ? $catLabels[$catId] : '';
                $byCat[$catId] = array(
                    'id' => $catId,
                    'label' => $catLabelKey !== '' ? (string) $lang($catLabelKey) : $catId,
                    'blocks' => array(),
                );
                if (!in_array($catId, $catOrder, true)) {
                    $catOrder[] = $catId;
                }
            }
            $byCat[$catId]['blocks'][] = $entry;
        }

        $out = array();
        foreach ($catOrder as $catId) {
            if (!empty($byCat[$catId]['blocks'])) {
                $out[] = $byCat[$catId];
            }
        }
        foreach ($byCat as $catId => $cat) {
            if (in_array($catId, $catOrder, true)) {
                continue;
            }
            if (!empty($cat['blocks'])) {
                $out[] = $cat;
            }
        }
        return $out;
    }

    /**
     * null = 不过滤（首页全量）；否则返回允许的 component id 列表。
     * @param string $pageType
     * @param string $module 路由模块 vod|manga|art|...
     * @return array|null
     */
    public function allowedComponentsForPageType($pageType, $module = '')
    {
        $pageType = strtolower(trim((string) $pageType));
        $module = strtolower(trim((string) $module));
        $chrome = array('site_nav', 'site_footer', 'bottom_nav', 'header_decor', 'channel_strip', 'ads_grid', 'friend_links');
        $layout = array('section', 'columns', 'grid', 'spacer', 'sec_heading');
        $carousel = array('filmstrip_layout', 'vod_carousel', 'carousel-compact', 'carousel-fullbleed');
        $list = array(
            'content_feed', 'rank_feed', 'vod_list', 'art_list', 'manga_list', 'topic_list',
            'live_list', 'website_list', 'actor_list', 'role_list', 'gbook_list', 'rank_list', 'rank_inline', 'rank_card',
            'breadcrumb', 'filter_chips',
        );
        $play = array('vod_player', 'live_player', 'manga_player', 'novel_player', 'novel_chapter_catalog', 'popedom_gate');
        $member = array('section', 'spacer');

        switch ($pageType) {
            case 'home':
                // 首页仅展示能在 index/index 正常渲染的块；面包屑/频道条/小说 dock 等不在 content 面板列出
                return array_merge($layout, $carousel, array(
                    'ads_grid',
                    'content_feed', 'rank_feed', 'rank_list', 'rank_inline', 'rank_card',
                    'filter_chips',
                    'friend_links',
                ));
            case 'list':
                // 漫画/小说首页专用块按 module 注入，不再塞进所有 list 页
                $listAllowed = array_merge($chrome, $layout, $list);
                if ($module === 'manga') {
                    $listAllowed = array_merge($listAllowed, array(
                        'manga_mosaic_hero', 'manga_genre_nav', 'manga_home_spotlight',
                        'manga_home_row', 'manga_home_rank',
                    ));
                } elseif ($module === 'art') {
                    $listAllowed = array_merge($listAllowed, array(
                        'novel_home_banner', 'novel_genre_nav', 'novel_home_dock', 'novel_home_rank',
                        'novel_home_grid', 'novel_home_row', 'novel_home_zone',
                    ));
                }
                return $this->narrowAllowedByModule($listAllowed, $module, $pageType);
            case 'detail':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, array(
                    'vod_detail',
                    'art_detail',
                    'manga_detail',
                    'topic_detail',
                    'actor_detail',
                    'role_detail',
                    'website_detail',
                    'content_feed', 'rank_list', 'rank_inline', 'rank_card', 'comment_block',
                    'cinema_detail_stage', 'media_intro_panel', 'media_related_panel',
                    'detail_intro_block', 'dt_header', 'vod_cast_panel',
                )), $module, $pageType);
            case 'play':
            case 'read':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, $play, array('content_feed', 'rank_list', 'comment_block')), $module, $pageType);
            case 'member':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, $member, array('section', 'grid', 'spacer')), $module, $pageType);
            case 'rank':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, array('rank_board', 'rank_list', 'rank_card', 'rank_inline', 'rank_theme_boards', 'section')), $module, $pageType);
            case 'gbook':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, array('gbook_form', 'gbook_list', 'section')), $module, $pageType);
            case 'map':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, array('map_filters', 'map_update_row', 'section')), $module, $pageType);
            case 'gate':
                return $this->narrowAllowedByModule(array_merge($chrome, array('section', 'spacer', 'popedom_gate')), $module, $pageType);
            case 'special':
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, array('dt_header', 'detail_intro_block', 'content_feed', 'section')), $module, $pageType);
            default:
                return $this->narrowAllowedByModule(array_merge($chrome, $layout, $list), $module, $pageType);
        }
    }


    /**
     * 按内容模块收窄组件白名单：漫画页只留漫画相关，小说/视频同理。
     * 共用布局/站点壳/筛选分页/排行仍保留。
     *
     * @param array  $allowed
     * @param string $module
     * @param string $pageType
     * @return array
     */
    private function narrowAllowedByModule(array $allowed, $module, $pageType)
    {
        $module = strtolower(trim((string) $module));
        $pageType = strtolower(trim((string) $pageType));
        if ($module === '' || $module === 'index' || $pageType === 'home') {
            return array_values(array_unique($allowed));
        }

        $shared = array(
            'site_nav', 'site_footer', 'bottom_nav', 'header_decor', 'channel_strip', 'ads_grid', 'friend_links',
            'section', 'columns', 'grid', 'spacer', 'sec_heading',
            'breadcrumb', 'filter_chips',
            'content_feed',
            'rank_feed', 'rank_list', 'rank_inline', 'rank_card', 'rank_board', 'rank_theme_boards',
            'comment_block', 'popedom_gate',
        );

        $familyExact = array(
            'manga' => array(
                'manga_list', 'manga_detail', 'manga_player',
                'manga_mosaic_hero', 'manga_genre_nav', 'manga_home_spotlight',
                'manga_home_row', 'manga_home_rank',
                'media_intro_panel', 'media_related_panel', 'detail_intro_block',
            ),
            'art' => array(
                'art_list', 'art_detail',
                'novel_player', 'novel_chapter_catalog',
                'novel_home_banner', 'novel_genre_nav', 'novel_home_dock', 'novel_home_rank',
                'novel_home_grid', 'novel_home_row', 'novel_home_zone',
                'detail_intro_block', 'dt_header',
            ),
            'vod' => array(
                'vod_list', 'vod_detail', 'vod_player', 'vod_carousel', 'vod_cast_panel',
                'filmstrip_layout', 'carousel-compact', 'carousel-fullbleed',
                'cinema_detail_stage', 'media_intro_panel', 'media_related_panel',
                'detail_intro_block', 'dt_header', 'topic_list',
            ),
            'actor' => array('actor_list', 'actor_detail'),
            'role' => array('role_list', 'role_detail'),
            'website' => array('website_list', 'website_detail'),
            'topic' => array('topic_list', 'topic_detail', 'dt_header', 'detail_intro_block'),
            'special' => array('topic_list', 'topic_detail', 'dt_header', 'detail_intro_block', 'content_feed'),
            'plot' => array('topic_list', 'topic_detail', 'dt_header', 'detail_intro_block'),
            'live' => array('live_list', 'live_player'),
            'label' => array('rank_board', 'rank_list', 'rank_card', 'rank_inline', 'rank_theme_boards'),
            'gbook' => array('gbook_form', 'gbook_list'),
            'map' => array('map_filters', 'map_update_row'),
            'user' => array(),
            'comment' => array('comment_block'),
        );

        if (!isset($familyExact[$module])) {
            return array_values(array_unique($allowed));
        }

        $family = array_flip($familyExact[$module]);
        $sharedMap = array_flip($shared);
        $out = array();
        foreach ($allowed as $id) {
            $id = (string) $id;
            if ($id === '') {
                continue;
            }
            // 只保留：本页已允许的共用块 + 本模块家族块（不额外塞进别的页面类型）
            if (isset($sharedMap[$id]) || isset($family[$id])) {
                $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    /** 双栏布局拖入面板时的默认结构：左右各带一个空分区容器 */
    public function defaultColumnsPaletteBlock()
    {
        // 列槽直接承接内容组件，不再预置空分区
        return array(
            'component' => 'columns',
            'props' => $this->defaultPropsForComponent('columns'),
            'slots' => array(
                '0' => array(),
                '1' => array(),
            ),
        );
    }

    /** 单组件拖入时的默认 props */
    public function defaultPropsForComponent($componentId)
    {
        $componentId = (string) $componentId;
        $map = array(
            'section' => array('variant' => 'plain', 'title' => '', 'more_url' => 'vod/index'),
            'columns' => array('cols' => array(8, 4), 'stack_at' => 720),
            'grid' => array('variant' => 'auto', 'min' => '280px'),
            'spacer' => array('size' => 'md'),
            'sec_heading' => array('title' => '', 'unit' => '条', 'title_lang' => ''),
            'breadcrumb' => array('auto' => true, 'sep' => '›', 'items' => array()),
            'filter_chips' => array('flag' => 'show'),
            'filter_panel' => array('preset' => 'vod_show'),
            'paging' => array(),
            'empty_state' => array('title' => '', 'preset' => '', 'title_lang' => 'string_no_have'),
            'vod_detail' => array('show_intro' => true),
            'art_detail' => array('show_toc' => true, 'show_intro' => true),
            'manga_detail' => array('show_chapters' => true, 'show_intro' => true, 'card' => 'poster-v', 'ratio' => '3-4'),
            'topic_detail' => array('show_intro' => true, 'num' => 0, 'vod_title' => '专题影片', 'vod_title_lang' => 'topic_vod_title', 'art_title' => '相关文章', 'art_title_lang' => 'string_topic_arts'),
            'cinema_detail_stage' => array('auto' => true),
            'media_intro_panel' => array('title' => '剧情简介', 'title_lang' => 'string_vod_synopsis'),
            'media_related_panel' => array('title' => '相关推荐', 'title_lang' => 'string_xgtj', 'num' => 10, 'by' => 'hits', 'relation' => true, 'context' => 'detail', 'bind' => 'vod', 'layout' => 'grid', 'card' => 'poster-h', 'ratio' => 'default'),
            'vod_carousel' => array('bind' => 'vod', 'num' => 6, 'by' => 'hits', 'type' => 0),
            'filmstrip_layout' => array(),
            'carousel-fullbleed' => array('bind' => 'vod', 'num' => 6, 'by' => 'hits', 'type' => 0),
            'carousel-compact' => array('bind' => 'vod', 'num' => 5, 'by' => 'hits_day', 'type' => 0),
            'content_feed' => array('bind' => 'vod', 'render' => 'grid', 'num' => 12, 'by' => 'time', 'type' => 0, 'card' => 'poster-h', 'ratio' => 'default', 'paging' => false, 'pageurl' => 'vod/show', 'relation' => false, 'refresh' => false),
            'rank_feed' => array('style' => 'panel', 'num' => 10, 'type' => 0, 'period' => 'week'),
            'vod_list' => array('num' => 12, 'by' => 'time', 'order' => 'desc', 'type' => 0, 'render' => 'grid', 'card' => 'poster-h', 'ratio' => 'default', 'paging' => false, 'pageurl' => 'vod/show', 'relation' => false),
            'vod_list_np2' => array('title' => '热门推荐', 'title_mode' => 'custom', 'icon' => 'fire', 'layout' => 'grid2', 'num' => 12, 'by' => 'hits', 'order' => 'desc', 'type_pick' => 'all', 'type' => 0, 'top_types_index' => 1, 'show_shuffle' => true, 'show_more' => true),
            'art_list' => array('num' => 8, 'by' => 'time', 'order' => 'desc', 'type' => 0, 'render' => 'grid', 'card' => 'poster-v', 'ratio' => 'default'),
            'manga_list' => array('num' => 10, 'by' => 'time', 'order' => 'desc', 'type' => 0, 'render' => 'grid', 'card' => 'poster-v', 'ratio' => '3-4'),
            'manga_mosaic_hero' => array('bind' => 'manga', 'num' => 24, 'by' => 'hits_week'),
            'manga_genre_nav' => array('num' => 16, 'more_url' => 'manga/show'),
            'manga_home_spotlight' => array('bind' => 'manga', 'num' => 7, 'by' => 'hits_month', 'title' => '为你推荐', 'subtitle' => 'Pick你的最爱！'),
            'manga_home_row' => array('by' => 'hits', 'num' => 10),
            'manga_home_rank' => array('num' => 6),
            'novel_home_banner' => array('bind' => 'art', 'num' => 6, 'by' => 'hits_week'),
            'novel_genre_nav' => array('num' => 16, 'more_url' => 'art/type'),
            'novel_home_dock' => array(),
            'novel_home_rank' => array('num' => 10),
            'novel_home_grid' => array('num' => 9, 'by' => 'hits_month', 'title' => '猜你喜欢', 'subtitle' => '为你精选好书'),
            'novel_home_row' => array('preset' => 'hot', 'num' => 12),
            'novel_home_zone' => array('type_num' => 4, 'row_num' => 10),
            'topic_list' => array('num' => 8, 'by' => 'hits', 'order' => 'desc', 'card' => 'poster-h', 'ratio' => 'default'),
            'live_list' => array('num' => 8, 'card' => 'poster-h', 'ratio' => 'default'),
            'website_list' => array('num' => 8, 'by' => 'hits', 'order' => 'desc', 'type' => 0, 'card' => 'poster-h', 'ratio' => 'default'),
            'gbook_form' => array('show_hint' => true),
            'gbook_list' => array('num' => 20, 'paging' => true),
            'rank_list' => array('num' => 5, 'type' => 0, 'period' => 'week'),
            'rank_inline' => array('num' => 10, 'type' => 0, 'period' => 'week'),
            'rank_card' => array('num' => 5, 'type' => 0, 'title' => ''),
            'site_nav' => array('max' => 8, 'show_search' => true, 'type_ids' => array()),
            'header_decor' => array('width' => '100%', 'height' => 72, 'image' => ''),
            'channel_strip' => array('compact' => false, 'chip_limit' => 11, 'show_icons' => true, 'show_more' => true, 'type_ids' => array()),
            'site_footer' => array('variant' => 'default', 'copyright' => '', 'show_nav' => true, 'show_friends' => true, 'logo' => ''),
            'friend_links' => array('num' => 50),
            'bottom_nav' => array(
                'items' => array(
                    array('id' => 'home', 'label' => '首页', 'href' => '', 'icon' => 'home'),
                    array('id' => 'video', 'label' => '视频', 'href' => '', 'icon' => 'video'),
                    array('id' => 'novel', 'label' => '小说', 'href' => '', 'icon' => 'book'),
                    array('id' => 'manga', 'label' => '漫画', 'href' => '', 'icon' => 'manga'),
                    array('id' => 'me', 'label' => '会员', 'href' => '', 'icon' => 'user'),
                ),
            ),
            'ads_grid' => array(
                'items' => array(
                    array('id' => 'ad_vip', 'label' => '会员福利', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_hot', 'label' => '热播推荐', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_new', 'label' => '新片速递', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_rank', 'label' => '排行榜', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_coin', 'label' => '充值有礼', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_topic', 'label' => '精选专题', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_manga', 'label' => '漫画专区', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_novel', 'label' => '小说专区', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_live', 'label' => '限时活动', 'href' => '', 'cover' => ''),
                    array('id' => 'ad_more', 'label' => '更多精彩', 'href' => '', 'cover' => ''),
                ),
            ),
            'vod_player' => array('show_episodes' => true, 'show_nav' => false),
            'np2_play_shell' => array(),
            'np2_short_feed' => array('num' => 24, 'by' => 'hits', 'order' => 'desc'),
            'novel_player' => array(
                'show_settings' => true,
                'show_share' => true,
                'show_catalog_btn' => true,
                'show_fav' => true,
            ),
            'novel_chapter_catalog' => array(
                'show_chapter_no' => true,
            ),
            'manga_player' => array(),
            'user_member_hub' => array(),
            'user_notify_panel' => array(),
            'user_social_panel' => array(),
            'user_checkin_panel' => array(),
            'user_invite_panel' => array(),
            'user_plog_panel' => array(),
            'user_info_panel' => array(),
            'user_bind_panel' => array(),
            'user_unbind_panel' => array(),
            'user_cash_panel' => array(),
            'user_mall_panel' => array(),
            'member_nav' => array('active' => 'index'),
            'member_profile_card' => array(),
            'member_hub_menu' => array('active' => ''),
            'member_filter_tabs' => array('preset' => 'favs', 'active_mid' => 0),
            'notify_bell' => array(),
            'auth_form' => array('expand_invite' => false),
            'member_shell' => array('active' => 'index', 'title' => '个人中心', 'show_hub' => true, 'require_login' => true),
            'pay_shell' => array('default_tab' => 'recharge', 'variant' => 'default'),
            'order_list' => array(),
            'cards_list' => array(),
            'plays_list' => array('show_clear' => true),
            'benefits_panel' => array('variant' => 'default'),
            'auth_modals' => array(),
            'auth_page' => array(
                'panel' => 'login',
                'expand_invite' => false,
                'hero_title' => '',
                'hero_desc' => '',
                'hero_image' => '',
                'hero_image_mobile' => '',
                'show_hero' => true,
                'layout' => 'split',
            ),
        );
        return isset($map[$componentId]) ? $map[$componentId] : array();
    }

    /**
     * 全站登录弹窗默认文案（与 kit auth_modals 模板一致）。
     *
     * @return array
     */
    public function defaultAuthModalConfig()
    {
        return array(
            'login' => array(
                'welcome' => '欢迎回来',
                'hint' => '登录即可收藏、评论与同步观看记录',
                'title_text' => '登录 / 注册',
                'subtitle' => '新用户将自动创建账号',
                'hero_image' => '',
                'hero_image_mobile' => '',
            ),
            'findpass' => array(
                'welcome' => '找回密码',
                'hint' => '选择方式验证身份后重置',
                'title_text' => '找回密码',
                'subtitle' => '',
                'hero_image' => '',
                'hero_image_mobile' => '',
            ),
        );
    }

    /**
     * 合并 draft auth_modal 与用户数据（缺项补默认，不整文件覆盖）。
     *
     * @param array|null $stored
     * @return array
     */
    public function mergeAuthModalConfig($stored)
    {
        $defaults = $this->defaultAuthModalConfig();
        if (!is_array($stored)) {
            return $defaults;
        }
        $out = $defaults;
        foreach (array('login', 'findpass') as $key) {
            if (!isset($stored[$key]) || !is_array($stored[$key])) {
                continue;
            }
            foreach ($stored[$key] as $fk => $fv) {
                if ($fk === 'welcome' || $fk === 'hint' || $fk === 'title_text' || $fk === 'subtitle') {
                    $out[$key][$fk] = mb_substr(mac_filter_xss((string) $fv), 0, 200, 'UTF-8');
                } elseif ($fk === 'hero_image' || $fk === 'hero_image_mobile') {
                    $out[$key][$fk] = preg_replace('/[^a-zA-Z0-9_\\/.\\-~%]/', '', (string) $fv);
                }
            }
        }
        return $out;
    }

    /**
     * 前台当前主题目录名（预览 td_theme 优先，否则站点 template_dir）。
     *
     * @return string
     */
    public function resolveFrontThemeDir()
    {
        $theme = '';
        if (function_exists('request')) {
            $req = request();
            if (is_object($req) && !empty($req->param('td_theme'))) {
                $theme = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $req->param('td_theme'));
            }
        }
        if ($theme === '' && function_exists('config')) {
            $theme = (string) config('site.template_dir');
        }
        if ($theme === '' && isset($GLOBALS['config']['site']['template_dir'])) {
            $theme = (string) $GLOBALS['config']['site']['template_dir'];
        }
        return $theme;
    }

    /**
     * 主题是否已同步全站 auth_modals 壳组件（仅 PB 薄壳主题需要注入 auth_modal）。
     *
     * @param string|null $themeDir
     * @return bool
     */
    public function themeHasAuthModals($themeDir = null)
    {
        if ($themeDir === null || $themeDir === '') {
            $themeDir = $this->resolveFrontThemeDir();
        }
        if ($themeDir === '') {
            return false;
        }
        $rel = 'template/' . $themeDir . '/' . self::THEME_COMPONENTS_DIR . '/auth_modals/template.twig';
        return is_file($this->rootPath . $rel);
    }

    /**
     * 前台全站登录弹窗配置（预览读 draft，线上读 theme-design.json，缺项补 kit 默认）。
     *
     * @param string|null $themeDir
     * @return array
     */
    public function resolveAuthModalConfigForFront($themeDir = null)
    {
        static $cached = array();
        if ($themeDir === null || $themeDir === '') {
            $themeDir = $this->resolveFrontThemeDir();
        }
        $preview = ThemePreviewAuth::isActive();
        $cacheKey = $themeDir . ($preview ? '|draft' : '|pub');
        if (isset($cached[$cacheKey])) {
            return $cached[$cacheKey];
        }

        $defaults = $this->defaultAuthModalConfig();
        if ($themeDir === '') {
            $cached[$cacheKey] = $defaults;
            return $cached[$cacheKey];
        }

        $stored = array();
        if ($preview) {
            $file = $this->rootPath . 'application/data/theme_design/' . $themeDir . '/draft.json';
            if (!is_file($file)) {
                $alt = $this->rootPath . 'template/' . $themeDir . '/theme-design.draft.json';
                if (is_file($alt)) {
                    $file = $alt;
                }
            }
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                $cfg = ($raw !== false && $raw !== '') ? json_decode($raw, true) : array();
                $stored = (is_array($cfg) && isset($cfg['auth_modal'])) ? $cfg['auth_modal'] : array();
            }
        } else {
            $pub = $this->rootPath . 'template/' . $themeDir . '/theme-design.json';
            if (is_file($pub)) {
                $raw = @file_get_contents($pub);
                $cfg = ($raw !== false && $raw !== '') ? json_decode($raw, true) : array();
                $stored = (is_array($cfg) && isset($cfg['auth_modal'])) ? $cfg['auth_modal'] : array();
            }
        }

        $cached[$cacheKey] = $this->mergeAuthModalConfig($stored);
        return $cached[$cacheKey];
    }

    /**
     * 固定顺序合并 chrome：site_nav → header_decor → channel_strip。
     * 存量仅含导航+频道条时自动插入顶栏装饰，保证预览 data-pb-path 与编辑器一致。
     *
     * @param array $chrome
     * @param array $typeIds 可选，写入 site_nav / channel_strip 的 type_ids
     * @param array $opts header_decor / channel_strip 为 false 时不强制插入
     * @return array
     */
    public function normalizeChrome(array $chrome, array $typeIds = array(), array $opts = array())
    {
        $navProps = $this->defaultPropsForComponent('site_nav');
        $decorProps = $this->defaultPropsForComponent('header_decor');
        $channelProps = $this->defaultPropsForComponent('channel_strip');
        if (!empty($typeIds)) {
            $navProps['type_ids'] = $typeIds;
            $channelProps['type_ids'] = $typeIds;
        }
        $includeDecor = true;
        $includeChannel = true;
        if (array_key_exists('header_decor', $opts)) {
            $includeDecor = !empty($opts['header_decor']);
        }
        if (array_key_exists('channel_strip', $opts)) {
            $includeChannel = !empty($opts['channel_strip']);
        }
        $defaults = array(
            array('component' => 'site_nav', 'props' => $navProps),
        );
        if ($includeDecor) {
            $defaults[] = array('component' => 'header_decor', 'props' => $decorProps);
        }
        if ($includeChannel) {
            $defaults[] = array('component' => 'channel_strip', 'props' => $channelProps);
        }
        $saved = array();
        foreach ($chrome as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $saved[(string) $b['component']] = $b;
        }
        $out = array();
        foreach ($defaults as $def) {
            $id = $def['component'];
            if (isset($saved[$id])) {
                $merged = $saved[$id];
                $merged['component'] = $id;
                $props = isset($merged['props']) && is_array($merged['props']) ? $merged['props'] : array();
                foreach ($def['props'] as $k => $v) {
                    if (!isset($props[$k]) || $props[$k] === '' || $props[$k] === null) {
                        $props[$k] = $v;
                    }
                }
                $merged['props'] = $props;
                $out[] = $merged;
            } else {
                $out[] = $def;
            }
        }
        return $out;
    }

    /**
     * 归一化首页页脚组件（独立于顶部 chrome，预览 path 在内容块之后）。
     *
     * @param array $footer
     * @param array $opts 可选 copyright / logo 来自主题设计 attributes
     * @return array
     */
    public function normalizeFooter(array $footer = null, array $opts = array())
    {
        $defaults = $this->defaultPropsForComponent('site_footer');
        if (isset($opts['copyright']) && trim((string) $opts['copyright']) !== '') {
            $defaults['copyright'] = trim((string) $opts['copyright']);
        }
        if (isset($opts['logo']) && trim((string) $opts['logo']) !== '') {
            $defaults['logo'] = trim((string) $opts['logo']);
        }
        if (!is_array($footer) || empty($footer['component'])) {
            return array('component' => 'site_footer', 'props' => $defaults);
        }
        if ((string) $footer['component'] !== 'site_footer') {
            return array('component' => 'site_footer', 'props' => $defaults);
        }
        $props = isset($footer['props']) && is_array($footer['props']) ? $footer['props'] : array();
        foreach ($defaults as $k => $v) {
            if (!isset($props[$k]) || $props[$k] === '' || $props[$k] === null) {
                $props[$k] = $v;
            }
        }
        return array('component' => 'site_footer', 'props' => $props);
    }

    /**
     * 页脚区域固定为法律页脚 + 移动底栏；存量只有 site_footer 时自动补齐。
     *
     * @param array $blocks
     * @param array $opts
     * @return array
     */
    public function normalizeFooterBlocks(array $blocks = array(), array $opts = array())
    {
        $saved = array();
        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['component'])) {
                continue;
            }
            $saved[(string) $block['component']] = $block;
        }

        $footer = isset($saved['site_footer']) ? $saved['site_footer'] : array();
        $bottom = isset($saved['bottom_nav']) ? $saved['bottom_nav'] : array();
        $bottomProps = isset($bottom['props']) && is_array($bottom['props']) ? $bottom['props'] : array();
        $bottomProps = $this->normalizeBottomNavProps($bottomProps);

        return array(
            $this->normalizeFooter($footer, $opts),
            array('component' => 'bottom_nav', 'props' => $bottomProps),
        );
    }

    /**
     * 规范化底部导航 props：支持动态 items[]，并兼容旧四槽字段。
     *
     * @param array $props
     * @return array
     */
    public function normalizeBottomNavProps(array $props = array())
    {
        $icons = array('home', 'video', 'book', 'manga', 'user', 'grid', 'compass');
        $iconById = array(
            'home' => 'home',
            'video' => 'video',
            'vod' => 'video',
            'catalog' => 'video',
            'novel' => 'book',
            'art' => 'book',
            'manga' => 'manga',
            'discover' => 'compass',
            'me' => 'user',
        );
        $defaults = $this->defaultPropsForComponent('bottom_nav');
        $defaultItems = isset($defaults['items']) && is_array($defaults['items'])
            ? $defaults['items']
            : array();

        $rawItems = array();
        if (isset($props['items']) && is_array($props['items']) && count($props['items']) > 0) {
            $rawItems = $props['items'];
            // 上一版内置四槽默认（首页/片库/发现/会员）升级为新默认五槽
            if ($this->isLegacyStockBottomNavItems($rawItems)) {
                $rawItems = $defaultItems;
            }
        } else {
            $legacySlots = array('home', 'catalog', 'discover', 'me');
            $legacyIcons = array('home' => 'home', 'catalog' => 'grid', 'discover' => 'compass', 'me' => 'user');
            $legacyLabels = array('home' => '首页', 'catalog' => '片库', 'discover' => '发现', 'me' => '会员');
            $hasLegacy = false;
            foreach ($legacySlots as $slot) {
                if (isset($props[$slot . '_label']) || isset($props[$slot . '_href']) || isset($props[$slot . '_icon'])) {
                    $hasLegacy = true;
                    break;
                }
            }
            if ($hasLegacy) {
                // 未改过的旧四槽字段 → 直接用新默认；有自定义文案则保留迁移
                $touched = false;
                foreach ($legacySlots as $slot) {
                    $label = isset($props[$slot . '_label']) ? trim((string) $props[$slot . '_label']) : $legacyLabels[$slot];
                    $href = isset($props[$slot . '_href']) ? trim((string) $props[$slot . '_href']) : '';
                    $icon = isset($props[$slot . '_icon']) ? trim((string) $props[$slot . '_icon']) : $legacyIcons[$slot];
                    if ($label !== $legacyLabels[$slot] || ($href !== '' && $href !== '/') || $icon !== $legacyIcons[$slot]) {
                        $touched = true;
                        break;
                    }
                }
                if (!$touched) {
                    $rawItems = $defaultItems;
                } else {
                    foreach ($legacySlots as $slot) {
                        $labelKey = $slot . '_label';
                        $hrefKey = $slot . '_href';
                        $iconKey = $slot . '_icon';
                        $rawItems[] = array(
                            'id' => $slot,
                            'label' => isset($props[$labelKey]) ? (string) $props[$labelKey] : $legacyLabels[$slot],
                            'href' => isset($props[$hrefKey]) ? (string) $props[$hrefKey] : '',
                            'icon' => isset($props[$iconKey]) ? (string) $props[$iconKey] : $legacyIcons[$slot],
                        );
                    }
                }
            } else {
                $rawItems = $defaultItems;
            }
        }

        $out = array();
        $usedIds = array();
        foreach ($rawItems as $idx => $item) {
            if (count($out) >= 6) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            $id = preg_replace('/[^a-z0-9_\\-]/i', '', $id);
            if ($id === '') {
                $id = 'item_' . ($idx + 1);
            }
            if (isset($usedIds[$id])) {
                $id = $id . '_' . ($idx + 1);
            }
            $usedIds[$id] = true;
            $label = isset($item['label']) ? mb_substr(mac_filter_xss((string) $item['label']), 0, 20, 'UTF-8') : '';
            if ($label === '') {
                $label = '菜单';
            }
            $href = isset($item['href']) ? trim(mac_filter_xss((string) $item['href'])) : '';
            if ($href === '/') {
                $href = '';
            }
            // 协议相对地址 //host 会被浏览器当成外站，两条站内正则都能匹配到，须先排除
            if ($href !== '' && (strpos($href, '//') === 0 || (
                !preg_match('#^https?://#i', $href)
                && !preg_match('~^/[a-z0-9_./?&=%#-]*$~i', $href)
                && !preg_match('~^[a-z0-9_./?&=%#-]+$~i', $href)
            ))) {
                $href = '';
            }
            $href = mb_substr($href, 0, 500, 'UTF-8');
            $icon = isset($item['icon']) ? (string) $item['icon'] : '';
            // 类型默认图标：空或旧占位 grid/compass 按 id 纠正
            if ($icon === '' || !in_array($icon, $icons, true)) {
                $icon = isset($iconById[$id]) ? $iconById[$id] : 'home';
            } elseif (isset($iconById[$id])) {
                $want = $iconById[$id];
                if (($id === 'video' || $id === 'vod' || $id === 'catalog') && $icon === 'grid') {
                    $icon = $want;
                } elseif ($id === 'manga' && $icon === 'compass') {
                    $icon = $want;
                } elseif (($id === 'novel' || $id === 'art') && ($icon === 'compass' || $icon === 'grid')) {
                    $icon = $want;
                }
            }
            if (!in_array($icon, $icons, true)) {
                $icon = 'home';
            }
            $out[] = array(
                'id' => $id,
                'label' => $label,
                'href' => $href,
                'icon' => $icon,
            );
        }

        if (!$out) {
            $out = $defaultItems;
        }

        return array('items' => array_values($out));
    }

    /**
     * 规范化广告宫格 props：items[] = label / href / cover。
     *
     * @param array $props
     * @return array
     */
    public function normalizeAdsGridProps(array $props = array())
    {
        $variant = isset($props['variant']) ? (string) $props['variant'] : 'default';
        if ($variant !== 'splash') {
            $variant = 'default';
        }
        $rawItems = array();
        if (isset($props['items']) && is_array($props['items'])) {
            $rawItems = $props['items'];
        }
        $out = array();
        $usedIds = array();
        foreach ($rawItems as $idx => $item) {
            if (count($out) >= 10) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            $id = preg_replace('/[^a-z0-9_\\-]/i', '', $id);
            if ($id === '') {
                $id = 'ad_' . ($idx + 1);
            }
            if (isset($usedIds[$id])) {
                $id = $id . '_' . ($idx + 1);
            }
            $usedIds[$id] = true;
            $label = isset($item['label']) ? mb_substr(mac_filter_xss((string) $item['label']), 0, 20, 'UTF-8') : '';
            $href = isset($item['href']) ? trim(mac_filter_xss((string) $item['href'])) : '';
            if ($href === '/') {
                $href = '';
            }
            // 协议相对地址 //host 会被浏览器当成外站，两条站内正则都能匹配到，须先排除
            if ($href !== '' && (strpos($href, '//') === 0 || (
                !preg_match('#^https?://#i', $href)
                && !preg_match('~^/[a-z0-9_./?&=%#-]*$~i', $href)
                && !preg_match('~^[a-z0-9_./?&=%#-]+$~i', $href)
            ))) {
                $href = '';
            }
            $href = mb_substr($href, 0, 500, 'UTF-8');
            $cover = isset($item['cover']) ? trim(mac_filter_xss((string) $item['cover'])) : '';
            if ($cover !== ''
                && !preg_match('#^(https?:)?//#i', $cover)
                && isset($cover[0])
                && $cover[0] !== '/'
                && $cover[0] !== '.'
            ) {
                $cover = mb_substr(preg_replace('/[^a-z0-9_\\/.\\-]/i', '', $cover), 0, 500);
            } else {
                $cover = mb_substr($cover, 0, 500, 'UTF-8');
            }
            if ($label === '' && $cover === '' && $href === '') {
                continue;
            }
            $out[] = array(
                'id' => $id,
                'label' => $label,
                'href' => $href,
                'cover' => $cover,
            );
        }

        if ($variant === 'splash' && empty($out)) {
            $out = \app\common\util\ThemePreset::np2SplashAdItems();
        }

        return array(
            'variant' => $variant,
            'items' => array_values($out),
        );
    }

    /**
     * NP2 首页视频列表 props 归一化（保存时保留 layout / 数据来源等字段）。
     *
     * @param array $props
     * @return array
     */
    public function normalizeVodListNp2Props(array $props = array())
    {
        $defaults = $this->defaultPropsForComponent('vod_list_np2');
        if (!is_array($defaults)) {
            $defaults = array();
        }
        $out = array();
        if (isset($props['title'])) {
            $out['title'] = mb_substr(mac_filter_xss((string) $props['title']), 0, 80, 'UTF-8');
        } elseif (isset($defaults['title'])) {
            $out['title'] = (string) $defaults['title'];
        }
        $titleMode = isset($props['title_mode']) ? (string) $props['title_mode'] : (isset($defaults['title_mode']) ? (string) $defaults['title_mode'] : 'custom');
        if (in_array($titleMode, array('custom', 'type_name'), true)) {
            $out['title_mode'] = $titleMode;
        }
        $icons = array('auto', 'spark', 'film', 'heart', 'clapper', 'fire', 'tv', 'star');
        $icon = isset($props['icon']) ? (string) $props['icon'] : (isset($defaults['icon']) ? (string) $defaults['icon'] : 'fire');
        if (in_array($icon, $icons, true)) {
            $out['icon'] = $icon;
        }
        $layouts = array('grid2', 'hscroll', 'cols3');
        $layout = isset($props['layout']) ? (string) $props['layout'] : (isset($defaults['layout']) ? (string) $defaults['layout'] : 'grid2');
        if (in_array($layout, $layouts, true)) {
            $out['layout'] = $layout;
        }
        $num = isset($props['num']) ? (int) $props['num'] : (isset($defaults['num']) ? (int) $defaults['num'] : 12);
        if ($num >= 1 && $num <= 24) {
            $out['num'] = $num;
        }
        $sorts = array('time', 'hits', 'hits_day', 'hits_week', 'hits_month', 'score');
        $by = isset($props['by']) ? (string) $props['by'] : (isset($defaults['by']) ? (string) $defaults['by'] : 'hits');
        if (in_array($by, $sorts, true)) {
            $out['by'] = $by;
        }
        $order = isset($props['order']) ? (string) $props['order'] : (isset($defaults['order']) ? (string) $defaults['order'] : 'desc');
        if ($order === 'asc' || $order === 'desc') {
            $out['order'] = $order;
        }
        $typePick = isset($props['type_pick']) ? (string) $props['type_pick'] : (isset($defaults['type_pick']) ? (string) $defaults['type_pick'] : 'all');
        if (in_array($typePick, array('all', 'category', 'top_types'), true)) {
            $out['type_pick'] = $typePick;
        }
        if (array_key_exists('type', $props)) {
            $t = (int) $props['type'];
            if ($t >= 0) {
                $out['type'] = $t;
            }
        } elseif (isset($defaults['type'])) {
            $out['type'] = (int) $defaults['type'];
        }
        if (isset($props['top_types_index'])) {
            $idx = (int) $props['top_types_index'];
            if ($idx >= 1 && $idx <= 24) {
                $out['top_types_index'] = $idx;
            }
        } elseif (isset($defaults['top_types_index'])) {
            $out['top_types_index'] = (int) $defaults['top_types_index'];
        }
        $out['show_shuffle'] = array_key_exists('show_shuffle', $props)
            ? !empty($props['show_shuffle'])
            : (!empty($defaults['show_shuffle']));
        $out['show_more'] = array_key_exists('show_more', $props)
            ? !empty($props['show_more'])
            : (!empty($defaults['show_more']));
        return $out;
    }

    /**
     * 是否为上一版内置默认四项（可安全升级到首页/视频/小说/漫画/会员）。
     *
     * @param array $items
     * @return bool
     */
    private function isLegacyStockBottomNavItems(array $items)
    {
        if (count($items) !== 4) {
            return false;
        }
        $expect = array(
            array('id' => 'home', 'label' => '首页', 'icon' => 'home'),
            array('id' => 'catalog', 'label' => '片库', 'icon' => 'grid'),
            array('id' => 'discover', 'label' => '发现', 'icon' => 'compass'),
            array('id' => 'me', 'label' => '会员', 'icon' => 'user'),
        );
        foreach ($items as $i => $item) {
            if (!is_array($item) || !isset($expect[$i])) {
                return false;
            }
            $id = isset($item['id']) ? (string) $item['id'] : '';
            $label = isset($item['label']) ? trim((string) $item['label']) : '';
            $icon = isset($item['icon']) ? (string) $item['icon'] : '';
            $href = isset($item['href']) ? trim((string) $item['href']) : '';
            if ($id !== $expect[$i]['id']
                || $label !== $expect[$i]['label']
                || $icon !== $expect[$i]['icon']
                || ($href !== '' && $href !== '/')
            ) {
                return false;
            }
        }
        return true;
    }

    /** 主题侧是否已安装打包组件 */
    public function isPackagedInTheme($themeDir, $componentId)
    {
        $componentId = (string) $componentId;
        if ($componentId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $componentId)) {
            return false;
        }
        $base = $this->themeComponentsDir($themeDir) . $componentId . '/';
        return is_file($base . 'template.twig');
    }

    /**
     * 聚合 kit 内全部组件契约（机器可读 catalog）。
     *
     * @return array{version:int,kit:array,components:array,ids:string[]}
     */
    public function componentCatalog()
    {
        $dirs = $this->discoverKitComponentDirs();
        $components = array();
        foreach ($dirs as $id => $path) {
            $meta = $this->readJsonFile($path . '/component.json');
            if (!is_array($meta)) {
                $meta = array();
            }
            if (empty($meta['id'])) {
                $meta['id'] = $id;
            }
            $category = basename(dirname($path));
            if (!isset($meta['category']) || $meta['category'] === '') {
                $meta['category'] = $category;
            }
            $components[$id] = array(
                'id' => $id,
                'category' => isset($meta['category']) ? (string) $meta['category'] : $category,
                'meta' => $meta,
            );
        }
        ksort($components);
        return array(
            'version' => $this->kitVersion(),
            'kit' => $this->kitMeta(),
            'components' => $components,
            'ids' => array_keys($components),
        );
    }

    /**
     * Theme AI / 文档用：精简 catalog（仅 id、kind、props 摘要、pageTypes）。
     *
     * @param string $pageType 空=全部；否则仅含 context.pageTypes 匹配或未声明 pageTypes 的项
     * @return array
     */
    public function componentCatalogSummaryForAi($pageType = '')
    {
        $catalog = $this->componentCatalog();
        $pageType = strtolower(trim((string) $pageType));
        $out = array();
        foreach ($catalog['components'] as $id => $entry) {
            $meta = $entry['meta'];
            $pageTypes = (isset($meta['context']['pageTypes']) && is_array($meta['context']['pageTypes']))
                ? $meta['context']['pageTypes'] : null;
            if ($pageType !== '' && is_array($pageTypes) && !empty($pageTypes)
                && !in_array($pageType, $pageTypes, true)) {
                continue;
            }
            $props = array();
            if (isset($meta['props']) && is_array($meta['props'])) {
                foreach ($meta['props'] as $propName => $def) {
                    if (!is_array($def)) {
                        continue;
                    }
                    $item = array('type' => isset($def['type']) ? $def['type'] : 'string');
                    if (array_key_exists('default', $def)) {
                        $item['default'] = $def['default'];
                    }
                    if (!empty($def['values']) && is_array($def['values'])) {
                        $item['values'] = array_values($def['values']);
                    }
                    $props[$propName] = $item;
                }
            }
            $out[] = array(
                'id' => $id,
                'kind' => isset($meta['kind']) ? (string) $meta['kind'] : '',
                'palette' => array_key_exists('palette', $meta) ? $meta['palette'] : true,
                'pageTypes' => $pageTypes,
                'props' => $props,
            );
        }
        return $out;
    }

    /**
     * 从 catalog context.pageTypes 推导页面可用组件（已迁移契约的组件）。
     *
     * @param string $pageType
     * @param string $module
     * @return array
     */
    public function allowedComponentsForPageTypeFromCatalog($pageType, $module = '')
    {
        $pageType = strtolower(trim((string) $pageType));
        if ($pageType === 'home') {
            return array();
        }
        $catalog = $this->componentCatalog();
        $allowed = array();
        foreach ($catalog['components'] as $id => $entry) {
            $meta = $entry['meta'];
            if (!isset($meta['context']['pageTypes']) || !is_array($meta['context']['pageTypes'])) {
                continue;
            }
            if (in_array($pageType, $meta['context']['pageTypes'], true)) {
                $allowed[] = $id;
            }
        }
        $allowed = array_values(array_unique($allowed));
        if ($module !== '' && $pageType !== 'home') {
            $allowed = $this->narrowAllowedByModule($allowed, $module, $pageType);
        }
        return $allowed;
    }

    /** catalog 中是否存在组件 id */
    public function catalogHasComponent($componentId)
    {
        $componentId = (string) $componentId;
        if ($componentId === '') {
            return false;
        }
        $catalog = $this->componentCatalog();
        return isset($catalog['components'][$componentId]);
    }

    /**
     * 将 catalog 全量 id 写入 kit/manifest.json。
     *
     * @return bool
     */
    public function writeKitManifest()
    {
        $catalog = $this->componentCatalog();
        $manifest = array(
            'version' => $catalog['version'],
            'generatedAt' => gmdate('c'),
            'components' => $catalog['ids'],
        );
        $file = $this->kitDir() . 'manifest.json';
        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $json .= "\n";
        return @file_put_contents($file, $json) !== false;
    }

    /** 读取主题或 kit 内的 component.json */
    public function componentMeta($themeDir, $componentId)
    {
        $componentId = (string) $componentId;
        if ($componentId === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $componentId)) {
            return array();
        }
        $themeFile = $this->themeComponentsDir($themeDir) . $componentId . '/component.json';
        if (is_file($themeFile)) {
            $meta = $this->readJsonFile($themeFile);
            if (is_array($meta)) {
                return $meta;
            }
        }
        $dirs = $this->discoverKitComponentDirs();
        if (isset($dirs[$componentId])) {
            $kitFile = $dirs[$componentId] . '/component.json';
            if (is_file($kitFile)) {
                $meta = $this->readJsonFile($kitFile);
                if (is_array($meta)) {
                    return $meta;
                }
            }
        }
        return array();
    }

    /**
     * 扫描 blocks 树，收集需加载的打包组件与 peer 样式。
     *
     * @param string $themeDir
     * @param array  $blocks
     * @return array{components:array,peer_styles:array}
     */
    public function collectBlockAssets($themeDir, $blocks)
    {
        $components = array();
        $peerStyles = array();
        $this->walkBlocksForAssets($themeDir, $blocks, $components, $peerStyles);
        return array(
            'components' => array_values($components),
            'peer_styles' => array_values($peerStyles),
        );
    }

    /**
     * 扫描首页 page 树（chrome / blocks / footer），收集编译与同步所需的组件 id。
     * 逻辑与 TwigPageCompiler 资源收集一致（content_feed → 列表组件、rank_feed → inline/list 等）。
     *
     * @param array $page homepage.page（建议经 enrichHomepagePage 归一化后传入）
     * @return array
     */
    public function collectPageComponentIds(array $page)
    {
        $components = array();
        if (isset($page['regions']) && is_array($page['regions'])) {
            foreach ($page['regions'] as $blocks) {
                if (is_array($blocks)) {
                    $this->walkPageForComponentIds($blocks, $components);
                }
            }
        }
        $chrome = isset($page['chrome']) && is_array($page['chrome']) ? $page['chrome'] : array();
        $blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : array();
        $footer = isset($page['footer']) && is_array($page['footer']) ? $page['footer'] : array();

        $this->walkPageForComponentIds($chrome, $components);
        $this->walkPageForComponentIds($blocks, $components);
        if (!empty($footer['component'])) {
            $this->registerPageComponentId((string) $footer['component'], array(), $components);
        }
        if (isset($page['regions']['footer']) && is_array($page['regions']['footer'])) {
            $this->walkPageForComponentIds($page['regions']['footer'], $components);
        }

        foreach ($this->implicitShellComponentIds() as $id) {
            if ($id !== '' && !isset($components[$id])) {
                $components[$id] = $id;
            }
        }

        return array_values($components);
    }

    /**
     * 壳层隐式组件：不在页面 JSON 树里，但 layout / site_header_bar 会 {% include %}。
     * 发布裁剪或按需同步时必须保留，否则前台白屏。
     *
     * @return string[]
     */
    public function implicitShellComponentIds()
    {
        return array('notify_bell', 'auth_modals');
    }

    /**
     * 主题是否允许写入 page-builder-components / .page-builder-kit-version。
     * Think/Smarty 存量主题（default、prism 等）禁止同步，避免污染。
     *
     * @param string $themeDir
     * @return bool
     */
    public function themeAcceptsKitSync($themeDir)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir === '' || !is_dir($themeDir)) {
            return false;
        }
        $iniFile = $themeDir . 'info.ini';
        if (is_file($iniFile)) {
            $ini = @parse_ini_file($iniFile, false, INI_SCANNER_RAW);
            if (is_array($ini)) {
                $engine = isset($ini['engine']) ? strtolower(trim((string) $ini['engine'])) : '';
                if ($engine === 'twig') {
                    return true;
                }
                if ($engine !== '' && $engine !== 'twig') {
                    return false;
                }
                if (isset($ini['pb_enabled'])
                    && in_array(strtolower((string) $ini['pb_enabled']), array('1', 'true', 'yes', 'on'), true)) {
                    return true;
                }
            }
        }
        return is_file($themeDir . 'html/index/index.twig');
    }

    /**
     * 读取主题 info.ini 中的 kit 同步保护配置。
     *
     * - pb_protect_kit_assets=1 启用
     * - pb_protect_components=id1,id2  受保护组件（upgrade 时已存在文件不覆盖；空=保护全部已存在组件文件）
     * - pb_protect_shared=art_row.twig,js/foo.js  相对 _shared/ 的路径
     * - pb_keep_local_components=ads_grid  prune 时额外保留的主题本地组件
     *
     * @param string $themeDir
     * @return array{enabled:bool,components:array<string,bool>,shared:array<string,bool>,keep_local:array<string,bool>}
     */
    public function themeSyncProtectConfig($themeDir)
    {
        $out = array(
            'enabled' => false,
            'components' => array(),
            'shared' => array(),
            'keep_local' => array(),
        );
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $iniFile = $themeDir . 'info.ini';
        if (!is_file($iniFile)) {
            return $out;
        }
        $ini = @parse_ini_file($iniFile, false, INI_SCANNER_RAW);
        if (!is_array($ini)) {
            return $out;
        }
        $flag = isset($ini['pb_protect_kit_assets']) ? strtolower(trim((string) $ini['pb_protect_kit_assets'])) : '';
        if (!in_array($flag, array('1', 'true', 'yes', 'on'), true)) {
            return $out;
        }
        $out['enabled'] = true;
        $out['components'] = $this->parseIniIdList(isset($ini['pb_protect_components']) ? $ini['pb_protect_components'] : '');
        $out['keep_local'] = $this->parseIniIdList(isset($ini['pb_keep_local_components']) ? $ini['pb_keep_local_components'] : '');
        $sharedRaw = isset($ini['pb_protect_shared']) ? (string) $ini['pb_protect_shared'] : '';
        foreach (preg_split('/\s*,\s*/', $sharedRaw) as $rel) {
            $rel = str_replace('\\', '/', trim((string) $rel));
            $rel = ltrim($rel, '/');
            if ($rel === '' || strpos($rel, '..') !== false) {
                continue;
            }
            $out['shared'][$rel] = true;
        }
        return $out;
    }

    /**
     * @param string $raw
     * @return array<string,bool>
     */
    private function parseIniIdList($raw)
    {
        $map = array();
        foreach (preg_split('/\s*,\s*/', (string) $raw) as $id) {
            $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
            if ($id !== '') {
                $map[$id] = true;
            }
        }
        return $map;
    }

    /** 受保护组件：upgrade 时对已存在文件按 fill-only 处理 */
    private function isProtectedComponent($componentId)
    {
        if ($this->syncProtect === null || empty($this->syncProtect['enabled'])) {
            return false;
        }
        $comps = isset($this->syncProtect['components']) ? $this->syncProtect['components'] : array();
        // 未列具体 id → 保护主题内全部已存在组件文件
        if (empty($comps)) {
            return true;
        }
        return isset($comps[(string) $componentId]);
    }

    /** @param string $relPath 相对 page-builder-components/_shared/ */
    private function isProtectedSharedRel($relPath)
    {
        if ($this->syncProtect === null || empty($this->syncProtect['enabled'])) {
            return false;
        }
        $relPath = ltrim(str_replace('\\', '/', (string) $relPath), '/');
        $shared = isset($this->syncProtect['shared']) ? $this->syncProtect['shared'] : array();
        return isset($shared[$relPath]);
    }

    /**
     * 同步 kit 到主题。$onlyIds 非空时仅复制页面用到的组件（+ _shared）；发布且 $upgrade 时剔除主题内多余组件目录。
     *
     * @param string     $themeDir
     * @param bool       $upgrade  发布时为 true，覆盖已存在文件
     * @param array|null $onlyIds  null=全量同步（兼容脚本/测试）；数组=按需同步
     * @return bool
     */
    public function syncToTheme($themeDir, $upgrade = false, $onlyIds = null)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir === '' || !is_dir($themeDir)) {
            return false;
        }
        // 非 Twig/PB 主题：静默跳过，不写 page-builder-components 与版本戳
        if (!$this->themeAcceptsKitSync($themeDir)) {
            return true;
        }

        $filterIds = null;
        if ($onlyIds !== null) {
            $filterIds = array();
            foreach ($onlyIds as $id) {
                $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
                if ($id !== '') {
                    $filterIds[] = $id;
                }
            }
            if (empty($filterIds)) {
                return false;
            }
        }

        $this->syncProtect = $this->themeSyncProtectConfig($themeDir);
        $kitVersion = $this->kitVersion();
        $ok = $this->syncPackagedComponents($themeDir, $filterIds, $upgrade);
        if ($ok) {
            $ok = $this->syncSharedPeers($themeDir);
        }
        if ($ok) {
            $ok = $this->syncPbMacrosTwig($themeDir);
        }
        if ($ok && $filterIds !== null && $upgrade) {
            $ok = $this->pruneThemeComponents($themeDir, $filterIds);
        }
        if ($ok && class_exists('\app\common\util\PageScaffolder')) {
            $scaffolder = new PageScaffolder();
            $ok = $scaffolder->ensurePublicStatusTemplates($themeDir);
        }
        if ($ok) {
            $this->writeThemeSyncedVersion($themeDir, $kitVersion);
        }
        $this->syncProtect = null;

        return $ok;
    }

    public function themeSyncedVersion($themeDir)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $file = $themeDir . self::VERSION_FILE;
        if (!is_file($file)) {
            return 0;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return 0;
        }
        return (int) trim($raw);
    }

    /** 同步 kit/shared（顶层文件 + js/css/img/language/svg 子目录）到主题 _shared/ */
    private function syncSharedPeers($themeDir)
    {
        $src = $this->kitDir() . 'shared/';
        $dest = rtrim((string) $themeDir, '/') . '/' . self::THEME_COMPONENTS_DIR . '/_shared/';
        if (!is_dir($src)) {
            return true;
        }
        if (!is_dir($dest)) {
            if (!@mkdir($dest, 0755, true) && !is_dir($dest)) {
                return false;
            }
        }
        $ok = true;
        $dh = @opendir($src);
        if (!$dh) {
            return false;
        }
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $from = $src . $f;
            $to = $dest . $f;
            if (is_dir($from)) {
                if (!$this->syncSharedSubdir($from, $to, $f)) {
                    $ok = false;
                }
                continue;
            }
            if (!is_file($from)) {
                continue;
            }
            if ($this->isProtectedSharedRel($f) && is_file($to)) {
                continue;
            }
            if (@copy($from, $to) === false) {
                $ok = false;
            }
        }
        closedir($dh);
        return $ok;
    }

    /**
     * 递归同步 kit/shared 子目录（js / css / img / language / svg…）
     * @param string $srcDir
     * @param string $destDir
     * @param string $relPrefix 相对 _shared/ 的前缀（如 js）
     * @return bool
     */
    private function syncSharedSubdir($srcDir, $destDir, $relPrefix = '')
    {
        $srcDir = rtrim((string) $srcDir, '/') . '/';
        $destDir = rtrim((string) $destDir, '/') . '/';
        $relPrefix = trim(str_replace('\\', '/', (string) $relPrefix), '/');
        if (!is_dir($srcDir)) {
            return true;
        }
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return false;
            }
        }
        $ok = true;
        $dh = @opendir($srcDir);
        if (!$dh) {
            return false;
        }
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $from = $srcDir . $f;
            $to = $destDir . $f;
            $rel = $relPrefix === '' ? $f : ($relPrefix . '/' . $f);
            if (is_dir($from)) {
                if (!$this->syncSharedSubdir($from, $to, $rel)) {
                    $ok = false;
                }
                continue;
            }
            if (!is_file($from)) {
                continue;
            }
            if ($this->isProtectedSharedRel($rel) && is_file($to)) {
                continue;
            }
            if (@copy($from, $to) === false) {
                $ok = false;
            }
        }
        closedir($dh);
        return $ok;
    }

    /** 同步 PB 资源宏到 html/lib/pb_bundles.twig（主题无需手改 components.twig） */
    private function syncPbMacrosTwig($themeDir)
    {
        $src = $this->kitDir() . 'shared/pb_macros.twig';
        if (!is_file($src)) {
            return true;
        }
        $destDir = rtrim((string) $themeDir, '/') . '/html/lib/';
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return false;
            }
        }
        return @copy($src, $destDir . 'pb_bundles.twig') !== false;
    }

    private function syncPackagedComponents($themeDir, $onlyIds = null, $upgrade = false)
    {
        $componentDirs = $this->discoverKitComponentDirs();
        $destRoot = $this->themeComponentsDir($themeDir);
        if ($componentDirs === array()) {
            return true;
        }
        if (!is_dir($destRoot)) {
            if (!@mkdir($destRoot, 0755, true) && !is_dir($destRoot)) {
                return false;
            }
        }
        $allow = null;
        if ($onlyIds !== null) {
            $allow = array();
            foreach ($onlyIds as $id) {
                $allow[(string) $id] = true;
            }
        }
        $ok = true;
        foreach ($componentDirs as $id => $compDir) {
            if ($id === '' || ($allow !== null && !isset($allow[$id]))) {
                continue;
            }
            $dest = $destRoot . $id . '/';
            if (!is_dir($dest)) {
                if (!@mkdir($dest, 0755, true) && !is_dir($dest)) {
                    $ok = false;
                    continue;
                }
            }
            // 受保护组件：即使 upgrade=true，已存在文件也不覆盖（只补缺）
            $compUpgrade = $upgrade;
            if ($upgrade && $this->isProtectedComponent($id)) {
                $compUpgrade = false;
            }
            $dh = @opendir($compDir);
            if (!$dh) {
                $ok = false;
                continue;
            }
            while (($f = readdir($dh)) !== false) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $from = $compDir . '/' . $f;
                if (is_dir($from)) {
                    // fixtures 仅编辑器预览用，不进主题；其余子目录（icons/assets/decoration…）整包同步
                    if ($f === 'fixtures') {
                        continue;
                    }
                    $ok = $this->syncComponentResourceDir($from, $dest . $f . '/', $compUpgrade) && $ok;
                    continue;
                }
                $to = $dest . $f;
                if (!$compUpgrade && is_file($to)) {
                    continue;
                }
                if (@copy($from, $to) === false) {
                    $ok = false;
                }
            }
            closedir($dh);
        }
        return $ok;
    }

    /**
     * 递归同步组件资源子目录（icons / assets / decoration 等）
     * @param string $srcDir
     * @param string $destDir
     * @param bool   $upgrade
     * @return bool
     */
    private function syncComponentResourceDir($srcDir, $destDir, $upgrade = false)
    {
        $srcDir = rtrim((string) $srcDir, '/') . '/';
        $destDir = rtrim((string) $destDir, '/') . '/';
        if (!is_dir($srcDir)) {
            return true;
        }
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return false;
            }
        }
        $ok = true;
        $dh = @opendir($srcDir);
        if (!$dh) {
            return false;
        }
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $from = $srcDir . $f;
            $to = $destDir . $f;
            if (is_dir($from)) {
                $ok = $this->syncComponentResourceDir($from, $to . '/', $upgrade) && $ok;
                continue;
            }
            if (!is_file($from)) {
                continue;
            }
            if (!$upgrade && is_file($to)) {
                continue;
            }
            if (@copy($from, $to) === false) {
                $ok = false;
            }
        }
        closedir($dh);
        return $ok;
    }

    /** @deprecated 保留别名；请用 syncComponentResourceDir */
    private function syncComponentIconsDir($srcDir, $destDir, $upgrade = false)
    {
        return $this->syncComponentResourceDir($srcDir, $destDir, $upgrade);
    }

    /** 发布时移除主题内未使用的 page-builder-components 子目录（保留 _shared） */
    private function pruneThemeComponents($themeDir, array $keepIds)
    {
        $destRoot = $this->themeComponentsDir($themeDir);
        if (!is_dir($destRoot)) {
            return true;
        }
        $keep = array('_shared' => true);
        foreach (array_merge($keepIds, $this->implicitShellComponentIds()) as $id) {
            $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
            if ($id !== '') {
                $keep[$id] = true;
            }
        }
        // 主题声明的本地保留组件 + 受保护组件目录，prune 时不删
        if ($this->syncProtect !== null && !empty($this->syncProtect['enabled'])) {
            foreach (array('keep_local', 'components') as $key) {
                if (empty($this->syncProtect[$key]) || !is_array($this->syncProtect[$key])) {
                    continue;
                }
                foreach ($this->syncProtect[$key] as $id => $_true) {
                    $id = preg_replace('/[^a-z0-9_-]/i', '', (string) $id);
                    if ($id !== '') {
                        $keep[$id] = true;
                    }
                }
            }
        }
        $ok = true;
        foreach (glob($destRoot . '*', GLOB_ONLYDIR) as $dir) {
            $id = basename($dir);
            if ($id === '' || isset($keep[$id])) {
                continue;
            }
            if (!$this->removeThemeComponentDir($dir)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** 递归删除主题内单个 page-builder-components 子目录 */
    private function removeThemeComponentDir($dir)
    {
        if (!is_dir($dir)) {
            return true;
        }
        $ok = true;
        foreach (glob(rtrim((string) $dir, '/') . '/*') as $f) {
            if (is_dir($f)) {
                if (!$this->removeThemeComponentDir($f)) {
                    $ok = false;
                }
                continue;
            }
            if (is_file($f) && @unlink($f) === false) {
                $ok = false;
            }
        }
        if (@rmdir($dir) === false) {
            $ok = false;
        }
        return $ok;
    }

    private function walkPageForComponentIds(array $blocks, &$components)
    {
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            $props = isset($b['props']) && is_array($b['props']) ? $b['props'] : array();
            $this->registerPageComponentId($comp, $props, $components);

            if (isset($b['slots']) && is_array($b['slots'])) {
                foreach ($b['slots'] as $children) {
                    if (is_array($children)) {
                        $this->walkPageForComponentIds($children, $components);
                    }
                }
            }
        }
    }

    private function registerPageComponentId($comp, array $props, &$components)
    {
        $comp = (string) $comp;
        if ($comp === '') {
            return;
        }
        if ($comp === 'content_feed') {
            if (!isset($components['content_feed'])) {
                $components['content_feed'] = 'content_feed';
            }
            $bind = isset($props['bind']) ? (string) $props['bind'] : 'vod';
            $comp = $this->bindToListComponent($bind);
        } elseif ($comp === 'rank_feed') {
            $style = isset($props['style']) ? (string) $props['style'] : 'panel';
            $comp = ($style === 'inline') ? 'rank_inline' : 'rank_list';
        }
        if (!isset($components[$comp])) {
            $components[$comp] = $comp;
        }
        foreach ($this->componentDependencyIds($comp, $props) as $dep) {
            if ($dep !== '' && !isset($components[$dep])) {
                $components[$dep] = $dep;
            }
        }
    }

    /**
     * 组件模板内 {% include %} 的隐式依赖（须同步到主题，否则预览白屏）。
     *
     * @param string $comp
     * @param array  $props
     * @return string[]
     */
    public function componentDependencyIds($comp, array $props = array())
    {
        $comp = (string) $comp;
        $deps = array();
        if ($comp === 'filter_chips') {
            $deps[] = 'filter_panel';
        }
        if ($comp === 'site_nav') {
            $deps[] = 'notify_bell';
        }
        if (in_array($comp, array('vod_list', 'art_list', 'manga_list', 'actor_list', 'role_list', 'live_list', 'website_list', 'topic_list'), true)) {
            $deps[] = 'empty_state';
            if (!empty($props['paging'])) {
                $deps[] = 'paging';
            }
        } elseif ($comp === 'content_feed') {
            $merged = array_merge($this->defaultPropsForComponent('content_feed'), $props);
            $listComp = $this->bindToListComponent(isset($merged['bind']) ? (string) $merged['bind'] : 'vod');
            foreach ($this->componentDependencyIds($listComp, $merged) as $dep) {
                $deps[] = $dep;
            }
        } elseif ($comp === 'gbook_list') {
            $deps[] = 'empty_state';
            if (!empty($props['paging'])) {
                $deps[] = 'paging';
            }
        } elseif (in_array($comp, array('media_related_panel', 'manga_player', 'novel_player'), true)) {
            $deps[] = 'empty_state';
        } elseif ($comp === 'member_shell') {
            $deps[] = 'member_nav';
            $deps[] = 'member_hub_menu';
            $deps[] = 'member_profile_card';
        } elseif ($comp === 'np2_play_shell') {
            $deps[] = 'vod_player';
        } elseif ($comp === 'np2_rank_board') {
            $deps[] = 'rank_list';
        } elseif ($comp === 'rank_theme_boards') {
            $deps[] = 'np2_rank_board';
            foreach ($this->componentDependencyIds('np2_rank_board', $props) as $dep) {
                $deps[] = $dep;
            }
        }
        return $deps;
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

    private function walkBlocksForAssets($themeDir, $blocks, &$components, &$peerStyles)
    {
        if (!is_array($blocks)) {
            return;
        }
        foreach ($blocks as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            if ($this->isPackagedInTheme($themeDir, $comp)) {
                if (!isset($components[$comp])) {
                    $components[$comp] = $comp;
                    $meta = $this->componentMeta($themeDir, $comp);
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
            if (isset($b['slots']) && is_array($b['slots'])) {
                foreach ($b['slots'] as $children) {
                    if (is_array($children)) {
                        $this->walkBlocksForAssets($themeDir, $children, $components, $peerStyles);
                    }
                }
            }
        }
    }

    private function writeThemeSyncedVersion($themeDir, $version)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        return @file_put_contents($themeDir . self::VERSION_FILE, (string) (int) $version) !== false;
    }

    private function readJsonFile($path)
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : null;
    }

    private function hydrateBlockLangKeys($block, $lang)
    {
        if (!is_array($block)) {
            return array();
        }
        $out = $block;
        if (isset($out['props']) && is_array($out['props'])) {
            $props = $out['props'];
            if (isset($props['title_lang_key'])) {
                $key = (string) $props['title_lang_key'];
                $props['title'] = (string) $lang($key);
                unset($props['title_lang_key']);
            }
            $out['props'] = $props;
        }
        if (isset($out['slots']) && is_array($out['slots'])) {
            $slots = array();
            foreach ($out['slots'] as $sk => $children) {
                if (!is_array($children)) {
                    continue;
                }
                $list = array();
                foreach ($children as $child) {
                    $list[] = $this->hydrateBlockLangKeys($child, $lang);
                }
                $slots[(string) $sk] = $list;
            }
            $out['slots'] = $slots;
        }
        return $out;
    }

    /**
     * 主题 draft 首页是否已切到 app_top_chrome（全站顶栏策略以此为准）。
     *
     * @param array $cfg draft.json 根对象
     * @return bool
     */
    public function themeConfigUsesAppTopChrome(array $cfg)
    {
        if (isset($cfg['attributes']['preset_id'])
            && class_exists('\app\common\util\ThemePreset')
            && \app\common\util\ThemePreset::isNp2Preset($cfg['attributes']['preset_id'])) {
            return true;
        }
        $home = null;
        if (isset($cfg['pages']['index/index']) && is_array($cfg['pages']['index/index'])) {
            $home = $cfg['pages']['index/index'];
        } elseif (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $home = $cfg['homepage']['page'];
        }
        if (!is_array($home)) {
            return false;
        }
        if ($this->pageRegionHasComponent($home, 'site_header', 'app_top_chrome')) {
            return true;
        }
        $content = isset($home['regions']['content']) && is_array($home['regions']['content'])
            ? $home['regions']['content'] : array();
        if (empty($content) && isset($home['blocks']) && is_array($home['blocks'])) {
            $content = $home['blocks'];
        }
        foreach ($content as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'app_top_chrome') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array  $page
     * @param string $region
     * @param string $compId
     * @return bool
     */
    public function pageRegionHasComponent(array $page, $region, $compId)
    {
        $region = (string) $region;
        $compId = (string) $compId;
        if (!isset($page['regions'][$region]) || !is_array($page['regions'][$region])) {
            return false;
        }
        foreach ($page['regions'][$region] as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === $compId) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否将 app_top_chrome 放在 site_header（preset-np2 或页面已声明）。
     *
     * @param array  $page
     * @param string $route
     * @param string $themeDir
     * @return bool
     */
    public function usesSiteHeaderChromeMode(array $page, $route = '', $themeDir = '')
    {
        if ($this->pageRegionHasComponent($page, 'site_header', 'app_top_chrome')) {
            return true;
        }
        $lc = isset($page['layout_compile']) && is_array($page['layout_compile']) ? $page['layout_compile'] : array();
        if (!empty($lc['site_header'])) {
            return true;
        }
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir !== '/' && $themeDir !== '' && class_exists('\app\common\util\ThemePreset')) {
            $scaffold = \app\common\util\ThemePreset::scaffoldFlags($themeDir);
            if (\app\common\util\ThemePreset::usesSiteHeaderAppChrome($scaffold)) {
                return true;
            }
        }
        return false;
    }

    /**
     * NP2 等自管顶栏：清空 site_header/hero/channel，content 首部插入 app_top_chrome。
     *
     * @param string $route
     * @param array  $page
     * @return array
     */
    public function applyAppTopChromeLayout($route, array $page, $themeDir = '')
    {
        $route = PageRegistry::normalizeRoute($route);
        if ($this->pageContentHasComponent($page, 'np2_play_shell')) {
            return $this->applyNp2PlayShellLayout($page);
        }
        if (PageRegistry::isImmersiveRoute($route) || PageRegistry::isAuthPageRoute($route)) {
            return $page;
        }
        $siteHeaderMode = $this->usesSiteHeaderChromeMode($page, $route, $themeDir);
        if (!isset($page['regions']) || !is_array($page['regions'])) {
            $page['regions'] = array();
        }
        $legacyChrome = array('site_nav', 'header_decor', 'channel_strip');
        $content = isset($page['regions']['content']) && is_array($page['regions']['content'])
            ? $page['regions']['content'] : array();
        $topChrome = null;
        $rest = array();
        foreach ($content as $b) {
            if (!is_array($b) || empty($b['component'])) {
                continue;
            }
            $comp = (string) $b['component'];
            if ($comp === 'app_top_chrome' && $topChrome === null) {
                $topChrome = $b;
                continue;
            }
            if (in_array($comp, $legacyChrome, true)) {
                continue;
            }
            $rest[] = $b;
        }
        if ($topChrome === null) {
            $headerBlocks = isset($page['regions']['site_header']) && is_array($page['regions']['site_header'])
                ? $page['regions']['site_header'] : array();
            foreach ($headerBlocks as $b) {
                if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'app_top_chrome') {
                    $topChrome = $b;
                    break;
                }
            }
        }
        if ($topChrome === null) {
            $topChrome = array(
                'component' => 'app_top_chrome',
                'props' => array(
                    'tab' => $this->appTopChromeTabForRoute($route, $page),
                    'embed_search' => $this->appTopChromeEmbedSearchForRoute($route),
                ),
            );
        } else {
            if (!isset($topChrome['props']) || !is_array($topChrome['props'])) {
                $topChrome['props'] = array();
            }
            if (empty($topChrome['props']['tab'])) {
                $topChrome['props']['tab'] = $this->appTopChromeTabForRoute($route, $page);
            }
            if (!array_key_exists('embed_search', $topChrome['props'])) {
                $topChrome['props']['embed_search'] = $this->appTopChromeEmbedSearchForRoute($route);
            }
        }
        $tab = $this->appTopChromeTabForRoute($route, $page);
        if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
            $page['twig_sets'] = array();
        }
        $page['twig_sets']['np2_tab'] = $tab;

        if ($siteHeaderMode) {
            $page['regions']['site_header'] = array($topChrome);
            $page['regions']['content'] = $rest;
            $page['blocks'] = $rest;
            $page['chrome'] = array();
            $page['chrome_opts'] = array(
                'header_decor' => false,
                'channel_strip' => false,
            );
            $hasFooter = !empty($page['regions']['footer']);
            $lc = isset($page['layout_compile']) && is_array($page['layout_compile']) ? $page['layout_compile'] : array();
            $page['layout_compile'] = array(
                'chrome_fallbacks' => false,
                'site_header' => true,
                'hero' => array_key_exists('hero', $lc) ? (bool) $lc['hero'] : true,
                'channel' => array_key_exists('channel', $lc) ? (bool) $lc['channel'] : false,
                'footer' => $hasFooter,
            );
        } else {
            array_unshift($rest, $topChrome);
            $page['regions']['site_header'] = array();
            $page['regions']['hero'] = array();
            $page['regions']['channel'] = array();
            $page['regions']['content'] = $rest;
            $page['blocks'] = $rest;
            $page['chrome'] = array();
            $page['chrome_opts'] = array(
                'header_decor' => false,
                'channel_strip' => false,
            );
            $hasFooter = !empty($page['regions']['footer']);
            $lc = isset($page['layout_compile']) && is_array($page['layout_compile']) ? $page['layout_compile'] : array();
            $page['layout_compile'] = array(
                'chrome_fallbacks' => false,
                'site_header' => false,
                'hero' => false,
                'channel' => false,
                'footer' => $hasFooter,
            );
        }
        if ($route === 'index/index') {
            $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
            if ($extra === '' || strpos($extra, 'np2-home-page') === false) {
                $page['extra_page_class'] = trim($extra . ' np2-home-page');
            }
        }
        if ($route === 'user/index') {
            $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
            if ($extra === '' || strpos($extra, 'np2-mine-page') === false) {
                $page['extra_page_class'] = trim($extra . ' np2-mine-page');
            }
        }
        return $page;
    }

    /**
     * NP2 播放页壳层：沉浸式布局，禁止注入 app_top_chrome / 页脚。
     *
     * @param array $page
     * @return array
     */
    public function applyNp2PlayShellLayout(array $page)
    {
        $content = isset($page['regions']['content']) && is_array($page['regions']['content'])
            ? $page['regions']['content'] : array();
        $shell = null;
        foreach ($content as $b) {
            if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'np2_play_shell') {
                $shell = $b;
                break;
            }
        }
        if ($shell === null) {
            $shell = array(
                'component' => 'np2_play_shell',
                'props' => $this->defaultPropsForComponent('np2_play_shell'),
            );
        }
        $page['regions']['content'] = array($shell);
        $page['blocks'] = array($shell);
        $page['regions']['site_header'] = array();
        $page['regions']['hero'] = array();
        $page['regions']['channel'] = array();
        $page['regions']['footer'] = array();
        $page['layout_mod'] = 'inner';
        $page['layout_compile'] = array(
            'chrome_fallbacks' => false,
            'site_header' => false,
            'hero' => false,
            'channel' => false,
            'footer' => false,
        );
        $page['chrome'] = array();
        $page['chrome_opts'] = array(
            'header_decor' => false,
            'channel_strip' => false,
        );
        if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
            $page['twig_sets'] = array();
        }
        $page['twig_sets']['np2_tab'] = 'none';
        $page['twig_sets']['np2_hide_search'] = true;
        $page['twig_sets']['np2_top_over'] = true;
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if ($extra === '' || strpos($extra, 'np2-play-page') === false) {
            $page['extra_page_class'] = trim($extra . ' np2-inner-page np2-play-page');
        }
        if (empty($page['title_twig'])) {
            $page['title_twig'] = "{{ obj.vod_name|default('播放')|replace({'[FAILOVER测试]': ''}) }}";
        }
        return $page;
    }

    /**
     * @param array  $page
     * @param string $compId
     * @return bool
     */
    private function pageContentHasComponent(array $page, $compId)
    {
        $compId = (string) $compId;
        $lists = array();
        if (isset($page['regions']['content']) && is_array($page['regions']['content'])) {
            $lists[] = $page['regions']['content'];
        }
        if (isset($page['blocks']) && is_array($page['blocks'])) {
            $lists[] = $page['blocks'];
        }
        foreach ($lists as $blocks) {
            foreach ($blocks as $b) {
                if (is_array($b) && isset($b['component']) && (string) $b['component'] === $compId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $route
     * @param array  $page
     * @return string
     */
    public function appTopChromeTabForRoute($route, array $page = array())
    {
        if (isset($page['twig_sets']['np2_tab']) && is_scalar($page['twig_sets']['np2_tab'])) {
            $tab = trim((string) $page['twig_sets']['np2_tab']);
            if ($tab !== '') {
                return $tab;
            }
        }
        $route = PageRegistry::normalizeRoute($route);
        if ($route === 'label/rank') {
            return 'rank';
        }
        if ($route === 'label/short') {
            return 'short';
        }
        if (strpos($route, 'user/') === 0) {
            return 'mine';
        }
        if (strpos($route, 'manga/') === 0) {
            return 'manga';
        }
        if (strpos($route, 'art/') === 0) {
            return 'art';
        }
        if (in_array($route, array('vod/play', 'vod/detail', 'manga/detail', 'art/detail'), true)) {
            return 'none';
        }
        return 'home';
    }

    /**
     * @param string $route
     * @return bool
     */
    public function appTopChromeEmbedSearchForRoute($route)
    {
        return PageRegistry::normalizeRoute($route) === 'vod/search';
    }
}
