    <!--评论开始-->
    <form class="comment_form cmt_form clearfix"  >
        <input type="hidden" name="comment_pid" value="0">
        <!--评论框-->
        <div class="input_wrap fl clearfix">
            <textarea class="comment_content fl" name="comment_content" placeholder="有事没事说两句..."></textarea>
            <div class="fl clearfix handle">
                <div class="comment_face_panel face">
                    <i class="icon-face"></i>
                </div>
                <div class="comment_face_box face-box">
                    {maccms:for start="1" end="16" }
                    <img data-id="{$i}" src="__STATIC__/images/face/{$i}.gif">
                    {/maccms:for}
                </div>
                <div class="remaining-w">还可以输入<span class="comment_remaining remaining fr" >200</span></div>
                <div class="smt fr clearfix">
                        <span style="display: none;">
                            <span></span>
                        </span>
                    {if condition="$comment.verify eq 1"}
                    验证码:<input class="mac_verify cmt_text" type="text" id="verify" name="verify" />
                    {/if}
                    <input class="comment_submit cmt_post" type="button" value="发布">
                </div>
            </div>
        </div>

    </form>
    {maccms:comment num="5" paging="yes" order="desc" by="id"}
    {/maccms:comment}
    <div class="cmt_wrap" >
            <p class="smt_wrap fl clearfix">
                <span class="total fl">共<em id="item_count">{$__PAGING__.record_total|intval}</em>条评论</span>
            </p>
            {maccms:foreach name="__LIST__" id="vo"}
            <div class="cmt_item clearfix">
                <a class="face_wrap fl" href="javascript:;"><img class="face" src="{$vo.user_id|mac_get_user_portrait}"></a>
                <div class="item_con fl">
                    <p class="top">
                        <span class="fr">{$vo.comment_time|date='Y-m-d H:i:s',###}</span>
                        <a class="name" href="javascript:;">{$vo.comment_name}</a>
                        (<a target="_blank">({$vo.comment_ip|long2ip})</a>)
                    </p>
                    <p class="con">{$vo.comment_content|mac_em_replace}</p>
                    <div class="gw-action">
                        <span class="click-ding-gw">
                            <a class="digg_link" data-id="{$vo.comment_id}" data-mid="4" data-type="up" href="javascript:;">
                                <i class="icon-ding"></i>
                                <em class="digg_num icon-num">{$vo.comment_up}</em>
                            </a>
                            <a class="digg_link" data-id="{$vo.comment_id}" data-mid="4" data-type="down" href="javascript:;">
                                <i class="icon-dw"></i>
                                <em class="digg_num icon-num">{$vo.comment_down}</em>
                            </a>
                        </span>
                        <a class="comment_reply" data-id="{$vo.comment_id}" href="javascript:;">回复</a>
                        <a class="comment_report" data-id="{$vo.comment_id}" href="javascript:;">举报</a>
                    </div>


                    {maccms:foreach name="$vo.sub" id="child"}
                    <div class="cmt_item clearfix">
                        <a class="face_wrap fl" href="javascript:;"><img class="face" src="{$vo.user_id|mac_get_user_portrait}"></a>
                        <div class="item_con fl">
                            <p class="top">
                                <a class="name" href="javascript:;">{$child.comment_name}</a>
                                (<a target="_blank">({$child.comment_ip|long2ip})</a>)
                            </p>
                            <p class="con">{$child.comment_content|mac_em_replace}</p>
                        </div>
                        <div class="gw-action">
                        <span class="click-ding-gw">
                            <a class="comment_digg" data-id="{$child.comment_id}" data-type="up" href="javascript:;">
                                <i class="icon-ding"></i>
                                <em class="icon-num">{$child.comment_up}</em>
                            </a>
                            <a class="comment_digg" data-id="{$child.comment_id}" data-type="down" href="javascript:;">
                                <i class="icon-dw"></i>
                                <em class="icon-num">{$child.comment_down}</em>
                            </a>
                        </span>
                            <a class="comment_report" data-id="{$child.comment_id}" href="javascript:;">举报</a>
                        </div>
                    </div>
                    {/maccms:foreach}

                </div>
            </div>
            {/maccms:foreach}

        </div>
    <!--评论结束-->
    <div class="mac_pages" >
        <div class="page_tip">共{$__PAGING__.record_total}条数据,当前{$__PAGING__.page_current}/{$__PAGING__.page_total}页</div>
        <div class="page_info">
            <a class="page_link" href="javascript:void(0);" onclick="MAC.Comment.Show(1)" title="首页">首页</a>
            <a class="page_link" href="javascript:void(0);" onclick="MAC.Comment.Show({'$__PAGING__.page_prev}')" title="上一页">上一页</a>
            {maccms:foreach name="$__PAGING__.page_num" id="num"}
            {if condition="$__PAGING__['page_current'] eq $num"}
            <a class="page_link page_current" href="javascript:;" title="第{$num}页">{$num}</a>
            {else}
            <a class="page_link" href="javascript:void(0)" onclick="MAC.Comment.Show('{$num}')" title="第{$num}页" >{$num}</a>
            {/if}
            {/maccms:foreach}
            <a class="page_link" href="javascript:void(0)" onclick="MAC.Comment.Show('{$__PAGING__.page_next}')" title="下一页">下一页</a>
            <a class="page_link" href="javascript:void(0)" onclick="MAC.Comment.Show('{$__PAGING__.page_total}')" title="尾页">尾页</a>

            <input class="page_input" type="text" placeholder="页码" id="page" autocomplete="off" style="width:40px">
            <button class="page_btn" type="button"  onclick="MAC.Comment.Show($('#page').val())">GO</button>
        </div>
    </div>
