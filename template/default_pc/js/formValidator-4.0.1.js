//====================================================================================================
// [插件名称] jQuery formValidator
//----------------------------------------------------------------------------------------------------
// [描    述] jQuery formValidator表单验证插件，它是基于jQuery类库，实现了js脚本于页面的分离。对一个表
//            单对象，你只需要写一行代码就可以轻松实现20种以上的脚本控制。现支持一个表单元素累加很多种
//            校验方式,采用配置信息的思想，而不是把信息写在表单元素上，能比较完美的实现ajax请求。
//----------------------------------------------------------------------------------------------------
// [作者网名] 猫冬	
// [邮    箱] wzmaodong@126.com
// [作者博客] http://wzmaodong.cnblogs.com
// [QQ群交流] 74106519
// [更新日期] 2011-05-22
// [版 本 号] ver4.0.1
//====================================================================================================
(function($) {

$.formValidator = 
{
	//全局配置
	initConfig : function(controlOptions)
	{
		var settings = 
		{
			debug:false,								//调试模式
			validatorGroup : "1",						//分组号
			alertMessage:false,							//是否为弹出窗口提示模式
			validObjects:[],							//参加校验的控件集合
			ajaxObjects:"",							//传到服务器的控件列表
			forceValid:false,							//控件输入正确之后，才允许失去焦点
			onSuccess: function() {return true;},		//提交成功后的回调函数
			onError: $.noop,						//提交失败的回调函数
			submitOnce:false,							//页面是否提交一次，不会停留
			formID:"",					//表单ID
			submitButtonID:"",			//提交按钮ID
			autoTip: false,				//是否自动构建提示层
			tidyMode:false,				//精简模式
			errorFocus:true,			//第一个错误的控件获得焦点
			wideWord:true,				//一个汉字当做2个长度
			status:"",					//提交的状态：submited、sumbiting、sumbitingWithAjax
			submitAfterAjaxPrompt : "当前有数据正在进行服务器端校验，请稍候",	//控件失去焦点后，触发ajax校验，没有返回结果前的错误提示
			validCount:0,			//含ajaxValidator的控件个数
			ajaxCountSubmit:0,		//提交的时候触发的ajax验证个数
			ajaxCountValid:0,		//失去焦点时候触发的ajax验证个数
			inIframe:false
			
		};
		controlOptions = controlOptions || {};
		$.extend(settings, controlOptions);
		//如果是精简模式，发生错误的时候，第一个错误的控件就不获得焦点
		if(settings.tidyMode){settings.errorFocus=false};
		//如果填写了表单和按钮，就注册验证事件
		if(settings.formID!=""){
			$("#"+settings.formID).submit(function(){return $.formValidator.bindSubmit(settings);});
		}
		else if(settings.submitButtonID!="")
		{
			$("#"+settings.submitButtonID).click(function(){return $.formValidator.bindSubmit(settings);});
		}
		$('body').data(settings.validatorGroup, settings);
	},
	
	//调用验证函数
	bindSubmit : function(settings)
	{
		if (settings.ajaxCountValid > 0 && settings.submitAfterAjaxPrompt != "") {
			alert(settings.submitAfterAjaxPrompt);	
			return false;
		}
		return $.formValidator.pageIsValid(settings.validatorGroup);
	},
	
	//各种校验方式支持的控件类型
	sustainType : function(id,setting)
	{
		var elem = $("#"+id).get(0);
		var srcTag = elem.tagName;
		var stype = elem.type;
		switch(setting.validateType)
		{
			case "InitValidator":
				return true;
			case "InputValidator":
				return (srcTag == "INPUT" || srcTag == "TEXTAREA" || srcTag == "SELECT");
			case "CompareValidator":
				return ((srcTag == "INPUT" || srcTag == "TEXTAREA") ? (stype != "checkbox" && stype != "radio") : false);
			case "AjaxValidator":
				return (stype == "text" || stype == "textarea" || stype == "file" || stype == "password" || stype == "select-one");
			case "RegexValidator":
				return ((srcTag == "INPUT" || srcTag == "TEXTAREA") ? (stype != "checkbox" && stype != "radio") : false);
			case "FunctionValidator":
			    return true;
		}
	},
    
	//如果validator对象对应的element对象的validator属性追加要进行的校验。
	appendValid : function(id, setting )
	{
		//如果是各种校验不支持的类型，就不追加到。返回-1表示没有追加成功
		if(!$.formValidator.sustainType(id,setting)) return -1;
		var srcjo = $("#"+id).get(0);   
		//重新初始化
		if (setting.validateType=="InitValidator" || srcjo.settings == undefined ){srcjo.settings = new Array();}   
		var len = srcjo.settings.push( setting );
		srcjo.settings[len - 1].index = len - 1;
		return len - 1;
	},
	
	//设置显示信息
	setTipState : function(elem,showclass,showmsg)
	{
		var initConfig = $('body').data(elem.validatorGroup);
	    var tip = $("#"+elem.settings[0].tipID);
		if(showmsg==null || showmsg=="")
		{
			tip.hide();
		}
		else
		{
			if(initConfig.tidyMode)
			{
				//显示和保存提示信息
				$("#fv_content").html(showmsg);
				elem.Tooltip = showmsg;
				if(showclass!="onError"){tip.hide();}
			}
			else
			{
				tip.show().removeClass().addClass( showclass+" ui-message" ).html( showmsg );
			}
		}
	},
		
	//把提示层重置成原始提示(如果有defaultPassed,应该设置为onCorrect)
	resetTipState : function(validatorGroup)
	{
		if(validatorGroup == undefined){validatorGroup = "1"};
		var initConfig = $('body').data(validatorGroup);
		$.each(initConfig.validObjects,function(){
			var elem = this.get(0);
			var setting = elem.settings[0];
			var passed = setting.defaultPassed;
			$.formValidator.setTipState(elem, passed ? "onCorrect" : "onShow", passed ? setting.onCorrect : setting.onShow);	
		});
	},
	
	//设置错误的显示信息
	setFailState : function(tipID,showmsg)
	{
	    var tip = $("#"+tipID);
	    tip.removeClass().addClass("onError ui-message ui-warning").html(showmsg);
	},

	//根据单个对象,正确:正确提示,错误:错误提示
	showMessage : function(returnObj)
	{
	    var id = returnObj.id;
		var elem = $("#"+id).get(0);
		var isValid = returnObj.isValid;
		var setting = returnObj.setting;//正确:setting[0],错误:对应的setting[i]
		var showmsg = "",showclass = "";
		var intiConfig = $('body').data(elem.validatorGroup);
		if (!isValid)
		{		
			showclass = "onError";
			if(setting.validateType=="AjaxValidator")
			{
				if(setting.lastValid=="")
				{
				    showclass = "onLoad";
				    showmsg = setting.onWait;
				}
				else
				{
				    showmsg = setting.onError;
				}
			}
			else
			{
				showmsg = (returnObj.errormsg==""? setting.onError : returnObj.errormsg);
				
			}
			if(intiConfig.alertMessage)		
			{
				if(elem.validValueOld!=$(elem).val()){alert(showmsg);}   
			}
			else
			{
				$.formValidator.setTipState(elem,showclass,showmsg);
			}
		}
		else
		{		
			//验证成功后,如果没有设置成功提示信息,则给出默认提示,否则给出自定义提示;允许为空,值为空的提示
			showmsg = $.formValidator.isEmpty(id) ? setting.onEmpty : setting.onCorrect;
			$.formValidator.setTipState(elem,"onCorrect",showmsg);
		}
		return showmsg;
	},

	showAjaxMessage : function(returnObj)
	{
		var elem = $("#"+returnObj.id).get(0);
		var setting = elem.settings[returnObj.ajax];
		var validValueOld = elem.validValueOld;
		var validvalue = $(elem).val();
		returnObj.setting = setting;
		//defaultPassed还未处理
		if(validValueOld!= validvalue || validValueOld == validvalue && !elem.onceValided)
		{
			$.formValidator.ajaxValid(returnObj);
		}
		else
		{
			if(setting.isValid!=undefined && !setting.isValid){
				elem.lastshowclass = "onError"; 
				elem.lastshowmsg = setting.onError;
			}
			$.formValidator.setTipState(elem,elem.lastshowclass,elem.lastshowmsg);
		}
	},

	//获取指定字符串的长度
    getLength : function(id)
    {
        var srcjo = $("#"+id);
		var elem = srcjo.get(0);
        var sType = elem.type;
        var len = 0;
        switch(sType)
		{
			case "text":
			case "hidden":
			case "password":
			case "textarea":
			case "file":
		        var val = srcjo.val();
				var initConfig = $('body').data(elem.validatorGroup);
				if (initConfig.wideWord)
				{
					for (var i = 0; i < val.length; i++) 
					{
						len = len + ((val.charCodeAt(i) >= 0x4e00 && val.charCodeAt(i) <= 0x9fa5) ? 2 : 1); 
					}
				}
				else{
					len = val.length;
				}
		        break;
			case "checkbox":
			case "radio": 
				len = $("input[type='"+sType+"'][name='"+srcjo.attr("name")+"']:checked").length;
				break;
		    case "select-one":
		        len = elem.options ? elem.options.selectedIndex : -1;
				break;
			case "select-multiple":
				len = $("select[name="+elem.name+"] option:selected").length;
				break;
	    }
		return len;
    },
    
	//结合empty这个属性，判断仅仅是否为空的校验情况。
    isEmpty : function(id)
    {
        return ($("#"+id).get(0).settings[0].empty && $.formValidator.getLength(id)==0);
    },
    
	//对外调用：判断单个表单元素是否验证通过，不带回调函数
    isOneValid : function(id)
    {
	    return $.formValidator.oneIsValid(id).isValid;
    },
    
	//验证单个是否验证通过,正确返回settings[0],错误返回对应的settings[i]
	oneIsValid : function (id)
	{
		var returnObj = new Object();
		var elem = $("#"+id).get(0);
		returnObj.initConfig = $('body').data(elem.validatorGroup);
		returnObj.id = id;
		returnObj.ajax = -1;
		returnObj.errormsg = "";       //自定义错误信息
	    var settings = elem.settings;
	    var settingslen = settings.length;
		var validateType;
		//只有一个formValidator的时候不检验
		if (settingslen==1){settings[0].bind=false;}
		if(!settings[0].bind){return null;}
		for ( var i = 0 ; i < settingslen ; i ++ )
		{   
			if(i==0){
				//如果为空，直接返回正确
				if($.formValidator.isEmpty(id)){
					returnObj.isValid = true;
					returnObj.setting = settings[0];
					break;
				}
				continue;
			}
			returnObj.setting = settings[i];
			validateType = settings[i].validateType;
			//根据类型触发校验
			switch(validateType)
			{
				case "InputValidator":
					$.formValidator.inputValid(returnObj);
					break;
				case "CompareValidator":
					$.formValidator.compareValid(returnObj);
					break;
				case "RegexValidator":
					$.formValidator.regexValid(returnObj);
					break;
				case "FunctionValidator":
					$.formValidator.functionValid(returnObj);
					break;
				case "AjaxValidator":
					//如果是ajax校验，这里直接取上次的结果值
					returnObj.ajax = i
					break;
			}
			//校验过一次
			elem.onceValided = true;
			if(!settings[i].isValid) {
				returnObj.isValid = false;
				returnObj.setting = settings[i];
				break;
			}else{
				returnObj.isValid = true;
				returnObj.setting = settings[0];
				if (settings[i].validateType == "AjaxValidator"){break};
			}
		}
		return returnObj;
	},

	//验证所有需要验证的对象，并返回是否验证成功（如果曾经触发过ajaxValidator，提交的时候就不触发校验，直接读取结果）
	pageIsValid : function (validatorGroup)
	{
	    if(validatorGroup == undefined){validatorGroup = "1"};
		var isValid = true,returnObj,firstErrorMessage="",errorMessage;
		var error_tip = "^",thefirstid,name,name_list="^"; 	
		var errorlist = new Array();
		//设置提交状态、ajax是否出错、错误列表
		var initConfig = $('body').data(validatorGroup);
		initConfig.status = "sumbiting";
		initConfig.ajaxCountSubmit = 0;
		//遍历所有要校验的控件,如果存在ajaxValidator就先直接触发
		$.each(initConfig.validObjects,function()
		{
			if (this.settings[0].bind && this.validatorAjaxIndex!=undefined && this.onceValided == undefined) {
				returnObj = $.formValidator.oneIsValid(this.id);
				if (returnObj.ajax == this.validatorAjaxIndex) {
					initConfig.status = "sumbitingWithAjax";
					$.formValidator.ajaxValid(returnObj);
				}
			}
		});
		//如果有提交的时候有触发ajaxValidator，所有的处理都放在ajax里处理
		if(initConfig.ajaxCountSubmit > 0){return false}
		//遍历所有要校验的控件
		$.each(initConfig.validObjects,function()
		{
			//只校验绑定的控件
			if(this.settings[0].bind){
				name = this.name;
				//相同name只校验一次
				if (name_list.indexOf("^"+name+"^") == -1) {
					onceValided = this.onceValided == undefined ? false : this.onceValided;
					if(name){name_list = name_list + name + "^"};
					returnObj = $.formValidator.oneIsValid(this.id);
					if (returnObj) {
						//校验失败,获取第一个发生错误的信息和ID
						if (!returnObj.isValid) {
							//记录不含ajaxValidator校验函数的校验结果
							isValid = false;
							errorMessage = returnObj.errormsg == "" ? returnObj.setting.onError : returnObj.errormsg;
							errorlist[errorlist.length] = errorMessage;
							if (thefirstid == null) {thefirstid = returnObj.id};
							if(firstErrorMessage==""){firstErrorMessage=errorMessage};
						}
						//为了解决使用同个TIP提示问题:后面的成功或失败都不覆盖前面的失败
						if (!initConfig.alertMessage) {
							var tipID = this.settings[0].tipID;
							if (error_tip.indexOf("^" + tipID + "^") == -1) {
								if (!returnObj.isValid) {error_tip = error_tip + tipID + "^"};
								$.formValidator.showMessage(returnObj);
							}
						}
					}
				}
			}
		});
		
		//成功或失败进行回调函数的处理，以及成功后的灰掉提交按钮的功能
		if(isValid)
		{
            initConfig.onSuccess();
			if(initConfig.submitOnce){$(":submit,:button,:reset").attr("disabled",true);}
			return false;
		}
		else
		{
			initConfig.onError(firstErrorMessage, $("#" + thefirstid).get(0), errorlist);
			if (thefirstid && initConfig.errorFocus) {$("#" + thefirstid).focus()};
		}
		initConfig.status="init";
		return !initConfig.debug && isValid;
	},

	//ajax校验
	ajaxValid : function(returnObj)
	{
		var id = returnObj.id;
	    var srcjo = $("#"+id);
		var elem = srcjo.get(0);
		var initConfig = returnObj.initConfig;
		var settings = elem.settings;
		var setting = settings[returnObj.ajax];
		var ls_url = setting.url;
		//获取要传递的参数
		var validatorGroup = elem.validatorGroup;
		var initConfig = $('body').data(validatorGroup);
		var parm = $(initConfig.ajaxObjects).serialize();
		//添加触发的控件名、随机数、传递的参数
		var parm = encodeURIComponent(srcjo.val())+"-"+Math.random();//"clientid="+id+"&"+id+"="+encodeURIComponent(srcjo.val());
			ls_url = ls_url + parm;//(ls_url.indexOf("?")>0?("&"+ parm) : ("?"+parm));
		//parm = "clientid=" + id + "&" +(setting.randNumberName ? setting.randNumberName+"="+((new Date().getTime())+Math.round(Math.random() * 10000)) : "") + (parm.length > 0 ? "&" + parm : "");
		//ls_url = ls_url + (ls_url.indexOf("?") > -1 ? ("&" + parm) : ("?" + parm));
		//发送ajax请求
		//alert(setting.type);
		if(setting.type=='post')
		{
			setting.data=srcjo.attr('id')+"="+srcjo.val();
		}
		$.ajax(
		{	
			type : setting.type, 
			url : ls_url, 
			cache : setting.cache,
			data : setting.data, 
			async : setting.async, 
			timeout : setting.timeout, 
			dataType : setting.dataType, 
			success : function(data, textStatus, jqXHR){
				var lb_ret,ls_status,ls_msg;
				$.formValidator.dealAjaxRequestCount(validatorGroup,-1);
				//根据业务判断设置显示信息
				lb_ret = setting.success(data, textStatus, jqXHR);
				setting.isValid = lb_ret;
				if(lb_ret){
					ls_status = "onCorrect";
					ls_msg = data.data!=undefined?data.data:settings[0].onCorrect;
				}else{
					ls_status = "onError";
					ls_msg = data.data!=undefined?data.data:setting.onError;
				}
				$.formValidator.setTipState(elem,ls_status,ls_msg);
				//提交的时候触发了ajax校验，等ajax校验完成，无条件重新校验
				if(returnObj.initConfig.status=="sumbitingWithAjax" && returnObj.initConfig.ajaxCountSubmit == 0)
				{
					if (initConfig.formID != "") {
						$('#' + initConfig.formID).trigger('submit');
					}else if (initConfig.formID != ""){
						$('#' + initConfig.submitButtonID).trigger('click');
					}
				}
			},
			complete : function(jqXHR, textStatus){
				if(setting.buttons && setting.buttons.length > 0){setting.buttons.attr({"disabled":false})};
				setting.complete(jqXHR, textStatus);
			}, 
			beforeSend : function(jqXHR, configs){
				//本控件如果正在校验，就中断上次
				if (this.lastXMLHttpRequest) {this.lastXMLHttpRequest.abort()};
				this.lastXMLHttpRequest = jqXHR;
				//再服务器没有返回数据之前，先回调提交按钮
				if(setting.buttons && setting.buttons.length > 0){setting.buttons.attr({"disabled":true})};
				var isValid = setting.beforeSend(jqXHR,configs);
				if(isValid)
				{
					setting.isValid = false;		//如果前面ajax请求成功了，再次请求之前先当作错误处理
					$.formValidator.setTipState(elem,"onLoad",settings[returnObj.ajax].onWait);
				}
				setting.lastValid = "-1";
				if(isValid){$.formValidator.dealAjaxRequestCount(validatorGroup,1);}
				return isValid;
			}, 
			error : function(jqXHR, textStatus, errorThrown){
				$.formValidator.dealAjaxRequestCount(validatorGroup,-1);
			    $.formValidator.setTipState(elem,"onError",setting.onError);
			    setting.isValid = false;
				setting.error(jqXHR, textStatus, errorThrown);
			},
			processData : setting.processData 
		});
	},
	
	//处理ajax的请求个数
	dealAjaxRequestCount : function(validatorGroup,val)
	{
		var initConfig = $('body').data(validatorGroup);
		initConfig.ajaxCountValid = initConfig.ajaxCountValid + val;
		if (initConfig.status == "sumbitingWithAjax") {
			initConfig.ajaxCountSubmit = initConfig.ajaxCountSubmit + val;
		}
	},

	//对正则表达式进行校验（目前只针对input和textarea）
	regexValid : function(returnObj)
	{
		var id = returnObj.id;
		var setting = returnObj.setting;
		var srcTag = $("#"+id).get(0).tagName;
		var elem = $("#"+id).get(0);
		var isValid;
		//如果有输入正则表达式，就进行表达式校验
		if(elem.settings[0].empty && elem.value==""){
			setting.isValid = true;
		}
		else 
		{
			var regexArray = setting.regExp;
			setting.isValid = false;
			if((typeof regexArray)=="string") regexArray = [regexArray];
			$.each(regexArray, function() {
			    var r = this;
			    if(setting.dataType=="enum"){r = eval("regexEnum."+r);}			
			    if(r==undefined || r=="") 
			    {
			        return false;
			    }
			    isValid = (new RegExp(r, setting.param)).test($(elem).val());
			    
			    if(setting.compareType=="||" && isValid)
			    {
			        setting.isValid = true;
			        return false;
			    }
			    if(setting.compareType=="&&" && !isValid) 
			    {
			        return false
			    }
            });
            if(!setting.isValid) setting.isValid = isValid;
		}
	},
	
	//函数校验。返回true/false表示校验是否成功;返回字符串表示错误信息，校验失败;如果没有返回值表示处理函数，校验成功
	functionValid : function(returnObj)
	{
		var id = returnObj.id;
		var setting = returnObj.setting;
	    var srcjo = $("#"+id);
		var lb_ret = setting.fun(srcjo.val(),srcjo.get(0));
		if(lb_ret != undefined) 
		{
			if((typeof lb_ret) === "string"){
				setting.isValid = false;
				returnObj.errormsg = lb_ret;
			}else{
				setting.isValid = lb_ret;
			}
		}
	},
	
	//对input和select类型控件进行校验
	inputValid : function(returnObj)
	{
		var id = returnObj.id;
		var setting = returnObj.setting;
		var srcjo = $("#"+id);
		var elem = srcjo.get(0);
		var val = srcjo.val();
		var sType = elem.type;
		var len = $.formValidator.getLength(id);
		var empty = setting.empty,emptyError = false;
		switch(sType)
		{
			case "text":
			case "hidden":
			case "password":
			case "textarea":
			case "file":
				if (setting.type == "size") {
					empty = setting.empty;
					if(!empty.leftEmpty){
						emptyError = (val.replace(/^[ \s]+/, '').length != val.length);
					}
					if(!emptyError && !empty.rightEmpty){
						emptyError = (val.replace(/[ \s]+$/, '').length != val.length);
					}
					if(emptyError && empty.emptyError){returnObj.errormsg= empty.emptyError}
				}
			case "checkbox":
			case "select-one":
			case "select-multiple":
			case "radio":
				var lb_go_on = false;
				if(sType=="select-one" || sType=="select-multiple"){setting.type = "size";}
				var type = setting.type;
				if (type == "size") {		//获得输入的字符长度，并进行校验
					if(!emptyError){lb_go_on = true}
					if(lb_go_on){val = len}
				}
				else if (type =="date" || type =="datetime")
				{
					var isok = false;
					if(type=="date"){lb_go_on = isDate(val)};
					if(type=="datetime"){lb_go_on = isDate(val)};
					if(lb_go_on){val = new Date(val);setting.min=new Date(setting.min);setting.max=new Date(setting.max);};
				}else{
					stype = (typeof setting.min);
					if(stype =="number")
					{
						val = (new Number(val)).valueOf();
						if(!isNaN(val)){lb_go_on = true;}
					}
					if(stype =="string"){lb_go_on = true;}
				}
				setting.isValid = false;
				if(lb_go_on)
				{
					if(val < setting.min || val > setting.max){
						if(val < setting.min && setting.onErrorMin){
							returnObj.errormsg= setting.onErrorMin;
						}
						if(val > setting.min && setting.onErrorMax){
							returnObj.errormsg= setting.onErrorMax;
						}
					}
					else{
						setting.isValid = true;
					}
				}
				break;
		}
	},
	
	//对两个控件进行比较校验
	compareValid : function(returnObj)
	{
		var id = returnObj.id;
		var setting = returnObj.setting;
		var srcjo = $("#"+id);
	    var desjo = $("#"+setting.desID );
		var ls_dataType = setting.dataType;
		
		curvalue = srcjo.val();
		ls_data = desjo.val();
		if(ls_dataType=="number")
        {
            if(!isNaN(curvalue) && !isNaN(ls_data)){
				curvalue = parseFloat(curvalue);
                ls_data = parseFloat(ls_data);
			}
			else{
			    return;
			}
        }
		if(ls_dataType=="date" || ls_dataType=="datetime")
		{
			var isok = false;
			if(ls_dataType=="date"){isok = (isDate(curvalue) && isDate(ls_data))};
			if(ls_dataType=="datetime"){isok = (isDateTime(curvalue) && isDateTime(ls_data))};
			if(isok){
				curvalue = new Date(curvalue);
				ls_data = new Date(ls_data)
			}
			else{
				return;
			}
		}
		
	    switch(setting.operateor)
	    {
	        case "=":
	            setting.isValid = (curvalue == ls_data);
	            break;
	        case "!=":
	            setting.isValid = (curvalue != ls_data);
	            break;
	        case ">":
	            setting.isValid = (curvalue > ls_data);
	            break;
	        case ">=":
	            setting.isValid = (curvalue >= ls_data);
	            break;
	        case "<": 
	            setting.isValid = (curvalue < ls_data);
	            break;
	        case "<=":
	            setting.isValid = (curvalue <= ls_data);
	            break;
			default :
				setting.isValid = false;
				break; 
	    }
	},
	
	//定位漂浮层
	localTooltip : function(e)
	{
		e = e || window.event;
		var mouseX = e.pageX || (e.clientX ? e.clientX + document.body.scrollLeft : 0);
		var mouseY = e.pageY || (e.clientY ? e.clientY + document.body.scrollTop : 0);
		$("#fvtt").css({"top":(mouseY+2)+"px","left":(mouseX-40)+"px"});
	},
	
	reloadAutoTip : function(validatorGroup)
	{
		if(validatorGroup == undefined) validatorGroup = "1";
		var initConfig = $('body').data(validatorGroup);
		$.each(initConfig.validObjects,function()
		{
			if(initConfig.autoTip && !initConfig.tidyMode)
			{
				//获取层的ID、相对定位控件的ID和坐标
				var setting = this.settings[0];
				var relativeID = "#"+setting.relativeID;
				var offset = $(relativeID ).offset();
				var y = offset.top;
				var x = $(relativeID ).width() + offset.left;
				$("#"+setting.tipID).parent().show().css({left: x+"px", top: y+"px"});			
			}
		});
	}
};

//每个校验控件必须初始化的
$.fn.formValidator = function(cs) 
{
	var setting = 
	{
		validatorGroup : "1",
		empty :false,
		autoModify : false,
		onShow :"请输入内容",
		onFocus: "请输入内容",
		onCorrect: "输入正确",
		onEmpty: "输入内容为空",
		defaultValue : null,
		bind : true,
		ajax : false,
		validateType : "InitValidator",
		tipCss : 
		{
			"left" : "10px",
			"top" : "1px",
			"height" : "20px",
			"width":"250px"
		},
		triggerEvent:"blur",
		forceValid : false,
		tipID : null,
		relativeID : null,
		index : 0
	};

	//获取该校验组的全局配置信息
	cs = cs || {};
	if(cs.validatorGroup == undefined){cs.validatorGroup = "1"};
	
	var initConfig = $('body').data(cs.validatorGroup);
	
	//校验索引号，和总记录数
	initConfig.validCount += 1;
	
	//如果为精简模式，tipCss要重新设置初始值
	if(initConfig.tidyMode){setting.tipCss = {"left" : "2px","width":"22px","height":"22px","display":"none"}};
	
	//弹出消息提示模式，自动修复错误
	if(initConfig.alertMessage){setting.autoModify=true};
	
	//先合并整个配置(深度拷贝)
	$.extend(true,setting, cs);

	return this.each(function(e)
	{
		//记录该控件的校验顺序号和校验组号
		this.validatorIndex = initConfig.validCount - 1;
		this.validatorGroup = cs.validatorGroup;
		var jqobj = $(this);
		//自动形成TIP
		var setting_temp = {};
		$.extend(true,setting_temp, setting);
		var tip = setting_temp.tipID ? setting_temp.tipID : this.id+"Tip";
		if(initConfig.autoTip)
		{
			if(!initConfig.tidyMode)
			{				
				//获取层的ID、相对定位控件的ID和坐标
				if($("body [id="+tip+"]").length==0)
				{		
					var relativeID = setting_temp.relativeID ? setting_temp.relativeID : this.id;
					var offset = $("#"+relativeID ).position();
					var y = offset.top;
					var x = $("#"+relativeID ).width() + offset.left;
					var formValidateTip = $("<div class='formValidateTip'></div>");
					if(initConfig.inIframe){formValidateTip.hide();}
					formValidateTip.appendTo($("body")).css({left: x+"px", top: y+"px"}).prepend($('<div id="'+tip+'"></div>').css(setting_temp.tipCss));
					setting.relativeID = relativeID ;
				}
			}
			else
			{
				jqobj.showTooltips();
			}
		}
		//每个控件都要保存这个配置信息、为了取数方便，冗余一份控件总体配置到控件上
		setting.tipID = tip;
		$.formValidator.appendValid(this.id,setting);

		//保存控件ID
		if($.inArray(jqobj,initConfig.validObjects) == -1)
		{
			if (setting_temp.ajax) {
				var ajax = initConfig.ajaxObjects;
				initConfig.ajaxObjects = ajax + (ajax != "" ? ",#" : "#") + this.id;
			}
			initConfig.validObjects.push(this);
		}

		//初始化显示信息
		if(!initConfig.alertMessage){
			$.formValidator.setTipState(this,"onShow",setting.onShow);
		}

		var srcTag = this.tagName.toLowerCase();
		var stype = this.type;
		var defaultval = setting.defaultValue;
		//处理默认值
		if(defaultval){
			jqobj.val(defaultval);
		}

		if(srcTag == "input" || srcTag=="textarea")
		{
			//注册获得焦点的事件。改变提示对象的文字和样式，保存原值
			jqobj.focus(function()
			{	
				if(!initConfig.alertMessage){
					//保存原来的状态
					var tipjq = $("#"+tip);
					this.lastshowclass = tipjq.attr("class");
					this.lastshowmsg = tipjq.html();
					$.formValidator.setTipState(this,"onFocus",setting.onFocus);
				}
				if (stype == "password" || stype == "text" || stype == "textarea" || stype == "file") {
					this.validValueOld = jqobj.val();
				}
			});
			//注册失去焦点的事件。进行校验，改变提示对象的文字和样式；出错就提示处理
			jqobj.bind(setting.triggerEvent, function(){
				var settings = this.settings;
				var returnObj = $.formValidator.oneIsValid(this.id);
				if(returnObj==null){return;}
				if(returnObj.ajax >= 0) 
				{
					$.formValidator.showAjaxMessage(returnObj);
				}
				else
				{
					var showmsg = $.formValidator.showMessage(returnObj);
					if(!returnObj.isValid)
					{
						//自动修正错误
						var auto = setting.autoModify && (this.type=="text" || this.type=="textarea" || this.type=="file");
						if(auto)
						{
							$(this).val(this.validValueOld);
							if(!initConfig.alertMessage){$.formValidator.setTipState(this,"onShow",setting.onShow)};
						}
						else
						{
							if(initConfig.forceValid || setting.forceValid){
								alert(showmsg);this.focus();
							}
						}
					}
				}
			});
		} 
		else if (srcTag == "select")
		{
			jqobj.bind({
				//获得焦点
				focus: function(){	
					if (!initConfig.alertMessage) {
						$.formValidator.setTipState(this, "onFocus", setting.onFocus)
					};
				},
				//失去焦点
				blur: function(){$(this).trigger("change")},
				//选择项目后触发
				change: function(){
					var returnObj = $.formValidator.oneIsValid(this.id);	
					if(returnObj==null){return;}
					if ( returnObj.ajax >= 0){
						$.formValidator.showAjaxMessage(returnObj);
					}else{
						$.formValidator.showMessage(returnObj); 
					}
				}
			});
		}
	});
}; 

$.fn.inputValidator = function(controlOptions)
{
	var settings = 
	{
		isValid : false,
		min : 0,
		max : 99999999999999,
		type : "size",
		onError:"输入错误",
		validateType:"InputValidator",
		empty:{leftEmpty:true,rightEmpty:true,leftEmptyError:null,rightEmptyError:null}
	};
	controlOptions = controlOptions || {};
	$.extend(true, settings, controlOptions);
	return this.each(function(){
		$.formValidator.appendValid(this.id,settings);
	});
};

$.fn.compareValidator = function(controlOptions)
{
	var settings = 
	{
		isValid : false,
		desID : "",
		operateor :"=",
		onError:"输入错误",
		validateType:"CompareValidator"
	};
	controlOptions = controlOptions || {};
	$.extend(true, settings, controlOptions);
	return this.each(function(){
		$.formValidator.appendValid(this.id,settings);
	});
};

$.fn.regexValidator = function(controlOptions)
{
	var settings = 
	{
		isValid : false,
		regExp : "",
		param : "i",
		dataType : "string",
		compareType : "||",
		onError:"输入的格式不正确",
		validateType:"RegexValidator"
	};
	controlOptions = controlOptions || {};
	$.extend(true, settings, controlOptions);
	return this.each(function(){
		$.formValidator.appendValid(this.id,settings);
	});
};

$.fn.functionValidator = function(controlOptions)
{
	var settings = 
	{
		isValid : true,
		fun : function(){this.isValid = true;},
		validateType:"FunctionValidator",
		onError:"输入错误"
	};
	controlOptions = controlOptions || {};
	$.extend(true, settings, controlOptions);
	return this.each(function(){
		$.formValidator.appendValid(this.id,settings);
	});
};

$.fn.ajaxValidator = function(controlOptions)
{
	var settings = 
	{
		type : "GET",
		url : "",
		dataType : "html",
		timeout : 100000,
		data : null,
		async : true,
		cache : false,
		beforeSend : function(){return true;},
		success : function(){return true;},
		complete : function(){},
		processData : true,
		error : function(){},
		isValid : false,
		lastValid : "",
		buttons : null,
		oneceValid : false,
		randNumberName : "rand",
		onError:"服务器校验没有通过",
		onWait:"正在等待服务器返回数据",
		ajaxExistsError:"前面的校验尚未完成，请稍候...",
		validateType:"AjaxValidator"
			
	};
	controlOptions = controlOptions || {};
	$.extend(true, settings, controlOptions);
	return this.each(function()
	{
		var initConfig = $('body').data(this.validatorGroup);
		var ajax = initConfig.ajaxObjects;
		if((ajax+",").indexOf("#"+this.id+",") == -1)
		{
			initConfig.ajaxObjects = ajax + (ajax != "" ? ",#" : "#") + this.id;
		}
		this.validatorAjaxIndex = $.formValidator.appendValid(this.id,settings);
	});
};

//指定控件显示通过或不通过样式
$.fn.defaultPassed = function(onShow)
{
	return this.each(function()
	{
		var settings = this.settings;
		settings[0].defaultPassed = true;
		for ( var i = 1 ; i < settings.length ; i ++ )
		{   
			settings[i].isValid = true;
			if(!$('body').data(settings[0].validatorGroup).alertMessage){
				var ls_style = onShow ? "onShow" : "onCorrect";
				$.formValidator.setTipState(this,ls_style,settings[0].onCorrect);
			}
		}
	});
};

//指定控件不参加校验
$.fn.unFormValidator = function(unbind)
{
	return this.each(function()
	{
		this.settings[0].bind = !unbind;
		if(unbind){
			$("#"+this.settings[0].tipID).hide();
		}else{
			$("#"+this.settings[0].tipID).show();
		}
	});
};

//显示漂浮显示层
$.fn.showTooltips = function()
{
	if($("body [id=fvtt]").length==0){
		fvtt = $("<div id='fvtt' style='position:absolute;z-index:56002'></div>");
		$("body").append(fvtt);
		fvtt.before("<iframe src='about:blank' class='fv_iframe' scrolling='no' frameborder='0'></iframe>");
		
	}
	return this.each(function()
	{
		jqobj = $(this);
		s = $("<span class='top' id=fv_content style='display:block'></span>");
		b = $("<b class='bottom' style='display:block' />");
		this.tooltip = $("<span class='fv_tooltip' style='display:block'></span>").append(s).append(b).css({"filter":"alpha(opacity:95)","KHTMLOpacity":"0.95","MozOpacity":"0.95","opacity":"0.95"});
		//注册事件 
		jqobj.bind({
			mouseover : function(e){
				$("#fvtt").append(this.tooltip);
				$("#fv_content").html(this.Tooltip);
				$.formValidator.localTooltip(e);
			},
			mouseout : function(){
				$("#fvtt").empty();
			},
			mousemove: function(e){
				$("#fv_content").html(this.Tooltip);
				$.formValidator.localTooltip(e);
			}
		});
	});
}
})(jQuery);


