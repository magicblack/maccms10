/**
 * Theme Design — GrapesJS multipage page builder
 * Layout: left components/navigator | center live preview | right traits
 */
(function () {
    'use strict';

    var PB = window.TD_PB || {};
    var editor = null;
    var saveTimer = null;
    var previewSeq = 0;
    var loading = true;
    var suppressSave = false;
    // 插入 undo 批处理锚点：一次组件插入会在 UndoManager 里产生 N 条碎片记录
    // （壳标志/属性等每条 set 各占一条），单击「撤销」只弹最近一条、界面无变化。
    // 锚点记录插入前的树行数，undo/redo 批量弹到锚点为止；选中变化/移动时清锚。
    // 已知取舍：插入后若再改 trait 再撤销，批量会连带回退那次 trait 修改。
    var undoRowsAnchor = null;
    var undoRedoRowsAnchor = null;
    var previewScrollY = 0;
    var previewScrollX = 0;
    var skipScrollOnce = false;
    var PREVIEW_WIDTH_DESKTOP = 1280;
    var PREVIEW_WIDTH_MOBILE = 440;
    var previewScaleTimer = null;
    var previewScaleRetries = 0;
    var appliedPreviewLogicalW = 1280;
    var appliedPreviewScale = 1;
    var previewUrlFetch = null;

    var SORT_OPTS = null;
    var LAYOUT_OPTS = null;
    var FEED_RENDER_OPTS = null;
    var PERIOD_OPTS = null;
    var VARIANT_OPTS = null;
    var ICON_OPTS = null;
    var NP2_LAYOUT_OPTS = null;
    var NP2_ICON_OPTS = null;
    var MEMBER_VISUAL_VARIANT_OPTS = null;
    var BIND_OPTS = null;
    var CAROUSEL_BIND_OPTS = null;
    var CARD_OPTS = null;
    var RATIO_OPTS = null;
    var COVER_RATIO_KNOWN = ['default', '16-9', '4-3', '3-4', '2-3', '69-40', '20-29', '1-1'];
    var coverRatioTraitBound = {};
    var RANK_STYLE_OPTS = null;
    var FOOTER_VARIANT_OPTS = null;
    var COVER_LIST_COMPS = ['content_feed', 'vod_list', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'role_list'];
    var PB_CATALOG = PB.componentCatalog || null;
    var CATALOG_TRAIT_IDS = ['actor_detail', 'actor_list', 'ads_grid', 'app_top_chrome', 'art_detail', 'art_list', 'auth_form', 'auth_modals', 'auth_page', 'benefits_panel', 'bottom_nav', 'breadcrumb', 'cards_list', 'carousel-compact', 'carousel-fullbleed', 'channel_strip', 'cinema_detail_stage', 'columns', 'content_feed', 'empty_state', 'favs_list', 'filmstrip_layout', 'filter_chips', 'filter_panel', 'gbook_form', 'gbook_list', 'grid', 'header_decor', 'live_list', 'live_player', 'manga_detail', 'manga_genre_nav', 'manga_home_rank', 'manga_home_row', 'manga_home_spotlight', 'manga_list', 'manga_mosaic_hero', 'manga_player', 'media_intro_panel', 'media_related_panel', 'member_filter_tabs', 'member_hub_menu', 'member_nav', 'member_profile_card', 'member_shell', 'notify_bell', 'novel_chapter_catalog', 'novel_genre_nav', 'novel_home_banner', 'novel_home_dock', 'novel_home_grid', 'novel_home_rank', 'novel_home_row', 'novel_home_zone', 'novel_player', 'order_list', 'paging', 'pay_shell', 'plays_list', 'rank_card', 'rank_feed', 'rank_inline', 'rank_list', 'role_detail', 'role_list', 'sec_heading', 'section', 'site_footer', 'site_nav', 'spacer', 'topic_detail', 'topic_list', 'user_bind_panel', 'user_cash_panel', 'user_checkin_panel', 'user_info_panel', 'user_invite_panel', 'user_mall_panel', 'user_member_hub', 'user_notify_panel', 'user_plog_panel', 'user_portrait_panel', 'user_social_panel', 'vod_carousel', 'vod_detail', 'vod_list', 'vod_list_np2', 'vod_player', 'website_detail', 'website_list'];
    var CAROUSEL_CATALOG_COMPS = ['vod_carousel', 'carousel-fullbleed', 'carousel-compact', 'manga_home_spotlight', 'manga_mosaic_hero', 'novel_home_banner'];
    var CATALOG_PAGING_COVER_COMPS = {
        vod_list: { relation: true },
        art_list: { relation: false },
        manga_list: { relation: false },
        topic_list: { relation: false },
        live_list: { relation: false },
        website_list: { relation: false },
        actor_list: { relation: false },
        role_list: { relation: false },
        gbook_list: { relation: false }
    };

    function catalogSkipsPagingCoverProp(comp, name) {
        if (!CATALOG_PAGING_COVER_COMPS[comp]) {
            return false;
        }
        return name === 'paging' || name === 'pageurl' || name === 'relation' || name === 'card' || name === 'ratio';
    }

    function catalogUsesCoverTraits(comp, meta) {
        if (CATALOG_PAGING_COVER_COMPS[comp]) {
            return !!(meta && meta.props && (meta.props.card || meta.props.ratio));
        }
        if (!meta || !meta.props || !meta.props.ratio) {
            return false;
        }
        var ratioDef = meta.props.ratio;
        return ratioDef && ratioDef.trait === 'cover_ratio';
    }

    function catalogSkipsCoverProp(comp, meta, name) {
        if (!catalogUsesCoverTraits(comp, meta)) {
            return false;
        }
        return name === 'card' || name === 'ratio';
    }

    function carouselCatalogBind(model, comp) {
        var bind = model && model.get('bind');
        if (bind) {
            return String(bind);
        }
        if (comp && comp.indexOf('manga_') === 0) {
            return 'manga';
        }
        if (comp && comp.indexOf('novel_') === 0) {
            return 'art';
        }
        return 'vod';
    }

    function traitsFromCatalogContentFeed(model, meta) {
        var t = [];
        t.push({
            type: 'select',
            name: 'bind',
            label: traitLabel('trait_bind', 'Content type'),
            changeProp: 1,
            options: BIND_OPTS || []
        });
        var cfBind = contentFeedBind(model);
        if (cfBind === 'gbook') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'List title'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            pushListPagingTraits(t, model, 'content_feed', false);
        } else {
            if (listBindUsesLayout(cfBind)) {
                t.push({ type: 'select', name: 'render', label: traitLabel('trait_layout', 'Layout'), changeProp: 1, options: listLayoutOpts(model) });
            }
            if (listBindUsesCover(cfBind)) {
                pushCoverTraits(t);
            }
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            if (listBindUsesCategory(cfBind)) {
                t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(bindMid(cfBind)) });
            }
            if (listBindUsesSort(cfBind)) {
                t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            }
            pushListPagingTraits(t, model, 'content_feed', listBindUsesRelation(cfBind));
            // traitNames(comp) 无 model；须短接，避免 model.get 抛错导致无法插入
            if (!isPagingEnabled(model) && !(model && model.get('relation'))) {
                t.push({ type: 'checkbox', name: 'refresh', label: traitLabel('trait_refresh', '换一换'), changeProp: 1 });
            }
        }
        return t.length ? t : null;
    }

    function traitsFromCatalogAuthPage(model, meta) {
        var t = [];
        var authRoute = String(PB.route || '');
        var panelOpts = [];
        if (authRoute === 'user/login') {
            panelOpts.push({ value: 'login', name: traitLabel('trait_auth_panel_login', 'Login') });
        } else if (authRoute === 'user/findpass') {
            panelOpts.push({ value: 'findpass', name: traitLabel('trait_auth_panel_findpass', 'Find password') });
        } else {
            panelOpts.push({ value: 'login', name: traitLabel('trait_auth_panel_login', 'Login') });
            panelOpts.push({ value: 'findpass', name: traitLabel('trait_auth_panel_findpass', 'Find password') });
        }
        t.push({ type: 'select', name: 'panel', label: traitLabel('trait_auth_panel', 'Panel'), changeProp: 1, options: panelOpts });
        t.push({ type: 'checkbox', name: 'expand_invite', label: traitLabel('trait_expand_invite', 'Expand invite'), changeProp: 1 });
        t.push({ type: 'text', name: 'hero_title', label: traitLabel('trait_hero_title', 'Hero title'), changeProp: 1 });
        t.push({ type: 'text', name: 'hero_desc', label: traitLabel('trait_hero_desc', 'Hero description'), changeProp: 1 });
        t.push({ type: 'pb-image', name: 'hero_image', label: traitLabel('trait_hero_image', 'Hero image'), changeProp: 1 });
        t.push({ type: 'pb-image', name: 'hero_image_mobile', label: traitLabel('trait_hero_image_mobile', 'Mobile hero image'), changeProp: 1 });
        t.push({ type: 'checkbox', name: 'show_hero', label: traitLabel('trait_show_hero', 'Show hero'), changeProp: 1 });
        t.push({ type: 'select', name: 'layout', label: traitLabel('trait_auth_layout', 'Layout'), changeProp: 1, options: [
            { value: 'split', name: traitLabel('trait_auth_layout_split', 'Split') },
            { value: 'center', name: traitLabel('trait_auth_layout_center', 'Center') },
            { value: 'minimal', name: traitLabel('trait_auth_layout_minimal', 'Minimal') }
        ] });
        return t.length ? t : null;
    }

    function catalogComponentMeta(comp) {
        if (!PB_CATALOG || !PB_CATALOG.components) {
            return null;
        }
        var entry = PB_CATALOG.components[comp];
        if (!entry || !entry.meta) {
            return null;
        }
        return entry.meta;
    }

    function applyCatalogPropDefaults(comp, model, props) {
        var meta = catalogComponentMeta(comp);
        if (!meta || !meta.props || !model) {
            return;
        }
        Object.keys(meta.props).forEach(function (name) {
            var def = meta.props[name];
            if (!def || typeof def !== 'object' || def.default === undefined) {
                return;
            }
            var fromProps = props && props[name] !== undefined && props[name] !== null;
            var fromModel = model.get(name);
            if (fromProps || (fromModel !== undefined && fromModel !== null && fromModel !== '')) {
                return;
            }
            model.set(name, def.default, { silent: true });
        });
    }

    function traitLabelForProp(propName, def) {
        var traitKey = (def && def.trait) ? def.trait : propName;
        if (traitKey === 'empty_preset') {
            return traitLabel('trait_empty_preset', 'Preset');
        }
        if (traitKey === 'nav_max') {
            return traitLabel('trait_nav_max', 'Nav items');
        }
        if (traitKey === 'nav_search') {
            return traitLabel('trait_nav_search', 'Search box');
        }
        if (traitKey === 'show_intro') {
            return traitLabel('trait_show_intro', 'Show synopsis');
        }
        if (traitKey === 'show_toc') {
            return traitLabel('trait_show_toc', 'Show TOC');
        }
        if (traitKey === 'show_chapters') {
            return traitLabel('trait_show_chapters', 'Show chapters');
        }
        if (traitKey === 'title') {
            return traitLabel('trait_title', 'Title');
        }
        if (traitKey === 'title_lang') {
            return traitLabel('trait_title_lang', 'Title lang key');
        }
        if (traitKey === 'count') {
            return traitLabel('trait_count', 'Count');
        }
        if (traitKey === 'by') {
            return traitLabel('trait_sort', 'Sort');
        }
        if (traitKey === 'preset') {
            return traitLabel('trait_preset', 'Preset');
        }
        return traitLabel('trait_' + traitKey, propName);
    }

    function traitsFromCatalog(comp, model) {
        var meta = catalogComponentMeta(comp);
        if (!meta || !meta.props) {
            return null;
        }
        if (comp === 'content_feed') {
            return traitsFromCatalogContentFeed(model, meta);
        }
        if (comp === 'auth_page') {
            return traitsFromCatalogAuthPage(model, meta);
        }
        if (comp === 'bottom_nav') {
            return [{
                type: 'pb-bottom-nav-items',
                name: 'items',
                label: traitLabel('trait_bottom_nav_items', 'Menu items'),
                changeProp: 1
            }];
        }
        if (comp === 'ads_grid') {
            return [
                {
                    type: 'select',
                    name: 'variant',
                    label: traitLabel('trait_variant', 'Variant'),
                    changeProp: 1,
                    options: [
                        { value: 'default', name: traitLabel('trait_ads_variant_default', 'Default grid') },
                        { value: 'splash', name: traitLabel('trait_ads_variant_splash', 'Splash strip') }
                    ]
                },
                {
                    type: 'pb-ads-grid-items',
                    name: 'items',
                    label: traitLabel('trait_ads_grid_items', 'Ad items'),
                    changeProp: 1
                }
            ];
        }
        var t = [];
        var props = meta.props;
        var names = Object.keys(props);
        for (var i = 0; i < names.length; i++) {
            var name = names[i];
            var def = props[name];
            if (!def || typeof def !== 'object') {
                continue;
            }
            if (def.type === 'array') {
                continue;
            }
            if (catalogSkipsPagingCoverProp(comp, name)) {
                continue;
            }
            if (catalogSkipsCoverProp(comp, meta, name)) {
                continue;
            }
            if (comp === 'paging' && name === 'class') {
                continue;
            }
            if (comp === 'header_decor' && name === 'image') {
                continue;
            }
            if (comp === 'sec_heading' && name === 'title_lang') {
                continue;
            }
            if (comp === 'rank_list' && name === 'mid') {
                continue;
            }
            var traitKey = def.trait || name;
            var label = traitLabelForProp(name, def);
            if (def.type === 'number') {
                var numTrait = {
                    type: 'number',
                    name: name,
                    label: label,
                    changeProp: 1,
                    min: def.min != null ? def.min : undefined,
                    max: def.max != null ? def.max : undefined
                };
                t.push(numTrait);
            } else if (def.type === 'boolean') {
                t.push({ type: 'checkbox', name: name, label: label, changeProp: 1 });
            } else if (def.type === 'category') {
                var catMid = def.mid != null ? def.mid : 1;
                if (CAROUSEL_CATALOG_COMPS.indexOf(comp) >= 0) {
                    catMid = bindMid(carouselCatalogBind(model, comp));
                } else if (comp === 'media_related_panel' && name === 'type') {
                    catMid = bindMid(String(model && model.get('bind') || 'vod'));
                }
                t.push({
                    type: 'select',
                    name: name,
                    label: label,
                    changeProp: 1,
                    options: catTraitOptions(catMid)
                });
            } else if (def.type === 'enum') {
                var opts = (def.values || []).map(function (val) {
                    var optLabel = String(val);
                    if (traitKey === 'sort' && SORT_OPTS) {
                        var so = SORT_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (so) {
                            optLabel = so.name;
                        }
                    }
                    if (traitKey === 'by' && SORT_OPTS) {
                        var boSort = SORT_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (boSort) {
                            optLabel = boSort.name;
                        }
                    }
                    if (traitKey === 'layout' && LAYOUT_OPTS) {
                        var lo = LAYOUT_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (lo) {
                            optLabel = lo.name;
                        }
                    }
                    if (traitKey === 'variant' && VARIANT_OPTS) {
                        var vo = VARIANT_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (vo) {
                            optLabel = vo.name;
                        }
                    }
                    if (traitKey === 'member_visual' && MEMBER_VISUAL_VARIANT_OPTS) {
                        var mvo = MEMBER_VISUAL_VARIANT_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (mvo) {
                            optLabel = mvo.name;
                        }
                    }
                    if (traitKey === 'icon' && ICON_OPTS) {
                        var io = ICON_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (io) {
                            optLabel = io.name;
                        }
                    }
                    if (traitKey === 'empty_preset') {
                        if (val === '') {
                            optLabel = traitLabel('trait_empty_preset_none', 'Default');
                        } else if (val === 'filter') {
                            optLabel = traitLabel('trait_empty_preset_filter', 'Filter empty');
                        }
                    }
                    if (traitKey === 'preset' && comp === 'novel_home_row') {
                        if (val === 'hot') {
                            optLabel = traitLabel('pb_novel_row_hot', 'Hot');
                        } else if (val === 'new') {
                            optLabel = traitLabel('pb_novel_row_new', 'New');
                        } else if (val === 'finish') {
                            optLabel = traitLabel('pb_novel_row_finish', 'Finished');
                        }
                    }
                    if (traitKey === 'card' && CARD_OPTS) {
                        var co = CARD_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (co) {
                            optLabel = co.name;
                        }
                    }
                    if (traitKey === 'bind') {
                        var bindOpts = BIND_OPTS;
                        if (CAROUSEL_CATALOG_COMPS.indexOf(comp) >= 0 && CAROUSEL_BIND_OPTS) {
                            bindOpts = CAROUSEL_BIND_OPTS;
                        }
                        var bo = bindOpts.filter(function (x) { return x.value === val; })[0];
                        if (bo) {
                            optLabel = bo.name;
                        }
                    }
                    if (traitKey === 'period' && PERIOD_OPTS) {
                        var po = PERIOD_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (po) {
                            optLabel = po.name;
                        }
                    }
                    if (traitKey === 'rank_style' && RANK_STYLE_OPTS) {
                        var ro = RANK_STYLE_OPTS.filter(function (x) { return x.value === val; })[0];
                        if (ro) {
                            optLabel = ro.name;
                        }
                    }
                    if (traitKey === 'auth_layout') {
                        if (val === 'split') {
                            optLabel = traitLabel('trait_auth_layout_split', 'Split');
                        } else if (val === 'center') {
                            optLabel = traitLabel('trait_auth_layout_center', 'Center');
                        } else if (val === 'minimal') {
                            optLabel = traitLabel('trait_auth_layout_minimal', 'Minimal');
                        }
                    }
                    if (traitKey === 'spacer_size') {
                        if (val === 'sm') {
                            optLabel = traitLabel('trait_spacer_sm', 'Small (12px)');
                        } else if (val === 'md') {
                            optLabel = traitLabel('trait_spacer_md', 'Medium (24px)');
                        } else if (val === 'lg') {
                            optLabel = traitLabel('trait_spacer_lg', 'Large (48px)');
                        }
                    }
                    if (traitKey === 'filter_preset') {
                        if (val === 'vod_show') {
                            optLabel = 'VOD show filters';
                        } else if (val === 'vod_type') {
                            optLabel = 'VOD subtype chips';
                        }
                    }
                    if (traitKey === 'grid_variant') {
                        if (val === 'auto') {
                            optLabel = traitLabel('trait_grid_variant_auto', 'Auto fill');
                        } else if (val === 'rankmods') {
                            optLabel = traitLabel('trait_grid_variant_rankmods', 'Rank modules');
                        }
                    }
                    if (comp === 'vod_list_np2') {
                        if (traitKey === 'layout' && NP2_LAYOUT_OPTS) {
                            var nlo = NP2_LAYOUT_OPTS.filter(function (x) { return x.value === val; })[0];
                            if (nlo) {
                                optLabel = nlo.name;
                            }
                        }
                        if (traitKey === 'icon' && NP2_ICON_OPTS) {
                            var nio = NP2_ICON_OPTS.filter(function (x) { return x.value === val; })[0];
                            if (nio) {
                                optLabel = nio.name;
                            }
                        }
                        if (traitKey === 'title_mode') {
                            if (val === 'custom') {
                                optLabel = traitLabel('trait_title_mode_custom', 'Custom title');
                            } else if (val === 'type_name') {
                                optLabel = traitLabel('trait_title_mode_type', 'Category name');
                            }
                        }
                        if (traitKey === 'type_pick') {
                            if (val === 'all') {
                                optLabel = traitLabel('trait_type_all', 'All videos');
                            } else if (val === 'category') {
                                optLabel = traitLabel('trait_type_category', 'Category');
                            } else if (val === 'top_types') {
                                optLabel = traitLabel('trait_type_top_types', 'Top categories');
                            }
                        }
                        if (traitKey === 'order') {
                            if (val === 'desc') {
                                optLabel = traitLabel('trait_order_desc', 'Descending');
                            } else if (val === 'asc') {
                                optLabel = traitLabel('trait_order_asc', 'Ascending');
                            }
                        }
                    }
                    return { value: val, name: optLabel };
                });
                if (traitKey === 'layout' && comp === 'vod_list_np2' && NP2_LAYOUT_OPTS) {
                    opts = NP2_LAYOUT_OPTS;
                } else if (traitKey === 'icon' && comp === 'vod_list_np2' && NP2_ICON_OPTS) {
                    opts = NP2_ICON_OPTS;
                } else if (traitKey === 'layout' && (comp === 'vod_list' || comp === 'art_list' || comp === 'manga_list')) {
                    opts = listLayoutOpts(model);
                }
                t.push({ type: 'select', name: name, label: label, changeProp: 1, options: opts });
            } else if (traitKey === 'hero_image' || traitKey === 'hero_image_mobile') {
                t.push({ type: 'pb-image', name: name, label: label, changeProp: 1 });
            } else if (def.trait === 'select' && Array.isArray(def.options) && def.options.length) {
                var selOpts = def.options.map(function (val) {
                    return { value: String(val), name: String(val) };
                });
                t.push({ type: 'select', name: name, label: label, changeProp: 1, options: selOpts });
            } else {
                t.push({ type: 'text', name: name, label: label, changeProp: 1 });
            }
        }
        var pagingCfg = CATALOG_PAGING_COVER_COMPS[comp];
        if (pagingCfg) {
            if (props.paging) {
                pushListPagingTraits(t, model, comp, pagingCfg.relation);
            }
            if (props.card || props.ratio) {
                pushCoverTraits(t);
            }
        } else if (catalogUsesCoverTraits(comp, meta)) {
            pushCoverTraits(t);
        }
        return t.length ? t : null;
    }

    function ensureComponentCatalog(cb) {
        if (PB_CATALOG && PB_CATALOG.components) {
            if (typeof cb === 'function') {
                cb(PB_CATALOG);
            }
            return;
        }
        var url = PB.urls && PB.urls.componentCatalog ? PB.urls.componentCatalog : '';
        if (!url || typeof $ === 'undefined') {
            if (typeof cb === 'function') {
                cb(null);
            }
            return;
        }
        $.getJSON(url, function (res) {
            if (res && res.code === 1 && res.data) {
                PB_CATALOG = res.data;
                PB.componentCatalog = res.data;
            }
            if (typeof cb === 'function') {
                cb(PB_CATALOG);
            }
        });
    }

    var REGION_NAMES = (PB.regionNames && PB.regionNames.length)
        ? PB.regionNames.slice()
        : ['site_header', 'hero', 'channel', 'content', 'sidebar', 'footer'];
    var APP_TOP_CHROME_MANAGED = ['site_header', 'hero', 'channel'];
    var CHROME_REGIONS = ['site_header', 'hero', 'channel', 'footer'];
    var CHROME_COMPONENTS = ['site_nav', 'header_decor', 'channel_strip', 'site_footer', 'bottom_nav'];
    /** layout / 顶栏隐式 include，全站自带，不可拖入页面 */
    var SHELL_COMPONENTS = ['auth_modals', 'notify_bell'];
    /** member_shell 内硬编码碎片，不可单独拖入 */
    var MEMBER_INTERNAL = ['member_nav', 'member_profile_card', 'member_hub_menu', 'member_filter_tabs', 'auth_form'];
    var REGION_PATH_PREFIX = {
        site_header: 'sh',
        hero: 'hero',
        channel: 'ch',
        content: '',
        sidebar: 'sb',
        footer: 'ft'
    };
    var REGION_PATH_TO_NAME = {
        sh: 'site_header',
        hero: 'hero',
        ch: 'channel',
        sb: 'sidebar',
        ft: 'footer'
    };
    var SIDEBAR_TOP = ['columns', 'grid', 'spacer', 'rank_list', 'rank_card', 'rank_inline', 'rank_feed', 'gbook_form', 'gbook_list'];

    function regionLabel(name) {
        var key = 'pb_region_' + name;
        return traitLabel(key, name);
    }

    function isChromeComponent(comp) {
        return CHROME_COMPONENTS.indexOf(comp) >= 0;
    }

    function isShellComponent(comp) {
        return SHELL_COMPONENTS.indexOf(comp) >= 0;
    }

    function stripShellBlocks(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.filter(function (b) {
            return b && b.component && !isShellComponent(b.component);
        }).map(normalizeBlockTree);
    }

    function pageMode() {
        return PB.page_mode || 'build';
    }

    function isMemberMode() {
        return pageMode() === 'member';
    }

    function isAuthMode() {
        return pageMode() === 'auth';
    }

    function isAuthModalMode() {
        return pageMode() === 'auth_modal';
    }

    function isRouteLockedMode() {
        return isMemberMode() || isAuthMode() || isAuthModalMode();
    }

    function isMemberInternalComponent(comp) {
        return MEMBER_INTERNAL.indexOf(comp) >= 0;
    }

    function isChromeRegion(name) {
        return CHROME_REGIONS.indexOf(name) >= 0;
    }

    function regionContentHasAppTopChrome(page) {
        page = page || PB.page || {};
        var content = [];
        if (page.regions && Array.isArray(page.regions.content)) {
            content = page.regions.content;
        } else if (Array.isArray(page.blocks)) {
            content = page.blocks;
        }
        for (var i = 0; i < content.length; i++) {
            if (content[i] && content[i].component === 'app_top_chrome') {
                return true;
            }
        }
        return false;
    }

    function regionSiteHeaderHasAppTopChrome(page) {
        page = page || PB.page || {};
        var header = (page.regions && Array.isArray(page.regions.site_header)) ? page.regions.site_header : [];
        for (var i = 0; i < header.length; i++) {
            if (header[i] && header[i].component === 'app_top_chrome') {
                return true;
            }
        }
        return false;
    }

    /** NP2 site_header 顶栏：保留 hero/channel；legacy 为 content 内顶栏并隐藏三区 */
    function siteHeaderUsesAppTopChrome(page) {
        if (PB.siteHeaderChrome) {
            return true;
        }
        page = page || PB.page || {};
        if (regionSiteHeaderHasAppTopChrome(page)) {
            return true;
        }
        return homepageAppTopChromeMode(page);
    }

    /** @deprecated 使用 siteHeaderUsesAppTopChrome；保留兼容旧草稿 content 顶栏 */
    function homepageAppTopChromeMode(page) {
        if (PB.appTopChromeTheme && !PB.siteHeaderChrome) {
            return true;
        }
        page = page || PB.page || {};
        if (regionSiteHeaderHasAppTopChrome(page)) {
            return false;
        }
        if (!regionContentHasAppTopChrome(page)) {
            return false;
        }
        var lc = page.layout_compile;
        if (!lc || typeof lc !== 'object' || lc.chrome_fallbacks === false) {
            return true;
        }
        if (PB.regionNames && PB.regionNames.length) {
            return APP_TOP_CHROME_MANAGED.every(function (n) {
                return PB.regionNames.indexOf(n) < 0;
            });
        }
        return true;
    }

    function effectiveRegionNames(page) {
        if (siteHeaderUsesAppTopChrome(page)) {
            return REGION_NAMES;
        }
        if (homepageAppTopChromeMode(page)) {
            return REGION_NAMES.filter(function (n) {
                return APP_TOP_CHROME_MANAGED.indexOf(n) < 0;
            });
        }
        return REGION_NAMES;
    }

    function stripAppTopChromeFromCollectedPage(out) {
        if (siteHeaderUsesAppTopChrome(out)) {
            if (regionContentHasAppTopChrome(out) && out.regions) {
                var content = out.regions.content || [];
                var chromeBlock = null;
                var rest = [];
                content.forEach(function (b) {
                    if (!chromeBlock && b && b.component === 'app_top_chrome') {
                        chromeBlock = b;
                    } else {
                        rest.push(b);
                    }
                });
                out.regions.content = rest;
                if (chromeBlock && !regionSiteHeaderHasAppTopChrome(out)) {
                    out.regions.site_header = [chromeBlock];
                    out.layout_compile = $.extend({
                        chrome_fallbacks: false,
                        site_header: true,
                        hero: true,
                        channel: false,
                        footer: false
                    }, out.layout_compile || {});
                    out.layout_compile.chrome_fallbacks = false;
                    out.layout_compile.site_header = true;
                }
            }
            return out;
        }
        if (!homepageAppTopChromeMode(out)) {
            return out;
        }
        if (!out.regions) {
            out.regions = {};
        }
        out.regions.site_header = [];
        out.regions.hero = [];
        out.regions.channel = [];
        out.chrome = [];
        out.chrome_opts = { header_decor: false, channel_strip: false };
        out.layout_compile = $.extend({
            chrome_fallbacks: false,
            site_header: false,
            hero: false,
            channel: false,
            footer: false
        }, out.layout_compile || {});
        out.layout_compile.chrome_fallbacks = false;
        out.layout_compile.site_header = false;
        out.layout_compile.hero = false;
        out.layout_compile.channel = false;
        var extra = String(out.extra_page_class || '').trim();
        if (extra.indexOf('np2-home-page') < 0) {
            out.extra_page_class = (extra + ' np2-home-page').trim();
        }
        if (!out.twig_sets || typeof out.twig_sets !== 'object') {
            out.twig_sets = { np2_tab: 'home' };
        } else if (!out.twig_sets.np2_tab) {
            out.twig_sets.np2_tab = 'home';
        }
        return out;
    }

    function pageOwnsHeroRegion() {
        if (siteHeaderUsesAppTopChrome()) {
            return true;
        }
        if (homepageAppTopChromeMode()) {
            return false;
        }
        var mod = (PB.page && PB.page.layout_mod) || '';
        var route = PB.route || '';
        return mod === 'home' || mod === 'manga-home' || mod === 'novel-home'
            || route === 'index/index' || route === 'manga/index' || route === 'art/index';
    }

    /** 站点首页：频道条可由编辑器拖入/删除，并落盘到 chrome_opts */
    function pageOwnsChannelRegion() {
        if (homepageAppTopChromeMode()) {
            return false;
        }
        var route = String(PB.route || '');
        if (route === 'index/index') {
            return true;
        }
        var mod = '';
        if (PB.page && PB.page.layout_mod) {
            mod = String(PB.page.layout_mod);
        }
        return mod === 'home';
    }

    function isChromeModel(model) {
        if (!model || model.is('wrapper')) {
            return false;
        }
        var comp = model.getAttributes()['data-component'] || '';
        if (isChromeComponent(comp)) {
            return true;
        }
        var rn = model.getAttributes()['data-pb-region'] || '';
        return isChromeRegion(rn);
    }

    /** 仅 chrome 区域容器（顶栏/Hero/频道/页脚），不可直接选中编辑 */
    function isChromeRegionWrapper(model) {
        if (!model || model.is('wrapper')) {
            return false;
        }
        if (model.getAttributes()['data-component']) {
            return false;
        }
        var rn = model.getAttributes()['data-pb-region'] || '';
        return isChromeRegion(rn);
    }

    /** 顶栏/装饰/频道/页脚等 chrome 子组件：不可拖拽删除，但可选中改属性 */
    function isChromeComponentModel(model) {
        if (!model || model.is('wrapper')) {
            return false;
        }
        var comp = model.getAttributes()['data-component'] || '';
        return isChromeComponent(comp);
    }

    function lockChromeEditing() {
        if (!editor) {
            return;
        }
        editor.getWrapper().components().forEach(function (regionModel) {
            var rn = regionModel.getAttributes()['data-pb-region'] || '';
            if (!isChromeRegion(rn)) {
                return;
            }
            regionModel.set({
                selectable: false,
                hoverable: false,
                highlightable: false,
                editable: false,
                droppable: false
            });
            regionModel.components().forEach(function (ch) {
                var childComp = ch.getAttributes()['data-component'] || '';
                // 页面自有 hero：允许删除 header_decor / 漫画小说组件，否则灰条永远删不掉
                if (rn === 'hero' && pageOwnsHeroRegion()) {
                    ch.set({
                        selectable: true,
                        hoverable: true,
                        highlightable: true,
                        editable: false,
                        draggable: true,
                        removable: true,
                        copyable: false
                    });
                    return;
                }
                if (rn === 'channel' && pageOwnsChannelRegion() && childComp === 'channel_strip') {
                    ch.set({
                        selectable: true,
                        hoverable: true,
                        highlightable: true,
                        editable: false,
                        draggable: true,
                        removable: true,
                        copyable: false
                    });
                    return;
                }
                ch.set({
                    selectable: true,
                    hoverable: true,
                    highlightable: true,
                    editable: false,
                    draggable: false,
                    removable: false,
                    copyable: false
                });
            });
            // 空的可选 hero：结构树仍显示但可选中后用 Del 清空已无子块；标记 layer 提示
            if (rn === 'hero' && pageOwnsHeroRegion() && regionModel.components().length === 0) {
                regionModel.set({
                    selectable: true,
                    hoverable: true,
                    highlightable: true,
                    droppable: true
                });
            }
            if (rn === 'channel' && pageOwnsChannelRegion()) {
                regionModel.set({
                    selectable: true,
                    hoverable: true,
                    highlightable: true,
                    droppable: function (dragged) {
                        if (!dragged || typeof dragged.getAttributes !== 'function') {
                            return false;
                        }
                        var childComp = dragged.getAttributes()['data-component'] || '';
                        return childComp === 'channel_strip';
                    }
                });
            }
        });
    }

    /** 主内容/侧栏区域壳不可选中，避免点结构树或预览时误选「主内容」而非子块 */
    function lockRegionShellSelection() {
        if (!editor) {
            return;
        }
        editor.getWrapper().components().forEach(function (regionModel) {
            var rn = regionModel.getAttributes()['data-pb-region'] || '';
            if (!rn || isChromeRegion(rn)) {
                return;
            }
            regionModel.set({
                selectable: false,
                hoverable: false,
                highlightable: false,
                draggable: false,
                removable: false
            });
        });
    }

    function isPageRegionWrapper(model) {
        if (!model || model.is('wrapper')) {
            return false;
        }
        if (model.getAttributes()['data-component']) {
            return false;
        }
        var rn = model.getAttributes()['data-pb-region'] || '';
        return rn === 'content' || rn === 'sidebar';
    }

    function lockMemberBlockTree(model) {
        if (!model) {
            return;
        }
        model.set({
            selectable: true,
            hoverable: true,
            highlightable: true,
            editable: false,
            draggable: false,
            removable: false,
            copyable: false
        });
        model.components().forEach(function (ch) {
            lockMemberBlockTree(ch);
        });
    }

    function lockMemberEditing() {
        if (!editor || (!isMemberMode() && !isAuthMode())) {
            return;
        }
        editor.getWrapper().components().forEach(function (regionModel) {
            var rn = regionModel.getAttributes()['data-pb-region'] || '';
            if (rn !== 'content' && rn !== 'sidebar') {
                return;
            }
            regionModel.set({
                droppable: false,
                selectable: false,
                hoverable: true,
                highlightable: true
            });
            regionModel.components().forEach(function (ch) {
                lockMemberBlockTree(ch);
            });
        });
    }

    function applyPageModeUi() {
        var locked = isRouteLockedMode();
        document.body.classList.toggle('pb-route-locked', locked);
        var $blocksTab = $('.td-pb-side-tab[data-tab="blocks"]');
        if (locked) {
            $blocksTab.hide();
            $('.td-pb-side-tab[data-tab="layers"]').addClass('active');
            $blocksTab.removeClass('active');
            $('#pb-blocks-panel').removeClass('active');
            $('#pb-layers-panel').addClass('active');
            $('#pb-center-hint').text(traitLabel('pb_member_mode_hint', 'Member pages use fixed layout. Click the preview or structure tree; edit properties on the right.'));
        } else {
            $blocksTab.show();
        }
        if (isAuthModalMode()) {
            $('#pb-gjs-dock').attr('hidden', 'hidden');
            $('#pb-center-hint').text(traitLabel('pb_auth_modal_mode_hint', 'Edit global login popup copy and preview on the homepage.'));
            showAuthModalTraitsPanel();
        } else {
            $('#pb-gjs-dock').removeAttr('hidden');
            $('#pb-auth-modal-traits').hide().empty();
        }
    }

    function showAuthModalTraitsPanel() {
        if (!isAuthModalMode()) {
            return;
        }
        $('#pb-traits-empty').hide();
        $('#pb-traits-panel').hide();
        $('#pb-chrome-cats, #pb-chrome-logo, #pb-chrome-decor, #pb-chrome-footer-logo').prop('hidden', true);
        var $panel = $('#pb-auth-modal-traits');
        $panel.show();
        if (!$panel.find('.pb-auth-modal-input').length) {
            initAuthModalEditor();
        }
    }

    function showMemberLockedHint() {
        var msg = traitLabel('pb_member_locked_hint', 'This block is fixed for this member page. Select member_shell or a panel with editable properties.');
        $('#pb-traits-panel').hide();
        $('#pb-chrome-cats, #pb-chrome-logo, #pb-chrome-decor, #pb-chrome-footer-logo').prop('hidden', true);
        $('#pb-traits-empty').show().html('<p>' + escHtml(msg) + '</p>');
    }

    function showMemberNoTraitsHint(comp) {
        var msg = traitLabel('pb_member_select_editable_hint', 'This block has no editable properties. Select member_shell, plays_list, pay_shell, auth_page, or empty_state in the structure tree.');
        $('#pb-traits-panel').hide();
        $('#pb-chrome-cats, #pb-chrome-logo, #pb-chrome-decor, #pb-chrome-footer-logo').prop('hidden', true);
        $('#pb-traits-empty').show().html('<p>' + escHtml(msg) + '</p><p class="pb-member-hint-comp">' + escHtml(comp) + '</p>');
    }

    function refreshTraitPanelForSelection(model) {
        if (!editor) {
            return;
        }
        if (isAuthModalMode()) {
            if (model && model.getAttributes()['data-component'] === 'auth_modals') {
                showAuthModalTraitsPanel();
            }
            return;
        }
        if (!model || model.is('wrapper')) {
            toggleTraitsEmpty();
            return;
        }
        var comp = model.getAttributes()['data-component'] || '';
        if (!comp) {
            toggleTraitsEmpty();
            return;
        }
        if ((isMemberMode() || isAuthMode()) && isMemberInternalComponent(comp)) {
            showMemberLockedHint();
            return;
        }
        toggleChromeCatsPanel(null);
        toggleChromeLogoPanel(null);
        toggleChromeDecorPanel(null);
        toggleChromeFooterLogoPanel(null);
        if (listUsesSectionHeader(comp)) {
            pullSectionHeaderToList(model);
        }
        var traits = traitsFor(comp, model);
        if ((isMemberMode() || isAuthMode() || isAuthModalMode()) && !traits.length) {
            showMemberNoTraitsHint(comp);
            return;
        }
        model.set('traits', traits);
        $('#pb-traits-empty').hide();
        $('#pb-traits-panel').show();
        if (editor.TraitManager && typeof editor.TraitManager.render === 'function') {
            editor.TraitManager.render();
        }
        bindPagingTraitHandlers(model);
        bindContentFeedTraitHandlers(model);
        bindCoverRatioTraitHandlers(model);
    }

    function selectFirstMemberEditableBlock() {
        if (!editor || (!isMemberMode() && !isAuthMode())) {
            return;
        }
        var region = getRegionModel('content');
        if (!region) {
            return;
        }
        var top = region.components().at(0);
        if (top) {
            editor.select(top);
        }
    }

    function showChromeLockedHint() {
        var msg = traitLabel('pb_chrome_locked_hint', 'Top bar and footer are configured once in Theme Design → Site chrome.');
        var link = traitLabel('pb_edit_site_chrome', 'Edit site chrome');
        var designUrl = PB.urls && PB.urls.design ? PB.urls.design : '';
        if (designUrl.indexOf('step=') < 0) {
            designUrl += (designUrl.indexOf('?') >= 0 ? '&' : '?') + 'step=1';
        }
        $('#pb-traits-panel').hide();
        $('#pb-chrome-cats, #pb-chrome-logo, #pb-chrome-decor, #pb-chrome-footer-logo').prop('hidden', true);
        var $empty = $('#pb-traits-empty');
        $empty.show().html(
            '<p>' + escHtml(msg) + '</p>'
            + (designUrl ? '<a class="td-pb-chrome-link" href="' + escHtml(designUrl) + '" target="_blank">' + escHtml(link) + '</a>' : '')
        );
    }

    function getRegionModel(regionName) {
        if (!editor) {
            return null;
        }
        var found = null;
        editor.getWrapper().components().forEach(function (m) {
            if (m.getAttributes()['data-pb-region'] === regionName) {
                found = m;
            }
        });
        return found;
    }

    function defaultRegionForComponent(comp) {
        if (comp === 'site_nav') {
            return 'site_header';
        }
        if (comp === 'header_decor') {
            return 'hero';
        }
        if (comp === 'channel_strip') {
            return 'channel';
        }
        if (comp === 'site_footer' || comp === 'bottom_nav') {
            return 'footer';
        }
        if (comp === 'vod_player') {
            return 'content';
        }
        if (SIDEBAR_TOP.indexOf(comp) >= 0 && ['rank_list', 'rank_card', 'rank_inline', 'rank_feed'].indexOf(comp) >= 0) {
            return 'sidebar';
        }
        return 'content';
    }

    function regionAllowsDirectChild(regionName, childComp) {
        if (isShellComponent(childComp)) {
            return false;
        }
        if (isRouteLockedMode()) {
            return false;
        }
        if (regionName === 'site_header') {
            return childComp === 'site_nav' || childComp === 'app_top_chrome';
        }
        if (regionName === 'hero') {
            return childComp === 'header_decor'
                || MANGA_HOME_COMPS.indexOf(childComp) >= 0
                || NOVEL_HOME_COMPS.indexOf(childComp) >= 0;
        }
        if (regionName === 'channel') {
            return childComp === 'channel_strip';
        }
        if (regionName === 'footer') {
            return childComp === 'site_footer' || childComp === 'bottom_nav';
        }
        if (regionName === 'content') {
            return TOP_LEVEL.indexOf(childComp) >= 0 || isLayoutContentComp(childComp) || childComp === 'vod_player'
                || MANGA_HOME_COMPS.indexOf(childComp) >= 0
                || NOVEL_HOME_COMPS.indexOf(childComp) >= 0;
        }
        if (regionName === 'sidebar') {
            return SIDEBAR_TOP.indexOf(childComp) >= 0 || isLayoutContentComp(childComp);
        }
        return false;
    }

    function pageToRegions(page) {
        page = page || {};
        var regions = {};
        var regionNames = effectiveRegionNames(page);
        var i;
        if (page.regions && typeof page.regions === 'object') {
            regionNames.forEach(function (name) {
                var list = page.regions[name];
                if (name === 'content' || name === 'sidebar') {
                    regions[name] = stripShellBlocks(list);
                } else {
                    regions[name] = Array.isArray(list) ? list.map(normalizeBlockTree) : [];
                }
            });
            if (homepageAppTopChromeMode(page)) {
                APP_TOP_CHROME_MANAGED.forEach(function (name) {
                    regions[name] = [];
                });
            }
            if (regionNames.indexOf('footer') >= 0) {
                regions.footer = normalizeFooterBlocks(regions.footer);
            }
            return regions;
        }
        var chrome = normalizeChromeBlocks(page.chrome || []);
        var chromeByComp = {};
        chrome.forEach(function (b) {
            if (b && b.component) {
                chromeByComp[b.component] = b;
            }
        });
        regions.site_header = chromeByComp.site_nav ? [chromeByComp.site_nav] : [defaultChromeBlocks()[0]];
        regions.hero = chromeByComp.header_decor ? [chromeByComp.header_decor] : [defaultChromeBlocks()[1]];
        regions.channel = chromeByComp.channel_strip ? [chromeByComp.channel_strip] : [defaultChromeBlocks()[2]];
        regions.content = stripShellBlocks(page.blocks || []);
        regions.sidebar = [];
        regions.footer = normalizeFooterBlocks(page.footer ? [page.footer] : []);
        return regions;
    }

    function isInsideFilmstripLayout(model) {
        if (!model || typeof model.parent !== 'function') {
            return false;
        }
        var p = model.parent();
        while (p && !p.is('wrapper')) {
            var comp = p.getAttributes()['data-component'] || '';
            if (comp === 'filmstrip_layout') {
                return true;
            }
            p = p.parent();
        }
        return false;
    }

    function isFilmstripBannerComp(comp) {
        return comp === 'vod_carousel';
    }

    function isFilmstripFeedComp(comp) {
        return comp === 'vod_list' || comp === 'content_feed';
    }

    function applyFilmstripFeedProps(model) {
        if (!model || !isFilmstripFeedComp(model.getAttributes()['data-component'] || '')) {
            return;
        }
        var comp = model.getAttributes()['data-component'] || '';
        model.set('render', 'filmstrip', { silent: true });
        if (!model.get('card')) {
            model.set('card', 'poster-h', { silent: true });
        }
        if (!model.get('ratio') || model.get('ratio') === 'default') {
            model.set('ratio', '69-40', { silent: true });
        }
        if (!model.get('num')) {
            model.set('num', 12, { silent: true });
        }
        if (!model.get('by')) {
            model.set('by', 'hits_day', { silent: true });
        }
        if (comp === 'content_feed' && !model.get('bind')) {
            model.set('bind', 'vod', { silent: true });
        }
    }

    function listLayoutOpts(model) {
        var opts = LAYOUT_OPTS || [];
        if (isInsideFilmstripLayout(model)) {
            return opts;
        }
        return opts.filter(function (o) {
            return o.value !== 'filmstrip';
        });
    }

    function contentFeedBind(model) {
        return String(model && model.get('bind') || 'vod');
    }

    function listBindUsesCover(bind) {
        return bind !== 'gbook';
    }

    function listBindUsesCategory(bind) {
        return ['vod', 'art', 'manga', 'website', 'actor'].indexOf(bind) >= 0;
    }

    function listBindUsesSort(bind) {
        return ['vod', 'art', 'manga', 'topic', 'website', 'actor', 'role'].indexOf(bind) >= 0;
    }

    function listBindUsesLayout(bind) {
        return ['vod', 'art', 'manga'].indexOf(bind) >= 0;
    }

    function listBindUsesRelation(bind) {
        return bind === 'vod';
    }

    var PAGEURL_OPTS_VOD = [];
    var PAGEURL_OPTS_ART = [];
    var PAGEURL_OPTS_MANGA = [];
    var pagingTraitBound = {};
    var contentFeedTraitBound = {};

    function isPagingEnabled(model) {
        if (!model) {
            return false;
        }
        var v = model.get('paging');
        return v === true || v === 1 || v === '1' || v === 'true';
    }

    function defaultPageurlForList(comp, model) {
        var route = String(PB.route || '');
        var byRoute = {
            'vod/search': 'vod/search',
            'vod/show': 'vod/show',
            'vod/type': 'vod/show',
            'vod/index': 'vod/show',
            'art/search': 'art/search',
            'art/show': 'art/show',
            'art/type': 'art/show',
            'manga/show': 'manga/show',
            'manga/type': 'manga/show',
            'live/show': 'live/show',
            'website/show': 'website/show'
        };
        if (byRoute[route]) {
            return byRoute[route];
        }
        var byComp = {
            art_list: 'art/show',
            manga_list: 'manga/show',
            topic_list: 'topic/index',
            live_list: 'live/show',
            website_list: 'website/show',
            actor_list: 'actor/show',
            role_list: 'role/show',
            gbook_list: 'gbook/index'
        };
        if (byComp[comp]) {
            return byComp[comp];
        }
        if (comp === 'content_feed' && model) {
            var bind = contentFeedBind(model);
            var byBind = {
                art: 'art/show',
                manga: 'manga/show',
                topic: 'topic/index',
                live: 'live/show',
                website: 'website/show',
                actor: 'actor/show',
                role: 'role/show',
                gbook: 'gbook/index'
            };
            if (byBind[bind]) {
                return byBind[bind];
            }
        }
        return 'vod/show';
    }

    function pageurlOptsForComp(comp, model) {
        if (comp === 'art_list') {
            return PAGEURL_OPTS_ART;
        }
        if (comp === 'manga_list') {
            return PAGEURL_OPTS_MANGA;
        }
        if (comp === 'content_feed' && model) {
            var bind = String(model.get('bind') || 'vod');
            if (bind === 'art') {
                return PAGEURL_OPTS_ART;
            }
            if (bind === 'manga') {
                return PAGEURL_OPTS_MANGA;
            }
        }
        return PAGEURL_OPTS_VOD;
    }

    function pushListPagingTraits(t, model, comp, withRelation) {
        t.push({ type: 'checkbox', name: 'paging', label: traitLabel('trait_paging', 'Pagination'), changeProp: 1 });
        if (isPagingEnabled(model)) {
            t.push({
                type: 'select',
                name: 'pageurl',
                label: traitLabel('trait_pageurl', 'Pagination page'),
                changeProp: 1,
                options: pageurlOptsForComp(comp, model)
            });
        }
        if (withRelation) {
            t.push({ type: 'checkbox', name: 'relation', label: traitLabel('trait_relation', 'Related content'), changeProp: 1 });
        }
    }

    function bindPagingTraitHandlers(model) {
        if (!model || model.cid === undefined || pagingTraitBound[model.cid]) {
            return;
        }
        pagingTraitBound[model.cid] = true;
        model.on('change:paging', function () {
            if (suppressSave) {
                return;
            }
            if (isPagingEnabled(model)) {
                var cur = String(model.get('pageurl') || '').trim();
                if (!cur) {
                    model.set('pageurl', defaultPageurlForList(model.getAttributes()['data-component'] || '', model), { silent: true });
                }
            }
            refreshTraitPanelForSelection(model);
        });
    }

    function bindContentFeedTraitHandlers(model) {
        if (!model || model.cid === undefined || contentFeedTraitBound[model.cid]) {
            return;
        }
        var comp = model.getAttributes()['data-component'] || '';
        if (comp !== 'content_feed') {
            return;
        }
        contentFeedTraitBound[model.cid] = true;
        model.on('change:bind', function () {
            if (suppressSave) {
                return;
            }
            refreshTraitPanelForSelection(model);
        });
    }

    function isKnownCoverRatioPreset(ratio) {
        return COVER_RATIO_KNOWN.indexOf(String(ratio || '')) >= 0;
    }

    function normalizeCoverRatioWh(w, h) {
        w = parseInt(w, 10);
        h = parseInt(h, 10);
        if (isNaN(w) || isNaN(h) || w < 1 || w > 999 || h < 1 || h > 999) {
            return null;
        }
        return { w: w, h: h };
    }

    function parseCoverRatioSlug(ratio) {
        var m = String(ratio || '').match(/^(\d{1,3})-(\d{1,3})$/);
        if (!m) {
            return null;
        }
        return normalizeCoverRatioWh(m[1], m[2]);
    }

    function ensureCoverRatioWh(model) {
        if (!model) {
            return;
        }
        var cur = normalizeCoverRatioWh(model.get('ratio_w'), model.get('ratio_h'));
        if (cur) {
            return;
        }
        var ratio = String(model.get('ratio') || 'default');
        if (isKnownCoverRatioPreset(ratio)) {
            return;
        }
        var parsed = parseCoverRatioSlug(ratio);
        if (parsed) {
            model.set({ ratio_w: parsed.w, ratio_h: parsed.h }, { silent: true });
        }
    }

    function bindCoverRatioTraitHandlers(model) {
        if (!model || model.cid === undefined || coverRatioTraitBound[model.cid]) {
            return;
        }
        if (model.get('ratio') === undefined) {
            return;
        }
        coverRatioTraitBound[model.cid] = true;
        ensureCoverRatioWh(model);
        model.on('change:ratio', function () {
            if (suppressSave) {
                return;
            }
            var ratio = String(model.get('ratio') || 'default');
            if (isKnownCoverRatioPreset(ratio)) {
                model.unset('ratio_w', { silent: true });
                model.unset('ratio_h', { silent: true });
            }
        });
        model.on('change:ratio_w change:ratio_h', function () {
            if (suppressSave) {
                return;
            }
            var wh = normalizeCoverRatioWh(model.get('ratio_w'), model.get('ratio_h'));
            if (!wh) {
                return;
            }
            model.set('ratio', wh.w + '-' + wh.h, { silent: true });
        });
    }

    function traitLabel(key, fallback) {
        var L = PB.lang || {};
        return L[key] || fallback;
    }

    function buildTraitOptionSets() {
        SORT_OPTS = [
            { value: 'hits', name: traitLabel('trait_sort_hits', 'Hits') },
            { value: 'hits_month', name: traitLabel('trait_sort_hits_month', 'Hits month') },
            { value: 'hits_week', name: traitLabel('trait_sort_hits_week', 'Hits week') },
            { value: 'hits_day', name: traitLabel('trait_sort_hits_day', 'Hits day') },
            { value: 'time', name: traitLabel('trait_sort_time', 'Time') },
            { value: 'score', name: traitLabel('trait_sort_score', 'Score') }
        ];
        LAYOUT_OPTS = [
            { value: 'slider', name: traitLabel('trait_layout_slider', 'Slider') },
            { value: 'grid', name: traitLabel('trait_layout_grid', 'Grid') },
            { value: 'filmstrip', name: traitLabel('trait_layout_filmstrip', 'Surround cards') }
        ];
        FEED_RENDER_OPTS = LAYOUT_OPTS;
        PERIOD_OPTS = [
            { value: 'day', name: traitLabel('trait_period_day', 'Day') },
            { value: 'week', name: traitLabel('trait_period_week', 'Week') },
            { value: 'month', name: traitLabel('trait_period_month', 'Month') },
            { value: 'all', name: traitLabel('trait_period_all', 'All') }
        ];
        VARIANT_OPTS = [
            { value: 'plain', name: traitLabel('trait_variant_plain', 'Plain') },
            { value: 'card', name: traitLabel('trait_variant_card', 'Card') }
        ];
        ICON_OPTS = [
            { value: '', name: traitLabel('trait_icon_none', 'None') },
            { value: 'fire', name: traitLabel('trait_icon_fire', 'Hot') },
            { value: 'book', name: traitLabel('trait_icon_book', 'Manga') },
            { value: 'collection', name: traitLabel('trait_icon_collection', 'Topic') },
            { value: 'bar-chart', name: traitLabel('trait_icon_bar_chart', 'Ranking') },
            { value: 'newspaper', name: traitLabel('trait_icon_newspaper', 'News') },
            { value: 'broadcast', name: traitLabel('trait_icon_broadcast', 'Live') }
        ];
        NP2_LAYOUT_OPTS = [
            { value: 'grid2', name: traitLabel('np2_layout_grid2', '2-column grid') },
            { value: 'hscroll', name: traitLabel('np2_layout_hscroll', 'Horizontal scroll') },
            { value: 'cols3', name: traitLabel('np2_layout_cols3', '3-column cards') }
        ];
        NP2_ICON_OPTS = [
            { value: '', name: traitLabel('trait_icon_none', 'None') },
            { value: 'auto', name: traitLabel('trait_icon_auto', 'Auto') },
            { value: 'spark', name: traitLabel('trait_icon_spark', 'Spark') },
            { value: 'film', name: traitLabel('trait_icon_film', 'Film') },
            { value: 'heart', name: traitLabel('trait_icon_heart', 'Heart') },
            { value: 'clapper', name: traitLabel('trait_icon_clapper', 'Clapper') },
            { value: 'fire', name: traitLabel('trait_icon_fire', 'Hot') },
            { value: 'tv', name: traitLabel('trait_icon_tv', 'TV') },
            { value: 'star', name: traitLabel('trait_icon_star', 'Star') }
        ];
        MEMBER_VISUAL_VARIANT_OPTS = [
            { value: 'default', name: traitLabel('trait_member_visual_default', 'Default') },
            { value: 'minimal', name: traitLabel('trait_member_visual_minimal', 'Minimal') }
        ];
        BIND_OPTS = [
            { value: 'vod', name: traitLabel('trait_bind_vod', 'Video') },
            { value: 'manga', name: traitLabel('trait_bind_manga', 'Manga') },
            { value: 'art', name: traitLabel('trait_bind_art', 'Article') },
            { value: 'topic', name: traitLabel('trait_bind_topic', 'Topic') },
            { value: 'live', name: traitLabel('trait_bind_live', 'Live') },
            { value: 'website', name: traitLabel('trait_bind_website', 'Website') },
            { value: 'gbook', name: traitLabel('trait_bind_gbook', 'Guestbook') },
            { value: 'actor', name: traitLabel('trait_bind_actor', 'Actor') },
            { value: 'role', name: traitLabel('trait_bind_role', 'Role') }
        ];
        CAROUSEL_BIND_OPTS = [
            { value: 'vod', name: traitLabel('trait_bind_vod', 'Video') },
            { value: 'manga', name: traitLabel('trait_bind_manga', 'Manga') },
            { value: 'art', name: traitLabel('trait_bind_art', 'Novel') }
        ];
        CARD_OPTS = [
            { value: 'poster-v', name: traitLabel('trait_card_poster_v', 'Vertical cover') },
            { value: 'poster-h', name: traitLabel('trait_card_poster_h', 'Horizontal cover') }
        ];
        RATIO_OPTS = [
            { value: 'default', name: traitLabel('trait_ratio_default', 'Follow orientation default') },
            { value: '16-9', name: traitLabel('trait_ratio_16_9', '16:9') },
            { value: '4-3', name: traitLabel('trait_ratio_4_3', '4:3') },
            { value: '3-4', name: traitLabel('trait_ratio_3_4', '3:4') },
            { value: '2-3', name: traitLabel('trait_ratio_2_3', '2:3') },
            { value: '69-40', name: traitLabel('trait_ratio_69_40', '69:40') },
            { value: '20-29', name: traitLabel('trait_ratio_20_29', '20:29') },
            { value: '1-1', name: traitLabel('trait_ratio_1_1', '1:1') }
        ];
        RANK_STYLE_OPTS = [
            { value: 'panel', name: traitLabel('trait_rank_style_panel', 'Panel') },
            { value: 'inline', name: traitLabel('trait_rank_style_inline', 'Inline list') }
        ];
        FOOTER_VARIANT_OPTS = [
            { value: 'default', name: traitLabel('trait_footer_variant_default', 'Default') },
            { value: 'compact', name: traitLabel('trait_footer_variant_compact', 'Compact') }
        ];
        PAGEURL_OPTS_VOD = [
            { value: 'vod/show', name: traitLabel('trait_pageurl_vod_show', 'Video filter page') },
            { value: 'vod/search', name: traitLabel('trait_pageurl_vod_search', 'Video search page') }
        ];
        PAGEURL_OPTS_ART = [
            { value: 'art/show', name: traitLabel('trait_pageurl_art_show', 'Novel filter page') },
            { value: 'art/search', name: traitLabel('trait_pageurl_art_search', 'Novel search page') }
        ];
        PAGEURL_OPTS_MANGA = [
            { value: 'manga/show', name: traitLabel('trait_pageurl_manga_show', 'Manga filter page') }
        ];
    }

    function bindMid(bind) {
        if (bind === 'art') {
            return 2;
        }
        if (bind === 'manga') {
            return 12;
        }
        if (bind === 'website') {
            return 1;
        }
        if (bind === 'actor') {
            return 8;
        }
        return 1;
    }

    function bindLabel(bind) {
        var hit = null;
        (BIND_OPTS || []).forEach(function (o) {
            if (o.value === bind) {
                hit = o.name;
            }
        });
        return hit || bind || 'content';
    }

    var LABELS = {
        vod_carousel: traitLabel('pb_block_vod_carousel', 'Carousel · standard'),
        filmstrip_layout: 'Surround layout',
        carousel_filmstrip: 'Carousel filmstrip',
        carousel_fullbleed: 'Carousel fullbleed',
        carousel_compact: 'Carousel compact',
        'carousel-filmstrip': 'Carousel filmstrip',
        'carousel-fullbleed': traitLabel('pb_comp_carousel_fullbleed', 'Carousel · full bleed'),
        'carousel-compact': traitLabel('pb_comp_carousel_compact', 'Carousel · compact strip'),
        section: 'Section',
        columns: 'Columns',
        grid: 'Grid',
        spacer: 'Spacer',
        content_feed: traitLabel('pb_block_content_feed', 'List · content feed'),
        rank_feed: 'Rank feed',
        vod_list: 'VOD list',
        vod_list_np2: traitLabel('pb_block_vod_list_np2', 'NP2 video list'),
        art_list: 'Article list',
        manga_list: 'Manga list',
        topic_list: 'Topic list',
        live_list: 'Live list',
        website_list: 'Website list',
        gbook_form: traitLabel('pb_block_gbook_form', 'Guestbook form'),
        gbook_list: 'Guestbook',
        rank_list: 'Rank list',
        rank_inline: 'Rank inline',
        rank_card: 'Rank card',
        site_nav: traitLabel('pb_block_site_nav', 'Site nav'),
        channel_strip: traitLabel('pb_block_channel_strip', 'Channel strip'),
        header_decor: traitLabel('pb_block_header_decor', 'Header decor'),
        site_footer: traitLabel('pb_block_site_footer', 'Site footer'),
        bottom_nav: traitLabel('pb_block_bottom_nav', 'Bottom navigation'),
        ads_grid: traitLabel('pb_block_ads_grid', 'Ads grid'),
        vod_player: traitLabel('pb_block_vod_player', 'VOD player'),
        sec_heading: traitLabel('pb_block_sec_heading', 'Section heading'),
        breadcrumb: traitLabel('pb_block_breadcrumb', 'Breadcrumb'),
        filter_chips: traitLabel('pb_block_filter_chips', 'Filter chips'),
        filter_panel: traitLabel('pb_block_filter_panel', 'Filter panel'),
        paging: traitLabel('pb_block_paging', 'Paging'),
        empty_state: traitLabel('pb_block_empty_state', 'Empty state'),
        vod_detail: traitLabel('pb_block_vod_detail', 'VOD detail'),
        art_detail: traitLabel('pb_block_art_detail', 'Article detail'),
        manga_detail: traitLabel('pb_block_manga_detail', 'Manga detail'),
        topic_detail: traitLabel('pb_block_topic_detail', 'Topic detail'),
        actor_detail: traitLabel('pb_block_actor_detail', 'Actor detail'),
        website_detail: traitLabel('pb_block_website_detail', 'Website detail'),
        actor_list: traitLabel('pb_block_actor_list', 'Actor list'),
        user_member_hub: traitLabel('pb_block_user_member_hub', 'Member hub'),
        user_notify_panel: traitLabel('pb_block_user_notify_panel', 'Notify panel'),
        manga_mosaic_hero: traitLabel('pb_block_manga_mosaic_hero', 'Carousel · mosaic'),
        manga_genre_nav: traitLabel('pb_block_manga_genre_nav', 'Manga genres'),
        manga_home_spotlight: traitLabel('pb_block_manga_home_spotlight', 'Carousel · spotlight'),
        manga_home_row: traitLabel('pb_block_manga_home_row', 'Manga scroll row'),
        manga_home_rank: traitLabel('pb_block_manga_home_rank', 'Manga rank board'),
        novel_home_banner: traitLabel('pb_block_novel_home_banner', 'Carousel · cover focus'),
        novel_genre_nav: traitLabel('pb_block_novel_genre_nav', 'Novel genres'),
        novel_home_dock: traitLabel('pb_block_novel_home_dock', 'Novel quick dock'),
        novel_home_rank: traitLabel('pb_block_novel_home_rank', 'Novel rank board'),
        novel_home_grid: traitLabel('pb_block_novel_home_grid', 'Novel grid'),
        novel_home_row: traitLabel('pb_block_novel_home_row', 'Novel scroll row'),
        novel_home_zone: traitLabel('pb_block_novel_home_zone', 'Novel type zones'),
        novel_player: traitLabel('pb_block_novel_player', 'Novel reader'),
        novel_chapter_catalog: traitLabel('pb_block_novel_chapter_catalog', 'Chapter catalog'),
        manga_player: traitLabel('pb_block_manga_player', 'Manga reader'),
        media_intro_panel: traitLabel('pb_block_media_intro_panel', 'Synopsis'),
        media_related_panel: traitLabel('pb_block_media_related_panel', 'Related')
    };

    var TOP_LEVEL = ['filmstrip_layout', 'carousel-fullbleed', 'carousel-compact', 'vod_carousel', 'manga_mosaic_hero', 'manga_home_spotlight', 'novel_home_banner', 'columns', 'grid', 'spacer'];
    var MANGA_HOME_COMPS = ['manga_mosaic_hero', 'manga_genre_nav', 'manga_home_spotlight', 'manga_home_row', 'manga_home_rank'];
    var NOVEL_HOME_COMPS = ['novel_home_banner', 'novel_genre_nav', 'novel_home_dock', 'novel_home_rank', 'novel_home_grid', 'novel_home_row', 'novel_home_zone'];
    var LIST_COMPS = ['content_feed', 'rank_feed', 'vod_list', 'vod_list_np2', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'gbook_list', 'rank_list', 'rank_inline'];
    var LIST_WITH_SECTION_HEADER = ['content_feed', 'vod_list', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'rank_inline', 'rank_list', 'gbook_list'];
    var SECTION_HEADER_KEYS = ['title', 'icon', 'variant'];
    var sectionHeaderSync = false;
    var paletteMap = {};
    var skipBlockClickOnce = false;
    var dragState = null;
    var paletteDragMoveHandler = null;
    var paletteDragUpHandler = null;
    var paletteIframeMoveHandler = null;
    var paletteScrollHandler = null;
    var lastDropPointer = { x: 0, y: 0 };
    var dropInsertedOnRelease = false;
    var pendingInsertMeta = null;

    function listUsesSectionHeader(comp) {
        return LIST_WITH_SECTION_HEADER.indexOf(comp) >= 0;
    }

    function getParentSection(model) {
        var p = model && model.parent();
        if (p && p.getAttributes()['data-component'] === 'section') {
            return p;
        }
        return null;
    }

    function ensureLayerRoot() {
        if (!editor) {
            return false;
        }
        var wrapper = editor.getWrapper();
        if (!wrapper) {
            return false;
        }
        var lm = editor.LayerManager || editor.Layers;
        if (!lm || typeof lm.setRoot !== 'function') {
            return false;
        }
        var root = typeof lm.getRoot === 'function' ? lm.getRoot() : null;
        if (!root || root !== wrapper) {
            lm.setRoot(wrapper);
        }
        return true;
    }

    function applySectionShellFlags(model) {
        if (!model || model.getAttributes()['data-component'] !== 'section') {
            return;
        }
        // 不可设 layerable:false — GrapesJS 会连子组件一并从结构树移除并触发 ItemView 报错（#4812）
        model.set('layerable', true);
        model.set('selectable', true);
        model.set('hoverable', true);
        model.set('highlightable', true);
        model.set('draggable', false);
        model.set('removable', false);
        model.set('copyable', false);
        model.set('badgable', false);
        // 结构树行用 CSS 隐藏；名称留空避免闪「Section」
        model.set('name', '\u200b');
    }

    function decorateSectionLayerEl(component, layerEl) {
        if (!layerEl || !component) {
            return;
        }
        var comp = component.getAttributes()['data-component'] || '';
        if (comp === 'section') {
            layerEl.classList.add('pb-layer-shell-section');
            layerEl.setAttribute('data-pb-shell-section', '1');
        } else {
            layerEl.classList.remove('pb-layer-shell-section');
            layerEl.removeAttribute('data-pb-shell-section');
        }
    }

    function decorateSectionLayersInPanel() {
        if (!editor) {
            return;
        }
        var panel = document.getElementById('pb-layers-panel');
        if (!panel) {
            return;
        }
        panel.querySelectorAll('.gjs-layer').forEach(function (layerEl) {
            var model = findModelByLayerEl(layerEl);
            decorateSectionLayerEl(model, layerEl);
        });
    }

    function walkApplySectionShellFlags(model) {
        if (!model) {
            return;
        }
        applySectionShellFlags(model);
        model.components().forEach(walkApplySectionShellFlags);
    }

    function firstListHostInSectionEl(sectionHost) {
        if (!sectionHost) {
            return null;
        }
        var hosts = sectionHost.querySelectorAll('.pb-block-host[data-pb-component]');
        var i;
        for (i = 0; i < hosts.length; i++) {
            var comp = hosts[i].getAttribute('data-pb-component') || '';
            if (comp && comp !== 'section') {
                return hosts[i];
            }
        }
        return null;
    }

    function resolveListModelInSection(sectionModel) {
        if (!sectionModel) {
            return null;
        }
        var found = null;
        sectionModel.components().forEach(function (ch) {
            if (!found && ch.getAttributes()['data-component']) {
                found = ch;
            }
        });
        return found;
    }

    function pullSectionHeaderToList(listModel) {
        var sec = getParentSection(listModel);
        var comp = listModel ? listModel.getAttributes()['data-component'] : '';
        if (!sec || !listUsesSectionHeader(comp)) {
            return;
        }
        sectionHeaderSync = true;
        SECTION_HEADER_KEYS.forEach(function (k) {
            var v = sec.get(k);
            listModel.set(k, v !== undefined && v !== null ? v : '');
        });
        listModel.set('_secHeaderSynced', true);
        sectionHeaderSync = false;
    }

    function pushSectionHeaderFromList(listModel) {
        if (sectionHeaderSync || !listModel) {
            return;
        }
        if (!listModel.get('_secHeaderSynced')) {
            return;
        }
        var sec = getParentSection(listModel);
        var comp = listModel.getAttributes()['data-component'] || '';
        if (!sec || !listUsesSectionHeader(comp)) {
            return;
        }
        sectionHeaderSync = true;
        var secProps = {};
        SECTION_HEADER_KEYS.forEach(function (k) {
            var v = listModel.get(k);
            if (v === undefined || v === null) {
                v = '';
            }
            sec.set(k, v);
            secProps[k] = v;
        });
        updateLayerName(sec, 'section', secProps);
        var listProps = {};
        modelPropNames(comp).forEach(function (n) {
            listProps[n] = listModel.get(n);
        });
        updateLayerName(listModel, comp, listProps);
        sectionHeaderSync = false;
    }

    function isLayoutContentComp(comp) {
        if (!comp) {
            return false;
        }
        if (LIST_COMPS.indexOf(comp) >= 0 || comp === 'rank_card' || comp === 'rank_board') {
            return true;
        }
        // 列内也可放筛选条、面包屑等轻量块（轮播/漫画·小说首页块不套分区「更多」）
        if (comp === 'breadcrumb' || comp === 'filter_chips' || comp === 'comment_block') {
            return true;
        }
        return false;
    }

    /** 列表拖入 content/sidebar 时，外层 section 的默认标题/图标（允许之后清空） */
    function defaultSectionPropsForList(listComp) {
        var map = {
            vod_list: { title: '热门推荐', icon: 'fire' },
            art_list: { title: '最新资讯', icon: 'newspaper' },
            manga_list: { title: '热门漫画', icon: 'book' },
            topic_list: { title: '专题推荐', icon: 'collection' },
            live_list: { title: '直播频道', icon: 'broadcast' },
            website_list: { title: '网址列表', icon: 'collection' },
            actor_list: { title: '演员列表', icon: 'collection' },
            gbook_list: { title: '留言列表', icon: 'newspaper' },
            content_feed: { title: '内容列表', icon: 'fire' },
            rank_feed: { title: '排行榜', icon: 'bar-chart' },
            rank_list: { title: '排行榜', icon: 'bar-chart' },
            rank_inline: { title: '排行榜', icon: 'bar-chart' }
        };
        var base = { variant: 'plain', title: '', icon: '' };
        var extra = map[listComp] || {};
        return {
            variant: base.variant,
            title: extra.title != null ? extra.title : base.title,
            icon: extra.icon != null ? extra.icon : base.icon
        };
    }

    function defaultSectionBlock(listComp) {
        return {
            component: 'section',
            props: defaultSectionPropsForList(listComp || ''),
            slots: {}
        };
    }

    function wrapBlockInSection(innerBlock) {
        var listComp = innerBlock && innerBlock.component ? innerBlock.component : '';
        var section = defaultSectionBlock(listComp);
        section.slots = { body: [JSON.parse(JSON.stringify(innerBlock))] };
        return section;
    }

    function maybeWrapBlockForRegion(block, regionName) {
        if (!block || !block.component) {
            return block;
        }
        // 只有内容列表才自动套分区标题（含「更多」）；轮播/漫画首页块自带版式，不套
        if ((regionName === 'content' || regionName === 'sidebar') && listUsesSectionHeader(block.component)) {
            return wrapBlockInSection(block);
        }
        return block;
    }

    function wrapListModelInSection(listModel, regionParent) {
        if (!listModel || !regionParent) {
            return;
        }
        var idx = regionParent.components().indexOf(listModel);
        if (idx < 0) {
            return;
        }
        var listBlock = componentToBlock(listModel);
        var sectionBlock = wrapBlockInSection(listBlock);
        suppressSave = true;
        regionParent.components().remove(listModel);
        var added = regionParent.components().add(blockToContent(sectionBlock), { at: idx });
        suppressSave = false;
        finalizeInsert(added, sectionBlock);
    }

    function ensureColumnsModelSections(columnsModel) {
        // 兼容旧草稿：去掉列里「空分区」壳，让组件直接落在列槽
        if (!columnsModel || columnsModel.getAttributes()['data-component'] !== 'columns') {
            return;
        }
        var doomed = [];
        columnsModel.components().forEach(function (ch) {
            if (ch.getAttributes()['data-component'] !== 'section') {
                return;
            }
            var kids = ch.components();
            if (!kids || !kids.length) {
                doomed.push(ch);
            }
        });
        if (!doomed.length) {
            return;
        }
        suppressSave = true;
        doomed.forEach(function (ch) {
            try { columnsModel.components().remove(ch); } catch (e) {}
        });
        suppressSave = false;
    }

    function findSectionPathInColumnsSlot(columnsPath, columnSlot) {
        var columnsModel = getModelByPath(columnsPath);
        if (!columnsModel) {
            return null;
        }
        ensureColumnsModelSections(columnsModel);
        var slot = String(columnSlot == null ? '0' : columnSlot);
        var path = null;
        columnsModel.components().forEach(function (ch) {
            if (ch.getAttributes()['data-slot'] === slot && ch.getAttributes()['data-component'] === 'section') {
                path = ch.getAttributes()['data-pb-path'] || null;
            }
        });
        return path;
    }

    function resolveColumnsSlotFromPoint(columnsPath, clientX, clientY) {
        var doc = getPreviewDoc();
        if (!doc || !columnsPath) {
            return '0';
        }
        var host = doc.querySelector('.pb-block-host[data-pb-path="' + columnsPath + '"]');
        if (!host) {
            return '0';
        }
        var cols = host.querySelectorAll('.bili-columns__col');
        var c;
        for (c = 0; c < cols.length; c++) {
            var r = iframeElParentRect(cols[c]);
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                return String(c);
            }
        }
        var hr = iframeElParentRect(host);
        return clientX < hr.left + hr.width / 2 ? '0' : '1';
    }

    function getFilmstripSlotEl(filmstripPath, slot) {
        var doc = getPreviewDoc();
        if (!doc || !filmstripPath || !slot) {
            return null;
        }
        var host = doc.querySelector('.pb-block-host[data-pb-path="' + filmstripPath + '"]');
        if (!host) {
            return null;
        }
        var sel = slot === 'banner' ? '.pb-surround-slot--banner' : '.pb-surround-slot--feed';
        return host.querySelector(sel);
    }

    function getFilmstripSlotBounds(filmstripPath, slot) {
        var colEl = getFilmstripSlotEl(filmstripPath, slot);
        if (colEl) {
            var childHost = colEl.querySelector(':scope > .pb-block-host');
            if (childHost) {
                var cr = iframeElParentRect(childHost);
                if (cr.width > 4 && cr.height > 4) {
                    return cr;
                }
            }
            var sr = iframeElParentRect(colEl);
            if (sr.width > 4 && sr.height > 4) {
                return sr;
            }
        }
        var doc = getPreviewDoc();
        if (!doc || !filmstripPath) {
            return null;
        }
        var host = doc.querySelector('.pb-block-host[data-pb-path="' + filmstripPath + '"]');
        if (!host) {
            return null;
        }
        var layout = host.querySelector('[data-pb-surround-layout]') || host.querySelector('.bili-feed.pb-cf');
        var hr = iframeElParentRect(layout || host);
        if (!hr.width || !hr.height) {
            return null;
        }
        var split = hr.left + hr.width * 0.42;
        if (slot === 'banner') {
            return {
                left: hr.left,
                top: hr.top,
                right: split,
                bottom: hr.bottom,
                width: split - hr.left,
                height: hr.height
            };
        }
        return {
            left: split,
            top: hr.top,
            right: hr.right,
            bottom: hr.bottom,
            width: hr.right - split,
            height: hr.height
        };
    }

    function resolveFilmstripSlotFromPoint(filmstripPath, clientX, clientY) {
        var doc = getPreviewDoc();
        if (!doc || !filmstripPath) {
            return 'feed';
        }
        var host = doc.querySelector('.pb-block-host[data-pb-path="' + filmstripPath + '"]');
        if (!host) {
            return 'feed';
        }
        var bannerSlot = host.querySelector('.pb-surround-slot--banner');
        var feedSlot = host.querySelector('.pb-surround-slot--feed');
        if (bannerSlot) {
            var br = getFilmstripSlotBounds(filmstripPath, 'banner');
            if (br && clientX >= br.left && clientX <= br.right && clientY >= br.top && clientY <= br.bottom) {
                return 'banner';
            }
        }
        if (feedSlot) {
            var fr = getFilmstripSlotBounds(filmstripPath, 'feed');
            if (fr && clientX >= fr.left && clientX <= fr.right && clientY >= fr.top && clientY <= fr.bottom) {
                return 'feed';
            }
        }
        var hr = iframeElParentRect(host);
        return clientX < hr.left + hr.width * 0.42 ? 'banner' : 'feed';
    }

    function computeFilmstripSlotInsertIndex(parentModel, slot) {
        if (!parentModel) {
            return 0;
        }
        var bannerIdx = -1;
        var feedIdx = -1;
        var i = 0;
        parentModel.components().forEach(function (ch) {
            var sk = ch.getAttributes()['data-slot'];
            var cc = ch.getAttributes()['data-component'];
            if (sk === 'banner' || isFilmstripBannerComp(cc)) {
                bannerIdx = i;
            }
            if (sk === 'feed' || isFilmstripFeedComp(cc)) {
                feedIdx = i;
            }
            i++;
        });
        if (slot === 'banner') {
            return bannerIdx >= 0 ? bannerIdx : (feedIdx >= 0 ? feedIdx : 0);
        }
        return feedIdx >= 0 ? feedIdx : parentModel.components().length;
    }

    function clearFilmstripSlot(parentModel, slot) {
        if (!parentModel || !slot) {
            return;
        }
        var doomed = [];
        parentModel.components().forEach(function (ch) {
            var sk = ch.getAttributes()['data-slot'];
            var cc = ch.getAttributes()['data-component'];
            if (sk === slot
                || (slot === 'banner' && isFilmstripBannerComp(cc))
                || (slot === 'feed' && isFilmstripFeedComp(cc))) {
                doomed.push(ch);
            }
        });
        doomed.forEach(function (ch) {
            try {
                parentModel.components().remove(ch);
            } catch (e) {}
        });
    }

    function enrichDropTarget(target, clientX, clientY, childComp) {
        if (!target || target.mode !== 'child' || !target.parentPath) {
            return target;
        }
        var parentModel = getModelByPath(target.parentPath);
        if (!parentModel) {
            return target;
        }
        var parentComp = parentModel.getAttributes()['data-component'] || '';
        if (parentComp === 'columns') {
            // 以列几何为准；不要只因纵向叠在左列高列表上就把右列吸走
            var slotFromPoint = resolveColumnsSlotFromPoint(target.parentPath, clientX, clientY);
            target.columnSlot = slotFromPoint;
            if (parentModel) {
                var siblings = parentModel.components();
                var idx = target.at != null && target.at >= 0 ? target.at : siblings.length;
                var refIdx = Math.min(Math.max(idx, 1) - 1, Math.max(siblings.length - 1, 0));
                if (siblings.length && refIdx >= 0) {
                    var ref = siblings.at ? siblings.at(refIdx) : siblings[refIdx];
                    if (ref) {
                        var refSlot = ref.getAttributes()['data-slot'];
                        var refPath = ref.getAttributes()['data-pb-path'] || '';
                        var refHost = refPath ? (getPreviewDoc() && getPreviewDoc().querySelector('.pb-block-host[data-pb-path="' + refPath + '"]')) : null;
                        if (refHost && refSlot !== undefined && refSlot !== null && refSlot !== '') {
                            var rr = iframeElParentRect(refHost);
                            // 必须水平也落在该子块列内，才继承其 slot
                            if (clientX >= rr.left - 4 && clientX <= rr.right + 4
                                && clientY >= rr.top - 8 && clientY <= rr.bottom + 8) {
                                target.columnSlot = String(refSlot);
                            }
                        }
                    }
                }
            }
            target.at = computeChildInsertIndex(target.parentPath, clientY, target.columnSlot);
        }
        if (parentComp === 'filmstrip_layout') {
            var filmstripSlot = resolveFilmstripSlotFromPoint(target.parentPath, clientX, clientY);
            if (isFilmstripBannerComp(childComp)) {
                filmstripSlot = 'banner';
            } else if (isFilmstripFeedComp(childComp)) {
                filmstripSlot = 'feed';
            }
            target.filmstripSlot = filmstripSlot;
            target.at = computeFilmstripSlotInsertIndex(parentModel, filmstripSlot);
        }
        return target;
    }

    function buildPaletteMap() {
        paletteMap = {};
        (PB.palette || []).forEach(function (cat) {
            (cat.blocks || []).forEach(function (b) {
                if (b.id) {
                    paletteMap[b.id] = b;
                }
            });
        });
    }

    function canDropChildInParent(childComp, parentComp, parentIsWrapper, parentModel) {
        if (!childComp) {
            return false;
        }
        if (parentIsWrapper) {
            return false;
        }
        if (parentModel && parentModel.getAttributes()['data-pb-region']) {
            if (parentComp) {
                return canDropChildInParent(childComp, parentComp, false, null);
            }
            return regionAllowsDirectChild(parentModel.getAttributes()['data-pb-region'], childComp);
        }
        if (!parentComp) {
            return false;
        }
        if (parentComp === 'section') {
            return isLayoutContentComp(childComp);
        }
        if (parentComp === 'columns') {
            return isLayoutContentComp(childComp);
        }
        if (parentComp === 'filmstrip_layout') {
            return isFilmstripBannerComp(childComp) || isFilmstripFeedComp(childComp);
        }
        if (parentComp === 'grid') {
            return childComp === 'rank_card' || LIST_COMPS.indexOf(childComp) >= 0;
        }
        return false;
    }

    function assignSlotOnInsert(model, parent, meta) {
        if (!model || !parent) {
            return;
        }
        meta = meta || pendingInsertMeta || {};
        var parentComp = parent.is('wrapper') ? '' : (parent.getAttributes()['data-component'] || '');
        if (parentComp === 'columns' && meta.columnSlot !== undefined && meta.columnSlot !== null && meta.columnSlot !== '') {
            model.addAttributes({ 'data-slot': String(meta.columnSlot) });
            return;
        }
        if (parentComp === 'filmstrip_layout' && meta.filmstripSlot) {
            model.addAttributes({ 'data-slot': String(meta.filmstripSlot) });
            return;
        }
        if (model.getAttributes()['data-slot']) {
            return;
        }
        if (parentComp === 'section') {
            model.addAttributes({ 'data-slot': 'body' });
        } else if (parentComp === 'columns') {
            // 无明确列提示时：跟插入位置相邻子块同列（允许同列叠加）
            var kids = parent.components();
            var slot = '0';
            var at = meta.at;
            if (at === undefined || at === null || at < 0) {
                at = kids.indexOf(model);
            }
            if (at < 0) {
                at = kids.length;
            }
            var ref = null;
            if (at > 0) {
                ref = kids.at ? kids.at(at - 1) : kids[at - 1];
            } else if (kids.length && at < kids.length) {
                ref = kids.at ? kids.at(at) : kids[at];
            } else if (kids.length) {
                ref = kids.at ? kids.at(kids.length - 1) : kids[kids.length - 1];
            }
            if (ref) {
                var refSlot = ref.getAttributes()['data-slot'];
                if (refSlot !== undefined && refSlot !== null && refSlot !== '') {
                    slot = String(refSlot);
                }
            }
            model.addAttributes({ 'data-slot': slot });
        } else if (parentComp === 'filmstrip_layout') {
            var usedBanner = false;
            var usedFeed = false;
            parent.components().forEach(function (ch) {
                var sk = ch.getAttributes()['data-slot'];
                var cc = ch.getAttributes()['data-component'];
                if (sk === 'banner' || isFilmstripBannerComp(cc)) {
                    usedBanner = true;
                }
                if (sk === 'feed' || isFilmstripFeedComp(cc)) {
                    usedFeed = true;
                }
            });
            var childComp = model.getAttributes()['data-component'];
            if (isFilmstripBannerComp(childComp) && !usedBanner) {
                model.addAttributes({ 'data-slot': 'banner' });
            } else if (isFilmstripFeedComp(childComp) && !usedFeed) {
                model.addAttributes({ 'data-slot': 'feed' });
            }
        } else if (parentComp === 'grid') {
            model.addAttributes({ 'data-slot': 'children' });
        }
    }

    function normalizeAdded(added) {
        if (!added) {
            return [];
        }
        if (Array.isArray(added)) {
            return added;
        }
        return [added];
    }

    function finalizeInsert(added, block) {
        var models = normalizeAdded(added);
        if (!models.length) {
            return null;
        }
        suppressSave = true;
        models.forEach(function (m, idx) {
            if (idx === 0) {
                hydrateModel(m, block, '');
            }
            if (m.parent()) {
                assignSlotOnInsert(m, m.parent(), pendingInsertMeta || {});
                if (m.parent().getAttributes()['data-component'] === 'filmstrip_layout') {
                    applyFilmstripFeedProps(m);
                }
            }
        });
        if (block && block.component === 'columns' && models[0]) {
            ensureColumnsModelSections(models[0]);
        }
        editor.select(models[models.length - 1]);
        suppressSave = false;
        reindexPaths();
        window.setTimeout(function () {
            refreshAllLayerMoveHandles();
            var doc = getPreviewDoc();
            if (doc) {
                markEmptyLayoutSlots(doc);
            }
        }, 0);
        scheduleAutoSave(true);
        updateUndoRedoButtons();
        return models[models.length - 1];
    }

    function getChromeModelByComponent(comp) {
        if (!editor || !comp) {
            return null;
        }
        var found = null;
        editor.getWrapper().components().forEach(function (region) {
            if (found) {
                return;
            }
            region.components().forEach(function (m) {
                if (m.getAttributes()['data-component'] === comp) {
                    found = m;
                }
            });
        });
        return found;
    }

    function selectChromeComponent(comp) {
        var model = getChromeModelByComponent(comp);
        if (!model) {
            return false;
        }
        var path = model.getAttributes()['data-pb-path'] || '';
        selectByPath(path, false);
        switchToLayersTab();
        return true;
    }

    function insertBlockAtTarget(blockId, target) {
        if (!editor || !paletteMap[blockId] || !target) {
            insertPaletteBlock(blockId);
            return;
        }
        var entry = paletteMap[blockId];
        if (entry.select_only) {
            selectChromeComponent(entry.block.component);
            return;
        }
        var block = entry.block;
        var childComp = block.component;
        var added;
        var undoRowsBefore = (function () {
            var n = 0;
            (function w(m) { n++; m.components().each(w); })(editor.getWrapper());
            return n;
        })();

        if (target.mode === 'child') {
            var parentModel = getModelByPath(target.parentPath);
            if (!parentModel) {
                insertPaletteBlock(blockId);
                return;
            }
            pendingInsertMeta = {
                columnSlot: target.columnSlot || null,
                filmstripSlot: target.filmstripSlot || null
            };
            if (target.filmstripSlot && parentModel.getAttributes()['data-component'] === 'filmstrip_layout') {
                clearFilmstripSlot(parentModel, target.filmstripSlot);
            }
            var at = target.at != null && target.at >= 0 ? target.at : parentModel.components().length;
            added = parentModel.components().add(blockToContent(block), { at: at });
        } else if (target.mode === 'region') {
            var regionName = target.regionName || defaultRegionForComponent(childComp);
            block = maybeWrapBlockForRegion(block, regionName);
            var regionModel = getRegionModel(regionName);
            if (!regionModel) {
                insertPaletteBlock(blockId);
                return;
            }
            var regionAt = target.at != null && target.at >= 0 ? target.at : regionModel.components().length;
            added = regionModel.components().add(blockToContent(block), { at: regionAt });
        } else if (target.mode === 'root') {
            var regionName = defaultRegionForComponent(childComp);
            var regionModelRoot = getRegionModel(regionName);
            if (!regionModelRoot) {
                insertPaletteBlock(blockId);
                return;
            }
            var rootAt = target.at != null && target.at >= 0 ? target.at : regionModelRoot.components().length;
            added = regionModelRoot.components().add(blockToContent(block), { at: rootAt });
        } else {
            insertPaletteBlock(blockId);
            return;
        }
        finalizeInsert(added, block);
        pendingInsertMeta = null;
        // 成功插入：记录撤销批处理锚点（插入前的树行数）
        if (added) {
            undoRowsAnchor = undoRowsBefore;
            undoRedoRowsAnchor = null;
        }
    }

    function insertPaletteBlock(blockId) {
        if (!editor || !paletteMap[blockId]) {
            return;
        }
        var undoRowsBefore = (function () {
            var n = 0;
            (function w(m) { n++; m.components().each(w); })(editor.getWrapper());
            return n;
        })();
        var entry = paletteMap[blockId];
        var block = entry.block;
        var childComp = block.component;
        if (entry.select_only) {
            selectChromeComponent(childComp);
            return;
        }
        if (!childComp) {
            return;
        }

        var wrapper = editor.getWrapper();
        var sel = editor.getSelected();
        var parent = null;
        var added;

        if (sel && !sel.is('wrapper')) {
            var selComp = sel.getAttributes()['data-component'];
            var selRegion = sel.getAttributes()['data-pb-region'];
            if (selRegion && !selComp) {
                parent = sel;
            } else if (canDropChildInParent(childComp, selComp, false, sel.parent())) {
                parent = sel;
            } else if (canDropChildInParent(childComp, selComp, false, sel)) {
                parent = sel;
            } else {
                var top = sel;
                while (top.parent() && !top.parent().is('wrapper') && !top.parent().getAttributes()['data-pb-region']) {
                    top = top.parent();
                }
                if (top.parent() && top.parent().getAttributes()['data-pb-region']) {
                    var regionParent = top.parent();
                    var regionNameInline = regionParent.getAttributes()['data-pb-region'] || 'content';
                    block = maybeWrapBlockForRegion(block, regionNameInline);
                    var at = regionParent.components().indexOf(top) + 1;
                    added = regionParent.components().add(blockToContent(block), { at: at });
                } else {
                    var regionNameDefault = defaultRegionForComponent(childComp);
                    block = maybeWrapBlockForRegion(block, regionNameDefault);
                    var regionModel = getRegionModel(regionNameDefault);
                    added = regionModel ? regionModel.components().add(blockToContent(block)) : null;
                }
            }
        }
        if (!added) {
            if (!parent) {
                parent = getRegionModel(defaultRegionForComponent(childComp));
            }
            if (parent) {
                var regionNameSel = parent.getAttributes()['data-pb-region'] || defaultRegionForComponent(childComp);
                block = maybeWrapBlockForRegion(block, regionNameSel);
                added = parent.components().add(blockToContent(block));
            }
        }

        finalizeInsert(added, block);
        pendingInsertMeta = null;
        // 成功插入：记录撤销批处理锚点（插入前的模型组件总数）
        if (added) {
            undoRowsAnchor = undoRowsBefore;
            undoRedoRowsAnchor = null;
        }
    }

    function findPreviewHostAtPoint(clientX, clientY) {
        var doc = getPreviewDoc();
        if (!doc) {
            return null;
        }
        var hits = [];
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            var r = iframeElParentRect(el);
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                hits.push({ el: el, area: r.width * r.height });
            }
        });
        if (hits.length) {
            hits.sort(function (a, b) {
                return a.area - b.area;
            });
            return hits[0].el;
        }
        var local = parentClientToIframeCss(clientX, clientY);
        var stacked = null;
        try {
            stacked = doc.elementFromPoint(local.x, local.y);
        } catch (err) {
            stacked = null;
        }
        if (stacked && stacked.nodeType !== 1) {
            stacked = stacked.parentElement;
        }
        if (stacked && stacked.closest) {
            return stacked.closest('.pb-block-host');
        }
        return null;
    }

    function isPointerOverPreview(clientX, clientY) {
        var wrap = document.getElementById('pb-preview-wrap');
        if (wrap) {
            var wr = wrap.getBoundingClientRect();
            if (clientX >= wr.left && clientX <= wr.right && clientY >= wr.top && clientY <= wr.bottom) {
                return true;
            }
        }
        var iframe = document.getElementById('pb-preview');
        if (!iframe) {
            return false;
        }
        var rect = iframe.getBoundingClientRect();
        return clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom;
    }

    function getTopLevelPreviewHosts() {
        var doc = getPreviewDoc();
        if (!doc) {
            return [];
        }
        var hosts = [];
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            var p = el.getAttribute('data-pb-path');
            if (p && /^\d+$/.test(p)) {
                hosts.push(el);
            }
        });
        hosts.sort(function (a, b) {
            return parseInt(a.getAttribute('data-pb-path'), 10) - parseInt(b.getAttribute('data-pb-path'), 10);
        });
        return hosts;
    }

    function getDirectChildHosts(parentPath) {
        var doc = getPreviewDoc();
        if (!doc || !parentPath) {
            return [];
        }
        var hosts = [];
        var prefix = parentPath + '/';
        var parentDepth = parentPath.split('/').length;
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            var p = el.getAttribute('data-pb-path');
            if (!p || p.indexOf(prefix) !== 0) {
                return;
            }
            if (p.split('/').length === parentDepth + 2) {
                hosts.push(el);
            }
        });
        hosts.sort(function (a, b) {
            return iframeElParentRect(a).top - iframeElParentRect(b).top;
        });
        return hosts;
    }

    function computeTopLevelInsertIndex(clientY) {
        var hosts = getTopLevelPreviewHosts();
        if (!hosts.length) {
            return 0;
        }
        var edgePad = 20;
        var firstR = iframeElParentRect(hosts[0]);
        if (clientY <= firstR.top + edgePad) {
            return 0;
        }
        for (var i = 0; i < hosts.length - 1; i++) {
            var curR = iframeElParentRect(hosts[i]);
            var nextR = iframeElParentRect(hosts[i + 1]);
            var gapSplit = (curR.bottom + nextR.top) / 2;
            if (clientY < gapSplit) {
                return i + 1;
            }
        }
        return hosts.length;
    }

    function isNestableLayoutComp(comp) {
        // 注意：不要把 section 算进来。列表外常包一层分区，若优先「入分区」，
        // 会吞掉「列表上下插兄弟 / 拖完列表再拖别的」的落点（表现为拖完就卡住）。
        return comp === 'columns' || comp === 'grid' || comp === 'filmstrip_layout';
    }

    /** 预览 DOM：从当前 host 向上找最近的可嵌套布局（双栏等） */
    function findNestableHostEl(fromEl) {
        var doc = getPreviewDoc();
        if (!doc || !fromEl) {
            return null;
        }
        var el = fromEl;
        while (el && el !== doc.body && el !== doc.documentElement) {
            if (el.getAttribute && el.classList && el.classList.contains('pb-block-host')) {
                var c = el.getAttribute('data-pb-component') || '';
                if (isNestableLayoutComp(c)) {
                    return el;
                }
            }
            el = el.parentElement;
        }
        return null;
    }

    /** 指针是否落在某个双栏/栅格的包围盒内（子块再大也算在布局里） */
    function findNestableHostAtPoint(clientX, clientY) {
        var doc = getPreviewDoc();
        if (!doc) {
            return null;
        }
        var best = null;
        var bestArea = Infinity;
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            var c = el.getAttribute('data-pb-component') || '';
            if (!isNestableLayoutComp(c)) {
                return;
            }
            var r = iframeElParentRect(el);
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                var area = r.width * r.height;
                if (area < bestArea) {
                    bestArea = area;
                    best = el;
                }
            }
        });
        return best;
    }

    function resolveNestableChildTarget(blockId, nestHostEl, clientX, clientY, childComp) {
        if (!nestHostEl || !childComp) {
            return null;
        }
        var nestComp = nestHostEl.getAttribute('data-pb-component') || '';
        var nestPath = nestHostEl.getAttribute('data-pb-path') || '';
        if (!nestPath || !isNestableLayoutComp(nestComp)) {
            return null;
        }
        if (!canDropChildInParent(childComp, nestComp, false, getModelByPath(nestPath))) {
            return null;
        }
        // 窄边：贴着双栏最外上下沿时让出「插到双栏外面」；列内底部（如「最新更新」下方）仍算入列
        var nr = iframeElParentRect(nestHostEl);
        var edge = 8;
        if (nestComp === 'columns') {
            var colSlot = resolveColumnsSlotFromPoint(nestPath, clientX, clientY);
            var colEl = getColumnElForSlot(nestPath, colSlot);
            if (colEl) {
                var cr = iframeElParentRect(colEl);
                if (clientY >= cr.top + 4 && clientY <= cr.bottom - 4) {
                    return enrichDropTarget({
                        mode: 'child',
                        parentPath: nestPath,
                        at: computeChildInsertIndex(nestPath, clientY, colSlot),
                        columnSlot: colSlot
                    }, clientX, clientY, childComp);
                }
            }
        }
        if (clientY < nr.top + edge || clientY > nr.bottom - edge) {
            return null;
        }
        return enrichDropTarget({
            mode: 'child',
            parentPath: nestPath,
            at: computeChildInsertIndex(nestPath, clientY)
        }, clientX, clientY, childComp);
    }


    function isInTopLevelGapZone(clientY) {
        var hosts = getTopLevelPreviewHosts();
        if (!hosts.length) {
            return false;
        }
        // 只认「块与块之间 / 首尾外侧」的空隙，绝不能把块内部当成间隙
        // （旧逻辑用 pad 吃进块内，空双栏 ~100px 时几乎整块都拖不进去）
        var edge = 12;
        var first = iframeElParentRect(hosts[0]);
        if (clientY < first.top) {
            return true;
        }
        for (var i = 0; i < hosts.length - 1; i++) {
            var cur = iframeElParentRect(hosts[i]);
            var next = iframeElParentRect(hosts[i + 1]);
            var gap = next.top - cur.bottom;
            if (gap > 2) {
                if (clientY >= cur.bottom && clientY <= next.top) {
                    return true;
                }
            } else {
                // 贴得很近：只留一条很窄的兄弟插入缝，且可嵌套布局不参与抢缝
                var curComp = hosts[i].getAttribute('data-pb-component') || '';
                var nextComp = hosts[i + 1].getAttribute('data-pb-component') || '';
                if (isNestableLayoutComp(curComp) || isNestableLayoutComp(nextComp)) {
                    continue;
                }
                var mid = (cur.bottom + next.top) / 2;
                if (clientY >= mid - edge && clientY <= mid + edge) {
                    return true;
                }
            }
        }
        var last = iframeElParentRect(hosts[hosts.length - 1]);
        if (clientY > last.bottom) {
            return true;
        }
        return false;
    }

    function computeChildInsertIndex(parentPath, clientY, columnSlot) {
        var hosts = (columnSlot !== undefined && columnSlot !== null && columnSlot !== '')
            ? getDirectChildHostsInSlot(parentPath, columnSlot)
            : getDirectChildHosts(parentPath);
        var parentModel = getModelByPath(parentPath);
        if (!parentModel) {
            return 0;
        }
        if (!hosts.length) {
            return parentModel.components().length;
        }
        for (var i = 0; i < hosts.length; i++) {
            var r = iframeElParentRect(hosts[i]);
            var split = r.top + r.height / 2;
            if (clientY < split) {
                var childModel = getModelByPath(hosts[i].getAttribute('data-pb-path'));
                return childModel ? parentModel.components().indexOf(childModel) : i;
            }
        }
        if (columnSlot !== undefined && columnSlot !== null && columnSlot !== '') {
            var slotKey = String(columnSlot);
            var lastIdx = -1;
            parentModel.components().forEach(function (ch, idx) {
                if (String(ch.getAttributes()['data-slot'] || '') === slotKey) {
                    lastIdx = idx;
                }
            });
            if (lastIdx >= 0) {
                return lastIdx + 1;
            }
        }
        return parentModel.components().length;
    }

    function resolveDropTarget(blockId, hostEl, clientX, clientY) {
        var entry = paletteMap[blockId];
        var rootAt = computeTopLevelInsertIndex(clientY);
        if (!entry) {
            return { mode: 'region', regionName: 'content', at: rootAt };
        }
        var childComp = entry.block.component;

        // 几何优先：只要指针还在双栏包围盒内（哪怕上面盖着列表），就入列。
        // 不依赖 getModelByPath / 子块 path，避免列表把落点抢走变成「只能插在双栏上下」。
        var nestAtPoint = findNestableHostAtPoint(clientX, clientY);
        if (!nestAtPoint && hostEl) {
            nestAtPoint = findNestableHostEl(hostEl);
        }
        var nestTarget = resolveNestableChildTarget(blockId, nestAtPoint, clientX, clientY, childComp);
        if (nestTarget) {
            return nestTarget;
        }

        if (isInTopLevelGapZone(clientY)) {
            return { mode: 'region', regionName: defaultRegionForComponent(childComp), at: rootAt };
        }

        if (hostEl) {
            var path = hostEl.getAttribute('data-pb-path') || '';
            var hostComp = hostEl.getAttribute('data-pb-component') || '';
            var hostR = iframeElParentRect(hostEl);

            // 顶层块上下边缘：作为主内容区兄弟插入，避免被列表/分区「吃掉」落点
            // 可嵌套布局用更窄的边，避免空双栏几乎整块被边缘吃掉
            var topLevelEl = hostEl;
            var topPath = path;
            while (topPath && topPath.indexOf('/') >= 0) {
                topPath = topPath.replace(/\/[^/]+$/, '');
                var cand = getPreviewDoc() && getPreviewDoc().querySelector('.pb-block-host[data-pb-path="' + topPath + '"]');
                if (cand) {
                    topLevelEl = cand;
                } else {
                    break;
                }
            }
            if (topLevelEl && /^\d+$/.test(topLevelEl.getAttribute('data-pb-path') || '')) {
                var tlComp = topLevelEl.getAttribute('data-pb-component') || '';
                var tlR = iframeElParentRect(topLevelEl);
                var edge = isNestableLayoutComp(tlComp) ? 10 : 48;
                // 可嵌套且当前组件能拖进去：边缘不抢，整块留给入列
                if (!(isNestableLayoutComp(tlComp) && canDropChildInParent(childComp, tlComp, false, getModelByPath(topLevelEl.getAttribute('data-pb-path') || '')))) {
                    var tlIdx = parseInt(topLevelEl.getAttribute('data-pb-path'), 10);
                    if (clientY < tlR.top + edge) {
                        return { mode: 'region', regionName: defaultRegionForComponent(childComp), at: tlIdx };
                    }
                    if (clientY > tlR.bottom - edge) {
                        return { mode: 'region', regionName: defaultRegionForComponent(childComp), at: tlIdx + 1 };
                    }
                }
            }

            if (canDropChildInParent(childComp, hostComp, false, getModelByPath(path))) {
                return enrichDropTarget({
                    mode: 'child',
                    parentPath: path,
                    at: computeChildInsertIndex(path, clientY)
                }, clientX, clientY, childComp);
            }

            var model = getModelByPath(path);
            if (model) {
                var walker = model;
                while (walker) {
                    var attrs = walker.getAttributes() ? walker.getAttributes() : {};
                    var regionName = attrs['data-pb-region'] || '';
                    var pComp = attrs['data-component'] || '';
                    var pPath = attrs['data-pb-path'] || '';

                    // 内容区等 region：此前因 parent 是 wrapper 被 while 条件跳过
                    if (regionName) {
                        if (regionAllowsDirectChild(regionName, childComp)) {
                            return {
                                mode: 'region',
                                regionName: regionName,
                                at: rootAt
                            };
                        }
                        break;
                    }

                    if (pComp && canDropChildInParent(childComp, pComp, false, walker)) {
                        return enrichDropTarget({
                            mode: 'child',
                            parentPath: pPath,
                            at: computeChildInsertIndex(pPath, clientY)
                        }, clientX, clientY, childComp);
                    }

                    var par = walker.parent();
                    if (!par || par.is('wrapper')) {
                        break;
                    }
                    walker = par;
                }
            }
        }

        if (TOP_LEVEL.indexOf(childComp) >= 0) {
            return { mode: 'region', regionName: 'content', at: rootAt };
        }

        return {
            mode: 'region',
            regionName: defaultRegionForComponent(childComp),
            at: rootAt
        };
    }


    function getPreviewFrameScale() {
        if (appliedPreviewScale > 0 && isFinite(appliedPreviewScale)) {
            return appliedPreviewScale;
        }
        var frame = document.getElementById('pb-preview-frame');
        if (!frame) {
            return 1;
        }
        var rect = frame.getBoundingClientRect();
        var logicalW = appliedPreviewLogicalW || getPreviewLogicalWidth();
        if (!rect.width || !logicalW) {
            return 1;
        }
        return rect.width / logicalW;
    }

    function getIframeParentRect() {
        var iframe = document.getElementById('pb-preview');
        return iframe ? iframe.getBoundingClientRect() : null;
    }

    function parentClientToIframeCss(clientX, clientY) {
        var ir = getIframeParentRect();
        var scale = getPreviewFrameScale() || 1;
        if (!ir || scale <= 0) {
            return { x: clientX, y: clientY };
        }
        return {
            x: (clientX - ir.left) / scale,
            y: (clientY - ir.top) / scale
        };
    }

    /** iframe 内元素 → 父页面坐标（兼容已合成/未合成两种浏览器行为） */
    function iframeElParentRect(el) {
        if (!el || !el.getBoundingClientRect) {
            return { left: 0, top: 0, right: 0, bottom: 0, width: 0, height: 0 };
        }
        var r = el.getBoundingClientRect();
        var ir = getIframeParentRect();
        var scale = getPreviewFrameScale() || 1;
        if (!ir) {
            return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
        }
        if (r.left >= ir.left - 1 && r.top >= ir.top - 1 && r.right <= ir.right + 2 && r.bottom <= ir.bottom + 2) {
            return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
        }
        var left = ir.left + r.left * scale;
        var top = ir.top + r.top * scale;
        var width = r.width * scale;
        var height = r.height * scale;
        return { left: left, top: top, right: left + width, bottom: top + height, width: width, height: height };
    }

    function viewportRectToFrameLocal(rect) {
        var frame = document.getElementById('pb-preview-frame');
        if (!frame || !rect) {
            return { left: 0, top: 0, width: 0, height: 0 };
        }
        var fr = frame.getBoundingClientRect();
        var scale = getPreviewFrameScale() || 1;
        return {
            left: (rect.left - fr.left) / scale,
            top: (rect.top - fr.top) / scale,
            width: rect.width / scale,
            height: rect.height / scale
        };
    }

    function isDropTargetValid(blockId, target) {
        var entry = paletteMap[blockId];
        if (!entry || !target) {
            return false;
        }
        var childComp = entry.block.component;
        if (target.mode === 'root' || target.mode === 'region') {
            var regionName = target.regionName || defaultRegionForComponent(childComp);
            return regionAllowsDirectChild(regionName, childComp);
        }
        if (target.mode === 'child') {
            var parentModel = getModelByPath(target.parentPath);
            if (!parentModel) {
                return false;
            }
            var parentComp = parentModel.getAttributes()['data-component'] || '';
            var parentRegion = parentModel.getAttributes()['data-pb-region'] || '';
            if (parentRegion && !parentComp) {
                return regionAllowsDirectChild(parentRegion, childComp);
            }
            return canDropChildInParent(childComp, parentComp, false, parentModel);
        }
        return false;
    }

    function getColumnElForSlot(columnsPath, columnSlot) {
        var doc = getPreviewDoc();
        if (!doc || !columnsPath) {
            return null;
        }
        var host = doc.querySelector('.pb-block-host[data-pb-path="' + columnsPath + '"]');
        if (!host) {
            return null;
        }
        var cols = host.querySelectorAll('.bili-columns__col');
        var idx = parseInt(columnSlot, 10);
        if (isNaN(idx) || idx < 0) {
            idx = 0;
        }
        return cols[idx] || null;
    }

    function getDirectChildHostsInSlot(parentPath, columnSlot) {
        var hosts = getDirectChildHosts(parentPath);
        if (columnSlot === undefined || columnSlot === null || columnSlot === '') {
            return hosts;
        }
        var slot = String(columnSlot);
        var prefix = parentPath + '/' + slot + '/';
        return hosts.filter(function (el) {
            var p = el.getAttribute('data-pb-path') || '';
            return p.indexOf(prefix) === 0;
        });
    }

    function resolveFilmstripDropSlot(target, clientX, clientY) {
        if (!target || target.mode !== 'child' || !target.parentPath) {
            return 'feed';
        }
        if (target.filmstripSlot) {
            return target.filmstripSlot;
        }
        var childComp = '';
        if (dragState && dragState.blockId && paletteMap[dragState.blockId]) {
            childComp = paletteMap[dragState.blockId].block.component || '';
        }
        if (isFilmstripBannerComp(childComp)) {
            return 'banner';
        }
        if (isFilmstripFeedComp(childComp)) {
            return 'feed';
        }
        return resolveFilmstripSlotFromPoint(target.parentPath, clientX, clientY);
    }

    function slotViewportFromBounds(bounds, minSlotH, pad) {
        if (!bounds || bounds.width <= 4 || bounds.height <= 4) {
            return null;
        }
        pad = pad != null ? pad : 10;
        var lineY = bounds.top + bounds.height / 2;
        lineY = Math.max(bounds.top + 10, Math.min(lineY, bounds.bottom - 10));
        return {
            left: bounds.left + pad,
            top: bounds.top + Math.max(0, (bounds.height - minSlotH) / 2),
            width: Math.max(120, bounds.width - pad * 2),
            height: minSlotH,
            lineY: lineY
        };
    }

    function resolveFilmstripSlotForPointer(clientX, clientY) {
        var nestHost = findNestableHostAtPoint(clientX, clientY);
        if (!nestHost || nestHost.getAttribute('data-pb-component') !== 'filmstrip_layout') {
            return null;
        }
        var fsPath = nestHost.getAttribute('data-pb-path') || '';
        var fsSlot = resolveFilmstripSlotFromPoint(fsPath, clientX, clientY);
        var childComp = '';
        if (dragState && dragState.blockId && paletteMap[dragState.blockId]) {
            childComp = paletteMap[dragState.blockId].block.component || '';
        }
        if (isFilmstripBannerComp(childComp)) {
            fsSlot = 'banner';
        } else if (isFilmstripFeedComp(childComp)) {
            fsSlot = 'feed';
        }
        return { path: fsPath, slot: fsSlot };
    }

    function getActiveSlotViewportRect(target, clientX, clientY) {
        var ir = getIframeParentRect();
        var side = 20;
        var minSlotH = 88;

        // 环绕布局：指针在布局内时，占位始终对齐 banner/feed 槽（不受 display:contents 影响）
        if (clientX != null && clientY != null) {
            var fsHit = resolveFilmstripSlotForPointer(clientX, clientY);
            if (fsHit) {
                var fsVp = slotViewportFromBounds(getFilmstripSlotBounds(fsHit.path, fsHit.slot), minSlotH, 10);
                if (fsVp) {
                    return fsVp;
                }
            }
        }

        if (target.mode === 'child') {
            var doc = getPreviewDoc();
            var parentHost = doc ? doc.querySelector('.pb-block-host[data-pb-path="' + target.parentPath + '"]') : null;
            var parentComp = parentHost ? (parentHost.getAttribute('data-pb-component') || '') : '';
            var colEl = null;
            var siblings;
            var slotName = '';
            if (parentComp === 'columns' && target.columnSlot !== undefined && target.columnSlot !== null && target.columnSlot !== '') {
                colEl = getColumnElForSlot(target.parentPath, target.columnSlot);
                siblings = getDirectChildHostsInSlot(target.parentPath, target.columnSlot);
            } else if (parentComp === 'filmstrip_layout') {
                slotName = resolveFilmstripDropSlot(target, clientX, clientY);
                colEl = getFilmstripSlotEl(target.parentPath, slotName);
                siblings = getDirectChildHostsInSlot(target.parentPath, slotName);
            } else {
                siblings = getDirectChildHosts(target.parentPath);
            }
            var lineY;
            var bounds = null;
            if (parentComp === 'filmstrip_layout') {
                bounds = getFilmstripSlotBounds(target.parentPath, slotName || resolveFilmstripDropSlot(target, clientX, clientY));
            } else if (colEl) {
                bounds = iframeElParentRect(colEl);
            } else if (parentHost) {
                bounds = iframeElParentRect(parentHost);
            }
            if (!siblings.length && bounds) {
                lineY = bounds.top + bounds.height / 2;
            } else if (target.at >= siblings.length) {
                if (siblings.length) {
                    var lastR = iframeElParentRect(siblings[siblings.length - 1]);
                    lineY = (lastR.height > 4) ? lastR.bottom : (bounds ? bounds.top + bounds.height / 2 : clientY);
                } else {
                    lineY = bounds ? bounds.top + bounds.height / 2 : clientY;
                }
            } else {
                var sibR = iframeElParentRect(siblings[target.at]);
                lineY = (sibR.height > 4) ? sibR.top : (bounds ? bounds.top + bounds.height / 2 : clientY);
            }
            if (bounds) {
                var pad = colEl ? 10 : side;
                var vp = slotViewportFromBounds(bounds, minSlotH, pad);
                if (vp) {
                    if (siblings.length) {
                        vp.lineY = Math.max(bounds.top + 10, Math.min(lineY, bounds.bottom - 10));
                        vp.top = vp.lineY - minSlotH / 2;
                    }
                    return vp;
                }
            }
        }

        var roots = getTopLevelPreviewHosts();
        var top;
        var bottom;
        var lineY2;
        if (!roots.length) {
            top = ir ? ir.top + 48 : clientY - minSlotH / 2;
            bottom = top + minSlotH;
            lineY2 = top + minSlotH / 2;
        } else if (target.at <= 0) {
            var firstR = iframeElParentRect(roots[0]);
            lineY2 = firstR.top;
            top = lineY2 - minSlotH / 2;
            bottom = lineY2 + 8;
        } else if (target.at >= roots.length) {
            var lastR = iframeElParentRect(roots[roots.length - 1]);
            lineY2 = lastR.bottom;
            top = lineY2 - 8;
            bottom = lineY2 + minSlotH / 2;
        } else {
            var prevR = iframeElParentRect(roots[target.at - 1]);
            var nextR = iframeElParentRect(roots[target.at]);
            lineY2 = (prevR.bottom + nextR.top) / 2;
            top = prevR.bottom + 6;
            bottom = nextR.top - 6;
            if (bottom - top < minSlotH) {
                top = lineY2 - minSlotH / 2;
                bottom = lineY2 + minSlotH / 2;
            }
        }
        if (!ir) {
            return { left: side, top: top, width: 400, height: minSlotH, lineY: lineY2 };
        }
        return {
            left: ir.left + side,
            top: top,
            width: Math.max(160, ir.width - side * 2),
            height: Math.max(minSlotH, bottom - top),
            lineY: lineY2
        };
    }

    var DROP_LINE_BAND_H = 44;
    var DROP_CHIP_H = 44;

    function dropLineFrameLocal(slotVp) {
        var lineY = slotVp.lineY != null ? slotVp.lineY : (slotVp.top + slotVp.height / 2);
        var slotLocal = viewportRectToFrameLocal({
            left: slotVp.left,
            top: slotVp.top,
            width: slotVp.width,
            height: slotVp.height
        });
        var lineLocal = viewportRectToFrameLocal({
            left: slotVp.left,
            top: lineY,
            width: 1,
            height: 1
        });
        return { lineTop: lineLocal.top, slotLocal: slotLocal };
    }

    function paintActiveGapMarker(target, valid, clientX, clientY) {
        var layer = document.getElementById('pb-drop-indicators');
        if (!layer || !target) {
            return;
        }
        layer.querySelectorAll('.pb-drop-gap-marker').forEach(function (n) {
            n.remove();
        });
        if (!valid) {
            return;
        }
        var cx = clientX != null ? clientX : (dragState ? dragState.clientX : 0);
        var cy = clientY != null ? clientY : (dragState ? dragState.clientY : 0);
        var slot = getActiveSlotViewportRect(target, cx, cy);
        var pos = dropLineFrameLocal(slot);
        var m = document.createElement('div');
        m.className = 'pb-drop-gap-marker is-active';
        m.style.left = pos.slotLocal.left + 'px';
        m.style.width = Math.max(80, pos.slotLocal.width) + 'px';
        m.style.right = 'auto';
        m.style.top = (pos.lineTop - DROP_LINE_BAND_H / 2) + 'px';
        m.style.height = DROP_LINE_BAND_H + 'px';
        layer.appendChild(m);
    }

    function markAdjacentRootBlocks(target) {
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        doc.querySelectorAll('.pb-drop-adjacent').forEach(function (el) {
            el.classList.remove('pb-drop-adjacent');
        });
        if (!target || target.mode !== 'root') {
            return;
        }
        var roots = getTopLevelPreviewHosts();
        if (target.at > 0 && roots[target.at - 1]) {
            roots[target.at - 1].classList.add('pb-drop-adjacent');
        }
        if (target.at < roots.length && roots[target.at]) {
            roots[target.at].classList.add('pb-drop-adjacent');
        }
    }

    function paintDropZoneHints(blockId) {
        var doc = getPreviewDoc();
        var entry = paletteMap[blockId];
        if (!doc || !entry) {
            return;
        }
        var childComp = entry.block.component;
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            var comp = el.getAttribute('data-pb-component') || '';
            var path = el.getAttribute('data-pb-path') || '';
            if (canDropChildInParent(childComp, comp, false)) {
                el.classList.add('pb-drop-valid-parent');
            }
            if (TOP_LEVEL.indexOf(childComp) >= 0 && /^\d+$/.test(path)) {
                el.classList.add('pb-drop-root-marker');
            }
        });
    }

    function markHostsDropZones(blockId, activeHost, target, valid) {
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            el.classList.remove('pb-drop-inside', 'pb-drop-valid-hover', 'pb-drop-reject');
        });
        if (!activeHost) {
            return;
        }
        if (!valid) {
            activeHost.classList.add('pb-drop-reject');
            return;
        }
        if (target && target.mode === 'child') {
            var parentHost = doc.querySelector('.pb-block-host[data-pb-path="' + target.parentPath + '"]');
            if (parentHost) {
                parentHost.classList.add('pb-drop-inside', 'pb-drop-valid-hover');
            }
        }
    }

    function setDropHintText(text, valid) {
        var hintEl = document.getElementById('pb-center-hint');
        if (!hintEl) {
            return;
        }
        hintEl.textContent = text;
        hintEl.classList.toggle('is-drop-valid', valid);
        hintEl.classList.toggle('is-drop-invalid', !valid);
    }

    function updateDropVisuals(blockId, target, clientX, clientY, valid, activeHost) {
        var layer = document.getElementById('pb-drop-indicators');
        if (layer) {
            layer.querySelectorAll('.pb-drop-ghost, .pb-drop-insert-line-v').forEach(function (n) {
                n.remove();
            });
        }
        markHostsDropZones(blockId, activeHost, target, valid);
        markAdjacentRootBlocks(valid ? target : null);

        var overPreview = dragState && isPointerOverPreview(dragState.clientX, dragState.clientY);
        if (!overPreview) {
            $('#pb-preview-wrap').removeClass('is-drop-valid').addClass('is-drop-invalid');
            setDropHintText(PB.lang.drop_invalid || 'Cannot drop here', false);
            paintActiveGapMarker(null, false);
            return;
        }

        var hint = valid
            ? (PB.lang.drop_here || 'Release to insert')
            : (PB.lang.drop_invalid || 'Cannot drop here');
        setDropHintText(hint, valid);
        $('#pb-preview-wrap').toggleClass('is-drop-valid', valid).toggleClass('is-drop-invalid', !valid);

        if (!layer || !target) {
            return;
        }

        paintActiveGapMarker(target, valid, clientX, clientY);

        var entry = paletteMap[blockId];
        var label = entry ? (entry.label || entry.block.component || '') : '';
        var slotVp = getActiveSlotViewportRect(target, clientX, clientY);
        var pos = dropLineFrameLocal(slotVp);
        var slotLocal = pos.slotLocal;

        if (slotVp.lineY != null) {
            var lineLocal = viewportRectToFrameLocal({
                left: slotVp.left,
                top: slotVp.lineY - 2,
                width: slotVp.width,
                height: 4
            });
            var line = document.createElement('div');
            line.className = 'pb-drop-insert-line-v' + (valid ? ' is-valid' : ' is-invalid');
            line.style.left = slotLocal.left + 'px';
            line.style.top = lineLocal.top + 'px';
            line.style.width = slotLocal.width + 'px';
            layer.appendChild(line);
        }

        var ghost = document.createElement('div');
        ghost.className = 'pb-drop-ghost pb-drop-ghost--chip' + (valid ? ' is-valid' : ' is-invalid');
        ghost.innerHTML =
            '<span class="pb-drop-ghost-plus" aria-hidden="true">+</span>' +
            '<span class="pb-drop-ghost-body">' +
            '<span class="pb-drop-ghost-label">' + escHtml(label) + '</span>' +
            '</span>';
        // 列内指示：胶囊靠左，不在整行居中造成「飘在双栏顶上」的错觉
        var chipLeft = slotLocal.left + 12;
        ghost.style.left = chipLeft + 'px';
        ghost.style.top = (slotLocal.top + Math.max(0, (slotLocal.height - DROP_CHIP_H) / 2)) + 'px';
        ghost.style.maxWidth = Math.max(120, Math.min(220, slotLocal.width - 24)) + 'px';
        layer.appendChild(ghost);
    }

    function refreshDragStateFromPointer(clientX, clientY) {
        if (!dragState || !dragState.blockId) {
            return;
        }
        if (clientX || clientY) {
            lastDropPointer.x = clientX;
            lastDropPointer.y = clientY;
        }
        dragState.clientX = clientX;
        dragState.clientY = clientY;
        if (!isPointerOverPreview(clientX, clientY)) {
            dragState.host = null;
            dragState.target = { mode: 'region', regionName: 'content', at: computeTopLevelInsertIndex(clientY) };
            dragState.valid = false;
            dragState.overPreview = false;
            updateDropVisuals(dragState.blockId, dragState.target, clientX, clientY, false, null);
            return;
        }
        dragState.overPreview = true;
        dragState.host = findPreviewHostAtPoint(clientX, clientY);
        dragState.target = dragState.host
            ? resolveDropTarget(dragState.blockId, dragState.host, clientX, clientY)
            : { mode: 'region', regionName: defaultRegionForComponent((paletteMap[dragState.blockId] || {}).block ? paletteMap[dragState.blockId].block.component : ''), at: computeTopLevelInsertIndex(clientY) };
        dragState.valid = isDropTargetValid(dragState.blockId, dragState.target);
        updateDropVisuals(dragState.blockId, dragState.target, clientX, clientY, dragState.valid, dragState.host);
    }

    function finalizeDropInsert(blockId, state) {
        if (!blockId || !state) {
            return false;
        }
        if (dropInsertedOnRelease) {
            return true;
        }
        var px = state.clientX || lastDropPointer.x;
        var py = state.clientY || lastDropPointer.y;
        // 拖拽过程中已判定有效：直接插入，不再因松手离开预览区而推翻
        if (state.valid && state.target) {
            insertBlockAtTarget(blockId, state.target);
            dropInsertedOnRelease = true;
            return true;
        }
        if (px || py) {
            if (isPointerOverPreview(px, py)) {
                refreshDragStateFromPointer(px, py);
            }
        }
        if (dragState && dragState.valid && dragState.target) {
            insertBlockAtTarget(blockId, dragState.target);
            dropInsertedOnRelease = true;
            return true;
        }
        var entry = paletteMap[blockId];
        var childComp = entry && entry.block ? entry.block.component : '';
        if (childComp === 'channel_strip' && pageOwnsChannelRegion()) {
            var chRegion = getRegionModel('channel');
            var at = chRegion ? chRegion.components().length : 0;
            insertBlockAtTarget(blockId, { mode: 'region', regionName: 'channel', at: at });
            dropInsertedOnRelease = true;
            return true;
        }
        return false;
    }

    function clearDropIndicators(resetHint) {
        var doc = getPreviewDoc();
        if (doc) {
            doc.querySelectorAll('.pb-block-host').forEach(function (el) {
                el.classList.remove(
                    'pb-drop-inside',
                    'pb-drop-valid-parent',
                    'pb-drop-valid-hover',
                    'pb-drop-reject',
                    'pb-drop-root-marker',
                    'pb-drop-adjacent'
                );
            });
        }
        var layer = document.getElementById('pb-drop-indicators');
        if (layer) {
            layer.innerHTML = '';
        }
        $('#pb-preview-wrap').removeClass('is-drop-valid is-drop-invalid');
        if (resetHint) {
            var hintEl = document.getElementById('pb-center-hint');
            if (hintEl) {
                hintEl.textContent = PB.lang.click_hint || '';
                hintEl.classList.remove('is-drop-valid', 'is-drop-invalid');
            }
        }
    }

    function stopPaletteDragTracking() {
        if (paletteDragMoveHandler) {
            document.removeEventListener('mousemove', paletteDragMoveHandler, true);
            window.removeEventListener('mousemove', paletteDragMoveHandler, true);
            window.removeEventListener('pointermove', paletteDragMoveHandler, true);
        }
        if (paletteDragUpHandler) {
            document.removeEventListener('mouseup', paletteDragUpHandler, true);
            window.removeEventListener('mouseup', paletteDragUpHandler, true);
            window.removeEventListener('pointerup', paletteDragUpHandler, true);
        }
        if (paletteIframeMoveHandler) {
            var doc = getPreviewDoc();
            if (doc) {
                doc.removeEventListener('mousemove', paletteIframeMoveHandler, true);
            }
        }
        var overlay = document.getElementById('pb-preview-drag-overlay');
        if (overlay && paletteDragMoveHandler) {
            overlay.removeEventListener('mousemove', paletteDragMoveHandler);
        }
        if (overlay && paletteDragUpHandler) {
            overlay.removeEventListener('mouseup', paletteDragUpHandler);
            overlay.removeEventListener('pointerup', paletteDragUpHandler);
        }
        if (paletteScrollHandler) {
            var scrollDoc = getPreviewDoc();
            if (scrollDoc) {
                scrollDoc.removeEventListener('scroll', paletteScrollHandler, true);
            }
            paletteScrollHandler = null;
        }
        paletteDragMoveHandler = null;
        paletteDragUpHandler = null;
        paletteIframeMoveHandler = null;
    }

    function onPaletteBlockDrag(block, ev) {
        if (!dragState || !dragState.blockId) {
            return;
        }
        if (ev && (ev.clientX || ev.clientY)) {
            refreshDragStateFromPointer(ev.clientX, ev.clientY);
        }
    }

    function startPaletteDragTracking(block) {
        stopPaletteDragTracking();
        dropInsertedOnRelease = false;
        dragState = {
            blockId: block ? block.getId() : '',
            clientX: 0,
            clientY: 0,
            host: null,
            target: null,
            valid: false,
            overPreview: false
        };
        lastDropPointer = { x: 0, y: 0 };
        $('#pb-preview-wrap').addClass('is-preview-drop');
        document.body.classList.add('pb-palette-dragging');
        paintDropZoneHints(dragState.blockId);

        paletteScrollHandler = function () {
            if (dragState) {
                refreshDragStateFromPointer(dragState.clientX, dragState.clientY);
            }
        };
        var scrollDoc = getPreviewDoc();
        if (scrollDoc) {
            scrollDoc.addEventListener('scroll', paletteScrollHandler, true);
        }

        paletteDragMoveHandler = function (e) {
            refreshDragStateFromPointer(e.clientX, e.clientY);
        };
        paletteDragUpHandler = function (e) {
            if (!dragState || !dragState.blockId) {
                return;
            }
            lastDropPointer.x = e.clientX;
            lastDropPointer.y = e.clientY;
            refreshDragStateFromPointer(e.clientX, e.clientY);
            if (!dropInsertedOnRelease && dragState.valid && dragState.overPreview && dragState.target) {
                insertBlockAtTarget(dragState.blockId, dragState.target);
                dropInsertedOnRelease = true;
            }
        };

        document.addEventListener('mousemove', paletteDragMoveHandler, true);
        window.addEventListener('mousemove', paletteDragMoveHandler, true);
        window.addEventListener('pointermove', paletteDragMoveHandler, true);
        document.addEventListener('mouseup', paletteDragUpHandler, true);
        window.addEventListener('mouseup', paletteDragUpHandler, true);
        window.addEventListener('pointerup', paletteDragUpHandler, true);

        var doc = getPreviewDoc();
        if (doc) {
            paletteIframeMoveHandler = paletteDragMoveHandler;
            doc.addEventListener('mousemove', paletteIframeMoveHandler, true);
        }

        var overlay = document.getElementById('pb-preview-drag-overlay');
        if (overlay) {
            overlay.addEventListener('mousemove', paletteDragMoveHandler);
            overlay.addEventListener('mouseup', paletteDragUpHandler);
            overlay.addEventListener('pointerup', paletteDragUpHandler);
        }
    }

    function catTraitOptions(mid) {
        var opts = [
            { value: 'current', name: traitLabel('trait_cat_current', 'Current category') },
            { value: '0', name: traitLabel('trait_cat_all', 'All categories') }
        ];
        (PB.cats || []).forEach(function (c) {
            if (mid && c.mid && c.mid !== mid) {
                return;
            }
            opts.push({ value: String(c.id), name: String(c.name || c.id).replace(/^[\s　├]+/, '') });
        });
        return opts;
    }

    function syncChromeTypeIds(sourceModel, ids) {
        if (!editor || !sourceModel) {
            return;
        }
        ids = Array.isArray(ids) ? ids.slice() : [];
        editor.getWrapper().components().forEach(function (m) {
            if (!m.getAttributes()['data-chrome']) {
                return;
            }
            var c = m.getAttributes()['data-component'];
            if (c === 'site_nav' || c === 'channel_strip') {
                if (m !== sourceModel) {
                    m.set('type_ids', ids.slice());
                }
                if (editor.getSelected() === m) {
                    syncChromeCatsCheckboxes(ids.slice());
                }
            }
        });
    }

    function modelPropNames(comp) {
        var names = traitNames(comp);
        if (comp === 'site_nav') {
            if (names.indexOf('logo') < 0) {
                names.unshift('logo');
            }
        }
        if (comp === 'header_decor') {
            if (names.indexOf('image') < 0) {
                names.unshift('image');
            }
        }
        if (comp === 'site_footer') {
            if (names.indexOf('logo') < 0) {
                names.unshift('logo');
            }
        }
        if (comp === 'site_nav' || comp === 'channel_strip') {
            if (names.indexOf('type_ids') < 0) {
                names.unshift('type_ids');
            }
        }
        return names;
    }

    var chromeCatsBound = false;
    var chromeCatsSuppress = false;
    var chromeLogoBound = false;
    var chromeLogoSuppress = false;
    var chromeDecorBound = false;
    var chromeDecorSuppress = false;
    var chromeFooterLogoBound = false;
    var chromeFooterLogoSuppress = false;

    function showPbChromeImagePreview($img, path, fallbackUrl) {
        if (!$img || !$img.length) {
            return;
        }
        var src = path ? pbRootedSrc(path) : (fallbackUrl || '');
        if (src) {
            $img.attr('src', src).prop('hidden', false);
        } else {
            $img.removeAttr('src').prop('hidden', true);
        }
    }

    function showPbLogoPreview(path) {
        showPbChromeImagePreview($('#pbLogoPreview'), path, PB.themeLogoUrl || '');
    }

    function showPbDecorPreview(path) {
        showPbChromeImagePreview($('#pbDecorPreview'), path, '');
    }

    function showPbFooterLogoPreview(path) {
        showPbChromeImagePreview($('#pbFooterLogoPreview'), path, PB.themeLogoUrl || '');
    }

    function pbRootedSrc(p) {
        if (!p) {
            return '';
        }
        if (p.indexOf('://') >= 0 || p.indexOf('data:') === 0 || p.charAt(0) === '/') {
            return p;
        }
        var root = (typeof ROOT_PATH !== 'undefined' && ROOT_PATH) ? ROOT_PATH : '';
        return root + '/' + p;
    }

    function bindPbUploadButton(elem, onPath) {
        if (!elem || typeof layui === 'undefined') {
            return;
        }
        layui.use(['upload', 'layer'], function () {
            layui.upload.render({
                elem: elem,
                url: PB.urls.upload || '',
                method: 'post',
                accept: 'images',
                before: function () {
                    layui.layer.msg(PB.lang.uploading || 'Uploading…', { time: 30000 });
                },
                done: function (res) {
                    if (res && res.code === 1 && res.data && res.data.path) {
                        onPath(String(res.data.path));
                        layui.layer.msg(PB.lang.upload_ok || 'OK', { icon: 1 });
                    } else {
                        layui.layer.msg((res && res.msg) ? res.msg : (PB.lang.upload_err || 'Failed'), { icon: 2 });
                    }
                },
                error: function () {
                    layui.layer.msg(PB.lang.upload_err || 'Failed', { icon: 2 });
                }
            });
        });
    }

    function syncPbImageFieldPreview($preview, path) {
        if (!$preview || !$preview.length) {
            return;
        }
        path = String(path || '').trim();
        if (path) {
            $preview.attr('src', pbRootedSrc(path)).prop('hidden', false);
        } else {
            $preview.removeAttr('src').prop('hidden', true);
        }
    }

    function registerPbBottomNavItemsTrait() {
        if (!editor || !editor.TraitManager) {
            return;
        }
        var ICON_OPTS_BN = [
            { value: 'home', name: 'home' },
            { value: 'video', name: 'video' },
            { value: 'book', name: 'book' },
            { value: 'manga', name: 'manga' },
            { value: 'user', name: 'user' },
            { value: 'grid', name: 'grid' },
            { value: 'compass', name: 'compass' }
        ];

        editor.TraitManager.addType('pb-bottom-nav-items', {
            createInput: function (opts) {
                var trait = opts.trait;
                var component = opts.component;
                var name = trait.get('name');
                var wrap = document.createElement('div');
                wrap.className = 'pb-bnav-items';

                function lang(key, fallback) {
                    return traitLabel(key, fallback);
                }

                function readItems() {
                    return normalizeBottomNavItems({ items: component.get(name) });
                }

                function writeItems(items, persist) {
                    var next = normalizeBottomNavItems({ items: items });
                    wrap.__pbItems = next;
                    render();
                    if (persist) {
                        component.set(name, next);
                        if (!loading && !suppressSave) {
                            scheduleAutoSave(true);
                            updateUndoRedoButtons();
                        }
                    }
                }

                function render() {
                    var items = wrap.__pbItems || readItems();
                    wrap.__pbItems = items;
                    var hrefPh = escHtml(lang('trait_bottom_nav_href_ph', '留空=按类型默认'));
                    var html = '';
                    items.forEach(function (item, idx) {
                        html += '<div class="pb-bnav-items__row" data-idx="' + idx + '">' +
                            '<div class="pb-bnav-items__head">' +
                            '<span class="pb-bnav-items__idx">#' + (idx + 1) + '</span>' +
                            '<div class="pb-bnav-items__actions">' +
                            '<button type="button" class="pb-bnav-items__btn" data-act="up"' + (idx === 0 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_move_up', 'Up')) + '</button>' +
                            '<button type="button" class="pb-bnav-items__btn" data-act="down"' + (idx >= items.length - 1 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_move_down', 'Down')) + '</button>' +
                            '<button type="button" class="pb-bnav-items__btn pb-bnav-items__btn--danger" data-act="remove"' + (items.length <= 1 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_remove', 'Remove')) + '</button>' +
                            '</div></div>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_bottom_nav_item_label', 'Label')) + '</span>' +
                            '<input type="text" data-field="label" maxlength="20" value="' + escHtml(item.label) + '"></label>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_bottom_nav_item_href', 'Link')) + '</span>' +
                            '<input type="text" data-field="href" value="' + escHtml(item.href) + '" placeholder="' + hrefPh + '"></label>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_bottom_nav_item_icon', 'Icon')) + '</span>' +
                            '<select data-field="icon">';
                        ICON_OPTS_BN.forEach(function (opt) {
                            html += '<option value="' + opt.value + '"' + (item.icon === opt.value ? ' selected' : '') + '>' + opt.name + '</option>';
                        });
                        html += '</select></label></div>';
                    });
                    html += '<button type="button" class="pb-bnav-items__add"' + (items.length >= 6 ? ' disabled' : '') + '>' +
                        escHtml(lang('trait_bottom_nav_add', 'Add item')) + '</button>';
                    wrap.innerHTML = html;
                }

                wrap.addEventListener('click', function (ev) {
                    var t = ev.target;
                    if (!t || !t.getAttribute) {
                        return;
                    }
                    if (t.classList.contains('pb-bnav-items__add')) {
                        ev.preventDefault();
                        var cur = (wrap.__pbItems || readItems()).slice();
                        if (cur.length >= 6) {
                            return;
                        }
                        cur.push({ id: 'item_' + Date.now(), label: '菜单', href: '', icon: 'home' });
                        writeItems(cur, true);
                        return;
                    }
                    var act = t.getAttribute('data-act');
                    if (!act) {
                        return;
                    }
                    ev.preventDefault();
                    var row = t.closest ? t.closest('.pb-bnav-items__row') : null;
                    if (!row) {
                        return;
                    }
                    var idx = parseInt(row.getAttribute('data-idx'), 10);
                    var list = (wrap.__pbItems || readItems()).slice();
                    if (isNaN(idx) || idx < 0 || idx >= list.length) {
                        return;
                    }
                    if (act === 'remove') {
                        if (list.length <= 1) {
                            return;
                        }
                        list.splice(idx, 1);
                        writeItems(list, true);
                    } else if (act === 'up' && idx > 0) {
                        var tmp = list[idx - 1];
                        list[idx - 1] = list[idx];
                        list[idx] = tmp;
                        writeItems(list, true);
                    } else if (act === 'down' && idx < list.length - 1) {
                        var tmp2 = list[idx + 1];
                        list[idx + 1] = list[idx];
                        list[idx] = tmp2;
                        writeItems(list, true);
                    }
                });

                wrap.addEventListener('change', function (ev) {
                    var t = ev.target;
                    if (!t || !t.getAttribute) {
                        return;
                    }
                    var field = t.getAttribute('data-field');
                    if (!field) {
                        return;
                    }
                    var row = t.closest ? t.closest('.pb-bnav-items__row') : null;
                    if (!row) {
                        return;
                    }
                    var idx = parseInt(row.getAttribute('data-idx'), 10);
                    var list = (wrap.__pbItems || readItems()).slice();
                    if (isNaN(idx) || !list[idx]) {
                        return;
                    }
                    list[idx] = {
                        id: list[idx].id,
                        label: field === 'label' ? t.value : list[idx].label,
                        href: field === 'href' ? t.value : list[idx].href,
                        icon: field === 'icon' ? t.value : list[idx].icon
                    };
                    writeItems(list, true);
                });

                wrap.__pbRenderFromModel = function () {
                    wrap.__pbItems = readItems();
                    render();
                };
                wrap.__pbItems = readItems();
                render();
                return wrap;
            },
            onUpdate: function (opts) {
                var elInput = opts.elInput;
                if (elInput && typeof elInput.__pbRenderFromModel === 'function') {
                    suppressSave = true;
                    elInput.__pbRenderFromModel();
                    suppressSave = false;
                }
            }
        });
    }

    function normalizeAdsGridItems(props) {
        props = props || {};
        var raw = Array.isArray(props.items) ? props.items : [];
        var out = [];
        var used = {};
        raw.forEach(function (item, idx) {
            if (out.length >= 10 || !item || typeof item !== 'object') {
                return;
            }
            var id = String(item.id != null ? item.id : '').trim().replace(/[^a-z0-9_\-]/ig, '');
            if (!id) {
                id = 'ad_' + (idx + 1);
            }
            if (used[id]) {
                id = id + '_' + (idx + 1);
            }
            used[id] = 1;
            var label = String(item.label != null ? item.label : '').trim().slice(0, 20);
            var href = String(item.href != null ? item.href : '').trim();
            if (href === '/') {
                href = '';
            }
            var cover = String(item.cover != null ? item.cover : '').trim();
            if (!label && !cover && !href) {
                return;
            }
            out.push({ id: id, label: label, href: href, cover: cover });
        });
        return out;
    }

    function registerPbAdsGridItemsTrait() {
        if (!editor || !editor.TraitManager) {
            return;
        }
        editor.TraitManager.addType('pb-ads-grid-items', {
            createInput: function (opts) {
                var trait = opts.trait;
                var component = opts.component;
                var name = trait.get('name');
                var wrap = document.createElement('div');
                wrap.className = 'pb-bnav-items';

                function lang(key, fallback) {
                    return traitLabel(key, fallback);
                }

                function readItems() {
                    return normalizeAdsGridItems({ items: component.get(name) });
                }

                function writeItems(items, persist) {
                    var next = normalizeAdsGridItems({ items: items });
                    wrap.__pbItems = next;
                    render();
                    if (persist) {
                        component.set(name, next);
                        if (!loading && !suppressSave) {
                            scheduleAutoSave(true);
                            updateUndoRedoButtons();
                        }
                    }
                }

                function render() {
                    var items = wrap.__pbItems || readItems();
                    wrap.__pbItems = items;
                    var hrefPh = escHtml(lang('trait_ads_grid_href_ph', 'https:// or /path'));
                    var coverPh = escHtml(lang('trait_ads_grid_cover_ph', 'Image URL'));
                    var html = '';
                    items.forEach(function (item, idx) {
                        html += '<div class="pb-bnav-items__row" data-idx="' + idx + '">' +
                            '<div class="pb-bnav-items__head">' +
                            '<span class="pb-bnav-items__idx">#' + (idx + 1) + '</span>' +
                            '<div class="pb-bnav-items__actions">' +
                            '<button type="button" class="pb-bnav-items__btn" data-act="up"' + (idx === 0 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_move_up', 'Up')) + '</button>' +
                            '<button type="button" class="pb-bnav-items__btn" data-act="down"' + (idx >= items.length - 1 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_move_down', 'Down')) + '</button>' +
                            '<button type="button" class="pb-bnav-items__btn pb-bnav-items__btn--danger" data-act="remove"' + (items.length <= 0 ? ' disabled' : '') + '>' + escHtml(lang('trait_bottom_nav_remove', 'Remove')) + '</button>' +
                            '</div></div>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_ads_grid_item_label', 'Label')) + '</span>' +
                            '<input type="text" data-field="label" maxlength="20" value="' + escHtml(item.label) + '"></label>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_ads_grid_item_href', 'Link')) + '</span>' +
                            '<input type="text" data-field="href" value="' + escHtml(item.href) + '" placeholder="' + hrefPh + '"></label>' +
                            '<label class="pb-bnav-items__field"><span>' + escHtml(lang('trait_ads_grid_item_cover', 'Cover')) + '</span>' +
                            '<input type="text" data-field="cover" value="' + escHtml(item.cover) + '" placeholder="' + coverPh + '"></label></div>';
                    });
                    html += '<button type="button" class="pb-bnav-items__add"' + (items.length >= 10 ? ' disabled' : '') + '>' +
                        escHtml(lang('trait_ads_grid_add', 'Add ad')) + '</button>';
                    wrap.innerHTML = html;
                }

                wrap.addEventListener('click', function (ev) {
                    var t = ev.target;
                    if (!t || !t.getAttribute) {
                        return;
                    }
                    if (t.classList.contains('pb-bnav-items__add')) {
                        ev.preventDefault();
                        var cur = (wrap.__pbItems || readItems()).slice();
                        if (cur.length >= 10) {
                            return;
                        }
                        cur.push({ id: 'ad_' + Date.now(), label: '广告', href: '', cover: '' });
                        writeItems(cur, true);
                        return;
                    }
                    var act = t.getAttribute('data-act');
                    if (!act) {
                        return;
                    }
                    ev.preventDefault();
                    var row = t.closest ? t.closest('.pb-bnav-items__row') : null;
                    if (!row) {
                        return;
                    }
                    var idx = parseInt(row.getAttribute('data-idx'), 10);
                    var list = (wrap.__pbItems || readItems()).slice();
                    if (isNaN(idx) || idx < 0 || idx >= list.length) {
                        return;
                    }
                    if (act === 'remove') {
                        list.splice(idx, 1);
                        writeItems(list, true);
                    } else if (act === 'up' && idx > 0) {
                        var tmp = list[idx - 1];
                        list[idx - 1] = list[idx];
                        list[idx] = tmp;
                        writeItems(list, true);
                    } else if (act === 'down' && idx < list.length - 1) {
                        var tmp2 = list[idx + 1];
                        list[idx + 1] = list[idx];
                        list[idx] = tmp2;
                        writeItems(list, true);
                    }
                });

                wrap.addEventListener('change', function (ev) {
                    var t = ev.target;
                    if (!t || !t.getAttribute) {
                        return;
                    }
                    var field = t.getAttribute('data-field');
                    if (!field) {
                        return;
                    }
                    var row = t.closest ? t.closest('.pb-bnav-items__row') : null;
                    if (!row) {
                        return;
                    }
                    var idx = parseInt(row.getAttribute('data-idx'), 10);
                    var list = (wrap.__pbItems || readItems()).slice();
                    if (isNaN(idx) || !list[idx]) {
                        return;
                    }
                    list[idx] = {
                        id: list[idx].id,
                        label: field === 'label' ? t.value : list[idx].label,
                        href: field === 'href' ? t.value : list[idx].href,
                        cover: field === 'cover' ? t.value : list[idx].cover
                    };
                    writeItems(list, true);
                });

                wrap.__pbRenderFromModel = function () {
                    wrap.__pbItems = readItems();
                    render();
                };
                wrap.__pbItems = readItems();
                render();
                return wrap;
            },
            onUpdate: function (opts) {
                var elInput = opts.elInput;
                if (elInput && typeof elInput.__pbRenderFromModel === 'function') {
                    suppressSave = true;
                    elInput.__pbRenderFromModel();
                    suppressSave = false;
                }
            }
        });
    }

    function registerPbImageTrait() {
        if (!editor || !editor.TraitManager) {
            return;
        }
        editor.TraitManager.addType('pb-image', {
            createInput: function (opts) {
                var trait = opts.trait;
                var component = opts.component;
                var name = trait.get('name');
                var wrap = document.createElement('div');
                wrap.className = 'pb-trait-image';
                wrap.innerHTML =
                    '<img class="pb-trait-image__preview" alt="" hidden>' +
                    '<div class="pb-trait-image__tools">' +
                    '<button type="button" class="pb-trait-image__upload">' + escHtml(PB.lang.upload || 'Upload') + '</button>' +
                    '<button type="button" class="pb-trait-image__clear">' + escHtml(PB.lang.clear || 'Clear') + '</button>' +
                    '</div>' +
                    '<input type="text" class="pb-trait-image__path" placeholder="' + escHtml(PB.lang.image_path_ph || '') + '">';
                var preview = wrap.querySelector('.pb-trait-image__preview');
                var pathInput = wrap.querySelector('.pb-trait-image__path');
                var uploadBtn = wrap.querySelector('.pb-trait-image__upload');
                var clearBtn = wrap.querySelector('.pb-trait-image__clear');

                function applyPath(val, persist) {
                    val = String(val || '').trim();
                    pathInput.value = val;
                    if (val) {
                        preview.src = pbRootedSrc(val);
                        preview.hidden = false;
                    } else {
                        preview.removeAttribute('src');
                        preview.hidden = true;
                    }
                    if (persist) {
                        component.set(name, val);
                        if (!loading && !suppressSave) {
                            scheduleAutoSave(true);
                            updateUndoRedoButtons();
                        }
                    }
                }

                pathInput.addEventListener('change', function () {
                    applyPath(pathInput.value, true);
                });
                clearBtn.addEventListener('click', function () {
                    applyPath('', true);
                });
                bindPbUploadButton(uploadBtn, function (path) {
                    applyPath(path, true);
                });
                wrap.__pbApplyPath = applyPath;
                return wrap;
            },
            onUpdate: function (opts) {
                var trait = opts.trait;
                var component = opts.component;
                var elInput = opts.elInput;
                var name = trait.get('name');
                var val = component.get(name);
                if (val === undefined || val === null) {
                    val = '';
                }
                if (elInput && typeof elInput.__pbApplyPath === 'function') {
                    suppressSave = true;
                    elInput.__pbApplyPath(String(val), false);
                    suppressSave = false;
                }
            }
        });
    }

    var pagePickerBound = false;

    function findPagePickerLabel(route) {
        var groups = PB.pageRegistry || {};
        var keys = Object.keys(groups);
        for (var i = 0; i < keys.length; i++) {
            var list = groups[keys[i]] || [];
            for (var j = 0; j < list.length; j++) {
                if (list[j] && list[j].route === route) {
                    return list[j].label || route;
                }
            }
        }
        return route || '';
    }

    function navigatePageRoute(nextRoute) {
        if (!nextRoute || nextRoute === PB.route) {
            return;
        }
        var switching = true;
        saveDraft(true, false).always(function () {
            if (!switching) {
                return;
            }
            var base = PB.urls.pageBuilder || window.location.pathname;
            var q = '?theme=' + encodeURIComponent(PB.theme) + '&route=' + encodeURIComponent(nextRoute);
            if (PB.embed) {
                q += '&embed=1&return_step=' + encodeURIComponent(String(PB.returnStep != null ? PB.returnStep : 4));
            }
            window.location.href = base + q;
        });
    }

    function initPagePicker() {
        if (pagePickerBound) {
            return;
        }
        var $picker = $('#pb-page-picker');
        var $btn = $('#pb-page-picker-btn');
        var $panel = $('#pb-page-picker-panel');
        var $list = $('#pb-page-picker-list');
        var $label = $('#pb-page-picker-label');
        if (!$picker.length || !$btn.length || !$list.length) {
            return;
        }
        pagePickerBound = true;
        var groups = PB.pageRegistry || {};
        var html = '';
        Object.keys(groups).forEach(function (group) {
            var items = groups[group] || [];
            if (!items.length) {
                return;
            }
            html += '<div class="td-pb-page-picker__group"><div class="td-pb-page-picker__group-title">' + escHtml(group) + '</div>';
            items.forEach(function (item) {
                if (!item || !item.route) {
                    return;
                }
                var active = item.route === PB.route ? ' is-active' : '';
                html += '<button type="button" class="td-pb-page-picker__item' + active + '" data-route="' + escHtml(item.route) + '" role="option">' +
                    escHtml(item.label || item.route) + '</button>';
            });
            html += '</div>';
        });
        $list.html(html);
        $label.text(findPagePickerLabel(PB.route) || PB.routeLabel || PB.route || '');

        function closePanel() {
            $panel.prop('hidden', true);
            $btn.attr('aria-expanded', 'false');
        }

        function openPanel() {
            $panel.prop('hidden', false);
            $btn.attr('aria-expanded', 'true');
        }

        $btn.on('click', function (e) {
            e.stopPropagation();
            if ($panel.prop('hidden')) {
                openPanel();
            } else {
                closePanel();
            }
        });
        $list.on('click', '.td-pb-page-picker__item', function () {
            var route = $(this).data('route');
            closePanel();
            navigatePageRoute(route);
        });
        $(document).on('click.pbpagepicker', function (e) {
            if ($(e.target).closest('#pb-page-picker').length) {
                return;
            }
            closePanel();
        });
        $(document).on('keydown.pbpagepicker', function (e) {
            if (e.key === 'Escape') {
                closePanel();
            }
        });
    }

    function syncChromeLogoPanel(model) {
        var logo = model && model.get('logo');
        logo = typeof logo === 'string' ? logo : '';
        chromeLogoSuppress = true;
        $('#pbLogoPath').val(logo);
        showPbLogoPreview(logo);
        chromeLogoSuppress = false;
    }

    function findChromeBlockModel(compId) {
        if (!editor || !compId) {
            return null;
        }
        var found = null;
        function walk(model) {
            if (found) {
                return;
            }
            if (model.getAttributes()['data-component'] === compId) {
                found = model;
                return;
            }
            model.components().forEach(walk);
        }
        editor.getWrapper().components().forEach(function (regionModel) {
            regionModel.components().forEach(walk);
        });
        return found;
    }

    function resolveChromePanelModel(compId, selected) {
        if (selected && !selected.is('wrapper') && selected.getAttributes()['data-component'] === compId) {
            return selected;
        }
        return findChromeBlockModel(compId);
    }

    /** 保存前把 chrome 侧栏输入同步到模型，避免仅面板有值但未选中组件时落盘丢失 */
    function flushChromePanelsToModels() {
        if (!editor) {
            return;
        }
        applyChromeLogoFromPanel(true);
        applyChromeDecorFromPanel(true);
        applyChromeFooterLogoFromPanel(true);
    }

    function applyChromeLogoFromPanel(silent) {
        if (chromeLogoSuppress || !editor) {
            return;
        }
        var model = resolveChromePanelModel('site_nav', editor.getSelected());
        if (!model) {
            return;
        }
        var logo = String($('#pbLogoPath').val() || '').trim();
        model.set('logo', logo);
        if (!silent && !loading && !suppressSave) {
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        }
    }

    function syncChromeDecorPanel(model) {
        var image = model && model.get('image');
        image = typeof image === 'string' ? image : '';
        chromeDecorSuppress = true;
        $('#pbDecorPath').val(image);
        showPbDecorPreview(image);
        chromeDecorSuppress = false;
    }

    function applyChromeDecorFromPanel(silent) {
        if (chromeDecorSuppress || !editor) {
            return;
        }
        var model = resolveChromePanelModel('header_decor', editor.getSelected());
        if (!model) {
            return;
        }
        var image = String($('#pbDecorPath').val() || '').trim();
        model.set('image', image);
        if (!silent && !loading && !suppressSave) {
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        }
    }

    function syncChromeFooterLogoPanel(model) {
        var logo = model && model.get('logo');
        logo = typeof logo === 'string' ? logo : '';
        chromeFooterLogoSuppress = true;
        $('#pbFooterLogoPath').val(logo);
        showPbFooterLogoPreview(logo);
        chromeFooterLogoSuppress = false;
    }

    function applyChromeFooterLogoFromPanel(silent) {
        if (chromeFooterLogoSuppress || !editor) {
            return;
        }
        var model = resolveChromePanelModel('site_footer', editor.getSelected());
        if (!model) {
            return;
        }
        var logo = String($('#pbFooterLogoPath').val() || '').trim();
        model.set('logo', logo);
        if (!silent && !loading && !suppressSave) {
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        }
    }

    function toggleChromeFooterLogoPanel(model) {
        var $panel = $('#pb-chrome-footer-logo');
        if (!$panel.length) {
            return;
        }
        if (!model || model.is('wrapper')) {
            $panel.prop('hidden', true);
            return;
        }
        if (model.getAttributes()['data-component'] === 'site_footer') {
            $panel.prop('hidden', false);
            syncChromeFooterLogoPanel(model);
        } else {
            $panel.prop('hidden', true);
        }
    }

    function initChromeFooterLogoPanel() {
        if (chromeFooterLogoBound) {
            return;
        }
        var $path = $('#pbFooterLogoPath');
        if (!$path.length) {
            return;
        }
        chromeFooterLogoBound = true;
        $path.on('change', function () {
            applyChromeFooterLogoFromPanel();
        });
        $('#pbFooterLogoClear').on('click', function () {
            chromeFooterLogoSuppress = true;
            $path.val('');
            showPbFooterLogoPreview('');
            chromeFooterLogoSuppress = false;
            applyChromeFooterLogoFromPanel();
        });
        if (typeof layui !== 'undefined') {
            layui.use(['upload', 'layer'], function () {
                layui.upload.render({
                    elem: '#pbFooterLogoUpload',
                    url: PB.urls.upload || '',
                    method: 'post',
                    field: 'file',
                    data: { theme: PB.theme },
                    before: function () {
                        layui.layer.msg(PB.lang.uploading || 'Uploading…', { time: 30000 });
                    },
                    done: function (res) {
                        layui.layer.closeAll();
                        if (res && res.code === 1 && res.file) {
                            chromeFooterLogoSuppress = true;
                            $path.val(res.file);
                            showPbFooterLogoPreview(res.file);
                            chromeFooterLogoSuppress = false;
                            applyChromeFooterLogoFromPanel();
                            layui.layer.msg(PB.lang.upload_ok || 'OK', { icon: 1 });
                        } else {
                            layui.layer.msg((res && res.msg) ? res.msg : (PB.lang.upload_err || 'Failed'), { icon: 2 });
                        }
                    },
                    error: function () {
                        layui.layer.closeAll();
                        layui.layer.msg(PB.lang.upload_err || 'Failed', { icon: 2 });
                    }
                });
            });
        }
    }

    function toggleChromeDecorPanel(model) {
        var $panel = $('#pb-chrome-decor');
        if (!$panel.length) {
            return;
        }
        if (!model || model.is('wrapper')) {
            $panel.prop('hidden', true);
            return;
        }
        if (model.getAttributes()['data-component'] === 'header_decor') {
            $panel.prop('hidden', false);
            syncChromeDecorPanel(model);
            // 装饰背景图只在首页（owns hero）落盘；其它页仅展示，避免上传后保存丢失
            var canEditDecor = pageOwnsHeroRegion();
            $('#pbDecorUpload, #pbDecorClear').prop('disabled', !canEditDecor);
            $('#pbDecorPath').prop('readonly', !canEditDecor);
            var $hint = $panel.find('.pb-chrome-decor-hint');
            if (!canEditDecor) {
                if (!$hint.length) {
                    $hint = $('<p class="pb-chrome-decor-hint" style="margin:8px 0 0;font-size:12px;color:#9098a3;"></p>');
                    $panel.append($hint);
                }
                $hint.text(traitLabel('pb_decor_edit_on_home', 'Edit header decor image on the homepage.'));
            } else if ($hint.length) {
                $hint.remove();
            }
        } else {
            $panel.prop('hidden', true);
        }
    }

    function initChromeDecorPanel() {
        if (chromeDecorBound) {
            return;
        }
        var $path = $('#pbDecorPath');
        if (!$path.length) {
            return;
        }
        chromeDecorBound = true;
        $path.on('change', function () {
            applyChromeDecorFromPanel();
        });
        $('#pbDecorClear').on('click', function () {
            chromeDecorSuppress = true;
            $path.val('');
            showPbDecorPreview('');
            chromeDecorSuppress = false;
            applyChromeDecorFromPanel();
        });
        if (typeof layui !== 'undefined') {
            layui.use(['upload', 'layer'], function () {
                layui.upload.render({
                    elem: '#pbDecorUpload',
                    url: PB.urls.upload || '',
                    method: 'post',
                    field: 'file',
                    data: { theme: PB.theme },
                    before: function () {
                        layui.layer.msg(PB.lang.uploading || 'Uploading…', { time: 30000 });
                    },
                    done: function (res) {
                        layui.layer.closeAll();
                        if (!pageOwnsHeroRegion()) {
                            layui.layer.msg(traitLabel('pb_decor_edit_on_home', 'Edit header decor image on the homepage.'), { icon: 0 });
                            return;
                        }
                        if (res && res.code === 1 && res.file) {
                            chromeDecorSuppress = true;
                            $path.val(res.file);
                            showPbDecorPreview(res.file);
                            chromeDecorSuppress = false;
                            applyChromeDecorFromPanel();
                            layui.layer.msg(PB.lang.upload_ok || 'OK', { icon: 1 });
                        } else {
                            layui.layer.msg((res && res.msg) ? res.msg : (PB.lang.upload_err || 'Failed'), { icon: 2 });
                        }
                    },
                    error: function () {
                        layui.layer.closeAll();
                        layui.layer.msg(PB.lang.upload_err || 'Failed', { icon: 2 });
                    }
                });
            });
        }
    }

    function toggleChromeLogoPanel(model) {
        var $panel = $('#pb-chrome-logo');
        if (!$panel.length) {
            return;
        }
        if (!model || model.is('wrapper')) {
            $panel.prop('hidden', true);
            return;
        }
        if (model.getAttributes()['data-component'] === 'site_nav') {
            $panel.prop('hidden', false);
            syncChromeLogoPanel(model);
        } else {
            $panel.prop('hidden', true);
        }
    }

    function initChromeLogoPanel() {
        if (chromeLogoBound) {
            return;
        }
        var $path = $('#pbLogoPath');
        if (!$path.length) {
            return;
        }
        chromeLogoBound = true;
        $path.on('change', function () {
            applyChromeLogoFromPanel();
        });
        $('#pbLogoClear').on('click', function () {
            chromeLogoSuppress = true;
            $path.val('');
            showPbLogoPreview('');
            chromeLogoSuppress = false;
            applyChromeLogoFromPanel();
        });
        if (typeof layui !== 'undefined') {
            layui.use(['upload', 'layer'], function () {
                layui.upload.render({
                    elem: '#pbLogoUpload',
                    url: PB.urls.upload || '',
                    method: 'post',
                    field: 'file',
                    data: { theme: PB.theme },
                    before: function () {
                        layui.layer.msg(PB.lang.uploading || 'Uploading…', { time: 30000 });
                    },
                    done: function (res) {
                        layui.layer.closeAll();
                        if (res && res.code === 1 && res.file) {
                            chromeLogoSuppress = true;
                            $path.val(res.file);
                            showPbLogoPreview(res.file);
                            chromeLogoSuppress = false;
                            applyChromeLogoFromPanel();
                            layui.layer.msg(PB.lang.upload_ok || 'OK', { icon: 1 });
                        } else {
                            layui.layer.msg((res && res.msg) ? res.msg : (PB.lang.upload_err || 'Failed'), { icon: 2 });
                        }
                    },
                    error: function () {
                        layui.layer.closeAll();
                        layui.layer.msg(PB.lang.upload_err || 'Failed', { icon: 2 });
                    }
                });
            });
        }
    }

    function readChromeCatsChecked() {
        var ids = [];
        $('#pb-chrome-cats-list input[type="checkbox"]:checked').each(function () {
            var id = parseInt($(this).val(), 10);
            if (!isNaN(id) && id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function syncChromeCatsCheckboxes(ids) {
        ids = Array.isArray(ids) ? ids : [];
        var set = {};
        ids.forEach(function (id) { set[String(id)] = 1; });
        chromeCatsSuppress = true;
        $('#pb-chrome-cats-list input[type="checkbox"]').each(function () {
            this.checked = !!set[String($(this).val())];
        });
        chromeCatsSuppress = false;
    }

    function applyChromeCatsFromPanel() {
        if (chromeCatsSuppress || !editor) {
            return;
        }
        var sel = editor.getSelected();
        if (!sel || sel.is('wrapper')) {
            return;
        }
        var comp = sel.getAttributes()['data-component'];
        if (comp !== 'site_nav' && comp !== 'channel_strip') {
            return;
        }
        var ids = readChromeCatsChecked();
        sel.set('type_ids', ids);
        syncChromeTypeIds(sel, ids);
        if (!loading && !suppressSave) {
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        }
    }

    function toggleChromeCatsPanel(model) {
        var $panel = $('#pb-chrome-cats');
        if (!$panel.length) {
            return;
        }
        if (!model || model.is('wrapper')) {
            $panel.prop('hidden', true);
            return;
        }
        var comp = model.getAttributes()['data-component'];
        if (comp === 'site_nav' || comp === 'channel_strip') {
            $panel.prop('hidden', false);
            var ids = model.get('type_ids');
            if (!Array.isArray(ids)) {
                ids = [];
            }
            syncChromeCatsCheckboxes(ids);
        } else {
            $panel.prop('hidden', true);
        }
    }

    function buildChromeCatsTreeHtml() {
        var tree = PB.catTree || [];
        if (!tree.length) {
            return '';
        }
        var html = '';
        tree.forEach(function (p) {
            var pid = String(p.id);
            html += '<div class="pb-chrome-cats__group">';
            html += '<label class="pb-chrome-cats__item pb-chrome-cats__item--parent" data-is-top="1" data-pid="0">'
                + '<input type="checkbox" value="' + escHtml(pid) + '">'
                + '<span class="pb-chrome-cats__name">' + escHtml(String(p.name || p.id)) + '</span></label>';
            if (p.children && p.children.length) {
                html += '<div class="pb-chrome-cats__children">';
                p.children.forEach(function (c) {
                    html += '<label class="pb-chrome-cats__item pb-chrome-cats__item--child" data-is-top="0" data-pid="' + escHtml(pid) + '">'
                        + '<input type="checkbox" value="' + escHtml(String(c.id)) + '">'
                        + '<span class="pb-chrome-cats__name">'
                        + '<span class="pb-chrome-cats__branch" aria-hidden="true">└</span>'
                        + escHtml(String(c.name || c.id)) + '</span></label>';
                });
                html += '</div>';
            }
            html += '</div>';
        });
        return html;
    }

    function initChromeCatsPanel() {
        if (chromeCatsBound) {
            return;
        }
        var $list = $('#pb-chrome-cats-list');
        if (!$list.length) {
            return;
        }
        chromeCatsBound = true;
        var treeHtml = buildChromeCatsTreeHtml();
        if (!treeHtml) {
            $list.html('<div class="pb-chrome-cats__empty">' + escHtml(traitLabel('trait_nav_categories_empty', 'No categories available')) + '</div>');
        } else {
            $list.html(treeHtml);
        }
        $list.on('mousedown click', 'input[type="checkbox"], label', function (e) {
            e.stopPropagation();
        });
        $list.on('change', 'input[type="checkbox"]', function () {
            applyChromeCatsFromPanel();
        });
        $('#pbCatsAll').on('click', function () {
            $('#pb-chrome-cats-list input[type="checkbox"]').prop('checked', true);
            applyChromeCatsFromPanel();
        });
        $('#pbCatsNone').on('click', function () {
            $('#pb-chrome-cats-list input[type="checkbox"]').prop('checked', false);
            applyChromeCatsFromPanel();
        });
        $('#pbCatsTops').on('click', function () {
            $('#pb-chrome-cats-list .pb-chrome-cats__item--parent input[type="checkbox"]').prop('checked', true);
            $('#pb-chrome-cats-list .pb-chrome-cats__item--child input[type="checkbox"]').prop('checked', false);
            applyChromeCatsFromPanel();
        });
    }

    function clampColWidth(n, fallback) {
        n = parseInt(n, 10);
        if (isNaN(n) || n < 1) {
            return fallback;
        }
        if (n > 12) {
            return 12;
        }
        return n;
    }

    function columnsWidthsFromProps(props) {
        props = props || {};
        if (Array.isArray(props.cols) && props.cols.length >= 2) {
            return [clampColWidth(props.cols[0], 8), clampColWidth(props.cols[1], 4)];
        }
        return [
            clampColWidth(props.col_left, 8),
            clampColWidth(props.col_right, 4)
        ];
    }

    function applyColumnsTraitDefaults(model, props) {
        if (!model) {
            return;
        }
        var widths = columnsWidthsFromProps(props);
        model.set('col_left', widths[0]);
        model.set('col_right', widths[1]);
        var stackAt = props && props.stack_at !== undefined && props.stack_at !== null && props.stack_at !== ''
            ? parseInt(props.stack_at, 10)
            : parseInt(model.get('stack_at'), 10);
        if (isNaN(stackAt) || stackAt < 320) {
            stackAt = 720;
        }
        if (stackAt > 1600) {
            stackAt = 1600;
        }
        model.set('stack_at', stackAt);
    }

    function pushCoverTraits(t) {
        t.push({ type: 'select', name: 'card', label: traitLabel('trait_cover_orient', 'Cover orientation'), changeProp: 1, options: CARD_OPTS });
        t.push({ type: 'select', name: 'ratio', label: traitLabel('trait_cover_ratio', 'Cover ratio'), changeProp: 1, options: RATIO_OPTS });
        t.push({ type: 'number', name: 'ratio_w', label: traitLabel('trait_ratio_w', 'Cover width'), changeProp: 1, min: 1, max: 999 });
        t.push({ type: 'number', name: 'ratio_h', label: traitLabel('trait_ratio_h', 'Cover height'), changeProp: 1, min: 1, max: 999 });
    }

    function pushSectionHeaderTraits(t) {
        t.push({ type: 'select', name: 'title_mode', label: traitLabel('trait_title_mode', 'Title mode'), changeProp: 1, options: [
            { value: 'custom', name: traitLabel('trait_title_mode_custom', 'Custom title') },
            { value: 'type_name', name: traitLabel('trait_title_mode_type', 'Category name') }
        ] });
        t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
        t.push({ type: 'select', name: 'icon', label: traitLabel('trait_icon', 'Icon'), changeProp: 1, options: ICON_OPTS || [] });
        t.push({ type: 'select', name: 'variant', label: traitLabel('trait_variant', 'Variant'), changeProp: 1, options: VARIANT_OPTS || [] });
    }

    /** section 壳已隐藏：标题/图标编辑入口合并到列表组件 traits 顶部 */
    function prependSectionHeaderTraits(traits, comp) {
        if (!listUsesSectionHeader(comp) || !traits) {
            return traits || [];
        }
        var headerTraits = [];
        pushSectionHeaderTraits(headerTraits);
        var have = {};
        traits.forEach(function (x) {
            if (x && x.name) {
                have[x.name] = 1;
            }
        });
        headerTraits = headerTraits.filter(function (x) {
            return x && x.name && !have[x.name];
        });
        return headerTraits.concat(traits);
    }

    function traitsFor(comp, model) {
        if (CATALOG_TRAIT_IDS.indexOf(comp) >= 0) {
            var catalogTraits = traitsFromCatalog(comp, model);
            if (catalogTraits && catalogTraits.length) {
                // catalog 早退曾漏掉 section→list 合并后的 title/icon/variant 入口
                return prependSectionHeaderTraits(catalogTraits, comp);
            }
            return prependSectionHeaderTraits([], comp);
        }
        var t = [];
        if (comp === 'section') {
            pushSectionHeaderTraits(t);
        }
        if (comp === 'columns') {
            t.push({ type: 'number', name: 'col_left', label: traitLabel('trait_col_left', 'Left column width'), changeProp: 1, min: 1, max: 12 });
            t.push({ type: 'number', name: 'col_right', label: traitLabel('trait_col_right', 'Right column width'), changeProp: 1, min: 1, max: 12 });
            t.push({ type: 'number', name: 'stack_at', label: traitLabel('trait_stack_at', 'Stack below (px)'), changeProp: 1, min: 320, max: 1600 });
        }
        if (comp === 'content_feed') {
            t.push({ type: 'select', name: 'bind', label: traitLabel('trait_bind', 'Content type'), changeProp: 1, options: BIND_OPTS });
            var cfBind = contentFeedBind(model);
            if (cfBind === 'gbook') {
                t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'List title'), changeProp: 1 });
                t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
                pushListPagingTraits(t, model, 'content_feed', false);
            } else {
                if (listBindUsesLayout(cfBind)) {
                    t.push({ type: 'select', name: 'render', label: traitLabel('trait_layout', 'Layout'), changeProp: 1, options: listLayoutOpts(model) });
                }
                if (listBindUsesCover(cfBind)) {
                    pushCoverTraits(t);
                }
                t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
                if (listBindUsesCategory(cfBind)) {
                    t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(bindMid(cfBind)) });
                }
                if (listBindUsesSort(cfBind)) {
                    t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
                }
                pushListPagingTraits(t, model, 'content_feed', listBindUsesRelation(cfBind));
                if (!isPagingEnabled(model) && !(model && model.get('relation'))) {
                    t.push({ type: 'checkbox', name: 'refresh', label: traitLabel('trait_refresh', '换一换'), changeProp: 1 });
                }
            }
        }
        if (comp === 'rank_feed') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'select', name: 'style', label: traitLabel('trait_rank_style', 'Rank style'), changeProp: 1, options: RANK_STYLE_OPTS });
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(1) });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            t.push({ type: 'select', name: 'period', label: traitLabel('trait_period', 'Period'), changeProp: 1, options: PERIOD_OPTS });
        }
        var CAROUSEL_LAYOUT_COMPS = ['vod_carousel', 'carousel-fullbleed', 'carousel-compact', 'manga_home_spotlight', 'manga_mosaic_hero', 'novel_home_banner'];
        if (CAROUSEL_LAYOUT_COMPS.indexOf(comp) >= 0) {
            t.push({ type: 'select', name: 'bind', label: traitLabel('trait_bind', 'Data source'), changeProp: 1, options: CAROUSEL_BIND_OPTS || BIND_OPTS });
            var defBind = (comp.indexOf('manga_') === 0) ? 'manga' : (comp.indexOf('novel_') === 0 ? 'art' : 'vod');
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(bindMid(defBind)) });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            if (comp === 'manga_home_spotlight') {
                t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
                t.push({ type: 'text', name: 'subtitle', label: traitLabel('trait_subtitle', 'Subtitle'), changeProp: 1 });
            }
        }
        if (comp === 'vod_list' || comp === 'art_list' || comp === 'manga_list' || comp === 'website_list') {
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(comp === 'website_list' ? 1 : (comp === 'art_list' ? 2 : (comp === 'manga_list' ? 12 : 1))) });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
        }
        if (comp === 'vod_list' || comp === 'art_list' || comp === 'manga_list') {
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
        }
        if (comp === 'vod_list' || comp === 'art_list' || comp === 'manga_list') {
            t.push({ type: 'select', name: 'render', label: traitLabel('trait_layout', 'Layout'), changeProp: 1, options: listLayoutOpts(model) });
        }
        if (comp === 'vod_list') {
            pushListPagingTraits(t, model, 'vod_list', true);
        }
        if (comp === 'vod_list_np2') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'select', name: 'title_mode', label: traitLabel('trait_title_mode', 'Title mode'), changeProp: 1, options: [
                { value: 'custom', name: traitLabel('trait_title_mode_custom', 'Custom title') },
                { value: 'type_name', name: traitLabel('trait_title_mode_type', 'Category name') }
            ] });
            t.push({ type: 'select', name: 'icon', label: traitLabel('trait_icon', 'Icon'), changeProp: 1, options: NP2_ICON_OPTS || [
                { value: '', name: traitLabel('trait_icon_none', 'None') },
                { value: 'auto', name: traitLabel('trait_icon_auto', 'Auto') },
                { value: 'spark', name: 'Spark' },
                { value: 'film', name: 'Film' },
                { value: 'heart', name: 'Heart' },
                { value: 'clapper', name: 'Clapper' },
                { value: 'fire', name: 'Fire' },
                { value: 'tv', name: 'TV' },
                { value: 'star', name: 'Star' }
            ] });
            t.push({ type: 'select', name: 'layout', label: traitLabel('trait_layout', 'Layout'), changeProp: 1, options: [
                { value: 'grid2', name: traitLabel('pb_np2_layout_grid2', '2-column grid') },
                { value: 'hscroll', name: traitLabel('pb_np2_layout_hscroll', 'Horizontal scroll') },
                { value: 'cols3', name: traitLabel('pb_np2_layout_cols3', '3-column cards') }
            ] });
            t.push({ type: 'select', name: 'type_pick', label: traitLabel('trait_type_pick', 'Data source'), changeProp: 1, options: [
                { value: 'all', name: traitLabel('trait_type_all', 'All videos') },
                { value: 'category', name: traitLabel('trait_category', 'Category') },
                { value: 'top_types', name: traitLabel('trait_top_types_nth', 'Top category by index') }
            ] });
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(1) });
            t.push({ type: 'number', name: 'top_types_index', label: traitLabel('trait_top_types_index', 'Category index'), changeProp: 1, min: 1, max: 24 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 24 });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            t.push({ type: 'checkbox', name: 'show_shuffle', label: traitLabel('trait_show_shuffle', 'Shuffle button'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_more', label: traitLabel('trait_show_more', 'More link'), changeProp: 1 });
        }
        if (comp === 'art_list' || comp === 'manga_list') {
            pushListPagingTraits(t, model, comp, false);
        }
        if (COVER_LIST_COMPS.indexOf(comp) >= 0 && comp !== 'content_feed') {
            pushCoverTraits(t);
        }
        if (comp === 'live_list' || comp === 'topic_list') {
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
        }
        if (comp === 'topic_list') {
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            pushListPagingTraits(t, model, 'topic_list', false);
        }
        if (comp === 'live_list') {
            pushListPagingTraits(t, model, 'live_list', false);
        }
        if (comp === 'website_list') {
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            pushListPagingTraits(t, model, 'website_list', false);
        }
        if (comp === 'actor_list') {
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(8) });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            pushListPagingTraits(t, model, 'actor_list', false);
        }
        if (comp === 'role_list') {
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: SORT_OPTS });
            pushListPagingTraits(t, model, 'role_list', false);
        }
        if (comp === 'gbook_list') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'List title'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            pushListPagingTraits(t, model, 'gbook_list', false);
        }
        if (comp === 'gbook_form') {
            t.push({ type: 'checkbox', name: 'show_hint', label: traitLabel('trait_show_hint', 'Show hint'), changeProp: 1 });
        }
        if (comp === 'rank_list' || comp === 'rank_inline' || comp === 'rank_card') {
            if (comp === 'rank_card' || comp === 'rank_list') {
                t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            }
            t.push({ type: 'select', name: 'type', label: traitLabel('trait_category', 'Category'), changeProp: 1, options: catTraitOptions(1) });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 1, max: 50 });
            if (comp === 'rank_inline' || comp === 'rank_list') {
                t.push({ type: 'select', name: 'period', label: traitLabel('trait_period', 'Period'), changeProp: 1, options: PERIOD_OPTS });
            }
        }
        if (comp === 'site_nav') {
            t.push({ type: 'number', name: 'max', label: traitLabel('trait_nav_max', 'Nav items'), changeProp: 1, min: 1, max: 20 });
            t.push({ type: 'checkbox', name: 'show_search', label: traitLabel('trait_nav_search', 'Search box'), changeProp: 1 });
        }
        if (comp === 'channel_strip') {
            t.push({ type: 'checkbox', name: 'compact', label: traitLabel('trait_channel_compact', 'Compact'), changeProp: 1 });
            t.push({ type: 'number', name: 'chip_limit', label: traitLabel('trait_channel_chips', 'Category chips'), changeProp: 1, min: 3, max: 20 });
            t.push({ type: 'checkbox', name: 'show_icons', label: traitLabel('trait_channel_icons', 'Icon row'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_more', label: traitLabel('trait_channel_more', 'More links'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_live', label: traitLabel('trait_channel_live', 'Live link'), changeProp: 1 });
        }
        if (comp === 'header_decor') {
            t.push({ type: 'text', name: 'width', label: traitLabel('trait_decor_width', 'Width'), changeProp: 1 });
            t.push({ type: 'number', name: 'height', label: traitLabel('trait_decor_height', 'Height (px)'), changeProp: 1, min: 0, max: 800 });
        }
        if (comp === 'site_footer') {
            t.push({ type: 'select', name: 'variant', label: traitLabel('trait_footer_variant', 'Layout'), changeProp: 1, options: FOOTER_VARIANT_OPTS });
            t.push({ type: 'text', name: 'copyright', label: traitLabel('trait_footer_copyright', 'Copyright'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_nav', label: traitLabel('trait_footer_show_nav', 'Nav columns'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_friends', label: traitLabel('trait_footer_show_friends', 'Friend links'), changeProp: 1 });
        }
        if (comp === 'sec_heading') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'unit', label: traitLabel('trait_unit', 'Count unit'), changeProp: 1 });
        }
        if (comp === 'breadcrumb') {
            t.push({ type: 'checkbox', name: 'auto', label: traitLabel('trait_breadcrumb_auto', 'Auto from page'), changeProp: 1 });
            t.push({ type: 'text', name: 'sep', label: traitLabel('trait_breadcrumb_sep', 'Separator'), changeProp: 1 });
        }
        if (comp === 'spacer') {
            t.push({ type: 'select', name: 'size', label: traitLabel('trait_spacer_size', 'Spacing'), changeProp: 1, options: [
                { value: 'sm', name: traitLabel('trait_spacer_sm', 'Small (12px)') },
                { value: 'md', name: traitLabel('trait_spacer_md', 'Medium (24px)') },
                { value: 'lg', name: traitLabel('trait_spacer_lg', 'Large (48px)') }
            ] });
        }
        if (comp === 'filter_chips') {
            t.push({ type: 'select', name: 'flag', label: traitLabel('trait_filter_flag', 'URL flag'), changeProp: 1, options: [
                { value: 'show', name: 'show' },
                { value: 'type', name: 'type' }
            ] });
        }
        if (comp === 'filter_panel') {
            t.push({ type: 'select', name: 'preset', label: traitLabel('trait_filter_preset', 'Preset'), changeProp: 1, options: [
                { value: 'vod_show', name: 'VOD show filters' },
                { value: 'vod_type', name: 'VOD subtype chips' }
            ] });
        }
        if (comp === 'empty_state') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'hint', label: traitLabel('trait_hint', 'Hint'), changeProp: 1 });
            t.push({ type: 'select', name: 'preset', label: traitLabel('trait_empty_preset', 'Preset'), changeProp: 1, options: [
                { value: '', name: traitLabel('trait_empty_preset_none', 'Default') },
                { value: 'filter', name: traitLabel('trait_empty_preset_filter', 'Filter empty') }
            ] });
        }
        if (comp === 'vod_detail') {
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
        }
        if (comp === 'art_detail') {
            t.push({ type: 'checkbox', name: 'show_toc', label: traitLabel('trait_show_toc', 'Show TOC'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
        }
        if (comp === 'manga_detail') {
            t.push({ type: 'checkbox', name: 'show_chapters', label: traitLabel('trait_show_chapters', 'Show chapters'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
        }
        if (comp === 'topic_detail') {
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 48 });
            t.push({ type: 'text', name: 'vod_title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
        }
        if (comp === 'actor_detail') {
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 48 });
            t.push({ type: 'text', name: 'vod_title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
        }
        if (comp === 'website_detail') {
            t.push({ type: 'checkbox', name: 'show_intro', label: traitLabel('trait_show_intro', 'Show synopsis'), changeProp: 1 });
        }
        if (comp === 'manga_genre_nav') {
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 24 });
        }
        if (comp === 'manga_home_row') {
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_sort', 'Sort'), changeProp: 1, options: [
                { value: 'hits', name: traitLabel('trait_sort_hits', 'Hits') },
                { value: 'hits_week', name: traitLabel('trait_sort_hits_week', 'Hits week') },
                { value: 'hits_month', name: traitLabel('trait_sort_hits_month', 'Hits month') },
                { value: 'time', name: traitLabel('trait_sort_time', 'Time') }
            ] });
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'subtitle', label: traitLabel('trait_subtitle', 'Subtitle'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 20 });
        }
        if (comp === 'manga_home_rank') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 3, max: 12 });
        }
        if (comp === 'novel_genre_nav') {
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 24 });
        }
        if (comp === 'novel_home_rank') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 12 });
        }
        if (comp === 'novel_home_grid') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'subtitle', label: traitLabel('trait_subtitle', 'Subtitle'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 18 });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_by', 'Sort by'), changeProp: 1, options: [
                { value: 'hits_month', name: traitLabel('trait_by_hits', 'Hits month') },
                { value: 'hits', name: traitLabel('trait_by_hits', 'Hits') },
                { value: 'time', name: traitLabel('trait_by_time', 'Time') }
            ] });
        }
        if (comp === 'novel_home_row') {
            t.push({ type: 'select', name: 'preset', label: traitLabel('trait_preset', 'Preset'), changeProp: 1, options: [
                { value: 'hot', name: traitLabel('pb_novel_row_hot', 'Hot') },
                { value: 'new', name: traitLabel('pb_novel_row_new', 'New') },
                { value: 'finish', name: traitLabel('pb_novel_row_finish', 'Finished') }
            ] });
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'subtitle', label: traitLabel('trait_subtitle', 'Subtitle'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 20 });
        }
        if (comp === 'novel_home_zone') {
            t.push({ type: 'number', name: 'type_num', label: traitLabel('trait_type_count', 'Type count'), changeProp: 1, min: 1, max: 8 });
            t.push({ type: 'number', name: 'row_num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 20 });
        }
        if (comp === 'novel_player') {
            t.push({ type: 'checkbox', name: 'show_settings', label: traitLabel('trait_show_settings', 'Show settings'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_share', label: traitLabel('trait_show_share', 'Show share'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_catalog_btn', label: traitLabel('trait_show_catalog_btn', 'Show catalog button'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_fav', label: traitLabel('trait_show_fav', 'Show favorite'), changeProp: 1 });
        }
        if (comp === 'novel_chapter_catalog') {
            t.push({ type: 'checkbox', name: 'show_chapter_no', label: traitLabel('trait_show_chapter_no', 'Show chapter number'), changeProp: 1 });
        }
        if (comp === 'media_related_panel') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'text', name: 'title_lang', label: traitLabel('trait_title_lang', 'Title lang key'), changeProp: 1 });
            t.push({ type: 'number', name: 'num', label: traitLabel('trait_count', 'Count'), changeProp: 1, min: 4, max: 24 });
            t.push({ type: 'checkbox', name: 'relation', label: traitLabel('trait_relation', 'Related only'), changeProp: 1 });
            t.push({ type: 'select', name: 'bind', label: traitLabel('pb_trait_bind', 'Content type'), changeProp: 1, options: [
                { value: 'vod', name: traitLabel('pb_trait_bind_vod', 'VOD') },
                { value: 'art', name: traitLabel('pb_trait_bind_art', 'Article') },
                { value: 'manga', name: traitLabel('pb_trait_bind_manga', 'Manga') }
            ] });
            t.push({ type: 'select', name: 'layout', label: traitLabel('pb_trait_layout', 'Layout'), changeProp: 1, options: [
                { value: 'grid', name: traitLabel('trait_layout_grid', 'Grid') },
                { value: 'rows', name: traitLabel('trait_layout_rows', 'Rows') }
            ] });
            t.push({ type: 'select', name: 'context', label: traitLabel('trait_context', 'Context'), changeProp: 1, options: [
                { value: 'detail', name: traitLabel('trait_context_detail', 'Detail page') },
                { value: 'play', name: traitLabel('trait_context_play', 'Play page') }
            ] });
            t.push({ type: 'select', name: 'by', label: traitLabel('trait_by', 'Sort by'), changeProp: 1, options: [
                { value: 'hits', name: traitLabel('trait_by_hits', 'Hits') },
                { value: 'time', name: traitLabel('trait_by_time', 'Time') },
                { value: 'score', name: traitLabel('trait_by_score', 'Score') }
            ] });
            t.push({ type: 'select', name: 'card', label: traitLabel('trait_card', 'Card'), changeProp: 1, options: [
                { value: 'poster-h', name: traitLabel('trait_card_poster_h', 'Poster H') },
                { value: 'poster-v', name: traitLabel('trait_card_poster_v', 'Poster V') }
            ] });
        }
        if (comp === 'member_shell') {
            t.push({ type: 'text', name: 'title', label: traitLabel('trait_title', 'Title'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_hub', label: traitLabel('trait_show_hub', 'Show hub menu'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'require_login', label: traitLabel('trait_require_login', 'Require login'), changeProp: 1 });
            t.push({ type: 'text', name: 'active', label: traitLabel('trait_member_active', 'Active nav key'), changeProp: 1 });
        }
        if (comp === 'plays_list') {
            t.push({ type: 'checkbox', name: 'show_clear', label: traitLabel('trait_show_clear', 'Show clear button'), changeProp: 1 });
        }
        if (comp === 'pay_shell') {
            t.push({ type: 'select', name: 'default_tab', label: traitLabel('trait_default_tab', 'Default tab'), changeProp: 1, options: [
                { value: 'recharge', name: traitLabel('trait_tab_recharge', 'Recharge') },
                { value: 'upgrade', name: traitLabel('trait_tab_upgrade', 'VIP upgrade') }
            ] });
            t.push({ type: 'select', name: 'variant', label: traitLabel('trait_member_visual', 'Visual style'), changeProp: 1, options: MEMBER_VISUAL_VARIANT_OPTS });
        }
        if (comp === 'benefits_panel') {
            t.push({ type: 'select', name: 'variant', label: traitLabel('trait_member_visual', 'Visual style'), changeProp: 1, options: MEMBER_VISUAL_VARIANT_OPTS });
        }
        if (comp === 'auth_page') {
            var authRoute = String(PB.route || '');
            var panelOpts = [];
            if (authRoute === 'user/login') {
                panelOpts.push({ value: 'login', name: traitLabel('trait_auth_panel_login', 'Login') });
            } else if (authRoute === 'user/findpass') {
                panelOpts.push({ value: 'findpass', name: traitLabel('trait_auth_panel_findpass', 'Find password') });
            } else {
                panelOpts.push({ value: 'login', name: traitLabel('trait_auth_panel_login', 'Login') });
                panelOpts.push({ value: 'findpass', name: traitLabel('trait_auth_panel_findpass', 'Find password') });
            }
            t.push({ type: 'select', name: 'panel', label: traitLabel('trait_auth_panel', 'Panel'), changeProp: 1, options: panelOpts });
            t.push({ type: 'checkbox', name: 'expand_invite', label: traitLabel('trait_expand_invite', 'Expand invite'), changeProp: 1 });
            t.push({ type: 'text', name: 'hero_title', label: traitLabel('trait_hero_title', 'Hero title'), changeProp: 1 });
            t.push({ type: 'text', name: 'hero_desc', label: traitLabel('trait_hero_desc', 'Hero description'), changeProp: 1 });
            t.push({ type: 'pb-image', name: 'hero_image', label: traitLabel('trait_hero_image', 'Hero image'), changeProp: 1 });
            t.push({ type: 'pb-image', name: 'hero_image_mobile', label: traitLabel('trait_hero_image_mobile', 'Mobile hero image'), changeProp: 1 });
            t.push({ type: 'checkbox', name: 'show_hero', label: traitLabel('trait_show_hero', 'Show hero'), changeProp: 1 });
            t.push({ type: 'select', name: 'layout', label: traitLabel('trait_auth_layout', 'Layout'), changeProp: 1, options: [
                { value: 'split', name: traitLabel('trait_auth_layout_split', 'Split') },
                { value: 'center', name: traitLabel('trait_auth_layout_center', 'Center') },
                { value: 'minimal', name: traitLabel('trait_auth_layout_minimal', 'Minimal') }
            ] });
        }
        return prependSectionHeaderTraits(t, comp);
    }

    function traitNames(comp) {
        return traitsFor(comp).map(function (x) { return x.name; });
    }

    /**
     * 写入 JSON 的 props。
     * 列表若包在 section 内：title/icon/variant 只存 section，避免双份。
     * 独立摆放（如排行榜页的 rank_list）：title 仍写在组件自身。
     */
    function persistPropNames(comp, model) {
        var names = modelPropNames(comp);
        if (!listUsesSectionHeader(comp)) {
            return names;
        }
        if (model && getParentSection(model)) {
            return names.filter(function (k) {
                return SECTION_HEADER_KEYS.indexOf(k) < 0;
            });
        }
        return names;
    }

    function blockLabel(block) {
        var comp = block.component || '';
        var p = block.props || {};
        if (p.title) {
            return p.title;
        }
        return LABELS[comp] || comp;
    }

    function blockToContent(block) {
        var content = {
            type: 'mcms-block',
            attributes: {
                class: 'mcms-pb-item',
                'data-component': block.component
            }
        };
        var children = [];
        if (block.slots && typeof block.slots === 'object') {
            Object.keys(block.slots).forEach(function (sk) {
                var list = block.slots[sk];
                if (!Array.isArray(list)) {
                    return;
                }
                if (list.length === 1 && Array.isArray(list[0]) && !(list[0].component)) {
                    list = list[0];
                }
                list.forEach(function (child) {
                    if (!child || !child.component) {
                        return;
                    }
                    var c = blockToContent(child);
                    c.attributes['data-slot'] = String(sk);
                    children.push(c);
                });
            });
        }
        if (children.length) {
            content.components = children;
        }
        return content;
    }

    function hydrateModel(model, block, path) {
        if (!model || !block) {
            return;
        }
        var prev = model.getAttributes() || {};
        var comp = block.component || prev['data-component'] || '';
        var attrs = { 'data-component': comp, class: 'mcms-pb-item' };
        if (path) {
            attrs['data-pb-path'] = path;
        } else if (prev['data-pb-path']) {
            attrs['data-pb-path'] = prev['data-pb-path'];
        }
        if (prev['data-slot']) {
            attrs['data-slot'] = prev['data-slot'];
        }
        model.setAttributes(attrs);
        model.set('traits', traitsFor(comp, model));
        var props = block.props || {};
        persistPropNames(comp).forEach(function (k) {
            if (props[k] !== undefined && props[k] !== null) {
                model.set(k, props[k], { silent: true });
            }
        });
        applyCatalogPropDefaults(comp, model, props);
        if (comp === 'bottom_nav') {
            model.set('items', normalizeBottomNavItems(props), { silent: true });
        }
        if (comp === 'ads_grid') {
            model.set('items', normalizeAdsGridItems(props), { silent: true });
        }
        // section 标题/图标必须落到模型上（含空字符串，表示用户清空）
        if (comp === 'section') {
            SECTION_HEADER_KEYS.forEach(function (k) {
                if (props[k] !== undefined && props[k] !== null) {
                    model.set(k, props[k], { silent: true });
                }
            });
            if (props.title_key) {
                model.set('title_key', props.title_key, { silent: true });
            }
        }
        if (comp === 'columns') {
            applyColumnsTraitDefaults(model, props);
        }
        if (comp === 'header_decor') {
            var dh = model.get('height');
            if (dh === '' || dh === null || dh === undefined || isNaN(parseInt(dh, 10))) {
                model.set('height', 120);
            }
        }
        var pagingTraitComps = ['vod_list', 'content_feed', 'art_list', 'manga_list', 'topic_list', 'live_list', 'website_list', 'actor_list', 'role_list', 'gbook_list'];
        if (pagingTraitComps.indexOf(comp) >= 0 && isPagingEnabled(model)) {
            var pageurlVal = String(model.get('pageurl') || '').trim();
            if (!pageurlVal) {
                model.set('pageurl', defaultPageurlForList(comp, model), { silent: true });
            }
        }
        updateLayerName(model, comp, props);
        if (comp === 'section') {
            applySectionShellFlags(model);
        }
        if (comp === 'site_nav' || comp === 'channel_strip' || comp === 'header_decor') {
            model.addAttributes({ 'data-chrome': '1' });
            model.set('removable', false);
            model.set('draggable', false);
        }
        if (comp === 'site_footer') {
            model.addAttributes({ 'data-chrome-footer': '1' });
            model.set('removable', false);
            model.set('draggable', false);
        }
        if (block.slots && model.components().length) {
            var idx = 0;
            Object.keys(block.slots).forEach(function (sk) {
                var list = block.slots[sk];
                if (!Array.isArray(list)) {
                    return;
                }
                list.forEach(function (childBlock, j) {
                    var child = collAt(model.components(), idx);
                    var childPath = path ? (path + '/' + sk + '/' + j) : '';
                    if (child) {
                        hydrateModel(child, childBlock, childPath);
                        child.addAttributes({ 'data-slot': sk });
                        idx += 1;
                    }
                });
            });
        }
        if (listUsesSectionHeader(comp)) {
            pullSectionHeaderToList(model);
        }
    }

    function collAt(collection, idx) {
        if (!collection) {
            return null;
        }
        if (typeof collection.at === 'function') {
            return collection.at(idx);
        }
        return collection.models && collection.models[idx];
    }

    function getModelByPath(path) {
        if (!path || !editor) {
            return null;
        }
        var pathStr = String(path);
        var regionMatch = pathStr.match(/^(sh|hero|ch|sb|ft)\/(.+)$/);
        var regionName = 'content';
        var rest = pathStr;
        if (regionMatch) {
            regionName = REGION_PATH_TO_NAME[regionMatch[1]] || 'content';
            rest = regionMatch[2];
        } else if (/^\d+(\/|$)/.test(pathStr)) {
            regionName = 'content';
            rest = pathStr;
        }
        var region = getRegionModel(regionName);
        if (!region) {
            return null;
        }
        var segs = rest.split('/');
        var cur = collAt(region.components(), parseInt(segs[0], 10));
        if (!cur || segs.length === 1) {
            return cur;
        }
        var i = 1;
        while (i < segs.length) {
            var slotKey = segs[i];
            var childIdx = parseInt(segs[i + 1], 10);
            var found = 0;
            var next = null;
            cur.components().forEach(function (ch) {
                var sk = ch.getAttributes()['data-slot'] || 'body';
                if (sk === slotKey) {
                    if (found === childIdx) {
                        next = ch;
                    }
                    found += 1;
                }
            });
            if (!next) {
                return null;
            }
            cur = next;
            i += 2;
        }
        return cur;
    }

    function findModelByPbPath(path) {
        if (!path || !editor) {
            return null;
        }
        var target = String(path);
        var found = null;
        function walk(model) {
            if (found) {
                return;
            }
            var p = model.getAttributes()['data-pb-path'] || '';
            if (p === target) {
                found = model;
                return;
            }
            model.components().forEach(walk);
        }
        walk(editor.getWrapper());
        return found;
    }

    function resolveModelFromPreviewPath(path) {
        var model = findModelByPbPath(path) || getModelByPath(path);
        if (model) {
            return model;
        }
        var m = String(path || '').match(/^(\d+)\/(banner|feed)\/(\d+)$/);
        if (!m) {
            return null;
        }
        var parent = getModelByPath(m[1]) || findModelByPbPath(m[1]);
        if (!parent || parent.getAttributes()['data-component'] !== 'filmstrip_layout') {
            return null;
        }
        var wantSlot = m[2];
        var wantIdx = parseInt(m[3], 10);
        var found = 0;
        var hit = null;
        parent.components().forEach(function (ch) {
            var sk = ch.getAttributes()['data-slot'] || '';
            var cc = ch.getAttributes()['data-component'] || '';
            var matchSlot = sk === wantSlot
                || (wantSlot === 'banner' && isFilmstripBannerComp(cc))
                || (wantSlot === 'feed' && isFilmstripFeedComp(cc));
            if (matchSlot) {
                if (found === wantIdx) {
                    hit = ch;
                }
                found += 1;
            }
        });
        return hit;
    }

    function normalizeFilmstripSlots(model) {
        if (!model) {
            return;
        }
        var comp = model.getAttributes()['data-component'] || '';
        if (comp === 'filmstrip_layout') {
            model.components().forEach(function (ch) {
                var cc = ch.getAttributes()['data-component'] || '';
                if (isFilmstripBannerComp(cc)) {
                    ch.addAttributes({ 'data-slot': 'banner' });
                } else if (isFilmstripFeedComp(cc)) {
                    ch.addAttributes({ 'data-slot': 'feed' });
                    applyFilmstripFeedProps(ch);
                }
                if (isFilmstripBannerComp(cc) || isFilmstripFeedComp(cc)) {
                    ch.set({
                        selectable: true,
                        layerable: true,
                        hoverable: true,
                        highlightable: true,
                        removable: true
                    });
                }
            });
        }
        model.components().forEach(normalizeFilmstripSlots);
    }

    function modelContainsDescendant(ancestor, descendant) {
        if (!ancestor || !descendant || ancestor === descendant) {
            return false;
        }
        var cur = descendant.parent();
        while (cur && !cur.is('wrapper')) {
            if (cur === ancestor) {
                return true;
            }
            cur = cur.parent();
        }
        return false;
    }

    function expandLayerAncestors(model, includeSelf) {
        if (!model || model.is('wrapper')) {
            return;
        }
        var cur = includeSelf ? model : model.parent();
        while (cur && !cur.is('wrapper')) {
            cur.set('open', true);
            cur = cur.parent();
        }
    }

    function scrollLayerToModel(model) {
        if (!model || model.is('wrapper')) {
            return;
        }
        expandLayerAncestors(model, false);
        window.requestAnimationFrame(function () {
            if (!model.viewLayer || !model.viewLayer.el) {
                return;
            }
            var row = model.viewLayer.el.querySelector('.gjs-layer-item');
            if (!row) {
                return;
            }
            var panel = document.getElementById('pb-layers-panel');
            if (!panel) {
                return;
            }
            var pr = panel.getBoundingClientRect();
            var rr = row.getBoundingClientRect();
            if (rr.top < pr.top + 8 || rr.bottom > pr.bottom - 8) {
                row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        });
    }

    function paintLayerSelectionRow(model) {
        var panel = document.getElementById('pb-layers-panel');
        if (!panel) {
            return;
        }
        panel.querySelectorAll('.gjs-layer-item.pb-layer-selected').forEach(function (el) {
            el.classList.remove('pb-layer-selected');
        });
        if (!model || model.is('wrapper')) {
            return;
        }
        if (!model.viewLayer || !model.viewLayer.el) {
            return;
        }
        var row = model.viewLayer.el.querySelector(':scope > .gjs-layer-item');
        if (!row) {
            row = model.viewLayer.el.querySelector('.gjs-layer-item');
        }
        if (row) {
            row.classList.add('pb-layer-selected');
        }
    }

    /**
     * @param {object} model
     * @param {{skipScroll?: boolean}} [opts] skipScroll=true：结构树点击时勿 scrollIntoView，
     *   否则双击第二次会因树位移点到上方祖先（如「主内容」）并误触发层名编辑。
     */
    function highlightLayerForModel(model, opts) {
        opts = opts || {};
        if (!model || !editor || model.is('wrapper')) {
            return;
        }
        // 只展开祖先，不改自身 open（叶子 open 切换会触发层重绘，选中高亮会闪没）
        expandLayerAncestors(model, false);
        var apply = function () {
            if (!model.viewLayer || !model.viewLayer.el) {
                return false;
            }
            paintLayerSelectionRow(model);
            if (!opts.skipScroll) {
                scrollLayerToModel(model);
            }
            return true;
        };
        if (!apply()) {
            refreshLayerTree();
            window.setTimeout(function () {
                expandLayerAncestors(model, false);
                apply();
            }, 40);
            return;
        }
        // 祖先展开后 Layer 可能异步重挂 DOM，再补一次选中态
        window.requestAnimationFrame(function () {
            paintLayerSelectionRow(editor.getSelected() || model);
        });
        window.setTimeout(function () {
            paintLayerSelectionRow(editor.getSelected() || model);
        }, 60);
    }

    function updateUndoRedoButtons() {
        if (!editor) {
            return;
        }
        var um = editor.UndoManager;
        $('#pbUndo').prop('disabled', !um.hasUndo());
        $('#pbRedo').prop('disabled', !um.hasRedo());
    }

    function findModelByLayerEl(layerEl) {
        if (!editor || !layerEl) {
            return null;
        }
        var rowEl = layerEl.classList && layerEl.classList.contains('gjs-layer-item')
            ? layerEl
            : (layerEl.querySelector ? layerEl.querySelector(':scope > .gjs-layer-item') : null);
        var fromData = $(layerEl).data('model');
        if (fromData) {
            return fromData;
        }
        if (rowEl) {
            fromData = $(rowEl).data('model');
            if (fromData) {
                return fromData;
            }
        }
        var found = null;
        function viewMatches(modelEl) {
            if (!modelEl) {
                return false;
            }
            return modelEl === layerEl || modelEl === rowEl;
        }
        function walk(model) {
            if (found) {
                return;
            }
            if (model.viewLayer && viewMatches(model.viewLayer.el)) {
                found = model;
                return;
            }
            model.components().forEach(walk);
        }
        walk(editor.getWrapper());
        return found;
    }

    var layerDragState = null;
    var layerDragMoveHandler = null;
    var layerDragUpHandler = null;
    /** 结构树双击防抖：第一次 pointerdown 的 model/坐标，避免第二次因 DOM 位移误命中祖先 */
    var layerPointerGuard = { model: null, time: 0, x: 0, y: 0 };

    function findLayerItemAt(clientX, clientY, dragModel) {
        var panel = document.getElementById('pb-layers-panel');
        if (!panel) {
            return null;
        }
        var items = panel.querySelectorAll('.gjs-layer-item');
        var dragParent = dragModel && dragModel.parent();
        if (dragParent && dragParent.is('wrapper')) {
            var rootLayers = panel.querySelectorAll('.gjs-layers > .gjs-layer > .gjs-layer-item');
            if (rootLayers.length) {
                items = rootLayers;
            }
        }
        var best = null;
        var bestArea = Infinity;
        items.forEach(function (el) {
            var r = el.getBoundingClientRect();
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                var area = r.width * r.height;
                if (area < bestArea) {
                    bestArea = area;
                    best = el;
                }
            }
        });
        return best;
    }

    function alignDropTarget(dragModel, targetModel) {
        if (!dragModel || !targetModel || dragModel === targetModel) {
            return targetModel;
        }
        var dragParent = dragModel.parent();
        if (!dragParent) {
            return targetModel;
        }
        if (dragParent.is('wrapper')) {
            var root = targetModel;
            while (root.parent() && !root.parent().is('wrapper')) {
                root = root.parent();
            }
            return root;
        }
        var cur = targetModel;
        while (cur && cur !== dragModel) {
            if (cur.parent() === dragParent) {
                return cur;
            }
            cur = cur.parent();
        }
        return targetModel;
    }

    function layerRowRect(model) {
        if (!model || !model.viewLayer || !model.viewLayer.el) {
            return null;
        }
        var row = model.viewLayer.el.querySelector('.gjs-layer-item');
        return row ? row.getBoundingClientRect() : null;
    }

    function resolveLayerDrop(dragModel, clientY, clientX) {
        if (!dragModel || !editor) {
            return { valid: false };
        }
        clientX = clientX || (layerDragState ? layerDragState.clientX : 0);
        var hitItem = findLayerItemAt(clientX, clientY, dragModel);
        if (!hitItem) {
            return { valid: false };
        }
        var layerEl = hitItem.closest('.gjs-layer');
        var targetModel = findModelByLayerEl(layerEl);
        if (!targetModel) {
            return { valid: false };
        }
        targetModel = alignDropTarget(dragModel, targetModel);
        if (!targetModel || targetModel === dragModel) {
            return { valid: false };
        }

        var dragComp = dragModel.getAttributes()['data-component'] || '';
        var dragParent = dragModel.parent();
        var targetParent = targetModel.parent();
        var rect = layerRowRect(targetModel) || hitItem.getBoundingClientRect();
        var insertBefore = clientY < rect.top + rect.height / 2;

        if (dragParent && targetParent && dragParent === targetParent) {
            var coll = dragParent.components();
            var targetIdx = coll.indexOf(targetModel);
            if (targetIdx < 0) {
                return { valid: false };
            }
            var at = insertBefore ? targetIdx : targetIdx + 1;
            var fromIdx = coll.indexOf(dragModel);
            if (fromIdx < 0) {
                return { valid: false };
            }
            if (fromIdx < at) {
                at -= 1;
            }
            if (at === fromIdx) {
                return { valid: false };
            }
            return { valid: true, parent: dragParent, at: at, lineY: insertBefore ? rect.top : rect.bottom };
        }

        if (canDropChildInParent(dragComp, targetModel.getAttributes()['data-component'] || '', false)) {
            var childAt = insertBefore ? 0 : targetModel.components().length;
            return {
                valid: true,
                parent: targetModel,
                at: childAt,
                lineY: insertBefore ? rect.top : rect.bottom
            };
        }

        if (targetParent && !targetParent.is('wrapper')) {
            var parentComp = targetParent.getAttributes()['data-component'] || '';
            if (canDropChildInParent(dragComp, parentComp, false)) {
                var siblingColl = targetParent.components();
                var sibIdx = siblingColl.indexOf(targetModel);
                if (sibIdx < 0) {
                    return { valid: false };
                }
                var childAt2 = insertBefore ? sibIdx : sibIdx + 1;
                return {
                    valid: true,
                    parent: targetParent,
                    at: childAt2,
                    lineY: insertBefore ? rect.top : rect.bottom
                };
            }
        }

        var targetRegion = targetModel.getAttributes()['data-pb-region'] || '';
        if (targetRegion && regionAllowsDirectChild(targetRegion, dragComp)) {
            var regionAt = insertBefore ? 0 : targetModel.components().length;
            return {
                valid: true,
                parent: targetModel,
                at: regionAt,
                lineY: insertBefore ? rect.top : rect.bottom
            };
        }

        if (targetParent && targetParent.is('wrapper')) {
            if (TOP_LEVEL.indexOf(dragComp) >= 0 || isLayoutContentComp(dragComp)) {
                var roots = editor.getWrapper().components();
                var rootAt = roots.indexOf(targetModel);
                if (rootAt < 0) {
                    return { valid: false };
                }
                if (!insertBefore) {
                    rootAt += 1;
                }
                return {
                    valid: true,
                    parent: editor.getWrapper(),
                    at: rootAt,
                    lineY: insertBefore ? rect.top : rect.bottom
                };
            }
            return { valid: false, reason: PB.lang.drop_list_in_section || PB.lang.drop_invalid };
        }

        return { valid: false };
    }

    function refreshLayerTree() {
        if (!editor) {
            return;
        }
        if (!ensureLayerRoot()) {
            return;
        }
        try {
            var lm = editor.LayerManager || editor.Layers;
            if (lm && typeof lm.render === 'function') {
                lm.render();
            }
        } catch (err) {
            if (window.console && console.warn) {
                console.warn('[PB] refreshLayerTree failed', err);
            }
        }
        try {
            refreshAllLayerMoveHandles();
            decorateSectionLayersInPanel();
            if (editor) {
                paintLayerSelectionRow(editor.getSelected());
            }
        } catch (err2) {
            if (window.console && console.warn) {
                console.warn('[PB] layer decorate failed', err2);
            }
        }
    }

    function relocateModel(model, newParent, at) {
        if (!model || !newParent || !editor) {
            return false;
        }
        var oldParent = model.parent();
        if (!oldParent) {
            return false;
        }
        var coll = newParent.components();
        at = Math.max(0, Math.min(at, coll.length));

        if (oldParent === newParent) {
            var fromIdx = coll.indexOf(model);
            if (fromIdx < 0) {
                return false;
            }
            if (fromIdx === at || (fromIdx < at && fromIdx + 1 === at)) {
                return false;
            }
        }

        try {
            if (typeof model.move === 'function') {
                model.move(newParent, { at: at });
            } else {
                oldParent.components().remove(model);
                newParent.components().add(model, { at: at });
            }
            if (oldParent !== newParent) {
                assignSlotOnInsert(model, newParent);
            }
            return true;
        } catch (err) {
            return false;
        }
    }

    function paintLayerDropLine(drop) {
        var line = document.getElementById('pb-layer-insert-line');
        var panel = document.getElementById('pb-layers-panel');
        if (!line || !panel) {
            return;
        }
        if (!drop || !drop.valid || !drop.lineY) {
            line.classList.remove('is-active');
            return;
        }
        var pr = panel.getBoundingClientRect();
        line.style.left = (pr.left + 8) + 'px';
        line.style.width = Math.max(120, pr.width - 16) + 'px';
        line.style.top = (drop.lineY - 1) + 'px';
        line.classList.add('is-active');
    }

    function clearLayerDropLine() {
        var line = document.getElementById('pb-layer-insert-line');
        if (line) {
            line.classList.remove('is-active');
        }
        document.querySelectorAll('#pb-layers-panel .gjs-layer.pb-layer-drag-target').forEach(function (el) {
            el.classList.remove('pb-layer-drag-target');
        });
    }

    function applyLayerDrop(dragModel, drop) {
        if (!drop || !drop.valid || !drop.parent) {
            if (drop && drop.reason) {
                showStatus(drop.reason);
            }
            return false;
        }
        suppressSave = true;
        var ok = relocateModel(dragModel, drop.parent, drop.at);
        suppressSave = false;
        if (!ok) {
            showStatus(PB.lang.drop_invalid || 'Cannot move here');
            return false;
        }
        editor.select(dragModel);
        refreshLayerTree();
        afterStructureMutation(false);
        return true;
    }

    /**
     * 移动单位升级：可见块（如「热门推荐」）的直接父级是单子 section 壳
     * （壳行在结构树里被隐藏，壳内只有这一个可见子行）。直接按 sel.parent()
     * 排序时 idx=0/len=1，工具条 ↑↓ 永远禁用——向上取壳的父级，让移动发生在
     * 「块与块」之间。最多升 5 层，防意外死循环。
     */
    function resolveMoveUnit(model) {
        var unit = model;
        var guard = 0;
        while (unit && guard++ < 5) {
            var p = unit.parent();
            if (!p || p.is('wrapper')) {
                break;
            }
            var pa = p.getAttributes();
            if (p.components().length === 1
                && (pa['data-component'] === 'section' || (p.getName() || '') === '\u200b')) {
                unit = p;
                continue;
            }
            break;
        }
        return unit;
    }

    function updateLayerMoveButtons() {
        if (!editor) {
            $('#pbMoveUp, #pbMoveDown').prop('disabled', true);
            return;
        }
        var sel = editor.getSelected();
        if (!sel || sel.is('wrapper')) {
            $('#pbMoveUp, #pbMoveDown').prop('disabled', true);
            return;
        }
        var unit = resolveMoveUnit(sel);
        var parent = unit.parent();
        if (!parent) {
            $('#pbMoveUp, #pbMoveDown').prop('disabled', true);
            return;
        }
        var siblings = parent.components();
        var idx = siblings.indexOf(unit);
        var len = siblings.length;
        $('#pbMoveUp').prop('disabled', idx <= 0);
        $('#pbMoveDown').prop('disabled', idx < 0 || idx >= len - 1);
    }

    function moveSelectedSibling(delta) {
        if (!editor || isRouteLockedMode()) {
            return false;
        }
        var sel = editor.getSelected();
        if (!sel || sel.is('wrapper')) {
            return false;
        }
        var unit = resolveMoveUnit(sel);
        var parent = unit.parent();
        if (!parent) {
            return false;
        }
        var coll = parent.components();
        var idx = coll.indexOf(unit);
        var newIdx = idx + delta;
        if (newIdx < 0 || newIdx >= coll.length) {
            return false;
        }
        suppressSave = true;
        coll.remove(unit);
        coll.add(unit, { at: newIdx });
        assignSlotOnInsert(unit, parent);
        suppressSave = false;
        editor.select(sel);
        afterStructureMutation(false);
        updateLayerMoveButtons();
        return true;
    }

    function syncLayerSelection(model) {
        if (!model || model.is('wrapper')) {
            return;
        }
        highlightLayerForModel(model);
    }

    function initLayerCollapseGuard() {
        var panel = document.getElementById('pb-layers-panel');
        if (!panel || panel.getAttribute('data-pb-layer-collapse-guard')) {
            return;
        }
        panel.setAttribute('data-pb-layer-collapse-guard', '1');
        panel.addEventListener('click', function (e) {
            var caret = e.target.closest('[data-toggle-open]');
            if (!caret || !editor) {
                return;
            }
            // GJS 0.21 把 [data-toggle-open] 事件绑定在父级子容器视图（ItemsView）上：
            // 点子层 caret，toggleOpening 的 this.model 是父组件 —— 点 Surround layout
            // 会把「主内容」整个收起。这里捕获阶段接管：按 DOM 归属算出被点的层，
            // 翻转它自己的 open，并手动同步行 class（行视图不监听该模型的变化，
            // children 显隐完全由 .open class 的 CSS 驱动）。
            var layerEl = caret.closest('.gjs-layer');
            var layerModel = findModelByLayerEl(layerEl);
            if (!layerModel) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            e.stopPropagation();
            var isOpenNow = layerEl.classList.contains('open');
            var selected = editor.getSelected();
            if (selected && layerModel !== selected && modelContainsDescendant(layerModel, selected)) {
                // 选中态在该层的子孙里：不许收起祖先
                layerModel.set('open', true);
                layerEl.classList.add('open');
                return;
            }
            var next = !isOpenNow;
            layerModel.set('open', next);
            layerEl.classList.toggle('open', next);
        }, true);
    }

    function isLayerCaretTarget(el) {
        return !!(el && (el.closest('[data-toggle-open]') || el.closest('.gjs-layer-caret')));
    }

    function isLayerVisTarget(el) {
        return !!(el && (el.closest('[data-toggle-visible]') || el.closest('.gjs-layer-vis')));
    }

    /** 主内容/侧栏 + 顶栏/Hero/频道/页脚等区域壳：结构树点击应落到子块 */
    function isRegionShellWrapper(model) {
        return isPageRegionWrapper(model) || isChromeRegionWrapper(model);
    }

    function selectLayerTreeModel(model) {
        if (!editor || !model || model.is('wrapper')) {
            return;
        }
        if (isRegionShellWrapper(model)) {
            var firstChild = model.components().at(0);
            if (firstChild) {
                selectLayerTreeModel(firstChild);
            }
            return;
        }
        // fromLayers:true —— 避免 LayerManager 先收起再展开祖先，导致子层 DOM 重挂、高亮闪没
        // skipScroll：用户已点中该行，禁止 scrollIntoView 挪树，否则双击会点到上方节点
        var opts = { fromLayers: true };
        var hlOpts = { skipScroll: true };
        if (model.getAttributes()['data-component'] === 'section') {
            var listInSec = resolveListModelInSection(model);
            var target = listInSec || model;
            target.set({
                selectable: true,
                layerable: true,
                hoverable: true,
                highlightable: true
            });
            editor.select(target, opts);
            highlightLayerForModel(target, hlOpts);
            return;
        }
        model.set({
            selectable: true,
            layerable: true,
            hoverable: true,
            highlightable: true
        });
        editor.select(model, opts);
        highlightLayerForModel(model, hlOpts);
    }

    function initLayerDragBridge() {
        var panel = document.getElementById('pb-layers-panel');
        if (!panel || panel.getAttribute('data-pb-layer-bridge')) {
            return;
        }
        panel.setAttribute('data-pb-layer-bridge', '1');

        if (!document.getElementById('pb-layer-insert-line')) {
            var line = document.createElement('div');
            line.id = 'pb-layer-insert-line';
            line.className = 'pb-layer-insert-line';
            line.setAttribute('aria-hidden', 'true');
            document.body.appendChild(line);
        }

        function stopLayerDrag(e) {
            if (!layerDragState) {
                return;
            }
            var dragModel = layerDragState.model;
            var clientY = e.clientY;
            var clientX = e.clientX;
            if (e.changedTouches && e.changedTouches.length) {
                clientY = e.changedTouches[0].clientY;
                clientX = e.changedTouches[0].clientX;
            }
            var drop = resolveLayerDrop(dragModel, clientY, clientX);
            if (!drop.valid && layerDragState.drop && layerDragState.drop.valid) {
                drop = layerDragState.drop;
            }
            clearLayerDropLine();
            document.body.classList.remove('pb-layer-dragging');
            if (layerDragMoveHandler) {
                window.removeEventListener('mousemove', layerDragMoveHandler, true);
                window.removeEventListener('pointermove', layerDragMoveHandler, true);
                window.removeEventListener('touchmove', layerDragMoveHandler, true);
            }
            if (layerDragUpHandler) {
                window.removeEventListener('mouseup', layerDragUpHandler, true);
                window.removeEventListener('pointerup', layerDragUpHandler, true);
                window.removeEventListener('touchend', layerDragUpHandler, true);
            }
            layerDragMoveHandler = null;
            layerDragUpHandler = null;
            layerDragState = null;
            if (drop && drop.valid) {
                applyLayerDrop(dragModel, drop);
            } else if (drop && drop.reason) {
                showStatus(drop.reason);
            }
        }

        function onLayerDragMove(e) {
            if (!layerDragState) {
                return;
            }
            var clientY = e.clientY;
            var clientX = e.clientX;
            if (e.touches && e.touches.length) {
                clientY = e.touches[0].clientY;
                clientX = e.touches[0].clientX;
            }
            layerDragState.clientX = clientX;
            layerDragState.clientY = clientY;
            var drop = resolveLayerDrop(layerDragState.model, clientY, clientX);
            layerDragState.drop = drop;
            paintLayerDropLine(drop);
            document.querySelectorAll('#pb-layers-panel .gjs-layer.pb-layer-drag-target').forEach(function (el) {
                el.classList.remove('pb-layer-drag-target');
            });
            if (drop && drop.valid) {
                var hitItem = findLayerItemAt(clientX, clientY, layerDragState.model);
                if (hitItem) {
                    var layerEl = hitItem.closest('.gjs-layer');
                    if (layerEl) {
                        layerEl.classList.add('pb-layer-drag-target');
                    }
                }
            }
        }

        function handleLayerDragStart(e) {
            if (!editor || layerDragState) {
                return;
            }
            // pointerdown + mousedown 会各触发一次，忽略同一次点击的重复
            if (e.type === 'mousedown' && e.pointerType && e.pointerType !== '') {
                return;
            }
            var rowEl = e.target.closest('.gjs-layer-item');
            if (!rowEl) {
                return;
            }
            var layerEl = rowEl.closest('.gjs-layer');
            if (!layerEl) {
                return;
            }
            var model = findModelByLayerEl(layerEl);
            if (!model || model.is('wrapper')) {
                return;
            }
            if (isLayerVisTarget(e.target) || isLayerCaretTarget(e.target)) {
                return;
            }
            // 双击第二次：坐标几乎不变但 DOM 可能已滚动/重挂，命中变成上方祖先 → 粘住第一次的 model
            var now = Date.now();
            var guard = layerPointerGuard;
            if (
                guard.model &&
                model !== guard.model &&
                (now - guard.time) < 400 &&
                Math.abs((e.clientX || 0) - guard.x) < 6 &&
                Math.abs((e.clientY || 0) - guard.y) < 6
            ) {
                model = guard.model;
            } else {
                layerPointerGuard = {
                    model: model,
                    time: now,
                    x: e.clientX || 0,
                    y: e.clientY || 0
                };
            }
            if (isRouteLockedMode()) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                selectLayerTreeModel(model);
                return;
            }
            var moveHandle = e.target.closest('[data-toggle-move]');
            if (!moveHandle) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                selectLayerTreeModel(model);
                return;
            }
            if (e.cancelable) {
                e.preventDefault();
            }
            e.stopPropagation();
            e.stopImmediatePropagation();

            layerDragState = {
                model: model,
                clientX: e.clientX,
                clientY: e.clientY,
                drop: null
            };
            document.body.classList.add('pb-layer-dragging');
            editor.select(model, { fromLayers: true });

            layerDragMoveHandler = onLayerDragMove;
            layerDragUpHandler = stopLayerDrag;
            window.addEventListener('mousemove', layerDragMoveHandler, true);
            window.addEventListener('pointermove', layerDragMoveHandler, true);
            window.addEventListener('touchmove', layerDragMoveHandler, { capture: true, passive: false });
            window.addEventListener('mouseup', layerDragUpHandler, true);
            window.addEventListener('pointerup', layerDragUpHandler, true);
            window.addEventListener('touchend', layerDragUpHandler, true);
            onLayerDragMove(e);
        }

        /** GJS LayerManager 的 click(data-toggle-select) 会在 pointerdown 之后误选 chrome 区域壳并取消选中 */
        function handleLayerTreeClick(e) {
            if (!editor) {
                return;
            }
            var rowEl = e.target.closest('.gjs-layer-item');
            if (!rowEl) {
                return;
            }
            if (isLayerVisTarget(e.target) || isLayerCaretTarget(e.target)) {
                return;
            }
            var layerEl = rowEl.closest('.gjs-layer');
            if (!layerEl) {
                return;
            }
            var model = findModelByLayerEl(layerEl);
            if (!model || model.is('wrapper')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }

        /**
         * 拦截双击：GJS 默认 dblclick[data-name] 会进入层名编辑；
         * 再叠加选中时的树位移，常会误打开上方「主内容」重命名。PB 层名由 title 驱动，禁双击改名。
         */
        function handleLayerTreeDblClick(e) {
            if (!editor) {
                return;
            }
            if (isLayerVisTarget(e.target) || isLayerCaretTarget(e.target)) {
                return;
            }
            var rowEl = e.target.closest('.gjs-layer-item');
            if (!rowEl) {
                return;
            }
            var layerEl = rowEl.closest('.gjs-layer');
            if (!layerEl) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            var model = layerPointerGuard.model || findModelByLayerEl(layerEl);
            if (!model || model.is('wrapper')) {
                return;
            }
            selectLayerTreeModel(model);
            // 双击：定位预览到该块（结构树已可见，无需再 scroll 层）
            var path = model.getAttributes()['data-pb-path'];
            if (!path && model.getAttributes()['data-component'] === 'section') {
                var listInSec = resolveListModelInSection(model);
                path = listInSec ? listInSec.getAttributes()['data-pb-path'] : '';
            }
            if (path) {
                scrollPreviewToPath(path);
            }
        }

        // 优先 pointerdown；无 pointer 时再靠 mousedown
        panel.addEventListener('pointerdown', handleLayerDragStart, true);
        panel.addEventListener('click', handleLayerTreeClick, true);
        panel.addEventListener('dblclick', handleLayerTreeDblClick, true);
        panel.addEventListener('contextmenu', handleLayerTreeContextMenu, true);
        panel.addEventListener('mousedown', function (e) {
            if (window.PointerEvent) {
                return;
            }
            handleLayerDragStart(e);
        }, true);
        panel.addEventListener('touchstart', function (e) {
            if (window.PointerEvent) {
                return;
            }
            if (!e.touches || !e.touches.length) {
                return;
            }
            var t = e.touches[0];
            handleLayerDragStart({
                type: 'touchstart',
                target: e.target,
                clientX: t.clientX,
                clientY: t.clientY,
                cancelable: true,
                preventDefault: function () { e.preventDefault(); },
                stopPropagation: function () { e.stopPropagation(); },
                stopImmediatePropagation: function () { e.stopImmediatePropagation(); }
            });
        }, { capture: true, passive: false });
    }

    function bindSorterDragFeedback() {
        if (!editor) {
            return;
        }
        editor.em.on('sorter:drag:end', function (data) {
            // 预览区自定义落点已成功时，忽略 Grapes 画布「不可放置」误报
            if (dropInsertedOnRelease) {
                return;
            }
            if (!data || !data.warns || !data.warns.length) {
                return;
            }
            var msg = String(data.warns[0] || '');
            if (msg.indexOf('not droppable') >= 0 || msg.indexOf('Target is not droppable') >= 0) {
                msg = PB.lang.drop_list_in_section || PB.lang.drop_invalid;
            }
            showStatus(msg);
        });
    }

    function afterStructureMutation(fromUndo) {
        reindexPaths();
        refreshLayerTree();
        updateLayerMoveButtons();
        updateUndoRedoButtons();
        var doc = getPreviewDoc();
        if (doc) {
            markEmptyLayoutSlots(doc);
        }
        if (fromUndo) {
            capturePreviewScroll();
        }
        scheduleAutoSave(true);
        scheduleFitPreviewScale();
    }

    function expandLayerTree(model) {
        if (!model) {
            return;
        }
        model.set('open', true);
        model.components().forEach(function (ch) {
            expandLayerTree(ch);
        });
    }

    function refreshAllLayerMoveHandles() {
        if (!editor) {
            return;
        }
        var wrapper = editor.getWrapper();
        function refreshModel(model) {
            if (model && model.viewLayer && typeof model.viewLayer.updateMove === 'function') {
                model.viewLayer.updateMove();
            }
        }
        refreshModel(wrapper);
        wrapper.components().forEach(function walk(ch) {
            refreshModel(ch);
            ch.components().forEach(walk);
        });
    }

    function getPreviewLogicalWidth() {
        return $('#pb-center').hasClass('is-mobile') ? PREVIEW_WIDTH_MOBILE : PREVIEW_WIDTH_DESKTOP;
    }

    function fitPreviewScale() {
        var $scaler = $('#pb-preview-scaler');
        var $frame = $('#pb-preview-frame');
        var $iframe = $('#pb-preview');
        if (!$scaler.length || !$frame.length || !$iframe.length) {
            return;
        }
        var logicalW = getPreviewLogicalWidth();
        var availW = $scaler.innerWidth();
        var availH = $scaler.innerHeight();
        if (availW <= 0 || availH <= 0) {
            return;
        }
        var isMobile = $('#pb-center').hasClass('is-mobile');
        if (!isMobile) {
            // Wide panel: 1:1 canvas. Narrow: scale from 1200 so hit-testing stays usable.
            logicalW = availW >= 1200 ? Math.round(availW) : 1200;
        }
        var scale = availW / logicalW;
        if (scale > 1) {
            scale = 1;
        }
        var iframeH = Math.max(1, Math.round(availH / scale));
        var visualW = Math.round(logicalW * scale);
        var offsetX = Math.max(0, Math.round((availW - visualW) / 2));
        appliedPreviewLogicalW = logicalW;
        appliedPreviewScale = scale;
        $scaler.css({ position: 'relative' });
        $frame.css({
            position: 'absolute',
            top: '0',
            left: offsetX + 'px',
            right: 'auto',
            bottom: 'auto',
            width: logicalW + 'px',
            height: iframeH + 'px',
            transform: scale === 1 ? 'none' : ('scale(' + scale + ')'),
            transformOrigin: 'top left',
            margin: '0'
        });
        $iframe.css({
            width: logicalW + 'px',
            height: iframeH + 'px'
        });
        if (editor) {
            try {
                editor.refresh();
            } catch (err) {
                if (window.console && console.warn) {
                    console.warn('[PB] editor.refresh failed', err);
                }
            }
        }
    }

    function scheduleFitPreviewScale() {
        if (previewScaleTimer) {
            clearTimeout(previewScaleTimer);
        }
        previewScaleTimer = setTimeout(function () {
            previewScaleTimer = null;
            fitPreviewScale();
            var $scaler = $('#pb-preview-scaler');
            if ($scaler.length && ($scaler.innerWidth() <= 0 || $scaler.innerHeight() <= 0) && previewScaleRetries < 24) {
                previewScaleRetries += 1;
                scheduleFitPreviewScale();
            } else {
                previewScaleRetries = 0;
            }
        }, 60);
    }

    var previewResizeObserver = null;
    function ensurePreviewResizeObserver() {
        if (previewResizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }
        var scaler = document.getElementById('pb-preview-scaler');
        if (!scaler) {
            return;
        }
        previewResizeObserver = new ResizeObserver(function () {
            scheduleFitPreviewScale();
        });
        previewResizeObserver.observe(scaler);
    }

    function getPreviewDoc() {
        var iframe = document.getElementById('pb-preview');
        if (!iframe) {
            return null;
        }
        try {
            return iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
        } catch (e) {
            return null;
        }
    }

    function clearSurroundSlotHighlights(doc) {
        if (!doc) {
            return;
        }
        doc.querySelectorAll('.pb-surround-slot').forEach(function (el) {
            el.classList.remove('pb-surround-slot-selected');
        });
    }

    function syncSurroundSlotHighlight(hostEl) {
        if (!hostEl || !hostEl.closest) {
            return;
        }
        var doc = hostEl.ownerDocument;
        clearSurroundSlotHighlights(doc);
        var slot = hostEl.closest('.pb-surround-slot');
        if (slot) {
            slot.classList.add('pb-surround-slot-selected');
        }
    }

    function highlightPreviewPath(path, hostEl) {
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        doc.querySelectorAll('.pb-block-host').forEach(function (el) {
            el.classList.remove('pb-block-selected');
        });
        clearSurroundSlotHighlights(doc);
        if (!path && !hostEl) {
            return;
        }
        if (hostEl && hostEl.classList && hostEl.classList.contains('pb-block-host')) {
            hostEl.classList.add('pb-block-selected');
            syncSurroundSlotHighlight(hostEl);
            return;
        }
        if (!path) {
            return;
        }
        var matches = doc.querySelectorAll('.pb-block-host[data-pb-path="' + path + '"]');
        if (!matches.length) {
            return;
        }
        var pick = matches[0];
        if (matches.length > 1) {
            for (var i = 0; i < matches.length; i++) {
                var comp = matches[i].getAttribute('data-pb-component') || '';
                if (comp !== 'site_nav' && comp !== 'site_footer' && comp !== 'bottom_nav' && comp !== 'channel_strip' && comp !== 'header_decor') {
                    pick = matches[i];
                    break;
                }
            }
        }
        pick.classList.add('pb-block-selected');
        syncSurroundSlotHighlight(pick);
    }

    function scrollPreviewToPath(path) {
        if (!path) {
            return;
        }
        highlightPreviewPath(path);
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        var el = doc.querySelector('.pb-block-host[data-pb-path="' + path + '"]');
        if (!el) {
            return;
        }
        var win = doc.defaultView;
        if (!win) {
            return;
        }
        try {
            var rect = el.getBoundingClientRect();
            var top = win.scrollY + rect.top - 72;
            win.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        } catch (e) {
            try {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e2) {
                el.scrollIntoView(true);
            }
        }
    }

    function capturePreviewScroll() {
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        previewScrollY = doc.documentElement.scrollTop || doc.body.scrollTop || 0;
        previewScrollX = doc.documentElement.scrollLeft || doc.body.scrollLeft || 0;
    }

    function restorePreviewScroll() {
        var doc = getPreviewDoc();
        if (!doc) {
            return;
        }
        doc.documentElement.scrollTop = previewScrollY;
        doc.body.scrollTop = previewScrollY;
        doc.documentElement.scrollLeft = previewScrollX;
        doc.body.scrollLeft = previewScrollX;
    }

    function findPreviewHostFromTarget(target, doc) {
        if (!target || !doc || !doc.body) {
            return null;
        }
        if (target.nodeType === 1 && target.classList && target.classList.contains('pb-block-host')) {
            return target;
        }
        if (target.closest) {
            var host = target.closest('.pb-block-host');
            if (host) {
                return host;
            }
            var slot = target.closest('.pb-surround-slot');
            if (slot) {
                var slotHost = slot.querySelector('.pb-block-host');
                if (slotHost) {
                    return slotHost;
                }
            }
            var surround = target.closest('[data-pb-surround-layout]');
            if (surround) {
                var layoutHost = surround.closest('.pb-block-host');
                if (layoutHost) {
                    return layoutHost;
                }
            }
        }
        return null;
    }

    function resolveFilmstripInnerHost(layoutHost, clientX, clientY) {
        if (!layoutHost) {
            return null;
        }
        var comp = layoutHost.getAttribute('data-pb-component') || '';
        var path = layoutHost.getAttribute('data-pb-path') || '';
        if (comp !== 'filmstrip_layout' || !path) {
            return layoutHost;
        }
        var slotName = resolveFilmstripSlotFromPoint(path, clientX, clientY);
        var slotSel = slotName === 'banner' ? '.pb-surround-slot--banner' : '.pb-surround-slot--feed';
        var inner = layoutHost.querySelector(slotSel + ' .pb-block-host');
        return inner || layoutHost;
    }

    function findPreviewHostFromPoint(doc, clientX, clientY) {
        if (!doc || !doc.elementFromPoint || clientX == null || clientY == null) {
            return null;
        }
        var stack = doc.elementsFromPoint(clientX, clientY);
        if (!stack || !stack.length) {
            return null;
        }
        var layoutHost = null;
        var i;
        for (i = 0; i < stack.length; i++) {
            var el = stack[i];
            if (!el || !el.classList) {
                continue;
            }
            if (el.classList.contains('pb-block-host')) {
                var c = el.getAttribute('data-pb-component') || '';
                if (c === 'filmstrip_layout') {
                    layoutHost = el;
                    break;
                }
                return el;
            }
        }
        if (layoutHost) {
            return resolveFilmstripInnerHost(layoutHost, clientX, clientY);
        }
        for (i = 0; i < stack.length; i++) {
            var node = stack[i];
            if (!node || !node.closest) {
                continue;
            }
            var slot = node.closest('.pb-surround-slot');
            if (slot) {
                var slotHost = slot.querySelector('.pb-block-host');
                if (slotHost) {
                    return slotHost;
                }
            }
        }
        return null;
    }

    function resolvePreviewSelectPath(hostEl) {
        if (!hostEl) {
            return '';
        }
        if (!isMemberMode() && !isAuthMode()) {
            var comp = hostEl.getAttribute('data-pb-component') || '';
            if (comp === 'section') {
                var listHost = firstListHostInSectionEl(hostEl);
                if (listHost) {
                    return listHost.getAttribute('data-pb-path') || '';
                }
            }
            return hostEl.getAttribute('data-pb-path') || '';
        }
        var cur = hostEl;
        while (cur) {
            var comp = cur.getAttribute('data-pb-component') || '';
            if (isMemberInternalComponent(comp)) {
                var skipParent = cur.parentElement ? cur.parentElement.closest('.pb-block-host') : null;
                cur = skipParent;
                continue;
            }
            if (traitsFor(comp).length > 0) {
                return cur.getAttribute('data-pb-path') || '';
            }
            var parent = cur.parentElement ? cur.parentElement.closest('.pb-block-host') : null;
            if (!parent || parent === cur) {
                return cur.getAttribute('data-pb-path') || '';
            }
            cur = parent;
        }
        return hostEl.getAttribute('data-pb-path') || '';
    }

    function handlePreviewHostPick(e) {
        if (document.body.classList.contains('pb-palette-dragging') || $('#pb-preview-wrap').hasClass('is-preview-drop')) {
            stopPaletteDragTracking();
            document.body.classList.remove('pb-palette-dragging');
            $('#pb-preview-wrap').removeClass('is-preview-drop');
            clearDropIndicators(true);
            dragState = null;
            var docDrop = getPreviewDoc();
            if (docDrop && docDrop.body) {
                docDrop.body.classList.remove('pb-drop-active');
            }
        }
        var doc = getPreviewDoc();
        if (!doc || !doc.body || !editor) {
            return;
        }
        var host = findPreviewHostFromPoint(doc, e.clientX, e.clientY);
        if (!host) {
            host = findPreviewHostFromTarget(e.target, doc);
        }
        if (host) {
            host = resolveFilmstripInnerHost(host, e.clientX, e.clientY);
        }
        if (!host) {
            if (e.target && e.target.closest) {
                var blocked = e.target.closest('a');
                if (blocked) {
                    if (e.cancelable) {
                        e.preventDefault();
                    }
                    e.stopPropagation();
                }
            }
            return;
        }
        var path = resolvePreviewSelectPath(host);
        if (!path) {
            return;
        }
        if (e.cancelable) {
            e.preventDefault();
        }
        e.stopPropagation();
        selectByPath(path, true, host);
    }

    function installPreviewLinkGuard(doc) {
        if (!doc || !doc.body || doc.body.getAttribute('data-pb-link-guard') === '1') {
            return;
        }
        doc.body.setAttribute('data-pb-link-guard', '1');
        doc.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a') : null;
            if (!a) {
                return;
            }
            var h = a.getAttribute('href');
            if (!h || h.charAt(0) === '#') {
                return;
            }
            if (/^(javascript:|mailto:|tel:|data:)/i.test(h)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
        }, true);
    }

    function wirePreviewPickHandlers(doc) {
        if (!doc || !doc.body) {
            return;
        }
        if (doc.body.getAttribute('data-pb-pick-wired') !== '1') {
            doc.body.setAttribute('data-pb-pick-wired', '1');
            doc.body.addEventListener('mousedown', handlePreviewHostPick, true);
        }
        if (!doc.body.getAttribute('data-pb-contextmenu')) {
            doc.body.setAttribute('data-pb-contextmenu', '1');
            doc.body.addEventListener('contextmenu', handlePreviewContextMenu, true);
            doc.body.addEventListener('mousedown', function () {
                hidePbContextMenu();
            }, true);
        }
    }

    function wirePreviewIframe() {
        var doc = getPreviewDoc();
        if (!doc || !doc.body) {
            return;
        }
        injectPreviewBridge(doc);
        installPreviewLinkGuard(doc);
        wirePreviewPickHandlers(doc);
        if (!editor) {
            return;
        }
        var sel = editor.getSelected();
        if (sel && !sel.is('wrapper')) {
            var p = sel.getAttributes()['data-pb-path'];
            if (p) {
                highlightPreviewPath(p);
            }
        }
    }

    /** 手机预览宽度下，环绕布局与真机对齐（兜底：避免主题 CSS 缓存或未同步时仍锁 5 列） */
    function previewResponsiveCss() {
        var w = getPreviewLogicalWidth();
        if (w > 560) {
            return '';
        }
        return [
            'body.pb-preview-mode .bili-feed.pb-cf[data-pb-surround-layout],',
            'body.pb-preview-mode .pb-cf[data-pb-surround-layout] {',
            '  grid-template-columns: repeat(2, minmax(0, 1fr)) !important;',
            '  gap: 12px 10px !important;',
            '}',
            'body.pb-preview-mode .pb-cf .bili-feed__carousel,',
            'body.pb-preview-mode .pb-cf .pb-cf__carousel,',
            'body.pb-preview-mode .pb-cf .pb-vc.pb-cf__carousel {',
            '  grid-column: 1 / -1 !important;',
            '  grid-row: auto !important;',
            '  aspect-ratio: 16 / 9;',
            '  min-height: 180px;',
            '}'
        ].join('');
    }

    function markEmptyLayoutSlots(doc) {
        if (!doc) {
            return;
        }
        var leftHint = traitLabel('col_drop_left', 'Left column — drop lists, rankings, etc.');
        var rightHint = traitLabel('col_drop_right', 'Right column — drop lists, rankings, etc.');
        var sectionHint = traitLabel('section_drop_hint', 'Drop lists, rankings, and other content');
        var bannerHint = traitLabel('filmstrip_drop_banner', 'Carousel area — drop video carousel');
        var feedHint = traitLabel('filmstrip_drop_feed', 'Cards area — drop video list (surround style)');
        doc.querySelectorAll('.pb-block-host[data-pb-component="columns"] .bili-columns__col').forEach(function (col, idx) {
            var hosts = col.querySelectorAll(':scope > .pb-block-host, .pb-block-host');
            var hasReal = false;
            Array.prototype.forEach.call(hosts, function (h) {
                var comp = h.getAttribute('data-pb-component') || '';
                if (comp === 'section') {
                    // 空分区不算真实内容
                    if (h.querySelector('.pb-block-host[data-pb-component]:not([data-pb-component="section"])')) {
                        hasReal = true;
                    }
                    return;
                }
                if (comp) {
                    hasReal = true;
                }
            });
            if (!hasReal) {
                col.classList.add('pb-col-empty');
                col.setAttribute('data-pb-col-hint', idx === 0 ? leftHint : rightHint);
            } else {
                col.classList.remove('pb-col-empty');
                col.removeAttribute('data-pb-col-hint');
            }
        });
        doc.querySelectorAll('.pb-surround-slot--banner, .pb-surround-slot--feed').forEach(function (slot) {
            var hasChild = slot.querySelector('.pb-block-host');
            var isBanner = slot.classList.contains('pb-surround-slot--banner');
            if (!hasChild) {
                slot.classList.add('pb-surround-empty');
                slot.setAttribute('data-pb-surround-hint', isBanner ? bannerHint : feedHint);
            } else {
                slot.classList.remove('pb-surround-empty');
                slot.removeAttribute('data-pb-surround-hint');
            }
        });
        doc.querySelectorAll('.pb-block-host[data-pb-component="section"] .bili-sec__body').forEach(function (body) {
            var hasChild = body.querySelector('.pb-block-host');
            if (!hasChild) {
                body.classList.add('pb-section-empty');
                body.setAttribute('data-pb-section-hint', sectionHint);
            } else {
                body.classList.remove('pb-section-empty');
                body.removeAttribute('data-pb-section-hint');
            }
        });
    }

    function injectPreviewBridge(doc) {
        if (!doc.body) {
            return;
        }
        doc.body.classList.add('pb-preview-mode');
        var canLabel = (PB.lang && PB.lang.drop_can) ? PB.lang.drop_can : 'Drop';
        var cannotLabel = (PB.lang && PB.lang.drop_cannot) ? PB.lang.drop_cannot : 'No';
        var css = [
            '.pb-preview-mode .pb-block-host * { pointer-events: none !important; }',
            '.pb-preview-mode .pb-block-host { pointer-events: auto !important; }',
            '.pb-preview-mode .pb-block-host .pb-block-host { pointer-events: auto !important; }',
            'body.pb-preview-mode .pb-surround-slot { pointer-events: auto !important; cursor: pointer !important; position: relative !important; }',
            'body.pb-preview-mode .pb-cf[data-pb-surround-layout] > .pb-surround-slot:not(.pb-surround-empty):hover {',
            '  outline: 2px solid #40cc92 !important; outline-offset: -2px; z-index: 50 !important;',
            '}',
            'body.pb-preview-mode .pb-cf[data-pb-surround-layout] .pb-surround-slot.pb-surround-slot-selected {',
            '  outline: 3px solid #2563eb !important; outline-offset: -3px; z-index: 9999 !important;',
            '}',
            '.pb-preview-mode .pb-block-host[data-pb-component="gbook_form"] input,',
            '.pb-preview-mode .pb-block-host[data-pb-component="gbook_form"] textarea,',
            '.pb-preview-mode .pb-block-host[data-pb-component="gbook_form"] button { pointer-events: auto !important; }',
            '.pb-block-host { cursor: pointer !important; position: relative !important; transition: outline 0.15s ease, box-shadow 0.15s ease; z-index: 1; }',
            '.pb-block-host[data-pb-component="header_decor"] { display: block !important; min-height: 40px; }',
            'body:not(.page-member) .pb-block-host[data-pb-component="site_footer"] { display: block !important; min-height: 48px; }',
            '@media (max-width: 960px) {',
            '  body.page-member.pb-preview-mode .pb-block-host[data-pb-component="site_nav"],',
            '  body.page-member.pb-preview-mode .pb-block-host[data-pb-component="site_footer"] { display: none !important; min-height: 0 !important; }',
            '}',
            '.pb-block-host[data-pb-component="member_shell"] { display: block !important; min-height: 120px; }',
            '.pb-block-host[data-pb-component="auth_page"] { display: block !important; min-height: 120px; }',
            '.pb-block-host:hover { outline: 2px solid #40cc92 !important; outline-offset: -2px; z-index: 50 !important; }',
            '.pb-block-host.pb-block-selected { outline: 3px solid #2563eb !important; outline-offset: -3px; box-shadow: none !important; z-index: 9999 !important; isolation: isolate; }',
            '.pb-block-host.pb-block-selected::after {',
            '  content: attr(data-pb-component); position: absolute; top: 8px; right: 10px; z-index: 10001;',
            '  font-size: 11px; padding: 4px 10px; border-radius: 4px; background: #2563eb; color: #fff;',
            '  pointer-events: none; font-family: system-ui, sans-serif; box-shadow: 0 2px 8px rgba(0,0,0,0.25);',
            '}',
            '.pb-preview-mode.pb-drop-active .pb-block-host { opacity: 0.92; }',
            '.pb-preview-mode.pb-drop-active .pb-block-host.pb-drop-valid-parent {',
            '  outline: 2px dashed rgba(64,204,146,0.65) !important; outline-offset: -2px;',
            '  background: rgba(64,204,146,0.06) !important; z-index: 40 !important;',
            '}',
            '.pb-preview-mode.pb-drop-active .pb-block-host.pb-drop-valid-parent::before {',
            '  content: "' + canLabel.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"; position: absolute; top: 8px; left: 10px; z-index: 10001;',
            '  font-size: 10px; padding: 3px 8px; border-radius: 4px; background: #059669; color: #fff;',
            '  pointer-events: none; font-family: system-ui, sans-serif;',
            '}',
            '.pb-preview-mode.pb-drop-active .pb-block-host.pb-drop-root-marker {',
            '  outline: 1px dashed rgba(64,204,146,0.35) !important; outline-offset: -1px;',
            '}',
            '.pb-preview-mode.pb-drop-active .pb-block-host:not(.pb-drop-valid-parent):not(.pb-drop-root-marker):not(.pb-drop-adjacent) {',
            '  opacity: 0.45 !important; filter: grayscale(0.15);',
            '}',
            '.pb-block-host.pb-drop-adjacent {',
            '  outline: 2px solid rgba(64,204,146,0.5) !important; outline-offset: -2px;',
            '}',
            '.pb-block-host.pb-drop-inside.pb-drop-valid-hover {',
            '  outline: 3px dashed #40cc92 !important; outline-offset: -3px;',
            '  background: rgba(64,204,146,0.12) !important; z-index: 80 !important;',
            '}',
            '.pb-block-host.pb-drop-reject {',
            '  outline: 3px dashed #ef4444 !important; outline-offset: -3px;',
            '  background: rgba(239,68,68,0.08) !important; z-index: 80 !important;',
            '}',
            '.pb-block-host.pb-drop-reject::before {',
            '  content: "' + cannotLabel.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"; position: absolute; top: 8px; left: 10px; z-index: 10001;',
            '  font-size: 10px; padding: 3px 8px; border-radius: 4px; background: #dc2626; color: #fff;',
            '  pointer-events: none; font-family: system-ui, sans-serif;',
            '}',
            'body.pb-preview-mode .pb-block-host[data-pb-component="section"] .bili-sec__body.pb-section-empty {',
            '  min-height: 72px; border: 1px dashed rgba(64,204,146,0.45); border-radius: 8px;',
            '  background: rgba(64,204,146,0.06); position: relative; box-sizing: border-box;',
            '}',
            'body.pb-preview-mode .pb-block-host[data-pb-component="section"] .bili-sec__body.pb-section-empty::after {',
            '  content: attr(data-pb-section-hint); position: absolute; inset: 0; display: flex;',
            '  align-items: center; justify-content: center; padding: 12px; text-align: center;',
            '  font-size: 12px; line-height: 1.45; color: #64748b; font-family: system-ui, sans-serif; pointer-events: none;',
            '}',
            previewResponsiveCss()
        ].join('');
        var existing = doc.getElementById('pb-bridge-style');
        if (existing) {
            existing.textContent = css;
            markEmptyLayoutSlots(doc);
            return;
        }
        var style = doc.createElement('style');
        style.id = 'pb-bridge-style';
        style.textContent = css;
        doc.head.appendChild(style);
        markEmptyLayoutSlots(doc);
    }

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    var pbContextMenuEl = null;
    var pbContextMenuBackdrop = null;
    var pbContextMenuDismissBound = false;
    var pbContextMenuDeleteModel = null;

    function isPbContextMenuOpen() {
        return !!(pbContextMenuEl && !pbContextMenuEl.hidden);
    }

    function hidePbContextMenu() {
        if (pbContextMenuBackdrop) {
            pbContextMenuBackdrop.hidden = true;
        }
        if (!pbContextMenuEl) {
            return;
        }
        pbContextMenuEl.hidden = true;
        pbContextMenuEl.innerHTML = '';
        pbContextMenuDeleteModel = null;
    }

    function onPbContextMenuOutsidePress(e) {
        if (!isPbContextMenuOpen()) {
            return;
        }
        if (pbContextMenuEl.contains(e.target)) {
            return;
        }
        hidePbContextMenu();
    }

    function bindPbContextMenuDismiss() {
        if (pbContextMenuDismissBound) {
            return;
        }
        pbContextMenuDismissBound = true;
        document.addEventListener('mousedown', onPbContextMenuOutsidePress, true);
        document.addEventListener('click', onPbContextMenuOutsidePress, true);
        document.addEventListener('contextmenu', function (e) {
            if (!isPbContextMenuOpen()) {
                return;
            }
            if (!pbContextMenuEl.contains(e.target)) {
                hidePbContextMenu();
            }
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hidePbContextMenu();
            }
        }, true);
        window.addEventListener('scroll', hidePbContextMenu, true);
        window.addEventListener('resize', hidePbContextMenu, true);
    }

    function ensurePbContextMenu() {
        bindPbContextMenuDismiss();
        if (pbContextMenuEl) {
            return pbContextMenuEl;
        }
        pbContextMenuBackdrop = document.createElement('div');
        pbContextMenuBackdrop.id = 'pb-context-menu-backdrop';
        pbContextMenuBackdrop.className = 'pb-context-menu-backdrop';
        pbContextMenuBackdrop.hidden = true;
        pbContextMenuBackdrop.addEventListener('mousedown', function (e) {
            e.preventDefault();
            hidePbContextMenu();
        });
        document.body.appendChild(pbContextMenuBackdrop);

        pbContextMenuEl = document.createElement('div');
        pbContextMenuEl.id = 'pb-context-menu';
        pbContextMenuEl.className = 'pb-context-menu';
        pbContextMenuEl.setAttribute('role', 'menu');
        pbContextMenuEl.hidden = true;
        pbContextMenuEl.addEventListener('click', function (e) {
            var item = e.target.closest('.pb-context-menu__item[data-pb-action="delete"]');
            if (!item || item.disabled || item.classList.contains('is-disabled')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var model = pbContextMenuDeleteModel;
            hidePbContextMenu();
            if (model) {
                deleteEditorModel(model);
            }
        }, true);
        document.body.appendChild(pbContextMenuEl);
        return pbContextMenuEl;
    }

    function resolveDeletableModel(model) {
        if (!model || model.is('wrapper')) {
            return null;
        }
        if (isRegionShellWrapper(model)) {
            var shellChild = model.components().at(0);
            return shellChild || null;
        }
        if (isChromeRegionWrapper(model)) {
            var rn = model.getAttributes()['data-pb-region'] || '';
            if ((rn === 'hero' && pageOwnsHeroRegion()) || (rn === 'channel' && pageOwnsChannelRegion())) {
                var chromeChild = model.components().at(0);
                return chromeChild || null;
            }
            return null;
        }
        return model;
    }

    function canDeleteModel(model) {
        var target = resolveDeletableModel(model);
        if (!target) {
            return { ok: false };
        }
        if (target.get('removable') === false) {
            var hint = (isMemberMode() || isAuthMode())
                ? traitLabel('pb_member_locked_hint', 'This block is fixed for this member page.')
                : traitLabel('pb_chrome_locked_hint', 'Top bar and footer are configured in Theme Design → Site chrome.');
            return { ok: false, hint: hint };
        }
        return { ok: true, model: target };
    }

    function deleteEditorModel(model) {
        if (!editor) {
            return false;
        }
        var check = canDeleteModel(model || editor.getSelected());
        if (!check.ok || !check.model) {
            if (check.hint) {
                showStatus(check.hint);
            }
            return false;
        }
        check.model.remove();
        afterStructureMutation(false);
        return true;
    }

    function clampPbContextMenuPos(menu, x, y) {
        menu.style.visibility = 'hidden';
        menu.hidden = false;
        menu.style.left = '0';
        menu.style.top = '0';
        var w = menu.offsetWidth || 160;
        var h = menu.offsetHeight || 40;
        var maxX = Math.max(8, window.innerWidth - w - 8);
        var maxY = Math.max(8, window.innerHeight - h - 8);
        menu.style.left = Math.min(Math.max(8, x), maxX) + 'px';
        menu.style.top = Math.min(Math.max(8, y), maxY) + 'px';
        menu.style.visibility = '';
    }

    function showPbContextMenu(pageX, pageY, model) {
        if (!editor || !model) {
            return;
        }
        hidePbContextMenu();
        var check = canDeleteModel(model);
        var target = check.model || resolveDeletableModel(model);
        if (target) {
            selectLayerTreeModel(target);
        }
        var menu = ensurePbContextMenu();
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pb-context-menu__item';
        btn.setAttribute('role', 'menuitem');
        btn.setAttribute('data-pb-action', 'delete');
        btn.textContent = traitLabel('pb_delete', 'Delete block');
        if (!check.ok || !check.model) {
            btn.disabled = true;
            btn.classList.add('is-disabled');
            if (check.hint) {
                btn.title = check.hint;
            }
            pbContextMenuDeleteModel = null;
        } else {
            pbContextMenuDeleteModel = check.model;
        }
        menu.appendChild(btn);
        if (pbContextMenuBackdrop) {
            pbContextMenuBackdrop.hidden = false;
        }
        clampPbContextMenuPos(menu, pageX, pageY);
    }

    function iframeClientToPage(clientX, clientY) {
        var ir = getIframeParentRect();
        var scale = getPreviewFrameScale() || 1;
        if (!ir) {
            return { x: clientX, y: clientY };
        }
        return {
            x: ir.left + clientX * scale,
            y: ir.top + clientY * scale
        };
    }

    function handlePreviewContextMenu(e) {
        if (document.body.classList.contains('pb-palette-dragging') || $('#pb-preview-wrap').hasClass('is-preview-drop')) {
            return;
        }
        var doc = getPreviewDoc();
        if (!doc || !doc.body || !editor) {
            return;
        }
        var host = findPreviewHostFromPoint(doc, e.clientX, e.clientY);
        if (!host) {
            host = findPreviewHostFromTarget(e.target, doc);
        }
        if (!host) {
            return;
        }
        host = resolveFilmstripInnerHost(host, e.clientX, e.clientY);
        var path = resolvePreviewSelectPath(host);
        if (!path) {
            return;
        }
        var model = resolveModelFromPreviewPath(path);
        if (!model) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var pos = iframeClientToPage(e.clientX, e.clientY);
        showPbContextMenu(pos.x, pos.y, model);
    }

    function handleLayerTreeContextMenu(e) {
        if (!editor) {
            return;
        }
        if (isLayerVisTarget(e.target) || isLayerCaretTarget(e.target)) {
            return;
        }
        var rowEl = e.target.closest('.gjs-layer-item');
        if (!rowEl) {
            return;
        }
        var layerEl = rowEl.closest('.gjs-layer');
        if (!layerEl) {
            return;
        }
        var model = findModelByLayerEl(layerEl);
        if (!model || model.is('wrapper')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        showPbContextMenu(e.clientX, e.clientY, model);
    }

    function switchToLayersTab() {
        $('.td-pb-side-tab').removeClass('active');
        $('.td-pb-side-tab[data-tab="layers"]').addClass('active');
        $('.td-pb-side-panel').removeClass('active');
        $('#pb-layers-panel').addClass('active');
    }

    function selectByPath(path, fromPreview, hostEl) {
        var model = fromPreview ? resolveModelFromPreviewPath(path) : (findModelByPbPath(path) || getModelByPath(path));
        if (!model) {
            return;
        }
        suppressSave = true;
        editor.select(model);
        suppressSave = false;
        if (fromPreview) {
            skipScrollOnce = true;
            switchToLayersTab();
            highlightPreviewPath(path, hostEl || null);
            highlightLayerForModel(model);
        } else {
            scrollPreviewToPath(path);
        }
    }

    function assignPathsRecursive(parent, prefix) {
        if (!parent) {
            return;
        }
        if (parent.is('wrapper')) {
            parent.components().forEach(function (ch) {
                var rn = ch.getAttributes()['data-pb-region'];
                if (rn) {
                    assignRegionPaths(ch, rn);
                }
            });
            return;
        }
        parent.addAttributes({ 'data-pb-path': prefix });
        var slotBuckets = {};
        parent.components().forEach(function (ch) {
            var sk = ch.getAttributes()['data-slot'] || 'body';
            if (!slotBuckets[sk]) {
                slotBuckets[sk] = 0;
            }
            var childPath = prefix + '/' + sk + '/' + slotBuckets[sk];
            slotBuckets[sk] += 1;
            assignPathsRecursive(ch, childPath);
        });
    }

    function assignRegionPaths(regionModel, regionName) {
        var rprefix = REGION_PATH_PREFIX[regionName] || '';
        regionModel.components().forEach(function (ch, i) {
            var blockPath = rprefix === '' ? String(i) : (rprefix + '/' + i);
            assignPathsRecursive(ch, blockPath);
        });
    }

    function reindexPaths() {
        if (editor) {
            normalizeFilmstripSlots(editor.getWrapper());
        }
        assignPathsRecursive(editor.getWrapper(), '');
    }

    function updateLayerName(model, comp, props) {
        props = props || {};
        if (comp === 'section') {
            model.set('name', '\u200b');
            return;
        }
        var label = props.title || LABELS[comp] || comp;
        if (listUsesSectionHeader(comp)) {
            var sec = getParentSection(model);
            if (sec) {
                var secTitle = sec.get('title');
                if (secTitle) {
                    label = secTitle;
                } else if (props.title) {
                    label = props.title;
                }
            } else if (props.title) {
                label = props.title;
            }
        } else if (comp === 'content_feed') {
            label = bindLabel(props.bind || 'vod');
            if (props.render === 'slider') {
                label += ' · ' + traitLabel('trait_layout_slider', 'Slider');
            } else {
                label += ' · ' + traitLabel('trait_layout_grid', 'Grid');
            }
        } else if (comp === 'rank_feed') {
            label = traitLabel('trait_rank_feed', 'Rank');
            if (props.style === 'inline') {
                label += ' · ' + traitLabel('trait_rank_style_inline', 'Inline');
            } else {
                label += ' · ' + traitLabel('trait_rank_style_panel', 'Panel');
            }
        } else if (comp === 'columns') {
            var cw = columnsWidthsFromProps(props);
            label = (LABELS[comp] || 'Columns') + ' · ' + cw[0] + ':' + cw[1];
        }
        model.set('name', label);
    }

    function normalizeBlockTree(block) {
        if (!block || typeof block !== 'object') {
            return block;
        }
        if (block.component === 'columns' && Array.isArray(block.slots)) {
            var colObj = {};
            block.slots.forEach(function (col, i) {
                if (Array.isArray(col) && col.length) {
                    colObj[String(i)] = col.map(normalizeBlockTree);
                }
            });
            block.slots = colObj;
        } else if (block.slots && typeof block.slots === 'object' && !Array.isArray(block.slots)) {
            var slots = {};
            Object.keys(block.slots).forEach(function (sk) {
                var list = block.slots[sk];
                if (Array.isArray(list) && list.length) {
                    slots[sk] = list.map(normalizeBlockTree);
                }
            });
            block.slots = slots;
        }
        if (block.component === 'columns') {
            finalizeColumnsBlock(block);
        }
        if (block.component === 'filmstrip_layout') {
            finalizeFilmstripLayoutBlock(block);
        }
        return block;
    }

    function finalizeFilmstripLayoutBlock(block) {
        if (!block || block.component !== 'filmstrip_layout') {
            return block;
        }
        block.slots = block.slots || {};
        var banner = Array.isArray(block.slots.banner) ? block.slots.banner : [];
        var feed = Array.isArray(block.slots.feed) ? block.slots.feed : [];
        var bannerOut = [];
        banner.forEach(function (child) {
            if (!child || child.component !== 'vod_carousel' || bannerOut.length) {
                return;
            }
            bannerOut.push(child);
        });
        var feedOut = [];
        feed.forEach(function (child) {
            if (!child || !isFilmstripFeedComp(child.component) || feedOut.length) {
                return;
            }
            child.props = child.props || {};
            child.props.render = 'filmstrip';
            if (!child.props.card) {
                child.props.card = 'poster-h';
            }
            if (!child.props.ratio || child.props.ratio === 'default') {
                child.props.ratio = '69-40';
            }
            if (!child.props.num) {
                child.props.num = 12;
            }
            if (!child.props.by) {
                child.props.by = 'hits_day';
            }
            if (child.component === 'content_feed' && !child.props.bind) {
                child.props.bind = 'vod';
            }
            feedOut.push(child);
        });
        block.slots.banner = bannerOut;
        block.slots.feed = feedOut;
    }

    function finalizeColumnsBlock(block) {
        if (!block || block.component !== 'columns') {
            return block;
        }
        block.props = block.props || {};
        // 历史 bug：只往一列塞内容时 PHP 曾把 cols 收成 [12]，预览变单列。
        // 双栏组件始终按至少两列恢复。
        if (!block.props.cols || !block.props.cols.length || block.props.cols.length < 2) {
            block.props.cols = [8, 4];
        }
        if (!block.props.stack_at) {
            block.props.stack_at = 720;
        }
        var colCount = block.props.cols.length;
        var normalized = {};
        var i;
        for (i = 0; i < colCount; i++) {
            normalized[String(i)] = [];
        }
        var raw = block.slots;
        if (raw && typeof raw === 'object') {
            Object.keys(raw).forEach(function (sk) {
                var list = raw[sk];
                if (!Array.isArray(list)) {
                    return;
                }
                var key = String(sk);
                // 兼容旧草稿把整列又包了一层数组
                if (list.length === 1 && Array.isArray(list[0]) && !list[0].component) {
                    list = list[0];
                }
                if (key === 'body') {
                    key = '0';
                }
                if (!normalized[key]) {
                    key = String(Math.max(colCount - 1, 0));
                    if (!normalized[key]) {
                        normalized[key] = [];
                    }
                }
                list.forEach(function (child) {
                    if (child && child.component) {
                        normalized[key].push(child);
                    }
                });
            });
        }
        block.slots = normalized;
        return block;
    }


    function componentToBlock(model) {
        var comp = model.getAttributes()['data-component'] || '';
        var block = { component: comp };
        var props = {};
        persistPropNames(comp, model).forEach(function (k) {
            var v = model.get(k);
            if (k === 'type_ids' && Array.isArray(v)) {
                props[k] = v.map(function (id) { return parseInt(id, 10); }).filter(function (id) { return !isNaN(id) && id > 0; });
                return;
            }
            if (k === 'items' && Array.isArray(v) && comp === 'bottom_nav') {
                props[k] = normalizeBottomNavItems({ items: v });
                return;
            }
            if (k === 'items' && Array.isArray(v) && comp === 'ads_grid') {
                props[k] = normalizeAdsGridItems({ items: v });
                return;
            }
            if (typeof v === 'boolean') {
                props[k] = v;
                return;
            }
            if (k === 'type' && (v === 0 || v === '0')) {
                props[k] = 0;
                return;
            }
            if (v !== undefined && v !== null && v !== '') {
                if (k === 'type' || k === 'num' || k === 'stack_at' || k === 'col_left' || k === 'col_right') {
                    props[k] = parseInt(v, 10);
                    if (isNaN(props[k])) {
                        delete props[k];
                    }
                } else {
                    props[k] = v;
                }
            }
        });
        // section 头字段允许为空字符串（清空后仍要落盘，否则下次又被默认值填回）
        if (comp === 'section') {
            SECTION_HEADER_KEYS.forEach(function (k) {
                var v = model.get(k);
                props[k] = (v === undefined || v === null) ? '' : v;
            });
            var tk = model.get('title_key');
            if (tk) {
                props.title_key = tk;
            }
        }
        if (comp === 'columns') {
            var colLeft = clampColWidth(model.get('col_left'), 8);
            var colRight = clampColWidth(model.get('col_right'), 4);
            props.cols = [colLeft, colRight];
            var stackAt = parseInt(model.get('stack_at'), 10);
            if (!isNaN(stackAt) && stackAt >= 320 && stackAt <= 1600) {
                props.stack_at = stackAt;
            }
            delete props.col_left;
            delete props.col_right;
        }
        // header_decor.image 允许空串落盘；并兜底从侧栏读取，避免模型未同步时丢图
        if (comp === 'header_decor') {
            var decorImg = model.get('image');
            if (decorImg === undefined || decorImg === null || decorImg === '') {
                decorImg = String($('#pbDecorPath').val() || '').trim();
            }
            props.image = decorImg === undefined || decorImg === null ? '' : String(decorImg);
            if (props.width === undefined || props.width === null || props.width === '') {
                props.width = '100%';
            }
            var decorH = parseInt(model.get('height'), 10);
            props.height = (!isNaN(decorH) && decorH >= 0) ? Math.min(decorH, 800) : 72;
        }
        if (Object.keys(props).length) {
            block.props = props;
        }
        var children = model.components();
        if (children.length) {
            block.slots = {};
            children.forEach(function (child) {
                if (child.get('visible') === false) {
                    return;
                }
                var sk = child.getAttributes()['data-slot'] || 'body';
                if (!block.slots[sk]) {
                    block.slots[sk] = [];
                }
                block.slots[sk].push(componentToBlock(child));
            });
        }
        if (block.component === 'columns') {
            finalizeColumnsBlock(block);
        }
        return block;
    }

    function defaultChromeBlocks() {
        var ids = PB.navbarTypeIds || [];
        return [
            {
                component: 'site_nav',
                props: { max: 8, show_search: true, type_ids: ids }
            },
            {
                component: 'header_decor',
                props: { width: '100%', height: 120, image: '' }
            },
            {
                component: 'channel_strip',
                props: { compact: false, chip_limit: 11, show_icons: true, show_more: true, type_ids: ids }
            }
        ];
    }

    function defaultFooterBlock() {
        return {
            component: 'site_footer',
            props: {
                variant: 'default',
                copyright: '',
                show_nav: true,
                show_friends: true,
                logo: ''
            }
        };
    }

    function normalizeFooterBlock(footer) {
        var def = defaultFooterBlock();
        if (!footer || !footer.component) {
            return def;
        }
        footer = normalizeBlockTree(footer);
        if (footer.component !== 'site_footer') {
            return def;
        }
        footer.props = footer.props || {};
        Object.keys(def.props).forEach(function (k) {
            if (footer.props[k] === undefined || footer.props[k] === null || footer.props[k] === '') {
                footer.props[k] = def.props[k];
            }
        });
        return footer;
    }

    function defaultBottomNavItems() {
        return [
            { id: 'home', label: '首页', href: '', icon: 'home' },
            { id: 'video', label: '视频', href: '', icon: 'video' },
            { id: 'novel', label: '小说', href: '', icon: 'book' },
            { id: 'manga', label: '漫画', href: '', icon: 'manga' },
            { id: 'me', label: '会员', href: '', icon: 'user' }
        ];
    }

    function isLegacyStockBottomNavItems(items) {
        if (!Array.isArray(items) || items.length !== 4) {
            return false;
        }
        var expect = [
            { id: 'home', label: '首页', icon: 'home' },
            { id: 'catalog', label: '片库', icon: 'grid' },
            { id: 'discover', label: '发现', icon: 'compass' },
            { id: 'me', label: '会员', icon: 'user' }
        ];
        for (var i = 0; i < 4; i++) {
            var item = items[i] || {};
            var href = String(item.href != null ? item.href : '').trim();
            if (String(item.id || '') !== expect[i].id
                || String(item.label || '').trim() !== expect[i].label
                || String(item.icon || '') !== expect[i].icon
                || (href !== '' && href !== '/')) {
                return false;
            }
        }
        return true;
    }

    function normalizeBottomNavItems(props) {
        props = props || {};
        var icons = { home: 1, video: 1, book: 1, manga: 1, user: 1, grid: 1, compass: 1 };
        var iconById = {
            home: 'home',
            video: 'video',
            vod: 'video',
            catalog: 'video',
            novel: 'book',
            art: 'book',
            manga: 'manga',
            discover: 'compass',
            me: 'user'
        };
        var raw = [];
        if (Array.isArray(props.items) && props.items.length) {
            raw = isLegacyStockBottomNavItems(props.items) ? defaultBottomNavItems() : props.items;
        } else {
            var slots = ['home', 'catalog', 'discover', 'me'];
            var legacyIcons = { home: 'home', catalog: 'grid', discover: 'compass', me: 'user' };
            var legacyLabels = { home: '首页', catalog: '片库', discover: '发现', me: '会员' };
            var hasLegacy = false;
            slots.forEach(function (slot) {
                if (props[slot + '_label'] != null || props[slot + '_href'] != null || props[slot + '_icon'] != null) {
                    hasLegacy = true;
                }
            });
            if (hasLegacy) {
                var touched = false;
                slots.forEach(function (slot) {
                    var label = props[slot + '_label'] != null ? String(props[slot + '_label']).trim() : legacyLabels[slot];
                    var href = props[slot + '_href'] != null ? String(props[slot + '_href']).trim() : '';
                    var icon = props[slot + '_icon'] != null ? String(props[slot + '_icon']).trim() : legacyIcons[slot];
                    if (label !== legacyLabels[slot] || (href !== '' && href !== '/') || icon !== legacyIcons[slot]) {
                        touched = true;
                    }
                });
                if (!touched) {
                    return defaultBottomNavItems();
                }
                slots.forEach(function (slot) {
                    raw.push({
                        id: slot,
                        label: props[slot + '_label'] != null ? String(props[slot + '_label']) : legacyLabels[slot],
                        href: props[slot + '_href'] != null ? String(props[slot + '_href']) : '',
                        icon: props[slot + '_icon'] != null ? String(props[slot + '_icon']) : legacyIcons[slot]
                    });
                });
            } else {
                return defaultBottomNavItems();
            }
        }
        var out = [];
        var used = {};
        raw.forEach(function (item, idx) {
            if (out.length >= 6 || !item || typeof item !== 'object') {
                return;
            }
            var id = String(item.id || '').replace(/[^a-z0-9_\-]/gi, '');
            if (!id) {
                id = 'item_' + (idx + 1);
            }
            if (used[id]) {
                id = id + '_' + (idx + 1);
            }
            used[id] = 1;
            var label = String(item.label != null ? item.label : '').trim() || '菜单';
            if (label.length > 20) {
                label = label.slice(0, 20);
            }
            var href = String(item.href != null ? item.href : '').trim();
            if (href === '/') {
                href = '';
            }
            var icon = String(item.icon || '').trim();
            if (!icon || !icons[icon]) {
                icon = iconById[id] || 'home';
            } else if (iconById[id]) {
                var want = iconById[id];
                if ((id === 'video' || id === 'vod' || id === 'catalog') && icon === 'grid') {
                    icon = want;
                } else if (id === 'manga' && icon === 'compass') {
                    icon = want;
                } else if ((id === 'novel' || id === 'art') && (icon === 'compass' || icon === 'grid')) {
                    icon = want;
                }
            }
            if (!icons[icon]) {
                icon = 'home';
            }
            out.push({ id: id, label: label, href: href, icon: icon });
        });
        return out.length ? out : defaultBottomNavItems();
    }

    function defaultBottomNavBlock() {
        return {
            component: 'bottom_nav',
            props: {
                items: defaultBottomNavItems()
            }
        };
    }

    function normalizeBottomNavBlock(block) {
        var def = defaultBottomNavBlock();
        if (!block || block.component !== 'bottom_nav') {
            return def;
        }
        block = normalizeBlockTree(block);
        block.props = {
            items: normalizeBottomNavItems(block.props || {})
        };
        return block;
    }

    function normalizeFooterBlocks(blocks) {
        var byComp = {};
        (blocks || []).forEach(function (block) {
            if (block && block.component) {
                byComp[block.component] = block;
            }
        });
        return [
            normalizeFooterBlock(byComp.site_footer || null),
            normalizeBottomNavBlock(byComp.bottom_nav || null)
        ];
    }

    function normalizeChromeBlocks(chrome) {
        var saved = {};
        (chrome || []).forEach(function (b) {
            if (b && b.component) {
                saved[b.component] = normalizeBlockTree(b);
            }
        });
        return defaultChromeBlocks().map(function (def) {
            if (saved[def.component]) {
                var merged = saved[def.component];
                merged.props = merged.props || {};
                def.props = def.props || {};
                Object.keys(def.props).forEach(function (k) {
                    if (merged.props[k] === undefined || merged.props[k] === null || merged.props[k] === '') {
                        merged.props[k] = def.props[k];
                    }
                });
                if (def.component === 'header_decor') {
                    var h = parseInt(merged.props.height, 10);
                    merged.props.height = (isNaN(h) || h < 0) ? def.props.height : Math.min(h, 800);
                }
                return merged;
            }
            return def;
        });
    }

    function regionContentBlocks(page) {
        page = page || {};
        if (page.regions && Array.isArray(page.regions.content)) {
            return page.regions.content;
        }
        return Array.isArray(page.blocks) ? page.blocks : [];
    }

    function pageContentBlockCount(page) {
        return regionContentBlocks(page).length;
    }

    function collectPage() {
        flushChromePanelsToModels();
        var page = PB.page || {};
        var regions = {};
        var ownsHero = pageOwnsHeroRegion();
        var ownsChannel = pageOwnsChannelRegion();
        var regionNames = effectiveRegionNames(page);
        regionNames.forEach(function (name) {
            if (isChromeRegion(name)) {
                if (name === 'footer') {
                    regions.footer = [];
                    return;
                }
                // 页面自有 hero 要落盘（含空数组，表示用户已清空）
                if (name === 'hero' && ownsHero) {
                    regions[name] = [];
                }
                if (name === 'channel' && ownsChannel) {
                    regions[name] = [];
                }
                return;
            }
            regions[name] = [];
        });
        editor.getWrapper().components().forEach(function (regionModel) {
            var rn = regionModel.getAttributes()['data-pb-region'];
            if (!rn) {
                return;
            }
            if (!regions.hasOwnProperty(rn)) {
                return;
            }
            regionModel.components().forEach(function (m) {
                var block = normalizeBlockTree(componentToBlock(m));
                if (block && block.component && !isShellComponent(block.component)) {
                    regions[rn].push(block);
                }
            });
        });
        var out = {
            template: page.template || PB.route || 'index/index',
            layout: page.layout || 'base',
            layout_mod: page.layout_mod || (PB.route === 'index/index' ? 'home' : ''),
            page_type: page.page_type || PB.pageType || '',
            regions: regions
        };
        if (page.layout_compile && typeof page.layout_compile === 'object') {
            out.layout_compile = $.extend({}, page.layout_compile);
        }
        if (page.extra_page_class) {
            out.extra_page_class = page.extra_page_class;
        }
        if (page.twig_sets && typeof page.twig_sets === 'object') {
            out.twig_sets = $.extend({}, page.twig_sets);
        }
        if (ownsChannel) {
            var opts = (page.chrome_opts && typeof page.chrome_opts === 'object') ? $.extend({}, page.chrome_opts) : {};
            var hasChannel = false;
            (regions.channel || []).forEach(function (b) {
                if (b && b.component === 'channel_strip') {
                    hasChannel = true;
                }
            });
            opts.channel_strip = hasChannel;
            out.chrome_opts = opts;
            out.chrome = [];
            (regions.channel || []).forEach(function (b) {
                if (b && b.component) {
                    out.chrome.push(b);
                }
            });
        }
        if (ownsHero) {
            var heroOpts = (out.chrome_opts && typeof out.chrome_opts === 'object')
                ? $.extend({}, out.chrome_opts)
                : ((page.chrome_opts && typeof page.chrome_opts === 'object') ? $.extend({}, page.chrome_opts) : {});
            var hasDecor = false;
            (regions.hero || []).forEach(function (b) {
                if (b && b.component === 'header_decor') {
                    hasDecor = true;
                }
            });
            heroOpts.header_decor = hasDecor;
            out.chrome_opts = heroOpts;
            if (!out.chrome) {
                out.chrome = [];
            }
            (regions.hero || []).forEach(function (b) {
                if (b && b.component === 'header_decor') {
                    out.chrome.unshift(b);
                }
            });
        }
        return stripAppTopChromeFromCollectedPage(out);
    }

    function showStatus(msg, ok) {
        var $s = $('#pb-status');
        if (!$s.length) {
            $s = $('<div id="pb-status" class="td-pb-status"></div>').appendTo('body');
        }
        $s.text(msg).toggleClass('is-ok', ok !== false).toggleClass('is-err', ok === false).addClass('show');
        setTimeout(function () { $s.removeClass('show is-ok is-err'); }, 2200);
        if (typeof layui !== 'undefined') {
            layui.use('layer', function () {
                layui.layer.msg(msg, { icon: ok === false ? 2 : 1, time: 1800, zIndex: 20000002 });
            });
        }
    }

    function pbCsrfToken() {
        if (typeof window.__MAC_ADMIN_CSRF__ === 'string' && window.__MAC_ADMIN_CSRF__) {
            return window.__MAC_ADMIN_CSRF__;
        }
        var meta = document.querySelector('meta[name="mac-admin-csrf"]');
        return (meta && meta.getAttribute('content')) ? meta.getAttribute('content') : '';
    }

    function pbSavePostData(extra) {
        var data = extra || {};
        var token = pbCsrfToken();
        if (token) {
            data.__token__ = token;
        }
        return data;
    }

    function normalizePreviewUrl(url) {
        url = String(url || '').trim();
        if (!url) {
            return '';
        }
        return url.replace(/&amp;/g, '&');
    }

    function resolvePreviewBaseUrl() {
        var url = normalizePreviewUrl(PB.previewUrl || '');
        if (isMemberMode() && $('#pb-member-mock').length) {
            var mock = $('#pb-member-mock').val() || 'member';
            url = url.replace(/([?&])td_member_mock=[^&]*/g, '$1').replace(/[?&]$/, '');
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'td_member_mock=' + encodeURIComponent(mock);
        }
        return url;
    }

    function fetchPreviewUrl() {
        if (!PB.urls || !PB.urls.preview) {
            return $.Deferred().resolve(normalizePreviewUrl(PB.previewUrl || '')).promise();
        }
        if (previewUrlFetch) {
            return previewUrlFetch;
        }
        previewUrlFetch = $.get(PB.urls.preview, {
            theme: PB.theme,
            route: PB.route || 'index/index'
        }).then(function (res) {
            previewUrlFetch = null;
            if (res && res.code === 1 && res.url) {
                PB.previewUrl = normalizePreviewUrl(res.url);
                return PB.previewUrl;
            }
            return normalizePreviewUrl(PB.previewUrl || '');
        }, function () {
            previewUrlFetch = null;
            return normalizePreviewUrl(PB.previewUrl || '');
        });
        return previewUrlFetch;
    }

    function bootPreview(forceFetch) {
        var run = function () {
            refreshPreview();
        };
        // 登录弹窗虚拟路由也要拉首页 + open_auth_modal=1，否则 iframe 只剩初始首页
        if (forceFetch || !resolvePreviewBaseUrl() || isAuthModalMode()) {
            fetchPreviewUrl().always(run);
            return;
        }
        run();
    }

    function authModalFieldHtml(prefix, label, value) {
        return '<label class="pb-auth-modal-field"><span>' + escHtml(label) + '</span>' +
            '<input type="text" class="pb-auth-modal-input" data-auth-key="' + escHtml(prefix) + '" value="' + escHtml(value || '') + '"></label>';
    }

    function authModalImageFieldHtml(prefix, label, value) {
        var key = escHtml(prefix);
        var previewHidden = value ? '' : ' hidden';
        var previewSrc = value ? ' src="' + escHtml(pbRootedSrc(value)) + '"' : '';
        return '<div class="pb-auth-modal-field pb-auth-modal-field--image">' +
            '<span>' + escHtml(label) + '</span>' +
            '<img class="pb-auth-modal-image-preview" data-auth-preview="' + key + '" alt=""' + previewSrc + previewHidden + '>' +
            '<div class="pb-auth-modal-image-tools">' +
            '<button type="button" class="pb-auth-modal-image-upload" data-auth-key="' + key + '"><i class="layui-icon">&#xe67c;</i> ' + escHtml(PB.lang.upload || 'Upload') + '</button>' +
            '<button type="button" class="pb-auth-modal-image-clear" data-auth-key="' + key + '">' + escHtml(PB.lang.clear || 'Clear') + '</button>' +
            '</div>' +
            '<input type="text" class="pb-auth-modal-input" data-auth-key="' + key + '" value="' + escHtml(value || '') + '" placeholder="' + escHtml(PB.lang.image_path_ph || '') + '">' +
            '</div>';
    }

    function bindAuthModalImageUploads() {
        $('#pb-auth-modal-traits .pb-auth-modal-image-upload').each(function () {
            var $btn = $(this);
            if ($btn.data('pbUploadBound')) {
                return;
            }
            $btn.data('pbUploadBound', 1);
            var key = $btn.data('auth-key');
            bindPbUploadButton(this, function (path) {
                var $input = $('#pb-auth-modal-traits .pb-auth-modal-input[data-auth-key="' + key + '"]');
                $input.val(path);
                syncPbImageFieldPreview($('#pb-auth-modal-traits .pb-auth-modal-image-preview[data-auth-preview="' + key + '"]'), path);
                scheduleAutoSave(true);
            });
        });
    }

    function initAuthModalEditor() {
        var cfg = PB.authModalConfig || {};
        var login = cfg.login || {};
        var find = cfg.findpass || {};
        var html = '<div class="pb-auth-modal-form">' +
            '<h3 class="pb-auth-modal-form__title">' + escHtml(traitLabel('pb_auth_modal_login', 'Login popup')) + '</h3>' +
            authModalFieldHtml('login.welcome', traitLabel('trait_welcome', 'Welcome'), login.welcome) +
            authModalFieldHtml('login.hint', traitLabel('trait_hint', 'Hint'), login.hint) +
            authModalFieldHtml('login.title_text', traitLabel('trait_title', 'Title'), login.title_text) +
            authModalFieldHtml('login.subtitle', traitLabel('trait_subtitle', 'Subtitle'), login.subtitle) +
            authModalImageFieldHtml('login.hero_image', traitLabel('trait_hero_image', 'Hero image'), login.hero_image) +
            authModalImageFieldHtml('login.hero_image_mobile', traitLabel('trait_hero_image_mobile', 'Mobile hero image'), login.hero_image_mobile) +
            '<h3 class="pb-auth-modal-form__title">' + escHtml(traitLabel('pb_auth_modal_findpass', 'Find password popup')) + '</h3>' +
            authModalFieldHtml('findpass.welcome', traitLabel('trait_welcome', 'Welcome'), find.welcome) +
            authModalFieldHtml('findpass.hint', traitLabel('trait_hint', 'Hint'), find.hint) +
            authModalFieldHtml('findpass.title_text', traitLabel('trait_title', 'Title'), find.title_text) +
            authModalFieldHtml('findpass.subtitle', traitLabel('trait_subtitle', 'Subtitle'), find.subtitle) +
            authModalImageFieldHtml('findpass.hero_image', traitLabel('trait_hero_image', 'Hero image'), find.hero_image) +
            authModalImageFieldHtml('findpass.hero_image_mobile', traitLabel('trait_hero_image_mobile', 'Mobile hero image'), find.hero_image_mobile) +
            '</div>';
        $('#pb-auth-modal-traits').html(html).show();
        $('#pb-traits-empty').hide();
        $('#pb-traits-panel').hide();
        bindAuthModalImageUploads();
        $('#pb-auth-modal-traits').off('input.pbauth').on('input.pbauth', '.pb-auth-modal-input', function () {
            var key = $(this).data('auth-key') || '';
            syncPbImageFieldPreview($('#pb-auth-modal-traits .pb-auth-modal-image-preview[data-auth-preview="' + key + '"]'), $(this).val());
            scheduleAutoSave(true);
        });
        $('#pb-auth-modal-traits').off('click.pbauthclear').on('click.pbauthclear', '.pb-auth-modal-image-clear', function () {
            var key = $(this).data('auth-key') || '';
            var $input = $('#pb-auth-modal-traits .pb-auth-modal-input[data-auth-key="' + key + '"]');
            $input.val('');
            syncPbImageFieldPreview($('#pb-auth-modal-traits .pb-auth-modal-image-preview[data-auth-preview="' + key + '"]'), '');
            scheduleAutoSave(true);
        });
    }

    function collectAuthModalForm() {
        var cfg = $.extend(true, {}, PB.authModalConfig || {});
        if (!cfg.login) {
            cfg.login = {};
        }
        if (!cfg.findpass) {
            cfg.findpass = {};
        }
        $('#pb-auth-modal-traits .pb-auth-modal-input').each(function () {
            var key = $(this).data('auth-key') || '';
            var parts = String(key).split('.');
            if (parts.length !== 2) {
                return;
            }
            cfg[parts[0]][parts[1]] = $(this).val();
        });
        return cfg;
    }

    function saveAuthModal(quiet, refresh) {
        var data = collectAuthModalForm();
        return $.post(PB.urls.saveStep, pbSavePostData({
            theme: PB.theme,
            step: 'auth_modal',
            data: JSON.stringify(data)
        })).then(function (res) {
            if (res && res.code === 1) {
                PB.authModalConfig = data;
                if (res.preview_url) {
                    PB.previewUrl = res.preview_url;
                }
                if (!quiet) {
                    showStatus(PB.lang.saved || 'Saved', true);
                }
                if (refresh) {
                    refreshPreview();
                }
                return true;
            }
            if (!quiet) {
                showStatus((res && res.msg) ? res.msg : (PB.lang.save_err || 'Save failed'), false);
            }
            return false;
        }).fail(function () {
            if (!quiet) {
                showStatus(PB.lang.save_err_net || 'Network error', false);
            }
            return false;
        });
    }

    function refreshPreview() {
        var base = resolvePreviewBaseUrl();
        if (!base) {
            return;
        }
        capturePreviewScroll();
        previewSeq += 1;
        var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + '_=' + (Date.now() + '' + previewSeq);
        var $iframe = $('#pb-preview');
        $iframe.off('load.pbwire').on('load.pbwire', function () {
            restorePreviewScroll();
            wirePreviewIframe();
            scheduleFitPreviewScale();
            verifyPreviewLoaded();
        });
        $iframe.removeClass('td-pb-preview--idle');
        $iframe.attr('src', url);
    }

    function verifyPreviewLoaded() {
        var doc = getPreviewDoc();
        if (!doc || !doc.body) {
            return;
        }
        if (isAuthModalMode()) {
            if (doc.querySelector('[data-auth-modal="login"]') || doc.body.classList.contains('has-auth-modal')) {
                $('#pb-preview-wrap').removeClass('is-preview-error');
            } else if (!PB._previewRetried) {
                PB._previewRetried = true;
                fetchPreviewUrl().done(function () { refreshPreview(); });
            } else {
                $('#pb-preview-wrap').addClass('is-preview-error');
            }
            return;
        }
        if (doc.querySelector('.pb-block-host')) {
            $('#pb-preview-wrap').removeClass('is-preview-error');
            return;
        }
        if (PB._previewRetried) {
            $('#pb-preview-wrap').addClass('is-preview-error');
            showStatus(traitLabel('pb_preview_no_markers', 'Preview draft markers missing — refresh preview or re-open builder'), false);
            return;
        }
        PB._previewRetried = true;
        showStatus(traitLabel('pb_preview_retry', 'Refreshing preview draft…'), true);
        fetchPreviewUrl().done(function () {
            refreshPreview();
        });
    }

    function saveDraft(quiet, refresh) {
        if (isAuthModalMode()) {
            return saveAuthModal(quiet, refresh);
        }
        if (saveTimer) {
            clearTimeout(saveTimer);
            saveTimer = null;
        }
        if (editor) {
            reindexPaths();
        }
        var page = collectPage();
        var route = PB.route || 'index/index';
        var prevCount = pageContentBlockCount(PB.page);
        var nextCount = (page.regions && page.regions.content) ? page.regions.content.length : 0;
        if (prevCount > 0 && nextCount === 0 && !isRouteLockedMode()) {
            if (refresh) {
                refreshPreview();
            }
            if (!quiet) {
                showStatus(traitLabel('pb_save_empty_blocked', 'Save skipped: page content is empty'));
            }
            return $.Deferred().resolve(false).promise();
        }
        return $.post(PB.urls.saveStep, pbSavePostData({
            theme: PB.theme,
            step: 'page',
            route: route,
            data: JSON.stringify(page)
        })).then(function (res) {
            if (res && res.code === 1) {
                if (res.page && typeof res.page === 'object') {
                    PB.page = res.page;
                } else {
                    PB.page = page;
                }
                if (res.preview_url) {
                    PB.previewUrl = normalizePreviewUrl(res.preview_url);
                }
                if (!quiet) {
                    showStatus(PB.lang.saved || 'Saved', true);
                }
                if (refresh) {
                    refreshPreview();
                }
                return true;
            }
            if (refresh) {
                refreshPreview();
            }
            if (!quiet) {
                showStatus((res && res.msg) ? res.msg : (PB.lang.save_err || 'Save failed'), false);
            }
            return false;
        }).fail(function (xhr) {
            if (refresh) {
                refreshPreview();
            }
            if (!quiet) {
                var msg = PB.lang.save_err_net || 'Network error';
                if (xhr && xhr.status === 403) {
                    msg = PB.lang.save_err || 'Save failed (403 — refresh admin and retry)';
                }
                showStatus(msg, false);
            }
            return false;
        });
    }

    function scheduleAutoSave(needPreview) {
        if (loading || suppressSave) {
            return;
        }
        if (isAuthModalMode() && !$('#pb-auth-modal-traits .pb-auth-modal-input').length) {
            return;
        }
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        var refresh = needPreview !== false;
        saveTimer = setTimeout(function () {
            if (refresh) {
                capturePreviewScroll();
            }
            saveDraft(true, refresh);
        }, 700);
    }

    function toggleTraitsEmpty() {
        if (!editor) {
            return;
        }
        if (isAuthModalMode()) {
            showAuthModalTraitsPanel();
            return;
        }
        var sel = editor.getSelected();
        if (sel && sel.getAttributes()['data-component']) {
            $('#pb-traits-empty').hide();
            $('#pb-traits-panel').show();
        } else {
            $('#pb-traits-panel').hide();
            $('#pb-traits-empty').show();
        }
    }

    function registerComponentType() {
        editor.DomComponents.addType('pb-region', {
            model: {
                defaults: {
                    tagName: 'div',
                    draggable: false,
                    removable: false,
                    copyable: false,
                    layerable: true,
                    highlightable: true,
                    attributes: { class: 'pb-region-wrap', 'data-pb-region': '' },
                    droppable: function (dragged) {
                        var childComp = dragged.getAttributes()['data-component'];
                        if (!childComp) {
                            return false;
                        }
                        var regionName = this.getAttributes()['data-pb-region'] || '';
                        return regionAllowsDirectChild(regionName, childComp);
                    }
                },
                init: function () {
                    var rn = this.getAttributes()['data-pb-region'] || '';
                    this.set('name', regionLabel(rn));
                }
            },
            view: {
                onRender: function () {
                    var rn = this.model.getAttributes()['data-pb-region'] || '';
                    this.el.innerHTML = '<div class="pb-region-label">' + escHtml(regionLabel(rn)) + '</div>';
                }
            }
        });

        editor.DomComponents.addType('mcms-block', {
            model: {
                defaults: {
                    tagName: 'div',
                    draggable: true,
                    removable: true,
                    layerable: true,
                    type_ids: [],
                    logo: '',
                    image: '',
                    droppable: function (dragged) {
                        var childComp = dragged.getAttributes()['data-component'];
                        if (!childComp) {
                            return false;
                        }
                        var parent = this;
                        if (parent.is('wrapper')) {
                            return false;
                        }
                        var parentComp = parent.getAttributes()['data-component'];
                        var parentRegion = parent.getAttributes()['data-pb-region'] || '';
                        if (parentRegion && !parentComp) {
                            return regionAllowsDirectChild(parentRegion, childComp);
                        }
                        return canDropChildInParent(childComp, parentComp, false, parent);
                    },
                    attributes: { class: 'mcms-pb-item', 'data-component': '' },
                    traits: []
                },
                init: function () {
                    var self = this;
                    var bindProps = function (comp) {
                        modelPropNames(comp).forEach(function (k) {
                            self.off('change:' + k);
                            self.on('change:' + k, function () {
                                var c = self.getAttributes()['data-component'] || '';
                                var props = {};
                                modelPropNames(c).forEach(function (n) {
                                    props[n] = self.get(n);
                                });
                                updateLayerName(self, c, props);
                                if (listUsesSectionHeader(c)) {
                                    pushSectionHeaderFromList(self);
                                }
                                if (!loading && !suppressSave) {
                                    scheduleAutoSave(true);
                                    updateUndoRedoButtons();
                                }
                            });
                        });
                    };
                    bindProps(self.getAttributes()['data-component'] || '');
                    self.on('change:attributes', function () {
                        bindProps(self.getAttributes()['data-component'] || '');
                    });
                }
            },
            view: {
                events: {
                    dblclick: 'onDblClick'
                },
                onRender: function () {
                    var model = this.model;
                    var el = this.el;
                    var comp = model.getAttributes()['data-component'] || '?';
                    var name = model.get('name') || LABELS[comp] || comp;
                    if (comp === 'columns' && !model.components().length) {
                        var leftHint = traitLabel('col_drop_left', 'Left — lists, rankings…');
                        var rightHint = traitLabel('col_drop_right', 'Right — lists, rankings…');
                        el.innerHTML = '<div class="mcms-pb-columns-empty">' +
                            '<div class="mcms-pb-columns-empty__head"><strong>' + escHtml(name) + '</strong><span>columns</span></div>' +
                            '<div class="mcms-pb-columns-empty__cols">' +
                            '<div class="mcms-pb-columns-empty__col">' + escHtml(leftHint) + '</div>' +
                            '<div class="mcms-pb-columns-empty__col">' + escHtml(rightHint) + '</div>' +
                            '</div></div>';
                        return;
                    }
                    if (comp === 'filmstrip_layout' && !model.components().length) {
                        var bannerHint = traitLabel('filmstrip_drop_banner', 'Carousel — drop video carousel');
                        var feedHint = traitLabel('filmstrip_drop_feed', 'Cards — drop video list (surround)');
                        el.innerHTML = '<div class="mcms-pb-filmstrip-empty">' +
                            '<div class="mcms-pb-filmstrip-empty__head"><strong>' + escHtml(name) + '</strong><span>filmstrip</span></div>' +
                            '<div class="mcms-pb-filmstrip-empty__slots">' +
                            '<div class="mcms-pb-filmstrip-empty__slot">' + escHtml(bannerHint) + '</div>' +
                            '<div class="mcms-pb-filmstrip-empty__slot">' + escHtml(feedHint) + '</div>' +
                            '</div></div>';
                        return;
                    }
                    el.innerHTML = '<div class="mcms-pb-card"><strong>' + escHtml(name) + '</strong><span>' + escHtml(comp) + '</span></div>';
                },
                onDblClick: function (e) {
                    e.preventDefault();
                }
            }
        });
    }

    function registerBlocks() {
        buildPaletteMap();
        if (isRouteLockedMode()) {
            $('#pb-blocks-panel').html(
                '<div class="pb-member-mode-empty">' + escHtml(traitLabel('pb_member_mode_hint', 'Member pages use fixed layout. Edit properties on the right.')) + '</div>'
            );
            return;
        }
        (PB.palette || []).forEach(function (cat) {
            (cat.blocks || []).forEach(function (b) {
                if (!b.block || !b.block.component) {
                    return;
                }
                var kind = b.kind || b.block.component || 'default';
                var blockClass = 'gjs-block gjs-block-kind-' + kind;
                if (b.select_only) {
                    blockClass += ' pb-block-select-only';
                }
                editor.BlockManager.add(b.id, {
                    label: b.label || blockLabel(b.block),
                    category: cat.label || cat.id,
                    content: blockToContent(b.block),
                    media: (window.TD_PB_THUMBS && window.TD_PB_THUMBS.media(b)) || '',
                    activate: false,
                    draggable: !b.select_only,
                    attributes: {
                        class: blockClass,
                        'data-pb-kind': kind,
                        'data-pb-select-only': b.select_only ? '1' : '0'
                    }
                });
            });
        });
    }

    function loadAuthModalVirtualPage() {
        editor.setComponents([{
            type: 'mcms-block',
            attributes: {
                'data-component': 'auth_modals',
                class: 'mcms-pb-item',
                'data-pb-path': 'auth_modals'
            },
            name: traitLabel('pb_block_auth_modals', 'Auth modals'),
            components: []
        }]);
        reindexPaths();
        lockChromeEditing();
        var model = editor.getWrapper().components().at(0);
        if (model) {
            model.set({
                draggable: false,
                removable: false,
                copyable: false,
                selectable: true,
                hoverable: true,
                highlightable: true
            });
        }
        editor.getWrapper().set('name', PB.routeLabel || traitLabel('pb_block_auth_modals', 'Auth modals'));
        if (!loading) {
            refreshLayerTree();
        }
        window.setTimeout(function () {
            if (model) {
                editor.select(model);
            }
            showAuthModalTraitsPanel();
        }, 80);
    }

    function loadFromPage(page) {
        var regions = pageToRegions(page);
        var regionNames = effectiveRegionNames(page);
        var regionComponents = regionNames.map(function (name) {
            var blocks = regions[name] || [];
            return {
                type: 'pb-region',
                attributes: {
                    'data-pb-region': name,
                    class: 'pb-region-wrap'
                },
                name: regionLabel(name),
                components: blocks.map(blockToContent)
            };
        });
        editor.setComponents(regionComponents);
        var regionModels = editor.getWrapper().components();
        regionNames.forEach(function (name, ri) {
            var regionModel = collAt(regionModels, ri);
            var blocks = regions[name] || [];
            blocks.forEach(function (b, bi) {
                var child = collAt(regionModel.components(), bi);
                hydrateModel(child, b, '');
            });
        });
        reindexPaths();
        lockChromeEditing();
        lockRegionShellSelection();
        lockMemberEditing();
        walkApplySectionShellFlags(editor.getWrapper());
        if (!loading) {
            refreshLayerTree();
        }
        if (isMemberMode() || isAuthMode()) {
            window.setTimeout(selectFirstMemberEditableBlock, 80);
        }
    }

    function initEditor() {
        editor = window.__PB_EDITOR = grapesjs.init({
            container: '#gjs-structure',
            height: '100%',
            fromElement: false,
            storageManager: false,
            noticeOnUnload: false,
            showOffsets: true,
            undoManager: { trackSelection: false, maximumStackLength: 100 },
            panels: { defaults: [] },
            blockManager: { appendTo: '#pb-blocks-panel' },
            layerManager: { appendTo: '#pb-layers-panel', scrollCanvas: false, sortable: false },
            traitManager: { appendTo: isAuthModalMode() ? '#pb-traits-dock-hidden' : '#pb-traits-panel' },
            selectorManager: { componentFirst: true },
            deviceManager: { devices: [] }
        });

        registerComponentType();
        registerPbImageTrait();
        registerPbBottomNavItemsTrait();
        registerPbAdsGridItemsTrait();
        initChromeCatsPanel();
        initChromeLogoPanel();
        initChromeDecorPanel();
        initChromeFooterLogoPanel();
        registerBlocks();
        if (isAuthModalMode()) {
            loadAuthModalVirtualPage();
        } else {
            loadFromPage(PB.page);
        }
        applyPageModeUi();
        expandLayerTree(editor.getWrapper());
        if (!isAuthModalMode()) {
            editor.getWrapper().set('name', PB.route || 'index/index');
        }
        editor.getWrapper().set('droppable', false);
        editor.UndoManager.clear();
        loading = false;
        updateUndoRedoButtons();
        ensureLayerRoot();
        refreshLayerTree();
        editor.on('load', function () {
            ensureLayerRoot();
            refreshLayerTree();
        });
        window.setTimeout(function () {
            refreshAllLayerMoveHandles();
            initLayerDragBridge();
            initLayerCollapseGuard();
            bindSorterDragFeedback();
        }, 0);

        editor.on('component:move', function (component) {
            if (component && component.parent()) {
                assignSlotOnInsert(component, component.parent());
            }
            afterStructureMutation(false);
        });
        editor.on('component:add', function (model) {
            var comp = model.getAttributes()['data-component'];
            if (!comp && model.parent() && model.parent().is('wrapper')) {
                return;
            }
            if (comp) {
                var parent = model.parent();
                if (parent && !loading) {
                    var parentRegion = parent.getAttributes()['data-pb-region'] || '';
                    if ((parentRegion === 'content' || parentRegion === 'sidebar') && isLayoutContentComp(comp)) {
                        wrapListModelInSection(model, parent);
                        return;
                    }
                }
                if (parent) {
                    assignSlotOnInsert(model, parent);
                }
                if (!model.components().length) {
                    hydrateModel(model, { component: comp, props: {} }, '');
                } else {
                    model.set('traits', traitsFor(comp, model));
                    var props = {};
                    modelPropNames(comp).forEach(function (n) {
                        if (model.get(n) !== undefined && model.get(n) !== null) {
                            props[n] = model.get(n);
                        }
                    });
                    if (comp === 'columns') {
                        applyColumnsTraitDefaults(model, props);
                        modelPropNames(comp).forEach(function (n) {
                            props[n] = model.get(n);
                        });
                        ensureColumnsModelSections(model);
                    }
                    updateLayerName(model, comp, props);
                }
                if (comp === 'section') {
                    applySectionShellFlags(model);
                }
            }
            reindexPaths();
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        });
        editor.on('component:remove', function (removed) {
            if (removed && typeof removed.parent === 'function') {
                var parent = removed.parent();
                if (parent && parent.getAttributes()['data-component'] === 'section' && parent.components().length === 0) {
                    parent.remove();
                }
            }
            reindexPaths();
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        });
        editor.on('component:update', function () {
            scheduleAutoSave(true);
            updateUndoRedoButtons();
        });
        editor.on('layer:render', function (payload) {
            if (payload && payload.component && payload.el) {
                decorateSectionLayerEl(payload.component, payload.el);
            }
            // 层节点重绘后补回选中高亮（否则会「闪一下就没」）
            window.requestAnimationFrame(function () {
                paintLayerSelectionRow(editor.getSelected());
            });
        });
        editor.on('component:deselected', function () {
            undoRowsAnchor = null;
            undoRedoRowsAnchor = null;
            if (isAuthModalMode()) {
                showAuthModalTraitsPanel();
                return;
            }
            toggleTraitsEmpty();
            toggleChromeCatsPanel(null);
            toggleChromeLogoPanel(null);
            toggleChromeDecorPanel(null);
            toggleChromeFooterLogoPanel(null);
            updateLayerMoveButtons();
        });

        editor.on('component:selected', function (model) {
            suppressSave = true;
            if (isAuthModalMode()) {
                showAuthModalTraitsPanel();
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            if (isChromeRegionWrapper(model)) {
                editor.select(null);
                showChromeLockedHint();
                highlightPreviewPath('');
                updateLayerMoveButtons();
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            if (isPageRegionWrapper(model)) {
                editor.select(null);
                highlightPreviewPath('');
                updateLayerMoveButtons();
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            if (isChromeComponentModel(model)) {
                toggleTraitsEmpty();
                toggleChromeCatsPanel(model);
                toggleChromeLogoPanel(model);
                toggleChromeDecorPanel(model);
                toggleChromeFooterLogoPanel(model);
                updateLayerMoveButtons();
                var chromePath = model.getAttributes()['data-pb-path'];
                if (chromePath) {
                    if (skipScrollOnce) {
                        skipScrollOnce = false;
                        highlightPreviewPath(chromePath);
                    } else {
                        scrollPreviewToPath(chromePath);
                    }
                }
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            var selComp = model.getAttributes()['data-component'] || '';
            if (selComp === 'section') {
                var listInSec = resolveListModelInSection(model);
                if (listInSec) {
                    editor.select(listInSec);
                    return;
                }
            }
            if ((isMemberMode() || isAuthMode()) && isMemberInternalComponent(selComp)) {
                refreshTraitPanelForSelection(model);
                updateLayerMoveButtons();
                var internalPath = model.getAttributes()['data-pb-path'];
                if (internalPath) {
                    highlightPreviewPath(internalPath);
                }
                syncLayerSelection(model);
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            refreshTraitPanelForSelection(model);
            updateLayerMoveButtons();
            if (model.is('wrapper')) {
                highlightPreviewPath('');
                setTimeout(function () { suppressSave = false; }, 80);
                return;
            }
            var path = model.getAttributes()['data-pb-path'];
            if (path) {
                if (skipScrollOnce) {
                    skipScrollOnce = false;
                    highlightPreviewPath(path);
                } else {
                    scrollPreviewToPath(path);
                }
            }
            syncLayerSelection(model);
            setTimeout(function () { suppressSave = false; }, 80);
        });

        editor.on('block:click', function (block) {
            if (skipBlockClickOnce) {
                skipBlockClickOnce = false;
                return;
            }
            if (block) {
                insertPaletteBlock(block.getId());
            }
        });
        editor.on('block:drag', onPaletteBlockDrag);
        editor.on('block:drag:start', function (block) {
            var entry = block ? paletteMap[block.getId()] : null;
            if (entry && entry.select_only) {
                skipBlockClickOnce = true;
                selectChromeComponent(entry.block.component);
                return;
            }
            skipBlockClickOnce = false;
            startPaletteDragTracking(block);
            document.body.classList.add('pb-palette-dragging');
            var doc = getPreviewDoc();
            if (doc && doc.body) {
                doc.body.classList.add('pb-drop-active');
            }
        });
        editor.on('block:drag:stop', function (component, block) {
            // 先快照拖拽过程中的有效落点；松手时指针常已离开 iframe，再 refresh 会误判为不可放置
            var savedState = dragState ? {
                blockId: dragState.blockId,
                clientX: dragState.clientX || lastDropPointer.x,
                clientY: dragState.clientY || lastDropPointer.y,
                host: dragState.host,
                target: dragState.target,
                valid: dragState.valid,
                overPreview: dragState.overPreview
            } : null;

            if (savedState && (savedState.clientX || savedState.clientY)
                && isPointerOverPreview(savedState.clientX, savedState.clientY)) {
                refreshDragStateFromPointer(savedState.clientX, savedState.clientY);
                if (dragState && dragState.valid) {
                    savedState = {
                        blockId: dragState.blockId,
                        clientX: dragState.clientX,
                        clientY: dragState.clientY,
                        host: dragState.host,
                        target: dragState.target,
                        valid: dragState.valid,
                        overPreview: dragState.overPreview
                    };
                }
            }

            stopPaletteDragTracking();
            document.body.classList.remove('pb-palette-dragging');
            $('#pb-preview-wrap').removeClass('is-preview-drop');
            clearDropIndicators(true);
            var doc = getPreviewDoc();
            if (doc && doc.body) {
                doc.body.classList.remove('pb-drop-active');
            }
            skipBlockClickOnce = true;

            if (component) {
                try { component.destroy(); } catch (err) {}
            }

            var blockId = (block && block.getId()) || (savedState && savedState.blockId);
            var inserted = false;
            if (blockId && savedState) {
                inserted = !!finalizeDropInsert(blockId, savedState);
            }
            // 频道条：首页兜底落到频道栏（避免 Grapes 画布不可放置导致失败）
            if (!inserted && blockId && pageOwnsChannelRegion()) {
                var entry = paletteMap[blockId];
                if (entry && entry.block && entry.block.component === 'channel_strip') {
                    var chRegion = getRegionModel('channel');
                    var at = chRegion ? chRegion.components().length : 0;
                    insertBlockAtTarget(blockId, { mode: 'region', regionName: 'channel', at: at });
                    dropInsertedOnRelease = true;
                    inserted = true;
                }
            }
            if (!inserted && blockId && !savedState) {
                insertPaletteBlock(blockId);
            }
            dragState = null;
            updateUndoRedoButtons();
        });

        updateUndoRedoButtons();
    }

    function initCurrentPage() {
        var route = PB.route || 'index/index';
        if (!window.confirm(PB.lang.init_confirm || 'Initialize this page with defaults?')) {
            return $.Deferred().resolve(false).promise();
        }
        return $.post(PB.urls.initPage, pbSavePostData({
            theme: PB.theme,
            route: route
        })).then(function (res) {
            if (res && res.code === 1) {
                PB.page = res.page || PB.page;
                if (res.preview_url) {
                    PB.previewUrl = res.preview_url;
                }
                // hydrate 会 set title/icon；此时 loading 必须为 true，否则 change 监听会触发自动保存，
                // 在模型尚未完全写入时把空 props 写回草稿，导致初始化后标题丢失。
                loading = true;
                try {
                    loadFromPage(PB.page);
                    if (editor && editor.UndoManager) {
                        editor.UndoManager.clear();
                    }
                } finally {
                    loading = false;
                }
                refreshPreview();
                showStatus(PB.lang.init_ok || PB.lang.saved || 'OK', true);
                return true;
            }
            showStatus((res && res.msg) ? res.msg : (PB.lang.init_fail || PB.lang.save_err || 'Failed'), false);
            return false;
        }).fail(function () {
            showStatus(PB.lang.save_err_net || 'Network error', false);
            return false;
        });
    }

    function bindUi() {
        $('.td-pb-side-tab').on('click', function () {
            if (isRouteLockedMode() && $(this).data('tab') === 'blocks') {
                return;
            }
            var tab = $(this).data('tab');
            $('.td-pb-side-tab').removeClass('active');
            $(this).addClass('active');
            $('.td-pb-side-panel').removeClass('active');
            if (tab === 'blocks') {
                $('#pb-blocks-panel').addClass('active');
            } else {
                $('#pb-layers-panel').addClass('active');
                window.setTimeout(function () {
                    refreshAllLayerMoveHandles();
                    if (editor) {
                        editor.refresh();
                    }
                }, 50);
            }
        });

        $('.td-pb-view').on('click', function () {
            $('.td-pb-view').removeClass('active');
            $(this).addClass('active');
            var v = $(this).data('view');
            if (v === 'mobile') {
                $('#pb-center').addClass('is-mobile');
            } else {
                $('#pb-center').removeClass('is-mobile');
            }
            scheduleFitPreviewScale();
            window.setTimeout(function () {
                fitPreviewScale();
                PB._previewRetried = false;
                refreshPreview();
            }, 60);
        });

        $(window).on('resize.pbpreview', scheduleFitPreviewScale);

        $('#pbSave').on('click', function () {
            saveDraft(false, true);
        });
        $('#pbInitPage').on('click', function () {
            initCurrentPage();
        });
        $('#pbRefresh').on('click', function () {
            PB._previewRetried = false;
            fetchPreviewUrl().always(function () {
                saveDraft(true, true);
            });
        });

        initPagePicker();
        $('#pb-member-mock').on('change', function () {
            refreshPreview();
        });
        $('#pbUndo').on('click', function () {
            if (!editor || !editor.UndoManager.hasUndo()) {
                return;
            }
            var batchRows = undoRowsAnchor;
            undoRowsAnchor = null;
            suppressSave = true;
            editor.UndoManager.undo();
            // 刚插入过的场景：一次 undo 只弹一条碎片记录、界面无变化，需批量弹到锚点
            if (batchRows !== null) {
                var modelCount = function () {
                    var n = 0;
                    (function w(m) { n++; m.components().each(w); })(editor.getWrapper());
                    return n;
                };
                var guard = 0;
                while (editor.UndoManager.hasUndo() && guard++ < 80 && modelCount() > batchRows) {
                    editor.UndoManager.undo();
                }
                undoRedoRowsAnchor = modelCount();
            }
            window.setTimeout(function () {
                afterStructureMutation(true);
                suppressSave = false;
            }, 0);
        });
        $('#pbRedo').on('click', function () {
            if (!editor || !editor.UndoManager.hasRedo()) {
                return;
            }
            var batchRows = undoRedoRowsAnchor;
            undoRedoRowsAnchor = null;
            suppressSave = true;
            editor.UndoManager.redo();
            if (batchRows !== null) {
                var modelCount2 = function () {
                    var n = 0;
                    (function w(m) { n++; m.components().each(w); })(editor.getWrapper());
                    return n;
                };
                var guard = 0;
                while (editor.UndoManager.hasRedo() && guard++ < 80 && modelCount2() < batchRows) {
                    editor.UndoManager.redo();
                }
                undoRowsAnchor = modelCount2();
            }
            window.setTimeout(function () {
                afterStructureMutation(true);
                suppressSave = false;
            }, 0);
        });
        $('#pbMoveUp').on('click', function () {
            moveSelectedSibling(-1);
        });
        $('#pbMoveDown').on('click', function () {
            moveSelectedSibling(1);
        });

        $(document).on('keydown.pbdelete', function (e) {
            if (e.key !== 'Delete' && e.key !== 'Backspace') {
                return;
            }
            if ($(e.target).closest('input, textarea, select, [contenteditable="true"]').length) {
                return;
            }
            if (!editor) {
                return;
            }
            var sel = editor.getSelected();
            if (!sel || sel.is('wrapper')) {
                return;
            }
            e.preventDefault();
            deleteEditorModel(sel);
        });

        $(document).on('keydown.pbreorder', function (e) {
            if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) {
                return;
            }
            if ($(e.target).closest('input, textarea, select, [contenteditable="true"]').length) {
                return;
            }
            e.preventDefault();
            moveSelectedSibling(e.key === 'ArrowUp' ? -1 : 1);
        });
    }

    $(function () {
        if (typeof grapesjs === 'undefined') {
            alert('GrapesJS failed to load');
            return;
        }
        try {
            buildTraitOptionSets();
            var thumbsReady = (window.TD_PB_THUMBS && window.TD_PB_THUMBS.ready)
                ? window.TD_PB_THUMBS.ready()
                : Promise.resolve();
            thumbsReady.then(function () {
                initEditor();
            }).catch(function (err) {
                if (window.console && console.error) {
                    console.error('[PB] thumb preload failed', err);
                }
                initEditor();
            });
        } catch (err) {
            if (window.console && console.error) {
                console.error('[PB] initEditor failed', err);
            }
        }
        try {
            bindUi();
        } catch (err) {
            if (window.console && console.error) {
                console.error('[PB] bindUi failed', err);
            }
        }
        try {
            toggleTraitsEmpty();
        } catch (err) {}
        ensurePreviewResizeObserver();
        scheduleFitPreviewScale();
        window.requestAnimationFrame(function () {
            try {
                bootPreview(false);
            } catch (err) {
                if (window.console && console.error) {
                    console.error('[PB] bootPreview failed', err);
                }
            }
        });
        $(window).on('load.pbpreview', function () {
            scheduleFitPreviewScale();
            wirePreviewIframe();
        });
        window.setTimeout(function () {
            scheduleFitPreviewScale();
            wirePreviewIframe();
        }, 800);
        window.setTimeout(function () {
            scheduleFitPreviewScale();
            wirePreviewIframe();
        }, 2000);
        try {
            window.__PB_RESOLVE_DROP = resolveDropTarget;
            window.__PB_FIND_HOST = findPreviewHostAtPoint;
            window.__PB_RECT = iframeElParentRect;
            window.__PB_INSERT = insertBlockAtTarget;
            window.__PB_GAP = isInTopLevelGapZone;
            window.__PB_SCALE = getPreviewFrameScale;
            // embed 返回向导前可强制落盘，避免未点保存就关闭
            window.__PB_SAVE_DRAFT = function (quiet, refresh) {
                return saveDraft(!!quiet, refresh !== false);
            };
        } catch (err2) {}
    });
})();
