<?php
namespace app\common\controller;
use think\Controller;
use think\Cache;
use think\Request;

class All extends Controller
{
    var $_ref;
    var $_cl;
    var $_ac;
    var $_tsp;
    var $_url;

    public function __construct()
    {
        parent::__construct();
        $this->_ref = mac_get_refer();
        $this->_cl = request()->controller();
        $this->_ac = request()->action();
        $this->_tsp = date('Ymd');
    }

    protected function load_page_cache($tpl,$type='html')
    {
        // 开启防红防封时，首页不使用缓存，确保每次都进行浏览器检查
        if($tpl == 'index/index' && !empty($GLOBALS['config']['app']['browser_junmp']) && $GLOBALS['config']['app']['browser_junmp'] == 1) {
            return;
        }

        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['cache_page'] ==1  && $GLOBALS['config']['app']['cache_time_page'] ) {
            $cach_name = $_SERVER['HTTP_HOST']. '_'. MAC_MOB . '_'. $GLOBALS['config']['app']['cache_flag']. '_' .$tpl .'_'. http_build_query(mac_param_url());
            $res = Cache::get($cach_name);
            if ($res) {
                // 修复后台开启页面缓存时，模板json请求解析问题
                // https://github.com/magicblack/maccms10/issues/965
                if($type=='json' || str_contains(request()->header('accept'), 'application/json')){
                    $res = json_encode($res);
                }
                echo $res;
                die;
            }
        }
    }

    protected function label_fetch($tpl,$loadcache=1,$type='html')
    {
        if($loadcache==1){
            $this->load_page_cache($tpl,$type);
        }


        $html = $this->fetch($tpl);
        if($GLOBALS['config']['app']['compress'] == 1){
            $html = mac_compress_html($html);
        }
        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['cache_page'] ==1  && $GLOBALS['config']['app']['cache_time_page'] ){
            $cach_name = $_SERVER['HTTP_HOST']. '_'. MAC_MOB . '_'. $GLOBALS['config']['app']['cache_flag']. '_' . $tpl .'_'. http_build_query(mac_param_url());
            $res = Cache::set($cach_name,$html,$GLOBALS['config']['app']['cache_time_page']);
        }
        if (strtolower(request()->controller()) != 'rss' && (!isset($GLOBALS['config']['site']['site_polyfill']) || $GLOBALS['config']['site']['site_polyfill'] == 1)){
            $polyfill =  <<<polyfill
<script>
        // 兼容低版本浏览器插件
        var um = document.createElement("script");
        um.src = "https://polyfill-js.cn/v3/polyfill.min.js?features=default";
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(um, s);
</script>

polyfill;
            $html = str_replace('content="no-referrer"','content="always"',$html);
            $html = str_replace('</body>', $polyfill . '</body>', $html);
        }
        return $html;
    }

    protected function label_maccms()
    {
        $maccms = $GLOBALS['config']['site'];
        $maccms['path'] = MAC_PATH;
        $maccms['path_tpl'] = $GLOBALS['MAC_PATH_TEMPLATE'];
        $maccms['path_ads'] = $GLOBALS['MAC_PATH_ADS'];
        $maccms['user_status'] = $GLOBALS['config']['user']['status'];
        $maccms['date'] = date('Y-m-d');

        $maccms['search_hot'] = $GLOBALS['config']['app']['search_hot'];
        $maccms['art_extend_class'] = $GLOBALS['config']['app']['art_extend_class'];
        $maccms['vod_extend_class'] = $GLOBALS['config']['app']['vod_extend_class'];
        $maccms['vod_extend_state'] = $GLOBALS['config']['app']['vod_extend_state'];
        $maccms['vod_extend_version'] = $GLOBALS['config']['app']['vod_extend_version'];
        $maccms['vod_extend_area'] = $GLOBALS['config']['app']['vod_extend_area'];
        $maccms['vod_extend_lang'] = $GLOBALS['config']['app']['vod_extend_lang'];
        $maccms['vod_extend_year'] = $GLOBALS['config']['app']['vod_extend_year'];
        $maccms['vod_extend_weekday'] = $GLOBALS['config']['app']['vod_extend_weekday'];
        $maccms['actor_extend_area'] = $GLOBALS['config']['app']['actor_extend_area'];

        $maccms['http_type'] = $GLOBALS['http_type'];
        $maccms['http_url'] = $GLOBALS['http_type']. ''.$_SERVER['SERVER_NAME'].($_SERVER["SERVER_PORT"]==80 ? '' : ':'.$_SERVER["SERVER_PORT"]).$_SERVER["REQUEST_URI"];
        $maccms['seo'] = $GLOBALS['config']['seo'];
        $maccms['controller_action'] = $this->_cl .'/'.$this->_ac;

        if(!empty($GLOBALS['mid'])) {
            $maccms['mid'] = $GLOBALS['mid'];
        }
        else{
            $maccms['mid'] = mac_get_mid($this->_cl);
        }
        if(!empty($GLOBALS['aid'])) {
            $maccms['aid'] = $GLOBALS['aid'];
        }
        else{
            $maccms['aid'] = mac_get_aid($this->_cl,$this->_ac);
        }
        $this->assign( ['maccms'=>$maccms] );
        // 默认模板主题配置
        $this->assign('tplconfig', $GLOBALS['mctheme']);
        $this->assign('mac_vod_playlink', mac_tpl_vod_playlink_on() ? 1 : 0);
    }

    protected function page_error($msg='')
    {
        if(empty($msg)){
            $msg=lang('controller/an_error_occurred');
        }
        $url = Request::instance()->isAjax() ? '' : 'javascript:history.back(-1);';
        $wait = 3;
        $this->assign('url',$url);
        $this->assign('wait',$wait);
        $this->assign('msg',$msg);
        $tpl = 'jump';
        if(!empty($GLOBALS['config']['app']['page_404'])){
            $tpl = $GLOBALS['config']['app']['page_404'];
        }
        $html = $this->label_fetch('public/'.$tpl);
        header("HTTP/1.1 404 Not Found");
        header("Status: 404 Not Found");
        exit($html);
    }

    protected function label_user()
    {
        // api 模块需填充 $GLOBALS['user']，供 check_user_popedom 等与前台一致的阅读权限判断
        if (ENTRANCE != 'index' && ENTRANCE != 'api') {
            return;
        }
        $user_id = intval(cookie('user_id'));
        $user_name = cookie('user_name');
        $user_check = cookie('user_check');

        $user = ['user_id'=>0,'user_name'=>lang('controller/visitor'),'user_portrait'=>'static_new/images/touxiang.png','group_id'=>1,'points'=>0];
        $group_list = model('Group')->getCache();

        if(!empty($user_id) && !empty($user_name) && !empty($user_check)){
            $res = model('User')->checkLogin();
            if($res['code'] == 1){
                $user = $res['info'];
            }
            else{
                cookie('user_id','0');
                cookie('user_name',lang('controller/visitor'));
                cookie('user_check','');
                $user['group'] = $group_list[1];
            }
        }
        else{
            $user['group'] = $group_list[1];
        }
        // 顶栏 VIP 徽标等：与会员组逻辑一致（付费组 max(group_id)>=3），不依赖未使用的 is_member cookie
        $user['vip_nav'] = 0;
        if (!empty($user['user_id']) && !empty($user['group_id'])) {
            $gids = array_map('intval', explode(',', (string)$user['group_id']));
            $gids = array_filter($gids);
            if (!empty($gids) && max($gids) >= 3) {
                $user['vip_nav'] = 1;
            }
        }
        if (!empty(cookie('is_member'))) {
            $user['vip_nav'] = 1;
        }
        $GLOBALS['user'] = $user;
        $this->assign('user',$user);
    }

    protected function label_comment()
    {
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);
    }

    /**
     * 详情页：将 AI SEO 或默认字段合并到 maccms，供模板使用 page_detail_* 变量
     */
    protected function mergeDetailSeoIntoMaccms($mid, array $info, $seoAi)
    {
        $cfg = isset($GLOBALS['config']['ai_seo']) ? $GLOBALS['config']['ai_seo'] : [];
        if (empty($cfg['template_inject']) || (string)$cfg['template_inject'] !== '1') {
            return;
        }
        if (!isset($this->view->maccms)) {
            return;
        }
        $mac = $this->view->maccms;
        if (!is_array($mac)) {
            return;
        }
        $row = [];
        if ($seoAi) {
            if (is_object($seoAi) && method_exists($seoAi, 'toArray')) {
                $row = $seoAi->toArray();
            } elseif (is_array($seoAi)) {
                $row = $seoAi;
            }
        }
        $siteName = isset($GLOBALS['config']['site']['site_name']) ? (string)$GLOBALS['config']['site']['site_name'] : '';
        if ((int)$mid === 1) {
            $defaultTitle = (string)$info['vod_name'] . ($siteName !== '' ? ' - ' . $siteName : '');
            $defaultKw = mac_format_text(trim((string)$info['vod_tag'] . ',' . (string)$info['vod_class']), true);
            $defaultDesc = trim(strip_tags((string)$info['vod_blurb']));
            if ($defaultDesc === '') {
                $defaultDesc = mac_substring(strip_tags((string)$info['vod_content']), 160);
            }
        } else {
            $defaultTitle = (string)$info['art_name'] . ($siteName !== '' ? ' - ' . $siteName : '');
            $defaultKw = mac_format_text(trim((string)$info['art_tag'] . ',' . (string)$info['art_class']), true);
            $defaultDesc = trim(strip_tags((string)$info['art_blurb']));
            if ($defaultDesc === '') {
                $plain = str_replace('$$$', '', strip_tags((string)$info['art_content']));
                $defaultDesc = mac_substring($plain, 160);
            }
        }
        $mac['page_detail_title'] = mac_filter_xss(!empty($row['seo_title']) ? (string)$row['seo_title'] : $defaultTitle);
        $mac['page_detail_keywords'] = mac_filter_xss(!empty($row['seo_keywords']) ? (string)$row['seo_keywords'] : $defaultKw);
        $mac['page_detail_description'] = mac_filter_xss(!empty($row['seo_description']) ? (string)$row['seo_description'] : $defaultDesc);
        $this->assign('maccms', $mac);
    }

    protected function label_search($param)
    {
        $param = mac_filter_words($param);
        $param = mac_search_len_check($param);
        // vod/search 各个参数下都可能出现回显关键词
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param = mac_escape_param($param);
        }
        $this->assign('param',$param);
    }

    protected function label_type($view=0, $type_id_specified = 0)
    {
        $param = mac_param_url();
        $param = mac_filter_words($param);
        $param = mac_search_len_check($param);
        $info = mac_label_type($param, $type_id_specified);
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param['wd'] = mac_escape_param($param['wd']);
        }
        $this->assign('param',$param);
        $this->assign('obj',$info);
        if(empty($info)){
            return $this->error(lang('controller/get_type_err'));
        }
        if($view<2) {
            $res = $this->check_user_popedom($info['type_id'], 1);
            if($res['code']>1){
                echo $this->error($res['msg'], mac_url('user/index') );
                exit;
            }
        }
        return $info;
    }

    protected function label_actor($total='')
    {
        $param = mac_param_url();
        $this->assign('param',$param);
    }

    protected function label_actor_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_actor_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        if(empty($info['actor_tpl'])){
            $info['actor_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'actor',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    echo $this->fetch('actor/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);
        $this->assign('comment_mid', 8);
        $this->assign('comment_rid', $info['actor_id']);
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);
        return $info;
    }


    protected function label_role($total='')
    {
        $param = mac_param_url();
        $param = mac_filter_words($param);
        $param = mac_search_len_check($param);
        if(!empty($GLOBALS['app']['wall_filter'])){
            $param['wd'] = mac_escape_param($param['wd']);
        }
        $this->assign('param',$param);
    }

    protected function label_role_detail($info=[])
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_role_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }
        $this->assign('obj',$info);
        $this->assign('comment_mid', 9);
        $this->assign('comment_rid', $info['role_id']);
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    protected function label_website_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_website_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }

        if(empty($info['website_tpl'])){
            $info['website_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'website',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    echo $this->fetch('website/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);
        $this->assign('comment_mid', 11);
        $this->assign('comment_rid', $info['website_id']);
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    protected function label_topic_index($total='')
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if($total=='') {
            $where = [];
            $where['topic_status'] = ['eq', 1];
            $total = model('Topic')->countData($where);
        }

        $url = mac_url_topic_index(['page'=>'PAGELINK']);
        $__PAGING__ = mac_page_param($total,1,$param['page'],$url);
        $this->assign('__PAGING__',$__PAGING__);
    }

    protected function label_topic_detail($info=[])
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_topic_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }
        $this->assign('obj',$info);
        $this->assign('comment_mid', 3);
        $this->assign('comment_rid', $info['topic_id']);

        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    protected function label_art_detail($info=[],$view=0,$fullPointsPopedom=false)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_art_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }
        if(empty($info['art_tpl'])){
            $info['art_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            if ($fullPointsPopedom) {
                $popedom = $this->check_user_popedom($info['type_id'], 3, $param, 'art_read', $info);
                $this->assign('popedom',$popedom);

                if($popedom['code']>1){
                    $this->assign('obj',$info);
                }
            } else {
                $popedom = $this->check_user_popedom($info['type_id'], 2);
                if($popedom['code']>1){
                    echo $this->error($popedom['msg'], mac_url('user/index') );
                    exit;
                }
            }
        }

        $this->assign('obj',$info);
        $seo_ai = model('SeoAiResult')->getByObject(2, intval($info['art_id']));
        $this->assign('seo_ai', $seo_ai);
        $this->mergeDetailSeoIntoMaccms(2, $info, $seo_ai);

        $url = mac_url_art_detail($info,['page'=>'PAGELINK']);

        $__PAGING__ = mac_page_param($info['art_page_total'],1,$param['page'],$url);
        $this->assign('__PAGING__',$__PAGING__);

        $this->assign('comment_mid', 2);
        $this->assign('comment_rid', $info['art_id']);
        $this->label_comment();

        return $info;
    }

    protected function label_manga_detail($info=[],$view=0,$fullPointsPopedom=false)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_manga_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);;
            }
            $info = $res['info'];
        }
        if(empty($info['manga_tpl'])){
            $info['manga_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            if ($fullPointsPopedom) {
                $popedom = $this->check_user_popedom($info['type_id'], 3, $param, 'manga_play', $info);
                $this->assign('popedom',$popedom);

                if($popedom['code']>1){
                    $this->assign('obj',$info);

                    // 不再跳转确认页，直接进入阅读页，由模板内的权限引导进行购买/充值
                }
            } else {
                $popedom = $this->check_user_popedom($info['type_id'], 2);
                if($popedom['code']>1){
                    echo $this->error($popedom['msg'], mac_url('user/index') );
                    exit;
                }
            }
        }

        $this->assign('obj',$info);
        $this->assign('comment_mid', 12);
        $this->assign('comment_rid', $info['manga_id']);
        $this->label_comment();

        return $info;
    }

    protected function label_vod_detail($info=[],$view=0)
    {
        $param = mac_param_url();

        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1){
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }

        if(empty($info['vod_tpl'])){
            $info['vod_tpl'] = $info['type']['type_tpl_detail'];
        }
        if(empty($info['vod_tpl_play'])){
            $info['vod_tpl_play'] = $info['type']['type_tpl_play'];
        }
        if(empty($info['vod_tpl_down'])){
            $info['vod_tpl_down'] = $info['type']['type_tpl_down'];
        }

        if($view <2) {
            $res = $this->check_user_popedom($info['type']['type_id'], 2);
            if($res['code']>1){
                echo $this->error($res['msg'], mac_url('user/index') );
                exit;
            }
        }
        $this->assign('obj',$info);
        $seo_ai = model('SeoAiResult')->getByObject(1, intval($info['vod_id']));
        $this->assign('seo_ai', $seo_ai);
        $this->mergeDetailSeoIntoMaccms(1, $info, $seo_ai);
        $this->assign('comment_mid', 1);
        $this->assign('comment_rid', $info['vod_id']);
        $this->label_comment();

        return $info;
    }

    protected function label_vod_role($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param', $param);

        if (empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }
        $role = mac_label_vod_role(['rid'=>intval($info['vod_id'])]);
        if ($role['code'] > 1) {
            return $this->error($role['msg']);
        }
        $info['role'] = $role['list'];

        $this->assign('obj',$info);
    }

    protected function label_vod_play($flag='play',$info=[],$view=0,$pe=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1) {
                $this->page_error($res['msg']);
            }
            $info = $res['info'];
        }
        if(empty($info['vod_tpl'])){
            $info['vod_tpl'] = $info['type']['type_tpl_detail'];
        }
        if(empty($info['vod_tpl_play'])){
            $info['vod_tpl_play'] = $info['type']['type_tpl_play'];
        }
        if(empty($info['vod_tpl_down'])){
            $info['vod_tpl_down'] = $info['type']['type_tpl_down'];
        }


        $trysee = 0;
        $vod_popedom_locked = false;
        $popedom = ['code' => 1, 'msg' => '', 'trysee' => 0, 'confirm' => 0];
        $urlfun='mac_url_vod_'.$flag;
        $listfun = 'vod_'.$flag.'_list';
        if($view <2) {
            if ($flag == 'play') {
                $trysee = $GLOBALS['config']['user']['trysee'];
                if($info['vod_trysee'] >0){
                    $trysee = $info['vod_trysee'];
                }
                $popedom = $this->check_user_popedom($info['type_id'], ($pe==0 ? 3 : 5),$param,$flag,$info,$trysee);
            }
            else {
                $popedom =  $this->check_user_popedom($info['type_id'], 4,$param,$flag,$info);
            }

            if($pe==0 && $popedom['code']>1 && empty($popedom["trysee"])){
                $info['player_info']['flag'] = $flag;

                // 下载页面：清空下载列表
                if ($flag == 'down') {
                    $info['vod_down_list'] = [];
                }

                $this->assign('obj',$info);

                // 不再跳转确认页，直接进入播放页/下载页，由模板内的权限引导进行购买/充值
                $vod_popedom_locked = true;
            }
        }
        $this->assign('popedom',$popedom);

        if (!empty($vod_popedom_locked)) {
            $player_info = [
                'flag' => $flag,
                'encrypt' => 0,
                'trysee' => 0,
                'points' => intval($info['vod_points_'.$flag]),
                'link' => '',
                'link_next' => '',
                'link_pre' => '',
                'url' => '',
                'url_next' => '',
                'from' => '',
                'server' => '',
                'note' => '',
                'id' => $param['id'],
                'sid' => $param['sid'],
                'nid' => $param['nid'],
                'vod_data' => [
                    'vod_name'     => $info['vod_name'],
                    'vod_actor'    => $info['vod_actor'],
                    'vod_director' => $info['vod_director'],
                    'vod_class'    => $info['vod_class'],
                ],
            ];
            // 无权限时仍生成上/下集播放页链接，便于游客切换集数（各集仍受权限门控）
            if ($param['nid'] > 1) {
                $player_info['link_pre'] = $urlfun($info, ['sid' => $param['sid'], 'nid' => $param['nid'] - 1]);
            }
            $list_key = 'vod_' . $flag . '_list';
            if (!empty($info[$list_key][$param['sid']]['url_count'])
                && $param['nid'] < $info[$list_key][$param['sid']]['url_count']) {
                $player_info['link_next'] = $urlfun($info, ['sid' => $param['sid'], 'nid' => $param['nid'] + 1]);
            }
            $info['player_info'] = $player_info;
            $this->assign('obj',$info);
            $favPlay = mac_user_fav_state((int)($GLOBALS['user']['user_id'] ?? 0), 1, (int)($info['vod_id'] ?? 0));
            $this->assign('vod_play_fav_ulog_id', $favPlay['fav_ulog_id']);
            $this->assign('vod_play_is_fav', $favPlay['is_fav']);
            $this->assign('player_data','');
            $this->assign('player_js','');
            $this->assign('comment_mid', 1);
            $this->assign('comment_rid', $info['vod_id']);
            $this->label_comment();
            $__vodTagwall = mac_vod_play_tagwall_payload($info);
            $this->assign('vod_play_tagwall_enabled', $__vodTagwall['enabled']);
            $this->assign('vod_play_tagwall_json', $__vodTagwall['json']);
            return $info;
        }

        $player_info=[];
        $player_info['flag'] = $flag;
        $player_info['encrypt'] = intval($GLOBALS['config']['app']['encrypt']);
        $player_info['trysee'] = intval($trysee);
        $player_info['points'] = intval($info['vod_points_'.$flag]);
        $player_info['link'] = $urlfun($info,['sid'=>'{sid}','nid'=>'{nid}']);
        $player_info['link_next'] = '';
        $player_info['link_pre'] = '';
        $player_info['vod_data'] = [
            'vod_name'     => $info['vod_name'],
            'vod_actor'    => $info['vod_actor'],
            'vod_director' => $info['vod_director'],
            'vod_class'    => $info['vod_class'],
        ];
        if($param['nid']>1){
            $player_info['link_pre'] = $urlfun($info,['sid'=>$param['sid'],'nid'=>$param['nid']-1]);
        }
        if($param['nid'] < $info['vod_'.$flag.'_list'][$param['sid']]['url_count']){
            $player_info['link_next'] = $urlfun($info,['sid'=>$param['sid'],'nid'=>$param['nid']+1]);
        }
        $player_info['url'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['url'];
        $player_info['url_next'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']+1]['url'];

        if(substr($player_info['url'],0,6) == 'upload'){
            $player_info['url'] = MAC_PATH . $player_info['url'];
        }
        if(substr($player_info['url_next'],0,6) == 'upload'){
            $player_info['url_next'] = MAC_PATH . $player_info['url_next'];
        }

        $player_info['from'] = (string)$info[$listfun][$param['sid']]['from'];
        if((string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['from'] != $player_info['from']){
            $player_info['from'] = (string)$info[$listfun][$param['sid']]['urls'][$param['nid']]['from'];
        }
        $player_info['server'] = (string)$info[$listfun][$param['sid']]['server'];
        $player_info['note'] = (string)$info[$listfun][$param['sid']]['note'];

        if($GLOBALS['config']['app']['encrypt']=='1'){
            $player_info['url'] = mac_escape($player_info['url']);
            $player_info['url_next'] = mac_escape($player_info['url_next']);
        }
        elseif($GLOBALS['config']['app']['encrypt']=='2'){
            $player_info['url'] = base64_encode(mac_escape($player_info['url']));
            $player_info['url_next'] = base64_encode(mac_escape($player_info['url_next']));
        }
        $player_info['id'] = $param['id'];
        $player_info['sid'] = $param['sid'];
        $player_info['nid'] = $param['nid'];
        $info['player_info'] = $player_info;
        $this->assign('obj',$info);
        $seo_ai = model('SeoAiResult')->getByObject(1, intval($info['vod_id']));
        $this->assign('seo_ai', $seo_ai);
        $this->mergeDetailSeoIntoMaccms(1, $info, $seo_ai);
        $favPlay = mac_user_fav_state((int)($GLOBALS['user']['user_id'] ?? 0), 1, (int)($info['vod_id'] ?? 0));
        $this->assign('vod_play_fav_ulog_id', $favPlay['fav_ulog_id']);
        $this->assign('vod_play_is_fav', $favPlay['is_fav']);

        $pwd_key = '1-'.($flag=='play' ?'4':'5').'-'.$info['vod_id'];

        if( $pe==0 && $flag=='play' && ($popedom['trysee']>0 ) || ($info['vod_pwd_'.$flag]!='' && session($pwd_key)!='1') || ($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==4) ) {
            $id = $info['vod_id'];
            if($GLOBALS['config']['rewrite']['vod_id']==2){
                $id = mac_alphaID($info['vod_id'],false,$GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key']);
            }
            $dy_play = mac_url('index/vod/'.$flag.'er',['id'=>$id,'sid'=>$param['sid'],'nid'=>$param['nid']]);
            $this->assign('player_data','');
            $this->assign('player_js','<div class="MacPlayer" style="z-index:99999;width:100%;height:100%;margin:0px;padding:0px;"><iframe id="player_if" name="player_if" src="'.$dy_play.'" style="z-index:9;width:100%;height:100%;" border="0" marginWidth="0" frameSpacing="0" marginHeight="0" frameBorder="0" scrolling="no" allowfullscreen="allowfullscreen" mozallowfullscreen="mozallowfullscreen" msallowfullscreen="msallowfullscreen" oallowfullscreen="oallowfullscreen" webkitallowfullscreen="webkitallowfullscreen" ></iframe></div>');
        }
        else {
            $this->assign('player_data', '<script type="text/javascript">var player_aaaa=' . json_encode($player_info) . '</script>');
            $this->assign('player_js', '<script type="text/javascript" src="' . MAC_PATH . 'static/js/playerconfig.js?t='.$this->_tsp.'"></script><script type="text/javascript" src="' . MAC_PATH . 'static/js/player.js?t=a'.$this->_tsp.'"></script>');
        }
        $this->assign('comment_mid', 1);
        $this->assign('comment_rid', $info['vod_id']);
        $this->label_comment();
        $__vodTagwall = mac_vod_play_tagwall_payload($info);
        $this->assign('vod_play_tagwall_enabled', $__vodTagwall['enabled']);
        $this->assign('vod_play_tagwall_json', $__vodTagwall['json']);
        return $info;
    }

    /**
     * 用户组/积分阅读与播放权限（前台 index 与 api 模块共用）
     */
    protected function check_user_popedom($type_id, $popedom, $param = [], $flag = '', $info = [], $trysee = 0)
    {
        $user = $GLOBALS['user'];
        $group_ids = explode(',', $user['group_id']);
        $group_list = model('Group')->getCache();

        $res = false;
        $read_popedoms = [$popedom];
        foreach ($group_ids as $group_id) {
            if (!isset($group_list[$group_id])) {
                continue;
            }
            $group = $group_list[$group_id];
            if (strpos(',' . $group['group_type'], ',' . $type_id . ',') === false) {
                continue;
            }
            foreach ($read_popedoms as $p) {
                if (!empty($group['group_popedom'][$type_id][$p])) {
                    $res = true;
                    break 2;
                }
            }
        }

        $pre = $flag;
        $col = 'detail';
        if ($flag == 'play' || $flag == 'down') {
            $pre = 'vod';
            $col = $flag;
        } elseif ($flag == 'art_read') {
            $pre = 'art';
            $col = 'detail';
        } elseif ($flag == 'manga_play') {
            $pre = 'manga';
            $col = 'detail';
        }

        $points = 0;
        if (in_array($pre, ['art', 'manga'], true)) {
            $points = mac_content_read_points_amount($pre, $info);
        } elseif (in_array($pre, ['vod', 'actor', 'website'], true)) {
            $points = (int) ($info[$pre . '_points_' . $col] ?? 0);
            if ($GLOBALS['config']['user'][$pre . '_points_type'] == '1') {
                $points = (int) ($info[$pre . '_points'] ?? 0);
            }
        }

        if ($GLOBALS['config']['user']['status'] == 0) {
        } elseif (($popedom == 2 && in_array($pre, ['art', 'actor', 'website', 'manga'])) || ($popedom == 3 && in_array($flag, ['art_read', 'manga_play']))) {
            $has_permission = false;
            $has_trysee = false;
            $check_p = in_array($flag, ['art_read', 'manga_play']) ? [3] : [2];
            foreach ($group_ids as $group_id) {
                if (!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                foreach ($check_p as $p) {
                    if (!empty($group['group_popedom'][$type_id][$p])) {
                        $has_permission = true;
                        break;
                    }
                }
                if ($trysee > 0) {
                    $has_trysee = true;
                }
            }

            if ($res === false) {
                if ($has_trysee) {
                    return ['code' => 1, 'msg' => lang('controller/in_try_see'), 'trysee' => $trysee];
                }
                if (in_array($flag, ['art_read', 'manga_play'], true) && $points > 0) {
                    if ($user['user_id'] > 0) {
                        $mid = mac_get_mid($pre);
                        $where = [];
                        $where['ulog_mid'] = $mid;
                        $where['ulog_type'] = 1;
                        $where['ulog_rid'] = $param['id'];
                        $where['ulog_sid'] = ($pre == 'manga') ? ($param['sid'] ?? 0) : ($param['page'] ?? 0);
                        $where['ulog_nid'] = ($pre == 'manga') ? ($param['nid'] ?? 0) : 0;
                        $where['user_id'] = $user['user_id'];
                        $where['ulog_points'] = $points;
                        if ($GLOBALS['config']['user'][$pre . '_points_type'] == '1') {
                            $where['ulog_sid'] = 0;
                            $where['ulog_nid'] = 0;
                        }
                        $ulogRes = model('Ulog')->infoData($where);
                        if ($ulogRes['code'] == 1) {
                            return ['code' => 1, 'msg' => lang('controller/popedom_ok')];
                        }
                    }
                    return ['code' => 3003, 'msg' => lang('controller/pay_play_points', [$points]), 'points' => $points, 'confirm' => 1, 'trysee' => 0];
                }
                return ['code' => 3001, 'msg' => lang('controller/no_popedom'), 'trysee' => 0];
            }

            if (max($group_ids) < 3 && $points > 0) {
                $mid = mac_get_mid($pre);
                $where = [];
                $where['ulog_mid'] = $mid;
                $where['ulog_type'] = 1;
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = ($pre == 'manga') ? ($param['sid'] ?? 0) : ($param['page'] ?? 0);
                $where['ulog_nid'] = ($pre == 'manga') ? ($param['nid'] ?? 0) : 0;
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;
                if ($GLOBALS['config']['user'][$pre . '_points_type'] == '1') {
                    $where['ulog_sid'] = 0;
                    $where['ulog_nid'] = 0;
                }
                $res = model('Ulog')->infoData($where);

                if ($res['code'] > 1) {
                    return ['code' => 3003, 'msg' => lang('controller/pay_play_points', [$points]), 'points' => $points, 'confirm' => 1, 'trysee' => 0];
                }
            }
        } elseif ($popedom == 3) {
            $has_permission = false;
            foreach ($group_ids as $group_id) {
                if (!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                if (!empty($group['group_popedom'][$type_id][5])) {
                    $has_permission = true;
                    break;
                }
            }

            if ($res === false) {
                if ($has_permission && max($group_ids) < 3) {
                    return ['code' => 3002, 'msg' => lang('controller/in_try_see'), 'trysee' => $trysee];
                } else {
                    return ['code' => 3001, 'msg' => lang('controller/no_popedom'), 'trysee' => 0];
                }
            }
            if (max($group_ids) < 3 && $points > 0) {
                $where = [];
                $where['ulog_mid'] = 1;
                $where['ulog_type'] = $flag == 'play' ? 4 : 5;
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['sid'];
                $where['ulog_nid'] = $param['nid'];
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;
                if ($GLOBALS['config']['user']['vod_points_type'] == '1') {
                    $where['ulog_sid'] = 0;
                    $where['ulog_nid'] = 0;
                }
                $res_ulog = model('Ulog')->infoData($where);

                if ($res_ulog['code'] > 1) {
                    return ['code' => 3003, 'msg' => lang('controller/pay_play_points', [$points]), 'points' => $points, 'confirm' => 1, 'trysee' => 0];
                }
            }
        } else {
            if ($res === false) {
                return ['code' => 1001, 'msg' => lang('controller/no_popedom')];
            }
            if ($popedom == 4) {
                if (max($group_ids) == 1 && $points > 0) {
                    return ['code' => 4001, 'msg' => lang('controller/charge_data'), 'trysee' => 0];
                } elseif (max($group_ids) == 2 && $points > 0) {
                    $where = [];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag == 'play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if ($GLOBALS['config']['user']['vod_points_type'] == '1') {
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    if ($res['code'] > 1) {
                        return ['code' => 4003, 'msg' => lang('controller/pay_down_points', [$points]), 'points' => $points, 'confirm' => 1, 'trysee' => 0];
                    }
                }
            } elseif ($popedom == 5) {
                $has_permission = false;
                $has_trysee = false;
                foreach ($group_ids as $group_id) {
                    if (!isset($group_list[$group_id])) {
                        continue;
                    }
                    $group = $group_list[$group_id];
                    if (!empty($group['group_popedom'][$type_id][3])) {
                        $has_permission = true;
                    }
                    if (!empty($group['group_popedom'][$type_id][5])) {
                        $has_trysee = true;
                    }
                }

                if (!$has_permission && $has_trysee && max($group_ids) < 3) {
                    $where = [];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag == 'play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if ($GLOBALS['config']['user']['vod_points_type'] == '1') {
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    if ($points > 0 && $res['code'] == 1) {
                        return ['code' => 5001, 'msg' => lang('controller/popedom_ok')];
                    }

                    if ($user['user_id'] > 0) {
                        if ($points > intval($user['user_points'])) {
                            return ['code' => 5002, 'msg' => lang('controller/not_enough_points', [$points, $user['user_points']]), 'trysee' => $trysee];
                        } else {
                            return ['code' => 5001, 'msg' => lang('controller/try_see_end', [$points, $user['user_points']]), 'trysee' => $trysee];
                        }
                    } else {
                        if ($points > 0) {
                            return ['code' => 5002, 'msg' => lang('controller/not_enough_points', [$points, $user['user_points']]), 'trysee' => $trysee];
                        } else {
                            return ['code' => 5001, 'msg' => lang('controller/try_see_end', [$points, $user['user_points']]), 'trysee' => $trysee];
                        }
                    }
                }
            }
        }

        return ['code' => 1, 'msg' => lang('controller/popedom_ok')];
    }
}
