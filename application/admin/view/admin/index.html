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
                <th width="140">上次登录时间</th>
                <th width="140">上次登录IP</th>
                <th width="130">登录次数</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.admin_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.admin_id}</td>
                <td>{$vo.admin_name}</td>
                <td>
                    <input type="checkbox" name="status" {if condition="$vo['admin_status'] eq 1"}checked{/if} value="{$vo['admin_status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=admin_status&ids='.$vo['admin_id'])}">
                </td>
                <td>{$vo.admin_last_login_time|mac_day=color}</td>
                <td>{$vo.admin_last_login_ip|long2ip}</td>
                <td>{$vo.admin_login_num}</td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['admin_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['admin_id'])}" href="javascript:;" title="删除">删除</a>
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>
        <div id="pages" class="center"></div>
    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">
    var curUrl="{:url('admin/index',$param)}";
    layui.use(['laypage', 'layer'], function() {
        var laypage = layui.laypage
                , layer = layui.layer;

        laypage.render({
            elem: 'pages'
            ,count: {$total}
            ,limit: {$limit}
            ,curr: {$page}
            ,layout: ['count', 'prev', 'page', 'next', 'limit', 'skip']
            ,jump: function(obj,first){
                if(!first){
                    location.href = curUrl.replace('%7Bpage%7D',obj.curr).replace('%7Blimit%7D',obj.limit);
                }
            }
        });
    });
</script>
</body>
</html>