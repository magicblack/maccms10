(function () {
    'use strict';

    var FAILOVER_LOADED = false;

    function tplBase() {
        var m = window.maccms || {};
        var p = m.path_tpl || '';
        return String(p).replace(/\/+$/, '') + '/page-builder-components/_shared/';
    }

    function loadCss(href) {
        if (!href || document.querySelector('link[data-vod-failover-css="1"]')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute('data-vod-failover-css', '1');
        document.head.appendChild(link);
    }

    function loadFailoverJs(src, cb) {
        if (FAILOVER_LOADED || document.querySelector('script[data-vod-failover-js="1"]')) {
            if (cb) { cb(); }
            return;
        }
        var s = document.createElement('script');
        s.src = src;
        s.defer = true;
        s.setAttribute('data-vod-failover-js', '1');
        s.onload = function () {
            FAILOVER_LOADED = true;
            if (cb) { cb(); }
        };
        document.head.appendChild(s);
    }

    function bootFailover() {
        if (!document.getElementById('vod-play-failover-config')) {
            return;
        }
        var base = tplBase();
        loadCss(base + 'css/vod-play-failover.css?v=1');
        loadFailoverJs(base + 'js/vod-play-failover.js?v=3');
    }

    function recordPlayUlog() {
        var root = document.querySelector('[data-component="vod_player"]');
        if (!root) {
            return;
        }
        var el = root.querySelector('.mac_ulog_set');
        if (!el) {
            return;
        }

        var mac = window.maccms || {};
        var uid = Number(mac.userId || mac.user_id || 0);
        if (!(uid > 0)) {
            return;
        }
        if (!window.MacTheme || typeof window.MacTheme.post !== 'function') {
            return;
        }

        window.MacTheme.post('user/ajax_ulog', {
            ac: 'set',
            mid: el.getAttribute('data-mid') || '1',
            id: el.getAttribute('data-id') || '',
            sid: el.getAttribute('data-sid') || '1',
            nid: el.getAttribute('data-nid') || '1',
            type: el.getAttribute('data-type') || '4'
        }, function () {});
    }

    function setReportOpen(open) {
        var panel = document.getElementById('pbs-vod-player-report');
        if (!panel) {
            return;
        }
        if (open) {
            panel.hidden = false;
            panel.setAttribute('aria-hidden', 'false');
            var ta = panel.querySelector('textarea[name="gbook_content"]');
            if (ta) {
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            }
            return;
        }
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
    }

    function bindReportUi(root) {
        if (!root) {
            return;
        }
        root.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || !t.closest) {
                return;
            }
            if (t.closest('[data-vod-player-report]')) {
                ev.preventDefault();
                setReportOpen(true);
                return;
            }
            if (t.closest('[data-vod-player-report-close]')) {
                ev.preventDefault();
                setReportOpen(false);
                return;
            }
            if (t.closest('[data-vod-player-refresh]')) {
                ev.preventDefault();
                window.location.reload();
            }
            var captcha = t.closest('[data-vod-player-report-captcha]');
            if (captcha) {
                captcha.src = captcha.src.split('?')[0] + '?v=' + Date.now();
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' || ev.key === 'Esc') {
                setReportOpen(false);
            }
        });
    }

    function boot() {
        var root = document.querySelector('[data-component="vod_player"]');
        recordPlayUlog();
        bootFailover();
        bindReportUi(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
