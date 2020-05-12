{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <form class="layui-form layui-form-pane" method="post" action="">
        <input id="admin_id" name="admin_id" type="hidden" value="{$info.admin_id}">
        <div class="layui-form-item">
            <label class="layui-form-label">账号：</label>
            <div class="layui-input-block  ">
                <input type="text" class="layui-input" value="{$info.admin_name}" placeholder="" id="admin_name" name="admin_name">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">密码：</label>
            <div class="layui-input-block">
                <input type="password" class="layui-input" value="{$info.admin_pwd}" placeholder="" id="admin_pwd" name="admin_pwd">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">状态：</label>
            <div class="layui-input-block">
                    <input name="admin_status" type="radio" id="rad-1" value="0" title="禁用" {if condition="$info['admin_status'] neq 1"}checked {/if}>
                    <input name="admin_status" type="radio" id="rad-2" value="1" title="启用" {if condition="$info['admin_status'] eq 1"}checked {/if}>
            </div>
        </div>

        <div class="layui-form-item ">
            <label class="layui-form-label">权限：</label>
            <div class="layui-input-block">
                <blockquote class="layui-elem-quote layui-quote-nm">
                    提示：<br>
                    1.权限控制精准到每个操作，创始人ID为1的管理员拥有所有权限。
                    2.--开头的是页面内按钮操作选项。
                </blockquote>


                <div class="role-list-form ">
                    {volist name="menus" id="vo" key="k1"}
                    <dl class="role-list-form-top permission-list">
                        <dt>
                            <input type="checkbox" value="" {$vo.ck} lay-skin="primary" data-id="{$k1}" lay-filter="roleAuth1" title="{$vo.name}">
                        </dt>
                        <dd>
                            {volist name="$vo.sub" id="sub" key="k2"}
                                <input type="checkbox" value="{$sub.controller}/{$sub.action}" name="admin_auth[]" {$sub.ck} data-pid="{$k1}" title="{$sub.name}" lay-skin="primary" lay-filter="roleAuth2">
                            {/volist}
                        </dd>
                    </dl>
                    {/volist}
                </div>

            </div>
        </div>

        <div class="layui-form-item center">
            <div class="layui-input-block">
                <button type="button" class="layui-btn layui-btn-normal formCheckAll" lay-filter="formCheckAll" >全选</button>
                <button type="button" class="layui-btn layui-btn-normal formCheckOther" lay-filter="formCheckOther">反选</button>

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
            admin_name: function (value) {
                if (value == "") {
                    return "请输入管理员名称";
                }
            },
            admin_pwd: function (value) {
                if (value == "") {
                    return "请输入管理员密码";
                }
            }
        });

        form.on('checkbox(roleAuth1)', function(data) {
            var child = $(data.elem).parent('dt').siblings('dd').find('input');
            /* 自动选中子节点 */
            child.each(function(index, item) {
                if(item.disabled == true){

                }
                else {
                    item.checked = data.elem.checked;
                }
            });
            form.render('checkbox');
        });

        form.on('checkbox(roleAuth2)', function(data) {
            var child = $(data.elem).parent().find('input');
            var parent = $(data.elem).parent('dd').siblings('dt').find('input');
            var parent_ck= true;
            /* 自动选中子节点 */
            child.each(function(index, item) {
                if(!item.checked){
                    parent_ck = false;
                }
            });
            parent.each(function(index, item) {
                item.checked = parent_ck;
            });
            form.render('checkbox');
        });


        $('.formCheckAll').click(function(){
            var child = $('.role-list-form-top').find('input');
            /* 自动选中子节点 */
            child.each(function(index, item) {
                item.checked = true;
            });
            form.render('checkbox');
        });
        $('.formCheckOther').click(function(){
            var child = $('.role-list-form-top').find('input');
            /* 自动选中子节点 */
            child.each(function(index, item) {
                item.checked = (item.checked  ? false : true);
            });
            form.render('checkbox');
        });

    });




</script>

</body>
</html>