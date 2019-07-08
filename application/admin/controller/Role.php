<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Role extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if (!empty($param['level'])) {
            $where['role_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'], ['0', '1'])) {
            $where['role_status'] = ['eq', $param['status']];
        }
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['role_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['role_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['role_pic'] = ['like','%#err%'];
            }
        }
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $where['role_name'] = ['like','%'.$param['wd'].'%'];
        }


        $order='role_time desc';
        $res = model('Role')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', '角色管理');
        return $this->fetch('admin@role/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $param['role_content'] = str_replace( $GLOBALS['config']['upload']['protocol'].':','mac:',$param['role_content']);
            $res = model('Role')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $tab = input('tab');
        $rid = input('rid');

        $where=[];
        $where['role_id'] = ['eq',$id];
        $res = model('Role')->infoData($where);
        $info = $res['info'];
        if(empty($info)){
            $info['role_rid'] =  $rid;
        }
        $this->assign('info',$info);

        $where=[];
        $where['vod_id'] = ['eq', $info['role_rid'] ];
        $res = model('Vod')->infoData($where);
        $data = $res['info'];
        $this->assign('data',$data);

        $this->assign('title','角色信息');
        return $this->fetch('admin@role/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['role_id'] = ['in',$ids];
            $res = model('Role')->delData($where);
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
        $start = $param['start'];
        $end = $param['end'];


        if(!empty($ids) && in_array($col,['role_status','role_lock','role_level','role_hits'])){
            $where=[];
            $where['role_id'] = ['in',$ids];
            if(empty($start)) {
                $res = model('Role')->fieldData($where, $col, $val);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['role_id'] = ['eq',$v];
                    $res = model('Role')->fieldData($where, $col, $val);
                }
            }
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

}
