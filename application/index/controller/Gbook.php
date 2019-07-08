<?php
namespace app\index\controller;
use think\Controller;

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
        return $this->label_fetch('gbook/ajax');
    }

    public function report()
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/report');
    }

    public function error()
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
                return ['code'=>1002,'msg'=>'验证码错误'];
            }
        }

        if($GLOBALS['config']['gbook']['login'] ==1){
            if(empty(cookie('user_id'))){
                return ['code' => 1003, 'msg' => '登录后才可以发表留言'];
            }
            $res = model('User')->checkLogin();
            if($res['code']>1) {
                return ['code' => 1003, 'msg' => '登录后才可以发表留言'];
            }
        }

        if(empty($param['gbook_content'])){
            return ['code'=>1004,'msg'=>'留言内容不能为空'];
        }

        $cookie = 'gbook_timespan';
        if(!empty(cookie($cookie))){
            return ['code'=>1005,'msg'=>'请不要频繁操作'];
        }

        $pattern = '/[^\x00-\x80]/';
        if(!preg_match($pattern,$param['gbook_content'])){
            return ['code'=>1005,'msg'=>'内容必须包含中文,请重新输入'];
        }
        $param['gbook_content']= htmlentities(mac_filter_words($param['gbook_content']));
        $param['gbook_reply'] = '';

        if(empty(cookie('user_id'))){
            $param['gbook_name'] = '游客';
        }
        else{
            $param['gbook_name'] = cookie('user_name');
            $param['user_id'] = intval(cookie('user_id'));
        }
        $param['gbook_name'] = htmlentities($param['gbook_name']);

        if($GLOBALS['config']['gbook']['audit'] ==1){
            $param['gbook_status'] = 0;
        }

        $ip = sprintf('%u',ip2long(request()->ip()));
        if($ip>2147483647){
            $ip=0;
        }
        $param['gbook_ip'] = $ip;

        $res = model('Gbook')->saveData($param);

        if($res['code']>1){
            return $res;
        }
        else{
            cookie($cookie, 't', $GLOBALS['config']['gbook']['timespan']);
            if($GLOBALS['config']['gbook']['audit'] ==1){
                $res['msg'] = '谢谢，我们会尽快审核你的发言！';
            }
            else{
                $res['msg'] = '感谢你的留言！';
            }
            return $res;
        }
    }

}
