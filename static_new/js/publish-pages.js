(function () {
  var i18n = window.MAC_PUBLISH_I18N || {};
  var copyLabel = i18n.copyButton || 'Copy URL';
  var copiedLabel = i18n.copied || 'Copied';
  var copyFailLabel = i18n.copyFail || 'Copy failed. Long-press the link to copy manually';

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
    } finally {
      document.body.removeChild(ta);
    }
    return Promise.resolve();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.pub-copy');
    if (!btn) return;
    var raw = btn.getAttribute('data-copy-url');
    if (!raw) return;
    var defaultLabel = btn.getAttribute('data-copy-label') || copyLabel;
    if (!btn.getAttribute('data-copy-label')) {
      btn.setAttribute('data-copy-label', defaultLabel);
    }
    copyText(raw).then(function () {
      btn.textContent = copiedLabel;
      setTimeout(function () { btn.textContent = defaultLabel; }, 2000);
    }).catch(function () {
      alert(copyFailLabel);
    });
  });
})();
