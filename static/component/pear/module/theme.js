layui.define(["jquery","layer"], function (exports) {
	var MOD_NAME = 'theme',
	    $ = layui.jquery;

	var theme = {};
	theme.autoHead = false;

	theme.changeTheme = function (target, autoHead) {
		this.autoHead = autoHead;
		const color = localStorage.getItem("theme-color-color");
		const second = localStorage.getItem("theme-color-second");
		this.colorSet(color, second);
		if (target.frames.length == 0) return;
		for (var i = 0; i < target.frames.length; i++) {
			try {
				if(target.frames[i].layui == undefined) continue;
				target.frames[i].layui.theme.changeTheme(target.frames[i], autoHead);
			}
			catch (error) {
				console.log(error);
			}
		}
	}

	theme.colorSet = function(color, second) {
		
		let style = '';
		style += '.light-theme .pear-nav-tree .layui-this a:hover,.light-theme .pear-nav-tree .layui-this,.light-theme .pear-nav-tree .layui-this a,.pear-nav-tree .layui-this a,.pear-nav-tree .layui-this{background-color: ' +color + '!important;}';
		style += '.pear-admin .layui-logo .title{color:' + color + '!important;}';
		style += '.pear-frame-title .dot,.pear-tab .layui-this .pear-tab-active{background-color: ' + color +'!important;}';
		style += '.bottom-nav li a:hover{background-color:' + color + '!important;}';
		style += '.pear-admin .layui-header .layui-nav .layui-nav-bar{background-color: ' + color + '!important;}'
		style += '.ball-loader>span,.signal-loader>span {background-color: ' + color + '!important;}';
		style += '.layui-header .layui-nav-child .layui-this a{background-color:' + color +'!important;color:white!important;}';
		style += '#preloader{background-color:' + color + '!important;}';
		style += '.pearone-color .color-content li.layui-this:after, .pearone-color .color-content li:hover:after {border: ' +color + ' 3px solid!important;}';
		style += '.layui-nav .layui-nav-child dd.layui-this a, .layui-nav-child dd.layui-this{background-color:' + color + ';color:white;}';	
		style += '.pear-social-entrance {background-color:' + color + '!important}';
		style += '.pear-admin .pe-collaspe {background-color:' + color + '!important}';
		style += '.layui-fixbar li {background-color:' + color + '!important}';
		style += '.pear-btn-primary {background-color:' + color + '!important}';
		style += '.layui-input:focus,.layui-textarea:focus {border-color: '+ color +'!important;box-shadow: 0 0 0 3px '+ second +' !important;}'
		style += '.layui-form-checked[lay-skin=primary] i {border-color: '+ color +'!important;background-color: ' + color + ';}'
		style += '.layui-form-onswitch { border-color: ' + color + '; background-color: '+color+';}'
		style += '.layui-form-radio>i:hover, .layui-form-radioed>i {color: ' + color + ';}'
		style += '.layui-laypage .layui-laypage-curr .layui-laypage-em{background-color:'+ color +'!important}'
		style += '.layui-tab-brief>.layui-tab-more li.layui-this:after, .layui-tab-brief>.layui-tab-title .layui-this:after{border-bottom: 3px solid '+color+'!important}'
		style += '.layui-tab-brief>.layui-tab-title .layui-this{color:'+color+'!important}'
		style += '.layui-progress-bar{background-color:'+color+'}';
		style += '.layui-elem-quote{border-left: 5px solid '+ color +'}';
		style += '.layui-timeline-axis{color:' + color + '}';
		style += '.layui-laydate .layui-this{background-color:'+color+'!important}';
		style += '.pear-this,.pear-text{color:' + color + '!important}';
		style += '.pear-back{background-color:'+ color +'!important}';
		style += '.pear-collasped-pe{background-color:'+color+'!important}'
		style += '.layui-form-select dl dd.layui-this{color:'+color+'!important;}'
		style += '.tag-item-normal{background:'+color+'!important}';
		style += '.step-item-head.step-item-head-active{background-color:'+color+'}'
		style += '.step-item-head{border: 3px solid '+color+';}'
		style += '.step-item-tail i{background-color:'+color+'}'
		style += '.step-item-head{color:' + color + '}'
		style += 'div[xm-select-skin=normal] .xm-select-title div.xm-select-label>span i {background-color:'+color+'!important}'
		style += 'div[xm-select-skin=normal] .xm-select-title div.xm-select-label>span{border: 1px solid '+color+'!important;background-color:'+color+'!important}'
		style += 'div[xm-select-skin=normal] dl dd:not(.xm-dis-disabled) i{border-color:'+color+'!important}'
		style += 'div[xm-select-skin=normal] dl dd.xm-select-this:not(.xm-dis-disabled) i{color:'+color+'!important}'
		style += 'div[xm-select-skin=normal].xm-form-selected .xm-select, div[xm-select-skin=normal].xm-form-selected .xm-select:hover{border-color:'+color+'!important}'
		style += '.layui-layer-btn a:first-child{border-color:'+color+';background-color:'+color+'!important}';
		style += '.layui-form-checkbox[lay-skin=primary]:hover i{border-color:'+color+'!important}'
		style += '.pear-tab-menu .item:hover{background-color:'+color+'!important}'
		style += '.layui-form-danger:focus {border-color:#FF5722 !important}'
		style += '.pear-admin .user .layui-this a:hover{color:white!important}'
		style += '.pear-notice .layui-this{color:'+color+'!important}'
        style += '.layui-form-radio:hover *, .layui-form-radioed, .layui-form-radioed>i{color:' + color + ' !important}';
		style += '.pear-btn:hover {color: '+color+';background-color: ' + second + ';}'
		style += '.pear-btn-primary[plain] {color: '+ color +' !important;background: ' + second + ' !important;}'
		style += '.pear-btn-primary[plain]:hover {background-color: ' + color + '!important}'
		style += '.light-theme .pear-nav-tree .layui-this a:hover,.light-theme .pear-nav-tree .layui-this,.light-theme .pear-nav-tree .layui-this a {background-color:'+second+'!important;color:'+color+'!important;}'
		style += '.light-theme .pear-nav-tree .layui-this{ border-right: 3px solid '+color+'!important}'
		style += '.loader:after {background:'+color+'}'
		if(this.autoHead === true || this.autoHead === "true"){
			style += '.pear-admin.banner-layout .layui-header .layui-logo,.pear-admin .layui-header{border:none;background-color:' + color + '!important;}.pear-admin.banner-layout .layui-header .layui-logo .title,.pear-admin .layui-header .layui-nav .layui-nav-item>a{color:whitesmoke!important;}';
			style += '.pear-admin.banner-layout .layui-header{ box-shadow: 2px 0 6px rgb(0 21 41 / 35%) }'
			style += '.pear-admin .layui-header .layui-layout-control .layui-this *,.pear-admin.banner-layout .layui-header .layui-layout-control .layui-this *{ background-color: rgba(0,0,0,.1)!important;}'
		}
		var colorPane = $("#pear-admin-color");
		if(colorPane.length>0){
			colorPane.html(style);
		}else{
			$("head").append("<style id='pear-admin-color'>"+style+"</style>")
		}
	}

	exports(MOD_NAME, theme);
});