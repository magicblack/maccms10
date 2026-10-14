/**
 * twigspike 头部消息铃铛 — [data-mac-notify-bell]
 */
(function (win, doc) {
    'use strict';

    var state = win.__macNotifyBell || (win.__macNotifyBell = {
        ML: win.MacMemberList,
        bells: [],
        pollTimer: null,
        wired: false,
        clickWired: false,
        retryTimer: null
    });
    var ML = state.ML;
    var POLL_MS = 60000;

    function apiUserBase() {
        return ML.normalizeApiBase().replace(/\/+$/, '') + '/user';
    }

    function $(sel, root) {
        return (root || doc).querySelector(sel);
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
        return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function isLoggedIn() {
        try {
            if (win.maccms && parseInt(win.maccms.userId, 10) > 0) { return true; }
            if (win.MAC && win.MAC.User && Number(win.MAC.User.IsLogin) === 1) { return true; }
        } catch (e) { /* ignore */ }
        return false;
    }

    function eachBell(fn) {
        for (var i = 0; i < state.bells.length; i++) { fn(state.bells[i]); }
    }

    function setBadge(count) {
        eachBell(function (root) {
            var badge = $('.mac-notify-bell__badge', root);
            if (!badge) { return; }
            if (count > 0) {
                badge.hidden = false;
                badge.textContent = count > 99 ? '99+' : String(count);
            } else {
                badge.hidden = true;
                badge.textContent = '';
            }
        });
    }

    function fetchUnread() {
        if (!ML || !ML.apiJson) { return Promise.resolve(0); }
        if (!isLoggedIn()) {
            eachBell(function (root) { root.hidden = true; });
            return Promise.resolve(0);
        }
        eachBell(function (root) { root.hidden = false; });
        return ML.apiJson(apiUserBase() + '/get_notify_unread')
            .then(function (res) {
                if (!res || res.code !== 1) { return 0; }
                var n = (res.info && res.info.unread) || 0;
                setBadge(n);
                return n;
            })
            .catch(function () { return 0; });
    }

    function renderList(root, rows) {
        var scope = (root && root._macNotifyPanel) || root;
        var list = $('.mac-notify-bell__list', scope);
        var empty = $('.mac-notify-bell__empty', scope);
        if (!list) { return; }

        if (!rows || !rows.length) {
            list.innerHTML = '';
            if (empty) { empty.hidden = false; }
            return;
        }

        if (empty) { empty.hidden = true; }
        var notifyPage = ML.memberUrl('notify');
        list.innerHTML = rows.map(function (row) {
            var unread = Number(row.notify_read) === 0;
            return '<a class="mac-notify-bell__item' + (unread ? ' is-unread' : '') +
                '" href="' + esc(notifyPage) + '">' +
                '<span class="mac-notify-bell__item-title">' + esc(row.notify_title || '') + '</span>' +
                '<span class="mac-notify-bell__item-time">' + esc(fmtTime(row.notify_time)) + '</span></a>';
        }).join('');
    }

    function fetchPreview(root) {
        return ML.apiJson(apiUserBase() + '/get_notify_list?page=1&limit=5')
            .then(function (res) {
                if (!res || res.code !== 1) { return; }
                var rows = (res.info && (res.info.list || res.info.rows)) || [];
                renderList(root, rows);
                if (res.info && res.info.unread != null) {
                    setBadge(res.info.unread);
                }
            })
            .catch(function () { /* ignore */ });
    }

    /** 弹层挂到 body，避免被 header/overflow 裁切或错位 */
    function getPanel(root) {
        if (root && root._macNotifyPanel && root._macNotifyPanel.isConnected) {
            return root._macNotifyPanel;
        }
        return root ? $('.mac-notify-bell__dropdown', root) : null;
    }

    function ensurePortaled(root) {
        var panel = getPanel(root);
        if (!panel) { return null; }
        if (panel.parentNode !== doc.body) {
            panel.classList.add('is-portaled');
            panel.setAttribute('data-mac-notify-portaled', '1');
            doc.body.appendChild(panel);
        }
        root._macNotifyPanel = panel;
        panel._macNotifyRoot = root;
        return panel;
    }

    function positionPanel(root, panel) {
        var btn = $('.mac-notify-bell__btn', root);
        if (!btn || !panel) { return; }
        var r = btn.getBoundingClientRect();
        var gap = 6;
        var width = Math.min(320, Math.max(200, win.innerWidth - 24));
        var left = r.right - width;
        if (left < 12) { left = 12; }
        if (left + width > win.innerWidth - 12) {
            left = Math.max(12, win.innerWidth - width - 12);
        }
        panel.style.position = 'fixed';
        panel.style.top = Math.round(r.bottom + gap) + 'px';
        panel.style.left = Math.round(left) + 'px';
        panel.style.right = 'auto';
        panel.style.width = width + 'px';
        panel.style.zIndex = '10050';
    }

    function closeAll() {
        eachBell(function (root) {
            root.classList.remove('is-open');
            var panel = getPanel(root);
            if (panel) { panel.hidden = true; }
            var btn = $('.mac-notify-bell__btn', root);
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
        // 兜底：body 上孤立的 portaled 面板
        var orphans = doc.querySelectorAll('.mac-notify-bell__dropdown.is-portaled:not([hidden])');
        for (var i = 0; i < orphans.length; i++) {
            orphans[i].hidden = true;
        }
    }

    function toggle(root) {
        var open = !root.classList.contains('is-open');
        closeAll();
        if (!open) { return; }
        root.classList.add('is-open');
        var panel = ensurePortaled(root);
        if (panel) {
            panel.hidden = false;
            positionPanel(root, panel);
        }
        var btn = $('.mac-notify-bell__btn', root);
        if (btn) { btn.setAttribute('aria-expanded', 'true'); }
        fetchPreview(root);
    }

    function bindBell(root) {
        if (root.getAttribute('data-mac-notify-panel-bound') === '1') { return; }

        var panel = $('.mac-notify-bell__dropdown', root);
        if (!panel) { return; }

        root.setAttribute('data-mac-notify-panel-bound', '1');
        // 先挂到 body，再绑事件（避免仍受 header overflow 影响）
        ensurePortaled(root);
        panel = getPanel(root);
        if (panel) {
            panel.addEventListener('click', function (e) { e.stopPropagation(); });
        }
    }

    function onReposition() {
        eachBell(function (root) {
            if (!root.classList.contains('is-open')) { return; }
            var panel = getPanel(root);
            if (panel && !panel.hidden) { positionPanel(root, panel); }
        });
    }

    function onDocumentClick(e) {
        var btn = e.target.closest && e.target.closest('.mac-notify-bell__btn');
        if (btn) {
            var root = btn.closest('[data-mac-notify-bell]');
            if (root) {
                e.preventDefault();
                e.stopPropagation();
                if (!ML || !ML.loginUrl) { return; }
                if (!isLoggedIn()) {
                    win.location.href = ML.loginUrl();
                    return;
                }
                toggle(root);
                return;
            }
        }
        if (e.target.closest && (
            e.target.closest('[data-mac-notify-bell]') ||
            e.target.closest('.mac-notify-bell__dropdown')
        )) { return; }
        closeAll();
    }

    function startPolling() {
        if (state.pollTimer) { return; }
        state.pollTimer = win.setInterval(function () {
            if (doc.visibilityState !== 'hidden') {
                fetchUnread();
            }
        }, POLL_MS);
    }

    function wireGlobalListeners() {
        if (!state.wired) {
            state.wired = true;
            doc.addEventListener('visibilitychange', function () {
                if (doc.visibilityState === 'visible') { fetchUnread(); }
            });
            doc.addEventListener('mac:notify-unread-changed', fetchUnread);
        }
        if (!state.clickWired) {
            state.clickWired = true;
            doc.addEventListener('click', onDocumentClick);
            win.addEventListener('resize', onReposition);
            win.addEventListener('scroll', onReposition, true);
        }
    }

    function startBell() {
        if (!ML || !ML.apiJson) {
            if (state.retryTimer) { clearTimeout(state.retryTimer); }
            state.retryTimer = win.setTimeout(startBell, 50);
            return;
        }

        state.bells = Array.prototype.slice.call(doc.querySelectorAll('[data-mac-notify-bell]'));
        if (!state.bells.length) {
            if (state.retryTimer) { clearTimeout(state.retryTimer); }
            state.retryTimer = win.setTimeout(startBell, 50);
            return;
        }
        if (state.retryTimer) {
            clearTimeout(state.retryTimer);
            state.retryTimer = null;
        }

        if (!isLoggedIn()) {
            state.bells.forEach(function (root) { root.hidden = true; });
            win.setTimeout(function () {
                if (!isLoggedIn()) { return; }
                state.bells.forEach(function (root) {
                    root.hidden = false;
                    bindBell(root);
                });
                wireGlobalListeners();
                fetchUnread();
                startPolling();
            }, 800);
            return;
        }

        state.bells.forEach(bindBell);
        wireGlobalListeners();
        fetchUnread();
        startPolling();
    }

    function boot() {
        function start(memberList) {
            if (!memberList || !memberList.apiJson) { return false; }
            state.ML = ML = memberList;
            startBell();
            return true;
        }
        function retry(attempt) {
            if (start(win.MacMemberList)) { return; }
            if (attempt >= 40) { return; }
            win.setTimeout(function () { retry(attempt + 1); }, 50);
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(function (memberList) {
                if (!start(memberList)) { retry(0); }
            });
            return;
        }
        retry(0);
    }

    wireGlobalListeners();
    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
