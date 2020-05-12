<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>找回密码 - {$maccms.site_name} </title>
	<meta name="keywords" content="{$maccms.site_keywords}"/>
	<meta name="description" content="{$maccms.site_description}"/>
	{include file="user/include" /}
</head>
<body>
<div class="header">
	<div class="layout fn-clear">
		<div class="logo">
			<a href="{$maccms.path}"><img width="157" height="42" src="{$maccms.path_tpl}images/member/ilogo.gif" alt=""/></a>
		</div>
		<ul class="nav">
			<li class="nav-item"><a class="nav-link" href="{$maccms.path}">返回首页</a></li>
		</ul>
	</div>
</div>

<div class="layout clearfix">
	<div class="reg-w">
		<form method="post" id="fm" action="">
			<input type="hidden" name="ac" value="{$param['ac']}">
			<h4>{$param['ac_text']}找回密码</h4>
			<div class="reg-group">
				<label class="bd-r" style="letter-spacing: normal;">{$param['ac_text']}</label>
				<input type="text" id="to" name="to" class="reg-control" placeholder="请输入您绑定的{$param['ac_text']}">
			</div>

			<div class="reg-group">
				<label>验证码</label>
				<input type="text" class="reg-control w150" id="verify" name="verify" placeholder="请输入验证码">
				<img class="fr mr10 mt10" src="{:url('verify/index')}" onClick="this.src=this.src+'?'"  alt="单击刷新" />
			</div>
			<input type="button" id="btn_send" class="btn-brand btn-sub" style="margin-top:5px;" value="发送验证码">
		</form>

		<form method="post" id="fm2" action="">
			<input type="hidden" name="ac" value="email">
			<h4>验证信息</h4>
			<div class="reg-group">
				<label class="bd-r" style="letter-spacing: normal;">验证码</label>
				<input type="text" id="code" name="code" class="reg-control" placeholder="请输入验证码">
			</div>
			<div class="reg-group">
				<label>新密码</label>
				<input type="password" class="reg-control w150" id="user_pwd" name="user_pwd" placeholder="请输入新密码">
			</div>
			<div class="reg-group">
				<label>确认密码</label>
				<input type="password" class="reg-control w150" id="user_pwd2" name="user_pwd2" placeholder="请输入确认密码">
			</div>
			<input type="button" id="btn_submit" class="btn-brand btn-sub" value="重置密码">
		</form>

	</div>


	<div class="reg-another">
		<h5>注册通行证可享会员服务</h5>
		<h5>收费影片</h5>
		<h5>会员影片</h5>
		<h5>特殊影片</h5>
		<a href="{:url('user/login')}"><i class="i-pers"></i><span>想起密码了？直接登录</span></a>
		<a href="{:url('user/findpass')}"><i class="i-pers"></i><span>预留问题找回密码！</span></a>
		<a href="{:url('user/findpass_msg')}?ac=email"><i class="i-pers"></i><span>绑定邮箱找回密码！</span></a>
		<a href="{:url('user/findpass_msg')}?ac=phone"><i class="i-pers"></i><span>绑定手机找回密码！</span></a>
	</div>
</div>

<!-- // sign-box#regbox end -->
<script type="text/javascript">

	$(function(){
		$("body").bind('keyup',function(event) {
			if(event.keyCode==13){ $('#btnLogin').click(); }
		});
		$('#btn_send').click(function() {
			if ($('#to').val()  == '') { alert('请输入{$param["ac_text"]}！'); $("#to").focus(); return false; }

			$.ajax({
				url: "{:url('user/findpass_msg')}",
				type: "post",
				dataType: "json",
				data: $('#fm').serialize(),
				beforeSend: function () {
					$("#btn_send").css("background","#fd6a6a").val("loading...");
				},
				success: function (r) {
					alert(r.msg);
				},
				complete: function () {
					$('#verify').click();
					$("#btn_send").css("background","#fa4646").val("发送邮件");
				}
			});
		});

		$('#btn_submit').click(function() {
			if ($('#to').val()  == '') { alert('请输入{$param["ac_text"]}'); $("#to").focus(); return false; }
			if ($('#code').val()  == '') { alert('请输入验证码！'); $("#code").focus(); return false; }
			if ($('#user_pwd').val()  == '') { alert('请输入新密码！'); $("#user_pwd").focus(); return false; }
			if ($('#user_pwd2').val()  == '') { alert('请输入确认密码！'); $("#user_pwd2").focus(); return false; }
			if ($('#user_pwd').val()  != $('#user_pwd2').val() ) { alert('二次密码不一致！'); $("#user_pwd2").focus(); return false; }

			var data= {ac:'{$param["ac"]}',to:$('#to').val(),code:$('#code').val(),user_pwd:$('#user_pwd').val(),user_pwd2:$('#user_pwd2').val()};
			$.ajax({
				url: "{:url('user/findpass_reset')}",
				type: "post",
				dataType: "json",
				data: data,
				beforeSend: function () {
					$("#btn_submit").css("background","#fd6a6a").val("loading...");
				},
				success: function (r) {
					alert(r.msg);
				},
				complete: function () {
					$("#btn_submit").css("background","#fa4646").val("重置密码");
				}
			});
		});
	});

</script>
{include file="user/foot" /}
</body>
</html>