(function (w) {
    'use strict';

    function fmt(str, o) {
        if (!str) {
            return '';
        }
        return String(str).replace(/\{(\w+)\}/g, function (_, k) {
            return o[k] != null ? o[k] : '';
        });
    }

    function parseCode(res) {
        if (typeof res === 'string') {
            try {
                res = JSON.parse(res);
            } catch (e) {
                return 0;
            }
        }
        if (!res || typeof res !== 'object') {
            return 0;
        }
        return parseInt(res.code, 10) || 0;
    }

    /**
     * List page: batch AI SEO for checked rows (current page only) + per-row button.
     *
     * opts.$, opts.layer, opts.postUrl required.
     * opts.form — optional layui.form for checkbox render.
     * opts.lang — see templates
     * opts.skipCheckbox — default #ai_seo_skip_existing
     * opts.batchButton — default #btn_ai_seo_batch
     * opts.rowButtonSelector — default .j-ai-seo-row
     */
    w.initAiSeoListPage = function (opts) {
        opts = opts || {};
        var $ = opts.$ || (w.layui && w.layui.jquery);
        var layer = opts.layer;
        var postUrl = opts.postUrl;
        if (!$ || !layer || !postUrl) {
            return;
        }

        var lang = opts.lang || {};
        var skipSel = opts.skipCheckbox || '#ai_seo_skip_existing';
        var batchSel = opts.batchButton || '#btn_ai_seo_batch';
        var rowSel = opts.rowButtonSelector || '.j-ai-seo-row';

        function shouldSkipForBatch(seoStatus) {
            var $sk = $(skipSel);
            if (!$sk.length || !$sk.prop('checked')) {
                return false;
            }
            var st = parseInt(seoStatus, 10) || 0;
            return st === 1 || st === 2;
        }

        function postGenerate(id, done) {
            $.post(postUrl, { id: id }, function (res) {
                done(parseCode(res) === 1, res);
            }, 'json').fail(function () {
                done(false, null);
            });
        }

        /** After success, keep tr data-seo-status in sync for "skip already processed" on next batch */
        function markRowSeoOptimized(id) {
            $('.checkbox-ids')
                .filter(function () {
                    return String($(this).val()) === String(id);
                })
                .first()
                .closest('tr')
                .attr('data-seo-status', '1');
        }

        /**
         * @param {object} pbOpts — cancelBtnText, onUserClose (layer end: user closed or Cancel btn; not used after normal complete)
         */
        function openProgressLayer(total, pbOpts) {
            pbOpts = pbOpts || {};
            var cancelTxt = pbOpts.cancelBtnText || 'Cancel';
            var html =
                '<div id="ai_seo_pb_root" style="padding:16px 20px 14px;min-width:320px;">' +
                '<div id="ai_seo_pb_line" style="margin-bottom:10px;font-size:14px;font-weight:500;"></div>' +
                '<div style="background:#eee;height:24px;border-radius:4px;overflow:hidden;">' +
                '<div id="ai_seo_pb_bar" style="height:100%;width:0%;background:#5FB878;transition:width .18s ease-out;"></div>' +
                '</div>' +
                '<div id="ai_seo_pb_sub" style="margin-top:10px;font-size:12px;color:#666;line-height:1.5;"></div>' +
                '<div style="margin-top:14px;text-align:center;">' +
                '<button type="button" id="ai_seo_pb_cancel" class="layui-btn layui-btn-primary layui-btn-sm">' +
                String(cancelTxt).replace(/</g, '&lt;') +
                '</button></div></div>';
            return layer.open({
                type: 1,
                title: lang.progress_title || 'AI SEO',
                area: ['440px', 'auto'],
                shade: 0.35,
                closeBtn: 1,
                btn: false,
                content: html,
                success: function (layero, index) {
                    paintProgress(0, total, { ok: 0, fail: 0, skip: 0 });
                    layero.find('#ai_seo_pb_cancel').on('click', function () {
                        layer.close(index);
                    });
                },
                end: function () {
                    if (typeof pbOpts.onUserClose === 'function') {
                        pbOpts.onUserClose();
                    }
                }
            });
        }

        function paintProgress(completed, total, stats) {
            var pct = total > 0 ? Math.min(100, Math.round((completed / total) * 100)) : 100;
            var $root = $('#ai_seo_pb_root');
            if (!$root.length) {
                return;
            }
            $('#ai_seo_pb_line').text(
                fmt(lang.progress_line || '{current} / {total} ({pct}%)', {
                    current: completed,
                    total: total,
                    pct: pct
                })
            );
            $('#ai_seo_pb_bar').css('width', pct + '%');
            $('#ai_seo_pb_sub').text(
                fmt(lang.progress_sub || '', {
                    ok: stats.ok,
                    fail: stats.fail,
                    skip: stats.skip
                })
            );
        }

        $(batchSel).off('click.aiSeoList').on('click.aiSeoList', function () {
            var $batch = $(this);
            if ($batch.prop('disabled')) {
                return;
            }
            var items = [];
            $('.checkbox-ids:checked').each(function () {
                var $cb = $(this);
                var id = $cb.val();
                var $tr = $cb.closest('tr');
                var st = $tr.attr('data-seo-status');
                if (id) {
                    items.push({ id: id, seoStatus: st });
                }
            });
            if (!items.length) {
                layer.msg(lang.no_selection || '');
                return;
            }

            $batch.prop('disabled', true).addClass('layui-btn-disabled');

            var total = items.length;
            var stats = { ok: 0, fail: 0, skip: 0 };
            var completed = 0;
            var idx = 0;
            var batchState = 'running';
            var cancelled = false;

            var progressIdx = openProgressLayer(total, {
                cancelBtnText: lang.list_batch_cancel,
                onUserClose: function () {
                    if (batchState !== 'running') {
                        return;
                    }
                    batchState = 'cancelled';
                    cancelled = true;
                    $batch.prop('disabled', false).removeClass('layui-btn-disabled');
                    layer.msg(
                        fmt(lang.list_summary_cancelled || lang.summary || '', {
                            completed: completed,
                            total: total,
                            ok: stats.ok,
                            fail: stats.fail,
                            skip: stats.skip
                        }),
                        { time: 5000 }
                    );
                }
            });

            function finishBatchSuccess() {
                if (batchState !== 'running') {
                    return;
                }
                batchState = 'done';
                layer.close(progressIdx);
                $batch.prop('disabled', false).removeClass('layui-btn-disabled');
                layer.msg(
                    fmt(lang.summary || '', {
                        total: total,
                        ok: stats.ok,
                        fail: stats.fail,
                        skip: stats.skip
                    }),
                    { time: 5000 }
                );
            }

            function next() {
                if (cancelled) {
                    return;
                }
                if (idx >= items.length) {
                    paintProgress(total, total, stats);
                    setTimeout(finishBatchSuccess, 280);
                    return;
                }

                var it = items[idx];
                if (shouldSkipForBatch(it.seoStatus)) {
                    stats.skip++;
                    idx++;
                    completed++;
                    paintProgress(completed, total, stats);
                    if (cancelled) {
                        return;
                    }
                    setTimeout(next, 0);
                    return;
                }

                postGenerate(it.id, function (ok) {
                    if (ok) {
                        stats.ok++;
                        markRowSeoOptimized(it.id);
                    } else {
                        stats.fail++;
                    }
                    idx++;
                    completed++;
                    paintProgress(completed, total, stats);
                    if (cancelled) {
                        return;
                    }
                    next();
                });
            }

            next();
        });

        $(document).off('click.aiSeoRow', rowSel).on('click.aiSeoRow', rowSel, function (e) {
            e.preventDefault();
            var $btn = $(this);
            if ($btn.data('aiSeoRowPending')) {
                return;
            }
            var id = $btn.data('id');
            if (!id) {
                return;
            }
            $btn.data('aiSeoRowPending', 1).addClass('layui-btn-disabled');
            if ($btn.is('button,input')) {
                $btn.prop('disabled', true);
            }
            var loadIdx = layer.load(2, { shade: [0.2, '#000'] });
            postGenerate(id, function (ok, res) {
                layer.close(loadIdx);
                $btn.removeData('aiSeoRowPending').removeClass('layui-btn-disabled');
                if ($btn.is('button,input')) {
                    $btn.prop('disabled', false);
                }
                if (ok) {
                    markRowSeoOptimized(id);
                    layer.msg(lang.row_ok || '');
                } else {
                    var msg = (res && res.msg) ? String(res.msg) : (lang.row_fail || '');
                    layer.msg(msg || (lang.msg_request_fail || ''));
                }
            });
        });

        if (opts.form && typeof opts.form.render === 'function') {
            opts.form.render('checkbox');
        }
    };
})(window);
