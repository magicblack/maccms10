/**
 * manga_home_row — mc-row horizontal scroll (twigspike scroll-row)
 */
(function (win, doc) {
  'use strict';
  var bound = false;

  function scrollRow(btn) {
    var row = btn.closest('[data-mc-row]');
    if (!row) return;
    var vp = row.querySelector('.mc-row__viewport');
    if (!vp) return;
    var dir = parseInt(btn.getAttribute('data-dir'), 10) || 1;
    var step = Math.max(220, vp.clientWidth * 0.82);
    var reduce = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;
    vp.scrollBy({ left: dir * step, behavior: reduce ? 'auto' : 'smooth' });
  }

  function bind() {
    if (bound) return;
    bound = true;
    doc.addEventListener('click', function (e) {
      var btn = e.target.closest('.mc-row__btn');
      if (!btn || !btn.closest('[data-component="manga_home_row"]')) return;
      e.preventDefault();
      scrollRow(btn);
    });
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})(window, document);
