<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$maccms.site_name}</title>
    <meta name="keywords" content="{$maccms.site_keywords}" />
    <meta name="description" content="{$maccms.site_description}" />
    {include file="public/include"}
</head>
<body>
{include file="public/head"}
<!-- banner 开始 -->
<div class="banner">
    <div id="lbsub">
        <div class="deansubdiv"><dl>
            {maccms:type ids="1,2,3,4" order="asc" by="sort" id="vo1" key="key1"}
            <dd class="deanddt{$key}">
                <h3><a href="{:mac_url_type($vo1)}">{$vo1.type_name} </a><span></span></h3>
                <div class="deansubpt">
                    {if condition="$key1 lt 3"}
                    <div class="deansubptpp">
                        <h5>类型</h5>
                        <div class="deansubptc">
                            <a href="{:mac_url_type($vo1,[],'show')}">全部</a>
                            {maccms:type parent="'.$vo1['type_id'].'" order="asc" by="sort" id="vo2" key="key2"}
                                <a href="{:mac_url_type($vo2,[],'show')}">{$vo2.type_name}</a>
                            {/maccms:type}
                        </div>
                    </div>
                    {/if}
                    <div class="deansubptpp">
                        <h5>地区</h5>
                        <div class="deansubptc">
                            <a href="{:mac_url_type($vo1,[],'show')}">全部</a>
                            {maccms:foreach name=":explode(',',$vo1.type_extend.area)" id="vo2" key="key2"}
                            <a href="{:mac_url_type($vo1,['area'=>$vo2],'show')}">{$vo2}</a>
                            {/maccms:foreach}
                        </div>
                    </div>
                    <div class="deansubptpp">
                        <h5>年代</h5>
                        <div class="deansubptc">
                            <a href="{:mac_url_type($vo1,[],'show')}">全部</a>
                            {maccms:foreach name=":explode(',',$vo1.type_extend.year)" id="vo2" key="key2"}
                            <a href="{:mac_url_type($vo1,['year'=>$vo2],'show')}">{$vo2}</a>
                            {/maccms:foreach}
                        </div>
                    </div>
                    <div class="deansubptpp">
                        <h5>字母</h5>
                        <div class="deansubptc">
                            <a href="{:mac_url_type($vo1,[],'show')}">全部</a>
                            {maccms:foreach name=":explode(',','A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0-9')" id="vo2" key="key2"}
                            <a href="{:mac_url_type($vo1,['letter'=>$vo2],'show')}">{$vo2}</a>
                            {/maccms:foreach}
                        </div>
                    </div>
                </div>
            </dd>
            {/maccms:type}
            <dd class="deanddt5">
                {maccms:type ids="5" order="asc" by="sort"}
                <h3><a href="{:mac_url_type($vo,[],'show')}">{$vo.type_name}</a><span></span></h3>
                {/maccms:type}
            </dd>
            <dd class="deanddt5">
                <h3><a href="{:mac_url('label/rank')}">影视排行榜</a><span></span></h3>
            </dd>
        </dl></div></div>
    <script type="text/javascript">
        $(".deansubdiv dd").each(function(s){
            $(this).hover(
                    function(){
                        $(".deansubpt").eq(s).show();
                    },
                    function(){
                        $(".deansubpt").eq(s).hide();
                    })
        })
    </script>
    <ul class="51buypic">
        {maccms:vod num="5" level="9" order="desc" by="time"}
        <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}  {$vo.vod_remarks}"><img src="{$vo.vod_pic_slide|mac_url_img}" alt="{$vo.vod_name} {$vo.vod_remarks}" style="background-color: #EEEEEE;"/></a></li>
        {/maccms:vod}
    </ul>
    <a class="prev" href="javascript:void(0)"></a>
    <a class="next" href="javascript:void(0)"></a>
    <div class="num"><ul></ul></div>
    <script>
        /*鼠标移过，左右按钮显示*/
        $(".banner").hover(function(){
            $(this).find(".prev,.next").fadeTo("show",0.5);
        },function(){
            $(this).find(".prev,.next").hide();
        })
        /*鼠标移过某个按钮 高亮显示*/
        $(".prev,.next").hover(function(){
            $(this).fadeTo("show",0.8);
        },function(){
            $(this).fadeTo("show",0.5);
        })
        $(".banner").slide({ titCell:".num ul" , mainCell:".51buypic" , effect:"fold", autoPlay:true, delayTime:700 ,interTime:5000 , autoPage:true });
    </script>
