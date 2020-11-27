<?php
namespace app\admin\controller;
use think\Db;

class Topic extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        if(in_array($param['status'],['0','1'],true)){
            $where['topic_status'] = ['eq',$param['status']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['topic_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='topic_time desc';
        $res = model('Topic')->listData($where,$order,$param['page'],$param['limit']);

        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['topic_detail'] >0 && $v['topic_time_make'] < $v['topic_time']){
                $v['ismake'] = 0;
            }
        }

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/topic/title'));
        return $this->fetch('admin@topic/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Topic')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }


        $id = input('id');
        $where=[];
        $where['topic_id'] = ['eq',$id];
        $res = model('Topic')->infoData($where);


        $this->assign('info',$res['info']);

        $config = config('maccms.site');
        $this->assign('install_dir',$config['install_dir']);
        $this->assign('title',lang('admin/topic/title'));
        return $this->fetch('admin@topic/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['topic_id'] = ['in',$ids];
            $res = model('Topic')->delData($where);
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

        if(!empty($ids) && in_array($col,['topic_status','topic_level']) ){
            $where=[];
            $where['topic_id'] = ['in',$ids];

            $res = model('Topic')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

}
