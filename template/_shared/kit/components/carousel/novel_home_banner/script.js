/**
 * novel_home_banner — 横向书封轮播（箭头控制，无圆点）
 * 滚动容器为 .nv-banner__viewport（与 nv-row 一致），避免 flex track 在 overflow:clip 祖先下无法横滑。
 */
(function (win, doc) {
  'use strict';

  function getViewport(root) {
    return root ? root.querySelector('.nv-banner__viewport') : null;
  }

  function canBannerScroll(viewport) {
    return viewport && viewport.scrollWidth > viewport.clientWidth + 4;
  }

  function stepSize(viewport) {
    return Math.max(240, Math.round(viewport.clientWidth * 0.78));
  }

  function syncBtns(root, viewport) {
    var prev = root.querySelector('.nv-banner__btn--prev');
    var next = root.querySelector('.nv-banner__btn--next');
    var max = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
    var left = viewport.scrollLeft;
    var scrollable = max > 4;
    root.classList.toggle('is-scrollable', scrollable);
    if (!prev || !next) return;
    prev.disabled = !scrollable || left <= 2;
    next.disabled = !scrollable || left >= max - 2;
  }

  function initBanner(root) {
    if (!root || root._nvBannerInited) return;
    var viewport = getViewport(root);
    var slides = Array.prototype.slice.call(root.querySelectorAll('.nv-banner__slide'));
    if (!viewport || slides.length < 2) return;
    root._nvBannerInited = true;

    var timer = null;
    var reduce = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function paint(dir) {
      if (!canBannerScroll(viewport)) {
        syncBtns(root, viewport);
        return;
      }
      var max = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
      var left = viewport.scrollLeft;
      var step = Math.min(stepSize(viewport), Math.max(180, viewport.clientWidth * 0.82));
      var nextLeft = left + dir * step;
      if (dir > 0) {
        if (nextLeft >= max - 2) {
          nextLeft = left >= max - 2 ? 0 : max;
        }
      } else if (nextLeft <= 2) {
        nextLeft = left <= 2 ? max : 0;
      }
      viewport.scrollTo({ left: nextLeft, behavior: reduce ? 'auto' : 'smooth' });
    }

    function arm() {
      clearInterval(timer);
      if (reduce || !canBannerScroll(viewport)) return;
      timer = setInterval(function () { paint(1); }, 4800);
    }

    root.addEventListener('click', function (e) {
      var btn = e.target.closest('.nv-banner__btn');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      var dir = parseInt(btn.getAttribute('data-dir'), 10) || 1;
      paint(dir);
      arm();
    });

    viewport.addEventListener('scroll', function () {
      syncBtns(root, viewport);
    }, { passive: true });

    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', arm);

    if (win.ResizeObserver) {
      var ro = new ResizeObserver(function () {
        syncBtns(root, viewport);
        arm();
      });
      ro.observe(viewport);
    }

    syncBtns(root, viewport);
    arm();
  }

  function boot() {
    var nodes = doc.querySelectorAll('[data-component="novel_home_banner"][data-nv-banner]');
    Array.prototype.forEach.call(nodes, initBanner);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
