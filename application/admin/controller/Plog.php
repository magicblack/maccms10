<?php
namespace app\admin\controller;
use think\Db;

class Plog extends Base
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
        if(!empty($param['type'])){
            $where['plog_type'] = ['eq',$param['type']];
        }
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }

        $order='plog_id desc';
        $res = model('Plog')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/plog/title'));
        return $this->fetch('admin@plog/index');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];
        if(!empty($ids)){
            $where=[];
            $where['plog_id'] = ['in',$ids];
            if($all==1){
                $where['plog_id'] = ['gt',0];
            }
            $res = model('Plog')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
