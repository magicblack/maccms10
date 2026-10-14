/**
 * twigspike 漫画阅读器：目录抽屉、键盘切话、阅读记录、长图懒载、章节已读
 */
(function (win, doc) {
  'use strict';

  var PROGRESS_LOADED = false;

  function closest(el, sel) {
    while (el && el.nodeType === 1) {
      if (el.matches(sel)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function basePath() {
    var p = win.maccms && win.maccms.path ? String(win.maccms.path) : '';
    return p.replace(/\/+$/, '');
  }

  function tplBase() {
    var m = win.maccms || {};
    var p = m.path_tpl || '';
    return String(p).replace(/\/+$/, '') + '/page-builder-components/_shared/';
  }

  function loadProgressJs(cb) {
    if (PROGRESS_LOADED || win.MacReaderReadProgress || win.MacMangaReadProgress) {
      PROGRESS_LOADED = true;
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
    s.onload = function () {
      PROGRESS_LOADED = true;
      if (cb) cb();
    };
    doc.head.appendChild(s);
  }

  function saveGuestHistory(item) {
    try {
      var key = 'guest_play_history';
      var list = JSON.parse(win.localStorage.getItem(key) || '[]');
      if (!Array.isArray(list)) list = [];
      list = list.filter(function (v) { return v && v.link && v.link !== item.link; });
      list.unshift(item);
      if (list.length > 60) list = list.slice(0, 60);
      win.localStorage.setItem(key, JSON.stringify(list));
    } catch (e) { /* ignore */ }
  }

  function isLoggedInForUlog() {
    var uid = win.maccms && parseInt(win.maccms.userId, 10);
    if (uid > 0) return true;
    try {
      if (typeof win.MAC !== 'undefined' && win.MAC.User) {
        if (Number(win.MAC.User.IsLogin) === 1) return true;
        if (typeof win.MAC.User.getAuthMe === 'function') {
          var me = win.MAC.User.getAuthMe();
          if (me && Number(me.is_login) === 1) return true;
        }
      }
      if (typeof win.MAC !== 'undefined' && win.MAC.Cookie && typeof win.MAC.Cookie.Get === 'function') {
        var cid = win.MAC.Cookie.Get('user_id');
        if (cid != null && String(cid).trim() !== '') return true;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function recordPlayHistory(root) {
    var ulog = root.querySelector('[data-mac-manga-ulog]');
    if (!ulog) return;
    if (isLoggedInForUlog()) {
      var q = [
        'ac=set',
        'mid=' + encodeURIComponent(ulog.getAttribute('data-mid') || '12'),
        'id=' + encodeURIComponent(ulog.getAttribute('data-id') || ''),
        'sid=' + encodeURIComponent(ulog.getAttribute('data-sid') || '1'),
        'nid=' + encodeURIComponent(ulog.getAttribute('data-nid') || '1'),
        'type=' + encodeURIComponent(ulog.getAttribute('data-type') || '4')
      ].join('&');
      fetch(basePath() + '/index.php/user/ajax_ulog/?' + q, { credentials: 'same-origin' }).catch(function () {});
      return;
    }
    var shareBtn = root.querySelector('.manga-player__share');
    saveGuestHistory({
      mid: 12,
      name: root.getAttribute('data-mac-manga-name') || '',
      link: shareBtn ? shareBtn.getAttribute('data-mac-share-url') || win.location.href : win.location.href,
      pic: root.getAttribute('data-mac-manga-pic') || '',
      part: root.getAttribute('data-mac-manga-part') || ''
    });
  }

  function bindCatalog(root) {
    var catalog = root.querySelector('[data-mac-manga-catalog]');
    if (!catalog) return;
    var openBtn = root.querySelector('[data-mac-manga-catalog-open]');
    var closers = catalog.querySelectorAll('[data-mac-manga-catalog-close]');

    function openCatalog() {
      catalog.hidden = false;
      catalog.setAttribute('aria-hidden', 'false');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
      doc.body.classList.add('manga-catalog-open');
      var active = catalog.querySelector('.manga-player__catalog-item.is-cur, .manga-player__catalog-item.is-last-read');
      if (!active) {
        active = catalog.querySelector('.manga-player__catalog-item.is-read');
      }
      var list = catalog.querySelector('.manga-player__catalog-list');
      if (active && list) {
        list.scrollTop = active.offsetTop - list.offsetTop - 48;
      }
    }

    function closeCatalog() {
      catalog.hidden = true;
      catalog.setAttribute('aria-hidden', 'true');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
      doc.body.classList.remove('manga-catalog-open');
    }

    if (openBtn) openBtn.addEventListener('click', openCatalog);
    closers.forEach(function (btn) {
      btn.addEventListener('click', closeCatalog);
    });
    doc.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !catalog.hidden) closeCatalog();
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
    var back = root.querySelector('[data-mac-manga-back]');
    if (!back) return;
    back.addEventListener('click', function (ev) {
      try {
        var len = parseInt(win.sessionStorage.getItem('manga_detail_history_length'), 10);
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

  function bindLazy(root) {
    var imgs = root.querySelectorAll('img[data-mac-manga-lazy]');
    if (!imgs.length) return;
    if (!('IntersectionObserver' in win)) {
      imgs.forEach(function (img) {
        img.src = img.getAttribute('data-mac-manga-lazy');
        img.removeAttribute('data-mac-manga-lazy');
      });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var img = entry.target;
        var src = img.getAttribute('data-mac-manga-lazy');
        if (src) img.src = src;
        img.removeAttribute('data-mac-manga-lazy');
        io.unobserve(img);
      });
    }, { rootMargin: '240px 0px' });
    imgs.forEach(function (img) { io.observe(img); });
  }

  function bindFavLabel(root) {
    var fav = root.querySelector('.manga-player__bar-item.mac_ulog');
    if (!fav || !win.MacTheme) return;
    win.MacTheme.on('ulog:changed', function (ev) {
      if (!ev || !ev.el || ev.el !== fav) return;
      var label = fav.querySelector('span');
      if (!label) return;
      label.textContent = ev.added ? '已收藏' : '收藏';
    });
  }

  function init(root) {
    if (!root || root._mangaInited) return;
    root._mangaInited = true;
    var jumpUrl = root.getAttribute('data-jumpurl');
    if (jumpUrl) {
      win.location.replace(jumpUrl);
      return;
    }
    bindCatalog(root);
    bindKeyboard(root);
    bindBack(root);
    bindLazy(root);
    bindFavLabel(root);
    recordPlayHistory(root);
    loadProgressJs(function () {
      var prog = win.MacReaderReadProgress || win.MacMangaReadProgress;
      if (prog && typeof prog.initMangaPlayer === 'function') prog.initMangaPlayer(root);
      else if (prog && typeof prog.initPlayer === 'function') prog.initPlayer(root);
    });
  }

  doc.addEventListener('DOMContentLoaded', function () {
    init(doc.querySelector('[data-mac-manga-player]'));
  });
})(window, document);
