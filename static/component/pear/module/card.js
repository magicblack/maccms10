layui.define(['table', 'laypage','jquery', 'element'], function(exports) {
	"use strict";

	var MOD_NAME = 'card',
		$ = layui.jquery,
		element = layui.element,
		laypage = layui.laypage;
		
	var _instances = {};  // 记录所有实例
	
	var defaultOption = {
		elem: "#currentTableId",// 构建的模型
		url: "",// 数据 url 连接
		loading: true,//是否加载
		limit: 0, //每页数量默认是每行数量的双倍
		linenum: 4, //每行数量 2,3,4,6
		currentPage: 1,//当前页
		data:[],       //静态数据
		limits:[],     //页码
		page: true, //是否分页
		layout: ['count', 'prev', 'page', 'next','limit', 'skip'],//分页控件
		request: {
			pageName: 'page' //页码的参数名称，默认：page
			, limitName: 'limit' //每页数据量的参数名，默认：limit
			, idName: 'id'       //主键名称，默认：id
			, titleName: 'title' //标题名称，默认：title
			, imageName: 'image' //图片地址，默认：image
			, remarkName: 'remark' //备注名称，默认：remark
			, timeName: 'time' //时间名称，默认：time
		},
		response: {
			statusName: 'code' //规定数据状态的字段名称，默认：code
			, statusCode: 0 //规定成功的状态码，默认：0
			, msgName: 'msg' //规定状态信息的字段名称，默认：msg
			, countName: 'count' //规定数据总数的字段名称，默认：count
			, dataName: 'data' //规定数据列表的字段名称，默认：data
		},
		clickItem: function(data){},
		done: function () {

		}
	};
	
	var card = function(opt) {
		_instances[opt.elem.substring(1)] = this;
		this.reload(opt);
	};
	card.prototype.initOptions = function (opt) {
		this.option = $.extend(true, {}, defaultOption, opt);
		if (!this.option.limit || this.option.limit == 0) {
			this.option.limit = this.option.linenum * 2;
		}
		if (!this.option.limits || this.option.limits.length == 0) {
			this.option.limits = [this.option.limit];
        }
	};
	
	card.prototype.init = function () {
		var option = this.option;
		var url = option.url;
		var html = "";
		html += option.loading == true ? '<div class="ew-table-loading">' : '      <div class="ew-table-loading layui-hide">';
		html += '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>';
		html += '</div>';
		$(option.elem).html(html);
		html = "";
		if (!!url) {
			if (url.indexOf("?") >= 0) {
				url = url + '&v=1.0.0';
			}
			else {
				url = url + '?v=1.0.0';
			}
			if (!!option.page) {
				url = url + '&' + option.request.limitName + '=' + option.limit;
				url = url + '&' + option.request.pageName + '=' + option.currentPage;
			}
			if (!!option.where) {
				for (let key in option.where) {
					url = url + '&' + key + '=' + option.where[key];
				}
            }
			getData(url).then(function(data){
				data = initData(data, option);
				if (data.code != option.response.statusCode) {
					option.data = [];
					option.count = 0;
				} else {
					option.data = data.data;
					option.count = option.data.length;
				}
				
				if (!!option.data && option.data.length > 0) {
					html = createComponent(option.elem.substring(1), option.linenum, option.data);
					html += "<div id='cardpage'></div>";
				}
				else {
					html = "<p>没有数据</p>";
				}
				$(option.elem).html(html);
				if (option.page) {
					laypage.render({
						elem: 'cardpage'
						, count: option.count, limit: option.limit, limits: option.limits, curr: option.currentPage
						, layout: option.layout
						, jump: function (obj, first) {
							option.limit = obj.limit;
							option.currentPage = obj.curr;
							if (!first) {
								_instances[option.elem.substring(1)].reload(option);
							}
						}
					});
				}
			});
		}
		else {
			if (!option.alldata) {
				option.alldata = option.data;
            }
			if (option.page) {
				var data = [];
				option.count = option.alldata.length;
				for (var i = (option.currentPage - 1) * option.limit; i < option.currentPage * option.limit && i<option.alldata.length; i++) {
					data.push(option.alldata[i]);
				}
				option.data = data;
			}
			if (!!option.data && option.data.length > 0) {
				html = createComponent(option.elem.substring(1), option.linenum, option.data);
				html += "<div id='cardpage'></div>";
			}
			else {
				html = "<p>没有数据</p>";
			}
			$(option.elem).html(html);
			if (option.page) {
				laypage.render({
					elem: 'cardpage'
					, count: option.count, limit: option.limit, limits: option.limits, curr: option.currentPage
					, layout: option.layout
					, jump: function (obj, first) {
						option.limit = obj.limit;
						option.currentPage = obj.curr;
						if (!first) {
							_instances[option.elem.substring(1)].reload(option);
						}
					}
				});
			}
		}
	}
	
	card.prototype.reload = function (opt) {
		this.initOptions(this.option ? $.extend(true, this.option, opt) : opt);
		this.init();  // 初始化表格
    }

	function createComponent(elem,linenum,data) {
		var html = "<div class='cloud-card-component'>"
		var content = createCards(elem, linenum,data);
        var page = "";
        content = content + page;
        html += content + "</div>"
        return html;
	}
	
	function createCards(elem, linenum,data) {	
		var content = "<div class='layui-row layui-col-space30'>";
		for (var i = 0; i < data.length; i++) {
			content += createCard(elem, linenum,data[i],i);
        }
		content += "</div>";
		return content;
	}
	
	function createCard(elem, linenum, item, no) {
		var line = 12 / linenum;
		var card =
			'<div id=' + item.id + ' onclick="cardTableCheckedCard(' + elem + ',this)" class="layui-col-md' + line + ' ew-datagrid-item" data-index="' + no+'" data-number="1"> <div class="project-list-item"> <img class="project-list-item-cover" src="' +item.image + '"> <div class="project-list-item-body"> <h2>' + item.title + '</h2> <div class="project-list-item-text layui-text">' + item.remark + '</div> <div class="project-list-item-desc"> <span class="time">' +item.time + '</span> <div class="ew-head-list"></div> </div> </div > </div > </div > '
		return card;
	}
	
	function initData(tempData, option) {
		var data = {};
		data.code = tempData[option.response.statusName];
		data.msg = tempData[option.response.msgName];
		data.count = tempData[option.response.countName];
		var dataList = tempData[option.response.dataName];
		data.data = [];
		for (var i = 0; i < dataList.length; i++) {
			var item = {};
			item.id = dataList[i][option.request.idName];
			item.image = dataList[i][option.request.imageName];
			item.title = dataList[i][option.request.titleName];
			item.remark = dataList[i][option.request.remarkName];
			item.time = dataList[i][option.request.timeName];
			data.data.push(item);
		}
		return data;
    }

	function getData(url) {
		var defer = $.Deferred();
		$.get(url + "?fresh=" + Math.random(), function(result) {
			defer.resolve(result)
		});
		return defer.promise();
	}
	
	window.cardTableCheckedCard = function (elem,obj) {
		$(obj).addClass('layui-table-click').siblings().removeClass('layui-table-click');
		var item = {};
		item.id = obj.id;
		item.image = $(obj).find('.project-list-item-cover')[0].src;
		item.title = $(obj).find('h2')[0].innerHTML;
		item.remark = $(obj).find('.project-list-item-text')[0].innerHTML;
		item.time = $(obj).find('.time')[0].innerHTML;
		_instances[elem.id].option.checkedItem = item;
		_instances[elem.id].option.clickItem(item);
	}
	
	/** 对外提供的方法 */
	var tt = {
		
		render: function (options) {
			return new card(options);
		},
		
		reload: function (id, opt) {
			_instances[id].option.checkedItem = null;
			_instances[id].reload(opt);
		},
		
		getChecked: function (id) {
			var option = _instances[id].option;
			var data = option.checkedItem;
			var item = {};
            if (!data) {
				return null;
            }
			item[option.request.idName] = data.id;
			item[option.request.imageName] = data.image;
			item[option.request.titleName] = data.title;
			item[option.request.remarkName] = data.remark;
			item[option.request.timeName] = data.time;
			return item;
		},
		
		getAllData: function (id) {
			var option = _instances[id].option;
			var data = [];
			for (var i = 0; i < option.data.length; i++) {
				var item = {};
				item[option.request.idName] = option.data[i].id;
				item[option.request.imageName] = option.data[i].image;
				item[option.request.titleName] = option.data[i].title;
				item[option.request.remarkName] = option.data[i].remark;
				item[option.request.timeName] = option.data[i].time;
				data.push(item);
            }
			return data;
		},
	}
	
	exports(MOD_NAME, tt);
})