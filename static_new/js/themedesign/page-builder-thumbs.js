/**
 * Page Builder 组件库缩略图：wireframe SVG 独立文件，按组件 id 加载。
 */
(function (global) {
    'use strict';

    var THUMB_IDS = [
        'section', 'columns', 'filmstrip_layout', 'grid', 'spacer',
        'carousel-fullbleed', 'carousel-compact', 'vod_carousel',
        'manga_home_spotlight', 'manga_mosaic_hero', 'novel_home_banner',
        'vod_list', 'art_list', 'manga_list', 'topic_list', 'live_list',
        'website_list', 'gbook_list', 'content_feed',
        'rank_list', 'rank_inline', 'rank_card', 'rank_feed',
        'header_decor', 'site_nav', 'channel_strip', 'site_footer',
        'slider', 'list', 'filmstrip', '_default'
    ];

    var BY_RENDER = {
        slider: 'slider',
        list: 'list',
        filmstrip: 'filmstrip',
        grid: 'vod_list'
    };

    var BY_RANK_STYLE = {
        inline: 'rank_inline',
        panel: 'rank_feed'
    };

    var cache = {};
    var readyPromise = null;

    function thumbBase() {
        var pb = global.TD_PB || {};
        if (pb.thumbBase) {
            return pb.thumbBase;
        }
        if (typeof ROOT_PATH === 'string' && ROOT_PATH !== '') {
            return ROOT_PATH + 'static_new/svg/themedesign/thumbs/';
        }
        return '/static_new/svg/themedesign/thumbs/';
    }

    function thumbUrl(id) {
        return thumbBase() + encodeURIComponent(id) + '.svg';
    }

    function preload() {
        if (readyPromise) {
            return readyPromise;
        }
        if (typeof fetch !== 'function') {
            readyPromise = Promise.resolve();
            return readyPromise;
        }
        readyPromise = Promise.all(THUMB_IDS.map(function (id) {
            return fetch(thumbUrl(id), { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('thumb ' + id + ' HTTP ' + res.status);
                    }
                    return res.text();
                })
                .then(function (svg) {
                    cache[id] = svg;
                })
                .catch(function () {
                    cache[id] = cache._default || '';
                });
        })).then(function () {
            if (!cache._default) {
                cache._default = '';
            }
        });
        return readyPromise;
    }

    function getThumb(id) {
        if (id && cache[id]) {
            return cache[id];
        }
        return cache._default || '';
    }

    /**
     * @param {{ block?: { component?: string, props?: object }, kind?: string }} entry
     * @returns {string}
     */
    function blockThumbMedia(entry) {
        if (!entry || !entry.block) {
            return '';
        }
        var comp = entry.block.component || '';
        var props = entry.block.props || {};
        if (comp === 'rank_feed' && props.style && BY_RANK_STYLE[props.style]) {
            return getThumb(BY_RANK_STYLE[props.style]);
        }
        if (props.render && BY_RENDER[props.render]) {
            return getThumb(BY_RENDER[props.render]);
        }
        if (comp && cache[comp]) {
            return getThumb(comp);
        }
        if (entry.kind === 'rank') {
            return getThumb('rank_list');
        }
        if (entry.kind === 'carousel') {
            return getThumb('vod_carousel');
        }
        if (entry.kind === 'content') {
            return getThumb('content_feed');
        }
        if (entry.kind === 'chrome') {
            return getThumb('site_nav');
        }
        return getThumb('_default');
    }

    global.TD_PB_THUMBS = {
        media: blockThumbMedia,
        ready: preload,
        url: thumbUrl
    };
})(typeof window !== 'undefined' ? window : this);
