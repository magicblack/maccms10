<?php
namespace app\index\controller;
use think\Controller;

class Topic extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->label_topic_index();
        return $this->label_fetch('topic/index');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->assign('param',$param);
        return $this->label_fetch('topic/search');
    }

    public function detail()
    {
        $info = $this->label_topic_detail();
        return $this->label_fetch(  mac_tpl_fetch('topic',$info['topic_tpl'],'detail')  );
    }

    public function ajax_detail()
    {
        $info = $this->label_topic_detail();
        return $this->label_fetch('topic/ajax_detail');
    }

}
