(function () {
  'use strict';

  var mq = window.matchMedia('(max-width: 768px)');

  function scrollActiveChip(chips) {
    if (!chips || !mq.matches) return;
    var active = chips.querySelector('.on');
    if (!active) return;
    /* 只滚 chips 横向容器；scrollIntoView 在 resize 时会连带 window 跳回筛选区（vod/show 触底上滑） */
    var left = active.offsetLeft - (chips.clientWidth - active.offsetWidth) / 2;
    if (left < 0) left = 0;
    var max = Math.max(0, chips.scrollWidth - chips.clientWidth);
    if (left > max) left = max;
    try {
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      chips.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' });
    } catch (e) {
      chips.scrollLeft = left;
    }
  }

  function bindCollapse(row) {
    if (row.getAttribute('data-filter-bound') === '1') return;
    row.setAttribute('data-filter-bound', '1');

    var chips = row.querySelector('.bili-filter__chips');
    var btn = row.querySelector('[data-filter-more]');
    if (!chips) return;

    function needsCollapse() {
      /* 只看是否真的超出一行；不要用数量/soft 标签硬阈值展开 */
      var wasCollapsed = row.classList.contains('is-collapsed');
      row.classList.remove('is-collapsed');
      var naturalH = chips.scrollHeight;
      row.classList.add('is-collapsed');
      var collapsedH = chips.clientHeight;
      if (!wasCollapsed) {
        row.classList.remove('is-collapsed');
      }
      return naturalH > collapsedH + 4;
    }

    function setOpen(open) {
      row.classList.toggle('is-collapsed', !open);
      if (!btn) return;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      var more = btn.querySelector('[data-more]');
      var less = btn.querySelector('[data-less]');
      if (more) more.hidden = open;
      if (less) less.hidden = !open;
    }

    function syncRow() {
      if (mq.matches) {
        row.classList.remove('is-collapsed');
        if (btn) btn.hidden = true;
        scrollActiveChip(chips);
        return;
      }
      if (!btn) return;

      var overflow = needsCollapse();
      btn.hidden = !overflow;
      if (!overflow) {
        row.classList.remove('is-collapsed');
        return;
      }

      var active = chips.querySelector('.on');
      var activeHidden = false;
      var activeSoft = !!(active && active.classList.contains('bili-filter__chip--soft'));
      if (active && !activeSoft) {
        var cr = chips.getBoundingClientRect();
        var ar = active.getBoundingClientRect();
        activeHidden = ar.bottom > cr.bottom + 2;
      }
      setOpen(activeHidden || activeSoft);
    }

    if (btn) {
      btn.addEventListener('click', function () {
        var open = row.classList.contains('is-collapsed');
        setOpen(open);
      });
    }

    syncRow();
    /* 字体/布局稳定后再测一次，避免宽屏误判 */
    if (typeof requestAnimationFrame === 'function') {
      requestAnimationFrame(function () { requestAnimationFrame(syncRow); });
    }
    window.addEventListener('resize', syncRow, { passive: true });
  }

  function bindAdvanced(root) {
    var toggle = root.querySelector('[data-filter-advanced-toggle]');
    var panel = root.querySelector('[data-filter-advanced]');
    if (!toggle || !panel) return;

    function setOpen(open) {
      panel.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (!open) panel.removeAttribute('data-open');
    }

    function sync() {
      if (!mq.matches) {
        panel.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        return;
      }
      var shouldOpen = panel.getAttribute('data-open') === '1' || panel.classList.contains('is-open');
      setOpen(shouldOpen);
    }

    toggle.addEventListener('click', function () {
      setOpen(!panel.classList.contains('is-open'));
    });
    sync();
    window.addEventListener('resize', sync, { passive: true });
  }

  function init(root) {
    root = root || document;
    root.querySelectorAll('[data-filter-collapse]').forEach(bindCollapse);
    root.querySelectorAll('[data-filter-panel]').forEach(bindAdvanced);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }

  document.addEventListener('pb:component-mounted', function (e) {
    if (e.detail && e.detail.root) init(e.detail.root);
  });
})();
