/**
 * twigspike member-popedom — 权限门栏购片 / 升级
 */
(function (win, doc) {
    'use strict';

    var MT = win.MacTheme;

    function isPointsNotEnough(resp) {
        if (!resp) { return false; }
        if (Number(resp.code) === 1005) { return true; }
        var msg = String(resp.msg || '');
        return (
            (msg.indexOf('金币') > -1 || msg.indexOf('积分') > -1) &&
            msg.indexOf('不') > -1
        ) || msg.toLowerCase().indexOf('not enough') > -1;
    }

    function isPopedomBuyBtn(el) {
        if (!el) { return false; }
        if (el.closest('.js-popedom-buy-btn, .status-block__cta[data-mid]')) { return true; }
        var macBtn = el.closest('.mac_user[data-id][data-mid], .pb-player-gate__btn[data-id][data-mid]');
        return !!macBtn;
    }

    function readBuyPayload(btn) {
        return {
            id: btn.getAttribute('data-id') || '',
            mid: btn.getAttribute('data-mid') || '',
            sid: btn.getAttribute('data-sid') || '',
            nid: btn.getAttribute('data-nid') || '',
            type: btn.getAttribute('data-type') || ''
        };
    }

    doc.addEventListener('click', function (e) {
        var buyBtn = e.target.closest('.js-popedom-buy-btn, .status-block__cta[data-mid], .mac_user[data-id][data-mid], .pb-player-gate__btn[data-id][data-mid]');
        if (buyBtn && isPopedomBuyBtn(buyBtn)) {
            e.preventDefault();
            if (MT && MT.requireLogin && !MT.requireLogin()) { return; }
            buyBtn.disabled = true;
            var payload = readBuyPayload(buyBtn);
            if (MT && MT.post) {
                MT.post('user/ajax_buy_popedom', payload, function (err, r) {
                    buyBtn.disabled = false;
                    if (r && r.msg && MT.notify) { MT.notify(r.msg); }
                    if (r && Number(r.code) === 1) { win.location.reload(); return; }
                    if (isPointsNotEnough(r) && typeof win.openRechargeModal === 'function') {
                        setTimeout(function () { win.openRechargeModal(); }, 500);
                    }
                });
            }
            return;
        }
        var upBtn = e.target.closest('.js-popedom-upgrade-btn');
        if (upBtn && upBtn.getAttribute('href') === '') {
            e.preventDefault();
            var gate = upBtn.closest('.member-popedom-gate, .popedom-upgrade-gate');
            var url = gate ? gate.getAttribute('data-upgrade-url') : '';
            if (url) { win.location.href = url; }
        }
    });
})(window, document);
