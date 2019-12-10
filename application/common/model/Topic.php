<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Topic extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'topic';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';
    protected $autoWriteTimestamp = true;

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getTopicStatusTextAttr($val,$data)
    {
        $arr = [0=>'禁用',1=>'启用'];
        return $arr[$data['topic_status']];
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where,$order,$page=1,$limit=20,$start=0,$field='*',$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        $tmp = Db::name('Topic')->where($where)->order($order)->limit($limit_str)->select();

        $list = [];
        foreach($tmp as $k=>$v){
            $list[$v['topic_id']] = $v;
        }

        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
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
        $letter = $lp['letter'];
        $tag = $lp['tag'];
        $class = $lp['class'];
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
        $totalshow = 0;

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        if(!in_array($paging, ['yes', 'no'])) {
            $paging = 'no';
        }

        if($paging=='yes') {
            $totalshow = 1;
            $param = mac_param_url();
            if (!empty($param['id'])) {
                $ids = intval($param['id']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['level'])) {
                if($param['level']=='all'){
                    $level = '1,2,3,4,5,6,7,8,9';
                }
                else{
                    $level = $param['level'];
                }
            }
            if(!empty($param['letter'])) {
                $letter = $param['letter'];
            }
            if(!empty($param['wd'])) {
                $wd = $param['wd'];
            }
            if(!empty($param['tag'])) {
                $tag = $param['tag'];
            }
            if(!empty($param['class'])) {
                $class = $param['class'];
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
                $pageurl = 'topic/index';
            }
            $param['page'] = 'PAGELINK';
            $pageurl = mac_url($pageurl,$param);

        }

        $where['topic_status'] = ['eq',1];
        if(!empty($level)) {
            $where['topic_level'] = ['in',explode(',',$level)];
        }
        if(!empty($ids)) {
            if($ids!='all'){
                $where['topic_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['topic_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['topic_letter'] = ['in',explode(',',$letter)];
        }
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['topic_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['topic_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['topic_time'] =['gt',$s];
        }
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['topic_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['topic_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['topic_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['topic_hits'] = ['gt', $tmp];
            }
            else{
                $where['topic_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        if(!empty($wd)) {
            $where['topic_name|topic_en|topic_sub'] = ['like', '%' . $wd . '%'];
        }
        if(!empty($tag)) {
            $where['topic_tag'] = ['like', mac_like_arr($tag),'OR'];
        }
        if(!empty($class)) {
            $where['topic_type'] = ['like', mac_like_arr($class),'OR'];
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
        $order= 'topic_'.$by .' ' . $order;
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['topic_id']);
            $where_cache['order'] = 'rnd';
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('topic_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',$totalshow);
            $cache_time = $GLOBALS['config']['app']['cache_time'];
            if(intval($cachetime)>0){
                $cache_time = $cachetime;
            }
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($cach_name, $res, $cache_time);
            }
        }
        $res['pageurl'] = $pageurl;
        $res['half'] = $half;
        return $res;
    }

    public function infoData($where,$field='*',$cache=0)
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>'参数错误'];
        }
        $key = 'topic_detail_'.$where['topic_id'][1].'_'.$where['topic_en'][1];
        $info = Cache::get($key);

        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['topic_id']) ) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => '获取数据失败'];
            }
            $info = $info->toArray();
            if (!empty($info['topic_extend'])) {
                $info['topic_extend'] = json_decode($info['topic_extend'], true);
            } else {
                $info['topic_extend'] = json_decode('{"type":"","area":"","lang":"","year":"","star":"","director":"","state":"","version":""}', true);
            }
            $info['vod_list'] = [];
            $info['art_list'] = [];

            if (!empty($info['topic_rel_vod'])) {
                $where = [];
                $where['vod_id'] = ['in', $info['topic_rel_vod']];
                $where['vod_status'] = ['eq', 1];
                $order = 'vod_time desc';
                $field = '*';
                $res = model('Vod')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['vod_list'] = $res['list'];
                }
            }
            if (!empty($info['topic_rel_art'])) {
                $where = [];
                $where['art_id'] = ['in', $info['topic_rel_art']];
                $where['art_status'] = ['eq', 1];
                $order = 'art_time desc';
                $field = '*';
                $res = model('Art')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['art_list'] = $res['list'];
                }
            }

            if (!empty($info['topic_tag'])) {
                $where=[];
                $where['vod_tag'] = ['like', mac_like_arr($info['topic_tag']),'OR'];
                $where['vod_status'] = ['eq', 1];
                $order = 'vod_time desc';
                $field = '*';
                $res = model('Vod')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['vod_list'] = array_merge($info['vod_list'],$res['list']);
                }

                $where=[];
                $where['art_tag'] = ['like', mac_like_arr($info['topic_tag']),'OR'];
                $where['art_status'] = ['eq', 1];
                $order = 'art_time desc';
                $field = '*';
                $res = model('Art')->listData($where, $order, 1, 999, 0, $field);
                if ($res['code'] == 1) {
                    $info['art_list'] = array_merge($info['art_list'],$res['list']);
                }
            }
            if($GLOBALS['config']['app']['cache_core']==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Topic');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>'参数错误：'.$validate->getError() ];
        }
        $key = 'topic_detail_'.$data['topic_id'];
        Cache::rm($key);
        $key = 'topic_detail_'.$data['topic_en'];
        Cache::rm($key);
        $key = 'topic_detail_'.$data['topic_id'].'_'.$data['topic_en'];
        Cache::rm($key);


        if(empty($data['topic_extend'])){
            $data['topic_extend'] = '';
        }
        if(empty($data['topic_en'])){
            $data['topic_en'] = Pinyin::get($data['topic_name']);
        }

        if(!empty($data['topic_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['topic_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['topic_content'] = str_replace($v1, $v2, $data['topic_content']);
                }
            }
            unset($match_src1);
        }

        if(empty($data['topic_blurb'])){
            $data['topic_blurb'] = mac_substring( strip_tags($data['topic_content']) ,100);
        }

        if($data['uptime']==1){
            $data['topic_time'] = time();
        }
        unset($data['uptime']);

        if(!empty($data['topic_id'])){
            $where=[];
            $where['topic_id'] = ['eq',$data['topic_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['topic_time_add'] = time();
            $data['topic_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>'删除失败：'.$this->getError() ];
        }
        $list = $this->where($where)->select();
        $path = './';
        foreach($list as $k=>$v){
            if(file_exists($path.$v['topic_pic'])){
                unlink($path.$v['topic_pic']);
            }
            if(file_exists($path.$v['topic_pic_thumb'])){
                unlink($path.$v['topic_pic_thumb']);
            }
            if(file_exists($path.$v['topic_pic_slide'])){
                unlink($path.$v['topic_pic_slide']);
            }
        }
        return ['code'=>1,'msg'=>'删除成功'];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>'参数错误'];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1002,'msg'=>'设置失败：'.$this->getError() ];
        }

        $list = $this->field('topic_id,topic_name,topic_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'topic_detail_'.$v['topic_id'];
            Cache::rm($key);
            $key = 'topic_detail_'.$v['topic_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>'设置成功'];
    }



}