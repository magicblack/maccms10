/**
 * 播放页线路自动切换（父页脚本）。
 *
 * postMessage 协议（与 static/player/dplayer.html、videojs.html 对齐）：
 * - 子 -> 父：{ source:'mac-player', type:'play_fail', reason:'error'|'timeout'|'script_load' }
 * - 子 -> 父：{ source:'mac-player', type:'play_ok' }
 * - 父 -> 子：{ source:'mac-play-parent', type:'failover_init', canplayTimeoutMs:number }
 *
 * 另：
 * - 捕获父页加载 /static/player/*.js 失败，reason=script_load
 * - 捕获解析站/外链 iframe：error、超时未 load、Performance 显式 status>=400
 *   （跨域 opaque 读不到状态码时不误判，宁可漏报）
 *
 * 线路列表优先读 #vod-play-failover-config，否则从 #bofy 播放源 Tab DOM 解析。
 * 失败上报：POST /api.php/ulog/report_fail + localStorage 计数兜底。
 */
(function (window, document) {
  'use strict';

  var MSG_CHILD = 'mac-player';
  var MSG_PARENT = 'mac-play-parent';
  var LS_FAIL_KEY = 'mac_vod_source_fail_v1';
  var SS_CHAIN_KEY = 'mac_play_failover_chain';
  var SS_TOAST_KEY = 'mac_play_failover_toast';
  var DEFAULT_CANPLAY_TIMEOUT_MS = 15000;
  // 外链播放页（资源站云播/分享页、解析接口页）跨域，拿不到播放状态，只能看 iframe load。
  // 这类页面常从第三方 CDN 同步加载脚本，视频早已开播而 load 要 30s+（新浪 play.xluuss.com 实测约 35s），
  // 沿用 15s 会在播放中途误判失败并跳线，所以单独给一个宽松上限，只兜底“页面一直打不开”。
  var EXTERNAL_IFRAME_LOAD_TIMEOUT_MS = 60000;
  var FAIL_COOLDOWN_MS = 2500;

  function readConfig() {
    var el = document.getElementById('vod-play-failover-config');
    if (!el || !el.textContent) return null;
    try {
      return JSON.parse(el.textContent);
    } catch (e) {
      return null;
    }
  }

  function apiUrl(base, path) {
    var b = String(base || '').replace(/\/+$/, '');
    var p = String(path || '').replace(/^\/+/, '');
    return b + '/' + p;
  }

  function i18n(key, fallback, vars) {
    var tpl = fallback;
    if (typeof jQuery !== 'undefined' && jQuery.i18n && jQuery.i18n.map && jQuery.i18n.map[key]) {
      tpl = jQuery.i18n.map[key];
    }
    if (vars) {
      Object.keys(vars).forEach(function (k) {
        tpl = tpl.replace(new RegExp('\\{' + k + '\\}', 'g'), vars[k]);
      });
    }
    return tpl;
  }

  function parseSidFromHref(href, fallbackSid) {
    var s = String(href || '');
    if (!s || s.indexOf('javascript:') === 0) return parseInt(fallbackSid, 10) || 0;
    var m = s.match(/\/sid\/(\d+)/i);
    if (m) return parseInt(m[1], 10) || 0;
    m = s.match(/\/vod\/play\/[^/]+\/(\d+)\/(\d+)/i);
    if (m) return parseInt(m[2], 10) || 0;
    return parseInt(fallbackSid, 10) || 0;
  }

  function collectSourcesFromDom(cfg) {
    var out = [];
    var tabs = document.querySelectorAll('#bofy .title_nav li.tab-play');
    for (var i = 0; i < tabs.length; i++) {
      var a = tabs[i].querySelector('a');
      if (!a) continue;
      var href = a.getAttribute('href') || '';
      var sid = parseSidFromHref(href, cfg.sid);
      if (!sid) continue;
      var show = String(a.textContent || '')
        .replace(/\s+/g, ' ')
        .trim();
      show = show.replace(/^[^\u4e00-\u9fa5a-zA-Z0-9]+/, '').trim();
      out.push({ sid: sid, show: show || '线路' + sid, href: href });
    }
    return out;
  }

  function normalizeSources(cfg) {
    if (cfg.sources && cfg.sources.length) {
      return cfg.sources
        .map(function (row) {
          return {
            sid: parseInt(row.sid, 10) || 0,
            show: String(row.show || row.name || '').trim() || '线路' + row.sid,
            href: String(row.href || row.url || '').trim(),
          };
        })
        .filter(function (row) {
          return row.sid > 0;
        });
    }
    return collectSourcesFromDom(cfg);
  }

  function readChain(cfg) {
    try {
      var raw = window.sessionStorage.getItem(SS_CHAIN_KEY);
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || parseInt(data.vodId, 10) !== parseInt(cfg.vodId, 10)) return null;
      if (parseInt(data.nid, 10) !== parseInt(cfg.nid, 10)) return null;
      if (!Array.isArray(data.tried)) data.tried = [];
      return data;
    } catch (e) {
      return null;
    }
  }

  function writeChain(chain) {
    try {
      window.sessionStorage.setItem(SS_CHAIN_KEY, JSON.stringify(chain));
    } catch (e) {}
  }

  function clearChain() {
    try {
      window.sessionStorage.removeItem(SS_CHAIN_KEY);
    } catch (e) {}
  }

  function readPendingToast() {
    try {
      var raw = window.sessionStorage.getItem(SS_TOAST_KEY);
      if (!raw) return null;
      window.sessionStorage.removeItem(SS_TOAST_KEY);
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function queueToast(fromShow, toShow) {
    try {
      window.sessionStorage.setItem(
        SS_TOAST_KEY,
        JSON.stringify({ from: fromShow, to: toShow, at: Date.now() })
      );
    } catch (e) {}
  }

  function bumpLocalFail(cfg, sid, reason) {
    try {
      var map = JSON.parse(window.localStorage.getItem(LS_FAIL_KEY) || '{}');
      if (!map || typeof map !== 'object') map = {};
      var key =
        String(cfg.vodId) +
        '_' +
        String(sid) +
        '_' +
        String(cfg.nid);
      var row = map[key] || { count: 0 };
      row.count = (parseInt(row.count, 10) || 0) + 1;
      row.reason = reason || 'error';
      row.updated = Date.now();
      row.show = row.show || '';
      map[key] = row;
      window.localStorage.setItem(LS_FAIL_KEY, JSON.stringify(map));
    } catch (e) {}
  }

  function reportSourceFail(cfg, sid, show, reason, switched) {
    bumpLocalFail(cfg, sid, reason);
    var url = apiUrl(cfg.apiBase, 'ulog/report_fail');
    var body =
      'vod_id=' +
      encodeURIComponent(cfg.vodId) +
      '&sid=' +
      encodeURIComponent(sid) +
      '&nid=' +
      encodeURIComponent(cfg.nid) +
      '&play_from=' +
      encodeURIComponent(cfg.playFrom || show || '') +
      '&vod_name=' +
      encodeURIComponent(cfg.vodName || '') +
      '&switched=' +
      encodeURIComponent(switched ? 1 : 0);
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body,
      keepalive: true,
    }).catch(function () {});
  }

  function showToastMessage(text) {
    if (window.MacTheme && typeof window.MacTheme.notify === 'function') {
      window.MacTheme.notify(text);
      return;
    }
    if (typeof window.MAC !== 'undefined' && typeof window.MAC.msg === 'function') {
      window.MAC.msg(text, 3600);
      return;
    }
    var wrap = document.querySelector('.player_video');
    if (!wrap) {
      return;
    }
    var el = document.createElement('div');
    el.className = 'mac-play-failover-toast is-visible';
    el.setAttribute('role', 'status');
    el.textContent = text;
    wrap.appendChild(el);
    window.setTimeout(function () {
      el.classList.remove('is-visible');
      window.setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
      }, 260);
    }, 4200);
  }

  function MacPlayFailover(cfg) {
    this.cfg = cfg;
    this.sources = normalizeSources(cfg);
    this.iframe = null;
    this.playerReady = false;
    this.switching = false;
    this.lastFailAt = 0;
    this._boundMsg = this.onMessage.bind(this);
  }

  MacPlayFailover.prototype.findCurrentSource = function () {
    var sid = parseInt(this.cfg.sid, 10) || 0;
    for (var i = 0; i < this.sources.length; i++) {
      if (this.sources[i].sid === sid) return this.sources[i];
    }
    return { sid: sid, show: '线路' + sid, href: '' };
  };

  MacPlayFailover.prototype.isBridgePlayerSrc = function (src) {
    src = String(src || '');
    return src.indexOf('dplayer.html') !== -1 || src.indexOf('videojs.html') !== -1;
  };

  MacPlayFailover.prototype.findPlayerIframe = function () {
    var wrap = document.querySelector('.player_video');
    if (!wrap) return null;
    var frames = wrap.querySelectorAll('iframe');
    var external = null;
    for (var i = 0; i < frames.length; i++) {
      var f = frames[i];
      var id = f.id || '';
      if (id === 'buffer' || id === 'install') continue;
      var src = f.getAttribute('src') || '';
      if (!src || src.indexOf('prestrain') !== -1) continue;
      if (this.isBridgePlayerSrc(src)) return f;
      if (!external && (/^https?:/i.test(src) || src.indexOf('//') === 0 || src.indexOf('/static/player/') !== -1)) {
        external = f;
      }
    }
    return external;
  };

  MacPlayFailover.prototype.isPlayerScriptSrc = function (src) {
    return String(src || '').indexOf('/static/player/') !== -1;
  };

  MacPlayFailover.prototype.bindScriptLoadFail = function () {
    var self = this;
    function onScriptError(ev) {
      var t = ev && ev.target;
      if (!t || !t.tagName || String(t.tagName).toUpperCase() !== 'SCRIPT') return;
      if (!self.isPlayerScriptSrc(t.src || t.getAttribute('src'))) return;
      self.handlePlayFail('script_load');
    }
    window.addEventListener('error', onScriptError, true);
    if (window.__macPlayerScriptFail && window.__macPlayerScriptFail.src) {
      this.handlePlayFail('script_load');
    }
  };

  MacPlayFailover.prototype.findIframeResourceEntry = function (src) {
    src = String(src || '');
    if (!src || typeof performance === 'undefined' || !performance.getEntriesByType) {
      return null;
    }
    var base = src.split('#')[0];
    var entries = performance.getEntriesByType('resource');
    var best = null;
    for (var i = 0; i < entries.length; i++) {
      var e = entries[i];
      if (e.initiatorType && e.initiatorType !== 'iframe') continue;
      var name = String(e.name || '');
      if (name !== src && name !== base && name.indexOf(base) !== 0) continue;
      if (!best || e.responseEnd >= best.responseEnd) best = e;
    }
    return best;
  };

  MacPlayFailover.prototype.isExternalIframeFailed = function (iframe) {
    var src = (iframe && iframe.getAttribute('src')) || '';
    var entry = this.findIframeResourceEntry(src);
    if (!entry) return false;
    return typeof entry.responseStatus === 'number' && entry.responseStatus >= 400;
  };

  MacPlayFailover.prototype.watchExternalPlayerIframe = function (iframe) {
    var self = this;
    if (!iframe || iframe.__macFailoverWatch) return;
    iframe.__macFailoverWatch = true;

    var timeoutMs = Math.max(
      parseInt(this.cfg.canplayTimeoutMs, 10) || DEFAULT_CANPLAY_TIMEOUT_MS,
      EXTERNAL_IFRAME_LOAD_TIMEOUT_MS
    );
    var loaded = false;

    // 本脚本是异步加载、轮询找 iframe 的；解析接口页（跨域）可能在挂监听之前就已 load 完，
    // 之后不会再触发 load，旧逻辑会在超时后误判失败并跳到别的线路。iframe 的 resource timing
    // 条目在其 load 完成时才写入，所以有条目即视为已加载，只看状态码。
    var doneEntry = this.findIframeResourceEntry(iframe.getAttribute('src') || '');
    if (doneEntry) {
      if (this.isExternalIframeFailed(iframe)) {
        this.handlePlayFail('timeout');
      }
      return;
    }

    var timer = window.setTimeout(function () {
      if (loaded || self.switching) return;
      self.handlePlayFail('timeout');
    }, timeoutMs);

    iframe.addEventListener('error', function () {
      window.clearTimeout(timer);
      self.handlePlayFail('error');
    });

    iframe.addEventListener('load', function () {
      loaded = true;
      window.clearTimeout(timer);
      window.setTimeout(function () {
        if (self.switching) return;
        if (self.isExternalIframeFailed(iframe)) {
          self.handlePlayFail('timeout');
        }
      }, 50);
    });
  };

  MacPlayFailover.prototype.postToIframe = function (payload) {
    if (!this.iframe || !this.iframe.contentWindow) return;
    try {
      this.iframe.contentWindow.postMessage(
        Object.assign({ source: MSG_PARENT }, payload),
        window.location.origin
      );
    } catch (e) {}
  };

  MacPlayFailover.prototype.sendInit = function () {
    this.postToIframe({
      type: 'failover_init',
      canplayTimeoutMs: parseInt(this.cfg.canplayTimeoutMs, 10) || DEFAULT_CANPLAY_TIMEOUT_MS,
    });
  };

  MacPlayFailover.prototype.onMessage = function (ev) {
    var data = ev.data;
    if (!data || data.source !== MSG_CHILD) return;
    try {
      if (ev.origin !== window.location.origin) return;
    } catch (e) {
      return;
    }
    if (!this.iframe || ev.source !== this.iframe.contentWindow) {
      var wrap = document.querySelector('.player_video');
      var frames = wrap ? wrap.querySelectorAll('iframe') : [];
      var matched = null;
      for (var i = 0; i < frames.length; i++) {
        if (frames[i].contentWindow === ev.source) {
          matched = frames[i];
          break;
        }
      }
      if (!matched) return;
      this.iframe = matched;
    }
    if (data.type === 'ready') {
      this.playerReady = true;
      this.sendInit();
      return;
    }
    if (data.type === 'play_ok') {
      clearChain();
      return;
    }
    if (data.type === 'play_fail') {
      this.handlePlayFail(String(data.reason || 'error'));
    }
  };

  MacPlayFailover.prototype.getNextSource = function (tried) {
    var triedMap = {};
    for (var i = 0; i < tried.length; i++) triedMap[parseInt(tried[i], 10)] = 1;
    for (var j = 0; j < this.sources.length; j++) {
      var row = this.sources[j];
      if (!triedMap[row.sid]) return row;
    }
    return null;
  };

  MacPlayFailover.prototype.handlePlayFail = function (reason) {
    var self = this;
    if (this.switching) return;
    var now = Date.now();
    if (now - this.lastFailAt < FAIL_COOLDOWN_MS) return;
    this.lastFailAt = now;

    var cur = this.findCurrentSource();
    var chain = readChain(this.cfg) || {
      vodId: this.cfg.vodId,
      nid: this.cfg.nid,
      tried: [],
    };
    if (chain.tried.indexOf(cur.sid) === -1) {
      chain.tried.push(cur.sid);
    }

    var next = this.getNextSource(chain.tried);
    var willSwitch =
      this.sources.length >= 2 &&
      next &&
      next.href &&
      next.href.indexOf('javascript:') !== 0;
    reportSourceFail(this.cfg, cur.sid, cur.show, reason, willSwitch);

    if (!willSwitch) {
      clearChain();
      showToastMessage(
        this.sources.length < 2
          ? i18n('string_play_failover_single_failed', '当前线路无法播放，请稍后再试')
          : i18n('string_play_failover_all_failed', '所有线路均无法播放，请稍后再试或更换集数')
      );
      return;
    }

    writeChain(chain);
    queueToast(cur.show, next.show);
    this.switching = true;
    window.setTimeout(function () {
      window.location.assign(next.href);
    }, 120);
  };

  MacPlayFailover.prototype.showArrivalToast = function () {
    var pending = readPendingToast();
    if (!pending || !pending.from || !pending.to) return;
    showToastMessage(
      i18n('string_play_failover_switched', '线路 {from} 失效，已切换到线路 {to}', {
        from: pending.from,
        to: pending.to,
      })
    );
  };

  MacPlayFailover.prototype.start = function () {
    var self = this;
    this.showArrivalToast();
    this.bindScriptLoadFail();
    window.addEventListener('message', this._boundMsg);

    var polls = 0;
    function pollIframe() {
      self.iframe = self.findPlayerIframe();
      if (!self.iframe) {
        polls += 1;
        if (polls < 40) window.setTimeout(pollIframe, 400);
        return;
      }
      var src = self.iframe.getAttribute('src') || '';
      if (self.isBridgePlayerSrc(src)) {
        if (self.playerReady) self.sendInit();
        return;
      }
      self.watchExternalPlayerIframe(self.iframe);
    }
    pollIframe();
  };

  function boot() {
    var cfg = readConfig();
    if (!cfg || !cfg.enabled) return;
    var inst = new MacPlayFailover(cfg);
    inst.start();
    window.MacPlayFailover = inst;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
