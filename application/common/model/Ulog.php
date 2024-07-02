<?php
namespace app\common\model;
use think\Db;

class Ulog extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'ulog';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $total = $this->where($where)->count();
        $list = Db::name('Ulog')->where($where)->order($order)->limit($limit_str)->select();

        $user_ids=[];
        foreach($list as $k=>&$v){
            if($v['user_id'] >0){
                $user_ids[$v['user_id']] = $v['user_id'];
            }

            if($v['ulog_mid']==1){
                $vod_info = model('Vod')->infoData(['vod_id'=>['eq',$v['ulog_rid']]],'*',1);

                if($v['ulog_sid']>0 && $v['ulog_nid']>0){
                    if($v['ulog_type']==5){
                        $vod_info['info']['link'] = mac_url_vod_down($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                    else{
                        $vod_info['info']['link'] = mac_url_vod_play($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                }
                else{
                    $vod_info['info']['link'] = mac_url_vod_detail($vod_info['info']);
                }
                $v['data'] = [
                    'id'=>$vod_info['info']['vod_id'],
                    'name'=>$vod_info['info']['vod_name'],
                    'pic'=>mac_url_img($vod_info['info']['vod_pic']),
                    'link'=>$vod_info['info']['link'],
                    'type'=>[
                        'type_id'=>$vod_info['info']['type']['type_id'],
                        'type_name'=>$vod_info['info']['type']['type_name'],
                        'link'=>mac_url_type($vod_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==2){
                $art_info = model('Art')->infoData(['art_id'=>['eq',$v['ulog_rid']]],'*',1);
                $art_info['info']['link'] = mac_url_art_detail($art_info['info']);
                $v['data'] = [
                    'id'=>$art_info['info']['art_id'],
                    'name'=>$art_info['info']['art_name'],
                    'pic'=>mac_url_img($art_info['info']['art_pic']),
                    'link'=>$art_info['info']['link'],
                    'type'=>[
                        'type_id'=>$art_info['info']['type']['type_id'],
                        'type_name'=>$art_info['info']['type']['type_name'],
                        'link'=>mac_url_type($art_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==3){
                $topic_info = model('Topic')->infoData(['topic_id'=>['eq',$v['ulog_rid']]],'*',1);
                $topic_info['info']['link'] = mac_url_topic_detail($topic_info['info']);
                $v['data'] = [
                    'id'=>$topic_info['info']['topic_id'],
                    'name'=>$topic_info['info']['topic_name'],
                    'pic'=>mac_url_img($topic_info['info']['topic_pic']),
                    'link'=>$topic_info['info']['link'],
                    'type'=>[],
                ];
            }
            elseif($v['ulog_mid']==8){
                $actor_info = model('Actor')->infoData(['actor_id'=>['eq',$v['ulog_rid']]],'*',1);
                $actor_info['info']['link'] = mac_url_actor_detail($actor_info['info']);
                $v['data'] = [
                    'id'=>$actor_info['info']['actor_id'],
                    'name'=>$actor_info['info']['actor_name'],
                    'pic'=>mac_url_img($actor_info['info']['actor_pic']),
                    'link'=>$actor_info['info']['link'],
                    'type'=>[],
                ];
            }
        }

        if(!empty($user_ids)){
            $where2=[];
            $where['user_id'] = ['in', $user_ids];
            $order='user_id desc';
            $user_list = model('User')->listData($where2,$order,1,999);
            $user_list = mac_array_rekey($user_list['list'],'user_id');

            foreach($list as $k=>&$v){
                $list[$k]['user_name'] = $user_list[$v['user_id']]['user_name'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $data['user_id'] = intval(cookie('user_id'));
        $data['ulog_time'] = time();

        $validate = \think\Loader::validate('Ulog');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if($data['user_id']==0 || !in_array($data['ulog_mid'],['1','2','3','8']) || !in_array($data['ulog_type'],['1','2','3','4','5']) ) {
            return ['code'=>1002,'msg'=>lang('param_err')];
        }

        if(!empty($data['ulog_id'])){
            $where=[];
            $where['ulog_id'] = ['eq',$data['ulog_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1004,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}