<?php
namespace app\common\util;

/**
 * Page Builder 可编辑页面注册表（twigspike / pb-sandbox）。
 * 路由键格式：module/action，如 vod/type、index/index。
 */
class PageRegistry
{
    const REGION_NAMES = array('site_header', 'hero', 'channel', 'content', 'sidebar', 'footer');

    /** Page Builder 虚拟路由：全站登录弹窗配置（非前台 URL） */
    const AUTH_MODAL_VIRTUAL_ROUTE = '__auth_modal__';

    /**
     * 允许在 Page Builder 中编辑的会员/认证路由（固定结构 + trait 编辑）。
     *
     * @var string[]
     */
    const MEMBER_DESIGNABLE_ROUTES = array(
        'user/index',
        'user/notify',
        'user/upgrade',
        'user/buy',
        'user/orders',
        'user/cards',
        'user/benefits',
        'user/favs',
        'user/plays',
        'user/info',
        'user/login',
        'user/findpass',
    );

    /** 认证整页路由（layout=auth，无顶栏页脚） */
    const AUTH_PAGE_ROUTES = array(
        'user/login',
        'user/findpass',
        'user/findpass_msg',
    );

    /**
     * 与另一认证路由共用 PB 配置（仅编译产物路由不同，panel 由路由决定）。
     * @var array<string,string>
     */
    const AUTH_PAGE_CONFIG_ALIASES = array(
        'user/findpass_msg' => 'user/findpass',
    );

    /** PB 页面选择器中文 label（route => label） */
    const PICKER_ROUTE_LABELS = array(
        'user/index' => '个人中心',
        'user/notify' => '消息中心',
        'user/upgrade' => '充值中心',
        'user/buy' => '积分充值',
        'user/orders' => '充值记录',
        'user/cards' => '卡密充值',
        'user/benefits' => '会员权益',
        'user/favs' => '我的收藏',
        'user/plays' => '播放记录',
        'user/info' => '资料修改',
        'user/login' => '登录注册页',
        'user/findpass' => '找回密码',
        self::AUTH_MODAL_VIRTUAL_ROUTE => '全站 · 登录弹窗',
    );

    /** 已注册但无需 Page Builder 编排的 page_type */
    const NON_DESIGNABLE_PAGE_TYPES = array(
        'gate',   // 版权提示、密码门、确认页
        'map',    // 网站地图（Wave D 未落地）
    );

    /**
     * 已注册但无需 Page Builder 编排的个别路由。
     * 薄功能页、无 kit 组件支撑的播放页、低频子模块等。
     */
    const NON_DESIGNABLE_ROUTES = array(
        'vod/search_hub',  // 移动端搜索落地（仅表单跳转）
        'vod/down',          // 下载地址页（固定选集逻辑）
        'comment/index',     // 评论列表（Wave D 未落地）
        'live/index',        // 直播分类筛选（T0 固定页）
        'live/play',         // 直播播放（live_player 未进 kit）
        'plot/index',
        'plot/detail',
        'special/index',
        'special/detail',
        'role/show',
        'role/index',
        'role/detail',
    );

    /** @var array|null */
    private static $routes = null;

