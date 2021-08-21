<?php
namespace app\index\controller;
use think\Controller;

class Art extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->label_fetch('art/index');
    }

    public function type()
    {
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('art',$info['type_tpl'],'type') );
    }

    public function show()
    {
        $this->check_show();
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('art',$info['type_tpl_list'],'show') );
    }

    public function ajax_show()
    {
        $this->check_ajax();
        $this->check_show(1);
        $info = $this->label_type();
        return $this->label_fetch('art/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->label_search($param);
        return $this->label_fetch('art/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param,1);
        $this->label_search($param);
        return $this->label_fetch('art/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_art_detail();
        if(!empty($info['art_pwd']) && session('2-1-'.$info['art_id'])!='1'){
            return $this->label_fetch('art/detail_pwd');
        }
        return $this->label_fetch( mac_tpl_fetch('art',$info['art_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_art_detail();
        return $this->label_fetch('art/ajax_detail');
    }

    public function rss()
    {
        $info = $this->label_art_detail();
        return $this->label_fetch('art/rss');
    }

}
