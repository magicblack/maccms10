/**
 * 秒杀：升级 Tab 入口条 + 弹窗列表（支持多场活动）
 * API: seckill/get_list → info.list；seckill/buy → 金币购买
 */
(function (window, document) {
  'use strict';

  var OVERLAY_ID = 'mac-seckill-overlay';

  function apiBase() {
    if (window.MacUserCoupon && MacUserCoupon.apiBase) return MacUserCoupon.apiBase();
    if (window.maccms && maccms.path != null) {
      return String(maccms.path).replace(/\/+$/, '') + '/api.php';
    }
    return '/api.php';
  }

  function csrfHeaders() {
    var h = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
    if (window.maccms && maccms.csrfToken) h['X-CSRF-Token'] = maccms.csrfToken;
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

  /** 成功：轻提示自动关，不用后端「秒杀成功」+「确定」 */
  function toastOk(msg) {
    var m = String(msg || '');
    if (window.MemberDialog && window.MemberDialog.status) {
      window.MemberDialog.status({ type: 'success', message: m, hideOkButton: true, autoCloseMs: 2200 });
      return;
    }
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
      MAC.Pop.StatusSheet({
        type: 'success',
        message: m,
        hideOkButton: true,
        autoCloseMs: 2200
      });
    } else if (typeof MAC !== 'undefined' && MAC.alert) {
      MAC.alert(m);
    }
  }

  function toastErr(msg) {
    var m = String(msg || '');
    if (window.MemberDialog && window.MemberDialog.notify) {
      window.MemberDialog.notify(m);
      return;
    }
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
      MAC.Pop.StatusSheet({ type: 'error', message: m, hideOkButton: true, autoCloseMs: 2400 });
    } else if (typeof MAC !== 'undefined' && MAC.alert) {
      MAC.alert(m);
    }
  }

  /** 秒杀层须高于充值等动态抬 z 的浮层 */
  function bumpOverlayZ(ov) {
    if (!ov) return;
    var floor = 10000000015;
    var CAP = 2147483646;
    var z = floor;
    try {
      if (typeof MAC !== 'undefined' && typeof MAC.nextOverlayZBase === 'function') {
        z = MAC.nextOverlayZBase({ exclude: '#' + ov.id + ', .mac-seckill-overlay' });
      } else {
        var ro = document.getElementById('recharge-overlay');
        if (ro && ro.classList.contains('show')) {
          var n = parseInt(window.getComputedStyle(ro).zIndex, 10);
          if (!isNaN(n) && n >= z) z = n + 10;
        }
      }
      if (typeof MAC !== 'undefined' && typeof MAC.clampOverlayZAboveRecharge === 'function') {
        z = MAC.clampOverlayZAboveRecharge(z);
      }
      if (z < floor) z = floor;
      if (z >= CAP) {
        z = CAP;
        var el = document.getElementById('recharge-overlay');
        if (el && el.classList.contains('show')) {
          var rz = parseInt(window.getComputedStyle(el).zIndex, 10);
          if (!isNaN(rz) && rz >= CAP - 1) {
            el.style.setProperty('z-index', String(CAP - 2), 'important');
          }
        }
      }
    } catch (e) {}
    if (typeof MAC !== 'undefined' && typeof MAC.setZIndexImportant === 'function') {
      MAC.setZIndexImportant(ov, z);
    } else {
      ov.style.setProperty('z-index', String(z), 'important');
    }
  }

  function successCopy(json) {
    var pts = json && json.info ? Number(json.info.seckill_price_points) || 0 : 0;
    if (pts > 0) {
      return getLang(
        'string_seckill_ok_pts',
        '抢购成功，已扣 ' + pts + ' 金币，会员权益已到账',
        'Success! ' + pts + ' points used. VIP unlocked.'
      );
    }
    return getLang(
      'string_seckill_ok',
      '抢购成功，会员权益已到账',
      'Success! VIP unlocked.'
    );
  }

  function goLogin() {
    var root = document.getElementById('mac-user-buy-root');
    var loginUrl = (root && root.getAttribute('data-url-login')) || '/index.php/user/login';
    var back = window.location.href || '/';
    if (typeof MAC !== 'undefined' && MAC.User && typeof MAC.User.openLoginOrNavigate === 'function') {
      MAC.User.openLoginOrNavigate(loginUrl, back);
    } else {
      window.location.href = loginUrl + (loginUrl.indexOf('?') >= 0 ? '&' : '?') + 'url=' + encodeURIComponent(back);
    }
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  /** 剩余毫秒 → MM:SS.ms / HH:MM:SS.ms（毫秒两位，即百分之一秒） */
  function formatRemainMs(ms) {
    ms = Math.max(0, Math.floor(Number(ms) || 0));
    var totalSec = Math.floor(ms / 1000);
    var centi = Math.floor((ms % 1000) / 10);
    var h = Math.floor(totalSec / 3600);
    var m = Math.floor((totalSec % 3600) / 60);
    var s = totalSec % 60;
    var base = h > 0 ? pad2(h) + ':' + pad2(m) + ':' + pad2(s) : pad2(m) + ':' + pad2(s);
    return base + '.' + pad2(centi);
  }

  function stampEndsAt(list) {
    var now = Date.now();
    for (var i = 0; i < list.length; i++) {
      var cd = Math.max(0, parseInt(list[i].countdown, 10) || 0);
      list[i]._endsAt = now + cd * 1000;
    }
    return list;
  }

  function countBuyable(list) {
    var n = 0;
    for (var i = 0; i < list.length; i++) {
      if ((parseInt(list[i].stock_remaining, 10) || 0) > 0) n++;
    }
    return n;
  }

  function pickHighlight(list) {
    var best = null;
    for (var i = 0; i < list.length; i++) {
      var row = list[i];
      if ((parseInt(row.stock_remaining, 10) || 0) < 1) continue;
      if (!best) {
        best = row;
        continue;
      }
      var a = Number(row.seckill_price_points) || 0;
      var b = Number(best.seckill_price_points) || 0;
      if (a < b) best = row;
    }
    return best || list[0] || null;
  }

  function Seckill(el) {
    this.el = el;
    this.open = false;
    this.list = [];
    this._tick = null;
    this._overlay = null;
    this._onKey = null;
  }

  Seckill.prototype.mount = function () {
    var self = this;
    // 先保持 hidden，等 get_list 确认有活动再露出
    this.el.setAttribute('hidden', '');
    this.el.innerHTML =
      '<button type="button" class="mac-seckill-bar" data-seckill-open aria-haspopup="dialog">' +
      '<span class="mac-seckill-bar__copy">' +
      '<span class="mac-seckill-bar__title-row">' +
      '<span class="mac-seckill-bar__title">' +
      esc(getLang('string_seckill_title', '限时秒杀 · 金币抢 VIP', 'Flash sale · VIP with coins')) +
      '</span>' +
      '<span class="mac-seckill-bar__cd mac-seckill-countdown" data-seckill-bar-cd data-ends-at="0" hidden>00:00.00</span>' +
      '</span>' +
      '<span class="mac-seckill-bar__sub" data-seckill-sub>' +
      esc(getLang('string_loading', '加载中…', 'Loading…')) +
      '</span></span>' +
      '<span class="mac-seckill-bar__action">' +
      esc(getLang('string_seckill_view', '查看', 'View')) +
      '<span class="mac-seckill-bar__badge" data-seckill-badge hidden>0</span>' +
      '</span></button>';

    this.ensureOverlay();

    this.el.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!t || !t.closest) return;
      if (t.closest('[data-seckill-open]')) {
        self.showModal();
      }
    });
  };

  /** 无活动 / 未登录不可拉列表时整段入口隐藏 */
  Seckill.prototype.setVisible = function (show) {
    if (!this.el) return;
    if (show) {
      this.el.removeAttribute('hidden');
      return;
    }
    this.el.setAttribute('hidden', '');
    this.stopTick();
    if (this.open) this.hideModal();
  };

  Seckill.prototype.ensureOverlay = function () {
    var self = this;
    var ov = document.getElementById(OVERLAY_ID);
    if (!ov) {
      ov = document.createElement('div');
      ov.id = OVERLAY_ID;
      ov.className = 'mac-seckill-overlay';
      ov.setAttribute('hidden', '');
      ov.innerHTML =
        '<div class="mac-seckill-modal" role="dialog" aria-modal="true" aria-labelledby="mac-seckill-modal-title">' +
        '<div class="mac-seckill-modal__hd">' +
        '<div>' +
        '<h3 class="mac-seckill-modal__title" id="mac-seckill-modal-title">' +
        esc(getLang('string_seckill_title', '限时秒杀 · 金币抢 VIP', 'Flash sale · VIP with coins')) +
        '</h3>' +
        '<p class="mac-seckill-modal__sub" data-seckill-modal-sub></p>' +
        '</div>' +
        '<button type="button" class="mac-seckill-modal__close" data-seckill-close aria-label="Close">×</button>' +
        '</div>' +
        '<div class="mac-seckill-modal__bd" data-seckill-body>' +
        '<div class="mac-seckill-loading">' +
        esc(getLang('string_loading', '加载中…', 'Loading…')) +
        '</div></div></div>';
      document.body.appendChild(ov);
    }
    this._overlay = ov;

    if (!ov.getAttribute('data-bound')) {
      ov.setAttribute('data-bound', '1');
      ov.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) return;
        if (t === ov || t.closest('[data-seckill-close]')) {
          self.hideModal();
          return;
        }
        var buy = t.closest('[data-seckill-buy]');
        if (buy && !buy.disabled) {
          self.buy(Number(buy.getAttribute('data-seckill-buy')) || 0, buy);
        }
      });
    }
  };

  Seckill.prototype.updateBarCd = function (hi) {
    var barCd = this.el.querySelector('[data-seckill-bar-cd]');
    if (!barCd) return;
    var endsAt = hi && hi._endsAt ? Number(hi._endsAt) : 0;
    var left = endsAt > 0 ? endsAt - Date.now() : 0;
    if (!hi || left < 1) {
      barCd.setAttribute('hidden', '');
      barCd.setAttribute('data-ends-at', '0');
      barCd.textContent = '00:00.00';
      return;
    }
    barCd.removeAttribute('hidden');
    barCd.setAttribute('data-ends-at', String(endsAt));
    barCd.textContent = formatRemainMs(left);
  };

  Seckill.prototype.updateBar = function (list) {
    var sub = this.el.querySelector('[data-seckill-sub]');
    var badge = this.el.querySelector('[data-seckill-badge]');
    var buyable = countBuyable(list);
    var hi = pickHighlight(list);
    if (!list.length) {
      if (sub) sub.textContent = getLang('string_seckill_none', '暂无秒杀活动', 'No flash sales');
      if (badge) badge.setAttribute('hidden', '');
      this.updateBarCd(null);
      return;
    }
    if (badge) {
      badge.textContent = String(list.length);
      badge.removeAttribute('hidden');
    }
    this.updateBarCd(hi);
    if (!sub) return;
    if (hi) {
      sub.textContent =
        (hi.seckill_name || '') +
        ' · ' +
        getLang('string_seckill_from', '低至', 'From') +
        ' ' +
        (hi.seckill_price_points || '') +
        getLang('string_jf', '金币', 'coins') +
        (buyable
          ? ' · ' + getLang('string_seckill_buyable', buyable + ' 场可抢', buyable + ' available')
          : ' · ' + getLang('string_seckill_all_sold', '已抢完', 'Sold out'));
    } else {
      sub.textContent = getLang('string_seckill_count', list.length + ' 场活动', list.length + ' events');
    }
  };

  Seckill.prototype.showModal = function () {
    var self = this;
    this.ensureOverlay();
    this.open = true;
    if (this._overlay.parentNode) {
      document.body.appendChild(this._overlay);
    }
    bumpOverlayZ(this._overlay);
    this._overlay.removeAttribute('hidden');
    // 下一帧再加 class，保证过渡
    requestAnimationFrame(function () {
      bumpOverlayZ(self._overlay);
      self._overlay.classList.add('is-open');
    });
    document.documentElement.style.overflow = 'hidden';
    this._onKey = function (ev) {
      if (ev.key === 'Escape') self.hideModal();
    };
    document.addEventListener('keydown', this._onKey);
    this.load(true);
  };

  Seckill.prototype.hideModal = function () {
    if (!this._overlay) return;
    this.open = false;
    this._overlay.classList.remove('is-open');
    document.documentElement.style.overflow = '';
    if (this._onKey) {
      document.removeEventListener('keydown', this._onKey);
      this._onKey = null;
    }
    // 入口条倒计时继续走，不 stopTick
    var ov = this._overlay;
    setTimeout(function () {
      if (!ov.classList.contains('is-open')) ov.setAttribute('hidden', '');
    }, 240);
  };

  Seckill.prototype.load = function (forModal) {
    var self = this;
    var body = this._overlay && this._overlay.querySelector('[data-seckill-body]');
    if (forModal && body) {
      body.innerHTML =
        '<div class="mac-seckill-loading">' + esc(getLang('string_loading', '加载中…', 'Loading…')) + '</div>';
    }
    fetch(apiBase() + '/seckill/get_list', {
      method: 'GET',
      credentials: 'same-origin',
      headers: csrfHeaders()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || Number(json.code) !== 1) {
          if (json && Number(json.code) === 1401) {
            if (forModal && body) {
              body.innerHTML =
                '<div class="mac-seckill-empty">' +
                esc(getLang('string_pay_login_first', '请先登录', 'Please login')) +
                '</div>';
            }
            self._pendingReload = false;
            goLogin();
            return;
          }
          if (forModal && body) {
            body.innerHTML =
              '<div class="mac-seckill-empty">' +
              esc((json && json.msg) || getLang('string_load_err', '加载失败', 'Failed')) +
              '</div>';
          }
          self._pendingReload = false;
          self.setVisible(false);
          return;
        }
        var list = stampEndsAt((json.info && json.info.list) || []);
        self.list = list;
        self._pendingReload = false;
        if (!list.length) {
          self.setVisible(false);
          if (forModal && body) {
            body.innerHTML =
              '<div class="mac-seckill-empty">' +
              esc(getLang('string_seckill_none', '暂无秒杀活动', 'No flash sales')) +
              '</div>';
          }
          return;
        }
        self.setVisible(true);
        self.updateBar(list);
        self.startTick();
        if (forModal) self.render(list);
      })
      .catch(function () {
        self._pendingReload = false;
        if (forModal && body) {
          body.innerHTML =
            '<div class="mac-seckill-empty">' +
            esc(getLang('string_load_err', '网络错误', 'Network error')) +
            '</div>';
        }
      });
  };

  Seckill.prototype.render = function (list) {
    var body = this._overlay && this._overlay.querySelector('[data-seckill-body]');
    var sub = this._overlay && this._overlay.querySelector('[data-seckill-modal-sub]');
    if (!body) return;
    if (sub) {
      sub.textContent = list.length
        ? getLang('string_seckill_modal_sub', '共 ' + list.length + ' 场 · 用金币直接抢', list.length + ' events · pay with coins')
        : '';
    }
    if (!list.length) {
      body.innerHTML =
        '<div class="mac-seckill-empty">' +
        esc(getLang('string_seckill_none', '暂无秒杀活动', 'No flash sales')) +
        '</div>';
      return;
    }
    var html = ['<div class="mac-seckill-list" role="list">'];
    for (var i = 0; i < list.length; i++) {
      var row = list[i];
      var remain = Math.max(0, parseInt(row.stock_remaining, 10) || 0);
      var total = parseInt(row.seckill_total, 10) || 0;
      var soldOut = remain < 1;
      var boughtByMe = Number(row.already_bought) === 1;
      var cd = parseInt(row.countdown, 10) || 0;
      var pct = total > 0 ? Math.max(0, Math.min(100, Math.round((remain / total) * 100))) : 0;
      html.push(
        '<article class="mac-seckill-card' +
          (boughtByMe ? ' is-bought' : soldOut ? ' is-soldout' : '') +
          '" data-seckill-id="' +
          esc(String(row.seckill_id)) +
          '" role="listitem">'
      );
      html.push('<div class="mac-seckill-card__top">');
      html.push('<div class="mac-seckill-card__name">' + esc(row.seckill_name || '') + '</div>');
      if (boughtByMe) {
        html.push(
          '<span class="mac-seckill-card__tag mac-seckill-card__tag--bought">' +
            esc(getLang('string_seckill_bought', '已购买', 'Purchased')) +
            '</span>'
        );
      } else if (soldOut) {
        html.push(
          '<span class="mac-seckill-card__tag mac-seckill-card__tag--sold">' +
            esc(getLang('string_seckill_soldout', '已抢完', 'Sold out')) +
            '</span>'
        );
      } else {
        html.push(
          '<span class="mac-seckill-card__tag">' +
            esc(getLang('string_seckill_hot', '抢购中', 'Live')) +
            '</span>'
        );
      }
      html.push('</div>');
      html.push(
        '<div class="mac-seckill-card__price">' +
          esc(getLang('string_seckill_origin', '原价', 'Was')) +
          ' <s>' +
          esc(String(row.seckill_origin_points)) +
          '</s> → <strong>' +
          esc(String(row.seckill_price_points)) +
          '</strong> ' +
          esc(getLang('string_jf', '金币', 'coins')) +
          '</div>'
      );
      html.push('<div class="mac-seckill-card__meta">');
      html.push(
        '<div class="mac-seckill-card__stock">' +
          esc(getLang('string_seckill_stock', '剩余', 'Left')) +
          ' ' +
          remain +
          ' / ' +
          total +
          '<div class="mac-seckill-card__stock-track"><div class="mac-seckill-card__stock-fill" style="width:' +
          pct +
          '%"></div></div></div>'
      );
      var endsAt = row._endsAt || Date.now() + cd * 1000;
      html.push(
        '<span>' +
          esc(getLang('string_seckill_ends', '距结束', 'Ends in')) +
          ' <span class="mac-seckill-countdown" data-ends-at="' +
          endsAt +
          '">' +
          formatRemainMs(endsAt - Date.now()) +
          '</span></span>'
      );
      html.push('</div>');
      if (boughtByMe) {
        html.push(
          '<button type="button" class="mac-seckill-card__btn" disabled>' +
            esc(getLang('string_seckill_bought', '已购买', 'Purchased')) +
            '</button>'
        );
      } else if (soldOut) {
        html.push(
          '<button type="button" class="mac-seckill-card__btn" disabled>' +
            esc(getLang('string_seckill_soldout', '已抢完', 'Sold out')) +
            '</button>'
        );
      } else {
        html.push(
          '<button type="button" class="mac-seckill-card__btn" data-seckill-buy="' +
            esc(String(row.seckill_id)) +
            '">' +
            esc(getLang('string_seckill_buy', '立即抢', 'Buy now')) +
            '</button>'
        );
      }
      html.push('</article>');
    }
    html.push('</div>');
    body.innerHTML = html.join('');
  };

  Seckill.prototype.startTick = function () {
    var self = this;
    this.stopTick();
    // 约 50ms 刷新一次，入口条/弹窗倒计时可显示毫秒
    this._tick = setInterval(function () {
      var now = Date.now();
      var needReload = false;
      var i;
      for (i = 0; i < self.list.length; i++) {
        var ends = Number(self.list[i]._endsAt) || 0;
        var left = Math.max(0, ends - now);
        self.list[i].countdown = Math.ceil(left / 1000);
        if (left <= 0) needReload = true;
      }
      self.updateBarCd(pickHighlight(self.list));
      if (self.open && self._overlay) {
        var nodes = self._overlay.querySelectorAll('.mac-seckill-countdown[data-ends-at]');
        Array.prototype.forEach.call(nodes, function (n) {
          var endsAt = Number(n.getAttribute('data-ends-at')) || 0;
          var leftMs = Math.max(0, endsAt - now);
          n.textContent = formatRemainMs(leftMs);
          if (leftMs <= 0) needReload = true;
        });
      }
      if (needReload && !self._pendingReload) {
        self._pendingReload = true;
        self.load(!!self.open);
      }
    }, 50);
  };

  Seckill.prototype.stopTick = function () {
    if (this._tick) {
      clearInterval(this._tick);
      this._tick = null;
    }
  };

  /** 已购买提示文案：与后端 lang('seckill/per_user_limit') 语义一致，前端固定拼出以保证措辞明确 */
  function alreadyBoughtCopy() {
    return getLang(
      'string_seckill_already_bought',
      '您已参与过本次秒杀，每人限购1次',
      'You have already joined this flash sale. Limit 1 per person.'
    );
  }

  Seckill.prototype.buy = function (id, btn) {
    var self = this;
    if (id < 1) return;
    for (var i = 0; i < this.list.length; i++) {
      if (Number(this.list[i].seckill_id) === id && Number(this.list[i].already_bought) === 1) {
        toastErr(alreadyBoughtCopy());
        return;
      }
    }
    if (btn) {
      btn.disabled = true;
      btn.textContent = getLang('string_pay_ing', '处理中...', 'Processing...');
    }
    var body = new URLSearchParams();
    body.set('seckill_id', String(id));
    fetch(apiBase() + '/seckill/buy', {
      method: 'POST',
      credentials: 'same-origin',
      headers: Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, csrfHeaders()),
      body: body.toString()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || Number(json.code) !== 1) {
          if (json && Number(json.code) === 1401) {
            goLogin();
            return;
          }
          if (json && Number(json.code) === 1006) {
            for (var i = 0; i < self.list.length; i++) {
              if (Number(self.list[i].seckill_id) === id) {
                self.list[i].already_bought = 1;
                break;
              }
            }
            toastErr(alreadyBoughtCopy());
          } else {
            toastErr((json && json.msg) || getLang('string_load_err', '抢购失败', 'Failed'));
          }
          self.load(true);
          return;
        }
        // 成功文案由前端自拟；不直接展示后端 lang('seckill/buy_ok')「秒杀成功」
        for (var j = 0; j < self.list.length; j++) {
          if (Number(self.list[j].seckill_id) === id) {
            self.list[j].already_bought = 1;
            break;
          }
        }
        toastOk(successCopy(json));
        if (self.hideModal) self.hideModal();
        var root = document.getElementById('mac-user-buy-root');
        if (root && typeof window.macReloadUpgradeData === 'function') {
          window.macReloadUpgradeData(root);
        } else {
          setTimeout(function () {
            location.reload();
          }, 800);
          return;
        }
        self.load(false);
      })
      .catch(function () {
        toastErr(getLang('string_load_err', '网络错误', 'Network error'));
      })
      .then(function () {
        if (btn && self.open) {
          // load 会重绘；若按钮仍在则恢复
          btn.disabled = false;
          btn.textContent = getLang('string_seckill_buy', '立即抢', 'Buy now');
        }
      });
  };

  function init() {
    var el = document.getElementById('mac-user-seckill');
    if (!el) return;
    var sk = new Seckill(el);
    sk.mount();
    // 仅当有进行中的秒杀活动时才显示入口条（未登录 / 无活动 / 失败 → 隐藏）
    fetch(apiBase() + '/seckill/get_list', {
      method: 'GET',
      credentials: 'same-origin',
      headers: csrfHeaders()
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        var list = (json && json.info && json.info.list) || [];
        if (json && Number(json.code) === 1 && list.length) {
          sk.list = stampEndsAt(list);
          sk.setVisible(true);
          sk.updateBar(sk.list);
          sk.startTick();
          return;
        }
        sk.setVisible(false);
      })
      .catch(function () {
        sk.setVisible(false);
      });

    window.MacUserSeckill = window.MacUserSeckill || {};
    window.MacUserSeckill._instance = sk;
  }

  window.MacUserSeckill = {
    init: init,
    open: function () {
      if (window.MacUserSeckill._instance) window.MacUserSeckill._instance.showModal();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
