{include file="../../../application/admin/view/public/head" /}

<div class="page-container">
    <form class="layui-form layui-form-pane" action="">
        <div class="layui-tab">
            <ul class="layui-tab-title">
                <li class="layui-this">邮件发送设置</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">

                    <blockquote class="layui-elem-quote layui-quote-nm">
                        提示信息：<br>
                        发送邮件请开启openssl扩展库，否则可能发送失败
                    </blockquote>

                    <div class="layui-form-item">
                        <label class="layui-form-label">服务器：</label>
                        <div class="layui-input-inline">
                            <input type="text" id="host" name="email[host]" placeholder="" value="{$config['email']['host']}" class="layui-input w200"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">smtp服务器</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">端口：</label>
                        <div class="layui-input-inline">
                            <input type="text" id="port" name="email[port]" placeholder="" value="{$config['email']['port']}" class="layui-input w200"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">smtp端口</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">帐号：</label>
                        <div class="layui-input-inline">
                            <input type="text" id="username" name="email[username]" placeholder="" value="{$config['email']['username']}" class="layui-input w200"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">smtp服务帐号</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">密码：</label>
                        <div class="layui-input-inline">
                            <input type="password" id="password" name="email[password]" placeholder="" value="{$config['email']['password']}" class="layui-input w200"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">smtp服务密码</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">昵称：</label>
                        <div class="layui-input-inline">
                            <input type="text" id="nick" name="email[nick]" placeholder="" value="{$config['email']['nick']}" class="layui-input w200"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">发件人昵称</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">测试地址：</label>
                        <div class="layui-input-inline">
                            <input type="text" id="test" name="email[test]" placeholder="" value="{$config['email']['test']}" class="layui-input w200"  >
                        </div>
                        <button type="button" class="layui-btn layui-btn-normal" onclick="test_email()">发送测试邮件</button>
                    </div>

            </div>
            </div>

        </div>
        <div class="layui-form-item center">
            <div class="layui-input-block">
                <button type="submit" class="layui-btn" lay-submit="" lay-filter="formSubmit">保 存</button>
                <button class="layui-btn layui-btn-warm" type="reset">还 原</button>
            </div>
        </div>
    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript">
    function test_email() {
        var host = $("#host").val();
        var username = $("#username").val();
        var password = $("#password").val();
        var test = $("#test").val();
        var nick = $("#nick").val();
        var port = $('#port').val();

        layer.msg('数据提交中...',{time:500000});
        $.ajax({
            url: "{:url('system/test_email')}",
            type: "post",
            dataType: "json",
            data: {host:host,username:username,password:password,port:port,nick:nick,test:test},
            beforeSend: function () {
            },
            error:function(r){
                layer.msg('发生错误，请检查是否开启相应扩展库',{time:1800});
            },
            success: function (r) {
                layer.msg(r.msg,{time:1800});
            },
            complete: function () {
            }
        });
    }
</script>

</body>
</html>