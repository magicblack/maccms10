<?php
namespace app\common\extend\editor;

class Kindeditor {

    public $name = 'Kindeditor';
    public $ver = '1.0';

    public function front($param)
    {

    }

    public function back($info='',$status=0,$data=[])
    {
        $arr=[];
        if ($status == 0) {
            $arr['error'] = 1;
            $arr['message'] = $info;
        } else {
            $arr['error'] = 0;
            $arr['url'] = $data['file'];
        }
        echo json_encode($arr, 1);
        exit;
    }
}
