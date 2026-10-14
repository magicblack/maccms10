/**
 * 全站分享底部弹层 — data-mac-share-open 触发
 */
(function (win, doc) {
  'use strict';

  function toast(msg) {
    if (typeof win.macToast === 'function') {
      win.macToast(msg);
      return;
    }
    if (win.MacTheme && typeof win.MacTheme.notify === 'function') {
      win.MacTheme.notify(msg);
      return;
    }
    if (win.MAC && win.MAC.Pop && win.MAC.Pop.Msg) {
      win.MAC.Pop.Msg(msg);
    }
  }

  function initShareSheet() {
    var sheet = doc.querySelector('[data-mac-share-sheet]');
    if (!sheet || sheet._shareInited) return;
    sheet._shareInited = true;

    var input = sheet.querySelector('[data-mac-share-url-input]');
    var preview = sheet.querySelector('[data-mac-share-preview]');
    var state = { url: '', title: '', text: '' };

    function absUrl(raw) {
      var u = String(raw || '').trim();
      if (!u) return win.location.href;
      if (/^https?:\/\//i.test(u)) return u;
      if (u.charAt(0) === '/') return win.location.origin + u;
      return win.location.href;
    }

    function openSheet(opts) {
      state.url = absUrl(opts.url);
      state.title = String(opts.title || doc.title || '').trim();
      state.text = String(opts.text || state.title || '').trim();
      if (input) {
        input.value = state.url;
        input.setAttribute('value', state.url);
      }
      if (preview) {
        if (state.title) {
          preview.textContent = state.title;
          preview.hidden = false;
        } else {
          preview.hidden = true;
        }
      }
      sheet.hidden = false;
      doc.body.classList.add('has-share-sheet');
      var copyBtn = sheet.querySelector('[data-mac-share-copy]');
      if (copyBtn) copyBtn.focus();
    }

    function closeSheet() {
      sheet.hidden = true;
      doc.body.classList.remove('has-share-sheet');
    }

    function copyLink(msg) {
      var done = function () { toast(msg || '链接已复制'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(state.url).then(done).catch(fallbackCopy);
      } else {
        fallbackCopy();
      }
      function fallbackCopy() {
        var ta = doc.createElement('textarea');
        ta.value = state.url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        ta.style.top = '0';
        doc.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
          doc.execCommand('copy');
          done();
        } catch (e) {
          if (input && !sheet.hidden) {
            input.focus();
            input.select();
            try {
              doc.execCommand('copy');
              done();
              return;
            } catch (e2) { /* ignore */ }
          }
          toast('请手动复制链接');
        } finally {
          doc.body.removeChild(ta);
        }
      }
    }

    function openPopup(url) {
      win.open(url, '_blank', 'noopener,noreferrer,width=600,height=480');
    }

    function handleChannel(key) {
      var encUrl = encodeURIComponent(state.url);
      var encTitle = encodeURIComponent(state.title || state.text);
      if (key === 'copy' || key === 'wechat') {
        copyLink(key === 'wechat' ? '链接已复制，去微信粘贴分享' : '链接已复制');
        return;
      }
      if (key === 'weibo') {
        openPopup('https://service.weibo.com/share/share.php?url=' + encUrl + '&title=' + encTitle);
        return;
      }
      if (key === 'qq') {
        openPopup('https://connect.qq.com/widget/shareqq/index.html?url=' + encUrl + '&title=' + encTitle);
      }
    }

    doc.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-mac-share-open]') : null;
      if (!btn) return;
      ev.preventDefault();
      openSheet({
        url: btn.getAttribute('data-mac-share-url') || '',
        title: btn.getAttribute('data-mac-share-title') || '',
        text: btn.getAttribute('data-mac-share-text') || ''
      });
    });

    sheet.querySelectorAll('[data-mac-share-close]').forEach(function (el) {
      el.addEventListener('click', closeSheet);
    });

    var copyBtn = sheet.querySelector('[data-mac-share-copy]');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () { copyLink('链接已复制'); });
    }

    sheet.querySelectorAll('[data-mac-share-channel]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleChannel(btn.getAttribute('data-mac-share-channel'));
      });
    });

    doc.querySelectorAll('[data-mac-share-quick]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.url = absUrl(btn.getAttribute('data-mac-share-url') || win.location.href);
        state.title = String(btn.getAttribute('data-mac-share-title') || doc.title || '').trim();
        state.text = String(btn.getAttribute('data-mac-share-text') || state.title || '').trim();
        handleChannel(btn.getAttribute('data-mac-share-quick'));
      });
    });

    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !sheet.hidden) closeSheet();
    });
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', initShareSheet);
  } else {
    initShareSheet();
  }
})(window, document);
