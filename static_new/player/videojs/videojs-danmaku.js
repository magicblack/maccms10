/**
 * MacCMS：Video.js 弹幕插件（基于 npm comment-core-library + Video.js 7 registerPlugin）。
 * 与 SunnyLi/videojs-danmaku 同类能力：覆盖层 + 时间轴；数据源为本站 api.php/danmaku/dplayer。
 * 依赖：先加载 video.min.js、CommentCoreLibrary.min.js，再在 player.ready 中调用 player.danmaku()。
 */
(function (window, videojs) {
	'use strict';
	if (!videojs || typeof videojs.registerPlugin !== 'function') {
		return;
	}

	var MSG_CHILD = 'mac-player';
	var MSG_PARENT = 'mac-play-parent';

	function postToParent(payload) {
		try {
			parent.postMessage(Object.assign({ source: MSG_CHILD }, payload), location.origin);
		} catch (e) {}
	}

	function jsonOkCode(j) {
		return j && (j.code === 0 || j.code === '0' || Number(j.code) === 0);
	}

	function colorToInt(c) {
		if (c === undefined || c === null || c === '') return 0xffffff;
		var s = String(c).trim();
		if (s.indexOf('rgb') === 0) return 0xffffff;
		if (s.charAt(0) === '#') s = s.slice(1);
		if (s.length === 3) {
			s = s
				.split('')
				.map(function (ch) {
					return ch + ch;
				})
				.join('');
		}
		var n = parseInt(s, 16);
		return isNaN(n) ? 0xffffff : n;
	}

	function mapMode(t) {
		var x = parseInt(t, 10);
		if (x === 1) return 5;
		if (x === 2) return 4;
		return 1;
	}

	function rowToComment(row) {
		return {
			stime: Math.round(Number(row[0]) * 1000),
			mode: mapMode(row[1]),
			text: String(row[4] != null ? row[4] : ''),
			color: colorToInt(row[2]),
		};
	}

	videojs.registerPlugin('danmaku', function maccmsVideojsDanmaku() {
		var player = this;
		if (player._maccmsDanmakuPlugin) {
			return;
		}
		player._maccmsDanmakuPlugin = true;

		if (typeof window.CommentManager !== 'function') {
			postToParent({ type: 'ready', player: 'videojs' });
			return;
		}

		var abp = document.createElement('div');
		abp.className = 'abp vjs-mac-danmaku-abp';
		abp.setAttribute('aria-hidden', 'true');
		abp.style.cssText =
			'position:absolute;left:0;top:0;right:0;bottom:0;pointer-events:none;z-index:10;';
		var overlay = document.createElement('div');
		overlay.className = 'container';
		abp.appendChild(overlay);

		var rootEl = player.el();
		var pos = window.getComputedStyle(rootEl).position;
		if (pos === 'static') {
			rootEl.style.position = 'relative';
		}
		// 必须插在视频层之后，否则 .vjs-tech 会盖住弹幕（同层后绘制在上层）
		var techEl = rootEl.querySelector('.vjs-tech') || rootEl.querySelector('video');
		if (techEl && techEl.parentNode === rootEl) {
			if (techEl.nextSibling) {
				rootEl.insertBefore(abp, techEl.nextSibling);
			} else {
				rootEl.appendChild(abp);
			}
		} else {
			rootEl.appendChild(abp);
		}
		abp.style.zIndex = '3';

		var cm = new window.CommentManager(overlay);
		cm.init('css');
		cm.options.scroll.scale = 1.75;
		cm.options.global.scale = 1.75;

		var state = {
			apiBase: '',
			dmIdStr: '',
			loadAbort: null,
			dmOn: true,
			running: false,
		};

		function resizeCm() {
			window.setTimeout(function () {
				try {
					cm.setBounds();
				} catch (e0) {}
			}, 50);
		}

		function normalizeApiBase(base) {
			var b = String(base || '').replace(/\/+$/, '');
			if (b && b.indexOf('api.php') !== -1) {
				return b;
			}
			try {
				if (parent.maccms) {
					var p = parent.maccms.path;
					if (p == null || String(p) === '') {
						p = parent.maccms.path_assets;
					}
					var root = String(p != null ? p : '').replace(/\/+$/, '');
					if (root === '/' || root === '.') {
						root = '';
					}
					var guess = root + '/api.php';
					if (guess.indexOf('api.php') !== -1) {
						return guess.replace(/\/+$/, '');
					}
				}
			} catch (eBase) {}
			return b;
		}

		function fetchAndLoad() {
			state.apiBase = normalizeApiBase(state.apiBase);
			if (!state.apiBase || !state.dmIdStr) return;
			if (state.loadAbort) {
				try {
					state.loadAbort.abort();
				} catch (e1) {}
			}
			state.loadAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var myId = state.dmIdStr;
			var url =
				state.apiBase.replace(/\/+$/, '') + '/danmaku/dplayer?id=' + encodeURIComponent(state.dmIdStr);
			var opts = { credentials: 'same-origin' };
			if (state.loadAbort) opts.signal = state.loadAbort.signal;
			fetch(url, opts)
				.then(function (r) {
					return r.json();
				})
				.then(function (j) {
					if (myId !== state.dmIdStr) return;
					var rows = jsonOkCode(j) && j.data && Array.isArray(j.data) ? j.data : [];
					var timeline = rows.map(rowToComment);
					var wasRunning = state.running;
					try {
						cm.stop();
					} catch (e2) {}
					cm.clear();
					cm.load(timeline);
					try {
						cm.seek(Math.floor(player.currentTime() * 1000));
					} catch (e3) {}
					if (wasRunning && state.dmOn) {
						try {
							cm.start();
						} catch (e4) {}
					} else if (!player.paused() && state.dmOn) {
						state.running = true;
						try {
							cm.start();
						} catch (e5) {}
					}
					resizeCm();
				})
				.catch(function (err) {
					if (err && (err.name === 'AbortError' || err.code === 20)) return;
				});
		}

		function setVisible(on) {
			state.dmOn = !!on;
			abp.style.visibility = state.dmOn ? 'visible' : 'hidden';
			if (!state.dmOn) {
				try {
					cm.stop();
				} catch (e6) {}
			} else if (state.running) {
				try {
					cm.start();
				} catch (e7) {}
			}
		}

		var lastPost = 0;
		function postTimeThrottle(t) {
			var now = Date.now();
			if (now - lastPost < 220) return;
			lastPost = now;
			postToParent({ type: 'time', t: t });
		}

		function onPlay() {
			state.running = true;
			if (state.dmOn) {
				try {
					cm.start();
				} catch (e8) {}
			}
		}

		function onPause() {
			state.running = false;
			try {
				cm.stop();
			} catch (e9) {}
		}

		function onTimeUpdate() {
			var t = player.currentTime();
			if (typeof t !== 'number' || isNaN(t)) return;
			postTimeThrottle(t);
			if (!state.dmOn) return;
			try {
				cm.time(t * 1000);
			} catch (e10) {}
		}

		function onSeeked() {
			try {
				cm.clear();
				cm.seek(Math.floor(player.currentTime() * 1000));
			} catch (e11) {}
		}

		function sendDanmaku(text, color, type0) {
			var t = player.currentTime();
			if (typeof t !== 'number' || isNaN(t)) t = 0;
			var ty = type0 === undefined || type0 === null ? 0 : type0;
			var body =
				'id=' +
				encodeURIComponent(state.dmIdStr) +
				'&time=' +
				encodeURIComponent(String(t)) +
				'&text=' +
				encodeURIComponent(text) +
				'&type=' +
				encodeURIComponent(String(ty)) +
				'&color=' +
				encodeURIComponent(color || '#FFFFFF');
			fetch(state.apiBase.replace(/\/+$/, '') + '/danmaku/dplayer', {
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
					if (ok && state.dmOn) {
						cm.send({
							stime: Math.round(t * 1000),
							mode: mapMode(ty),
							text: String(text),
							color: colorToInt(color),
						});
					}
				})
				.catch(function () {
					postToParent({ type: 'danmaku_sent', ok: false, msg: 'network' });
				});
		}

		function onWinMessage(ev) {
			if (typeof player.isDisposed === 'function' && player.isDisposed()) {
				return;
			}
			try {
				if (ev.origin !== location.origin) return;
			} catch (e13) {
				return;
			}
			var d = ev.data;
			if (!d || d.source !== MSG_PARENT) return;
			if (d.type === 'init') {
				state.apiBase = normalizeApiBase(d.apiBase || '');
				var vid = parseInt(d.vodId, 10) || 0;
				var sid = parseInt(d.sid, 10) || 0;
				var nid = parseInt(d.nid, 10) || 0;
				state.dmIdStr = vid + '-' + sid + '-' + nid;
				fetchAndLoad();
				return;
			}
			if (d.type === 'danmaku_toggle') {
				setVisible(d.on !== false);
				return;
			}
			if (d.type === 'danmaku_submit' && d.text) {
				sendDanmaku(String(d.text), d.color, d.dmType);
			}
		}

		player.on('play', onPlay);
		player.on('pause', onPause);
		player.on('timeupdate', onTimeUpdate);
		player.on('seeked', onSeeked);
		player.on('resize', resizeCm);
		player.on('fullscreenchange', resizeCm);

		window.addEventListener('message', onWinMessage);

		player.on('dispose', function () {
			window.removeEventListener('message', onWinMessage);
			try {
				cm.stop();
			} catch (e14) {}
		});

		postToParent({ type: 'ready', player: 'videojs' });
	});
})(window, window.videojs);