    /**
     * @return array<string,array{route:string,page_type:string,group:string,preview:array,module:string,action:string}>
     */
    public static function routes()
    {
        if (self::$routes !== null) {
            return self::$routes;
        }
        $list = array(
            array('index/index', 'home', '首页', array()),
            array('vod/type', 'list', '视频', array('id' => 1)),
            array('vod/show', 'list', '视频', array('id' => 1)),
            array('vod/search', 'list', '视频', array('wd' => 'test')),
            array('vod/search_hub', 'list', '视频', array()),
            array('vod/index', 'list', '视频', array()),
            array('vod/detail', 'detail', '视频', array('id' => 1)),
            array('vod/down', 'detail', '视频', array('id' => 1)),
            array('vod/copyright', 'gate', '视频', array('id' => 1)),
            array('vod/play', 'play', '视频', array('id' => 1, 'sid' => 1, 'nid' => 1)),
            array('vod/confirm', 'gate', '视频', array('id' => 1)),
            array('vod/detail_pwd', 'gate', '视频', array('id' => 1)),
            array('vod/player_pwd', 'gate', '视频', array('id' => 1)),
            array('art/type', 'list', '文章', array('id' => 1)),
            array('art/show', 'list', '文章', array('id' => 1)),
            array('art/index', 'list', '文章', array()),
            array('art/search', 'list', '文章', array('wd' => 'test')),
            array('art/detail', 'detail', '文章', array('id' => 1)),
            array('art/detail_pwd', 'gate', '文章', array('id' => 1)),
            array('art/read', 'read', '文章', array('id' => 1)),
            array('art/confirm', 'gate', '文章', array('id' => 1)),
            array('manga/type', 'list', '漫画', array('id' => 1)),
            array('manga/show', 'list', '漫画', array('id' => 1)),
            array('manga/index', 'list', '漫画', array()),
            array('manga/detail', 'detail', '漫画', array('id' => 1)),
            array('manga/play', 'play', '漫画', array('id' => 1)),
            array('manga/detail_pwd', 'gate', '漫画', array('id' => 1)),
            array('manga/confirm', 'gate', '漫画', array('id' => 1)),
            array('actor/type', 'list', '演员', array('id' => 1)),
            array('actor/show', 'list', '演员', array('id' => 1)),
            array('actor/index', 'list', '演员', array()),
            array('actor/detail', 'detail', '演员', array('id' => 1)),
            array('role/show', 'list', '角色', array()),
            array('role/index', 'list', '角色', array()),
            array('role/detail', 'detail', '角色', array('id' => 1)),
            array('website/type', 'list', '网址', array('id' => 1)),
            array('website/show', 'list', '网址', array('id' => 1)),
            array('website/index', 'list', '网址', array()),
            array('website/detail', 'detail', '网址', array('id' => 1)),
            array('topic/index', 'list', '专题', array()),
            array('topic/detail', 'detail', '专题', array('id' => 1)),
            array('plot/index', 'list', '剧情', array()),
            array('plot/detail', 'detail', '剧情', array('id' => 1)),
            array('live/show', 'list', '直播', array()),
            array('live/index', 'list', '直播', array()),
            array('live/play', 'play', '直播', array('id' => 1)),
            array('special/index', 'list', '专题页', array()),
            array('special/detail', 'detail', '专题页', array('id' => 1)),
            array('map/index', 'map', '地图', array()),
            array('comment/index', 'list', '评论', array()),
            array('label/rank', 'rank', '排行', array()),
            array('gbook/index', 'gbook', '留言', array()),
            array('user/index', 'member', '会员', array()),
            array('user/info', 'member', '会员', array()),
            array('user/login', 'member', '会员', array()),
            array('user/reg', 'member', '会员', array()),
            array('user/findpass', 'member', '会员', array()),
            array('user/findpass_msg', 'member', '会员', array('ac' => 'email')),
            array('user/favs', 'member', '会员', array()),
            array('user/plays', 'member', '会员', array()),
            array('user/plog', 'member', '会员', array()),
            array('user/downs', 'member', '会员', array()),
            array('user/upgrade', 'member', '会员', array()),
            array('user/buy', 'member', '会员', array()),
            array('user/pay', 'member', '会员', array()),
            array('user/payment_weixin', 'member', '会员', array()),
            array('user/orders', 'member', '会员', array()),
            array('user/benefits', 'member', '会员', array()),
            array('user/popedom', 'member', '会员', array()),
            array('user/checkin', 'member', '会员', array()),
            array('user/invite', 'member', '会员', array()),
            array('user/cards', 'member', '会员', array()),
            array('user/cash', 'member', '会员', array()),
            array('user/reward', 'member', '会员', array()),
            array('user/bind', 'member', '会员', array()),
            array('user/unbind', 'member', '会员', array()),
            array('user/mall', 'member', '会员', array()),
            array('user/mall_orders', 'member', '会员', array()),
            array('user/notify', 'member', '会员', array()),
            array('user/dynamics', 'member', '会员', array()),
        );

        self::$routes = array();
        foreach ($list as $row) {
            $route = $row[0];
            $parts = explode('/', $route, 2);
            self::$routes[$route] = array(
                'route' => $route,
                'page_type' => $row[1],
                'group' => $row[2],
                'preview' => array('params' => isset($row[3]) && is_array($row[3]) ? $row[3] : array()),
                'module' => isset($parts[0]) ? $parts[0] : '',
                'action' => isset($parts[1]) ? $parts[1] : '',
            );
        }
        return self::$routes;
    }

