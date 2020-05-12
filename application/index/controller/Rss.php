<?php
namespace app\index\controller;
use think\Controller;

class Rss extends Base
{
    public function __construct()
    {
        parent::__construct();
        header("Content-Type:text/xml");
    }

    public function index()
    {
        $html = $this->label_fetch('rss/index');
        echo $html;
    }

    public function baidu()
    {
        $html = $this->label_fetch('rss/baidu');
        echo $html;
    }

    public function google()
    {
        $html = $this->label_fetch('rss/google');
        echo $html;
    }

    public function so()
    {
        $html = $this->label_fetch('rss/so');
        echo $html;
    }

    public function sogou()
    {
        $html = $this->label_fetch('rss/sogou');
        echo $html;
    }

    public function bing()
    {
        $html = $this->label_fetch('rss/bing');
        echo $html;
    }

    public function sm()
    {
        $html = $this->label_fetch('rss/sm');
        echo $html;
    }

}
