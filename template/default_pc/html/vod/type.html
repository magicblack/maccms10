<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>最新{$obj.type_title}-推荐{$obj.type_title}-第{$param.page}页 - {$maccms.site_name}</title>
    <meta name="keywords" content="{$obj.type_key}" />
    <meta name="description" content="{$obj.type_des}" />
    {include file="public/include"}
</head>
<body>
{include file="public/head"}
<!--频道banner-->
<div class="channel-focus">
    <div class="channel-silder layout">
        <ul class="channel-silder-cnt">
            {maccms:vod num="9" type="current" level="1,2,3,4,5,6,7,8,9" order="desc" by="time"}
            <li class="channel-silder-panel fn-clear"><a class="channel-silder-img" href="{:mac_url_vod_detail($vo)}"><img src="{:mac_url_img($vo.vod_pic)}" title="{$vo.vod_name}" /></a>
                <div class="channel-silder-intro">
                    <div class="channel-silder-title">
                        <h2><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h2>{$vo.vod_version}<span><i>{$vo.vod_score}</i> 分</span></div>
                    <ul class="channel-silder-info fn-clear">
                        <li class="long">主演：<span>{$vo.vod_actor}</span></li>
                        <li>类型：<span>{$vo.type.type_name}</span></li>
                        <li>导演：<span>{$vo.vod_director}</span></li>
                        <li>地区：<span>{$vo.vod_area}</span></li>
                        <li>年份：<span>{$vo.vod_year|mac_default}</span></li>
                    </ul>
                    <p class="channel-silder-desc">剧情：<span>{$vo.vod_blurb}...</span></p><a class="channel-silder-play" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">立即观看</a></div>
            </li>
            {/maccms:vod}
        </ul>
        <ul class="channel-silder-nav fn-clear">
            {maccms:vod num="9" type="current" level="1,2,3,4,5,6,7,8,9" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" ><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>
<script type="text/javascript">
    jQuery(".channel-silder").slide({
        titCell:".channel-silder-nav li",
        mainCell:".channel-silder-cnt",
        delayTime:800,
        triggerTime:0,
        interTime:5000,
        pnLoop:false,
        autoPage:false,
        autoPlay:true
    });
</script>
<!-- 条件搜索 -->
<div class="directory-item" id="tv-directory">
    <ul class="directory-list">
        <li>
            <dl class="leixing">
                <dt>按类型<i class="iconfont"></i></dt>
                {empty name="$obj.type_extend.class"}
                    {maccms:foreach name=":explode(',',$obj.parent.type_extend.class)"}
                    <dd><a href="{:mac_url_type($obj,['class'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {else /}
                    {maccms:foreach name=":explode(',',$obj.type_extend.class)"}
                    <dd><a href="{:mac_url_type($obj,['class'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {/empty}
            </dl>
        </li>
        <li>
            <dl class="area">
                <dt>按地区<i class="iconfont"></i></dt>
                {empty name="$obj.type_extend.area"}
                    {maccms:foreach name=":explode(',',$obj.parent.type_extend.area)"}
                    <dd><a href="{:mac_url_type($obj,['class'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {else /}
                    {maccms:foreach name=":explode(',',$obj.type_extend.area)"}
                    <dd><a href="{:mac_url_type($obj,['area'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {/empty}
            </dl>
        </li>
        <li>
            <dl>
                <dt>按年代<i class="iconfont"></i></dt>
                {empty name="$obj.type_extend.year"}
                    {maccms:foreach name=":explode(',',$obj.parent.type_extend.year)"}
                    <dd><a href="{:mac_url_type($obj,['class'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {else /}
                    {maccms:foreach name=":explode(',',$obj.type_extend.year)"}
                    <dd><a href="{:mac_url_type($obj,['year'=>$vo],'show')}">{$vo}</a></dd>
                    {/maccms:foreach}
                {/empty}
            </dl>
        </li>
    </ul>
</div>
<!--频道循环分类影视-->
<div class="fn-clear" id="channel-box">
    <!-- 左侧影视 -->
    <div class="qire-box">
        {maccms:type parent="current" order="asc" by="sort"}
        <div class="channel-item">
            <div class="ui-title fn-clear"><span><a href="{:mac_url_type($vo)}">查看更多></a></span><h2>{$vo.type_name}</h2></div>
            <div class="box_con">
                <ul class="img-list">
                    {maccms:vod num="8" type="'.$vo['type_id'].'" order="desc" by="time"}
                    <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
                    {/maccms:vod}
                </ul>
            </div>
        </div>
        {/maccms:type}

        {if condition="$obj.childids eq '' "}
        <div class="channel-item">
            <div class="ui-title fn-clear"><span><a href="{:mac_url_type($obj)}">查看更多></a></span><h2>{$obj.type_name}</h2></div>
            <div class="box_con">
                <ul class="img-list">
                    {maccms:vod num="24" paging="yes" type="current" order="desc" by="time"}
                    <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
                    {/maccms:vod}
                </ul>
            </div>
        </div>
        <!-- 分页 -->
        <div class="ui-bar list-page fn-clear">
            {include file="public/paging"}
        </div>
        {/if}

    </div>
    <!-- 右侧排行 -->
    <div class="qire-l">
        <div class="ui-ranking">
            <h3>热播排行榜</h3>
            <ul class="ranking-list">
                {maccms:vod num="15" type="current" order="desc" by="hits_month"}
                <li><span>{$vo.vod_hits_month}</span><i {if condition="$key lt 4"} class="stress" {/if}>{$key}</i><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></li>
                {/maccms:vod}
            </ul>
        </div>
        <div class="ui-ranking">
            <h3>好评排行榜</h3>
            <ul class="ranking-list">
                {maccms:vod num="15" type="current" order="desc" by="score"}
                <li><span>{$vo.vod_score}</span><i {if condition="$key lt 4"}class="stress" {/if}>{$key}</i><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></li>
                {/maccms:vod}
            </ul>
        </div>
        <div class="ui-ranking">
            <h3>最新上映排行</h3>
            <ul class="ranking-list">
                {maccms:vod num="15" type="current" order="desc" by="time"}
                <li><span>{$vo.vod_hits}</span><i {if condition="$key lt 4"}class="stress" {/if}>{$key}</i><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></li>
                {/maccms:vod}
            </ul>
        </div>
    </div>
</div>
{include file="public/foot"}
</body>
</html>