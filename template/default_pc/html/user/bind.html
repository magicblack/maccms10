<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>绑定数据 - 会员中心 - {$maccms.site_name}</title>
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
					<li class="cur">绑定{if condition="$ac eq 'phone'"}手机{else/}邮箱{/if}</li>
				</ul>
			</div>
			<div id="listCon">
				<!-- 修改信息 -->
				<div class="cur">
					<form id="fm" name="fm" method="post" action="" >
						<input type="hidden" name="ac" value="{$ac}">
						<p><span class="xiang">{if condition="$ac eq 'phone'"}手机{else/}邮箱{/if}：</span><input type="text" name="to" class="member-input" value=""><input type="button" id="btn_bind_send" value="获取验证码"/></p>
						<p><span class="xiang">验证码：</span><input type="text" name="code" class="member-input" value=""></p>
						<p><span class="xiang"></span><input type="button" id="btn_submit" class="search-button" value="确认绑定"></p>
					</form>
				</div>

			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	var countdown=60;
	function settime(val) {
		if (countdown == 0) {
			val.removeAttribute("disabled");
			val.value="获取验证码";
			countdown = 60;
			return true;
		} else {
			val.setAttribute("disabled", true);
			val.value="重新发送(" + countdown + ")";
			countdown--;
		}
		setTimeout(function() {settime(val) },1000)
	}

	$('#btn_bind_send').click(function(){
	    var ac = $('input[name="ac"]').val();
		var to = $('input[name="to"]').val();
		if(ac=='email') {
            var pattern = /^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
            var ex = pattern.test(to);
            if (!ex) {
                alert('邮箱格式不正确');
                return;
            }
        }
        else if(ac=='phone') {
            var pattern=/^[1][0-9]{10}$/;
            var ex = pattern.test(to);
            if (!ex) {
                alert('手机号格式不正确');
                return;
            }
        }
		else{
		    alert('参数错误');
			return;
		}


		settime(this);
		var data = $("#fm").serialize();

		$.ajax({
			url: "{:url('user/bindmsg')}",
			type: "post",
			dataType: "json",
			data: data,
			beforeSend: function () {
				//开启loading
			},
			success: function (r) {
				alert(r.msg);
			},
			complete: function () {
				//结束loading
			}
		});
	});

	$("#btn_submit").click(function() {
        var ac = $('input[name="ac"]').val();
        var to = $('input[name="to"]').val();

        if(ac=='email') {
            var pattern = /^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+\.[a-zA-Z]{2,3}$/;
            var ex = pattern.test(to);
            if (!ex) {
                alert('邮箱格式不正确');
                return;
            }
        }
        else if(ac=='phone') {
            var pattern=/^[1][0-9]{10}$/;
            var ex = pattern.test(to);
            if (!ex) {
                alert('手机号格式不正确');
                return;
            }
        }
        else{
            alert('参数错误');
            return;
        }

		var code = $('input[name="code"]').val();
		if(code==''){
			alert('请输入验证码');
			return;
		}
		var data = $("#fm").serialize();

		$.ajax({
			url: "{:url('user/bind')}",
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