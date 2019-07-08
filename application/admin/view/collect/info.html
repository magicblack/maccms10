{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <form class="layui-form layui-form-pane" method="post" action="">
        <input id="collect_id" name="collect_id" type="hidden" value="{$info.collect_id}">
        <div class="layui-form-item">
            <label class="layui-form-label">资源名称：</label>
            <div class="layui-input-block  ">
                <input type="text" class="layui-input" value="{$info.collect_name}" placeholder="" id="collect_name" name="collect_name">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">接口地址：</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" value="{$info.collect_url}" placeholder="" id="collect_url" name="collect_url">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">附加参数：</label>
            <div class="layui-input-block">
                <input type="text" class="layui-input" value="{$info.collect_param}" placeholder="" id="collect_param" name="collect_param">
            </div>
            <div class="layui-form-mid layui-word-aux" style="margin-left:110px; ">提示信息：一般&开头，例如老版xml格式采集下载地址需加入&ct=1</div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">接口类型：</label>
            <div class="layui-input-block">
                <input name="collect_type" type="radio" value="1" title="xml" {if condition="$info['collect_type'] eq 1"}checked {/if}>
                <input name="collect_type" type="radio" value="2" title="json" {if condition="$info['collect_type'] neq 1"}checked {/if}>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">资源类型：</label>
            <div class="layui-input-block">
                <input name="collect_mid" type="radio" value="1" title="视频" {if condition="$info['collect_mid'] eq 1"}checked {/if}>
                <input name="collect_mid" type="radio" value="2" title="文章" {if condition="$info['collect_mid'] eq 2"}checked {/if}>
                <input name="collect_mid" type="radio" value="8" title="演员" {if condition="$info['collect_mid'] eq 8"}checked {/if}>
            </div>
        </div>

        <div class="layui-form-item center">
            <div class="layui-input-block">
                <button class="layui-btn layui-btn-normal" type="button" id="btnTest" >测 试</button>
                <button type="submit" class="layui-btn" lay-submit="" lay-filter="formSubmit" data-child="true">保 存</button>
                <button class="layui-btn layui-btn-warm" type="reset">还 原</button>
            </div>
        </div>
    </form>

</div>
{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">
    layui.use(['form', 'layer'], function () {
        // 操作对象
        var form = layui.form
                , layer = layui.layer
                , $ = layui.jquery;

        // 验证
        form.verify({
            collect_name: function (value) {
                if (value == "") {
                    return "请输入资源名称";
                }
            },
            collect_url: function (value) {
                if (value == "") {
                    return "请输入接口地址";
                }
            }
        });


        $('#btnTest').click(function() {
            var that = $(this);
            var data = 'cjurl='+ $('#collect_url').val() + '&cjflag='+ '&ac=list';

            $.post("{:url('test')}",data,function(r){
                if(r.code==1){
                    layer.msg('测试类型成功，接口类型：'+ r.msg ,{time:1800});
                    if(r.msg=='json'){
                        $("input[name='collect_type'][value=2]").attr("checked",true);
                    }
                    else{
                        $("input[name='collect_type'][value=1]").attr("checked",true);
                    }
                    form.render('radio');
                }
                else{
                    layer.msg(r.msg,{time:1800});
                }
            });

        });

    });




</script>

</body>
</html>