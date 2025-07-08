<?php
namespace app\admin\controller;
use think\Db;

class User extends Base
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

        if($param['page'] ==1){
            model('User')->expire();
        }

        $where=[];
        if(in_array($param['status'],['0','1'],true)){
            $where['user_status'] = $param['status'];
        }
        if(!empty($param['group'])){
            $where['group_id'] =  $param['group'];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['user_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='user_id desc';
        $res = model('User')->listData($where,$order,$param['page'],$param['limit']);

        $group_list = model('Group')->getCache('group_list');
        foreach($res['list'] as $k=>$v){
            $group_ids = explode(',', $v['group_id']);
            $names = [];
            foreach($group_ids as $gid){
                if(isset($group_list[$gid])){
                    $names[] = $group_list[$gid]['group_name'];
                }
            }
            $res['list'][$k]['group_name'] = implode(',', $names);
        }

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('group_list',$group_list);

        $this->assign('title',lang('admin/user/title'));
        return $this->fetch('admin@user/index');
    }

    public function reward()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $param['uid'] = intval($param['uid']);
        $where=[];
        if(!empty($param['level'])){
            if($param['level']=='1'){
                $where['user_pid'] = ['eq', $param['uid']];
            }
            elseif($param['level']=='2'){
                $where['user_pid_2'] = ['eq', $param['uid']];
            }
            elseif($param['level']=='3'){
                $where['user_pid_3'] = ['eq', $param['uid']];
            }
        }
        else{
            $where['user_pid|user_pid_2|user_pid_3'] = ['eq', intval($param['uid']) ];
        }

        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['user_name'] = ['like','%'.$param['wd'].'%'];
        }

        $order='user_id desc';
        $res = model('User')->listData($where,$order,$param['page'],$param['limit']);
        $group_list = model('Group')->getCache('group_list');
        foreach($res['list'] as $k=>$v){
            $res['list'][$k]['group_name'] = $group_list[$v['group_id']]['group_name'];
        }

        $where2=[];
        $where2['user_pid'] = ['eq', $param['uid']];
        $level_cc_1 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 4;
        $points_cc_1 = Db::name('Plog')->where($where3)->sum('plog_points');

        $where2=[];
        $where2['user_pid_2'] = ['eq', $param['uid']];
        $level_cc_2 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 5;
        $points_cc_2 = Db::name('Plog')->where($where3)->sum('plog_points');

        $where2=[];
        $where2['user_pid_3'] = ['eq', $param['uid']];
        $level_cc_3 = Db::name('User')->where($where2)->count();
        $where3 = [];
        $where3['user_id'] = $param['uid'];
        $where3['plog_type'] = 6;
        $points_cc_3 = Db::name('Plog')->where($where3)->sum('plog_points');

        $data=[];
        $data['level_cc_1'] = intval($level_cc_1);
        $data['level_cc_2'] = intval($level_cc_2);
        $data['level_cc_3'] = intval($level_cc_3);
        $data['points_cc_1'] = intval($points_cc_1);
        $data['points_cc_2'] = intval($points_cc_2);
        $data['points_cc_3'] = intval($points_cc_3);

        $this->assign('data',$data);
        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('title',lang('admin/user/title'));
        return $this->fetch('admin@user/reward');
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(isset($param['group_id']) && is_array($param['group_id'])) {
                $param['group_id'] = implode(',', $param['group_id']);
            }
            $res = model('User')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id/d');
        $where=[];
        $where['user_id'] = ['eq',$id];
        $res = model('User')->infoData($where);
        $info = $res['info'];

        $group_list = model('Group')->getCache('group_list');
        $group_ids = isset($info['group_id']) ? explode(',', $info['group_id']) : [];
        $has_vip_group = false;
        foreach($group_ids as $gid){
            if(intval($gid) > 2){
                $has_vip_group = true;
                break;
            }
        }
        $this->assign('info', $info);
        $this->assign('group_list', $group_list);
        $this->assign('has_vip_group', $has_vip_group);
        return $this->fetch('admin@user/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['user_id'] = ['in',$ids];
            $res = model('User')->delData($where);
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

        if(!empty($ids) && in_array($col,['user_status']) && in_array($val,['0','1'])){
            $where=[];
            $where['user_id'] = ['in',$ids];

            $res = model('User')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }




}
