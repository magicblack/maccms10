<?php
namespace app\index\controller;
use think\Controller;

class Label extends Base
{
    public function __construct()
    {
        parent::__construct();

        $dispatch = request()->dispatch();
        if (isset($dispatch['module'])) {
            $file = $dispatch['module'][2];
            $param = mac_param_url();
            if(!empty($param['file'])){
                $file = $param['file'];
            }
            if(!file_exists(MAC_ROOT_TEMPLATE . 'label/'. $file.'.html' )){
                return $this->error('非法请求');
            }
            echo $this->label_fetch('label/'.$file);
        }
        exit;
    }

    public function index()
    {

    }

}
