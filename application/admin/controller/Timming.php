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
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }

            $param['weeks'] = isset($param['weeks']) ? join(',',$param['weeks']) : '';
            $param['hours'] = isset($param['hours']) ? join(',',$param['hours']) : '';
            // interval（秒）：分钟/秒级执行间隔，0=维持星期+小时的小时级逻辑
            $param['interval'] = isset($param['interval']) ? intval($param['interval']) : 0;
            // 不把 CSRF token 写进配置文件
            unset($param['__token__']);
            // 合并保留旧配置中表单未提交的字段（如 runtime、id），避免整表覆盖导致丢失
            $old = isset($list[$param['name']]) && is_array($list[$param['name']]) ? $list[$param['name']] : [];
            $list[$param['name']] = array_merge($old, $param);
            $res = mac_arr2file( APP_PATH .'extra/timming.php', $list);
            if($res===false){
                return $this->ajaxErrorWithFreshToken(lang('write_err_config'));
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
