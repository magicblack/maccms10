{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">

        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w150">
                    <select name="level">
                        <option value="">选择级别</option>
                        <option value="1" {if condition="$param['level'] eq '1'"}selected {/if}>一级分销</option>
                        <option value="2" {if condition="$param['level'] eq '2'"}selected {/if}>二级分销</option>
                        <option value="3" {if condition="$param['level'] eq '3'"}selected {/if}>三级分销</option>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <input type="hidden" name="uid" value="{$param['uid']}">
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>

        <div class="layui-btn-group">
            <a class="layui-btn ">一级分销总人数【{$data.level_cc_1}】总提成积分【{$data.points_cc_1}】</a>
            <a class="layui-btn layui-btn-normal">二级分销总人数【{$data.level_cc_2}】总提成积分【{$data.points_cc_2}】</a>
            <a class="layui-btn layui-btn-warm">三级分销总人数【{$data.level_cc_3}】总提成积分【{$data.points_cc_3}】</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="100">编号</th>
                <th>名称</th>
                <th width="120">会员组</th>
                <th width="120">状态</th>
                <th width="120">分销级别</th>
                <th width="130">注册时间</th>
            </tr>
            </thead>
            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.user_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.user_id}</td>
                <td>{$vo.user_name}</td>
                <td>{$vo.group_name}</td>
                <td>{if condition="$vo['user_status'] eq 1"}<span class="layui-badge layui-bg-green">正常</span>{else/}<span class="layui-badge">关闭</span>{/if}</td>
                <td>{if condition="$vo['user_pid'] eq $param['uid']"}一级分销{elseif condition="$vo['user_pid_2'] eq $param['uid']"}二级分销{else/}{/if}</td>
                <td>{$vo.user_reg_time|mac_day=color}</td>
            </tr>
            {/volist}
            </tbody>
        </table>
        <div id="pages" class="center"></div>
    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}

<script type="text/javascript">
    var curUrl="{:url('user/reward',$param)}";
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