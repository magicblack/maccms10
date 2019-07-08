{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <div class="showpic" style="display:none;"><img class="showpic_img" width="120" height="160"></div>

    <form class="layui-form layui-form-pane" method="post" action="">
        <input id="admin_id" name="name" type="hidden" value="{$info.name}">

        {foreach $config as $item}
        <div class="layui-form-item">
            <label class="layui-form-label">{$item.title}：</label>

                {switch $item.type}
                {case string}
                <div class="layui-input-inline w500 ">
                <input type="text" name="row[{$item.name}]" value="{$item.value}" class="layui-input" data-rule="{$item.rule}" data-tip="{$item.tip}" {$item.extend} />
                </div>
            {/case}
                {case text}
                <div class="layui-input-inline w500 ">
                <textarea name="row[{$item.name}]" class="layui-input" data-rule="{$item.rule}" rows="5" data-tip="{$item.tip}" {$item.extend}>{$item.value}</textarea>
                </div>
                {/case}
                {case array}
                    {foreach name="item.value" item="vo" }

            <div class="layui-input-block">
                {$key}:<input type="text" name="row[{$item.name}][{$key}]" value="{$vo}" class="layui-input w500"  />
            </div>
                {/foreach}

                {/case}
                {case datetime}
            <div class="layui-input-inline w500 ">
                <input type="text" name="row[{$item.name}]" value="{$item.value}" class="layui-input datetimepicker" data-tip="{$item.tip}" data-rule="{$item.rule}" {$item.extend} />
                </div>
                {/case}
                {case number}
            <div class="layui-input-inline  w500">
                <input type="number" name="row[{$item.name}]" value="{$item.value}" class="layui-input" data-tip="{$item.tip}" data-rule="{$item.rule}" {$item.extend} />
                </div>
                {/case}
                {case checkbox}
            <div class="layui-input-block  ">
                {foreach name="item.content" item="vo"}
                <input id="row[{$item.name}][]-{$key}" name="row[{$item.name}][]" type="checkbox" value="{$key}" data-tip="{$item.tip}" title="{$vo}" {in name="key" value="$item.value"}checked{/in} />
                {/foreach}
                </div>
                {/case}
                {case radio}
            <div class="layui-input-block  ">
                {foreach name="item.content" item="vo"}
                <input id="row[{$item.name}]-{$key}" name="row[{$item.name}]" type="radio" value="{$key}" data-tip="{$item.tip}" title="{$vo}" {in name="key" value="$item.value"}checked{/in} />
                {/foreach}
                </div>
                {/case}
                {case value="select" break="0"}{/case}
                {case value="selects"}
            <div class="layui-input-block  ">
                <select name="row[{$item.name}]{$item.type=='selects'?'[]':''}" class="layui-input selectpicker" data-tip="{$item.tip}" {$item.type=='selects'?'multiple':''}>
                    {foreach name="item.content" item="vo"}
                    <option value="{$key}" {in name="key" value="$item.value"}selected{/in}>{$vo}</option>
                    {/foreach}
                </select>
                </div>
                {/case}
                {case value="image" break="0"}{/case}
                {case value="images"}
            <div class="layui-input-inline w500 ">
                <input id="c-{$item.name}" class="layui-input upload-input upload-img" size="37" name="row[{$item.name}]" type="text" value="{$item.value}" data-tip="{$item.tip}">
                </div>
            <div class="layui-input-inline ">
                <button type="button" class="layui-btn layui-upload" lay-data="{data:{thumb:0,thumb_class:''}}" >上传</button>
                </div>

                {/case}
                {case value="file" break="0"}{/case}
                {case value="files"}
            <div class="layui-input-inline w500 ">
                <input id="c-{$item.name}" class="layui-input upload-input" size="37" name="row[{$item.name}]" type="text" value="{$item.value}" data-tip="{$item.tip}">
            </div>
            <div class="layui-input-inline ">
                <button type="button" class="layui-btn layui-upload" lay-data="{data:{thumb:0,thumb_class:''}}" >上传</button>
            </div>

                {/case}
                {case bool}
            <div class="layui-input-block">
                <input id="row[{$item.name}]-yes" name="row[{$item.name}]" type="radio" value="1" {$item.value?'checked':''} data-tip="{$item.tip}" title="是"/>
                <input id="row[{$item.name}]-no" name="row[{$item.name}]" type="radio" value="0" {$item.value?'':'checked'} data-tip="{$item.tip}" title="否"/>
                </div>
                {/case}
                {/switch}
            </div>
        {/foreach}

        <div class="layui-form-item center">
            <div class="layui-input-block">
                <button type="submit" class="layui-btn" lay-submit="" lay-filter="formSubmit" data-child="true">保 存</button>
                <button class="layui-btn layui-btn-warm" type="reset">还 原</button>
            </div>
        </div>
    </form>

</div>
{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">
    layui.use(['form','upload', 'layer'], function () {
        // 操作对象
        var form = layui.form
                , layer = layui.layer
                , $ = layui.jquery, upload = layui.upload;;

        upload.render({
            elem: '.layui-upload'
            ,url: "{:url('upload/upload')}?flag=addon"
            ,method: 'post'
            ,before: function(input) {
                layer.msg('文件上传中...', {time:3000000});
            },done: function(res, index, upload) {
                var obj = this.item;
                if (res.code == 0) {
                    layer.msg(res.msg);
                    return false;
                }
                layer.closeAll();
                var input = $(obj).parent().parent().find('.upload-input');
                if ($(obj).attr('lay-type') == 'image') {
                    input.siblings('img').attr('src', res.data.file).show();
                }
                input.val(res.data.file);

            }
        });


        $('.upload-img').hover(function (e){
            var e = window.event || e;
            var imgsrc = $(this).val();
            if(imgsrc.trim()==""){ return; }
            var left = e.clientX+document.body.scrollLeft+20;
            var top = e.clientY+document.body.scrollTop+20;
            $(".showpic").css({left:left,top:top,display:""});
            if(imgsrc.indexOf('://')<0){ imgsrc = ROOT_PATH + '/' + imgsrc;	} else{ imgsrc = imgsrc.replace('mac:','http:'); }
            $(".showpic_img").attr("src", imgsrc);
        },function (e){
            $(".showpic").css("display","none");
        });

    });

</script>

</body>
</html>