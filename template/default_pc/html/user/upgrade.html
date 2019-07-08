<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>升级会员组 - 会员中心 - {$maccms.site_name}</title>
<meta name="keywords" content="{$maccms.site_keywords}"/>
<meta name="description" content="{$maccms.site_description}"/>
{include file="user/include" /}
</head>
<body>
{include file="user/head" /}
<!-- 会员中心 -->
<div id="member" class="fn-clear">
    <div id="left">
		<div class="tou"><img src="{$obj.user_portrait|mac_default='static/images/touxiang.png'|mac_url_img}" alt="会员头像"><p>{$obj.user_name}<br />{$obj.group.group_name}</p></div>
		<ul>
			<li ><a href="{:url('user/index')}">我的资料</a></li>
			<li><a href="{:url('user/favs')}">我的收藏</a></li>
			<li><a href="{:url('user/plays')}">播放记录</a></li>
			<li><a href="{:url('user/downs')}">下载记录</a></li>
			<li><a href="{:url('user/buy')}">在线充值</a></li>
			<li class="hover"><a href="{:url('user/upgrade')}">升级会员</a></li>
			<li><a href="{:url('user/orders')}">充值记录</a></li>
			<li><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>

    <div id="right">
		<h2>升级会员</h2>
		<div class="line40">
			<p><span class="xiang">所属会员组：</span>[{$obj.group.group_name}] </p>
			<p><span class="xiang">剩余积分：</span>[{$obj.user_points}] </p>
			<p><span class="xiang">到期时间：</span>{if condition="$obj.group_id lt 3"}[无限期]{else}[{$obj.user_end_time|mac_day}]{/if}</p>
			<p><span class="xiang">请选择升级选项：</span><span class="fen">点击需要的会员组和时长进行购买升级</span></p>
		</div>
		<form action="" method="post" name="form3" id="form3">
		<div class="shengji">
		 <!-- BEGIN row -->
		{volist name="group_list" id="vo"}
			 {if condition="$vo.group_id gt 2 && $vo.group_status eq 1"}
			 <div class="huang grade" style="width:170px; line-height:40px; text-align:center; color:#fff; margin-right:15px; display:inline-block;" data-id="{$vo.group_id}" data-name="{$vo.group_name}" data-points="{$vo.group_points_day}" data-long="day">
				 {$vo.group_name}-包天：{$vo.group_points_day}积分
			 </div>
			<div class="lan grade" style="width:170px; line-height:40px; text-align:center; color:#fff; margin-right:15px; display:inline-block;" data-id="{$vo.group_id}" data-name="{$vo.group_name}" data-points="{$vo.group_points_week}" data-long="week">
				{$vo.group_name}-包周：{$vo.group_points_week}积分
			</div>
				<div class="hong grade" style="width:170px; line-height:40px; text-align:center; color:#fff; margin-right:15px; display:inline-block;" data-id="{$vo.group_id}" data-name="{$vo.group_name}" data-points="{$vo.group_points_month}" data-long="month">
					{$vo.group_name}-包月：{$vo.group_points_month}积分
				</div>
					<div class="zi grade" style="width:170px; line-height:40px; text-align:center; color:#fff; margin-right:15px; display:inline-block;" data-id="{$vo.group_id}" data-name="{$vo.group_name}" data-points="{$vo.group_points_year}" data-long="year">
						{$vo.group_name}-包年：{$vo.group_points_year}积分
					</div>
			 {/if}
		 {/volist}
		 <!-- END row -->
		</div>
	</form>
    </div>
</div>
<script>
	
		$('.grade').click(function(){
			var that=$(this);
			var group_id = that.attr('data-id');
			var group_name = that.attr('data-name');
			var long = that.attr('data-long');
			var points = that.attr('data-points');
	
			if(confirm('确定要升级到【'+group_name+'】吗,需要花费【'+points+'】积分')) {
				$.ajax({
					url: "{:url('user/upgrade')}",
					type: "post",
					dataType: "json",
					data: {group_id: group_id,long:long },
					beforeSend: function () {
						$("#btn_submit").css("background","#fd6a6a").val("loading...");
					},
					success: function (r) {
						alert(r.msg);
						if (r.code == 1) {
							location.reload();
						}
					},
					complete: function () {
						$("#btn_submit").css("background","#fa4646").val("提交");
					}
				});
			}
		});
</script>
{include file="user/foot" /}
</body>
</html>