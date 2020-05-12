{include file="../../../application/admin/view/public/head" /}

<div class="page-container">
    <form class="layui-form layui-form-pane" action="">
        <div class="layui-tab">
            <ul class="layui-tab-title">
                <li class="layui-this">短信发送设置</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">

                    <blockquote class="layui-elem-quote layui-quote-nm">
                        提示信息：<br>
                        请务必按照短信接口服务商的要求做好短信签名和短信内容的设置。<br>
                        腾讯云短信：https://cloud.tencent.com/product/sms<br>
                        腾讯云短信模板例子：<br>
                        尊敬的用户，您的注册会员验证码为：{1}，请勿泄漏于他人！<br>
                        验证码为：{1}，您正在绑定手机，若非本人操作，请勿泄露。<br>
                        验证码为：{1}，您正在进行密码重置操作，如非本人操作，请忽略本短信！<br>
                        阿里云短信：https://www.aliyun.com/product/sms<br>
                        阿里云短信模板例子：<br>
                        尊敬的用户，您的注册会员验证码为：${code}，请勿泄漏于他人！<br>
                        验证码为：${code}，您正在绑定手机，若非本人操作，请勿泄露。<br>
                        验证码为：${code}，您正在进行密码重置操作，如非本人操作，请忽略本短信！<br>

                    </blockquote>

                    <div class="layui-form-item">
                        <label class="layui-form-label">服务商：</label>
                        <div class="layui-input-inline">
                            <select  name="sms[type]">
                                <option value="" >请选择...</option>
                                {volist name="ext_list" id="vo"}
                                <option value="{$key}" {if condition="$config['sms']['type'] eq $key"}selected {/if}>{$vo}</option>
                                {/volist}
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux"></div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">appid：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="appid" name="sms[appid]" placeholder="" value="{$config['sms']['appid']}" class="layui-input"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">腾讯云对应AppId，阿里云对应KeyId</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">appkey：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="appkey" name="sms[appkey]" placeholder="" value="{$config['sms']['appkey']}" class="layui-input"  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">腾讯云对应AppKey，阿里云对应KeySecret</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">短信签名：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="sign" name="sms[sign]" placeholder="" value="{$config['sms']['sign']}" class="layui-input "  >
                        </div>
                        <div class="layui-form-mid layui-word-aux"></div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">注册模板编号：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="tpl_code_reg" name="sms[tpl_code_reg]" placeholder="" value="{$config['sms']['tpl_code_reg']}" class="layui-input "  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">模板编号需要在服务商短信控制台中申请</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">绑定模板编号：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="tpl_code_bind" name="sms[tpl_code_bind]" placeholder="" value="{$config['sms']['tpl_code_bind']}" class="layui-input "  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">模板编号需要在服务商短信控制台中申请</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">找回模板编号：</label>
                        <div class="layui-input-inline w400">
                            <input type="text" id="tpl_code_findpass" name="sms[tpl_code_findpass]" placeholder="" value="{$config['sms']['tpl_code_findpass']}" class="layui-input "  >
                        </div>
                        <div class="layui-form-mid layui-word-aux">模板编号需要在服务商短信控制台中申请</div>
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
        var port = $('#port').val();

        layer.msg('数据提交中...',{time:500000});
        $.ajax({
            url: "{:url('system/test_email')}",
            type: "post",
            dataType: "json",
            data: {host:host,username:username,password:password,port:port,test:test},
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