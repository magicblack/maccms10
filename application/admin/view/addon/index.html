{include file="../../../application/admin/view/public/head" /}
<div class="page-container p10">

    <div class="layui-tab layui-tab-brief" lay-filter="tabs">
        <ul class="layui-tab-title">
            <li class="layui-this btn-local" data-href="{:url('downloaded')}">本地应用</li>
            <li class="btn-online" data-href="http://api.maccms.com/addon/index">在线商店</li>
            <li class="layui-upload" data-href="{:url('add')}">离线安装</li>
            <li class="">绑定账号</li>
        </ul>
        <div class="layui-tab-content">
            <div class="layui-tab-item layui-show">

    <div class="my-toolbar-box" >
        <div class="center mb10">
        <form class="layui-form " method="post">
            <div class="layui-input-inline w300">
                <input type="text" autocomplete="off" placeholder="请输入搜索条件" class="layui-input" name="wd" value="{$param['wd']}">
            </div>
            <button class="layui-btn mgl-20 j-search" >查询</button>
        </form>
        </div>
    </div>

    <form class="layui-form p10 " method="post" id="pageListForm">
        <div class="layui-row layui-col-space15" id="addon_list">

        </div>
        <div id="pages" class="center"></div>
    </form>
</div>
            </div>
        </div>
    </div>

{include file="../../../application/admin/view/public/foot" /}


<script type="text/javascript">
    var url='';
    layui.use(['form','laypage', 'layer','upload','element'], function() {
        // 操作对象
        var form = layui.form
                , layer = layui.layer
                , upload = layui.upload
                ,element = layui.element;

        //监听Tab切换
        element.on('tab(tabs)', function(data){
            if(data.index <2){
                url = $(this).attr('data-href');
                load_list();
            }
        });

        upload.render({
            elem: '.layui-upload'
            ,url: "{:url('addon/local')}"
            ,method: 'post'
            ,exts:'zip'
            ,before: function(input) {
                layer.msg('文件上传中...', {time:3000000});
            },done: function(res, index, upload) {
                var obj = this.item;
                if (res.code == 0) {
                    layer.msg(res.msg);
                    return false;
                }
                layer.closeAll();
            }
        });


        $(document).on('click', '.btn-disable,.btn-enable', function() {
            $.ajax({
                type: 'get',
                dataType:'json',
                url: "{:url('addon/state')}",
                data:{name:$(this).attr('data-name'),action:$(this).attr('data-action'),force:0},
                success:function($r){
                    if($r.code ==1){
                        load_list();
                    }
                    layer.msg($r.msg);
                },
                complete:function(){

                }
            });
        });
        $(document).on('click', '.btn-install', function() {

        });
        $(document).on('click', '.btn-uninstall', function() {
            $.ajax({
                type: 'get',
                dataType:'json',
                url: "{:url('addon/uninstall')}",
                data:{name:$(this).attr('data-name'),force:0},
                success:function($r){
                    if($r.code ==1){
                        load_list();
                    }
                    layer.msg($r.msg);
                },
                complete:function(){

                }
            });
        });
        $(document).on('click', '.btn-info', function() {

        });

        $('.btn-local').click();
    });

    function load_list(){
        var h='';
        $('#addon_list').html('');
        layer.msg('数据请求中...',{time:500000});

        $.ajax({
            type: 'get',
            dataType:'jsonp',
            url: url,
            success:function($r){
                $.each($r.rows,function(i,row){
                    h ='<div class="layui-col-md3"><div class="addon"> <a href="#" target="_blank"> <img src="'+row.image+'" class="add-logo"> </a> <div class="addon-caption"> <h4>'+row.title+'<span class="layui-badge layui-bg-green">推荐</span></h4> <p><b>￥'+row.price+'</b></p> <p>作者: '+row.author+'</p> <p>介绍: '+row.intro+'</p> <p>版本: '+row.version+'</p> <p>添加时间: '+ getDataTime(row.createtime)+'</p>';
                    h+='<div class="operate"><span class="btn-group">';
                    if(row.install =='1'){
                        h+='<a href="javascript:;" class="layui-btn layui-btn-normal layui-btn-sm j-iframe" data-name="'+row.name+'" data-href="{:url('config')}?name='+row.name+'" ><i class="layui-icon">&#xe614;</i>配置</a>';
                        if(row.state=='1'){
                            h+='<a href="javascript:;" class="layui-btn layui-btn-warm layui-btn-sm btn-disable" data-name="'+row.name+'" data-action="disable"><i class="layui-icon">&#x1006;</i>禁用</a>';
                        }
                        else{
                            h+='<a href="javascript:;" class="layui-btn  layui-btn-sm btn-enable" data-name="'+row.name+'" data-action="enable"><i class="layui-icon">&#xe605;</i>启用</a><a href="javascript:;" class="layui-btn layui-btn-danger layui-btn-sm btn-uninstall" data-name="'+row.name+'"><i class="layui-icon">&#x1006;</i>卸载</a>';
                        }
                    }
                    else{
                        h+='<a href="javascript:;" class="layui-btn layui-btn-sm btn-install"><i class="layui-icon">&#xe601;</i>安装</a>';
                    }
                    h+='</span> <span class="fr" style="margin-top:10px;"> <a href="javascript:;" class="btn-info text-gray " data-name="'+row.name+'" title="详情"><i class="layui-icon">&#xe63c;</i></a> </span> </div> </div> </div> </div>';

                    $('#addon_list').append(h);
                });
            },
            complete:function(){
                layer.closeAll();
            }
        });
    }

</script>
</body>
</html>