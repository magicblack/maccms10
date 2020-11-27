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
        $this->assign('title',lang('admin/timming/title'));
        return $this->fetch('admin@timming/index');
    }

    public function info()
    {
        $param = input();
        $list = config('timming');
        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            $param['weeks'] = join(',',$param['weeks']);
            $param['hours'] = join(',',$param['hours']);
            $list[$param['name']] = $param;
            $res = mac_arr2file( APP_PATH .'extra/timming.php', $list);
            if($res===false){
                return $this->error(lang('write_err_config'));
            }

            return $this->success(lang('save_ok'));
        }
        $info = $list[$param['id']];

        $this->assign('info',$info);
        $this->assign('title',lang('admin/timming/title'));
        return $this->fetch('admin@timming/info');
    }

    public function del()
    {
        $param = input();
        $list = config('timming');
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/timming.php', $list);
        if($res===false){
            return $this->error(lang('del_err'));
        }

        return $this->success(lang('del_ok'));
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
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }
}
