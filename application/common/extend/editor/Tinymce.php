<?php
namespace app\common\extend\editor;

class Tinymce {

    public $name = 'Tinymce';
    public $ver = '1.0';

    public function front($param)
    {

    }

    public function back($info='',$status=0,$data=[])
    {
        $arr=[];
        $arr['location'] = $data['file'];
        echo json_encode($arr, 1);
        exit;
    }
}
