/**
 * manga_mosaic_hero — twigspike manga/index mosaic carousel
 */
(function (win, doc) {
  'use strict';

  function initMosaic(root) {
    if (!root || root._mosaicInited) return;
    root._mosaicInited = true;
    var vp = root.querySelector('.mc-mosaic__viewport');
    var track = root.querySelector('.mc-mosaic__track');
    var anchors = Array.prototype.slice.call(root.querySelectorAll('.mc-mosaic__group'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('.mc-mosaic__dot'));
    var btns = Array.prototype.slice.call(root.querySelectorAll('.mc-mosaic__nav'));
    if (!vp || !track || anchors.length < 1) return;
    var i = 0;
    var timer = null;
    var n = anchors.length;
    var mobileMQ = win.matchMedia ? win.matchMedia('(max-width: 900px)') : null;

    function isMobile() { return !!(mobileMQ && mobileMQ.matches); }

    function scrollGroupInViewport(el) {
      if (!el || !vp) return;
      var left = el.offsetLeft;
      var reduce = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (typeof vp.scrollTo === 'function') {
        vp.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' });
      } else {
        vp.scrollLeft = left;
      }
    }

    function paint() {
      anchors.forEach(function (c, k) { c.classList.toggle('is-focus', k === i); });
      dots.forEach(function (d) {
        d.classList.toggle('is-on', Math.floor((parseInt(d.getAttribute('data-i'), 10) || 0) / 6) === i);
      });
      var el = anchors[i];
      if (!el) return;
      if (isMobile()) {
        /* 只滚横向 viewport；scrollIntoView 会连带 window 竖向滚回顶部封面区 */
        scrollGroupInViewport(el);
        return;
      }
      var focusCard = el.querySelector('.mc-mosaic__card--tall') || el;
      var anchorX = el.offsetLeft + focusCard.offsetLeft + focusCard.offsetWidth / 2;
      var targetX = vp.clientWidth * 0.36;
      var left = anchorX - targetX;
      var max = Math.max(0, track.scrollWidth - vp.clientWidth);
      if (left < 0) left = 0;
      if (left > max) left = max;
      track.style.transform = 'translateX(' + (-left) + 'px)';
    }

    function go(to) { i = (to + n) % n; paint(); }

    function syncFromScroll() {
      if (!isMobile()) return;
      var closest = 0;
      var minDist = Infinity;
      anchors.forEach(function (c, k) {
        var d = Math.abs(c.offsetLeft - vp.scrollLeft);
        if (d < minDist) { minDist = d; closest = k; }
      });
      i = closest;
      anchors.forEach(function (c, k) { c.classList.toggle('is-focus', k === i); });
      dots.forEach(function (d) {
        d.classList.toggle('is-on', Math.floor((parseInt(d.getAttribute('data-i'), 10) || 0) / 6) === i);
      });
    }

    function arm() {
      clearInterval(timer);
      if (win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = setInterval(function () { go(i + 1); }, 5200);
    }

    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        go(Math.floor((parseInt(d.getAttribute('data-i'), 10) || 0) / 6));
        arm();
      });
    });
    btns.forEach(function (b) {
      b.addEventListener('click', function () { go(i + (parseInt(b.getAttribute('data-dir'), 10) || 1)); arm(); });
    });
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', arm);
    win.addEventListener('resize', paint);
    var scrollRAF = null;
    vp.addEventListener('scroll', function () {
      if (scrollRAF) return;
      scrollRAF = win.requestAnimationFrame(function () { scrollRAF = null; syncFromScroll(); });
    }, { passive: true });
    paint();
    arm();
  }

  function boot() {
    var nodes = doc.querySelectorAll('[data-component="manga_mosaic_hero"][data-mc-mosaic]');
    Array.prototype.forEach.call(nodes, initMosaic);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
