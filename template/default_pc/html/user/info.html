<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>修改资料 - 会员中心 - {$maccms.site_name}</title>
	<meta name="keywords" content="">
	<meta name="description" content="">
	{include file="user/include" /}
</head>
<body>
{include file="user/head" /}
<!-- 会员中心 -->
<div id="member" class="fn-clear">
	<div id="left">
		<div class="tou"><img src="{$obj.user_portrait|mac_default='static/images/touxiang.png'|mac_url_img}" alt="会员头像"><p>{$obj.user_name}<br />{$obj.group.group_name}</p></div>
		<ul>
			<li class="hover"><a href="{:url('user/index')}">我的资料</a></li>
			<li><a href="{:url('user/favs')}">我的收藏</a></li>
			<li><a href="{:url('user/plays')}">播放记录</a></li>
			<li><a href="{:url('user/downs')}">下载记录</a></li>
			<li><a href="{:url('user/buy')}">在线充值</a></li>
			<li><a href="{:url('user/upgrade')}">升级会员</a></li>
			<li><a href="{:url('user/orders')}">充值记录</a></li>
			<li><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>
	<div id="right">
		<h2>我的资料</h2>
		<div id="tab">
			<div class="list">
				<ul class="fn-clear">
					<li><a href="{:url('user/index')}">基本资料</a></li>
					<li class="cur">修改信息</li>
					<li><a href="{:url('user/popedom')}">我的权限</a></li>
				</ul>
			</div>
			<div id="listCon">

				<!-- 修改信息 -->
				<div class="cur">
					<form id="fm" name="fm" method="post" action="" >
					<p><span class="xiang">用户名：</span>{$obj.user_name}</p>
					<p><span class="xiang">原始密码：</span><input type="password" name="user_pwd" class="member-input"></p>
					<p><span class="xiang">新密码：</span><input type="password" name="user_pwd1" class="member-input"><span class="tishi">不修改请留空</span></p>
					<p><span class="xiang">重复密码：</span><input type="password" name="user_pwd2" class="member-input"></p>
					<p><span class="xiang">QQ号码：</span><input type="text" name="user_qq" class="member-input" value="{$obj.user_qq}"></p>
					{if condition="$obj.user_email neq ''"}
						<p><span class="xiang">邮箱：</span><input type="text" name="user_email" class="member-input" readonly="readonly" value="{$obj.user_email}">[<a class="btn_unbind" ac="email" href="javascript:;">解绑</a>]</p>
					{else/}
						<p><span class="xiang">邮箱：</span><input type="text" name="user_email" class="member-input" value="">[<a href="{:url('user/bind')}?ac=email">绑定</a>]</p>
					{/if}

						{if condition="$obj.user_phone neq ''"}
						<p><span class="xiang">手机：</span><input type="text" name="user_phone" class="member-input" readonly="readonly" value="{$obj.user_phone}">[<a class="btn_unbind" ac="phone" href="javascript:;">解绑</a>]</p>
						{else/}
						<p><span class="xiang">手机：</span><input type="text" name="user_phone" class="member-input" value="">[<a href="{:url('user/bind')}?ac=phone">绑定</a>]</p>
						{/if}

					<p><span class="xiang">找回密码问题：</span><input type="text" name="user_question" class="member-input" value="{$obj.user_question}"></p>
					<p><span class="xiang">找回密码答案：</span><input type="text" name="user_answer" class="member-input" value="{$obj.user_answer}"></p>
					<p><span class="xiang"></span><input type="button" id="btn_submit" class="search-button" value="保存"><span class="wjmm"><a href="{:url('user/findpass')}">忘记密码了？</a></span></p>
					<p><span class="xiang"></span></p>
					</form>
				</div>

			</div>
		</div>
	</div>
</div>
<script type="text/javascript">

	$('.btn_unbind').click(function(){
		var ac = $(this).attr('ac');
		if(ac!='email' && ac!='phone'){
			alert('参数错误');
		}
		if(confirm('确认解除绑定吗？此操作不可恢复？')) {
			$.ajax({
				url: "{:url('user/unbind')}",
				type: "post",
				dataType: "json",
				data: {ac: ac},
				beforeSend: function () {
					//开启loading
				},
				success: function (r) {
					alert(r.msg);
					if(r.code==1){
						location.href="{:url('user/info')}";
					}
				},
				complete: function () {
					//结束loading
				}
			});
		}
	});

	$("#btn_submit").click(function() {
		var data = $("#fm").serialize();
		$.ajax({
			url: "{:url('user/info')}",
			type: "post",
			dataType: "json",
			data: data,
			beforeSend: function () {
				//开启loading
				//$(".loading_box").css("display","block");
				$("#btn_submit").css("background","#fd6a6a").val("loading...");
			},
			success: function (r) {
				alert(r.msg);
				if(r.code==1){
					location.href="{:url('user/info')}";
				}
			},
			complete: function () {
				//结束loading
				//$(".loading_box").css("display","none");
				$("#btn_submit").css("background","#fa4646").val("提交");
			}
		});
	});

</script>
{include file="user/foot" /}
</body>
</html>