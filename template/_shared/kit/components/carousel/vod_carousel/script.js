(function () {
  'use strict';
  function initCarousel(root) {
    if (!root) return;
    var slides = root.querySelectorAll('.bili-feed__slide');
    var dots = root.querySelectorAll('.bili-feed__dot');
    var btns = root.querySelectorAll('.bili-feed__btn');
    if (!slides.length) return;
    var i = 0, timer = null, n = slides.length;
    function go(to) {
      i = (to + n) % n;
      slides.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });
      dots.forEach(function (el, k) { el.classList.toggle('is-on', k === i); });
    }
    function next(d) { go(i + (d || 1)); }
    function arm() {
      clearInterval(timer);
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = setInterval(function () { next(1); }, 4500);
    }
    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        go(parseInt(d.getAttribute('data-i'), 10) || 0);
        arm();
      });
    });
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        next(parseInt(b.getAttribute('data-dir'), 10) || 1);
        arm();
      });
    });
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', arm);
    arm();
  }
  function boot() {
    document.querySelectorAll('[data-pb-vc-root], [data-bili-banner]').forEach(initCarousel);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
