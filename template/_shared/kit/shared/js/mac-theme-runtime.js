/*!
 * maccms Theme Contract v1 —— 行為 runtime
 *
 * 目的：讓「結構與樣式完全自訂」的主題（含 AI 生成的主題）不必重寫一遍
 * 登入／收藏／評論／頂踩／懶載入／多語 這些行為。
 *
 * 設計原則：
 *   1. **只認 hook，不認 class 樣式**。所有選擇器都是契約裡宣告過的
 *      data-* 屬性或 mac_ 前綴行為 class，不依賴任何版面結構或視覺 class。
 *   2. **零第三方依賴**（不需要 jQuery）。ES5 語法，配合站點既有的 polyfill 策略。
 *   3. **不修改 DOM 結構**，只讀 hook、發請求、回寫狀態 class 與文案。
 *
 * 契約見 docs/theme-contract-v1.md。
 *
 * 配置（可選）：在引入本檔的 <script> 標籤上加 data-base / data-lang-path，
 * 或預先設定 window.maccms = { path: '/', path_tpl: '/template/xxx/html' }。
 */
(function (win, doc) {
    'use strict';

    if (win.MacTheme) { return; }

    /* ---------------------------------------------------------------- 基礎 */

    function each(list, fn) {
        if (!list) { return; }
        for (var i = 0; i < list.length; i++) { fn(list[i], i); }
    }

    function $all(sel, root) {
        return (root || doc).querySelectorAll(sel);
    }

    function closest(el, sel) {
        while (el && el.nodeType === 1) {
            if (el.matches ? el.matches(sel) : el.msMatchesSelector && el.msMatchesSelector(sel)) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    function cookie(name) {
        var m = doc.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    function setCookie(name, val, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 86400000);
            expires = '; expires=' + d.toUTCString();
        }
        doc.cookie = name + '=' + encodeURIComponent(val) + '; path=/' + expires;
    }

    /** 解析引入本檔的 script 標籤上的 data-* 配置 */
    function selfConfig() {
        var s = doc.currentScript;
        if (!s) {
            var all = doc.getElementsByTagName('script');
            s = all[all.length - 1];
        }
        var src = (s && s.getAttribute('src')) || '';
        return {
            /* 封面失效預設圖：與 runtime 同源目錄的 img/cover-fallback.svg
               （PB 主題 = page-builder-components/_shared/img/，源碼 = kit/shared/img/）。 */
            coverFallback: (s && s.getAttribute('data-cover-fallback')) ||
                (/\/js\/mac-theme-runtime\.js(?:[?#].*)?$/.test(src) ? src.replace(/\/js\/mac-theme-runtime\.js(?:[?#].*)?$/, '/img/cover-fallback.svg') : ''),
            base: (s && s.getAttribute('data-base')) || (win.maccms && win.maccms.path) || '/',
            langPath: (s && s.getAttribute('data-lang-path')) ||
                ((win.maccms && win.maccms.path_tpl) ? String(win.maccms.path_tpl).replace(/\/+$/, '') + '/../asset/language/' : '')
        };
    }

    var CFG = selfConfig();

    function url(path) {
        return String(CFG.base).replace(/\/+$/, '') + '/index.php/' + String(path).replace(/^\/+/, '');
    }

    function apiUrl(path) {
        var base = String(CFG.base).replace(/\/+$/, '');
        return (base ? base + '/' : '/') + 'api.php/' + String(path).replace(/^\/+/, '');
    }

    /** 登入頁 URL 模式（XHR 被 302 到登入頁時 responseURL 會命中） */
    var LOGIN_URL_RE = /\/user\/login(\.|\/|$|\?)/;

    /** 認證類 POST 端點本身就在 login 頁，responseURL 命中 LOGIN_URL_RE 屬正常，不可當重導向 */
    function isAuthPostPath(path) {
        return /^user\/(login|reg|findpass_msg|findpass_reset)\b/i.test(String(path).replace(/^\/+/, ''));
    }

    function userId() {
        var m = win.maccms || {};
        var id = m.userId != null ? m.userId : m.user_id;
        return parseInt(id, 10) || 0;
    }

    function isLoggedIn() {
        return userId() > 0;
    }

    /**
     * 未登入時廣播 login:required 並提示；已登入返回 true。
     * @param {object} detail 可帶 source / el 等上下文
     */
    function promptLogin(detail) {
        if (win.MacTheme && typeof win.MacTheme.emit === 'function') {
            win.MacTheme.emit('login:required', detail || {});
        }
        notify(tr('login_req'));
    }

    /**
     * 需登入才執行的操作。已登入則執行 fn 並返回 true；否則彈登入提示并返回 false。
     * @param {function} fn
     * @param {object}   detail
     */
    function requireLogin(fn, detail) {
        if (isLoggedIn()) {
            if (fn) { fn(); }
            return true;
        }
        promptLogin(detail);
        return false;
    }

    function isLoginRedirectUrl(urlStr) {
        return LOGIN_URL_RE.test(String(urlStr || ''));
    }

    /** 登录页 ?url= 回跳地址（仅允许同站相对或绝对路径） */
    function getLoginReturnUrl() {
        var m = String(win.location.search || '').match(/[?&]url=([^&]+)/);
        if (!m || !m[1]) { return ''; }
        try {
            var raw = decodeURIComponent(m[1]);
            if (!raw) { return ''; }
            var a = doc.createElement('a');
            a.href = raw;
            if (a.protocol !== 'http:' && a.protocol !== 'https:') { return ''; }
            if (a.hostname && a.hostname !== win.location.hostname) { return ''; }
            if (isLoginRedirectUrl(a.href)) { return ''; }
            return a.href;
        } catch (e) {
            return '';
        }
    }

    function gotoAfterLogin() {
        var back = getLoginReturnUrl();
        if (back) {
            win.location.href = back;
            return;
        }
        win.location.reload();
    }

    function isLoginRequiredResponse(res) {
        if (!res) { return false; }
        var code = Number(res.code);
        return code === 1003;
    }

    /**
     * POST 表單資料。自動帶上 CSRF（雙重提交：cookie csrf_token → X-CSRF-Token 標頭）。
     * @param {string}   path 形如 'user/ajax_ulog'
     * @param {object}   data
     * @param {function} cb   cb(err, json)
     */
    function post(path, data, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url(path), true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var token = cookie('csrf_token');
        if (token) { xhr.setRequestHeader('X-CSRF-Token', token); }

        var body = [];
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
            }
        }
        if (token) { body.push('__token__=' + encodeURIComponent(token)); }

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            var finalUrl = xhr.responseURL || '';
            var authPost = isAuthPostPath(path);
            // 302 到登入頁後瀏覽器會跟隨重導向；即使 status=200 且能 parse JSON，
            // responseURL 仍會是 login 頁（內容可能是登入表單校驗錯誤，而非業務 JSON）。
            // 但 POST user/login 等認證端點時 responseURL 本來就是 login 頁，須排除。
            if (xhr.status >= 200 && xhr.status < 300 && res && (authPost || !isLoginRedirectUrl(finalUrl))) {
                cb(null, res);
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300 && res && isLoginRedirectUrl(finalUrl)) {
                var loginErr = new Error('login required');
                loginErr.status = xhr.status;
                loginErr.finalUrl = finalUrl;
                loginErr.loginRequired = true;
                cb(loginErr, res);
                return;
            }
            // 需要登入的動作，index/controller/User.php 的建構子會 302 到 user/login。
            // XHR 會靜默跟隨重導向，於是我們拿到的是「登入頁 HTML」而非 JSON——
            // 若只看 JSON.parse 失敗就報網路錯誤，使用者會看到「網路錯誤」而不是
            // 「請先登入」。用 responseURL 認出這種情況（重導後才會與請求 URL 不同）。
            var err = new Error('request failed: ' + xhr.status);
            err.status = xhr.status;
            err.finalUrl = finalUrl;
            err.loginRequired = isLoginRedirectUrl(finalUrl);
            cb(err, res);
        };
        xhr.send(body.join('&'));
    }

    /**
     * runtime 自己的少量文案。共用字典 kit/shared/language/ 是
     * template/default/asset/language/ 的逐字副本，往裡加 key 會和前端團隊維護的
     * 正本產生漂移，所以這幾條隨 runtime 走，並跟著 i18n.lang 一起切換。
     * （主題要自己的文案就傳 data-lang-path 指到自己的目錄，不要改這裡。）
     */
    var BUILTIN = {
        net_err:   ['网络错误，请稍后重试', 'Network error, please try again'],
        login_req: ['请先登录', 'Please log in first']
    };

    function tr(key) {
        var pair = BUILTIN[key];
        return pair ? pair[i18n.lang ? 1 : 0] : '';
    }

    /**
     * 把 post() 的失敗轉成一句可讀訊息：需要登入就說需要登入，並廣播
     * login:required 讓主題有機會彈自己的登入框，而不是只丟一句錯誤。
     */
    function reportError(err, res) {
        if (err && err.loginRequired || isLoginRequiredResponse(res)) {
            MacTheme.emit('login:required', { error: err, res: res });
            notify((res && res.msg) || tr('login_req'));
            return;
        }
        notify((res && res.msg) || tr('net_err'));
    }

    /**
     * 提示。預設用非阻塞的浮層，而不是 window.alert——alert 會凍結整個頁面、
     * 在行動端體驗尤其差，也讓自動化測試無法繼續。主題可覆寫 MacTheme.notify
     * 接管成自己的樣式；也可只覆寫 CSS（.mac-toast）。
     */
    function notify(msg) {
        if (!msg) { return; }
        var box = doc.getElementById('mac-toast');
        if (!box) {
            box = doc.createElement('div');
            box.id = 'mac-toast';
            box.className = 'mac-toast';
            box.setAttribute('role', 'status');
            box.setAttribute('aria-live', 'polite');
            // 只給定位與層級，配色字體留給主題；沒有主題樣式時也仍然可讀。
            box.style.cssText = 'position:fixed;left:50%;top:12%;transform:translateX(-50%);' +
                'z-index:99999;padding:10px 18px;border-radius:6px;background:rgba(0,0,0,.82);' +
                'color:#fff;font-size:14px;max-width:80%;text-align:center;pointer-events:none;';
            (doc.body || doc.documentElement).appendChild(box);
        }
        box.textContent = String(msg);
        box.style.display = '';
        if (box._macTimer) { win.clearTimeout(box._macTimer); }
        box._macTimer = win.setTimeout(function () { box.style.display = 'none'; }, 2600);
    }

    /* ------------------------------------------------------------ 多語 i18n */

    var i18n = {
        map: {},
        lang: 0, // 0=zh 1=en

        /** 解析 .properties（key=value，# 開頭為註解） */
        parse: function (text) {
            var map = {};
            var lines = String(text).split(/\r?\n/);
            for (var i = 0; i < lines.length; i++) {
                var line = lines[i];
                if (!line || line.charAt(0) === '#' || line.charAt(0) === '!') { continue; }
                var p = line.indexOf('=');
                if (p < 1) { continue; }
                map[line.substring(0, p).trim()] = line.substring(p + 1).trim();
            }
            return map;
        },

        /** 把字典套到 DOM。四種 hook 與舊主題保持一致。
         *  data-lang 另支援 data-lang-arg：文案中的 %s 以該值替換（如「共%s章」）。 */
        apply: function (root) {
            root = root || doc;
            var map = i18n.map;
            var pairs = [
                ['data-lang', function (el, v) {
                    var arg = el.getAttribute('data-lang-arg');
                    el.textContent = arg === null ? v : v.replace('%s', arg);
                }],
                ['data-title', function (el, v) { el.setAttribute('title', v); }],
                ['data-lang-title', function (el, v) { el.setAttribute('title', v); }],
                ['data-lang-aria-label', function (el, v) { el.setAttribute('aria-label', v); }],
                ['data-placeholder', function (el, v) { el.setAttribute('placeholder', v); }],
                ['data-lang-placeholder', function (el, v) { el.setAttribute('placeholder', v); }],
                ['data-value', function (el, v) { el.setAttribute('value', v); }]
            ];
            each(pairs, function (pair) {
                each($all('[' + pair[0] + ']', root), function (el) {
                    var key = el.getAttribute(pair[0]);
                    if (key && Object.prototype.hasOwnProperty.call(map, key)) {
                        pair[1](el, map[key]);
                    }
                });
            });
        },

        load: function (lang, done) {
            if (!CFG.langPath) { if (done) { done(); } return; }
            var file = CFG.langPath.replace(/\/+$/, '') + '/strings_' + (lang ? 'en' : 'zh') + '.properties';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', file, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) { return; }
                if (xhr.status >= 200 && xhr.status < 300) {
                    i18n.map = i18n.parse(xhr.responseText);
                    i18n.apply(doc);
                }
                if (done) { done(); }
            };
            xhr.send();
        },

        /** 與舊主題共用 localStorage 'lang' 與 cookie langType，切換行為一致 */
        set: function (lang, done, isUserAction) {
            lang = lang ? 1 : 0;
            i18n.lang = lang;
            try { win.localStorage.setItem('lang', String(lang)); } catch (e) {}
            setCookie('langType', String(lang));
            doc.body && doc.body.classList.remove(lang ? 'zh' : 'en');
            doc.body && doc.body.classList.add(lang ? 'en' : 'zh');
            /*
             * isUserAction=true（使用者真的點了頂欄切換器，而非 init() 讀舊值或
             * storage 事件跟另一個分頁同步）時，順帶牽動後台「內容多語言」
             * （application/common.php mac_get_content_lang() / mac_content_lang_overlay()，
             * 分類名、影片標題這類資料庫內容走這條，不吃本檔的 data-lang 字典）。
             * 那套機制是純伺服器端渲染，前端改不動已輸出的 HTML，只能靠重整頁面
             * 讓 PHP 重新按 cookie mac_content_lang 出圖；沒動這顆 cookie 之前，
             * 點頂欄英文只有內建文案（data-lang）變英文、分類/片名等資料庫內容
             * 仍是原語言——這正是使用者回報「切換英文全局不生效」的成因。
             * 另外：後台 系統設定 > 內容多語言 要勾選啟用該語言、且該筆內容要有
             * 譯文，mac_content_lang_overlay() 才會真的換字；沒有譯文一律回退原文
             * （見該函式註解），這是既定 fallback，不是這裡要修的 bug。
             */
            if (isUserAction) {
                var contentLang = lang ? 'en-us' : 'zh-cn';
                var prevContentLang = cookie('mac_content_lang') || 'zh-cn';
                if (prevContentLang !== contentLang) {
                    setCookie('mac_content_lang', contentLang, 30);
                    win.location.reload();
                    return;
                }
            }
            i18n.load(lang, done);
        },

        init: function () {
            var saved = null;
            try { saved = win.localStorage.getItem('lang'); } catch (e) {}
            if (saved === null) {
                var nav = (win.navigator.userLanguage || win.navigator.language || 'zh').substring(0, 2).toLowerCase();
                saved = nav === 'zh' ? '0' : '1';
            }
            i18n.set(saved === '1' ? 1 : 0);
        }
    };

    // 語言切換 hook：任何帶 data-mac-lang 的元素（值為 zh / en）
    doc.addEventListener('click', function (ev) {
        var el = closest(ev.target, '[data-mac-lang]');
        if (!el) { return; }
        ev.preventDefault();
        i18n.set(String(el.getAttribute('data-mac-lang')).toLowerCase() === 'en' ? 1 : 0, null, true);
    });

    /**
     * 同源的 iframe（如 vod_pwd_play 播放页密码门）各自載入一份本檔，各自的
     * i18n.init() 只在自己載入當下讀一次 localStorage——之後 top frame 切換語言，
     * 這份既有的 iframe 副本並不會重新執行，畫面就卡在舊語言，得整頁重載才會更新。
     * 瀏覽器原生行為：其他同源 document 呼叫 localStorage.setItem 時，*不會*在
     * 該 document 自己身上觸發 'storage' 事件，只會在其他同源 window（含 iframe）
     * 觸發——所以這裡不需要輪詢，接住這個原生事件就能讓 iframe 跟 top frame 即時同步，
     * 不必等重載。
     */
    win.addEventListener('storage', function (ev) {
        if (ev.key !== 'lang' || ev.newValue == null) { return; }
        var lang = ev.newValue === '1' ? 1 : 0;
        if (lang === i18n.lang) { return; }
        i18n.lang = lang;
        doc.body && doc.body.classList.remove(lang ? 'zh' : 'en');
        doc.body && doc.body.classList.add(lang ? 'en' : 'zh');
        i18n.load(lang);
    });

    /* -------------------------------------------------------------- 懶載入 */

    /* ------------------------------------------------------- 封面失效回退 */

    /**
     * 封面圖載入失敗（源站失效 / 空地址）時統一換成預設縮略圖，與排行榜一致。
     * 適用：懶載入圖（data-original）與標了 data-cover 的直出封面圖。
     * 不處理：自帶 onerror 的圖（各元件自己管回退，如書封 is-empty、rank_card）、
     * 頭像（[class*=avatar]）。在 document 上以捕獲階段監聽，
     * 因 img 的 error 事件不冒泡；runtime 載入前就已失敗的圖由 cover.scan 補掃。
     *
     * 小說封面：圖本身或祖先標了 data-cover-kind="book" 時改用書形占位
     * （淺底 + 主題色書本，與 art_book_card / novel_home_grid 的 is-empty 占位同款），
     * 讓列表、收藏、播放記錄、輪播、詳情等所有小說封面的無圖狀態一致。
     */
    var cover = {
        CLS: 'mac-cover-fallback',
        BOOK_CLS: 'mac-cover-fallback--book',
        bookCache: {},

        isBook: function (el) {
            var k = closest(el, '[data-cover-kind]');
            return !!k && k.getAttribute('data-cover-kind') === 'book';
        },

        /** 主題色：用探針元素解析 var(--c-accent) 成實際 rgb，再寫進 SVG（data URI 內讀不到 CSS 變數） */
        accent: function (el) {
            var host = (el && el.parentNode && el.parentNode.nodeType === 1) ? el.parentNode : doc.body;
            var c = '';
            if (host && win.getComputedStyle) {
                var probe = doc.createElement('span');
                probe.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;color:var(--c-accent,#6366f1)';
                host.appendChild(probe);
                c = win.getComputedStyle(probe).color || '';
                host.removeChild(probe);
            }
            return c || '#6366f1';
        },

        /** 與 _shared/book_cover_empty.twig 同一本書；viewBox 留白讓書佔封面寬約 42% */
        bookSrc: function (el) {
            var c = cover.accent(el);
            if (!cover.bookCache[c]) {
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="152" height="152" viewBox="-44 -44 152 152" fill="none">' +
                    '<g stroke="' + c + '" stroke-width="2.1" stroke-linejoin="round" stroke-linecap="round">' +
                    '<path d="M18 10h30c1.7 0 3 1.3 3 3v38c0 1.7-1.3 3-3 3H18c-1.7 0-3-1.3-3-3V13c0-1.7 1.3-3 3-3Z"/>' +
                    '<path d="M23 10v44"/>' +
                    '<path d="M28 18h16M28 24h12M28 30h14" opacity="0.85"/>' +
                    '</g></svg>';
                cover.bookCache[c] = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
            }
            return cover.bookCache[c];
        },

        /** 回退圖地址：小說封面用書形，其餘用預設縮略圖 */
        srcFor: function (el) {
            return cover.isBook(el) ? cover.bookSrc(el) : CFG.coverFallback;
        },

        isCandidate: function (img) {
            if (!img || img.tagName !== 'IMG' || !CFG.coverFallback) { return false; }
            if (!img.hasAttribute('data-original') && !img.hasAttribute('data-cover')) { return false; }
            if (img.hasAttribute('onerror') || img.hasAttribute('data-no-cover-fallback')) { return false; }
            return !closest(img, '[class*="avatar"]');
        },

        apply: function (img) {
            if (!cover.isCandidate(img) || img.classList.contains(cover.CLS)) { return; }
            if (closest(img, '[hidden]')) { return; }
            cover.style();
            var fb = cover.srcFor(img);
            img.classList.add(cover.CLS);
            if (cover.isBook(img)) { img.classList.add(cover.BOOK_CLS); }
            img.removeAttribute('srcset');
            img._macCoverSrc = fb;
            img.src = fb;
        },

        /** 樣式由 runtime 注入：不必在每個主題 / 版型額外引 CSS，主題重置後也照樣生效 */
        style: function () {
            if (cover.styled || !doc.head) { return; }
            cover.styled = true;
            var st = doc.createElement('style');
            st.setAttribute('data-mac-cover-fallback', '');
            st.textContent = 'img.' + cover.CLS + '{object-fit:contain!important;padding:8%;box-sizing:border-box;' +
                'background:var(--c-surface-2,#f4f5f7);opacity:.85}' +
                '[data-cover-bg].' + cover.CLS + '{background-size:70% auto!important;background-repeat:no-repeat!important;' +
                'background-position:center!important;background-color:var(--c-surface-2,#f4f5f7)!important}' +
                'img.' + cover.BOOK_CLS + '{padding:0!important;opacity:1!important;object-position:center!important;background:var(--c-surface-2,#eceff1)!important}' +
                '[data-cover-bg].' + cover.BOOK_CLS + '{background-size:contain!important;background-color:var(--c-surface-2,#eceff1)!important}';
            doc.head.appendChild(st);
        },

        /**
         * 背景圖封面（<span data-cover-bg="封面地址" style="background-image:…">）：
         * 背景圖失敗不觸發任何事件，故以 Image 探測，空地址或載入失敗就換成預設縮略圖。
         */
        applyBg: function (el) {
            if (!CFG.coverFallback || el.classList.contains(cover.CLS)) { return; }
            cover.style();
            el.classList.add(cover.CLS);
            if (cover.isBook(el)) { el.classList.add(cover.BOOK_CLS); }
            el.style.backgroundImage = 'url("' + cover.srcFor(el).replace(/"/g, '\\"') + '")';
        },

        probeBg: function (el) {
            if (el.hasAttribute('data-cover-checked')) { return; }
            el.setAttribute('data-cover-checked', '');
            var src = el.getAttribute('data-cover-bg') || '';
            if (!src) {
                cover.applyBg(el);
                return;
            }
            var probe = new win.Image();
            probe.onerror = function () { cover.applyBg(el); };
            probe.onload = function () { if (!probe.naturalWidth) { cover.applyBg(el); } };
            probe.src = src;
        },

        /** 封面地址為空，或 runtime 載入前就已失敗（complete 但無尺寸）的封面 */
        scan: function (root) {
            each($all('[data-cover-bg]', root || doc), cover.probeBg);
            each($all('img[data-cover], img[data-original]', root || doc), function (img) {
                if (img.classList.contains(cover.CLS)) { return; }
                if (img.getAttribute('data-original') === '') {
                    cover.apply(img);
                    return;
                }
                if (img.complete && !img.naturalWidth) {
                    var src = img.getAttribute('src');
                    if (src === null || src.indexOf('data:') !== 0) { cover.apply(img); }
                    return;
                }
                if (img.complete) { cover.orient(img); }
            });
        },

        /**
         * 封面實際方向：各資源站同一分類裡橫版、豎版封面混雜（短劇 270x405、抖音類 240x320 等），
         * 分類比例（type_extend.cover）管不到單條內容，故按圖片本身尺寸逐條判斷。
         * 宿主元素標 data-cover-auto：
         *   "fit"   封面框固定（列表卡片）：方向與框差太多時寫 data-cover-fit="contain"，
         *           元件 CSS 讓圖完整顯示並以框內 [data-cover-backdrop] 墊同圖模糊底；否則 "cover"。
         *   "shape" 封面框可變（詳情海報、收藏、播放記錄）：元件 CSS 依 data-cover-natural 換版式。
         * 兩種都寫 data-cover-natural="v|h|s" 與 --mac-cover-ar（寬/高，限 9:16～16:9）。
         */
        /** 寬/高 → v 豎版 | h 橫版 | s 近方形（±15%） */
        orientOf: function (r) {
            return r < 0.87 ? 'v' : (r > 1.15 ? 'h' : 's');
        },

        orient: function (img) {
            if (!img || img.tagName !== 'IMG') { return; }
            if (!img.hasAttribute('data-original') && !img.hasAttribute('data-cover')) { return; }
            var host = closest(img, '[data-cover-auto]');
            if (!host) { return; }
            if (img.classList.contains(cover.CLS)) {
                host.removeAttribute('data-cover-natural');
                host.removeAttribute('data-cover-fit');
                return;
            }
            var src = img.currentSrc || img.getAttribute('src') || '';
            var w = img.naturalWidth;
            var h = img.naturalHeight;
            if (!w || !h || !src || src.indexOf('data:') === 0) { return; }
            var r = w / h;
            var o = cover.orientOf(r);
            host.setAttribute('data-cover-natural', o);
            host.style.setProperty('--mac-cover-ar', String(Math.round(Math.min(Math.max(r, 9 / 16), 16 / 9) * 1e4) / 1e4));
            if (host.getAttribute('data-cover-auto') !== 'fit') { return; }
            var frame = img.parentNode;
            var fw = frame && frame.clientWidth;
            var fh = frame && frame.clientHeight;
            if (!fw || !fh) { return; }
            /* 只在方向相反時（豎圖進橫框 / 橫圖進豎框）改完整顯示；同方向（寬橫幅、16:9 進 69:40）與方形照常裁切 */
            var fo = cover.orientOf(fw / fh);
            var fit = (o !== 's' && fo !== 's' && o !== fo) ? 'contain' : 'cover';
            host.setAttribute('data-cover-fit', fit);
            if (fit === 'contain') {
                var bd = frame.querySelector('[data-cover-backdrop]');
                if (bd && !bd.style.backgroundImage) {
                    bd.style.backgroundImage = 'url("' + src.replace(/"/g, '\\"') + '")';
                }
            }
        },

        bind: function () {
            doc.addEventListener('error', function (e) {
                cover.apply(e.target);
            }, true);
            /* 輪播等腳本會把同一張 img 換成別的封面：換上能載入的圖就撤掉回退樣式 */
            doc.addEventListener('load', function (e) {
                var img = e.target;
                if (img && img.tagName === 'IMG' && img.classList.contains(cover.CLS) &&
                    img.getAttribute('src') !== (img._macCoverSrc || CFG.coverFallback)) {
                    img.classList.remove(cover.CLS, cover.BOOK_CLS);
                }
                cover.orient(img);
            }, true);
        }
    };

    cover.bind();

    var lazy = {
        io: null,

        show: function (el) {
            var src = el.getAttribute('data-original');
            if (!src) {
                /* 封面地址為空：直接給預設縮略圖，免得留一格空白 */
                if (el.tagName === 'IMG') { cover.apply(el); }
                return;
            }
            if (el.tagName === 'IMG') {
                el.src = src;
            } else {
                el.style.backgroundImage = 'url("' + src.replace(/"/g, '\\"') + '")';
            }
            el.classList.add('lazyloaded');
        },

        /**
         * 只認 data-original，不認任何樣式 class ——
         * 舊主題的實作寫死了 .vodlist_thumb / .topiclist_thumb 等一串預設主題的 class，
         * 換結構就失效，這正是本 runtime 要解決的問題。
         */
        scan: function (root) {
            var els = $all('[data-original]:not(.lazyloaded)', root || doc);
            if (!win.IntersectionObserver) {
                each(els, lazy.show);
                return;
            }
            if (!lazy.io) {
                lazy.io = new win.IntersectionObserver(function (entries) {
                    each(entries, function (e) {
                        if (e.isIntersecting) {
                            lazy.io.unobserve(e.target);
                            lazy.show(e.target);
                        }
                    });
                }, { rootMargin: '220px 0px', threshold: 0 });
            }
            each(els, function (el) {
                if (el._macLazyObserved) { return; }
                el._macLazyObserved = true;
                lazy.io.observe(el);
            });
        }
    };

    /* ---------------------------------------------------------------- 收藏 */

    /**
     * 同步收藏/點讚等 mac_ulog 按鈕文案（detail/play 等手寫按鈕與 fav_button 宏皆適用）。
     * 「開」「關」兩個 data-lang key 預設是收藏的 string_isfav/string_fav，
     * 其他 mac_ulog 變體（如點讚）可用 data-lang-on / data-lang-off 覆寫，
     * 不必為每個變體另開一個 sync 函式。
     */
    function syncUlogLabel(el, isFav) {
        var onKey = el.getAttribute('data-lang-on') || 'string_isfav';
        var offKey = el.getAttribute('data-lang-off') || 'string_fav';
        var label = el.querySelector('[data-lang="' + onKey + '"], [data-lang="' + offKey + '"]');
        if (label) {
            label.setAttribute('data-lang', isFav ? onKey : offKey);
            i18n.apply(el);
            return;
        }
        el.textContent = isFav ? '已收藏' : '收藏';
    }

    /** 若按鈕內帶 .mac_ulog_count（如點讚數），本地 ±1，避免為了一個數字再打一次請求。 */
    function bumpUlogCount(el, delta) {
        var num = el.querySelector('.mac_ulog_count');
        if (!num) { return; }
        var cur = parseInt(num.textContent, 10);
        if (isNaN(cur)) { cur = 0; }
        num.textContent = String(Math.max(0, cur + delta));
    }

    function ulogTarget(el) {
        return {
            mid: el.getAttribute('data-mid') || '1',
            id: el.getAttribute('data-id') || '',
            type: el.getAttribute('data-type') || '2',
            sid: el.getAttribute('data-sid') || '0',
            nid: el.getAttribute('data-nid') || '0'
        };
    }

    /**
     * 取得這條收藏在 mac_ulog 表裡的主鍵。
     * 新增成功時 Ulog::saveData() 只回 {code,msg}、不帶 id，所以拿不到就得回查：
     * user/ajax_ulog 不帶 ac 走的是列表分支，會依 user_id + mid/rid/type 篩出該記錄。
     * 早期版本在這裡用 `res.ulog_id || res.id || '1'` 回退成寫死的 '1'，
     * 等於在後續取消收藏時對著別條記錄下手，必須拿真值。
     */
    function resolveUlogId(el, cb) {
        var known = el.getAttribute('data-uid') || '';
        if (known) { cb(known); return; }
        var q = ulogTarget(el);
        post('user/ajax_ulog', { mid: q.mid, id: q.id, type: q.type, page: 1, limit: 1 },
            function (err, res) {
                if (err || !res || res.code !== 1) { cb(''); return; }
                var list = res.list || [];
                cb(list.length ? String(list[0].ulog_id || '') : '');
            });
    }

    /**
     * hook: .mac_ulog[data-type][data-mid][data-id][data-uid]
     * data-uid 非空 = 已收藏（值是 ulog 記錄 id），點擊即取消。
     * 狀態變化只加減 is_fav class 與更新 data-uid，樣式完全由主題自己決定。
     *
     * 注意兩個動作走的是不同端點：
     *   收藏   user/ajax_ulog  ac=set
     *   取消   user/ulog_del   ids=<ulog_id>&type=<ulog_type>
     * ajax_ulog 沒有 del 分支——早期版本送 ac=del 會掉進列表查詢，
     * 回一包 code:1 的列表被當成成功，UI 顯示已取消而資料庫紋風不動。
     */
    function bindUlog() {
        doc.addEventListener('click', function (ev) {
            var el = closest(ev.target, '.mac_ulog');
            if (!el || el._macBusy) { return; }
            ev.preventDefault();

            if (!requireLogin(null, { source: 'ulog', el: el })) { return; }

            // 以可見狀態（is_fav）判斷方向，而不是 data-uid——新增成功時後端不回
            // ulog_id，data-uid 會暫時是空的，若以它判斷會把「再點一次」誤判成再收藏一次。
            var isFav = el.classList.contains('is_fav') || !!(el.getAttribute('data-uid') || '');
            el._macBusy = true;

            if (isFav) {
                removeFav(el);
            } else {
                addFav(el);
            }
        });
    }

    function addFav(el) {
        var payload = ulogTarget(el);
        payload.ac = 'set';
        post('user/ajax_ulog', payload, function (err, res) {
            if (err || !res) { el._macBusy = false; reportError(err, res); return; }
            if (res.code !== 1) { el._macBusy = false; notify(res.msg); return; }

            el.classList.add('is_fav');
            syncUlogLabel(el, true);
            bumpUlogCount(el, 1);
            // 後端新增成功時只回 {code,msg}，沒有 ulog_id；只有「本來就已收藏」那條
            // 路徑才會帶 info.ulog_id。拿得到就順手存起來當快取，拿不到也不強求——
            // 真正需要 id 的是取消收藏，交給 removeFav 當場回查即可，
            // 免得每次收藏都白白多打一次請求。
            if (res.info && res.info.ulog_id) {
                el.setAttribute('data-uid', String(res.info.ulog_id));
            }
            el._macBusy = false;
            MacTheme.emit('ulog:changed', { el: el, added: true, res: res });
        });
    }

        function removeFav(el) {
        resolveUlogId(el, function (id) {
            if (!id) {
                el.setAttribute('data-uid', '');
                el.classList.remove('is_fav');
                syncUlogLabel(el, false);
                el._macBusy = false;
                return;
            }
            post('user/ulog_del', { ids: id, type: el.getAttribute('data-type') || '2' },
                function (err, res) {
                    el._macBusy = false;
                    if (err || !res) { reportError(err, res); return; }
                    if (res.code !== 1) { notify(res.msg); return; }
                    el.setAttribute('data-uid', '');
                    el.classList.remove('is_fav');
                    syncUlogLabel(el, false);
                    bumpUlogCount(el, -1);
                    MacTheme.emit('ulog:changed', { el: el, added: false, res: res });
                });
        });
    }

    /* ------------------------------------------------------------- 頂 / 踩 */

    /** hook: .digg_link[data-id][data-mid][data-type]，計數寫回 .digg_num */
    function bindDigg() {
        doc.addEventListener('click', function (ev) {
            var el = closest(ev.target, '.digg_link');
            if (!el || el._macBusy) { return; }
            ev.preventDefault();
            el._macBusy = true;
            post('ajax/digg', {
                id: el.getAttribute('data-id') || '',
                mid: el.getAttribute('data-mid') || '1',
                type: el.getAttribute('data-type') || 'up'
            }, function (err, res) {
                el._macBusy = false;
                if (err || !res) { reportError(err, res); return; }
                if (res.code !== 1) { notify(res.msg); return; }
                // index/Ajax::digg() 回的是 {code, msg, data:{up, down}}，沒有 num 這個鍵。
                // 之前讀 res.num 永遠是 undefined —— 頂／踩其實已寫進庫，畫面卻紋風不動，
                // 使用者只會覺得按鈕壞了。取哪一個由按鈕自己的 data-type 決定，
                // 與 default 的 public-home-stack.js 同語義。
                var num = el.querySelector('.digg_num');
                var val = res.data ? res.data[el.getAttribute('data-type') === 'down' ? 'down' : 'up'] : null;
                if (num && val != null) { num.textContent = val; }
                MacTheme.emit('digg:changed', { el: el, res: res });
            });
        });
    }

    /* ---------------------------------------------------------- 需登入 gate */

    /**
     * hook: [data-mac-auth] — 未登入點擊時攔截並彈登入框，不執行預設行為。
     * 已登入則不攔截，由元素自身邏輯（href / 其他 listener）繼續。
     * 可選 data-mac-auth-modal="reg|findpass" 指定彈窗類型。
     */
    function bindAuthGate() {
        doc.addEventListener('click', function (ev) {
            var el = closest(ev.target, '[data-mac-auth]');
            if (!el || isLoggedIn()) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            var modal = el.getAttribute('data-mac-auth-modal') || 'login';
            MacTheme.emit('login:required', { source: 'auth-gate', el: el, modal: modal });
            notify(tr('login_req'));
        }, true);
    }

    /* ---------------------------------------------------------------- 評論 */

    /**
     * hook: form[data-mac-comment-form]，欄位用原生 name（mid / rid / comment_content …）
     * 端點是 comment/saveData —— Comment 控制器沒有 save() 這個動作，
     * 早期版本送 comment/save 一律 404。
     */
    function refreshCommentCaptcha(form) {
        if (win.MacCommentBlock && typeof win.MacCommentBlock.refreshCaptcha === 'function') {
            win.MacCommentBlock.refreshCaptcha(form);
            return;
        }
        if (!form) { return; }
        var input = form.querySelector('input[name="verify"]');
        if (input) { input.value = ''; }
        var img = form.querySelector('.cmt-form__captcha-img, [class*="captcha"] img');
        if (img && img.src) {
            img.src = img.src.split('?')[0] + '?v=' + Date.now();
        }
    }

    function bindComment() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-comment-form]');
            if (!form) { return; }
            ev.preventDefault();

            if (!requireLogin(null, { source: 'comment', form: form })) { return; }

            var btn = form.querySelector('[type="submit"]');
            var data = {};
            each(form.querySelectorAll('input[name], textarea[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            if (btn) {
                btn.disabled = true;
                btn.setAttribute('data-loading', '1');
            }

            post('comment/saveData', data, function (err, res) {
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                }
                if (err || !res) {
                    refreshCommentCaptcha(form);
                    reportError(err, res);
                    return;
                }
                notify(res.msg);
                if (res.code === 1) {
                    var box = form.querySelector('[name="comment_content"], textarea');
                    if (box) { box.value = ''; }
                    form.classList.remove('is-active');
                    MacTheme.emit('comment:saved', { form: form, res: res });
                } else {
                    refreshCommentCaptcha(form);
                }
            });
        });
    }

    /* ---------------------------------------------------------------- 留言 */

    /**
     * hook: form[data-mac-gbook-form]，欄位 gbook_content / verify（gbook_name 由後端依登入態自動填入）
     * 端點 gbook/saveData（POST 回 JSON）。原生 submit 會跳到空白 JSON 頁。
     */
    function bindGbook() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-gbook-form]');
            if (!form) { return; }
            ev.preventDefault();

            if (form.getAttribute('data-gbook-login') === '1' && !requireLogin(null, { source: 'gbook', form: form })) {
                return;
            }

            var content = form.querySelector('[name="gbook_content"]');
            if (!content || !String(content.value || '').trim()) {
                notify(tr('validate_require_content') || '请输入留言内容');
                if (content) { content.focus(); }
                return;
            }

            var btn = form.querySelector('[type="submit"]');
            var data = {};
            each(form.querySelectorAll('input[name], textarea[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            if (btn) {
                btn.disabled = true;
                btn.setAttribute('data-loading', '1');
            }

            post('gbook/saveData', data, function (err, res) {
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                }
                if (err || !res) { reportError(err, res); return; }
                notify(res.msg);
                if (res.code === 1) {
                    win.location.reload();
                } else {
                    var img = form.querySelector('.gbook-form__captcha-img, [class*="captcha"] img');
                    if (img && img.src) {
                        img.src = img.src.split('?')[0] + '?v=' + Date.now();
                    }
                }
            });
        });
    }

    /* ---------------------------------------------------------------- 訪問密碼 */

    /**
     * hook: form[data-mac-pwd-form]，屬性 data-mid / data-id / data-type，欄位 pwd
     * 端點 ajax/pwd（回 JSON）。驗過後端寫 session key `mid-type-id`，故成功即 reload，
     * 由控制器自己決定放行到詳情／播放／下載頁 —— 前端不需要知道該跳去哪。
     */
    function bindPwd() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-pwd-form]');
            if (!form) { return; }
            ev.preventDefault();

            var input = form.querySelector('[name="pwd"]');
            var pwd = input ? String(input.value || '').trim() : '';
            if (!pwd) {
                notify(tr('password_here') || '请输入访问密码');
                if (input) { input.focus(); }
                return;
            }

            var btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('data-loading', '1');
            }

            post('ajax/pwd', {
                mid: form.getAttribute('data-mid') || '1',
                id: form.getAttribute('data-id') || '',
                type: form.getAttribute('data-type') || '1',
                pwd: pwd
            }, function (err, res) {
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                }
                if (err || !res) { reportError(err, res); return; }
                // 1002 = 本頁 session 已驗過，一樣放行
                if (res.code === 1 || res.code === 1002) {
                    win.location.reload();
                    return;
                }
                notify(res.msg);
            });
        });
    }

    /* ---------------------------------------------------------------- 登入 */

    /**
     * hook: form[data-mac-login-form]，欄位 user_name / user_pwd / verify
     * 端點是 user/login（POST 時回 JSON）。user/ajax_login 只負責 fetch 出登入表單的
     * HTML 片段，不做認證——早期版本送去那裡，永遠登不進來。
     */
    function bindUnifiedAuth() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-auth-unified-form]');
            if (!form) { return; }
            ev.preventDefault();

            var data = {};
            each(form.querySelectorAll('input[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            var errBox = form.querySelector('[data-auth-unified-error]');
            var btn = form.querySelector('[data-auth-unified-submit]');
            var btnText = btn ? btn.textContent : '';
            if (errBox) {
                errBox.hidden = true;
                errBox.textContent = '';
            }
            if (btn) {
                btn.disabled = true;
                btn.textContent = '加载中...';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiUrl('user/login_or_register'), true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            var token = cookie('csrf_token');
            if (token) { xhr.setRequestHeader('X-CSRF-Token', token); }

            var body = [];
            for (var k in data) {
                if (Object.prototype.hasOwnProperty.call(data, k)) {
                    body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]));
                }
            }
            if (token) { body.push('__token__=' + encodeURIComponent(token)); }

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) { return; }
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = btnText;
                }
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
                if (!res) {
                    notify('网络异常，请稍后重试');
                    return;
                }
                if (res.code === 1) {
                    MacTheme.emit('login:ok', { form: form, res: res, unified: true });
                    gotoAfterLogin();
                    return;
                }
                if (errBox) {
                    errBox.textContent = res.msg || '登录失败';
                    errBox.hidden = false;
                } else {
                    notify(res.msg);
                }
            };
            xhr.send(body.join('&'));
        });
    }

    function bindLogin() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-login-form]');
            if (!form) { return; }
            ev.preventDefault();

            var data = {};
            each(form.querySelectorAll('input[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            post('user/login', data, function (err, res) {
                if (err || !res) { reportError(err, res); return; }
                if (res.code === 1) {
                    MacTheme.emit('login:ok', { form: form, res: res });
                    gotoAfterLogin();
                } else {
                    notify(res.msg);
                }
            });
        });
    }

    /* ---------------------------------------------------------------- 註冊 */

    /**
     * hook: form[data-mac-reg-form]，欄位 user_name / user_pwd / user_pwd2 / verify
     * 端點 user/reg（POST 回 JSON），成功後自動登入。
     */
    function bindReg() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-reg-form]');
            if (!form) { return; }
            ev.preventDefault();

            var data = {};
            each(form.querySelectorAll('input[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            post('user/reg', data, function (err, res) {
                if (err || !res) { reportError(err, res); return; }
                if (res.code === 1) {
                    MacTheme.emit('reg:ok', { form: form, res: res });
                    gotoAfterLogin();
                } else {
                    notify(res.msg);
                }
            });
        });
    }

    /* ---------------------------------------------------------------- 找回密码 */

    function refreshFindpassCaptcha(root) {
        var img = root && root.querySelector('[data-mac-findpass-captcha]');
        if (img) {
            img.src = img.src.split('?')[0] + '?v=' + (new Date()).getTime();
        }
    }

    function refreshFindpassMsgCaptcha(root) {
        var img = root && root.querySelector('[data-mac-findpass-msg-captcha]');
        if (img) {
            img.src = img.src.split('?')[0] + '?v=' + (new Date()).getTime();
        }
    }

    function showFindpassMsgStep2(wrap, toValue) {
        var sendForm = wrap.querySelector('[data-mac-findpass-send-form]');
        var resetForm = wrap.querySelector('[data-mac-findpass-reset-form]');
        var toHidden = wrap.querySelector('[data-findpass-to-hidden]');
        if (toHidden) toHidden.value = toValue || '';
        if (sendForm) {
            sendForm.hidden = true;
            sendForm.setAttribute('hidden', 'hidden');
        }
        if (resetForm) {
            resetForm.hidden = false;
            resetForm.removeAttribute('hidden');
        }
        each(wrap.querySelectorAll('[data-findpass-step-dot]'), function (dot) {
            var on = dot.getAttribute('data-findpass-step-dot') === '2';
            if (on) dot.classList.add('is-on');
            else dot.classList.remove('is-on');
        });
        var codeInput = resetForm && resetForm.querySelector('input[name="code"]');
        if (codeInput) codeInput.focus();
    }

    function bindFindpass() {
        doc.addEventListener('submit', function (ev) {
            var form = closest(ev.target, '[data-mac-findpass-form]');
            if (!form) { return; }
            ev.preventDefault();

            var data = {};
            each(form.querySelectorAll('input[name], select[name]'), function (f) {
                data[f.name] = f.value;
            });

            post('user/findpass', data, function (err, res) {
                refreshFindpassCaptcha(form);
                if (err || !res) { reportError(err, res); return; }
                if (res.code === 1) {
                    notify(res.msg || '密码已重置，请登录');
                    MacTheme.emit('findpass:ok', { form: form, res: res });
                    if (doc.querySelector('[data-auth-page]')) {
                        return;
                    }
                    if (doc.body.classList.contains('page-auth')) {
                        win.location.href = String(CFG.base).replace(/\/+$/, '') + '/index.php/user/login.html';
                    }
                } else {
                    notify(res.msg);
                }
            });
        });
    }

    function bindFindpassMsg() {
        doc.addEventListener('submit', function (ev) {
            var sendForm = closest(ev.target, '[data-mac-findpass-send-form]');
            if (sendForm) {
                ev.preventDefault();
                var wrap = closest(sendForm, '[data-mac-findpass-msg]');
                var data = {};
                each(sendForm.querySelectorAll('input[name], select[name]'), function (f) {
                    data[f.name] = f.value;
                });

                post('user/findpass_msg', data, function (err, res) {
                    refreshFindpassMsgCaptcha(wrap || sendForm);
                    if (err || !res) { reportError(err, res); return; }
                    if (res.code === 1) {
                        notify(res.msg || '验证码已发送');
                        showFindpassMsgStep2(wrap || sendForm, data.to);
                    } else {
                        notify(res.msg);
                    }
                });
                return;
            }

            var resetForm = closest(ev.target, '[data-mac-findpass-reset-form]');
            if (!resetForm) { return; }
            ev.preventDefault();

            var wrap2 = closest(resetForm, '[data-mac-findpass-msg]');
            var toInput = wrap2 && wrap2.querySelector('[data-findpass-to-input]');
            var toHidden = resetForm.querySelector('[data-findpass-to-hidden]');
            if (toHidden && toInput && !toHidden.value) {
                toHidden.value = toInput.value;
            }

            var payload = {};
            each(resetForm.querySelectorAll('input[name], select[name]'), function (f) {
                payload[f.name] = f.value;
            });

            post('user/findpass_reset', payload, function (err, res) {
                if (err || !res) { reportError(err, res); return; }
                if (res.code === 1) {
                    notify(res.msg || '密码已重置，请登录');
                    MacTheme.emit('findpass:ok', { form: resetForm, res: res });
                    if (doc.body.classList.contains('page-auth')) {
                        win.location.href = String(CFG.base).replace(/\/+$/, '') + '/index.php/user/login.html';
                    }
                } else {
                    notify(res.msg);
                }
            });
        });
    }

    function initRecoveryTabs() {
        function setRecoveryTab(root, tab) {
            if (!root) return;
            tab = tab === 'phone' ? 'phone' : (tab === 'email' ? 'email' : 'security');
            // 邮箱/短信未配置时该面板不输出（#272），回落到密保找回
            if (!root.querySelector('[data-auth-recovery-panel="' + tab + '"]')) tab = 'security';
            root.setAttribute('data-active-tab', tab);
            each(root.querySelectorAll('[data-auth-recovery-tab]'), function (btn) {
                var on = btn.getAttribute('data-auth-recovery-tab') === tab;
                if (on) btn.classList.add('is-on');
                else btn.classList.remove('is-on');
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            each(root.querySelectorAll('[data-auth-recovery-panel]'), function (panel) {
                var on = panel.getAttribute('data-auth-recovery-panel') === tab;
                panel.hidden = !on;
                if (on) panel.classList.add('is-on');
                else panel.classList.remove('is-on');
            });
        }

        each(doc.querySelectorAll('[data-auth-recovery]'), function (root) {
            if (root.getAttribute('data-recovery-bound') === '1') return;
            root.setAttribute('data-recovery-bound', '1');
            each(root.querySelectorAll('[data-auth-recovery-tab]'), function (btn) {
                btn.addEventListener('click', function () {
                    setRecoveryTab(root, btn.getAttribute('data-auth-recovery-tab'));
                });
            });
        });

        MacTheme.setRecoveryTab = setRecoveryTab;
    }

    /* ------------------------------------------------------------ 确认弹窗 */

    var confirmState = null;

    function closeConfirm(ok) {
        var root = doc.getElementById('mac-confirm');
        if (!root || !confirmState) { return; }
        var st = confirmState;
        confirmState = null;
        root.classList.remove('is-open');
        doc.body.classList.remove('has-mac-confirm');
        var reduce = win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function done() {
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            if (st.cb) { st.cb(!!ok); }
        }
        if (reduce) {
            done();
            return;
        }
        win.setTimeout(done, 220);
    }

    function openConfirm(options, callback) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        options = options || {};
        var cb = typeof callback === 'function' ? callback : null;
        var root = doc.getElementById('mac-confirm');
        if (!root) {
            if (win.MemberDialog && typeof win.MemberDialog.confirm === 'function') {
                win.MemberDialog.confirm(options, cb);
                return;
            }
            toast(tr('net_err'));
            if (cb) { cb(false); }
            return;
        }
        if (confirmState && confirmState.cb) {
            confirmState.cb(false);
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
            iconWrap.setAttribute('aria-hidden', tone === 'danger' ? 'false' : 'true');
        }
        confirmState = { cb: cb };
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('has-mac-confirm');
        win.requestAnimationFrame(function () {
            win.requestAnimationFrame(function () {
                root.classList.add('is-open');
                var focusEl = tone === 'danger' ? cancelBtn : okBtn;
                if (focusEl && focusEl.focus) { focusEl.focus(); }
            });
        });
    }

    function bindConfirm() {
        var root = doc.getElementById('mac-confirm');
        if (!root || root.getAttribute('data-mac-confirm-bound') === '1') { return; }
        root.setAttribute('data-mac-confirm-bound', '1');
        root.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-mac-confirm-cancel]')) {
                ev.preventDefault();
                closeConfirm(false);
                return;
            }
            if (ev.target.closest('[data-mac-confirm-ok]')) {
                ev.preventDefault();
                closeConfirm(true);
            }
        });
        doc.addEventListener('keydown', function (ev) {
            if (!confirmState || root.hidden) { return; }
            if (ev.key === 'Escape') {
                ev.preventDefault();
                closeConfirm(false);
            }
        });
    }

    /* ---------------------------------------------------------------- 出口 */

    var listeners = {};

    var MacTheme = {
        version: '1.0.0',
        config: CFG,
        post: post,
        i18n: i18n,
        lazy: lazy,
        cover: cover,
        notify: notify,
        confirm: openConfirm,
        isLoggedIn: isLoggedIn,
        requireLogin: requireLogin,
        openLogin: function () {
            MacTheme.emit('login:required', { source: 'api', modal: 'login' });
            notify(tr('login_req'));
        },

        /** 內容以 AJAX 換入後呼叫，重新掛上懶載入與多語 */
        refresh: function (root) {
            lazy.scan(root);
            cover.scan(root);
            i18n.apply(root);
        },

        on: function (name, fn) {
            (listeners[name] = listeners[name] || []).push(fn);
        },

        emit: function (name, detail) {
            each(listeners[name] || [], function (fn) {
                try { fn(detail); } catch (e) {}
            });
        },

        init: function () {
            bindAuthGate();
            bindUlog();
            bindDigg();
            bindComment();
            bindGbook();
            bindPwd();
            bindUnifiedAuth();
            bindLogin();
            bindReg();
            bindFindpass();
            bindFindpassMsg();
            initRecoveryTabs();
            bindConfirm();
            lazy.scan(doc);
            cover.scan(doc);
            i18n.init();
        }
    };

    win.MacTheme = MacTheme;

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', MacTheme.init);
    } else {
        MacTheme.init();
    }
})(window, document);
