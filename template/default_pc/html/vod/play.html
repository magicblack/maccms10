<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width = device-width ,initial-scale = 1,minimum-scale = 1,maximum-scale = 1,user-scalable =no,"/>.
    <title>在线播放{$obj.vod_name} {$obj['vod_play_list'][$param['sid']]['urls'][$param['nid']]['name']} - 高清资源 - {$maccms.site_name}</title>
    <meta name="keywords" content="{$obj.vod_name}{$obj['vod_play_list'][$param['sid']]['urls'][$param['nid']]['name']}免费在线观看,{$obj.vod_name}剧情介绍" />
    <meta name="description" content="{$obj.vod_name}{$obj['vod_play_list'][$param['sid']]['urls'][$param['nid']]['name']}免费在线观看,{$obj.vod_name}剧情介绍" />
    {include file="public/include"}
</head>
<body>
{include file="public/head"}
<!--当前位置-->
<div class="bread-crumb-nav fn-clear">
    <ul class="bread-crumbs">
        <li class="home"><a href="{$maccms.path}">首页</a></li>
        {if condition="$obj.type_1.type_id neq '' "}
        <li><a href="{:mac_url_type($obj.type_1)}">{$obj.type_1.type_name}</a></li>
        {/if}
        <li><a href="{:mac_url_type($obj.type)}">{$obj.type.type_name}</a></li>
        <li>{$obj.vod_name} {$obj['vod_play_list'][$param['sid']]['urls'][$param['nid']]['name']}在线点播</li>
        <li class="back">
            <a href="javascript:;" onclick="MAC.Gbook.Report('编号【{$obj.vod_id}】名称【{$obj.vod_name}】不能观看请检查修复，页面地址' + location.href,'{$obj.vod_id}')">【报错】</a>
            <a href="{$obj.player_info.link_pre}">【上一集】</a>
            <a href="{$obj.player_info.link_next}">【下一集】</a>
        </li>
    </ul>
</div>
<!--播放器-->
<div class="ui-box" id="detail-box">
    <div id="bofang_box">
        {$player_data}
        {$player_js}
    </div>
</div>
<!--在线播放地址-->
{maccms:foreach name="obj.vod_play_list" id="vo"}
<div class="ui-box marg" id="playlist_{$key}">
    <div class="down-title">
        <h2>{$vo.player_info.show}-在线播放</h2><span>[{$vo.player_info.tip}]</span>
    </div>
    <div class="video_list fn-clear">
        {maccms:foreach name="vo.urls" id="vo2" key="key2"}
        <a data-i="{$key2}" href="{:mac_url_vod_play($obj,['sid'=>$vo.sid,'nid'=>$vo2.nid])}" {if condition="$param.sid eq $vo.sid && $param.nid eq $vo2.nid"}class="cur" {/if} >{$vo2.name}</a>
        {/maccms:foreach}
    </div>
</div>
{/maccms:foreach}

<!--猜你喜欢-->
<div class="ui-box marg" id="xihuan">
    <div class="ui-title">
        <h2>喜欢看<strong>“{$obj.vod_name}”</strong>的人也喜欢</h2>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="6" type="current" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p></p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>
<!-- 剧情介绍 -->
<div class="ui-box marg" id="juqing" >
    <div class="ui-title">
        <h3>剧情介绍</h3>
    </div>
    <div class="tjuqing">
        {$obj.vod_content}
    </div>
</div>
<!--PC版评论-->
<div class="ui-box marg" id="pinglun" >
    <div class="ui-title">
        <h3>评论</h3>
    </div>
    <div class="mac_comment" data-id="{$obj.vod_id}" data-mid="{$maccms.mid}" ></div>
    <script>
        $(function(){
            MAC.Comment.Login = {$comment.login};
            MAC.Comment.Verify = {$comment.verify};
            MAC.Comment.Init();
            MAC.Comment.Show(1);
        });
    </script>
</div>

<span style="display:none" class="mac_ulog_set" alt="设置播放页浏览记录" data-type="4" data-mid="{$maccms.mid}" data-id="{$obj.vod_id}" data-sid="{$param.sid}" data-nid="{$param.nid}"></span>

{include file="public/foot"}
</body>
</html>
