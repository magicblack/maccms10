
document.write('<script type="text/javascript" src="//cytroncdn.videojj.com/latest/cytron.core.js"></script>');

MacPlayer.Html = '';
MacPlayer.Show();

setTimeout(function(){
	var ivaInstance = new Iva('playleft', {
		appkey: 'EyPKeiUt',//必填，请在控制台查看应用标识
		video: MacPlayer.PlayUrl,//必填，播放地址
		title: '',//选填，建议填写方便后台数据统计
		vnewsEnable: false,//是否开启新闻推送功能，默认为true
		playerUrl: '', //选填，第三方播放器与Video++互动层的桥接文件，由Video++官方定制提供，默认为空
		videoStartPrefixSeconds: 0,//选填，跳过片头，默认为0
		videoEndPrefixSeconds: 0,//选填，跳过片尾，默认为0
		/* 以下参数可以在“控制台->项目看板->应用管理->播放器设置” >进行全局设置，前端设置可以覆盖全局设置 */
		skinSelect: 0,//选填，播放器皮肤，可选0、1、2，默认为0，
		autoplay: true,//选填，是否自动播放，默认为false
		rightHand: true,//选填，是否开启右键菜单，默认为false
		autoFormat: false,//选填，是否自动选择最高清晰度，默>认为false
		bubble: true,//选填，是否开启云泡功能，默认为true
		jumpStep: 10,//选填，左右方向键快退快进的时间
		tagTrack: false,//选填，云链是否跟踪，默认为false
		tagShow: false,//选填，云链是否显示，默认为false
		tagDuration: 5,//选填，云链显示时间，默认为5秒
		tagFontSize: 16,//选填，云链文字大小，默认为16像素
		editorEnable: true, // 选填，当用户登录之后，是否允许加载编辑器，默认为true
		vorEnable: true, // 选填，是否允许加载灵悟，默认为true
		vorStartGuideEnable: true //选填， 是否启用灵悟新人引导，默认为true
	});
},
MacPlayer.Second * 1000 - 1000);

