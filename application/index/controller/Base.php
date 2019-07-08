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
        $this->label_user();
    }

    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        echo  '<script>setTimeout(function (){location.href="'.MAC_PATH.'";},'.(2000).');</script>';
        $msg = '页面不存在';
        abort(404,$msg);
        exit;
    }

    protected function check_search($param)
    {
        if($GLOBALS['config']['app']['search'] !='1'){
            echo $this->error('搜索功能关闭中');
            exit;
        }

        if ( $param['page']==1 && mac_get_time_span("last_searchtime") < $GLOBALS['config']['app']['search_timespan']){
            echo $this->error("请不要频繁操作，搜索时间间隔为".$GLOBALS['config']['app']['search_timespan']."秒");
            exit;
        }

    }

    protected function check_site_status()
    {
        //站点关闭中
        if ($GLOBALS['config']['site']['site_status'] == 0) {
            $this->assign('close_tip',$GLOBALS['config']['site']['site_close_tip']);
            echo $this->fetch('public/close');
            die;
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

        if(in_array($flag,['art','play','down'])){
            if($flag=='art') {
                $points = $info['art_points_detail'];
                if($GLOBALS['config']['user']['art_points_type']=='1'){
                    $points = $info['art_points'];
                }
            }
            else{
                $points = $info['vod_points_'.$flag];
                if($GLOBALS['config']['user']['vod_points_type']=='1'){
                    $points = $info['vod_points'];
                }
            }
        }


        if($GLOBALS['config']['user']['status']==0){

        }
        elseif($popedom==2 && $flag=='art'){
            if($res===false && (empty($group['group_popedom'][$type_id][2]) || $trysee==0)){
                return ['code'=>3001,'msg'=>'您没有权限访问此数据，请升级会员','trysee'=>0];
            }
            elseif($group['group_id']<3 && $points>0  ){
                $where=[];
                $where['ulog_mid'] = 2;
                $where['ulog_type'] = 1;
                $where['ulog_rid'] = $param['id'];
                $where['ulog_sid'] = $param['page'];
                $where['ulog_nid'] = 0;
                $where['user_id'] = $user['user_id'];
                $where['ulog_points'] = $points;
                if($GLOBALS['config']['user']['art_points_type']=='1'){
                    $where['ulog_sid'] = 0;
                }
                $res = model('Ulog')->infoData($where);

                if($res['code'] > 1) {
                    return ['code'=>3003,'msg'=>'观看此数据，需要支付【'.$points.'】积分，确认支付吗？','points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        elseif($popedom==3){
            if($res===false && (empty($group['group_popedom'][$type_id][5]) || $trysee==0)){
                return ['code'=>3001,'msg'=>'您没有权限访问此数据，请升级会员','trysee'=>0];
            }
            elseif($group['group_id']<3 && empty($group['group_popedom'][$type_id][3]) && !empty($group['group_popedom'][$type_id][5]) && $trysee>0){
                return ['code'=>3002,'msg'=>'进入试看模式','trysee'=>$trysee];
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
                    return ['code'=>3003,'msg'=>'观看此数据，需要支付【'.$points.'】积分，确认支付吗？','points'=>$points,'confirm'=>1,'trysee'=>0];
                }
            }
        }
        else{
            if($res===false){
                return ['code'=>1001,'msg'=>'您没有权限访问此页面，请升级会员组'];
            }
            if($popedom == 4){
                if( $group['group_id'] ==1 && $points>0){
                    return ['code'=>4001,'msg'=>'此页面为收费数据，请先登录后访问！','trysee'=>0];
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
                        return ['code'=>4003,'msg'=>'下载此数据，需要支付【'.$points.'】积分，确认支付吗？','points'=>$points,'confirm'=>1,'trysee'=>0];
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

                    if($res['code'] == 1) {

                    }
                    elseif( $group['group_id'] <=2 && $points <= intval($user['user_points']) ){
                        return ['code'=>5001,'msg'=>'试看结束,是否支付[' . $points . ']积分观看完整数据？您还剩下[' . $user['user_points'] . ']积分，请先充值！','trysee'=>$trysee];
                    }
                    elseif( $group['group_id'] <3 && $points > intval($user['user_points']) ){
                        return ['code'=>5002,'msg'=>'对不起,观看此页面数据需要[' . $points . ']积分，您还剩下[' . $user['user_points'] . ']积分，请先充值！','trysee'=>$trysee];
                    }
                }
            }
        }

        return ['code'=>1,'msg'=>'权限验证通过'];
    }
}