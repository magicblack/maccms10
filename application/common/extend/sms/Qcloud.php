<?php
namespace app\common\extend\sms;

use Qcloud\Sms\SmsSingleSender;

class Qcloud {

    public $name = '腾讯云短信';
    public $ver = '1.0';

    public function submit($phone,$code,$type_flag,$type_des,$text)
    {
        if(empty($phone) || empty($code) || empty($type_flag)){
            return ['code'=>101,'msg'=>'参数错误'];
        }

        $appid = $GLOBALS['config']['sms']['appid'];
        $appkey = $GLOBALS['config']['sms']['appkey'];
        $sign = $GLOBALS['config']['sms']['sign'];
        $tpl = $GLOBALS['config']['sms']['tpl_code_'.$type_flag];
        $params = [
            $code
        ];

        try {
            $ssender = new SmsSingleSender($appid, $appkey);
            //$result = $ssender->send(0, "86", $phone, '【'.$sign.'】'.$text, "", "");
            $result = $ssender->sendWithParam("86", $phone, $tpl, $params, $sign, "", "");

            $rsp = json_decode($result,true);
            if($rsp['result'] ==0){
                return ['code'=>1,'msg'=>'ok'];
            }
            return ['code'=>101,'msg'=>$rsp['errmsg']];
        }
        catch(\Exception $e) {
            return ['code'=>102,'msg'=>'发生异常请重试'];
        }
    }
}
