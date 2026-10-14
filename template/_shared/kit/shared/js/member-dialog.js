/**
 * 会员区统一对话框：确认框 + 底部状态 Sheet + MAC 兼容层
 * 禁止回退到 window.alert / window.confirm
 */
(function (win, doc) {
    'use strict';

    var ICON_SUCCESS = '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.9-4.9 1.4 1.4-6.3 6.3Z"/></svg>';
    var ICON_ERROR = '<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 5h-2v6h2V7Zm0 8h-2v2h2v-2Z"/></svg>';

    var statusEl = null;
    var statusTimer = null;

    function toast(msg) {
        if (!msg) { return; }
        if (win.MacTheme && typeof win.MacTheme.notify === 'function') {
            win.MacTheme.notify(msg);
            return;
        }
        var box = doc.getElementById('mac-toast');
        if (!box) {
            box = doc.createElement('div');
            box.id = 'mac-toast';
            box.className = 'mac-toast';
            box.setAttribute('role', 'status');
            box.style.cssText = 'position:fixed;left:50%;bottom:calc(18px + env(safe-area-inset-bottom,0));transform:translateX(-50%);z-index:10070;padding:12px 18px;border-radius:999px;background:rgba(15,23,42,.92);color:#fff;font-size:14px;max-width:min(90vw,360px);text-align:center;box-shadow:0 8px 24px rgba(0,0,0,.25);';
            (doc.body || doc.documentElement).appendChild(box);
        }
        box.textContent = String(msg);
        box.style.display = '';
        if (box._macTimer) { win.clearTimeout(box._macTimer); }
        box._macTimer = win.setTimeout(function () { box.style.display = 'none'; }, 2600);
    }

    function normalizeConfirmOptions(options) {
        if (typeof options === 'string') {
            return { message: options };
        }
        return options || {};
    }

    function openConfirm(options, callback) {
        options = normalizeConfirmOptions(options);
        var cb = typeof callback === 'function' ? callback : null;
        if (win.MacTheme && typeof win.MacTheme.confirm === 'function') {
            win.MacTheme.confirm(options, cb);
            return;
        }
        var root = doc.getElementById('mac-confirm');
        if (!root) {
            toast(options.message || options.title || '');
            if (cb) { cb(false); }
            return;
        }
        var titleEl = root.querySelector('[data-mac-confirm-title]');
        var msgEl = root.querySelector('[data-mac-confirm-message]');
        var okBtn = root.querySelector('[data-mac-confirm-ok]');
        var cancelBtn = root.querySelector('[data-mac-confirm-cancel]');
        var iconWrap = root.querySelector('[data-mac-confirm-icon]');
        var title = options.title || '';
        var message = options.message || '';
        var confirmText = options.confirmText || options.okText || '确定';
        var cancelText = options.cancelText || options.cancel || '取消';
        var tone = options.tone || options.variant || 'default';
        if (titleEl) {
            titleEl.textContent = title;
            titleEl.hidden = !title;
        }
        if (msgEl) { msgEl.textContent = message; }
        if (okBtn) { okBtn.textContent = confirmText; }
        if (cancelBtn) { cancelBtn.textContent = cancelText; }
        root.classList.remove('mac-confirm--danger', 'mac-confirm--default');
        root.classList.add(tone === 'danger' ? 'mac-confirm--danger' : 'mac-confirm--default');
        if (iconWrap) {
            iconWrap.hidden = tone !== 'danger';
        }
        var state = { cb: cb };
        function finish(ok) {
            root.classList.remove('is-open');
            doc.body.classList.remove('has-mac-confirm');
            win.setTimeout(function () {
                root.hidden = true;
                root.setAttribute('aria-hidden', 'true');
                if (state.cb) { state.cb(!!ok); }
            }, 180);
        }
        function onClick(ev) {
            if (ev.target.closest('[data-mac-confirm-cancel]')) {
                root.removeEventListener('click', onClick);
                finish(false);
            } else if (ev.target.closest('[data-mac-confirm-ok]')) {
                root.removeEventListener('click', onClick);
                finish(true);
            }
        }
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('has-mac-confirm');
        root.addEventListener('click', onClick);
        win.requestAnimationFrame(function () {
            root.classList.add('is-open');
            var focusEl = tone === 'danger' ? cancelBtn : okBtn;
            if (focusEl && focusEl.focus) { focusEl.focus(); }
        });
    }

    function closeStatusSheet() {
        if (!statusEl) { return; }
        statusEl.classList.remove('is-open');
        win.setTimeout(function () {
            if (statusEl && statusEl.parentNode) {
                statusEl.parentNode.removeChild(statusEl);
            }
            statusEl = null;
        }, 220);
        if (statusTimer) {
            win.clearTimeout(statusTimer);
            statusTimer = null;
        }
    }

    function openStatusSheet(options) {
        options = options || {};
        closeStatusSheet();
        var type = options.type === 'error' ? 'error' : 'success';
        var msg = options.message != null ? String(options.message) : (options.title != null ? String(options.title) : (type === 'error' ? '操作失败' : '操作成功'));
        var detail = options.detail != null ? String(options.detail) : '';
        var hideOk = options.hideOkButton === true;
        var okText = options.okText != null ? String(options.okText) : '确定';
        var autoMs = parseInt(options.autoCloseMs, 10);
        var onClose = typeof options.onClose === 'function' ? options.onClose : null;

        statusEl = doc.createElement('div');
        statusEl.className = 'mac-status-sheet mac-status-sheet--' + type + (hideOk ? ' mac-status-sheet--toast' : '');
        statusEl.innerHTML =
            '<div class="mac-status-sheet__backdrop" data-status-close tabindex="-1"></div>' +
            '<div class="mac-status-sheet__panel" role="' + (hideOk ? 'status' : 'dialog') + '" aria-live="polite">' +
            '<div class="mac-status-sheet__icon">' + (type === 'error' ? ICON_ERROR : ICON_SUCCESS) + '</div>' +
            '<p class="mac-status-sheet__msg"></p>' +
            (detail ? '<p class="mac-status-sheet__detail"></p>' : '') +
            (hideOk ? '' : '<button type="button" class="mac-status-sheet__ok"></button>') +
            '</div>';
        statusEl.querySelector('.mac-status-sheet__msg').textContent = msg;
        if (detail) {
            statusEl.querySelector('.mac-status-sheet__detail').textContent = detail;
        }
        var okBtn = statusEl.querySelector('.mac-status-sheet__ok');
        if (okBtn) { okBtn.textContent = okText; }
        statusEl.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-status-close]') || ev.target.closest('.mac-status-sheet__ok')) {
                closeStatusSheet();
                if (onClose) { onClose(); }
            }
        });
        doc.body.appendChild(statusEl);
        win.requestAnimationFrame(function () {
            statusEl.classList.add('is-open');
        });
        function autoDone() {
            closeStatusSheet();
            if (onClose) { onClose(); }
        }
        if (hideOk && autoMs > 0) {
            statusTimer = win.setTimeout(autoDone, autoMs);
        } else if (hideOk) {
            statusTimer = win.setTimeout(autoDone, 2200);
        }
    }

    function notifyError(msg) {
        openStatusSheet({ type: 'error', message: String(msg || ''), hideOkButton: true, autoCloseMs: 2400 });
    }

    win.MemberDialog = {
        toast: toast,
        notify: notifyError,
        confirm: openConfirm,
        status: openStatusSheet
    };

    win.MAC = win.MAC || {};
    win.MAC.Pop = win.MAC.Pop || {};
    if (typeof win.MAC.Pop.StatusSheet !== 'function') {
        win.MAC.Pop.StatusSheet = openStatusSheet;
    }
    if (typeof win.MAC.alert !== 'function') {
        win.MAC.alert = function (msg) {
            openStatusSheet({ type: 'error', message: String(msg || ''), hideOkButton: true, autoCloseMs: 2400 });
        };
    }
    if (typeof win.MAC.confirm !== 'function') {
        win.MAC.confirm = function (msg, fn) {
            openConfirm(typeof msg === 'string' ? { message: msg, title: '请确认' } : msg, fn);
        };
    }
})(window, document);
