
if(/^(\-?)(\d+)$/.test(MacPlayer.PlayUrl)) {
	MacPlayer.Html = '<embed type="application/x-shockwave-flash" src="http://www.iqiyi.com/player/20121017112902/Player.swf" id="Player" bgcolor="#FFFFFF" quality="high" allowfullscreen="true" allowscriptaccess="always" wmode="transparent" menu="false" always="false"  pluginspage="http://www.macromedia.com/go/getflashplayer" width="100%" height="100%" flashvars="flag=0&coreUrl=http://www.qiyipic.com/1000/fix/cp_2023_pp.jpg&vid='+MacPlayer.PlayUrl+'">';
}
else{
	MacPlayer.Html = '<embed type="application/x-shockwave-flash" src="http://www.iqiyi.com/player/20130917134401/Player.swf" id="Player" bgcolor="#FFFFFF" quality="high" allowfullscreen="true" allowscriptaccess="always" swLiveConnect="true" wmode="Opaque" menu="false" always="false"  pluginspage="http://www.macromedia.com/go/getflashplayer" width="100%" height="100%" flashvars="cid=qc_100001_100014&coreUrl=http://www.qiyipic.com/flashcp/fix/cp21842.jpg&tipdataurl=http://static.qiyi.com/ext/201309171416/tipdata.xml&components=feffffeee&preloader=http://www.iqiyi.com/player/20130515145936/preloader.swf&vipPreloader=http://www.iqiyi.com/player/20130121161835/vip.swf&adurl=http://www.iqiyi.com/player/20a7af2ec3583ed292c809aa1d889c68a2a.swf&flashP2PCoreUrl=http://www.qiyipic.com/p20071/fix/library.jpg&origin=flash&pageOpenSrc=1&expandState=true&autoplay=true&isMember=false&cyclePlay=false&share_sTime=0&share_eTime=0&albumId=&tvId=&vid='+MacPlayer.PlayUrl+'">';
}

MacPlayer.Show();