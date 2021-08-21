<?php
namespace app\index\controller;
use think\Controller;
use app\common\controller\All;

class Base extends All
{
    var $_group;
    var $_user;

    public function __construct()
    {
        parent::__construct();
        $this->check_site_status();
        $this->label_maccms();
        $this->check_browser_jump();
        $this->label_user();
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
        $group = $GLOBALS['user']['group'];

        $res = false;
        if(strpos(','.$group['group_type'],','.$type_id.',')!==false && !empty($group['group_popedom'][$type_id][$popedom])!==false){
            $res = true;
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

            if($res===false && (empty($group['group_popedom'][$type_id][2]) || $trysee==0)){
                return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
            }
            elseif($group['group_id']<3 && $points>0  ){
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
            if($res===false && (empty($group['group_popedom'][$type_id][5]) || $trysee==0)){
                return ['code'=>3001,'msg'=>lang('controller/no_popedom'),'trysee'=>0];
            }
            elseif($group['group_id']<3 && empty($group['group_popedom'][$type_id][3]) && !empty($group['group_popedom'][$type_id][5]) && $trysee>0){
                return ['code'=>3002,'msg'=>lang('controller/in_try_see'),'trysee'=>$trysee];
            }
            elseif($group['group_id']<3 && $points>0  ){
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
                    return ['code'=>3003,'msg'=>lang('controller/pay_play_points',[$points]),'points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        else{
            if($res===false){
                return ['code'=>1001,'msg'=>lang('controller/no_popedom')];
            }
            if($popedom == 4){
                if( $group['group_id'] ==1 && $points>0){
                    return ['code'=>4001,'msg'=>lang('controller/charge_data'),'trysee'=>0];
                }
                elseif($group['group_id'] ==2 && $points>0){
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
                if(empty($group['group_popedom'][$type_id][3]) && !empty($group['group_popedom'][$type_id][5])){
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

                    if(2 == 1) {

                    }
                    elseif($points>0 && $res['code'] == 1) {

                    }
                    elseif( $group['group_id'] <=2 && $points <= intval($user['user_points']) ){
                        return ['code'=>5001,'msg'=>lang('controller/try_see_end',[$points, $user['user_points']]),'trysee'=>$trysee];
                    }
                    elseif( $group['group_id'] <3 && $points > intval($user['user_points']) ){
                        return ['code'=>5002,'msg'=>lang('controller/not_enough_points',[$points,$user['user_points'] ]),'trysee'=>$trysee];
                    }
                }
            }
        }

        return ['code'=>1,'msg'=>lang('controller/popedom_ok')];
    }
}