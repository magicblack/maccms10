<?php
namespace app\common\extend\upload;

use Upyun\Upyun as upOper;
use Upyun\Config;

class Upyun
{
    public $name = '又拍云存储';
    public $ver = '1.0';

    public function submit($file_path)
    {
        $bucket = $GLOBALS['config']['upload']['api']['upyun']['bucket'];
        $username = $GLOBALS['config']['upload']['api']['upyun']['username'];
        $pwd = $GLOBALS['config']['upload']['api']['upyun']['pwd'];

        require_once ROOT_PATH . 'extend/upyun/vendor/autoload.php';
        $bucketConfig = new Config($bucket, $username, $pwd);
        $client = new upOper($bucketConfig);
        $_file = fopen($file_path, 'r');
        $a = $client->write($file_path, $_file);
        $filePath = ROOT_PATH . $file_path;
        unset($_file);
        @unlink($filePath);
        return $GLOBALS['config']['upload']['api']['upyun']['url'] . '/' . $file_path;
    }
}