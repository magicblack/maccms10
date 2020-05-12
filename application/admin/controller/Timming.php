<?php
namespace app\admin\controller;
use think\Db;

class Timming extends Base
{
    var $_pre;
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $list = config('timming');
        $this->assign('list',$list);
        $this->assign('title','定时任务管理');
        return $this->fetch('admin@timming/index');
    }

    public function info()
    {
        $param = input();
        $list = config('timming');
        if (Request()->isPost()) {
            $param['weeks'] = join(',',$param['weeks']);
            $param['hours'] = join(',',$param['hours']);
            $list[$param['name']] = $param;
            $res = mac_arr2file( APP_PATH .'extra/timming.php', $list);
            if($res===false){
                return $this->error('保存配置文件失败，请重试!');
            }

            return $this->success('保存成功!');
        }
        $info = $list[$param['id']];

        $this->assign('info',$info);
        $this->assign('title','信息管理');
        return $this->fetch('admin@timming/info');
    }

    public function del()
    {
        $param = input();
        $list = config('timming');
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/timming.php', $list);
        if($res===false){
            return $this->error('删除失败，请重试!');
        }

        return $this->success('删除成功!');
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['status'])){
            $list = config('timming');
            $ids = explode(',',$ids);
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
            }
            $res = mac_arr2file(APP_PATH. 'extra/timming.php', $list);
            if($res===false){
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }
        return $this->error('参数错误');
    }
}
