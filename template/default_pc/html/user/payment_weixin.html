<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>微信充值 - 会员中心 - {$maccms.site_name}</title>
	<meta name="keywords" content="{$maccms.site_keywords}"/>
	<meta name="description" content="{$maccms.site_description}"/>
	{include file="user/include" /}
</head>
<body>
{include file="user/head" /}
<div id="member" class="fn-clear">
	<div id="left">
		<div class="tou"><img src="{$obj.user_portrait|mac_default='static/images/touxiang.png'|mac_url_img}" alt="会员头像"><p>{$info.user_name}<br />{$info.group.group_name}</p></div>
		<ul>
			<li><a href="{:url('user/index')}">我的资料</a></li>
			<li><a href="{:url('user/favs')}">我的收藏</a></li>
			<li><a href="{:url('user/plays')}">播放记录</a></li>
			<li><a href="{:url('user/downs')}">下载记录</a></li>
			<li class="hover"><a href="{:url('user/buy')}">在线充值</a></li>
			<li><a href="{:url('user/upgrade')}">升级会员</a></li>
		</ul>
	</div>
	<div id="right">
		<h2>微信在线充值</h2>
		<form method="post" target="_blank" action="{:url('user/gopay')}">
			<input type="hidden" name="order_id" value="{$info.order_id}">
			<input type="hidden" name="order_code" value="{$info.order_code}">
		<div class="line40">
			<p><span class="xiang">订单编号：</span>{$order.order_code}</p>
			<p><span class="xiang">订单金额：</span>{$order.order_price}元</p>
			<p><img src="{:url('user/qrcode')}?data={$payment.code_url|urlencode}" width="150" height="150"/></p>
			<p>打开微信，扫码支付</p>
		</div>
		</form>
	</div>
</div>
<script>
	function check(){
		$.get("{:url('user/order_info')}" + '?order_id={$order.order_id}', function(data){
			if(data.info.order_status == 1){
				alert('支付完成，即将跳转到会员中心');
				window.location.href = "{:url('user/index')}";
			}
		});
	}
	$(function(){
		setInterval(function(){check()}, 5000);  //5秒查询一次支付是否成功
	})
</script>
{include file="user/foot" /}
</body>
</html>