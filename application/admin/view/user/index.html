{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">

        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w150">
                    <select name="status">
                        <option value="">选择状态</option>
                        <option value="0" {if condition="$param['status'] eq '0'"}selected {/if}>未审核</option>
                        <option value="1" {if condition="$param['status'] eq '1'"}selected {/if}>已审核</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="group">
                        <option value="">选择会员组</option>
                        {volist name="group_list" id="vo"}
                        <option value="{$vo.group_id}" {if condition="$param['group'] eq $vo.group_id"}selected {/if}>{$vo.group_name}</option>
                        {/volist}
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>

        <div class="layui-btn-group">
            <a data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
        </div>

    </div>

    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="100">编号</th>
                <th >名称</th>
                <th width="100">会员组</th>
                <th width="80">状态</th>
                <th width="80">积分</th>
                <th width="130">上次登录时间</th>
                <th width="130">上次登录IP</th>
                <th width="80">登录次数</th>
                <th width="220">相关数据</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.user_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.user_id}</td>
                <td>{$vo.user_name}</td>
                <td>{$vo.group_name}</td>
                <td>
                    <input type="checkbox" name="status" {if condition="$vo['user_status'] eq 1"}checked{/if} value="{$vo['user_status']}" lay-skin="switch" lay-filter="switchStatus" lay-text="正常|关闭" data-href="{:url('field?col=user_status&ids='.$vo['user_id'])}">
                </td>
                <td>{$vo.user_points}</td>
                <td>{$vo.user_login_time|mac_day=color}</td>
                <td>{$vo.user_login_ip|long2ip}</td>
                <td>{$vo.user_login_num}</td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('comment/data?uid='.$vo['user_id'])}" href="javascript:;" title="评论记录">评论记录</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('order/index?uid='.$vo['user_id'])}" href="javascript:;" title="订单记录">订单记录</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('ulog/index?uid='.$vo['user_id'])}" href="javascript:;" title="访问记录">访问记录</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('plog/index?uid='.$vo['user_id'])}" href="javascript:;" title="积分记录">积分记录</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('cash/index?uid='.$vo['user_id'])}" href="javascript:;" title="提现记录">提现记录</a>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('user/reward?uid='.$vo['user_id'])}" href="javascript:;" title="提现记录">三级分销</a>
                </td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['user_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['user_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('user/data',$param)}";
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