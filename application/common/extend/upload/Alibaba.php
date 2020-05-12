<?php
namespace app\common\extend\upload;

class Alibaba
{
    public $name = '阿里巴巴云存储';
    public $ver = '1.0';

    public function submit($file_path)
    {
        $filePath = ROOT_PATH . $file_path;

        $url = 'https://kfupload.alibaba.com/mupload';
        $data = [];
        $data['scene'] = 'aeMessageCenterV2ImageRule';
        $data['name'] = 'player.jpg';
        if (class_exists('CURLFile')) {
            $data['file'] = new \CURLFile(realpath($file_path));
        } else {
            $data['file'] = '@'.realpath($file_path);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $httpheader[] = "Accept:application/json";
        $httpheader[] = "Accept-Encoding:gzip,deflate,sdch";
        $httpheader[] = "Accept-Language:zh-CN,zh;q=0.8";
        $httpheader[] = "Connection:close";
        $ip = mt_rand(48, 140) . "." . mt_rand(10, 240) . "." . mt_rand(10, 240) . "." . mt_rand(10, 240); //随机 ip
        $httpheader[] = 'CLIENT-IP:' . $ip;
        $httpheader[] = 'X-FORWARDED-FOR:' . $ip;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERAGENT, 'iAliexpress/6.22.1 (iPhone; iOS 12.1.2; Scale/2.00)');
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $html = @curl_exec($ch);
        curl_close($ch);
        $json = @json_decode($html,true);

        if($json['code']=='0'){
            $file_path = $json['url'];
            @unlink($filePath);
        }

        return $file_path;
    }
}