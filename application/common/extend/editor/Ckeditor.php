<?php
namespace app\common\extend\editor;

class Ckeditor {

    public $name = 'Ckeditor';
    public $ver = '1.0';

    public function front($param)
    {

    }

    public function back($info='',$status=0,$data=[])
    {
        $arr=[];
        if ($status == 1) {
            $arr['uploaded'] = 1;
            $arr['fileName'] = '';
            $arr['url'] = $data['file'];
            //echo '<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(1, "'.$data['file'].'", "");</script>';
        } else {
            $arr['uploaded'] = 0;
            $arr['error']['msg'] = $info;
            //echo '<script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(1, "", "'.$info.'");</script>';
        }
        echo json_encode($arr, 1);
        exit;
    }
}
