<?php
namespace app\common\extend\editor;

class Ueditor {

    public $name = 'Ueditor';
    public $ver = '1.0';

    public function front($param)
    {
        if (isset($param['action']) && $param['action'] == 'config') {
            $configFile = './static/ueditor/config.json';
            if (!empty($param['ueditor_theme']) && $param['ueditor_theme'] === 'new') {
                $configFile = './static_new/ueditor/config.json';
            }
            $raw = @file_get_contents($configFile);
            if ($raw === false || $raw === '') {
                $raw = file_get_contents('./static/ueditor/config.json');
            }
            $UE_CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", $raw), true);
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
