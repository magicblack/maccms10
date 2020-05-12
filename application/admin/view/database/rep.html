{include file="../../../application/admin/view/public/head" /}
<style>
    .layui-form-select ul {max-height:200px}
    .layui-btn+.layui-btn{margin-left:0px; }
</style>
<div class="page-container">
    <form class="layui-form layui-form-pane" action="">
        <div class="layui-tab">
            <ul class="layui-tab-title">
                <li class="layui-this">批量替换</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">

                <div class="layui-form-item">
                    <label class="layui-form-label">选择数据表：</label>
                    <div class="layui-input-inline w400" >
                        <select name="table" lay-filter="table" lay-verify="table">
                            <option value="">请选择表</option>
                            {volist name="list" id="vo"}
                                <option value="{$vo.Name}">{$vo.Name}【{$vo.Comment}】</option>
                            {/volist}
                        </select>
                    </div>
                </div>
                <div class="layui-form-item row-fields">
                    <label class="layui-form-label">选择字段：</label>
                    <div class="layui-input-block fields" >

                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label">要替换的字段：</label>
                    <div class="layui-input-block" >
                        <input type="text" id="field" name="field" placeholder="请选择字段" lay-verify="field" class="layui-input">
                    </div>
                </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">被替换的内容：</label>
                        <div class="layui-input-block" >
                            <textarea name="findstr" placeholder="请输入" lay-verify="findstr" class="layui-textarea"></textarea>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">替换为内容：</label>
                        <div class="layui-input-block" >
                            <textarea name="tostr" placeholder="请输入" lay-verify="tostr" class="layui-textarea"></textarea>
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">替换条件：</label>
                        <div class="layui-input-block" >
                            <input type="text" name="where" placeholder="请输入" value="" class="layui-input">
                        </div>
                    </div>

                <div class="layui-form-item">

                </div>
            </div>
            </div>
        </div>
        <div class="layui-form-item center">
            <div class="layui-input-block">
                <button type="submit" class="layui-btn" lay-submit="" lay-filter="formSubmit">保 存</button>
                <button class="layui-btn layui-btn-warm" type="reset">还 原</button>
            </div>
        </div>
    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript">
    layui.use(['form', 'layer'], function(){
        // 操作对象
        var form = layui.form
                , layer = layui.layer,
                $ = layui.jquery;

        form.on('select(table)', function(data){
            $('.fields').html('');
            if(data.value !=''){
                $.post("{:url('columns')}", {table:data.value}, function(res) {
                    if (res.code == 1) {
                        $.each(res.data,function(index,row){
                            $(".fields").append('<a class="layui-btn layui-btn-xs w80" href="javascript:setfield(\''+row.Field+'\')">'+row.Field+'</a>&nbsp;&nbsp;');
                            if(index>0 && index%5==0){
                                //$(".fields").append('<br>');
                            }

                        });
                    }
                    layer.msg(res.msg);
                });
            }
        });


        // 验证
        form.verify({
            table: function (value) {
                if (value == "") {
                    return "请选择数据表";
                }
            },
            field: function (value) {
                if (value == "") {
                    return "请选择字段";
                }
            },
            findstr: function (value) {
                if (value == "") {
                    return "请输入需要替换的内容";
                }
            },
            tostr: function (value) {
                if (value == "") {
                    return "请输入替换为内容";
                }
            }
        });

    });

    function setfield(v){
        $('#field').val(v);
    }

</script>

</body>
</html>