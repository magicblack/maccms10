(function () {
  'use strict';

  function activate(root, sort) {
    root.querySelectorAll('[data-vod-sort]').forEach(function (tab) {
      var on = tab.getAttribute('data-vod-sort') === sort;
      tab.classList.toggle('is-on', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-vod-sort-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-vod-sort-panel') !== sort;
    });
  }

  function init(root) {
    var tabs = root.querySelectorAll('[data-vod-sort]');
    if (!tabs.length) {
      return;
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(root, tab.getAttribute('data-vod-sort'));
      });
    });
  }

  function boot() {
    document.querySelectorAll('[data-component="topic_detail"] .pb-topic-detail__vod').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
