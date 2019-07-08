<?php
namespace app\admin\controller;

class Link extends Base
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
            $where['link_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='link_id desc';
        $res = model('Link')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title','友情链接管理');
        return $this->fetch('admin@link/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input();
            $res = model('Link')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['link_id'] = ['eq',$id];
        $res = model('Link')->infoData($where);


        $this->assign('info',$res['info']);
        $this->assign('title','友情链接信息');
        return $this->fetch('admin@link/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['link_id'] = ['in',$ids];
            $res = model('Link')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    public function batch()
    {
        $param = input();
        $ids = $param['ids'];
        foreach ($ids as $k=>$id) {
            $data = [];
            $data['link_id'] = intval($id);
            $data['link_name'] = $param['link_name'][$k];
            $data['link_sort'] = $param['link_sort'][$k];
            $data['link_url'] = $param['link_url'][$k];
            $data['link_type'] = intval($param['link_type'][$k]);
            $data['link_logo'] = $param['link_logo'][$k];

            if (empty($data['link_name'])) {
                $data['link_name'] = '未知';
            }
            $res = model('Link')->saveData($data);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
        }
        $this->success($res['msg']);
    }

}
