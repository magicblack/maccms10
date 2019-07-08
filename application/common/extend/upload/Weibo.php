<?php
namespace app\common\extend\upload;

use app\common\util\SinaUpload as suOper;

class Weibo
{
    public $name = '新浪图床';
    public $ver = '1.0';

    public function submit($file_path)
    {
        $weibo =  new suOper();

        $weibo->config($GLOBALS['config']['upload']['api']['weibo']);
        $res = $weibo->check();

        if($res['code']>1){
            echo $res['msg'];die;
        }
        $res = $weibo->upload($file_path,false,$weibo->_config['cookie']);
        if(!empty($res['url'])){
            return $res['url'];
        }
        return $file_path;
    }
}