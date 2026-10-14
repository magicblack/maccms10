/**
 * twigspike 分页跳转 — components/paging.twig
 */
(function (win, doc) {
  'use strict';

  function buildPageUrl(baseUrl, sp, page) {
    var n = parseInt(page, 10);
    if (isNaN(n) || n < 1) n = 1;
    var url = String(baseUrl || '');
    var separator = sp != null ? String(sp) : '';
    if (separator) {
      url = url.split(separator + 'PAGELINK').join(n > 1 ? separator + n : '');
    }
    return url.split('PAGELINK').join(String(n));
  }

  function bindJump(root) {
    if (!root || root.getAttribute('data-mac-paging-bound') === '1') return;
    var input = root.querySelector('.bili-paging__jump-input');
    var btn = root.querySelector('[data-mac-paging-jump]');
    if (!input || !btn) return;

    root.setAttribute('data-mac-paging-bound', '1');
    var total = parseInt(root.getAttribute('data-page-total'), 10) || 1;
    var baseUrl = root.getAttribute('data-page-url') || '';
    var sp = root.getAttribute('data-page-sp') || '';

    function go() {
      var val = parseInt(input.value, 10);
      if (isNaN(val) || val < 1) {
        input.focus();
        return;
      }
      if (val > total) val = total;
      win.location.href = buildPageUrl(baseUrl, sp, val);
    }

    btn.addEventListener('click', go);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        go();
      }
    });
  }

  function boot() {
    doc.querySelectorAll('[data-mac-paging]').forEach(bindJump);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
