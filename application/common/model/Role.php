<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Role extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'role';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getRoleStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['role_status']];
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1,$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        $list = Db::name('Role')->field($field)->where($where)->order($order)->limit($limit_str)->select();
        $vod_list=[];
        if($addition==1){
            $vod_ids=[];
            foreach($list as $k=>$v){
                $vod_ids[$v['role_rid']] = $v['role_rid'];
            }
            $where2=[];
            $where2['vod_id'] = ['in', implode(',',$vod_ids)];
            $tmp_list = model('Vod')->listData($where2,'vod_id desc',1,999,0);
            //$tmp_list = Db::name('Vod')->field('vod_id,vod_name,vod_en,type_id,type_id_1')->where($where2)->select();
            foreach($tmp_list['list'] as $k=>$v){
                $vod_list[$v['vod_id']] = $v;
            }
        }
        foreach($list as $k=>$v){
            if($addition==1){
                $list[$k]['data'] = $vod_list[$v['role_rid']];
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
        $ids = $lp['ids'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $wd = $lp['wd'];
        $actor = $lp['actor'];
        $name = $lp['name'];
        $rid = $lp['rid'];
        $letter = $lp['letter'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $half = intval(abs($lp['half']));
        $timeadd = $lp['timeadd'];
        $timehits = $lp['timehits'];
        $time = $lp['time'];
        $hitsmonth = $lp['hitsmonth'];
        $hitsweek = $lp['hitsweek'];
        $hitsday = $lp['hitsday'];
        $hits = $lp['hits'];
        $not = $lp['not'];
        $cachetime = $lp['cachetime'];
        $page = 1;
        $where = [];
        $totalshow=0;

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }
        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }
        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            if(!empty($param['rid'])) {
                $rid = intval($param['rid']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['level'])) {
                $level = $param['level'];
            }
            if(!empty($param['letter'])) {
                $letter = $param['letter'];
            }
            if(!empty($param['wd'])) {
                $wd = $param['wd'];
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
                $pageurl = 'role/index';
            }
            $param['page'] = 'PAGELINK';
            $pageurl = mac_url($pageurl,$param);

        }

        $where['role_status'] = ['eq',1];
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['role_level'] = ['in',explode(',',$level)];
        }
        if(!empty($ids)) {
            if($ids!='all'){
                $where['role_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['role_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['role_letter'] = ['in',explode(',',$letter)];
        }
        if(!empty($rid)) {
            $where['role_rid'] = ['eq',$rid];
        }
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['role_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['role_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['role_time'] =['gt',$s];
        }
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['role_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['role_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['role_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['role_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['role_hits'] = ['gt', $tmp];
            }
            else{
                $where['role_hits'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($actor)){
            $where['role_actor'] = ['in',explode(',',$actor) ];
        }
        if(!empty($name)){
            $where['role_name'] = ['in',explode(',',$name) ];
        }

        if(!empty($wd)) {
            $where['role_name|role_en'] = ['like', '%' . $wd . '%'];
        }
        if($by=='rnd'){
            $data_count = $this->countData($where);
            $page_total = floor($data_count / $lp['num']) + 1;
            if($data_count < $lp['num']){
                $lp['num'] = $data_count;
            }
            $randi = @mt_rand(1, $page_total);
            $page = $randi;
            $by = 'hits_week';
            $order = 'desc';
        }

        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'role_'.$by .' ' . $order;
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['role_id']);
            $where_cache['order'] = 'rnd';
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('role_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',1,$totalshow);
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cachetime);
            }
        }
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;
    }

    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $data_cache = false;
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'role_detail_'.$where['role_id'][1].'_'.$where['role_en'][1];
        if($where['role_id'][0]=='eq' || $where['role_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['role_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            $info['data'] = [];
            if(!empty($info['role_rid'])){
                $where2=[];
                $where2['vod_id'] = ['eq', $info['role_rid']];
                $vod_info = model('Vod')->infoData($where2);
                if($vod_info['code'] == 1){
                    $info['data'] = $vod_info['info'];
                }
            }
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Role');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        $key = 'role_detail_'.$data['role_id'];
        Cache::rm($key);
        $key = 'role_detail_'.$data['role_en'];
        Cache::rm($key);
        $key = 'role_detail_'.$data['role_id'].'_'.$data['role_en'];
        Cache::rm($key);


        if(empty($data['role_en'])){
            $data['role_en'] = Pinyin::get($data['role_name']);
        }

        if(empty($data['role_letter'])){
            $data['role_letter'] = strtoupper(substr($data['role_en'],0,1));
        }

        if(!empty($data['role_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['role_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['role_content'] = str_replace($v1, $v2, $data['role_content']);
                }
            }
            unset($match_src1);
        }

        if($data['uptime']==1){
            $data['role_time'] = time();
        }
        unset($data['uptime']);

        if(!empty($data['role_id'])){
            $where=[];
            $where['role_id'] = ['eq',$data['role_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['role_time_add'] = time();
            $data['role_time'] = time();
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
        $list = $this->where($where)->select();
        $path = './';
        foreach($list as $k=>$v){
            $pic = $path.$v['role_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
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

        $list = $this->field('role_id,role_name,role_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'role_detail_'.$v['role_id'];
            Cache::rm($key);
            $key = 'role_detail_'.$v['role_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}