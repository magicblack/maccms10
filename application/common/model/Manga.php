<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Manga extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'manga';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getMangaStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['manga_status']];
    }

    public function getMangaContentTextAttr($val,$data)
    {
        $arr = explode('$$$',$data['manga_content']);
        return $arr;
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
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",". $limit;
        if($totalshow==1) {
            $total = $this->where($where)->count();
        }
        $list = Db::name('Manga')->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();
        //dump($where);die;
        //echo $this->getLastSql();die;
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
        $limit_str = ($limit * ($page-1) + $start) .",". $limit;

        $total = $this
            ->join('tmpmanga t','t.name1 = manga_name')
            ->where($where)
            ->count();

        $list = Db::name('Manga')
            ->join('tmpmanga t','t.name1 = manga_name')
            ->field($field)
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();

        //dump($where);die;
        //echo $this->getLastSql();die;
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
        if (!is_array($lp)) {
            $lp = json_decode($lp, true);
        }

        $order = $lp['order'];
        $by = $lp['by'];
        $type = $lp['type'];
        $ids = $lp['ids'];
        $rel = $lp['rel'];
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $wd = $lp['wd'];
        $tag = $lp['tag'];
        $class = $lp['class'];
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
        $typenot = $lp['typenot'];
        $name = $lp['name'];
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
            if(!empty($param['id'])) {
                //$type = intval($param['id']);
            }
            if(!empty($param['ids'])){
                $ids = $param['ids'];
            }
            if(!empty($param['tid'])) {
                $tid = intval($param['tid']);
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
            if(!empty($param['name'])) {
                $name = $param['name'];
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
                $pageurl = 'manga/type';
            }
            $param['page'] = 'PAGELINK';

            if($pageurl=='manga/type' || $pageurl=='manga/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'manga/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }

        }

        $where['manga_status'] = ['eq',1];
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['manga_level'] = ['in',explode(',',$level)];
        }
        if(!empty($ids)) {
            if($ids!='all'){
                $where['manga_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['manga_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($rel)){
            $tmp = explode(',',$rel);
            if(is_numeric($rel) || mac_array_check_num($tmp)==true ){
                $where['manga_id'] = ['in',$tmp];
            }
            else{
                $where['manga_rel_manga'] = ['like', mac_like_arr($rel),'OR'];
            }
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['manga_letter'] = ['in',explode(',',$letter)];
        }
        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['manga_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['manga_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['manga_time'] =['gt',$s];
        }
        if(!empty($type)) {
            if($type=='current'){
                $type = intval( $GLOBALS['type_id'] );
            }
            if($type!='all') {
                $tmp_arr = explode(',', $type);
                $type_list = model('Type')->getCache('type_list');
                $type = [];
                foreach ($type_list as $k2 => $v2) {
                    if (in_array($v2['type_id'] . '', $tmp_arr) || in_array($v2['type_pid'] . '', $tmp_arr)) {
                        $type[] = $v2['type_id'];
                    }
                }
                $type = array_unique($type);
                $where['type_id'] = ['in', implode(',', $type)];
            }
        }
        if(!empty($typenot)){
            $where['type_id'] = ['not in',$typenot];
        }
        if(!empty($tid)) {
            $where['type_id|type_id_1'] = ['eq',$tid];
        }
        if(!empty($hitsmonth)){
            $tmp = explode(' ',$hitsmonth);
            if(count($tmp)==1){
                $where['manga_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['manga_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['manga_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['manga_hits'] = ['gt', $tmp];
            }
            else{
                $where['manga_hits'] = [$tmp[0],$tmp[1]];
            }
        }

        if(!empty($wd)) {
            $role = 'manga_name';
            if(!empty($GLOBALS['config']['app']['search_manga_rule'])){
                $role .= '|'.$GLOBALS['config']['app']['search_manga_rule'];
            }
            $where[$role] = ['like', '%' . $wd . '%'];
        }
        if(!empty($name)) {
            $where['manga_name'] = ['like', mac_like_arr($name),'OR'];
        }
        if(!empty($tag)) {
            $where['manga_tag'] = ['like', mac_like_arr($tag),'OR'];
        }
        if(!empty($class)) {
            $where['manga_class'] = ['like',mac_like_arr($class),'OR'];
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
        $order= 'manga_'.$by .' ' . $order;
        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['manga_id']);
            $where_cache['order'] = 'rnd';
        }
        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('manga_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
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
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'manga_detail_'.$where['manga_id'][1].'_'.$where['manga_en'][1];
        if($where['manga_id'][0]=='eq' || $where['manga_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['manga_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            //内容
            if (!empty($info['manga_chapter_url'])) {
                $info['manga_page_list'] = mac_manga_list($info['manga_chapter_from'], $info['manga_chapter_url'], $info['manga_play_server'], $info['manga_play_note']);
                $info['manga_page_total'] = count($info['manga_page_list']);
            }
            if(!empty($info['manga_pic_screenshot'])){
                $info['manga_pic_screenshot_list'] = mac_screenshot_list($info['manga_pic_screenshot']);
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
        $validate = \think\Loader::validate('Manga');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        $key = 'manga_detail_'.$data['manga_id'];
        Cache::rm($key);
        $key = 'manga_detail_'.$data['manga_en'];
        Cache::rm($key);
        $key = 'manga_detail_'.$data['manga_id'].'_'.$data['manga_en'];
        Cache::rm($key);


        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        if(empty($data['manga_en'])){
            $data['manga_en'] = Pinyin::get($data['manga_name']);
        }
        if(empty($data['manga_letter'])){
            $data['manga_letter'] = strtoupper(substr($data['manga_en'],0,1));
        }
        if(!empty($data['manga_pic_screenshot'])){
            $data['manga_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['manga_pic_screenshot']);
        }
        if(!empty($data['manga_content'])) {
            if(is_array($data['manga_content'])){
                $data['manga_content'] = join('$$$', $data['manga_content']);
            }
            if(is_array($data['manga_title'])){
                $data['manga_title'] = join('$$$', $data['manga_title']);
            }
            if(is_array($data['manga_note'])){
                $data['manga_note'] = join('$$$', $data['manga_note']);
            }

            $pattern_src = '/<img[\s\S]*?src\s*=\s*[\"|\"](.*?)[\"|\"][\s\S]*?>/';
            @preg_match_all($pattern_src, $data['manga_content'], $match_src1);
            if (!empty($match_src1)) {
                foreach ($match_src1[1] as $v1) {
                    $v2 = str_replace($GLOBALS['config']['upload']['protocol'] . ':', 'mac:', $v1);
                    $data['manga_content'] = str_replace($v1, $v2, $data['manga_content']);
                }
                if (empty($data['manga_pic'])) {
                    $data['manga_pic'] = (string)$match_src1[1][0];
                }
            }
            unset($match_src1);
        }

        if(empty($data['manga_blurb'])){
            $data['manga_blurb'] = mac_substring( str_replace('$$$','', strip_tags($data['manga_content'])),100);
        }

        if($data['uptime']==1){
            $data['manga_time'] = time();
        }
        if($data['uptag']==1){
            $data['manga_tag'] = mac_get_tag($data['manga_name'], $data['manga_content']);
        }
        unset($data['uptime']);
        unset($data['uptag']);

        // xss过滤
        $filter_fields = [
            'manga_name',
            'manga_sub',
            'manga_en',
            'manga_color',
            'manga_from',
            'manga_author',
            'manga_tag',
            'manga_class',
            'manga_pic',
            'manga_pic_thumb',
            'manga_pic_slide',
            'manga_blurb',
            'manga_remarks',
            'manga_jumpurl',
            'manga_tpl',
            'manga_rel_manga',
            'manga_rel_vod',
            'manga_pwd',
            'manga_pwd_url',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        if(!empty($data['manga_id'])){
            $where=[];
            $where['manga_id'] = ['eq',$data['manga_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['manga_time_add'] = time();
            $data['manga_time'] = time();
            $res = $this->allowField(true)->insert($data);
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
            $pic = $path.$v['manga_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['manga_pic_thumb'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            $pic = $path.$v['manga_pic_slide'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            if($GLOBALS['config']['view']['manga_detail'] ==2 ){
                $lnk = mac_url_manga_detail($v);
                $lnk = reset_html_filename($lnk);
                if(file_exists($lnk)){
                    unlink($lnk);
                }
            }
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
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

        $list = $this->field('manga_id,manga_name,manga_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'manga_detail_'.$v['manga_id'];
            Cache::rm($key);
            $key = 'manga_detail_'.$v['manga_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function updateToday($flag='manga')
    {
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['manga_time'] = ['gt',$today];
        if($flag=='type'){
            $ids = $this->where($where)->column('type_id');
        }
        else{
            $ids = $this->where($where)->column('manga_id');
        }
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

}