// formValidatorRegex.js
var regexEnum = 
{
	intege:"^-?[1-9]\\d*$",					//整数
	intege1:"^[1-9]\\d*$",					//正整数
	intege2:"^-[1-9]\\d*$",					//负整数
	num:"^([+-]?)\\d*\\.?\\d+$",			//数字
	num1:"^[1-9]\\d*|0$",					//正数（正整数 + 0）
	num2:"^-[1-9]\\d*|0$",					//负数（负整数 + 0）
	decmal:"^([+-]?)\\d*\\.\\d+$",			//浮点数
	decmal1:"^[1-9]\\d*.\\d*|0.\\d*[1-9]\\d*$",　　	//正浮点数
	decmal2:"^-([1-9]\\d*.\\d*|0.\\d*[1-9]\\d*)$",　 //负浮点数
	decmal3:"^-?([1-9]\\d*.\\d*|0.\\d*[1-9]\\d*|0?.0+|0)$",　 //浮点数
	decmal4:"^[1-9]\\d*.\\d*|0.\\d*[1-9]\\d*|0?.0+|0$",　　 //非负浮点数（正浮点数 + 0）
	decmal5:"^(-([1-9]\\d*.\\d*|0.\\d*[1-9]\\d*))|0?.0+|0$",　　//非正浮点数（负浮点数 + 0）

	email:"^\\w+((-\\w+)|(\\.\\w+))*\\@[A-Za-z0-9]+((\\.|-)[A-Za-z0-9]+)*\\.[A-Za-z0-9]+$", //邮件
	color:"^[a-fA-F0-9]{6}$",				//颜色
	url:"^http[s]?:\\/\\/([\\w-]+\\.)+[\\w-]+([\\w-./?%&=]*)?$",	//url
	chinese:"^[\\u4E00-\\u9FA5\\uF900-\\uFA2D]+$",					//仅中文
	ascii:"^[\\x00-\\xFF]+$",				//仅ACSII字符
	zipcode:"^\\d{6}$",						//邮编
	mobile:"^(13|15|18|17|16|19)[0-9]{9}$",				//手机
	ip4:"^(25[0-5]|2[0-4]\\d|[0-1]\\d{2}|[1-9]?\\d)\\.(25[0-5]|2[0-4]\\d|[0-1]\\d{2}|[1-9]?\\d)\\.(25[0-5]|2[0-4]\\d|[0-1]\\d{2}|[1-9]?\\d)\\.(25[0-5]|2[0-4]\\d|[0-1]\\d{2}|[1-9]?\\d)$",	//ip地址
	notempty:"^\\S+$",						//非空
	picture:"(.*)\\.(jpg|bmp|gif|ico|pcx|jpeg|tif|png|raw|tga)$",	//图片
	rar:"(.*)\\.(rar|zip|7zip|tgz)$",								//压缩文件
	date:"^\\d{4}(\\-|\\/|\.)\\d{1,2}\\1\\d{1,2}$",					//日期
	qq:"^[1-9]*[1-9][0-9]*$",				//QQ号码
	tel:"^(([0\\+]\\d{2,3}-)?(0\\d{2,3})-)?(\\d{7,8})(-(\\d{3,}))?$",	//电话号码的函数(包括验证国内区号,国际区号,分机号)
	username:"^\\w+$",						//用来用户注册。匹配由数字、26个英文字母或者下划线组成的字符串
	letter:"^[A-Za-z]+$",					//字母
	letter_u:"^[A-Z]+$",					//大写字母
	letter_l:"^[a-z]+$",					//小写字母
	idcard:"^[1-9]([0-9]{14}|[0-9]{17})$",	//身份证
	myusername:"^[\\w\\u4E00-\\u9FA5\\uF900-\\uFA2D]+$"					//仅中文
}