    /**
     * @param string $route
     * @return array|null
     */
    public static function get($route)
    {
        $route = self::normalizeRoute($route);
        $all = self::routes();
        return isset($all[$route]) ? $all[$route] : null;
    }

    /** 路由是否在注册表中（含 gate 等系统页，供预览参数等元数据使用） */
    public static function isRegistered($route)
    {
        return self::get($route) !== null;
    }

    /**
     * 是否需要在 Page Builder 中可视化编排。
     * gate（版权提示、密码门、确认页等）走固定模板，不提供设计入口。
     */
    public static function isDesignableRoute($route)
    {
        $route = self::normalizeRoute($route);
        if ($route === self::AUTH_MODAL_VIRTUAL_ROUTE) {
            return true;
        }
        $meta = self::get($route);
        if ($meta === null) {
            return false;
        }
        if ($meta['page_type'] === 'member') {
            // 认证整页（login / findpass / findpass_msg）一律可编可脚手架
            if (self::isAuthPageRoute($route)) {
                return true;
            }
            return in_array($route, self::MEMBER_DESIGNABLE_ROUTES, true);
        }
        if (in_array($meta['page_type'], self::NON_DESIGNABLE_PAGE_TYPES, true)) {
            return false;
        }
        if (in_array($meta['route'], self::NON_DESIGNABLE_ROUTES, true)) {
            return false;
        }
        return true;
    }

    /** 是否为 PB 会员壳路由（非认证整页） */
    public static function isMemberShellRoute($route)
    {
        $route = self::normalizeRoute($route);
        return in_array($route, self::MEMBER_DESIGNABLE_ROUTES, true)
            && !in_array($route, self::AUTH_PAGE_ROUTES, true);
    }

    /** 是否为认证整页路由 */
    public static function isAuthPageRoute($route)
    {
        $route = self::normalizeRoute($route);
        return in_array($route, self::AUTH_PAGE_ROUTES, true);
    }

    /** PB 草稿中存储配置的路由（找回密码结果页与找回密码共用） */
    public static function authPageConfigRoute($route)
    {
        $route = self::normalizeRoute($route);
        if (isset(self::AUTH_PAGE_CONFIG_ALIASES[$route])) {
            return self::AUTH_PAGE_CONFIG_ALIASES[$route];
        }
        return $route;
    }

    /** 编译前台模板时 auth_page.panel 由路由决定，不读草稿 panel */
    public static function authPagePanelForRoute($route)
    {
        $route = self::normalizeRoute($route);
        if ($route === 'user/login') {
            return 'login';
        }
        if ($route === 'user/findpass') {
            return 'findpass';
        }
        if ($route === 'user/findpass_msg') {
            return 'findpass_msg';
        }
        return '';
    }

    public static function isAuthModalVirtualRoute($route)
    {
        return self::normalizeRoute($route) === self::AUTH_MODAL_VIRTUAL_ROUTE;
    }

    /**
     * Page Builder 编辑模式：build | member | auth | auth_modal
     *
     * @param string $route
     * @return string
     */
    public static function pageBuilderMode($route)
    {
        $route = self::normalizeRoute($route);
        if ($route === self::AUTH_MODAL_VIRTUAL_ROUTE) {
            return 'auth_modal';
        }
        if (self::isAuthPageRoute($route)) {
            return 'auth';
        }
        if (self::isMemberShellRoute($route)) {
            return 'member';
        }
        return 'build';
    }

