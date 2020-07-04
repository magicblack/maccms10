<?php
namespace app\common\extend\editor;

class Umeditor {

    public $name = 'Umeditor';
    public $ver = '1.0';

    public function front($param)
    {
        if (isset($param['action']) && $param['action'] == 'config') {
            $UE_CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents('./static/ueditor/config.json')), true);
            echo json_encode($UE_CONFIG);
            exit;
        }
    }

    public function back($info='',$status=0,$data=[])
    {
        $arr=[];
        if ($status == 0) {
            $arr['message'] = $info;
            $arr['state'] = 'ERROR';
        } else {
            $arr['message'] = $info;
            $arr['url'] = $data['file'];
            $arr['state'] = 'SUCCESS';
        }
        echo json_encode($arr, 1);
        exit;
    }
}
