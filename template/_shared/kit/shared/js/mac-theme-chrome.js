/* template/_shared chrome init — header/auth/tabs (shared by all thin PB themes) */
(function () {
  function langText(key, fallback) {
    if (window.MacTheme && MacTheme.i18n && MacTheme.i18n.map && Object.prototype.hasOwnProperty.call(MacTheme.i18n.map, key)) {
      return MacTheme.i18n.map[key];
    }
    return fallback || '';
  }
  /** 全站统一轻提示（顶部居中，带进出场过渡） */
  function macToast(msg) {
    if (!msg) return;
    var box = document.getElementById('mac-toast');
    if (!box) {
      box = document.createElement('div');
      box.id = 'mac-toast';
      box.className = 'mac-toast';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      box.hidden = true;
      document.body.appendChild(box);
    }
    /* 无独立 toast CSS 时也保证可见：顶部居中浮层 */
    box.style.cssText = [
      'position:fixed',
      'left:50%',
      'top:14%',
      'transform:translateX(-50%) translateY(-8px)',
      'z-index:99999',
      'max-width:min(86vw,320px)',
      'padding:10px 18px',
      'border-radius:999px',
      'background:rgba(17,24,39,.92)',
      'color:#fff',
      'font-size:14px',
      'font-weight:600',
      'line-height:1.35',
      'text-align:center',
      'pointer-events:none',
      'box-shadow:0 8px 24px rgba(0,0,0,.18)',
      'opacity:0',
      'transition:opacity .22s ease, transform .22s ease'
    ].join(';');
    box.textContent = String(msg);
    if (box._macTimer) clearTimeout(box._macTimer);
    if (box._macHideTimer) clearTimeout(box._macHideTimer);
    if (box._macHideEnd) {
      box.removeEventListener('transitionend', box._macHideEnd);
      box._macHideEnd = null;
    }
    box.hidden = false;
    box.classList.remove('is-show');
    void box.offsetWidth;
    requestAnimationFrame(function () {
      box.classList.add('is-show');
      box.style.opacity = '1';
      box.style.transform = 'translateX(-50%) translateY(0)';
    });
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var showMs = reduceMotion ? 1600 : 2400;
    var hideMs = reduceMotion ? 0 : 320;
    box._macTimer = setTimeout(function () {
      box.classList.remove('is-show');
      box.style.opacity = '0';
      box.style.transform = 'translateX(-50%) translateY(-8px)';
      if (hideMs <= 0) {
        box.hidden = true;
        return;
      }
      var finished = false;
      function finishHide() {
        if (finished) return;
        finished = true;
        if (box._macHideEnd) {
          box.removeEventListener('transitionend', box._macHideEnd);
          box._macHideEnd = null;
        }
        box.hidden = true;
      }
      box._macHideEnd = function (e) {
        if (e.target !== box) return;
        finishHide();
      };
      box.addEventListener('transitionend', box._macHideEnd);
      box._macHideTimer = setTimeout(finishHide, hideMs);
    }, showMs);
  }
  window.macToast = macToast;
  if (window.MacTheme) {
    MacTheme.toast = macToast;
    MacTheme.notify = macToast;
  }
  function initPlayTabs() {
    var root = document.querySelector('[data-play-tabs]');
    if (!root) return;
    var tabs = root.querySelectorAll('[data-play-tab]');
    var panels = root.querySelectorAll('[data-play-panel]');
    Array.prototype.forEach.call(tabs, function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-play-tab');
        Array.prototype.forEach.call(tabs, function (b) {
          var on = b === btn;
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(panels, function (panel) {
          var on = panel.getAttribute('data-play-panel') === key;
          panel.classList.toggle('is-on', on);
          panel.hidden = !on;
        });
      });
    });
    var toggle = root.querySelector('[data-play-intro-toggle]');
    if (toggle) {
      var textEl = root.querySelector('.play-intro__text');
      toggle.addEventListener('click', function () {
        if (!textEl) return;
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          var short = textEl.getAttribute('data-short');
          if (short) textEl.textContent = short;
          textEl.classList.add('is-clamped');
          toggle.textContent = langText('string_filter_expand', '展开');
          toggle.setAttribute('aria-expanded', 'false');
        } else {
          var full = toggle.getAttribute('data-full');
          if (full) {
            if (!textEl.getAttribute('data-short')) {
              textEl.setAttribute('data-short', textEl.textContent || '');
            }
            textEl.textContent = full;
          }
          textEl.classList.remove('is-clamped');
          textEl.classList.add('is-expanded');
          toggle.textContent = langText('string_filter_collapse', '收起');
          toggle.setAttribute('aria-expanded', 'true');
        }
      });
    }
  }
  function initMediaTabs() {
    var nav = document.querySelector('.media-tabs');
    if (!nav) return;
    var links = nav.querySelectorAll('a[href^="#"]');
    var sections = [];
    Array.prototype.forEach.call(links, function (a) {
      var id = (a.getAttribute('href') || '').slice(1);
      var el = id && document.getElementById(id);
      if (el) sections.push({ id: id, el: el });
    });
    if (!sections.length) return;
    function scrollOffset() {
      var h = 0;
      var hd = document.querySelector('.hd');
      var channel = document.querySelector('.bili-channel');
      var tabBar = document.querySelector('.cinema-stage__bar');
      if (hd) h += hd.offsetHeight;
      if (channel) h += channel.offsetHeight;
      if (tabBar && getComputedStyle(tabBar).position === 'sticky') {
        h += tabBar.offsetHeight;
      } else if (getComputedStyle(nav).position === 'sticky') {
        h += nav.offsetHeight;
      }
      return h + 8;
    }
    function scrollToSection(el) {
      var y = el.getBoundingClientRect().top + window.pageYOffset - scrollOffset();
      window.scrollTo({ behavior: 'smooth', top: y });
    }
    function setOn(id) {
      Array.prototype.forEach.call(links, function (a) {
        a.classList.toggle('is-on', a.getAttribute('href') === '#' + id);
      });
    }
    Array.prototype.forEach.call(links, function (a) {
      a.addEventListener('click', function (e) {
        var id = (a.getAttribute('href') || '').slice(1);
        var el = id && document.getElementById(id);
        if (!el) return;
        e.preventDefault();
        setOn(id);
        scrollToSection(el);
      });
    });
    var scrollTick = false;
    function onScrollSpy() {
      if (scrollTick) return;
      scrollTick = true;
      window.requestAnimationFrame(function () {
        scrollTick = false;
        var offset = scrollOffset() + 24;
        var active = sections[0].id;
        for (var i = 0; i < sections.length; i++) {
          if (sections[i].el.getBoundingClientRect().top <= offset) {
            active = sections[i].id;
          }
        }
        setOn(active);
      });
    }
    window.addEventListener('scroll', onScrollSpy, { passive: true });
    onScrollSpy();
  }
  function initCastCollapse() {
    var panels = document.querySelectorAll('[data-cast-collapse]');
    if (!panels.length) return;
    Array.prototype.forEach.call(panels, function (panel) {
      var wrap = panel.querySelector('.cast-list__wrap');
      var btn = panel.querySelector('[data-cast-more]');
      if (!wrap || !btn) return;
      var more = btn.querySelector('[data-more]');
      var less = btn.querySelector('[data-less]');

      function setExpanded(open) {
        panel.classList.toggle('is-expanded', open);
        panel.classList.toggle('is-collapsed', !open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (more) more.hidden = open;
        if (less) less.hidden = !open;
      }

      function needsCollapse() {
        panel.classList.add('is-collapsed');
        var overflow = wrap.scrollHeight > wrap.clientHeight + 2;
        panel.classList.remove('is-collapsed');
        return overflow;
      }

      function sync() {
        if (!needsCollapse()) {
          btn.hidden = true;
          panel.classList.remove('is-expanded');
          panel.classList.remove('is-collapsed');
          return;
        }
        btn.hidden = false;
        if (!panel.classList.contains('is-expanded')) {
          setExpanded(false);
        }
      }

      btn.addEventListener('click', function () {
        setExpanded(!panel.classList.contains('is-expanded'));
      });

      sync();
      window.addEventListener('resize', sync, { passive: true });
    });
  }
  function initMemberHistory() {
    var root = document.querySelector('[data-member-history]');
    if (!root || !window.MacTheme || typeof MacTheme.post !== 'function') return;
    function removeItems(ids) {
      Array.prototype.forEach.call(root.querySelectorAll('[data-ulog-id]'), function (item) {
        var id = item.getAttribute('data-ulog-id');
        if (ids.indexOf(id) !== -1) item.remove();
      });
      if (!root.querySelector('li[data-ulog-id], .member-history__item, .member-fav')) {
        window.location.reload();
      }
    }
    function del(ids, type, all) {
      var payload = { type: type || root.getAttribute('data-ulog-type') || '4' };
      if (all) {
        payload.all = '1';
      } else {
        payload.ids = ids.join(',');
      }
      MacTheme.post('user/ulog_del', payload, function (err, res) {
        if (err || !res || Number(res.code) !== 1) {
          MacTheme.notify((res && res.msg) || langText('string_operation_failed', '操作失败'));
          return;
        }
        if (all) {
          window.location.reload();
          return;
        }
        removeItems(ids);
      });
    }
    /*
     * 這四個以前是 var（頁面載入當下算一次就凍結），但 initMemberHistory() 跟
     * i18n 的字典 XHR 都掛在 DOMContentLoaded、字典還沒載完這裡就已經算完，
     * 算出來的值永遠是 langText() 的中文 fallback，跟語言切換無關；改成函式，
     * 每次要跳確認框才即時算，才吃得到當下已載入的字典或模板給的 -lang 屬性。
     * -lang 屬性（data-confirm-del-lang 等）優先於直接寫死中文的舊屬性，
     * 讓呼叫端可以指到字典裡更貼切的文案（如 favs_confirm_del_msg），
     * 不必為了翻譯把整句中文塞進 HTML 屬性、堵死字典查找。
     */
    function confirmDel() {
      var k = root.getAttribute('data-confirm-del-lang');
      return (k && langText(k, '')) || root.getAttribute('data-confirm-del') || langText('string_confirm_delete_row', '确定删除这条记录？');
    }
    function confirmClear() {
      var k = root.getAttribute('data-confirm-clear-lang');
      return (k && langText(k, '')) || root.getAttribute('data-confirm-clear') || langText('string_confirm_clear_all', '确定清空全部记录？');
    }
    function confirmDelTitle() {
      var k = root.getAttribute('data-confirm-del-title-lang');
      return (k && langText(k, '')) || root.getAttribute('data-confirm-del-title') || langText('string_confirm_title', '请确认');
    }
    function confirmClearTitle() {
      var k = root.getAttribute('data-confirm-clear-title-lang');
      return (k && langText(k, '')) || root.getAttribute('data-confirm-clear-title') || langText('string_confirm_title', '请确认');
    }
    function askConfirm(opts, next) {
      if (window.MacTheme && typeof MacTheme.confirm === 'function') {
        MacTheme.confirm(opts, next);
        return;
      }
      next(window.confirm(opts.message || ''));
    }
    /* 单条删除：列表内 */
    root.addEventListener('click', function (ev) {
      var delBtn = ev.target.closest('[data-member-history-del]');
      if (!delBtn || !root.contains(delBtn)) return;
      ev.preventDefault();
      var id = delBtn.getAttribute('data-ulog-id');
      if (!id) return;
      askConfirm({
        title: delBtn.getAttribute('data-confirm-title') || confirmDelTitle(),
        message: delBtn.getAttribute('data-confirm-message') || confirmDel(),
        tone: 'danger',
        confirmText: langText('string_confirm_ok', '确定')
      }, function (ok) {
        if (!ok) return;
        del([id], delBtn.getAttribute('data-ulog-type') || root.getAttribute('data-ulog-type') || '4', false);
      });
    });
    /* 清空：按钮在顶栏/桌面头，不在 root 内，必须挂 document */
    document.addEventListener('click', function (ev) {
      var clearBtn = ev.target.closest('[data-member-history-clear]');
      if (!clearBtn) return;
      ev.preventDefault();
      askConfirm({
        title: clearBtn.getAttribute('data-confirm-title') || confirmClearTitle(),
        message: clearBtn.getAttribute('data-confirm-message') || confirmClear(),
        tone: 'danger',
        confirmText: langText('string_confirm_clear_btn', '清空')
      }, function (ok) {
        if (!ok) return;
        del([], clearBtn.getAttribute('data-ulog-type') || root.getAttribute('data-ulog-type') || '4', true);
      });
    });
  }
  function initRankTabs() {
    var root = document.querySelector('[data-rankpage]');
    if (!root) return;
    var tip = root.querySelector('[data-rank-tip]');
    var tabs = root.querySelectorAll('[data-rank-tab]');
    var boards = root.querySelectorAll('[data-rank-board]');
    Array.prototype.forEach.call(tabs, function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-rank-tab');
        Array.prototype.forEach.call(tabs, function (b) {
          var on = b === btn;
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(boards, function (board) {
          var on = board.getAttribute('data-rank-board') === key;
          board.classList.toggle('is-on', on);
          board.hidden = !on;
        });
        if (tip) tip.textContent = btn.getAttribute('data-tip') || '';
      });
    });
  }
  function initDropdown(root, btnSel, menuSel, opts) {
    if (!root) return;
    var btn = root.querySelector(btnSel);
    var menu = root.querySelector(menuSel);
    if (!btn || !menu) return;
    opts = opts || {};
    function needsFixedPin() {
      return !!(opts.pinFixed && window.matchMedia && window.matchMedia('(max-width: 960px)').matches);
    }
    function pinMenu() {
      if (!needsFixedPin()) {
        menu.style.position = '';
        menu.style.top = '';
        menu.style.right = '';
        menu.style.left = '';
        menu.style.zIndex = '';
        return;
      }
      var r = btn.getBoundingClientRect();
      var gap = 6;
      var pad = 8;
      var menuW = menu.offsetWidth || 188;
      var menuH = menu.offsetHeight || 180;
      var left = Math.round(r.right - menuW);
      left = Math.max(pad, Math.min(left, window.innerWidth - menuW - pad));
      var top = Math.round(r.bottom + gap);
      if (top + menuH > window.innerHeight - pad) {
        top = Math.max(pad, Math.round(r.top - gap - menuH));
      }
      menu.style.position = 'fixed';
      menu.style.top = top + 'px';
      menu.style.left = left + 'px';
      menu.style.right = 'auto';
      menu.style.maxWidth = Math.max(160, window.innerWidth - pad * 2) + 'px';
      menu.style.zIndex = '10050';
    }
    function clearPin() {
      if (!opts.pinFixed) return;
      menu.style.top = '';
      menu.style.right = '';
      menu.style.left = '';
      menu.style.maxWidth = '';
      menu.style.position = '';
      menu.style.zIndex = '';
    }
    function close() {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      clearPin();
    }
    function open() {
      menu.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      pinMenu();
      requestAnimationFrame(pinMenu);
    }
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (menu.hidden) open(); else close();
    });
    menu.addEventListener('click', function (e) {
      if (opts.onMenuClick) opts.onMenuClick(e, close);
    });
    document.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
    if (opts.pinFixed) {
      window.addEventListener('resize', function () {
        if (btn.getAttribute('aria-expanded') === 'true') pinMenu();
      }, { passive: true });
      window.addEventListener('scroll', function () {
        if (btn.getAttribute('aria-expanded') === 'true') pinMenu();
      }, { passive: true });
    }
  }
  function initLangDd(root) {
    var cur = root && root.querySelector('[data-lang-dd-cur]');
    function syncCurLabel(langBit) {
      if (!cur) return;
      if (langBit === 1 || langBit === '1') {
        cur.textContent = langText('string_lang_en', 'English');
      } else {
        cur.textContent = langText('string_lang_zh', '中文');
      }
    }
    initDropdown(root, '.lang-dd__btn', '.lang-dd__menu', {
      pinFixed: true,
      onMenuClick: function (e, close) {
        var t = e.target.closest('[data-mac-lang]');
        if (!t) return;
        var code = String(t.getAttribute('data-mac-lang') || '').toLowerCase();
        var langBit = code === 'en' ? 1 : 0;
        if (window.MacTheme && MacTheme.i18n && typeof MacTheme.i18n.set === 'function') {
          MacTheme.i18n.set(langBit, function () {
            syncCurLabel(langBit);
          }, true);
        } else {
          syncCurLabel(langBit);
        }
        close();
      }
    });
    try {
      syncCurLabel(localStorage.getItem('lang'));
    } catch (err) {}
  }
  function initUserDd(root) {
    initDropdown(root, '.user-dd__btn', '.user-dd__menu', { pinFixed: true });
  }
  function initHdMenu() {
    var btn = document.querySelector('[data-hd-menu]');
    var nav = document.getElementById('hd-primary-nav');
    if (!btn || !nav) return;
    var navHome = nav.parentNode;
    var navNext = nav.nextSibling;
    function isMobileNav() {
      return window.matchMedia && window.matchMedia('(max-width: 960px)').matches;
    }
    function portalNav() {
      if (!isMobileNav() || nav._hdPortaled) return;
      nav._hdPortaled = true;
      nav._hdNavHome = navHome;
      nav._hdNavNext = navNext;
      document.body.appendChild(nav);
    }
    function restoreNav() {
      if (!nav._hdPortaled || !nav._hdNavHome) return;
      if (nav._hdNavNext) {
        nav._hdNavHome.insertBefore(nav, nav._hdNavNext);
      } else {
        nav._hdNavHome.appendChild(nav);
      }
      nav._hdPortaled = false;
    }
    function setOpen(open) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('hd-nav-open', open);
      document.documentElement.classList.toggle('hd-nav-open', open);
      if (open && isMobileNav()) {
        portalNav();
      } else {
        restoreNav();
      }
    }
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(btn.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('hd-nav-open')) return;
      if (e.target.closest('.hd-menu') || e.target.closest('#hd-primary-nav')) return;
      setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('hd-nav-open')) {
        setOpen(false);
      }
    });
    window.addEventListener('resize', function () {
      if (!isMobileNav()) {
        if (document.body.classList.contains('hd-nav-open')) setOpen(false);
        restoreNav();
      }
    }, { passive: true });
  }
  function initPageScrollXLock() {
    if (!window.matchMedia('(max-width: 960px)').matches) return;
    /* iOS 惯性/回弹滚动过程中调用 scrollTo 会打断原生滚动物理，
       曾导致触底后回滑瞬间被强制归零、跳回顶部；改为滚动/触摸停止后延迟修正，绝不在滚动进行中介入。
       仅凭"滚动事件停止 150ms"判断"已停止"并不可靠：触底反向上滑的瞬间，
       手指仍按在屏幕上但滚动方向反转会有短暂停顿，此时定时器仍可能触发并打断正在进行的触摸滚动，
       因此额外用 touchstart/touchend 跟踪手指是否仍按住，按住期间一律不修正。 */
    var timer = null;
    var touching = false;
    var resetX = function () {
      timer = null;
      if (touching) return;
      if (Math.abs(window.scrollX) > 0.5) {
        window.scrollTo(0, window.scrollY);
      }
    };
    var scheduleReset = function () {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(resetX, 150);
    };
    document.addEventListener('touchstart', function () {
      touching = true;
    }, { passive: true });
    document.addEventListener('touchend', function () {
      touching = false;
      scheduleReset();
    }, { passive: true });
    document.addEventListener('touchcancel', function () {
      touching = false;
      scheduleReset();
    }, { passive: true });
    window.addEventListener('scroll', scheduleReset, { passive: true });
  }
  function initHdScroll() {
    var hd = document.querySelector('[data-hd]');
    if (!hd || !document.body.classList.contains('page-home')) return;
    var shell = document.querySelector('.pbs-shell');
    var isCinema = shell && shell.classList.contains('is-cinema');
    var isMobile = window.matchMedia('(max-width: 960px)').matches;
    /* 影院壳：透明/实心切换；移动端首页：滚动态 class，便于 CSS 锁定顶栏高度 */
    if (!isCinema && !isMobile) return;
    var onScroll = function () {
      var solid = window.scrollY > 24;
      hd.classList.toggle('is-scrolled', solid);
      document.body.classList.toggle('is-hd-solid', solid);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }
  var channelPanelOpenCount = 0;
  var channelTouchLockBound = false;

  function setChannelPanelScrollLock(locked) {
    if (locked) {
      channelPanelOpenCount += 1;
      if (channelPanelOpenCount === 1) {
        document.documentElement.classList.add('is-channel-panel-open');
        document.body.classList.add('is-channel-panel-open');
      }
      return;
    }
    if (channelPanelOpenCount <= 0) return;
    channelPanelOpenCount -= 1;
    if (channelPanelOpenCount === 0) {
      document.documentElement.classList.remove('is-channel-panel-open');
      document.body.classList.remove('is-channel-panel-open');
      document.documentElement.style.removeProperty('--channel-panel-top');
    }
  }

  function ensureChannelTouchLock() {
    if (channelTouchLockBound) return;
    channelTouchLockBound = true;
    document.addEventListener('touchmove', function (e) {
      if (!document.body.classList.contains('is-channel-panel-open')) return;
      var openPanels = document.querySelectorAll('[data-channel-overlay]:not([hidden]) [data-channel-panel]');
      for (var i = 0; i < openPanels.length; i++) {
        if (openPanels[i].contains(e.target)) return;
      }
      e.preventDefault();
    }, { passive: false });
  }

  function initChannelMob() {
    ensureChannelTouchLock();
    document.querySelectorAll('[data-channel-mob]').forEach(function (mob) {
      if (mob.dataset.channelMobInit === '1') return;
      mob.dataset.channelMobInit = '1';

      var nav = mob.closest('[data-channel-nav]');
      var scroll = mob.querySelector('[data-channel-tab-scroll]');
      var toggle = mob.querySelector('[data-channel-panel-toggle]');
      var overlay = mob.querySelector('[data-channel-overlay]');
      var backdrop = mob.querySelector('[data-channel-panel-backdrop]');
      var panel = mob.querySelector('[data-channel-panel]');
      if (!scroll || !toggle || !overlay || !panel) return;

      var tabbar = mob.querySelector('.bili-channel__tabbar');
      var overlayHome = mob;

      function syncPanelTop() {
        var anchor = tabbar || nav;
        if (!anchor) return;
        var bottom = Math.max(0, Math.round(anchor.getBoundingClientRect().bottom));
        document.documentElement.style.setProperty('--channel-panel-top', bottom + 'px');
      }

      function mountOverlay() {
        if (overlay.parentNode === document.body) return;
        overlay._channelOverlayHome = overlayHome;
        document.body.appendChild(overlay);
      }

      function restoreOverlay() {
        var home = overlay._channelOverlayHome || overlayHome;
        if (overlay.parentNode === home) return;
        home.appendChild(overlay);
      }

      function setPanelOpen(open) {
        var wasOpen = toggle.getAttribute('aria-expanded') === 'true';
        if (open === wasOpen) return;

        if (open) {
          syncPanelTop();
          mountOverlay();
          overlay.hidden = false;
          overlay.classList.remove('is-closing');
          toggle.setAttribute('aria-expanded', 'true');
          if (nav) nav.classList.add('is-panel-open');
          setChannelPanelScrollLock(true);
          requestAnimationFrame(function () {
            syncPanelTop();
            requestAnimationFrame(function () {
              overlay.classList.add('is-open');
            });
          });
          return;
        }

        toggle.setAttribute('aria-expanded', 'false');
        if (nav) nav.classList.remove('is-panel-open');
        setChannelPanelScrollLock(false);
        overlay.classList.remove('is-open');
        overlay.classList.add('is-closing');

        var closed = false;
        function finishClose() {
          if (closed) return;
          closed = true;
          overlay.classList.remove('is-closing');
          overlay.hidden = true;
          restoreOverlay();
        }

        var duration = 280;
        try {
          if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            duration = 0;
          }
        } catch (err) {}
        if (duration <= 0) {
          finishClose();
          return;
        }
        window.setTimeout(finishClose, duration + 40);
      }

      function isOverlayActive() {
        return overlay.classList.contains('is-open')
          || overlay.classList.contains('is-closing')
          || !overlay.hidden;
      }

      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setPanelOpen(toggle.getAttribute('aria-expanded') !== 'true');
      });

      if (backdrop) {
        backdrop.addEventListener('click', function () {
          setPanelOpen(false);
        });
      }

      document.addEventListener('click', function (e) {
        if (!isOverlayActive() || !overlay.classList.contains('is-open')) return;
        if (toggle.contains(e.target)) return;
        if (panel.contains(e.target)) return;
        if (!mob.contains(e.target)) setPanelOpen(false);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) setPanelOpen(false);
      });

      window.addEventListener('resize', function () {
        if (overlay.classList.contains('is-open')) syncPanelTop();
      });

      function syncTabScrollEdges() {
        var max = scroll.scrollWidth - scroll.clientWidth;
        var sl = scroll.scrollLeft;
        var overflow = max > 2;
        var atEnd = !overflow || sl >= max - 2;
        scroll.classList.toggle('has-overflow', overflow);
        scroll.classList.toggle('is-scroll-end', atEnd);
      }

      scroll.addEventListener('scroll', syncTabScrollEdges, { passive: true });
      window.addEventListener('resize', syncTabScrollEdges);
      syncTabScrollEdges();

      var active = scroll.querySelector('.bili-channel__tab.on');
      if (active && scroll.scrollWidth > scroll.clientWidth) {
        var offset = active.offsetLeft - (scroll.clientWidth - active.offsetWidth) / 2;
        scroll.scrollLeft = Math.max(0, offset);
        syncTabScrollEdges();
      }
    });
  }
  function initFilterCollapse() {
    var mq = window.matchMedia('(max-width: 768px)');
    function wrapLimit() {
      return 1;
    }
    function scrollActiveChip(chips) {
      if (!chips || !mq.matches) return;
      var active = chips.querySelector('.on');
      if (!active) return;
      var offset = active.offsetLeft - (chips.clientWidth - active.offsetWidth) / 2;
      chips.scrollLeft = Math.max(0, offset);
    }
    function ensureFrowWrap(row) {
      if (row.querySelector('.frow__chips')) return;
      var items = [];
      var kids = Array.prototype.slice.call(row.children);
      kids.forEach(function (el) {
        if (el.tagName === 'A') items.push(el);
      });
      if (items.length < wrapLimit()) return;
      var wrap = document.createElement('div');
      wrap.className = 'frow__chips';
      items.forEach(function (a) { wrap.appendChild(a); });
      row.appendChild(wrap);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'frow__more';
      btn.setAttribute('data-filter-more', '');
      btn.setAttribute('aria-expanded', 'false');
      btn.hidden = true;
      btn.innerHTML = '<span data-more data-lang="string_filter_expand">' + langText('string_filter_expand', '展开') + '</span><span data-less hidden data-lang="string_filter_collapse">' + langText('string_filter_collapse', '收起') + '</span>';
      row.appendChild(btn);
      row.setAttribute('data-filter-collapse', '');
    }
    function bindCollapse(row) {
      if (row.getAttribute('data-filter-bound') === '1') return;
      row.setAttribute('data-filter-bound', '1');
      var chips = row.querySelector('.bili-filter__chips, .frow__chips');
      var btn = row.querySelector('[data-filter-more]');
      if (!chips) return;
      function syncRow() {
        if (mq.matches) {
          row.classList.remove('is-collapsed');
          if (btn) btn.hidden = true;
          scrollActiveChip(chips);
          return;
        }
        if (!btn) return;
        var more = btn.querySelector('[data-more]');
        var less = btn.querySelector('[data-less]');
        function needsCollapse() {
          /* 只看是否真的超出一行；不要用数量/soft 标签硬阈值展开 */
          var wasCollapsed = row.classList.contains('is-collapsed');
          row.classList.remove('is-collapsed');
          var naturalH = chips.scrollHeight;
          row.classList.add('is-collapsed');
          var collapsedH = chips.clientHeight;
          if (!wasCollapsed) {
            row.classList.remove('is-collapsed');
          }
          return naturalH > collapsedH + 4;
        }
        function setOpen(open) {
          row.classList.toggle('is-collapsed', !open);
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (more) more.hidden = open;
          if (less) less.hidden = !open;
        }
        var overflow = needsCollapse();
        btn.hidden = !overflow;
        if (!overflow) {
          row.classList.remove('is-collapsed');
          return;
        }
        var active = chips.querySelector('.on');
        var activeHidden = false;
        var activeSoft = !!(active && (active.classList.contains('bili-filter__chip--soft') || active.classList.contains('taglist__soft')));
        if (active && !activeSoft) {
          var cr = chips.getBoundingClientRect();
          var ar = active.getBoundingClientRect();
          activeHidden = ar.bottom > cr.bottom + 2;
        }
        setOpen(activeHidden || activeSoft);
      }
      if (btn) {
        btn.addEventListener('click', function () {
          row.classList.toggle('is-collapsed');
          var open = !row.classList.contains('is-collapsed');
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
          var more = btn.querySelector('[data-more]');
          var less = btn.querySelector('[data-less]');
          if (more) more.hidden = open;
          if (less) less.hidden = !open;
        });
      }
      syncRow();
      window.addEventListener('resize', syncRow, { passive: true });
    }
    document.querySelectorAll('.filters .frow').forEach(ensureFrowWrap);
    document.querySelectorAll('[data-filter-collapse]').forEach(bindCollapse);
  }
  function initAuthModals() {
    var modals = Array.prototype.slice.call(document.querySelectorAll('[data-auth-modal]'));
    if (!modals.length) return;
    var lastFocus = null;

    function setRecoveryTab(root, tab) {
      if (!root) return;
      tab = tab === 'phone' ? 'phone' : (tab === 'email' ? 'email' : 'security');
      // 邮箱/短信未配置时该面板不输出（#272），回落到密保找回
      if (!root.querySelector('[data-auth-recovery-panel="' + tab + '"]')) tab = 'security';
      root.setAttribute('data-active-tab', tab);
      root.querySelectorAll('[data-auth-recovery-tab]').forEach(function (btn) {
        var on = btn.getAttribute('data-auth-recovery-tab') === tab;
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      root.querySelectorAll('[data-auth-recovery-panel]').forEach(function (panel) {
        var on = panel.getAttribute('data-auth-recovery-panel') === tab;
        panel.hidden = !on;
        panel.classList.toggle('is-on', on);
      });
    }

    function show(id) {
      modals.forEach(function (m) {
        var on = m.getAttribute('data-auth-modal') === id;
        m.hidden = !on;
        m.setAttribute('aria-hidden', on ? 'false' : 'true');
      });
      document.body.classList.add('has-auth-modal');
      var modal = document.querySelector('[data-auth-modal="' + id + '"]');
      var first = modal && modal.querySelector('input:not([type="hidden"])');
      /* 触屏设备不自动聚焦：否则弹窗一打开软键盘就弹出并盖住弹窗 */
      var touch = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
      if (first && !touch) setTimeout(function () { first.focus(); }, 60);
    }
    function closeAll() {
      modals.forEach(function (m) {
        m.hidden = true;
        m.setAttribute('aria-hidden', 'true');
      });
      document.body.classList.remove('has-auth-modal');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }
    function open(id, opts) {
      lastFocus = document.activeElement;
      if (id === 'findpass' || id === 'findpass-msg') {
        var fpModal = document.querySelector('[data-auth-modal="findpass"]');
        var tab = 'security';
        if (opts && opts.tab) tab = opts.tab;
        else if (opts && opts.ac) tab = opts.ac;
        else if (id === 'findpass-msg') tab = 'email';
        var recoveryRoot = fpModal && fpModal.querySelector('[data-auth-recovery]');
        if (window.MacTheme && typeof window.MacTheme.setRecoveryTab === 'function') {
          window.MacTheme.setRecoveryTab(recoveryRoot, tab);
        } else {
          setRecoveryTab(recoveryRoot, tab);
        }
        show('findpass');
        return;
      }
      show(id);
    }

    document.querySelectorAll('[data-auth-open]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var id = el.getAttribute('data-auth-open') || 'login';
        var ac = el.getAttribute('data-auth-ac');
        if (id === 'findpass-msg' && ac) {
          open('findpass', { tab: ac });
        } else {
          open(id);
        }
      });
    });
    document.querySelectorAll('[data-auth-switch]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        show(el.getAttribute('data-auth-switch') || 'login');
      });
    });
    modals.forEach(function (modal) {
      modal.querySelectorAll('[data-auth-close]').forEach(function (el) {
        el.addEventListener('click', function () { closeAll(); });
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('has-auth-modal')) closeAll();
    });
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (!a || a.hasAttribute('data-auth-static')) return;
      var href = a.getAttribute('href') || '';
      if (/user\/login(\.html)?/i.test(href)) {
        e.preventDefault();
        open('login');
      } else if (/user\/reg(\.html)?/i.test(href)) {
        e.preventDefault();
        open('login');
      } else if (/user\/findpass(\.html)?/i.test(href) && !/findpass_msg/i.test(href)) {
        e.preventDefault();
        open('findpass');
      } else if (/user\/findpass_msg/i.test(href)) {
        e.preventDefault();
        var acTab = /ac=phone/i.test(href) ? 'phone' : 'email';
        open('findpass', { tab: acTab });
      }
    });
    if (location.search.indexOf('login=1') >= 0 || location.hash === '#login') open('login');
    if (location.search.indexOf('reg=1') >= 0 || location.hash === '#reg') open('reg');
    if (location.hash === '#findpass') open('findpass');
    if (window.MacTheme && typeof MacTheme.on === 'function') {
      MacTheme.on('login:required', function (detail) {
        var id = (detail && detail.modal) ? detail.modal : 'login';
        if (id === 'findpass-msg') {
          open('findpass', { tab: (detail && detail.tab) || 'email' });
        } else if (modals.some(function (m) { return m.getAttribute('data-auth-modal') === id; })) {
          open(id);
        } else {
          open('login');
        }
      });
      MacTheme.openLogin = function () { open('login'); };
      MacTheme.openReg = function () { open('reg'); };
      MacTheme.openFindpass = function (opts) { open('findpass', opts || {}); };
      MacTheme.on('login:ok', function () { closeAll(); });
      MacTheme.on('reg:ok', function () { closeAll(); });
      MacTheme.on('findpass:ok', function () {
        closeAll();
        setTimeout(function () { open('login'); }, 120);
      });
    }
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get('open_auth_modal') === '1') {
        setTimeout(function () { open('login'); }, 120);
      }
    } catch (e) {}
  }
  function initFilterAdvanced() {
    document.querySelectorAll('[data-filter-panel]').forEach(function (root) {
      var toggle = root.querySelector('[data-filter-advanced-toggle]');
      var panel = root.querySelector('[data-filter-advanced]');
      if (!toggle || !panel) return;

      function mq() {
        return window.matchMedia('(max-width: 768px)').matches;
      }
      function setOpen(open) {
        panel.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!open) panel.removeAttribute('data-open');
      }
      function sync() {
        if (!mq()) {
          panel.classList.add('is-open');
          toggle.setAttribute('aria-expanded', 'true');
          return;
        }
        var shouldOpen = panel.getAttribute('data-open') === '1' || panel.classList.contains('is-open');
        setOpen(shouldOpen);
      }
      toggle.addEventListener('click', function () {
        setOpen(!panel.classList.contains('is-open'));
      });
      sync();
      window.addEventListener('resize', sync, { passive: true });
    });
  }
  document.addEventListener('DOMContentLoaded', function () {
    initPlayTabs();
    initMediaTabs();
    initCastCollapse();
    initMemberHistory();
    initRankTabs();
    initAuthModals();
    initLangDd(document.querySelector('[data-lang-dd]'));
    document.querySelectorAll('[data-user-dd]').forEach(function (root) {
      initUserDd(root);
    });
    initHdMenu();
    /* 已由 pb-shell-base.css 的 overscroll-behavior-x: none 从根上禁用横向弹性回弹，
       不再需要这里事后用 scrollTo 纠正（该纠正本身在触摸未松开时触发会打断原生滚动，曾导致触底反向上滑跳回顶部）；
       如后续发现仍有真实横向溢出导致的 scrollX 漂移，再评估是否重新启用 initPageScrollXLock() */
    // initPageScrollXLock();
    initHdScroll();
    initChannelMob();
    initFilterCollapse();
    initFilterAdvanced();
    (function remapDefaultAvatars() {
      var tpl = ((window.maccms && window.maccms.path_tpl) || '').replace(/\/?$/, '/');
      var fallback = tpl
        ? (tpl + 'page-builder-components/_shared/img/avatar-default.svg')
        : (((window.maccms && window.maccms.path) || '/') + 'template/_shared/kit/shared/img/avatar-default.svg');
      document.querySelectorAll('img[src*="touxiang.png"]').forEach(function (img) {
        if (img.getAttribute('data-pb-theme-avatar') === '1') {
          return;
        }
        img.src = fallback;
      });
    })();
  });
})();
