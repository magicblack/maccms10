<?php
namespace app\index\controller;


class Ajax extends Base
{
    var $_param;
    
    public function __construct()
    {
        parent::__construct();

        $this->_param = mac_param_url();
    }

    public function index()
    {

    }

    //加载最多不超过20页数据，防止非法采集。每页条数可以是10,20,30
    public function data()
    {
        $mid = $this->_param['mid'];
        $limit = $this->_param['limit'];
        $page = $this->_param['page'];
        $type_id = $this->_param['tid'];
        if( !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        if( !in_array($limit,['10','20','30']) ) {
            $limit =10;
        }
        if($page < 1 || $page > 20){
            $page =1;
        }

        $pre = mac_get_mid_code($mid);
        $order= $pre.'_time desc';
        $where=[];
        $where[$pre.'_status'] = [ 'eq',1];
        if(!empty($type_id)) {
            if(in_array($mid, ['1', '2'])){
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type_id];
                if(!empty($type_info)) {
                    $ids = $type_info['type_pid'] == 0 ? $type_info['childids'] : $type_info['type_id'];
                    $where['type_id|type_id_1'] = ['in', $ids];
                }
            }
        }
        $field='*';
        $res = model($pre)->listData($where,$order,$page,$limit,0,$field);
        if($res['code']==1) {
            foreach ($res['list'] as $k => &$v) {
                unset($v[$pre.'_time_hits'],$v[$pre.'_time_make']);
                $v[$pre.'_time'] = date('Y-m-d H:i:s',$v[$pre.'_time']);
                $v[$pre.'_time_add'] = date('Y-m-d H:i:s',$v[$pre.'_time_add']);
                if($mid=='1'){
                    unset($v['vod_play_from'],$v['vod_play_server'],$v['vod_play_note'],$v['vod_play_url']);
                    unset($v['vod_down_from'],$v['vod_down_server'],$v['vod_down_note'],$v['vod_down_url']);

                    $v['detail_link'] = mac_url_vod_detail($v);
                }
                elseif($mid=='2'){
                    $v['detail_link'] = mac_url_art_detail($v);
                }
                elseif($mid=='3'){
                    $v['detail_link'] = mac_url_topic_detail($v);
                }
                elseif($mid=='8'){
                    $v['detail_link'] = mac_url_actor_detail($v);
                }
                elseif($mid=='9'){
                    $v['detail_link'] = mac_url_role_detail($v);
                }
                elseif($mid=='11'){
                    $v['detail_link'] = mac_url_website_detail($v);
                }
                $v[$pre.'_pic'] = mac_url_img($v[$pre.'_pic']);
                $v[$pre.'_pic_thumb'] = mac_url_img($v[$pre.'_pic_thumb']);
                $v[$pre.'_pic_slide'] = mac_url_img($v[$pre.'_pic_slide']);
            }
        }
        return json($res);
    }

    public function suggest()
    {
        if($GLOBALS['config']['app']['search'] !='1'){
            return json(['code'=>999,'msg'=>lang('suggest_close')]);
        }

        $mid = $this->_param['mid'];
        $wd = $this->_param['wd'];
        $limit = intval($this->_param['limit']);

        if( $wd=='' || !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $mids = [1=>'vod',2=>'art',3=>'topic',8=>'actor',9=>'role',11=>'website'];
        $pre = $mids[$mid];
        if($limit<1){
            $limit = 20;
        }
        $where = [];
        $where[$pre.'_name|'.$pre.'_en'] = ['like','%'.$wd.'%'];
        $order = $pre.'_id desc';
        $field = $pre.'_id as id,'.$pre.'_name as name,'.$pre.'_en as en,'.$pre.'_pic as pic';

        $url = mac_url_search(['wd'=>'mac_wd'],$pre);

        $res = model($pre)->listData($where,$order,1,$limit,0,$field);
        if($res['code']==1) {
            foreach ($res['list'] as $k => $v) {
                $res['list'][$k]['pic'] = mac_url_img($v['pic']);
            }
        }
        $res['url'] = $url;
        return json($res);
    }

    public function desktop()
    {
        $name = $this->_param['name'];
        $url = $this->_param['url'];

        $config = config('maccms.site');
        if(empty($name)){
            $name = $config['site_name'];
            $url = "http://".$config['site_url'];
        }
        if(substr($url,0,4)!="http"){
            $url = "http://".$url;
        }
        $Shortcut = "[InternetShortcut]
        URL=".$url."
        IDList=
        IconIndex=1
        [{000214A0-0000-0000-C000-000000000046}]
        Prop3=19,2";
        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")){
            header("Content-Disposition: attachment; filename=". urlencode($name) .".url;");
        }
        else{
            header("Content-Disposition: attachment; filename=". $name .".url;");
        }
        echo $Shortcut;
    }

