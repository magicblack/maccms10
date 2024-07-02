<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;
use app\common\validate\Vod as VodValidate;

class Vod extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'vod';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function countData($where)
    {
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }
        $total = $this->where($where)->where($where2)->count();
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
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->where($where)->where($where2)->count();
        }

        $list = Db::name('Vod')->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();

        //分类
        $type_list = model('Type')->getCache('type_list');
        //用户组
        $group_list = model('Group')->getCache('group_list');

        foreach($list as $k=>$v){
            if($addition==1){
	            if(!empty($v['type_id'])) {
	                $list[$k]['type'] = $type_list[$v['type_id']];
                    $list[$k]['type_1'] = $type_list[$list[$k]['type']['type_pid']];
	            }
	            if(!empty($v['group_id'])) {
	                $list[$k]['group'] = $group_list[$v['group_id']];
	            }
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function listRepeatData($where,$order,$page=1,$limit=20,$start=0,$field='*',$addition=1)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        $total = $this
            ->join('tmpvod t','t.name1 = vod_name')
            ->where($where)
            ->count();

        $list = Db::name('Vod')
            ->join('tmpvod t','t.name1 = vod_name')
            ->field($field)
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();

        //分类
        $type_list = model('Type')->getCache('type_list');
        //用户组
        $group_list = model('Group')->getCache('group_list');

        foreach($list as $k=>$v){
            if($addition==1){
                if(!empty($v['type_id'])) {
                    $list[$k]['type'] = $type_list[$v['type_id']];
                    $list[$k]['type_1'] = $type_list[$list[$k]['type']['type_pid']];
                }
                if(!empty($v['group_id'])) {
                    $list[$k]['group'] = $group_list[$v['group_id']];
                }
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function listCacheData($lp)
    {
        if(!is_array($lp)){
            $lp = json_decode($lp,true);
        }

        $order = $lp['order'];
        $by = $lp['by'];
        $type = $lp['type'];
        $ids = $lp['ids'];
        $rel = $lp['rel'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $area = $lp['area'];
        $lang = $lp['lang'];
        $state = $lp['state'];
        $wd = $lp['wd'];
        $tag = $lp['tag'];
        $class = $lp['class'];
        $letter = $lp['letter'];
        $actor = $lp['actor'];
        $director = $lp['director'];
        $version = $lp['version'];
        $year = $lp['year'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $half = intval(abs($lp['half']));
        $weekday = $lp['weekday'];
        $tv = $lp['tv'];
        $timeadd = $lp['timeadd'];
        $timehits = $lp['timehits'];
        $time = $lp['time'];
        $hitsmonth = $lp['hitsmonth'];
        $hitsweek = $lp['hitsweek'];
        $hitsday = $lp['hitsday'];
        $hits = $lp['hits'];
        $not = $lp['not'];
        $cachetime = $lp['cachetime'];
        $isend = $lp['isend'];
        $plot = $lp['plot'];
        $typenot = $lp['typenot'];
        $name = $lp['name'];

        $page = 1;
        $where=[];
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

        $param = mac_param_url();
        if($paging=='yes') {
            $param = mac_search_len_check($param);
            $totalshow = 1;
            if(!empty($param['id'])){
                //$type = intval($param['id']);
            }
            if(!empty($param['level'])){
                $level = $param['level'];
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['tid'])) {
                $tid = intval($param['tid']);
            }
            if(!empty($param['year'])){
                if(strlen($param['year'])==4){
                    $year = intval($param['year']);
                }
                elseif(strlen($param['year'])==9){
                    $s=substr($param['year'],0,4);
                    $e=substr($param['year'],5,4);
                    $s1 = intval($s);$s2 = intval($e);
                    if($s1>$s2){
                        $s1 = intval($e);$s2 = intval($s);
                    }

                    $tmp=[];
                    for($i=$s1;$i<=$s2;$i++){
                        $tmp[] = $i;
                    }
                    $year = join(',',$tmp);
                }
            }
            if(!empty($param['area'])){
                $area = $param['area'];
            }
            if(!empty($param['lang'])){
                $lang = $param['lang'];
            }
            if(!empty($param['tag'])){
                $tag = $param['tag'];
            }
            if(!empty($param['class'])){
                $class = $param['class'];
            }
            if(!empty($param['state'])){
                $state = $param['state'];
            }
            if(!empty($param['letter'])){
                $letter = $param['letter'];
            }
            if(!empty($param['version'])){
                $version = $param['version'];
            }
            if(!empty($param['actor'])){
                $actor = $param['actor'];
            }
            if(!empty($param['director'])){
                $director = $param['director'];
            }
            if(!empty($param['wd'])){
                $wd = $param['wd'];
            }
            if(!empty($param['name'])){
                $name = $param['name'];
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
            if(isset($param['isend'])){
                $isend = intval($param['isend']);
            }

            foreach($param as $k=>$v){
                if(empty($v)){
                    unset($param[$k]);
                }
            }
            if(empty($pageurl)){
                $pageurl = 'vod/type';
            }
            $param['page'] = 'PAGELINK';

            if($pageurl=='vod/type' || $pageurl=='vod/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'vod/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }
        }
        $where['vod_status'] = ['eq',1];
        if(!empty($ids)) {
            if($ids!='all'){
                $where['vod_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['vod_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($rel)){
            $tmp = explode(',',$rel);
            if(is_numeric($rel) || mac_array_check_num($tmp)==true  ){
                $where['vod_id'] = ['in',$tmp];
            }
            else{
                $where['vod_rel_vod'] = ['like', mac_like_arr($rel),'OR'];
            }
        }
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['vod_level'] = ['in',explode(',',$level)];
        }
        if(!empty($year)) {
            $where['vod_year'] = ['in',explode(',',$year)];
        }
        if(!empty($area)) {
            $where['vod_area'] = ['in',explode(',',$area)];
        }
        if(!empty($lang)) {
            $where['vod_lang'] = ['in',explode(',',$lang)];
        }
        if(!empty($state)) {
            $where['vod_state'] = ['in',explode(',',$state)];
        }
        if(!empty($version)) {
            $where['vod_version'] = ['in',explode(',',$version)];
        }
        if(!empty($weekday)){
            //$where['vod_weekday'] = ['in',explode(',',$weekday)];
            $where['vod_weekday'] = ['like', mac_like_arr($weekday),'OR'];
        }
        if(!empty($tv)){
            $where['vod_tv'] = ['in',explode(',',$tv)];
        }
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['vod_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['vod_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['vod_time'] =['gt',$s];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['vod_letter'] = ['in',explode(',',$letter)];
        }
        if(!empty($type)) {
            if($type=='current'){
                $type = intval( $GLOBALS['type_id'] );
            }
            if($type!='all') {
                $tmp_arr = explode(',',$type);
                $type_list = model('Type')->getCache('type_list');
                $type = [];
                foreach($type_list as $k2=>$v2){
                    if(in_array($v2['type_id'].'',$tmp_arr) || in_array($v2['type_pid'].'',$tmp_arr)){
                        $type[]=$v2['type_id'];
                    }
                }
                $type = array_unique($type);
                $where['type_id'] = ['in', implode(',',$type) ];
            }
        }
        if(!empty($typenot)){
            $where['type_id'] = ['not in',$typenot];
        }
        if(!empty($tid)) {
            $where['type_id|type_id_1'] = ['eq',$tid];
        }
        if(!in_array($GLOBALS['aid'],[13,14,15]) && !empty($param['id'])){
            //$where['vod_id'] = ['not in',$param['id']];
        }

        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['vod_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['vod_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['vod_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['vod_hits'] = ['gt', $tmp];
            }
            else{
                $where['vod_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        if(in_array($isend,['0','1'])){
            $where['vod_isend'] = $isend;
        }

        $vod_search = model('VodSearch');
        $vod_search_enabled = $vod_search->isFrontendEnabled();
        $max_id_count = $vod_search->maxIdCount;
        if ($vod_search_enabled) {
            // 开启搜索优化，查询并缓存Id
            $search_id_list = [];
            if(!empty($wd)) {
                $role = 'vod_name';
                if(!empty($GLOBALS['config']['app']['search_vod_rule'])){
                    $role .= '|'.$GLOBALS['config']['app']['search_vod_rule'];
                }
                $where[$role] = ['like', '%' . $wd . '%'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($wd, $role)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where[$role]);
                }
            }
            if(!empty($name)) {
                $where['vod_name'] = ['like',mac_like_arr($name),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($name, 'vod_name')) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_name']);
                }
            }
            if(!empty($tag)) {
                $where['vod_tag'] = ['like',mac_like_arr($tag),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($tag, 'vod_tag', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_tag']);
                }
            }
            if(!empty($class)) {
                $where['vod_class'] = ['like',mac_like_arr($class), 'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($class, 'vod_class', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_class']);
                }
            }
            if(!empty($actor)) {
                $where['vod_actor'] = ['like', mac_like_arr($actor), 'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($actor, 'vod_actor', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_actor']);
                }
            }
            if(!empty($director)) {
                $where['vod_director'] = ['like',mac_like_arr($director),'OR'];
                if (count($search_id_list_tmp = $vod_search->getResultIdList($director, 'vod_director', true)) <= $max_id_count) {
                    $search_id_list += $search_id_list_tmp;
                    unset($where['vod_director']);
                }
            }
            $search_id_list = array_unique($search_id_list);
            if (!empty($search_id_list)) {
                $where['_string'] = "vod_id IN (" . join(',', $search_id_list) . ")";
            }
        } else {
            // 不开启搜索优化，使用默认条件
            if(!empty($wd)) {
                $role = 'vod_name';
                if(!empty($GLOBALS['config']['app']['search_vod_rule'])){
                    $role .= '|'.$GLOBALS['config']['app']['search_vod_rule'];
                }
                $where[$role] = ['like', '%' . $wd . '%'];
            }
            if(!empty($name)) {
                $where['vod_name'] = ['like',mac_like_arr($name),'OR'];
            }
            if(!empty($tag)) {
                $where['vod_tag'] = ['like',mac_like_arr($tag),'OR'];
            }
            if(!empty($class)) {
                $where['vod_class'] = ['like',mac_like_arr($class), 'OR'];
            }
            if(!empty($actor)) {
                $where['vod_actor'] = ['like', mac_like_arr($actor), 'OR'];
            }
            if(!empty($director)) {
                $where['vod_director'] = ['like',mac_like_arr($director),'OR'];
            }
        }
        if(in_array($plot,['0','1'])){
            $where['vod_plot'] = $plot;
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
        // 优化随机视频排序rnd的性能问题
        // https://github.com/magicblack/maccms10/issues/967
        $use_rand = false;
        if($by=='rnd'){
            $use_rand = true;
            $algo2_threshold = 2000;
            $data_count = $this->countData($where);
            $where_string_addon = "";
            if ($data_count > $algo2_threshold) {
                $rows = $this->field("vod_id")->where($where)->select();
                foreach ($rows as $row) {
                    $id_list[] = $row['vod_id'];
                }
                if (
                    !empty($id_list)
                ) {
                    $random_count = intval($algo2_threshold / 2);
                    $specified_list = array_rand($id_list, intval($algo2_threshold / 2));
                    $random_keys = array_rand($id_list, $random_count);
                    $specified_list = [];

                    if ($random_count == 1) {
                        $specified_list[] = $id_list[$random_keys];
                    } else {
                        foreach ($random_keys as $key) {
                            $specified_list[] = $id_list[$key];
                        }
                    }
                    if (!empty($specified_list)) {
                        $where_string_addon = " AND vod_id IN (" . join(',', $specified_list) . ")";
                    }
                }
            }
            if (!empty($where_string_addon)) {
                $where['_string'] .= $where_string_addon;
                $where['_string'] = trim($where['_string'], " AND ");
            } else {
                if ($data_count % $lp['num'] === 0) {
                    $page_total = floor($data_count / $lp['num']);
                } else {
                    $page_total = floor($data_count / $lp['num']) + 1;
                }
                if($data_count < $lp['num']){
                    $lp['num'] = $data_count;
                }
                $randi = @mt_rand(1, $page_total);
                $page = $randi;
            }
            $by = 'hits_week';
            $order = 'desc';
        }

        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }
        $order= 'vod_'.$by .' ' . $order;
        $where_cache = $where;
        if($use_rand){
            unset($where_cache['vod_id']);
            $where_cache['order'] = 'rnd';
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('vod_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where, $order, $page, $num, $start,'*',1, $totalshow);
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
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'vod_detail_'.$where['vod_id'][1].'_'.$where['vod_en'][1];
        if($where['vod_id'][0]=='eq' || $where['vod_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }

        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['vod_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            $info['vod_play_list']=[];
            $info['vod_down_list']=[];
            $info['vod_plot_list']=[];
            $info['vod_pic_screenshot_list']=[];

            if (!empty($info['vod_play_from'])) {
                $info['vod_play_list'] = mac_play_list($info['vod_play_from'], $info['vod_play_url'], $info['vod_play_server'], $info['vod_play_note'], 'play');
            }
            if (!empty($info['vod_down_from'])) {
                $info['vod_down_list'] = mac_play_list($info['vod_down_from'], $info['vod_down_url'], $info['vod_down_server'], $info['vod_down_note'], 'down');
            }
            if (!empty($info['vod_plot_name'])) {
                $info['vod_plot_list'] = mac_plot_list($info['vod_plot_name'], $info['vod_plot_detail']);
            }
            if(!empty($info['vod_pic_screenshot'])){
                $info['vod_pic_screenshot_list'] = mac_screenshot_list($info['vod_pic_screenshot']);
            }


            //分类
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
            }
            //用户组
            if (!empty($info['group_id'])) {
                $group_list = model('Group')->getCache('group_list');
                $info['group'] = $group_list[$info['group_id']];
            }
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Vod');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }
        $key = 'vod_detail_'.$data['vod_id'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_en'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_id'].'_'.$data['vod_en'];
        Cache::rm($key);

        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        if(empty($data['vod_en'])){
            $data['vod_en'] = Pinyin::get($data['vod_name']);
        }

        if(empty($data['vod_letter'])){
            $data['vod_letter'] = strtoupper(substr($data['vod_en'],0,1));
        }

        if(!empty($data['vod_content'])) {
            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\'](.*?)[\"|\'][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['vod_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['vod_content'] = str_replace($v1, $v2, $data['vod_content']);
                }
            }
            unset($match_src1);
        }

        if(empty($data['vod_blurb'])){
            $data['vod_blurb'] = mac_substring( strip_tags($data['vod_content']) ,100);
        }

        if(empty($data['vod_play_url'])){
            $data['vod_play_url'] = '';
        }
        if(empty($data['vod_down_url'])){
            $data['vod_down_url'] = '';
        }
        if(!empty($data['vod_pic_screenshot'])){
            $data['vod_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['vod_pic_screenshot']);
        }
        if(!empty($data['vod_play_from'])) {
            $data['vod_play_from'] = join('$$$', $data['vod_play_from']);
            $data['vod_play_server'] = join('$$$', $data['vod_play_server']);
            $data['vod_play_note'] = join('$$$', $data['vod_play_note']);
            $data['vod_play_url'] = join('$$$', $data['vod_play_url']);
            $data['vod_play_url'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['vod_play_url']);
        }
        else{
            $data['vod_play_from'] = '';
            $data['vod_play_server'] = '';
            $data['vod_play_note'] = '';
            $data['vod_play_url'] = '';
        }

        if(!empty($data['vod_down_from'])) {
            $data['vod_down_from'] = join('$$$', $data['vod_down_from']);
            $data['vod_down_server'] = join('$$$', $data['vod_down_server']);
            $data['vod_down_note'] = join('$$$', $data['vod_down_note']);
            $data['vod_down_url'] = join('$$$', $data['vod_down_url']);
            $data['vod_down_url'] = str_replace(array(chr(10),chr(13)), array('','#'),$data['vod_down_url']);
        }else{
            $data['vod_down_from']='';
            $data['vod_down_server']='';
            $data['vod_down_note']='';
            $data['vod_down_url']='';
        }
        
        if($data['uptime']==1){
            $data['vod_time'] = time();
        }
        if($data['uptag']==1){
            $data['vod_tag'] = mac_get_tag($data['vod_name'], $data['vod_content']);
        }
        unset($data['uptime']);
        unset($data['uptag']);

        $data = VodValidate::formatDataBeforeDb($data);
        if(!empty($data['vod_id'])){
            $where=[];
            $where['vod_id'] = ['eq',$data['vod_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['vod_plot'] = 0;
            $data['vod_plot_name']='';
            $data['vod_plot_detail']='';
            $data['vod_time_add'] = time();
            $data['vod_time'] = time();
            $res = $this->allowField(true)->insert($data, false, true);
            if ($res > 0 && model('VodSearch')->isFrontendEnabled()) {
                model('VodSearch')->checkAndUpdateTopResults(['vod_id' => $res] + $data);
            }
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function savePlot($data)
    {
        $validate = \think\Loader::validate('Vod');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }
        $key = 'vod_detail_'.$data['vod_id'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_en'];
        Cache::rm($key);
        $key = 'vod_detail_'.$data['vod_id'].'_'.$data['vod_en'];
        Cache::rm($key);

        if(!empty($data['vod_plot_name'])) {
            $data['vod_plot'] = 1;
            $data['vod_plot_name'] = join('$$$', $data['vod_plot_name']);
            $data['vod_plot_detail'] = join('$$$', $data['vod_plot_detail']);
        }else{
            $data['vod_plot'] = 0;
            $data['vod_plot_name']='';
            $data['vod_plot_detail']='';
        }

        if(!empty($data['vod_id'])){
            $where=[];
            $where['vod_id'] = ['eq',$data['vod_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = false;
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function delData($where)
    {
        $list = $this->listData($where,'',1,9999);
        if($list['code'] !==1){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $path = './';
        foreach($list['list'] as $k=>$v){
            $pic = $path.$v['vod_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['vod_pic_thumb'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['vod_pic_slide'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            if($GLOBALS['config']['view']['vod_detail'] ==2 ){
                $lnk = mac_url_vod_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1002,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function fieldData($where,$update)
    {
        if(!is_array($update)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $res = $this->allowField(true)->where($where)->update($update);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        $list = $this->field('vod_id,vod_name,vod_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'vod_detail_'.$v['vod_id'];
            Cache::rm($key);
            $key = 'vod_detail_'.$v['vod_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function updateToday($flag='vod')
    {
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['vod_time'] = ['gt',$today];
        if($flag=='type'){
            $ids = $this->where($where)->column('type_id');
        }
        else{
            $ids = $this->where($where)->column('vod_id');
        }
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

}