    /** 选择器显示 label */
    public static function pickerLabel($route)
    {
        $route = self::normalizeRoute($route);
        if (isset(self::PICKER_ROUTE_LABELS[$route])) {
            return self::PICKER_ROUTE_LABELS[$route];
        }
        return $route;
    }

    /** 是否允许在 Page Builder 中编辑（同 isDesignableRoute） */
    public static function isEditableRoute($route)
    {
        return self::isDesignableRoute($route);
    }

    /**
     * @return array
     */
    public static function regionNames()
    {
        return self::REGION_NAMES;
    }

    /**
     * 按分组列出路由（供后台页面选择器）。
     * @return array<string,array<int,array{route:string,label:string,page_type:string}>>
     */
    public static function groupedForPicker()
    {
        $groups = array();
        foreach (self::routes() as $meta) {
            if (!self::isDesignableRoute($meta['route'])) {
                continue;
            }
            $route = $meta['route'];
            if (self::isAuthPageRoute($route)) {
                $g = '认证';
            } elseif ($meta['page_type'] === 'member') {
                $g = '会员';
            } else {
                $g = $meta['group'];
            }
            if (!isset($groups[$g])) {
                $groups[$g] = array();
            }
            $groups[$g][] = array(
                'route' => $route,
                'label' => self::pickerLabel($route),
                'page_type' => $meta['page_type'],
            );
        }
        if (!isset($groups['全站'])) {
            $groups['全站'] = array();
        }
        $groups['全站'][] = array(
            'route' => self::AUTH_MODAL_VIRTUAL_ROUTE,
            'label' => self::pickerLabel(self::AUTH_MODAL_VIRTUAL_ROUTE),
            'page_type' => 'auth_modal',
        );
        return $groups;
    }

    /**
     * 构建前台预览 URL 路径（不含 query）。
     * @param string $route
     * @return string
     */
    public static function previewPath($route)
    {
        $route = self::normalizeRoute($route);
        if ($route === self::AUTH_MODAL_VIRTUAL_ROUTE) {
            return 'index/index';
        }
        $meta = self::get($route);
        if ($meta === null) {
            return 'index/index';
        }
        if ($route === 'index/index') {
            return 'index/index';
        }
        $params = isset($meta['preview']['params']) ? $meta['preview']['params'] : array();
        if (isset($params['id'])) {
            $params['id'] = self::resolvePreviewId($meta['module'], $meta['action'], (int) $params['id']);
        }
        // 后台预览统一走 index.php 路由，避免伪静态在本地/部分环境下落到首页或 404。
        $path = 'index.php/' . $meta['module'] . '/' . $meta['action'];
        if (is_array($params)) {
            foreach ($params as $key => $val) {
                if ($val === '' || $val === null) {
                    continue;
                }
                $path .= '/' . rawurlencode((string) $key) . '/' . rawurlencode((string) $val);
            }
        }
        return $path;
    }

    /**
     * 详情/播放类预览的内容 id：固定 id=1 在采集站上常常不存在（被删或采集 id 不从 1 开始）→ 前台 404。
     * id=1 仍是已审核、未进回收站的记录则沿用，否则取该模型最新一条可前台访问的记录；播放页还要求有播放/章节地址。
     *
     * @param string $module
     * @param string $action
     * @param int    $default
     * @return int
     */
    private static function resolvePreviewId($module, $action, $default)
    {
        // plot/detail 以视频 id 访问
        $tables = array(
            'vod' => array('vod', 'vod_id', 'vod_status', 'vod_recycle_time', 'vod_play_url'),
            'plot' => array('vod', 'vod_id', 'vod_status', 'vod_recycle_time', ''),
            'art' => array('art', 'art_id', 'art_status', 'art_recycle_time', ''),
            'manga' => array('manga', 'manga_id', 'manga_status', 'manga_recycle_time', 'manga_chapter_url'),
            'actor' => array('actor', 'actor_id', 'actor_status', '', ''),
            'role' => array('role', 'role_id', 'role_status', '', ''),
            'website' => array('website', 'website_id', 'website_status', '', ''),
            'topic' => array('topic', 'topic_id', 'topic_status', '', ''),
        );
        if (!isset($tables[$module])) {
            return $default;
        }
        list($table, $pk, $statusField, $recycleField, $playField) = $tables[$module];
        $where = array($statusField => 1);
        if ($recycleField !== '') {
            $where[$recycleField] = 0;
        }
        if ($playField !== '' && $action === 'play') {
            $where[$playField] = array('neq', '');
        }
        try {
            if ($default > 0 && \think\Db::name($table)->where($where)->where($pk, $default)->value($pk)) {
                return $default;
            }
            $id = (int) \think\Db::name($table)->where($where)->order($pk . ' desc')->value($pk);
        } catch (\Exception $e) {
            return $default;
        }
        return $id > 0 ? $id : $default;
    }

