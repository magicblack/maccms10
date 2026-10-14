/**
 * 小说 / 漫画章节已读进度（对齐 default 模板 art/read + 漫画阅读器）
 * - 小说 mid=2：localStorage key novel_reader_progress_art_{id}，字段 page / readPages
 * - 漫画 mid=12：localStorage key manga_reader_progress_{id}_s{sid}，字段 nid / readChapters
 * - 会员：get_read_chapters + ajax_ulog type=4
 */
(function (win, doc) {
  'use strict';

  var MID_ART = 2;
  var MID_MANGA = 12;

  function basePath() {
    var p = win.maccms && win.maccms.path ? String(win.maccms.path) : '';
    return p.replace(/\/+$/, '');
  }

  function apiUserUrl(path) {
    return basePath() + '/api.php/user/' + String(path || '').replace(/^\/+/, '');
  }

  function langText(key, fallback) {
    try {
      var map = win.MacTheme && win.MacTheme.i18n && win.MacTheme.i18n.map;
      if (map && Object.prototype.hasOwnProperty.call(map, key) && map[key] !== '') {
        return String(map[key]);
      }
      if (typeof win.MAC !== 'undefined' && typeof win.MAC.GetLang === 'function') {
        var v = win.MAC.GetLang(key);
        if (v != null && String(v) !== '' && String(v) !== 'undefined') {
          return String(v);
        }
      }
    } catch (e) { /* ignore */ }
    return fallback;
  }

  function defaultArtChapterTitle(page) {
    var n = parseInt(page, 10) || 0;
    return langText('art_read_chapter_ordinal', '第') + n + langText('art_read_chapter_unit', '章');
  }

  function defaultMangaChapterTitle(nid) {
    var n = parseInt(nid, 10) || 0;
    return langText('art_read_chapter_ordinal', '第') + n + langText('string_manga_hua', '话');
  }

  function normalizeChapterList(list) {
    var out = [];
    var seen = {};
    var src = list;
    if (typeof src === 'string') {
      src = src.split(/[\s,，、]+/);
    }
    if (!Array.isArray(src)) return out;
    for (var i = 0; i < src.length; i++) {
      var n = parseInt(src[i], 10);
      if (isNaN(n) || n < 1 || seen[n]) continue;
      seen[n] = 1;
      out.push(n);
    }
    out.sort(function (a, b) { return a - b; });
    return out;
  }

  function mergeChapterList(a, b) {
    return normalizeChapterList([].concat(a || [], b || []));
  }

  function isMemberLoggedIn() {
    try {
      if (typeof win.MAC !== 'undefined' && win.MAC.User) {
        if (Number(win.MAC.User.IsLogin) === 1) return true;
        if (typeof win.MAC.User.getAuthMe === 'function') {
          var me = win.MAC.User.getAuthMe();
          if (me && Number(me.is_login) === 1) return true;
        }
      }
      var uid = win.maccms && parseInt(win.maccms.userId, 10);
      if (uid > 0) return true;
    } catch (e) { /* ignore */ }
    return false;
  }

  function isAuthReady() {
    return typeof win.__authMe !== 'undefined';
  }

  function hasUserIdCookie() {
    try {
      if (typeof win.MAC !== 'undefined' && win.MAC.Cookie && typeof win.MAC.Cookie.Get === 'function') {
        var cid = win.MAC.Cookie.Get('user_id');
        return cid != null && String(cid).trim() !== '';
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function artStorageKey(artId) {
    return 'novel_reader_progress_art_' + String(artId);
  }

  function mangaStorageKey(mangaId, sid) {
    return 'manga_reader_progress_' + String(mangaId) + '_s' + String(sid || 1);
  }

  function readArtGuestProgress(artId) {
    try {
      var raw = win.localStorage.getItem(artStorageKey(artId));
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return null;
      var page = parseInt(data.page, 10);
      if (isNaN(page) || page < 1) return null;
      var readPages = normalizeChapterList(data.readPages);
      if (!readPages.length && page) readPages = [page];
      return {
        page: page,
        title: data.title || defaultArtChapterTitle(page),
        readPages: readPages,
        ts: parseInt(data.ts, 10) || 0
      };
    } catch (e) {
      return null;
    }
  }

  function saveArtGuestProgress(artId, page, title) {
    try {
      var old = readArtGuestProgress(artId);
      var readPages = mergeChapterList(old && old.readPages, [page]);
      win.localStorage.setItem(artStorageKey(artId), JSON.stringify({
        page: page,
        title: title || defaultArtChapterTitle(page),
        readPages: readPages,
        ts: Date.now()
      }));
    } catch (e) { /* ignore */ }
  }

  function readMangaGuestProgress(mangaId, sid) {
    try {
      var raw = win.localStorage.getItem(mangaStorageKey(mangaId, sid));
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return null;
      var nid = parseInt(data.nid, 10);
      if (isNaN(nid) || nid < 1) return null;
      var chapters = normalizeChapterList(data.readChapters);
      if (!chapters.length && nid) chapters = [nid];
      return {
        sid: sid,
        nid: nid,
        title: data.title || defaultMangaChapterTitle(nid),
        readChapters: chapters,
        ts: parseInt(data.ts, 10) || 0
      };
    } catch (e) {
      return null;
    }
  }

  function saveMangaGuestProgress(mangaId, sid, nid, title) {
    try {
      var old = readMangaGuestProgress(mangaId, sid);
      var chapters = mergeChapterList(old && old.readChapters, [nid]);
      win.localStorage.setItem(mangaStorageKey(mangaId, sid), JSON.stringify({
        nid: nid,
        title: title || defaultMangaChapterTitle(nid),
        readChapters: chapters,
        ts: Date.now()
      }));
    } catch (e) { /* ignore */ }
  }

  function touchMangaProgress(mangaId, sid, nid, title) {
    if (nid > 0) saveMangaGuestProgress(mangaId, sid, nid, title);
    return readMangaGuestProgress(mangaId, sid);
  }

  function touchArtProgress(artId, page, title) {
    if (page > 0) saveArtGuestProgress(artId, page, title);
    return readArtGuestProgress(artId);
  }

  function fetchMemberProgress(mid, rid, sid) {
    var url = apiUserUrl('get_read_chapters')
      + '?mid=' + encodeURIComponent(String(mid))
      + '&rid=' + encodeURIComponent(String(rid))
      + '&sid=' + encodeURIComponent(String(sid || 1));
    if (win.fetch) {
      return win.fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.withCredentials = true;
      xhr.onload = function () {
        try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); }
      };
      xhr.onerror = reject;
      xhr.send();
    });
  }

  function artMemberToState(info, curPage, curTitle, guestState) {
    if (!info && !guestState) return null;
    var pages = normalizeChapterList(info && info.read_chapters);
    if (guestState) pages = mergeChapterList(pages, guestState.readPages);
    var lastPage = parseInt(info && info.last_chapter, 10) || 0;
    if (guestState && parseInt(guestState.page, 10) > lastPage) {
      lastPage = parseInt(guestState.page, 10);
    }
    if (curPage > 0) {
      pages = mergeChapterList(pages, [curPage]);
      lastPage = curPage;
    }
    if (!lastPage && pages.length) lastPage = pages[pages.length - 1];
    return {
      page: lastPage,
      title: curTitle || (guestState && guestState.title) || defaultArtChapterTitle(lastPage),
      readPages: pages,
      ts: parseInt(info && info.last_read_time, 10) || (guestState && guestState.ts) || 0
    };
  }

  function mangaMemberToState(info, curNid, curTitle, guestState) {
    if (!info && !guestState) return null;
    var chapters = normalizeChapterList(info && info.read_chapters);
    if (guestState) chapters = mergeChapterList(chapters, guestState.readChapters);
    var lastNid = parseInt(info && info.last_chapter, 10) || 0;
    if (guestState && parseInt(guestState.nid, 10) > lastNid) {
      lastNid = parseInt(guestState.nid, 10);
    }
    if (curNid > 0) {
      chapters = mergeChapterList(chapters, [curNid]);
      lastNid = curNid;
    }
    if (!lastNid && chapters.length) lastNid = chapters[chapters.length - 1];
    return {
      sid: parseInt(info && info.sid, 10) || (guestState && guestState.sid) || 1,
      nid: lastNid,
      title: curTitle || (guestState && guestState.title) || defaultMangaChapterTitle(lastNid),
      readChapters: chapters,
      ts: parseInt(info && info.last_read_time, 10) || (guestState && guestState.ts) || 0
    };
  }

  function bootstrapProgress(opts, onReady) {
    var mid = parseInt(opts.mid, 10) || 0;
    var rid = parseInt(opts.rid, 10) || 0;
    var sid = parseInt(opts.sid, 10) || 1;
    var curChapter = parseInt(opts.curChapter, 10) || 0;
    var curTitle = opts.curTitle || '';
    var identityMode = '';

    function deliver(state) {
      if (onReady) onReady(state || null);
    }

    function startGuest() {
      identityMode = 'guest';
      var state = null;
      if (mid === MID_ART) state = touchArtProgress(rid, curChapter, curTitle);
      else if (mid === MID_MANGA) state = touchMangaProgress(rid, sid, curChapter, curTitle);
      deliver(state);
    }

    function startMember() {
      identityMode = 'member';
      var guestState = null;
      if (mid === MID_ART) guestState = touchArtProgress(rid, curChapter, curTitle);
      else if (mid === MID_MANGA) guestState = touchMangaProgress(rid, sid, curChapter, curTitle);
      if (guestState) deliver(guestState);
      fetchMemberProgress(mid, rid, sid).then(function (res) {
        if (identityMode !== 'member') return;
        if (res && Number(res.code) === 1401) {
          identityMode = '';
          startGuest();
          return;
        }
        var cachedGuest = null;
        if (mid === MID_ART) cachedGuest = readArtGuestProgress(rid);
        else if (mid === MID_MANGA) cachedGuest = readMangaGuestProgress(rid, sid);
        if (!(res && Number(res.code) === 1 && res.info)) {
          if (cachedGuest) deliver(cachedGuest);
          return;
        }
        if (mid === MID_ART) deliver(artMemberToState(res.info, curChapter, curTitle, cachedGuest));
        else if (mid === MID_MANGA) deliver(mangaMemberToState(res.info, curChapter, curTitle, cachedGuest));
      }).catch(function () {
        if (identityMode === 'member') {
          identityMode = '';
          startGuest();
        }
      });
    }

    function bootstrap() {
      if (identityMode) return;
      if (isMemberLoggedIn()) {
        startMember();
        return;
      }
      if (!hasUserIdCookie()) {
        startGuest();
        return;
      }
      if (isAuthReady()) {
        startMember();
      }
    }

    function waitForIdentity() {
      bootstrap();
      if (identityMode) return;

      var settled = false;
      function finish() {
        if (settled || identityMode) return;
        settled = true;
        try { doc.removeEventListener('mac:auth-me-ready', onAuthReady); } catch (e) { /* ignore */ }
        if (isMemberLoggedIn() || hasUserIdCookie()) startMember();
        else startGuest();
      }
      function onAuthReady() { finish(); }
      try { doc.addEventListener('mac:auth-me-ready', onAuthReady); } catch (e) { /* ignore */ }
      setTimeout(finish, 1200);
    }

    waitForIdentity();
  }

  function applyNovelPlayerMarks(root, state, curPage) {
    var catalog = doc.querySelector('[data-mac-novel-catalog]');
    var scope = catalog || root;
    if (!state) return;
    var readPages = normalizeChapterList(state.readPages);
    var lastPage = parseInt(state.page, 10) || 0;
    var chapterMark = lastPage ? defaultArtChapterTitle(lastPage) : '';
    var lastTitle = state.title || chapterMark;

    scope.querySelectorAll('[data-mac-novel-catalog-item]').forEach(function (item) {
      var p = parseInt(item.getAttribute('data-page'), 10);
      var isRead = !isNaN(p) && readPages.indexOf(p) !== -1;
      item.classList.toggle('read', isRead);
      item.classList.toggle('is-read', isRead);
    });

    var progressEl = catalog ? catalog.querySelector('[data-mac-novel-catalog-progress]') : null;
    var progressText = catalog ? catalog.querySelector('[data-mac-novel-progress-text]') : null;
    var continueBtn = catalog ? catalog.querySelector('[data-mac-novel-continue]') : null;

    if (lastPage && lastPage !== curPage && progressEl && continueBtn) {
      var label = lastTitle && lastTitle !== chapterMark
        ? (lastTitle + '（' + chapterMark + '）')
        : chapterMark;
      if (progressText) progressText.textContent = label;
      var target = scope.querySelector('[data-mac-novel-catalog-item][data-page="' + String(lastPage) + '"]');
      if (target && target.href) continueBtn.setAttribute('href', target.href);
      progressEl.hidden = false;
    } else if (progressEl) {
      progressEl.hidden = true;
    }
  }

  function applyMangaPlayerMarks(root, state, curNid) {
    if (!root) return;
    var list = state ? normalizeChapterList(state.readChapters) : [];
    root.querySelectorAll('[data-mac-manga-catalog-item]').forEach(function (item) {
      var nid = parseInt(item.getAttribute('data-nid'), 10);
      var isRead = state && !isNaN(nid) && list.indexOf(nid) !== -1;
      item.classList.toggle('is-read', !!isRead);
      var readEl = item.querySelector('.manga-player__catalog-read');
      if (readEl) readEl.hidden = !isRead || item.classList.contains('is-cur');
    });
  }

  function applyArtDetailMarks(root, state) {
    if (!root || !state) return;
    var readPages = normalizeChapterList(state.readPages);
    var lastPage = parseInt(state.page, 10) || 0;

    root.querySelectorAll('[data-mac-art-toc-item]').forEach(function (item) {
      var p = parseInt(item.getAttribute('data-page'), 10);
      var isRead = !isNaN(p) && readPages.indexOf(p) !== -1;
      item.classList.toggle('is-read', isRead);
      item.classList.toggle('read', isRead);
      item.classList.toggle('is-last-read', !isNaN(p) && p === lastPage);
    });

    var playBtn = root.querySelector('[data-mac-art-read-btn]');
    var playLabel = root.querySelector('[data-mac-art-read-btn-label]');
    var progressEl = root.querySelector('[data-mac-art-read-progress]');
    var progressText = root.querySelector('[data-mac-art-read-progress-text]');

    if (lastPage > 1 && playBtn) {
      var link = root.querySelector('[data-mac-art-toc-item][data-page="' + String(lastPage) + '"] a');
      if (link && link.href) {
        playBtn.setAttribute('href', link.href);
        if (playLabel) {
          // 「继续阅读」保留 data-lang，字典晚于本脚本加载时仍会被 i18n.apply 翻译；
          // 章节名单独成段，桌面端由 CSS 隐藏（已在「最近阅读」行展示），保证按钮尺寸前后一致
          var title = state.title || defaultArtChapterTitle(lastPage);
          var verb = doc.createElement('span');
          verb.setAttribute('data-lang', 'string_continue_read');
          verb.textContent = langText('string_continue_read', '继续阅读');
          var chapter = doc.createElement('span');
          chapter.className = 'media-hero__play-chapter';
          chapter.textContent = ' · ' + title;
          playLabel.removeAttribute('data-lang');
          playLabel.textContent = '';
          playLabel.appendChild(verb);
          playLabel.appendChild(chapter);
        }
      }
    }

    if (progressEl && progressText && lastPage > 0) {
      progressText.textContent = state.title || defaultArtChapterTitle(lastPage);
      progressEl.hidden = false;
    }
  }

  function applyMangaDetailMarks(root, sid, state) {
    if (!root || !state) return;
    var list = normalizeChapterList(state.readChapters);
    var lastNid = parseInt(state.nid, 10) || 0;
    var group = root.querySelector('[data-mac-manga-eps-group][data-sid="' + String(sid) + '"]');
    if (!group) return;

    group.querySelectorAll('[data-mac-manga-eps-item]').forEach(function (item) {
      var nid = parseInt(item.getAttribute('data-nid'), 10);
      var isRead = !isNaN(nid) && list.indexOf(nid) !== -1;
      item.classList.toggle('is-read', isRead);
      item.classList.toggle('is-last-read', !isNaN(nid) && nid === lastNid);
    });

    var playBtn = root.querySelector('[data-mac-manga-read-btn]');
    var playLabel = root.querySelector('[data-mac-manga-read-btn-label]');
    var progressEl = root.querySelector('[data-mac-manga-read-progress]');
    var progressText = root.querySelector('[data-mac-manga-read-progress-text]');

    if (lastNid > 0 && playBtn) {
      var link = group.querySelector('[data-mac-manga-eps-item][data-nid="' + String(lastNid) + '"] a');
      if (link && link.href) {
        playBtn.setAttribute('href', link.href);
        if (playLabel) {
          playLabel.setAttribute('data-lang', 'string_continue_read');
          playLabel.textContent = langText('string_continue_read', '继续阅读');
        }
      }
    }

    if (progressEl && progressText && lastNid > 0) {
      progressText.textContent = state.title || defaultMangaChapterTitle(lastNid);
      progressEl.hidden = false;
    }
  }

  function initNovelPlayer(root) {
    if (!root || root._novelReadInited) return;
    root._novelReadInited = true;
    var ulog = root.querySelector('[data-mac-novel-ulog]');
    if (!ulog) return;
    var artId = parseInt(ulog.getAttribute('data-id'), 10) || 0;
    var curPage = parseInt(ulog.getAttribute('data-nid'), 10) || 0;
    var curTitle = root.getAttribute('data-mac-novel-part') || '';
    bootstrapProgress({
      mid: MID_ART,
      rid: artId,
      sid: 1,
      curChapter: curPage,
      curTitle: curTitle
    }, function (state) {
      applyNovelPlayerMarks(root, state, curPage);
    });
  }

  function initArtDetail(root) {
    if (!root || root._artDetailReadInited) return;
    root._artDetailReadInited = true;
    var artId = parseInt(root.getAttribute('data-mac-art-id'), 10) || 0;
    if (artId < 1) return;
    bootstrapProgress({
      mid: MID_ART,
      rid: artId,
      sid: 1,
      curChapter: 0,
      curTitle: ''
    }, function (state) {
      applyArtDetailMarks(root, state);
    });
  }

  function initMangaPlayer(root) {
    if (!root || root._mangaReadInited) return;
    root._mangaReadInited = true;
    var ulog = root.querySelector('[data-mac-manga-ulog]');
    if (!ulog) return;
    var mangaId = parseInt(ulog.getAttribute('data-id'), 10) || 0;
    var sid = parseInt(ulog.getAttribute('data-sid'), 10) || 1;
    var curNid = parseInt(ulog.getAttribute('data-nid'), 10) || 0;
    var curTitle = root.getAttribute('data-mac-manga-part') || '';
    bootstrapProgress({
      mid: MID_MANGA,
      rid: mangaId,
      sid: sid,
      curChapter: curNid,
      curTitle: curTitle
    }, function (state) {
      applyMangaPlayerMarks(root, state, curNid);
    });
  }

  function initMangaDetail(root) {
    if (!root || root._mangaDetailReadInited) return;
    root._mangaDetailReadInited = true;
    var mangaId = parseInt(root.getAttribute('data-mac-manga-id'), 10) || 0;
    if (mangaId < 1) return;
    root.querySelectorAll('[data-mac-manga-eps-group]').forEach(function (group) {
      var sid = parseInt(group.getAttribute('data-sid'), 10) || 1;
      bootstrapProgress({
        mid: MID_MANGA,
        rid: mangaId,
        sid: sid,
        curChapter: 0,
        curTitle: ''
      }, function (state) {
        applyMangaDetailMarks(root, sid, state);
      });
    });
  }

  var api = {
    initNovelPlayer: initNovelPlayer,
    initArtDetail: initArtDetail,
    initMangaPlayer: initMangaPlayer,
    initMangaDetail: initMangaDetail,
    readArtGuestProgress: readArtGuestProgress,
    readMangaGuestProgress: readMangaGuestProgress,
    artStorageKey: artStorageKey,
    mangaStorageKey: mangaStorageKey
  };

  win.MacReaderReadProgress = api;
  win.MacMangaReadProgress = {
    initPlayer: initMangaPlayer,
    initDetail: initMangaDetail,
    readGuestProgress: readMangaGuestProgress,
    storageKey: mangaStorageKey
  };
})(window, document);
