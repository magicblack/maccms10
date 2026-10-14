/**
 * art_detail — 章节已读标记与「继续阅读」（对齐 default art/detail）
 */
(function (win, doc) {
  'use strict';

  function tplBase() {
    var m = win.maccms || {};
    var p = m.path_tpl || '';
    return String(p).replace(/\/+$/, '') + '/page-builder-components/_shared/';
  }

  function loadProgressJs(cb) {
    if (win.MacReaderReadProgress) {
      if (cb) cb();
      return;
    }
    if (doc.querySelector('script[data-reader-read-progress="1"]')) {
      if (cb) cb();
      return;
    }
    var s = doc.createElement('script');
    s.src = tplBase() + 'js/reader-read-progress.js?v=5';
    s.defer = true;
    s.setAttribute('data-reader-read-progress', '1');
    s.onload = function () { if (cb) cb(); };
    doc.head.appendChild(s);
  }

  function init(root) {
    if (!root || root._artDetailInited) return;
    root._artDetailInited = true;
    try {
      win.sessionStorage.setItem('novel_detail_history_length', String(win.history.length));
    } catch (e) { /* ignore */ }
    loadProgressJs(function () {
      if (win.MacReaderReadProgress && typeof win.MacReaderReadProgress.initArtDetail === 'function') {
        win.MacReaderReadProgress.initArtDetail(root);
      }
    });
  }

  doc.addEventListener('DOMContentLoaded', function () {
    init(doc.querySelector('[data-mac-art-detail]'));
  });
})(window, document);
