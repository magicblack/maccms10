/**
 * 视频播放进度记忆 / 续播（TwigPageCompiler::emitNp2PlayShellHeadTags 在含 vod_player 的页面注入）
 *
 * 同源播放 iframe（static/player/dplayer.html / videojs.html）经 postMessage 上报进度：
 * - 子 -> 父：{ source:'mac-player', type:'ready'|'playing'|'progress', point, duration, checkpoint }
 * - 父 -> 子：{ source:'mac-play-parent', type:'progress_seek', point }
 * 第三方解析页 / 外链分享页（跨域）读不到播放时间：按页面可见时长估算，封顶片长
 * （durationSec = 服务端解析的 vod_duration；解析不出时读 probe 里同一集的 m3u8 累加 EXTINF）。
 *
 * 已登录：节流 POST api.php/ulog/progress 写入 mac_ulog(type=4) 的 ulog_point / ulog_duration，
 * 个人中心「播放记录」据此显示观看进度；未登录先存 localStorage，登录后 ulog/merge 合并。
 * 配置读 #vod-play-progress-config。
 */
(function (win, doc) {
  'use strict';

  var MSG_CHILD = 'mac-player';
  var MSG_PARENT = 'mac-play-parent';
  var LS_KEY = 'mac_vod_play_progress_v1';
  var REPORT_INTERVAL = 12000;
  var LOCAL_SAVE_INTERVAL = 3000;
  var MIN_POINT = 3;
  var END_RATIO = 0.95;
  var LS_MAX = 120;
  var TOAST_MS = 12000;

  function readConfig() {
    var el = doc.getElementById('vod-play-progress-config');
    if (!el || !el.textContent) return null;
    try {
      return JSON.parse(el.textContent);
    } catch (e) {
      return null;
    }
  }

  function joinUrl(base, path) {
    return String(base || '').replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
  }

  function sec(v) {
    return Math.max(0, Math.floor(Number(v) || 0));
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function fmtTime(s) {
    s = sec(s);
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var r = s % 60;
    return h > 0 ? h + ':' + pad2(m) + ':' + pad2(r) : m + ':' + pad2(r);
  }

  function langText(key, fallback) {
    try {
      var map = win.MacTheme && win.MacTheme.i18n && win.MacTheme.i18n.map;
      if (map && Object.prototype.hasOwnProperty.call(map, key) && map[key] !== '') {
        return String(map[key]);
      }
      if (typeof jQuery !== 'undefined' && jQuery.i18n && jQuery.i18n.map && jQuery.i18n.map[key]) {
        return String(jQuery.i18n.map[key]);
      }
    } catch (e) { /* ignore */ }
    return fallback;
  }

  function currentUserId(cfg) {
    var id = Number(cfg.userId) || 0;
    if (id > 0) return id;
    var m = win.maccms || {};
    return Number(m.userId != null ? m.userId : m.user_id) || 0;
  }

  /* ---------------------------------------------------- localStorage 兜底 */

  function lsKey(cfg) {
    return String(cfg.vodId) + '_' + String(cfg.sid) + '_' + String(cfg.nid);
  }

  function lsAll() {
    try {
      var raw = win.localStorage.getItem(LS_KEY);
      if (!raw) return {};
      var o = JSON.parse(raw);
      return o && typeof o === 'object' ? o : {};
    } catch (e) {
      return {};
    }
  }

  function lsWrite(all) {
    try {
      var keys = Object.keys(all || {});
      if (keys.length > LS_MAX) {
        keys.sort(function (a, b) {
          return ((all[b] && all[b].updated) || 0) - ((all[a] && all[a].updated) || 0);
        });
        var trimmed = {};
        for (var i = 0; i < LS_MAX; i++) trimmed[keys[i]] = all[keys[i]];
        all = trimmed;
      }
      win.localStorage.setItem(LS_KEY, JSON.stringify(all));
    } catch (e) { /* ignore */ }
  }

  function lsSave(cfg, point, duration, opts) {
    opts = opts || {};
    point = sec(point);
    duration = sec(duration);
    var key = lsKey(cfg);
    var all = lsAll();
    var prev = all[key];
    if (prev) {
      var prevPoint = sec(prev.point);
      // 刚加载（point≈0）时不要把已有进度冲掉
      if (point < MIN_POINT && prevPoint >= MIN_POINT) return;
      if (!opts.exact) {
        if (prevPoint > point) point = prevPoint;
        if (!duration && prev.duration) duration = sec(prev.duration);
      }
    }
    all[key] = {
      vod_id: cfg.vodId,
      sid: cfg.sid,
      nid: cfg.nid,
      point: point,
      duration: duration,
      updated: Date.now()
    };
    lsWrite(all);
  }

  function lsGet(cfg) {
    return lsAll()[lsKey(cfg)] || null;
  }

  /** 进度太靠前或已接近片尾都不提示续播 */
  function worthResume(point, duration) {
    point = sec(point);
    duration = sec(duration);
    return !(point < MIN_POINT || (duration > MIN_POINT && point >= duration * END_RATIO));
  }

  function pickLater(a, b) {
    var pa = (a && Number(a.point)) || 0;
    var pb = (b && Number(b.point)) || 0;
    if (pb >= pa && pb > 0) return b;
    return pa > 0 ? a : null;
  }

  /* ---------------------------------------------------- 服务端 */

  function fetchServer(cfg) {
    if (!(currentUserId(cfg) > 0)) return Promise.resolve(null);
    var url = joinUrl(cfg.apiBase, 'ulog/get_progress') +
      '?vod_id=' + encodeURIComponent(cfg.vodId) + '&nid=' + encodeURIComponent(cfg.nid);
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j) return null;
        var info = j.info || j.data || null;
        if (!info || (Number(j.code) !== 1 && Number(j.code) !== 0)) return null;
        return { point: Number(info.point) || 0, duration: Number(info.duration) || 0 };
      })
      .catch(function () { return null; });
  }

  function report(cfg, point, duration, useBeacon, opts) {
    point = sec(point);
    duration = sec(duration);
    lsSave(cfg, point, duration, opts);
    if (!(currentUserId(cfg) > 0)) return;
    var url = joinUrl(cfg.apiBase, 'ulog/progress');
    var body = 'vod_id=' + encodeURIComponent(cfg.vodId) +
      '&sid=' + encodeURIComponent(cfg.sid) +
      '&nid=' + encodeURIComponent(cfg.nid) +
      '&point=' + encodeURIComponent(String(point)) +
      '&duration=' + encodeURIComponent(String(duration));
    if (useBeacon && navigator.sendBeacon) {
      try {
        navigator.sendBeacon(url, new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' }));
      } catch (e) { /* ignore */ }
      return;
    }
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body,
      keepalive: true
    }).catch(function () {});
  }

  /** 登录后把未登录期间存在本地的进度合并到服务端 */
  function mergeLocal(cfg) {
    if (!(currentUserId(cfg) > 0)) return;
    var all = lsAll();
    var list = [];
    Object.keys(all).forEach(function (k) {
      var r = all[k];
      if (!r || !(Number(r.vod_id) > 0) || !(Number(r.point) > 0)) return;
      list.push({
        vod_id: Number(r.vod_id) || 0,
        sid: Number(r.sid) || 0,
        nid: Number(r.nid) || 0,
        point: Number(r.point) || 0,
        duration: Number(r.duration) || 0
      });
    });
    if (!list.length) return;
    fetch(joinUrl(cfg.apiBase, 'ulog/merge'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: 'list=' + encodeURIComponent(JSON.stringify(list))
    }).catch(function () {});
  }

  /* ---------------------------------------------------- 控制器 */

  function Progress(cfg) {
    this.cfg = cfg;
    this.iframe = null;
    this.playerReady = false;
    this.pendingSeek = null;
    this.lastReportAt = 0;
    this.lastLocalSaveAt = 0;
    this.lastPoint = 0;
    this.lastDuration = 0;
    this.resumePoint = 0;
    this.resumeSeekPending = false;
    this.resumeToastShown = false;
    this.resumeEl = null;
    this._onMessage = this.onMessage.bind(this);
  }

  function isPlayerFrame(f) {
    var src = f.getAttribute('src') || '';
    return src.indexOf('dplayer.html') !== -1 || src.indexOf('videojs.html') !== -1;
  }

  function frameDoc(f) {
    try {
      return f.contentDocument || null;
    } catch (e) {
      return null;
    }
  }

  function isAdFrame(src) {
    var cfgP = win.MacPlayerConfig || {};
    var skip = [cfgP.prestrain, cfgP.buffer, 'prestrain.html', 'loading.html'];
    for (var k = 0; k < skip.length; k++) {
      if (skip[k] && src.indexOf(String(skip[k]).replace(/^https?:/, '')) !== -1) return true;
    }
    return false;
  }

  function isForeignFrame(f) {
    var src = f.getAttribute('src') || '';
    if (!src || src.indexOf('javascript:') === 0 || isPlayerFrame(f) || isAdFrame(src)) return false;
    var a = doc.createElement('a');
    a.href = src;
    return (a.protocol === 'http:' || a.protocol === 'https:') && a.host !== win.location.host;
  }

  /**
   * 判定播放区当前是哪种播放器：
   * - exact：同源 dplayer/videojs（直接嵌，或嵌在密码/试看门控的 vod/player 包装页里，
   *   包装页会把 postMessage 双向转发，见 starter html/vod/player.twig）
   * - foreign：第三方解析页 / 外链分享页（跨域，只能估算）
   * - null：还在预加载广告 / 密码门 / 未渲染，继续等
   */
  Progress.prototype.classify = function () {
    var wrap = doc.querySelector('.player_video');
    if (!wrap) return null;
    var frames = wrap.querySelectorAll('iframe');
    for (var i = frames.length - 1; i >= 0; i--) {
      var f = frames[i];
      if (isPlayerFrame(f)) return { mode: 'exact', frame: f };
      if (isForeignFrame(f)) return { mode: 'foreign', frame: f };
      var inner = frameDoc(f);
      if (!inner) continue;
      var sub = inner.querySelectorAll('iframe');
      for (var k = sub.length - 1; k >= 0; k--) {
        if (isPlayerFrame(sub[k])) return { mode: 'exact', frame: f };
        if (isForeignFrame(sub[k])) return { mode: 'foreign', frame: sub[k] };
      }
    }
    return null;
  };

  Progress.prototype.findPlayerIframe = function () {
    var c = this.classify();
    return c && c.mode === 'exact' ? c.frame : null;
  };

  Progress.prototype.postToIframe = function (payload) {
    if (!this.iframe || !this.iframe.contentWindow) return;
    try {
      this.iframe.contentWindow.postMessage(Object.assign({ source: MSG_PARENT }, payload), win.location.origin);
    } catch (e) { /* ignore */ }
  };

  Progress.prototype.onMessage = function (ev) {
    try {
      if (ev.origin !== win.location.origin) return;
    } catch (e) {
      return;
    }
    var d = ev.data;
    if (!d || d.source !== MSG_CHILD) return;
    // iframe 可能在轮询找到它之前就发出 ready，这里顺带认领
    if (!this.iframe) this.iframe = this.findPlayerIframe();
    if (!this.iframe || ev.source !== this.iframe.contentWindow) return;
    if (!this.playerReady && d.type === 'progress') {
      this.playerReady = true;
      if (this.pendingSeek != null) {
        this.seekPlayer(this.pendingSeek);
        this.pendingSeek = null;
      }
    }
    if (d.type === 'ready') {
      this.playerReady = true;
      if (this.pendingSeek != null) {
        this.seekPlayer(this.pendingSeek);
        this.pendingSeek = null;
      } else if (this.resumePoint > 0) {
        this.seekPlayer(this.resumePoint);
      }
      return;
    }
    if (d.type === 'playing') {
      // 部分 HLS 源在首帧前 seek 无效，开播后再补一次
      if (this.resumeSeekPending && this.resumePoint > 0) this.seekPlayer(this.resumePoint);
      return;
    }
    if (d.type === 'progress') {
      if (this.resumeSeekPending && this.resumePoint > 0 && sec(d.point) >= this.resumePoint - 1) {
        this.resumeSeekPending = false;
      }
      this.handleProgress(Number(d.point) || 0, Number(d.duration) || 0, d.checkpoint === true);
    }
  };

  Progress.prototype.flush = function (useBeacon) {
    if (this.lastPoint <= 0) return;
    report(this.cfg, this.lastPoint, this.lastDuration, !!useBeacon, { exact: true });
  };

  Progress.prototype.handleProgress = function (point, duration, checkpoint) {
    point = sec(point);
    duration = sec(duration);
    this.lastPoint = point;
    if (duration > 0) this.lastDuration = duration;
    if (checkpoint && point <= 0) return;
    var dur = duration || this.lastDuration;
    var now = Date.now();
    if (checkpoint) {
      // 暂停：立即落盘
      this.lastLocalSaveAt = now;
      this.lastReportAt = now;
      report(this.cfg, point, dur, false, { exact: true });
      this.refreshResumeText(point, dur);
      return;
    }
    if (now - this.lastLocalSaveAt >= LOCAL_SAVE_INTERVAL) {
      this.lastLocalSaveAt = now;
      lsSave(this.cfg, point, dur);
    }
    if (now - this.lastReportAt >= REPORT_INTERVAL) {
      this.lastReportAt = now;
      report(this.cfg, point, dur, false);
    }
  };

  Progress.prototype.seekPlayer = function (point) {
    if (!this.playerReady) {
      this.pendingSeek = point;
      return;
    }
    this.postToIframe({ type: 'progress_seek', point: point });
  };

  Progress.prototype.resumeHint = function (point) {
    return langText('string_play_resume_hint', '上次看到 {time}，点击继续').replace(/\{time\}/g, fmtTime(point));
  };

  Progress.prototype.refreshResumeText = function (point, duration) {
    if (!worthResume(point, duration)) return;
    this.resumePoint = sec(point);
    if (!this.resumeEl) return;
    var t = this.resumeEl.querySelector('.mac-play-resume__text');
    if (t) t.textContent = this.resumeHint(point);
  };

  Progress.prototype.showResumeToast = function (point) {
    var wrap = doc.querySelector('.player_video');
    if (!wrap) return;
    var self = this;
    var el = doc.createElement('div');
    el.className = 'mac-play-resume';
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<span class="mac-play-resume__text"></span>' +
      '<button type="button" class="mac-play-resume__btn" data-lang="string_play_resume_btn"></button>' +
      '<button type="button" class="mac-play-resume__close" data-lang-aria-label="string_close">&times;</button>';
    el.querySelector('.mac-play-resume__text').textContent = this.resumeHint(point);
    el.querySelector('.mac-play-resume__btn').textContent = langText('string_play_resume_btn', '继续播放');
    el.querySelector('.mac-play-resume__close').setAttribute('aria-label', langText('string_close', '关闭'));
    wrap.appendChild(el);
    this.resumeEl = el;

    function close() {
      el.classList.remove('is-visible');
      win.setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
        if (self.resumeEl === el) self.resumeEl = null;
      }, 260);
    }
    el.querySelector('.mac-play-resume__btn').addEventListener('click', function () {
      self.seekPlayer(self.resumePoint || point);
      close();
    });
    el.querySelector('.mac-play-resume__close').addEventListener('click', close);
    win.requestAnimationFrame(function () { el.classList.add('is-visible'); });
    win.setTimeout(close, TOAST_MS);
  };

  Progress.prototype.applyResume = function (point) {
    point = sec(point);
    if (point < MIN_POINT) return 0;
    this.resumePoint = point;
    this.resumeSeekPending = true;
    this.seekPlayer(point);
    return point;
  };

  Progress.prototype.loadAndOfferResume = function () {
    var self = this;
    var local = lsGet(this.cfg);
    if (local && worthResume(local.point, local.duration)) this.applyResume(Number(local.point) || 0);
    fetchServer(this.cfg).then(function (server) {
      var localNow = lsGet(self.cfg);
      var best = pickLater(localNow, server);
      if (!best || !worthResume(best.point, best.duration)) return;
      var p = Number(best.point) || 0;
      if (server && Number(server.point) > Number(localNow && localNow.point)) {
        lsSave(self.cfg, server.point, server.duration || 0);
      }
      if (p > self.resumePoint) self.applyResume(p);
      if (!self.resumeToastShown) {
        self.resumeToastShown = true;
        self.showResumeToast(p);
      }
    });
  };

  Progress.prototype.bindLifecycle = function () {
    var self = this;
    function onLeave() { self.flush(true); }
    win.addEventListener('pagehide', onLeave);
    win.addEventListener('beforeunload', onLeave);
    doc.addEventListener('visibilitychange', function () {
      if (doc.visibilityState === 'hidden') onLeave();
    });
    // 「刷新」按钮 / 选集链接跳转前先落一次进度
    doc.addEventListener('click', function (ev) {
      var t = ev.target;
      if (t && t.closest && t.closest('[data-vod-player-refresh], .pbs-vod-player__eps a')) {
        self.flush(false);
      }
    }, true);
  };

  Progress.prototype.bindLoginMerge = function () {
    var self = this;
    if (currentUserId(this.cfg) > 0) {
      mergeLocal(this.cfg);
      return;
    }
    doc.addEventListener('mac:auth-me-ready', function onReady(ev) {
      var me = ev && ev.detail;
      if (!me || Number(me.is_login) !== 1) return;
      doc.removeEventListener('mac:auth-me-ready', onReady);
      self.cfg.userId = Number(me.user_id) || 1;
      mergeLocal(self.cfg);
      if (self.lastPoint > 0) report(self.cfg, self.lastPoint, self.lastDuration, false);
    });
  };

  /* ---------------------------------------------------- 跨域播放器估算 */

  /**
   * 跨域播放器无法上报进度：按「播放页可见时长」从上次进度往后累加，封顶为影片时长（vod_duration）。
   * 是近似值（感知不到暂停 / 拖动），但让「播放记录」对所有线路都有进度可看。
   */
  Progress.prototype.startEstimate = function () {
    var self = this;
    var cfg = this.cfg;
    var duration = sec(cfg.durationSec) || parseVodDuration(cfg.vodDuration);
    var base = 0;
    var elapsed = 0;
    var started = false;

    function begin(prev) {
      if (started) return;
      started = true;
      if (prev && worthResume(prev.point, duration || prev.duration)) base = sec(prev.point);
      // 首次上报推迟一个周期：打开即离开不留「已看 0:01」
      self.lastReportAt = Date.now();
      win.setInterval(function () {
        if (doc.visibilityState === 'hidden') return;
        elapsed += 1;
        var point = base + elapsed;
        if (duration > 0 && point > duration) point = duration;
        self.handleProgress(point, duration, false);
      }, 1000);
    }

    // vod_duration 缺失 / 无法识别：读同一集 m3u8 得到真实片长，否则进度只能显示「已看 mm:ss」没有进度条
    if (!(duration > 0)) {
      probeDuration(cfg.probe).then(function (d) {
        if (!(d > 0)) return;
        duration = d;
      });
    }

    var local = lsGet(cfg);
    fetchServer(cfg).then(function (server) { begin(pickLater(local, server)); });
    // 接口异常也不要卡住计时
    win.setTimeout(function () { begin(local); }, 4000);
  };

  var PROBE_TIMEOUT = 10000;

  function fetchText(url) {
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = ctrl ? win.setTimeout(function () { ctrl.abort(); }, PROBE_TIMEOUT) : 0;
    return fetch(url, { credentials: 'omit', signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .catch(function () { return ''; })
      .then(function (t) {
        if (timer) win.clearTimeout(timer);
        return t;
      });
  }

  /** m3u8 片长 = 各分片 EXTINF 之和；主播放列表取第一个码率子列表 */
  function m3u8Duration(url, depth) {
    return fetchText(url).then(function (txt) {
      if (!txt || txt.indexOf('#EXTM3U') === -1) return 0;
      var lines = txt.split(/\r?\n/);
      if (txt.indexOf('#EXT-X-STREAM-INF') !== -1) {
        if (depth > 1) return 0;
        for (var i = 0; i < lines.length; i++) {
          if (lines[i].indexOf('#EXT-X-STREAM-INF') !== 0) continue;
          for (var j = i + 1; j < lines.length; j++) {
            var u = lines[j].trim();
            if (!u || u.charAt(0) === '#') continue;
            try {
              return m3u8Duration(new URL(u, url).href, depth + 1);
            } catch (e) {
              return 0;
            }
          }
        }
        return 0;
      }
      var total = 0;
      lines.forEach(function (ln) {
        var m = /^#EXTINF:\s*([\d.]+)/.exec(ln);
        if (m) total += parseFloat(m[1]) || 0;
      });
      return Math.round(total);
    });
  }

  /** 依次尝试 probe 列表（服务端给的同一集 m3u8 地址），取第一个可用片长 */
  function probeDuration(list) {
    list = Array.isArray(list) ? list.slice(0, 2) : [];
    var i = 0;
    function next() {
      if (i >= list.length) return Promise.resolve(0);
      var url = String(list[i++] || '');
      if (url.indexOf('//') === 0) url = win.location.protocol + url;
      return m3u8Duration(url, 0).then(function (d) {
        return d >= 10 && d <= 86400 ? d : next();
      });
    }
    return next();
  }

  /** vod_duration 自由文本：H:MM:SS / MM:SS / 纯数字分钟；服务端 durationSec（mac_vod_duration_seconds）解析更全，此处仅兜底 */
  function parseVodDuration(v) {
    var s = String(v == null ? '' : v).trim();
    var m;
    if ((m = s.match(/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/))) return (+m[1]) * 3600 + (+m[2]) * 60 + (+m[3]);
    if ((m = s.match(/^(\d{1,3}):(\d{1,2})$/))) return (+m[1]) * 60 + (+m[2]);
    if (/^\d{1,4}$/.test(s)) return (+s) * 60;
    return 0;
  }

  Progress.prototype.start = function () {
    var self = this;
    win.addEventListener('message', this._onMessage);
    this.bindLifecycle();
    this.bindLoginMerge();
    var tries = 0;
    (function waitFrame() {
      var c = self.iframe ? { mode: 'exact', frame: self.iframe } : self.classify();
      if (c && c.mode === 'exact') {
        // 同源 dplayer/videojs：精确进度 + 续播
        self.iframe = c.frame;
        self.loadAndOfferResume();
        return;
      }
      if (c && c.mode === 'foreign') {
        self.startEstimate();
        return;
      }
      // 预加载广告页（MacPlayerConfig.second 秒）/ 播放密码门期间继续等
      if (++tries < 600) win.setTimeout(waitFrame, 1000);
    })();
  };

  function boot() {
    if (win.MacPlayProgress) return;
    var cfg = readConfig();
    if (!cfg || !cfg.enabled || !(Number(cfg.vodId) > 0)) return;
    var p = new Progress(cfg);
    win.MacPlayProgress = p;
    p.start();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
