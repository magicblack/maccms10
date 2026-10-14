/**
 * manga_home_rank — tabbed rank boards
 */
(function (win, doc) {
  'use strict';

  function initRank(root) {
    if (!root || root._rankInited) return;
    root._rankInited = true;
    var tabs = root.querySelectorAll('[data-rank-tab]');
    var boards = root.querySelectorAll('[data-rank-board]');
    Array.prototype.forEach.call(tabs, function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-rank-tab');
        Array.prototype.forEach.call(tabs, function (b) {
          var on = b === btn;
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        Array.prototype.forEach.call(boards, function (board) {
          var on = board.getAttribute('data-rank-board') === key;
          board.classList.toggle('is-on', on);
          board.hidden = !on;
        });
      });
    });
  }

  function boot() {
    var nodes = doc.querySelectorAll('[data-component="manga_home_rank"][data-rankpage]');
    Array.prototype.forEach.call(nodes, initRank);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
