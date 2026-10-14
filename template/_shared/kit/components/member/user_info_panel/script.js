/**
 * 会员中心 — 资料修改 / 密码修改
 * POST user/info（昵称/QQ/密保 + 可选改密）；解绑走 user/unbind
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var MT = win.MacTheme;

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
        if (!msg) {
            msg = lang('string_load_err', '操作失败', 'Operation failed');
        }
        if (typeof win.macToast === 'function') {
            win.macToast(msg);
            // 二次保证：macToast 依赖 #mac-toast；若被主题 CSS 改坏，强制 fixed 可见
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
        if (MT && MT.notify) { MT.notify(msg); return; }
        if (ML && ML.notify) { ML.notify(msg); return; }
        if (win.MAC && win.MAC.alert) { win.MAC.alert(msg); return; }
        win.alert(msg);
    }

    function postUrl(url, data) {
        if (MT && MT.post) {
            return new Promise(function (resolve) {
                var path = String(url).replace(/^.*\/index\.php\//, '');
                path = path.replace(/^\/+/, '').replace(/\.html(?=$|[?#])/, '');
                MT.post(path, data || {}, function (err, res) {
                    if (err && err.loginRequired) {
                        resolve({ code: 1401, msg: lang('string_login', '请先登录', 'Please log in') });
                        return;
                    }
                    resolve(res || { code: 0, msg: (err && err.message) || lang('string_load_err', '网络错误', 'Network error') });
                });
            });
        }
        if (ML && ML.postForm) {
            return ML.postForm(url, data || {});
        }
        var body = [];
        Object.keys(data || {}).forEach(function (k) {
            body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
        });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.join('&')
        }).then(function (r) {
            return r.json().catch(function () { return { code: 0, msg: 'invalid json' }; });
        });
    }

    function readForm(form) {
        if (ML && ML.readForm) { return ML.readForm(form); }
        var data = {};
        var fields = form.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            if (!el.name || el.disabled) { continue; }
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { continue; }
            data[el.name] = el.value;
        }
        return data;
    }

    function toggleEye(btn) {
        var sel = btn.getAttribute('data-target');
        var input = sel ? doc.querySelector(sel) : null;
        if (!input) { return; }
        var show = input.getAttribute('type') === 'password';
        input.setAttribute('type', show ? 'text' : 'password');
        btn.classList.toggle('is-open', show);
    }

    function setBusy(btn, on, idleText) {
        if (!btn) { return; }
        btn.disabled = !!on;
        if (on) {
            btn.setAttribute('data-idle-text', btn.textContent || '');
            btn.textContent = lang('string_auth_loading', '加载中...', 'Loading...');
        } else {
            btn.textContent = idleText || btn.getAttribute('data-idle-text') || btn.textContent;
        }
    }


    function bustUrl(url) {
        url = String(url || '');
        if (!url) { return url; }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 't=' + Date.now();
    }

    function uploadPortrait(url, file) {
        var fd = new FormData();
        fd.append('file', file);
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) {
            return r.json().catch(function () { return { code: 0, msg: 'invalid json' }; });
        });
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

    function initPortrait(root) {
        var fileInput = doc.getElementById('mac-info-portrait-file');
        var avatar = root.querySelector('.pb-user-info__avatar');
        var preview = doc.getElementById('mac-info-portrait-preview') || root.querySelector('.pb-user-info__avatar-preview');
        var portraitUrl = root.getAttribute('data-url-portrait') || '';
        if (!fileInput || !portraitUrl) { return; }

        var previewObjectUrl = null;
        var uploading = false;

        function ensurePreviewImg() {
            if (!preview) { return null; }
            var img = preview.querySelector('img');
            if (img) { return img; }
            preview.innerHTML = '';
            img = doc.createElement('img');
            img.id = 'mac-info-portrait-img';
            img.className = 'pb-user-info__avatar-img';
            img.width = 96;
            img.height = 96;
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
            if (!file) { return; }
            var img = ensurePreviewImg();
            if (!img) { return; }
            previewObjectUrl = URL.createObjectURL(file);
            img.src = previewObjectUrl;
            img.style.display = '';
        }

        function setBusyAvatar(on) {
            uploading = !!on;
            if (avatar) { avatar.classList.toggle('is-busy', uploading); }
            fileInput.disabled = uploading;
        }

        function doUpload(file) {
            if (!file || uploading) { return; }
            setPreview(file);
            setBusyAvatar(true);
            uploadPortrait(portraitUrl, file).then(function (res) {
                if (res && Number(res.code) === 1) {
                    notify((res && res.msg) || lang('user_portrait_ok', '头像上传成功', 'Avatar uploaded'));
                    var img = ensurePreviewImg();
                    if (img && res.file) {
                        img.src = bustUrl(res.file);
                    }
                    syncSideAvatars(img ? img.src : '');
                } else {
                    notify((res && res.msg) || lang('string_load_err', '上传失败', 'Upload failed'));
                }
            }).catch(function () {
                notify(lang('string_load_err', '网络错误', 'Network error'));
            }).then(function () {
                setBusyAvatar(false);
                try { fileInput.value = ''; } catch (e) { /* ignore */ }
            });
        }

        fileInput.addEventListener('change', function () {
            var f = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!f) { return; }
            if (f.type && f.type.indexOf('image/') !== 0) {
                notify(lang('user_portrait_type_err', '请选择图片文件', 'Please choose an image'));
                fileInput.value = '';
                return;
            }
            // 选图即上传 + toast
            doUpload(f);
        });
    }

    function init(root) {
        if (!root || root.getAttribute('data-mac-info-inited') === '1') { return; }
        root.setAttribute('data-mac-info-inited', '1');

        var infoForm = doc.getElementById('mac-user-info-form');
        var pwdForm = doc.getElementById('mac-user-pwd-form');
        var infoUrl = root.getAttribute('data-url-info') || (infoForm && infoForm.getAttribute('action')) || '';
        var unbindUrl = root.getAttribute('data-url-unbind') || '';

        root.querySelectorAll('.js-pwd-eye').forEach(function (btn) {
            btn.addEventListener('click', function () { toggleEye(btn); });
        });

        if (infoForm) {
            infoForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = doc.getElementById('btn_info_submit');
                var data = readForm(infoForm);
                // 资料保存不要带上空密码字段，避免误触改密校验
                delete data.user_pwd;
                delete data.user_pwd1;
                delete data.user_pwd2;
                setBusy(btn, true);
                postUrl(infoUrl || infoForm.action, data).then(function (res) {
                    notify((res && res.msg) || (res && Number(res.code) === 1
                        ? lang('save_ok', '保存成功', 'Saved')
                        : lang('string_load_err', '保存失败', 'Save failed')));
                    if (res && Number(res.code) === 1) {
                        // 等 toast 可见后再刷新
                        win.setTimeout(function () { win.location.reload(); }, 1600);
                    }
                }).catch(function () {
                    notify(lang('string_load_err', '网络错误', 'Network error'));
                }).then(function () {
                    setBusy(btn, false, lang('string_save', '保存', 'Save'));
                });
            });
        }

        if (pwdForm) {
            pwdForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var oldPwd = String((pwdForm.querySelector('[name="user_pwd"]') || {}).value || '').trim();
                var pwd1 = String((pwdForm.querySelector('[name="user_pwd1"]') || {}).value || '').trim();
                var pwd2 = String((pwdForm.querySelector('[name="user_pwd2"]') || {}).value || '').trim();
                if (!oldPwd) {
                    notify(lang('user_place_ysmm', '请输入原始密码', 'Please enter old password'));
                    return;
                }
                if (!pwd1 || !pwd2) {
                    notify(lang('user_place_xmm', '请输入新密码', 'Please enter new password'));
                    return;
                }
                if (pwd1 !== pwd2) {
                    notify(lang('model/user/pass_not_same_pass2', '两次密码不一致', 'Passwords do not match'));
                    return;
                }
                var btn = doc.getElementById('btn_pwd_submit');
                var data = readForm(pwdForm);
                setBusy(btn, true);
                postUrl(infoUrl || pwdForm.action, data).then(function (res) {
                    notify((res && res.msg) || (res && Number(res.code) === 1
                        ? lang('user_change_pwd_ok', '密码修改成功', 'Password updated')
                        : lang('string_load_err', '修改失败', 'Update failed')));
                    if (res && Number(res.code) === 1) {
                        pwdForm.reset();
                    }
                }).catch(function () {
                    notify(lang('string_load_err', '网络错误', 'Network error'));
                }).then(function () {
                    setBusy(btn, false, lang('user_change_pwd', '修改密码', 'Change password'));
                });
            });
        }

        root.querySelectorAll('.js-unbind').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var ac = btn.getAttribute('data-ac');
                if (ac !== 'email' && ac !== 'phone') {
                    notify(lang('string_login_cscw', '参数错误', 'Parameter error'));
                    return;
                }
                if (!win.confirm(lang('string_user_unbind_confirm', '确认解除绑定吗？此操作不可恢复。', 'Unbind? This cannot be undone.'))) {
                    return;
                }
                var pwd = win.prompt(lang('user_place_current_pwd', '请输入当前密码', 'Please enter current password'), '');
                if (pwd == null) { return; }
                pwd = String(pwd).trim();
                if (!pwd) {
                    notify(lang('user_place_current_pwd', '请输入当前密码', 'Please enter current password'));
                    return;
                }
                btn.disabled = true;
                postUrl(unbindUrl, { ac: ac, user_pwd: pwd }).then(function (res) {
                    notify((res && res.msg) || (res && Number(res.code) === 1
                        ? lang('model/user/update_unbind_ok', '解绑成功', 'Unbound')
                        : lang('string_load_err', '解绑失败', 'Unbind failed')));
                    if (res && Number(res.code) === 1) {
                        win.setTimeout(function () { win.location.reload(); }, 1600);
                    }
                }).catch(function () {
                    notify(lang('string_load_err', '网络错误', 'Network error'));
                }).then(function () {
                    btn.disabled = false;
                });
            });
        });

        initPortrait(root);

        // 从菜单锚点进入时滚到密码区
        if (win.location.hash === '#mac-user-pwd') {
            var pwdHead = doc.getElementById('mac-user-pwd');
            if (pwdHead && pwdHead.scrollIntoView) {
                win.setTimeout(function () { pwdHead.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
            }
        }
    }

    function boot() {
        function start(memberList) {
            ML = memberList || ML;
            var root = doc.getElementById('mac-user-info-root');
            if (root) { init(root); }
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        } else {
            start(null);
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
