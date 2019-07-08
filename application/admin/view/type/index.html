{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">
        <div class="layui-btn-group">
            <a data-full="1" data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('batch')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe642;</i>修改</a>
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
            <a data-href="{:url('index/select')}?tab=type&col=type_status&tpl=select_status&url=type/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
            <a data-href="{:url('index/select')}?tab=type&col=type_status&tpl=select_type&url=type/move" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>转移</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
        <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th>名称</th>
                <th width="50">状态</th>
                <th width="40">类型</th>
                <th width="40">排序</th>
                <th width="80">名称</th>
                <th width="120">英文名</th>
                <th width="100">分类页模版</th>
                <th width="100">筛选页模版</th>
                <th width="100">内容页模版</th>
                <th width="130">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.type_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.type_id}、<a target="_blank" class="layui-badge-rim " href="{:mac_url_type($vo)}">{$vo.type_name}</a> <span class="layui-badge">{$vo.cc}</span></td>
                <td>
                    <input type="checkbox" name="status" {if condition="$vo['type_status'] eq 1"}checked{/if} value="{$vo['type_status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=type_status&ids='.$vo['type_id'])}">
                </td>
                <td>{if condition="$vo.type_mid eq 1"} <span class="label label-success radius	">视频</span>{else}<span class="label label-danger radius">文章</span>{/if}</td>
                <td><input type="input" name="type_sort_{$vo.type_id}" value="{$vo.type_sort}" class="layui-input"></td>
                <td><input type="input" name="type_name_{$vo.type_id}" value="{$vo.type_name}" class="layui-input"></td>
                <td><input type="input" name="type_en_{$vo.type_id}" value="{$vo.type_en}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_{$vo.type_id}" value="{$vo.type_tpl}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_list_{$vo.type_id}" value="{$vo.type_tpl_list}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_detail_{$vo.type_id}" value="{$vo.type_tpl_detail}" class="layui-input"></td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('info?id='.$vo['type_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['type_id'])}" href="javascript:;" title="删除">删除</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('info')}?pid={$vo.type_id}" href="javascript:;" title="添加">添加</a>
                </td>
            </tr>
            {volist name="vo.child" id="ch"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$ch.type_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>&nbsp;&nbsp;&nbsp;&nbsp;├&nbsp;{$ch.type_id}、<a target="_blank" class="layui-badge-rim " href="{:mac_url_type($ch)}">{$ch.type_name}</a> <span class="layui-badge">{$ch.cc}</span></td>
                <td>
                    <input type="checkbox" name="status" {if condition="$ch['type_status'] eq 1"}checked{/if} value="{$ch['type_status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=type_status&ids='.$ch['type_id'])}">
                </td>
                <td>{if condition="$ch.type_mid eq 1"} <span class="label label-success radius	">视频</span>{else}<span class="label label-danger radius">文章</span>{/if}</td>
                <td><input type="input" name="type_sort_{$ch.type_id}" value="{$ch.type_sort}" class="layui-input"></td>
                <td><input type="input" name="type_name_{$ch.type_id}" value="{$ch.type_name}" class="layui-input"></td>
                <td><input type="input" name="type_en_{$ch.type_id}" value="{$ch.type_en}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_{$ch.type_id}" value="{$ch.type_tpl}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_list_{$ch.type_id}" value="{$ch.type_tpl_list}" class="layui-input"></td>
                <td><input type="input" name="type_tpl_detail_{$ch.type_id}" value="{$ch.type_tpl_detail}" class="layui-input"></td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('info?id='.$ch['type_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$ch['type_id'])}" href="javascript:;" title="删除">删除</a>
                </td>
            </tr>


            {/volist}

            {/volist}
            </tbody>
        </table>

    </form>
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