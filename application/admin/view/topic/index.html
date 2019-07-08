{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box" >

        <div class="center mb10">
            <form class="layui-form " method="post">
                <div class="layui-input-inline w150">
                    <select name="status">
                        <option value="">选择状态</option>
                        <option value="0" {if condition="$param['status'] == '0'"}selected {/if}>未审核</option>
                        <option value="1" {if condition="$param['status'] == '1'"}selected {/if}>已审核</option>
                    </select>
                </div>
                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>
        <div class="layui-btn-group">
            <a data-full="1" data-href="{:url('info')}" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
            <a data-href="{:url('index/select')}?tab=topic&col=topic_level&tpl=select_level&url=topic/field" data-width="270" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>推荐</a>
            <a data-href="{:url('index/select')}?tab=topic&col=topic_status&tpl=select_status&url=topic/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
            <a class="layui-btn layui-btn-primary j-iframe" data-href="{:url('images/opt?tab=topic')}" href="javascript:;" title="同步远程图片"><i class="layui-icon">&#xe620;</i>同步图片</a>
        </div>
    </div>

    <form class="layui-form" method="post" id="pageListForm" >
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="100">编号</th>
                <th >名称</th>
                <th width="30">人气</th>
                <th width="30">评分</th>
                <th width="30">推荐</th>
                <th width="30">浏览</th>
                <th width="150">更新时间</th>
                <th width="100">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.topic_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.topic_id}</td>
                <td><a target="_blank" class="layui-badge-rim " href="{:mac_url_topic_detail($vo)}">{$vo.topic_name}</a> {if condition="$vo.topic_status eq 0"} <span class="layui-badge">未审</span>{/if} </td>
                <td>{$vo.topic_hits}</td>
                <td>{$vo.topic_score}</td>
                <td><a data-href="{:url('index/select')}?tab=topic&col=topic_level&tpl=select_level&url=topic/field&ids={$vo.topic_id}" data-width="270" data-height="100" class=" j-select"><span class="layui-badge layui-bg-orange">{$vo.topic_level}</span></a></td>
                <td>{if condition="$vo.ismake eq 1"}<a target="_blank" class="layui-badge layui-bg-green " href="{:mac_url_topic_detail($vo)}">Y</a>{else/}<a class="layui-badge" href="{:url('make/make?ac=topic_info')}?topic={$vo.topic_id}&ref=1">N</a>{/if}</td>
                <td>{$vo.topic_time|mac_day=color}</td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-full="1" data-href="{:url('info?id='.$vo['topic_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['topic_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('topic/data',$param)}";
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