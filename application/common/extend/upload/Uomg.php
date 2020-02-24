<?php
namespace app\common\extend\upload;

class Uomg
{
    public $name = '优启梦云存储';
    public $ver = '1.0';

    public function submit($file_path)
    {
        $type = $GLOBALS['config']['upload']['api']['uomg']['type'];
        $openid = $GLOBALS['config']['upload']['api']['uomg']['openid'];
        $key = $GLOBALS['config']['upload']['api']['uomg']['key'];
        if(empty($type)){
            $type = 'ali';
        }
        $filePath = ROOT_PATH . $file_path;

        $url = 'https://api.uomg.com/api/image.'.$type;
        $data = [];
        //$data['imgurl'] = 'http://imgsrc.baidu.com/forum/pic/item/09f790529822720edafc8a9d76cb0a46f21faba3.jpg';
        $data['file'] = 'multipart';

        if (class_exists('CURLFile')) {
            $data['Filedata'] = new \CURLFile(realpath($file_path));
        } else {
            $data['Filedata'] = '@'.realpath($file_path);
        }

        $html = mac_curl_post($url,$data);
        $json = @json_decode($html,true);
        if($json['code']=='1'){
            $file_path = $json['imgurl'];
            @unlink($filePath);
        }

        return $file_path;
    }
}