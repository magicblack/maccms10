{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">

        <div class="layui-btn-group">
            <a data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="100">编号</th>
                <th >名称</th>
                <th width="100">状态</th>
                <th width="100">包天</th>
                <th width="100">包周</th>
                <th width="100">包月</th>
                <th width="100">包年</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td>
                    {if condition="$vo['group_id'] gt 2"}
                    <input type="checkbox" name="ids[]" value="{$vo.group_id}" class="layui-checkbox checkbox-ids" lay-skin="primary">
                    {/if}
                </td>
                <td>{$vo.group_id}</td>
                <td>{$vo.group_name}</td>
                <td>
                    {if condition="$vo['group_id'] gt 2"}
                    <input type="checkbox" name="status" {if condition="$vo['group_status'] eq 1"}checked{/if} value="{$vo['group_status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=group_status&ids='.$vo['group_id'])}">
                    {/if}
                </td>
                <td>{$vo.group_points_day}</td>
                <td>{$vo.group_points_week}</td>
                <td>{$vo.group_points_month}</td>
                <td>{$vo.group_points_year}</td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['group_id'])}" href="javascript:;" title="编辑">编辑</a>
                    {if condition="$vo['group_id'] gt 2"}
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['group_id'])}" href="javascript:;" title="删除">删除</a>
                    {/if}
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>

    </form>

    <blockquote class="layui-elem-quote layui-quote-nm">
        提示信息：<br>
        1.游客、普通会员属于系统内置会员组,无法删除和禁用; <br>2.请单独设置每个会员组的权限,不会向下继承权限;
    </blockquote>
</div>

{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">

    layui.use(['laypage', 'layer'], function() {
        var laypage = layui.laypage
                , layer = layui.layer;


    });
</script>
</body>
</html>