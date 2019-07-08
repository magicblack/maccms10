{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <div class="center mb10">
            <form class="layui-form " method="post"  id="searchForm">
                <div class="layui-input-inline w150">
                    <select name="sale_status">
                        <option value="">选择出售状态</option>
                        <option value="0" {if condition="$param['sale_status'] eq '0'"}selected {/if}>未出售</option>
                        <option value="1" {if condition="$param['sale_status'] eq '1'"}selected {/if}>已出售</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="use_status">
                        <option value="">选择使用状态</option>
                        <option value="0" {if condition="$param['use_status'] eq '0'"}selected {/if}>未使用</option>
                        <option value="1" {if condition="$param['use_status'] eq '1'"}selected {/if}>已使用</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="time">
                        <option value="">选择时间</option>
                        <option value="1" {if condition="$param['time'] eq '1'"}selected {/if}>最后一次</option>
                        <option value="0" {if condition="$param['time'] eq '0'"}selected {/if}>当天</option>
                        <option value="7" {if condition="$param['time'] eq '7'"}selected {/if}>一周内</option>
                        <option value="30" {if condition="$param['time'] eq '30'"}selected {/if}>一个月内</option>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
                <button class="layui-btn mgl-20" type="button" id="btnExport">导出</button>
            </form>
        </div>

        <div class="layui-btn-group">
            <a data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe" data-width="600px" data-height="400px"><i class="layui-icon">&#xe654;</i>添加</a>
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
                <th width="150">卡号</th>
                <th width="100">密码</th>
                <th width="100">面值</th>
                <th width="100">积分</th>
                <th width="100">创建时间</th>
                <th width="100">使用人</th>
                <th width="150">使用时间</th>
                <th width="50">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.card_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.card_id}</td>
                <td>{$vo.card_no}</td>
                <td>{$vo.card_pwd}</td>
                <td>{$vo.card_money}</td>
                <td>{$vo.card_points}</td>
                <td>{$vo.card_add_time|mac_day=color}</td>
                <td>{$vo.user_id}、{$vo.user.user_name}</td>
                <td>{if condition="$vo.card_use_time eq 0"} {else}{$vo.card_use_time|mac_day=color}{/if}</td>
                <td>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['card_id'])}" href="javascript:;" title="删除">删除</a>
                </td>
            </tr>
            {/volist}
            </tbody>
        </table>

        <div id="pages" class="center"></div>

    </form>
    <iframe id="if" width="0" height="0"></iframe>
</div>

{include file="../../../application/admin/view/public/foot" /}


<script type="text/javascript">
    var curUrl="{:url('card/index',$param)}";
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

        $('#btnExport').click(function(){
            var par = $('#searchForm').serialize() + '&export=1';

            $('#if').attr('src',"{:url('card/index')}?" + par);
        });
    });
</script>
</body>
</html>