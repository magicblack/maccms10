<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>充值卡充值 - 会员中心 - {$maccms.site_name}</title>
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
			<li><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>
	<div id="right">
		<h2>在线充值</h2>
		<form method="post" target="_blank" action="{:url('user/gopay')}">
			<input type="hidden" name="order_id" value="{$info.order_id}">
			<input type="hidden" name="order_code" value="{$info.order_code}">
		<div class="line40">
			<p><span class="xiang">订单编号：</span>{$info.order_code}</p>
			<p><span class="xiang">订单金额：</span>{$info.order_price}元</p>
			<p>
				<span class="xiang">支付方式：</span>
				<select name="payment" id="payment">
					<option value ="">请选择...</option>
					{volist name="ext_list" id="vo"}
					<option value="{$key}">{$vo}支付</option>
					{/volist}
				</select>
			</p>

			<p class="info-item" id="paytype_box" style="display:none;">
				<span class="xiang">支付类型：</span>
				<select class="paytype" id="paytype" name="paytype">
				</select>
			</p>

			<p><input type="submit" id="btn_submit" class="jifen2-button" value="确认"></p>
		</div>
		</form>
	</div>
</div>
<script>
	var codepay_type = '{maccms:foreach name=":explode(',',$config.codepay.type)"}<option value ="{$vo}">{if condition="$vo==1"}支付宝二维码{elseif condition="$vo==2"/}QQ钱包二维{elseif condition="$vo==3"/}微信二维码{/if}</option>{/maccms:foreach}';
	var zhapay_type ='{maccms:foreach name=":explode(',',$config.zhapay.type)"}<option value ="{$vo}">{if condition="$vo==1"}微信{elseif condition="$vo==2"/}支付宝{/if}</option> {/maccms:foreach}';

	$("#payment").change(function() {
		$('#paytype').html('');
		if($("#payment").val()=="codepay" || $("#payment").val()=="zhapay" ){
			if($("#payment").val()=="codepay") {
				$('#paytype').html(codepay_type);
			}
			if($("#payment").val()=="zhapay") {
				$('#paytype').html(zhapay_type);
			}
			$("#paytype_box").slideDown();
		}
		else{
			$("#paytype_box").slideUp();
		}
	});

	$(".paytype").change(function() {
		$('#paytype').val( $(this).val() );
	});
</script>
{include file="user/foot" /}
</body>
</html>