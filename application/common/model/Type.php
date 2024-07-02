<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Type extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'type';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    //自定义初始化
    protected function initialize()
    {
        //需要调用`Model`的`initialize`方法
        parent::initialize();
        //TODO:自定义的初始化
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where,$order,$format='def',$mid=0,$limit=999,$start=0,$totalshow=1)
    {
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * (1-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        else{

        }
        $tmp = Db::name('Type')->where($where)->order($order)->limit($limit_str)->select();

        $list = [];
        $childs=[];
        foreach($tmp as $k=>$v){
            $v['type_extend'] = json_decode($v['type_extend'],true);
            $list[$v['type_id']] = $v;
            $childs[$v['type_pid']][] = $v['type_id'];
        }

        $rc=false;
        foreach($list as $k=>$v){
            if($v['type_pid']==0){
                if(!empty($where)){
                    if(!$rc){
                        $type_list = model('Type')->getCache('type_list');
                        $rc=true;
                    }
                    $list[$k]['childids'] = $type_list[$v['type_id']]['childids'];
                }
                else {
                    $list[$k]['childids'] = join(',', (array)$childs[$v['type_id']]);
                }
            }
            else {
                $list[$k]['type_1'] = $list[$v['type_pid']];
            }
        }
        if($mid>0){
            foreach($list as $k=>$v){
                if($v['type_mid'] !=$mid) {
                    unset($list[$k]);
                }
            }
        }

        if($format=='tree'){
            $list = mac_list_to_tree($list,'type_id','type_pid');
        }

        return ['code'=>1,'msg'=>lang('data_list'),'total'=>$total,'list'=>$list];
    }

    public function listCacheData($lp)
    {
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        $order = $lp['order'];
        $by = $lp['by'];
        $mid = $lp['mid'];
        $ids = $lp['ids'];
        $parent = $lp['parent'];
        $format = $lp['format'];
        $flag = $lp['flag'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $cachetime = $lp['cachetime'];
        $not = $lp['not'];
        $page=1;
        $where = [];


        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        if (!in_array($by, ['id', 'sort'])) {
            $by = 'id';
        }
        if (!in_array($format, ['def', 'tree'])) {
            $format = 'def';
        }
        if (in_array($mid, ['1', '2','8','11'])) {
            $where['type_mid'] = ['eq',$mid];
        }
        if(!empty($flag)){
            if($flag=='vod'){
                $where['type_mid'] = ['eq',1];
            }
            elseif($flag=='art'){
                $where['type_mid'] = ['eq',2];
            }
        }

        $param = mac_param_url();

        if (!empty($ids)) {
            if($ids=='parent'){
                $where['type_pid'] = ['eq',0];
            }
            elseif($ids=='child'){
                $where['type_pid'] = ['gt',0];
            }
            elseif($ids=='current'){
                $type_info = $this->getCacheInfo($param['id']);
                $doid = $param['id'];
                $childs = $type_info['childids'];
                if($type_info['type_pid']>0){//二级分类->一级
                    $doid = $type_info['type_pid'];
                    $type_info1 = $this->getCacheInfo($doid);
                    $childs = $type_info1['childids'];
                }

                $where['type_id'] = ['in',$childs];
            }
            else{
                $where['type_id'] = ['in',$ids];
            }
        }
        if(!empty($parent)){
            if($parent=='current'){
                $type_info = $this->getCacheInfo($param['id']);
                $parent = intval($type_info['type_id']);
                if($type_info['type_pid'] !=0){
                    //$parent = $type_info['type_pid'];
                }
            }
            $where['type_pid'] = ['in',$parent];
        }
        if(!empty($not)){
            $where['type_id'] = ['not in',$not];
        }

        if(defined('ENTRANCE') && ENTRANCE == 'index' && $GLOBALS['config']['app']['popedom_filter'] ==1){
            $type_ids = mac_get_popedom_filter($GLOBALS['user']['group']['group_type']);
            if(!empty($type_ids)){
                if(!empty($where['type_id'])){
                    $where['type_id'] = [ $where['type_id'],['not in', explode(',',$type_ids)] ];
                }
                else{
                    $where['type_id'] = ['not in', explode(',',$type_ids)];
                }
            }
        }

        $where['type_status'] = ['eq',1];

        $by = 'type_'.$by;
        $order = 'type_pid asc,'. $by . ' ' . $order;

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('type_listcache_'.http_build_query($where).'_'.$order.'_'.$num.'_'.$start);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$format,$mid,$num,$start,0);
            $res['list'] = array_values($res['list']);
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }

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

        if(!empty($info['type_extend'])){
            $info['type_extend'] = json_decode($info['type_extend'],true);
        }
        else{
            $info['type_extend'] = json_decode('{"type":"","area":"","lang":"","year":"","star":"","director":"","state":"","version":""}',true);
        }


        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Type');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if(!empty($data['type_extend'])){
            $data['type_extend'] = json_encode($data['type_extend']);
        }
        if(empty($data['type_en'])){
            $data['type_en'] = Pinyin::get($data['type_name']);
        }

        // xss过滤
        $filter_fields = [
            'type_name',
            'type_en',
            'type_tpl',
            'type_tpl_list',
            'type_tpl_detail',
            'type_tpl_play',
            'type_tpl_down',
            'type_key',
            'type_des',
            'type_title',
            'type_union',
            'type_logo',
            'type_pic',
            'type_jumpurl',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        if(!empty($data['type_id'])){
            $where=[];
            $where['type_id'] = ['eq',$data['type_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        $this->setCache();
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function delData($where)
    {
        $list = $this->where($where)->select();
        foreach($list as $k=>$v){
            $where2=[];
            $where2['type_id|type_id_1'] = ['eq',$v['type_id']];
            $flag = $v['type_mid'] == 1 ? 'Vod' : 'Art';
            $cc = model($flag)->where($where2)->count();
            if($cc > 0){
                return ['code'=>1021,'msg'=>lang('del_err').'：'. $v['type_name'].'还有'.$cc.'条数据，请先删除或转移' ];
            }
        }

        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }

        $this->setCache();
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
            return ['code'=>1002,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        $this->setCache();
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function moveData($where,$val)
    {
        $list = $this->where($where)->select();
        $type_info = $this->getCacheInfo($val);
        if(empty($type_info)){
            return ['code'=>1011,'msg'=>lang('model/type/to_info_err')];
        }
        foreach($list as $k=>$v){
            $where2=[];
            $where2['type_id|type_id_1'] = ['eq',$v['type_id']];
            $update=[];
            $update['type_id'] = $val;
            $update['type_id_1'] = $type_info['type_pid'];
            $flag = $v['type_mid'] == 1 ? 'Vod' : 'Art';
            $cc = model($flag)->where($where2)->update($update);
            if($cc ===false){
                return ['code'=>1012,'msg'=>lang('model/type/move_err').'：'. $v['type_name'].''.$this->getError()  ];
            }
        }
        return ['code'=>1,'msg'=>lang('model/type/move_ok')];
    }

    public function setCache()
    {
        $res = $this->listData([],'type_id asc');
        $list = $res['list'];
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'type_list';
        Cache::set($key,$list);

        $type_tree = mac_list_to_tree($list,'type_id','type_pid');
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'type_tree';
        Cache::set($key,$type_tree);
    }

    public function getCache($flag='type_list')
    {
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.$flag;
        $cache = Cache::get($key);
        if(empty($cache)){
            $this->setCache();
            $cache = Cache::get($key);
        }
        return $cache;
    }

    public function getCacheInfo($id)
    {
        $type_list = $this->getCache('type_list');
        if(is_numeric($id)) {
            return $type_list[$id];
        }
        else{

            foreach($type_list as $k=>$v){
                if($v['type_en'] == $id){
                    return $type_list[$k];
                }
            }
        }
    }



}