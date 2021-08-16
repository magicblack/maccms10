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
        if($GLOBALS['config']['app']['show_verify'] ==1){
            if(empty(session('show_verify'))){
                $this->assign('type','show');
                return $this->label_fetch('public/verify');
            }
        }
        $this->label_role();
        return $this->label_fetch('role/show');
    }

    public function ajax_show()
    {
        $this->check_show();
        $this->label_role();
        return $this->label_fetch('role/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        if($GLOBALS['config']['app']['search_verify'] ==1){
            if(empty(session('search_verify'))){
                $this->assign('type','search');
                return $this->label_fetch('public/verify');
            }
        }
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param = mac_escape_param($param);
        }
        $this->assign('param',$param);
        return $this->label_fetch('role/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        if(!empty($GLOBALS['config']['app']['wall_filter'])){
            $param = mac_escape_param($param);
        }
        $this->assign('param',$param);
        return $this->label_fetch('role/ajax_search');
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
