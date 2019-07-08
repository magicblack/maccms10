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
			<li class="hover"><a href="{:url('user/plog')}">积分记录</a></li>
			<li><a href="{:url('user/cash')}">提现记录</a></li>
			<li><a href="{:url('user/reward')}">三级分销</a></li>
		</ul>
	</div>
    <div id="right">
		<h2>我的积分记录</h2>
		<div class="gong">
		<a href="javascript:;" onClick="MAC.CheckBox.All('ids[]');">全选</a>
		<a href="javascript:;" onClick="MAC.CheckBox.Other('ids[]');">反选</a>
		<a href="javascript:;" id="btnDel">删除</a>
		<a href="javascript:;" id="btnClear">清空</a>
		</div>
		<table width="770" border="0" cellspacing="1" cellpadding="0" class="table">
		  <tr>
			<td width="66" height="36" align="center" valign="middle" bgcolor="#f7f7f7">选择</td>
			<td width="80" align="center" valign="middle" bgcolor="#f7f7f7">编号</td>
			<td width="100" align="center" valign="middle" bgcolor="#f7f7f7">分类</td>
			<td width="100" align="center" valign="middle" bgcolor="#f7f7f7">积分</td>
			  <td width="140" align="center" valign="middle" bgcolor="#f7f7f7">时间</td>
			<td width="80" align="center" valign="middle" bgcolor="#f7f7f7">操作</td>
		  </tr>
		  <form id="form1" name="form1" method="post">
			  {volist name="list" id="vo"}
		  <tr>
			<td height="36" align="center" valign="middle" bgcolor="#FFFFFF">
			<input type="checkbox" name="ids[]" id="checkbox" value="{$vo.plog_id}"/></td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.plog_id}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.plog_type|mac_get_plog_type_text}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF">{if condition="in_array($vo.plog_type,[1,2,3,4])"}+{else/}-{/if}{$vo.plog_points}</td>
			  <td align="center" valign="middle" bgcolor="#FFFFFF">{$vo.plog_time|mac_day}</td>
			<td align="center" valign="middle" bgcolor="#FFFFFF"><a href="javascript:;" onclick="delData({$vo.plog_id},0)" class="delete">删除</a></td>
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
			$.post("{:url('user/plog_del')}",{ids:ids,all:all},function(data) {
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
</script>
{include file="user/foot" /}
</body>
</html>