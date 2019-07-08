{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <div class="layui-btn-group">
            <a data-href="{:url('info')}" data-full="1" class="layui-btn layui-btn-primary j-iframe" data-width="600px" data-height="400px"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('import')}" class="layui-btn layui-btn-primary layui-upload" ><i class="layui-icon">&#xe654;</i>导入</a>
        </div>
    </div>

    <form method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="50">编号</th>
                <th >名称</th>
                <th width="120">最后采集时间</th>
                <th width="250">内容操作</th>
                <th width="250">操作</th>
            </tr>
            </thead>
            <tbody>
            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.nodeid}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.nodeid}</td>
                <td>{$vo.name}</td>
                <td>{$vo.lastdate|mac_day}</td>
                <td>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-iframe" data-href="{:url('col_url')}?id={$vo.nodeid}">采集网址</a>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-iframe" data-href="{:url('col_content')}?id={$vo.nodeid}">采集内容</a>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-iframe" data-href="{:url('publish')}?id={$vo.nodeid}&status=2">内容发布</a>
                </td>
                <td>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-iframe" data-full="1" data-href="{:url('info')}?id={$vo.nodeid}" title="编辑">编辑</a>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-iframe" data-href="{:url('program')}?id={$vo.nodeid}" title="发布方案">发布方案</a>
                    <a class="layui-btn layui-btn-primary layui-btn-xs" href="{:url('export')}?id={$vo.nodeid}" title="导出">导出</a>
                    <a class="layui-btn layui-btn-primary layui-btn-xs j-tr-del" href="{:url('del')}?ids={$vo.nodeid}" title="删除">删除</a>
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>
        <div id="pages" class="center"></div>
    </form>

</div>
{include file="../../../application/admin/view/public/foot" /}
<!-- 注意：如果你直接复制所有代码到本地，上述js路径需要改成你本地的 -->
<script>
    layui.use(['form','laypage', 'layer','upload'], function() {
        // 操作对象
        var form = layui.form
                , layer = layui.layer
                , $ = layui.jquery
                , upload = layui.upload;

        upload.render({
            elem: '.layui-upload'
            ,url: "{:url('cj/import')}"
            ,method: 'post'
            ,exts:'txt'
            ,before: function(input) {
                layer.msg('文件上传中...', {time:3000000});
            },done: function(res, index, upload) {
                var obj = this.item;
                if (res.code == 0) {
                    layer.msg(res.msg);
                    return false;
                }
                location.reload();
            }
        });

    });
</script>
</body>
</html>