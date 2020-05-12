{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <ul class="layui-tab-title mb10">
            <li class="layui-this"><a href="{:url('index')}">备份数据库</a></li>
            <li><a href="{:url('index')}?group=import">恢复数据库</a></li>
        </ul>

        <div class="layui-btn-group">
            <a data-href="{:url('export')}" class="layui-btn layui-btn-primary j-page-btns"><i class="layui-icon">&#xe62d;</i>备份数据库</a>
            <a data-href="{:url('optimize')}" class="layui-btn layui-btn-primary j-page-btns"><i class="layui-icon">&#xe631;</i>优化数据库</a>
            <a data-href="{:url('repair')}" class="layui-btn layui-btn-primary j-page-btns"><i class="layui-icon">&#xe60c;</i>修复数据库</a>
        </div>
    </div>

    <form id="pageListForm" class="layui-form">
        <table class="layui-table mt10" lay-even="" lay-skin="row">
            <colgroup>
                <col width="50">
            </colgroup>
            <thead>
            <tr>
                <th><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th>表名</th>
                <th>数据量</th>
                <th>大小</th>
                <th>冗余</th>
                <th>备注</th>
                <th width="90">操作</th>
            </tr>
            </thead>
            <tbody>
            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" class="layui-checkbox checkbox-ids" value="{$vo['Name']}" lay-skin="primary"></td>
                <td>{$vo['Name']}</td>
                <td>{$vo['Rows']}</td>
                <td>{$vo['Data_length']/1024|round=###,2} kb</td>
                <td>{$vo['Data_free']/1024|round=###,2} kb</td>
                <td>{$vo['Comment']}</td>
                <td>
                        <a data-href="{:url('optimize?ids='.$vo['Name'])}" class="layui-badge-rim j-ajax">优化</a>
                        <a data-href="{:url('repair?ids='.$vo['Name'])}" class="layui-badge-rim  j-ajax">修复</a>
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>
    </form>

</div>
{include file="../../../application/admin/view/public/foot" /}


<script type="text/javascript">
    layui.use(['form', 'layer'], function () {
        // 操作对象
        var form = layui.form
                , layer = layui.layer
                , $ = layui.jquery;



    });
</script>
</body>
</html>