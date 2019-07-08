<?php
namespace app\index\controller;
use think\Controller;

class Role extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->label_role();
        return $this->label_fetch('role/index');
    }

    public function show()
    {
        $this->label_role();
        return $this->label_fetch('role/show');
    }

    public function ajax_show()
    {
        $this->label_role();
        return $this->label_fetch('role/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->assign('param',$param);
        return $this->label_fetch('role/search');
    }

    public function detail()
    {
        $info = $this->label_role_detail();
        return $this->label_fetch( mac_tpl_fetch('role',$info['role_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $info = $this->label_role_detail();
        return $this->label_fetch('role/ajax_detail');
    }

}
