<?php
namespace app\index\event;
use login\ThinkOauth;

class LoginEvent
{
    //登录成功，获取腾讯QQ用户信息
    public function qq($token)
    {
        $qq = ThinkOauth::getInstance('qq', $token);
        $data = $qq->call('user/get_user_info');
        if ($data['ret'] == 0) {
            $userInfo['type'] = 'QQ';
            $userInfo['name'] = $data['nickname'];
            $userInfo['nick'] = $data['nickname'];
            $userInfo['head'] = $data['figureurl_2'];
            $userInfo['openid'] = $qq->openid();
            return ['code'=>1,'msg'=>'ok','info'=>$userInfo];
        } else {
            return ['code'=>0,'msg'=>"获取腾讯QQ用户信息失败：{$data['msg']}"];
        }
    }

    //登录成功，获取微信用户信息
    public function weixin($token)
    {
        $weixin = ThinkOauth::getInstance('weixin', $token);
        $data = $weixin->call('sns/userinfo');
        if ($data['errcode'] == 0) {
            $userInfo['type'] = 'WEIXIN';
            $userInfo['name'] = $data['nickname'];
            $userInfo['nick'] = $data['nickname'];
            $userInfo['head'] = $data['headimgurl'];
            $userInfo['openid'] = $weixin->openid();
            return ['code'=>1,'msg'=>'ok','info'=>$userInfo];
        } else {
            return ['code'=>0,'msg'=>"获取微信用户信息失败：{$data['errmsg']}"];
        }
    }

}