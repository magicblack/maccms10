{include file="../../../application/admin/view/public/head" /}
<div class="page-container">
    <form class="layui-form layui-form-pane" action="">
        <div class="layui-tab">
            <ul class="layui-tab-title">
                <li {if condition="$param.status eq ''"}class="layui-this"{/if}><a href="{:url('cj/publish')}?id={$param.id}">全部</a></li>
                <li {if condition="$param.status eq '1'"}class="layui-this"{/if}><a href="{:url('cj/publish')}?id={$param.id}&status=1">未采集</a></li>
                <li {if condition="$param.status eq '2'"}class="layui-this"{/if}><a href="{:url('cj/publish')}?id={$param.id}&status=2">已采集</a></li>
                <li {if condition="$param.status eq '3'"}class="layui-this"{/if}><a href="{:url('cj/publish')}?id={$param.id}&status=3">已发布</a></li>
            </ul>

            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">

                    <div class="layui-btn-group">
                        <a data-href="{:url('content_del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
                        <a data-href="{:url('content_del')}?ids=1&all=1" class="layui-btn layui-btn-primary j-ajax" confirm="确认清空数据吗？操作不可恢复"><i class="layui-icon">&#xe640;</i>清空</a>

                        <a data-href="{:url('content_into')}?id={$param.id}" data-ajax="no" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe654;</i>导入</a>
                        <a data-href="{:url('content_into')}?id={$param.id}&all=1" data-ajax="no" data-checkbox="no" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe654;</i>全部导入</a>
                    </div>

                    <table class="layui-table" lay-size="sm">
                        <thead>
                        <tr>
                            <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                            <th width="50">编号</th>
                            <th width="50">状态</th>
                            <th width="250">标题</th>
                            <th >网址</th>
                            <th width="40">操作</th>
                        </tr>
                        </thead>

                        {volist name="list" id="vo"}
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{$vo.id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                            <td>{$vo.id}</td>
                            <td>{if condition="$vo.status eq '1'"}未采集{elseif condition="$vo.status eq '2'"}已采集{else/}已发布{/if}</td>
                            <td>{$vo.title}</td>
                            <td>{$vo.url}</td>
                            <td>
                                <a class="layui-badge-rim " data-href="{:url('show?ids='.$vo['id'])}" href="javascript:;" title="查看">查看</a>
                            </td>
                        </tr>
                        {/volist}
                        </tbody>
                    </table>

                    <div id="pages" class="center"></div>

                </div>

            </div>
        </div>

    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript">
    var curUrl="{:url('cj/publish',$param)}";
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