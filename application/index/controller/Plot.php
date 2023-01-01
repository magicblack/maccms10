<?php
namespace app\index\controller;
use think\Controller;

class Plot extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // https://github.com/magicblack/maccms10/issues/960
        $param = mac_param_url();
        $this->assign('param',$param);
        return $this->label_fetch('plot/index');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->label_search($param);
        return $this->label_fetch('plot/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param,1);
        $this->label_search($param);
        return $this->label_fetch('plot/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('plot/detail');
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_vod_detail();
        return $this->label_fetch('plot/ajax_detail');
    }

    public function rss()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('plot/rss');
    }

}
