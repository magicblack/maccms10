<?php
namespace app\common\util;

/**
 * 为 Page Builder 主题按 page_type 自动生成页面 JSON 种子并编译 draft.twig。
 */
class PageScaffolder
{
    /** @var PageBuilderKit */
    private $kit;

    public function __construct()
    {
        $this->kit = new PageBuilderKit();
    }

    /**
     * 预览主题缺页时写入 html/{route}.json 并编译 {route}.draft.twig。
     * @param string $themeDir 主题根目录（绝对路径，可带尾斜杠）
     * @param string $route 如 vod/show
     * @param bool   $force 为 true 时覆盖已有 JSON / draft
     * @return bool
     */
    public function ensureScaffolded($themeDir, $route, $force = false)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $route = PageRegistry::normalizeRoute($route);
        if ($themeDir === '' || !is_dir($themeDir) || $route === '') {
            return false;
        }
        if (!$this->isTwigTheme($themeDir)) {
            return false;
        }
        $this->ensurePublicStatusTemplates($themeDir);
        $this->ensureT0CoreTemplates($themeDir, false);
        $themeName = basename(rtrim($themeDir, '/'));
        if (!$force && $this->themeHasPageTpl($themeDir, $route)) {
            return true;
        }
        $page = $this->defaultPageForRoute($route, $themeDir);
        if (empty($page)) {
            return false;
        }
        if (!$this->writePageJson($themeDir, $route, $page, $force)) {
            return false;
        }
        $componentIds = $this->kit->collectPageComponentIds($page);
        $chromeIds = array('site_nav', 'header_decor', 'channel_strip', 'site_footer', 'section', 'spacer');
        $componentIds = array_values(array_unique(array_merge($chromeIds, $componentIds)));
        $this->kit->syncToTheme($themeDir, false, $componentIds);

