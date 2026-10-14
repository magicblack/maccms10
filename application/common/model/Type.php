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
        $names = $lp['names'];
        $parent = $lp['parent'];
        $format = $lp['format'];
        $flag = $lp['flag'];
        $start = abs(intval($lp['start']));
        $num = abs(intval($lp['num']));
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
        if (in_array($mid, ['1', '2', '8', '11', '12'])) {
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
        // 按名称查询：仅展示名称在列表中的分类，查不到的不展示
        if(!empty($names)){
            $name_arr = array_map('trim', explode(',', $names));
            $name_arr = array_filter($name_arr);
            if(!empty($name_arr)){
                $where['type_name'] = ['in', $name_arr];
            }
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

        // 内容级多语言：{maccms:type} 导航标签走这里而非 getCache()，缓存 key 要带上当前内容语言，
        // 否则先渲染的语言会把译文/原文串给其它语言
        $lang = \app\common\model\ContentLang::getCurrent();
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('type_listcache_'.$lang.'_'.http_build_query($where).'_'.$order.'_'.$num.'_'.$start);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            // 展示字段的译文覆盖要在 tree 组装之前，按 type_id 平铺替换后再转树
            $res = $this->listData($where,$order,'def',$mid,$num,$start,0);
            $res['list'] = $this->langOverlayTypeList(array_values($res['list']), $lang);
            if($format=='tree'){
                $res['list'] = mac_list_to_tree($res['list'],'type_id','type_pid');
            }
            if($GLOBALS['config']['app']['cache_core']==1) {
                // 打上表名标签，写路径才能定点作废这一族列表缓存（见 Base::clearListCache()）
                Cache::tag($this->name)->set($cach_name, $res, $cachetime);
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
        $info = mac_content_lang_overlay('type', $info);

        if(!empty($info['type_extend'])){
            //overlay 回填的 type_extend 可能已是数组（译文按数组存进 content_lang.data）
            $info['type_extend'] = is_array($info['type_extend']) ? $info['type_extend'] : json_decode($info['type_extend'],true);
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
            if(false !== $res){
                $data['type_id'] = $this->getLastInsID();
            }
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        $this->setCache();
        $this->clearListCache();
        return ['code'=>1,'msg'=>lang('save_ok'),'type_id'=>$data['type_id']];
    }

    public function delData($where)
    {
        $list = $this->where($where)->select();
        $delIds = [];
        foreach($list as $k=>$v){
            $delIds[] = intval($v['type_id']);
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
        \app\common\model\ContentLang::deleteByContent('type', $delIds);

        $this->setCache();
        $this->clearListCache();
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
        $this->clearListCache();
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
        $flag = $GLOBALS['config']['app']['cache_flag'];
        Cache::set($flag.'_type_list',$list);
        Cache::set($flag.'_type_tree', mac_list_to_tree($list,'type_id','type_pid'));

        // 内容级多语言：前台分类走 getCacheInfo()→getCache('type_list'/'type_tree') 这份缓存，
        // 不经过 infoData()，所以在这里为每个已启用的非默认语言各存一份 overlay 过的变体。
        // overlay 只在建缓存时批量跑一次（getFieldsMap 一条 SQL），无逐请求/逐行查询。
        $default = mac_content_lang_default();
        $langs = mac_content_lang_allow_list();
        foreach ($langs as $lang) {
            if ($lang === $default) {
                continue;
            }
            $langList = $this->langOverlayTypeList($list, $lang);
            Cache::set($flag.'_'.$lang.'_type_list', $langList);
            Cache::set($flag.'_'.$lang.'_type_tree', mac_list_to_tree($langList,'type_id','type_pid'));
        }
    }

    /**
     * 把分类列表里的纯展示字段替换成指定内容语言的译文（内容级多语言）。
     * 只覆盖 type_name/type_title/type_key/type_des/type_extend：type_en 是 URL slug、
     * getCacheInfo() 与路由按它匹配，type_pid/childids 等结构字段也必须保持原值。
     * $lang 为空时取当前请求语言；非默认且已启用才生效，否则原样返回。
     */
    protected function langOverlayTypeList(array $list, $lang = null)
    {
        $lang = $lang ?: \app\common\model\ContentLang::getCurrent();
        if ($lang === mac_content_lang_default() || !in_array($lang, mac_content_lang_allow_list(), true)) {
            return $list;
        }
        $map = model('ContentLang')->getFieldsMap('type', $lang);
        if (empty($map)) {
            return $list;
        }
        $fields = ['type_name', 'type_title', 'type_key', 'type_des', 'type_extend'];
        $apply = function ($row) use ($map, $fields) {
            $tid = isset($row['type_id']) ? $row['type_id'] : 0;
            if (empty($map[$tid])) {
                return $row;
            }
            foreach ($fields as $f) {
                $tr = isset($map[$tid][$f]) ? $map[$tid][$f] : null;
                if ($tr === null || $tr === '') {
                    continue;
                }
                $orig = $row[$f] ?? null;
                $row[$f] = ($f === 'type_extend' && !is_array($tr)) ? json_decode($tr, true) : $tr;
                // cover（封面比例）是非翻译的展示设置，译文里没有这个键，保留原值
                if ($f === 'type_extend' && is_array($orig) && isset($orig['cover'])) {
                    if (!is_array($row[$f])) {
                        $row[$f] = [];
                    }
                    $row[$f]['cover'] = $orig['cover'];
                }
            }
            return $row;
        };
        foreach ($list as $k => $row) {
            $row = $apply($row);
            // 子分类里冗余的父级副本（模板常用 $vo.type_1.type_name）也同步覆盖
            if (!empty($row['type_1']) && is_array($row['type_1'])) {
                $row['type_1'] = $apply($row['type_1']);
            }
            $list[$k] = $row;
        }
        return $list;
    }

    public function getCache($flag='type_list')
    {
        $prefix = $GLOBALS['config']['app']['cache_flag'];

        // 前台切到非默认内容语言时优先取 overlay 变体（后台/采集 getCurrent()==default，自然取原缓存）
        // 仅对 setCache() 真正构建了变体的 flag 生效，避免其它 flag 白跑一遍 setCache()
        $lang = \app\common\model\ContentLang::getCurrent();
        if (in_array($flag, ['type_list', 'type_tree'], true)
            && $lang !== mac_content_lang_default()
            && in_array($lang, mac_content_lang_allow_list(), true)) {
            $langKey = $prefix.'_'.$lang.'_'.$flag;
            $cache = Cache::get($langKey);
            if (empty($cache)) {
                $this->setCache();
                $cache = Cache::get($langKey);
            }
            if (!empty($cache)) {
                return $cache;
            }
        }

        $key = $prefix.'_'.$flag;
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