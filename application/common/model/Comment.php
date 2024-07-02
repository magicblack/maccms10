<?php
namespace app\common\model;
use think\Db;

class Comment extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'comment';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getCommentStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['comment_status']];
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        $list = Db::name('Comment')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        $user_ids=[];
        foreach($list as $k=>$v){
            $list[$k]['user_portrait'] = mac_get_user_portrait($v['user_id']);
            $list[$k]['comment_content'] = mac_restore_htmlfilter($list[$k]['comment_content']);

            $where2=[];
            $where2['comment_pid'] = $v['comment_id'];
            $where2['comment_status'] = ['eq',1];
            $sub = Db::name('Comment')->where($where2)->order($order)->select();
            $list[$k]['sub'] = $sub;
            foreach($sub as $k2=>$v2){
                $list[$k]['sub'][$k2]['user_portrait'] = mac_get_user_portrait($v2['user_id']);
                $list[$k]['sub'][$k2]['comment_content'] = mac_restore_htmlfilter($list[$k]['sub'][$k2]['comment_content']);
            }
            $list[$k]['data'] = [];
            if($v['comment_mid'] == 1){
                $where3=[];
                $where3['vod_id'] = ['eq',$v['comment_rid']];
                $vod = model('Vod')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
            elseif($v['comment_mid'] == 2){
                $where3=[];
                $where3['art_id'] = ['eq',$v['comment_rid']];
                $vod = model('Art')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
            elseif($v['comment_mid'] == 3){
                $where3=[];
                $where3['topic_id'] = ['eq',$v['comment_rid']];
                $vod = model('Topic')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
            elseif($v['comment_mid'] == 8){
                $where3=[];
                $where3['actor_id'] = ['eq',$v['comment_rid']];
                $vod = model('Actor')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
            elseif($v['comment_mid'] == 9){
                $where3=[];
                $where3['role_id'] = ['eq',$v['comment_rid']];
                $vod = model('Role')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
            elseif($v['comment_mid'] == 11){
                $where3=[];
                $where3['website_id'] = ['eq',$v['comment_rid']];
                $vod = model('Website')->infoData($where3);
                $list[$k]['data'] = $vod['info'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function listCacheData($lp)
    {
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        $order = $lp['order'];
        $by = $lp['by'];
        $paging = $lp['paging'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $rid = intval(abs($lp['rid']));
        $pid = intval(abs($lp['pid']));
        $mid = intval(abs($lp['mid']));
        $uid = intval(abs($lp['uid']));
        $half = intval(abs($lp['half']));
        $pageurl = $lp['pageurl'];
        $page = 1;
        $where = [];

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if (!in_array($mid, ['1','2','3','8','9'])) {
            //$mid = 1;
        }

        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        if($paging=='yes') {
            $param = mac_param_url();
            if(!empty($param['mid'])){
                $mid = $param['mid'];
            }
            if(!empty($param['rid'])){
                $rid = $param['rid'];
            }
            if(!empty($param['pid'])){
                $pid = $param['pid'];
            }
            if(!empty($param['by'])){
                $by = $param['by'];
            }
            if(!empty($param['order'])){
                $order = $param['order'];
            }
            if(!empty($param['page'])){
                $page = intval($param['page']);
            }

            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }
            if(empty($pageurl)){
                $pageurl = 'comment/index';
            }
            $param['page'] = 'PAGELINK';
            $pageurl = mac_url($pageurl,$param);
        }

        $where['comment_status'] = ['eq',1];
        $where['comment_pid'] = ['eq',0];

        if(!empty($rid)){
            $where['comment_rid'] = ['eq',$rid];
        }
        if(!empty($pid)){
            $where['comment_pid'] = ['eq',$pid];
        }
        if(!empty($uid)){
            $where['user_id'] = ['eq',$uid];
        }
        if(!empty($mid)){
            $where['comment_mid'] = ['eq',$mid];
        }

        if(!in_array($by, ['id', 'time','up','down'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'comment_'.$by .' ' . $order;

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('comment_listcache_'.join('&',$where).'_'.$order.'_'.$page.'_'.$num.'_'.$start);

        $res = $this->listData($where,$order,$page,$num,$start);
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;

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
        $validate = \think\Loader::validate('Comment');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        // xss过滤
        $filter_fields = [
            'comment_name',
            'comment_content',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        if(!empty($data['comment_id'])){
            $where=[];
            $where['comment_id'] = ['eq',$data['comment_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['comment_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
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