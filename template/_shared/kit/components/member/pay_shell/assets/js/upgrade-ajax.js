
(function () {
    'use strict';

    function escAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escText(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function tplPath(root) {
        var p = (root.getAttribute('data-path-tpl') || '').trim();
        if (!p) {
            return '';
        }
        return p.replace(/\/?$/, '/');
    }

    /** 角标等图走 page-builder-components/_shared/img/upgrade/ */
    function upgradeImgBase(root) {
        var p = (root.getAttribute('data-upgrade-img-base') || '').trim();
        if (p) {
            return p.replace(/\/?$/, '/');
        }
        var tpl = tplPath(root);
        if (tpl) {
            return tpl + 'page-builder-components/_shared/img/upgrade/';
        }
        var m = (window.maccms && maccms.path_tpl) || '';
        m = String(m).replace(/\/?$/, '/');
        if (m) {
            return m + 'page-builder-components/_shared/img/upgrade/';
        }
        return '';
    }

    function applyDataLang(container) {
        if (typeof window.jQuery === 'undefined' || !jQuery.i18n || !jQuery.i18n.map) {
            return;
        }
        var map = jQuery.i18n.map;
        if (!map) {
            return;
        }
        jQuery(container)
            .find('[data-lang]')
            .addBack('[data-lang]')
            .each(function () {
                var k = jQuery(this).attr('data-lang');
                if (k && map[k]) {
                    jQuery(this).text(map[k]);
                }
            });
    }

    function setExpireMetric(el, mode, dateText) {
        if (!el) {
            return;
        }
        el.removeAttribute('data-loading');
        if (mode === 'guest') {
            el.innerHTML = '<span data-lang="user_not_logged_in">未登录</span>';
        } else if (mode === 'permanent') {
            el.innerHTML = '<span data-lang="user_valid">永久有效</span>';
        } else if (mode === 'date') {
            el.textContent = dateText || '--';
            return;
        } else {
            el.textContent = '--';
            return;
        }
        applyDataLang(el);
    }

    function setLevelMetric(el, mode, name) {
        if (!el) {
            return;
        }
        el.removeAttribute('data-loading');
        if (mode === 'guest') {
            el.innerHTML = '<span data-lang="user_guest">游客</span>';
            applyDataLang(el);
        } else {
            el.textContent = name || '--';
        }
    }

    function moneyBlock(points, price) {
        var p = parseInt(points, 10) || 0;
        if (p === 0) {
            return '<p class="point point--stack is-free"><span class="point-money">免费开通</span></p>';
        }
        var cash = price != null && String(price) !== '' ? String(price) : String(p);
        return (
            '<p class="point point--stack">' +
            '<span class="point-money"><span class="point-money__currency">¥</span>' +
            escText(cash) +
            '</span></p>'
        );
    }

    function pillClass(points) {
        return (parseInt(points, 10) || 0) === 0 ? 'point-pill is-free' : 'point-pill';
    }

    var PLAN_ROWS = [
        { long: 'day', idx: 0, tagLang: 'string_user_bt', timeLang: 'string_user_24xs', label: '日卡', ribbon: '' },
        { long: 'week', idx: 1, tagLang: 'string_user_bz', timeLang: 'string_user_7t', label: '周卡', ribbon: '' },
        { long: 'month', idx: 2, tagLang: 'string_user_by', timeLang: 'string_user_30t', label: '月卡', ribbon: 'hot' },
        { long: 'year', idx: 3, tagLang: 'string_user_bn', timeLang: 'string_user_365t', label: '年卡', ribbon: 'value' }
    ];

    function formatCountdown(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        function p(n) {
            return n < 10 ? '0' + n : String(n);
        }
        return p(h) + ':' + p(m) + ':' + p(s);
    }

    var _countdownTimer = null;

    function stopPlanCountdowns() {
        if (_countdownTimer) {
            clearInterval(_countdownTimer);
            _countdownTimer = null;
        }
    }

    function startPlanCountdowns(root) {
        stopPlanCountdowns();
        _countdownTimer = setInterval(function () {
            var nodes = root.querySelectorAll('.plan-countdown[data-remain]');
            var needReload = false;
            Array.prototype.forEach.call(nodes, function (n) {
                var r = Math.max(0, (parseInt(n.getAttribute('data-remain'), 10) || 0) - 1);
                n.setAttribute('data-remain', String(r));
                n.textContent = formatCountdown(r);
                if (r <= 0) needReload = true;
            });
            if (needReload) {
                stopPlanCountdowns();
                loadUpgradeData(root);
            }
        }, 1000);
    }

    function renderGroupBlock(g, gi, imgBase) {
        var gid = g.group_id;
        var gname = g.group_name || '';
        var nameAttr = escAttr(gname);
        var chipText = escText(gname);
        var parts = [];
        var planCount = 0;
        parts.push('<div class="group-block">');
        parts.push('<div class="group-head group-head--vip-chip"><div class="group-vip-chip">');
        parts.push('<span class="group-vip-chip__text">' + chipText + '</span>');
        parts.push('</div></div><ul class="shengji group-plans group-plans--grid">');

        for (var r = 0; r < PLAN_ROWS.length; r++) {
            var row = PLAN_ROWS[r];
            var lk = row.long;
            var pts = parseInt(g['group_points_' + lk], 10) || 0;
            // 积分为 0 的时长不展示
            if (pts <= 0) {
                continue;
            }
            planCount++;
            var price = g['price_' + lk];
            var isAct = parseInt(g['is_activity_' + lk], 10) === 1;
            var cd = parseInt(g['activity_countdown_' + lk], 10) || 0;
            var selClass = gi === 0 && lk === 'month' ? 'shengji-item select-vip' : 'shengji-item';
            var ribbon = '';
            if (row.ribbon === 'hot') {
                ribbon = '<span class="plan-ribbon plan-ribbon--hot">热门</span>';
            } else if (row.ribbon === 'value') {
                ribbon = '<span class="plan-ribbon plan-ribbon--value">超值</span>';
            }
            parts.push('<li data-index="' + row.idx + '" class="' + selClass + '">');
            parts.push(ribbon);
            parts.push(
                '<a class="grade" data-id="' +
                    escAttr(String(gid)) +
                    '" data-name="' +
                    nameAttr +
                    '" data-points="' +
                    escAttr(String(pts)) +
                    '" data-price="' +
                    escAttr(String(price)) +
                    '" data-long="' +
                    escAttr(lk) +
                    '" data-is-activity="' +
                    (isAct && cd > 0 ? '1' : '0') +
                    '">'
            );
            parts.push('<div class="plan-card-head">');
            parts.push(
                '<p class="time">' +
                    escText(row.label) +
                    '</p>'
            );
            if (isAct && cd > 0) {
                parts.push('<span class="plan-activity-badge">限时</span>');
                parts.push(
                    '<span class="plan-countdown" data-remain="' +
                        cd +
                        '">' +
                        formatCountdown(cd) +
                        '</span>'
                );
            }
            parts.push('</div><div class="plan-card-body">');
            parts.push(moneyBlock(pts, price));
            parts.push('</div><div class="plan-card-foot">');
            parts.push(
                '<span class="' +
                    pillClass(pts) +
                    '">' +
                    escText(String(pts)) +
                    ' <span data-lang="string_jf">金币</span></span>'
            );
            parts.push('</div></a></li>');
        }
        parts.push('</ul></div>');
        if (planCount === 0) {
            return '';
        }
        return parts.join('');
    }

    function loadUpgradeData(root) {
        var url = root.getAttribute('data-url-upgrade-data');
        if (!url) {
            return;
        }
        var imgBase = upgradeImgBase(root);
        var groupsEl = root.querySelector('.js-upgrade-groups');
        var loadingEl = root.querySelector('.js-upgrade-groups-loading');
        var ptEl = root.querySelector('#buy_user_points');
        var mPt = root.querySelector('.js-upgrade-metric-points');
        var mEx = root.querySelector('.js-upgrade-metric-expire');
        var mLv = root.querySelector('.js-upgrade-metric-level');

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (json) {
                if (!json || Number(json.code) !== 1 || !json.data) {
                    var msg = (json && json.msg) || '';
                    throw new Error(msg || 'bad response');
                }
                var d = json.data;
                window.macBuyUpgradeState = {
                    isLogin: !!d.is_login,
                    userPoints: parseInt(d.user_points, 10) || 0,
                    expireMode: d.expire_mode,
                    expireDate: d.expire_date,
                    memberMode: d.member_mode,
                    memberName: d.member_name,
                    metricsLoaded: true
                };

                var ptsStr = String(d.user_points != null ? d.user_points : 0);
                if (ptEl) {
                    ptEl.textContent = ptsStr;
                }
                if (mPt) {
                    mPt.textContent = ptsStr;
                }
                setExpireMetric(mEx, d.expire_mode, d.expire_date);
                setLevelMetric(mLv, d.member_mode, d.member_name);

                if (!groupsEl) {
                    if (loadingEl) {
                        loadingEl.remove();
                    }
                    applyDataLang(root.querySelector('.user-buy-upgrade-panel--upgrade') || root);
                    return;
                }

                var groups = d.groups || [];
                if (loadingEl) {
                    loadingEl.remove();
                }
                if (!groups.length) {
                    groupsEl.innerHTML =
                        '<p class="text_muted js-upgrade-empty" data-lang="string_upgrade_no_plans">暂无可购买的会员套餐</p>';
                    applyDataLang(groupsEl);
                    return;
                }

                var html = [];
                for (var i = 0; i < groups.length; i++) {
                    var block = renderGroupBlock(groups[i], i, imgBase);
                    if (block) html.push(block);
                }
                if (!html.length) {
                    groupsEl.innerHTML =
                        '<p class="text_muted js-upgrade-empty" data-lang="string_upgrade_no_plans">暂无可购买的会员套餐</p>';
                    applyDataLang(groupsEl);
                    return;
                }
                groupsEl.innerHTML = html.join('');
                applyDataLang(groupsEl);
                startPlanCountdowns(root);
                if (window.MacUpgradeCheckout && typeof MacUpgradeCheckout.sync === 'function') {
                    MacUpgradeCheckout.sync(root);
                }
                if (window.MacUserCoupon && MacUserCoupon.getByMount) {
                    var vipPanel = MacUserCoupon.getByMount('buy-upgrade');
                    if (vipPanel) vipPanel.scheduleRefresh();
                }
            })
            .catch(function () {
                window.macBuyUpgradeState = window.macBuyUpgradeState || { isLogin: false, userPoints: 0 };
                stopPlanCountdowns();
                if (loadingEl) {
                    loadingEl.setAttribute('data-lang', 'string_load_err');
                    loadingEl.textContent =
                        (typeof MAC !== 'undefined' && MAC.GetLang && MAC.GetLang('string_load_err')) ||
                        '加载失败，请稍后重试';
                }
                if (mEx) {
                    mEx.textContent = '--';
                }
                if (mLv) {
                    mLv.textContent = '--';
                }
            });
    }

    window.macReloadUpgradeData = loadUpgradeData;

    function getBuyUpgradeTabFromBody() {
        if (document.body.classList.contains('body-buy-upgrade-tab-upgrade')) {
            return 'upgrade';
        }
        return 'recharge';
    }

    function inferBuyUpgradeTabFromUrl() {
        var path = (window.location.pathname || '') + (window.location.search || '');
        if (/user\/upgrade|\/upgrade(?:\.html|\/|\?|$)/i.test(path)) {
            return 'upgrade';
        }
        if (/user\/buy|\/buy(?:\.html|\/|\?|$)/i.test(path)) {
            return 'recharge';
        }
        return null;
    }

    function restoreUpgradeMetrics(root) {
        var state = window.macBuyUpgradeState;
        if (!root || !state || !state.metricsLoaded) {
            return;
        }
        setExpireMetric(root.querySelector('.js-upgrade-metric-expire'), state.expireMode, state.expireDate);
        setLevelMetric(root.querySelector('.js-upgrade-metric-level'), state.memberMode, state.memberName);
    }

    function setBuyUpgradeTab(which) {
        var w = which === 'upgrade' ? 'upgrade' : 'recharge';
        document.body.classList.remove('body-buy-upgrade-tab-recharge', 'body-buy-upgrade-tab-upgrade');
        document.body.classList.add(
            w === 'upgrade' ? 'body-buy-upgrade-tab-upgrade' : 'body-buy-upgrade-tab-recharge'
        );
        document.querySelectorAll('[data-mac-buy-upgrade-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-mac-buy-upgrade-tab') === w;
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.classList.toggle('is-active', active);
        });
        var root = document.getElementById('mac-user-buy-root');
        if (root) {
            var recharge = root.querySelector('.member-pay-panel--recharge, .user-buy-upgrade-panel--recharge');
            var upgrade = root.querySelector('.member-pay-panel--upgrade, .user-buy-upgrade-panel--upgrade');
            if (recharge) {
                recharge.hidden = w !== 'recharge';
            }
            if (upgrade) {
                upgrade.hidden = w !== 'upgrade';
            }
            if (w === 'upgrade') {
                restoreUpgradeMetrics(root);
            }
        }
    }

    /**
     * Tab 仅前端切换；首屏高亮由入口页 body 类 + URL 推断（与 buy / upgrade 路由一致）
     */
    function initBuyUpgradeTabs() {
        var nav = document.querySelector('.user-buy-upgrade-tabs');
        if (!nav) {
            return;
        }
        var fromUrl = inferBuyUpgradeTabFromUrl();
        if (fromUrl) {
            setBuyUpgradeTab(fromUrl);
        } else {
            setBuyUpgradeTab(getBuyUpgradeTabFromBody());
        }

        nav.querySelectorAll('[data-mac-buy-upgrade-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var w = btn.getAttribute('data-mac-buy-upgrade-tab');
                if (w === 'recharge' || w === 'upgrade') {
                    setBuyUpgradeTab(w);
                }
            });
        });
    }

    function init() {
        var root = document.getElementById('mac-user-buy-root');
        if (!root) {
            return;
        }
        initBuyUpgradeTabs();
        loadUpgradeData(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
