<?php
namespace app\common\extend\upload;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3
{
    public $name = 'S3';
    public $ver = '1.0';
    private $config = [];

    public function __construct($config = []) {
        $this->config = $config;
    }

    public function submit($file_path)
    {
        $bucket = $GLOBALS['config']['upload']['api']['s3']['bucket'];
        $accessKey = $GLOBALS['config']['upload']['api']['s3']['accesskey'];
        $secretKey = $GLOBALS['config']['upload']['api']['s3']['secretkey'];
        $region = $GLOBALS['config']['upload']['api']['s3']['region'];
        $endpoint = $GLOBALS['config']['upload']['api']['s3']['endpoint'];
        $basepath = $GLOBALS['config']['upload']['api']['s3']['basepath'];
        $domain = $GLOBALS['config']['upload']['api']['s3']['domain'];

        require_once ROOT_PATH . 'extend/aws/autoload.php';
        $s3 = new S3Client([
            'region'  => $region,
            'version' => '2006-03-01',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $accessKey,
                'secret' => $secretKey
            ]
        ]);
        try {
            $filePath = ROOT_PATH . $file_path;
            $result = $s3->putObject([
                'Bucket' => $bucket,
                'Key'    => $basepath . $file_path,
                'Body'   => fopen($filePath, 'r'),
                'ACL'    => 'public-read'
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage() . "\n";
        }

        empty($this->config['keep_local']) && @unlink($filePath);
        // return $result['ObjectURL'];
        // echo $result;
        return $domain . $bucket . "/" . $basepath . $file_path;
    }
}
