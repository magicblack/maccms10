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
			<h4>预留问题找回密码</h4>
			<div class="reg-group">
				<label class="bd-r" style="letter-spacing: normal;">账号</label>
				<input type="text" id="user_name" name="user_name" class="reg-control" placeholder="请输入您的登录账号">
			</div>
			<div class="reg-group">
				<label>找回问题</label>
				<input type="text" id="user_question" name="user_question" class="reg-control" placeholder="请输入您密码找回问题">
			</div>
			<div class="reg-group">
				<label>找回答案</label>
				<input type="text" id="user_answer" name="user_answer" class="reg-control" placeholder="请输入您的密码找回答案">
			</div>
			<div class="reg-group">
				<label>新的密码</label>
				<input type="password" id="user_pwd" name="user_pwd" class="reg-control" placeholder="请输入您的新密码">
			</div>
			<div class="reg-group">
				<label>确认密码</label>
				<input type="password" id="user_pwd2" name="user_pwd2" class="reg-control" placeholder="请输入您的确认密码">
			</div>
			<div class="reg-group">
				<label>验证码</label>
				<input type="text" class="reg-control w150" id="verify" name="verify" placeholder="请输入验证码">
				<img class="fr mr10 mt10" src="{:url('verify/index')}" onClick="this.src=this.src+'?'"  alt="单击刷新" />
			</div>
			<input type="button" id="btn_submit" class="btn-brand btn-sub" value="立即找回">
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
		$('#btn_submit').click(function() {
			if ($('#user_name').val()  == '') { alert('请输入用户！'); $("#user_name").focus(); return false; }
			if ($('#user_pwd').val()  == '') { alert('请输入密码！'); $("#user_pwd").focus(); return false; }
			if ($('#verify').val()  == '') { alert('请输入验证码！'); $("#verify").focus(); return false; }

			$.ajax({
				url: "{:url('user/findpass')}",
				type: "post",
				dataType: "json",
				data: $('#fm').serialize(),
				beforeSend: function () {
					$("#btn_submit").css("background","#fd6a6a").val("loading...");
				},
				success: function (r) {
					if(r.code==1){
						location.href="{:url('user/index')}";
					}
					else{
						alert(r.msg);
					}
				},
				complete: function () {
					$('#verify').click();
					$("#btn_submit").css("background","#fa4646").val("立即找回");
				}
			});

		});
	});

</script>
{include file="user/foot" /}
</body>
</html>