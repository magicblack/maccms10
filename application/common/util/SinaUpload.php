<?php
namespace app\common\util;

class SinaUpload
{
    public $_config=[];

    public function __construct($config=array()){
        $this->config($config);
    }

    public function config($config=array()){
        $this->_config = array_merge($this->_config, $config);
    }

    function check()
    {
        if (time() - $this->_config['time'] >20*3600 || $this->_config['cookie']=='SUB;' || $this->_config['cookie']==''){
            $cookie = self::login($this->_config['user'],$this->_config['pwd']);
            if($cookie && $cookie!='SUB;'){
                $this->_config['cookie'] = $cookie;
                $this->_config['time'] = time();

                $config_old = config('maccms');

                $config_new['upload']=$config_old['upload'];
                $config_new['upload']['api']['weibo'] = $this->_config;
                $config_new = array_merge($config_old, $config_new);

                $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
                if ($res === false) {
                    return ['code'=>'202','msg'=>'写入微博登录状态失败'];
                }

            }else{
                return ['code'=>'203','msg'=>'获取新浪微博cookie出现错误，请检查账号状态或者重新获取cookie'];
            }
        }
        return ['code'=>'1','msg'=>'ok'];
    }

    function login($u,$p){
        $loginUrl = 'https://login.sina.com.cn/sso/login.php?client=ssologin.js(v1.4.15)&_=1403138799543';
        $loginData['entry'] = 'sso';
        $loginData['gateway'] = '1';
        $loginData['from'] = 'null';
        $loginData['savestate'] = '30';
        $loginData['useticket'] = '0';
        $loginData['pagerefer'] = '';
        $loginData['vsnf'] = '1';
        $loginData['su'] = base64_encode($u);
        $loginData['service'] = 'sso';
        $loginData['sp'] = $p;
        $loginData['sr'] = '1920*1080';
        $loginData['encoding'] = 'UTF-8';
        $loginData['cdult'] = '3';
        $loginData['domain'] = 'sina.com.cn';
        $loginData['prelt'] = '0';
        $loginData['returntype'] = 'TEXT';
        return self::loginPost($loginUrl,$loginData);
    }

    /**
     * 发送微博登录请求
     * @param  string $url  接口地址
     * @param  array  $data 数据
     * @return json         算了，还是返回cookie吧//返回登录成功后的用户信息json
     */
    function loginPost($url,$data){
        $tmp = '';
        if(is_array($data)){
            foreach($data as $key =>$value){
                $tmp .= $key."=".$value."&";
            }
            $post = trim($tmp,"&");
        }else{
            $post = $data;
        }
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
        $return = curl_exec($ch);
        curl_close($ch);
        return 'SUB' . self::getSubstr($return,"Set-Cookie: SUB",'; ') . ';';
    }

    /**
     * 取本文中间
     */
    function getSubstr($str,$leftStr,$rightStr){
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr,$left);
        //echo '<br>右边:'.$right;
        if($left <= 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
    }


    function upload($file, $multipart = true,$cookie) {
        $url = 'http://picupload.service.weibo.com/interface/pic_upload.php'.'?mime=image%2Fjpeg&data=base64&url=0&markpos=1&logo=&nick=0&marks=1&app=miniblog';
        if($multipart) {
            $url .= '&cb=http://weibo.com/aj/static/upimgback.html?_wv=5&callback=STK_ijax_'.time();
            if (class_exists('CURLFile')) {     // php 5.5
                $post['pic1'] = new \CURLFile(realpath($file));
            } else {
                $post['pic1'] = '@'.realpath($file);
            }
        } else {
            $post['b64_data'] = base64_encode(file_get_contents($file));
        }
        // Curl提交
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array("Cookie: $cookie"),
            CURLOPT_POSTFIELDS => $post,
        ));
        $output = curl_exec($ch);
        curl_close($ch);
        // 正则表达式提取返回结果中的json数据
        preg_match('/({.*)/i', $output, $match);
        if(!isset($match[1])) return ['code'=>'301','msg'=>'上传错误'];
        $a=json_decode($match[1],true);
        $width = $a['data']['pics']['pic_1']['width'];
        $size = $a['data']['pics']['pic_1']['size'];
        $height = $a['data']['pics']['pic_1']['height'];
        $pid = $a['data']['pics']['pic_1']['pid'];
        if(!$pid){return ['code'=>'202','msg'=>'上传错误']; }
        $size = 'large';
        if(!empty($GLOBALS['config']['upload']['api']['weibo']['size'])){
            $size = $GLOBALS['config']['upload']['api']['weibo']['size'];
        }
        return ['code'=>'200','width'=>$width,"height"=>$height,"size"=>$size,"pid"=>$pid,"url"=>"http://ws3.sinaimg.cn/".$size."/".$pid.".jpg"];
    }
}