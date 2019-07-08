<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>充值卡充值 - 会员中心 -{$maccms.site_name}</title>
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
			<li><a href="{:url('user/index')}">我的资料</a></li>
			<li><a href="{:url('user/favs')}">我的收藏</a></li>
			<li><a href="{:url('user/plays')}">播放记录</a></li>
			<li><a href="{:url('user/downs')}">下载记录</a></li>
			<li class="hover"><a href="{:url('user/buy')}">在线充值</a></li>
			<li><a href="{:url('user/upgrade')}">升级会员</a></li>
			<li><a href="{:url('user/orders')}">充值记录</a></li>
			<li><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>
    <div id="right">
		<h2>在线充值</h2>
		<div class="line40">
			<p><span class="xiang">剩余积分：</span>{$obj.user_points}</p>
			<p><span class="xiang">充值的金额：</span><input type="text" name="price" value="{$config.min}" class="jifen-input"></p>
			<p><input type="button" id="btn_submit_pay" class="jifen2-button" value="确认"></p>
			<p class="hui">友情提示：最小充值金额为{$config.min}元，1元可以兑换{$config.scale}个积分</p>
		</div>

		<div class="line40">
			<p><span class="xiang">充值卡号：</span><input type="text" name="card_no" value="" class="jifen-input">
				{if condition="$GLOBALS['config']['pay']['card']['url'] neq ''"}
				<a target="_blank" href="{$GLOBALS['config']['pay']['card']['url']}">点击购买卡密</a>
				{/if}
			</p>
			<p><span class="xiang">充值密码：</span><input type="text" name="card_pwd" value="" class="jifen-input"></p>
			<p><input type="button" id="btn_submit_card" class="jifen2-button" value="确认"></p>
			<p class="hui">友情提示：请到卡密平台购买充值卡</p>
		</div>
    </div>
</div>
<script>

	$(".go-back").click(function () {
		var ref = document.referrer;
		location.href=ref;
	});

	$('#btn_submit_pay').click(function(){
		var that=$(this);
		var price = $("input[name='price']").val();
		if(Number(price)<1){
			return;
		}
		if(confirm('确定要在线充值吗')) {
			$.ajax({
				url: "{:url('user/buy')}",
				type: "post",
				dataType: "json",
				data: {price: price,flag:'pay'},
				beforeSend: function () {
					$("#btn_submit_pay").css("background","#fd6a6a").val("loading...");
				},
				success: function (r) {
					if (r.code == 1) {
						location.href="{:url('user/pay')}?order_code=" + r.data.order_code;
					}
					else{
						alert(r.msg);
					}
				},
				complete: function () {
					$("#btn_submit_pay").css("background","#fa4646").val("提交");
				}
			});
		}
	});

	$('#btn_submit_card').click(function(){
		var that=$(this);
		var no = $('input[name="card_no"]').val();
		var pwd = $('input[name="card_pwd"]').val();
		if(no=='' || pwd==''){
			alert('请输入充值卡号和密码');
			return;
		}
		if(confirm('确定要使用充值卡充值吗')) {
			$.ajax({
				url: "{:url('user/buy')}",
				type: "post",
				dataType: "json",
				data: {card_no: no,card_pwd:pwd,flag:'card'},
				beforeSend: function () {
					$("#btn_submit_card").css("background","#fd6a6a").val("loading...");
				},
				success: function (r) {
					alert(r.msg);
				},
				complete: function () {
					$("#btn_submit_card").css("background","#fa4646").val("提交");
				}
			});
		}
	});
</script>
{include file="user/foot" /}
</body>
</html>