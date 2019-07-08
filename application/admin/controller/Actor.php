<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Actor extends Base
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
            $where['actor_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'], ['0', '1'])) {
            $where['actor_status'] = ['eq', $param['status']];
        }
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['actor_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['actor_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['actor_pic'] = ['like','%#err%'];
            }
        }
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $where['actor_name'] = ['like','%'.$param['wd'].'%'];
        }


        $order='actor_time desc';
        $res = model('Actor')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', '演员管理');
        return $this->fetch('admin@actor/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $param['actor_content'] = str_replace( $GLOBALS['config']['upload']['protocol'].':','mac:',$param['actor_content']);
            $res = model('Actor')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['actor_id'] = ['eq',$id];
        $res = model('Actor')->infoData($where);
        $info = $res['info'];
        $this->assign('info',$info);


        $this->assign('title','演员信息');
        return $this->fetch('admin@actor/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['actor_id'] = ['in',$ids];
            $res = model('Actor')->delData($where);
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


        if(!empty($ids) && in_array($col,['actor_status','actor_lock','actor_level','actor_hits'])){
            $where=[];
            $where['actor_id'] = ['in',$ids];
            if(empty($start)) {
                $res = model('Actor')->fieldData($where, $col, $val);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['actor_id'] = ['eq',$v];
                    $res = model('Actor')->fieldData($where, $col, $val);
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
