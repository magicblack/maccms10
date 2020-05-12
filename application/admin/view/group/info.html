{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <form class="layui-form layui-form-pane" method="post" action="">
        <input id="group_id" name="group_id" type="hidden" value="{$info.group_id}">
        <div class="layui-form-item">
            <label class="layui-form-label">名称：</label>
            <div class="layui-input-block  ">
                <input type="text" class="layui-input" value="{$info.group_name}" placeholder="请输入会员组名称" lay-verify="group_name" name="group_name">
            </div>
        </div>

        {if condition="$info.group_id gt 2"}
        <div class="layui-form-item">
            <label class="layui-form-label">包天价格：</label>
            <div class="layui-input-inline">
                <input type="text" class="layui-input" value="{$info.group_points_day}" placeholder="包天" lay-verify="group_points_day" name="group_points_day">
            </div>
            <label class="layui-form-label">包周价格：</label>
            <div class="layui-input-inline">
                <input type="text" class="layui-input" value="{$info.group_points_week}" placeholder="包周" lay-verify="group_points_week" name="group_points_week">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">包月价格：</label>
            <div class="layui-input-inline">
                <input type="text" class="layui-input" value="{$info.group_points_month}" placeholder="包月" lay-verify="group_points_month" name="group_points_month">
            </div>
            <label class="layui-form-label">包年价格：</label>
            <div class="layui-input-inline">
                <input type="text" class="layui-input" value="{$info.group_points_year}" placeholder="包年" lay-verify="group_points_year" name="group_points_year">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">状态：</label>
            <div class="layui-input-block">
                    <input name="group_status" type="radio" value="0" title="禁用" {if condition="$info['group_status'] neq 1"}checked {/if}>
                    <input name="group_status" type="radio" value="1" title="启用" {if condition="$info['group_status'] eq 1"}checked {/if}>
            </div>
        </div>
        {/if}

        <div class="layui-form-item ">
            <label class="layui-form-label">相关权限：</label>
            <div class="layui-input-block">
                <blockquote class="layui-elem-quote layui-quote-nm">
                    提示：<br>
                    1.列表页、内容页、播放页、下载页4个权限，控制是否可以进入页面，没权限会直接返回提示信息。<br>
                    2.试看权限：如果没有访问播放页的权限、或者有权限但是需要积分购买的数据，开启了试看权限也是可以进入页面的。
                </blockquote>

                <div class="role-list-form ">
                {volist name="type_tree" id="vo" key="k1"}
                    <dl class="role-list-form-top permission-list">
                        <dt>
                            分类：<input type="checkbox" value="{$vo.type_id}" name="group_type[]" data-id="{$k1}" lay-skin="primary" lay-filter="roleAuth1" title="{$vo.type_name}" {if condition="strpos(','.$info['group_type'],','.$vo['type_id'].',')>0"}checked {/if}>
                            权限：<input type="checkbox" name="group_popedom[{$vo.type_id}][1]" value="1" lay-skin="primary" title="列表页" {if condition="!empty($info['group_popedom'][$vo.type_id][1])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$vo.type_id}][2]" value="2" lay-skin="primary" title="内容页" {if condition="!empty($info['group_popedom'][$vo.type_id][2])"}checked {/if}>
                            {if condition="$vo.type_mid eq 1"}
                            <input type="checkbox" name="group_popedom[{$vo.type_id}][3]" value="3" lay-skin="primary" title="播放页" {if condition="!empty($info['group_popedom'][$vo.type_id][3])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$vo.type_id}][4]" value="4" lay-skin="primary" title="下载页" {if condition="!empty($info['group_popedom'][$vo.type_id][4])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$vo.type_id}][5]" value="5" lay-skin="primary" title="试看" {if condition="!empty($info['group_popedom'][$vo.type_id][5])"}checked {/if}>
                            {/if}
                        </dt>
                    </dl>
                    {volist name="$vo.child" id="sub" key="k2"}
                    <dl class="role-list-form-top permission-list">
                        <dt>
                            分类：<input type="checkbox" value="{$sub.type_id}" name="group_type[]" data-id="{$k1}" lay-skin="primary" lay-filter="roleAuth1" title="---{$sub.type_name}" {if condition="strpos(','.$info['group_type'],','.$sub  ['type_id'].',')>0"}checked {/if}>
                            权限：<input type="checkbox" name="group_popedom[{$sub.type_id}][1]" value="1" lay-skin="primary" title="列表页" {if condition="!empty($info['group_popedom'][$sub.type_id][1])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$sub.type_id}][2]" value="2" lay-skin="primary" title="内容页" {if condition="!empty($info['group_popedom'][$sub.type_id][2])"}checked {/if}>
                            {if condition="$sub.type_mid eq 1"}
                            <input type="checkbox" name="group_popedom[{$sub.type_id}][3]" value="3" lay-skin="primary" title="播放页" {if condition="!empty($info['group_popedom'][$sub.type_id][3])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$sub.type_id}][4]" value="4" lay-skin="primary" title="下载页" {if condition="!empty($info['group_popedom'][$sub.type_id][4])"}checked {/if}>
                            <input type="checkbox" name="group_popedom[{$sub.type_id}][5]" value="5" lay-skin="primary" title="试看" {if condition="!empty($info['group_popedom'][$sub.type_id][5])"}checked {/if}>
                            {/if}
                        </dt>
                    </dl>
                    {/volist}
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
            group_name: function (value) {
                if (value == "") {
                    return "请输入会员组名称";
                }
            }
        });

        $('.formCheckAll').click(function(){
            var child = $('.role-list-form').find('input');
            /* 自动选中子节点 */
            child.each(function(index, item) {
                item.checked = true;
            });
            form.render('checkbox');
        });
        $('.formCheckOther').click(function(){
            var child = $('.role-list-form').find('input');
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