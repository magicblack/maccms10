<?php
namespace app\index\controller;

class Gbook extends Base
{
    var $_config;
    public function __construct()
    {
        parent::__construct();
        //关闭中
        if($GLOBALS['config']['gbook']['status'] == 0){
            echo 'gbook is close';
            exit;
        }
    }

    public function index()
    {
        if (Request()->isPost()) {
            return $this->saveData();
        }
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/index');
    }

    public function ajax()
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/ajax',0,'json');
    }

    public function report()
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/report');
    }
    
    public function saveData() {
        $param = input();

        if($GLOBALS['config']['gbook']['verify'] == 1){
            if(!captcha_check($param['verify'])){
                return ['code'=>1002,'msg'=>lang('verify_err')];
            }
        }

        if($GLOBALS['config']['gbook']['login'] ==1){
            if(empty(cookie('user_id'))){
                return ['code' => 1003, 'msg' => lang('index/require_login')];
            }
            $res = model('User')->checkLogin();
            if($res['code']>1) {
                return ['code' => 1003, 'msg' => lang('index/require_login')];
            }
        }

        if(empty($param['gbook_content'])){
            return ['code'=>1004,'msg'=>lang('index/require_content')];
        }

        $cookie = 'gbook_timespan';
        if(!empty(cookie($cookie))){
            return ['code'=>1005,'msg'=>lang('frequently')];
        }

        $param['gbook_content']= htmlentities(mac_filter_words($param['gbook_content']));
        $pattern = '/[^\x00-\x80]/';
        if(!preg_match($pattern,$param['gbook_content'])){
            return ['code'=>1005,'msg'=>lang('index/require_cn')];
        }

        $param['gbook_reply'] = '';

        if(empty(cookie('user_id'))){
            $param['gbook_name'] = lang('controller/visitor');
            $param['user_id']=0;
        }
        else{
            $param['gbook_name'] = cookie('user_name');
            $param['user_id'] = intval(cookie('user_id'));
        }
        $param['gbook_name'] = htmlentities($param['gbook_name']);

        if($GLOBALS['config']['gbook']['audit'] ==1){
            $param['gbook_status'] = 0;
        }

        $param['gbook_ip'] = mac_get_ip_long();

        $res = model('Gbook')->saveData($param);

        if($res['code']>1){
            return $res;
        }
        else{
            cookie($cookie, 't', $GLOBALS['config']['gbook']['timespan']);
            if($GLOBALS['config']['gbook']['audit'] ==1){
                $res['msg'] =  lang('index/thanks_msg_audit');
            }
            else{
                $res['msg'] = lang('index/thanks_msg');
            }
            return $res;
        }
    }

}
