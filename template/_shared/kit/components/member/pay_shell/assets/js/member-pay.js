/**
 * twigspike member-pay — MacTheme 收银台（充值 / 确认支付）
 */
(function (win, doc) {
    'use strict';

    var MT = win.MacTheme;

    function $(sel, root) {
        return (root || doc).querySelector(sel);
    }

    function $$(sel, root) {
        return Array.prototype.slice.call((root || doc).querySelectorAll(sel));
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
        if (MT && MT.notify) { MT.notify(m); return; }
    }

    function confirmAction(msg, fn) {
        var opts = typeof msg === 'string' ? { message: msg, title: '请确认' } : msg;
        if (win.MemberDialog && win.MemberDialog.confirm) {
            win.MemberDialog.confirm(opts, fn);
            return;
        }
        if (MT && MT.confirm) { MT.confirm(opts, fn); return; }
        if (typeof MAC !== 'undefined' && MAC.confirm) { MAC.confirm(msg, fn); }
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
    }

    function apiPost(url, data, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var token = '';
        var m = doc.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
        if (m) { token = decodeURIComponent(m[1]); }
        if (token) { xhr.setRequestHeader('X-CSRF-Token', token); }
        var body = [];
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
            }
        }
        if (token) { body.push('__token__=' + encodeURIComponent(token)); }
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            cb(null, res, xhr.responseText);
        };
        xhr.send(body.join('&'));
    }

    function payIcon(key, tplId) {
        var tpl = doc.getElementById(tplId || 'buyPayIconsTpl');
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

    function renderPaytypes(container, paytypes) {
        if (!container) { return; }
        var html = '';
        if (paytypes && paytypes.length) {
            for (var i = 0; i < paytypes.length; i++) {
                var pt = paytypes[i];
                if (!pt) { continue; }
                var val = pt.value != null ? String(pt.value) : '';
                var lbl = pt.label != null ? String(pt.label) : val;
                html += '<div class="pt-card" data-type="' + esc(val) + '" role="button" tabindex="0">' + esc(lbl) + '</div>';
            }
        }
        container.innerHTML = html;
    }

    function renderPaymentGrid(grid, extList, iconsTplId) {
        if (!grid) { return; }
        if (!extList || !Object.keys(extList).length) {
            grid.innerHTML = '<div class="pm-empty">暂无可用支付方式</div>';
            return;
        }
        var html = '';
        for (var k in extList) {
            if (!Object.prototype.hasOwnProperty.call(extList, k)) { continue; }
            html += '<div class="pm-card" data-payment="' + esc(k) + '" role="button" tabindex="0">' +
                '<div class="pm-icon">' + payIcon(k, iconsTplId) + '</div>' +
                '<div class="pm-name">' + esc(extList[k]) + '</div></div>';
        }
        grid.innerHTML = html;
    }

    function bindPaymentPick(root, cfgKey) {
        root.addEventListener('click', function (e) {
            var card = e.target.closest('.pm-card');
            if (!card || !root.contains(card)) { return; }
            $$('.pm-card', root).forEach(function (c) { c.classList.remove('active'); });
            card.classList.add('active');
            var pay = card.getAttribute('data-payment') || '';
            var payInput = $('#payment_input', root) || $('#rcPayment', root);
            if (payInput) { payInput.value = pay; }
            var cfg = root._memberPayCfg || {};
            var method = findMethod(cfg.methods, pay);
            var ptBox = $('#paytype_box', root) || $('#rcPaytypeBox', root);
            var ptGrid = $('#paytype_grid', root) || $('#rcPaytypeGrid', root);
            if (method && method.paytypes && method.paytypes.length) {
                renderPaytypes(ptGrid, method.paytypes);
                if (ptBox) { ptBox.hidden = false; }
            } else if (ptBox) {
                ptBox.hidden = true;
                if (ptGrid) { ptGrid.innerHTML = ''; }
            }
        });
        root.addEventListener('click', function (e) {
            var pt = e.target.closest('.pt-card');
            if (!pt || !root.contains(pt)) { return; }
            $$('.pt-card', root).forEach(function (c) { c.classList.remove('active'); });
            pt.classList.add('active');
            var val = pt.getAttribute('data-type') || '';
            var ptInput = $('#paytype_input', root) || $('#rcPaytype', root);
            if (ptInput) { ptInput.value = val; }
        });
    }

    function openOverlay(overlay) {
        if (!overlay) { return; }
        overlay.hidden = false;
        overlay.classList.add('show');
        doc.body.style.overflow = 'hidden';
    }

    function closeOverlay(overlay) {
        if (!overlay) { return; }
        overlay.classList.remove('show');
        overlay.hidden = true;
        doc.body.style.overflow = '';
    }

    function initTabs(root) {
        var tabs = $$('[data-mac-buy-upgrade-tab]', root);
        var panels = {
            recharge: $('.member-pay-panel--recharge', root) || $('.user-buy-upgrade-panel--recharge', root),
            upgrade: $('.member-pay-panel--upgrade', root) || $('.user-buy-upgrade-panel--upgrade', root)
        };
        function activate(name) {
            tabs.forEach(function (t) {
                t.classList.toggle('is-active', t.getAttribute('data-mac-buy-upgrade-tab') === name);
            });
            if (panels.recharge) { panels.recharge.hidden = name !== 'recharge'; }
            if (panels.upgrade) { panels.upgrade.hidden = name !== 'upgrade'; }
        }
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-mac-buy-upgrade-tab') || 'recharge');
            });
        });
        var m = String(win.location.search || '').match(/[?&]tab=([^&]+)/);
        var path = String(win.location.pathname || '') + String(win.location.search || '');
        var initial = (root.getAttribute('data-default-tab') || 'recharge');
        if (initial !== 'recharge' && initial !== 'upgrade') {
            initial = 'recharge';
        }
        if (m && (m[1] === 'recharge' || m[1] === 'upgrade')) {
            initial = m[1];
        } else if (/user\/upgrade|\/upgrade(?:\.html|\/|\?|$)/i.test(path)) {
            initial = 'upgrade';
        } else if (/user\/buy|\/buy(?:\.html|\/|\?|$)/i.test(path)) {
            initial = 'recharge';
        }
        activate(initial);
    }

    function initBuyRoot(root) {
        if (root._memberPayInited) { return; }
        root._memberPayInited = true;

        var cfgUrl = root.getAttribute('data-url-recharge-config') || '';
        var buyUrl = root.getAttribute('data-url-buy') || '';
        var gopayUrl = root.getAttribute('data-url-gopay') || '';
        var queryUrl = root.getAttribute('data-url-order-query') || '';
        var loginUrl = root.getAttribute('data-url-login') || '';

        initTabs(root);
        bindPaymentPick(root);

        var overlay = $('#pay-overlay', root);
        var pollTimer = null;

        function loadConfig() {
            var grid = $('#payment_grid', root);
            if (grid) { grid.innerHTML = '<div class="pm-loading">加载中…</div>'; }
            apiGet(cfgUrl, function (err, res) {
                var info = res && (res.info || res.data);
                if (!res || Number(res.code) !== 1 || !info) {
                    if (grid) { grid.innerHTML = '<div class="pm-error">加载失败</div>'; }
                    return;
                }
                var cfg = {
                    is_login: info.is_login != null ? info.is_login : 0,
                    user_points: info.user_points != null ? info.user_points : 0,
                    pay_min: info.min != null ? info.min : (info.pay_min != null ? info.pay_min : 1),
                    pay_scale: info.scale != null ? info.scale : (info.pay_scale != null ? info.pay_scale : 1),
                    ext_list: methodsMap(info.methods),
                    methods: info.methods || []
                };
                root._memberPayCfg = cfg;
                var pts = $('#buy_user_points', root);
                if (pts) { pts.textContent = cfg.user_points; }
                var input = $('#price_input', root);
                if (input) { input.value = cfg.pay_min; }
                $$('.member-pay-amount, .amount-card', root).forEach(function (el) {
                    el.classList.toggle('is-active', el.getAttribute('data-amount') === String(cfg.pay_min));
                });
                var tips = $('#buy_recharge_tips', root);
                if (tips) {
                    tips.textContent = '最小充值金额为' + cfg.pay_min + '元，1元可以兑换' + cfg.pay_scale + '金币';
                }
                renderPaymentGrid(grid, cfg.ext_list, 'buyPayIconsTpl');
            });
        }

        root.addEventListener('click', function (e) {
            var amt = e.target.closest('.member-pay-amount, .amount-card');
            if (!amt || !root.contains(amt)) { return; }
            $$('.member-pay-amount, .amount-card', root).forEach(function (a) { a.classList.remove('is-active', 'active'); });
            amt.classList.add('is-active');
            var input = $('#price_input', root);
            if (input) { input.value = amt.getAttribute('data-amount') || ''; }
        });

        var submitBtn = $('#btn_submit_pay', root);
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var price = String(($('#price_input', root) || {}).value || '').trim();
                if (Number(price) < 1) { notify('请输入充值金额'); return; }
                var cfg = root._memberPayCfg || {};
                if (cfg.pay_min && Number(price) < Number(cfg.pay_min)) {
                    notify('最小充值金额为' + cfg.pay_min + '元');
                    return;
                }
                if (Number(cfg.is_login) !== 1) {
                    notify('请先登录');
                    if (loginUrl) {
                        win.location.href = loginUrl + (loginUrl.indexOf('?') >= 0 ? '&' : '?') +
                            'url=' + encodeURIComponent(win.location.href);
                    }
                    return;
                }
                confirmAction('确认充值？', function () {
                    submitBtn.disabled = true;
                    apiPost(buyUrl, { price: price }, function (err, res) {
                        submitBtn.disabled = false;
                        var info = res && (res.info || res.data);
                        if (!res || Number(res.code) !== 1 || !info) {
                            notify((res && res.msg) || '创建订单失败');
                            return;
                        }
                        $('#modal_order_code', root).textContent = info.order_code || '-';
                        $('#modal_order_price', root).textContent = info.order_price != null ? info.order_price : '0';
                        $('#modal_order_id', root).value = info.order_id != null ? String(info.order_id) : '';
                        $('#modal_order_code_input', root).value = info.order_code || '';
                        renderPaymentGrid($('#payment_grid', root), (root._memberPayCfg || {}).ext_list, 'buyPayIconsTpl');
                        openOverlay(overlay);
                    });
                });
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) { closeOverlay(overlay); }
            });
            var closeBtn = $('.pay-modal-close', overlay);
            if (closeBtn) { closeBtn.addEventListener('click', function () { closeOverlay(overlay); }); }
        }

        var payForm = $('#payForm', root);
        if (payForm) {
            payForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var payment = String($('#payment_input', root).value || '').trim();
                if (!payment) { notify('请选择支付方式'); return; }
                var orderId = String($('#modal_order_id', root).value || '').trim();
                var orderCode = String($('#modal_order_code_input', root).value || '').trim();
                if (!orderId || !orderCode) { notify('订单信息不完整'); return; }
                var data = { order_id: orderId, order_code: orderCode, payment: payment };
                var paytype = String($('#paytype_input', root).value || '').trim();
                if (paytype) { data.paytype = paytype; }
                apiPost(gopayUrl, data, function (err, res, raw) {
                    if (!res || Number(res.code) !== 1) {
                        notify((res && res.msg) || '发起支付失败');
                        return;
                    }
                    var pd = res.info && res.info.payment_data;
                    if (pd && pd.type === 'qrcode' && pd.code_url && win.macRenderPaymentQrcode) {
                        var host = $('#pay_loading_qrcode_host', root);
                        var panel = $('#pay_loading_panel', root);
                        if (host && panel) {
                            host.innerHTML = '';
                            win.macRenderPaymentQrcode(host, pd.code_url, function () {});
                            panel.hidden = false;
                            payForm.hidden = true;
                        }
                    } else if (pd && pd.html) {
                        win.open('', '_blank');
                    }
                    if (pollTimer) { clearInterval(pollTimer); }
                    pollTimer = setInterval(function () {
                        apiGet(queryUrl + '?order_code=' + encodeURIComponent(orderCode), function (e2, r2) {
                            var d = r2 && (r2.info || r2.data);
                            if (r2 && Number(r2.code) === 1 && d && Number(d.order_status) === 1) {
                                clearInterval(pollTimer);
                                notify('充值成功');
                                closeOverlay(overlay);
                                win.location.reload();
                            }
                        });
                    }, 5000);
                });
            });
        }

        loadConfig();
    }

    function parseGopayResponse(text) {
        var s = String(text == null ? '' : text).replace(/^\uFEFF/, '').trim();
        if (!s) { return { code: 0, msg: 'empty response' }; }
        if (s.charAt(0) === '{' || s.charAt(0) === '[') {
            try { return JSON.parse(s); } catch (e) { return { code: 0, msg: 'invalid json' }; }
        }
        if (/<\s*(script|form|html|head|body|iframe|meta)\b/i.test(s)) {
            return { code: 1, msg: 'pay_html', info: { payment_data: { type: 'html', html: s } } };
        }
        return { code: 0, msg: s.substring(0, 120) };
    }

    function extractRedirectUrlFromHtml(html) {
        if (!html || typeof html !== 'string') { return ''; }
        var m = html.match(/(?:(?:window|top|parent|self)\.)?location\.href\s*=\s*["']([^"']+)["']/i);
        return m ? m[1] : '';
    }

    function openPaymentHtmlDocument(html) {
        var w = win.open('', '_blank');
        if (!w) { return; }
        try {
            w.document.open();
            w.document.write(html);
            w.document.close();
        } catch (e) {}
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
            return !!(pd.code_url);
        }
        if ((type === 'form' || type === 'html') && pd.html) {
            if (target === '_self') {
                var ju = extractRedirectUrlFromHtml(pd.html);
                if (ju) {
                    win.location.href = ju;
                    return true;
                }
                try {
                    doc.open();
                    doc.write(pd.html);
                    doc.close();
                } catch (e) {
                    return false;
                }
                return true;
            }
            openPaymentHtmlDocument(pd.html);
            return true;
        }
        if (type === 'data' && pd.data && typeof pd.data === 'object') {
            var payUrl = pd.data.pay_url || pd.data.url;
            return payUrl ? go(payUrl) : false;
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

    function showPayLoading(root, codeUrl) {
        var panel = $('#pay_loading_panel', root);
        var form = $('#payForm', root);
        var qrBlock = $('#pay_loading_qrcode_block', root);
        var qrHost = $('#pay_loading_qrcode_host', root);
        var spinner = $('#pay_loading_spinner_wrap', root);
        if (!panel) { return; }
        if (codeUrl && qrHost && win.macRenderPaymentQrcode) {
            if (qrBlock) { qrBlock.hidden = false; qrBlock.setAttribute('aria-hidden', 'false'); }
            if (spinner) { spinner.hidden = true; }
            qrHost.innerHTML = '';
            win.macRenderPaymentQrcode(qrHost, codeUrl, function () {});
        } else {
            if (qrBlock) { qrBlock.hidden = true; }
            if (spinner) { spinner.hidden = false; }
            if (qrHost) { qrHost.innerHTML = ''; }
        }
        panel.hidden = false;
        if (form) { form.hidden = true; }
    }

    function hidePayLoading(root) {
        var panel = $('#pay_loading_panel', root);
        var form = $('#payForm', root);
        if (panel) { panel.hidden = true; }
        if (form) { form.hidden = false; }
    }

    function initPayConfirmRoot(root) {
        if (root._memberPayInited) { return; }
        root._memberPayInited = true;

        var cfgUrl = root.getAttribute('data-url-recharge-config') || '';
        var gopayUrl = root.getAttribute('data-url-gopay') || '';
        var queryUrl = root.getAttribute('data-url-order-query') || '';
        var orderCode = root.getAttribute('data-order-code') || '';
        var pollTimer = null;

        bindPaymentPick(root);

        apiGet(cfgUrl, function (err, res) {
            var info = res && (res.info || res.data);
            if (!res || Number(res.code) !== 1 || !info) {
                var grid = $('#payment_grid', root);
                if (grid) { grid.innerHTML = '<div class="pm-error">加载失败</div>'; }
                return;
            }
            root._memberPayCfg = { ext_list: methodsMap(info.methods), methods: info.methods || [] };
            renderPaymentGrid($('#payment_grid', root), root._memberPayCfg.ext_list, 'payPayIconsTpl');
        });

        var cancelBtn = $('.pay-loading-cancel', root);
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                hidePayLoading(root);
            });
        }

        var form = $('#payForm', root);
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var payment = String($('#payment_input', root).value || '').trim();
                if (!payment) { notify('请选择支付方式'); return; }
                var actPm = root.querySelector('.pm-card.active');
                var pmFromUi = actPm ? String(actPm.getAttribute('data-payment') || '').trim() : '';
                if (!actPm || pmFromUi.toLowerCase() !== payment.toLowerCase()) {
                    notify('请选择支付方式');
                    return;
                }
                var selM = findMethod((root._memberPayCfg || {}).methods, payment);
                if (!selM || Number(selM.enabled) !== 1) {
                    notify('请选择支付方式');
                    return;
                }
                if (selM.paytypes && selM.paytypes.length) {
                    var ptVal = String($('#paytype_input', root).value || '').trim();
                    var ptActive = root.querySelector('#paytype_grid .pt-card.active');
                    var ptFromUi = ptActive ? String(ptActive.getAttribute('data-type') || '').trim() : '';
                    if (!ptVal || !ptActive || ptFromUi !== ptVal) {
                        notify('请选择支付类型');
                        return;
                    }
                }
                var orderId = String(($('input[name="order_id"]', form) || {}).value || '').trim();
                var orderCodeVal = String(($('input[name="order_code"]', form) || {}).value || '').trim() || orderCode;
                if (!orderId || !orderCodeVal) { notify('订单信息不完整'); return; }

                var isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
                var isWeixinMobile = isMobile && payment.toLowerCase() === 'weixin';
                var data = { order_id: orderId, order_code: orderCodeVal, payment: payment };
                var paytype = String($('#paytype_input', root).value || '').trim();
                if (paytype) { data.paytype = paytype; }

                apiPost(gopayUrl, data, function (err, res, raw) {
                    var parsed = res || parseGopayResponse(raw);
                    if (!parsed || Number(parsed.code) !== 1 || !parsed.info) {
                        notify((parsed && parsed.msg) || '发起支付失败');
                        return;
                    }
                    var pd = parsed.info.payment_data;
                    if (pd && pd.type === 'qrcode' && pd.code_url) {
                        showPayLoading(root, pd.code_url);
                    } else {
                        var opened = openPaymentChannel(parsed.info, { target: isWeixinMobile ? '_self' : '_blank' });
                        if (!opened) {
                            notify('无法打开支付页，请检查支付配置或更换支付方式');
                            return;
                        }
                        if (!isWeixinMobile) {
                            setTimeout(function () { showPayLoading(root); }, 300);
                        } else {
                            showPayLoading(root);
                        }
                    }
                    if (pollTimer) { clearInterval(pollTimer); }
                    pollTimer = setInterval(function () {
                        apiGet(queryUrl + '?order_code=' + encodeURIComponent(orderCodeVal), function (e2, r2) {
                            var d = r2 && (r2.info || r2.data);
                            if (r2 && Number(r2.code) === 1 && d && Number(d.order_status) === 1) {
                                clearInterval(pollTimer);
                                pollTimer = null;
                                if (win.MemberDialog && win.MemberDialog.status) {
                                    win.MemberDialog.status({ title: '支付成功', message: '订单已支付完成', tone: 'success' }, function () {
                                        win.location.href = root.getAttribute('data-url-user-upgrade') || win.location.pathname;
                                    });
                                } else {
                                    notify('支付成功');
                                    win.location.reload();
                                }
                            }
                        });
                    }, 5000);
                });
            });
        }
    }

    function boot() {
        var buyRoot = doc.getElementById('mac-user-buy-root');
        if (buyRoot) { initBuyRoot(buyRoot); }
        var payRoot = doc.getElementById('mac-user-pay-root');
        if (payRoot && payRoot !== buyRoot) { initPayConfirmRoot(payRoot); }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    win.MacMemberPay = { initBuyRoot: initBuyRoot, initPayConfirmRoot: initPayConfirmRoot };
})(window, document);
