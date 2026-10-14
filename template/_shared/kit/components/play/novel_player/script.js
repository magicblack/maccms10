/**
 * twigspike 小说阅读器 — components/novel_player.twig
 */
(function (win, doc) {
  'use strict';

  var STORAGE_KEY = 'novel_reader_prefs';

  function closest(el, sel) {
    while (el && el.nodeType === 1) {
      if (el.matches(sel)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function tplBase() {
    var m = win.maccms || {};
    var p = m.path_tpl || '';
    return String(p).replace(/\/+$/, '') + '/page-builder-components/_shared/';
  }

  function loadProgressJs(cb) {
    if (win.MacReaderReadProgress) {
      if (cb) cb();
      return;
    }
    if (doc.querySelector('script[data-reader-read-progress="1"]')) {
      if (cb) cb();
      return;
    }
    var s = doc.createElement('script');
    s.src = tplBase() + 'js/reader-read-progress.js?v=5';
    s.defer = true;
    s.setAttribute('data-reader-read-progress', '1');
    s.onload = function () { if (cb) cb(); };
    doc.head.appendChild(s);
  }

  function basePath() {
    var p = win.maccms && win.maccms.path ? String(win.maccms.path) : '';
    return p.replace(/\/+$/, '');
  }

  function loadPrefs() {
    try {
      var raw = win.localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (e) {
      return {};
    }
  }

  function savePrefs(prefs) {
    try {
      win.localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
    } catch (e) { /* ignore */ }
  }

  function saveGuestHistory(item) {
    try {
      var key = 'guest_play_history';
      var list = JSON.parse(win.localStorage.getItem(key) || '[]');
      list.unshift(item);
      if (list.length > 50) list.length = 50;
      win.localStorage.setItem(key, JSON.stringify(list));
    } catch (e) { /* ignore */ }
  }

  function recordPlayHistory(root) {
    var ulog = root.querySelector('[data-mac-novel-ulog]');
    if (!ulog) return;
    var uid = win.maccms && parseInt(win.maccms.userId, 10);
    if (uid > 0) {
      var q = [
        'ac=set',
        'mid=' + encodeURIComponent(ulog.getAttribute('data-mid') || '2'),
        'id=' + encodeURIComponent(ulog.getAttribute('data-id') || ''),
        'sid=' + encodeURIComponent(ulog.getAttribute('data-sid') || '1'),
        'nid=' + encodeURIComponent(ulog.getAttribute('data-nid') || '1'),
        'type=' + encodeURIComponent(ulog.getAttribute('data-type') || '4')
      ].join('&');
      fetch(basePath() + '/index.php/user/ajax_ulog/?' + q, { credentials: 'same-origin' }).catch(function () {});
      return;
    }
    var shareBtn = root.querySelector('.novel-player__share');
    saveGuestHistory({
      mid: 2,
      name: root.getAttribute('data-mac-novel-name') || '',
      link: shareBtn ? shareBtn.getAttribute('data-mac-share-url') || win.location.href : win.location.href,
      pic: root.getAttribute('data-mac-novel-pic') || '',
      part: root.getAttribute('data-mac-novel-part') || ''
    });
  }

  function applyPrefs(root, prefs) {
    var theme = prefs.theme || 'day';
    var font = prefs.font || 'md';
    root.setAttribute('data-mac-novel-theme', theme);
    root.setAttribute('data-mac-novel-font', font);
    root.querySelectorAll('.novel-player__settings [data-mac-novel-theme]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-mac-novel-theme') === theme);
    });
    root.querySelectorAll('.novel-player__settings [data-mac-novel-font]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-mac-novel-font') === font);
    });
  }

  function bindCatalog(root) {
    var catalog = doc.querySelector('[data-mac-novel-catalog]');
    if (!catalog) return;
    var openBtn = root.querySelector('[data-mac-novel-catalog-open]');
    var closers = catalog.querySelectorAll('[data-mac-novel-catalog-close]');

    function openCatalog() {
      catalog.hidden = false;
      catalog.setAttribute('aria-hidden', 'false');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
      doc.body.classList.add('novel-catalog-open');
      var active = catalog.querySelector('.novel-player__catalog-item.is-cur, .novel-player__catalog-item.is-last-read');
      if (!active) {
        active = catalog.querySelector('.novel-player__catalog-item.read, .novel-player__catalog-item.is-read');
      }
      var list = catalog.querySelector('.novel-player__catalog-list');
      if (active && list) {
        list.scrollTop = active.offsetTop - list.offsetTop - 48;
      }
    }

    function closeCatalog() {
      catalog.hidden = true;
      catalog.setAttribute('aria-hidden', 'true');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
      doc.body.classList.remove('novel-catalog-open');
    }

    if (openBtn) openBtn.addEventListener('click', openCatalog);
    closers.forEach(function (btn) {
      btn.addEventListener('click', closeCatalog);
    });
    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !catalog.hidden) closeCatalog();
    });
  }

  function bindSettings(root) {
    var panel = root.querySelector('[data-mac-novel-settings]');
    if (!panel) return;
    var openBtn = root.querySelector('[data-mac-novel-settings-open]');
    var closers = panel.querySelectorAll('[data-mac-novel-settings-close]');
    var prefs = loadPrefs();
    applyPrefs(root, prefs);

    function openSettings() {
      panel.hidden = false;
      panel.setAttribute('aria-hidden', 'false');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
      doc.body.classList.add('novel-settings-open');
    }

    function closeSettings() {
      panel.hidden = true;
      panel.setAttribute('aria-hidden', 'true');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
      doc.body.classList.remove('novel-settings-open');
    }

    if (openBtn) openBtn.addEventListener('click', openSettings);
    closers.forEach(function (btn) {
      btn.addEventListener('click', closeSettings);
    });

    root.addEventListener('click', function (ev) {
      var themeBtn = closest(ev.target, '.novel-player__settings [data-mac-novel-theme]');
      if (themeBtn) {
        prefs.theme = themeBtn.getAttribute('data-mac-novel-theme') || 'day';
        applyPrefs(root, prefs);
        savePrefs(prefs);
        return;
      }
      var fontBtn = closest(ev.target, '.novel-player__settings [data-mac-novel-font]');
      if (fontBtn) {
        prefs.font = fontBtn.getAttribute('data-mac-novel-font') || 'md';
        applyPrefs(root, prefs);
        savePrefs(prefs);
      }
    });

    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !panel.hidden) closeSettings();
    });
  }

  function bindKeyboard(root) {
    doc.addEventListener('keydown', function (ev) {
      if (ev.defaultPrevented) return;
      var tag = (ev.target && ev.target.tagName) || '';
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag)) return;
      if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') {
        var prev = root.getAttribute('data-prev-url');
        if (prev) { win.location.href = prev; }
      } else if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') {
        var next = root.getAttribute('data-next-url');
        if (next) { win.location.href = next; }
      }
    });
  }

  function bindBack(root) {
    var back = root.querySelector('[data-mac-novel-back]');
    if (!back) return;
    back.addEventListener('click', function (ev) {
      try {
        var len = parseInt(win.sessionStorage.getItem('novel_detail_history_length'), 10);
        if (!isNaN(len) && len > 0) {
          var steps = win.history.length - len;
          if (steps > 0) {
            ev.preventDefault();
            win.history.go(-steps);
          }
        }
      } catch (e) { /* ignore */ }
    });
  }

  function bindFavLabel(root) {
    var fav = root.querySelector('.novel-player__bar-item.mac_ulog');
    if (!fav || !win.MacTheme) return;
    win.MacTheme.on('ulog:changed', function (ev) {
      if (!ev || !ev.el || ev.el !== fav) return;
      var label = fav.querySelector('span');
      if (!label) return;
      label.textContent = ev.added ? '已收藏' : '收藏';
    });
  }

  function init(root) {
    if (!root || root._novelInited) return;
    root._novelInited = true;
    var jumpUrl = root.getAttribute('data-jumpurl');
    if (jumpUrl) {
      win.location.replace(jumpUrl);
      return;
    }
    bindCatalog(root);
    bindSettings(root);
    bindKeyboard(root);
    bindBack(root);
    bindFavLabel(root);
    recordPlayHistory(root);
    loadProgressJs(function () {
      if (win.MacReaderReadProgress && typeof win.MacReaderReadProgress.initNovelPlayer === 'function') {
        win.MacReaderReadProgress.initNovelPlayer(root);
      }
    });
  }

  function boot() {
    init(doc.querySelector('[data-mac-novel-player]'));
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
