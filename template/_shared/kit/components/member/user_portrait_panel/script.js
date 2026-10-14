/**
 * 会员中心 — 头像上传页
 * POST user/portrait multipart field "file"
 * 选图/拖拽即预览并上传 + toast
 */
(function (win, doc) {
    'use strict';

    function lang(key, zh, en) {
        try {
            if (win.MAC && win.MAC.GetLang) {
                var t = win.MAC.GetLang(key);
                if (t) { return t; }
            }
        } catch (e) { /* ignore */ }
        return String(win.localStorage.getItem('lang')) === '1' ? en : zh;
    }

    function notify(msg) {
        msg = String(msg == null ? '' : msg).trim();
        if (!msg) { msg = lang('string_load_err', '操作失败', 'Operation failed'); }
        if (typeof win.macToast === 'function') {
            win.macToast(msg);
            var box = doc.getElementById('mac-toast');
            if (box) {
                box.style.position = 'fixed';
                box.style.zIndex = '99999';
                box.style.left = '50%';
                box.style.top = box.style.top || '14%';
                box.style.pointerEvents = 'none';
                if (box.hidden) { box.hidden = false; }
                if (!box.style.opacity || box.style.opacity === '0') {
                    box.style.opacity = '1';
                    box.style.transform = 'translateX(-50%) translateY(0)';
                }
            }
            return;
        }
        if (win.MacTheme && win.MacTheme.notify) { win.MacTheme.notify(msg); return; }
        if (win.MAC && win.MAC.alert) { win.MAC.alert(msg); return; }
        win.alert(msg);
    }

    function bustUrl(url) {
        url = String(url || '');
        if (!url) { return url; }
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    }

    function replaceAvatarEl(el, src) {
        if (!el || !src) { return; }
        if (el.tagName === 'IMG') {
            el.src = src;
            return;
        }
        var img = doc.createElement('img');
        var cls = String(el.className || '').replace(/\bpb-avatar-fallback\b/g, '').replace(/\s+/g, ' ').trim();
        img.className = cls;
        img.alt = el.getAttribute('aria-label') || '';
        img.decoding = 'async';
        var m = String(el.getAttribute('style') || '').match(/width:\s*(\d+)px/i);
        if (m) {
            img.width = Number(m[1]);
            img.height = Number(m[1]);
        }
        if (el.id) { img.id = el.id; }
        img.src = src;
        if (el.parentNode) { el.parentNode.replaceChild(img, el); }
    }

    function syncSideAvatars(src) {
        if (!src) { return; }
        try {
            doc.querySelectorAll('.member-hub__avatar, .member-profile__avatar, .uc-hero__avatar, .mac-avatar__img, .user-dd__avatar, .hd-login__avatar, .user-dd__head-avatar').forEach(function (el) {
                replaceAvatarEl(el, src);
            });
        } catch (e) { /* ignore */ }
    }

    function init(root) {
        if (!root || root.getAttribute('data-mac-portrait-inited') === '1') { return; }
        root.setAttribute('data-mac-portrait-inited', '1');

        var input = doc.getElementById('mac-portrait-file');
        var drop = doc.getElementById('mac-portrait-drop');
        var submit = doc.getElementById('mac-portrait-submit');
        var preview = doc.getElementById('mac-portrait-preview') || root.querySelector('.pb-user-portrait__preview');
        var url = root.getAttribute('data-url-portrait') || '';
        var picked = null;
        var previewObjectUrl = null;
        var uploading = false;

        function ensurePreviewImg() {
            if (!preview) { return null; }
            var img = preview.querySelector('img');
            if (img) { return img; }
            preview.innerHTML = '';
            img = doc.createElement('img');
            img.id = 'mac-portrait-img';
            img.className = 'pb-user-portrait__img';
            img.width = 160;
            img.height = 160;
            img.decoding = 'async';
            img.alt = '';
            preview.appendChild(img);
            return img;
        }

        function setPreview(file) {
            if (previewObjectUrl) {
                try { URL.revokeObjectURL(previewObjectUrl); } catch (e) { /* ignore */ }
                previewObjectUrl = null;
            }
            picked = file || null;
            if (submit) { submit.disabled = !picked || uploading; }
            if (!picked) { return; }
            var img = ensurePreviewImg();
            if (!img) { return; }
            previewObjectUrl = URL.createObjectURL(picked);
            img.src = previewObjectUrl;
        }

        function setBusy(on) {
            uploading = !!on;
            if (submit) {
                if (on) {
                    submit.setAttribute('data-idle-text', submit.textContent || '');
                    submit.textContent = lang('string_auth_loading', '加载中...', 'Loading...');
                    submit.disabled = true;
                } else {
                    submit.textContent = submit.getAttribute('data-idle-text') || lang('user_upload', '上传', 'Upload');
                    submit.disabled = !picked;
                }
            }
            if (input) { input.disabled = uploading; }
            if (drop) { drop.classList.toggle('is-busy', uploading); }
        }

        function doUpload(file) {
            if (!file) {
                notify(lang('user_portrait_tip', '请先选择图片', 'Please choose an image first'));
                return;
            }
            if (!url) {
                notify(lang('string_load_err', '上传地址缺失', 'Upload URL missing'));
                return;
            }
            if (uploading) { return; }
            setPreview(file);
            setBusy(true);
            var fd = new FormData();
            fd.append('file', file);
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (r) {
                return r.json().catch(function () { return { code: 0, msg: 'invalid json' }; });
            }).then(function (res) {
                if (res && Number(res.code) === 1) {
                    notify((res && res.msg) || lang('user_portrait_ok', '头像上传成功', 'Avatar uploaded'));
                    var img = ensurePreviewImg();
                    if (img && res.file) { img.src = bustUrl(res.file); }
                    syncSideAvatars(img ? img.src : '');
                    picked = null;
                    if (input) { input.value = ''; }
                } else {
                    notify((res && res.msg) || lang('string_load_err', '上传失败', 'Upload failed'));
                }
            }).catch(function () {
                notify(lang('string_load_err', '网络错误', 'Network error'));
            }).then(function () {
                setBusy(false);
                if (submit) { submit.disabled = !picked; }
            });
        }

        function acceptFile(file) {
            if (!file) { return; }
            if (file.type && file.type.indexOf('image/') !== 0) {
                notify(lang('user_portrait_type_err', '请选择图片文件', 'Please choose an image'));
                if (input) { input.value = ''; }
                setPreview(null);
                return;
            }
            // 选图/拖拽即上传
            doUpload(file);
        }

        if (input) {
            input.addEventListener('change', function () {
                var f = input.files && input.files[0] ? input.files[0] : null;
                acceptFile(f);
            });
        }

        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
            });
            drop.addEventListener('drop', function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    acceptFile(e.dataTransfer.files[0]);
                }
            });
        }

        if (submit) {
            submit.addEventListener('click', function () {
                if (picked) {
                    doUpload(picked);
                } else if (input) {
                    input.click();
                } else {
                    notify(lang('user_portrait_tip', '请先选择图片', 'Please choose an image first'));
                }
            });
        }
    }

    function boot() {
        var root = doc.getElementById('mac-user-portrait-root');
        if (root) { init(root); }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
