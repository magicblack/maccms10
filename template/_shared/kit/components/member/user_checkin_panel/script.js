/**
 * twigspike 每日签到页 — #mac-user-checkin-root
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var signing = false;
    var claimingId = 0;
    var TOAST_MS = 2200;

    function taskUrl(action) {
        return ML.apiModuleUrl('Task', action);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function lang(key, fallback) {
        try {
            if (win.MAC && win.MAC.GetLang && key) {
                var t = win.MAC.GetLang(key);
                if (t) { return t; }
            }
        } catch (e) { /* ignore */ }
        return fallback || '';
    }

    function applyLang(root) {
        if (!win.jQuery || !win.jQuery.i18n || !win.jQuery.i18n.map || !root) { return; }
        var map = win.jQuery.i18n.map;
        root.querySelectorAll('[data-lang]').forEach(function (el) {
            var key = el.getAttribute('data-lang');
            if (key && map[key]) { el.textContent = map[key]; }
        });
    }

    function hintHtml(tpl, vars) {
        if (!tpl) { return ''; }
        var out = String(tpl);
        Object.keys(vars || {}).forEach(function (key) {
            var re = new RegExp('\\{' + key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\}', 'g');
            out = out.replace(re, '<span class="uc-streak__hint-num">' + esc(String(vars[key])) + '</span>');
        });
        return out;
    }

    function captionHtml(text) {
        return String(text || '').replace(/(\d+)/g, '<span class="uc-mile__caption-num">$1</span>');
    }

    function toast(msg) {
        var text = msg != null && String(msg).trim() !== '' ? String(msg) : lang('task/sign_ok', '签到成功');
        if (win.MAC && win.MAC.Pop && win.MAC.Pop.StatusSheet) {
            win.MAC.Pop.StatusSheet({ type: 'success', message: text, hideOkButton: true, autoCloseMs: TOAST_MS });
        } else if (win.MAC && win.MAC.alert) {
            win.MAC.alert(text);
        }
    }

    function signSuccessMessage(res) {
        var info = (res && res.info) ? res.info : {};
        var claimable = Number(info.claimable_milestones) || 0;
        var serial = Number(info.serial_days) || 0;

        // 签到成功提示不按接口 points 展示，避免被误解为「每次签到都送分」
        if (claimable > 0) {
            if (serial > 0) {
                return lang('user_checkin_sign_ok_milestone', '签到成功，已连续签到 {days} 天，里程碑奖励可领取')
                    .replace(/\{days\}/g, String(serial));
            }
            return lang('user_checkin_sign_ok_claimable', '签到成功，连续签到里程碑奖励可领取');
        }
        if (serial > 0) {
            return lang('user_checkin_sign_ok_streak', '签到成功，已连续签到 {days} 天')
                .replace(/\{days\}/g, String(serial));
        }
        return lang('task/sign_ok', '签到成功');
    }

    function rootEl() {
        return doc.getElementById('mac-user-checkin-root');
    }

    function defaultAvatar() {
        var hero = doc.getElementById('mac-user-checkin-hero');
        return (hero && hero.getAttribute('data-default-avatar')) || '';
    }

    function syncHero(auth) {
        var avatar = doc.querySelector('.uc-hero__avatar');
        var pointsEl = doc.querySelector('.uc-hero__num');
        if (!avatar || !pointsEl) { return; }
        var fallback = defaultAvatar();
        if (!auth || Number(auth.is_login) !== 1) {
            if (fallback) { avatar.src = fallback; }
            pointsEl.textContent = '0';
            return;
        }
        var portrait = (auth.user_portrait && String(auth.user_portrait).trim()) || '';
        if (portrait) {
            avatar.onerror = function () {
                if (fallback) { avatar.src = fallback; }
                avatar.onerror = null;
            };
            avatar.src = portrait;
        } else if (fallback) {
            avatar.src = fallback;
        }
        pointsEl.textContent = auth.points != null && auth.points !== '' ? String(auth.points) : '0';
    }

    function refreshHero() {
        if (!win.MAC || !win.MAC.User || typeof win.MAC.User.fetchAuthMe !== 'function') { return; }
        win.MAC.User.fetchAuthMe(function (auth) {
            if (auth) { syncHero(auth); }
        });
    }

    function dailyPointsFromTasks(res) {
        if (!res || Number(res.code) !== 1 || !res.info) { return null; }
        var tasks = res.info.daily_tasks || [];
        for (var i = 0; i < tasks.length; i++) {
            if (tasks[i].task_action === 'daily_sign') {
                var pts = Number(tasks[i].task_points);
                return isNaN(pts) ? null : pts;
            }
        }
        return null;
    }

    function maxMilestoneDays(info) {
        var max = 0;
        var list = info && info.milestones;
        if (!Array.isArray(list)) { return 0; }
        list.forEach(function (row) {
            var days = Number(row.milestone_days) || 0;
            if (days > max) { max = days; }
        });
        return max;
    }

    function renderRules(info, dailyPts, loggedIn) {
        var listEl = doc.getElementById('mac-checkin-rules-list');
        if (!listEl) { return; }
        var lines = [];
        lines.push(lang('user_checkin_rule_milestone_only', '每日签到记录连续天数；达到里程碑天数后可领取对应金币奖励。'));
        lines.push(lang('user_checkin_rule_2', '若中断签到，连续天数将重新计算。'));
        var cycle = maxMilestoneDays(info);
        if (cycle > 0) {
            var cycleTpl = lang('user_checkin_rule_tpl_cycle', '一个周期为 {n} 天，第 {next} 天起从第 1 天重新计数。');
            lines.push(cycleTpl.replace(/\{n\}/g, String(cycle)).replace(/\{next\}/g, String(cycle + 1)));
        }
        lines.push(lang('user_checkin_rule_4', '本活动解释权归平台所有。'));
        listEl.innerHTML = lines.map(function (line) { return '<li>' + esc(line) + '</li>'; }).join('');
    }

    function milestoneHtml(row, coinImg, checkIco) {
        var status = Number(row.status) || 0;
        var name = row.milestone_name || '';
        var caption = captionHtml(esc(name));
        var points = Number(row.milestone_points) || 0;
        var id = Number(row.milestone_id) || 0;
        var claimed = lang('user_checkin_milestone_claimed', '已领取');
        var media = status === 2
            ? '<div class="uc-mile__media uc-mile__media--check"><img class="uc-mile__check" src="' +
              esc(checkIco) + '" width="28" height="28" loading="lazy" decoding="async" alt="" aria-hidden="true" /></div>'
            : '<div class="uc-mile__media"><img class="uc-mile__coin" src="' + esc(coinImg) +
              '" width="56" height="40" loading="lazy" decoding="async" alt="" /></div>';
        var actions = '';
        if (status === 1) {
            actions = '<div class="uc-mile__actions"><button type="button" class="uc-mile__claim" data-milestone-id="' +
                id + '" data-lang="user_checkin_milestone_claim">' +
                esc(lang('user_checkin_milestone_claim', '领取')) + '</button></div>';
        } else if (status === 2) {
            actions = '<div class="uc-mile__actions"><span class="uc-mile__claimed-label" data-lang="user_checkin_milestone_claimed">' +
                esc(claimed) + '</span></div>';
        }
        var aria = status === 2 ? ' aria-label="' + esc(name + ' ' + claimed) + '"' : '';
        return '<div class="uc-mile uc-mile--status-' + status + '" data-milestone-id="' + id + '"' + aria +
            '><div class="uc-mile__points">+' + points + '</div>' + media +
            '<div class="uc-mile__caption">' + caption + '</div>' + actions + '</div>';
    }

    function renderMilestones(list, coinImg) {
        var grid = doc.getElementById('mac-checkin-milestones-root');
        if (!grid) { return; }
        if (!Array.isArray(list) || !list.length) {
            grid.innerHTML = '';
            grid.hidden = true;
            return;
        }
        var root = rootEl();
        var checkIco = root && root.getAttribute('data-check-ico') ? String(root.getAttribute('data-check-ico')).trim() : '';
        var bands = [];
        for (var i = 0; i < list.length; i += 3) {
            var chunk = list.slice(i, i + 3);
            bands.push('<div class="uc-milestones__band">' + chunk.map(function (row) {
                return milestoneHtml(row, coinImg, checkIco);
            }).join('') + '</div>');
        }
        grid.innerHTML = bands.join('');
        grid.hidden = false;
        applyLang(grid);
    }

    function toggleGuestMilestones(isGuest) {
        var grid = doc.getElementById('mac-checkin-milestones-root');
        var guestMsg = doc.getElementById('mac-checkin-milestones-guest');
        if (grid && isGuest) {
            grid.innerHTML = '';
            grid.hidden = true;
        }
        if (guestMsg) {
            guestMsg.hidden = !isGuest;
            if (isGuest) { applyLang(guestMsg); }
        }
    }

    function renderNextHint(info, streak, loggedIn) {
        var el = doc.getElementById('mac-checkin-next-hint');
        if (!el) { return; }
        if (!loggedIn || !info) {
            el.removeAttribute('data-lang');
            var guestTpl = lang('user_checkin_streak_hint', '连续签到{d}天可领取{p}个金币');
            if (/\{d\}/.test(guestTpl) && /\{p\}/.test(guestTpl)) {
                el.innerHTML = hintHtml(guestTpl, { d: 3, p: 5 });
            } else {
                el.textContent = guestTpl;
            }
            return;
        }
        el.removeAttribute('data-lang');
        var next = info.next_milestone;
        if (next && next.days != null) {
            var target = Number(next.days) || 0;
            var current = Number(streak) || 0;
            var remain = Math.max(0, target - current);
            var tpl = lang('user_checkin_hint_next', '再连续签到{remain}天可领取{points}个金币');
            if (/\{remain\}/.test(tpl) || /\{points\}/.test(tpl)) {
                el.innerHTML = hintHtml(tpl, {
                    remain: remain,
                    points: next.points != null ? next.points : ''
                });
            } else {
                el.textContent = tpl
                    .replace(/\{remain\}/g, String(remain))
                    .replace(/\{points\}/g, String(next.points != null ? next.points : ''));
            }
        } else {
            el.textContent = lang('user_checkin_hint_done', '');
        }
    }

    function renderCta(root, signedToday, loggedIn) {
        var wrap = doc.getElementById('mac-checkin-cta-wrap');
        if (!wrap || !root) { return; }
        wrap.innerHTML = '';
        if (!loggedIn) {
            var loginBtn = doc.createElement('a');
            loginBtn.className = 'uc-cta uc-cta--guest';
            loginBtn.href = 'javascript:;';
            loginBtn.id = 'mac-checkin-cta-login';
            loginBtn.setAttribute('role', 'button');
            loginBtn.setAttribute('data-lang', 'user_checkin_btn_guest');
            loginBtn.textContent = lang('user_checkin_btn_guest', '登录后签到');
            wrap.appendChild(loginBtn);
            applyLang(wrap);
            return;
        }
        var btn = doc.createElement('button');
        btn.type = 'button';
        btn.id = 'mac-checkin-cta-btn';
        btn.className = 'uc-cta ' + (signedToday ? 'uc-cta--done' : 'uc-cta--guest');
        btn.disabled = !!signedToday;
        btn.setAttribute('data-lang', signedToday ? 'user_checkin_btn_done' : 'user_checkin_btn_sign');
        btn.textContent = lang(signedToday ? 'user_checkin_btn_done' : 'user_checkin_btn_sign',
            signedToday ? '今日已签到' : '今日签到');
        wrap.appendChild(btn);
        applyLang(wrap);
    }

    function applySectionAria() {
        doc.querySelectorAll('[data-mac-checkin-section-aria]').forEach(function (el) {
            var key = el.getAttribute('data-mac-checkin-section-aria');
            var label = key ? lang(key, '') : '';
            if (label) {
                el.setAttribute('aria-label', label);
            } else {
                el.removeAttribute('aria-label');
            }
        });
    }

    function loadState(root) {
        var coinImg = (root.getAttribute('data-coin-img')) || '';
        var signReq = ML.apiJson(taskUrl('get_sign_info'));
        var taskReq = ML.apiJson(taskUrl('get_task_list')).catch(function () { return { code: 0 }; });

        Promise.all([signReq, taskReq]).then(function (results) {
            var signRes = results[0];
            var taskRes = results[1];
            var code = Number(signRes.code);
            var dailyPts = dailyPointsFromTasks(taskRes);

            if (code === 1401 || !signRes.info) {
                var serialEl = doc.getElementById('mac-checkin-serial');
                if (serialEl) { serialEl.textContent = '0'; }
                renderNextHint(null, 0, false);
                toggleGuestMilestones(true);
                renderCta(root, false, false);
                renderRules(null, dailyPts, false);
                return;
            }

            if (code !== 1) {
                ML.notify(signRes.msg || lang('string_load_err', '加载失败'));
                return;
            }

            var info = signRes.info;
            toggleGuestMilestones(false);
            renderMilestones(info.milestones || [], coinImg);
            var streak = Number(info.serial_days) || 0;
            var serialEl = doc.getElementById('mac-checkin-serial');
            if (serialEl) { serialEl.textContent = String(streak); }
            renderNextHint(info, streak, true);
            renderCta(root, !!info.is_signed_today, true);
            renderRules(info, dailyPts, true);
        }).catch(function () {
            ML.notify(lang('string_load_err', '网络错误'));
        });
    }

    function dailySign(root) {
        if (signing) { return; }
        signing = true;
        var btn = doc.getElementById('mac-checkin-cta-btn');
        if (btn) { btn.disabled = true; }
        ML.postForm(taskUrl('daily_sign'), {}).then(function (res) {
            signing = false;
            if (Number(res.code) !== 1) {
                ML.notify(res.msg || lang('string_load_err', '签到失败'));
                loadState(root);
                return;
            }
            var msg = signSuccessMessage(res);
            toast(msg);
            refreshHero();
            loadState(root);
        }).catch(function () {
            signing = false;
            ML.notify(lang('string_load_err', '网络错误'));
            loadState(root);
        });
    }

    function claimMilestone(root, milestoneId) {
        if (claimingId) { return; }
        claimingId = milestoneId;
        ML.postForm(taskUrl('claim_sign_milestone'), { milestone_id: milestoneId }).then(function (res) {
            claimingId = 0;
            if (Number(res.code) !== 1) {
                ML.notify(res.msg || lang('string_load_err', '领取失败'));
                loadState(root);
                return;
            }
            var msg = (res.msg || lang('milestone/claim_ok', '领取成功'));
            if (res.info && res.info.points != null && Number(res.info.points) > 0) {
                msg += '，获得 ' + res.info.points + ' 金币';
            }
            toast(msg);
            refreshHero();
            loadState(root);
        }).catch(function () {
            claimingId = 0;
            ML.notify(lang('string_load_err', '网络错误'));
        });
    }

    function openLogin() {
        var login = ML.loginUrl();
        var back = win.location.href || '/';
        if (win.MAC && win.MAC.User) {
            if (typeof win.MAC.User.openLoginOrNavigate === 'function') {
                win.MAC.User.openLoginOrNavigate(login, back);
                return;
            }
            if (typeof win.MAC.User.Login === 'function') {
                win.MAC.User.Login();
                return;
            }
        }
        win.location.href = login + (login.indexOf('?') >= 0 ? '&' : '?') + 'url=' + encodeURIComponent(back);
    }

    function bindEvents(root) {
        var ctaWrap = doc.getElementById('mac-checkin-cta-wrap');
        if (ctaWrap && !ctaWrap.getAttribute('data-mac-checkin-bound')) {
            ctaWrap.setAttribute('data-mac-checkin-bound', '1');
            ctaWrap.addEventListener('click', function (e) {
                if (e.target.closest('#mac-checkin-cta-login')) {
                    e.preventDefault();
                    openLogin();
                    return;
                }
                var signBtn = e.target.closest('#mac-checkin-cta-btn');
                if (signBtn && !signBtn.disabled) {
                    dailySign(root);
                }
            });
        }

        var milestones = doc.getElementById('mac-checkin-milestones-root');
        if (milestones && !milestones.getAttribute('data-mac-checkin-bound')) {
            milestones.setAttribute('data-mac-checkin-bound', '1');
            milestones.addEventListener('click', function (e) {
                var btn = e.target.closest('.uc-mile__claim');
                if (!btn || btn.disabled) { return; }
                var id = btn.getAttribute('data-milestone-id');
                if (!id) { return; }
                btn.disabled = true;
                claimMilestone(root, id);
            });
        }
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            startCheckin();
        }
        if (typeof win.MacMemberReady === 'function') {
            win.MacMemberReady(start);
        } else if (win.MacMemberList) {
            start(win.MacMemberList);
        }
    }

    function startCheckin() {
        var root = rootEl();
        if (!root) { return; }
        bindEvents(root);
        applySectionAria();
        if (win.MAC && win.MAC.User && typeof win.MAC.User.getAuthMe === 'function') {
            var auth = win.MAC.User.getAuthMe();
            if (auth) { syncHero(auth); }
        }
        refreshHero();
        loadState(root);
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    doc.addEventListener('mac:auth-me-ready', function () {
        if (win.MAC && win.MAC.User && typeof win.MAC.User.getAuthMe === 'function') {
            syncHero(win.MAC.User.getAuthMe());
        }
        var root = rootEl();
        if (root) { loadState(root); }
    });
})(window, document);