    /**
     * homepage.page → pages['index/index']，并保留 homepage 只读兼容。
     * @param array $cfg
     * @return array
     */
    public static function migrateConfigPages(array $cfg)
    {
        if (!isset($cfg['pages']) || !is_array($cfg['pages'])) {
            $cfg['pages'] = array();
        }
        if (!isset($cfg['pages']['index/index']) && isset($cfg['homepage']['page']) && is_array($cfg['homepage']['page'])) {
            $cfg['pages']['index/index'] = $cfg['homepage']['page'];
        }
        foreach (self::AUTH_PAGE_CONFIG_ALIASES as $aliasRoute => $baseRoute) {
            if (!isset($cfg['pages'][$aliasRoute]) || !is_array($cfg['pages'][$aliasRoute])) {
                continue;
            }
            if (!isset($cfg['pages'][$baseRoute]) || !is_array($cfg['pages'][$baseRoute])) {
                $cfg['pages'][$baseRoute] = $cfg['pages'][$aliasRoute];
            }
            unset($cfg['pages'][$aliasRoute]);
        }
        if (isset($cfg['pages']['user/findpass']) && is_array($cfg['pages']['user/findpass'])) {
            $cfg['pages']['user/findpass'] = self::normalizeStoredAuthPagePanel(
                $cfg['pages']['user/findpass'],
                'findpass'
            );
        }
        return $cfg;
    }

