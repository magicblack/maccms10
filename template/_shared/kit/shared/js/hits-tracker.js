/**
 * 播放/阅读页人气计数上报。
 *
 * 背景：default 主题靠 static_new/js/home.js 里的 MAC.Hits.Init() —— 页面里放一个
 * `.mac_hits[data-mid][data-id]` 标记，home.js 读它触发 index.php/ajax/hits?...&type=update。
 * spike 是完全独立的 twig/page-builder 主题，从不加载 home.js，也没有任何地方调用过
 * 这个接口 —— 所以视频播放、小说阅读、漫画阅读一次都不会触发人气自增，不管是当页显示
 * 还是后台 vod_hits/art_hits/manga_hits 字段，本质上都卡在“从没发过这个请求”这一步。
 *
 * 这里给 vod_player / manga_player / novel_player 三个播放器组件的根节点补上
 * data-mid（1/12/2，对应 mac_get_mid_code()）与 data-id，页面加载时上报一次，
 * 命中同一条 index.php/ajax/hits 接口，与 default 主题共用同一套后端累加逻辑。
 */
(function (win, doc) {
  'use strict';

  function apiBase() {
    var p = (win.maccms && win.maccms.path != null) ? String(win.maccms.path) : '';
    return p.replace(/\/+$/, '');
  }

  function findTarget() {
    var nodes = doc.querySelectorAll(
      '[data-component="vod_player"][data-mid][data-id],' +
      '[data-component="manga_player"][data-mid][data-id],' +
      '[data-component="novel_player"][data-mid][data-id]'
    );
    for (var i = 0; i < nodes.length; i++) {
      var mid = parseInt(nodes[i].getAttribute('data-mid'), 10) || 0;
      var id = parseInt(nodes[i].getAttribute('data-id'), 10) || 0;
      if (mid > 0 && id > 0) {
        return { mid: mid, id: id };
      }
    }
    return null;
  }

  function report(target) {
    var url = apiBase() + '/index.php/ajax/hits?mid=' + target.mid + '&id=' + target.id + '&type=update';
    if (typeof win.fetch === 'function') {
      win.fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).catch(function () {});
      return;
    }
    try {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.send();
    } catch (e) {}
  }

  function boot() {
    if (win.__macHitsReported) {
      return;
    }
    var target = findTarget();
    if (!target) {
      return;
    }
    win.__macHitsReported = true;
    report(target);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
