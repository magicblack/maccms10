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
        $this->check_show();
        $this->label_role();
        return $this->label_fetch('role/show');
    }

    public function ajax_show()
    {
        $this->check_ajax();
        $this->check_show(1);
        $this->label_role();
        return $this->label_fetch('role/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->label_search($param);
        return $this->label_fetch('role/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param,1);
        $this->label_search($param);
        return $this->label_fetch('role/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_role_detail();
        return $this->label_fetch( mac_tpl_fetch('role',$info['role_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_role_detail();
        return $this->label_fetch('role/ajax_detail');
    }

    public function rss()
    {
        $info = $this->label_role_detail();
        return $this->label_fetch('role/rss');
    }
}
