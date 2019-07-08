pp = null;
function CheckAll(objname) {

    objEvent = getEvent();
    if (objEvent.srcElement) id = objEvent.srcElement;
    else id = objEvent.target;

    if (objname != '') {
        var code_Values = document.getElementsByName(objname);
        for (i = 0; i < code_Values.length; i++) {
            if (code_Values[i].type == "checkbox") {
                code_Values[i].checked = id.checked;
            }
        }
    } else {
        var code_Values = document.getElementsByTagName("input");
        for (i = 0; i < code_Values.length; i++) {
            if (code_Values[i].type == "checkbox") {
                code_Values[i].checked = id.checked;
            }
        }
    }
}
$.extend({
    refresh: function(url) {
        window.location.href = url;
    }
});
function getEvent() {
    if (document.all) return window.event;
    func = getEvent.caller;
    while (func != null) {
        var arg0 = func.arguments[0];
        if (arg0) {
            if ((arg0.constructor == Event || arg0.constructor == MouseEvent) || (typeof(arg0) == "object" && arg0.preventDefault && arg0.stopPropagation)) {
                return arg0;
            }
        }
        func = func.caller;
    }
    return null;
}
jQuery.showfloatdiv = function(ox) {
    var oxdefaults = {
        txt: '数据加载中,请稍后...',
        classname: 'progressBar',
        left: 410,
        top: 210,
        wantclose: 1,
        suredo: function(e) {
            return false;
        },
        succdo: function(r) {},
        completetxt: '操作成功!',
        autoclose: 1,
        ispost: 0,
        cssname: 'alert',
        isajax: 0,
        intvaltime: 1000,
        redirurl: '/'
    };
    ox = ox || {};
    $.extend(oxdefaults, ox);
    $("#qirebox_overlay").remove();
    $("#qirebox").remove();
    if (oxdefaults.wantclose == 1) {
        var floatdiv = $('<div class="qirebox-overlayBG" id="qirebox_overlay"></div><div id="qirebox" class="qirebox png-img"><iframe frameborder="0" class="ui-iframe"></iframe><table class="ui-dialog-box"><tr><td><div class="ui-dialog"><div class="ui-dialog-cnt" id="ui-dialog-cnt"><div class="ui-dialog-tip alert" id="ui-cnt"><span id="xtip">' + oxdefaults.txt + '</span></div></div><div class="ui-dialog-close"><span class="close">关闭</span></div></div></td></tr></table></div>');
        $("body").append(floatdiv);
        $("#qirebox_overlay").fadeIn(500);
        $("#qirebox").fadeIn(500);
        $("#ui-cnt").removeClass('succ error alert loading').addClass(oxdefaults.cssname);
        $(".ui-dialog-close").click(function() {
            $.closefloatdiv();
        });
        if (oxdefaults.isajax == 1) {
            objEvent = getEvent();
            if (objEvent.srcElement) id = objEvent.srcElement;
            else id = objEvent.target;
            var idval = (id.attributes["data"].nodeValue != null && id.attributes["data"].nodeValue != undefined) ? id.attributes["data"].nodeValue: id.data;
            $.ajax({
                url: idval,
                async: true,
                type: 'get',
                cache: true,
                dataType: 'json',
                success: function(data, textStatus) {
                    if (data.msg != null && data.msg != undefined) {
                        $("#xtip").html(data.msg);
                    } else {
                        $("#xtip").html(oxdefaults.completetxt);
                    }
                    oxdefaults.succdo(data);
                    if (data.wantclose != null && data.wantclose != undefined) {
                        $.hidediv(data);
                    } else if (oxdefaults.autoclose == 1) {
                        $.hidediv(data);
                    }
                    if (data.wantredir != undefined || data.wantredir != null) {
                        if (data.redir != undefined || data.redir != null) {
                            setTimeout("$.refresh('" + data.redir + "')", oxdefaults.intvaltime);
                        } else {
                            setTimeout("$.refresh('" + oxdefaults.redirurl + "')", oxdefaults.intvaltime);
                        }
                    }
                },
                error: function(e) {
                    $("#xtip").html('系统繁忙,请稍后再试...');
                }
            });
        }
    } else if (oxdefaults.wantclose == 2) {
        objEvent = getEvent();
        if (objEvent.srcElement) id = objEvent.srcElement;
        else id = objEvent.target;
        var idval = (id.attributes["data"].nodeValue != null && id.attributes["data"].nodeValue != undefined) ? id.attributes["data"].nodeValue: id.data;
        var floatdiv = $('<div class="qirebox-overlayBG" id="qirebox_overlay"></div><div id="qirebox" class="qirebox png-img"><iframe frameborder="0" class="ui-iframe"></iframe><table class="ui-dialog-box"><tr><td><div class="ui-dialog"><div class="ui-dialog-cnt" id="ui-dialog-cnt"><div class="ui-dialog-tip alert" id="ui-cnt"><span id="xtip">' + oxdefaults.txt + '</span></div></div><div class="ui-dialog-todo"><a class="ui-link ui-link-small" href="javascript:void(0);" id="surebt">确定</a><a class="ui-link ui-link-small cancelbt"  id="cancelbt">取消</a><input type="hidden" id="hideval" value=""/></div><div class="ui-dialog-close"><span class="close">关闭</span></div></div></td></tr></table></div>');
        $("body").append(floatdiv);
        $("#qirebox_overlay").fadeIn(500);
        $("#qirebox").fadeIn(500);
        $(".ui-dialog-close").click(function() {
            $.closefloatdiv();
        });
        $(".cancelbt").click(function() {
            $.closefloatdiv();
        });
        $("#surebt").click(function(e) {
            if (!oxdefaults.suredo(e)) {
                $(".ui-dialog-todo").remove();
                $("#ui-cnt").removeClass('succ error alert').addClass('loading');
                if (oxdefaults.ispost == 0) {
                    $.ajax({
                        url: idval,
                        async: true,
                        type: 'get',
                        cache: true,
                        dataType: 'json',
                        success: function(data, textStatus) {
                            if (data.msg != null && data.msg != undefined) {
                                $("#xtip").html(data.msg);
                            } else {
                                $("#xtip").html(oxdefaults.completetxt);
                            }
                            oxdefaults.succdo(data);
                            if (data.wantclose != null && data.wantclose != undefined) {
                                $.hidediv(data);
                            } else if (oxdefaults.autoclose == 1) {
                                $.hidediv(data);
                            }
                        },
                        error: function(e) {
                            $("#xtip").html('系统繁忙,请稍后再试...');
                        }
                    });
                } else {
                    $("#" + oxdefaults.formid).qiresub({
                        curobj: $("#surebt"),
                        txt: '数据提交中,请稍后...',
                        onsucc: function(result) {
                            oxdefaults.succdo(result);
                            $.hidediv(result);
                        }
                    }).post({
                        url: oxdefaults.url
                    });
                }
            } else {
                oxdefaults.succdo(e);
            }
        });
    } else {
        var floatdiv = $('<div class="qirebox_overlayBG" id="qirebox_overlay"></div><div id="qirebox" class="qirebox"><iframe frameborder="0" class="ui-iframe"></iframe><div class="ui-dialog"><div class="ui-dialog-cnt" id="ui-dialog-cnt"><div class="ui-dialog-box"<div class="ui-cnt" id="ui-cnt">' + oxdefaults.txt + '</div></div></div></div></div>');
        $("body").append(floatdiv);
        $("#qirebox_overlay").fadeIn(500);
        $("#qirebox").fadeIn(500);
    }
    $('#qirebox_overlay').bind('click', 
    function(e) {
        $.closefloatdiv(e);
        if (pp != null) {
            clearTimeout(pp);
        }
    });
};
jQuery.closefloatdiv = function(e) {
    $("#qirebox_overlay").remove();
    $("#qirebox").remove();
};
jQuery.hidediv = function(e) {
    var oxdefaults = {
        intvaltime: 1000
    };
    e = e || {};
    $.extend(oxdefaults, e);
    if (e.msg != null && e.msg != undefined) {
        $("#ui-cnt").html(e.msg);
    }
    if (parseInt(e.rcode) == 1) {
        $("#ui-cnt").removeClass('loading error alert').addClass('succ');
    } else if (parseInt(e.rcode) < 1) {
        $("#ui-cnt").removeClass('loading alert succ').addClass('error');
    }
    pp = setTimeout("$.closefloatdiv()", oxdefaults.intvaltime);
}; (function($) {
    $.fn.qiresub = function(options) {
        var defaults = {
            txt: '数据提交中,请稍后...',
            redirurl: window.location.href,
            dataType: 'json',
            onsucc: function(e) {},
            onerr: function() {
                $.hidediv({
                    msg: '系统繁忙'
                });
            },
            oncomplete: function() {},
            intvaltime: 1000
        };
        options.curobj.attr('disabled', true);
        var ox = options.curobj.offset();
        var options = $.extend(defaults, options);
        $.showfloatdiv({
            offset: ox,
            txt: defaults.txt
        });
        var obj = $(this);
        var id = obj.attr('id');
        return {
            post: function(e) {
                $("#ui-cnt").removeClass('succ error alert').addClass('loading');
                $.post(e.url, obj.serializeArray(), 
                function(result) {
                    options.curobj.attr('disabled', false);
                    defaults.onsucc(result);
                    if (result.closediv != undefined || result.closediv != null) {
                        $.closefloatdiv();
                    }
                    if (result.wantredir != undefined || result.wantredir != null) {
                        if (result.redir != undefined || result.redir != null) {
                            setTimeout("$.refresh('" + result.redir + "')", options.intvaltime);
                        } else {
                            setTimeout("$.refresh('" + options.redirurl + "')", options.intvaltime);
                        }
                    }
                },
                options.dataType).error(function() {
                    options.curobj.attr('disabled', false);
                    defaults.onerr();
                }).complete(function() {
                    defaults.oncomplete();
                    options.curobj.attr('disabled', false);
                });
            },
            implodeval: function(e) {
                val = $("#" + id + " :input").map(function() {
                    if ($(this).attr('name') != '' && $(this).attr('name') != undefined) {
                        return $(this).attr('name') + "-" + $(this).val();
                    }
                }).get().join("-");
                return val;
            },
            get: function(e) {
                $(".ui-dialog-todo").remove();
                $("#ui-cnt").removeClass('succ error alert').addClass('loading');
                var val = this.implodeval();
                $.get(e.url + "-" + val, '', 
                function(result) {
                    options.curobj.attr('disabled', false);
                    defaults.onsucc(result);
                    if (result.wantredir != undefined || result.wantredir != null) {
                        if (result.redir != undefined || result.redir != null) {
                            setTimeout("$.refresh(" + result.redir + ")", options.intvaltime);
                        } else {
                            setTimeout("$.refresh(" + options.redirurl + ")", options.intvaltime);
                        }
                    }
                },
                options.dataType).error(function() {
                    options.curobj.attr('disabled', false);
                    defaults.onerr();
                }).complete(function() {
                    defaults.oncomplete();
                    options.curobj.attr('disabled', false);
                });
            }
        };
    };
    $.fn.ajaxdel = function(options) {
        var defaults = {
            txt: '数据提交中,请稍后...',
            redirurl: window.location.href,
            dataType: 'json',
            onsucc: function(e) {},
            onerr: function() {},
            oncomplete: function() {},
            intvaltime: 3000
        };
        $(".ui-dialog-todo").remove();
        $("#ui-cnt").removeClass('succ error alert').addClass('loading');
        var options = $.extend(defaults, options);
        var ajurl = $(this).attr('url');
        $.ajax({
            url: ajurl,
            success: function(data) {
                options.onsucc(data);
            },
            error: function() {
                options.onerr();
            },
            complete: function() {
                options.oncomplete();
            },
            dataType: 'json'
        });
    };
})(jQuery);