<?php

namespace app\common\util;

/**
 * 站点框架（navbar / footer / attributes）单一真相源迁移：
 * - 从各页 chrome 提升到 attributes（logo、copyright）
 * - 从各页 regions/chrome 剥离顶栏页脚，仅保留 content / sidebar
 * - 首页 draft 归一为 blocks 形态（向导与 PB 共用）
 */
class ThemeDesignDraftMigrator
{
    /**
     * @param array $cfg draft.json 根对象
     * @return array [ 'cfg' => array, 'changed' => bool, 'notes' => string[] ]
     */
    public static function migrate(array $cfg)
    {
        $notes = array();
        $cfg = PageRegistry::migrateConfigPages($cfg);
        // 编辑器保存会带回 regions/chrome；与 normalize 后语义相同时不应反复触发 writeArtifacts
        $before = self::stableCfgJson($cfg);

        if (!isset($cfg['attributes']) || !is_array($cfg['attributes'])) {
            $cfg['attributes'] = array();
        }
        if (!isset($cfg['navbar']) || !is_array($cfg['navbar'])) {
            $cfg['navbar'] = array('type_ids' => array());
        }
        if (!isset($cfg['footer']) || !is_array($cfg['footer'])) {
            $cfg['footer'] = array('links' => array());
        }
        if (!isset($cfg['footer']['links']) || !is_array($cfg['footer']['links'])) {
            $cfg['footer']['links'] = array();
        }

        $logo = isset($cfg['attributes']['logo']) ? trim((string) $cfg['attributes']['logo']) : '';
        $copy = isset($cfg['attributes']['footer_copyright']) ? trim((string) $cfg['attributes']['footer_copyright']) : '';
        $navIds = isset($cfg['navbar']['type_ids']) && is_array($cfg['navbar']['type_ids'])
            ? $cfg['navbar']['type_ids'] : array();

        $pagesToScan = array();
        if (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $pagesToScan[] = $cfg['homepage']['page'];
        }
        if (isset($cfg['pages']) && is_array($cfg['pages'])) {
            foreach ($cfg['pages'] as $page) {
                if (is_array($page)) {
                    $pagesToScan[] = $page;
                }
            }
        }

        foreach ($pagesToScan as $page) {
            if ($logo === '') {
                $found = self::extractNavProp($page, 'logo');
                if ($found !== '') {
                    $logo = $found;
                }
            }
            if ($copy === '') {
                $found = self::extractFooterProp($page, 'copyright');
                if ($found !== '') {
                    $copy = $found;
                }
            }
            if (empty($navIds)) {
                $foundIds = self::extractNavTypeIds($page);
                if (!empty($foundIds)) {
                    $navIds = $foundIds;
                }
            }
        }

        if ($logo !== '' && empty($cfg['attributes']['logo'])) {
            $cfg['attributes']['logo'] = $logo;
            $notes[] = 'promoted logo to attributes';
        }
        if ($copy !== '' && empty($cfg['attributes']['footer_copyright'])) {
            $cfg['attributes']['footer_copyright'] = $copy;
            $notes[] = 'promoted footer_copyright to attributes';
        }
        if (empty($cfg['navbar']['type_ids']) && !empty($navIds)) {
            $cfg['navbar']['type_ids'] = $navIds;
            $notes[] = 'promoted navbar.type_ids from page chrome';
        }

        if (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $normalized = self::normalizeHomepageDraft($cfg['homepage']['page']);
            if (json_encode($normalized) !== json_encode($cfg['homepage']['page'])) {
                $cfg['homepage']['page'] = $normalized;
                $notes[] = 'normalized homepage.page (blocks only, no chrome)';
            }
        }

        if (isset($cfg['pages']) && is_array($cfg['pages'])) {
            foreach ($cfg['pages'] as $route => $page) {
                if (!is_array($page)) {
                    continue;
                }
                $route = PageRegistry::normalizeRoute($route);
                if ($route === 'index/index' && isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
                    $cfg['pages'][$route] = $cfg['homepage']['page'];
                    continue;
                }
                $stripped = self::stripPageChrome($route, $page);
                if (json_encode($stripped) !== json_encode($page)) {
                    $cfg['pages'][$route] = $stripped;
                    $notes[] = 'stripped chrome from page ' . $route;
                }
            }
        }

        $after = self::stableCfgJson($cfg);
        $changed = ($before !== $after);
        if ($changed) {
            $cfg['updated_at'] = time();
        }

        return array(
            'cfg' => $cfg,
            'changed' => $changed,
            'notes' => $notes,
        );
    }

