/**
 * twigspike MacMemberList — 会员列表 / API 共用工具
 * 页面与组件脚本统一通过 window.MacMemberList 访问，URL 从 maccms.path 推导。
 */
(function (win) {
    'use strict';

    var ML = win.MacMemberList || {};
    var MT = win.MacTheme;

    function siteBase() {
        try {
            if (win.maccms && win.maccms.path != null) {
                return String(win.maccms.path).replace(/\/+$/, '');
            }
        } catch (e) { /* ignore */ }
        return '';
    }

    ML.normalizeApiBase = function (raw) {
        var base = String(raw || '').trim();
        if (base) {
            base = base.replace(/\/+$/, '');
            return /api\.php$/i.test(base) ? base : base + '/api.php';
        }
        return siteBase() ? siteBase() + '/api.php' : '/api.php';
    };

    ML.normalizeIndexBase = function (raw) {
        return ML.normalizeApiBase(raw).replace(/\/api\.php$/i, '/index.php');
    };

    ML.memberUrl = function (action, noHtmlSuffix) {
        var base = ML.normalizeIndexBase().replace(/\/+$/, '');
        var seg = String(action || '').replace(/^user\/?/, '').replace(/^\/+|\/+$/g, '');
        if (!seg) { return base; }
        var path = '/user/' + seg;
        if (noHtmlSuffix !== false && !/\.\w+$/.test(seg)) {
            path += '.html';
        }
        return base + path;
    };

    ML.loginUrl = function () {
        return ML.memberUrl('login');
    };

    /** 站内通知跳转：/user/mall#orders → index.php/user/mall.html#orders */
    ML.isSafeNotifyLink = function (href) {
        var s = String(href || '').trim();
        if (!s) { return false; }
        return (s.charAt(0) === '/' && s.charAt(1) !== '/') || /^https?:\/\//i.test(s);
    };

    ML.resolveNotifyLink = function (href) {
        var s = String(href || '').trim();
        if (!s || !ML.isSafeNotifyLink(s)) { return ''; }
        if (/^https?:\/\//i.test(s)) { return s; }
        if (s.indexOf('index.php/') !== -1) {
            var base = siteBase();
            return base + (s.charAt(0) === '/' ? s : '/' + s);
        }
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
        var route = s.replace(/^\//, '').replace(/\.html$/, '').replace(/^user\/?/, '');
        if (route === 'mall_orders' || route.indexOf('mall_orders/') === 0) {
            return ML.memberUrl('mall') + '?open_orders=1' + (query ? '&' + query.replace(/^\?/, '') : '');
        }
        return ML.memberUrl(route) + query + hash;
    };

    ML.apiModuleUrl = function (module, action) {
        return ML.normalizeApiBase().replace(/\/+$/, '') + '/' +
            String(module || '').replace(/^\/+|\/+$/g, '') + '/' +
            String(action || '').replace(/^\/+|\/+$/g, '');
    };

    ML.escAttr = function (val) {
        if (val == null) { return ''; }
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    ML.escText = function (val) {
        return ML.escAttr(val);
    };

    ML.fmtDateTime = function (ts) {
        var n = parseInt(ts, 10);
        if (!n) { return ''; }
        var d = new Date(n * 1000);
        if (isNaN(d.getTime())) { return ''; }
        function pad(v) { return v < 10 ? '0' + v : '' + v; }
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    };

    ML.apiJson = function (url, opts) {
        opts = opts || {};
        return fetch(url, {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: opts.headers || {},
            body: opts.body != null ? opts.body : undefined
        }).then(function (res) {
            return res.json().catch(function () {
                return { code: 0, msg: 'invalid json' };
            });
        });
    };

    ML.toUrlEncodedBody = function (data) {
        if (typeof URLSearchParams !== 'undefined') {
            var params = new URLSearchParams();
            if (data) {
                Object.keys(data).forEach(function (key) {
                    if (data[key] !== undefined && data[key] !== null) {
                        params.set(key, String(data[key]));
                    }
                });
            }
            return params;
        }
        var parts = [];
        if (data) {
            Object.keys(data).forEach(function (key) {
                if (data[key] !== undefined && data[key] !== null) {
                    parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(data[key])));
                }
            });
        }
        return parts.join('&');
    };

    ML.postForm = function (url, data) {
        var payload = {};
        var k;
        if (data && typeof data === 'object') {
            for (k in data) {
                if (Object.prototype.hasOwnProperty.call(data, k)) {
                    payload[k] = data[k];
                }
            }
        }
        var token = (win.maccms && maccms.csrfToken) ? String(maccms.csrfToken) : '';
        // API CsrfGuard：__token__ / X-CSRF-Token，或带 X-Requested-With 的同站 AJAX
        if (token && !payload.__token__) {
            payload.__token__ = token;
        }
        var body = ML.toUrlEncodedBody(payload);
        var bodyStr = typeof body === 'string' ? body : body.toString();
        var headers = {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
        };
        if (token) {
            headers['X-CSRF-Token'] = token;
        }
        return ML.apiJson(url, {
            method: 'POST',
            headers: headers,
            body: bodyStr
        });
    };

    ML.notify = function (msg) {
        if (MT && MT.notify) { MT.notify(msg); return; }
        if (win.MAC && win.MAC.alert) { win.MAC.alert(msg); return; }
        win.alert(msg);
    };

    ML.requireLoginOrRedirect = function (code) {
        var n = Number(code);
        if (n !== 1401 && n !== 1010) { return false; }
        var login = ML.loginUrl();
        if (win.MAC && win.MAC.User && typeof win.MAC.User.openLoginOrNavigate === 'function') {
            win.MAC.User.openLoginOrNavigate(login, win.location.href);
        } else {
            win.location.href = login;
        }
        return true;
    };

    /** 会员列表通用分页 HTML */
    ML.buildPager = function (current, total, ulClass) {
        if (total < 2) { return ''; }
        var cur = Math.max(1, parseInt(current, 10) || 1);
        var max = parseInt(total, 10) || 1;
        var prev = Math.max(1, cur - 1);
        var next = Math.min(max, cur + 1);
        var nums = [];
        var i;

        if (max <= 12) {
            for (i = 1; i <= max; i++) { nums.push(i); }
        } else {
            var map = {};
            function mark(n) { if (n >= 1 && n <= max) { map[n] = 1; } }
            mark(1); mark(max); mark(cur); mark(cur - 1); mark(cur + 1); mark(2); mark(max - 1);
            nums = Object.keys(map).map(function (k) { return parseInt(k, 10); }).sort(function (a, b) {
                return a - b;
            });
        }

        var html = '<ul class="page text-center cleafix ' + ML.escAttr(ulClass || 'mac-member-page') + '">';
        html += '<li><a href="#" data-page="1"' + (cur <= 1 ? ' class="btns-disad"' : '') +
            ' data-lang="string_home">首页</a></li>';
        html += '<li><a href="#" data-page="' + prev + '"' + (cur <= 1 ? ' class="btns-disad"' : '') +
            ' data-lang="string_syy">上一页</a></li>';

        for (i = 0; i < nums.length; i++) {
            if (i > 0 && nums[i] - nums[i - 1] > 1) {
                html += '<li class="hidden-xs"><span class="num">…</span></li>';
            }
            html += '<li class="hidden-xs' + (nums[i] === cur ? ' active' : '') +
                '"><a href="#" data-page="' + nums[i] + '">' + nums[i] + '</a></li>';
        }

        html += '<li class="hidden-xs active"><span class="num btns-disad">' + cur + '/' + max + '</span></li>';
        html += '<li><a href="#" data-page="' + next + '"' + (cur >= max ? ' class="btns-disad"' : '') +
            ' data-lang="string_xyy">下一页</a></li>';
        html += '<li><a href="#" data-page="' + max + '"' + (cur >= max ? ' class="btns-disad"' : '') +
            ' data-lang="string_last_page">尾页</a></li></ul>';
        return html;
    };

    ML.bindPager = function (root, pagerSel, onPage) {
        if (!root) { return; }
        root.addEventListener('click', function (e) {
            var link = e.target.closest(pagerSel + ' a[data-page]');
            if (!link || link.classList.contains('btns-disad')) { return; }
            e.preventDefault();
            var page = parseInt(link.getAttribute('data-page'), 10);
            if (page > 0) { onPage(page); }
        });
    };

    ML.readForm = function (form) {
        var data = {};
        if (!form) { return data; }
        var fields = form.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            if (!el.name || el.disabled) { continue; }
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { continue; }
            data[el.name] = el.value;
        }
        return data;
    };

    win.MacMemberList = ML;
    win.MacMemberReady = function (cb) {
        if (typeof cb !== 'function') { return; }
        try { cb(ML); } catch (e) { /* ignore */ }
    };
    var wait = win.__macMemberWait || [];
    win.__macMemberWait = [];
    for (var i = 0; i < wait.length; i++) {
        try { wait[i](ML); } catch (e) { /* ignore */ }
    }
})(window);
