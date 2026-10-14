/**
 * VIP 升级：套餐选择 + 提交（纯原生，不依赖 jQuery）
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

  function getLang(key, zh, en) {
    if (typeof MAC !== 'undefined' && MAC.GetLang) {
      var s = MAC.GetLang(key);
      if (s) return s;
    }
    if (MT && MT.t) {
      var t = MT.t(key);
      if (t && t !== key) return t;
    }
    return String(localStorage.getItem('lang')) === '1' ? en : zh;
  }

  function submitLabelText() {
    return getLang('string_user_confirm_pay', '确认支付', 'Confirm payment');
  }

  function planLabelFromLong(longVal) {
    var map = { day: '日卡', week: '周卡', month: '月卡', year: '年卡' };
    return map[longVal] || 'VIP';
  }

  function getSelectedGrade(wrap) {
    var root = wrap || doc.getElementById('mac-user-buy-root');
    if (!root) return null;
    return root.querySelector('.select-vip .grade, .select-vip > .grade');
  }

  function getUserPoints(root) {
    if (
      win.macBuyUpgradeState &&
      typeof win.macBuyUpgradeState.userPoints === 'number' &&
      !isNaN(win.macBuyUpgradeState.userPoints)
    ) {
      return win.macBuyUpgradeState.userPoints;
    }
    var scope = root || doc.getElementById('mac-user-buy-root');
    var ptEl = scope ? scope.querySelector('#buy_user_points') : doc.getElementById('buy_user_points');
    if (ptEl) {
      var n = parseInt(String(ptEl.textContent || '').replace(/[^\d]/g, ''), 10);
      if (!isNaN(n)) return n;
    }
    return 0;
  }

  function shouldUseCashPay(_points, _userPoints, hasCoupon, _discount) {
    return !!hasCoupon;
  }

  function syncCheckoutBar(wrap) {
    var root = wrap || doc.getElementById('mac-user-buy-root');
    if (!root) return;
    var panel = root.querySelector('.member-pay-panel--upgrade, .user-buy-upgrade-panel--upgrade');
    if (!panel) return;

    var grade = getSelectedGrade(root);
    var descEl = panel.querySelector('.js-checkout-desc');
    var payEl = panel.querySelector('.js-checkout-pay');
    var coinEl = panel.querySelector('.js-checkout-coin');
    var pricingEl = panel.querySelector('.js-checkout-pricing');
    var hintEl = panel.querySelector('.js-checkout-hint');
    var settle = panel.querySelector('.js-upgrade-settle');

    if (!grade) {
      if (descEl) descEl.textContent = getLang('string_user_upgrade_select_first', '请选择套餐', 'Select a plan');
      if (payEl) payEl.textContent = '0';
      if (pricingEl) pricingEl.setAttribute('hidden', 'hidden');
      if (hintEl) hintEl.setAttribute('hidden', 'hidden');
      if (settle) settle.setAttribute('hidden', 'hidden');
      return;
    }

    if (settle) settle.removeAttribute('hidden');

    var points = parseInt(grade.getAttribute('data-points'), 10) || 0;
    var price = grade.getAttribute('data-price');
    var longVal = grade.getAttribute('data-long') || '';
    var planLabel = planLabelFromLong(longVal);
    var cash = price != null && String(price) !== '' ? Number(price) : points;
    var preview = null;
    var hasCoupon = false;

    if (win.MacUserCoupon && typeof MacUserCoupon.getByMount === 'function') {
      var vipPanel = MacUserCoupon.getByMount('buy-upgrade');
      if (vipPanel && typeof vipPanel.getPreview === 'function') {
        preview = vipPanel.getPreview();
      }
      if (vipPanel && typeof vipPanel.getSelectedCouponUserId === 'function') {
        hasCoupon = vipPanel.getSelectedCouponUserId() > 0;
      }
    }

    var payCash = preview && preview.pay >= 0 ? preview.pay : cash;
    var discount = preview ? preview.discount || 0 : 0;
    var original = preview ? preview.original || cash : cash;
    var userPoints = getUserPoints(root);
    var useCashPay = shouldUseCashPay(points, userPoints, hasCoupon, discount);
    var checkoutEl = panel.querySelector('.member-pay-checkout');

    if (checkoutEl) {
      if (useCashPay) {
        checkoutEl.classList.add('member-pay-checkout--cash');
      } else {
        checkoutEl.classList.remove('member-pay-checkout--cash');
      }
    }

    if (descEl) {
      if (useCashPay) {
        descEl.textContent =
          getLang('string_user_cash_pay_open', '现金支付', 'Cash pay') +
          ' ¥' +
          payCash.toFixed(2) +
          ' ' +
          getLang('string_user_open_plan', '即可开通', 'to unlock') +
          ' ' +
          planLabel +
          ' VIP';
      } else {
        descEl.textContent =
          getLang('string_user_pay_coin_open', '支付', 'Pay') +
          ' ' +
          points +
          ' ' +
          getLang('string_jf', '金币', 'coins') +
          ' ' +
          getLang('string_user_open_plan', '即可开通', 'to unlock') +
          ' ' +
          planLabel +
          ' VIP';
      }
    }

    if (pricingEl) {
      var pricingText;
      if (hasCoupon && discount > 0) {
        pricingText =
          getLang('string_coupon_preview_short', '原价', 'Was') +
          ' ¥' +
          original.toFixed(2) +
          ' − ' +
          getLang('string_coupon_discount_short', '券', 'Coupon') +
          ' ¥' +
          discount.toFixed(2) +
          ' = ' +
          getLang('string_coupon_pay_short', '实付', 'Pay') +
          ' ¥' +
          payCash.toFixed(2);
      } else {
        pricingText =
          getLang('string_user_no_coupon', '未选券', 'No coupon') +
          ' · ' +
          getLang('string_coupon_pay_short', '实付', 'Pay') +
          ' ¥' +
          payCash.toFixed(2);
      }
      pricingEl.textContent = pricingText;
      pricingEl.removeAttribute('hidden');
    }

    if (hintEl) {
      if (hasCoupon && discount > 0) {
        hintEl.textContent = getLang(
          'string_coupon_cash_only',
          '金币仍按充值原价到账，券只减现金；实付以支付页为准',
          'Coins credit uses original amount; final price is set by server'
        );
        hintEl.removeAttribute('hidden');
      } else {
        hintEl.setAttribute('hidden', 'hidden');
      }
    }

    if (payEl) {
      if (useCashPay) {
        payEl.textContent = '¥' + payCash.toFixed(2);
        if (coinEl) coinEl.setAttribute('hidden', 'hidden');
      } else {
        payEl.textContent = String(points);
        if (coinEl) coinEl.removeAttribute('hidden');
      }
    }
  }

  win.MacUpgradeCheckout = win.MacUpgradeCheckout || {};
  win.MacUpgradeCheckout.sync = syncCheckoutBar;

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
    if (MT && MT.notify) {
      MT.notify(m);
      return;
    }
  }

  function confirmAction(msg, fn) {
    var opts = typeof msg === 'string' ? { message: msg, title: '请确认', confirmText: submitLabelText() } : msg;
    if (win.MemberDialog && win.MemberDialog.confirm) {
      win.MemberDialog.confirm(opts, fn);
      return;
    }
    if (MT && MT.confirm) {
      MT.confirm(opts, fn);
      return;
    }
    if (typeof MAC !== 'undefined' && MAC.confirm) {
      MAC.confirm(msg, fn);
    }
  }

  function isPointsNotEnough(resp) {
    if (!resp) return false;
    if (Number(resp.code) === 1005) return true;
    var msg = String(resp.msg || '');
    return (
      msg.indexOf('金币') > -1 ||
      msg.indexOf('积分') > -1 ||
      msg.toLowerCase().indexOf('not enough') > -1
    );
  }

  function bumpRechargeOverlayZ() {
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
        if (!isNaN(n)) maxZ = Math.max(maxZ, n);
      }
      var ov = doc.getElementById('recharge-overlay');
      if (ov) {
        ov.style.setProperty('z-index', String(maxZ + 10), 'important');
      }
    } catch (e) {}
  }

  function onUpgradeSuccess(inPopContent) {
    function closeAndReload() {
      if (inPopContent) {
        try {
          $$('.mac_pop_bg, .mac_pop').forEach(function (n) {
            if (n && n.parentNode) n.parentNode.removeChild(n);
          });
        } catch (e) {}
      }
      location.reload();
    }
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.VipUpgradeSheet === 'function') {
      MAC.Pop.VipUpgradeSheet({ onClose: closeAndReload });
      return;
    }
    notify(getLang('string_mac_status_ok_generic', '操作成功', 'Success'));
    closeAndReload();
  }

  function apiPost(url, data, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    var token = '';
    var m = doc.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
    if (m) {
      token = decodeURIComponent(m[1]);
    }
    if (token) {
      xhr.setRequestHeader('X-CSRF-Token', token);
    }
    var body = [];
    Object.keys(data || {}).forEach(function (k) {
      body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
    });
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      var res = null;
      try {
        res = JSON.parse(xhr.responseText);
      } catch (e) {
        res = null;
      }
      cb(null, res);
    };
    xhr.send(body.join('&'));
  }

  function runUpgradeAjax(wrap, upgradeUrl, inPopContent) {
    var that = wrap.querySelector('.select-vip > .grade, .select-vip .grade');
    var group_id = that ? that.getAttribute('data-id') : '';
    if (!that || group_id === undefined || group_id === '') {
      notify(
        getLang(
          'string_user_upgrade_select_first',
          '请先选择会员组与开通时长',
          'Please select a membership plan and duration first.'
        )
      );
      return;
    }
    var group_name = that.getAttribute('data-name');
    var long = that.getAttribute('data-long');
    var points = that.getAttribute('data-points');
    var price = that.getAttribute('data-price');
    var couponUserId = 0;
    if (win.MacUserCoupon && typeof MacUserCoupon.getByMount === 'function') {
      var vipPanel = MacUserCoupon.getByMount('buy-upgrade');
      couponUserId = (vipPanel && vipPanel.getSelectedCouponUserId()) || 0;
    }

    if (couponUserId > 0 && typeof win.openRechargeModal === 'function') {
      win.openRechargeModal({
        mode: 'member_upgrade',
        group_id: group_id,
        group_name: group_name,
        long: long,
        points: points,
        price: price,
        coupon_user_id: couponUserId
      });
      if (inPopContent) bumpRechargeOverlayZ();
      return;
    }

    var isEn = localStorage.getItem('lang') === '1';
    var confirmMsg = isEn
      ? 'Are you sure you want to upgrade to [' + group_name + '] ? Need to spend [' + points + '] integral'
      : '确定要升级到【' + group_name + '】吗,需要花费【' + points + '】金币';
    var labelBefore = submitLabelText();
    var btn = wrap.querySelector('.submit-vip');
    confirmAction(confirmMsg, function () {
      if (btn) {
        btn.style.opacity = '0.85';
        btn.style.pointerEvents = 'none';
        btn.textContent = isEn ? 'Loading...' : '提交中...';
      }
      apiPost(upgradeUrl, { group_id: group_id, long: long }, function (_err, r) {
        if (btn) {
          btn.style.opacity = '';
          btn.style.pointerEvents = '';
          btn.textContent = labelBefore;
          if (inPopContent) {
            btn.removeAttribute('style');
            btn.textContent = labelBefore;
          }
        }
        if (!r) {
          notify(getLang('string_load_err', '操作失败', 'Failed'));
          return;
        }
        if (Number(r.code) === 1) {
          onUpgradeSuccess(inPopContent);
          return;
        }
        if (isPointsNotEnough(r) && typeof win.openRechargeModal === 'function') {
          win.openRechargeModal({
            mode: 'member_upgrade',
            group_id: group_id,
            group_name: group_name,
            long: long,
            points: points,
            coupon_user_id: couponUserId || undefined
          });
          if (inPopContent) bumpRechargeOverlayZ();
          return;
        }
        notify(r.msg || getLang('string_load_err', '操作失败', 'Failed'));
      });
    });
  }

  function isMergePageLoggedIn(root) {
    if (
      typeof win.macBuyUpgradeState === 'object' &&
      win.macBuyUpgradeState !== null &&
      typeof win.macBuyUpgradeState.isLogin === 'boolean'
    ) {
      return win.macBuyUpgradeState.isLogin;
    }
    return root.getAttribute('data-is-login') === '1';
  }

  function goLoginOrModal(loginUrl) {
    var u = String(loginUrl || '').trim();
    if (!u) return;
    var back = win.location.href || '/';
    if (typeof MAC !== 'undefined' && MAC.User && typeof MAC.User.openLoginOrNavigate === 'function') {
      MAC.User.openLoginOrNavigate(u, back);
    } else {
      win.location.href = u + (u.indexOf('?') >= 0 ? '&' : '?') + 'url=' + encodeURIComponent(back);
    }
  }

  function isUpgradePlanItem(el) {
    return !!(el && el.closest && el.closest('.member-pay-panel--upgrade, .user-buy-upgrade-panel--upgrade, #right, .ajax-upgrade-pop'));
  }

  function selectPlanItem(root, item) {
    $$( '.shengji-item', root).forEach(function (n) {
      n.classList.remove('select-vip');
    });
    item.classList.add('select-vip');
    syncCheckoutBar(root);
    if (win.MacUserCoupon && typeof MacUserCoupon.getByMount === 'function') {
      var vipPanel = MacUserCoupon.getByMount('buy-upgrade');
      if (vipPanel && typeof vipPanel.scheduleRefresh === 'function') {
        setTimeout(function () {
          vipPanel.scheduleRefresh();
        }, 0);
      }
    }
  }

  function initBuyRoot() {
    var buyRoot = doc.getElementById('mac-user-buy-root');
    if (!buyRoot) return;
    var mergeUpgradeUrl = buyRoot.getAttribute('data-url-user-upgrade');
    var mergeLoginUrl = buyRoot.getAttribute('data-url-login');
    if (!mergeUpgradeUrl) return;

    buyRoot.addEventListener('click', function (e) {
      var item = e.target && e.target.closest ? e.target.closest('.shengji-item') : null;
      if (item && isUpgradePlanItem(item) && buyRoot.contains(item)) {
        e.preventDefault();
        selectPlanItem(buyRoot, item);
        return;
      }
      var submit = e.target && e.target.closest ? e.target.closest('.submit-vip') : null;
      if (submit && buyRoot.contains(submit)) {
        e.preventDefault();
        if (!isMergePageLoggedIn(buyRoot)) {
          goLoginOrModal(mergeLoginUrl);
          return;
        }
        var wrap =
          buyRoot.querySelector('.member-pay-panel--upgrade, .user-buy-upgrade-panel--upgrade, #right') ||
          buyRoot;
        runUpgradeAjax(wrap, mergeUpgradeUrl, false);
      }
    });

    buyRoot.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var submit = e.target && e.target.closest ? e.target.closest('.submit-vip') : null;
      if (submit && buyRoot.contains(submit)) {
        e.preventDefault();
        submit.click();
      }
    });
  }

  function initPopDelegates() {
    doc.addEventListener('click', function (e) {
      var item = e.target && e.target.closest ? e.target.closest('.ajax-upgrade-pop .shengji-item') : null;
      if (item) {
        e.preventDefault();
        var wrap = item.closest('.ajax-upgrade-pop');
        if (wrap) selectPlanItem(wrap, item);
        return;
      }
      var submit = e.target && e.target.closest ? e.target.closest('.ajax-upgrade-pop .submit-vip') : null;
      if (!submit) return;
      var pop = submit.closest('.ajax-upgrade-pop');
      if (!pop) return;
      var upgradeUrl = pop.getAttribute('data-url-user-upgrade');
      var loginUrl = pop.getAttribute('data-url-login');
      if (!upgradeUrl) return;
      e.preventDefault();
      if (pop.getAttribute('data-is-login') !== '1') {
        goLoginOrModal(loginUrl);
        return;
      }
      runUpgradeAjax(pop, upgradeUrl, true);
    });
  }

  function init() {
    initBuyRoot();
    initPopDelegates();
    syncCheckoutBar();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
