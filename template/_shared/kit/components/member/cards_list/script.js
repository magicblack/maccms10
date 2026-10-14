/**
 * 卡密充值：兑换表单 + 使用记录 — [data-mac-user-cards]
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var PAGE_SIZE = 20;

    function renderRow(row) {
        var used = Number(row.card_use_status) === 1;
        var extra = used
            ? '<div class="card__mo clearfix"><span class="jf-shuliang">+' +
              ML.escText(row.card_points) +
              '</span><p><span data-lang="user_mz">面值</span> ¥' +
              ML.escText(row.card_money) + '</p></div>'
            : '';
        return '<li class="order__item"><div class="order__txt"><h4 class="order__title">' +
            '<span data-lang="user_kh">卡号：</span>' + ML.escText(row.card_no || '') +
            '</h4><p><span data-lang="string_vod_time">时间：</span> ' +
            ML.fmtDateTime(row.card_use_time) + '</p></div>' + extra + '</li>';
    }

    function initRoot(root) {
        if (root.getAttribute('data-mac-cards-inited') === '1') { return; }
        root.setAttribute('data-mac-cards-inited', '1');

        var apiBase = ML.normalizeApiBase(root.getAttribute('data-api-base'));
        var listEl = root.querySelector('.order__list, .member-rows');
        var pagerEl = root.querySelector('.member-page');
        var loadingEl = root.querySelector('.mac-cards-loading');
        var form = root.querySelector('[data-mac-card-form]');
        var busy = false;
        var cooldownUntil = 0;

        function setLoading(on) {
            if (loadingEl) { loadingEl.style.display = on ? '' : 'none'; }
        }

        function loadPage(page) {
            if (busy || Date.now() < cooldownUntil) { return; }
            busy = true;
            setLoading(true);

            ML.apiJson(apiBase + '/payment/get_cards?page=' + page + '&limit=' + PAGE_SIZE, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
            })
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
                        pagerEl.innerHTML = total > 1 ? ML.buildPager(cur, total, 'mac-cards-page') : '';
                    }
                })
                .catch(function () {
                    busy = false;
                    setLoading(false);
                    cooldownUntil = Date.now() + 4000;
                    ML.notify('加载失败');
                });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var noEl = form.querySelector('[data-mac-card-no]');
                var pwdEl = form.querySelector('[data-mac-card-pwd]');
                var btn = form.querySelector('[data-mac-card-submit]');
                var cardNo = noEl ? String(noEl.value || '').replace(/^\s+|\s+$/g, '') : '';
                var cardPwd = pwdEl ? String(pwdEl.value || '').replace(/^\s+|\s+$/g, '') : '';
                if (!cardNo || !cardPwd) {
                    ML.notify('请输入卡号和卡密');
                    return;
                }
                if (btn) { btn.disabled = true; }
                ML.postForm(apiBase + '/payment/use_card', { card_no: cardNo, card_pwd: cardPwd })
                    .then(function (res) {
                        if (!res || Number(res.code) !== 1) {
                            if (ML.requireLoginOrRedirect(res && res.code)) { return; }
                            throw new Error((res && res.msg) || '充值失败');
                        }
                        ML.notify((res && res.msg) || '充值成功');
                        if (noEl) { noEl.value = ''; }
                        if (pwdEl) { pwdEl.value = ''; }
                        loadPage(1);
                    })
                    .catch(function (err) {
                        ML.notify((err && err.message) || '充值失败');
                    })
                    .finally(function () {
                        if (btn) { btn.disabled = false; }
                    });
            });
        }

        ML.bindPager(root, '.mac-cards-page', loadPage);
        loadPage(1);
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            var roots = doc.querySelectorAll('[data-mac-user-cards]');
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
