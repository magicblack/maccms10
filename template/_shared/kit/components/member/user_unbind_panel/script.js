/**
 * 账号解绑页 — POST user/unbind（需当前密码）
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
        if (MT && MT.notify) { MT.notify(msg); return; }
        if (win.MAC && win.MAC.alert) { win.MAC.alert(msg); return; }
        win.alert(msg);
    }

    function postUrl(url, data) {
        if (MT && MT.post) {
            return new Promise(function (resolve) {
                var path = String(url).replace(/^.*\/index\.php\//, '');
                MT.post(path, data, function (err, res) {
                    resolve(res || { code: 0, msg: err || 'network error' });
                });
            });
        }
        if (ML && ML.postForm) {
            return ML.postForm(url, data);
        }
        return Promise.resolve({ code: 0, msg: 'network error' });
    }

    function boot() {
        var root = doc.querySelector('[data-component="user_unbind_panel"]');
        if (!root) { return; }
        var unbindUrl = root.getAttribute('data-url-unbind') || '';

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
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
