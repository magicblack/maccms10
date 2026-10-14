/**
 * twigspike — 支付二维码渲染（依赖 jQuery + stem-jquery.qrcode.js，仅 path_tpl）
 */
(function (win) {
    'use strict';

    var $ = win.jQuery;

    function qrcodeScriptPath() {
        var file = 'stem-jquery.qrcode.js';
        if (win.maccms && win.maccms.path_tpl) {
            return String(win.maccms.path_tpl).replace(/\/+$/, '') + '/asset/js/' + file;
        }
        return '';
    }

    function loadScript(src, cb) {
        var el = document.createElement('script');
        el.src = src;
        el.async = true;
        el.onload = function () { cb(null); };
        el.onerror = function () { cb(new Error('load fail')); };
        document.head.appendChild(el);
    }

    function ensureQrcodePlugin(cb) {
        if (!$ || !$.fn) {
            cb(new Error('no jquery'));
            return;
        }
        if (typeof $.fn.qrcode === 'function') {
            cb(null);
            return;
        }
        var src = qrcodeScriptPath();
        if (!src) {
            cb(new Error('no qrcode script path'));
            return;
        }
        loadScript(src, cb);
    }

    win.macRenderPaymentQrcode = function (box, text, opts, cb) {
        if (typeof opts === 'function') {
            cb = opts;
            opts = {};
        }
        opts = opts || {};
        cb = cb || function () {};

        var $box = box;
        if (box && box.nodeType === 1 && $) {
            $box = $(box);
        }
        if (!$ || !$box || !$box.length) {
            cb(new Error('bad args'));
            return;
        }

        var payload = String(text == null ? '' : text).trim();
        if (!payload) {
            cb(new Error('bad args'));
            return;
        }

        var w = Number(opts.width) > 0 ? Number(opts.width) : 200;
        var h = Number(opts.height) > 0 ? Number(opts.height) : w;

        $box.empty();
        ensureQrcodePlugin(function (err) {
            if (err || typeof $.fn.qrcode !== 'function') {
                cb(err || new Error('qrcode plugin'));
                return;
            }
            try {
                $box.qrcode({ width: w, height: h, text: payload });
                cb(null);
            } catch (e) {
                cb(e);
            }
        });
    };
})(window);
