<?php
namespace app\common\controller;
use think\Controller;
use think\Cache;

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
        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['cache_page'] ==1  && $GLOBALS['config']['app']['cache_time_page'] ) {
            $cach_name = MAC_MOB . '_'. $GLOBALS['config']['app']['cache_flag']. '_' .$tpl .'_'. http_build_query(mac_param_url());
            $res = Cache::get($cach_name);
            if ($res) {
                if($type=='json'){
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
            $cach_name = MAC_MOB . '_' . $GLOBALS['config']['app']['cache_flag']. '_' . $tpl .'_'. http_build_query(mac_param_url());
            $res = Cache::set($cach_name,$html,$GLOBALS['config']['app']['cache_time_page']);
        }
        return $html;
    }

    protected function label_maccms()
    {
        $maccms = $GLOBALS['config']['site'];
        $maccms['path'] = MAC_PATH;
        $maccms['path_tpl'] = MAC_PATH_TEMPLATE;
        $maccms['path_ads'] = MAC_PATH_ADS;
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
    }

    protected function label_user()
    {
        if(ENTRANCE != 'index'){
            return;
        }
        $user_id = intval(cookie('user_id'));
        $user_name = cookie('user_name');
        $user_check = cookie('user_check');

        $user = ['user_id'=>0,'user_name'=>'游客','user_portrait'=>'static/images/touxiang.png','group_id'=>1,'points'=>0];
        if(!empty($user_id) && !empty($user_name) && !empty($user_check)){
            $res = model('User')->checkLogin();
            if($res['code'] == 1){
                $user = $res['info'];
            }
            else{
                cookie('user_id','0');
                cookie('user_name','游客');
                cookie('user_check','');
            }
        }
        else{
            $group_list = model('Group')->getCache();
            $user['group'] = $group_list[1];
        }
        $GLOBALS['user'] = $user;
        $this->assign('user',$user);
    }

    protected function label_comment()
    {
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);
    }

    protected function label_type($view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $info = mac_label_type($param);

        $this->assign('obj',$info);
        if(empty($info)){
            return $this->error('获取分类失败，请选择其它分类！');
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
                return $this->error($res['msg']);
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
        $comment = config('maccms.comment');
        $this->assign('comment',$comment);
        return $info;
    }


    protected function label_role($total='')
    {
        $param = mac_param_url();
        $this->assign('param',$param);
    }

    protected function label_role_detail($info=[])
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_role_detail($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            $info = $res['info'];
        }
        $this->assign('obj',$info);
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
                return $this->error($res['msg']);
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
                return $this->error($res['msg']);
            }
            $info = $res['info'];
        }
        $this->assign('obj',$info);

        $comment = config('maccms.comment');
        $this->assign('comment',$comment);

        return $info;
    }

    protected function label_art_detail($info=[],$view=0)
    {
        $param = mac_param_url();
        $this->assign('param',$param);

        if(empty($info)) {
            $res = mac_label_art_detail($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            $info = $res['info'];
        }
        if(empty($info['art_tpl'])){
            $info['art_tpl'] = $info['type']['type_tpl_detail'];
        }

        if($view <2) {
            $popedom = $this->check_user_popedom($info['type_id'], 2,$param,'art',$info);
            $this->assign('popedom',$popedom);

            if($popedom['code']>1){
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    echo $this->fetch('art/confirm');
                    exit;
                }

                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $this->assign('obj',$info);

        $url = mac_url_art_detail($info,['page'=>'PAGELINK']);

        $__PAGING__ = mac_page_param($info['art_page_total'],1,$param['page'],$url);
        $this->assign('__PAGING__',$__PAGING__);

        $this->label_comment();

        return $info;
    }

    protected function label_vod_detail($info=[],$view=0)
    {
        $param = mac_param_url();

        $this->assign('param',$param);
        if(empty($info)) {
            $res = mac_label_vod_detail($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
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
                return $this->error($res['msg']);
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
                return $this->error($res['msg']);
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
            $this->assign('popedom',$popedom);


            if($pe==0 && $popedom['code']>1 && empty($popedom["trysee"])){
                $info['player_info']['flag'] = $flag;
                $this->assign('obj',$info);

                if($popedom['confirm']==1){
                    $this->assign('flag',$flag);
                    echo $this->fetch('vod/confirm');
                    exit;
                }
                echo $this->error($popedom['msg'], mac_url('user/index') );
                exit;
            }
        }

        $player_info=[];
        $player_info['flag'] = $flag;
        $player_info['encrypt'] = intval($GLOBALS['config']['app']['encrypt']);
        $player_info['trysee'] = intval($trysee);
        $player_info['points'] = intval($info['vod_points_'.$flag]);
        $player_info['link'] = $urlfun($info,['sid'=>'{sid}','nid'=>'{nid}']);
        $player_info['link_next'] = '';
        $player_info['link_pre'] = '';
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

        $info['player_info'] = $player_info;
        $this->assign('obj',$info);

        $pwd_key = '1-'.($flag=='play' ?'4':'5').'-'.$info['vod_id'];

        if( $pe==0 && $flag=='play' && ($popedom['trysee']>0 ) || ($info['vod_pwd_'.$flag]!='' && session($pwd_key)!='1') || ($info['vod_copyright']==1 && !empty($info['vod_jumpurl']) && $GLOBALS['config']['app']['copyright_status']==4) ) {
            $dy_play = mac_url('index/vod/'.$flag.'er',['id'=>$info['vod_id'],'sid'=>$param['sid'],'nid'=>$param['nid']]);
            $this->assign('player_data','');
            $this->assign('player_js','<div class="MacPlayer" style="z-index:99999;width:100%;height:100%;margin:0px;padding:0px;"><iframe id="player_if" name="player_if" src="'.$dy_play.'" style="z-index:9;width:100%;height:100%;" border="0" marginWidth="0" frameSpacing="0" marginHeight="0" frameBorder="0" scrolling="no" allowfullscreen="allowfullscreen" mozallowfullscreen="mozallowfullscreen" msallowfullscreen="msallowfullscreen" oallowfullscreen="oallowfullscreen" webkitallowfullscreen="webkitallowfullscreen" ></iframe></div>');
        }
        else {
            $this->assign('player_data', '<script type="text/javascript">var player_data=' . json_encode($player_info) . '</script>');
            $this->assign('player_js', '<script type="text/javascript" src="' . MAC_PATH . 'static/js/playerconfig.js?t='.$this->_tsp.'"></script><script type="text/javascript" src="' . MAC_PATH . 'static/js/player.js?t='.$this->_tsp.'"></script>');
        }
        $this->label_comment();
        return $info;
    }
}