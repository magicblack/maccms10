(function () {
  'use strict';

  function initRankBoard(root) {
    if (!root || root.getAttribute('data-np2-rank-inited') === '1') {
      return;
    }
    root.setAttribute('data-np2-rank-inited', '1');
    var tabs = root.querySelectorAll('.np2-rank__period[data-period]');
    var panels = root.querySelectorAll('[data-np2-rank-panel]');
    if (!tabs.length || !panels.length) {
      return;
    }
    tabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-period');
        if (!key) {
          return;
        }
        tabs.forEach(function (t) {
          var on = t === btn;
          t.classList.toggle('is-on', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
          var show = panel.getAttribute('data-np2-rank-panel') === key;
          if (show) {
            panel.removeAttribute('hidden');
          } else {
            panel.setAttribute('hidden', 'hidden');
          }
        });
      });
    });
  }

  function applyHashPeriod() {
    var key = (location.hash || '').replace('#', '');
    if (key !== 'day' && key !== 'week' && key !== 'month') {
      return;
    }
    document.querySelectorAll('[data-component="np2_rank_board"]').forEach(function (root) {
      var btn = root.querySelector('.np2-rank__period[data-period="' + key + '"]');
      if (btn) {
        btn.click();
      }
    });
  }

  function boot() {
    document.querySelectorAll('[data-component="np2_rank_board"]').forEach(initRankBoard);
    applyHashPeriod();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('hashchange', applyHashPeriod);
})();