</div>
<!-- 热门推荐 -->
<div class="layout fn-clear" id="latest-focus">
    <div class="latest-tab-nav">
        <ul class="fn-clear">
            <li id="latest1" onmouseover="setTab('latest',1,5);" class="current"><span><i class="ui-icon movie"></i>热门电影</span></li>
            <li id="latest2" onmouseover="setTab('latest',2,5);"><span><i class="ui-icon tv"></i>热播电视剧</span></li>
            <li id="latest3" onmouseover="setTab('latest',3,5);"><span><i class="ui-icon dm"></i>热门综艺</span></li>
            <li id="latest4" onmouseover="setTab('latest',4,5);"><span><i class="ui-icon fun"></i>热门动漫</span></li>
            <li id="latest5" onmouseover="setTab('latest',5,5);"><span><i class="ui-icon wei"></i>热门推荐</span></li>
        </ul>
    </div>
    <div class="latest-tab-box">
        <div id="con_latest_1" class="latest-item movie-latest">
            <div class="silder-cnt">
                <ul class="img-list">
                    {maccms:vod num="5" type="1" order="desc" by="hits_month"}
                    <li><a class="play-img" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/>
                    <label class="mask"></label>
                    <label class="text">{$vo.vod_version}</label></a>
                    <h5><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h5>
                    <p class="time">主演：{$vo.vod_actor}</p></li>
                    {/maccms:vod}
                </ul>
            </div>
            <ul class="txt-list">
                {maccms:vod num="12" type="1" start="6" order="desc" by="hits_month"}
                <li><span>{$vo.vod_time|date='m-d',###}.</span><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a>/ {$vo.vod_version}</li>
                {/maccms:vod}
            </ul>
        </div>
        <div id="con_latest_2" class="latest-item tv-latest fn-hide">
            <div class="silder-cnt">
                <ul class="img-list">
                    {maccms:vod num="5" type="2" order="desc" by="hits_month"}
                    <li><a class="play-img" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/>
                        <label class="mask"></label>
                        <label class="text">连载{$vo.vod_serial}集 / 共{$vo.vod_total}集</label></a>
                        <h5><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h5>
                        <p class="time">主演：{$vo.vod_actor}</p></li>
                    {/maccms:vod}
                </ul>
            </div>
            <ul class="txt-list">
                {maccms:vod num="12" type="2" start="6" order="desc" by="hits_month"}
                <li><span>{$vo.vod_time|date='m-d',###}.</span><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a>/ 连载{$vo.vod_serial}集 / 共{$vo.vod_total}集</li>
                {/maccms:vod}
            </ul>
        </div>
        <div id="con_latest_3" class="latest-item dm-latest fn-hide">
            <div class="silder-cnt">
                <ul class="img-list">
                    {maccms:vod num="5" type="3" order="desc" by="hits_month"}
                    <li><a class="play-img" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/>
                        <label class="mask"></label>
                        <label class="text">连载{$vo.vod_serial}集 / 共{$vo.vod_total}集</label></a>
                        <h5><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h5>
                        <p class="time">主演：{$vo.vod_actor}</p></li>
                    {/maccms:vod}
                </ul>
            </div>
            <ul class="txt-list">
                {maccms:vod num="12" type="3" start="6" order="desc" by="hits_month"}
                <li><span>{$vo.vod_time|date='m-d',###}.</span><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a>/ 连载{$vo.vod_serial}集 / 共{$vo.vod_total}集</li>
                {/maccms:vod}
            </ul>
        </div>
        <div id="con_latest_4" class="latest-item fun-latest fn-hide">
            <div class="silder-cnt">
                <ul class="img-list">
                    {maccms:vod num="5" type="4" order="desc" by="hits_month"}
                    <li><a class="play-img" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/>
                        <label class="mask"></label>
                        <label class="text">连载{$vo.vod_serial}期</label></a>
                        <h5><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h5>
                        <p class="time">主演：{$vo.vod_actor}</p></li>
                    {/maccms:vod}
                </ul>
            </div>
            <ul class="txt-list">
                {maccms:vod num="12" type="4" start="6" order="desc" by="hits_month"}
                <li><span>{$vo.vod_time|date='m-d',###}.</span><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a>/ 连载{$vo.vod_serial}期</li>
                {/maccms:vod}
            </ul>
        </div>
        <div id="con_latest_5" class="latest-item wei-latest fn-hide">
            <div class="silder-cnt">
                <ul class="img-list">
                    {maccms:vod num="5" type="1" level="1,2,3,4,5,6,7,8,9" order="desc" by="hits_month"}
                    <li><a class="play-img" href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/>
                        <label class="mask"></label>
                        <label class="text">{$vo.vod_version}</label></a>
                        <h5><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a></h5>
                        <p class="time">主演：{$vo.vod_actor}</p></li>
                    {/maccms:vod}
                </ul>
            </div>
            <ul class="txt-list">
                {maccms:vod num="12" type="1" start="6" level="1,2,3,4,5,6,7,8,9" order="desc" by="hits_month"}
                <li><span>{$vo.vod_time|date='m-d',###}.</span><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}">{$vo.vod_name}</a>/ {$vo.vod_version}</li>
                {/maccms:vod}
            </ul>
        </div>
    </div>
</div>
<!-- 最新电影资源 -->
<div class="box">
    <div class="title">
        <h2>最新电影</h2>
        <dl>
            {maccms:type ids="6,7,8,9,10,11,12" order="asc" by="sort"}
            <dd><a href="{:mac_url_type($vo)}">{$vo.type_name}</a></dd>
            {/maccms:type}
        </dl>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="10" type="6" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="7" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="8" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="9" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="10" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="11" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="12" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>{$vo.vod_version}</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>
<!--最新电视剧资源-->
<div class="box">
    <div class="title">
        <h2>最新电视剧</h2>
        <dl>
            {maccms:type ids="13,14,15,16" order="asc" by="sort"}
            <dd><a href="{:mac_url_type($vo)}">{$vo.type_name}</a></dd>
            {/maccms:type}
        </dl>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="10" type="13" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}集/共{$vo.vod_total}集</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="14" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}集/共{$vo.vod_total}集</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="15" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}集/共{$vo.vod_total}集</i><em></em></a></li>
            {/maccms:vod}
        </ul>
        <ul class="img-list undis">
            {maccms:vod num="10" type="16" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}集/共{$vo.vod_total}集</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>

<!--最新综艺节目资源-->
<div class="box">
    <div class="title">
        <h2 class="mar">最新综艺节目</h2>
        <dl>
            <dd class="font">不间断为您呈现最轻松、最搞笑、最火爆的综艺节目-尽在龙资源！</dd>
        </dl>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="10" type="3" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}期</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>
<!--最新动画片资源-->
<div class="box">
    <div class="title">
        <h2 class="mar">最新动画片</h2>
        <dl>
            <dd class="font">每天为您更新最热门的经典动漫-与你分享！</dd>
        </dl>
    </div>
    <div class="box_con">
        <ul class="img-list dis">
            {maccms:vod num="10" type="4" order="desc" by="time"}
            <li><a href="{:mac_url_vod_detail($vo)}" title="{$vo.vod_name}"><img src="{:mac_url_img($vo.vod_pic)}" alt="{$vo.vod_name}"/><h2>{$vo.vod_name}</h2><p>{$vo.vod_actor}</p><i>连载{$vo.vod_serial}集/共{$vo.vod_total}集</i><em></em></a></li>
            {/maccms:vod}
        </ul>
    </div>
</div>
<!--最新影视资讯-->
<div class="box">
    <div class="title">
        <h2 class="mar">最新影视资讯</h2>
        <dl>
            <dd class="font">这里会发布一些关于电影、电视剧、明星八卦、娱乐圈的相关报道</dd>
        </dl>
    </div>
    <div class="box_news pianyi">
        <ul>
            {maccms:art num="20" order="desc" by="time"}
            <li><a href="{:mac_url_art_detail($vo)}" title="{$vo.art_name}">{$vo.art_name|mac_substring=22}</a><span>{$vo.art_time|date='Y-m-d',###}</span></li>
            {/maccms:art}
        </ul>
    </div>
</div>
<!-- 友情链接 -->
<div id="link" class="block">
    <div id="title">友情链接 LINK</div>
    <ul>
        <li><a href="//www.maccms.com" target="_blank">苹果CMS-官网</a></li>
        <li><a href="//bbs.maccms.com" target="_blank">苹果CMS-论坛</a></li>
        {maccms:link num="10" type="all" order="desc" by="id"}
        <li><a href="{$vo.link_url}" target="_blank">{$vo.link_name}</a></li>
        {/maccms:link}
        <div class="clear"></div>
    </ul>
</div>
<script type="text/javascript">
    jQuery(".box").slide({ titCell:".title dd",mainCell:".box_con",delayTime:0 });
</script>
<span style="display: none;" class="mac_timming" data-file="" ></span>
{include file="public/foot"}
</body>
</html>
