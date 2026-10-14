/**
 * twigspike 充值记录列表 — [data-mac-user-orders]（components/member/order_list.twig）
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var PAGE_SIZE = 20;

    function payHref(base, orderCode) {
        if (!base) { return '#'; }
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + 'order_code=' + encodeURIComponent(orderCode || '');
    }

    function renderRow(row, payBase) {
        var code = ML.escText(row.order_code || '');
        var paid = Number(row.order_status) === 1;
        var price = ML.escText(row.order_price);
        var points = ML.escText(row.order_points);
        var time = ML.fmtDateTime(row.order_time);
        var paidLine = '<span data-lang="user_yzf">已支付：</span> ¥' + price +
            '<span class="split-line"></span> <span data-lang="string_jf">金币</span> ' +
            '<span class="jg-tz">+' + points + '</span>';
        var pendingLine = '<span data-lang="user_dzf">待支付：</span> <span class="jg-tz">¥' + price + '</span>';
        var action = '';
        if (!paid) {
            action = '<div class="order__del clearfix"><a class="btn-order btn-del" data-lang="user_zf" href="' +
                ML.escAttr(payHref(payBase, row.order_code)) + '">支付</a></div>';
        }
        return '<li class="order__item"><div class="order__txt"><h4 class="order__title">' +
            '<span data-lang="user_dh">单号：</span>' + code + '</h4><p>' +
            (paid ? paidLine : pendingLine) +
            '</p><p><span data-lang="string_vod_time">时间：</span> ' + time + '</p></div>' +
            action + '</li>';
    }

    function emptyRow() {
        return '<li class="mac-plog-no-data-li no-data-wrap"><div class="no-data">' +
            '<div class="nodata-img"></div><p class="nodata-txt" data-lang="string_zwnr">暂无内容~~</p></div></li>';
    }

    function initRoot(root) {
        if (root.getAttribute('data-mac-orders-inited') === '1') { return; }
        root.setAttribute('data-mac-orders-inited', '1');

        var apiBase = ML.normalizeApiBase(root.getAttribute('data-api-base'));
        var payBase = ML.memberUrl('pay');
        var listEl = root.querySelector('.mac-orders-list, .order__list');
        var loadingEl = root.querySelector('.mac-ulog-loading');
        var pagerEl = root.querySelector('.member-page');
        var busy = false;
        var cooldownUntil = 0;

        function setLoading(on) {
            if (loadingEl) { loadingEl.hidden = !on; }
        }

        function loadPage(page) {
            if (busy || Date.now() < cooldownUntil) { return; }
            busy = true;
            setLoading(true);

            ML.apiJson(apiBase + '/order/get_list?page=' + page + '&limit=' + PAGE_SIZE)
                .then(function (res) {
                    busy = false;
                    setLoading(false);

                    if (!res || Number(res.code) !== 1) {
                        if (ML.requireLoginOrRedirect(res && res.code)) { return; }
                        cooldownUntil = Date.now() + 4000;
                        ML.notify((res && res.msg) || '加载失败');
                        return;
                    }

                    var info = res.info || {};
                    var rows = info.list || [];
                    if (listEl) {
                        listEl.innerHTML = '';
                        if (rows.length) {
                            rows.forEach(function (row) {
                                listEl.insertAdjacentHTML('beforeend', renderRow(row, payBase));
                            });
                        } else {
                            listEl.insertAdjacentHTML('beforeend', emptyRow());
                        }
                    }

                    var cur = parseInt(info.page, 10) || page;
                    var total = parseInt(info.pagecount, 10) || 1;
                    if (pagerEl) {
                        pagerEl.innerHTML = total > 1 ? ML.buildPager(cur, total, 'mac-orders-page') : '';
                    }
                })
                .catch(function () {
                    busy = false;
                    setLoading(false);
                    cooldownUntil = Date.now() + 4000;
                    ML.notify('加载失败');
                });
        }

        ML.bindPager(root, '.mac-orders-page', loadPage);
        loadPage(1);
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            var roots = doc.querySelectorAll('[data-mac-user-orders]');
            for (var i = 0; i < roots.length; i++) {
                initRoot(roots[i]);
            }
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        } else {
            win.__macMemberWait = win.__macMemberWait || [];
            win.__macMemberWait.push(start);
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
