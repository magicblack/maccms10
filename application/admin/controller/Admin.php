<?php
namespace app\admin\controller;
use think\Db;

class Admin extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['admin_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='admin_id desc';
        $res = model('Admin')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';

        $this->assign('admin',$this->_admin);

        $this->assign('param',$param);
        $this->assign('title',lang('admin/admin/title'));
        return $this->fetch('admin@admin/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(!in_array('index/welcome',$param['admin_auth'])){
                $param['admin_auth'][] = 'index/welcome';
            }
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            $res = model('Admin')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');

        $where=[];
        $where['admin_id'] = ['eq',$id];

        $res = model('Admin')->infoData($where);
        $this->assign('info',$res['info']);

        //权限列表
        $menus = @include MAC_ADMIN_COMM . 'auth.php';

        foreach($menus as $k1=>$v1){
            $all = [];
            $cs = [];
            $menus[$k1]['ck'] = '';
            foreach($v1['sub'] as $k2=>$v2){
                $one = $v2['controller'] . '/' . $v2['action'];
                $menus[$k1]['sub'][$k2]['url'] = url($one);
                $menus[$k1]['sub'][$k2]['ck']= '';
                $all[] = $one;

                if(strpos(','.$res['info']['admin_auth'],$one)>0){
                    $cs[] = $one;
                    $menus[$k1]['sub'][$k2]['ck'] = 'checked';
                }
                if($k2==11){
                    $menus[$k1]['sub'][$k2]['ck'] = ' checked  readonly="readonly" ';
                }
            }
            if($all == $cs){
                $menus[$k1]['ck'] = 'checked';
            }
        }
        $this->assign('menus',$menus);


        $this->assign('title',lang('admin/admin/title'));
        return $this->fetch('admin@admin/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['admin_id'] = ['in',$ids];
            if(!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            if(in_array($this->_admin['admin_id'],$ids)){
                return $this->error(lang('admin/admin/del_cur_err'));
            }
            $res = model('Admin')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['admin_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['admin_id'] = ['in',$ids];

            $res = model('Admin')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
