(function () {
  function formatRelativeWhen(ts) {
    var sec = parseInt(ts, 10);
    if (!sec || sec <= 0) return '';
    var diff = Math.floor(Date.now() / 1000) - sec;
    if (diff < 0) diff = 0;
    if (diff < 10) return '刚刚';
    if (diff < 60) return diff + '秒前';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    if (diff < 31536000) return Math.floor(diff / 86400) + '天前';
    return Math.floor(diff / 31536000) + '年前';
  }

  function applyRelativeWhen(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-component="vod_list_np2"] .np2-card__when[data-ts]').forEach(function (el) {
      var text = formatRelativeWhen(el.getAttribute('data-ts'));
      if (text) el.textContent = text;
    });
  }

  function boot() {
    applyRelativeWhen(document);
    document.querySelectorAll('[data-component="vod_list_np2"] [data-np2-shuffle]').forEach(function (btn) {
      if (btn.dataset.np2ShuffleBound) return;
      btn.dataset.np2ShuffleBound = '1';
      btn.addEventListener('click', function () {
        var row = btn.closest('.np2-row');
        var wrap = row && row.querySelector('.np2-cards');
        if (!wrap) return;
        var cards = Array.prototype.slice.call(wrap.children);
        for (var i = cards.length - 1; i > 0; i--) {
          var j = Math.floor(Math.random() * (i + 1));
          var t = cards[i];
          cards[i] = cards[j];
          cards[j] = t;
        }
        cards.forEach(function (el) { wrap.appendChild(el); });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
