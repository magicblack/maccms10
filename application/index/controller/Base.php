<?php
namespace app\index\controller;
use think\Controller;
use app\common\controller\All;
use ip_limit\IpLocationQuery;
class Base extends All
{
    var $_group;
    var $_user;

    public function __construct()
    {
        parent::__construct();
        
        $this->check_ip_limit();
        $this->check_site_status();
        $this->label_maccms();
        $this->check_browser_jump();
        $this->label_user();
    }

    protected function check_ip_limit()
    {
       
        // 获取IP限制配置
        $mainland_ip_limit = $GLOBALS['config']['site']['mainland_ip_limit'] ?? "0";

        // 如果为0，不限制，直接通过
        if ($mainland_ip_limit == "0") {
            return;
        }
        
        // 获取用户真实IP
        $user_ip = mac_get_client_ip();
        try {
            $ipQuery = new IpLocationQuery();
            $country_code = $ipQuery->queryProvince($user_ip);
            // 根据配置进行限制
            if ($mainland_ip_limit == "1") {
                // 只允许中国大陆IP
                if ($country_code === "") {
                    echo $this->fetch('public/close');
                    die;
                }
            } elseif ($mainland_ip_limit == "2") {
                // 不允许中国大陆IP
                if ($country_code !== "") {
                    echo $this->fetch('public/close');
                    die;
                }
            }
            
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // 局域网IP或无效IP，直接通过
            return;
        } catch (\Exception $e) {
            // 其他异常
            return;
        }
    }

    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        echo  '<script>setTimeout(function (){location.href="'.MAC_PATH.'";},'.(2000).');</script>';
        $msg = lang('page_not_found');
        abort(404,$msg);
        exit;
    }

    protected function check_show($aj=0)
    {
        if($GLOBALS['config']['app']['show'] ==0){
            echo $this->error(lang('show_close'));
            exit;
        }
        if($GLOBALS['config']['app']['show_verify'] ==1 && $aj==0){
            if(empty(session('show_verify'))){
                mac_no_cahche();
                $this->assign('type','show');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    protected function check_ajax()
    {
        if($GLOBALS['config']['app']['ajax_page'] ==0){
            echo $this->error(lang('ajax_close'));
            exit;
        }
    }

    protected function check_search($param,$aj=0)
    {
        if($GLOBALS['config']['app']['search'] ==0){
            echo $this->error(lang('search_close'));
            exit;
        }
        if($param['page']==1 && mac_get_time_span("last_searchtime") < $GLOBALS['config']['app']['search_timespan']){
            echo $this->error(lang('search_frequently')."".$GLOBALS['config']['app']['search_timespan']."".lang('seconds'));
            exit;
        }
        if($GLOBALS['config']['app']['search_verify'] ==1 && $aj ==0){
            if(empty(session('search_verify'))){
                mac_no_cahche();
                $this->assign('type','search');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    protected function check_site_status()
    {
        if ($GLOBALS['config']['site']['site_status'] == 0) {
            $this->assign('close_tip',$GLOBALS['config']['site']['site_close_tip']);
            echo $this->fetch('public/close');
            die;
        }
    }

    protected function check_browser_jump()
    {
        if (ENTRANCE=='index' && $GLOBALS['config']['app']['browser_junmp'] == 1) {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            if(strpos($agent, 'QQ/')||strpos($agent, 'MicroMessenger')!==false){
                echo $this->fetch('public/browser');
                die;
            }
        }
    }
    protected function check_user_popedom($type_id,$popedom,$param=[],$flag='',$info=[],$trysee=0)
    {
        $user = $GLOBALS['user'];
        $group_ids = explode(',', $user['group_id']);
        $group_list = model('Group')->getCache();
        
        $res = false;
        foreach($group_ids as $group_id) {
            if(!isset($group_list[$group_id])) {
                continue;
            }
            $group = $group_list[$group_id];
            if(strpos(','.$group['group_type'],','.$type_id.',')!==false && !empty($group['group_popedom'][$type_id][$popedom])!==false){
                $res = true;
                break;
            }
        }
        
        $pre = $flag;
        $col = 'detail';
        if($flag=='play' || $flag=='down'){
            $pre = 'vod';
            $col = $flag;
        }

        if(in_array($pre,['art','vod','actor','website'])){
            $points = $info[$pre.'_points_'.$col];
            if($GLOBALS['config']['user'][$pre.'_points_type']=='1'){
                $points = $info[$pre.'_points'];
            }
        }

        if($GLOBALS['config']['user']['status']==0){

        }
        elseif($popedom==2 && in_array($pre,['art','actor','website'])){
            $has_permission = false;
            $has_trysee = false;
            foreach($group_ids as $group_id) {
                if(!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                if(!empty($group['group_popedom'][$type_id][2])) {
                    $has_permission = true;
                }
                if($trysee > 0) {
                    $has_trysee = true;
                }
            }

            if($res===false){
                if($has_trysee){
                    return ['code'=>1,'msg'=>lang('controller/in_try_see'),'trysee'=>$trysee];
                }
                return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
            }
            
            if(max($group_ids)<3 && $points>0){
                $mid = mac_get_mid($pre);
                $where=[];
                $where['ulog_mid'] = $mid;
                $where['ulog_type'] = 1;
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['page'];
                $where['ulog_nid'] = 0;
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;
                if($GLOBALS['config']['user'][$pre.'_points_type']=='1'){
                    $where['ulog_sid'] = 0;
                }
                $res = model('Ulog')->infoData($where);

                if($res['code'] > 1) {
                    return ['code'=>3003,'msg'=>lang('controller/pay_play_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        elseif($popedom==3){
            $has_permission = false;
            foreach($group_ids as $group_id) {
                if(!isset($group_list[$group_id])) {
                    continue;
                }
                $group = $group_list[$group_id];
                if(!empty($group['group_popedom'][$type_id][5])) {
                    $has_permission = true;
                    break;
                }
            }

            if ($res === false) {
                if ($has_permission && max($group_ids) < 3) {
                    return ['code'=>3002,'msg'=>lang('controller/in_try_see'),'trysee'=>$trysee];
                }
                else {
                    return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
                }
            }
            if(max($group_ids)<3 && $points>0){
                $where=[];
                $where['ulog_mid'] = 1;
                $where['ulog_type'] = $flag=='play' ? 4 : 5;
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['sid'];
                $where['ulog_nid'] = $param['nid'];
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;
                if($GLOBALS['config']['user']['vod_points_type']=='1'){
                    $where['ulog_sid'] = 0;
                    $where['ulog_nid'] = 0;
                }
                $res_ulog = model('Ulog')->infoData($where);

                if($res_ulog['code'] > 1) {
                    return ['code'=>3003,'msg'=>lang('controller/pay_play_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        else{
            if($res===false){
                return ['code'=>1001,'msg'=>lang('controller/no_popedom')];
            }
            if($popedom == 4){
                if(max($group_ids)==1 && $points>0){
                    return ['code'=>4001,'msg'=>lang('controller/charge_data'),'trysee'=>0];
                }
                elseif(max($group_ids)==2 && $points>0){
                    $where=[];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag=='play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if($GLOBALS['config']['user']['vod_points_type']=='1'){
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    if($res['code'] > 1) {
                        return ['code'=>4003,'msg'=>lang('controller/pay_down_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                    }
                }
            }
            elseif($popedom==5){
                $has_permission = false;
                $has_trysee = false;
                foreach($group_ids as $group_id) {
                    if(!isset($group_list[$group_id])) {
                        continue;
                    }
                    $group = $group_list[$group_id];
                    if(!empty($group['group_popedom'][$type_id][3])) {
                        $has_permission = true;
                    }
                    if(!empty($group['group_popedom'][$type_id][5])) {
                        $has_trysee = true;
                    }
                }

                if(!$has_permission && $has_trysee && max($group_ids) < 3){
                    $where=[];
                    $where['ulog_mid'] = 1;
                    $where['ulog_type'] = $flag=='play' ? 4 : 5;
                    $where['ulog_rid'] = $param['id'];
                    $where['ulog_sid'] = $param['sid'];
                    $where['ulog_nid'] = $param['nid'];
                    $where['user_id'] = $user['user_id'];
                    $where['ulog_points'] = $points;
                    if($GLOBALS['config']['user']['vod_points_type']=='1'){
                        $where['ulog_sid'] = 0;
                        $where['ulog_nid'] = 0;
                    }
                    $res = model('Ulog')->infoData($where);

                    if($points>0 && $res['code'] == 1) {
                        return ['code'=>5001,'msg'=>lang('controller/popedom_ok')];
                    }

                    if ($user['user_id'] > 0) {
                        if ($points > intval($user['user_points'])) {
                            return ['code'=>5002,'msg'=>lang('controller/not_enough_points',[$points,$user['user_points'] ]),'trysee'=>$trysee];
                        }
                        else {
                            return ['code'=>5001,'msg'=>lang('controller/try_see_end',[$points, $user['user_points']]),'trysee'=>$trysee];
                        }
                    }
                    else {
                        if ($points > 0) {
                            return ['code'=>5002,'msg'=>lang('controller/not_enough_points',[$points,$user['user_points'] ]),'trysee'=>$trysee];
                        }
                        else {
                            return ['code'=>5001,'msg'=>lang('controller/try_see_end',[$points, $user['user_points']]),'trysee'=>$trysee];
                        }
                    }
                }
            }
        }

        return ['code'=>1,'msg'=>lang('controller/popedom_ok')];
    }
}