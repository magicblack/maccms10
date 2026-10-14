/**
 * 全局充值弹层：金币充值 + 金币不足时 VIP 现金支付（member_upgrade）
 * 行为对齐 template/default/html/widget/recharge_modal.html
 */
(function (win, doc) {
    'use strict';

    var sheet = doc.getElementById('recharge-overlay');
    if (!sheet) { return; }

    var MT = win.MacTheme;
    var _context = { mode: 'recharge' };
    var _config = null;
    var _pollTimer = null;
    var _pollGen = 0;
    var _pollXhr = null;
    var _isPaying = false;

    function $(sel, root) {
        return (root || sheet).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || sheet).querySelectorAll(sel));
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function notify(msg) {
        var m = String(msg || '');
        if (win.MemberDialog && win.MemberDialog.notify) {
            win.MemberDialog.notify(m);
            return;
        }
        if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
            MAC.Pop.StatusSheet({ type: 'error', message: m, hideOkButton: true, autoCloseMs: 2400 });
            return;
        }
        if (MT && MT.notify) { MT.notify(m); return; }
    }

    function getLang(key, zh, en) {
        if (typeof MAC !== 'undefined' && MAC.GetLang) {
            var t = MAC.GetLang(key);
            if (t) { return t; }
        }
        if (MT && MT.t) {
            var tr = MT.t(key);
            if (tr && tr !== key) { return tr; }
        }
        return String(localStorage.getItem('lang')) === '1' ? en : zh;
    }

    function csrfToken() {
        var m = doc.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    function abortXhr(xhr) {
        try {
            if (xhr && xhr.readyState !== 4 && xhr.abort) { xhr.abort(); }
        } catch (e) {}
    }

    function apiGet(url, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            cb(null, res);
        };
        xhr.send();
        return xhr;
    }

    function apiPost(url, data, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var token = csrfToken();
        if (token) { xhr.setRequestHeader('X-CSRF-Token', token); }
        var body = [];
        Object.keys(data || {}).forEach(function (k) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
            }
        });
        if (token) { body.push('__token__=' + encodeURIComponent(token)); }
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            cb(null, res, xhr.responseText);
        };
        xhr.send(body.join('&'));
        return xhr;
    }

    function apiBase() {
        var u = sheet.getAttribute('data-rc-order-create-url') || sheet.getAttribute('data-url-buy') || '';
        var idx = u.indexOf('/order/create');
        if (idx !== -1) { return u.substring(0, idx); }
        return String((win.maccms && win.maccms.path) || '/').replace(/\/+$/, '') + '/api.php';
    }

    function loginUrl() {
        return sheet.getAttribute('data-url-login') || '';
    }

    function openLoginOrNavigate() {
        var u = loginUrl();
        if (!u) { return; }
        closeSheet(false);
        if (typeof MAC !== 'undefined' && MAC.User && typeof MAC.User.openLoginOrNavigate === 'function') {
            MAC.User.openLoginOrNavigate(u, win.location.href || '/');
        } else {
            win.location.href = u + (u.indexOf('?') >= 0 ? '&' : '?') + 'url=' + encodeURIComponent(win.location.href || '/');
        }
    }

    function payIcon(key) {
        var tpl = doc.getElementById('rcPayIconsTpl');
        if (!tpl) { return ''; }
        var k = String(key || '').toLowerCase();
        var node;
        if (k.indexOf('alipay') !== -1 || k.indexOf('zhifubao') !== -1) {
            node = tpl.querySelector('[data-icon="alipay"]');
        } else if (k.indexOf('wechat') !== -1 || k.indexOf('weixin') !== -1) {
            node = tpl.querySelector('[data-icon="wechat"]');
        } else if (k.indexOf('qq') !== -1) {
            node = tpl.querySelector('[data-icon="qq"]');
        } else {
            node = tpl.querySelector('[data-icon="wallet"]');
        }
        return node ? node.innerHTML : '';
    }

    function methodsMap(methods) {
        var out = {};
        if (!methods || !methods.length) { return out; }
        for (var i = 0; i < methods.length; i++) {
            var m = methods[i];
            if (m && Number(m.enabled) === 1 && m.key) {
                out[m.key] = m.name != null ? String(m.name) : String(m.key);
            }
        }
        return out;
    }

    function findMethod(methods, key) {
        if (!methods || !methods.length) { return null; }
        var k = String(key || '').toLowerCase();
        for (var i = 0; i < methods.length; i++) {
            if (methods[i] && String(methods[i].key || '').toLowerCase() === k) { return methods[i]; }
        }
        return null;
    }

    function normalizeConfig(res) {
        var info = res && (res.info || res.data);
        if (!res || Number(res.code) !== 1 || !info) { return null; }
        return {
            is_login: info.is_login != null ? info.is_login : 0,
            user_points: info.user_points != null ? info.user_points : 0,
            pay_min: info.min != null ? info.min : (info.pay_min != null ? info.pay_min : 1),
            pay_scale: info.scale != null ? info.scale : (info.pay_scale != null ? info.pay_scale : 1),
            ext_list: methodsMap(info.methods),
            methods: info.methods || []
        };
    }

    function renderPaytypes(container, paytypes) {
        if (!container) { return; }
        var isEn = String(localStorage.getItem('lang')) === '1';
        var html = '';
        if (paytypes && paytypes.length) {
            for (var i = 0; i < paytypes.length; i++) {
                var pt = paytypes[i];
                if (!pt) { continue; }
                var val = pt.value != null ? String(pt.value) : '';
                var lbl = isEn
                    ? (pt.label_en != null ? pt.label_en : (pt.label != null ? pt.label : val))
                    : (pt.label != null ? pt.label : (pt.label_en != null ? pt.label_en : val));
                html += '<div class="pt-card" data-type="' + esc(val) + '" role="button" tabindex="0">' + esc(lbl) + '</div>';
            }
        }
        container.innerHTML = html;
    }

    function renderPaymentGrid(grid, extList) {
        if (!grid) { return; }
        var emptyMsg = getLang('string_pay_no_method', '暂无可用支付方式', 'No payment method');
        if (!extList || !Object.keys(extList).length) {
            grid.innerHTML = '<div class="pm-empty">' + esc(emptyMsg) + '</div>';
            return;
        }
        var html = '';
        for (var k in extList) {
            if (!Object.prototype.hasOwnProperty.call(extList, k)) { continue; }
            html += '<div class="pm-card" data-payment="' + esc(k) + '" role="button" tabindex="0">' +
                '<div class="pm-icon">' + payIcon(k) + '</div>' +
                '<div class="pm-name">' + esc(extList[k]) + '</div></div>';
        }
        grid.innerHTML = html;
    }

    function showPanel(id) {
        ['rcInitLoading', 'rcLoginPanel', 'rcAmountPanel', 'rcPayPanel', 'rcLoadingPanel'].forEach(function (pid) {
            var el = doc.getElementById(pid);
            if (el) { el.hidden = pid !== id; }
        });
    }

    function resetPanels() {
        abortPaymentPoll();
        showPanel('rcInitLoading');
        var loading = doc.getElementById('rcInitLoading');
        if (loading) {
            loading.textContent = getLang('string_loading', '加载中…', 'Loading…');
        }
        var payInput = doc.getElementById('rcPayment');
        var ptInput = doc.getElementById('rcPaytype');
        if (payInput) { payInput.value = ''; }
        if (ptInput) { ptInput.value = ''; }
        var ptBox = doc.getElementById('rcPaytypeBox');
        if (ptBox) { ptBox.hidden = true; }
        var payForm = doc.getElementById('rcPayForm');
        if (payForm) { payForm.hidden = false; }
        resetLoadingVisuals();
        _isPaying = false;
    }

    function resetLoadingVisuals() {
        var qb = doc.getElementById('rcLoadingQrcodeBlock');
        var host = doc.getElementById('rcLoadingQrcodeHost');
        var sw = doc.getElementById('rcSpinnerWrap');
        var title = doc.getElementById('rcLoadingTitle');
        var hint = doc.getElementById('rcLoadingHint');
        var isEn = String(localStorage.getItem('lang')) === '1';
        if (qb) { qb.hidden = true; qb.setAttribute('aria-hidden', 'true'); }
        if (host) { host.innerHTML = ''; }
        if (sw) { sw.hidden = false; }
        if (title) {
            title.textContent = getLang('string_pay_waiting_title', '等待支付结果', 'Waiting for payment');
        }
        if (hint) {
            hint.innerHTML = isEn
                ? 'Complete payment in the new tab.<br>This page updates when paid.'
                : '请在新标签页完成支付<br>支付成功后将自动更新';
        }
    }

    function bumpZIndex() {
        try {
            var maxZ = 0;
            var nodes = doc.querySelectorAll('body *');
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                if (!el || el.id === 'recharge-overlay' || (el.closest && el.closest('#recharge-overlay'))) {
                    continue;
                }
                var z = win.getComputedStyle(el).zIndex;
                var n = parseInt(z, 10);
                if (!isNaN(n)) { maxZ = Math.max(maxZ, n); }
            }
            sheet.style.setProperty('z-index', String(maxZ + 10), 'important');
        } catch (e) {}
    }

    function openSheet() {
        if (doc.body) { doc.body.appendChild(sheet); }
        sheet.hidden = false;
        sheet.classList.add('is-open', 'show');
        doc.body.style.overflow = 'hidden';
        bumpZIndex();
    }

    function closeSheet(force) {
        if (_isPaying && !force) { return; }
        abortPaymentPoll();
        sheet.classList.remove('is-open', 'show');
        sheet.hidden = true;
        doc.body.style.overflow = '';
        _context = { mode: 'recharge' };
    }

    function renderAmountPanel(cfg) {
        showPanel('rcAmountPanel');
        var pts = doc.getElementById('rcPoints');
        if (pts) { pts.textContent = cfg.user_points; }
        var min = cfg.pay_min;
        var scale = cfg.pay_scale;
        var isEn = String(localStorage.getItem('lang')) === '1';
        var tips = doc.getElementById('rcTips');
        if (tips) {
            tips.textContent = isEn
                ? ('Minimum top-up is ' + min + ' CNY. 1 CNY = ' + scale + ' points.')
                : ('最小充值金额为' + min + '元，1元可以兑换' + scale + '金币');
        }
        var input = doc.getElementById('rcPriceInput');
        if (input) { input.value = min; }
        var grid = doc.getElementById('rcAmountsGrid');
        var imgBase = String(sheet.getAttribute('data-rc-img-base') || '').replace(/\/?$/, '/');
        if (grid) {
            var amounts = [50, 100, 200, 500];
            grid.innerHTML = amounts.map(function (a) {
                var ribbon = a === 200
                    ? '<img class="member-pay-amount__ribbon" src="' + esc(imgBase + 'recharge-ribbon-hot.png') + '" width="48" height="48" alt="">'
                    : '';
                var active = a === min ? ' is-active' : '';
                return '<div class="member-pay-amount' + active + '" data-amount="' + a + '" role="button" tabindex="0">' +
                    ribbon + '<span class="member-pay-amount__yen">¥</span><span class="member-pay-amount__num">' + a + '</span></div>';
            }).join('');
        }
        if (win.MacUserCoupon && typeof win.MacUserCoupon.initModalPanel === 'function') {
            win.MacUserCoupon.initModalPanel();
        }
    }

    function showPayPanel(orderData) {
        resetLoadingVisuals();
        var codeEl = doc.getElementById('rcOrderCode');
        var priceEl = doc.getElementById('rcOrderPrice');
        var idInput = doc.getElementById('rcPayOrderId');
        var codeInput = doc.getElementById('rcPayOrderCode');
        if (codeEl) { codeEl.textContent = orderData.order_code || '-'; }
        if (priceEl) { priceEl.textContent = orderData.order_price != null ? orderData.order_price : '0'; }
        if (idInput) { idInput.value = orderData.order_id != null ? String(orderData.order_id) : ''; }
        if (codeInput) { codeInput.value = orderData.order_code || ''; }
        renderPaymentGrid(doc.getElementById('rcPayGrid'), (_config && _config.ext_list) || {});
        $$('.pm-card', sheet).forEach(function (c) { c.classList.remove('active'); });
        var ptBox = doc.getElementById('rcPaytypeBox');
        if (ptBox) { ptBox.hidden = true; }
        var ptGrid = doc.getElementById('rcPaytypeGrid');
        if (ptGrid) { ptGrid.innerHTML = ''; }
        showPanel('rcPayPanel');
        var title = doc.getElementById('recharge-sheet-title');
        if (title) {
            if (_context.mode === 'member_upgrade' || _context.orderBiz === 'member_upgrade') {
                title.textContent = getLang('string_user_cash_pay_open', '现金支付', 'Cash pay');
            } else {
                title.textContent = getLang('user_zxcz', '在线充值', 'Recharge');
            }
        }
        var tips = doc.getElementById('rcTips');
        if (tips && (_context.mode === 'member_upgrade' || _context.orderBiz === 'member_upgrade')) {
            tips.textContent = '会员订单已创建，请选择支付方式完成开通。';
        }
    }

    function finishCreateOrderFromApi(res) {
        var row = res && (res.info || res.data);
        if (!row || !row.order_code) {
            if (win.MacUserCoupon && win.MacUserCoupon.handleOrderError) {
                win.MacUserCoupon.handleOrderError(res, win.MacUserCoupon.getByMount('recharge-modal'));
            } else {
                notify((res && res.msg) || getLang('string_load_err', '创建订单失败', 'Order failed'));
            }
            return;
        }
        if (row.order_id != null && String(row.order_id) !== '') {
            showPayPanel(row);
            return;
        }
        apiGet(apiBase() + '/order/get_detail?order_code=' + encodeURIComponent(row.order_code), function (_e, r2) {
            var inf = r2 && (r2.info || r2.data);
            if (r2 && Number(r2.code) === 1 && inf && inf.order_id != null) {
                showPayPanel({
                    order_id: inf.order_id,
                    order_code: inf.order_code || row.order_code,
                    order_price: row.order_price != null ? row.order_price : inf.order_price
                });
            } else {
                notify((r2 && r2.msg) || getLang('string_load_err', '获取订单信息失败', 'Failed to load order'));
            }
        });
    }

    function createMemberUpgradeOrder() {
        var groupId = _context.group_id;
        var groupLong = _context.long;
        if (!groupId || !groupLong) {
            notify('会员参数错误，请返回重试');
            closeSheet(true);
            return;
        }
        var loading = doc.getElementById('rcInitLoading');
        if (loading) { loading.textContent = '正在创建会员订单...'; }
        var data = { group_id: groupId, long: groupLong };
        if (_context.coupon_user_id) {
            data.coupon_user_id = _context.coupon_user_id;
        } else if (win.MacUserCoupon && typeof win.MacUserCoupon.getByMount === 'function') {
            var vipPanel = win.MacUserCoupon.getByMount('buy-upgrade');
            var cuid = vipPanel && vipPanel.getSelectedCouponUserId();
            if (cuid) { data.coupon_user_id = cuid; }
        }
        var url = sheet.getAttribute('data-rc-upgrade-order-create-url') || (apiBase() + '/user/upgrade_order_create');
        apiPost(url, data, function (_err, res) {
            var row = res && (res.data || res.info);
            if (!res || Number(res.code) !== 1 || !row) {
                if (loading) { loading.textContent = (res && res.msg) || '创建订单失败，请重试'; }
                if (win.MacUserCoupon && win.MacUserCoupon.handleOrderError) {
                    win.MacUserCoupon.handleOrderError(res, win.MacUserCoupon.getByMount('buy-upgrade'), { silent: true });
                } else {
                    notify((res && res.msg) || '创建订单失败');
                }
                return;
            }
            _context.orderBiz = 'member_upgrade';
            showPayPanel(row);
        });
    }

    function loadSheetConfig() {
        resetPanels();
        var cfgUrl = sheet.getAttribute('data-rc-config-url') || sheet.getAttribute('data-url-recharge-config') || '';
        apiGet(cfgUrl, function (_err, res) {
            _config = normalizeConfig(res);
            if (!_config) {
                notify((res && res.msg) || getLang('string_load_err', '加载失败', 'Failed to load'));
                closeSheet(true);
                return;
            }
            if (Number(_config.is_login) !== 1) {
                showPanel('rcLoginPanel');
                return;
            }
            if (_context.mode === 'member_upgrade') {
                createMemberUpgradeOrder();
                return;
            }
            renderAmountPanel(_config);
        });
    }

    function stopPoll() {
        if (_pollTimer) {
            clearInterval(_pollTimer);
            _pollTimer = null;
        }
    }

    function abortPaymentPoll() {
        _pollGen += 1;
        abortXhr(_pollXhr);
        _pollXhr = null;
        stopPoll();
    }

    function showSuccess(points) {
        resetLoadingVisuals();
        showPanel('rcInitLoading');
        _isPaying = false;
        var isMemberUp = _context.mode === 'member_upgrade' || _context.orderBiz === 'member_upgrade';
        var benefitsUrl = String(sheet.getAttribute('data-url-benefits') || '').trim();
        closeSheet(true);
        _context = { mode: 'recharge' };

        if (isMemberUp) {
            if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.VipUpgradeSheet === 'function') {
                MAC.Pop.VipUpgradeSheet({
                    benefitsUrl: benefitsUrl,
                    onClose: function () {
                        try {
                            $$('.mac_pop_bg, .mac_pop', doc).forEach(function (n) {
                                if (n && n.parentNode) { n.parentNode.removeChild(n); }
                            });
                        } catch (e) {}
                        win.location.reload();
                    }
                });
            } else {
                try {
                    $$('.mac_pop_bg, .mac_pop', doc).forEach(function (n) {
                        if (n && n.parentNode) { n.parentNode.removeChild(n); }
                    });
                } catch (e2) {}
                win.location.reload();
            }
            return;
        }

        var detail = '';
        if (points > 0) {
            var tpl = getLang('string_mac_recharge_detail_points', '+%s 金币', '+%s points');
            detail = tpl.replace('%s', String(points));
        }
        var title = getLang('string_mac_recharge_ok', '充值成功', 'Recharge successful');
        var needReloadUser = /\/user\//i.test(String(win.location.pathname || ''));
        if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
            MAC.Pop.StatusSheet({
                type: 'success',
                message: title,
                detail: detail,
                onClose: function () {
                    if (needReloadUser) { win.location.reload(); }
                }
            });
        } else {
            if (needReloadUser) { win.location.reload(); }
        }
    }

    function startPoll(orderCode) {
        abortPaymentPoll();
        var myGen = _pollGen;
        var queryUrl = sheet.getAttribute('data-rc-order-query-url') || sheet.getAttribute('data-url-order-query') || (apiBase() + '/order/check_status');
        var count = 0;

        function doQuery() {
            if (myGen !== _pollGen) { return; }
            count += 1;
            if (count > 30) {
                if (myGen !== _pollGen) { return; }
                abortPaymentPoll();
                exitLoading();
                notify(getLang('string_pay_poll_timeout', '支付结果查询超时，如已支付请稍后查看金币变化', 'Payment status timeout.'));
                return;
            }
            abortXhr(_pollXhr);
            _pollXhr = apiGet(queryUrl + '?order_code=' + encodeURIComponent(orderCode), function (_e2, r2) {
                _pollXhr = null;
                if (myGen !== _pollGen) { return; }
                var d = r2 && (r2.info || r2.data);
                if (r2 && Number(r2.code) === 1 && d && Number(d.order_status) === 1) {
                    abortPaymentPoll();
                    exitLoading();
                    showSuccess(Number(d.order_points || d.points || 0));
                }
            });
        }

        _pollTimer = setInterval(function () {
            if (myGen !== _pollGen) { return; }
            if (count === 3) {
                stopPoll();
                _pollTimer = setInterval(doQuery, 10000);
            }
            doQuery();
        }, 5000);
    }

    function extractRedirectUrlFromHtml(html) {
        if (!html || typeof html !== 'string') { return ''; }
        var m = html.match(/(?:(?:window|top|parent|self)\.)?location\.href\s*=\s*["']([^"']+)["']/i);
        return m ? m[1] : '';
    }

    function looksLikePaymentHtml(s) {
        if (!s || typeof s !== 'string') { return false; }
        var t = s.replace(/^\uFEFF/, '').trim();
        if (!t || t.charAt(0) !== '<') { return false; }
        if (/<\s*(script|form|html|body)\b/i.test(t)) { return true; }
        if (/location\.(href|replace)\s*=/i.test(t)) { return true; }
        return false;
    }

    function isExecutablePaymentHtml(html) {
        if (!html || typeof html !== 'string') { return false; }
        var s = html.replace(/^\uFEFF/, '').replace(/^\s+/, '');
        if (!s) { return false; }
        if (s.charAt(0) === '<') { return true; }
        return /<\s*(script|form|html|head|body|iframe|meta)\b/i.test(html);
    }

    function openPaymentHtmlDocument(html) {
        if (!html || typeof html !== 'string') { return; }
        if (isExecutablePaymentHtml(html)) {
            var w0 = win.open('', '_blank');
            if (w0) {
                try {
                    w0.document.open();
                    w0.document.write(html);
                    w0.document.close();
                } catch (e) {}
            }
            return;
        }
        var jumpUrl = extractRedirectUrlFromHtml(html);
        if (jumpUrl) {
            win.open(jumpUrl, '_blank');
            return;
        }
        var w = win.open('', '_blank');
        if (w) {
            try {
                w.document.open();
                w.document.write(html);
                w.document.close();
            } catch (e2) {}
        }
    }

    function openPaymentChannel(gopayInfo, opts) {
        var opts0 = opts || {};
        var target = opts0.target === '_self' ? '_self' : '_blank';
        var pd = (gopayInfo && gopayInfo.payment_data) || {};
        var type = pd.type;

        function go(u) {
            if (!u) { return false; }
            if (target === '_self') { win.location.href = u; }
            else { win.open(u, '_blank'); }
            return true;
        }

        if (type === 'qrcode') {
            if (!pd.code_url) { return false; }
            enterLoading(pd.code_url);
            return true;
        }

        if ((type === 'form' || type === 'html') && pd.html) {
            if (target === '_self') {
                if (isExecutablePaymentHtml(pd.html)) {
                    try {
                        doc.open();
                        doc.write(pd.html);
                        doc.close();
                    } catch (e) {
                        return false;
                    }
                    return true;
                }
                var ju = extractRedirectUrlFromHtml(pd.html);
                if (ju) {
                    win.location.href = ju;
                    return true;
                }
                try {
                    doc.open();
                    doc.write(pd.html);
                    doc.close();
                } catch (e2) {
                    return false;
                }
                return true;
            }
            openPaymentHtmlDocument(pd.html);
            return true;
        }

        if (type === 'data' && pd.data && typeof pd.data === 'object') {
            var payUrl = pd.data.pay_url || pd.data.url;
            if (payUrl) { return go(payUrl); }
            return false;
        }

        if (pd.action_url && pd.fields && typeof pd.fields === 'object' && Object.keys(pd.fields).length > 0) {
            var form = doc.createElement('form');
            form.method = 'POST';
            form.action = pd.action_url;
            form.target = target === '_self' ? '_self' : '_blank';
            Object.keys(pd.fields).forEach(function (k) {
                var inp = doc.createElement('input');
                inp.type = 'hidden';
                inp.name = k;
                inp.value = pd.fields[k];
                form.appendChild(inp);
            });
            doc.body.appendChild(form);
            form.submit();
            doc.body.removeChild(form);
            return true;
        }

        return false;
    }

    function parseGopayResponse(text) {
        var s = String(text == null ? '' : text).replace(/^\uFEFF/, '').trim();
        if (!s) { return { code: 0, msg: 'empty response' }; }
        if (s.charAt(0) === '{' || s.charAt(0) === '[') {
            try { return JSON.parse(s); } catch (e) { return { code: 0, msg: 'invalid json' }; }
        }
        if (looksLikePaymentHtml(s)) {
            return {
                code: 1,
                msg: 'pay_html',
                info: { payment_data: { type: 'html', html: s } }
            };
        }
        return { code: 0, msg: s.substring(0, 120) };
    }

    function enterLoading(codeUrl) {
        _isPaying = true;
        showPanel('rcLoadingPanel');
        var payForm = doc.getElementById('rcPayForm');
        if (payForm) { payForm.hidden = true; }
        if (codeUrl) {
            var qb = doc.getElementById('rcLoadingQrcodeBlock');
            var host = doc.getElementById('rcLoadingQrcodeHost');
            var sw = doc.getElementById('rcSpinnerWrap');
            var hint = doc.getElementById('rcLoadingHint');
            var isEn = String(localStorage.getItem('lang')) === '1';
            if (qb) { qb.hidden = false; qb.removeAttribute('aria-hidden'); }
            if (sw) { sw.hidden = true; }
            if (hint) {
                hint.innerHTML = isEn
                    ? 'Scan the QR code with WeChat.<br>This page will update when paid.'
                    : '请使用微信扫码完成支付<br>支付成功后将自动更新';
            }
            if (host && win.macRenderPaymentQrcode) {
                host.innerHTML = '';
                win.macRenderPaymentQrcode(host, codeUrl, function (err) {
                    if (err) {
                        notify(getLang('string_pay_qr_fail', '二维码生成失败，请刷新重试', 'Could not render QR code.'));
                    }
                });
            }
        } else {
            resetLoadingVisuals();
        }
    }

    function exitLoading() {
        _isPaying = false;
        resetLoadingVisuals();
    }

    function bindPaymentPick() {
        sheet.addEventListener('click', function (e) {
            var card = e.target.closest('.pm-card');
            if (!card || !sheet.contains(card)) { return; }
            $$('.pm-card', sheet).forEach(function (c) { c.classList.remove('active'); });
            card.classList.add('active');
            var pay = card.getAttribute('data-payment') || '';
            var payInput = doc.getElementById('rcPayment');
            if (payInput) { payInput.value = pay; }
            $$('.pt-card', sheet).forEach(function (c) { c.classList.remove('active'); });
            var ptInput = doc.getElementById('rcPaytype');
            if (ptInput) { ptInput.value = ''; }
            var method = findMethod(_config && _config.methods, pay);
            var ptBox = doc.getElementById('rcPaytypeBox');
            var ptGrid = doc.getElementById('rcPaytypeGrid');
            if (method && method.paytypes && method.paytypes.length) {
                renderPaytypes(ptGrid, method.paytypes);
                if (ptBox) { ptBox.hidden = false; }
            } else if (ptBox) {
                ptBox.hidden = true;
                if (ptGrid) { ptGrid.innerHTML = ''; }
            }
        });
        sheet.addEventListener('click', function (e) {
            var pt = e.target.closest('.pt-card');
            if (!pt || !sheet.contains(pt)) { return; }
            $$('.pt-card', sheet).forEach(function (c) { c.classList.remove('active'); });
            pt.classList.add('active');
            var val = pt.getAttribute('data-type') || '';
            var ptInput = doc.getElementById('rcPaytype');
            if (ptInput) { ptInput.value = val; }
        });
    }

    sheet.addEventListener('click', function (e) {
        if (_isPaying) { return; }
        if (e.target.closest('[data-recharge-close]') || e.target === sheet.querySelector('.member-recharge-sheet__backdrop')) {
            closeSheet(true);
        }
        var amt = e.target.closest('.member-pay-amount');
        if (amt) {
            $$('.member-pay-amount', sheet).forEach(function (a) { a.classList.remove('is-active'); });
            amt.classList.add('is-active');
            var inp = doc.getElementById('rcPriceInput');
            if (inp) { inp.value = amt.getAttribute('data-amount') || ''; }
            if (win.MacUserCoupon && win.MacUserCoupon.getByMount) {
                var p = win.MacUserCoupon.getByMount('recharge-modal');
                if (p && p.scheduleRefresh) { p.scheduleRefresh(); }
            }
        }
    });

    var priceInput = doc.getElementById('rcPriceInput');
    if (priceInput) {
        priceInput.addEventListener('input', function () {
            if (win.MacUserCoupon && win.MacUserCoupon.getByMount) {
                var p = win.MacUserCoupon.getByMount('recharge-modal');
                if (p && p.scheduleRefresh) { p.scheduleRefresh(); }
            }
        });
    }

    var goLogin = doc.getElementById('rcGoLogin');
    if (goLogin) {
        goLogin.addEventListener('click', function (e) {
            e.preventDefault();
            openLoginOrNavigate();
        });
    }

    var submit = doc.getElementById('rcSubmitBtn');
    if (submit) {
        submit.addEventListener('click', function () {
            var price = String((doc.getElementById('rcPriceInput') || {}).value || '').trim();
            if (Number(price) < 1) {
                notify(getLang('string_pay_price_err', '请输入充值金额', 'Enter amount'));
                return;
            }
            if (_config && Number(price) < Number(_config.pay_min)) {
                notify(getLang('user_zcts1', '最小充值金额为', 'Min ') + _config.pay_min + getLang('user_yuan', '元', ''));
                return;
            }
            if (!_config || Number(_config.is_login) !== 1) {
                notify(getLang('string_pay_login_first', '请先登录后完成下单支付', 'Please login first'));
                openLoginOrNavigate();
                return;
            }
            var orderData = { price: price };
            if (win.MacUserCoupon && typeof win.MacUserCoupon.getByMount === 'function') {
                var couponPanel = win.MacUserCoupon.getByMount('recharge-modal');
                var cuid = couponPanel && couponPanel.getSelectedCouponUserId();
                if (cuid) { orderData.coupon_user_id = cuid; }
            }
            submit.disabled = true;
            var labelBefore = submit.textContent;
            submit.textContent = getLang('string_pay_ing', '处理中...', 'Processing...');
            var createUrl = sheet.getAttribute('data-rc-order-create-url') || sheet.getAttribute('data-url-buy') || '';
            apiPost(createUrl, orderData, function (_err, res) {
                submit.disabled = false;
                submit.textContent = labelBefore;
                if (!res || Number(res.code) !== 1) {
                    if (win.MacUserCoupon && win.MacUserCoupon.handleOrderError) {
                        win.MacUserCoupon.handleOrderError(res, win.MacUserCoupon.getByMount('recharge-modal'));
                    } else {
                        notify((res && res.msg) || getLang('string_load_err', '创建订单失败', 'Order failed'));
                    }
                    return;
                }
                finishCreateOrderFromApi(res);
            });
        });
    }

    var payForm = doc.getElementById('rcPayForm');
    if (payForm) {
        payForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var payment = String((doc.getElementById('rcPayment') || {}).value || '').trim();
            if (!payment) {
                notify(getLang('string_pay_zffs_err', '请选择支付方式', 'Select payment method'));
                return;
            }
            var actPm = sheet.querySelector('.pm-card.active');
            var pmFromUi = actPm ? String(actPm.getAttribute('data-payment') || '').trim() : '';
            if (!actPm || pmFromUi.toLowerCase() !== payment.toLowerCase()) {
                notify(getLang('string_pay_zffs_err', '请选择支付方式', 'Select payment method'));
                return;
            }
            var selM = findMethod(_config && _config.methods, payment);
            if (!selM || Number(selM.enabled) !== 1) {
                notify(getLang('string_pay_zffs_err', '请选择支付方式', 'Select payment method'));
                return;
            }
            var needPaytype = selM.paytypes && selM.paytypes.length > 0;
            if (needPaytype) {
                var ptVal = String((doc.getElementById('rcPaytype') || {}).value || '').trim();
                var ptActive = sheet.querySelector('#rcPaytypeGrid .pt-card.active');
                var ptFromUi = ptActive ? String(ptActive.getAttribute('data-type') || '').trim() : '';
                if (!ptVal || !ptActive || ptFromUi !== ptVal) {
                    notify(getLang('string_pay_zflx_err', '请选择支付类型', 'Select pay type'));
                    return;
                }
                var ptOk = false;
                for (var pi = 0; pi < selM.paytypes.length; pi++) {
                    if (selM.paytypes[pi] && String(selM.paytypes[pi].value) === ptVal) {
                        ptOk = true;
                        break;
                    }
                }
                if (!ptOk) {
                    notify(getLang('string_pay_zflx_err', '请选择支付类型', 'Select pay type'));
                    return;
                }
            }
            var orderId = String((doc.getElementById('rcPayOrderId') || {}).value || '').trim();
            var orderCode = String((doc.getElementById('rcPayOrderCode') || {}).value || '').trim();
            if (!orderId || !orderCode) {
                notify(getLang('string_load_err', '订单信息不完整', 'Order incomplete'));
                return;
            }
            var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
            var isWeixinMobile = isMobile && payment.toLowerCase() === 'weixin';
            var data = { order_id: orderId, order_code: orderCode, payment: payment };
            var paytype = String((doc.getElementById('rcPaytype') || {}).value || '').trim();
            if (paytype) { data.paytype = paytype; }
            var gopayUrl = sheet.getAttribute('data-rc-gopay-url') || sheet.getAttribute('data-url-gopay') || '';
            apiPost(gopayUrl, data, function (_err, res, raw) {
                var parsed = res || parseGopayResponse(raw);
                if (!parsed || Number(parsed.code) !== 1 || !parsed.info) {
                    notify((parsed && parsed.msg) || getLang('string_load_err', '发起支付失败', 'Payment start failed'));
                    return;
                }
                var pd0 = parsed.info.payment_data;
                if (pd0 && pd0.type === 'qrcode' && pd0.code_url) {
                    enterLoading(pd0.code_url);
                    startPoll(orderCode);
                    return;
                }
                var opened = openPaymentChannel(parsed.info, { target: isWeixinMobile ? '_self' : '_blank' });
                if (!opened) {
                    notify(getLang('string_pay_open_fail', '无法打开支付页，请检查支付插件配置或尝试其他支付方式', 'Could not open payment page.'));
                    return;
                }
                if (!isWeixinMobile) {
                    setTimeout(function () {
                        enterLoading();
                        startPoll(orderCode);
                    }, 300);
                } else {
                    enterLoading();
                    startPoll(orderCode);
                }
            });
        });
    }

    var cancelPoll = doc.getElementById('rcCancelPoll');
    if (cancelPoll) {
        cancelPoll.addEventListener('click', function () {
            abortPaymentPoll();
            exitLoading();
            showPanel('rcPayPanel');
        });
    }

    bindPaymentPick();

    win.openRechargeModal = function (ctx) {
        _context = ctx || { mode: 'recharge' };
        if (!_context.mode) { _context.mode = 'recharge'; }
        openSheet();
        loadSheetConfig();
    };

    doc.addEventListener('click', function (e) {
        if (e.target.closest('[data-recharge-open]')) {
            e.preventDefault();
            win.openRechargeModal();
        }
    });
})(window, document);
