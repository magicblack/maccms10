<?php
namespace app\common\extend\sms;

use Aliyun\Sms\SignatureHelper;

class Aliyun {

    public $name = '阿里云短信';
    public $ver = '1.0';

    public function submit($phone,$code,$type_flag,$type_des,$text)
    {
        if(empty($phone) || empty($code) || empty($type_flag)){
            return ['code'=>101,'msg'=>'参数错误'];
        }

        $appid = $GLOBALS['config']['sms']['appid'];
        $appkey = $GLOBALS['config']['sms']['appkey'];
        $sign = $GLOBALS['config']['sms']['sign'];
        $security = false;
        $tpl = $GLOBALS['config']['sms']['tpl_code_'.$type_flag];

        $params=[];
        $params['PhoneNumbers'] = $phone;
        $params['SignName'] = $sign;
        $params['TemplateCode'] = $tpl;
        $params['TemplateParam'] = [
            'code'=>$code,
        ];

        if( is_array($params["TemplateParam"])) {
            $params["TemplateParam"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
        }

        try {
            $helper = new SignatureHelper();
            $rsp = $helper->request(
                $appid,
                $appkey,
                "dysmsapi.aliyuncs.com",
                array_merge($params, array(
                    "RegionId" => "cn-hangzhou",
                    "Action" => "SendSms",
                    "Version" => "2017-05-25",
                )),
                $security
            );

            if($rsp['Code'] == 'OK'){
                $rsp['result'] = 1;
            }

            if($rsp['result'] ==1){
                return ['code'=>1,'msg'=>'ok'];
            }
            return ['code'=>101,'msg'=>$rsp['Message']];
        }
        catch(\Exception $e) {
            return ['code'=>102,'msg'=>'发生异常请重试'];
        }
    }
}
