<?php
namespace app\admin\controller;
use think\Db;

class Ulog extends Base
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
        if(!empty($param['mid'])){
            $where['ulog_mid'] = ['eq',$param['mid']];
        }
        if(!empty($param['type'])){
            $where['ulog_type'] = ['eq',$param['type']];
        }
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }

        $order='ulog_id desc';
        $res = model('Ulog')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/ulog/title'));
        return $this->fetch('admin@ulog/index');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];
        if(!empty($ids)){
            $where=[];
            $where['ulog_id'] = ['in',$ids];
            if($all==1){
                $where['ulog_id'] = ['gt',0];
            }
            $res = model('Ulog')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
