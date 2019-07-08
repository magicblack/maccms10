islogin=0;
function checkcookie(){
	if(document.cookie.indexOf('qr_u=')>=0){
	islogin=1;
	return true;
	}
	return false;
}
checkcookie();

$(function(){
	// drop-down
	$(".drop-down").hover(function(){		
		$(this).find(".drop-title").addClass("drop-title-hover");
		$(this).find(".drop-box").show();
	},function(){
		$(this).find(".drop-title").removeClass("drop-title-hover");
		$(this).find(".drop-box").hide();
	});		
});

$(document).ready(function(){
			
	// ui-input
	$(".ui-input").focus(function(){
		$(this).addClass("ui-input-focus");
	}).hover(function(){
		$(this).addClass("ui-input-hover");
	},function(){
		$(this).removeClass("ui-input-hover");
	});
	$(".ui-input").blur(function(){
		$(this).removeClass("ui-input-focus");
	});			
	
	// ui-form-placeholder
	$(".ui-form-placeholder").each(function(){
		var _label = $(this).find(".ui-label");
		var _input = $(this).find(".ui-input");
		var _text =  $(this).find(".ui-input").val();
		
		if (_text != ""){
			_label.hide();
		}
		
		_label.css("z-index","3");
		_label.click(function(){
			$(this).hide();
			_input.focus();
		});	
		_input.focus(function(){
			_label.hide();
		});	
	});
		
	// ui-button
	$(".ui-button").hover(function(){
		$(this).addClass("ui-button-hover");
	},function(){
		$(this).removeClass("ui-button-hover");
	});
		
	// close-his	
	$(".close-his").click(function(){
		$(this).parents(".drop-box").hide();
	});
	
	// show-tipinfo
	$(".show-tipinfo a").hover(function(){
		$(this).parent().parent().find(".tipInfo").show();
	},function(){		
		$(this).parent().parent().find(".tipInfo").hide();
	});	
	
	$("#wish").trigger('click');
	
	
	
	// timeinfo
	$(".timeinfo").hover(function(){
		$(this).addClass("timeinfo-active");
	},function(){
		$(this).removeClass("timeinfo-active");
	});	
	
	// Date List Jquery
	$(".date-list").each(function(){
		$lis = $(this).find("li:last").index();		
		if($lis > 5){
			$(this).addClass("date-long");
		}	
	});
	
	
});

// Tab Menu JS Common
function setTab(name,cursel,n){
	for(i=1;i<=n;i++){
		var menu=document.getElementById(name+i);
		var con=document.getElementById("con_"+name+"_"+i);
		menu.className=i==cursel?"current":"";
		con.style.display=i==cursel?"block":"none";
	}
}
function qrsearch(){
	if($("#wd").val()=='请在此处输入影片片名或演员名称。'||$("#wd").val()==''){
		$("#wd").val('');
		$("#wd").focus();
	}else{
		document.location = MAC_PATH + 'index.php/vod/search.html?wd='+ encodeURIComponent($("#wd").val())+"";
	}
	return false;
}

function checkcookie(){
	if(document.cookie.indexOf('baient_pro=')>=0){
	islogin=1;
	return true;
	}
	return false;
}
checkcookie();
$(document).ready(function(){
	// Baby Time Step A Tips
	$(".play-mode-list a").each(function(j,div){
			$(this).click(function(){
		//$("html,body").animate({scrollTop:$("#"+listid).offset().top}, 500); //我要平滑
		        if ($(this).parent().hasClass("current") ){
				return;
                }
				var txt=$(this).attr("title").split('-');
				$(".detail-pic .text").text(txt[1]);
				var listid=$(this).attr("id")+'-list';
				if(listid !='bdhd-pl-list' && listid!='qvod-pl-list'){
					$('#'+listid+' .txt').text('( 无需安装任何插件，即可快速播放 )');
				}
				$(this).parent().nextAll().removeClass("current");
				$(this).parent().prevAll().removeClass("current");
				$(this).parent().addClass("current")
				$('.play-list-box').hide().css("opacity",0);
				
				$('.play-list-box:eq('+j+')').show().animate({"opacity":"1"},1200);
	});
	});
	//order
	$('#detail-list .order a').click(function(){
		if($(this).hasClass('asc')){
			$(this).removeClass('asc').addClass('desc').text('降序');
		}else{
			$(this).removeClass('desc').addClass('asc').text('升序');
		}
		var a=$('.play-list-box:eq('+$(this).attr('data')+') .play-list');
		var b=$('.play-list-box:eq('+$(this).attr('data')+') .play-list a');
		a.html(b.get().reverse());
	});

	
});
 function intval(v)
    {    
    v = parseInt(v);    
    return isNaN(v) ? 0 : v;
    } 
    // 获取元素信息
    function getPos(e)
    {    
    var l = 0;    
    var t  = 0;    
    var w = intval(e.style.width);    
    var h = intval(e.style.height);    
    var wb = e.offsetWidth;    
    var hb = e.offsetHeight;    
    while (e.offsetParent)
    {       
     l += e.offsetLeft + (e.currentStyle?intval(e.currentStyle.borderLeftWidth):0);        
     t += e.offsetTop  + (e.currentStyle?intval(e.currentStyle.borderTopWidth):0);        
     e = e.offsetParent;    
     }    
     l += e.offsetLeft + (e.currentStyle?intval(e.currentStyle.borderLeftWidth):0);    
     t  += e.offsetTop  + (e.currentStyle?intval(e.currentStyle.borderTopWidth):0);    
     return {x:l, y:t, w:w, h:h, wb:wb, hb:hb}; } 
     // 获取滚动条信息
     function getScroll() 
     {    
     var t, l, w, h;         
     if (document.documentElement && document.documentElement.scrollTop)
     {        
     t = document.documentElement.scrollTop;        
     l = document.documentElement.scrollLeft;        
     w = document.documentElement.scrollWidth;       
     h = document.documentElement.scrollHeight;    
     }
     else if (document.body)
     {        
     t = document.body.scrollTop;        
     l = document.body.scrollLeft;        
     w = document.body.scrollWidth;        
     h = document.body.scrollHeight;    
     }    
     return { t: t, l: l, w: w, h: h };
     } 
     // 锚点(Anchor)间平滑跳转
     function scroller(el, duration)
     {    
     if(typeof el != 'object')
     {
     el = document.getElementById(el);
     }     
     if(!el) return;     
     var z = this;    
     z.el = el;    
     z.p = getPos(el);    
     z.s = getScroll();    
     z.clear = function()
     {
     window.clearInterval(z.timer);z.timer=null
     };    
     z.t=(new Date).getTime();     
     z.step = function()
     {        
     var t = (new Date).getTime();        
     var p = (t - z.t) / duration;        
     if (t >= duration + z.t)
     {            
     z.clear();            
     window.setTimeout(function(){z.scroll(z.p.y, z.p.x)},13);         }
     else {            
     st = ((-Math.cos(p*Math.PI)/2) + 0.5) * (z.p.y-z.s.t) + z.s.t;            
     sl = ((-Math.cos(p*Math.PI)/2) + 0.5) * (z.p.x-z.s.l) + z.s.l;            
     z.scroll(st, sl);        
     }    
     };    
     z.scroll = function (t, l){window.scrollTo(l, t)};    
     z.timer = window.setInterval(function(){z.step();},13);
     }