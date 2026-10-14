(function () {
  'use strict';

  function periodFromHash() {
    var hash = (window.location.hash || '').replace(/^#/, '').trim().toLowerCase();
    return hash === 'day' || hash === 'week' || hash === 'month' ? hash : '';
  }

  function activate(root, period) {
    if (!period) {
      period = root.getAttribute('data-default-period') || 'week';
    }
    if (period !== 'day' && period !== 'week' && period !== 'month') {
      period = 'week';
    }
    root.setAttribute('data-period', period);
    root.querySelectorAll('[data-period]').forEach(function (tab) {
      if (!tab.classList.contains('pb-rank-period__tab')) {
        return;
      }
      var on = tab.getAttribute('data-period') === period;
      tab.classList.toggle('is-on', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-rank-period-panel]').forEach(function (panel) {
      var show = panel.getAttribute('data-rank-period-panel') === period;
      panel.hidden = !show;
    });
  }

  function init(root) {
    activate(root, periodFromHash());
    window.addEventListener('hashchange', function () {
      activate(root, periodFromHash());
    });
  }

  function boot() {
    document.querySelectorAll('[data-component="rank_period_board"]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
