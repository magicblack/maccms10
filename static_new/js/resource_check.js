// 内容资源状态：启用 / 停用（视频、漫画、文章列表与资源检测日志页共用）
// 页面需定义 RC_CFG = {mid, enableUrl, disableUrl, lang}；行按钮 .j-rc-row（data-act, data-id[, data-mid]），批量按钮 .j-rc-batch（data-act）
// 停用必须填写原因，启用原因可选；CSRF 头由后台 head 中的 ajax 钩子自动附带
;layui.use(['jquery','layer'], function(){
    var $ = layui.jquery || jQuery, layer = layui.layer;
    var L = RC_CFG.lang || {};

    function submit(act, mid, ids){
        var disable = act === 'disable';
        layer.open({
            type: 1,
            title: disable ? L.disable_title : L.enable_title,
            area: ['420px', 'auto'],
            content: '<div style="padding:15px"><textarea class="layui-textarea" id="rcReason" maxlength="200" placeholder="' + (disable ? L.reason_ph_disable : L.reason_ph_enable) + '"></textarea></div>',
            btn: [L.ok || 'OK', L.cancel || 'Cancel'],
            yes: function(index){
                var reason = $.trim($('#rcReason').val());
                if(disable && reason === ''){
                    layer.msg(L.reason_required, {icon: 2});
                    return;
                }
                layer.close(index);
                var loading = layer.load(1);
                $.post(disable ? RC_CFG.disableUrl : RC_CFG.enableUrl, {mid: mid, ids: ids.join(','), reason: reason}, function(res){
                    layer.close(loading);
                    layer.msg(res.msg, {icon: res.code == 1 ? 1 : 2, time: 1500}, function(){
                        if(res.code == 1){ location.reload(); }
                    });
                }, 'json').fail(function(){
                    layer.close(loading);
                    layer.msg('Request failed', {icon: 2});
                });
            }
        });
    }

    $(document).on('click', '.j-rc-row', function(){
        var btn = $(this);
        submit(btn.data('act'), btn.data('mid') || RC_CFG.mid, [btn.data('id')]);
    });

    $(document).on('click', '.j-rc-batch', function(){
        var ids = [];
        $('.checkbox-ids:checked').each(function(){ ids.push(this.value); });
        if(!ids.length){
            layer.msg(L.select_rows, {icon: 2});
            return;
        }
        submit($(this).data('act'), RC_CFG.mid, ids);
    });
});
