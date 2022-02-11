<?php
namespace app\common\extend\upload;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class Qiniu
{
    public $name = '七牛云存储';
    public $ver = '1.0';
    private $config = [];

    public function __construct($config = []) {
        $this->config = $config;
    }

    public function submit($file_path)
    {
        $bucket = $GLOBALS['config']['upload']['api']['qiniu']['bucket'];
        $accessKey = $GLOBALS['config']['upload']['api']['qiniu']['accesskey'];
        $secretKey = $GLOBALS['config']['upload']['api']['qiniu']['secretkey'];

        require_once ROOT_PATH . 'extend/qiniu/autoload.php';
        $auth = new Auth($accessKey, $secretKey);
        $return = '{"newName":"$(key)","hash":"$(etag)","fsize":$(fsize),"bucket":"$(bucket)","oldName":"$(fname)","width":"$(imageInfo.width)","height":"$(imageInfo.height)"}';
        $return = array('returnBody' => $return);
        $expires = 3600;
        $token = $auth->uploadToken($bucket,$file_path,$expires,$return);
        $filePath = ROOT_PATH . $file_path;
        $uploadMgr = new UploadManager();
        $a = $uploadMgr->putFile($token, $file_path, $filePath);
        empty($this->config['keep_local']) && @unlink($filePath);
        return $GLOBALS['config']['upload']['api']['qiniu']['url'] . '/' . $file_path;
    }
}