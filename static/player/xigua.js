var $$ = function(value){return document.getElementById(value)};

function PlayStatus() {
    if (MacPlayer.IsPlaying()) {
        MacPlayer.AdsEnd()
    } else {
        AdsStart()
    }
}

var onPlay = function(){
	$$('buffer').style.display = 'none';
	if(Player.IsPlaying()){
		Player.Play();
	}
}
var onPause = function(){
	$$('buffer').height = MacPlayer.Height-60;
	$$('buffer').style.display = 'block';
}
var onFirstBufferingStart = function(){
	$$('buffer').height = MacPlayer.Height-80;
	$$('buffer').style.display = 'block';
}
var onFirstBufferingEnd = function(){
	$$('buffer').style.display = 'none';
}
var onPlayBufferingStart = function(){
	$$('buffer').height = MacPlayer.Height-80;
	$$('buffer').style.display = 'block';
}
var onPlayBufferingEnd = function(){
	$$('buffer').style.display = 'none';
}
var onComplete = function(){
	onPause();
}
var onAdsEnd = function(){
	if(Player.IsPause()){
		Player.Play();
	}
}


MacPlayer.Html='<object id="Player" classid="clsid:BEF1C903-057D-435E-8223-8EC337C7D3D0" width="100%" height="100%" onError="MacPlayer.Install();"><param name="URL" value="'+ MacPlayer.PlayUrl +'"><param name="NextWebPage" value="'+ MacPlayer.PlayLinkNext +'"><param name="NextCacheUrl" value="'+ MacPlayer.PlayUrlNext +'"><param name="OnPlay" value="onPlay"/><param name="OnPause" value="onPause"/><param name="OnFirstBufferingStart" value="onFirstBufferingStart"/><param name="OnFirstBufferingEnd" value="onFirstBufferingEnd"/><param name="OnPlayBufferingStart" value="onPlayBufferingStart"/><param name="OnPlayBufferingEnd" value="onPlayBufferingEnd"/><param name="OnComplete" value="onComplete"/><param name="Autoplay" value="1"/></object>';
var rMsie = /(msie\s|trident.*rv:)([\w.]+)/;
var match = rMsie.exec(navigator.userAgent.toLowerCase());
if(match == null){
	if (navigator.plugins){
		var ll = false;
		for (var i=0;i<navigator.plugins.length;i++) {
			if(navigator.plugins[i].name == 'XiGua Yingshi Plugin'){
				ll = true;
				break;
			}
		}
	}
	if(ll){
		MacPlayer.Html = '<object id="Player" name="Player" type="application/xgyingshi-activex" progid="xgax.player.1" width="100%" height="100%" param_URL="'+MacPlayer.PlayUrl+'"param_NextCacheUrl="'+MacPlayer.PlayUrlNext+'" param_LastWebPage="" param_NextWebPage="'+MacPlayer.PlayLinkNext+'" param_OnPlay="onPlay" param_OnPause="onPause" param_OnFirstBufferingStart="onFirstBufferingStart" param_OnFirstBufferingEnd="onFirstBufferingEnd" param_OnPlayBufferingStart="onPlayBufferingStart" param_OnPlayBufferingEnd="onPlayBufferingEnd" param_OnComplete="onComplete" param_Autoplay="1"></object>'
	}
	else{
		MacPlayer.Install();
	}
}
MacPlayer.Show();
setTimeout(function(){
	if (MacPlayer.Status == true && MacPlayer.Flag==1){
		if (MacPlayer.PlayLinkNext) {
			MacPlayer.PlayLinkNext = MacPlayer.PlayLinkNext
		}
	}
},
MacPlayer.Second * 1000 + 1000);