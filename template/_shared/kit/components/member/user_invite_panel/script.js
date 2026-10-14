/**
 * twigspike 邀请好友页 — [data-mac-user-invite]
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;

    function siteOrigin() {
        if (win.maccms && win.maccms.base_url) {
            return String(win.maccms.base_url).replace(/\/+$/, '');
        }
        return win.location.protocol + '//' + win.location.host;
    }

    function buildInviteLinks(regPath, uid, code) {
        var origin = siteOrigin();
        var path = regPath || '/';
        if (path.indexOf('/') !== 0) { path = '/' + path; }
        var linkUid = origin + path + '?uid=' + encodeURIComponent(String(uid || ''));
        var linkCode = code
            ? origin + path + '?invite_code=' + encodeURIComponent(String(code))
            : '';
        return { linkUid: linkUid, linkCode: linkCode };
    }

    function lang(key, fallback) {
        try {
            if (win.MAC && win.MAC.GetLang && key) {
                var t = win.MAC.GetLang(key);
                if (t && t !== key) { return t; }
            }
        } catch (e) { /* ignore */ }
        return fallback || '';
    }

    function fill(tpl, vars) {
        return String(tpl || '').replace(/\{(\w+)\}/g, function (m, k) {
            return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
        });
    }

    var LONG_FALLBACK = { day: '1天', week: '1周', month: '1个月', year: '1年' };

    function tierGift(tier) {
        var parts = [];
        if (tier.group_id && tier.long) {
            parts.push(fill(lang('invite_reward_vip_tpl', '{group}（{long}）'), {
                group: tier.group_name || 'VIP',
                long: lang('invite_reward_long_' + tier.long, LONG_FALLBACK[tier.long] || tier.long)
            }));
        }
        var pts = parseInt(tier.points, 10) || 0;
        if (pts > 0) {
            parts.push(fill(lang('invite_reward_points_tpl', '{n} 积分'), { n: pts }));
        }
        // .properties 值会被 trim，英文逗号等 ASCII 分隔符需补空格
        var sep = lang('invite_reward_sep', '、');
        if (/^[,;\/+]$/.test(sep)) { sep += ' '; }
        return parts.join(sep);
    }

    /**
     * 邀请奖励规则卡：注册奖励积分 + 后台「邀请奖励」阶梯（邀请满 N 人送 VIP/积分）
     */
    function renderRules(root, rules) {
        var card = root.querySelector('.mac-invite-reward');
        if (!card) { return; }
        rules = rules || {};
        var regPoints = parseInt(rules.reg_points, 10) || 0;
        var tiers = Number(rules.reward_status) === 1 && Array.isArray(rules.tiers) ? rules.tiers : [];
        if (regPoints < 1 && !tiers.length) {
            card.hidden = true;
            return;
        }
        var inviteCount = parseInt(rules.invite_count, 10) || 0;

        var progressEl = card.querySelector('.mac-invite-reward-progress');
        if (progressEl) {
            var parts = lang('invite_reward_progress_tpl', '已累计邀请 {n} 人').split('{n}');
            progressEl.innerHTML = parts.map(ML.escText).join('<strong>' + inviteCount + '</strong>');
        }

        var regEl = card.querySelector('.mac-invite-reward-reg');
        if (regEl) {
            regEl.hidden = regPoints < 1;
            regEl.textContent = regPoints > 0
                ? fill(lang('invite_reward_reg_tpl', '每成功邀请 1 位好友注册，立即获得 {n} 积分'), { n: regPoints })
                : '';
        }

        var subEl = card.querySelector('.mac-invite-reward-sub');
        var listEl = card.querySelector('.mac-invite-reward-tiers');
        if (subEl) { subEl.hidden = !tiers.length; }
        if (listEl) {
            listEl.hidden = !tiers.length;
            listEl.innerHTML = tiers.map(function (tier) {
                var count = parseInt(tier.count, 10) || 0;
                var done = Number(tier.achieved) === 1;
                var state;
                if (done) {
                    state = lang('invite_reward_done', '已到账');
                } else if (inviteCount >= count) {
                    state = lang('invite_reward_pending', '待发放');
                } else {
                    state = fill(lang('invite_reward_remain_tpl', '还差 {n} 人'), { n: count - inviteCount });
                }
                return '<li class="invite-reward-tier' + (done ? ' is-done' : '') + '">' +
                    '<div class="invite-reward-tier__main">' +
                    '<p class="invite-reward-tier__count">' +
                    ML.escText(fill(lang('invite_reward_tier_tpl', '邀请满 {n} 人'), { n: count })) + '</p>' +
                    '<p class="invite-reward-tier__gift">' + ML.escText(tierGift(tier)) + '</p>' +
                    '</div>' +
                    '<span class="invite-reward-tier__state">' + ML.escText(state) + '</span>' +
                    '</li>';
            }).join('');
        }
        card.hidden = false;
    }

    function subCount(row) {
        if (row.sub_invitees_count != null) { return parseInt(row.sub_invitees_count, 10) || 0; }
        if (row.sub_count != null) { return parseInt(row.sub_count, 10) || 0; }
        return 0;
    }

    function renderTableRows(rows) {
        return rows.map(function (row) {
            return '<tr><td align="center" valign="middle">' + ML.escText(row.user_id) +
                '</td><td align="center" valign="middle">' + ML.escText(row.user_name) +
                '</td><td align="center" valign="middle">' +
                ML.escText(row.user_invite_count != null ? row.user_invite_count : '0') +
                '</td><td align="center" valign="middle">' + subCount(row) +
                '</td><td align="center" valign="middle">' +
                ML.escText(ML.fmtDateTime(row.user_reg_time)) + '</td></tr>';
        }).join('');
    }

    function renderMobileRows(rows) {
        return rows.map(function (row) {
            return '<div class="invite-mobile-item">' +
                '<p><span class="label">UID：</span><span class="value">' + ML.escText(row.user_id) + '</span></p>' +
                '<p><span class="label">账号：</span><span class="value">' + ML.escText(row.user_name) + '</span></p>' +
                '<p><span class="label">邀请人数：</span><span class="value">' +
                ML.escText(row.user_invite_count != null ? row.user_invite_count : '0') + '</span></p>' +
                '<p><span class="label">二级下线：</span><span class="value">' + subCount(row) + '</span></p>' +
                '<p><span class="label">注册时间：</span><span class="value">' +
                ML.escText(ML.fmtDateTime(row.user_reg_time)) + '</span></p></div>';
        }).join('');
    }

    function copyText(text) {
        var payload = String(text || '').trim();
        if (!payload) { return; }
        var okMsg = (win.MAC && win.MAC.GetLang) ? win.MAC.GetLang('copy_success') : '复制成功';
        var failMsg = '复制失败，请长按链接手动复制';
        function done(msg) {
            if (typeof win.macToast === 'function') {
                win.macToast(msg);
            } else if (win.MacTheme && typeof win.MacTheme.toast === 'function') {
                win.MacTheme.toast(msg);
            } else if (win.MacTheme && typeof win.MacTheme.notify === 'function') {
                win.MacTheme.notify(msg);
            } else if (win.MAC && win.MAC.Pop && typeof win.MAC.Pop.Msg === 'function') {
                win.MAC.Pop.Msg(280, 44, msg, 1800);
            } else if (win.MAC && win.MAC.alert) {
                win.MAC.alert(msg);
            } else {
                try { win.alert(msg); } catch (e) {}
            }
        }
        function fallback() {
            try {
                var ta = doc.createElement('textarea');
                ta.value = payload;
                ta.setAttribute('readonly', '');
                ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
                doc.body.appendChild(ta);
                ta.select();
                doc.execCommand('copy');
                doc.body.removeChild(ta);
                done(okMsg);
            } catch (e) {
                done(failMsg);
            }
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(payload).then(function () {
                done(okMsg);
            }).catch(fallback);
        } else {
            fallback();
        }
    }

    function initRoot(root) {
        if (root.getAttribute('data-mac-invite-inited') === '1') { return; }
        root.setAttribute('data-mac-invite-inited', '1');

        var apiBase = ML.normalizeApiBase(root.getAttribute('data-api-base'));
        var regPath = root.getAttribute('data-reg-path') || '';
        var pageSize = parseInt(root.getAttribute('data-limit') || '20', 10) || 20;
        var noCodeText = root.getAttribute('data-text-no-code') || '暂未生成（请重新登录）';
        var busy = false;
        var cooldownUntil = 0;

        function updateHeader(user) {
            user = user || {};
            var code = user.user_invite_code || '';
            var codeEl = root.querySelector('.mac-invite-code');
            if (codeEl) { codeEl.textContent = code || noCodeText; }
            var links = buildInviteLinks(regPath, user.user_id, code);
            var uidLink = root.querySelector('.mac-invite-link-uid');
            if (uidLink) {
                uidLink.setAttribute('href', links.linkUid);
                uidLink.textContent = links.linkUid;
            }
            var codeRow = root.querySelector('.mac-invite-row-code');
            var codeLink = root.querySelector('.mac-invite-link-code');
            if (links.linkCode && codeRow && codeLink) {
                codeRow.style.display = '';
                codeLink.setAttribute('href', links.linkCode);
                codeLink.textContent = links.linkCode;
            } else if (codeRow) {
                codeRow.style.display = 'none';
            }
        }

        function loadPage(page) {
            if (busy || Date.now() < cooldownUntil) { return; }
            busy = true;
            var loading = root.querySelector('.mac-invite-loading');
            if (loading) { loading.style.display = ''; }

            var qs = 'page=' + page + '&limit=' + pageSize;
            try {
                var params = new URLSearchParams(win.location.search);
                var uid = params.get('uid');
                if (uid && win.MAC && win.MAC.User && typeof win.MAC.User.getAuthMe === 'function') {
                    var me = win.MAC.User.getAuthMe();
                    if (me && Number(me.is_login) === 1 && String(me.user_id) === String(uid)) {
                        qs += '&user_id=' + encodeURIComponent(uid);
                    }
                }
            } catch (e) { /* ignore */ }

            ML.apiJson(apiBase + '/user/get_invite_list?' + qs).then(function (res) {
                busy = false;
                if (loading) { loading.style.display = 'none' };
                if (!res || Number(res.code) !== 1) {
                    if (ML.requireLoginOrRedirect(res && res.code)) { return; }
                    cooldownUntil = Date.now() + 4000;
                    ML.notify((res && res.msg) || '加载失败');
                    return;
                }
                var info = res.info || {};
                var rows = info.list || [];
                var total = parseInt(info.total, 10) || 0;
                updateHeader(info.user || {});
                renderRules(root, info.rules);
                var totalEl = root.querySelector('.mac-invite-total');
                if (totalEl) { totalEl.textContent = String(total); }

                var emptyEl = root.querySelector('.mac-invite-empty');
                var tableWrap = root.querySelector('.invite-table-wrap');
                var tbody = root.querySelector('.mac-invite-tbody');
                var mobileList = root.querySelector('.invite-mobile-list');
                var pagerEl = root.querySelector('.member-page');

                if (!rows.length) {
                    if (emptyEl) { emptyEl.style.display = ''; }
                    if (tableWrap) { tableWrap.style.display = 'none'; }
                    if (mobileList) { mobileList.style.display = 'none'; }
                    if (pagerEl) { pagerEl.innerHTML = ''; }
                    return;
                }
                if (emptyEl) { emptyEl.style.display = 'none'; }
                if (tableWrap) { tableWrap.style.display = ''; }
                if (mobileList) { mobileList.style.display = ''; }
                if (tbody) { tbody.innerHTML = renderTableRows(rows); }
                if (mobileList) { mobileList.innerHTML = renderMobileRows(rows); }
                if (pagerEl) {
                    var pages = Math.max(1, Math.ceil(total / pageSize));
                    pagerEl.innerHTML = pages > 1 ? ML.buildPager(page, pages, 'mac-invite-page') : '';
                }
            }).catch(function () {
                busy = false;
                if (loading) { loading.style.display = 'none'; }
                cooldownUntil = Date.now() + 4000;
                ML.notify('加载失败');
            });
        }

        ML.bindPager(root, '.mac-invite-page', loadPage);

        root.addEventListener('click', function (e) {
            var copyBtn = e.target.closest('.invite-link-copy');
            if (!copyBtn) { return; }
            e.preventDefault();
            e.stopPropagation();
            var mode = copyBtn.getAttribute('data-invite-copy');
            var sel = mode === 'code' ? '.mac-invite-link-code' : '.mac-invite-link-uid';
            var linkEl = root.querySelector(sel);
            if (!linkEl) { return; }
            var href = linkEl.getAttribute('href') || '';
            var text = (linkEl.textContent || '').trim();
            copyText(href && href !== '#' ? href : text);
        });

        loadPage(1);
    }

    function boot() {
        function start(memberList) {
            ML = memberList;
            var roots = doc.querySelectorAll('[data-mac-user-invite]');
            for (var i = 0; i < roots.length; i++) {
                initRoot(roots[i]);
            }
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
