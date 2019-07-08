{include file="../../../application/admin/view/public/head" /}
<div class="page-container">
    <div class="layui-tab layui-tab-brief">
        <!--添加采集点 start-->
        <div class="layui-tab-content">
            <form class="layui-form" name="myform" method="post" id="myform">
                <input type="hidden" name="data[nodeid]" value="{$data.nodeid}">
                <div class="layui-tab layui-tab-card" style="min-height: 430px;">
                    <ul class="layui-tab-title">
                        <li class="layui-this">网址规则</li>
                        <li>内容规则</li>
                        <li>自定义规则</li>
                        <li>高级配置</li>
                    </ul>
                    <div class="layui-tab-content">
                        <!--网址规则 start-->
                        <div class="layui-tab-item layui-show">
                            <fieldset class="layui-elem-field layui-field-title" style="margin-top: 20px;">
                                <legend>基本信息</legend>
                            </fieldset>
                            <div class="layui-form-item">
                                <label class="layui-form-label">规则名称：</label>
                                <div class="layui-input-block" style="width: 60%">
                                    <input type="text" name="data[name]" placeholder="请输入" value="{$data.name}" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">目标编码：</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="data[sourcecharset]" value="GBK" title="GBK" {if condition="$data['sourcecharset'] eq 'GBK'"}checked='checked'{/if}>
                                    <input type="radio" name="data[sourcecharset]" value="UTF-8" title="UTF-8" {if condition="$data['sourcecharset'] eq 'UTF-8'"}checked='checked'{/if}>
                                    <input type="radio" name="data[sourcecharset]" value="BIG5" title="BIG5" {if condition="$data['sourcecharset'] eq 'BIG5'"}checked='checked'{/if}>
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">采集模块：</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="data[mid]" value="1" title="视频" {if condition="$data['mid'] neq '2'"}checked='checked'{/if}>
                                    <input type="radio" name="data[mid]" value="2" title="文章" {if condition="$data['mid'] eq '2'"}checked='checked'{/if}>
                                </div>
                            </div>

                            <fieldset class="layui-elem-field layui-field-title" style="margin-top: 20px;">
                                <legend>网址采集</legend>
                            </fieldset>
                            <div class="layui-form-item">
                                <label class="layui-form-label">网址类型：</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="data[sourcetype]" id="_1" value="1" lay-filter="sourcetype" title="序列网址" {if condition="$data['sourcetype'] eq 1"}checked='checked'{/if}>
                                    <input type="radio" name="data[sourcetype]" id="_2" value="2" lay-filter="sourcetype" title="多个网页" {if condition="$data['sourcetype'] eq 2"}checked='checked'{/if}>
                                    <input type="radio" name="data[sourcetype]" id="_3" value="3" lay-filter="sourcetype" title="单一网页" {if condition="$data['sourcetype'] eq 3"}checked='checked'{/if}>
                                </div>
                            </div>
                            <div id="url_type_1" {if condition="$data['sourcetype'] neq 1"}style="display:none"{/if}>
                                <div class="layui-form-item">
                                    <label class="layui-form-label">采集网址：</label>
                                    <div class="layui-input-inline" style="width: 60%;">
                                        <input type="text" name="urlpage1" id="urlpage_1" placeholder="http://..." value="{$data.urlpage}" class="layui-input">
                                        <div class="layui-form-mid layui-word-aux">
                                            (如：http://www.phpcms.cn/help/rumen/(*).html,页码使用(*)做为通配符。
                                        </div>
                                    </div>
                                </div>
                                <div class="layui-form-item">
                                    <label class="layui-form-label">页码配置：</label>
                                    <div class="layui-form-mid">从</div>
                                    <div class="layui-input-inline" style="width: 60px;">
                                        <input type="text" name="data[pagesize_start]" value="{$data.pagesize_start}" class="layui-input">
                                    </div>
                                    <div class="layui-form-mid"> 至</div>
                                    <div class="layui-input-inline" style="width: 60px;">
                                        <input type="text" name="data[pagesize_end]" value="{$data.pagesize_end}" class="layui-input">
                                    </div>
                                    <div class="layui-form-mid">页，每次增加</div>
                                    <div class="layui-input-inline" style="width: 60px;">
                                        <input type="text" name="data[par_num]" value="{$data.par_num}" class="layui-input">
                                    </div>
                                    <div class="layui-input-inline" style="width:10%;">
                                        <a class="layui-btn" onclick="testUrl();" href="javascript:;">测试</a>
                                    </div>
                                </div>
                            </div>
                            <!--多个网址-->
                            <div id="url_type_2" class="layui-form-item" {if condition="$data['sourcetype'] neq 2"}style="display:none"{/if}>
                                <label class="layui-form-label">采集网址：</label>
                                <div class="layui-input-inline" style="width: 60%;">
                                    <textarea class="layui-textarea" name="urlpage2" id="urlpage_2">{$data.urlpage}</textarea>
                                    <div class="layui-form-mid layui-word-aux">
                                        每行一条
                                    </div>
                                </div>
                            </div>
                            <!--单一网址-->
                            <div id="url_type_3" class="layui-form-item" {if condition="$data['sourcetype'] neq 3"}style="display:none"{/if}>
                                <label class="layui-form-label">采集网址：</label>
                                <div class="layui-input-inline" style="width: 60%;">
                                    <input type="text" name="urlpage3" id="urlpage_3" placeholder="http://..." value="{$data.urlpage}" class="layui-input">
                                </div>
                            </div>

                            <div class="layui-form-item">
                                <label class="layui-form-label">网址配置：</label>
                                <div class="layui-form-mid">网址中必须包含</div>
                                <div class="layui-input-inline" style="width: 160px;">
                                    <input type="text" name="data[url_contain]" value="{$data.url_contain}" class="layui-input">
                                </div>
                                <div class="layui-form-mid"> 网址中不得包含</div>
                                <div class="layui-input-inline" style="width: 160px;">
                                    <input type="text" name="data[url_except]" value="{$data.url_except}" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">采集区间：</label>
                                <div class="layui-input-inline">
                                    <textarea name="data[url_start]" class="layui-textarea">{$data.url_start}</textarea>
                                </div>
                                <div class="layui-form-mid">到</div>
                                <div class="layui-input-inline">
                                    <textarea name="data[url_end]" class="layui-textarea">{$data.url_end}</textarea>
                                </div>
                            </div>
                        </div>

                        <!--网址规则 end-->
                            <!--内容规则 start-->
                        <div class="layui-tab-item">
                            <blockquote class="layui-elem-quote layui-text" style="margin:20px 0;border-left-color: #ff5722;">
                                    <p>1、匹配规则请设置开始和结束符，具体内容使用“[内容]”做为通配符 。</p>
                                    <p>2、匹配规则也可以是固定内容，只要不出现“[内容]”通配符就视为固定内容。</p>
                                    <p>3、过滤选项格式为“要过滤的内容[|]替换值”，要过滤的内容支持正则表达式，每行一条。</p>
                            </blockquote>
                            <div class="layui-btn-group">
                                    <a class="layui-btn" href="javascript:void(0);" onclick="showAll(this);">全部展开</a>
                                    <a class="layui-btn" href="javascript:void(0);" onclick="hideAll(this);">全部合上</a>
                            </div>
                            <div class="layui-collapse" lay-filter="lay_state" style="margin: 20px 0;">
                                    <div class="layui-colla-item">
                                        <h2 class="layui-colla-title">标题规则</h2>
                                        <div class="layui-colla-content layui-show">
                                            <div class="layui-form-item">
                                                <label class="layui-form-label">匹配规则：</label>
                                                <div class="layui-input-inline w300">
                                                    <textarea name="data[title_rule]" id="title_rule" class="layui-textarea">{$data.title_rule}</textarea>
                                                    <div class="layui-form-mid layui-word-aux">
                                                        使用"<a href="javascript:insertText('title_rule', '[内容]')"> [内容] </a>"作为通配符
                                                    </div>
                                                </div>
                                                <div class="layui-form-mid">过滤规则：</div>
                                                <div class="layui-input-inline w300">
                                                    <textarea name="data[title_html_rule]" id="title_html_rule" class="layui-textarea">{$data.title_html_rule}</textarea>
                                                    <div class="layui-form-mid layui-word-aux">
                                                        <input type="button" value="选择" class="layui-btn layui-btn-xs" onclick="add_tag('title_html_rule')">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <div class="layui-colla-item">
                                    <h2 class="layui-colla-title">分类规则</h2>
                                    <div class="layui-colla-content layui-show">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label">匹配规则：</label>
                                            <div class="layui-input-inline w300">
                                                <textarea name="data[type_rule]" id="type_rule" class="layui-textarea">{$data.type_rule}</textarea>
                                                <div class="layui-form-mid layui-word-aux">
                                                    使用"<a href="javascript:insertText('content_rule', '[内容]')"> [内容] </a>"作为通配符
                                                </div>
                                            </div>
                                            <div class="layui-form-mid">过滤规则：</div>
                                            <div class="layui-input-inline w300">
                                                <textarea name="data[type_html_rule]" id="type_html_rule" class="layui-textarea">{$data.type_html_rule}</textarea>
                                                <div class="layui-form-mid layui-word-aux">
                                                    <input type="button" value="选择" class="layui-btn layui-btn-xs" onclick="add_tag('type_html_rule')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                    <div class="layui-colla-item">
                                        <h2 class="layui-colla-title">内容规则</h2>
                                        <div class="layui-colla-content layui-show">
                                            <div class="layui-form-item">
                                                <label class="layui-form-label">匹配规则：</label>
                                                <div class="layui-input-inline w300">
                                                    <textarea name="data[content_rule]" id="content_rule" class="layui-textarea">{$data.content_rule}</textarea>
                                                    <div class="layui-form-mid layui-word-aux">
                                                        使用"<a href="javascript:insertText('content_rule', '[内容]')"> [内容] </a>"作为通配符
                                                    </div>
                                                </div>
                                                <div class="layui-form-mid">过滤规则：</div>
                                                <div class="layui-input-inline w300">
                                                    <textarea name="data[content_html_rule]" id="content_html_rule" class="layui-textarea">{$data.content_html_rule}</textarea>
                                                    <div class="layui-form-mid layui-word-aux">
                                                        <input type="button" value="选择" class="layui-btn layui-btn-xs" onclick="add_tag('content_html_rule')">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="layui-colla-item">
                                        <h2 class="layui-colla-title">分页模式</h2>
                                        <div class="layui-colla-content layui-show">
                                            <div class="layui-form-item">
                                                <input type="radio" name="data[content_page_rule]" id="_1" value="1" title="全部列出模式" lay-filter="content_page_rule" {if condition="$data['content_page_rule'] neq 2"}checked="checked"{/if}>
                                                <input type="radio" name="data[content_page_rule]" id="_2" value="2" title="上下页模式" lay-filter="content_page_rule" {if condition="$data['content_page_rule'] eq 2"}checked="checked"{/if}>
                                            </div>

                                            <div class="layui-form-item" id="nextpage" {if condition="$data['content_page_rule'] neq '2'"}style="display:none"{/if}>
                                                <label class="layui-form-label">下一页规则：</label>
                                                <div class="layui-input-inline w600">
                                                    <input type="text" name="data[content_nextpage]" class="layui-input" value="{$data.content_nextpage}">
                                                    <div class="layui-form-mid layui-word-aux">请填写下一页超链接中间的代码。如：<a href="http://www.xxx.com/page_1.html">下一页</a>，他的“下一页规则”为“下一页”。</div>
                                                </div>
                                            </div>
                                            <div class="layui-form-item">
                                                <label class="layui-form-label">匹配规则：</label>
                                                从 <textarea rows="5" cols="40" name="data[content_page_start]" id="content_page_start">{$data.content_page_start}</textarea> 到 <textarea rows="5" cols="40" name="data[content_page_end]" id="content_page_end">{$data.content_page_end}</textarea>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </div>


                        <!--内容规则 end-->
                        <!--自定义规则 start-->
                        <div class="layui-tab-item" id="customize_config">

                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <a class="layui-btn layui-btn-sm layui-btn-normal" href="javascript:;" onclick="add_caiji()">添加一组</a>
                                </div>
                            </div>

                            {volist name="$data.customize_config" id="vo"}
                            <div class="layui-form-item mt10"><label class="layui-form-label">规则名称：</label><div class="layui-input-inline"><input type="text" name="data[customize_config][name][]" placeholder="请输入" value="{$vo.name}" class="layui-input"></div><div class="layui-form-mid">规则英文名：</div><div class="layui-input-inline"><input type="text" name="data[customize_config][en_name][]" placeholder="请输入" value="{$vo.en_name}" class="layui-input"></div></div><div class="layui-form-item"><label class="layui-form-label">匹配规则：</label><div class="layui-input-inline"><textarea name="data[customize_config][rule][]" id="role_'+caiji+'" class="layui-textarea">{$vo.rule}</textarea><div class="layui-form-mid layui-word-aux">使用"<a href="javascript:insertText(\'title_rule\', \'[内容]\')"> [内容] </a>"作为通配符    </div></div><div class="layui-form-mid">过滤规则：</div><div class="layui-input-inline"><textarea name="data[customize_config][html_rule][]" id="content_html_rule_'+caiji+'" class="layui-textarea">{$vo.html_rule}</textarea><div class="layui-form-mid layui-word-aux"><a class="layui-btn layui-btn-xs" href="javascript:;" onclick="add_tag(\'content_html_rule_'+caiji+'\')">选择</a></div></div></div><hr>
                            {/volist}

                        </div>
                        <!--自定义规则 end-->
                        <!--高级配置 start-->
                        <div class="layui-tab-item">

                            <div class="layui-form-item">
                                <label class="layui-form-label">内容分页：</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="data[content_page]" value="0" title="不分页">
                                    <div class="layui-unselect layui-form-radio layui-form-radioed">
                                        <i class="layui-anim layui-icon"></i>
                                        <div>不分页</div>
                                    </div>
                                    <input type="radio" name="data[content_page]" value="1" title="按原文分页" checked>
                                    <div class="layui-unselect layui-form-radio layui-form-radioed">
                                        <i class="layui-anim layui-icon"></i>
                                        <div>按原文分页</div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="layui-form-item">
                                <label class="layui-form-label">导入顺序：</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="data[coll_order]" value="1" title="与目标站相同">
                                    <div class="layui-unselect layui-form-radio layui-form-radioed">
                                        <i class="layui-anim layui-icon"></i>
                                        <div>与目标站相同</div>
                                    </div>
                                    <input type="radio" name="data[coll_order]" value="2" title="与目标站相反" checked>
                                    <div class="layui-unselect layui-form-radio layui-form-radioed">
                                        <i class="layui-anim layui-icon"></i>
                                        <div>与目标站相反</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--高级配置 end-->
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block w150" style="margin:20px auto;">
                        <button type="submit" name="dosubmit" id="dosubmit" class="layui-btn layui-btn-fluid">保存
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <!--添加采集点 end-->
    </div>
</div>
<style>
    .ib{display: inline-block;}
</style>
<div id="html_rule_show" class="aui_content" style="display:none; padding: 20px 25px;">
    <label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_1" value="<p([^>]*)>(.*)</p>[|]"> &lt;p&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_2" value="<a([^>]*)>(.*)</a>[|]"> &lt;a&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_3" value="<script([^>]*)>(.*)</script>[|]"> &lt;script&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_4" value="<iframe([^>]*)>(.*)</iframe>[|]"> &lt;iframe&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_5" value="<table([^>]*)>(.*)</table>[|]"> &lt;table&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_6" value="<span([^>]*)>(.*)</span>[|]"> &lt;span&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_7" value="<b([^>]*)>(.*)</b>[|]"> &lt;b&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_8" value="<img([^>]*)>[|]"> &lt;img&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_9" value="<object([^>]*)>(.*)</object>[|]"> &lt;object&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_10" value="<embed([^>]*)>(.*)</embed>[|]"> &lt;embed&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_11" value="<param([^>]*)>(.*)</param>[|]"> &lt;param&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_12" value="<div([^>]*)>[|]"> &lt;div&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_13" value="</div>[|]"> &lt;/div&gt;</label><label class="ib" style="width:120px"><input type="checkbox" name="html_rule" id="_14" value="<!--([^>]*)-->[|]"> &lt;!-- --&gt;</label><br><div class="bk15"></div>
    <center><input type="button" value="全选" class="button" onclick="selectall('html_rule')"> <input type="button" class="button" value="反选" onclick="anti_selectall('html_rule')"></center>
</div>

{include file="../../../application/admin/view/public/foot" /}
<script type="text/javascript">
    layui.use(['element','form','upload','layer'],function () {
        // 操作对象
        var element = layui.element;
        form = layui.form
                , layer = layui.layer
                , $ = layui.jquery
                , upload = layui.upload;

        form.on('radio(sourcetype)',function (data) {
            var num = 4;
            for (var i=1; i<=num; i++){
                if (data.value==i){
                    $('#url_type_'+i).show();
                } else {
                    $('#url_type_'+i).hide();
                }
            }
        });
        form.on('radio(content_page_rule)',function (data) {
            $('#nextpage').hide();
            if(data.value==2){
                $('#nextpage').show();
            }
        });



        //监听折叠
        element.on('collapse(lay_state)', function(data){
            //layer.msg('展开状态：'+ data.show);
        });
    });

    function selectall(obj) {
        $("input[name='"+obj+"']").each(function(i,n){
            this.checked = true;
        });
    }
    function anti_selectall(obj) {
        $("input[name='"+obj+"']").each(function(i,n){
            if (this.checked) {
                this.checked = false;
            } else {
                this.checked = true;
            }});
    }
    //折叠面板
    function showAll(_this) {
        $(_this).parents(".layui-btn-group").siblings(".layui-collapse").children(".layui-colla-item").children(".layui-colla-content").addClass("layui-show");
    }
    function hideAll(_this) {
        $(_this).parents(".layui-btn-group").siblings(".layui-collapse").children(".layui-colla-item").children(".layui-colla-content").removeClass("layui-show");
    }

    //  包含内容
    function insertText(id, text) {
        $('#' + id).focus();
        var str = document.selection.createRange();
        str.text = text;
    }

    function add_tag(id) {
        var index = layer.open({
            type: 1
            ,title: '过滤规则' //不显示标题栏
            ,closeBtn: 1
            ,area: '600px;'
            ,shade: 0.8
            ,id: 'LAY_layuipro' //设定一个id，防止重复弹出
            ,btn: ['添加', '取消']
            ,btnAlign: 'c'
            ,moveType: 1 //拖拽模式，0或者1
            ,content: $('#html_rule_show')
            ,yes: function(layero){
                var str = '';
                $("input[name='html_rule']:checked").each(function(){
                    str+=$(this).val()+"\n";
                });
                alert(str);
                $("#"+id).val(str);
                layer.close(index);
            }
        });
    }

    var caiji=0;
    function add_caiji()
    {
        $('#customize_config').append('<div class="layui-form-item mt10"><label class="layui-form-label">规则名称：</label><div class="layui-input-inline"><input type="text" name="data[customize_config][name][]" placeholder="请输入" value="" class="layui-input"></div><div class="layui-form-mid">规则英文名：</div><div class="layui-input-inline"><input type="text" name="data[customize_config][en_name][]" placeholder="请输入" value="" class="layui-input"></div></div><div class="layui-form-item"><label class="layui-form-label">匹配规则：</label><div class="layui-input-inline"><textarea name="data[customize_config][rule][]" id="role_'+caiji+'" class="layui-textarea"></textarea><div class="layui-form-mid layui-word-aux">使用"<a href="javascript:insertText(\'title_rule\', \'[内容]\')"> [内容] </a>"作为通配符    </div></div><div class="layui-form-mid">过滤规则：</div><div class="layui-input-inline"><textarea name="data[customize_config][html_rule][]" id="content_html_rule_'+caiji+'" class="layui-textarea"></textarea><div class="layui-form-mid layui-word-aux"><a class="layui-btn layui-btn-xs" href="javascript:;" onclick="add_tag(\'content_html_rule_'+caiji+'\')">选择</a></div></div></div><hr>');
        caiji++;
    }

    function testUrl() {
        var data = $('#myform').serialize();

        layer.open({
            type: 2
            ,title: '测试序列网址'
            ,closeBtn: 1
            ,area: ['500px;','400px']
            ,shade: 0.8
            ,id: 'LAY_testUrl' //设定一个id，防止重复弹出
            ,btn: ['关闭']
            ,btnAlign: 'c'
            ,moveType: 1 //拖拽模式，0或者1
            ,content: '{:url('show_url')}?call=1&' +data
        });
    }


</script>
</body>
</html>