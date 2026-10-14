/**
 * 消息中心页 — [data-mac-notify-page]
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var PAGE_LIMIT = 10;
    /** 正文超过该字数时折叠，显示「展开/收起」；可由 [data-notify-collapse-chars] 覆盖 */
    var COLLAPSE_CHARS = 80;
    var state = { page: 1, type: '', pagecount: 1, total: 0, loading: false };

    function $(sel, root) {
        return (root || doc).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || doc).querySelectorAll(sel));
    }

    function apiUserBase() {
        return ML.normalizeApiBase().replace(/\/+$/, '') + '/user';
    }

    function lang(key, fallback) {
        try {
            if (win.MAC && win.MAC.GetLang) {
                var t = win.MAC.GetLang(key);
                if (t && t !== key) { return t; }
            }
        } catch (e) { /* ignore */ }
        return fallback || key;
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtTime(ts) {
        var n = parseInt(ts, 10);
        if (!n) { return ''; }
        if (n < 1e12) { n *= 1000; }
        var d = new Date(n);
        if (isNaN(d.getTime())) { return ''; }
        function pad(v) { return ('0' + v).slice(-2); }
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function isSafeLink(href) {
        if (ML && typeof ML.isSafeNotifyLink === 'function') {
            return ML.isSafeNotifyLink(href);
        }
        var s = String(href || '').trim();
        if (!s) { return false; }
        return (s.charAt(0) === '/' && s.charAt(1) !== '/') || /^https?:\/\//i.test(s);
    }

    function resolveNotifyLink(href) {
        if (ML && typeof ML.resolveNotifyLink === 'function') {
            return ML.resolveNotifyLink(href);
        }
        var s = String(href || '').trim();
        if (!s || !isSafeLink(s)) { return ''; }
        if (/^https?:\/\//i.test(s)) { return s; }
        var hash = '';
        var query = '';
        var hashAt = s.indexOf('#');
        if (hashAt >= 0) {
            hash = s.slice(hashAt);
            s = s.slice(0, hashAt);
        }
        var queryAt = s.indexOf('?');
        if (queryAt >= 0) {
            query = s.slice(queryAt);
            s = s.slice(0, queryAt);
        }
        var pathBase = (win.maccms && win.maccms.path) ? String(win.maccms.path).replace(/\/+$/, '') : '';
        var route = s.replace(/^\//, '').replace(/\.html$/, '').replace(/^user\/?/, '');
        if (route === 'mall_orders' || route.indexOf('mall_orders/') === 0) {
            return pathBase + '/index.php/user/mall.html?open_orders=1' + (query ? '&' + query.replace(/^\?/, '') : '');
        }
        return pathBase + '/index.php/user/' + route + '.html' + query + hash;
    }

    function typeLabel(type) {
        var map = {
            system: lang('notify_type_system', '系统'),
            announce: lang('notify_type_announce', '公告'),
            activity: lang('notify_type_activity', '活动'),
            vip: lang('notify_type_vip', 'VIP'),
            order: lang('notify_type_order', '订单'),
            reply: lang('notify_type_reply', '回复'),
            pm: lang('notify_type_pm', '私信'),
            follow: lang('notify_type_follow', '关注')
        };
        return map[String(type || '').toLowerCase()] || type || '';
    }

    function emitUnreadChanged() {
        try {
            doc.dispatchEvent(new CustomEvent('mac:notify-unread-changed'));
        } catch (e) { /* ignore */ }
    }

    function postRead(ids, all) {
        var body = all ? { all: '1' } : { ids: ids.join(',') };
        return ML.postForm(apiUserBase() + '/read_notify', body).then(function (res) {
            if (res && res.code === 1) { emitUnreadChanged(); }
            return res;
        });
    }

    function collapseChars() {
        var panel = $('[data-mac-notify-page]');
        var n = panel ? parseInt(panel.getAttribute('data-notify-collapse-chars'), 10) : NaN;
        return n > 0 ? n : COLLAPSE_CHARS;
    }

    /** 按字符（非 UTF-16 码元）计数，避免截断 emoji */
    function chars(s) {
        return Array.from ? Array.from(s) : String(s).split('');
    }

    function toggleLabel(expanded) {
        var key = expanded ? 'notify_body_collapse' : 'notify_body_expand';
        return { key: key, text: lang(key, expanded ? '收起' : '展开') };
    }

    function renderBody(body) {
        if (!body) { return ''; }
        var list = chars(body);
        var limit = collapseChars();
        if (list.length <= limit) {
            return '<div class="mac-notify-item__body">' + esc(body) + '</div>';
        }
        var label = toggleLabel(false);
        return '<div class="mac-notify-item__body is-collapsible">' +
            '<span class="mac-notify-item__body-short">' + esc(list.slice(0, limit).join('')) + '…</span>' +
            '<span class="mac-notify-item__body-full" hidden>' + esc(body) + '</span>' +
            '<button type="button" class="mac-notify-item__toggle" aria-expanded="false" data-lang="' +
            label.key + '">' + esc(label.text) + '</button></div>';
    }

    function toggleBody(btn) {
        var box = btn.closest('.mac-notify-item__body');
        if (!box) { return; }
        var expanded = btn.getAttribute('aria-expanded') !== 'true';
        var shortEl = box.querySelector('.mac-notify-item__body-short');
        var fullEl = box.querySelector('.mac-notify-item__body-full');
        if (shortEl) { shortEl.hidden = expanded; }
        if (fullEl) { fullEl.hidden = !expanded; }
        box.classList.toggle('is-expanded', expanded);
        var label = toggleLabel(expanded);
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        btn.setAttribute('data-lang', label.key);
        btn.textContent = label.text;
    }

    function renderRows(rows) {
        var list = $('#js-notify-list');
        var empty = $('#js-notify-empty');
        if (!list) { return; }
        list.innerHTML = '';
        if (!rows || !rows.length) {
            if (empty) { empty.hidden = false; }
            return;
        }
        if (empty) { empty.hidden = true; }
        list.insertAdjacentHTML('beforeend', rows.map(function (row) {
            var unread = Number(row.notify_read) === 0;
            var link = isSafeLink(row.notify_link) ? resolveNotifyLink(row.notify_link) : '';
            var body = String(row.notify_content || '');
            return '<li class="mac-notify-item' + (unread ? ' is-unread' : '') +
                '" data-id="' + esc(row.notify_id) + '" data-link="' + esc(link) +
                '" role="button" tabindex="0">' +
                '<div class="mac-notify-item__meta">' +
                '<span class="mac-notify-item__type">' + esc(typeLabel(row.notify_type)) + '</span>' +
                '<span class="mac-notify-item__time">' + esc(fmtTime(row.notify_time)) + '</span></div>' +
                '<div class="mac-notify-item__title">' + esc(row.notify_title || '') +
                (unread ? '<span class="mac-notify-item__dot" data-lang="notify_unread">' +
                    esc(lang('notify_unread', '未读')) + '</span>' : '') +
                '</div>' + renderBody(body) +
                '</li>';
        }).join(''));
    }

    function updatePager(info) {
        var pager = $('#js-notify-pager');
        if (!pager) { return; }
        state.page = info.page || state.page || 1;
        state.pagecount = Math.max(1, parseInt(info.pagecount, 10) || 1);
        state.total = parseInt(info.total, 10) || 0;

        if (state.pagecount <= 1) {
            pager.hidden = true;
            pager.innerHTML = '';
            return;
        }

        pager.hidden = false;
        var summary = lang('notify_pager_summary', '共 {total} 条 · 第 {page}/{pages} 页')
            .replace('{total}', String(state.total))
            .replace('{page}', String(state.page))
            .replace('{pages}', String(state.pagecount));
        var listHtml = ML.buildPager ? ML.buildPager(state.page, state.pagecount, 'mac-notify-pager__list') : '';
        pager.innerHTML = '<p class="mac-notify-pager__summary">' + esc(summary) + '</p>' + listHtml;
    }

    function scrollPanelTop() {
        var panel = $('[data-mac-notify-page]');
        if (!panel) { return; }
        try {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            panel.scrollIntoView(true);
        }
    }

    function loadList(opts) {
        opts = opts || {};
        if (state.loading) { return Promise.resolve(); }
        state.loading = true;
        var loadingEl = $('#js-notify-loading');
        if (loadingEl) { loadingEl.hidden = false; }

        var page = opts.page != null ? opts.page : state.page;
        var type = opts.type != null ? opts.type : state.type;
        var url = apiUserBase() + '/get_notify_list?page=' + encodeURIComponent(page) +
            '&limit=' + PAGE_LIMIT + (type ? '&type=' + encodeURIComponent(type) : '');

        return ML.apiJson(url).then(function (res) {
            if (!res || res.code !== 1) {
                if (ML.requireLoginOrRedirect(res && res.code)) { return; }
                throw new Error((res && res.msg) || 'load fail');
            }
            state.type = type;
            state.page = page;
            var rows = (res.info && (res.info.list || res.info.rows)) || [];
            renderRows(rows);
            updatePager(res.info || {});
            if (opts.scrollTop) { scrollPanelTop(); }
        }).catch(function (err) {
            if (win.console && win.console.warn) {
                win.console.warn('[notify-page]', err);
            }
        }).then(function () {
            state.loading = false;
            if (loadingEl) { loadingEl.hidden = true; }
        });
    }

    function bindTabs() {
        $$('[data-notify-type]').forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                $$('[data-notify-type]').forEach(function (el) {
                    el.classList.remove('is-active');
                    el.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');
                state.type = tab.getAttribute('data-notify-type') || '';
                state.page = 1;
                loadList({ page: 1, type: state.type, scrollTop: true });
            });
        });
    }

    function bindList() {
        var list = $('#js-notify-list');
        if (!list) { return; }
        list.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('.mac-notify-item__toggle');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();
                toggleBody(toggleBtn);
                return;
            }
            var item = e.target.closest('.mac-notify-item');
            if (!item) { return; }
            var id = parseInt(item.getAttribute('data-id'), 10);
            var link = item.getAttribute('data-link') || '';
            if (!id) { return; }
            postRead([id], false).finally(function () {
                item.classList.remove('is-unread');
                var dot = item.querySelector('.mac-notify-item__dot');
                if (dot) { dot.remove(); }
                if (link) { win.location.assign(link); }
            });
        });
    }

    function bindPager() {
        var pager = $('#js-notify-pager');
        if (!pager || pager.__notifyPagerBound) { return; }
        pager.__notifyPagerBound = true;
        if (ML.bindPager) {
            ML.bindPager(pager, '.mac-notify-pager__list', function (pageNum) {
                if (pageNum === state.page) { return; }
                loadList({ page: pageNum, scrollTop: true });
            });
        }
    }

    function bindMarkAll() {
        var btn = $('#js-notify-mark-all');
        if (!btn) { return; }
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            postRead([], true).then(function () {
                loadList({ page: state.page });
            });
        });
    }

    function bootPage() {
        if (!$('[data-mac-notify-page]')) { return; }
        bindTabs();
        bindList();
        bindPager();
        bindMarkAll();
        loadList({ page: 1 });
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            if (doc.readyState === 'loading') {
                doc.addEventListener('DOMContentLoaded', bootPage);
            } else {
                bootPage();
            }
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        } else {
            start({
                normalizeApiBase: function () {
                    var base = (win.maccms && win.maccms.path) ? win.maccms.path : '/';
                    return String(base).replace(/\/+$/, '') + '/index.php';
                },
                apiJson: function (url) {
                    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
                },
                postForm: function (url, body) {
                    var fd = new FormData();
                    Object.keys(body || {}).forEach(function (k) { fd.append(k, body[k]); });
                    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                },
                requireLoginOrRedirect: function (code) {
                    if (code === 1001 || code === 1002 || code === 1401) {
                        var login = (win.maccms && win.maccms.path ? win.maccms.path : '/') + 'user/login';
                        win.location.href = login;
                        return true;
                    }
                    return false;
                },
                buildPager: win.MacMemberList && win.MacMemberList.buildPager,
                bindPager: win.MacMemberList && win.MacMemberList.bindPager
            });
        }
    }

    boot();
})(window, document);
