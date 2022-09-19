layui.define(['jquery', 'element'], function (exports) {
	"use strict";

	var $ = layui.jquery;

	var pearFrame = function (opt) {
		this.option = opt;
	};

	pearFrame.prototype.render = function (opt) {
		var option = {
			elem: opt.elem,
			url: opt.url,
			title: opt.title,
			width: opt.width,
			height: opt.height,
			done: opt.done ? opt.done : function () { console.log("菜单渲染成功"); }
		}
		createFrameHTML(option);
		$("#" + option.elem).width(option.width);
		$("#" + option.elem).height(option.height);
		return new pearFrame(option);
	}

	pearFrame.prototype.changePage = function (url, loading) {
		var $frameLoad = $("#" + this.option.elem).find(".pear-frame-loading");
        
        /**
         * 非视图模式下，切换侧栏导航上条目时，会产生 loading.css 非 function错误
         * frame.js?v=3.9.4:28 Uncaught TypeError: loading.css is not a function
            at pearFrame.changePage (frame.js?v=3.9.4:28:12)
            at admin.js?v=3.9.4:165:17
            at HTMLAnchorElement.<anonymous> (menu.js?v=3.9.4:122:4)
            at HTMLBodyElement.dispatch (layui.js:2:22295)
            at HTMLBodyElement.m.handle (layui.js:2:18997)
         * 
        */
		if (loading && typeof loading.css ==='function') {
			loading.css({ display: 'block' });
		}
		$("#" + this.option.elem + " iframe").attr("src", url);
		if (loading) {
			setTimeout(function () {
				$frameLoad.fadeOut(500);
			}, 800)
		}
	}

	pearFrame.prototype.changePageByElement = function (elem, url, title, loading) {
		var $frameLoad = $("#" + elem).find(".pear-frame-loading");
		if (loading) {
			$frameLoad.css({ display: 'block' });
		}
		$("#" + elem + " iframe").attr("src", url);
		$("#" + elem + " .title").html(title);
		if (loading) {
			setTimeout(function () {
				$frameLoad.css({ display: 'none' });
			}, 400)
		}
	}

	pearFrame.prototype.refresh = function (time) {
		if (time != false) {
			var loading = $("#" + this.option.elem).find(".pear-frame-loading");
			loading.css({ display: 'block' });
			if (time != 0) {
				setTimeout(function () {
					loading.fadeOut(500);
				}, time)
			}
		}
		$("#" + this.option.elem).find("iframe")[0].contentWindow.location.reload(true);
	}

	function createFrameHTML(option) {
		var iframe = "<iframe class='pear-frame-content' style='width:100%;height:100%;'  scrolling='auto' frameborder='0' src='" + option.url + "' ></iframe>";
		var loading = '<div class="pear-frame-loading">' +
			'<div class="ball-loader">' +
			'<span></span><span></span><span></span><span></span>' +
			'</div>' +
			'</div></div>';
		$("#" + option.elem).html("<div class='pear-frame'>" + iframe + loading + "</div>");
	}

	exports('frame', new pearFrame());
});
