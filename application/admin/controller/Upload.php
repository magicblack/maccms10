<?php
namespace app\admin\controller;
use think\Db;



class Upload extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $this->assign('path',$param['path']);
        $this->assign('id',$param['id']);

        $this->assign('title',lang('upload_pic'));
        return $this->fetch('admin@upload/index');
    }

    public function test()
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        if($temp_file){
            echo lang('admin/upload/test_write_ok').'：' . $temp_file;
        }
        else{
            echo lang('admin/upload/test_write_err').'：' . sys_get_temp_dir() ;
        }
    }

    public function upload($p=[])
    {
		return model('Upload')->upload($p);
    }


}
