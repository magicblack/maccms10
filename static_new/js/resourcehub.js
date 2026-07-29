;layui.use(['jquery','layer'], function(){
    var $ = layui.jquery || jQuery, layer = layui.layer;
    if(!$ || !layer){ console.error('ResourceHub: layui.jquery or layui.layer not ready'); return; }
    if(typeof RH_LANG === 'undefined') RH_LANG = {};

    // 统一的请求失败提示
    function rhAjaxError(loading){
        if(loading) layer.close(loading);
        layer.msg(RH_LANG.request_fail || 'Request failed, please try again', {icon:2});
    }

    // 检测资源站
    $(document).on('click', '.btn-check', function(){
        var btn = $(this);
        var url = btn.data('url');
        var statusId = btn.data('status-id');
        btn.prop('disabled', true).text(RH_LANG.checking || 'Checking...');
        $.post(RH_URLS.check, {url: url}, function(res){
            btn.prop('disabled', false).text(RH_LANG.btn_check || 'Check');
            if(res.code == 1){
                $('#'+statusId).removeClass('unknown offline').addClass('online');
                layer.msg(res.msg + ' ('+res.data.total+')', {icon:1});
            } else {
                $('#'+statusId).removeClass('unknown online').addClass('offline');
                layer.msg(res.msg, {icon:2});
            }
        }, 'json').fail(function(){
            btn.prop('disabled', false).text(RH_LANG.btn_check || 'Check');
            $('#'+statusId).removeClass('unknown online').addClass('offline');
            rhAjaxError();
        });
    });

    // 进入资源库（POST 表单提交，防止 GET 触发 SSRF）
    $(document).on('click', '.btn-quick-collect', function(){
        var btn = $(this);
        // 防止重复点击造成多次提交
        if(btn.data('rh-submitting')){ return; }
        btn.data('rh-submitting', true);

        var url = btn.data('url');
        var type = btn.data('type');
        var mid = btn.data('mid');

        // 全屏载入提示，因后端需请求远端资源站，页面跳转后会自动消失
        layer.msg(RH_LANG.entering || 'Entering resource library, please wait...', {
            icon: 16,
            shade: 0.3,
            time: 0
        });

        var form = $('<form>', {method:'POST', action: RH_URLS.quickCollect});
        form.append($('<input>', {type:'hidden', name:'url', value: url}));
        form.append($('<input>', {type:'hidden', name:'type', value: type}));
        form.append($('<input>', {type:'hidden', name:'mid', value: mid}));
        form.append($('<input>', {type:'hidden', name:'h', value: '24'}));
        form.appendTo('body').submit();
    });

    // 同步分类
    $(document).on('click', '.btn-sync-type', function(){
        var url = $(this).data('url');
        var type = $(this).data('type');
        var loading = layer.load(1);
        $.get(RH_URLS.getTypes, {url:url, type:type}, function(res){
            layer.close(loading);
            if(res.code != 1){ layer.msg(res.msg, {icon:2}); return; }
            var html = '<div style="padding:15px;"><div style="margin-bottom:10px;"><button type="button" class="layui-btn layui-btn-xs" id="selectAllTypes">'+(RH_LANG.btn_select_all||'Select All')+'</button> <button type="button" class="layui-btn layui-btn-xs layui-btn-primary" id="selectNoneTypes">'+(RH_LANG.btn_select_none||'Deselect All')+'</button></div><div style="max-height:400px;overflow-y:auto;" id="typeCheckboxes">';
            res.data.types.forEach(function(t){
                var checked = t.exists ? '' : 'checked';
                var badge = t.exists ? ' <span style="color:#5FB878;">['+(RH_LANG.type_exists||'Exists')+']</span>' : '';
                var safeVal = $('<span>').text(t.name).html();
                html += '<div style="margin:5px 0;"><label><input type="checkbox" name="type_names[]" value="'+safeVal+'" '+checked+'> '+safeVal + badge+'</label></div>';
            });
            html += '</div><div style="margin-top:15px;text-align:center;"><button type="button" class="layui-btn" id="doSyncTypes">'+(RH_LANG.btn_confirm_sync||'Confirm Sync')+'</button></div></div>';

            layer.open({
                type: 1, title: RH_LANG.sync_type_title||'Sync Categories', area: ['500px', '550px'], content: html,
                success: function(layero){
                    layero.find('#selectAllTypes').on('click', function(){ layero.find('input[name="type_names[]"]').prop('checked', true); });
                    layero.find('#selectNoneTypes').on('click', function(){ layero.find('input[name="type_names[]"]').prop('checked', false); });
                    layero.find('#doSyncTypes').on('click', function(){
                        var btnSync = $(this);
                        var names = [];
                        layero.find('input[name="type_names[]"]:checked').each(function(){ names.push($(this).val()); });
                        if(names.length == 0){ layer.msg(RH_LANG.select_at_least_one||'Please select at least one'); return; }
                        btnSync.prop('disabled', true);
                        var loadingSync = layer.load(1);
                        $.post(RH_URLS.syncTypes, {type_names: names, pid:0}, function(r){
                            layer.close(loadingSync);
                            btnSync.prop('disabled', false);
                            layer.msg(r.msg, {icon: r.code==1?1:2});
                            if(r.code==1){
                                var loadingBind = layer.load(1);
                                $.post(RH_URLS.autoBind, {url:url, type:type}, function(r2){
                                    layer.close(loadingBind);
                                    if(r2.code==1) layer.msg(r2.msg, {icon:1});
                                }, 'json').fail(function(){ rhAjaxError(loadingBind); });
                            }
                        }, 'json').fail(function(){
                            btnSync.prop('disabled', false);
                            rhAjaxError(loadingSync);
                        });
                    });
                }
            });
        }, 'json').fail(function(){ rhAjaxError(loading); });
    });

    // 自动配置（绑定分类+播放器）
    $(document).on('click', '.btn-auto-bindplayer', function(){
        var url = $(this).data('url');
        var type = $(this).data('type');
        layer.confirm(RH_LANG.confirm_auto_config||'Auto-bind categories and add player config, continue?', function(idx){
            layer.close(idx);
            var loading = layer.load(1, {content: RH_LANG.processing || 'Processing...', shade: 0.3});
            $.post(RH_URLS.autoBind, {url:url, type:type}, function(res1){
                $.post(RH_URLS.autoPlayer, {url:url, type:type}, function(res2){
                    layer.close(loading);
                    layer.alert(res1.msg + '<br>' + res2.msg, {icon:1, title: RH_LANG.auto_config_result||'Auto Config Result'});
                }, 'json').fail(function(){ rhAjaxError(loading); });
            }, 'json').fail(function(){ rhAjaxError(loading); });
        });
    });

    // 添加到采集列表
    $(document).on('click', '.btn-add-collect', function(){
        var btn = $(this);
        var d = {url: btn.data('url'), type: btn.data('type'), mid: btn.data('mid'), name: btn.data('name')};
        btn.prop('disabled', true);
        var loading = layer.load(1);
        $.post(RH_URLS.addToCollect, d, function(res){
            layer.close(loading);
            btn.prop('disabled', false);
            layer.msg(res.msg, {icon: res.code==1?1:2});
        }, 'json').fail(function(){
            btn.prop('disabled', false);
            rhAjaxError(loading);
        });
    });

    // 添加定时任务
    $(document).on('click', '.btn-add-timming', function(){
        var url = $(this).data('url'), type = $(this).data('type'), mid = $(this).data('mid'), name = $(this).data('name');
        layer.open({
            type: 1, title: RH_LANG.add_timming_title||'Add Scheduled Task', area: ['400px', '250px'],
            content: '<div style="padding:20px;"><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.collect_range||'Range')+'</label><div class="layui-input-block"><select id="timming_hours"><option value="2">'+(RH_LANG.hours_2||'Last 2h')+'</option><option value="12">'+(RH_LANG.hours_12||'Last 12h')+'</option><option value="24" selected>'+(RH_LANG.hours_24||'Last 24h')+'</option><option value="48">'+(RH_LANG.hours_48||'Last 48h')+'</option><option value="168">'+(RH_LANG.hours_week||'Last week')+'</option><option value="">'+(RH_LANG.hours_all||'All')+'</option></select></div></div><div style="text-align:center;margin-top:15px;"><button class="layui-btn" id="doAddTimming">'+(RH_LANG.btn_confirm_add||'Confirm')+'</button></div></div>',
            success: function(layero){
                layero.find('#doAddTimming').on('click', function(){
                    var btnAdd = $(this);
                    var hours = layero.find('#timming_hours').val();
                    btnAdd.prop('disabled', true);
                    var loading = layer.load(1);
                    $.post(RH_URLS.addTimming, {url:url, type:type, mid:mid, name:name, hours:hours}, function(res){
                        layer.close(loading);
                        btnAdd.prop('disabled', false);
                        if(res.code==1){
                            layer.closeAll();
                            layer.alert(RH_LANG.add_timming_tip||res.msg, {icon:1, title: RH_LANG.add_timming_title||'Add Scheduled Task'});
                        } else {
                            layer.msg(res.msg, {icon:2});
                        }
                    }, 'json').fail(function(){
                        btnAdd.prop('disabled', false);
                        rhAjaxError(loading);
                    });
                });
            }
        });
    });

    // 添加自定义资源站
    $(document).on('click', '#addCustomSite', function(){
        layer.open({
            type: 1, title: RH_LANG.add_custom_title||'Add Custom Site', area: ['500px', '400px'],
            content: '<div style="padding:20px;"><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.site_name||'Site Name')+'</label><div class="layui-input-block"><input type="text" id="custom_name" class="layui-input"></div></div><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.site_url||'API URL')+'</label><div class="layui-input-block"><input type="text" id="custom_url" class="layui-input" placeholder="https://example.com/api.php/provide/vod/"></div></div><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.site_type||'API Type')+'</label><div class="layui-input-block"><select id="custom_type"><option value="2">JSON</option><option value="1">XML</option></select></div></div><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.site_mid||'Resource Type')+'</label><div class="layui-input-block"><select id="custom_mid"><option value="1">Video</option><option value="2">Article</option></select></div></div><div class="layui-form-item"><label class="layui-form-label">'+(RH_LANG.site_remark||'Remark')+'</label><div class="layui-input-block"><input type="text" id="custom_desc" class="layui-input"></div></div><div style="text-align:center;"><button class="layui-btn" id="doAddCustom">'+(RH_LANG.btn_confirm_add||'Confirm')+'</button></div></div>',
            success: function(layero){
                layero.find('#doAddCustom').on('click', function(){
                    var btnAdd = $(this);
                    var d = {name: layero.find('#custom_name').val(), url: layero.find('#custom_url').val(), type: layero.find('#custom_type').val(), mid: layero.find('#custom_mid').val(), desc: layero.find('#custom_desc').val()};
                    if(!d.name || !d.url){ layer.msg(RH_LANG.fill_complete||'Please fill in all fields'); return; }
                    btnAdd.prop('disabled', true);
                    var loading = layer.load(1);
                    $.post(RH_URLS.addCustomSite, d, function(res){
                        layer.close(loading);
                        layer.msg(res.msg, {icon: res.code==1?1:2});
                        if(res.code==1){
                            setTimeout(function(){ location.reload(); }, 1000);
                        } else {
                            btnAdd.prop('disabled', false);
                        }
                    }, 'json').fail(function(){
                        btnAdd.prop('disabled', false);
                        rhAjaxError(loading);
                    });
                });
            }
        });
    });

    // 删除自定义资源站
    $(document).on('click', '.btn-del-custom', function(){
        var index = $(this).data('index');
        layer.confirm(RH_LANG.confirm_delete||'Are you sure?', function(idx){
            layer.close(idx);
            var loading = layer.load(1);
            $.post(RH_URLS.delCustomSite, {index: index}, function(res){
                layer.close(loading);
                layer.msg(res.msg, {icon: res.code==1?1:2});
                if(res.code==1) setTimeout(function(){ location.reload(); }, 1000);
            }, 'json').fail(function(){ rhAjaxError(loading); });
        });
    });
});
