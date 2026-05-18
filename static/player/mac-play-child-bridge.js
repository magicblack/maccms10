/*
 * @Description: 
 * @Date: 2026-04-17 13:51:20
 * @LastEditTime: 2026-05-15 08:15:09
 */
/**
 * MacCMS 播放 iframe（dplayer / videojs）内：弹幕层 + postMessage 与父页通信。
 * 依赖：先设置 window.MacPlayChildConfig = { kind, getContainer, getTime, onSeek, onTime }
 */
(function () {
  'use strict';
  var C = window.MacPlayChildConfig;
  if (!C || typeof C.getContainer !== 'function' || typeof C.getTime !== 'function') {
    return;
  }

  var MSG_CHILD = 'mac-player';
  var MSG_PARENT = 'mac-play-parent';

  var apiBase = '';
  var vodId = 0;
  var sid = 0;
  var nid = 0;
  var dmIdStr = '';
  var dmList = [];
  var dmIdx = 0;
  var dmOn = true;
  var stage = null;
  var lastTimePost = 0;
  var seenSpawn = {};
  var loadDmAbort = null;
  var trustedOrigin = null;

  function postToParent(payload) {
    try {
      parent.postMessage(
        Object.assign({ source: MSG_CHILD }, payload),
        location.origin
      );
    } catch (e) {}
  }

  function throttleTime() {
    var t = C.getTime();
    if (typeof t !== 'number' || isNaN(t)) return;
    var now = Date.now();
    if (now - lastTimePost < 220) return;
    lastTimePost = now;
    postToParent({ type: 'time', t: t });
  }

  function ensureStage() {
    if (stage) return stage;
    var host = C.getContainer();
    if (!host) return null;
    var el = document.createElement('div');
    el.id = 'macDmStage';
    el.setAttribute('aria-hidden', 'true');
    el.style.cssText =
      'position:absolute;left:0;top:0;right:0;bottom:0;overflow:hidden;pointer-events:none;z-index:93;';
    var pos = window.getComputedStyle(host).position;
    if (pos === 'static') {
      host.style.position = 'relative';
    }
    host.appendChild(el);
    stage = el;
    return stage;
  }

  function clearStage() {
    if (stage) stage.innerHTML = '';
    seenSpawn = {};
    dmIdx = 0;
  }

  function resetIndexForTime(t) {
    dmIdx = 0;
    for (var i = 0; i < dmList.length; i++) {
      if (dmList[i][0] > t - 0.15) {
        dmIdx = i;
        break;
      }
      dmIdx = i + 1;
    }
  }

  function removeLine(line) {
    if (line && line.parentNode) line.parentNode.removeChild(line);
  }

  function spawnRow(row) {
    var st = ensureStage();
    if (!st || !dmOn) return;
    var t = row[0];
    var color = row[2] || '#fff';
    if (!/^#[0-9a-fA-F]{3,8}$/.test(color)) color = '#fff';
    var text = row[4] || '';
    var key = t + '|' + text;
    if (seenSpawn[key]) return;
    seenSpawn[key] = 1;

    var line = document.createElement('div');
    line.textContent = text;
    var topPx = 8 + Math.floor(Math.random() * 5) * 22;
    line.style.cssText =
      'position:absolute;left:0;top:' +
      topPx +
      'px;white-space:nowrap;transform:translateX(0);color:' +
      color +
      ';font-size:14px;line-height:22px;text-shadow:0 0 2px #000;will-change:transform;pointer-events:none;';
    st.appendChild(line);

    function scheduleRemove(ms) {
      window.setTimeout(function () {
        removeLine(line);
      }, ms + 500);
    }

    function runMotion() {
      if (!line.parentNode) return;
      var stageW = st.clientWidth || st.offsetWidth || 0;
      if (stageW < 4) {
        stageW = Math.max(320, window.innerWidth || 640);
      }
      var textW = line.offsetWidth || 0;
      if (textW < 4) {
        textW = Math.max(40, (text && text.length ? text.length : 4) * 14);
      }
      var travel = stageW + textW + 32;
      var durationMs = Math.min(22000, Math.max(6500, Math.round(travel * 14)));

      if (typeof line.animate === 'function') {
        var startX = stageW + 8;
        line.style.transform = 'translateX(' + startX + 'px)';
        try {
          var anim = line.animate(
            [
              { transform: 'translateX(' + startX + 'px)' },
              { transform: 'translateX(' + (-textW - 16) + 'px)' },
            ],
            { duration: durationMs, easing: 'linear', fill: 'forwards' }
          );
          anim.onfinish = function () {
            removeLine(line);
          };
          scheduleRemove(durationMs);
        } catch (e) {
          line.style.transform = '';
          line.style.left = '100%';
          line.style.animation =
            'macDmMarquee ' + Math.round(durationMs / 1000) + 's linear forwards';
          scheduleRemove(durationMs);
        }
      } else {
        line.style.left = '100%';
        line.style.transform = 'translateX(0)';
        line.style.animation =
          'macDmMarquee ' + Math.round(durationMs / 1000) + 's linear forwards';
        scheduleRemove(durationMs);
      }
    }

    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(runMotion);
    } else {
      runMotion();
    }
  }

  function injectKeyframes() {
    if (document.getElementById('macDmKeyframes')) return;
    var s = document.createElement('style');
    s.id = 'macDmKeyframes';
    // 回退：百分比 translate 相对自身宽度，需加上父宽；父用 100vw 近似舞台宽度
    s.textContent =
      '@keyframes macDmMarquee{from{transform:translateX(0);}to{transform:translateX(calc(-100% - 100vw));}}';
    document.head.appendChild(s);
  }

  function onTimeUpdate() {
    throttleTime();
    var cur = C.getTime();
    if (typeof cur !== 'number' || isNaN(cur)) return;
    while (dmIdx < dmList.length && dmList[dmIdx][0] <= cur + 0.12) {
      spawnRow(dmList[dmIdx]);
      dmIdx++;
    }
  }

  function jsonOkCode(j) {
    return j && (j.code === 0 || j.code === '0' || Number(j.code) === 0);
  }

  function loadDanmaku() {
    if (!apiBase || !dmIdStr) return;
    if (loadDmAbort) {
      try {
        loadDmAbort.abort();
      } catch (e) {}
    }
    loadDmAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var myDmId = dmIdStr;
    var url = apiBase.replace(/\/+$/, '') + '/danmaku/dplayer?id=' + encodeURIComponent(dmIdStr);
    var fetchOpts = { credentials: 'same-origin' };
    if (loadDmAbort) fetchOpts.signal = loadDmAbort.signal;
    fetch(url, fetchOpts)
      .then(function (r) {
        return r.json();
      })
      .then(function (j) {
        if (myDmId !== dmIdStr) return;
        dmList = jsonOkCode(j) && j.data && Array.isArray(j.data) ? j.data : [];
        dmIdx = 0;
        seenSpawn = {};
        resetIndexForTime(C.getTime());
      })
      .catch(function (e) {
        if (e && (e.name === 'AbortError' || e.code === 20)) return;
        if (myDmId !== dmIdStr) return;
        dmList = [];
      });
  }

  function setDmVisible(on) {
    dmOn = !!on;
    if (stage) {
      stage.style.visibility = dmOn ? 'visible' : 'hidden';
    }
  }

  function sendDanmaku(text, color, type0) {
    var t = C.getTime();
    if (typeof t !== 'number' || isNaN(t)) t = 0;
    var ty = type0 === undefined || type0 === null ? 0 : type0;
    var body =
      'id=' +
      encodeURIComponent(dmIdStr) +
      '&time=' +
      encodeURIComponent(String(t)) +
      '&text=' +
      encodeURIComponent(text) +
      '&type=' +
      encodeURIComponent(String(ty)) +
      '&color=' +
      encodeURIComponent(color || '#FFFFFF');
    fetch(apiBase.replace(/\/+$/, '') + '/danmaku/dplayer', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body,
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (j) {
        var ok = jsonOkCode(j);
        postToParent({ type: 'danmaku_sent', ok: !!ok, msg: j && j.msg });
        if (ok) {
          dmList.push([t, 0, color || '#FFFFFF', '', text]);
        }
      })
      .catch(function () {
        postToParent({ type: 'danmaku_sent', ok: false, msg: 'network' });
      });
  }

  function onMessage(ev) {
    var d = ev.data;
    if (!d || d.source !== MSG_PARENT) return;

    if (!trustedOrigin) {
      if (d.type !== 'init') return;
      try {
        var apiHost = new URL(d.apiBase || '').origin;
        if (apiHost && apiHost !== ev.origin) return;
      } catch (e) {}
      trustedOrigin = ev.origin;
    } else if (ev.origin !== trustedOrigin) {
      return;
    }
    if (d.type === 'init') {
      apiBase = d.apiBase || '';
      vodId = parseInt(d.vodId, 10) || 0;
      sid = parseInt(d.sid, 10) || 0;
      nid = parseInt(d.nid, 10) || 0;
      dmIdStr = vodId + '-' + sid + '-' + nid;
      injectKeyframes();
      ensureStage();
      loadDanmaku();
    }
    if (d.type === 'danmaku_toggle') {
      setDmVisible(d.on !== false);
    }
    if (d.type === 'danmaku_submit' && d.text) {
      sendDanmaku(String(d.text), d.color, d.dmType);
    }
  }

  window.addEventListener('message', onMessage);

  if (typeof C.onTime === 'function') {
    C.onTime(onTimeUpdate);
  }
  if (typeof C.onSeek === 'function') {
    C.onSeek(function () {
      clearStage();
      resetIndexForTime(C.getTime());
    });
  }

  postToParent({ type: 'ready', player: C.kind || 'player' });
})();
