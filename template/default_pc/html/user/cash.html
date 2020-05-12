<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>积分记录 - 会员中心 - {$maccms.site_name}</title>
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
			<li ><a href="{:url('user/index')}">我的资料</a></li>
			<li><a href="{:url('user/favs')}">我的收藏</a></li>
			<li><a href="{:url('user/plays')}">播放记录</a></li>
			<li><a href="{:url('user/downs')}">下载记录</a></li>
			<li><a href="{:url('user/buy')}">在线充值</a></li>
			<li><a href="{:url('user/upgrade')}">升级会员</a></li>
			<li><a href="{:url('user/orders')}">充值记录</a></li>
			<li><a href="{:url('user/plog')}">积分记录</a></li>
			<li class="hover"><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>
    <div id="right">
		<h2>我的提现记录</h2>
		<div id="gong">
			<!-- 修改信息 -->
			<div class="cur" style="border-color: red;    border-radius: 3px;">
				<form id="fm" name="fm" method="post" action="" >
					<p><span class="xiang">1元等于{$GLOBALS['config']['user']['cash_ratio']}积分，最低提现金额：{$GLOBALS['config']['user']['cash_min']}元</span></p>
					<p><span class="xiang">剩余{$GLOBALS['user']['user_points']}积分，相当于{$GLOBALS['user']['user_points']/$GLOBALS['config']['user']['cash_ratio']}元；冻结{$GLOBALS['user']['user_points_froze']}积分，相当于{$GLOBALS['user']['user_points_froze']/$GLOBALS['config']['user']['cash_ratio']}元；</span></p>
					<p><span class="xiang"></span></p>
					<p><span class="xiang">银行名称：</span><input type="text" name="cash_bank_name" class="member-input" placeholder="请输入开户行名称或支付宝微信" value="">银行账号：</span><input type="text" name="cash_bank_no" class="member-input" placeholder="请输入银行卡号或支付宝微信账号"  value=""></p>
					<p><span class="xiang">收款姓名：</span><input type="text" name="cash_payee_name" class="member-input" placeholder="请输入收款人姓名与上方账户对应"  value="">提现金额：</span><input type="text" name="cash_money" class="member-input" placeholder="请输入提现金额"  value=""></p>
					<p style="text-align: center;"><span class="xiang"></span><input type="button" id="btn_submit" class="search-button" value="提交" style="margin: 5px;"></p>
				</form>
			</div>

		</div>

		<table width="770" border="0" cellspacing="1" cellpadding="0" class="table">
		  <tr>
			<td width="66" height="36" align="center" valign="middle" bgcolor="#f7f7f7">选择</td>
			<td width="80" align="center" valign="middle" bgcolor="#f7f7f7">编号</td>
			<td width="100" align="center" valign="middle" bgcolor="#f7f7f7">提现积分</td>
			<td width="100" align="center" valign="middle" bgcolor="#f7f7f7">提现金额</td>
			<td width="100" align="center" valign="middle" bgcolor="#f7f7f7">状态</td>
			<td width="140" align="center" valign="middle" bgcolor="#f7f7f7">时间</td>
			<td width="80" align="center" valign="middle" bgcolor="#f7f7f7">操作</td>
		  </tr>
		  <form id="form1" name="form1" method="post">
			  {volist name="list" id="vo"}
		  <tr>
			<td height="36" align="center" valign="middle" bgcolor="#FFFFFF">
			<input type="checkbox" name="ids[]" id="checkbox" value="{$vo.cash_id}"/></td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.cash_id}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.cash_points}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.cash_money}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{if condition="$vo.cash_status eq '1'"}已审核{else/}未审核{/if}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.cash_time|mac_day}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF"><a href="javascript:;" onclick="delData({$vo.cash_id},0)" class="delete">删除</a></td>
		  </tr>
			  {/volist}
		  </form>
		</table>
		<div class="member-page">
			<em>共{$__PAGING__.record_total}条</em>
			<a class="page_link" href="{$__PAGING__.page_url|str_replace='PAGELINK',1,###}" title="首页">首页</a>
			<a class="page_link" href="{$__PAGING__.page_url|str_replace='PAGELINK',$__PAGING__.page_prev,###}" title="上一页">上一页</a>
			{volist name="$__PAGING__.page_num" id="num"}
			{if condition="$__PAGING__['page_current'] eq $num"}
			<a class="page_link page_current" href="javascript:;" title="第{$num}页">{$num}</a>
			{else}
			<a class="page_link" href="{$__PAGING__.page_url|str_replace='PAGELINK',$num,###}" title="第{$num}页" >{$num}</a>
			{/if}
			{/volist}
			<a class="page_link" href="{$__PAGING__.page_url|str_replace='PAGELINK',$__PAGING__.page_next,###}" title="下一页">下一页</a>
			<a class="page_link" href="{$__PAGING__.page_url|str_replace='PAGELINK',$__PAGING__.page_total,###}" title="尾页">尾页</a>
			<em>到第</em><input type="text" name="" class="page-input"><em>页</em><input type="submit" class="page-button" value="确定">
		</div>
    </div>
</div>
<script>
	function delData(ids,all){
		var msg ='删除';
		if(all==1){
			msg='清空';
		}
		if(confirm('确定要'+msg+'记录吗')){
			$.post("{:url('user/cash_del')}",{ids:ids,all:all},function(data) {
				if (data.code == '1') {
					alert('删除成功');
					location.reload();
				}else {
					alert('删除失败：' + data.msg);
				}
			}, 'json')
		}
	}
	$("#btnClear").click(function(){
		delData('',1);
	});
	$("#btnDel").click(function(){
		var ids = MAC.CheckBox.Ids('ids[]');
		if(ids==''){
			alert("请至少选择一个数据");
			return;
		}
		delData(ids,0);
	});
    $("#btn_submit").click(function() {
        var cash_bank_name = $('input[name="cash_bank_name"]').val();
        if(cash_bank_name==''){
            alert('请输入银行名称');
            return;
        }
        var cash_bank_no = $('input[name="cash_bank_no"]').val();
        if(cash_bank_no==''){
            alert('请输入银行账户');
            return;
        }
        var cash_payee_name = $('input[name="cash_payee_name"]').val();
        if(cash_payee_name==''){
            alert('请输入收款人姓名');
            return;
        }
        var cash_money = $('input[name="cash_money"]').val();
        if(cash_money==''){
            alert('请输入提现金额');
            return;
        }

        var data = $("#fm").serialize();
        $.ajax({
            url: "{:url('user/cash')}",
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
                    location.href="{:url('user/cash')}";
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