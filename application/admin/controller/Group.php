<?php
namespace app\admin\controller;
use think\Db;

class Group extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $where=[];

        if(in_array($param['status'],['0','1'],true)){
            $where['group_status'] = ['eq',$param['status']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $where['group_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='group_id asc';
        $res = model('Group')->listData($where,$order);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);

        $this->assign('param',$param);
        $this->assign('title','会员组管理');
        return $this->fetch('admin@group/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');

            if($GLOBALS['config']['user']['reg_group'] == $param['group_id']){
                $param['group_status'] = 1;
            }
            $res = model('Group')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['group_id'] = ['eq',$id];
        $res = model('Group')->infoData($where);

        $this->assign('info',$res['info']);


        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title','会员组信息');
        return $this->fetch('admin@group/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){

            if(strpos(','.$ids.',', ','.$GLOBALS['config']['user']['reg_group'].',')!==false){
                return $this->error('注册默认会员组无法删除');
            }

            $where=[];
            $where['group_id'] = ['in',$ids];
            $res = model('Group')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['group_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['group_id'] = ['in',$ids];

            $res = model('Group')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }


}
