<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$param.wd}{$param.actor}{$param.director}{$param.area}{$param.lang}{$param.year}{$param.class}搜索结果 - {$maccms.site_name}</title>
    <meta name="keywords" content="{$param.wd}{$param.actor}{$param.director}{$param.area}{$param.lang}{$param.year}{$param.class}搜索结果" />
    <meta name="description" content="{$param.wd}{$param.actor}{$param.director}{$param.area}{$param.lang}{$param.year}{$param.class}搜索结果" />
    {include file="public/include"}
</head>
<body>
{include file="public/head"}
<!--当前位置-->
<div class="bread-crumb-nav fn-clear">
    <ul class="bread-crumbs">
        <li class="home"><a href="{$maccms.path}">首页</a></li>
        <li>搜索" <strong style="color:#4c8fe8;">{$param.wd}{$param.actor}{$param.director}{$param.area}{$param.lang}{$param.year}{$param.class}</strong>" 结果 " <strong style="color:#4c8fe8" class="mac_total"></strong>" 个资源</li>
        <li class="back"><a href="javascript:MAC.GoBack()">返回上一页</a></li>
    </ul>
</div>

<div class="ui-bar fn-clear">
    <div class="view-filter">
        <a href="{:mac_url_search(['wd'=>$param['wd'],'area'=>$param['area'],'lang'=>$param['lang'],'year'=>$param['year'],'level'=>$param['level'],'letter'=>$param['letter'],'state'=>$param['state'],'tag'=>$param['tag'],'class'=>$param['class'],'order'=>$param['order'],'by'=>'time' ],'vod')}" class="order {if condition="$param.by eq '' || $param.by eq 'time'"}current{/if}">按时间</a>
        <a href="{:mac_url_search(['wd'=>$param['wd'],'area'=>$param['area'],'lang'=>$param['lang'],'year'=>$param['year'],'level'=>$param['level'],'letter'=>$param['letter'],'state'=>$param['state'],'tag'=>$param['tag'],'class'=>$param['class'],'order'=>$param['order'],'by'=>'hits' ],'vod')}" class="order {if condition="$param.by eq 'hits'"}current{/if}">按人气</a>
        <a href="{:mac_url_search(['wd'=>$param['wd'],'area'=>$param['area'],'lang'=>$param['lang'],'year'=>$param['year'],'level'=>$param['level'],'letter'=>$param['letter'],'state'=>$param['state'],'tag'=>$param['tag'],'class'=>$param['class'],'order'=>$param['order'],'by'=>'score' ],'vod')}" class="order {if condition="$param.by eq 'score'"}current{/if}">按评分</a>
    </div>
</div>

<!--搜索结果-->
<div class="ui-box ui-qire fn-clear" id="list-focus">
    <ul class="show-list">
        {maccms:vod num="10" paging="yes" pageurl="vod/search" order="desc" by="time"}
        <li><a class="play-img" href="{:mac_url_vod_detail($vo)}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}" /></a>
        <div class="play-txt">
            <h2><a href="{:mac_url_vod_detail($vo)}">{$vo.vod_name}</a></h2>
            <dl><dt>主演：</dt><dd>{$vo.vod_actor}</dd></dl>
            <dl class="fn-left"><dt>状态：</dt><dd><span class="color">{if condition="$vo.vod_serial gt 0"}第{$vo.vod_serial}集/共{$vo.vod_total}集{else/}{$vo.vod_remarks}{/if}</span></dd></dl>
            <dl class="fn-right"><dt>导演：</dt><dd>{$vo.vod_director}</dd></dl>
            <dl class="fn-left"><dt>类型：</dt><dd>{$vo.type.type_name}</dd></dl>
            <dl class="fn-right"><dt>地区：</dt><dd><span>{$vo.vod_area}</span></dd></dl>
            <dl class="fn-left"><dt>时间：</dt><dd><span id="addtime">{$vo.vod_time|date='Y-m-d',###}</span></dd></dl>
            <dl class="fn-right"><dt>年份：</dt><dd><span>{$vo.vod_year|mac_default}</span></dd></dl>
            <dl class="juqing"><dd>剧情：{$vo.vod_blurb}……<a class="link detail-desc" href="{:mac_url_vod_detail($vo)}">详细剧情</a></dd></dl>
        </div></li>
        {/maccms:vod}

    </ul>
    <div class="ui-bar list-page fn-clear">
        {include file="public/paging"}
    </div>
    <script>
        $('.mac_total').html('{$__PAGING__.record_total}');
    </script>

</div>
<!--猜你喜欢-->
<div class="ui-box marg" id="xihuan">
    <div class="ui-title">
        <h2>猜你喜欢</h2>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="6" type="current" order="desc" by="hits_week"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_remarks}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>

{include file="public/foot"}
</body>
</html>