    public function hits()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $type = $this->_param['type'];
        if(empty($id) ||  !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_hits,'.$pre.'_hits_day,'.$pre.'_hits_week,'.$pre.'_hits_month,'.$pre.'_time_hits';
        $model = model($pre);

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];

        if($type == 'update'){
            //初始化值
            $update[$pre.'_hits'] = $info[$pre.'_hits'];
            $update[$pre.'_hits_day'] = $info[$pre.'_hits_day'];
            $update[$pre.'_hits_week'] = $info[$pre.'_hits_week'];
            $update[$pre.'_hits_month'] = $info[$pre.'_hits_month'];
            $new = getdate();
            $old = getdate($info[$pre.'_time_hits']);
            //月
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon']){
                $update[$pre.'_hits_month'] ++;
            }else{
                $update[$pre.'_hits_month'] = 1;
            }
            //周
            $weekStart = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"] * 86400);
            $weekEnd = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6 - $new["wday"]) * 86400);
            if($info[$pre.'_time_hits'] >= $weekStart && $info[$pre.'_time_hits'] <= $weekEnd){
                $update[$pre.'_hits_week'] ++;
            }else{
                $update[$pre.'_hits_week'] = 1;
            }
            //日
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon'] && $new['mday'] == $old['mday']){
                $update[$pre.'_hits_day'] ++;
            }else{
                $update[$pre.'_hits_day'] = 1;
            }
            //更新数据库
            $update[$pre.'_hits'] = $update[$pre.'_hits']+1;
            $update[$pre.'_time_hits'] = time();
            $model->where($where)->update($update);

            $data['hits'] = $update[$pre.'_hits'];
            $data['hits_day'] = $update[$pre.'_hits_day'];
            $data['hits_week'] = $update[$pre.'_hits_week'];
            $data['hits_month'] = $update[$pre.'_hits_month'];
        }
        else{
            $data['hits'] = $info[$pre.'_hits'];
            $data['hits_day'] = $info[$pre.'_hits_day'];
            $data['hits_week'] = $info[$pre.'_hits_week'];
            $data['hits_month'] = $info[$pre.'_hits_month'];
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function referer()
    {
        $url = $this->_param['url'];
        $type = $this->_param['type'];
        $domain = $this->_param['domain'];

        if(empty($url)) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        if(strpos($_SERVER["HTTP_REFERER"],$_SERVER['HTTP_HOST'])===false){
            return json(['code'=>1002,'msg'=>lang('param_err')]);
        }

        if(strpos($url,$domain)===false){
            return json(['code'=>1003,'msg'=>lang('param_err')]);
        }

        $pre = 'website';
        $where=[];
        $where[$pre.'_jumpurl'] =  ['like', ['http://'.$domain.'%','https://'.$domain.'%'],'OR'];
        $model = model($pre);
        $field = $pre.'_referer,'.$pre.'_referer_day,'.$pre.'_referer_week,'.$pre.'_referer_month,'.$pre.'_time_referer';
        $res = $model->infoData($where,$field);
        if($res['code']>1){
            return json($res);
        }
        $info = $res['info'];
        $id = $info[$pre.'_id'];

        //来路访问记录验证
        $res = model('Website')->visit($this->_param);
        if($res['code']>1){
            return json($res);
        }

        if($type == 'update'){
            //初始化值
            $update[$pre.'_referer'] = $info[$pre.'_referer'];
            $update[$pre.'_referer_day'] = $info[$pre.'_referer_day'];
            $update[$pre.'_referer_week'] = $info[$pre.'_referer_week'];
            $update[$pre.'_referer_month'] = $info[$pre.'_referer_month'];
            $new = getdate();
            $old = getdate($info[$pre.'_time_referer']);
            //月
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon']){
                $update[$pre.'_referer_month'] ++;
            }else{
                $update[$pre.'_referer_month'] = 1;
            }
            //周
            $weekStart = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"] * 86400);
            $weekEnd = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6 - $new["wday"]) * 86400);
            if($info[$pre.'_time_referer'] >= $weekStart && $info[$pre.'_time_referer'] <= $weekEnd){
                $update[$pre.'_referer_week'] ++;
            }else{
                $update[$pre.'_referer_week'] = 1;
            }
            //日
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon'] && $new['mday'] == $old['mday']){
                $update[$pre.'_referer_day'] ++;
            }else{
                $update[$pre.'_referer_day'] = 1;
            }
            //更新数据库
            $update[$pre.'_referer'] = $update[$pre.'_referer']+1;
            $update[$pre.'_time_referer'] = time();
            $model->where($where)->update($update);

            $data['referer'] = $update[$pre.'_referer'];
            $data['referer_day'] = $update[$pre.'_referer_day'];
            $data['referer_week'] = $update[$pre.'_referer_week'];
            $data['referer_month'] = $update[$pre.'_referer_month'];
        }
        else{
            $data['referer'] = $info[$pre.'_referer'];
            $data['referer_day'] = $info[$pre.'_referer_day'];
            $data['referer_week'] = $info[$pre.'_referer_week'];
            $data['referer_month'] = $info[$pre.'_referer_month'];
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function digg()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $type = $this->_param['type'];

        if(empty($id) ||  !in_array($mid,['1','2','3','4','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_up,'.$pre.'_down';
        $model = model($pre);

        if($type) {
            $cookie = $pre . '-digg-' . $id;
            if(!empty(cookie($cookie))){
                return json(['code'=>1002,'msg'=>lang('index/haved')]);
            }
            if ($type == 'up') {
                $model->where($where)->setInc($pre.'_up');
                cookie($cookie, 't', 30);
            } elseif ($type == 'down') {
                $model->where($where)->setInc($pre.'_down');
                cookie($cookie, 't', 30);
            }
        }

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];
        if ($info) {
            $data['up'] = $info[$pre.'_up'];
            $data['down'] = $info[$pre.'_down'];
        }
        else{
            $data['up'] = 0;
            $data['down'] = 0;
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function score()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $score = $this->_param['score'];

        if(empty($id) ||  !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_score,'.$pre.'_score_num,'.$pre.'_score_all';
        $model = model($pre);

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];

        if ($info) {
            if($score){
                $cookie = $pre.'-score-'.$id;
                if(!empty(cookie($cookie))){
                    return json(['code'=>1002,'msg'=>lang('index/haved')]);
                }
                $update=[];
                $update[$pre.'_score_num'] = $info[$pre.'_score_num']+1;
                $update[$pre.'_score_all'] = $info[$pre.'_score_all']+$score;
                $update[$pre.'_score'] = number_format( $update[$pre.'_score_all'] / $update[$pre.'_score_num'] ,1,'.','');
                $model->where($where)->update($update);

                $data['score'] = $update[$pre.'_score'];
                $data['score_num'] = $update[$pre.'_score_num'];
                $data['score_all'] = $update[$pre.'_score_all'];

                cookie($cookie,'t',30);
            }
            else{
                $data['score'] = $info[$pre.'_score'];
                $data['score_num'] = $info[$pre.'_score_num'];
                $data['score_all'] = $info[$pre.'_score_all'];
            }
        }else{
            $data['score'] = 0.0;
            $data['score_num'] = 0;
            $data['score_all'] = 0;
        }
        return json(['code'=>1,'msg'=>lang('score_ok'),'data'=>$data]);
    }

    public function pwd()
    {
        $mid = $this->_param['mid'];
        $id = $this->_param['id'];
        $type = $this->_param['type'];
        $pwd = input('param.pwd');

        if( empty($id) || empty($pwd) || !in_array($mid,['1','2']) || !in_array($type,['1','4','5'])){
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $key = $mid.'-'.$type.'-'.$id;
        if(session($key)=='1'){
            return json(['code'=>1002,'msg'=>lang('index/pwd_repeat')]);
        }

        if ( mac_get_time_span("last_pwd") < 5){
            return json(['code'=>1003,'msg'=>lang('index/pwd_frequently')]);
        }


        if($mid=='1'){
            $where=[];
            $where['vod_id'] = ['eq',$id];
            $info = model('Vod')->infoData($where);
            if($info['code'] >1){
                return json(['code'=>1011,'msg'=>$info['msg']]);
            }
            if($type=='1'){
                if($info['info']['vod_pwd'] != $pwd){
                    return json(['code'=>1012,'msg'=>lang('pass_err')]);
                }
            }
            elseif($type=='4'){
                if($info['info']['vod_pwd_play'] != $pwd){
                    return json(['code'=>1013,'msg'=>lang('pass_err')]);
                }
            }
            elseif($type=='5'){
                if($info['info']['vod_pwd_down'] != $pwd){
                    return json(['code'=>1014,'msg'=>lang('pass_err')]);
                }
            }
        }
        else{
            $where=[];
            $where['art_id'] = ['eq',$id];
            $info = model('Art')->infoData($where);
            if($info['code'] >1){
                return json(['code'=>1021,'msg'=>$info['msg']]);
            }
            if($info['info']['art_pwd'] != $pwd){
                return json(['code'=>1022,'msg'=>lang('pass_err')]);
            }
        }

        session($key,'1');
        return json(['code'=>1,'msg'=>'ok']);
    }

    public function verify_check()
    {
        $param = input();
        if(!in_array($param['type'],['search','show'])){
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        if (!captcha_check($param['verify'])){
            return ['code' => 1002, 'msg' => lang('verify_err')];
        }
        session($param['type'].'_verify','1');
        return json(['code'=>1,'msg'=>lang('ok')]);
    }
}