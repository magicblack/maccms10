{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <div class="my-toolbar-box">

        <div class="layui-btn-group">
            <a data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('index/select')}?tab=vod&col=status&tpl=select_state&url=timming/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>

                <th width="80">名称</th>
                <th width="150">描述</th>
                <th width="80">执行</th>
                <th width="80">状态</th>
                <th width="80">运行时间</th>
                <th width="100">操作</th>
            </tr>
            </thead>
            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.name}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.name}</td>
                <td>{$vo.des}</td>
                <td>{$vo.file}</td>
                <td>
                    <input type="checkbox" name="status" {if condition="$vo['status'] eq 1"}checked{/if} value="{$vo['status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=status&ids='.$vo['name'])}">
                </td>
                <td>{$vo.runtime|mac_day}</td>
                <td>
                    <a class="layui-badge-rim" target="_blank" href="{php}echo $GLOBALS['config']['site']['install_dir'];{/php}api.php/timming/index.html?name={$vo['name']|rawurlencode}" title="测试">测试</a>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info')}?id={$vo['name']|rawurlencode}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del')}?ids={$vo['name']|rawurlencode}" href="javascript:;" title="删除">删除</a>
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>

    </form>
</div>
{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">

</script>
</body>
</html>