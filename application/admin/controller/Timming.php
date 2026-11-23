<?php
namespace app\admin\controller;
use app\common\util\TimmingConfig;
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
        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }

            $name = isset($param['name']) ? $param['name'] : '';
            if (!is_string($name) || trim($name) === '') {
                return $this->ajaxErrorWithFreshToken(lang('name_empty'));
            }

            $param['weeks'] = isset($param['weeks']) ? join(',',$param['weeks']) : '';
            $param['hours'] = isset($param['hours']) ? join(',',$param['hours']) : '';
            // interval（秒）：分钟/秒级执行间隔，0=维持星期+小时的小时级逻辑
            $param['interval'] = isset($param['interval']) ? intval($param['interval']) : 0;
            // 不把 CSRF token 写进配置文件
            unset($param['__token__']);
            $res = TimmingConfig::update(APP_PATH . 'extra/timming.php', function (&$list) use ($param) {
                $name = $param['name'];
                $old = isset($list[$name]) && is_array($list[$name]) ? $list[$name] : [];
                $list[$name] = array_merge($old, $param);
                return true;
            });
            if($res===false){
                return $this->ajaxErrorWithFreshToken(lang('write_err_config'));
            }

            return $this->success(lang('save_ok'));
        }
        $list = config('timming');
        $id = isset($param['id']) ? $param['id'] : '';
        $info = (is_string($id) || is_int($id)) && isset($list[$id]) && is_array($list[$id]) ? $list[$id] : [];
        $info += [
            'status' => '0',
            'name' => '',
            'des' => '',
            'file' => 'collect',
            'param' => '',
            'weeks' => '',
            'hours' => '',
            'interval' => 0,
        ];

        $this->assign('info',$info);
        $this->assign('title',lang('admin/timming/title'));
        return $this->fetch('admin@timming/info');
    }

    public function del()
    {
        $param = input();
        $res = TimmingConfig::update(APP_PATH . 'extra/timming.php', function (&$list) use ($param) {
            unset($list[$param['ids']]);
            return true;
        });
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
            $ids = explode(',', $ids);
            $res = TimmingConfig::update(APP_PATH . 'extra/timming.php', function (&$list) use ($ids, $col, $val) {
                foreach ($list as $key => &$task) {
                    if (in_array($key, $ids)) {
                        $task[$col] = $val;
                    }
                }
                return true;
            });
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        return $this->error(lang('param_err'));
    }
}
