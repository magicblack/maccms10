{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">
    <div class="my-btn-box lh30" >
        <div class="layui-btn-group fl">
            <a data-full="1" data-href="{:url('info')}?fpath={$curpath}&fname=" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
        </div>
        <div class="page-filter fr" >

        </div>
    </div>

    <form class="layui-form layui-form-pane" action="">
        <table class="layui-table mt10">
        <thead>
        <tr>
            <th>文件名</th>
            <th width="150">文件描述</th>
            <th width="150">文件大小</th>
            <th width="150">修改时间</th>
            <th width="200">调用代码</th>
            <th width="130">操作</th>
        </tr>
        </thead>
            {volist name="files" id="vo"}
            <tr>

                <th>{$vo.name}</a></th>
                <td>{$vo.note}</td>
                <td>{$vo.size}</td>
                <td>{$vo.time|mac_day=color}</td>
                <td>
                    <input id="txt{$key}" type="text" value='<script src="{literal}{$maccms.path_ads}{/literal}{$vo.name}"></script>' size="60">
                </td>
                <td>
                    <a class="layui-badge-rim j-clipboard" data-clipboard-target="#txt{$key}" href="javascript:;" title="复制">复制</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('info')}?fpath={$vo.path}&fname={$vo.name}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del')}?fname={$vo.fullname}" href="javascript:;" title="删除">删除</a>
                </td>
            </tr>
            {/volist}
        </tbody>
        <tfoot>
            <tr><td colspan="6">当前路径：{$curpath|str_replace='@','/',###}，共有<b class="red">{$num_file}</b>个文件,占用<b class="red">{$sum_size}</b>空间</td></tr>
        </tfoot>
    </table>
    </form>
</div>
{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript" src="__STATIC__/js/jquery.clipboard.js"></script>
<script type="text/javascript">
    var clipboard = new ClipboardJS('.j-clipboard');
    clipboard.on('success', function(e) {
       layer.msg('复制成功');
    });

    function data_info(path,name)
    {
        var index = layer.open({
            type: 2,
            shade:0.4,
            title: '编辑数据',
            content: "{:url('template/info')}?fpath="+path+'&fname='+name
        });

        layer.full(index);
    }

    function data_del(id)
    {
        if(!id){
            id  = checkIds('fname[]');
        }
        layer.confirm('确认要删除吗？', function (index) {
            location.href = "{:url('template/del')}?fname=" + id;
        });
    }

    $(function(){

    });
</script>
</body>
</html>