<?php
namespace app\common\extend\urlsend;

use think\Cache;

class Baidu {

    public $name = '百度推送普通';
    public $ver = '1.0';

    public function submit($data)
    {
        $token = $GLOBALS['config']['urlsend']['baidu']['token'];
        $site = $GLOBALS['http_type'] . $GLOBALS['config']['site']['site_url'];

        $api = 'http://data.zz.baidu.com/urls?site=' . $site . '&token=' . $token;
        $head = ['Content-Type: text/plain'];
        $post = implode("\n", $data['urls']);

        $r = mac_curl_post($api, $post, $head);
        $json = json_decode($r,true);
        if(!$json){
            return ['code'=>101,'msg'=>'请求失败，请重试'];
        }
        elseif($json['error']){
            return ['code'=>102,'msg'=>'发生错误：'. $json['message'] ];
        }
        return ['code'=>1,'msg'=>'推送成功'.$json['success'].'条；当天剩余可推'.$json['remain'].'条。' ];
    }

}
