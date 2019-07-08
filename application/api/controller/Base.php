<?php
namespace app\api\controller;
use think\Controller;
use app\common\controller\All;

class Base extends All
{
    public function __construct()
    {
        parent::__construct();
        $config = $GLOBALS['config']['site'];
        $this->assign($config);

        //站点关闭中
        if($config['site_status'] == 0){

        }
    }

}