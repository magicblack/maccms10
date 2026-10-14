/**
 * twigspike 积分提现页 — #mac-user-cash-root
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var PAGE_SIZE = 20;

    function renderRow(row) {
        var paid = String(row.cash_status) === '1' || Number(row.cash_status) === 1;
        var status = paid
            ? '<span data-lang="user_yjtixian">已提现</span>'
            : '<span data-lang="user_tixianz">提现中</span>';
        return '<li class="order__item"><div class="order__txt"><h3 class="order__title">' +
            '<span data-lang="user_dh">单号： </span>' + ML.escText(row.cash_no || '') +
            '</h3><p>' + status + '：¥' + ML.escText(row.cash_money) +
            '</p><p><span data-lang="string_vod_num"> 编号：</span>' + ML.escText(row.cash_id) +
            '</p><p><span data-lang="string_jf">金币</span> ：' + ML.escText(row.cash_points) +
            '</p><p><span data-lang="string_vod_time">时间：</span> ' + ML.fmtDateTime(row.cash_time) +
            '</p></div></li>';
    }

    function initRoot(root) {
        if (root.getAttribute('data-mac-cash-inited') === '1') { return; }
        root.setAttribute('data-mac-cash-inited', '1');

        var apiBase = ML.normalizeApiBase(root.getAttribute('data-api-base'));
        var listEl = root.querySelector('[data-mac-cash-list]');
        var pagerEl = root.querySelector('[data-mac-cash-page]');
        var loadingEl = root.querySelector('.mac-cash-loading');
        var historyLoaded = false;
        var busy = false;
        var cooldownUntil = 0;

        function showStep(step) {
            root.querySelectorAll('.label-item').forEach(function (tab) {
                tab.classList.toggle('active', String(tab.getAttribute('data-step')) === String(step));
            });
            var isForm = String(step) === '1';
            root.querySelector('.label-one').classList.toggle('show-content', isForm);
            root.querySelector('.label-one').classList.toggle('noshow-content', !isForm);
            root.querySelector('.label-two').classList.toggle('show-content', !isForm);
            root.querySelector('.label-two').classList.toggle('noshow-content', isForm);
            if (!isForm && !historyLoaded) {
                historyLoaded = true;
                loadHistory(1);
            }
        }

        function setLoading(on) {
            if (loadingEl) { loadingEl.style.display = on ? '' : 'none'; }
        }

        function loadHistory(page) {
            if (busy || Date.now() < cooldownUntil) { return; }
            busy = true;
            setLoading(true);

            ML.apiJson(apiBase + '/cash/get_list?page=' + page + '&limit=' + PAGE_SIZE)
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
                        listEl.innerHTML = rows.length
                            ? rows.map(renderRow).join('')
                            : '<li class="no-data-wrap"><div class="no-data"><div class="nodata-img"></div>' +
                              '<p class="nodata-txt" data-lang="string_zwnr">暂无内容~~</p></div></li>';
                    }
                    if (pagerEl) {
                        var total = parseInt(info.pagecount, 10) || 1;
                        var cur = parseInt(info.page, 10) || page;
                        pagerEl.innerHTML = total > 1 ? ML.buildPager(cur, total, 'mac-cash-page') : '';
                    }
                })
                .catch(function () {
                    busy = false;
                    setLoading(false);
                    cooldownUntil = Date.now() + 4000;
                    ML.notify('加载失败');
                });
        }

        root.querySelectorAll('.label-item').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var step = tab.getAttribute('data-step');
                if (step) { showStep(step); }
            });
        });

        ML.bindPager(root, '.mac-cash-page', loadHistory);

        var submitBtn = root.querySelector('#btn_submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var bank = (root.querySelector('[name="cash_bank_name"]') || {}).value;
                var account = (root.querySelector('[name="cash_bank_no"]') || {}).value;
                var payee = (root.querySelector('[name="cash_payee_name"]') || {}).value;
                var money = (root.querySelector('[name="cash_money"]') || {}).value;
                if (!bank) { ML.notify('请输入银行名称'); return; }
                if (!account) { ML.notify('请输入银行账号'); return; }
                if (!payee) { ML.notify('请输入收款姓名'); return; }
                if (!money) { ML.notify('请输入提现金额'); return; }

                submitBtn.disabled = true;
                ML.postForm(apiBase + '/cash/create', {
                    cash_bank_name: bank,
                    cash_bank_no: account,
                    cash_payee_name: payee,
                    cash_money: money
                }).then(function (res) {
                    if (ML.requireLoginOrRedirect(res && res.code)) { return; }
                    ML.notify((res && res.msg) || '');
                    if (res && Number(res.code) === 1) {
                        showStep('2');
                        historyLoaded = true;
                        loadHistory(1);
                    }
                }).finally(function () {
                    submitBtn.disabled = false;
                });
            });
        }
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            var root = doc.getElementById('mac-user-cash-root');
            if (root) { initRoot(root); }
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