var aCity={11:"北京",12:"天津",13:"河北",14:"山西",15:"内蒙古",21:"辽宁",22:"吉林",23:"黑龙江",31:"上海",32:"江苏",33:"浙江",34:"安徽",35:"福建",36:"江西",37:"山东",41:"河南",42:"湖北",43:"湖南",44:"广东",45:"广西",46:"海南",50:"重庆",51:"四川",52:"贵州",53:"云南",54:"西藏",61:"陕西",62:"甘肃",63:"青海",64:"宁夏",65:"新疆",71:"台湾",81:"香港",82:"澳门",91:"国外"} 

function isCardID(sId){ 
	var iSum=0 ;
	var info="" ;
	if(!/^\d{17}(\d|x)$/i.test(sId)) return "你输入的身份证长度或格式错误"; 
	sId=sId.replace(/x$/i,"a"); 
	if(aCity[parseInt(sId.substr(0,2))]==null) return "你的身份证地区非法"; 
	sBirthday=sId.substr(6,4)+"-"+Number(sId.substr(10,2))+"-"+Number(sId.substr(12,2)); 
	var d=new Date(sBirthday.replace(/-/g,"/")) ;
	if(sBirthday!=(d.getFullYear()+"-"+ (d.getMonth()+1) + "-" + d.getDate()))return "身份证上的出生日期非法"; 
	for(var i = 17;i>=0;i --) iSum += (Math.pow(2,i) % 11) * parseInt(sId.charAt(17 - i),11) ;
	if(iSum%11!=1) return "你输入的身份证号非法"; 
	return true;//aCity[parseInt(sId.substr(0,2))]+","+sBirthday+","+(sId.substr(16,1)%2?"男":"女") 
} 




