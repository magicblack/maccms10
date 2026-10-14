/**
 * novel_home_zone — nv-row horizontal scroll (shared with novel_home_row)
 */
(function (win, doc) {
  'use strict';
  var bound = false;

  function scrollRow(btn) {
    var row = btn.closest('[data-nv-row]');
    if (!row) return;
    var vp = row.querySelector('.nv-row__viewport');
    if (!vp) return;
    var dir = parseInt(btn.getAttribute('data-dir'), 10) || 1;
    var step = Math.max(200, vp.clientWidth * 0.82);
    var reduce = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;
    vp.scrollBy({ left: dir * step, behavior: reduce ? 'auto' : 'smooth' });
  }

  function bind() {
    if (bound) return;
    bound = true;
    doc.addEventListener('click', function (e) {
      var btn = e.target.closest('.nv-row__btn');
      if (!btn) return;
      var host = btn.closest('[data-component="novel_home_zone"]');
      if (!host) return;
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
