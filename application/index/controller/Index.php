<?php
namespace app\index\controller;

class Index extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->label_fetch('index/index');
    }

    public function test()
    {

    }

}
