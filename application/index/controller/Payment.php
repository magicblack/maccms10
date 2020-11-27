<?php
namespace app\index\controller;
use think\Controller;
use \think\Request;

class Payment extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function notify()
    {
        if (Request()->isPost()) {
            $param = input();
            $pay_type = $param['pay_type'];

            if ($GLOBALS['config']['pay'][$pay_type]['appid'] == '') {
                echo lang('index/payment_status');
                exit;
            }

            $cp = 'app\\common\\extend\\pay\\' . ucfirst($pay_type);
            if (class_exists($cp)) {
                $c = new $cp;
                $c->notify();
            }
            else{
                echo lang('index/payment_not');
                exit;
            }
        }
        else{
            return $this->success(lang('index/payment_ok'), url('user/index') );
        }
    }
}
