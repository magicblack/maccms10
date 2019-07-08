{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w150">
                    <select name="type">
                        <option value="">选择类型</option>
                        <option value="1" {if condition="$param['type'] eq '1'"}selected {/if}>积分充值</option>
                        <option value="2" {if condition="$param['type'] eq '2'"}selected {/if}>注册推广</option>
                        <option value="3" {if condition="$param['type'] eq '3'"}selected {/if}>访问推广</option>
                        <option value="4" {if condition="$param['type'] eq '4'"}selected {/if}>三级分销</option>
                        <option value="7" {if condition="$param['type'] eq '7'"}selected {/if}>积分升级</option>
                        <option value="8" {if condition="$param['type'] eq '8'"}selected {/if}>积分消费</option>
                        <option value="9" {if condition="$param['type'] eq '9'"}selected {/if}>积分提现</option>
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
        </div>
    </div>

     <form class="layui-form " method="post" id="pageListForm">
         <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="80">编号</th>
                <th width="100">用户</th>
                <th width="50">类型</th>
                <th width="50">积分</th>
                <th width="200">备注</th>
                <th width="140">日志时间</th>
                <th width="50">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.ulog_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.plog_id}</td>
                <td>[{$vo.user_id}]{$vo.user_name}</td>
                <td>{$vo.plog_type|mac_get_plog_type_text}</td>
                <td>{if condition="in_array($vo.plog_type,[1,2,3,4])"}+{else/}-{/if}{$vo.plog_points}</td>
                <td>{$vo.plog_remarks}</td>
                <td>{$vo.plog_time|mac_day=color}</td>
                <td>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['plog_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('plog/index',$param)}";
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