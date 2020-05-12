<?php
namespace app\api\controller;
use think\Controller;
use app\common\util\WechatPublic as wcOper;

class Wechat extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $config = config('maccms.weixin');
        if($config['status'] ==0){
            echo 'closed';
            exit;
        }
        $wechatObj = new wcOper($config);
        if (isset($_GET['echostr'])) {
            $wechatObj->valid();
        }
        else {
            $wechatObj->responseMsg();
        }
    }

}
