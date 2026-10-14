/**
 * manga_home_spotlight — twigspike pick / spotlight
 */
(function (win, doc) {
  'use strict';

  function formatDesc(raw, max) {
    max = max || 72;
    raw = (raw || '').replace(/\s+/g, ' ').trim();
    if (!raw) return '暂无简介';
    if (raw.length <= max) return raw;
    return raw.slice(0, max) + '…';
  }

  function renderPick(root, btn) {
    if (!root || !btn) return;
    var name = root.querySelector('[data-spot-name]');
    var tags = root.querySelector('[data-spot-tags]');
    var desc = root.querySelector('[data-spot-desc]');
    var link = root.querySelector('[data-spot-link]');
    var bg = root.querySelector('[data-spot-bg]');
    var tagList = (btn.getAttribute('data-tags') || '').split(',').filter(Boolean).slice(0, 3);
    var nextImg = btn.getAttribute('data-img') || '';
    if (name) name.textContent = btn.getAttribute('data-name') || '';
    if (desc) desc.textContent = formatDesc(btn.getAttribute('data-desc') || '');
    if (link) link.setAttribute('href', btn.getAttribute('data-href') || '#');
    root.querySelectorAll('[data-spot-art]').forEach(function (node) {
      node.setAttribute('href', btn.getAttribute('data-href') || '#');
      var label = btn.getAttribute('data-name') || '';
      if (label) {
        node.setAttribute('aria-label', label);
      }
    });
    /* 封面為空也要換：空 src 會觸發 error，由 runtime cover 模組換成預設縮略圖 */
    if (bg) {
      if (bg.getAttribute('data-current') !== nextImg) {
        bg.setAttribute('data-current', nextImg);
        bg.style.opacity = '0';
        setTimeout(function () {
          bg.src = nextImg;
          bg.style.opacity = '1';
        }, 140);
      }
    }
    var img = root.querySelector('[data-spot-img]');
    if (img) {
      var next = nextImg;
      if (img.getAttribute('src') !== next) {
        img.style.opacity = '0';
        setTimeout(function () {
          img.src = next;
          img.alt = btn.getAttribute('data-name') || '';
          img.style.opacity = '1';
        }, 120);
      } else {
        img.alt = btn.getAttribute('data-name') || '';
      }
    }
    if (tags) {
      tags.innerHTML = '';
      if (!tagList.length) {
        var em = doc.createElement('span');
        em.textContent = '漫画';
        tags.appendChild(em);
      } else {
        tagList.forEach(function (t) {
          var s = doc.createElement('span');
          s.textContent = t.trim();
          tags.appendChild(s);
        });
      }
    }
    root.querySelectorAll('.mc-spot__thumb').forEach(function (t) {
      var on = t === btn;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }

  function initPick(root) {
    if (!root || root._pickInited) return;
    root._pickInited = true;
    var thumbs = Array.prototype.slice.call(root.querySelectorAll('.mc-spot__thumb'));
    if (!thumbs.length) return;
    var idx = 0;
    var timer = null;
    function go(n) {
      idx = (n + thumbs.length) % thumbs.length;
      renderPick(root, thumbs[idx]);
    }
    function arm() {
      clearInterval(timer);
      if (win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      timer = setInterval(function () { go(idx + 1); }, 6000);
    }
    root.addEventListener('click', function (e) {
      var btn = e.target.closest('.mc-spot__thumb');
      if (!btn || !root.contains(btn)) return;
      go(parseInt(btn.getAttribute('data-i'), 10) || 0);
      arm();
    });
    root.addEventListener('mouseenter', function () { clearInterval(timer); });
    root.addEventListener('mouseleave', arm);
    arm();
  }

  function boot() {
    var nodes = doc.querySelectorAll('[data-component="manga_home_spotlight"][data-mc-pick]');
    Array.prototype.forEach.call(nodes, initPick);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
