<?php
namespace app\admin\controller;

class Urlsend extends Base
{
    var $_lastid='';

    public function __construct()
    {
        parent::__construct();
        $this->_param = input();
    }

    public function index()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['urlsend'] = $config['urlsend'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $urlsend_config = $GLOBALS['config']['urlsend'];
        $this->assign('config',$urlsend_config);

        $extends = mac_extends_list('urlsend');
        $this->assign('extends',$extends);


        $this->assign('title',lang('admin/urlsend/title'));
        return $this->fetch('admin@urlsend/index');
    }

    public function data()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        $list = [];
        $mid = $this->_param['mid'];
        $this->_param['page'] = intval($this->_param['page']) <1 ? 1 : $this->_param['page'];
        $this->_param['limit'] = intval($this->_param['limit']) <1 ? 50 : $this->_param['limit'];
        $ids = $this->_param['ids'];
        $ac2 = $this->_param['ac2'];
        $col_time = 'time';
        if($this->_param['range'] == '1'){
            $col_time = 'time_add';
        }
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $col = '';
        switch($mid)
        {
            case 1:
                $where['vod_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['vod_'.$col_time] = ['gt',$today];
                }
                if(!empty($ids)){
                    $where['vod_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['vod_id'] = ['gt', $data];
                }

                $col = 'vod';
                $order = 'vod_id asc';
                $fun = 'mac_url_vod_detail';
                $res = model('Vod')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
            case 2:
                $where['art_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['art_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['art_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['art_id'] = ['gt', $data];
                }

                $col = 'art';
                $order = 'art_id asc';
                $fun = 'mac_url_art_detail';
                $res = model('Art')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
            case 3:
                $where['topic_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['topic_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['topic_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['topic_id'] = ['gt', $data];
                }

                $col = 'topic';
                $order = 'topic_id asc';
                $fun = 'mac_url_topic_detail';
                $res = model('Topic')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
            case 8:
                $where['actor_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['actor_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['actor_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['actor_id'] = ['gt', $data];
                }
                $col = 'actor';
                $order = 'actor_id asc';
                $fun = 'mac_url_actor_detail';
                $res = model('Actor')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
            case 9:
                $where['role_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['role_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['role_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['role_id'] = ['gt', $data];
                }
                $col = 'role';
                $order = 'role_id asc';
                $fun = 'mac_url_role_detail';
                $res = model('Role')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
            case 11:
                $where['website_status'] = ['eq',1];

                if($ac2=='today'){
                    $where['website_'.$col_time] = ['gt',$today];

                }
                if(!empty($ids)){
                    $where['website_id'] = ['in',$ids];
                }
                elseif(!empty($data)){
                    $where['website_id'] = ['gt', $data];
                }
                $col = 'website';
                $order = 'website_id asc';
                $fun = 'mac_url_website_detail';
                $res = model('Website')->listData($where,$order,$this->_param['page'],$this->_param['limit']);
                break;
        }

        if(empty($res['list'])){
            mac_echo(lang('admin/urlsend/no_data'));
            return;
        }

        mac_echo(lang('admin/urlsend/tip',[$res['total'],$res['pagecount'],$res['page']]));

        $urls = [];
        foreach($res['list'] as $k=>$v){
            $urls[$v[$col.'_id']] =  $GLOBALS['http_type'] . $GLOBALS['config']['site']['site_url'] . $fun($v);
            $this->_lastid = $v[$col.'_id'];

            mac_echo($v[$col.'_id'] . '、'. $v[$col . '_name'] . '&nbsp;<a href="'.$urls[$v[$col.'_id']].'">'.$urls[$v[$col.'_id']].'</a>');
        }

        $res['urls'] = $urls;
        return $res;
    }


    public function push($pp=[])
    {
        if(!empty($pp)){
            $this->_param = $pp;
        }
        $ac = $this->_param['ac'];

        $cp = 'app\\common\\extend\\urlsend\\' . ucfirst($ac);
        if (class_exists($cp)) {
            $data = $this->data();

            $c = new $cp;
            $res = $c->submit($data);

            if($res['code']!=1){
                mac_echo($res['msg']);
                die;
            }

            if ($data['page'] >= $data['pagecount']) {
                mac_echo(lang('admin/urlsend/complete'));
                if(ENTRANCE=='admin') {

                }
            }
            else {
                $this->_param['page']++;
                $url = url('urlsend/push') . '?' . http_build_query($this->_param);
                if(ENTRANCE=='admin') {
                    mac_jump($url, 3);
                }
                else{
                    $this->push($this->_param);
                }
            }

        }
        else{
            $this->error(lang('param_err'));
        }
    }

}
