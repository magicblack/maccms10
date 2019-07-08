
MacPlayer.Html='<object id="Player" classid="clsid:E38F2429-07FE-464A-9DF6-C14EF88117DD" width="100%" height="100%" onError="MacPlayer.Install();"><param name="URL" value="'+ MacPlayer.PlayUrl +'"><param name="Status" value="1"></object>';

var rMsie = /(msie\s|trident.*rv:)([\w.]+)/;
var match = rMsie.exec(navigator.userAgent.toLowerCase());
if(match == null){
	if (navigator.plugins){
		var ll = false;
		for (var i=0;i<navigator.plugins.length;i++) {
			var n = navigator.plugins[i].name;
			if( navigator.plugins[n][0]['type'] == 'application/xfplay-plugin')
			{
				ll = true; break;
			}
		}
	}
	if(ll){
	MacPlayer.Html = '<embed id="Player" name="Player" type="application/xfplay-plugin" width="100%" height="100%"   PARAM_URL="'+MacPlayer.PlayUrl+' PARAM_Status="1" PARAM_Autoplay="1"></embed>'
	}
	else{
		MacPlayer.Install();
	}
}
MacPlayer.Show();
setTimeout(function(){
	if (MacPlayer.Status == true && MacPlayer.Flag==1){
		
	}
},
MacPlayer.Second * 1000 + 1000);