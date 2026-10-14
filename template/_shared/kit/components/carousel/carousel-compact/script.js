(function () {
  'use strict';
  function init(root) {
    if (!root) return;
    var items = root.querySelectorAll('.pb-ccc__item');
    var dots = root.querySelectorAll('.pb-ccc__dot');
    var label = root.querySelector('[data-pb-ccc-label]');
    if (!items.length) return;
    var i = 0, timer = null, n = items.length;
    function go(to) {
      i = (to + n) % n;
      items.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });
      dots.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });
      if (label && dots[i]) {
        label.textContent = dots[i].getAttribute('data-title') || '';
      }
    }
    function next() { go(i + 1); }
    function arm() {
      clearInterval(timer);
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = setInterval(next, 4000);
    }
    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        go(parseInt(d.getAttribute('data-i'), 10) || 0);
        arm();
      });
    });
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', arm);
    arm();
  }
  function boot() {
    document.querySelectorAll('[data-pb-carousel-compact] [data-pb-ccc-root]').forEach(init);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
