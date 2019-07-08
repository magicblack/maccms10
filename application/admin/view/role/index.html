{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="my-toolbar-box">
        {if condition="$param.select neq 1"}
        <div class="center mb10">
            <form class="layui-form " method="post">
                <input type="hidden" value="{$param.select}" name="select">
                <input type="hidden" value="{$param.input}" name="input">
                <div class="layui-input-inline w150">
                    <select name="status">
                        <option value="">选择状态</option>
                        <option value="0" {if condition="$param['status'] eq '0'"}selected {/if}>未审核</option>
                        <option value="1" {if condition="$param['status'] eq '1'"}selected {/if}>已审核</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="level">
                        <option value="">选择推荐</option>
                        <option value="9" {if condition="$param['level'] eq '9'"}selected {/if}>推荐9-幻灯</option>
                        <option value="1" {if condition="$param['level'] eq '1'"}selected {/if}>推荐1</option>
                        <option value="2" {if condition="$param['level'] eq '2'"}selected {/if}>推荐2</option>
                        <option value="3" {if condition="$param['level'] eq '3'"}selected {/if}>推荐3</option>
                        <option value="4" {if condition="$param['level'] eq '4'"}selected {/if}>推荐4</option>
                        <option value="5" {if condition="$param['level'] eq '5'"}selected {/if}>推荐5</option>
                        <option value="6" {if condition="$param['level'] eq '6'"}selected {/if}>推荐6</option>
                        <option value="7" {if condition="$param['level'] eq '7'"}selected {/if}>推荐7</option>
                        <option value="8" {if condition="$param['level'] eq '8'"}selected {/if}>推荐8</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="pic">
                        <option value="">选择图片</option>
                        <option value="1" {if condition="$param['pic'] eq '1'"}selected{/if}>无图片</option>
                        <option value="2" {if condition="$param['pic'] eq '2'"}selected{/if}>远程图片</option>
                        <option value="3" {if condition="$param['pic'] eq '3'"}selected{/if}>同步出错图</option>
                    </select>
                </div>
                <div class="layui-input-inline w150">
                    <select name="order">
                        <option value="">选择排序</option>
                        <option value="role_time" {if condition="$param['order'] eq 'role_time'"}selected{/if}>更新时间</option>
                        <option value="role_id" {if condition="$param['order'] eq 'role_id'"}selected{/if}>编号</option>
                        <option value="role_hits" {if condition="$param['order'] eq 'role_hits'"}selected{/if}>总人气</option>
                        <option value="role_hits_month" {if condition="$param['order'] eq 'role_hits_month'"}selected{/if}>月人气</option>
                        <option value="role_hits_week" {if condition="$param['order'] eq 'role_hits_week'"}selected{/if}>周人气</option>
                        <option value="role_hits_day" {if condition="$param['order'] eq 'role_hits_day'"}selected{/if}>日人气</option>
                    </select>
                </div>

                <div class="layui-input-inline">
                    <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
                </div>
                <button class="layui-btn mgl-20 j-search" >查询</button>
            </form>
        </div>
        {/if}

        <div class="layui-btn-group">
            {if condition="$param.select eq 1 && $param.rid neq ''"}
            <a data-href="{:url('info')}?tab={$param.tab}&rid={$param.rid}" data-full="" class="layui-btn layui-btn-primary j-iframe"><i class="layui-icon">&#xe654;</i>添加</a>
            {/if}
            <a data-href="{:url('del')}" class="layui-btn layui-btn-primary j-page-btns confirm"><i class="layui-icon">&#xe640;</i>删除</a>
            <a data-href="{:url('index/select')}?tab=role&col=role_level&tpl=select_level&url=role/field" data-width="270" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>推荐</a>
            <a data-href="{:url('index/select')}?tab=role&col=role_hits&tpl=select_hits&url=role/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>点击</a>
            <a data-href="{:url('index/select')}?tab=role&col=role_status&tpl=select_status&url=role/field" data-width="470" data-height="100" data-checkbox="1" class="layui-btn layui-btn-primary j-select"><i class="layui-icon">&#xe620;</i>状态</a>
            <a class="layui-btn layui-btn-primary j-iframe" data-href="{:url('images/opt?tab=role')}" href="javascript:;" title="同步远程图片"><i class="layui-icon">&#xe620;</i>同步图片</a>
        </div>

    </div>


    <form class="layui-form " method="post" id="pageListForm">
        <table class="layui-table" lay-size="sm">
            <thead>
            <tr>
                <th width="25"><input type="checkbox" lay-skin="primary" lay-filter="allChoose"></th>
                <th width="50">编号</th>
                <th >视频名称</th>
                <th width="150">角色名称</th>
                <th width="150">演员名称</th>
                <th width="40">排序</th>
                <th width="40">人气</th>
                <th width="40">推荐</th>
                <th width="120">更新时间</th>
                <th width="80">操作</th>
            </tr>
            </thead>

            {volist name="list" id="vo"}
            <tr>
                <td><input type="checkbox" name="ids[]" value="{$vo.role_id}" class="layui-checkbox checkbox-ids" lay-skin="primary"></td>
                <td>{$vo.role_id}</td>
                <td><a target="_blank" class="layui-badge-rim " href="{:mac_url_vod_detail($vo.data)}">[{$vo.data.vod_name}]</a></td>
                <td> <a target="_blank" class="layui-badge-rim " href="{:mac_url_role_detail($vo)}">{$vo.role_name}</a> {if condition="$vo.role_status eq 0"} <span class="layui-badge">未审</span>{/if} {if condition="$vo.role_lock eq 1"} <span class="layui-badge">锁定</span>{/if}</td>
                <td>{$vo.role_actor}</td>
                <td>{$vo.role_sort}</td>
                <td>{$vo.role_hits}</td>
                <td><a data-href="{:url('index/select')}?tab=role&col=role_level&tpl=select_level&url=role/field&ids={$vo.role_id}" data-width="270" data-height="100" class=" j-select"><span class="layui-badge layui-bg-orange">{$vo.role_level}</span></a></td>
                <td>{$vo.role_time|mac_day=color}</td>
                <td>
                    <a class="layui-badge-rim j-iframe" data-full="" data-href="{:url('info?id='.$vo['role_id'])}" href="javascript:;" title="编辑">编辑</a>
                    <a class="layui-badge-rim j-tr-del" data-href="{:url('del?ids='.$vo['role_id'])}" href="javascript:;" title="删除">删除</a>
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
    var curUrl="{:url('role/data',$param)}";
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