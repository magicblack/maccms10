(function () {
  function stripPrefix(s) {
    return s ? String(s).replace(/^\[[^\]]+\]\s*/, '') : s;
  }
  var h1 = document.querySelector('.np2-play__title');
  if (h1) h1.textContent = stripPrefix(h1.textContent);
  if (document.title) document.title = stripPrefix(document.title);

  var tabs = document.querySelectorAll('[data-np2-play-tab]');
  var panels = document.querySelectorAll('[data-np2-play-panel]');
  function showTab(name) {
    var i;
    for (i = 0; i < tabs.length; i++) {
      var on = tabs[i].getAttribute('data-np2-play-tab') === name;
      tabs[i].classList.toggle('is-on', on);
      tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
    }
    for (i = 0; i < panels.length; i++) {
      var match = panels[i].getAttribute('data-np2-play-panel') === name;
      panels[i].hidden = !match;
      panels[i].classList.toggle('is-on', match);
    }
  }
  for (var ti = 0; ti < tabs.length; ti++) {
    tabs[ti].addEventListener('click', function () {
      showTab(this.getAttribute('data-np2-play-tab'));
    });
  }

  var blurb = document.getElementById('np2-play-blurb');
  var blurbBtn = document.getElementById('np2-play-blurb-btn');
  if (blurb && blurbBtn) {
    if (blurb.scrollHeight > blurb.clientHeight + 4) {
      blurbBtn.hidden = false;
      blurbBtn.addEventListener('click', function () {
        var open = blurb.classList.toggle('is-open');
        blurbBtn.textContent = open ? '收起' : '展开';
      });
    }
  }

  var lineRoot = document.querySelector('[data-np2-play-line]');
  if (lineRoot) {
    var lineBtn = lineRoot.querySelector('[data-np2-play-line-swap]');
    var lineMenu = lineRoot.querySelector('[data-np2-play-line-menu]');
    var lineLinks = lineMenu ? lineMenu.querySelectorAll('a') : [];
    function closeLineMenu() {
      if (lineMenu) lineMenu.hidden = true;
    }
    if (lineBtn && lineMenu) {
      lineBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (lineLinks.length === 2) {
          var next = lineMenu.querySelector('a:not(.is-on)') || lineLinks[0];
          if (next && next.href) location.href = next.href;
          return;
        }
        lineMenu.hidden = !lineMenu.hidden;
      });
      document.addEventListener('click', function (e) {
        if (!lineRoot.contains(e.target)) closeLineMenu();
      });
    }
  }

  var epsRoot = document.querySelector('[data-np2-play-eps]');
  if (epsRoot) {
    var epsToggle = epsRoot.querySelector('[data-np2-play-eps-toggle]');
    if (epsToggle) {
      epsToggle.addEventListener('click', function () {
        var collapsed = epsRoot.classList.toggle('is-collapsed');
        epsToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    }
  }
})();
