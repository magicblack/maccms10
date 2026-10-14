(function (win, doc) {
  'use strict';

  function closest(el, sel) {
    while (el && el.nodeType === 1) {
      if (el.matches ? el.matches(sel) : el.msMatchesSelector && el.msMatchesSelector(sel)) {
        return el;
      }
      el = el.parentNode;
    }
    return null;
  }

  function closestTarget(target, sel) {
    if (!target) return null;
    if (target.nodeType !== 1) {
      return closest(target.parentNode, sel);
    }
    if (target.closest) {
      return target.closest(sel);
    }
    return closest(target, sel);
  }

  function setPanel(root, panel) {
    if (!root) return;
    root.setAttribute('data-auth-panel', panel);
    root.querySelectorAll('[data-auth-page-panel]').forEach(function (node) {
      var on = node.getAttribute('data-auth-page-panel') === panel;
      node.hidden = !on;
    });
    var title = root.querySelector('[data-auth-page-title]');
    if (title) {
      if (panel === 'findpass') {
        title.setAttribute('data-lang', 'user_ylwtzhmm');
        title.textContent = '找回密码';
      } else {
        title.setAttribute('data-lang', 'user_unified_page_title');
        title.textContent = '登录 / 注册';
      }
    }
  }

  function updateInviteLabel(toggle, open) {
    var label = toggle.querySelector('.auth-page-form__invite-label');
    if (!label) return;
    var key = open
      ? (label.getAttribute('data-lang-expanded') || 'string_auth_invite_collapse')
      : (label.getAttribute('data-lang-collapsed') || 'string_auth_invite_expand');
    label.setAttribute('data-lang', key);
    if (win.MacTheme && win.MacTheme.i18n && typeof win.MacTheme.i18n.apply === 'function') {
      win.MacTheme.i18n.apply(toggle);
      return;
    }
    label.textContent = open ? '收起邀请码' : '填写邀请码';
  }

  function setInviteOpen(box, open) {
    var panel = box.querySelector('.auth-page-form__invite-panel');
    var toggle = box.querySelector('[data-auth-invite-toggle]');
    if (!panel || !toggle) return;
    box.classList.toggle('is-open', open);
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    updateInviteLabel(toggle, open);
  }

  /*
   * 移动端软键盘：iOS Safari / Android Chrome 弹出键盘时只缩小 visual viewport，
   * position:fixed 的登录弹窗与 dvh 都不跟随，表单底部的邀请码输入框会被键盘盖住。
   * 这里把可视区高度/偏移写入 CSS 变量（auth_modals/style.css 使用），
   * 并把当前聚焦的输入框滚进键盘上方的可视区。
   */
  var AUTH_FIELD_SCOPE = '[data-auth-page], .auth-modal';

  function isAuthField(el) {
    return !!(el && el.nodeType === 1 && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) && closestTarget(el, AUTH_FIELD_SCOPE));
  }

  /*
   * 部分 WebView（如 Waydroid / LineageOS Jelly，窗口 adjustPan）弹出键盘时
   * innerHeight 与 visualViewport 都不变，页面无从得知键盘高度。触屏设备上聚焦
   * 表单后若可视区未缩小，则按屏幕比例估算键盘高度（kbGuess），走同一套 CSS 变量。
   */
  var kbGuess = 0;
  var fullHeight = 0;

  function isTouchDevice() {
    return !!(win.matchMedia && win.matchMedia('(hover: none) and (pointer: coarse)').matches);
  }

  function estimateKeyboard() {
    var sh = (win.screen && win.screen.height) || win.innerHeight;
    var landscape = win.innerWidth > win.innerHeight;
    return Math.round(sh * (landscape ? 0.52 : 0.42));
  }

  function viewportShrunk() {
    var vv = win.visualViewport;
    var h = vv ? vv.height : win.innerHeight;
    return fullHeight > 0 && h < fullHeight * 0.85;
  }

  function syncViewportVars() {
    var vv = win.visualViewport;
    var s = doc.documentElement.style;
    if (kbGuess) {
      s.setProperty('--auth-vv-h', Math.max(160, win.innerHeight - kbGuess) + 'px');
      s.setProperty('--auth-vv-top', '0px');
      s.setProperty('--auth-kb-h', kbGuess + 'px');
      return;
    }
    if (!vv) return;
    s.setProperty('--auth-vv-h', Math.round(vv.height) + 'px');
    s.setProperty('--auth-vv-top', Math.round(vv.offsetTop) + 'px');
    /* 键盘占用高度：整页注册表单据此补底部留白，保证文档可滚到键盘上方 */
    var kb = Math.round(win.innerHeight - vv.height);
    s.setProperty('--auth-kb-h', (kb > 80 ? kb : 0) + 'px');
  }

  function setKbGuess(on) {
    var next = on ? estimateKeyboard() : 0;
    if (next === kbGuess) return;
    kbGuess = next;
    doc.documentElement.classList.toggle('auth-kb-guess', !!kbGuess);
    syncViewportVars();
  }

  function findScroller(el) {
    var node = el.parentNode;
    while (node && node.nodeType === 1 && node !== doc.body && node !== doc.documentElement) {
      var oy = win.getComputedStyle(node).overflowY;
      if ((oy === 'auto' || oy === 'scroll') && node.scrollHeight > node.clientHeight) return node;
      node = node.parentNode;
    }
    return null;
  }

  function revealField(el) {
    if (!el || !el.getBoundingClientRect) return;
    var vv = win.visualViewport;
    var gap = 16;
    var viewTop = (vv && !kbGuess ? vv.offsetTop : 0) + gap;
    var viewBottom = (kbGuess ? win.innerHeight - kbGuess : (vv ? vv.offsetTop + vv.height : win.innerHeight)) - gap;
    var scroller = findScroller(el);
    if (scroller) {
      /* 同时限制在滚动容器（弹窗表单区）自身的可见范围内 */
      var sr = scroller.getBoundingClientRect();
      viewTop = Math.max(viewTop, sr.top + gap);
      viewBottom = Math.min(viewBottom, sr.bottom - gap);
    }
    var rect = el.getBoundingClientRect();
    var delta = 0;
    if (rect.bottom > viewBottom) delta = rect.bottom - viewBottom;
    else if (rect.top < viewTop) delta = rect.top - viewTop;
    if (!delta) return;
    if (scroller) {
      scroller.scrollTop += delta;
    } else if (!closestTarget(el, '.auth-modal')) {
      win.scrollBy(0, delta);
    }
  }

  function revealActiveField() {
    var el = doc.activeElement;
    if (isAuthField(el)) revealField(el);
  }

  function bindKeyboardViewport() {
    if (doc.documentElement._macAuthVvBound) return;
    doc.documentElement._macAuthVvBound = true;
    var vv = win.visualViewport;
    fullHeight = vv ? vv.height : win.innerHeight;
    syncViewportVars();
    var raf = win.requestAnimationFrame || setTimeout;
    if (vv) {
      vv.addEventListener('resize', function () {
        if (!isAuthField(doc.activeElement)) fullHeight = vv.height;
        /* 浏览器报告了真实键盘高度：放弃估算 */
        if (kbGuess && viewportShrunk()) setKbGuess(false);
        syncViewportVars();
        /* 等弹窗按新高度重排后再滚动 */
        raf(revealActiveField);
      });
      vv.addEventListener('scroll', syncViewportVars);
    } else {
      win.addEventListener('resize', function () {
        if (!isAuthField(doc.activeElement)) fullHeight = win.innerHeight;
      });
    }
    doc.addEventListener('focusin', function (e) {
      if (!isAuthField(e.target)) return;
      /* 键盘动画结束后：可视区没缩小（WebView 不报告键盘）则启用估算 */
      setTimeout(function () {
        if (!isAuthField(doc.activeElement)) return;
        if (isTouchDevice() && !viewportShrunk()) setKbGuess(true);
        raf(revealActiveField);
      }, 320);
    });
    doc.addEventListener('focusout', function () {
      /* 焦点在表单字段间切换时不收起；真正离开表单后恢复 */
      setTimeout(function () {
        if (!isAuthField(doc.activeElement)) setKbGuess(false);
      }, 120);
    });
  }

  function bindAuthFormUi(scope) {
    var root = scope && scope.querySelectorAll ? scope : doc;

    root.querySelectorAll('[data-auth-pwd-toggle]').forEach(function (btn) {
      if (btn._macAuthPwdBound) return;
      btn._macAuthPwdBound = true;
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var wrap = closest(btn, '.auth-page-form__pwd');
        var input = wrap && wrap.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.classList.toggle('is-on', show);
      });
    });

    root.querySelectorAll('[data-auth-invite]').forEach(function (box) {
      var toggle = box.querySelector('[data-auth-invite-toggle]');
      var panel = box.querySelector('.auth-page-form__invite-panel');
      if (!toggle || !panel || toggle._macAuthInviteBound) return;
      toggle._macAuthInviteBound = true;
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        var willOpen = !box.classList.contains('is-open');
        setInviteOpen(box, willOpen);
        if (!willOpen) return;
        /* 展开后直接聚焦邀请码框（在点击手势内调用，iOS 才会弹键盘），并滚入可视区 */
        var input = box.querySelector('[data-auth-invite-input]');
        if (!input) return;
        try { input.focus({ preventScroll: true }); } catch (err) { input.focus(); }
        revealField(input);
      });
      updateInviteLabel(toggle, box.classList.contains('is-open'));
    });
  }

  function readCookie(name) {
    try {
      var m = doc.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    } catch (e) {
      return '';
    }
  }

  function syncInviteFromUrl(root) {
    try {
      var sp = new URLSearchParams(win.location.search);
      var code = sp.get('invite_code');
      if (code) {
        code = decodeURIComponent(code.replace(/\+/g, ' '));
      } else {
        code = readCookie('invite_code');
      }
      if (!code) return;
      var input = root.querySelector('[data-auth-invite-input]');
      if (input && !input.value) input.value = code;
      var box = root.querySelector('[data-auth-invite]');
      if (box) setInviteOpen(box, true);
    } catch (e) {}
  }

  function syncUidFromUrl(root) {
    try {
      var sp = new URLSearchParams(win.location.search);
      var uid = sp.get('uid');
      if (!uid || !/^\d+$/.test(uid)) {
        uid = readCookie('uid');
      }
      if (!uid || !/^\d+$/.test(uid)) return '';
      var input = root.querySelector('[data-auth-unified-uid]');
      if (input && !input.value) input.value = uid;
      return uid;
    } catch (e) {
      return '';
    }
  }

  /**
   * ?uid= 推广链接只带用户ID，不带邀请码字符串，因此可见的“邀请码”输入框
   * 默认不会有内容（uid 只写入隐藏字段）。这里反查该 uid 对应的邀请码，
   * 回显到可见输入框，让访客能确认邀请关系已生效。
   */
  function syncInviteFromUid(root, uid) {
    try {
      if (!uid) return;
      var input = root.querySelector('[data-auth-invite-input]');
      if (!input || input.value) return;
      var base = (win.maccms && win.maccms.path) || '/';
      var xhr = new XMLHttpRequest();
      xhr.open('GET', String(base).replace(/\/+$/, '') + '/index.php/user/invite_lookup?uid=' + encodeURIComponent(uid), true);
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        if (xhr.status < 200 || xhr.status >= 300) return;
        var res = null;
        try { res = JSON.parse(xhr.responseText); } catch (e) { return; }
        if (!res || res.code !== 1 || !res.invite_code) return;
        if (!input.value) input.value = res.invite_code;
        var box = root.querySelector('[data-auth-invite]');
        if (box) setInviteOpen(box, true);
      };
      xhr.send();
    } catch (e) {}
  }

  function initGlobalAuthUi() {
    bindAuthFormUi(doc);
    bindKeyboardViewport();
    doc.addEventListener('click', function (e) {
      var sw = closestTarget(e.target, '[data-auth-switch-findpass]');
      if (!sw) return;
      e.preventDefault();
      if (win.MacTheme && typeof win.MacTheme.openFindpass === 'function') {
        win.MacTheme.openFindpass();
      }
    });
  }

  function initAuthPage() {
    var root = doc.querySelector('[data-auth-page]');
    if (!root) return;

    var initial = root.getAttribute('data-auth-panel') || 'login';
    setPanel(root, initial);

    bindAuthFormUi(root);
    syncInviteFromUrl(root);
    var syncedUid = syncUidFromUrl(root);
    syncInviteFromUid(root, syncedUid);

    root.querySelectorAll('[data-auth-switch-findpass]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        setPanel(root, 'findpass');
      });
    });
    root.querySelectorAll('[data-auth-switch-login]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        setPanel(root, 'login');
      });
    });

    var back = root.querySelector('[data-auth-page-back]');
    if (back) {
      back.addEventListener('click', function () {
        var panel = root.getAttribute('data-auth-panel');
        if (panel === 'findpass') {
          setPanel(root, 'login');
          return;
        }
        if (win.history.length > 1) {
          win.history.back();
        } else {
          win.location.href = (win.maccms && win.maccms.path) || '/';
        }
      });
    }

    if (win.MacTheme && typeof win.MacTheme.on === 'function') {
      win.MacTheme.on('findpass:ok', function () {
        setPanel(root, 'login');
      });
    }
  }

  function boot() {
    initGlobalAuthUi();
    initAuthPage();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
