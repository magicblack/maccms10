/**
 * twigspike 直播播放器：HLS 兼容、线路切换、选台抽屉
 */
(function (win, doc) {
  'use strict';

  var hlsLoading = null;

  function closest(el, sel) {
    while (el && el.nodeType === 1) {
      if (el.matches(sel)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function isHlsUrl(url) {
    var u = String(url || '').toLowerCase();
    return u.indexOf('.m3u8') > -1 || u.indexOf('application/vnd.apple.mpegurl') > -1;
  }

  function canNativeHls(video) {
    return video && typeof video.canPlayType === 'function'
      && video.canPlayType('application/vnd.apple.mpegurl') !== '';
  }

  function loadScript(src) {
    if (!src) return Promise.reject(new Error('missing src'));
    if (hlsLoading) return hlsLoading;
    hlsLoading = new Promise(function (resolve, reject) {
      if (win.Hls) {
        resolve(win.Hls);
        return;
      }
      var s = doc.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = function () { resolve(win.Hls); };
      s.onerror = function () { reject(new Error('hls load fail')); };
      doc.head.appendChild(s);
    });
    return hlsLoading;
  }

  function destroyHls(instance) {
    if (instance && typeof instance.destroy === 'function') {
      try { instance.destroy(); } catch (e) { /* ignore */ }
    }
    return null;
  }

  function setFail(root, show) {
    var box = root.querySelector('[data-mac-live-fail]');
    if (!box) return;
    if (show) {
      box.hidden = false;
      box.setAttribute('aria-hidden', 'false');
    } else {
      box.hidden = true;
      box.setAttribute('aria-hidden', 'true');
    }
  }

  function setActiveLine(root, index) {
    root.querySelectorAll('[data-mac-live-line]').forEach(function (btn) {
      var on = String(btn.getAttribute('data-mac-live-line')) === String(index);
      btn.classList.toggle('is-on', on);
    });
  }

  function getLineUrl(root, index) {
    var btn = root.querySelector('[data-mac-live-line="' + index + '"]');
    if (btn) return btn.getAttribute('data-mac-live-url') || '';
    if (index === 0) return root.getAttribute('data-initial-url') || '';
    return '';
  }

  function playVideo(video) {
    if (!video || typeof video.play !== 'function') return;
    var p = video.play();
    if (p && typeof p.catch === 'function') p.catch(function () {});
  }

  function attachPlayer(root) {
    var video = root.querySelector('.live-player__video');
    if (!video) return { destroy: function () {} };

    var hls = null;
    var lineIndex = 0;
    var from = root.getAttribute('data-mac-live-from') || '';
    var hlsLib = root.getAttribute('data-hls-lib') || '';

    function currentUrl() {
      return getLineUrl(root, lineIndex) || root.getAttribute('data-initial-url') || '';
    }

    function bindVideoEvents() {
      video.addEventListener('error', function () { setFail(root, true); });
      video.addEventListener('canplay', function () { setFail(root, false); });
      video.addEventListener('loadeddata', function () { setFail(root, false); });
    }

    function loadUrl(url) {
      if (!url) {
        setFail(root, true);
        return Promise.resolve();
      }
      setFail(root, false);
      hls = destroyHls(hls);

      var lowerFrom = String(from).toLowerCase();
      var useHls = isHlsUrl(url) || lowerFrom === 'hls' || lowerFrom === 'm3u8';

      if (useHls && !canNativeHls(video)) {
        return loadScript(hlsLib).then(function (Hls) {
          if (!Hls || !Hls.isSupported || !Hls.isSupported()) {
            video.removeAttribute('src');
            video.src = url;
            playVideo(video);
            return;
          }
          hls = new Hls({ enableWorker: true, lowLatencyMode: true });
          hls.on(Hls.Events.ERROR, function (_evt, data) {
            if (data && data.fatal) setFail(root, true);
          });
          hls.loadSource(url);
          hls.attachMedia(video);
          hls.on(Hls.Events.MANIFEST_PARSED, function () {
            setFail(root, false);
            playVideo(video);
          });
        }).catch(function () {
          video.removeAttribute('src');
          video.src = url;
          playVideo(video);
        });
      }

      video.removeAttribute('src');
      video.src = url;
      playVideo(video);
      return Promise.resolve();
    }

    function switchLine(index) {
      lineIndex = parseInt(index, 10) || 0;
      setActiveLine(root, lineIndex);
      return loadUrl(currentUrl());
    }

    bindVideoEvents();
    switchLine(0);

    return {
      retry: function () { return switchLine(lineIndex); },
      switchLine: switchLine,
      destroy: function () {
        hls = destroyHls(hls);
        if (video) {
          try { video.pause(); } catch (e) { /* ignore */ }
          video.removeAttribute('src');
          video.load();
        }
      }
    };
  }

  function bindChannels(root) {
    var panel = root.querySelector('[data-mac-live-channels]');
    if (!panel) { return; }
    var scope = root.closest('[data-component="live_player"]') || root;
    var openBtn = scope.querySelector('[data-mac-live-channels-open]');
    var closers = panel.querySelectorAll('[data-mac-live-channels-close]');

    function openPanel() {
      panel.hidden = false;
      panel.setAttribute('aria-hidden', 'false');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
      doc.body.classList.add('live-channels-open');
      var active = panel.querySelector('.live-player__channel.is-cur');
      var list = panel.querySelector('.live-player__channels-list');
      if (active && list) {
        list.scrollTop = active.offsetTop - list.offsetTop - 48;
      }
    }

    function closePanel() {
      panel.hidden = true;
      panel.setAttribute('aria-hidden', 'true');
      if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
      doc.body.classList.remove('live-channels-open');
    }

    if (openBtn) openBtn.addEventListener('click', openPanel);
    closers.forEach(function (el) {
      el.addEventListener('click', closePanel);
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) closePanel();
    });
  }

  function initRoot(root) {
    var player = attachPlayer(root);
    root._livePlayer = player;
    bindChannels(root);

    var scope = root.closest('[data-component="live_player"]') || root;

    scope.querySelectorAll('[data-mac-live-line]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        player.switchLine(btn.getAttribute('data-mac-live-line'));
      });
    });

    scope.querySelectorAll('[data-mac-live-retry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        player.retry();
      });
    });

    win.addEventListener('pagehide', function () {
      player.destroy();
      root._livePlayer = null;
    }, { once: true });
  }

  doc.addEventListener('click', function (e) {
    var btn = closest(e.target, '[data-mac-live-retry]');
    if (!btn || closest(btn, '[data-mac-live-player]')) return;
    var root = doc.querySelector('[data-mac-live-player]');
    if (root && root._livePlayer && typeof root._livePlayer.retry === 'function') {
      root._livePlayer.retry();
    }
  });

  function boot() {
    doc.querySelectorAll('[data-mac-live-player]').forEach(initRoot);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
