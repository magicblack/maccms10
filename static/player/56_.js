var str1=MacPlayer.PlayUrl;
if (str1.indexOf("/") >0){ str1= str1.split("/")[8] ; }
MacPlayer.Html = '<embed type="application/x-shockwave-flash" src="http://www.56.com/flashApp/out.14.03.03.a.swf" id="Player" bgcolor="#FFFFFF" quality="high" allowfullscreen="true" allowNetworking="internal" allowscriptaccess="never" wmode="transparent" menu="false" always="false"  pluginspage="http://www.macromedia.com/go/getflashplayer" width="100%" height="100%" flashvars="vid='+str1+'">';
MacPlayer.Show();