/**
 * 优惠券：入口摘要 + 弹窗选券（充值 / VIP / 充值弹窗共用）
 * API: coupon/get_list → info；coupon/receive → info；下单传 coupon_user_id
 */
(function (window, document) {
  'use strict';

  // buy/upgrade 页脚与 recharge_modal 都可能引入本脚本；二次加载直接跳过，避免双绑
  if (window.MacUserCoupon) {
    return;
  }

  var REFRESH_CODES = { 1003: 1, 1004: 1, 1005: 1, 1010: 1, 1011: 1 };
  var overlaySeq = 0;

  function apiBase() {
    if (window.maccms && maccms.path != null) {
      return String(maccms.path).replace(/\/+$/, '') + '/api.php';
    }
    return '/api.php';
  }

  function csrfHeaders() {
    var h = {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
      'Cache-Control': 'no-cache',
      Pragma: 'no-cache'
    };
    if (window.maccms && maccms.csrfToken) {
      h['X-CSRF-Token'] = maccms.csrfToken;
    }
    return h;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function getLang(key, zh, en) {
    if (typeof MAC !== 'undefined' && MAC.GetLang) {
      var t = MAC.GetLang(key);
      if (t) return t;
    }
    return String(localStorage.getItem('lang')) === '1' ? en : zh;
  }

  function toastErr(msg) {
    var m = String(msg || '');
    if (window.MemberDialog && window.MemberDialog.notify) {
      window.MemberDialog.notify(m);
      return;
    }
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
      MAC.Pop.StatusSheet({ type: 'error', message: m, hideOkButton: true, autoCloseMs: 2400 });
    } else if (typeof window.macToast === 'function') {
      window.macToast(m);
    } else if (window.MacTheme && typeof window.MacTheme.notify === 'function') {
      window.MacTheme.notify(m);
    } else if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.Msg === 'function') {
      MAC.Pop.Msg(280, 44, m, 1800);
    } else if (typeof MAC !== 'undefined' && MAC.alert) {
      MAC.alert(m);
    }
  }

  /** 选券层须高于 #recharge-overlay / .pay-overlay（二者打开时会动态抬 z） */
  function bumpOverlayZ(ov) {
    if (!ov) return;
    var floor = 10000000020;
    var CAP = 2147483646;
    var z = floor;
    try {
      if (typeof MAC !== 'undefined' && typeof MAC.nextOverlayZBase === 'function') {
        z = MAC.nextOverlayZBase({ exclude: '#' + ov.id + ', .mac-coupon-overlay' });
      } else {
        ['recharge-overlay', 'pay-overlay'].forEach(function (id) {
          var el = document.getElementById(id);
          if (!el || !el.classList.contains('show')) return;
          var n = parseInt(window.getComputedStyle(el).zIndex, 10);
          if (!isNaN(n) && n >= z) z = n + 10;
        });
      }
      if (typeof MAC !== 'undefined' && typeof MAC.clampOverlayZAboveRecharge === 'function') {
        z = MAC.clampOverlayZAboveRecharge(z);
      }
      if (z < floor) z = floor;
      // 撞 32-bit 上限时：把底下的充值/支付弹窗略降一档，保证选券层能盖住
      if (z >= CAP) {
        z = CAP;
        ['recharge-overlay', 'pay-overlay'].forEach(function (id) {
          var el = document.getElementById(id);
          if (!el || !el.classList.contains('show')) return;
          var n = parseInt(window.getComputedStyle(el).zIndex, 10);
          if (!isNaN(n) && n >= CAP - 1) {
            el.style.setProperty('z-index', String(CAP - 2), 'important');
          }
        });
      }
    } catch (e) {}
    if (typeof MAC !== 'undefined' && typeof MAC.setZIndexImportant === 'function') {
      MAC.setZIndexImportant(ov, z);
    } else {
      ov.style.setProperty('z-index', String(z), 'important');
    }
  }

  function toastOk(msg) {
    var m = String(msg || '');
    if (window.MemberDialog && window.MemberDialog.status) {
      window.MemberDialog.status({ type: 'success', message: m, hideOkButton: true, autoCloseMs: 1600 });
      return;
    }
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
      MAC.Pop.StatusSheet({ type: 'success', message: m, hideOkButton: true, autoCloseMs: 1600 });
    } else if (typeof window.macToast === 'function') {
      window.macToast(m);
    } else if (window.MacTheme && typeof window.MacTheme.notify === 'function') {
      window.MacTheme.notify(m);
    } else if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.Msg === 'function') {
      MAC.Pop.Msg(280, 44, m, 1800);
    } else if (typeof MAC !== 'undefined' && MAC.alert) {
      MAC.alert(m);
    }
  }

  function goLogin() {
    var loginUrl = '';
    var root = document.getElementById('mac-user-buy-root');
    if (root) loginUrl = root.getAttribute('data-url-login') || '';
    if (!loginUrl && typeof url === 'function') {
      try {
        loginUrl = url('user/login');
      } catch (e) {}
    }
    if (!loginUrl) loginUrl = '/index.php/user/login';
    var back = window.location.href || '/';
    if (typeof MAC !== 'undefined' && MAC.User && typeof MAC.User.openLoginOrNavigate === 'function') {
      MAC.User.openLoginOrNavigate(loginUrl, back);
    } else {
      window.location.href = loginUrl + (loginUrl.indexOf('?') >= 0 ? '&' : '?') + 'url=' + encodeURIComponent(back);
    }
  }

  function calcDiscount(price, coupon) {
    var original = Number(price) || 0;
    var discount = 0;
    if (!coupon || !meetsMinPrice(coupon, original)) {
      return { original: original, discount: 0, pay: original };
    }
    if (coupon.coupon_type === 'amount') {
      discount = Number(coupon.coupon_value) || 0;
    } else if (coupon.coupon_type === 'discount') {
      discount = (original * (Number(coupon.coupon_value) || 0)) / 100;
    }
    if (discount > original) discount = original;
    discount = Math.round(discount * 100) / 100;
    return {
      original: original,
      discount: discount,
      pay: Math.round((original - discount) * 100) / 100
    };
  }

  function titleOf(c) {
    if (c.coupon_type === 'discount') {
      var pct = Number(c.coupon_value) || 0;
      return getLang('string_coupon_off_pct', '减 ' + pct + '%', pct + '% off');
    }
    return getLang('string_coupon_off_amt', '减 ' + c.coupon_value + ' 元', 'Save ¥' + c.coupon_value);
  }

  function subOf(c) {
    var min = Number(c.coupon_min_price) || 0;
    if (min > 0) {
      return getLang('string_coupon_min', '满 ¥' + min + ' 可用', 'Min ¥' + min);
    }
    return getLang('string_coupon_no_min', '无门槛', 'No minimum');
  }

  /** 当前订单金额是否达到券门槛（前端兜底，防止后端 usable 未按金额过滤） */
  function meetsMinPrice(c, price) {
    if (!c) return false;
    var min = Number(c.coupon_min_price) || 0;
    if (min <= 0) return true;
    return Number(price) + 1e-6 >= min;
  }

  function isCouponSelectable(c, price) {
    if (!c) return false;
    if (Number(c.usable) !== 1) return false;
    if (String(c.order_code || '') !== '') return false;
    return meetsMinPrice(c, price);
  }

  function faceOf(c) {
    if (c.coupon_type === 'discount') {
      return { value: '-' + String(Number(c.coupon_value) || 0) + '%', unit: '' };
    }
    var n = Number(c.coupon_value);
    var show = isNaN(n) ? String(c.coupon_value || '') : String(n % 1 === 0 ? n : n.toFixed(2));
    return { value: show, unit: getLang('string_coupon_face_yuan', '元', 'CNY') };
  }

  function cardFaceHtml(c) {
    var f = faceOf(c);
    var unitHtml = f.unit ? '<span class="mac-coupon-card__value-unit">' + esc(f.unit) + '</span>' : '';
    return (
      '<div class="mac-coupon-card__face" aria-hidden="true">' +
      '<span class="mac-coupon-card__value">' +
      esc(f.value) +
      '</span>' +
      unitHtml +
      '</div>'
    );
  }

  function Panel(el, opts) {
    this.el = el;
    this.opts = opts || {};
    this.scene = el.getAttribute('data-scene') || this.opts.scene || 'recharge';
    this.selectedId = 0;
    this.selectedCoupon = null;
    this.available = [];
    this.mine = [];
    this._timer = null;
    this._bound = false;
    this._receiving = false;
    this._open = false;
    this._overlay = null;
    this._overlayId = 'mac-coupon-overlay-' + (el.id || el.getAttribute('data-mount') || ++overlaySeq);
    this._onKey = null;
    this._needLogin = false;
    this._loadError = '';
  }

  Panel.prototype.getPrice = function () {
    if (typeof this.opts.getPrice === 'function') {
      return Number(this.opts.getPrice()) || 0;
    }
    return 0;
  };

  Panel.prototype.getVipCtx = function () {
    if (typeof this.opts.getVipContext === 'function') {
      return this.opts.getVipContext() || {};
    }
    return {};
  };

  Panel.prototype.persistSelection = function () {
    if (!this.el) return;
    if (this.selectedId > 0) {
      this.el.setAttribute('data-selected-coupon-user-id', String(this.selectedId));
    } else {
      this.el.removeAttribute('data-selected-coupon-user-id');
    }
  };

  Panel.prototype.getSelectedCouponUserId = function () {
    var id = this.selectedId > 0 ? this.selectedId : 0;
    // 兜底：确认弹窗期间若内存态被静默刷新冲掉，仍可读 DOM 上锁住的选中
    if (!id && this.el) {
      id = Number(this.el.getAttribute('data-selected-coupon-user-id')) || 0;
    }
    return id > 0 ? id : 0;
  };

  Panel.prototype.getPreview = function () {
    return calcDiscount(this.getPrice(), this.selectedCoupon);
  };

  Panel.prototype.onChange = function () {
    if (typeof this.opts.onChange === 'function') {
      this.opts.onChange(this.getSelectedCouponUserId(), this.getPreview());
    }
  };

  Panel.prototype.bind = function () {
    if (this._bound) return;
    this._bound = true;
    var self = this;
    this.el.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      if (t.closest('[data-coupon-login]')) {
        ev.preventDefault();
        goLogin();
        return;
      }
      if (t.closest('[data-coupon-clear]')) {
        ev.preventDefault();
        ev.stopPropagation();
        self.selectedId = 0;
        self.selectedCoupon = null;
        self.persistSelection();
        self.renderSummary();
        self.onChange();
        return;
      }
      if (t.closest('[data-coupon-open]')) {
        ev.preventDefault();
        if (self._needLogin) {
          goLogin();
          return;
        }
        self.showModal();
      }
    });
  };

  Panel.prototype.ensureOverlay = function () {
    var self = this;
    var ov = document.getElementById(this._overlayId);
    if (!ov) {
      ov = document.createElement('div');
      ov.id = this._overlayId;
      ov.className = 'mac-coupon-overlay';
      ov.setAttribute('hidden', '');
      ov.innerHTML =
        '<div class="mac-coupon-modal" role="dialog" aria-modal="true" aria-labelledby="' +
        this._overlayId +
        '-title">' +
        '<div class="mac-coupon-modal__hd">' +
        '<div>' +
        '<h3 class="mac-coupon-modal__title" id="' +
        this._overlayId +
        '-title">' +
        esc(getLang('string_coupon_title', '优惠券', 'Coupons')) +
        '</h3>' +
        '<p class="mac-coupon-modal__sub" data-coupon-modal-sub></p>' +
        '</div>' +
        '<button type="button" class="mac-coupon-modal__close" data-coupon-close aria-label="Close">×</button>' +
        '</div>' +
        '<div class="mac-coupon-modal__bd" data-coupon-body></div>' +
        '<div class="mac-coupon-modal__ft" data-coupon-modal-preview></div>' +
        '</div>';
      document.body.appendChild(ov);
    }
    this._overlay = ov;
    if (!ov.getAttribute('data-bound')) {
      ov.setAttribute('data-bound', '1');
      ov.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        if (t === ov || t.closest('[data-coupon-close]')) {
          self.hideModal();
          return;
        }
        var recv = t.closest('[data-coupon-receive]');
        if (recv) {
          ev.preventDefault();
          ev.stopPropagation();
          self.receive(Number(recv.getAttribute('data-coupon-receive')) || 0, recv);
          return;
        }
        var pick = t.closest('[data-coupon-select]');
        if (pick) {
          ev.preventDefault();
          ev.stopPropagation();
          var id = Number(pick.getAttribute('data-coupon-select')) || 0;
          if (id < 1) return;
          var coupon = self.findMineById(id);
          if (!isCouponSelectable(coupon, self.getPrice())) {
            toastErr(
              getLang(
                'string_coupon_min_not_met',
                '未满使用门槛，当前金额不可用',
                'Order amount is below this coupon minimum'
              )
            );
            return;
          }
          if (self.selectedId === id) {
            self.selectedId = 0;
            self.selectedCoupon = null;
          } else {
            self.selectedId = id;
            self.selectedCoupon = coupon;
          }
          self.persistSelection();
          self.renderSummary();
          self.onChange();
          if (self.selectedId) {
            self.hideModal();
          } else if (self._open) {
            self.renderModalBody();
          }
        }
      });
    }
  };

  Panel.prototype.showModal = function () {
    var self = this;
    this.ensureOverlay();
    this._open = true;
    if (this._overlay.parentNode) {
      document.body.appendChild(this._overlay);
    }
    bumpOverlayZ(this._overlay);
    this._overlay.removeAttribute('hidden');
    requestAnimationFrame(function () {
      bumpOverlayZ(self._overlay);
      self._overlay.classList.add('is-open');
    });
    document.documentElement.style.overflow = 'hidden';
    this._onKey = function (ev) {
      if (ev.key === 'Escape') self.hideModal();
    };
    document.addEventListener('keydown', this._onKey);
    // 先画当前内存态，再强制拉最新，避免领完后重开仍显示「可领取」
    this.renderModalBody();
    this.refresh({ keepModalOpen: true, force: true });
  };

  Panel.prototype.hideModal = function () {
    if (!this._overlay) return;
    this._open = false;
    this._overlay.classList.remove('is-open');
    document.documentElement.style.overflow = '';
    if (this._onKey) {
      document.removeEventListener('keydown', this._onKey);
      this._onKey = null;
    }
    var ov = this._overlay;
    setTimeout(function () {
      if (!ov.classList.contains('is-open')) ov.setAttribute('hidden', '');
    }, 240);
  };

  Panel.prototype.receive = function (couponId, btnEl) {
    var self = this;
    if (couponId < 1) return;
    if (this._receiving) return;
    this._receiving = true;
    // 取消进行中的金额静默刷新，避免旧列表盖回「可领取」
    if (this._timer) {
      clearTimeout(this._timer);
      this._timer = null;
    }
    var btn =
      btnEl ||
      (this._overlay && this._overlay.querySelector('[data-coupon-receive="' + couponId + '"]'));
    var btnText = '';
    if (btn) {
      btn.disabled = true;
      btnText = btn.textContent || '';
      btn.textContent = getLang('string_pay_ing', '领取中…', 'Claiming…');
    }
    var body = new URLSearchParams();
    body.set('coupon_id', String(couponId));
    fetch(apiBase() + '/coupon/receive', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, csrfHeaders()),
      body: body.toString()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || Number(json.code) !== 1) {
          var code = json && Number(json.code);
          if (code === 1401) {
            goLogin();
            return;
          }
          if (code === 1006) {
            toastOk((json && json.msg) || getLang('string_coupon_receive_ok', '已领取', 'Already claimed'));
            self.moveClaimedLocally(couponId, 0);
            return self.refresh({ keepModalOpen: true, force: true }).then(function () {
              self.selectMineByCouponId(couponId);
            });
          }
          toastErr((json && json.msg) || getLang('string_load_err', '领取失败', 'Failed'));
          return self.refresh({ keepModalOpen: true, force: true });
        }
        var newId = Number((json.info || {}).coupon_user_id) || 0;
        toastOk((json && json.msg) || getLang('string_coupon_receive_ok', '领取成功', 'Claimed'));
        // 先本地挪走可领 → 我的券，再无缓存重拉，避免缓存把旧可领刷回来
        self.moveClaimedLocally(couponId, newId);
        self.applyReceivedCoupon(newId, couponId);
        return self.refresh({ keepModalOpen: self._open, force: true, silent: !self._open }).then(function () {
          if (newId && !self.findMineById(newId)) {
            return self.refresh({ keepModalOpen: self._open, force: true }).then(function () {
              self.applyReceivedCoupon(newId, couponId);
            });
          }
          // 列表已与服务器对齐；若仍开着弹窗且已选中，保持关闭逻辑已在 apply 做过
          if (self._open && newId) self.applyReceivedCoupon(newId, couponId);
        });
      })
      .catch(function () {
        toastErr(getLang('string_load_err', '网络错误', 'Network error'));
        if (btn) {
          btn.disabled = false;
          if (btnText) btn.textContent = btnText;
        }
      })
      .then(function () {
        self._receiving = false;
        if (btn && btn.isConnected) {
          btn.disabled = false;
          if (btnText) btn.textContent = btnText;
        }
      });
  };

  /** 领取成功瞬间：从 available 挪到 mine，立刻重绘 */
  Panel.prototype.moveClaimedLocally = function (couponId, couponUserId) {
    var cid = Number(couponId) || 0;
    if (cid < 1) return;
    var claimed = null;
    var nextAvail = [];
    var i;
    for (i = 0; i < this.available.length; i++) {
      if (Number(this.available[i].coupon_id) === cid) {
        claimed = this.available[i];
      } else {
        nextAvail.push(this.available[i]);
      }
    }
    this.available = nextAvail;

    var exists = false;
    for (i = 0; i < this.mine.length; i++) {
      if (Number(this.mine[i].coupon_id) === cid) {
        exists = true;
        if (couponUserId) this.mine[i].coupon_user_id = Number(couponUserId) || this.mine[i].coupon_user_id;
        this.mine[i].usable = 1;
        this.mine[i].coupon_user_status = 0;
        this.mine[i].order_code = '';
        break;
      }
    }
    if (!exists && claimed) {
      var row = {};
      for (var k in claimed) {
        if (Object.prototype.hasOwnProperty.call(claimed, k)) row[k] = claimed[k];
      }
      row.coupon_user_id = Number(couponUserId) || 0;
      row.coupon_user_status = 0;
      row.order_code = '';
      row.usable = 1;
      this.mine.unshift(row);
    }
    if (this._open) this.renderModalBody();
    this.renderSummary();
  };

  /** 领取成功后：可用则自动选中并关弹窗；不可用则留在「我的券」 */
  Panel.prototype.applyReceivedCoupon = function (couponUserId, couponId) {
    var id = Number(couponUserId) || 0;
    if (id < 1 && couponId) {
      this.selectMineByCouponId(couponId);
      return;
    }
    if (id < 1) {
      if (this._open) this.renderModalBody();
      return;
    }
    this.selectedId = id;
    this.syncSelectedFromMine();
    if (this.selectedCoupon) {
      this.renderSummary();
      this.onChange();
      this.hideModal();
      return;
    }
    // 领到了但当前金额/场景不可用：留在弹窗，滚到我的券
    if (this._open) {
      this.renderModalBody();
      this.scrollModalToMine();
    }
    this.renderSummary();
    this.onChange();
  };

  Panel.prototype.selectMineByCouponId = function (couponId) {
    var cid = Number(couponId) || 0;
    if (cid < 1) return;
    var found = null;
    for (var i = 0; i < this.mine.length; i++) {
      if (Number(this.mine[i].coupon_id) === cid) {
        found = this.mine[i];
        break;
      }
    }
    if (!found) {
      if (this._open) {
        this.renderModalBody();
        this.scrollModalToMine();
      }
      return;
    }
    this.applyReceivedCoupon(Number(found.coupon_user_id) || 0, 0);
  };

  Panel.prototype.scrollModalToMine = function () {
    if (!this._overlay) return;
    var mineSec = this._overlay.querySelector('.mac-coupon-list--mine');
    if (mineSec && typeof mineSec.scrollIntoView === 'function') {
      try {
        mineSec.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } catch (e) {
        try {
          mineSec.scrollIntoView(true);
        } catch (e2) {}
      }
    }
  };

  Panel.prototype.buildQuery = function () {
    // 不传 price：可领列表随时展示；满减是否可用由前端用当前金额判断
    var q = { scene: this.scene };
    if (this.scene === 'vip') {
      var ctx = this.getVipCtx();
      if (ctx.group_id) q.group_id = ctx.group_id;
      if (ctx.long) q.long = ctx.long;
    }
    var parts = [];
    Object.keys(q).forEach(function (k) {
      if (q[k] === undefined || q[k] === null || q[k] === '') return;
      parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(q[k]));
    });
    return parts.join('&');
  };

  Panel.prototype.syncSelectedFromMine = function () {
    if (!this.selectedId) {
      this.selectedCoupon = null;
      this.persistSelection();
      return;
    }
    var found = this.findMineById(this.selectedId);
    // 仅在券丢失 / 已用 / 被预占时清选中；满减门槛不达标仍保留用户选择（预览按金额算）
    // 避免：改金额瞬间 getPrice=0、或确认弹窗期间静默刷新把选中冲掉 → 下单丢 coupon_user_id
    if (
      !found ||
      Number(found.usable) !== 1 ||
      String(found.order_code || '') !== ''
    ) {
      this.selectedId = 0;
      this.selectedCoupon = null;
      this.persistSelection();
      return;
    }
    this.selectedCoupon = found;
    this.persistSelection();
  };

  /** 已领进 mine 的券不得再留在 available（防脏缓存/竞态） */
  Panel.prototype.dedupeAvailableAgainstMine = function () {
    if (!this.mine.length || !this.available.length) return;
    var mineIds = {};
    var i;
    for (i = 0; i < this.mine.length; i++) {
      mineIds[Number(this.mine[i].coupon_id) || 0] = 1;
    }
    var next = [];
    for (i = 0; i < this.available.length; i++) {
      var cid = Number(this.available[i].coupon_id) || 0;
      if (!mineIds[cid]) next.push(this.available[i]);
    }
    this.available = next;
  };

  Panel.prototype.findMineById = function (couponUserId) {
    var id = Number(couponUserId) || 0;
    if (id < 1) return null;
    for (var i = 0; i < this.mine.length; i++) {
      if (Number(this.mine[i].coupon_user_id) === id) return this.mine[i];
    }
    return null;
  };

  Panel.prototype.refresh = function (keepModalOpen) {
    var self = this;
    var silent = false;
    var force = false;
    if (keepModalOpen && typeof keepModalOpen === 'object') {
      silent = !!keepModalOpen.silent;
      force = !!keepModalOpen.force;
      keepModalOpen = !!keepModalOpen.keepModalOpen;
    }
    this.bind();
    this.el.removeAttribute('hidden');
    // 已有摘要时不要整块换成「加载中」，否则切金额会回闪
    var hasUi = !!this.el.querySelector(
      '.mac-coupon-summary, .mac-coupon-summary--login, .mac-coupon-summary--err'
    );
    if (!silent && !keepModalOpen && !this._open && !hasUi) {
      this.el.innerHTML =
        '<div class="mac-coupon-loading">' + esc(getLang('string_loading', '加载中…', 'Loading…')) + '</div>';
    }
    this._fetchSeq = (this._fetchSeq || 0) + 1;
    var seq = this._fetchSeq;
    if (force) this._forceSeq = seq;
    var q = this.buildQuery();
    // 防 GET 缓存把「已领」又刷成可领
    q = (q ? q + '&' : '') + '_ts=' + String(Date.now());
    return fetch(apiBase() + '/coupon/get_list?' + q, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: csrfHeaders()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        // force 请求：即使中间有静默刷新插队，只要不是更新的 force，仍可应用
        if (seq !== self._fetchSeq) {
          if (!force || seq !== self._forceSeq) return;
          // 若已有更新的 force，丢弃本轮
          if (self._forceSeq && seq < self._forceSeq) return;
        }
        self._needLogin = false;
        self._loadError = '';
        if (!json || Number(json.code) !== 1) {
          if (json && Number(json.code) === 1401) {
            self._needLogin = true;
            self.available = [];
            self.mine = [];
            self.selectedId = 0;
            self.selectedCoupon = null;
            self.persistSelection();
            self.renderSummary();
            self.onChange();
            return;
          }
          self._loadError = (json && json.msg) || getLang('string_load_err', '加载失败', 'Failed');
          self.available = [];
          self.mine = [];
          self.renderSummary();
          self.onChange();
          return;
        }
        var info = json.info || {};
        self.available = info.available || [];
        self.mine = info.mine || [];
        self.dedupeAvailableAgainstMine();
        self.syncSelectedFromMine();
        if (self._open) self.renderModalBody();
        if (
          silent &&
          self.el.querySelector('.mac-coupon-summary') &&
          !self._needLogin &&
          !self._loadError
        ) {
          self.patchSummaryMeta();
          self.applyPriceLocally();
        } else {
          self.renderSummary();
          self.onChange();
        }
      })
      .catch(function () {
        if (seq !== self._fetchSeq && !(force && seq === self._forceSeq)) return;
        self._loadError = getLang('string_load_err', '网络错误', 'Network error');
        self.renderSummary();
      });
  };

  /** 静默刷新后只改数量/提示，不重绘整块摘要，避免回闪 */
  Panel.prototype.patchSummaryMeta = function () {
    var total = this.available.length + this.mine.length;
    var usableCount = 0;
    var priceNow = this.getPrice();
    for (var i = 0; i < this.mine.length; i++) {
      if (isCouponSelectable(this.mine[i], priceNow)) usableCount++;
    }
    var countEl = this.el.querySelector('.mac-coupon-summary__count');
    var label = this.el.querySelector('.mac-coupon-summary__label');
    if (total > 0) {
      if (countEl) {
        countEl.textContent = String(total);
      } else if (label) {
        var span = document.createElement('span');
        span.className = 'mac-coupon-summary__count';
        span.textContent = String(total);
        label.appendChild(span);
      }
    } else if (countEl && countEl.parentNode) {
      countEl.parentNode.removeChild(countEl);
    }
    var muted = this.el.querySelector('.mac-coupon-summary__muted');
    if (muted && !this.selectedCoupon) {
      muted.textContent = getLang(
        'string_coupon_pick_hint',
        usableCount
          ? usableCount + ' 张可用 · 点击选择'
          : this.available.length
            ? this.available.length + ' 张可领 · 点击查看'
            : total
              ? '共 ' + total + ' 张 · 点击查看'
              : '暂无可用优惠券',
        'Tap to choose'
      );
    }
  };

  /** 金额变化时先本地刷新预览（不打断现有摘要 DOM） */
  Panel.prototype.applyPriceLocally = function () {
    if (!this.el.querySelector('.mac-coupon-summary')) return;
    this.syncSelectedFromMine();
    var picked = this.selectedCoupon;
    var price = this.getPrice();
    var p = calcDiscount(price, picked);
    var saveEl = this.el.querySelector('.mac-coupon-summary__save');
    var mid = this.el.querySelector('.mac-coupon-summary__muted, .mac-coupon-summary__picked');
    if (picked) {
      if (saveEl) {
        saveEl.textContent = '-¥' + p.discount.toFixed(2);
      } else if (mid && mid.classList.contains('mac-coupon-summary__picked') && price > 0) {
        var span = document.createElement('span');
        span.className = 'mac-coupon-summary__save';
        span.textContent = '-¥' + p.discount.toFixed(2);
        mid.appendChild(span);
      }
    } else if (saveEl && saveEl.parentNode) {
      saveEl.parentNode.removeChild(saveEl);
    }
    // 未选中时若被门槛清掉，补一句提示
    if (!picked) {
      var copy = this.el.querySelector('.mac-coupon-summary__copy');
      var muted = this.el.querySelector('.mac-coupon-summary__muted');
      var pickedWrap = this.el.querySelector('.mac-coupon-summary__picked');
      if (pickedWrap && copy) {
        pickedWrap.parentNode.removeChild(pickedWrap);
        if (!muted) {
          var m = document.createElement('span');
          m.className = 'mac-coupon-summary__muted';
          m.textContent = getLang('string_coupon_pick_hint', '点击选择优惠券', 'Tap to choose');
          copy.appendChild(m);
        }
      }
      var clearBtn = this.el.querySelector('[data-coupon-clear]');
      if (clearBtn && clearBtn.parentNode) clearBtn.parentNode.removeChild(clearBtn);
      var act = this.el.querySelector('.mac-coupon-summary__action');
      if (act) act.textContent = getLang('string_coupon_choose', '选择', 'Choose');
    }
    this.renderPreview();
    this.onChange();
  };

  Panel.prototype.renderSummary = function () {
    if (this._needLogin) {
      this.el.innerHTML =
        '<div class="mac-coupon-summary mac-coupon-summary--login">' +
        '<span class="mac-coupon-summary__text">' +
        esc(getLang('string_pay_login_first', '请先登录后查看优惠券', 'Please login to view coupons')) +
        '</span>' +
        ' <a href="javascript:;" class="mac-coupon-login-link" data-coupon-login="1">' +
        esc(getLang('string_go_login', '去登录', 'Login')) +
        '</a></div>';
      return;
    }
    if (this._loadError) {
      this.el.innerHTML =
        '<div class="mac-coupon-summary mac-coupon-summary--err">' + esc(this._loadError) + '</div>';
      return;
    }

    var total = this.available.length + this.mine.length;
    var usableCount = 0;
    var priceNow = this.getPrice();
    for (var i = 0; i < this.mine.length; i++) {
      if (isCouponSelectable(this.mine[i], priceNow)) usableCount++;
    }
    var picked = this.selectedCoupon;
    var face = picked ? faceOf(picked) : null;
    var faceText = face ? face.value + (face.unit || '') : '';
    var name = picked ? picked.coupon_name || titleOf(picked) : '';
    var price = this.getPrice();
    var p = calcDiscount(price, picked);

    var midHtml = '';
    if (picked) {
      midHtml =
        '<span class="mac-coupon-summary__picked">' +
        '<span class="mac-coupon-summary__face">' +
        esc(faceText) +
        '</span>' +
        '<span class="mac-coupon-summary__name">' +
        esc(name) +
        '</span>' +
        (price > 0
          ? '<span class="mac-coupon-summary__save">-' +
            esc('¥' + p.discount.toFixed(2)) +
            '</span>'
          : '') +
        '</span>';
    } else if (total < 1) {
      midHtml =
        '<span class="mac-coupon-summary__muted">' +
        esc(getLang('string_coupon_none', '暂无可用优惠券', 'No coupons')) +
        '</span>';
    } else {
      midHtml =
        '<span class="mac-coupon-summary__muted">' +
        esc(
          getLang(
            'string_coupon_pick_hint',
            usableCount
              ? usableCount + ' 张可用 · 点击选择'
              : this.available.length
                ? this.available.length + ' 张可领 · 点击查看'
                : '共 ' + total + ' 张 · 点击查看',
            usableCount
              ? usableCount + ' usable · tap to choose'
              : 'Tap to view'
          )
        ) +
        '</span>';
    }

    var actionLabel = picked
      ? getLang('string_coupon_change', '更换', 'Change')
      : getLang('string_coupon_choose', '选择', 'Choose');

    var clearBtn = picked
      ? '<button type="button" class="mac-coupon-summary__clear" data-coupon-clear>' +
        esc(getLang('string_coupon_unselect', '取消', 'Clear')) +
        '</button>'
      : '';

    this.el.innerHTML =
      '<div class="mac-coupon-summary" data-coupon-open role="button" tabindex="0">' +
      '<div class="mac-coupon-summary__copy">' +
      '<span class="mac-coupon-summary__label">' +
      esc(getLang('string_coupon_title', '优惠券', 'Coupons')) +
      (total
        ? '<span class="mac-coupon-summary__count">' + total + '</span>'
        : '') +
      '</span>' +
      midHtml +
      '</div>' +
      '<div class="mac-coupon-summary__aside">' +
      clearBtn +
      '<span class="mac-coupon-summary__action">' +
      esc(actionLabel) +
      '</span></div></div>' +
      (this.usesUnifiedVipCheckout()
        ? ''
        : '<div class="mac-coupon-preview" data-coupon-preview></div>');

    this.renderPreview();
    if (this.usesUnifiedVipCheckout() && window.MacUpgradeCheckout && typeof MacUpgradeCheckout.sync === 'function') {
      MacUpgradeCheckout.sync();
    }
  };

  Panel.prototype.usesUnifiedVipCheckout = function () {
    return this.opts.mount === 'buy-upgrade' && !!document.querySelector('.member-pay-settle');
  };

  Panel.prototype.renderPreview = function () {
    if (this.usesUnifiedVipCheckout()) {
      if (window.MacUpgradeCheckout && typeof MacUpgradeCheckout.sync === 'function') {
        MacUpgradeCheckout.sync();
      }
      return;
    }
    var box = this.el.querySelector('[data-coupon-preview]');
    if (!box) return;
    var price = this.getPrice();
    if (!(price > 0)) {
      box.innerHTML = '';
      box.setAttribute('hidden', '');
      return;
    }
    box.removeAttribute('hidden');
    var p = calcDiscount(price, this.selectedCoupon);
    var lineText;
    var hintText;
    if (!this.selectedCoupon) {
      lineText = getLang(
        'string_coupon_preview_none',
        '未选券 · 实付 ¥' + p.pay.toFixed(2),
        'No coupon · Pay ¥' + p.pay.toFixed(2)
      );
      hintText = getLang(
        'string_coupon_cash_only',
        '金币仍按充值原价到账，券只减现金',
        'Coins credit uses original amount; coupon reduces cash only'
      );
    } else {
      lineText = getLang(
        'string_coupon_preview',
        '原价 ¥' +
          p.original.toFixed(2) +
          ' − 券 ¥' +
          p.discount.toFixed(2) +
          ' = 实付 ¥' +
          p.pay.toFixed(2) +
          '（预览）',
        '¥' +
          p.original.toFixed(2) +
          ' − ¥' +
          p.discount.toFixed(2) +
          ' = ¥' +
          p.pay.toFixed(2) +
          ' (preview)'
      );
      hintText = getLang(
        'string_coupon_cash_only',
        '金币仍按充值原价到账，券只减现金；实付以支付页为准',
        'Coins use original amount; final price is set by server'
      );
    }
    // 原地改字，避免 innerHTML 整块替换造成回闪
    var line = box.querySelector('.mac-coupon-preview__line');
    var hint = box.querySelector('.mac-coupon-preview__hint');
    if (line && hint) {
      if (line.textContent !== lineText) line.textContent = lineText;
      if (hint.textContent !== hintText) hint.textContent = hintText;
      return;
    }
    box.innerHTML =
      '<div class="mac-coupon-preview__line">' +
      esc(lineText) +
      '</div>' +
      '<div class="mac-coupon-preview__hint">' +
      esc(hintText) +
      '</div>';
  };

  Panel.prototype.cardAvailable = function (c) {
    return (
      '<div class="mac-coupon-card mac-coupon-card--available">' +
      cardFaceHtml(c) +
      '<div class="mac-coupon-card__main">' +
      '<div class="mac-coupon-card__title">' +
      esc(c.coupon_name || titleOf(c)) +
      '</div>' +
      '<div class="mac-coupon-card__sub">' +
      esc(titleOf(c) + ' · ' + subOf(c)) +
      '</div></div>' +
      '<div class="mac-coupon-card__action">' +
      '<button type="button" class="mac-coupon-card__btn" data-coupon-receive="' +
      esc(String(c.coupon_id)) +
      '">' +
      esc(getLang('string_coupon_receive', '领取', 'Claim')) +
      '</button></div></div>'
    );
  };

  Panel.prototype.cardMine = function (c) {
    var price = this.getPrice();
    var reserved = Number(c.usable) !== 1 && String(c.order_code || '') !== '';
    var belowMin = !reserved && Number(c.usable) === 1 && !meetsMinPrice(c, price);
    var selectable = isCouponSelectable(c, price);
    var selected = this.selectedId === Number(c.coupon_user_id);
    var cls = 'mac-coupon-card mac-coupon-card--mine';
    if (selected) cls += ' is-selected';
    if (!selectable) cls += ' is-disabled';
    if (selectable) cls += ' is-pickable';
    var badge = '';
    if (selected) {
      badge = '<span class="mac-coupon-card__badge">' + esc(getLang('string_coupon_selected', '已选中', 'Selected')) + '</span>';
    } else if (reserved) {
      badge =
        '<span class="mac-coupon-card__badge mac-coupon-card__badge--warn">' +
        esc(getLang('string_coupon_reserved', '待支付占用中', 'Held by unpaid order')) +
        '</span>';
    } else if (belowMin) {
      var minNeed = Number(c.coupon_min_price) || 0;
      badge =
        '<span class="mac-coupon-card__badge mac-coupon-card__badge--warn">' +
        esc(getLang('string_coupon_below_min', '未满 ¥' + minNeed, 'Need ¥' + minNeed)) +
        '</span>';
    }
    var action = '';
    if (selectable) {
      action =
        '<span class="mac-coupon-card__btn-label">' +
        esc(
          selected
            ? getLang('string_coupon_unselect', '取消', 'Off')
            : getLang('string_coupon_select', '使用', 'Use')
        ) +
        '</span>';
    } else if (reserved) {
      action =
        '<span class="mac-coupon-card__hint">' +
        esc(
          getLang(
            'string_coupon_reserved_hint',
            '订单 ' + String(c.order_code).slice(0, 10) + '… 锁定',
            'Order locked'
          )
        ) +
        '</span>';
    } else if (belowMin) {
      action =
        '<span class="mac-coupon-card__hint">' +
        esc(getLang('string_coupon_below_min_hint', '提高金额后可用', 'Increase amount')) +
        '</span>';
    }
    var pickAttrs = selectable
      ? ' data-coupon-select="' +
        esc(String(c.coupon_user_id)) +
        '" data-usable="1" role="button" tabindex="0"'
      : '';
    return (
      '<div class="' +
      cls +
      '"' +
      pickAttrs +
      '>' +
      cardFaceHtml(c) +
      '<div class="mac-coupon-card__main">' +
      '<div class="mac-coupon-card__row">' +
      '<div class="mac-coupon-card__title">' +
      esc(c.coupon_name || titleOf(c)) +
      '</div>' +
      badge +
      '</div>' +
      '<div class="mac-coupon-card__sub">' +
      esc(titleOf(c) + ' · ' + subOf(c)) +
      '</div></div>' +
      '<div class="mac-coupon-card__action">' +
      action +
      '</div></div>'
    );
  };

  Panel.prototype.renderModalBody = function () {
    this.ensureOverlay();
    var body = this._overlay.querySelector('[data-coupon-body]');
    var sub = this._overlay.querySelector('[data-coupon-modal-sub]');
    var ft = this._overlay.querySelector('[data-coupon-modal-preview]');
    if (!body) return;

    var total = this.available.length + this.mine.length;
    if (sub) {
      sub.textContent = total
        ? getLang('string_coupon_modal_sub', '共 ' + total + ' 张 · 选中后立即生效', total + ' coupons')
        : '';
    }

    var html = [];
    if (this.available.length) {
      html.push('<section class="mac-coupon-section">');
      html.push(
        '<div class="mac-coupon-section-title">' +
          esc(getLang('string_coupon_available', '可领取', 'Available')) +
          '<span class="mac-coupon-section-title__num">' +
          this.available.length +
          '</span></div>'
      );
      html.push('<div class="mac-coupon-list" role="list">');
      for (var a = 0; a < this.available.length; a++) html.push(this.cardAvailable(this.available[a]));
      html.push('</div></section>');
    }
    if (this.mine.length) {
      html.push('<section class="mac-coupon-section">');
      html.push(
        '<div class="mac-coupon-section-title">' +
          esc(getLang('string_coupon_mine', '我的券', 'My coupons')) +
          '<span class="mac-coupon-section-title__num mac-coupon-section-title__num--muted">' +
          this.mine.length +
          '</span></div>'
      );
      html.push('<div class="mac-coupon-list mac-coupon-list--mine" role="list">');
      for (var m = 0; m < this.mine.length; m++) html.push(this.cardMine(this.mine[m]));
      html.push('</div></section>');
    }
    if (!this.available.length && !this.mine.length) {
      html.push(
        '<div class="mac-coupon-empty">' +
          esc(getLang('string_coupon_none', '暂无可用优惠券', 'No coupons')) +
          '</div>'
      );
    }
    body.innerHTML = html.join('');

    if (ft) {
      var price = this.getPrice();
      if (price > 0) {
        var p = calcDiscount(price, this.selectedCoupon);
        ft.innerHTML =
          '<div class="mac-coupon-preview__line">' +
          esc(
            this.selectedCoupon
              ? getLang(
                  'string_coupon_preview',
                  '实付预览 ¥' + p.pay.toFixed(2),
                  'Pay ¥' + p.pay.toFixed(2)
                )
              : getLang('string_coupon_preview_none', '未选券 · 实付 ¥' + p.pay.toFixed(2), 'No coupon · ¥' + p.pay.toFixed(2))
          ) +
          '</div>';
        ft.removeAttribute('hidden');
      } else {
        ft.innerHTML = '';
        ft.setAttribute('hidden', '');
      }
    }
  };

  Panel.prototype.scheduleRefresh = function () {
    var self = this;
    // 金额变化：本地更新「是否可用」与预览；仍静默拉列表（VIP 换套餐要带 group/long）
    this.applyPriceLocally();
    if (this._open) this.renderModalBody();
    if (this._timer) clearTimeout(this._timer);
    this._timer = setTimeout(function () {
      self.refresh({ silent: true, keepModalOpen: self._open });
    }, 280);
  };

  var instances = {};

  function mount(el, opts) {
    if (!el) return null;
    var key = el.id || el.getAttribute('data-mount') || 'default';
    if (instances[key]) {
      instances[key].opts = Object.assign({}, instances[key].opts, opts || {});
      instances[key].scheduleRefresh();
      return instances[key];
    }
    var p = new Panel(el, opts || {});
    instances[key] = p;
    p.refresh();
    return p;
  }

  function getByMount(mount) {
    var key;
    for (key in instances) {
      if (instances[key] && instances[key].el && instances[key].el.getAttribute('data-mount') === mount) {
        return instances[key];
      }
    }
    return null;
  }

  /**
   * @param {object} json
   * @param {object|null} panel
   * @param {object} [opts] opts.silent=true 时不 toast（调用方已展示错误文案）
   */
  function handleOrderError(json, panel, opts) {
    if (!json) return false;
    var code = Number(json.code);
    if (code === 1) return false;
    opts = opts || {};
    if (!opts.silent) {
      toastErr(json.msg || getLang('string_load_err', '操作失败', 'Failed'));
    }
    if (REFRESH_CODES[code] || code === 1012 || code === 1015) {
      if (panel) panel.refresh(false);
    }
    return true;
  }

  function initBuyPage() {
    var root = document.getElementById('mac-user-buy-root');
    if (!root) return;

    var rechargeEl = document.getElementById('mac-user-coupon-panel');
    var vipEl = document.getElementById('mac-user-coupon-panel-vip');

    if (rechargeEl) {
      mount(rechargeEl, {
        scene: 'recharge',
        getPrice: function () {
          var inp = document.getElementById('price_input');
          return inp ? Number(inp.value) || 0 : 0;
        },
        onChange: function (id, preview) {
          var btn = document.getElementById('btn_submit_pay');
          if (!btn || !preview) return;
          if (id && preview.pay >= 0) {
            btn.textContent = getLang('string_confirm_pay', '确认充值', 'Confirm') + ' ¥' + preview.pay.toFixed(2);
          } else {
            btn.textContent = getLang('string_confirm_pay', '确认充值', 'Confirm recharge');
          }
        }
      });
      var priceInput = document.getElementById('price_input');
      if (priceInput) {
        priceInput.addEventListener('input', function () {
          var p = getByMount('buy-recharge');
          if (p) p.scheduleRefresh();
        });
      }
      root.addEventListener('click', function (ev) {
        var card = ev.target && ev.target.closest && ev.target.closest('.amount-card');
        if (!card) return;
        // 等 user-buy 把 #price_input 写完再刷新，避免先用旧金额再跳到新金额的回闪
        setTimeout(function () {
          var p = getByMount('buy-recharge');
          if (p) p.scheduleRefresh();
        }, 0);
      });
    }

    if (vipEl) {
      mount(vipEl, {
        scene: 'vip',
        getPrice: function () {
          var grade = root.querySelector('.select-vip .grade');
          return grade ? Number(grade.getAttribute('data-price')) || 0 : 0;
        },
        getVipContext: function () {
          var grade = root.querySelector('.select-vip .grade');
          if (!grade) return {};
          return {
            group_id: grade.getAttribute('data-id'),
            long: grade.getAttribute('data-long'),
            price: grade.getAttribute('data-price')
          };
        },
        onChange: function () {
          if (window.MacUpgradeCheckout && typeof MacUpgradeCheckout.sync === 'function') {
            MacUpgradeCheckout.sync(root);
          }
        }
      });
      root.addEventListener('click', function (ev) {
        if (ev.target && ev.target.closest && ev.target.closest('.shengji-item')) {
          setTimeout(function () {
            var p = getByMount('buy-upgrade');
            if (p) p.scheduleRefresh();
          }, 0);
        }
      });
    }
  }

  function initModalPanel() {
    var el = document.getElementById('mac-user-coupon-panel-modal');
    if (!el) return null;
    return mount(el, {
      scene: 'recharge',
      getPrice: function () {
        var inp = document.getElementById('rcPriceInput');
        return inp ? Number(inp.value) || 0 : 0;
      },
      onChange: function (id, preview) {
        var btn = document.getElementById('rcSubmitBtn');
        if (!btn || !preview) return;
        if (id && preview.pay >= 0) {
          btn.textContent = getLang('string_confirm_pay', '确认充值', 'Confirm') + ' ¥' + preview.pay.toFixed(2);
        } else {
          btn.textContent = getLang('string_confirm_pay', '确认充值', 'Confirm recharge');
        }
      }
    });
  }

  window.MacUserCoupon = {
    mount: mount,
    getByMount: getByMount,
    initBuyPage: initBuyPage,
    initModalPanel: initModalPanel,
    handleOrderError: handleOrderError,
    calcDiscount: calcDiscount,
    apiBase: apiBase
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBuyPage);
  } else {
    initBuyPage();
  }
})(window, document);
