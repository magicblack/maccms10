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
                    <select name="mid">
                        <option value="">选择模块</option>
                        <option value="1" {if condition="$param['mid'] eq '1'"}selected {/if}>视频</option>
                        <option value="2" {if condition="$param['mid'] eq '2'"}selected {/if}>文章</option>
                        <option value="3" {if condition="$param['mid'] eq '3'"}selected {/if}>专题</option>
                    </select>
                </div>
                <div class="layui-input-inline w100">
                    <select name="report">
                        <option value="">选择举报</option>
                        <option value="1" {if condition="$param['report'] eq '1'"}selected {/if}>未举报</option>
                        <option value="2" {if condition="$param['report'] eq '2'"}selected {/if}>有举报</option>
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
            <a data-href="{:url('index/select')}?tab=comment&col=comment_status&tpl=select_status&url=comment/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
            <a data-href="{:url('del')}?all=1" class="layui-btn layui-btn-primary j-ajax" confirm="确认清空数据吗？操作不可恢复"><i class="layui-icon">&#xe640;</i>清空</a>
        </div>
    </div>

    <form class="layui-form" method="post" id="pageListForm" >
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="60">编号</th>
                <th width="60">模块</th>
                <th width="60">状态</th>
                <th >评论内容</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.comment_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.comment_id}</td>
                <td>{$vo.comment_mid|mac_get_mid_text}</td>
                <td>{if condition="$vo.comment_status eq 0"}<span class="layui-badge">未审核</span>{else}<span class="layui-badge layui-bg-green">已审核</span>{/if}</td>
                <td>
                    <div class="c-999 f-12">
                        <u style="cursor:pointer" class="text-primary">{$vo.comment_name}：</u>
                        <time>【{$vo.comment_time|mac_day=color}】</time>
                        <span class="ml-20">ip：【{$vo.comment_ip|long2ip}】</span>
                        <span class="ml-20">顶：【{$vo.comment_up}】</span>
                        <span class="ml-20">踩：【{$vo.comment_down}】</span>
                        <span class="ml-20">举报：【{$vo.comment_report}】</span>
                        <span class="ml-20">链接：
                            {if condition="!is_array($vo.data)"}
                            【数据已删除】
                            {elseif condition="$vo.comment_mid eq 1"}
                            【<a target="_blank" href="{$vo.data|mac_url_vod_detail}">{$vo.data.vod_name}</a>】</span>
                            {elseif condition="$vo.comment_mid eq 2"}
                            【<a target="_blank" href="{$vo.data|mac_url_art_detail}">{$vo.data.art_name}</a>】</span>
                            {elseif condition="$vo.comment_mid eq 3"}
                            【<a target="_blank" href="{$vo.data|mac_url_topic_detail}">{$vo.data.topic_name}</a>】</span>
                            {elseif condition="$vo.comment_mid eq 8"}
                            【<a target="_blank" href="{$vo.data|mac_url_actor_detail}">{$vo.data.actor_name}</a>】</span>
                            {elseif condition="$vo.comment_mid eq 9"}
                            【<a target="_blank" href="{$vo.data|mac_url_role_detail}">{$vo.data.role_name}</a>】</span>
                            {/if}
                    </div>
                    <div class="f-12 c-999">
                        评论：{$vo.comment_content}
                    </div>
                </td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-href="{:url('info?id='.$vo['comment_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['comment_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('comment/data',$param)}";
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