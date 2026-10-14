// 采集接口启用 / 停用按钮（自定义接口列表、资源站中心、接口日志页共用）
// 页面需定义 CH_TOGGLE_URL、CH_LANG；CSRF 头由后台 head 中的 ajax 钩子自动附带
;layui.use(['jquery','layer'], function(){
    var $ = layui.jquery || jQuery, layer = layui.layer;
    $(document).on('click', '.j-health-toggle', function(e){
        e.preventDefault();
        var btn = $(this);
        var status = String(btn.data('status'));
        var tip = status === '1' ? (CH_LANG.confirm_enable || 'Enable?') : (CH_LANG.confirm_disable || 'Disable?');
        layer.confirm(tip, {title: false, closeBtn: 0}, function(index){
            layer.close(index);
            var loading = layer.load(1);
            $.post(CH_TOGGLE_URL, {
                url: btn.data('url'),
                name: btn.data('name') || '',
                source: btn.data('source') || 'custom',
                status: status
            }, function(res){
                layer.close(loading);
                layer.msg(res.msg, {icon: res.code == 1 ? 1 : 2, time: 1200}, function(){
                    if(res.code == 1){ location.reload(); }
                });
            }, 'json').fail(function(){
                layer.close(loading);
                layer.msg('Request failed', {icon: 2});
            });
        });
    });
});