    /**
     * 用于变更检测：首页归一为 blocks、内页剥离 chrome，避免 enrich/save 冗余字段反复触发全量编译。
     */
    private static function stableCfgJson(array $cfg)
    {
        $cfg = PageRegistry::migrateConfigPages($cfg);
        if (isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $cfg['homepage']['page'] = self::normalizeHomepageDraft($cfg['homepage']['page']);
        }
        if (isset($cfg['pages']) && is_array($cfg['pages'])) {
            foreach ($cfg['pages'] as $route => $page) {
                if (!is_array($page)) {
                    continue;
                }
                $route = PageRegistry::normalizeRoute($route);
                if ($route === 'index/index' && isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
                    $cfg['pages'][$route] = $cfg['homepage']['page'];
                } else {
                    $cfg['pages'][$route] = self::stripPageChrome($route, $page);
                }
            }
        }
        return json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 迁移 draft 并同步 theme-design.draft.json。
     * @param string $themeDir template/<theme>/ 绝对路径
     * @param array  $cfg
     * @return array { cfg, changed, notes, theme_json_synced }
     */
    public static function migrateAndSync($themeDir, array $cfg)
    {
        $result = self::migrate($cfg);
        $synced = self::syncThemeDesignDraftJson($themeDir, $result['cfg']);
        if ($synced) {
            $result['notes'][] = 'synced theme-design.draft.json';
        }
        $result['theme_json_synced'] = $synced;
        return $result;
    }

    /**
     * 生成 template/<theme>/theme-design[.draft].json 内容（与 ThemeDesign::writeDesignJson 一致）。
     * @param array $cfg
     * @return array
     */
    public static function buildThemeDesignJson(array $cfg)
    {
        $attr = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : array();
        return array(
            'navbar' => isset($cfg['navbar']) ? $cfg['navbar'] : array('type_ids' => array()),
            'footer' => isset($cfg['footer']) ? $cfg['footer'] : array('links' => array()),
            'homepage' => isset($cfg['homepage']) ? $cfg['homepage'] : array(),
            'logo' => isset($attr['logo']) ? (string) $attr['logo'] : '',
            'footer_copyright' => isset($attr['footer_copyright']) ? (string) $attr['footer_copyright'] : '',
            'default_avatar' => isset($attr['default_avatar']) ? (string) $attr['default_avatar'] : '',
        );
    }

    /**
     * 将 draft.json 中的 attributes 同步到 theme-design.draft.json（修复 logo 不显示）。
     * @param string $themeDir 绝对路径 template/<theme>/
     * @param array  $cfg draft 根对象
     * @return bool
     */
    public static function syncThemeDesignDraftJson($themeDir, array $cfg)
    {
        $themeDir = rtrim(str_replace('\\', '/', (string) $themeDir), '/') . '/';
        if ($themeDir === '/' || !is_dir($themeDir)) {
            return false;
        }
        $data = self::buildThemeDesignJson($cfg);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $file = $themeDir . 'theme-design.draft.json';
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false && $raw === $json) {
                return false;
            }
        }
        return @file_put_contents($file, $json) !== false;
    }

    /** 首页：保留 blocks + 元数据，去掉 regions/chrome/footer 冗余 */
    public static function normalizeHomepageDraft(array $page)
    {
        $blocks = array();
        if (isset($page['blocks']) && is_array($page['blocks']) && !empty($page['blocks'])) {
            $blocks = $page['blocks'];
        } elseif (isset($page['regions']['content']) && is_array($page['regions']['content'])) {
            $blocks = $page['regions']['content'];
        }

        $out = array(
            'template' => isset($page['template']) ? (string) $page['template'] : 'index/index',
            'layout' => isset($page['layout']) ? (string) $page['layout'] : 'base',
            'blocks' => $blocks,
        );
        if (isset($page['layout_mod']) && (string) $page['layout_mod'] !== '') {
            $out['layout_mod'] = (string) $page['layout_mod'];
        }
        if (isset($page['page_type']) && (string) $page['page_type'] !== '') {
            $out['page_type'] = (string) $page['page_type'];
        }
        if (isset($page['chrome_opts']) && is_array($page['chrome_opts']) && !empty($page['chrome_opts'])) {
            $out['chrome_opts'] = $page['chrome_opts'];
        }
        return $out;
    }

    /** 内页：regions 只保留 content / sidebar，去掉 chrome 与 legacy chrome/footer 字段 */
    public static function stripPageChrome($route, array $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        $regions = isset($page['regions']) && is_array($page['regions']) ? $page['regions'] : array();
        $content = isset($regions['content']) && is_array($regions['content'])
            ? $regions['content']
            : (isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : array());
        $sidebar = isset($regions['sidebar']) && is_array($regions['sidebar']) ? $regions['sidebar'] : array();

        if (PageRegistry::isImmersiveRoute($route)) {
            $content = $content;
            $sidebar = array();
        }

        $out = array(
            'template' => isset($page['template']) ? (string) $page['template'] : $route,
            'layout' => isset($page['layout']) ? (string) $page['layout'] : 'base',
            'layout_mod' => isset($page['layout_mod']) ? (string) $page['layout_mod'] : '',
            'page_type' => isset($page['page_type']) ? (string) $page['page_type'] : '',
            'regions' => array(
                'site_header' => array(),
                'hero' => array(),
                'channel' => array(),
                'content' => $content,
                'sidebar' => $sidebar,
                'footer' => array(),
            ),
            'blocks' => $content,
        );
        if (isset($page['chrome_opts']) && is_array($page['chrome_opts']) && !empty($page['chrome_opts'])) {
            $out['chrome_opts'] = $page['chrome_opts'];
        }
        return $out;
    }

    private static function extractNavProp(array $page, $prop)
    {
        $lists = self::chromeBlockLists($page);
        foreach ($lists as $list) {
            foreach ($list as $block) {
                if (!is_array($block) || empty($block['component']) || $block['component'] !== 'site_nav') {
                    continue;
                }
                if (isset($block['props'][$prop]) && trim((string) $block['props'][$prop]) !== '') {
                    return trim((string) $block['props'][$prop]);
                }
            }
        }
        return '';
    }

    private static function extractFooterProp(array $page, $prop)
    {
        if (isset($page['footer']['props'][$prop]) && trim((string) $page['footer']['props'][$prop]) !== '') {
            return trim((string) $page['footer']['props'][$prop]);
        }
        if (isset($page['regions']['footer']) && is_array($page['regions']['footer'])) {
            foreach ($page['regions']['footer'] as $block) {
                if (is_array($block) && !empty($block['component']) && $block['component'] === 'site_footer'
                    && isset($block['props'][$prop]) && trim((string) $block['props'][$prop]) !== '') {
                    return trim((string) $block['props'][$prop]);
                }
            }
        }
        return '';
    }

    private static function extractNavTypeIds(array $page)
    {
        $lists = self::chromeBlockLists($page);
        foreach ($lists as $list) {
            foreach ($list as $block) {
                if (!is_array($block) || empty($block['component'])) {
                    continue;
                }
                if ($block['component'] !== 'site_nav' && $block['component'] !== 'channel_strip') {
                    continue;
                }
                if (isset($block['props']['type_ids']) && is_array($block['props']['type_ids']) && !empty($block['props']['type_ids'])) {
                    return $block['props']['type_ids'];
                }
            }
        }
        return array();
    }

    private static function chromeBlockLists(array $page)
    {
        $lists = array();
        if (isset($page['chrome']) && is_array($page['chrome'])) {
            $lists[] = $page['chrome'];
        }
        if (isset($page['regions']) && is_array($page['regions'])) {
            foreach (array('site_header', 'hero', 'channel') as $region) {
                if (isset($page['regions'][$region]) && is_array($page['regions'][$region])) {
                    $lists[] = $page['regions'][$region];
                }
            }
        }
        return $lists;
    }
}
