/**
 * 金币商城：商品列表 / 兑换记录 JSON 切换 + 确认/成功弹窗 + 兑换 Ajax + 卡密兑换
 */
(function () {
  'use strict';

  function getLang(key, fallback) {
    try {
      if (typeof MAC !== 'undefined' && MAC.GetLang && key) {
        var v = MAC.GetLang(key);
        if (v) return v;
      }
    } catch (e) {}
    try {
      if (typeof jQuery !== 'undefined' && jQuery.i18n && jQuery.i18n.map && key && jQuery.i18n.map[key]) {
        return jQuery.i18n.map[key];
      }
    } catch (e2) {}
    return fallback || '';
  }

  function applyPlaceholders(root) {
    if (!root) return;
    var nodes = root.querySelectorAll('[data-lang-placeholder]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      var key = el.getAttribute('data-lang-placeholder');
      if (!key) continue;
      var text = getLang(key, el.getAttribute('placeholder') || '');
      if (text) el.setAttribute('placeholder', text);
    }
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** 仅允许站内相对路径或 http(s)，拒绝 javascript: / data: / //host 等 */
  function isSafeHref(url) {
    var s = String(url == null ? '' : url).replace(/^\s+|\s+$/g, '');
    if (!s) return false;
    if (s.charAt(0) === '/' && s.charAt(1) !== '/') return true;
    if (/^https?:\/\//i.test(s)) return true;
    return false;
  }

  function withCsrf(data) {
    var out = data || {};
    if (typeof window !== 'undefined' && window.maccms && maccms.csrfToken) {
      out.__token__ = maccms.csrfToken;
    }
    return out;
  }

  function mallGridColumns() {
    try {
      return window.matchMedia('(min-width: 900px)').matches ? 3 : 2;
    } catch (e) {
      return 3;
    }
  }

  /** 每页条数对齐宫格列数，避免 PC 三列末行缺一格 */
  function mallPageLimit(cols, base) {
    cols = cols || mallGridColumns();
    base = parseInt(base, 10) || 20;
    var rem = base % cols;
    if (!rem) return base;
    return base + (cols - rem);
  }

  var SHEET_AUTO_MS = 2200;

  function toast(msg) {
    var text = msg == null ? '' : String(msg);
    if (!text) return;
    if (typeof window.macToast === 'function') {
      window.macToast(text);
      return;
    }
    try {
      if (window.MacTheme && typeof MacTheme.toast === 'function') {
        MacTheme.toast(text);
        return;
      }
    } catch (e) {}
    // 兜底：非阻塞浮层，绝不使用原生 alert
    var el = document.getElementById('js-mac-mall-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'js-mac-mall-toast';
      el.setAttribute('role', 'status');
      el.style.cssText =
        'position:fixed;left:50%;bottom:24%;transform:translateX(-50%);z-index:10050;' +
        'max-width:min(90vw,320px);padding:10px 16px;border-radius:10px;' +
        'background:rgba(20,20,24,.92);color:#fff;font-size:14px;line-height:1.4;' +
        'text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.28);pointer-events:none;' +
        'opacity:0;transition:opacity .18s ease';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.opacity = '1';
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(function () {
      el.style.opacity = '0';
    }, 1800);
  }

  /** 与签到成功同款：底部 StatusSheet 成功态 */
  function sheetOk(msg, onClose) {
    var m =
      msg != null && String(msg).trim() !== ''
        ? String(msg)
        : getLang('mall_redeem_card_ok', '卡密兑换成功');
    if (typeof MAC !== 'undefined' && MAC.Pop && typeof MAC.Pop.StatusSheet === 'function') {
      MAC.Pop.StatusSheet({
        type: 'success',
        message: m,
        hideOkButton: true,
        autoCloseMs: SHEET_AUTO_MS,
        onClose: onClose,
      });
      return;
    }
    toast(m);
    if (typeof onClose === 'function') {
      setTimeout(onClose, SHEET_AUTO_MS);
    }
  }

  function toQuery(data) {
    if (typeof URLSearchParams !== 'undefined') {
      var params = new URLSearchParams();
      if (data) {
        Object.keys(data).forEach(function (key) {
          if (data[key] !== undefined && data[key] !== null) {
            params.set(key, String(data[key]));
          }
        });
      }
      return params.toString();
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
  }

  /** 不依赖 jQuery：GET JSON */
  function ajaxGet(url, data) {
    var q = toQuery(data || {});
    var full = url + (url.indexOf('?') >= 0 ? '&' : '?') + q;
    return fetch(full, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).then(function (res) {
      return res.json().catch(function () {
        return { code: 0, msg: getLang('string_load_err', '请求失败') };
      });
    });
  }

  /** 不依赖 jQuery：POST application/x-www-form-urlencoded */
  function ajaxPost(url, data) {
    var payload = withCsrf(data || {});
    var token =
      typeof window !== 'undefined' && window.maccms && maccms.csrfToken
        ? String(maccms.csrfToken)
        : '';
    var headers = {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    if (token) headers['X-CSRF-Token'] = token;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: toQuery(payload),
    }).then(function (res) {
      return res.json().catch(function () {
        return { code: 0, msg: getLang('string_load_err', '请求失败') };
      });
    });
  }

  var TOAST_KEY = 'mac_user_mall_toast';

  function stashToast(msg) {
    var text = String(msg || '');
    if (!text) return;
    try {
      sessionStorage.setItem(TOAST_KEY, text);
    } catch (e) {}
  }

  function flushStashedToast() {
    var text = '';
    try {
      text = sessionStorage.getItem(TOAST_KEY) || '';
      sessionStorage.removeItem(TOAST_KEY);
    } catch (e) {
      text = '';
    }
    if (!text) return;
    function show() {
      toast(text);
    }
    // 等 MAC / 语言包就绪后再提示
    setTimeout(show, 200);
    setTimeout(show, 800);
  }

  function formatTpl(tpl, map) {
    var out = String(tpl || '');
    Object.keys(map || {}).forEach(function (k) {
      out = out.split('{' + k + '}').join(String(map[k]));
    });
    return out;
  }

  function typeLangKey(type) {
    if (type === 'vip') return 'mall_type_vip';
    if (type === 'card') return 'mall_type_card';
    if (type === 'download_quota') return 'mall_type_download_quota';
    return '';
  }

  function typeLabel(type) {
    var fallbacks = {
      vip: 'VIP天数',
      card: '充值卡密',
      download_quota: '下载额度'
    };
    var key = typeLangKey(type);
    if (key) return getLang(key, fallbacks[type] || type);
    return fallbacks[type] || type || '';
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function formatTime(ts) {
    var t = parseInt(ts, 10) || 0;
    if (!t) return '';
    var d = new Date(t * 1000);
    if (isNaN(d.getTime())) return '';
    return (
      d.getFullYear() +
      '-' +
      pad2(d.getMonth() + 1) +
      '-' +
      pad2(d.getDate()) +
      ' ' +
      pad2(d.getHours()) +
      ':' +
      pad2(d.getMinutes())
    );
  }

  function orderStatusMeta(status) {
    var s = parseInt(status, 10);
    if (s === 1) {
      return { cls: 'is-ok', text: getLang('mall_order_success', '成功') };
    }
    if (s === 2) {
      return { cls: 'is-fail', text: getLang('mall_order_fail', '失败') };
    }
    return { cls: 'is-pending', text: getLang('mall_order_pending', '处理中') };
  }

  function copyText(text) {
    var t = String(text || '');
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(
        function () {
          toast(getLang('mall_copy_ok', '已复制'));
        },
        function () {
          fallbackCopy(t);
        }
      );
      return;
    }
    fallbackCopy(t);
  }

  function fallbackCopy(t) {
    try {
      var ta = document.createElement('textarea');
      ta.value = t;
      ta.setAttribute('readonly', 'readonly');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      toast(getLang('mall_copy_ok', '已复制'));
    } catch (e) {
      toast(getLang('mall_copy_fail', '复制失败'));
    }
  }

  function MacUserMall(root) {
    this.root = root;
    this.$ = function (sel) {
      return root.querySelector(sel);
    };
    this.exchangeUrl = root.getAttribute('data-exchange-url') || '';
    this.goodsUrl = root.getAttribute('data-goods-url') || '';
    this.ordersUrl = root.getAttribute('data-orders-url') || '';
    this.checkinUrl = root.getAttribute('data-checkin-url') || '';
    this.buyUrl = root.getAttribute('data-buy-url') || '';
    this.userPoints = parseInt(root.getAttribute('data-user-points'), 10) || 0;
    this.currentType = root.getAttribute('data-type') || '';
    this.currentPage = parseInt(root.getAttribute('data-page'), 10) || 1;
    this.goodsPagecount = parseInt(root.getAttribute('data-pagecount'), 10) || 1;
    this.goodsBaseLimit = parseInt(root.getAttribute('data-limit'), 10) || 20;
    this.goodsLimit = mallPageLimit(mallGridColumns(), this.goodsBaseLimit);
    this.ordersPage = 1;
    this.ordersPagecount = 1;
    this.loadingGoods = false;
    this.loadingOrders = false;
    this.exchanging = false;
    this.redeeming = false;
    this.pendingGoods = null;
    this.bind();
  }

  MacUserMall.prototype.bind = function () {
    var self = this;
    var grid = this.$('[data-um-grid]');
    if (grid) {
      grid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-um-exchange]');
        if (!btn || btn.disabled || btn.classList.contains('is-disabled')) return;
        self.openConfirm({
          id: parseInt(btn.getAttribute('data-id'), 10) || 0,
          name: btn.getAttribute('data-name') || '',
          type: btn.getAttribute('data-type') || '',
          points: parseInt(btn.getAttribute('data-points'), 10) || 0,
          stock: parseInt(btn.getAttribute('data-stock'), 10) || 0,
        });
      });
    }

    this.bindModal('confirm');
    this.bindModal('success');
    this.bindModal('orders');
    this.bindModal('card-redeem');
    applyPlaceholders(this.root);

    var selfLimit = this;
    window.addEventListener('resize', function () {
      clearTimeout(selfLimit._limitTimer);
      selfLimit._limitTimer = setTimeout(function () {
        var nextLimit = mallPageLimit(mallGridColumns(), selfLimit.goodsBaseLimit);
        if (nextLimit === selfLimit.goodsLimit) return;
        selfLimit.goodsLimit = nextLimit;
        selfLimit.loadGoods(selfLimit.currentType, selfLimit.currentPage);
      }, 150);
    });

    var tabs = this.$('[data-um-tabs]');
    if (tabs) {
      tabs.addEventListener('click', function (e) {
        var tab = e.target.closest('[data-um-tab]');
        if (!tab || !tabs.contains(tab)) return;
        e.preventDefault();
        var type = tab.getAttribute('data-um-tab') || '';
        if (type === self.currentType && !self.loadingGoods) return;
        self.setActiveTab(type);
        self.loadGoods(type, 1);
      });
    }

    var goodsPager = this.$('[data-um-goods-pager]');
    if (goodsPager) {
      goodsPager.addEventListener('click', function (e) {
        var link = e.target.closest('[data-um-goods-page]');
        if (!link || link.classList.contains('btns-disad')) return;
        e.preventDefault();
        var page = parseInt(link.getAttribute('data-um-goods-page'), 10) || 1;
        if (page === self.currentPage) return;
        self.loadGoods(self.currentType, page);
      });
    }

    var confirmBtn = this.$('[data-um-confirm-submit]');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        self.submitExchange();
      });
    }

    var successDone = this.$('[data-um-success-done]');
    if (successDone) {
      successDone.addEventListener('click', function () {
        self.closeModal('success');
        self.loadGoods(self.currentType, self.currentPage);
      });
    }

    var openRedeem = this.$('[data-um-open-card-redeem]');
    if (openRedeem) {
      openRedeem.addEventListener('click', function () {
        self.openCardRedeem();
      });
    }

    var redeemForm = this.$('[data-um-card-redeem-form]');
    if (redeemForm) {
      redeemForm.addEventListener('submit', function (e) {
        e.preventDefault();
        self.submitCardRedeem();
      });
    }

    var ordersPrev = this.$('[data-um-orders-prev]');
    if (ordersPrev) {
      ordersPrev.addEventListener('click', function () {
        if (self.ordersPage <= 1) return;
        self.loadOrders(self.ordersPage - 1);
      });
    }
    var ordersNext = this.$('[data-um-orders-next]');
    if (ordersNext) {
      ordersNext.addEventListener('click', function () {
        if (self.ordersPage >= self.ordersPagecount) return;
        self.loadOrders(self.ordersPage + 1);
      });
    }

    // 顶栏兑换记录在 data-mac-user-mall 外，用 document 委托
    document.addEventListener('click', function (e) {
      var openOrders = e.target.closest('[data-um-open-orders]');
      if (!openOrders) return;
      e.preventDefault();
      self.openOrders();
    });

    this.root.addEventListener('click', function (e) {
      var copyBtn = e.target.closest('[data-um-copy]');
      if (copyBtn) {
        e.preventDefault();
        copyText(copyBtn.getAttribute('data-um-copy') || '');
        return;
      }
      var copyAllBtn = e.target.closest('[data-um-copy-card]');
      if (copyAllBtn) {
        e.preventDefault();
        self.copyCardAll(
          copyAllBtn.getAttribute('data-card-no') || '',
          copyAllBtn.getAttribute('data-card-pwd') || ''
        );
        return;
      }
      var fillBtn = e.target.closest('[data-um-fill-card]');
      if (fillBtn) {
        e.preventDefault();
        self.fillCardRedeem(
          fillBtn.getAttribute('data-card-no') || '',
          fillBtn.getAttribute('data-card-pwd') || ''
        );
      }
    });
  };

  MacUserMall.prototype.bindModal = function (name) {
    var self = this;
    var modal = this.$('[data-um-modal="' + name + '"]');
    if (!modal) return;
    var closers = modal.querySelectorAll('[data-um-close]');
    for (var i = 0; i < closers.length; i++) {
      closers[i].addEventListener('click', function () {
        self.closeModal(name);
      });
    }
  };

  MacUserMall.prototype.setBodyLock = function (on) {
    document.body.classList.toggle('um-modal-open', !!on);
  };

  MacUserMall.prototype.openModal = function (name) {
    var modal = this.$('[data-um-modal="' + name + '"]');
    if (!modal) return;
    // 互斥：打开新弹窗时关掉其它
    var opened = this.root.querySelectorAll('.um-modal.is-open');
    for (var i = 0; i < opened.length; i++) {
      if (opened[i] !== modal) opened[i].classList.remove('is-open');
    }
    modal.classList.add('is-open');
    this.setBodyLock(true);
  };

  MacUserMall.prototype.closeModal = function (name) {
    var modal = this.$('[data-um-modal="' + name + '"]');
    if (modal) modal.classList.remove('is-open');
    var anyOpen = this.root.querySelector('.um-modal.is-open');
    if (!anyOpen) this.setBodyLock(false);
  };

  MacUserMall.prototype.setActiveTab = function (type) {
    this.currentType = type || '';
    var tabs = this.root.querySelectorAll('[data-um-tab]');
    for (var i = 0; i < tabs.length; i++) {
      var t = tabs[i];
      var active = (t.getAttribute('data-um-tab') || '') === this.currentType;
      t.classList.toggle('active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    }
  };

  MacUserMall.prototype.syncUrl = function () {
    try {
      if (!window.history || !window.history.replaceState) return;
      var url = new URL(window.location.href);
      if (this.currentType) url.searchParams.set('type', this.currentType);
      else url.searchParams.delete('type');
      if (this.currentPage > 1) url.searchParams.set('page', String(this.currentPage));
      else url.searchParams.delete('page');
      window.history.replaceState(null, '', url.pathname + url.search + url.hash);
    } catch (e) {}
  };

  MacUserMall.prototype.loadGoods = function (type, page) {
    var self = this;
    if (!this.goodsUrl) {
      toast(getLang('string_load_err', '请求失败'));
      return;
    }
    if (this.loadingGoods) return;
    this.loadingGoods = true;
    var grid = this.$('[data-um-grid]');
    if (grid) grid.classList.add('is-loading');

    ajaxGet(this.goodsUrl, {
      type: type || '',
      page: page || 1,
      limit: this.goodsLimit || 20,
    })
      .then(function (res) {
        self.loadingGoods = false;
        if (grid) grid.classList.remove('is-loading');
        if (!res || parseInt(res.code, 10) !== 1) {
          toast((res && res.msg) || getLang('string_load_err', '请求失败'));
          return;
        }
        self.currentType = type || '';
        self.currentPage = parseInt(res.page, 10) || 1;
        self.goodsPagecount = parseInt(res.pagecount, 10) || 1;
        self.renderGoods(res.list || []);
        self.renderGoodsPager();
        self.syncUrl();
      })
      .catch(function () {
        self.loadingGoods = false;
        if (grid) grid.classList.remove('is-loading');
        toast(getLang('string_load_err', '请求失败'));
      });
  };

  MacUserMall.prototype.renderGoods = function (list) {
    var grid = this.$('[data-um-grid]');
    var empty = this.$('[data-um-empty]');
    if (!grid) return;

    if (!list || !list.length) {
      grid.innerHTML = '';
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;

    var html = '';
    for (var i = 0; i < list.length; i++) {
      var vo = list[i] || {};
      var type = vo.mall_goods_type || '';
      var stock = parseInt(vo.mall_goods_stock, 10) || 0;
      var name = vo.mall_goods_name || '';
      var points = parseInt(vo.mall_goods_points, 10) || 0;
      var id = parseInt(vo.mall_goods_id, 10) || 0;
      var soldout = stock <= 0;
      html +=
        '<li class="user-mall__card user-mall__card--' +
        esc(type) +
        (soldout ? ' is-soldout' : '') +
        '">' +
        '<span class="user-mall__card-type">' +
        esc(typeLabel(type)) +
        '</span>' +
        '<div class="user-mall__card-name">' +
        esc(name) +
        '</div>' +
        '<div class="user-mall__card-meta"><div class="user-mall__card-points">' +
        '<span class="user-mall__card-points-num">' +
        esc(points) +
        '</span>' +
        '<span class="user-mall__card-points-unit">' +
        esc(getLang('mall_points_unit', '金币')) +
        '</span></div></div>';
      if (soldout) {
        html +=
          '<button type="button" class="user-mall__card-btn is-disabled" disabled>' +
          esc(getLang('mall_sold_out', '已售罄')) +
          '</button>';
      } else {
        html +=
          '<button type="button" class="user-mall__card-btn" data-um-exchange' +
          ' data-id="' +
          esc(id) +
          '" data-name="' +
          esc(name) +
          '" data-type="' +
          esc(type) +
          '" data-points="' +
          esc(points) +
          '" data-stock="' +
          esc(stock) +
          '">' +
          esc(getLang('mall_exchange', '兑换')) +
          '</button>';
      }
      html += '</li>';
    }
    grid.innerHTML = html;
  };

  MacUserMall.prototype.renderGoodsPager = function () {
    var wrap = this.$('[data-um-goods-pager]');
    if (!wrap) return;
    var total = this.goodsPagecount || 1;
    var cur = this.currentPage || 1;
    if (total <= 1) {
      wrap.innerHTML = '';
      return;
    }

    var start = Math.max(1, cur - 2);
    var end = Math.min(total, start + 4);
    start = Math.max(1, end - 4);

    var html = '<ul class="page text-center cleafix">';
    html +=
      '<li><a href="javascript:;" data-um-goods-page="1"' +
      (cur <= 1 ? ' class="btns-disad"' : '') +
      '>' +
      esc(getLang('string_home', '首页')) +
      '</a></li>';
    html +=
      '<li><a href="javascript:;" data-um-goods-page="' +
      Math.max(1, cur - 1) +
      '"' +
      (cur <= 1 ? ' class="btns-disad"' : '') +
      '>' +
      esc(getLang('string_syy', '上一页')) +
      '</a></li>';
    for (var n = start; n <= end; n++) {
      html +=
        '<li class="hidden-xs' +
        (n === cur ? ' active' : '') +
        '"><a href="javascript:;" data-um-goods-page="' +
        n +
        '">' +
        n +
        '</a></li>';
    }
    html +=
      '<li><a href="javascript:;" data-um-goods-page="' +
      Math.min(total, cur + 1) +
      '"' +
      (cur >= total ? ' class="btns-disad"' : '') +
      '>' +
      esc(getLang('string_xyy', '下一页')) +
      '</a></li>';
    html +=
      '<li><a href="javascript:;" data-um-goods-page="' +
      total +
      '"' +
      (cur >= total ? ' class="btns-disad"' : '') +
      '>' +
      esc(getLang('string_last_page', '尾页')) +
      '</a></li>';
    html += '</ul>';
    wrap.innerHTML = html;
  };

  MacUserMall.prototype.openOrders = function () {
    this.closeModal('success');
    this.openModal('orders');
    this.loadOrders(1);
  };

  MacUserMall.prototype.loadOrders = function (page) {
    var self = this;
    if (!this.ordersUrl) {
      toast(getLang('string_load_err', '请求失败'));
      return;
    }
    if (this.loadingOrders) return;
    this.loadingOrders = true;
    var loading = this.$('[data-um-orders-loading]');
    var listEl = this.$('[data-um-orders-list]');
    var empty = this.$('[data-um-orders-empty]');
    var pager = this.$('[data-um-orders-pager]');
    if (loading) loading.hidden = false;
    if (listEl) listEl.innerHTML = '';
    if (empty) empty.hidden = true;
    if (pager) pager.hidden = true;

    ajaxGet(this.ordersUrl, {
      page: page || 1,
      limit: 20,
    })
      .then(function (res) {
        self.loadingOrders = false;
        if (loading) loading.hidden = true;
        if (!res || parseInt(res.code, 10) !== 1) {
          toast((res && res.msg) || getLang('string_load_err', '请求失败'));
          if (empty) empty.hidden = false;
          return;
        }
        self.ordersPage = parseInt(res.page, 10) || 1;
        self.ordersPagecount = parseInt(res.pagecount, 10) || 1;
        self.renderOrders(res.list || []);
        self.renderOrdersPager();
      })
      .catch(function () {
        self.loadingOrders = false;
        if (loading) loading.hidden = true;
        if (empty) empty.hidden = false;
        toast(getLang('string_load_err', '请求失败'));
      });
  };

  MacUserMall.prototype.renderOrders = function (list) {
    var listEl = this.$('[data-um-orders-list]');
    var empty = this.$('[data-um-orders-empty]');
    if (!listEl) return;

    if (!list || !list.length) {
      listEl.innerHTML = '';
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;

    var html = '';
    for (var i = 0; i < list.length; i++) {
      var vo = list[i] || {};
      var type = vo.mall_goods_type || '';
      var status = orderStatusMeta(vo.mall_order_status);
      var delivery = vo.mall_order_delivery_arr || {};
      var points = parseInt(vo.mall_order_points, 10) || 0;

      html +=
        '<li class="um-orders-item">' +
        '<div class="um-orders-item__top">' +
        '<div class="um-orders-item__title">' +
        '<span class="um-orders-item__type">' +
        esc(typeLabel(type)) +
        '</span>' +
        '<span class="um-orders-item__name">' +
        esc(vo.mall_goods_name || '') +
        '</span>' +
        '</div>' +
        '<span class="um-orders-item__status ' +
        status.cls +
        '">' +
        esc(status.text) +
        '</span>' +
        '</div>' +
        '<div class="um-orders-item__meta">' +
        '<div class="um-orders-item__cost">-' +
        esc(points) +
        ' ' +
        esc(getLang('mall_points_unit', '金币')) +
        '</div>' +
        '<div class="um-orders-item__time">' +
        esc(formatTime(vo.mall_order_time)) +
        '</div>';

      if (parseInt(vo.mall_order_status, 10) === 1) {
        if (type === 'vip' && delivery.days) {
          html +=
            '<div>' +
            esc(getLang('mall_vip_days', 'VIP天数')) +
            '：' +
            esc(delivery.days) +
            '</div>';
          if (delivery.group_name) {
            html +=
              '<div>' +
              esc(getLang('mall_vip_group', '会员组')) +
              '：' +
              esc(delivery.group_name) +
              '</div>';
          }
        } else if (type === 'download_quota' && delivery.quota) {
          html +=
            '<div>' +
            esc(getLang('mall_download_quota', '下载额度')) +
            '：' +
            esc(delivery.quota) +
            '</div>';
        } else if (type === 'card' && (delivery.card_no || delivery.card_pwd)) {
          html +=
            '<div class="um-copy-row"><span>' +
            esc(getLang('mall_card_no', '卡号')) +
            '：' +
            esc(delivery.card_no || '') +
            '</span><button type="button" data-um-copy="' +
            esc(delivery.card_no || '') +
            '">' +
            esc(getLang('mall_copy', '复制')) +
            '</button></div>';
          html +=
            '<div class="um-copy-row"><span>' +
            esc(getLang('mall_card_pwd', '密码')) +
            '：' +
            esc(delivery.card_pwd || '') +
            '</span><button type="button" data-um-copy="' +
            esc(delivery.card_pwd || '') +
            '">' +
            esc(getLang('mall_copy', '复制')) +
            '</button></div>';
          html +=
            '<div class="um-card-actions">' +
            '<button type="button" class="um-card-actions__btn" data-um-copy-card data-card-no="' +
            esc(delivery.card_no || '') +
            '" data-card-pwd="' +
            esc(delivery.card_pwd || '') +
            '">' +
            esc(getLang('mall_copy_all', '一键复制')) +
            '</button>' +
            '<button type="button" class="um-card-actions__btn um-card-actions__btn--primary" data-um-fill-card data-card-no="' +
            esc(delivery.card_no || '') +
            '" data-card-pwd="' +
            esc(delivery.card_pwd || '') +
            '">' +
            esc(getLang('mall_fill_card', '一键填充')) +
            '</button>' +
            '</div>';
        }
      }

      if (vo.mall_order_remarks) {
        html +=
          '<div>' +
          esc(getLang('mall_remarks', '备注')) +
          '：' +
          esc(vo.mall_order_remarks) +
          '</div>';
      }

      html += '</div></li>';
    }
    listEl.innerHTML = html;
  };

  MacUserMall.prototype.renderOrdersPager = function () {
    var pager = this.$('[data-um-orders-pager]');
    var info = this.$('[data-um-orders-page-info]');
    var prev = this.$('[data-um-orders-prev]');
    var next = this.$('[data-um-orders-next]');
    var listEl = this.$('[data-um-orders-list]');
    if (!pager) return;

    var total = Math.max(1, this.ordersPagecount || 1);
    var cur = Math.max(1, this.ordersPage || 1);
    var hasItems = !!(listEl && listEl.children && listEl.children.length);
    if (!hasItems && total <= 1) {
      pager.hidden = true;
      return;
    }
    pager.hidden = false;
    if (info) info.textContent = cur + ' / ' + total;
    if (prev) {
      prev.disabled = cur <= 1;
      prev.setAttribute('aria-disabled', cur <= 1 ? 'true' : 'false');
    }
    if (next) {
      next.disabled = cur >= total;
      next.setAttribute('aria-disabled', cur >= total ? 'true' : 'false');
    }
  };

  MacUserMall.prototype.copyCardAll = function (cardNo, cardPwd) {
    var no = String(cardNo || '');
    var pwd = String(cardPwd || '');
    if (!no && !pwd) return;
    var text =
      getLang('mall_card_no', '卡号') +
      '：' +
      no +
      '\n' +
      getLang('mall_card_pwd', '密码') +
      '：' +
      pwd;
    copyText(text);
  };

  MacUserMall.prototype.fillCardRedeem = function (cardNo, cardPwd) {
    var no = String(cardNo || '');
    var pwd = String(cardPwd || '');
    if (!no && !pwd) return;
    this.closeModal('orders');
    this.closeModal('success');
    this.openCardRedeem({ cardNo: no, cardPwd: pwd });
    toast(getLang('mall_fill_card_ok', '已填入兑换表单'));
  };

  MacUserMall.prototype.openConfirm = function (goods) {
    if (!goods || !goods.id) return;
    this.pendingGoods = goods;
    var nameEl = this.$('[data-um-confirm-name]');
    var typeEl = this.$('[data-um-confirm-type]');
    var costEl = this.$('[data-um-confirm-cost]');
    var balEl = this.$('[data-um-confirm-balance]');
    var hintEl = this.$('[data-um-confirm-hint]');
    var submitBtn = this.$('[data-um-confirm-submit]');
    var after = this.userPoints - (goods.points || 0);
    if (nameEl) nameEl.textContent = goods.name || '';
    if (typeEl) typeEl.textContent = typeLabel(goods.type);
    if (costEl) costEl.textContent = String(goods.points || 0);
    if (balEl) {
      balEl.textContent = this.userPoints + ' → ' + (after < 0 ? 0 : after);
    }
    var notEnough = after < 0;
    if (hintEl) {
      if (notEnough) {
        var link = this.checkinUrl || this.buyUrl || '';
        var tip = getLang('mall_points_not_enough_hint', '金币不足，去获取金币');
        if (link && isSafeHref(link)) {
          hintEl.innerHTML =
            '<a href="' +
            esc(link) +
            '">' +
            esc(tip) +
            '</a>';
        } else {
          hintEl.textContent = tip;
        }
        hintEl.hidden = false;
      } else {
        hintEl.innerHTML = '';
        hintEl.hidden = true;
      }
    }
    if (submitBtn) {
      var block = notEnough || this.exchanging;
      submitBtn.disabled = false; // 保留可点，便于 toast；用 is-disabled 灰显
      submitBtn.setAttribute('aria-disabled', block ? 'true' : 'false');
      submitBtn.classList.toggle('is-disabled', block);
    }
    this.openModal('confirm');
  };

  MacUserMall.prototype.submitExchange = function () {
    var self = this;
    var goods = this.pendingGoods;
    if (!goods || !goods.id || this.exchanging) return;
    if (this.userPoints < goods.points) {
      toast(getLang('mall_points_not_enough', '金币不足'));
      return;
    }

    var submitBtn = this.$('[data-um-confirm-submit]');
    this.exchanging = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.add('is-disabled');
      submitBtn.setAttribute('aria-disabled', 'true');
    }

    ajaxPost(this.exchangeUrl, { goods_id: goods.id, quantity: 1 })
      .then(function (res) {
        self.exchanging = false;
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-disabled');
          submitBtn.setAttribute('aria-disabled', 'false');
        }
        if (!res || parseInt(res.code, 10) !== 1) {
          toast((res && res.msg) || getLang('mall_exchange_fail', '兑换失败'));
          return;
        }
        self.closeModal('confirm');
        self.userPoints = Math.max(0, self.userPoints - (goods.points || 0));
        var pointsEl = self.$('[data-um-points-value]');
        if (pointsEl) pointsEl.textContent = String(self.userPoints);
        toast((res && res.msg) || getLang('mall_exchange_ok', '兑换成功'));
        self.showSuccess(res.info || {}, goods);
      })
      .catch(function () {
        self.exchanging = false;
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-disabled');
          submitBtn.setAttribute('aria-disabled', 'false');
        }
        toast(getLang('string_load_err', '请求失败'));
      });
  };

  MacUserMall.prototype.setCardRedeemError = function (msg) {
    var err = this.$('[data-um-card-redeem-error]');
    if (!err) return;
    if (msg) {
      err.textContent = msg;
      err.hidden = false;
    } else {
      err.textContent = '';
      err.hidden = true;
    }
  };

  MacUserMall.prototype.openCardRedeem = function (prefill) {
    var form = this.$('[data-um-card-redeem-form]');
    var noEl = this.$('[data-um-card-no]');
    var pwdEl = this.$('[data-um-card-pwd]');
    var submitBtn = this.$('[data-um-card-redeem-submit]');
    if (form) form.reset();
    if (noEl) noEl.value = (prefill && prefill.cardNo) || '';
    if (pwdEl) pwdEl.value = (prefill && prefill.cardPwd) || '';
    this.setCardRedeemError('');
    if (submitBtn) submitBtn.disabled = false;
    applyPlaceholders(this.$('[data-um-modal="card-redeem"]'));
    this.openModal('card-redeem');
    if (noEl && typeof noEl.focus === 'function' && !(prefill && prefill.cardNo)) {
      setTimeout(function () {
        noEl.focus();
      }, 40);
    }
  };

  MacUserMall.prototype.submitCardRedeem = function () {
    var self = this;
    if (this.redeeming) return;
    if (!this.buyUrl) {
      toast(getLang('string_load_err', '请求失败'));
      return;
    }

    var noEl = this.$('[data-um-card-no]');
    var pwdEl = this.$('[data-um-card-pwd]');
    var submitBtn = this.$('[data-um-card-redeem-submit]');
    var cardNo = noEl ? String(noEl.value || '').replace(/^\s+|\s+$/g, '') : '';
    var cardPwd = pwdEl ? String(pwdEl.value || '').replace(/^\s+|\s+$/g, '') : '';

    if (!cardNo || !cardPwd) {
      this.setCardRedeemError(getLang('mall_redeem_card_required', '请填写卡号和密码'));
      if (noEl && !cardNo && typeof noEl.focus === 'function') noEl.focus();
      else if (pwdEl && typeof pwdEl.focus === 'function') pwdEl.focus();
      return;
    }

    this.setCardRedeemError('');
    this.redeeming = true;
    if (submitBtn) submitBtn.disabled = true;

    ajaxPost(this.buyUrl, {
      flag: 'card',
      card_no: cardNo,
      card_pwd: cardPwd,
    })
      .then(function (res) {
        self.redeeming = false;
        if (submitBtn) submitBtn.disabled = false;
        if (!res || parseInt(res.code, 10) !== 1) {
          self.setCardRedeemError(
            getLang(
              'mall_redeem_card_invalid',
              '充值卡信息有误，可能已经兑换'
            )
          );
          return;
        }
        self.closeModal('card-redeem');
        var okMsg =
          (res && res.msg) || getLang('mall_redeem_card_ok', '卡密兑换成功');
        sheetOk(okMsg, function () {
          window.location.reload();
        });
      })
      .catch(function () {
        self.redeeming = false;
        if (submitBtn) submitBtn.disabled = false;
        self.setCardRedeemError(
          getLang(
            'mall_redeem_card_invalid',
            '充值卡信息有误，可能已经兑换'
          )
        );
      });
  };

  MacUserMall.prototype.showSuccess = function (info, goods) {
    var delivery = (info && info.delivery) || {};
    var type = delivery.type || (goods && goods.type) || '';
    var body = this.$('[data-um-success-body]');
    if (!body) {
      this.openModal('success');
      return;
    }
    var html = '';
    if (type === 'vip') {
      html =
        '<p>' +
        esc(
          formatTpl(getLang('mall_success_vip', '已发放 VIP {days} 天'), {
            days: delivery.days || '',
          })
        ) +
        '</p>';
      if (delivery.group_name) {
        html +=
          '<p class="muted">' +
          esc(getLang('mall_vip_group', '会员组')) +
          '：' +
          esc(delivery.group_name) +
          '</p>';
      }
    } else if (type === 'card') {
      html =
        '<p>' +
        esc(getLang('mall_success_card', '卡密已发放，请妥善保存')) +
        '</p>' +
        '<div class="um-copy-row"><span>' +
        esc(getLang('mall_card_no', '卡号')) +
        '：' +
        esc(delivery.card_no || '') +
        '</span><button type="button" data-um-copy="' +
        esc(delivery.card_no || '') +
        '">' +
        esc(getLang('mall_copy', '复制')) +
        '</button></div>' +
        '<div class="um-copy-row"><span>' +
        esc(getLang('mall_card_pwd', '密码')) +
        '：' +
        esc(delivery.card_pwd || '') +
        '</span><button type="button" data-um-copy="' +
        esc(delivery.card_pwd || '') +
        '">' +
        esc(getLang('mall_copy', '复制')) +
        '</button></div>' +
        '<div class="um-card-actions">' +
        '<button type="button" class="um-card-actions__btn" data-um-copy-card data-card-no="' +
        esc(delivery.card_no || '') +
        '" data-card-pwd="' +
        esc(delivery.card_pwd || '') +
        '">' +
        esc(getLang('mall_copy_all', '一键复制')) +
        '</button>' +
        '<button type="button" class="um-card-actions__btn um-card-actions__btn--primary" data-um-fill-card data-card-no="' +
        esc(delivery.card_no || '') +
        '" data-card-pwd="' +
        esc(delivery.card_pwd || '') +
        '">' +
        esc(getLang('mall_fill_card', '一键填充')) +
        '</button>' +
        '</div>';
    } else if (type === 'download_quota') {
      html =
        '<p>' +
        esc(
          formatTpl(getLang('mall_success_quota', '已增加下载额度 ×{quota}'), {
            quota: delivery.quota || '',
          })
        ) +
        '</p>';
    } else {
      html = '<p>' + esc(getLang('mall_exchange_ok', '兑换成功')) + '</p>';
    }
    body.innerHTML = html;
    this.openModal('success');
  };

  function isMallOrdersRoute() {
    try {
      return /\/mall_orders(\.html)?(?:\?|#|$)/i.test(String(window.location.pathname || ''));
    } catch (e) {
      return false;
    }
  }

  function shouldAutoOpenOrders() {
    if (isMallOrdersRoute()) {
      return true;
    }
    try {
      var hash = String(window.location.hash || '');
      if (hash === '#orders' || hash.indexOf('#orders') === 0) {
        return true;
      }
      var sp = new URLSearchParams(window.location.search || '');
      if (sp.get('open_orders') === '1' || sp.get('orders') === '1') {
        return true;
      }
    } catch (e2) { /* ignore */ }
    return false;
  }

  function boot() {
    var root = document.querySelector('[data-mac-user-mall]');
    if (!root) return;
    var mall = new MacUserMall(root);
    flushStashedToast();
    if (shouldAutoOpenOrders()) {
      mall.loadGoods('', 1);
      mall.openOrders();
    } else {
      var cols = mallGridColumns();
      var expectedLimit = mallPageLimit(cols, mall.goodsBaseLimit);
      var ssrLimit = parseInt(root.getAttribute('data-limit'), 10) || 20;
      var grid = root.querySelector('[data-um-grid]');
      var cardCount = grid ? grid.querySelectorAll('.user-mall__card').length : 0;
      var needsRefetch =
        expectedLimit !== ssrLimit || (cardCount > 0 && cardCount % cols !== 0);
      if (needsRefetch) {
        mall.goodsLimit = expectedLimit;
        mall.loadGoods(mall.currentType, mall.currentPage);
      } else if (mall.goodsPagecount > 1) {
        var pagerWrap = root.querySelector('[data-um-goods-pager]');
        if (pagerWrap && !pagerWrap.querySelector('ul.page')) {
          mall.renderGoodsPager();
        }
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