//短时间，形如 (13:04:06)
function isTime(str)
{
	var a = str.match(/^(\d{1,2})(:)?(\d{1,2})\2(\d{1,2})$/);
	if (a == null) {return false}
	if (a[1]>24 || a[3]>60 || a[4]>60)
	{
		return false;
	}
	return true;
}

//短日期，形如 (2003-12-05)
function isDate(str)
{
	var r = str.match(/^(\d{1,4})(-|\/)(\d{1,2})\2(\d{1,2})$/); 
	if(r==null)return false; 
	var d= new Date(r[1], r[3]-1, r[4]); 
	return (d.getFullYear()==r[1]&&(d.getMonth()+1)==r[3]&&d.getDate()==r[4]);
}

//长时间，形如 (2003-12-05 13:04:06)
function isDateTime(str)
{
	var reg = /^(\d{1,4})(-|\/)(\d{1,2})\2(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/; 
	var r = str.match(reg); 
	if(r==null) return false; 
	var d= new Date(r[1], r[3]-1,r[4],r[5],r[6],r[7]); 
	return (d.getFullYear()==r[1]&&(d.getMonth()+1)==r[3]&&d.getDate()==r[4]&&d.getHours()==r[5]&&d.getMinutes()==r[6]&&d.getSeconds()==r[7]);
}