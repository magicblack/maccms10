/**
 * twigspike 社交动态页 — [data-mac-social]
 */
(function (win, doc) {
    'use strict';

    var ML = win.MacMemberList;
    var PAGE_LIMIT = 10;

    function langText(key, fallback) {
        if (win.MacTheme && MacTheme.i18n && MacTheme.i18n.map &&
            Object.prototype.hasOwnProperty.call(MacTheme.i18n.map, key)) {
            return MacTheme.i18n.map[key];
        }
        return fallback || '';
    }

    function setLangEl(el, key, fallback) {
        if (!el || !key) { return; }
        el.setAttribute('data-lang', key);
        el.textContent = langText(key, fallback);
    }

    function qs(sel, ctx) {
        return (ctx || doc).querySelector(sel);
    }

    function qsa(sel, ctx) {
        return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel));
    }

    function fmtTime(ts) {
        var n = parseInt(ts, 10);
        if (!n) { return ''; }
        if (n < 1e12) { n *= 1000; }
        var d = new Date(n);
        if (isNaN(d.getTime())) { return ''; }
        function pad(v) { return v < 10 ? '0' + v : String(v); }
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function dynText(row) {
        var text = row && row.dyn_text ? String(row.dyn_text) : '';
        if (row && row.dynamics_type === 'follow') {
            return text.replace(/\s*(?:→|->)\s*/, ' 关注了 ');
        }
        return text;
    }

    function isDefaultPortrait(url) {
        if (!url) { return true; }
        // 站点默认头像 static(_new)/images/touxiang.png 为刺眼绿色剪影，改用主色字母占位
        return /(?:^|[\/?])touxiang\.png(?:\?|#|$)/i.test(url) ||
            /static(?:_new)?\/images\/touxiang\.png/i.test(url);
    }

    function avatarHtml(src, name) {
        var url = String(src || '').trim();
        var label = ML.escText(name);
        if (url && !/^javascript:/i.test(url) && !isDefaultPortrait(url)) {
            return '<img src="' + ML.escAttr(url) + '" alt="' + label +
                '" loading="lazy" decoding="async">';
        }
        var letter = ML.escText(String(name || '?').charAt(0) || '?');
        return '<span class="mac-social__avatar-letter" aria-hidden="true">' + letter + '</span>';
    }

    function contentUrl(mid, rid) {
        var typeId = parseInt(mid, 10) || 0;
        var resId = parseInt(rid, 10) || 0;
        if (!resId || [1, 2, 12].indexOf(typeId) === -1) { return ''; }
        var base = win.maccms && win.maccms.path != null
            ? String(win.maccms.path).replace(/\/+$/, '')
            : '';
        if (typeId === 2) { return base + '/index.php/art/detail/id/' + resId + '.html'; }
        if (typeId === 12) { return base + '/index.php/manga/detail/id/' + resId + '.html'; }
        return base + '/index.php/vod/detail/id/' + resId + '.html';
    }

    function parseViewFromUrl() {
        var view = new URLSearchParams(win.location.search).get('view') || 'dynamics';
        var allowed = ['dynamics', 'my_dynamics', 'following', 'fans', 'messages', 'conversation'];
        return allowed.indexOf(view) === -1 ? 'dynamics' : view;
    }

    function guardLogin(res) {
        if (res && (Number(res.code) === 1010 || Number(res.code) === 1401)) {
            ML.requireLoginOrRedirect(res.code);
            return Promise.reject(new Error('login required'));
        }
        return res;
    }

    function apiGet(url) {
        return ML.apiJson(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        }).then(guardLogin);
    }

    function apiPost(url, data) {
        return ML.postForm(url, data).then(guardLogin);
    }

    function SocialPage(root) {
        this.root = root;
        this.apiBase = ML.normalizeApiBase(root.getAttribute('data-api-base')).replace(/\/+$/, '');
        this.currentUid = parseInt(root.getAttribute('data-current-uid'), 10) || 0;
        this.pageType = parseViewFromUrl();
        this.peerUid = parseInt(new URLSearchParams(win.location.search).get('peer_uid'), 10) || 0;
        if (this.pageType === 'conversation' && !this.peerUid) {
            this.pageType = 'messages';
        }
        this.page = 1;
        this.pagecount = 1;
        this.total = 0;
        this.loading = false;
        this.requestToken = 0;
        this.peerHeaderReady = false;
        this.bind();
        this.switchView(this.pageType, { peerUid: this.peerUid, replace: true });
    }

    SocialPage.prototype.pageLimit = function () {
        if (this.pageType === 'conversation') { return 50; }
        return PAGE_LIMIT;
    };

    SocialPage.prototype.scrollContentTop = function () {
        var content = qs('[data-social-content]', this.root);
        if (!content) { return; }
        try {
            content.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            content.scrollIntoView(true);
        }
        content.scrollTop = 0;
    };

    SocialPage.prototype.endpoint = function () {
        var map = {
            dynamics: '/dynamics/feed',
            my_dynamics: '/dynamics/user_dynamics',
            following: '/follow/following',
            fans: '/follow/fans',
            messages: '/user_pm/conversations',
            conversation: '/user_pm/conversation'
        };
        return this.apiBase + (map[this.pageType] || '');
    };

    SocialPage.prototype.group = function () {
        if (this.pageType === 'following' || this.pageType === 'fans') { return 'following'; }
        if (this.pageType === 'messages' || this.pageType === 'conversation') { return 'messages'; }
        return 'dynamics';
    };

    SocialPage.prototype.isDynamicView = function () {
        return this.pageType === 'dynamics' || this.pageType === 'my_dynamics';
    };

    SocialPage.prototype.viewMeta = function () {
        var meta = {
            dynamics: {
                kickerKey: 'social_circle',
                kicker: '我的圈子',
                titleKey: 'social_friend_dynamics',
                title: '好友动态',
                emptyKey: 'social_empty_dynamics',
                empty: '暂时没有新动态',
                copyKey: 'social_empty_dynamics_copy',
                copy: '关注的人分享内容后，会出现在这里'
            },
            my_dynamics: {
                kickerKey: 'social_circle',
                kicker: '我的圈子',
                titleKey: 'social_my_dynamics',
                title: '我的动态',
                emptyKey: 'social_empty_my_dynamics',
                empty: '还没有发布动态',
                copyKey: 'social_empty_my_dynamics_copy',
                copy: '分享过的内容会集中保存在这里'
            },
            following: {
                kickerKey: 'social_kicker_network',
                kicker: '关系网络',
                titleKey: 'social_following',
                title: '我的关注',
                emptyKey: 'social_empty_following',
                empty: '还没有关注任何人',
                copyKey: 'social_empty_following_copy',
                copy: '关注感兴趣的用户，及时看到他们的分享'
            },
            fans: {
                kickerKey: 'social_kicker_network',
                kicker: '关系网络',
                titleKey: 'social_fans',
                title: '我的粉丝',
                emptyKey: 'social_empty_fans',
                empty: '还没有粉丝',
                copyKey: 'social_empty_fans_copy',
                copy: '有人关注你后，会出现在这里'
            },
            messages: {
                kickerKey: 'social_messages',
                kicker: '私信',
                titleKey: 'social_recent_conversations',
                title: '最近会话',
                emptyKey: 'social_empty_messages',
                empty: '还没有私信',
                copyKey: 'social_empty_messages_copy',
                copy: '和互相关注的人开始一段对话'
            },
            conversation: {
                kickerKey: 'social_messages',
                kicker: '私信',
                titleKey: 'social_conversation',
                title: '私信会话',
                emptyKey: 'social_empty_conversation',
                empty: '还没有消息',
                copyKey: 'social_empty_conversation_copy',
                copy: '发送第一条消息开始对话'
            }
        };
        return meta[this.pageType];
    };

    SocialPage.prototype.syncUrl = function (replace) {
        var url = new URL(win.location.href);
        url.searchParams.delete('view');
        url.searchParams.delete('peer_uid');
        if (this.pageType !== 'dynamics') {
            url.searchParams.set('view', this.pageType);
        }
        if (this.pageType === 'conversation') {
            url.searchParams.set('peer_uid', this.peerUid);
        }
        win.history[replace ? 'replaceState' : 'pushState'](
            { socialView: this.pageType, peerUid: this.peerUid },
            '',
            url.pathname + url.search
        );
    };

    SocialPage.prototype.renderNavigation = function () {
        var self = this;
        var group = this.group();

        qsa('[data-social-view]', this.root).forEach(function (btn) {
            var active = btn.getAttribute('data-social-view') === group;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-current', active ? 'page' : 'false');
        });

        var subtabs = qs('[data-social-subtabs]', this.root);
        if (subtabs) {
            if (group === 'dynamics') {
                subtabs.innerHTML =
                    '<button type="button" data-social-subview="dynamics" data-lang="social_friend_dynamics">' +
                    langText('social_friend_dynamics', '好友动态') + '</button>' +
                    '<button type="button" data-social-subview="my_dynamics" data-lang="social_my_dynamics">' +
                    langText('social_my_dynamics', '我的动态') + '</button>';
            } else if (group === 'following') {
                subtabs.innerHTML =
                    '<button type="button" data-social-subview="following" data-lang="social_following">' +
                    langText('social_following', '我的关注') + '</button>' +
                    '<button type="button" data-social-subview="fans" data-lang="social_fans">' +
                    langText('social_fans', '我的粉丝') + '</button>';
            } else {
                subtabs.innerHTML = '';
            }
            qsa('[data-social-subview]', subtabs).forEach(function (btn) {
                var active = btn.getAttribute('data-social-subview') === self.pageType;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        var info = this.viewMeta();
        var kicker = qs('[data-social-section-kicker]', this.root);
        var title = qs('[data-social-section-title]', this.root);
        var emptyTitle = qs('[data-social-empty-title]', this.root);
        var emptyCopy = qs('[data-social-empty-copy]', this.root);
        var mobileTitle = qs('[data-social-mobile-title]');

        if (kicker) { setLangEl(kicker, info.kickerKey, info.kicker); }
        if (title) { setLangEl(title, info.titleKey, info.title); }
        if (emptyTitle) { setLangEl(emptyTitle, info.emptyKey, info.empty); }
        if (emptyCopy) { setLangEl(emptyCopy, info.copyKey, info.copy); }
        if (mobileTitle) {
            var mobileKey = this.pageType === 'conversation'
                ? 'social_conversation'
                : group === 'following'
                    ? 'social_relationships'
                    : group === 'messages'
                        ? 'social_messages'
                        : 'social_circle';
            var mobileFallback = this.pageType === 'conversation'
                ? '私信会话'
                : group === 'following'
                    ? '关系'
                    : group === 'messages'
                        ? '私信'
                        : '我的圈子';
            setLangEl(mobileTitle, mobileKey, mobileFallback);
        }
    };

    SocialPage.prototype.switchView = function (view, opts) {
        opts = opts || {};
        var allowed = ['dynamics', 'my_dynamics', 'following', 'fans', 'messages', 'conversation'];
        if (allowed.indexOf(view) === -1) { view = 'dynamics'; }

        if (view === 'conversation') {
            this.peerUid = parseInt(opts.peerUid, 10) || this.peerUid;
            if (!this.peerUid) { view = 'messages'; }
            if (!opts.keepPeerHeader) { this.peerHeaderReady = false; }
            var peerAvatar = qs('[data-social-peer-avatar]', this.root);
            var peerName = qs('[data-social-peer-name]', this.root);
            var peerHint = qs('[data-social-peer-hint]', this.root);
            if (peerAvatar) { peerAvatar.setAttribute('data-user-card', this.peerUid); }
            if (peerName) { peerName.setAttribute('data-user-card', this.peerUid); }
            if (peerHint) { peerHint.setAttribute('data-user-card', this.peerUid); }
        }

        this.pageType = view;
        doc.body.classList.toggle('is-social-conversation', view === 'conversation');
        this.page = 1;
        this.pagecount = 1;
        this.root.setAttribute('data-page', view);
        this.root.className = this.root.className.replace(/\s*is-view-[\w-]+/g, '') +
            ' is-view-' + this.group();
        this.renderNavigation();

        var content = qs('[data-social-content]', this.root);
        var conversation = qs('[data-social-conversation]', this.root);
        var list = qs('[data-social-list]', this.root);
        var messages = qs('[data-social-messages]', this.root);
        var pager = qs('[data-social-pager]', this.root);

        if (content) { content.hidden = view === 'conversation'; }
        if (conversation) { conversation.hidden = view !== 'conversation'; }
        if (content) { content.scrollTop = 0; }
        if (list) { list.innerHTML = ''; }
        if (messages) { messages.innerHTML = ''; }
        if (pager) { pager.hidden = true; pager.innerHTML = ''; }

        this.setState('loading');
        if (!opts.fromPopstate) {
            this.syncUrl(opts.replace === true);
        }
        this.load(1);
    };

    SocialPage.prototype.setState = function (state) {
        var loading = qs('[data-social-loading]', this.root);
        var empty = qs('[data-social-empty]', this.root);
        if (loading) {
            loading.hidden = state !== 'loading' || this.pageType === 'conversation';
        }
        if (empty) {
            empty.hidden = state !== 'empty' || this.pageType === 'conversation';
        }
    };

    SocialPage.prototype.load = function (page, opts) {
        var self = this;
        opts = opts || {};
        if (this.loading) { return; }

        var token = ++this.requestToken;
        var url = this.endpoint();
        if (!url) { return; }

        this.loading = true;
        this.setState('loading');

        var limit = this.pageLimit();
        var req = url + '?page=' + encodeURIComponent(page) + '&limit=' + limit;
        if (this.pageType === 'conversation') {
            req += '&peer_uid=' + encodeURIComponent(this.peerUid);
        }

        apiGet(req).then(function (res) {
            if (token !== self.requestToken) { return; }
            if (!res || Number(res.code) !== 1) {
                throw new Error((res && res.msg) || '加载失败');
            }
            self.page = parseInt(res.page, 10) || 1;
            self.pagecount = Math.max(1, parseInt(res.pagecount, 10) || 1);
            self.total = parseInt(res.total, 10) || 0;
            if (self.pageType === 'conversation') {
                if (res.peer) {
                    self.renderPeer(res.peer);
                    self.peerHeaderReady = true;
                } else if (!self.peerHeaderReady) {
                    self.ensurePeerHeader();
                }
            }
            self.render(res.list || []);
            self.renderPager();
            if (opts.scrollTop) { self.scrollContentTop(); }
            if (self.pageType === 'conversation') {
                doc.dispatchEvent(new CustomEvent('mac:social-unread-refresh'));
            }
        }).catch(function (err) {
            if (token !== self.requestToken) { return; }
            if (err && err.message !== 'login required') {
                ML.notify(err.message || '加载失败');
            }
            self.setState('empty');
        }).finally(function () {
            if (token !== self.requestToken) { return; }
            self.loading = false;
            var loading = qs('[data-social-loading]', self.root);
            if (loading) { loading.hidden = true; }
        });
    };

    SocialPage.prototype.render = function (rows) {
        var target = this.pageType === 'conversation'
            ? qs('[data-social-messages]', this.root)
            : qs('[data-social-list]', this.root);
        if (!target) { return; }

        target.innerHTML = '';
        if (!rows.length) {
            if (this.pageType === 'conversation') {
                target.innerHTML =
                    '<div class="mac-social__conversation-empty" role="status">' +
                    '<div class="mac-social__conversation-empty-art" aria-hidden="true"></div>' +
                    '<strong>还没有消息</strong><small>发送第一条消息开始对话</small></div>';
            } else {
                this.setState('empty');
            }
            return;
        }

        this.setState('ready');
        if (this.pageType === 'following' || this.pageType === 'fans') {
            this.renderPeople(target, rows);
        } else if (this.pageType === 'messages') {
            this.renderConversations(target, rows);
        } else if (this.pageType === 'conversation') {
            this.renderMessages(target, rows);
        } else {
            this.renderDynamics(target, rows);
        }
    };

    SocialPage.prototype.renderPeople = function (listEl, rows) {
        var isFollowing = this.pageType === 'following';
        listEl.innerHTML = rows.map(function (row) {
            var uid = isFollowing ? row.follow_uid : row.user_id;
            var name = row.follow_user_name || row.user_name || ('用户 ' + uid);
            var following = isFollowing || Number(row.follow_mutual) === 1;
            var mutual = Number(row.follow_mutual) === 1;
            return '<li class="mac-social__item mac-social__person" data-user-id="' + ML.escAttr(uid) + '">' +
                '<button type="button" class="mac-social__profile-trigger mac-social__avatar" data-user-card="' +
                ML.escAttr(uid) + '" data-user-name="' + ML.escAttr(name) + '">' +
                avatarHtml(row.follow_portrait || row.user_portrait, name) +
                '</button><div class="mac-social__body"><div class="mac-social__row"><div>' +
                '<button type="button" class="mac-social__profile-trigger mac-social__name" data-user-card="' +
                ML.escAttr(uid) + '" data-user-name="' + ML.escAttr(name) + '">' + ML.escText(name) +
                '</button><div class="mac-social__meta">' +
                (mutual ? '互相关注 · ' : '') + ML.escText(fmtTime(row.follow_time)) +
                '</div></div><div class="mac-social__people-actions">' +
                (mutual
                    ? '<button type="button" class="mac-social__message-link" data-social-open-chat="' +
                      ML.escAttr(uid) + '" data-peer-name="' + ML.escAttr(name) + '">私信</button>'
                    : '') +
                '<button type="button" class="mac-social__action ' + (following ? 'is-secondary' : '') +
                '" data-social-follow data-user-id="' + ML.escAttr(uid) + '" data-following="' +
                (following ? '1' : '0') + '">' + (following ? '取消关注' : '关注') +
                '</button></div></div></div></li>';
        }).join('');
    };

    SocialPage.prototype.renderDynamics = function (listEl, rows) {
        listEl.innerHTML = rows.map(function (row) {
            var name = row.user_name || ('用户 ' + row.user_id);
            var typeText = row.dynamics_type_text || row.dynamics_type || '';
            var text = dynText(row);
            var link = contentUrl(row.dyn_mid, row.dyn_rid);
            var isShare = row.dynamics_type === 'share' &&
                Number(row.dyn_mid) > 0 && Number(row.dyn_rid) > 0;
            var attachment = isShare
                ? '<a class="mac-moments__attachment" href="' + ML.escAttr(link) +
                  '"><span class="mac-moments__attachment-mark" aria-hidden="true"></span><div><strong>' +
                  ML.escText(typeText) + '</strong><span>' + ML.escText(text) +
                  '</span></div><i aria-hidden="true">›</i></a>'
                : '';
            var contentLink = link && !isShare
                ? '<a class="mac-moments__content-link" href="' + ML.escAttr(link) +
                  '">查看内容 <span aria-hidden="true">›</span></a>'
                : '';
            return '<li class="mac-social__item mac-moments__item">' +
                '<button type="button" class="mac-social__profile-trigger mac-social__avatar mac-moments__avatar" data-user-card="' +
                ML.escAttr(row.user_id) + '" data-user-name="' + ML.escAttr(name) + '">' +
                avatarHtml(row.user_portrait, name) +
                '</button><article class="mac-social__body mac-moments__body">' +
                '<header class="mac-moments__header"><button type="button" class="mac-social__profile-trigger mac-social__name mac-moments__name" data-user-card="' +
                ML.escAttr(row.user_id) + '" data-user-name="' + ML.escAttr(name) + '">' +
                ML.escText(name) + '</button></header>' +
                (text && !isShare ? '<div class="mac-social__text mac-moments__text">' + ML.escText(text) + '</div>' : '') +
                attachment + contentLink +
                '<footer class="mac-moments__footer"><time>' + ML.escText(fmtTime(row.dyn_time)) +
                '</time><span>' + ML.escText(typeText) + '</span></footer></article></li>';
        }).join('');
    };

    SocialPage.prototype.renderConversations = function (listEl, rows) {
        listEl.innerHTML = rows.map(function (row) {
            var name = row.peer_name || ('用户 ' + row.peer_uid);
            return '<li class="mac-social__item mac-social__conversation-row">' +
                '<button type="button" class="mac-social__profile-trigger mac-social__avatar" data-user-card="' +
                ML.escAttr(row.peer_uid) + '" data-user-name="' + ML.escAttr(name) + '">' +
                avatarHtml(row.peer_portrait, name) +
                '</button><button type="button" class="mac-social__body mac-social__row" data-social-open-chat="' +
                ML.escAttr(row.peer_uid) + '" data-peer-name="' + ML.escAttr(name) +
                '" data-peer-portrait="' + ML.escAttr(row.peer_portrait || '') + '"><div>' +
                '<div class="mac-social__name">' + ML.escText(name) + '</div>' +
                '<div class="mac-social__text">' + ML.escText(row.last_content || '') + '</div></div>' +
                '<div class="mac-social__meta">' +
                (Number(row.unread) > 0 ? '<strong>' + ML.escText(row.unread) + '</strong>' : '') +
                '<time>' + ML.escText(fmtTime(row.last_time)) + '</time></div></button></li>';
        }).join('');
    };

    SocialPage.prototype.renderPeer = function (peer) {
        var uid = parseInt(peer.user_id, 10) || this.peerUid;
        var name = peer.user_name || '私信会话';
        var portrait = peer.user_portrait || '';
        var avatarBtn = qs('[data-social-peer-avatar]', this.root);
        var nameBtn = qs('[data-social-peer-name]', this.root);
        var hintBtn = qs('[data-social-peer-hint]', this.root);

        if (avatarBtn) {
            avatarBtn.innerHTML = avatarHtml(portrait, name);
            avatarBtn.setAttribute('data-user-card', uid);
            avatarBtn.setAttribute('data-user-name', name);
        }
        if (nameBtn) {
            nameBtn.textContent = name;
            nameBtn.setAttribute('data-user-card', uid);
            nameBtn.setAttribute('data-user-name', name);
        }
        if (hintBtn) {
            hintBtn.textContent = Number(peer.mutual) === 1
                ? '互相关注 · 点击查看个人主页'
                : '点击查看个人主页';
            hintBtn.setAttribute('data-user-card', uid);
            hintBtn.setAttribute('data-user-name', name);
        }
    };

    SocialPage.prototype.ensurePeerHeader = function () {
        var self = this;
        var uid = parseInt(this.peerUid, 10) || 0;
        if (!uid || this.peerHeaderReady) { return; }

        apiGet(this.apiBase + '/follow/profile?uid=' + encodeURIComponent(uid)).then(function (res) {
            if (self.peerUid !== uid || self.pageType !== 'conversation') { return; }
            if (!res || Number(res.code) !== 1 || !res.info) { return; }
            var info = res.info;
            self.renderPeer({
                user_id: info.user_id,
                user_name: info.display_name || info.user_name || '',
                user_portrait: info.user_portrait || '',
                mutual: info.mutual
            });
            self.peerHeaderReady = true;
        }).catch(function () { /* ignore */ });
    };

    SocialPage.prototype.renderMessages = function (box, rows) {
        var selfUid = this.currentUid;
        box.innerHTML = rows.map(function (row) {
            var selfMsg = Number(row.from_uid) === selfUid;
            return '<div class="mac-social__message' + (selfMsg ? ' is-self' : '') + '">' +
                '<button type="button" class="mac-social__profile-trigger mac-social__bubble" data-user-card="' +
                ML.escAttr(row.from_uid) + '">' + ML.escText(row.pm_content || '') +
                '<time>' + ML.escText(fmtTime(row.pm_time)) + '</time></button></div>';
        }).join('');
        box.scrollTop = box.scrollHeight;
    };

    SocialPage.prototype.renderPager = function () {
        var pager = qs('[data-social-pager]', this.root);
        if (!pager) { return; }
        if (this.pageType === 'conversation' || this.pagecount <= 1) {
            pager.hidden = true;
            pager.innerHTML = '';
            return;
        }
        pager.hidden = false;
        var summary = '共 ' + this.total + ' 条 · 第 ' + this.page + '/' + this.pagecount + ' 页';
        var listHtml = ML.buildPager
            ? ML.buildPager(this.page, this.pagecount, 'mac-social-pager__list')
            : '';
        pager.innerHTML = '<p class="mac-social-pager__summary">' + ML.escText(summary) + '</p>' + listHtml;
    };

    SocialPage.prototype.toggleFollow = function (btn) {
        var self = this;
        var uid = parseInt(btn.getAttribute('data-user-id'), 10) || 0;
        var following = btn.getAttribute('data-following') === '1';
        if (!uid || btn.disabled) { return; }

        btn.disabled = true;
        apiPost(this.apiBase + '/follow/' + (following ? 'unfollow' : 'follow'), {
            follow_uid: uid
        }).then(function (res) {
            if (!res || Number(res.code) !== 1) {
                throw new Error((res && res.msg) || '操作失败');
            }
            btn.setAttribute('data-following', following ? '0' : '1');
            btn.classList.toggle('is-secondary', !following);
            btn.textContent = following ? '关注' : '取消关注';
            ML.notify(res.msg || '操作成功');
            if (self.pageType === 'following' && following) {
                self.load(self.page);
            }
        }).catch(function (err) {
            if (err && err.message !== 'login required') {
                ML.notify(err.message || '操作失败');
            }
        }).finally(function () {
            btn.disabled = false;
        });
    };

    SocialPage.prototype.openConversation = function (trigger) {
        this.peerUid = parseInt(trigger.getAttribute('data-social-open-chat'), 10) || 0;
        if (!this.peerUid) { return; }
        var name = trigger.getAttribute('data-peer-name') || '私信会话';
        this.renderPeer({
            user_id: this.peerUid,
            user_name: name,
            user_portrait: trigger.getAttribute('data-peer-portrait') || '',
            mutual: 1
        });
        this.peerHeaderReady = true;
        this.switchView('conversation', { peerUid: this.peerUid, keepPeerHeader: true });
    };

    SocialPage.prototype.sendMessage = function () {
        var self = this;
        var input = qs('[data-social-message-input]', this.root);
        var submit = qs('[data-social-send]', this.root);
        if (!input || !submit) { return; }
        var content = input.value.replace(/^\s+|\s+$/g, '');
        if (!content || !this.peerUid || submit.disabled) { return; }

        submit.disabled = true;
        apiPost(this.apiBase + '/user_pm/send', { to_uid: this.peerUid, content: content })
            .then(function (res) {
                if (!res || Number(res.code) !== 1) {
                    throw new Error((res && res.msg) || '发送失败');
                }
                input.value = '';
                self.load(1);
            })
            .catch(function (err) {
                if (err && err.message !== 'login required') {
                    ML.notify(err.message || '发送失败');
                }
            })
            .finally(function () {
                submit.disabled = false;
            });
    };

    SocialPage.prototype.bind = function () {
        var self = this;

        var pagerRoot = qs('[data-social-pager]', this.root);
        if (pagerRoot && !pagerRoot.__socialPagerBound && ML.bindPager) {
            pagerRoot.__socialPagerBound = true;
            ML.bindPager(pagerRoot, '.mac-social-pager__list', function (pageNum) {
                if (pageNum === self.page) { return; }
                self.load(pageNum, { scrollTop: true });
            });
        }

        this.root.addEventListener('click', function (e) {
            var tab = e.target.closest('[data-social-view]');
            if (tab) {
                self.switchView(tab.getAttribute('data-social-view'));
                return;
            }
            var sub = e.target.closest('[data-social-subview]');
            if (sub) {
                self.switchView(sub.getAttribute('data-social-subview'));
                return;
            }
            var chat = e.target.closest('[data-social-open-chat]');
            if (chat) {
                self.openConversation(chat);
                return;
            }
            var follow = e.target.closest('[data-social-follow]');
            if (follow) {
                self.toggleFollow(follow);
                return;
            }
            if (e.target.closest('[data-social-chat-back]')) {
                self.switchView('messages');
                return;
            }
            if (e.target.closest('[data-social-send]')) {
                self.sendMessage();
            }
        });

        var input = qs('[data-social-message-input]', this.root);
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });
        }

        win.addEventListener('popstate', function () {
            var view = parseViewFromUrl();
            var peerUid = parseInt(new URLSearchParams(win.location.search).get('peer_uid'), 10) || 0;
            self.switchView(view, { peerUid: peerUid, fromPopstate: true });
        });
    };

    function boot() {
        function start(memberList) {
            ML = memberList;
            var root = qs('[data-mac-social]');
            if (root) { new SocialPage(root); }
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

/* --- user-profile-card.js bundled --- */
/**
 * 用户个人主页卡片（点击 [data-user-card] 打开）
 */
(function (win, doc) {
    'use strict';

    var state = { uid: 0, info: null, loading: false };

    function escText(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function apiBase() {
        var p = win.maccms && win.maccms.path != null ? String(win.maccms.path) : '';
        return p.replace(/\/+$/, '') + '/api.php';
    }

    function siteBase() {
        var p = win.maccms && win.maccms.path != null ? String(win.maccms.path) : '';
        return p.replace(/\/+$/, '');
    }

    function withCsrf(data) {
        var payload = data || {};
        if (win.maccms && win.maccms.csrfToken) {
            payload.__token__ = win.maccms.csrfToken;
        }
        return payload;
    }

    function encodeForm(data) {
        return Object.keys(data).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(data[key] == null ? '' : data[key]);
        }).join('&');
    }

    function apiGet(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (res) {
            return res.json();
        });
    }

    function apiPost(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: encodeForm(withCsrf(data || {}))
        }).then(function (res) {
            return res.json();
        });
    }

    function notify(msg) {
        if (!msg) { return; }
        if (win.MacMemberList && typeof win.MacMemberList.notify === 'function') {
            win.MacMemberList.notify(msg);
            return;
        }
        if (win.MAC && win.MAC.Pop && typeof win.MAC.Pop.Msg === 'function') {
            win.MAC.Pop.Msg(280, 44, msg, 1800);
            return;
        }
        if (win.MAC && typeof win.MAC.alert === 'function') {
            win.MAC.alert(msg);
            return;
        }
        win.alert(msg);
    }

    function ensureModal() {
        var root = doc.querySelector('[data-user-profile-card]');
        if (root) { return root; }

        root = doc.createElement('div');
        root.className = 'mac-user-card';
        root.setAttribute('data-user-profile-card', '');
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML =
            '<button type="button" class="mac-user-card__backdrop" data-user-card-close aria-label="关闭个人主页"></button>' +
            '<section class="mac-user-card__sheet" role="dialog" aria-modal="true" aria-labelledby="mac-user-card-title">' +
            '<div class="mac-user-card__handle" aria-hidden="true"></div>' +
            '<button type="button" class="mac-user-card__close" data-user-card-close aria-label="关闭">&times;</button>' +
            '<div class="mac-user-card__content" data-user-card-content></div>' +
            '</section>';
        doc.body.appendChild(root);
        return root;
    }

    function isDefaultPortrait(url) {
        if (!url) { return true; }
        // 站点默认 touxiang.png 为刺眼绿色剪影，改用主色字母占位
        return /(?:^|[\/?])touxiang\.png(?:\?|#|$)/i.test(url) ||
            /static(?:_new)?\/images\/touxiang\.png/i.test(url);
    }

    function avatarLetterHtml(name) {
        var letter = escText(String(name || '?').charAt(0) || '?');
        return '<span class="mac-user-card__avatar-letter" aria-hidden="true">' + letter + '</span>';
    }

    function avatarHtml(info) {
        var name = info.display_name || info.user_name || '用户';
        var portrait = String(info.user_portrait || '').trim();
        if (portrait && !/^javascript:/i.test(portrait) && !isDefaultPortrait(portrait)) {
            return '<img class="mac-user-card__avatar-img" src="' + escText(portrait) + '" alt="' + escText(name) +
                '" loading="lazy" decoding="async" data-fallback-letter="' + escText(name.charAt(0) || '友') + '">';
        }
        return avatarLetterHtml(name);
    }

    function bindAvatarFallback(container) {
        var img = container.querySelector('.mac-user-card__avatar-img');
        if (!img) { return; }
        img.addEventListener('error', function () {
            var wrap = img.parentNode;
            var letter = img.getAttribute('data-fallback-letter') || '?';
            if (wrap) {
                wrap.innerHTML = avatarLetterHtml(letter);
            }
        }, { once: true });
    }

    function regLabel(ts) {
        var n = parseInt(ts, 10);
        if (!n) { return '新朋友'; }
        var d = new Date(n * 1000);
        return d.getFullYear() + ' 年 ' + (d.getMonth() + 1) + ' 月加入';
    }

    function recentHtml(info) {
        var rows = info.recent_dynamics || [];
        if (!rows.length) {
            return '<div class="mac-user-card__empty">最近还没有公开动态</div>';
        }
        return '<div class="mac-user-card__recent">' + rows.map(function (row) {
            var typeText = row.dynamics_type_text || row.dynamics_type || '动态';
            var text = row.dyn_text || '分享了一条新动态';
            return '<div class="mac-user-card__recent-item"><span>' + escText(typeText) +
                '</span><p>' + escText(text) + '</p></div>';
        }).join('') + '</div>';
    }

    function render(info) {
        var root = ensureModal();
        var content = root.querySelector('[data-user-card-content]');
        var badge = info.is_self ? '这是你自己' : info.mutual ? '互相关注' : info.following ? '已关注' : '还未关注';
        var actions = '';

        if (info.is_self) {
            actions = '<a class="mac-user-card__action is-primary" href="' + siteBase() +
                '/index.php/user/dynamics.html?view=my_dynamics">查看我的动态</a>';
        } else if (info.is_login) {
            actions = '<button type="button" class="mac-user-card__action' + (info.following ? '' : ' is-primary') +
                '" data-user-card-follow>' + (info.following ? '取消关注' : '关注') + '</button>';
            if (info.mutual) {
                actions += '<a class="mac-user-card__action is-primary" href="' + siteBase() +
                    '/index.php/user/dynamics.html?view=conversation&amp;peer_uid=' + escText(info.user_id) +
                    '">发私信</a>';
            }
        } else {
            actions = '<a class="mac-user-card__action is-primary" href="' + siteBase() +
                '/index.php/user/login.html">登录后关注</a>';
        }

        content.innerHTML =
            '<div class="mac-user-card__hero">' +
            '<div class="mac-user-card__avatar">' + avatarHtml(info) + '</div>' +
            '<div class="mac-user-card__identity"><span>' + escText(badge) + '</span>' +
            '<h2 id="mac-user-card-title">' + escText(info.display_name || '用户') + '</h2>' +
            '<p>' + escText(regLabel(info.user_reg_time)) + '</p></div></div>' +
            '<div class="mac-user-card__stats"><div><strong>' + escText(info.following_count || 0) +
            '</strong><span>关注</span></div><div><strong>' + escText(info.fans_count || 0) +
            '</strong><span>粉丝</span></div><div><strong>' + escText(info.dynamics_count || 0) +
            '</strong><span>动态</span></div></div>' +
            '<div class="mac-user-card__section"><div class="mac-user-card__section-head"><strong>最近动态</strong>' +
            '<span>PUBLIC MOMENTS</span></div>' + recentHtml(info) + '</div>' +
            '<div class="mac-user-card__actions">' + actions + '</div>';
        bindAvatarFallback(content);
    }

    function open(uid, name) {
        uid = parseInt(uid, 10) || 0;
        if (!uid || state.loading) { return; }

        state.uid = uid;
        state.loading = true;
        var root = ensureModal();
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('mac-user-card-open');
        root.querySelector('[data-user-card-content]').innerHTML =
            '<div class="mac-user-card__loading"><span></span><strong>' +
            escText(name || '正在读取个人主页') + '</strong><small>正在加载公开资料</small></div>';

        apiGet(apiBase() + '/follow/profile?uid=' + encodeURIComponent(uid))
            .then(function (res) {
                if (!res || Number(res.code) !== 1) {
                    throw new Error((res && res.msg) || '读取用户资料失败');
                }
                state.info = res.info;
                render(res.info);
            })
            .catch(function (err) {
                root.querySelector('[data-user-card-content]').innerHTML =
                    '<div class="mac-user-card__loading"><strong>暂时无法查看</strong><small>' +
                    escText(err.message || '请稍后再试') + '</small></div>';
            })
            .finally(function () {
                state.loading = false;
            });
    }

    function close() {
        var root = doc.querySelector('[data-user-profile-card]');
        if (!root) { return; }
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        doc.body.classList.remove('mac-user-card-open');
    }

    function toggleFollow(btn) {
        if (!state.info || btn.disabled) { return; }

        btn.disabled = true;
        var following = Number(state.info.following) === 1;
        apiPost(apiBase() + '/follow/' + (following ? 'unfollow' : 'follow'), {
            follow_uid: state.info.user_id
        }).then(function (res) {
            if (!res || Number(res.code) !== 1) {
                if (res && Number(res.code) === 1010) {
                    win.location.href = siteBase() + '/index.php/user/login.html';
                }
                throw new Error((res && res.msg) || '操作失败');
            }
            notify(res.msg || '操作成功');
            return apiGet(apiBase() + '/follow/profile?uid=' + encodeURIComponent(state.info.user_id));
        }).then(function (res) {
            if (res && Number(res.code) === 1) {
                state.info = res.info;
                render(res.info);
            }
        }).catch(function (err) {
            notify(err.message || '操作失败');
        }).finally(function () {
            btn.disabled = false;
        });
    }

    doc.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-user-card]');
        if (trigger) {
            var uid = parseInt(trigger.getAttribute('data-user-card'), 10) || 0;
            if (uid) {
                e.preventDefault();
                e.stopPropagation();
                open(uid, trigger.getAttribute('data-user-name') || '');
            }
            return;
        }
        if (e.target.closest('[data-user-card-close]')) {
            close();
            return;
        }
        var followBtn = e.target.closest('[data-user-card-follow]');
        if (followBtn) {
            toggleFollow(followBtn);
        }
    });

    doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            close();
            return;
        }
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-user-card]')) {
            e.preventDefault();
            open(e.target.getAttribute('data-user-card'), e.target.getAttribute('data-user-name') || '');
        }
    });

    win.MacUserProfileCard = { open: open, close: close };
})(window, document);
