{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">
        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>

        <div class="layui-btn-group">
            <a data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('batch')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe642;</i>修改</a>
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
        <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="100">编号</th>
                <th width="100">排序</th>
                <th width="150">类型</th>
                <th >名称</th>
                <th >地址</th>
                <th >logo</th>
                <th width="80">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.link_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.link_id}</td>
                <td><input type="input" name="link_sort[]" value="{$vo.link_sort}" class="layui-input"></td>
                <td>
                    <select name="link_type[]">
                        <option value="0" {if condition="$vo['link_type'] eq 0"}selected {/if}>文字链接</option>
                        <option value="1" {if condition="$vo['link_type'] eq 1"}selected {/if}>图片链接</option>
                    </select>
                </td>
                <td><input type="input" name="link_name[]" value="{$vo.link_name}" class="layui-input"></td>
                <td><input type="input" name="link_url[]" value="{$vo.link_url}" class="layui-input"></td>
                <td><input type="input" name="link_logo[]" value="{$vo.link_logo}" class="layui-input"></td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['link_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['link_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('link/index',$param)}";
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