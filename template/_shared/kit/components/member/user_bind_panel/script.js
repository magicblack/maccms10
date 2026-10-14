/**
 * twigspike 账号绑定页 — #userBindRoot / #fm
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

    function postUrl(url, form) {
        var data = ML ? ML.readForm(form) : {};
        if (MT && MT.post) {
            return new Promise(function (resolve) {
                var path = String(url).replace(/^.*\/index\.php\//, '');
                MT.post(path, data, function (err, res) {
                    resolve(res || { code: 0, msg: err || 'network error' });
                });
            });
        }
        return ML.postForm(url, data);
    }

    function validateTarget(ac, to) {
        if (ac === 'email') {
            if (!/^([a-zA-Z0-9]+[_|\\_|\\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\\_|\\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/.test(to)) {
                notify(lang('string_login_yxgscw', '邮箱格式不正确', 'The mailbox format is incorrect'));
                return false;
            }
        } else if (ac === 'phone') {
            if (!/^[1][0-9]{10}$/.test(to)) {
                notify(lang('string_login_sjhcw', '手机号格式不正确', 'Incorrect mobile phone number format'));
                return false;
            }
        } else {
            notify(lang('string_login_cscw', '参数错误', 'Parameter error'));
            return false;
        }
        return true;
    }

    function startCountdown(btn) {
        var left = 60;
        function tick() {
            if (left <= 0) {
                btn.disabled = false;
                btn.value = lang('string_login_yzm', '获取验证码', 'Get code');
                return;
            }
            btn.disabled = true;
            btn.value = lang('string_login_cxfs', '重新发送', 'Resend') + '(' + left + ')';
            left -= 1;
            win.setTimeout(tick, 1000);
        }
        tick();
    }

    function init(root) {
        if (!root || String(root.getAttribute('data-readonly') || '') === '1') { return; }

        var form = doc.getElementById('fm');
        var sendBtn = doc.getElementById('btn_bind_send');
        var submitBtn = doc.getElementById('btn_submit');
        if (!form || !sendBtn || !submitBtn) { return; }

        var bindUrl = form.getAttribute('action') || (ML && ML.memberUrl('bind', false));
        var bindMsgUrl = ML && ML.memberUrl('bindmsg', false);
        var infoUrl = ML && ML.memberUrl('info');

        sendBtn.addEventListener('click', function () {
            var ac = (form.querySelector('[name="ac"]') || {}).value;
            var to = String((form.querySelector('[name="to"]') || {}).value || '').trim();
            if (!validateTarget(ac, to)) { return; }
            startCountdown(sendBtn);
            postUrl(bindMsgUrl, form).then(function (res) {
                notify((res && res.msg) || '');
            });
        });

        submitBtn.addEventListener('click', function () {
            var ac = (form.querySelector('[name="ac"]') || {}).value;
            var to = String((form.querySelector('[name="to"]') || {}).value || '').trim();
            if (!validateTarget(ac, to)) { return; }

            var code = String((form.querySelector('[name="code"]') || {}).value || '').trim();
            if (!code) {
                notify(lang('string_login_sryzm', '请输入验证码！', 'Please enter the verification code'));
                return;
            }
            var pwd = String((form.querySelector('[name="user_pwd"]') || {}).value || '').trim();
            if (!pwd) {
                notify(lang('user_place_current_pwd', '请输入当前密码', 'Please enter current password'));
                return;
            }

            submitBtn.disabled = true;
            submitBtn.value = lang('string_auth_loading', '加载中...', 'Loading...');
            postUrl(bindUrl, form).then(function (res) {
                notify((res && res.msg) || '');
                if (res && Number(res.code) === 1 && infoUrl) {
                    win.location.href = infoUrl;
                }
            }).finally(function () {
                submitBtn.disabled = false;
                submitBtn.value = lang('confirm_binding', '确认绑定', 'Confirm');
            });
        });
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            var root = doc.getElementById('userBindRoot');
            if (root) { init(root); }
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
