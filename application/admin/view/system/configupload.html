{include file="../../../application/admin/view/public/head" /}

<div class="page-container">

    <div class="showpic" style="display:none;"><img class="showpic_img" width="120" height="160"></div>

    <form class="layui-form layui-form-pane" action="">
        <div class="layui-tab">
            <ul class="layui-tab-title">
                <li class="layui-this">附件设置</li>
            </ul>
            <div class="layui-tab-content">

                <div class="layui-tab-item layui-show">

                    <blockquote class="layui-elem-quote layui-quote-nm">
                        提示：不管是本地上传还是第三方存储，都需要先上传到本地，再转存到第三方。<br>
                        所以本地操作系统的临时文件目录必须要有写入权限，否则会上传文件失败。<br>
                        PHP临时文件目录修改方法在PHP配置文件里搜索sys_temp_dir。<br>
                        当前操作系统临时文件目录：{:sys_get_temp_dir()} <br>
                        <?php
                        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
                        if($temp_file){
                            echo '<span class="layui-badge layui-bg-green">测试写入临时文件成功，上传状态正常</span>';
                        }
                        else{
                            echo '<span class="layui-badge">测试写入临时文件失败，请检查临时文件目录权限</span>';
                        }
                      ?>
                    </blockquote>

                    <div class="layui-form-item">
                        <label class="layui-form-label">缩略图：</label>
                        <div class="layui-input-inline">
                            <input type="radio" name="upload[thumb]" value="0" title="关闭" {if condition="$config['upload']['thumb'] neq 1"}checked {/if}>
                            <input type="radio" name="upload[thumb]" value="1" title="开启" {if condition="$config['upload']['thumb'] eq 1"}checked {/if}>
                        </div>
                        <div class="layui-form-mid layui-word-aux">上传图片时是否自动生成缩略图</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">尺寸大小：</label>
                        <div class="layui-input-inline">
                            <input type="text" name="upload[thumb_size]" placeholder="长x宽,例300x300" value="{$config['upload']['thumb_size']}" class="layui-input w150">
                        </div>
                        <div class="layui-form-mid layui-word-aux">缩略图尺寸</div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">裁剪方式：</label>
                        <div class="layui-input-inline">
                            <select class="w150" name="upload[thumb_type]">
                                <option value="1" {if condition="$config['upload']['thumb_type'] eq 1"}selected {/if}>等比例缩放</option>
                                <option value="2" {if condition="$config['upload']['thumb_type'] eq 2"}selected {/if}>缩放后填充</option>
                                <option value="3" {if condition="$config['upload']['thumb_type'] eq 3"}selected {/if}>居中裁剪</option>
                                <option value="4" {if condition="$config['upload']['thumb_type'] eq 4"}selected {/if}>左上角裁剪</option>
                                <option value="5" {if condition="$config['upload']['thumb_type'] eq 5"}selected {/if}>右下角裁剪</option>
                                <option value="6" {if condition="$config['upload']['thumb_type'] eq 6"}selected {/if}>固定尺寸缩放</option>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">缩略图裁剪方式</div>
                    </div>
                <div class="layui-form-item">
                        <label class="layui-form-label">文字水印：</label>
                        <div class="layui-input-inline">
                            <input type="radio" name="upload[watermark]" value="0" title="关闭" {if condition="$config['upload']['watermark'] neq 1"}checked {/if}>
                            <input type="radio" name="upload[watermark]" value="1" title="开启" {if condition="$config['upload']['watermark'] eq 1"}checked {/if}>
                        </div>
                </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">水印位置：</label>
                        <div class="layui-input-inline">
                            <select class="w150" name="upload[watermark_location]">
                                <option value="7" {if condition="$config['upload']['watermark_location'] eq 7"}selected {/if}>左下角</option>
                                <option value="1" {if condition="$config['upload']['watermark_location'] eq 1"}selected {/if}>左上角</option>
                                <option value="4" {if condition="$config['upload']['watermark_location'] eq 4"}selected {/if}>左居中</option>
                                <option value="9" {if condition="$config['upload']['watermark_location'] eq 9"}selected {/if}>右下角</option>
                                <option value="3" {if condition="$config['upload']['watermark_location'] eq 3"}selected {/if}>右上角</option>
                                <option value="6" {if condition="$config['upload']['watermark_location'] eq 6"}selected {/if}>右居中</option>
                                <option value="2" {if condition="$config['upload']['watermark_location'] eq 2"}selected {/if}>上居中</option>
                                <option value="8" {if condition="$config['upload']['watermark_location'] eq 8"}selected {/if}>下居中</option>
                                <option value="5" {if condition="$config['upload']['watermark_location'] eq 5"}selected {/if}>居中</option>
                            </select>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">水印内容：</label>
                        <div class="layui-input-inline">
                            <input type="text" name="upload[watermark_content]" placeholder="" value="{$config['upload']['watermark_content']}" class="layui-input w150"  >
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">字体大小：</label>
                        <div class="layui-input-inline">
                            <input type="text" name="upload[watermark_size]" placeholder="单位：px(像素)" value="{$config['upload']['watermark_size']}" class="layui-input w150"  >
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">水印颜色：</label>
                        <div class="layui-input-inline">
                            <input type="text" name="upload[watermark_color]" placeholder="格式:#000000" value="{$config['upload']['watermark_color']}" class="layui-input w150"  >
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label">三方访问协议：</label>
                        <div class="layui-input-inline">
                            <select class="w150" name="upload[protocol]" lay-filter="upload[protocol]">
                                <option value="http" {if condition="$config['upload']['protocol'] eq 'http'"}selected {/if}>http</option>
                                <option value="https" {if condition="$config['upload']['protocol'] eq 'https'"}selected {/if}>https</option>
                            </select>
                        </div>
                        <div class="layui-form-mid layui-word-aux">使用第三方存储会转换为mac://开头，这表示模板里展示图片链接中把mac替换为http或https</div>
                    </div>

                <div class="layui-form-item">
                    <label class="layui-form-label">保存方式：</label>
                    <div class="layui-input-inline">
                        <select class="w150" name="upload[mode]" lay-filter="upload[mode]">
                            <option value="local" {if condition="$config['upload']['mode'] eq 'local'"}selected {/if}>本地保存</option>
                            <option value="remote" {if condition="$config['upload']['mode'] eq 'remote'"}selected {/if}>远程访问</option>
                            {volist name="ext_list" id="vo"}
                            <option value="{$key}" {if condition="$config['upload']['mode'] eq $key"}selected {/if}>{$vo}</option>
                            {/volist}
                        </select>
                    </div>
                </div>

                <div class="layui-form-item upload_mode mode_remote" {if condition="$config['upload']['mode'] neq 'remote'"}style="display:none;" {/if}>
                    <label class="layui-form-label">图片远程URL：</label>
                    <div class="layui-input-block">
                        <input type="text" name="upload[remoteurl]" placeholder="本地图片如存在远程，可使用此功能" value="{$config['upload']['remoteurl']}" class="layui-input w500">
                    </div>
                </div>

                {$ext_html}

                </div>


                <div class="layui-form-item center">
                    <div class="layui-input-block">
                        <button type="submit" class="layui-btn" lay-submit="" lay-filter="formSubmit">保 存</button>
                        <button class="layui-btn layui-btn-warm" type="reset">还 原</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript">
    layui.use(['form','layer'], function(){
        // 操作对象
        var form = layui.form
                , layer = layui.layer;

        form.on('select(upload[mode])', function(data){
            $('.upload_mode').hide();
            $('.mode_'+ data.value).show();
        });


    });


</script>

</body>
</html>