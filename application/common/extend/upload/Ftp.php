<?php
namespace app\common\extend\upload;

use app\common\util\Ftp as ftpOper;

class Ftp
{
    public $name = 'FTP存储';
    public $ver = '1.0';

    public function submit($file_path)
    {
        $ftp = new ftpOper();
        $ftp_config = [
            'ftp_host'=>$GLOBALS['config']['upload']['api']['ftp']['host'],
            'ftp_port'=>$GLOBALS['config']['upload']['api']['ftp']['port'],
            'ftp_user'=>$GLOBALS['config']['upload']['api']['ftp']['user'],
            'ftp_pwd' =>$GLOBALS['config']['upload']['api']['ftp']['pwd'],
            'ftp_dir'=>$GLOBALS['config']['upload']['api']['ftp']['path'],
        ];
        $ftp->config($ftp_config);
        $ftp->connect();
        $a = $ftp->put(ROOT_PATH. $file_path, $file_path);
        $filePath = ROOT_PATH . $file_path;
        @unlink($filePath);
        return $GLOBALS['config']['upload']['api']['ftp']['url'] . '/' . $file_path;
    }
}