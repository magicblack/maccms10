(function () {
  var root = document.querySelector('[data-component="np2_short_feed"][data-np2-short]');
  if (!root) return;
  var hlsSrc = root.getAttribute('data-hls') || '/static_new/player/dplayer/hls.min.js';
  var hlsPromise = null;

  function loadHls() {
    if (window.Hls) return Promise.resolve(window.Hls);
    if (hlsPromise) return hlsPromise;
    hlsPromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = hlsSrc;
      s.async = true;
      s.onload = function () { resolve(window.Hls); };
      s.onerror = function () { reject(new Error('hls')); };
      document.head.appendChild(s);
    });
    return hlsPromise;
  }

  function firstSrc(raw) {
    if (!raw) return '';
    var groups = String(raw).split('$$$');
    for (var g = 0; g < groups.length; g++) {
      var eps = groups[g].split('#');
      for (var e = 0; e < eps.length; e++) {
        var ep = eps[e];
        var i = ep.indexOf('$');
        var url = (i >= 0 ? ep.slice(i + 1) : ep).trim();
        if (!url || url.indexOf('invalid.failover') !== -1) continue;
        if (/^https?:\/\//i.test(url)) return url;
      }
    }
    return '';
  }

  function isHls(url) {
    return /\.m3u8(\?|$)/i.test(url);
  }

  function markPlaying(item, on) {
    item.classList.toggle('is-playing', !!on);
    var badge = item.querySelector('.np2-short__live');
    if (badge) badge.hidden = !on;
  }

  function destroy(item) {
    var video = item.querySelector('video');
    if (item._hls) {
      try { item._hls.destroy(); } catch (err) {}
      item._hls = null;
    }
    if (video) {
      try { video.pause(); } catch (err) {}
    }
    markPlaying(item, false);
  }

  function tryPlay(item) {
    var video = item.querySelector('video');
    if (!video) return;
    video.muted = true;
    video.controls = false;
    var p = video.play();
    if (p && p.then) {
      p.then(function () { markPlaying(item, true); }).catch(function () {
        markPlaying(item, !video.paused);
      });
    } else {
      markPlaying(item, !video.paused);
    }
  }

  function attach(item, url) {
    var video = item.querySelector('video');
    if (!video || !url) return Promise.resolve();
    if (item._bound === url && (item._hls || video.src)) {
      tryPlay(item);
      return Promise.resolve();
    }
    item._bound = url;
    if (isHls(url)) {
      return loadHls().then(function (Hls) {
        if (Hls && Hls.isSupported()) {
          if (item._hls) try { item._hls.destroy(); } catch (err) {}
          var hls = new Hls({ enableWorker: false, maxBufferLength: 8 });
          item._hls = hls;
          hls.loadSource(url);
          hls.attachMedia(video);
          hls.on(Hls.Events.MANIFEST_PARSED, function () { tryPlay(item); });
          hls.on(Hls.Events.ERROR, function (_ev, data) {
            if (data && data.fatal) markPlaying(item, false);
          });
          return;
        }
        video.src = url;
        tryPlay(item);
      }).catch(function () {
        video.src = url;
        tryPlay(item);
      });
    }
    video.src = url;
    tryPlay(item);
    return Promise.resolve();
  }

  function playItem(item) {
    attach(item, firstSrc(item.getAttribute('data-play')));
  }

  var items = root.querySelectorAll('.np2-short__item');
  if (!('IntersectionObserver' in window)) {
    if (items[0]) playItem(items[0]);
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      var item = en.target;
      if (en.isIntersecting && en.intersectionRatio > 0.65) playItem(item);
      else destroy(item);
    });
  }, { root: root, threshold: [0.65] });
  items.forEach(function (el) { io.observe(el); });
})();