    /**
     * 草稿中 auth_page.panel 固定为设计路由对应值（别名路由配置已合并到 base）。
     *
     * @param array  $page
     * @param string $panel
     * @return array
     */
    private static function normalizeStoredAuthPagePanel(array $page, $panel)
    {
        if (!isset($page['regions']['content']) || !is_array($page['regions']['content'])) {
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
        if (isset($page['blocks']) && is_array($page['blocks'])) {
            $page['blocks'] = $page['regions']['content'];
        }
        return $page;
    }

    public static function normalizeRoute($route)
    {
        $route = str_replace('\\', '/', trim((string) $route, '/'));
        $route = preg_replace('/\.twig$/', '', $route);
        $route = preg_replace('/\.draft$/', '', $route);
        return $route;
    }

    /**
     * 路由 → 主题内 html 相对路径（不含后缀）。
     * @param string $route
     * @return string 如 vod/type
     */
    public static function themeTemplateRoute($route)
    {
        return self::normalizeRoute($route);
    }

    /**
     * 沉浸式阅读/播放页：无站点顶栏与页脚（小说阅读、漫画阅读）。
     * @param string $route
     * @return bool
     */
    public static function isImmersiveRoute($route)
    {
        $route = self::normalizeRoute($route);
        return in_array($route, array('art/read', 'manga/play'), true);
    }

    /**
     * Page Builder 左侧结构树可见区域（仅展示该页实际会编译/渲染的槽位）。
     * @param string $route
     * @return array
     */
    public static function editorRegionNames($route)
    {
        $route = self::normalizeRoute($route);
        if ($route === self::AUTH_MODAL_VIRTUAL_ROUTE) {
            return array();
        }
        if (self::isAuthPageRoute($route)) {
            return array('content');
        }
        if (self::isImmersiveRoute($route)) {
            return array('content');
        }
        if ($route === 'manga/index') {
            return array('site_header', 'hero', 'content', 'sidebar', 'footer');
        }
        if ($route === 'art/index') {
            return array('site_header', 'hero', 'content', 'sidebar', 'footer');
        }
        return self::regionNames();
    }

    /**
     * 沉浸式阅读页：清空除主内容外的六槽，避免草稿残留顶栏/页脚块。
     * @param array $regions
     * @return array
     */
    public static function applyImmersivePageRegions(array $regions)
    {
        $content = isset($regions['content']) && is_array($regions['content']) ? $regions['content'] : array();
        $out = array();
        foreach (self::regionNames() as $name) {
            $out[$name] = ($name === 'content') ? $content : array();
        }
        return $out;
    }

    /**
     * 编译 *.twig 时的 layout 区块开关与 chrome 自动回退策略（按 page_type）。
     * @param string $themeDir 主题根目录
     * @param string $route
     * @param array  $page 含 page_type
     * @param array  $extra 如 components_only
     * @return array
     */
    public static function layoutOptsForCompile($themeDir, $route, array $page = array(), array $extra = array())
    {
        $route = self::normalizeRoute($route);
        $themeDir = rtrim((string) $themeDir, '/') . '/';

        $baseMap = array(
            'site_header' => false,
            'channel' => false,
            'hero' => false,
            'footer' => false,
        );
        $base = $themeDir . 'html/layout/base.twig';
        if (is_file($base)) {
            $raw = @file_get_contents($base);
            if ($raw !== false && $raw !== '') {
                $baseMap['site_header'] = (strpos($raw, 'block site_header') !== false);
                $baseMap['channel'] = (strpos($raw, 'block channel') !== false);
                $baseMap['hero'] = (strpos($raw, 'block hero') !== false);
                $baseMap['footer'] = (strpos($raw, 'block footer') !== false);
            }
        }

        $pageType = isset($page['page_type']) ? strtolower((string) $page['page_type']) : '';
        if ($pageType === '' && $route === 'index/index') {
            $pageType = 'home';
        }
        if ($pageType === '') {
            $meta = self::get($route);
            $pageType = ($meta && isset($meta['page_type'])) ? strtolower((string) $meta['page_type']) : 'list';
        }

        $opts = array(
            'site_header' => false,
            'hero' => false,
            'channel' => false,
            'footer' => false,
            'chrome_fallbacks' => false,
        );

        // 认证整页走 layout/auth，无顶栏页脚；勿按 member 注入 chrome
        if (self::isAuthPageRoute($route)) {
            return array_merge($opts, $extra);
        }

        switch ($pageType) {
            case 'home':
                $opts['site_header'] = $baseMap['site_header'];
                $opts['hero'] = $baseMap['hero'];
                $opts['channel'] = $baseMap['channel'];
                $opts['footer'] = $baseMap['footer'];
                $opts['chrome_fallbacks'] = true;
                break;
            case 'list':
                $opts['site_header'] = $baseMap['site_header'];
                $opts['channel'] = $baseMap['channel'];
                $opts['footer'] = $baseMap['footer'];
                if ($route === 'manga/index') {
                    $opts['hero'] = $baseMap['hero'];
                    $opts['channel'] = false;
                }
                if ($route === 'art/index') {
                    $opts['hero'] = $baseMap['hero'];
                    $opts['channel'] = false;
                }
                break;
            case 'detail':
            case 'play':
            case 'read':
            case 'member':
            case 'rank':
            case 'gbook':
            case 'map':
            case 'gate':
            case 'special':
                if (self::isImmersiveRoute($route)) {
                    $opts['site_header'] = false;
                    $opts['footer'] = false;
                } else {
                    $opts['site_header'] = $baseMap['site_header'];
                    $opts['footer'] = $baseMap['footer'];
                }
                break;
            default:
                $opts['site_header'] = $baseMap['site_header'];
                $opts['footer'] = $baseMap['footer'];
                break;
        }

        if (isset($page['layout_compile']) && is_array($page['layout_compile'])) {
            foreach ($page['layout_compile'] as $optKey => $optVal) {
                if (array_key_exists($optKey, $opts)) {
                    $opts[$optKey] = $optVal;
                }
            }
        }

        return array_merge($opts, $extra);
    }
}
