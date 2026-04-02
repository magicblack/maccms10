(function (w, $) {
    'use strict';

    function toInt(v) {
        var n = parseInt(v, 10);
        return isNaN(n) ? 0 : n;
    }

    function pickFirstSelector(list) {
        for (var i = 0; i < list.length; i++) {
            var $el = $(list[i]).first();
            if ($el.length) return $el;
        }
        return $();
    }

    function normalizeSeoResponse(res) {
        if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch (e) {}
        }
        if (!res || typeof res !== 'object') return { code: 0, msg: '', data: {} };

        var code = toInt(res.code);
        var msg = (res.msg != null ? String(res.msg) : '');
        var seo = res.data || {};
        if (seo && typeof seo === 'object' && seo.data && typeof seo.data === 'object') {
            seo = seo.data;
        }
        if (!seo || typeof seo !== 'object') seo = {};
        return { code: code, msg: msg, data: seo };
    }

    function setBadgeText(status, lang, badgeSelector) {
        var $badge = $(badgeSelector || '#seo_ai_status_badge');
        if (!$badge.length) return;

        $badge.removeClass('layui-bg-green layui-bg-orange layui-bg-gray');
        if (toInt(status) === 1) {
            $badge.addClass('layui-bg-green').text(lang.badge_optimized);
        } else if (toInt(status) === 2) {
            $badge.addClass('layui-bg-orange').text(lang.badge_fallback);
        } else {
            $badge.addClass('layui-bg-gray').text(lang.badge_none);
        }
    }

    /**
     * initAiSeoButton
     *
     * @param {Object} options
     * @param {string} options.url                 - POST endpoint
     * @param {string} options.mid                 - 'vod' | 'art' (used for default selectors)
     * @param {string} options.idFieldName         - e.g. 'vod_id' | 'art_id'
     * @param {Object} options.lang                - localized strings
     * @param {number} options.initialStatus       - initial badge status
     * @param {string} [options.buttonSelector]    - default '#btn_ai_seo_generate'
     * @param {string} [options.badgeSelector]     - default '#seo_ai_status_badge'
     * @param {Object} [options.fallbackFields]    - {nameField, tagField, blurbField}
     */
    w.initAiSeoButton = function (options) {
        options = options || {};
        var lang = options.lang || {};
        var $btn = $(options.buttonSelector || '#btn_ai_seo_generate');
        if (!$btn.length) return;

        var badgeSelector = options.badgeSelector || '#seo_ai_status_badge';

        // Set initial badge state (if any).
        if (options.initialStatus != null) {
            setBadgeText(options.initialStatus, lang, badgeSelector);
        }

        $btn.off('click.ai_seo').on('click.ai_seo', function () {
            var idName = options.idFieldName || (options.mid === 'art' ? 'art_id' : 'vod_id');
            var id = $('input[name="' + idName + '"]').val();
            if (!id || toInt(id) <= 0) {
                if (w.layer && typeof w.layer.msg === 'function') {
                    w.layer.msg(lang.msg_save_first || '');
                }
                return;
            }

            var $that = $(this);
            if ($that.data('loading') === 1) return;

            $that.data('loading', 1).text(lang.msg_generating || '');

            $.post(options.url, { id: id }, function (res) {
                var normalized = normalizeSeoResponse(res);
                if (toInt(normalized.code) !== 1) {
                    if (w.layer && typeof w.layer.msg === 'function') {
                        w.layer.msg(normalized.msg || lang.msg_generate_fail || '');
                    }
                    return;
                }

                var seo = normalized.data || {};
                if (!seo.title && !seo.keywords && !seo.description) {
                    if (w.layer && typeof w.layer.msg === 'function') {
                        w.layer.msg(lang.msg_no_fillable || '');
                    }
                }

                setBadgeText(seo.status || 0, lang, badgeSelector);

                var prefix = options.mid === 'art' ? 'art' : 'vod';

                var $title = pickFirstSelector([
                    '#' + prefix + '_title',
                    '#title',
                    'input[name="' + prefix + '_title"]',
                    'input[name="title"]'
                ]);
                var $keywords = pickFirstSelector([
                    '#' + prefix + '_keywords',
                    '#keywords',
                    'input[name="' + prefix + '_keywords"]',
                    'input[name="keywords"]'
                ]);
                var $description = pickFirstSelector([
                    '#' + prefix + '_description',
                    '#description',
                    'textarea[name="' + prefix + '_description"]',
                    'textarea[name="description"]'
                ]);

                var fallback = options.fallbackFields || {};
                var $name = fallback.nameField ? $('input[name="' + fallback.nameField + '"],#' + fallback.nameField).first() : $();
                var $tag = fallback.tagField ? $('input[name="' + fallback.tagField + '"],#' + fallback.tagField).first() : $();
                var $blurb = fallback.blurbField ? $('textarea[name="' + fallback.blurbField + '"],#' + fallback.blurbField).first() : $();

                if ($title.length) {
                    $title.val(seo.title || '');
                } else if ($name.length) {
                    $name.val(seo.title || '');
                }
                if ($keywords.length) {
                    $keywords.val(seo.keywords || '');
                } else if ($tag.length) {
                    $tag.val(seo.keywords || '');
                }
                if ($description.length) {
                    $description.val(seo.description || '');
                } else if ($blurb.length) {
                    $blurb.val(seo.description || '');
                }

                if (w.layer && typeof w.layer.msg === 'function') {
                    w.layer.msg(lang.msg_done || '');
                }
            }, 'json').fail(function () {
                if (w.layer && typeof w.layer.msg === 'function') {
                    w.layer.msg(lang.msg_request_fail || '');
                }
            }).always(function () {
                $that.data('loading', 0).text(lang.btn_generate || '');
            });
        });
    };
})(window, window.jQuery);

