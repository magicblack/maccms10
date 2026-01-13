<?php
namespace app\common\extend\urlsend;

use think\Cache;

class Baidufast {

    public $name = '百度推送快速';
    public $ver = '1.0';

    public function submit($data)
    {
        $token = $GLOBALS['config']['urlsend']['baidufast']['token'];
        $site = $GLOBALS['http_type'] . $GLOBALS['config']['site']['site_url'];
        $api = 'http://data.zz.baidu.com/urls?site='.$site.'&token='.$token;

        $head = ['Content-Type: text/plain'];
        $post = implode("\n", $data['urls']);

        $r = mac_curl_post($api, $post, $head);
        $json = json_decode($r,true);

        $url_count = isset($data['urls']) ? count($data['urls']) : 0;
        $api_safe = preg_replace('/token=[^&]+/', 'token=***', $api);

        if(!$json){
            return [
                'code'=>101,
                'msg'=>"请求失败，请重试\n" .
                       "调试信息：\n" .
                       "- 推送URL数量：{$url_count}\n" .
                       "- API地址：{$api_safe}\n" .
                       "- 原始响应：{$r}"
            ];
        }
        elseif(isset($json['error']) && $json['error']){
            $error_msg = isset($json['message']) ? $json['message'] : '未知错误';

            $tips = '';
            if($error_msg == 'type not found') {
                $tips = "\n提示：type参数错误，请检查API地址配置";
            }
            elseif($error_msg == 'site error') {
                $tips = "\n提示：站点未在百度站长平台验证或站点地址配置错误";
            }
            elseif($error_msg == 'token is not valid') {
                $tips = "\n提示：Token无效，请在百度站长平台重新获取正确的Token";
            }

            return [
                'code'=>102,
                'msg'=>"发生错误：{$error_msg}\n" .
                       "调试信息：\n" .
                       "- 推送URL数量：{$url_count}\n" .
                       "- API地址：{$api_safe}\n" .
                       "- 百度返回码：{$json['error']}{$tips}"
            ];
        }
        elseif($json['remain'] ==0 && $json['success']>0){
            $data = array_slice($data['urls'], 0, $json['success'],true );
            $keys = array_keys($data);
            $this->_lastid = end($keys);

            $data = implode("\n", $data);
            $r = mac_curl_post($api, $data, $head);
            $json = json_decode($r,true);
            if(!$json){
                return [
                    'code'=>103,
                    'msg'=>"第二次请求失败，请重试\n" .
                           "调试信息：\n" .
                           "- API地址：{$api_safe}\n" .
                           "- 原始响应：{$r}"
                ];
            }
            elseif(isset($json['error']) && $json['error']){
                $error_msg = isset($json['message']) ? $json['message'] : '未知错误';

                $tips = '';
                if($error_msg == 'type not found') {
                    $tips = "\n提示：type参数错误，请检查API地址配置";
                }
                elseif($error_msg == 'site error') {
                    $tips = "\n提示：站点未在百度站长平台验证或站点地址配置错误";
                }
                elseif($error_msg == 'token is not valid') {
                    $tips = "\n提示：Token无效，请在百度站长平台重新获取正确的Token";
                }

                return [
                    'code'=>104,
                    'msg'=>"第二次请求发生错误：{$error_msg}\n" .
                           "调试信息：\n" .
                           "- API地址：{$api_safe}\n" .
                           "- 百度返回码：{$json['error']}{$tips}"
                ];
            }
        }

        return ['code'=>1,'msg'=>'推送'.$json['remain'].'条；剩余可推送'.$json['success'].'条.' ];
    }

}
