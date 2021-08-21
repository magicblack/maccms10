<?php
namespace app\index\controller;
use think\Controller;

class Actor extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $info = $this->label_actor();
        return $this->label_fetch('actor/index');
    }

    public function type()
    {
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('actor',$info['type_tpl'],'type') );
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
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('actor',$info['type_tpl_list'],'show') );
    }

    public function ajax_show()
    {
        $this->check_ajax();
        $this->check_show();
        $info = $this->label_type();
        return $this->label_fetch('actor/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->label_search($param);
        return $this->label_fetch('actor/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param);
        $this->label_search($param,1);
        return $this->label_fetch('actor/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_actor_detail();
        return $this->label_fetch( mac_tpl_fetch('actor',$info['actor_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_actor_detail();
        return $this->label_fetch('actor/ajax_detail');
    }

    public function rss()
    {
        $info = $this->label_actor_detail();
        return $this->label_fetch('actor/rss');
    }

}
