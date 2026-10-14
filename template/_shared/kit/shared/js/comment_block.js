/**
 * PB 评论区列表 — 监听 comment:saved 后 GET comment/ajax 刷新 [data-cmt-items]
 */
(function (win, doc) {
    'use strict';

    var bound = false;

    function indexUrl(route, qs) {
        var base = (win.maccms && win.maccms.path) ? win.maccms.path : '/';
        return String(base).replace(/\/+$/, '') + '/index.php/' + route + (qs ? '?' + qs : '');
    }

    function refreshCaptcha(form) {
        if (!form) { return; }
        var input = form.querySelector('input[name="verify"]');
        if (input) { input.value = ''; }
        var img = form.querySelector('.cmt-form__captcha-img, [class*="captcha"] img');
        if (img && img.src) {
            img.src = img.src.split('?')[0] + '?v=' + Date.now();
        }
    }

    function focusCommentInput(section) {
        if (!section) { return; }
        var input = section.querySelector('.cmt-form__input');
        if (!input) { return; }
        var form = input.closest('.cmt-form');
        if (form) { form.classList.add('is-active'); }
        input.focus();
        try {
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            input.scrollIntoView(true);
        }
    }

    function parseAjaxFragment(text) {
        var raw = String(text || '');
        var trimmed = raw.trim();
        if (trimmed.charAt(0) === '"' && trimmed.charAt(trimmed.length - 1) === '"') {
            try {
                return JSON.parse(trimmed);
            } catch (e) {
                return raw;
            }
        }
        return raw;
    }

    function normalizeTotal(total) {
        var val = String(total == null ? 0 : total).trim();
        if (val.charAt(0) === '"' && val.charAt(val.length - 1) === '"') {
            try {
                val = JSON.parse(val);
            } catch (e) {}
        }
        val = String(val).replace(/^["']+|["']+$/g, '');
        var n = parseInt(val, 10);
        return isNaN(n) ? '0' : String(n);
    }

    function updateTotal(listRoot, total) {
        var val = normalizeTotal(total);
        listRoot.setAttribute('data-comment-total', val);
        var totalEl = listRoot.querySelector('[data-cmt-total]');
        if (totalEl) { totalEl.textContent = val; }
    }

    function reloadList(form, page) {
        var section = form.closest('.cmt');
        var listRoot = section ? section.querySelector('[data-cmt-list]') : null;
        var itemsEl = listRoot ? listRoot.querySelector('[data-cmt-items]') : null;
        if (!listRoot || !itemsEl) { return; }

        var midInput = form.querySelector('input[name="mid"]');
        var ridInput = form.querySelector('input[name="rid"]');
        var mid = midInput ? midInput.value : '';
        var rid = ridInput ? ridInput.value : '';
        if (!mid || !rid) { return; }

        var limit = listRoot.getAttribute('data-cmt-num') || '10';
        var qs = 'mid=' + encodeURIComponent(mid)
            + '&rid=' + encodeURIComponent(rid)
            + '&page=' + encodeURIComponent(page || 1)
            + '&limit=' + encodeURIComponent(limit);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', indexUrl('comment/ajax.html', qs), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            if (xhr.status < 200 || xhr.status >= 300) { return; }

            itemsEl.innerHTML = parseAjaxFragment(xhr.responseText);
            var meta = itemsEl.querySelector('[data-cmt-ajax-total]');
            if (meta) {
                updateTotal(listRoot, meta.getAttribute('data-cmt-ajax-total'));
                if (meta.parentNode) { meta.parentNode.removeChild(meta); }
            }

            if (win.MacTheme && typeof win.MacTheme.refresh === 'function') {
                win.MacTheme.refresh(itemsEl);
            }
        };
        xhr.send();
    }

    function bindPagerClicks() {
        doc.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-cmt-page-nav]');
            if (!btn || btn.disabled) { return; }
            ev.preventDefault();

            var section = btn.closest('.cmt');
            var form = section ? section.querySelector('[data-mac-comment-form]') : null;
            var pager = btn.closest('[data-cmt-pager]');
            if (!form || !pager) { return; }

            var current = parseInt(pager.getAttribute('data-cmt-page-current') || '1', 10);
            var nav = btn.getAttribute('data-cmt-page-nav');
            var target = nav === 'prev' ? current - 1 : (nav === 'next' ? current + 1 : parseInt(nav, 10));
            if (!target || target < 1 || target === current) { return; }

            reloadList(form, target);
        });
    }

    function bindFocusCta() {
        doc.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-cmt-focus-input]');
            if (!btn) { return; }
            ev.preventDefault();
            focusCommentInput(btn.closest('.cmt'));
        });
    }

    function bindFormExpand() {
        doc.addEventListener('focusin', function (ev) {
            var form = ev.target.closest('.cmt-form');
            if (form) { form.classList.add('is-active'); }
        });

        doc.addEventListener('pointerdown', function (ev) {
            var img = ev.target.closest('.cmt-form .cmt-form__captcha-img, .cmt-form .cmt-form__captcha img');
            if (img) {
                var form = img.closest('.cmt-form');
                if (form) {
                    ev.preventDefault();
                    form.classList.add('is-active');
                    refreshCaptcha(form);
                }
                return;
            }
            if (ev.target.closest('.cmt-form')) { return; }
            var openForms = doc.querySelectorAll('.cmt-form.is-active');
            for (var i = 0; i < openForms.length; i += 1) {
                openForms[i].classList.remove('is-active');
            }
        }, true);

        // 宏里 captcha 图片带 inline onclick 换图；pointerdown 已 refresh，拦截 click 避免连换两次
        doc.addEventListener('click', function (ev) {
            var img = ev.target.closest('.cmt-form .cmt-form__captcha-img, .cmt-form .cmt-form__captcha img');
            if (!img) { return; }
            ev.preventDefault();
            ev.stopImmediatePropagation();
        }, true);
    }

    function bindSaved() {
        function attach() {
            if (!win.MacTheme || typeof win.MacTheme.on !== 'function') {
                return false;
            }
            win.MacTheme.on('comment:saved', function (detail) {
                var form = detail && detail.form;
                if (!form) { return; }
                refreshCaptcha(form);
                reloadList(form, 1);
            });
            return true;
        }
        if (attach()) { return; }
        var tries = 0;
        var timer = setInterval(function () {
            tries += 1;
            if (attach() || tries > 100) {
                clearInterval(timer);
            }
        }, 50);
    }

    function init() {
        if (bound) { return; }
        bound = true;
        bindFocusCta();
        bindFormExpand();
        bindSaved();
        bindPagerClicks();
    }

    win.MacCommentBlock = win.MacCommentBlock || {};
    win.MacCommentBlock.refreshCaptcha = refreshCaptcha;
    win.MacCommentBlock.reloadList = reloadList;

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
