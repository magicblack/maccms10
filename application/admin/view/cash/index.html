{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w150">
                    <select name="status">
                        <option value="">选择状态</option>
                        <option value="0" {if condition="$param['status'] eq '0'"}selected {/if}>待审核</option>
                        <option value="1" {if condition="$param['status'] eq '1'"}selected {/if}>已审核</option>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>

        <div class="layui-btn-group">
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
            <a data-href="{:url('del')}?ids=1&all=1" class="layui-btn layui-btn-primary j-ajax" confirm="确认清空数据吗？操作不可恢复"><i class="layui-icon">&#xe640;</i>清空</a>
            <a data-href="{:url('audit')}" class="layui-btn layui-btn-primary j-page-btns confirm" confirm="确认审核数据吗？操作不可恢复"><i class="layui-icon">&#xe640;</i>审核</a>
        </div>
    </div>

     <form class="layui-form " method="post" id="pageListForm">
         <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="50">编号</th>
                <th width="50">用户</th>
                <th width="50">状态</th>
                <th width="50">积分</th>
                <th width="50">金额</th>
                <th width="50">银行</th>
                <th width="50">账号</th>
                <th width="50">姓名</th>
                <th width="100">备注</th>
                <th width="100">时间</th>
                <th width="100">审核时间</th>
                <th width="50">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.cash_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.cash_id}</td>
                <td>[{$vo.user_id}]{$vo.user_name}</td>
                <td>{if condition="$vo.cash_status eq 1"}<span class="layui-badge layui-bg-green">已审核</span>{else/}<span class="layui-badge">未审核</span>{/if}</td>
                <td>{$vo.cash_points}</td>
                <td>{$vo.cash_money}</td>
                <td>{$vo.cash_bank_name}</td>
                <td>{$vo.cash_bank_no}</td>
                <td>{$vo.cash_payee_name}</td>
                <td>{$vo.cash_remarks}</td>
                <td>{$vo.cash_time|mac_day=color}</td>
                <td>{$vo.cash_time_audit|mac_day=color}</td>
                <td>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['cash_id'])}" href="javascript:;" title="删除">删除</a>
                    {if condition="$vo.cash_status neq '1'"}
                    <a class="layui-badge-rim j-ajax" confirm="确认审核数据吗？" data-href="{:url('audit?ids='.$vo['cash_id'])}" href="javascript:;" title="审核">审核</a>
                    {/if}
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
    var curUrl="{:url('cash/index',$param)}";
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