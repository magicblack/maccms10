<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>用户登录 - {$maccms.site_name}</title>
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
			<h4>账户信息</h4>
			<div class="reg-group">
				<label class="bd-r" style="letter-spacing: normal;">账号</label>
				<input type="text" id="user_name" name="user_name" class="reg-control" placeholder="请输入您的登录账号">
			</div>
			<div class="reg-group">
				<label>密码</label>
				<input type="password" id="user_pwd" name="user_pwd" class="reg-control" placeholder="请输入您的登录密码">
			</div>
			{if condition="$GLOBALS['config']['user']['login_verify'] eq 1"}
			<div class="reg-group">
				<label>验证码</label>
				<input type="text" class="reg-control w150" id="verify" name="verify" placeholder="请输入验证码">
				<img class="fr mr10 mt10" id="verify_img" src="{:url('verify/index')}" onClick="this.src=this.src+'?'"  alt="单击刷新" />
			</div>
			{/if}
			<input type="button" id="btn_submit" class="btn-brand btn-sub" value="立即登录">
		</form>
	</div>
	<div class="reg-another">
		<h5>注册通行证可享会员服务</h5>
		<h5>收费影片</h5>
		<h5>会员影片</h5>
		<h5>特殊影片</h5>
		<h5>你还可以用以下方式直接登录：</h5>
		{if condition="$GLOBALS['config']['connect']['qq']['status'] eq 1"}

		{/if}
		{if condition="$GLOBALS['config']['connect']['weixin']['status'] eq 1"}

		{/if}
		<a href="{:url('user/oauth')}?type=qq"><img src="{$maccms.path_tpl}images/user/qq_login.gif" alt=""/></a>
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
			if ($('#verify').length> 0 && $('#verify').val()  == '') { alert('请输入验证码！'); $("#verify").focus(); return false; }

			$.ajax({
				url: "{:url('user/login')}",
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
						$('#verify_img').click();
					}
				},
				complete: function () {
					$("#btn_submit").css("background","#fa4646").val("立即登录");
				}
			});

		});
	});

</script>
{include file="user/foot" /}
</body>
</html>