/**
 * content_feed · 换一换
 * 依赖既有 api.php 列表接口（start/num 偏移），客户端重绘 PB 卡片。
 */
(function () {
    'use strict';

    var PH = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    var REFRESH_PAGE_SPAN = 6;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function apiBase() {
        var path = (window.maccms && maccms.path) ? String(maccms.path) : '/';
        path = path.replace(/\/+$/, '');
        return (path ? path + '/' : '/') + 'api.php';
    }

    function lazyImg(src, alt, cls) {
        var u = esc(src || '');
        var a = esc(alt || '');
        var c = cls ? ' class="' + esc(cls) + '"' : '';
        return '<img' + c + ' data-original="' + u + '" src="' + PH + '" alt="' + a + '">';
    }

    function findCardHost(listEl) {
        if (!listEl) {
            return null;
        }
        return listEl.querySelector('.pb-scroll-row__track')
            || listEl.querySelector('.bili-grid')
            || listEl.querySelector('.rows')
            || listEl;
    }

    function readInt(el, name, fallback) {
        var v = parseInt(el.getAttribute(name), 10);
        return isNaN(v) ? fallback : v;
    }

    function nextStart(root) {
        var num = Math.max(1, readInt(root, 'data-num', 12));
        var cur = Math.max(0, readInt(root, 'data-start', 0));
        var span = Math.max(1, readInt(root, 'data-span', REFRESH_PAGE_SPAN));
        var next = cur + num;
        if (next >= num * span) {
            next = 0;
        }
        root.setAttribute('data-start', String(next));
        return next;
    }

    function resolveEndpoint(bind, by) {
        if (bind === 'vod') {
            return { path: '/vod/get_hot', useType: true, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'manga') {
            if (by === 'time') {
                return { path: '/manga/get_latest', useType: false, useBy: false, rowsKey: 'rows' };
            }
            return { path: '/manga/get_hot', useType: false, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'art') {
            if (by === 'time') {
                return { path: '/art/get_latest', useType: true, useBy: false, rowsKey: 'rows' };
            }
            return { path: '/art/get_hot', useType: true, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'actor') {
            return { path: '/actor/get_recommend', useType: false, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'topic') {
            return { path: '/topic/get_recommend', useType: false, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'live') {
            return { path: '/live/get_list', useType: false, useBy: false, rowsKey: 'list', offsetMode: true };
        }
        if (bind === 'role') {
            return { path: '/role/get_recommend', useType: false, useBy: true, rowsKey: 'rows' };
        }
        if (bind === 'website') {
            return { path: '/website/get_list', useType: false, useBy: false, rowsKey: 'rows', offsetMode: true };
        }
        return null;
    }

    function cardVod(item) {
        var link = esc(item.vod_link || '#');
        var name = esc(item.vod_name || '');
        var pic = item.vod_pic || '';
        var rm = item.vod_remarks ? '<span class="pb-vod-card__rm">' + episodeRemarks(item.vod_remarks) + '</span>' : '';
        var duration = item.vod_duration
            ? '<span class="pb-vod-card__duration">' + esc(item.vod_duration) + '</span>'
            : '';
        var hits = item.vod_hits && Number(item.vod_hits) > 0
            ? '<span class="pb-vod-card__mask"><span class="pb-vod-card__stats">'
                + '<span class="pb-vod-card__stat pb-vod-card__stat--hits">'
                + '<i class="pb-vod-card__ico pb-vod-card__ico--play" aria-hidden="true"></i>' + esc(item.vod_hits)
                + '</span></span></span>'
            : '';
        var meta = esc(item.vod_year || '—');
        if (item.vod_area) {
            meta += ' · ' + esc(item.vod_area);
        }
        /* data-cover-auto="fit"：竖版封面进横版框时由 runtime 改为完整显示 + 模糊底（同 _shared/vod_card.twig） */
        return '<article class="pb-vod-card" data-cover-auto="fit" data-mid="1" data-id="' + esc(item.vod_id) + '">'
            + '<a class="pb-vod-card__pic" href="' + link + '" title="' + name + '">'
            + (pic ? '<span class="pb-vod-card__backdrop" data-cover-backdrop aria-hidden="true"></span>' : '')
            + lazyImg(pic, name)
            + '<span class="pb-vod-card__play" aria-hidden="true"></span>'
            + rm
            + duration
            + hits
            + '</a>'
            + '<div class="pb-vod-card__title"><a href="' + link + '">' + name + '</a></div>'
            + '<div class="pb-vod-card__meta">' + meta + '</div>'
            + '</article>';
    }

    /** 章节数备注（共N章|话 等）走多语文案，与 _shared/chapter_remarks.twig 一致；其余原样输出（limit>0 时截断） */
    var CHAPTER_REMARKS_KEYS = { '共': 'total', '更新至': 'upto', '连载至': 'serial' };
    function chapterRemarks(rm, kind, limit) {
        rm = String(rm).trim();
        var manga = kind === 'manga';
        var m = rm.match(manga ? /^(共|更新至|连载至)(\d+)话$/ : /^(共|更新至|连载至)(\d+)章$/);
        if (!m) {
            return esc(limit ? rm.slice(0, limit) : rm);
        }
        var key = (manga ? 'manga_remarks_chapter_' : 'art_remarks_chapter_') + CHAPTER_REMARKS_KEYS[m[1]];
        var map = (window.MacTheme && MacTheme.i18n && MacTheme.i18n.map) || {};
        var text = Object.prototype.hasOwnProperty.call(map, key) ? map[key].replace('%s', m[2]) : rm;
        return '<span data-lang="' + key + '" data-lang-arg="' + m[2] + '">' + esc(text) + '</span>';
    }

    /** 集数备注（全N集 / 更新至第N集 等）走多语文案，与 _shared/episode_remarks.twig 一致；其余原样输出 */
    var EPISODE_REMARKS_KEYS = { '全': 'all', '共': 'total', '更新至': 'upto', '连载至': 'serial' };
    function episodeRemarks(rm) {
        rm = String(rm).trim();
        var m = rm.match(/^(全|共|更新至|连载至)(第?)(\d+)集$/);
        if (!m) {
            return esc(rm);
        }
        var key = 'vod_remarks_ep_' + EPISODE_REMARKS_KEYS[m[1]] + (m[2] ? '_nth' : '');
        var map = (window.MacTheme && MacTheme.i18n && MacTheme.i18n.map) || {};
        var text = Object.prototype.hasOwnProperty.call(map, key) ? map[key].replace('%s', m[3]) : rm;
        return '<span data-lang="' + key + '" data-lang-arg="' + m[3] + '">' + esc(text) + '</span>';
    }

    function cardManga(item) {
        var link = esc(item.manga_link || '#');
        var name = esc(item.manga_name || '');
        var rm = item.manga_remarks ? '<span class="pb-manga-card__rm">' + chapterRemarks(item.manga_remarks, 'manga') + '</span>' : '';
        return '<article class="pb-manga-card" data-mid="12" data-id="' + esc(item.manga_id) + '">'
            + '<a class="pb-manga-card__pic" href="' + link + '" title="' + name + '">'
            + lazyImg(item.manga_pic || '', name)
            + rm
            + '</a>'
            + '<div class="pb-manga-card__title"><a href="' + link + '">' + name + '</a></div>'
            + '<div class="pb-manga-card__meta">' + esc(item.manga_author || '') + '</div>'
            + '</article>';
    }

    function cardArt(item) {
        var link = esc(item.art_link || '#');
        var name = esc(item.art_name || '');
        var pic = item.art_pic || '';
        var badge = item.art_remarks
            ? '<i class="pb-art-book__badge">' + chapterRemarks(item.art_remarks, 'art', 8) + '</i>'
            : '';
        /* 封面為空也輸出懶載入圖：由 runtime cover 模組換成書形占位（data-cover-kind="book"），與伺服端書卡一致 */
        return '<a class="pb-art-book" href="' + link + '" title="' + name + '">'
            + '<span class="pb-art-book__cover" data-cover-kind="book">' + lazyImg(pic, name, 'pb-art-book__img') + badge + '</span>'
            + '<strong class="pb-art-book__title">' + name + '</strong>'
            + '<em class="pb-art-book__meta">' + esc(item.art_author || '') + '</em>'
            + '</a>';
    }

    function cardActor(item) {
        var link = esc(item.actor_link || '#');
        var name = esc(item.actor_name || '');
        return '<article class="pb-actor-card" data-mid="8" data-id="' + esc(item.actor_id) + '">'
            + '<a class="pb-actor-card__pic" href="' + link + '" title="' + name + '">'
            + lazyImg(item.actor_pic || '', name)
            + '</a>'
            + '<h3 class="pb-actor-card__title"><a href="' + link + '">' + name + '</a></h3>'
            + '<p class="pb-actor-card__meta">' + esc(item.actor_area || '') + '</p>'
            + '</article>';
    }

    function cardTopic(item) {
        if (item.topic_empty) {
            return '';
        }
        var link = esc(item.topic_link || '#');
        var name = esc(item.topic_name || '');
        return '<article class="pb-topic-card" data-mid="3" data-id="' + esc(item.topic_id) + '">'
            + '<a class="pb-topic-card__pic" href="' + link + '" title="' + name + '">'
            + lazyImg(item.topic_pic || '', name)
            + '</a>'
            + '<div class="pb-topic-card__title"><a href="' + link + '">' + name + '</a></div>'
            + '<div class="pb-topic-card__meta">' + esc(item.topic_sub || '') + '</div>'
            + '</article>';
    }

    function cardLive(item) {
        var id = item.live_id || 0;
        var path = (window.maccms && maccms.path) ? String(maccms.path).replace(/\/+$/, '') : '';
        var link = esc((path ? path + '/' : '/') + 'index.php/live/play/id/' + id + '.html');
        var name = esc(item.live_name || '');
        return '<article class="pb-live-card" data-live-id="' + esc(id) + '">'
            + '<a class="pb-live-card__pic" href="' + link + '" title="' + name + '">'
            + lazyImg(item.live_pic || '', name)
            + '</a>'
            + '<div class="pb-live-card__title"><a href="' + link + '">' + name + '</a></div>'
            + '<div class="pb-live-card__meta">' + esc(item.live_sub || item.cate_name || '') + '</div>'
            + '</article>';
    }

    function cardRole(item) {
        var link = esc(item.role_link || '#');
        var name = esc(item.role_name || '');
        var meta = item.role_actor || item.role_remarks || '';
        return '<article class="pb-actor-card pb-role-card" data-mid="9" data-id="' + esc(item.role_id) + '">'
            + '<a class="pb-actor-card__pic" href="' + link + '" title="' + name + '">'
            + lazyImg(item.role_pic || '', name)
            + '</a>'
            + '<h3 class="pb-actor-card__title"><a href="' + link + '">' + name + '</a></h3>'
            + (meta ? '<p class="pb-actor-card__meta">' + esc(meta) + '</p>' : '')
            + '</article>';
    }

    function cardWebsite(item) {
        var id = item.website_id || 0;
        var path = (window.maccms && maccms.path) ? String(maccms.path).replace(/\/+$/, '') : '';
        var link = esc(item.website_link || ((path ? path + '/' : '/') + 'index.php/website/detail/id/' + id + '.html'));
        var name = esc(item.website_name || '');
        var pic = item.website_logo || item.website_pic || '';
        var blurb = esc(item.website_blurb || '');
        if (blurb.length > 80) {
            blurb = blurb.slice(0, 80);
        }
        var meta = esc(item.website_area || '');
        if (item.website_hits) {
            meta += (meta ? ' · ' : '') + esc(item.website_hits);
        }
        return '<div class="pb-site-row" data-mid="11" data-id="' + esc(id) + '">'
            + '<div class="pb-site-row__fig"><a href="' + link + '">' + lazyImg(pic, name) + '</a></div>'
            + '<div class="pb-site-row__body">'
            + '<h4><a href="' + link + '">' + name + '</a></h4>'
            + '<p>' + blurb + '</p>'
            + '<div class="pb-site-row__meta">' + meta + '</div>'
            + '</div></div>';
    }

    function renderRows(bind, rows) {
        if (!rows || !rows.length) {
            return '';
        }
        var html = '';
        var i;
        var item;
        for (i = 0; i < rows.length; i++) {
            item = rows[i] || {};
            if (bind === 'vod') {
                html += cardVod(item);
            } else if (bind === 'manga') {
                html += cardManga(item);
            } else if (bind === 'art') {
                html += cardArt(item);
            } else if (bind === 'actor') {
                html += cardActor(item);
            } else if (bind === 'topic') {
                html += cardTopic(item);
            } else if (bind === 'live') {
                html += cardLive(item);
            } else if (bind === 'role') {
                html += cardRole(item);
            } else if (bind === 'website') {
                html += cardWebsite(item);
            }
        }
        return html;
    }

    function applyRows(root, rows) {
        var listEl = root.querySelector('[data-pb-cf-list]');
        var host = findCardHost(listEl);
        var bind = String(root.getAttribute('data-bind') || 'vod');
        var html = renderRows(bind, rows);
        if (!host) {
            return;
        }
        if (!html) {
            root.classList.add('is-empty');
            return;
        }
        root.classList.remove('is-empty');
        host.innerHTML = html;
        if (window.MacTheme && typeof window.MacTheme.refresh === 'function') {
            window.MacTheme.refresh(host);
        }
    }

    function fetchFeed(root, start) {
        var bind = String(root.getAttribute('data-bind') || 'vod');
        var by = String(root.getAttribute('data-by') || 'time');
        var num = Math.max(1, readInt(root, 'data-num', 12));
        var typeId = Math.max(0, readInt(root, 'data-type', 0));
        var ep = resolveEndpoint(bind, by);
        if (!ep) {
            return Promise.reject(new Error('unsupported bind'));
        }
        var data = {};
        if (ep.offsetMode) {
            data.offset = start;
            data.limit = num;
        } else {
            data.start = start;
            data.num = num;
        }
        if (ep.useType && typeId > 0) {
            data.type_id = typeId;
        }
        if (ep.useBy && by) {
            data.by = by;
        }
        var qs = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        var url = apiBase() + ep.path + (qs ? '?' + qs : '');
        return fetch(url, { credentials: 'same-origin' }).then(function (res) {
            return res.json();
        }).then(function (json) {
            if (!json || Number(json.code) !== 1) {
                throw new Error((json && json.msg) || 'load fail');
            }
            var rows = [];
            if (json.info && json.info[ep.rowsKey]) {
                rows = json.info[ep.rowsKey];
            } else if (json[ep.rowsKey]) {
                rows = json[ep.rowsKey];
            } else if (json.info && json.info.rows) {
                rows = json.info.rows;
            } else if (json.list) {
                rows = json.list;
            }
            return Array.isArray(rows) ? rows : [];
        });
    }

    function onRefreshClick(ev) {
        var btn = ev.currentTarget;
        var root = btn.closest('[data-pb-cf-root]');
        if (!root || root.getAttribute('data-loading') === '1') {
            return;
        }
        var start = nextStart(root);
        root.setAttribute('data-loading', '1');
        btn.classList.add('is-loading');
        fetchFeed(root, start).then(function (rows) {
            applyRows(root, rows);
        }).catch(function () {
            /* keep current list on failure */
        }).then(function () {
            root.setAttribute('data-loading', '0');
            btn.classList.remove('is-loading');
        });
    }

    function bindRoot(root) {
        if (!root || root.getAttribute('data-pb-cf-bound') === '1') {
            return;
        }
        root.setAttribute('data-pb-cf-bound', '1');
        var btn = root.querySelector('[data-pb-cf-refresh]');
        if (btn) {
            btn.addEventListener('click', onRefreshClick);
        }
    }

    function init(root) {
        var scope = root || document;
        var nodes = scope.querySelectorAll ? scope.querySelectorAll('[data-pb-cf-root]') : [];
        for (var i = 0; i < nodes.length; i++) {
            bindRoot(nodes[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }

    window.PbContentFeed = { init: init };
})();
