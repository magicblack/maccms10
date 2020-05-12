<?php
namespace app\common\model;

use app\common\util\Ftp as ftpOper;

class Upload extends Base {

    public function api($file_path,$config)
    {
        if(empty($config)){
            return $file_path;
        }

        if ($config['mode'] == '2') {
            $config['mode'] = 'upyun';
        }
        elseif ($config['mode'] == '3'){
            $config['mode'] = 'qiniu';
        }
        elseif ($config['mode'] == '4') {
            $config['mode'] = 'ftp';
        }
        elseif ($config['mode'] == '5') {
            $config['mode'] = 'weibo';
        }

        if(!in_array($config['mode'],['local','remote'])){
            $cp = 'app\\common\\extend\\upload\\' . ucfirst($config['mode']);
            if (class_exists($cp)) {
                $c = new $cp;
                $file_path = $c->submit($file_path);
            }
        }

        return str_replace(['http:','https:'],'mac:',$file_path);
    }

}