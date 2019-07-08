{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >
        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w100">
                    <select name="status">
                        <option value="">选择状态</option>
                        <option value="0" {if condition="$param['status'] == '0'"}selected {/if}>未审核</option>
                        <option value="1" {if condition="$param['status'] == '1'"}selected {/if}>已审核</option>
                    </select>
                </div>
                <div class="layui-input-inline w100">
                    <select name="type">
                        <option value="">选择回复状态</option>
                        <option value="1" {if condition="$param['reply'] eq '1'"}selected {/if}>未回复</option>
                        <option value="2" {if condition="$param['reply'] eq '2'"}selected {/if}>已回复</option>
                    </select>
                </div>
                <div class="layui-input-inline w100">
                    <select name="type">
                        <option value="">选择类型</option>
                        <option value="1" {if condition="$param['type'] eq '1'"}selected {/if}>留言数据</option>
                        <option value="2" {if condition="$param['type'] eq '2'"}selected {/if}>报错数据</option>
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
            <a data-href="{:url('index/select')}?tab=gbook&col=gbook_status&tpl=select_status&url=gbook/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
            <a data-href="{:url('del')}?all=1" class="layui-btn layui-btn-primary j-ajax" confirm="确认清空数据吗？操作不可恢复"><i class="layui-icon">&#xe640;</i>清空</a>
        </div>
    </div>


        <form class="layui-form" method="post" id="pageListForm" >
            <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="60">编号</th>
                <th width="60">状态</th>
                <th width="60">类型</th>
                <th >留言内容</th>
                <th >回复内容</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.gbook_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.gbook_id}</td>
                <td>{if condition="$vo.gbook_status eq 0"}<span class="layui-badge">未审核</span>{else}<span class="layui-badge layui-bg-green">已审核</span>{/if}</td>
                <td>{if condition="$vo.gbook_rid eq 0"}留言数据{else/}报错数据{/if}</td>
                <td>
                    <div class="c-999 f-12">
                        <u style="cursor:pointer" class="text-primary">{$vo.gbook_name}：</u>
                        <time>【{$vo.gbook_time|mac_day=color}】</time>
                        <span class="ml-20">ip：【{$vo.gbook_ip|long2ip}】</span>
                    </div>
                    <div class="f-12 c-999">
                        <span class="ml-20">状态：</span>
                        留言：{$vo.gbook_content}
                    </div>
                </td>
                <td>
                    <div class="c-999 f-12">
                        回复时间：{$vo.gbook_reply_time|mac_day=color}
                    </div>
                    <div class="f-12 c-999">
                        回复：{$vo.gbook_reply}
                    </div>
                    <div> </div>
                </td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['gbook_id'])}" href="javascript:;" title="回复">回复</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['gbook_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('gbook/data',$param)}";
    layui.use(['laypage', 'layer','form'], function() {
        var laypage = layui.laypage
                , layer = layui.layer,
                form = layui.form;

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