        $compiler = new TwigPageCompiler();
        if (!TwigPageCompiler::pageNeedsCompile($page, $route)) {
            return false;
        }
        $layoutOpts = PageRegistry::layoutOptsForCompile($themeDir, $route, $page);
        if (class_exists('\app\common\util\ThemePreset') && \app\common\util\ThemePreset::isThinShell($themeDir)) {
            $layoutOpts['components_only'] = true;
            $layoutOpts['theme_dir'] = $themeDir;
            $flags = \app\common\util\ThemePreset::scaffoldFlags($themeDir);
            if (!empty($flags['shell_left_nav'])) {
                $layoutOpts['shell_left_nav'] = true;
            }
        }
        $draftOk = $compiler->writePageTwig($route, $page, $themeDir, true, $layoutOpts);
        if (!$draftOk) {
            return false;
        }
        $parts = explode('/', $route, 2);
        if (count($parts) >= 2) {
            $published = rtrim($themeDir, '/') . '/html/' . $parts[0] . '/' . $parts[1] . '.twig';
            if (!is_file($published)) {
                $compiler->writePageTwig($route, $page, $themeDir, false, $layoutOpts);
            }
        }
        return true;
    }

    /**
     * 为所有可编辑路由批量脚手架并编译 draft。
     * @param string $themeDir
     * @param bool   $force
     * @return int 成功数
     */
    public function bootstrapEditablePages($themeDir, $force = false)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        // 不可编排的 T0 页（下载/角色/剧情/地图/评论/注册等）从 starter 补齐，
        // 否则「另存为新主题」按版式创建后 ThemeGate 仍会缺页白屏。
        $this->ensureT0CoreTemplates($themeDir, $force);
        $ok = 0;
        foreach (PageRegistry::routes() as $meta) {
            if (!PageRegistry::isEditableRoute($meta['route'])) {
                continue;
            }
            if ($this->ensureScaffolded($themeDir, $meta['route'], $force)) {
                $ok++;
            }
        }
        $ok += $this->ensureMemberT1Pages($themeDir, $force);
        return $ok;
    }

    /**
     * 将 starter 中的 T0 核心页、公共布局壳（layout/base、layout/auth）+ pb-t0.css 拷入主题（缺则补；force 则覆盖）。
     * 覆盖 ThemeContract T0 中薄壳主题常缺、且 NON_DESIGNABLE 的路由。
     *
     * 注意：force=true（即“重置主题为预设”）会用 starter 版本无条件覆盖已存在主题自己的
     * layout/base.twig、layout/auth.twig —— 如果某主题在这两个公共壳文件里做过自定义
     * （导航、统计脚本、品牌化内联样式等），执行一次真正的“重置”会把这些自定义内容一并冲掉。
     *
     * @param string $themeDir
     * @param bool   $force
     * @return bool
     */
    public function ensureT0CoreTemplates($themeDir, $force = false)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir === '' || !is_dir($themeDir)) {
            return false;
        }
        if (class_exists('\\app\\common\\util\\PageBuilderKit')) {
            $kit = new PageBuilderKit();
            if (!$kit->themeAcceptsKitSync($themeDir)) {
                return true;
            }
        } elseif (!$this->isTwigTheme($themeDir)) {
            return true;
        }
        if (!defined('ROOT_PATH')) {
            return false;
        }
        $starterRoot = ROOT_PATH . 'template/_shared/starter/';
        if (!is_dir($starterRoot)) {
            return false;
        }

        $relFiles = array(
            'asset/css/pb-t0.css',
            'html/layout/base.twig',
            'html/layout/auth.twig',
            'html/vod/down.twig',
            'html/vod/detail_pwd.twig',
            'html/vod/player_pwd.twig',
            'html/vod/downer_pwd.twig',
            'html/vod/player.twig',
            'html/vod/downer.twig',
            'html/art/detail_pwd.twig',
            'html/manga/detail_pwd.twig',
            'html/role/index.twig',
            'html/role/show.twig',
            'html/role/detail.twig',
            'html/plot/index.twig',
            'html/plot/detail.twig',
            'html/live/index.twig',
            'html/live/play.twig',
            'html/map/index.twig',
            'html/rss/index.twig',
            'html/rss/baidu.twig',
            'html/rss/google.twig',
            'html/rss/bing.twig',
            'html/rss/so.twig',
            'html/rss/sogou.twig',
            'html/rss/sm.twig',
            'html/rss/_urlset.twig',
            'html/comment/index.twig',
            'html/comment/ajax.twig',
            // ensurePublicStatusTemplates() 只补缺失文件（_status.twig 除外），这两页的修正需随重置同步
            'html/public/notempty.twig',
            'html/public/verify.twig',
            'html/user/reg.twig',
            'html/user/plog.twig',
            'html/user/findpass_msg.twig',
        );

        $ok = true;
        foreach ($relFiles as $rel) {
            $src = $starterRoot . $rel;
            if (!is_file($src)) {
                $ok = false;
                continue;
            }
            $dest = $themeDir . $rel;
            if (!$force && is_file($dest)) {
                continue;
            }
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                    $ok = false;
                    continue;
                }
            }
            if (@copy($src, $dest) === false) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * ThemeContract T1 会员子页：控制器与 PB 组件已有，缺页级 .twig 时自动脚手架并编译。
     *
     * @param string $themeDir
     * @param bool   $force
     * @return int 成功补齐的路由数
     */
    public function ensureMemberT1Pages($themeDir, $force = false)
    {
        $routes = array(
            'user/buy',
            'user/pay',
            'user/payment_weixin',
            'user/checkin',
            'user/popedom',
            'user/dynamics',
            'user/downs',
            'user/mall',
            'user/mall_orders',
            'user/cash',
            'user/reward',
            'user/invite',
            'user/bind',
            'user/unbind',
        );
        $ok = 0;
        foreach ($routes as $route) {
            if ($this->ensureScaffolded($themeDir, $route, $force)) {
                $ok++;
            }
        }
        return $ok;
    }

    public function defaultPageForRoute($route, $themeDir = '')
    {
        $route = PageRegistry::normalizeRoute($route);
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        $scaffold = $this->scaffoldFlagsForTheme($themeDir);
        if ($route === 'index/index') {
            $presetId = ThemePreset::DEFAULT_ID;
            if ($themeDir !== '/' && $themeDir !== '') {
                $meta = ThemePreset::readPbMeta($themeDir);
                if (!empty($meta['pb_preset'])) {
                    $presetId = $meta['pb_preset'];
                }
            }
            $home = $this->kit->defaultPage($presetId);
            if (!empty($home['regions']) && is_array($home['regions'])) {
                $page = array(
                    'template' => 'index/index',
                    'layout' => isset($home['layout']) ? (string) $home['layout'] : 'base',
                    'page_type' => isset($home['page_type']) ? (string) $home['page_type'] : 'home',
                    'layout_mod' => isset($home['layout_mod']) ? (string) $home['layout_mod'] : 'home',
                    'regions' => $home['regions'],
                );
                if (isset($home['layout_compile']) && is_array($home['layout_compile'])) {
                    $page['layout_compile'] = $home['layout_compile'];
                }
                if (isset($home['twig_sets']) && is_array($home['twig_sets'])) {
                    $page['twig_sets'] = $home['twig_sets'];
                }
                if (!empty($home['extra_page_class'])) {
                    $page['extra_page_class'] = (string) $home['extra_page_class'];
                }
                return $this->applyNp2PresetPage($route, $page, $scaffold, $themeDir);
            }
            if (!empty($home['blocks'])) {
                return $this->applyNp2PresetPage($route, array(
                    'template' => 'index/index',
                    'layout' => 'base',
                    'regions' => array_merge($this->emptyRegions(), array(
                        'content' => $home['blocks'],
                    )),
                ), $scaffold, $themeDir);
            }
            return array(
                'template' => 'index/index',
                'layout' => 'base',
                'regions' => $this->emptyRegions(),
            );
        }

        $meta = PageRegistry::get($route);
        if ($meta === null) {
            return array();
        }

        $pageType = isset($meta['page_type']) ? (string) $meta['page_type'] : 'list';
        $module = isset($meta['module']) ? (string) $meta['module'] : '';
        $action = isset($meta['action']) ? (string) $meta['action'] : '';

        $regions = $this->emptyRegions();
        $regions['site_header'] = array($this->block('site_nav'));
        $regions['footer'] = array($this->block('site_footer'));

        $layoutMod = '';
        $layout = 'base';
        switch ($pageType) {
            case 'home':
                break;
            case 'list':
                if ($module === 'manga' && $action === 'index') {
                    $layoutMod = 'manga-home';
                    $regions['channel'] = array();
                    $regions['hero'] = array(
                        $this->block('manga_mosaic_hero', array(
                            'num' => 24,
                            'by' => 'hits_week',
                        )),
                        $this->block('manga_genre_nav', array(
                            'num' => 16,
                        )),
                    );
                    $regions['content'] = array(
                        $this->block('manga_home_spotlight', array(
                            'num' => 7,
                            'by' => 'hits_month',
                            'title' => '为你推荐',
                            'subtitle' => 'Pick你的最爱！',
                        )),
                        $this->block('manga_home_row', array(
                            'by' => 'hits_week',
                            'title' => '全网热议',
                            'subtitle' => '恭喜你发现宝藏',
                            'num' => 10,
                        )),
                        $this->block('manga_home_row', array(
                            'by' => 'time',
                            'title' => '最近更新',
                            'subtitle' => '追更不间断',
                            'num' => 10,
                        )),
                        $this->block('manga_home_rank_tags', array('num' => 6, 'title' => '热门排行')),
                    );
                    $regions['sidebar'] = array();
                    break;
                }
                if ($module === 'art' && $action === 'index') {
                    $presetId = ThemePreset::DEFAULT_ID;
                    if ($themeDir !== '/' && $themeDir !== '') {
                        $meta = ThemePreset::readPbMeta($themeDir);
                        if (!empty($meta['pb_preset'])) {
                            $presetId = $meta['pb_preset'];
                        }
                    }
                    $seed = $this->kit->defaultRoutePage('art/index', $presetId);
                    if (!empty($seed['regions']) && is_array($seed['regions'])) {
                        $layoutMod = !empty($seed['layout_mod']) ? (string) $seed['layout_mod'] : 'novel-home';
                        $regions = $seed['regions'];
                        if (empty($regions['site_header'])) {
                            $regions['site_header'] = array($this->block('site_nav'));
                        }
                        if (empty($regions['footer'])) {
                            $regions['footer'] = array($this->block('site_footer'));
                        }
                        break;
                    }
                    $layoutMod = 'novel-home';
                    $regions['channel'] = array();
                    $regions['hero'] = array(
                        $this->block('novel_genre_nav', array('num' => 16, 'more_url' => 'art/type')),
                    );
                    $regions['content'] = array(
                        $this->block('novel_home_dock', array()),
                        $this->block('novel_home_rank', array('num' => 10)),
                        $this->sectionWithBody('最新小说', array(
                            // 小说已采集到封面：恢复竖版封面 + 标题网格（与漫画一致）
                            $this->block('art_list', array(
                                'num' => 30,
                                'by' => 'time',
                                'order' => 'desc',
                                'type' => 0,
                                'render' => 'grid',
                                'card' => 'poster-v',
                                'ratio' => '3-4',
                            )),
                        ), array(
                            'variant' => 'card',
                            'icon' => 'book',
                            'more_url' => 'art/show',
                        )),
                    );
                    $regions['sidebar'] = array();
                    break;
                }
                $layoutMod = 'list';
                if (!in_array($action, array('search', 'search_hub'), true)) {
                    $regions['channel'] = array($this->block('channel_strip', array('compact' => true)));
                }
                $content = array();
                if ($module === 'vod' && $action === 'show') {
                    $content[] = $this->block('filter_panel', array('preset' => 'vod_show'));
                } elseif ($module === 'art' && $action === 'show') {
                    $content[] = $this->block('filter_panel', array('preset' => 'art_show'));
                } elseif ($module === 'manga' && $action === 'show') {
                    $content[] = $this->block('filter_panel', array('preset' => 'manga_show'));
                } elseif ($module === 'vod' && $action === 'type') {
                    $content[] = $this->block('filter_chips', array('flag' => 'show'));
                } elseif ($module === 'vod' && $action === 'search') {
                    $content[] = $this->block('filter_panel', array('preset' => 'vod_search'));
                }
                $content[] = $this->sectionWithListBody(
                    $module,
                    $action,
                    $route,
                    in_array($action, array('type', 'show', 'search'), true)
                    || ($module === 'vod' && $action === 'index')
                    || ($module === 'topic' && $action === 'index')
                    || ($module === 'actor' && $action === 'index')
                );
                if ($module === 'actor' && $action === 'index') {
                    $content[] = $this->block('sec_heading', array(
                        'title' => '本周热门',
                        'title_lang' => 'string_bzph',
                    ));
                    $content[] = $this->sectionWithBody('', array(
                        $this->block('actor_list', array('num' => 12, 'by' => 'hits_week')),
                    ), array('variant' => 'plain'));
                }
                $regions['content'] = $content;
                if (!empty($scaffold['list_sidebar_rank'])
                    && in_array($action, array('type', 'show', 'index', 'search'), true)) {
                    $regions['sidebar'] = array($this->sectionWithRank());
                }
                break;
            case 'detail':
                $layoutMod = 'detail';
                if (in_array($module, array('actor', 'role', 'website', 'topic'), true)) {
                    $layoutMod = 'list';
                }
                if ($module === 'vod' && $action === 'down') {
                    $regions['content'] = array(
                        $this->block('breadcrumb', array('auto' => true)),
                        $this->block('sec_heading', array(
                            'title' => '下载地址',
                            'title_lang' => 'string_vod_down_addr',
                        )),
                        $this->block('media_related_panel', array(
                            'num' => 10,
                            'context' => 'play',
                            'relation' => false,
                            'title' => '相关推荐',
                            'title_lang' => 'string_xgtj',
                        )),
                    );
                } elseif ($module === 'vod') {
                    $regions['content'] = array(
                        $this->block('vod_detail', array(
                            'show_intro' => true,
                        )),
                        $this->block('media_related_panel', array(
                            'num' => 10,
                            'relation' => true,
                            'title' => '相关推荐',
                            'title_lang' => 'string_xgtj',
                        )),
                    );
                } else {
                    $detailBlocks = $this->detailContentBlocks($module);
                    if (!empty($detailBlocks)) {
                        $regions['content'] = $detailBlocks;
                    } else {
                        $regions['content'] = array(
                            $this->block('content_feed', array(
                                'bind' => $this->contentFeedBindForModule($module),
                                'num' => 12,
                                'render' => 'grid',
                                'by' => 'hits',
                                'relation' => true,
                            )),
                        );
                    }
                }
                if (!empty($scaffold['detail_sidebar_rank'])) {
                    $regions['sidebar'] = array($this->sectionWithRank());
                }
                break;
            case 'play':
                if ($module === 'manga') {
                    $layoutMod = 'solo';
                    $regions['site_header'] = array();
                    $regions['footer'] = array();
                } else {
                    $layoutMod = 'play';
                    // vod / live 播放页保留顶栏与页脚（与详情一致）；漫画阅读仍沉浸无头
                }
                $regions['content'] = $this->playContentBlocks($module);
                break;
            case 'read':
                $layoutMod = 'solo';
                if ($module === 'art') {
                    $regions['site_header'] = array();
                    $regions['footer'] = array();
                    $regions['content'] = array(
                        $this->block('novel_player', array()),
                        $this->block('novel_chapter_catalog', array()),
                    );
                } else {
                    $regions['content'] = array(
                        $this->block('sec_heading', array('title' => '阅读')),
                        $this->block('section', array('title' => '', 'variant' => 'plain')),
                    );
                }
                break;
            case 'member':
                $layoutMod = 'member';
                if ($route === 'user/index') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'index',
                                'show_hub' => true,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('user_member_hub', array()),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/notify') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'notify',
                                'title' => '消息中心',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('user_notify_panel', array()),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/upgrade') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'upgrade',
                                'title' => '充值中心',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('pay_shell', array(
                                    'default_tab' => 'upgrade',
                                ))),
                            ),
                        ),
                    );
                } elseif ($route === 'user/orders') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'orders',
                                'title' => '充值记录',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('order_list', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/pay') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'orders',
                                'title' => '确认订单',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    array(
                                        'type' => 'include',
                                        'path' => 'page-builder-components/_shared/pay_confirm.twig',
                                    ),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/cards') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'cards',
                                'title' => '卡密充值',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('cards_list', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/login' || $route === 'user/findpass' || $route === 'user/findpass_msg') {
                    $layout = 'auth';
                    $layoutMod = 'auth';
                    $regions['site_header'] = array();
                    $regions['footer'] = array();
                    $panel = 'login';
                    if ($route === 'user/findpass') {
                        $panel = 'findpass';
                    } elseif ($route === 'user/findpass_msg') {
                        $panel = 'findpass_msg';
                    }
                    $regions['content'] = array(
                        $this->block('auth_page', array(
                            'panel' => $panel,
                            'expand_invite' => $route === 'user/login',
                        )),
                    );
                } elseif ($route === 'user/benefits') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'benefits',
                                'title' => '会员权益',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('benefits_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/favs') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'favs',
                                'title' => '我的收藏',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('member_filter_tabs', array(
                                        'preset' => 'favs',
                                        'active_mid' => 0,
                                    )),
                                    $this->block('favs_list', array()),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/plays') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'plays',
                                'title' => '播放记录',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('member_filter_tabs', array(
                                        'preset' => 'plays',
                                        'active_mid' => 0,
                                    )),
                                    $this->block('plays_list', array(
                                        'show_clear' => true,
                                    )),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/info') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'info',
                                'title' => '资料修改',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('user_info_panel', array()),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/dynamics') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'dynamics',
                                'title' => '我的圈子',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array(
                                    $this->block('user_social_panel', array()),
                                ),
                            ),
                        ),
                    );
                } elseif ($route === 'user/buy') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'upgrade',
                                'title' => '充值中心',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('pay_shell', array(
                                    'default_tab' => 'recharge',
                                ))),
                            ),
                        ),
                    );
                } elseif ($route === 'user/checkin') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'checkin',
                                'title' => '每日签到',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_checkin_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/invite') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'invite',
                                'title' => '邀请好友',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_invite_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/plog') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'plog',
                                'title' => '积分记录',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_plog_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/mall' || $route === 'user/mall_orders') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'mall',
                                'title' => '金币商城',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_mall_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/bind') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'bind',
                                'title' => '账号绑定',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_bind_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/unbind') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'info',
                                'title' => '账号解绑',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_unbind_panel', array())),
                            ),
                        ),
                    );
                } elseif ($route === 'user/cash') {
                    $regions['content'] = array(
                        array(
                            'component' => 'member_shell',
                            'props' => array_merge($this->kit->defaultPropsForComponent('member_shell'), array(
                                'active' => 'cash',
                                'title' => '积分提现',
                                'show_hub' => false,
                            )),
                            'slots' => array(
                                'body' => array($this->block('user_cash_panel', array())),
                            ),
                        ),
                    );
                } else {
                    $regions['content'] = array(
                        $this->block('sec_heading', array('title' => '会员中心')),
                        $this->block('section', array('title' => '', 'variant' => 'plain')),
                    );
                }
                break;
            case 'rank':
                $layoutMod = 'rank';
                $regions['content'] = array(
                    $this->block('sec_heading', array('title' => '排行榜', 'title_lang' => 'string_rank')),
                    $this->block('rank_theme_boards', array()),
                );
                break;
            case 'gbook':
                $layoutMod = 'gbook';
                $regions['content'] = array(
                    $this->block('sec_heading', array('title' => '留言板', 'title_lang' => 'string_gbook_board')),
                    $this->block('gbook_form', array()),
                    $this->block('gbook_list', array('num' => 20, 'paging' => true)),
                );
                break;
            case 'map':
                $layoutMod = 'list';
                $regions['content'] = array(
                    $this->block('sec_heading', array('title' => '网站地图', 'title_lang' => 'string_foot_ditu')),
                );
                break;
            case 'gate':
                $regions['content'] = array(
                    $this->block('section', array('title' => '提示', 'variant' => 'plain')),
                );
                break;
            case 'special':
                $regions['content'] = array(
                    $this->block('section', array('title' => '专题', 'variant' => 'plain')),
                    $this->block('content_feed', array('bind' => 'vod', 'num' => 12)),
                );
                break;
            default:
                $regions['content'] = array($this->sectionWithList($module, $action, $route));
                break;
        }

        return $this->applyNp2PresetPage($route, $this->finalizeScaffoldPage($route, array(
            'template' => $route,
            'layout' => $layout,
            'page_type' => $pageType,
            'layout_mod' => $layoutMod,
            'regions' => $regions,
        )), $scaffold, $themeDir);
    }

    /**
     * 脚手架页级修饰（layout_mod / extra_page_class 等），避免散落改 spike 编译产物。
     *
     * @param string $route
     * @param array  $page
     * @return array
     */
    private function finalizeScaffoldPage($route, array $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        if ($route === 'topic/detail') {
            $page['layout_mod'] = 'list';
            $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
            $page['extra_page_class'] = trim($extra . ' page-topic-detail');
        }
        return $page;
    }

    /**
     * preset-np2：site_header 顶栏、空 footer、频道首页区块顺序。
     *
     * @param string $route
     * @param array  $page
     * @param array  $scaffold
     * @param string $themeDir
     * @return array
     */
    private function applyNp2PresetPage($route, array $page, array $scaffold, $themeDir = '')
    {
        if (empty($scaffold['site_header_chrome']) || $scaffold['site_header_chrome'] !== 'app_top_chrome') {
            return $page;
        }
        $route = PageRegistry::normalizeRoute($route);
        if (PageRegistry::isImmersiveRoute($route) || PageRegistry::isAuthPageRoute($route)) {
            if (!empty($scaffold['hide_pb_footer']) && isset($page['regions']) && is_array($page['regions'])) {
                $page['regions']['footer'] = array();
                if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
                    $page['layout_compile'] = array();
                }
                $page['layout_compile']['footer'] = false;
            }
            if (PageRegistry::isAuthPageRoute($route)) {
                $page = $this->applyNp2AuthPageSkin($page);
            }
            return $page;
        }
        $page = $this->kit->applyAppTopChromeLayout($route, $page, $themeDir);
        if (!empty($scaffold['hide_pb_footer']) && isset($page['regions']) && is_array($page['regions'])) {
            $page['regions']['footer'] = array();
            if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
                $page['layout_compile'] = array();
            }
            $page['layout_compile']['footer'] = false;
        }
        if ($route === 'manga/index' && isset($page['regions']['content']) && is_array($page['regions']['content'])) {
            $content = $page['regions']['content'];
            $spotlight = null;
            $rest = array();
            foreach ($content as $b) {
                if (is_array($b) && isset($b['component']) && (string) $b['component'] === 'manga_home_spotlight') {
                    if ($spotlight === null) {
                        if (!isset($b['props']) || !is_array($b['props'])) {
                            $b['props'] = array();
                        }
                        if (empty($scaffold['manga_home_spotlight_show_hero'])) {
                            $b['props']['show_hero'] = false;
                        }
                        $spotlight = $b;
                    }
                    continue;
                }
                $rest[] = $b;
            }
            if ($spotlight !== null) {
                $page['regions']['hero'] = array($spotlight);
                $page['regions']['content'] = $rest;
            } else {
                $page['regions']['hero'] = array();
            }
        }
        if ($route === 'art/index' && isset($page['regions']['hero']) && is_array($page['regions']['hero'])) {
            $hero = array();
            foreach ($page['regions']['hero'] as $b) {
                if (!is_array($b) || empty($b['component'])) {
                    continue;
                }
                if ((string) $b['component'] === 'novel_home_banner') {
                    continue;
                }
                $hero[] = $b;
            }
            $page['regions']['hero'] = $hero;
        }
        if ($route === 'art/index' && isset($page['regions']['content']) && is_array($page['regions']['content'])) {
            $drop = array('novel_home_banner', 'novel_home_grid', 'novel_home_row', 'novel_home_zone');
            $content = array();
            foreach ($page['regions']['content'] as $b) {
                if (!is_array($b) || empty($b['component'])) {
                    continue;
                }
                if (in_array((string) $b['component'], $drop, true)) {
                    continue;
                }
                $content[] = $b;
            }
            $page['regions']['content'] = $content;
        }
        if ($route === 'label/rank') {
            $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
            if (strpos($extra, 'np2-rank-page') === false) {
                $extra = trim($extra . ' np2-rank-page');
            }
            $page['extra_page_class'] = $extra;
            // rank_theme_boards 内部循环 mac_rank_boards()（视频/文章/漫画三种内容类型都会
            // 各自出榜），不像单个 np2_rank_board 只能固定一种 mid，才不会把非视频榜单丢掉。
            $page['regions']['content'] = array(
                $this->block('rank_theme_boards', array()),
            );
            $page['blocks'] = $page['regions']['content'];
            if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
                $page['twig_sets'] = array();
            }
            $page['twig_sets']['np2_tab'] = 'rank';
        }
        if ($route === 'user/index') {
            $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
            if (strpos($extra, 'np2-mine-page') === false) {
                $extra = trim($extra . ' np2-mine-page');
            }
            $page['extra_page_class'] = $extra;
            $page['np2_mine_layout'] = true;
            $page['regions']['site_header'] = array();
            $page['regions']['footer'] = array();
            $page['regions']['content'] = array();
            if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
                $page['layout_compile'] = array();
            }
            $page['layout_compile']['site_header'] = false;
            $page['layout_compile']['footer'] = false;
            if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
                $page['twig_sets'] = array();
            }
            $page['twig_sets']['np2_tab'] = 'mine';
        }
        if ($route === 'art/index') {
            $page = $this->applyNp2ArtIndexLayout($page);
        }
        if ($route === 'manga/index') {
            $page = $this->applyNp2MangaIndexLayout($page);
        }
        $page = $this->applyNp2DetailPageClass($route, $page);
        return $page;
    }

    /**
     * preset-np2 详情页 body 修饰类（与 np2-app.css 详情/播放 H5 规则对齐）。
     *
     * @param string $route
     * @param array  $page
     * @return array
     */
    private function applyNp2DetailPageClass($route, array $page)
    {
        $map = array(
            'vod/detail' => 'page-vod-detail',
            'manga/detail' => 'page-manga-detail',
            'art/detail' => 'page-art-detail',
        );
        if (!isset($map[$route])) {
            return $page;
        }
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        $modifier = $map[$route];
        if (strpos($extra, $modifier) === false) {
            $extra = trim($extra . ' ' . $modifier);
        }
        $page['extra_page_class'] = $extra;
        return $page;
    }

    /**
     * preset-np2 小说首页：np2_home_header + banner/区块，无 PB 页脚。
     *
     * @param array $page
     * @return array
     */
    private function applyNp2ArtIndexLayout(array $page)
    {
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if (strpos($extra, 'np2-novel-home') === false) {
            $extra = trim($extra . ' np2-novel-home');
        }
        $page['extra_page_class'] = $extra;
        $page['np2_channel_home_layout'] = 'art';
        $page['regions']['site_header'] = array();
        $page['regions']['footer'] = array();
        $page['regions']['hero'] = array();
        $page['regions']['content'] = array();
        if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
            $page['layout_compile'] = array();
        }
        $page['layout_compile']['site_header'] = true;
        $page['layout_compile']['hero'] = true;
        $page['layout_compile']['footer'] = false;
        $page['layout_compile']['chrome_fallbacks'] = false;
        if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
            $page['twig_sets'] = array();
        }
        $page['twig_sets']['np2_tab'] = 'art';
        return $page;
    }

    /**
     * preset-np2 漫画首页：np2_home_header + spotlight/横滑/榜，无 PB 页脚。
     *
     * @param array $page
     * @return array
     */
    private function applyNp2MangaIndexLayout(array $page)
    {
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if (strpos($extra, 'np2-manga-home') === false) {
            $extra = trim($extra . ' np2-manga-home');
        }
        $page['extra_page_class'] = $extra;
        $page['np2_channel_home_layout'] = 'manga';
        $page['regions']['site_header'] = array();
        $page['regions']['footer'] = array();
        $page['regions']['hero'] = array();
        $page['regions']['content'] = array();
        if (!isset($page['layout_compile']) || !is_array($page['layout_compile'])) {
            $page['layout_compile'] = array();
        }
        $page['layout_compile']['site_header'] = true;
        $page['layout_compile']['hero'] = true;
        $page['layout_compile']['footer'] = false;
        $page['layout_compile']['chrome_fallbacks'] = false;
        if (!isset($page['twig_sets']) || !is_array($page['twig_sets'])) {
            $page['twig_sets'] = array();
        }
        $page['twig_sets']['np2_tab'] = 'manga';
        return $page;
    }

    /**
     * preset-np2 认证页：深色壳 + 无 split 渐变 hero。
     *
     * @param array $page
     * @return array
     */
    private function applyNp2AuthPageSkin(array $page)
    {
        $extra = isset($page['extra_page_class']) ? trim((string) $page['extra_page_class']) : '';
        if (strpos($extra, 'np2-auth-page') === false) {
            $extra = trim($extra . ' np2-app np2-auth-page');
        }
        $page['extra_page_class'] = $extra;
        if (!isset($page['regions']['content']) || !is_array($page['regions']['content'])) {
            return $page;
        }
        foreach ($page['regions']['content'] as $idx => $block) {
            if (!is_array($block) || empty($block['component']) || (string) $block['component'] !== 'auth_page') {
                continue;
            }
            $props = isset($block['props']) && is_array($block['props']) ? $block['props'] : array();
            $props['layout'] = 'minimal';
            $props['show_hero'] = false;
            $props['hero_image'] = '';
            $props['hero_image_mobile'] = '';
            $page['regions']['content'][$idx]['props'] = $props;
            break;
        }
        return $page;
    }

    private function emptyRegions()
    {
        $regions = array();
        foreach (PageRegistry::regionNames() as $name) {
            $regions[$name] = array();
        }
        return $regions;
    }

    private function block($component, array $props = array())
    {
        $defaults = $this->kit->defaultPropsForComponent($component);
        return array(
            'component' => (string) $component,
            'props' => array_merge($defaults, $props),
        );
    }

    private function sectionWithBody($title, $bodyBlocks, array $sectionProps = array())
    {
        $props = array_merge(
            array('title' => $title, 'variant' => 'plain'),
            $sectionProps
        );
        return array(
            'component' => 'section',
            'props' => $props,
            'slots' => array(
                'body' => $bodyBlocks,
            ),
        );
    }

    private function sectionWithListBody($module, $action, $route, $paging = false)
    {
        $listComp = $this->listComponentForModule($module);
        $props = $this->kit->defaultPropsForComponent($listComp);
        $props['num'] = 24;
        $props['render'] = 'grid';
        $props['by'] = 'time';
        if (in_array($module, array('vod', 'art', 'manga'), true) && in_array($action, array('type', 'show'), true)) {
            $props['type'] = 'current';
        } elseif ($listComp === 'content_feed') {
            $props['bind'] = $this->contentFeedBindForModule($module);
            $props['num'] = 24;
        } else {
            $props['type'] = 0;
        }
        if ($paging) {
            $props['paging'] = true;
            $props['pageurl'] = $module . '/' . $action;
        }
        if ($module === 'vod' && $action === 'index') {
            $props['num'] = 48;
            $props['time'] = 'today';
        }
        if ($module === 'actor' && $action === 'index') {
            $props['num'] = 48;
        }
        // 漫画列表页用 default 比例；首页 preset 种子仍显式写 3-4
        if ($module === 'manga') {
            $props['ratio'] = 'default';
        }
        // 小说列表页：竖版封面 + 标题网格（封面缺失/失效时由 art_book_card 回退书形占位）
        if ($module === 'art') {
            $props['card'] = 'poster-v';
            $props['ratio'] = '3-4';
        }
        $title = $this->listTitleForRoute($route, $module, $action);
        $icon = 'fire';
        if ($module === 'art') {
            $icon = 'newspaper';
        } elseif ($module === 'manga') {
            $icon = 'book';
        } elseif ($module === 'topic') {
            $icon = 'collection';
        } elseif ($module === 'live') {
            $icon = 'broadcast';
        }
        $sectionProps = array(
            'variant' => 'plain',
            'icon' => $icon,
        );
        if ($module === 'vod' && in_array($action, array('type', 'show'), true)) {
            $sectionProps['head_sort'] = 'vod_show';
            $sectionProps['sort_route'] = $action === 'type' ? 'type' : 'show';
            if ($action === 'type') {
                $sectionProps['show_more'] = false;
            }
        } elseif (in_array($module, array('art', 'manga'), true) && $action === 'show') {
            $sectionProps['head_sort'] = 'content_show';
        } elseif ($module === 'topic' && $action === 'index') {
            $sectionProps['show_more'] = false;
        } elseif ($module === 'actor' && $action === 'index') {
            $sectionProps['show_more'] = false;
            $sectionProps['title_key'] = 'string_actor_library';
        } elseif ($module === 'vod' && $action === 'index') {
            $sectionProps['head_sort'] = 'vod_index';
            $sectionProps['sort_route'] = 'index';
            $sectionProps['show_more'] = false;
            $sectionProps['title_key'] = 'string_zuijingx';
        } else {
            $sectionProps['more_url'] = $module . '/index';
        }
        if ($action === 'type') {
            $sectionProps['title_mode'] = 'type_name';
        } else {
            $titleLang = $this->listTitleLangForRoute($module, $action);
            if ($titleLang !== '') {
                $sectionProps['title_key'] = $titleLang;
            }
        }
        return $this->sectionWithBody($title, array($this->block($listComp, $props)), $sectionProps);
    }

    private function sectionWithList($module, $action, $route)
    {
        $listComp = $this->listComponentForModule($module);
        $props = $this->kit->defaultPropsForComponent($listComp);
        $props['num'] = 24;
        $props['render'] = 'grid';
        $props['by'] = 'time';
        if (in_array($module, array('vod', 'art', 'manga'), true) && in_array($action, array('type', 'show'), true)) {
            $props['type'] = 'current';
        } elseif ($listComp === 'content_feed') {
            $props['bind'] = $this->contentFeedBindForModule($module);
            $props['num'] = 24;
        } else {
            $props['type'] = 0;
        }
        $title = $this->listTitleForRoute($route, $module, $action);
        return $this->sectionWithBody($title, array($this->block($listComp, $props)));
    }

    private function sectionWithRank()
    {
        return $this->sectionWithBody(
            '本周排行',
            array($this->block('rank_list', array('num' => 5, 'period' => 'week'))),
            array('variant' => 'card', 'icon' => 'bar-chart')
        );
    }

    private function sectionWithContentFeed($module, $title)
    {
        return $this->sectionWithBody(
            $title,
            array($this->block('content_feed', array(
                'bind' => $this->contentFeedBindForModule($module),
                'num' => 12,
                'render' => 'grid',
                'by' => 'time',
            )))
        );
    }

    private function playContentBlocks($module)
    {
        if ($module === 'manga') {
            return array(
                $this->block('manga_player', array()),
            );
        }
        $blocks = array();
        if ($module === 'vod') {
            $blocks[] = $this->block('breadcrumb', array('auto' => true));
        }
        $blocks[] = $this->block('vod_player', array('show_episodes' => true, 'show_nav' => false));
        $blocks[] = $this->block('media_related_panel', array(
            'num' => 10,
            'context' => 'play',
            'relation' => false,
            'by' => 'hits',
            'title' => '相关推荐',
            'title_lang' => 'string_xgtj',
        ));
        return $blocks;
    }

    private function detailContentBlocks($module)
    {
        if ($module === 'art') {
            return array(
                $this->block('art_detail', array(
                    'show_toc' => true,
                    'show_intro' => true,
                )),
                $this->block('media_related_panel', array(
                    'bind' => 'art',
                    'layout' => 'rows',
                    'num' => 8,
                    'relation' => true,
                    'title' => '相关推荐',
                    'title_lang' => 'string_xgtj',
                )),
            );
        }
        if ($module === 'manga') {
            return array(
                $this->block('manga_detail', array(
                    'show_chapters' => true,
                    'show_intro' => true,
                    'card' => 'poster-v',
                    'ratio' => '3-4',
                )),
                $this->block('media_related_panel', array(
                    'bind' => 'manga',
                    'card' => 'poster-v',
                    'num' => 10,
                    'relation' => true,
                    'title' => '相关推荐',
                    'title_lang' => 'string_xgtj',
                )),
            );
        }
        if ($module === 'topic') {
            return array(
                $this->block('topic_detail', array(
                    'show_intro' => true,
                    'num' => 0,
                )),
            );
        }
        if ($module === 'actor') {
            return array(
                $this->block('actor_detail', array(
                    'show_intro' => true,
                    'num' => 12,
                )),
            );
        }
        if ($module === 'role') {
            return array(
                $this->block('role_detail', array(
                    'show_intro' => true,
                    'num' => 8,
                )),
            );
        }
        if ($module === 'website') {
            return array(
                $this->block('website_detail', array(
                    'show_intro' => true,
                )),
            );
        }
        return array();
    }

    private function listComponentForModule($module)
    {
        $map = array(
            'vod' => 'vod_list',
            'art' => 'art_list',
            'manga' => 'manga_list',
            'website' => 'website_list',
            'topic' => 'topic_list',
            'live' => 'live_list',
            'actor' => 'actor_list',
            'role' => 'role_list',
        );
        if (isset($map[$module])) {
            return $map[$module];
        }
        return 'content_feed';
    }

    private function contentFeedBindForModule($module)
    {
        $allowed = array('vod', 'art', 'manga', 'topic', 'live', 'website', 'actor', 'role', 'gbook');
        return in_array($module, $allowed, true) ? $module : 'vod';
    }

    private function listTitleLangForRoute($module, $action)
    {
        if ($action === 'search') {
            return 'string_search_results';
        }
        if ($action === 'type') {
            return 'string_type_content';
        }
        if ($action === 'show') {
            return 'string_filter_results';
        }
        return '';
    }

    private function listTitleForRoute($route, $module, $action)
    {
        if ($action === 'search') {
            return '搜索结果';
        }
        if ($action === 'type') {
            return '分类内容';
        }
        if ($action === 'show') {
            return '筛选结果';
        }
        if ($action === 'index') {
            $labels = array(
                'vod' => '最近更新',
                'art' => '文章库',
                'manga' => '漫画库',
                'actor' => '明星库',
                'role' => '角色库',
                'website' => '网址导航',
                'topic' => '专题',
                'plot' => '剧情',
                'live' => '直播',
            );
            return isset($labels[$module]) ? $labels[$module] : '列表';
        }
        return '内容列表';
    }

    private function detailTitleForModule($module)
    {
        $labels = array(
            'vod' => '视频详情',
            'art' => '文章详情',
            'manga' => '漫画详情',
            'actor' => '演员详情',
            'role' => '角色详情',
            'website' => '网址详情',
            'topic' => '专题详情',
            'plot' => '剧情详情',
            'live' => '直播详情',
        );
        return isset($labels[$module]) ? $labels[$module] : '详情';
    }

    private function writePageJson($themeDir, $route, array $page, $force = false)
    {
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return false;
        }
        $dir = $themeDir . 'html/' . $parts[0] . '/';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }
        $file = $dir . $parts[1] . '.json';
        if (is_file($file) && !$force) {
            return true;
        }
        $json = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($file, $json . "\n") !== false;
    }

    private function isTwigTheme($themeDir)
    {
        $ini = rtrim((string) $themeDir, '/') . '/info.ini';
        if (!is_file($ini)) {
            return false;
        }
        $parsed = @parse_ini_file($ini);
        if (!is_array($parsed)) {
            return false;
        }
        return isset($parsed['engine']) && strtolower((string) $parsed['engine']) === 'twig';
    }

    private function themeLayoutBlockMap($themeDir)
    {
        return PageRegistry::layoutOptsForCompile($themeDir, 'index/index', array('page_type' => 'home'));
    }

    private function themeHasPageTpl($themeDir, $route)
    {
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return false;
        }
        $base = rtrim((string) $themeDir, '/') . '/html/' . $parts[0] . '/' . $parts[1];
        return is_file($base . '.twig') || is_file($base . '.draft.twig');
    }

    private function deletePageArtifacts($themeDir, $route)
    {
        $parts = explode('/', $route, 2);
        if (count($parts) < 2) {
            return;
        }
        $base = rtrim((string) $themeDir, '/') . '/html/' . $parts[0] . '/' . $parts[1];
        foreach (array('.json', '.draft.twig', '.twig') as $suffix) {
            if (is_file($base . $suffix)) {
                @unlink($base . $suffix);
            }
        }
    }

    /**
     * 状态页 public/*.twig 复制源：NP2 与其它 PB 主题均走通用 starter（物化时已写入主题）。
     *
     * @param string $themeDir
     * @return string
     */
    private function resolvePublicStatusRefDir($themeDir)
    {
        if (!defined('ROOT_PATH')) {
            return '';
        }
        $starter = ROOT_PATH . 'template/_shared/starter/html/public/';
        $legacy = ROOT_PATH . 'template/twigspike/html/public/';
        if (is_dir($starter) && is_file($starter . 'jump.twig')) {
            return $starter;
        }
        if (is_dir($legacy) && is_file($legacy . 'jump.twig')) {
            return $legacy;
        }
        return '';
    }

    /**
     * PB/Twig 主题需具备 public/jump 等状态页，否则 $this->error() / page_error 会白屏。
     * Think/Smarty 主题禁止写入（避免污染 default 等 html/*.twig）。
     * @param string $themeDir
     * @return bool
     */
    public function ensurePublicStatusTemplates($themeDir)
    {
        $themeDir = rtrim((string) $themeDir, '/') . '/';
        if ($themeDir === '' || !is_dir($themeDir)) {
            return false;
        }
        // 与 PageBuilderKit::themeAcceptsKitSync 对齐：非 Twig/PB 主题不写 public/*.twig
        if (class_exists('\\app\\common\\util\\PageBuilderKit')) {
            $kit = new PageBuilderKit();
            if (!$kit->themeAcceptsKitSync($themeDir)) {
                return true;
            }
        } elseif (!$this->isTwigTheme($themeDir)) {
            return true;
        }
        $destDir = $themeDir . 'html/public/';
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                return false;
            }
        }
        $refDir = $this->resolvePublicStatusRefDir($themeDir);
        if ($refDir === '') {
            return is_file($destDir . 'jump.twig');
        }
        $ok = true;
        $dh = @opendir($refDir);
        if (!$dh) {
            return false;
        }
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (substr($f, -5) !== '.twig') {
                continue;
            }
            $dest = $destDir . $f;
            // 始终用 starter/_status 覆盖旧路径（empty-state / 缺 CSS）
            if ($f !== '_status.twig' && is_file($dest)) {
                continue;
            }
            if (@copy($refDir . $f, $dest) === false) {
                $ok = false;
            }
        }
        closedir($dh);
        return $ok && is_file($destDir . 'jump.twig');
    }

    /**
     * @param string $themeDir
     * @return array
     */
    private function scaffoldFlagsForTheme($themeDir)
    {
        if ($themeDir === '' || $themeDir === '/') {
            return array(
                'list_sidebar_rank' => true,
                'detail_sidebar_rank' => false,
                'home_sidebar_rank' => false,
            );
        }
        if (class_exists('\app\common\util\ThemePreset')) {
            return \app\common\util\ThemePreset::scaffoldFlags($themeDir);
        }
        return array(
            'list_sidebar_rank' => true,
            'detail_sidebar_rank' => false,
            'home_sidebar_rank' => false,
        );
    }

    /**
     * 会员/认证路由：以 scaffold 结构为准，仅合并用户提交的 props。
     *
     * @param string $route
     * @param string $themeDir
     * @param array  $page
     * @return array
     */
    public function mergeMemberPageWithScaffold($route, $themeDir, array $page)
    {
        $route = PageRegistry::normalizeRoute($route);
        if (!PageRegistry::isDesignableRoute($route)
            || (!PageRegistry::isMemberShellRoute($route) && !PageRegistry::isAuthPageRoute($route))) {
            return $page;
        }
        $scaffold = $this->defaultPageForRoute($route, $themeDir);
        if (empty($scaffold) || !is_array($scaffold)) {
            return $page;
        }
        $userRegions = isset($page['regions']) && is_array($page['regions']) ? $page['regions'] : array();
        $scaffoldRegions = isset($scaffold['regions']) && is_array($scaffold['regions']) ? $scaffold['regions'] : array();
        $mergedRegions = $scaffoldRegions;
        if (isset($userRegions['content']) && is_array($userRegions['content'])) {
            $mergedRegions['content'] = $this->mergeBlockTreeProps(
                isset($scaffoldRegions['content']) ? $scaffoldRegions['content'] : array(),
                $userRegions['content']
            );
        }
        $out = $scaffold;
        $out['regions'] = $mergedRegions;
        $out['template'] = isset($scaffold['template']) ? (string) $scaffold['template'] : $route;
        $out['layout'] = isset($scaffold['layout']) ? (string) $scaffold['layout'] : 'base';
        $out['layout_mod'] = isset($scaffold['layout_mod']) ? (string) $scaffold['layout_mod'] : '';
        $out['page_type'] = isset($scaffold['page_type']) ? (string) $scaffold['page_type'] : 'member';
        if (PageRegistry::isAuthPageRoute($route)) {
            $out['regions']['site_header'] = array();
            $out['regions']['hero'] = array();
            $out['regions']['channel'] = array();
            $out['regions']['sidebar'] = array();
            $out['regions']['footer'] = array();
        }
        return $out;
    }

    /**
     * @param array $scaffoldBlocks
     * @param array $userBlocks
     * @return array
     */
    private function mergeBlockTreeProps(array $scaffoldBlocks, array $userBlocks)
    {
        $out = array();
        $count = count($scaffoldBlocks);
        for ($i = 0; $i < $count; $i++) {
            if (!isset($scaffoldBlocks[$i]) || !is_array($scaffoldBlocks[$i])) {
                continue;
            }
            $scaffold = $scaffoldBlocks[$i];
            $user = isset($userBlocks[$i]) && is_array($userBlocks[$i]) ? $userBlocks[$i] : array();
            $merged = $scaffold;
            if (!empty($user['props']) && is_array($user['props'])) {
                $defaults = $this->kit->defaultPropsForComponent((string) $scaffold['component']);
                $merged['props'] = array_merge(
                    is_array($defaults) ? $defaults : array(),
                    is_array($scaffold['props']) ? $scaffold['props'] : array(),
                    $user['props']
                );
            }
            if (isset($scaffold['slots']) && is_array($scaffold['slots'])) {
                $mergedSlots = array();
                foreach ($scaffold['slots'] as $slotKey => $slotChildren) {
                    if (!is_array($slotChildren)) {
                        continue;
                    }
                    $userSlot = array();
                    if (isset($user['slots'][$slotKey]) && is_array($user['slots'][$slotKey])) {
                        $userSlot = $user['slots'][$slotKey];
                    } elseif (isset($user['slots']) && is_array($user['slots'])) {
                        $keys = array_keys($scaffold['slots']);
                        $idx = array_search($slotKey, $keys, true);
                        if ($idx !== false && isset($user['slots'][$idx]) && is_array($user['slots'][$idx])) {
                            $userSlot = $user['slots'][$idx];
                        }
                    }
                    $mergedSlots[$slotKey] = $this->mergeBlockTreeProps($slotChildren, $userSlot);
                }
                $merged['slots'] = $mergedSlots;
            }
            $out[] = $merged;
        }
        return $out;
    }
}
