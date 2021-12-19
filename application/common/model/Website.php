<?php
namespace app\common\model;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;

class Website extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'website';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getWebsiteStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['website_status']];
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
        $list = Db::name('Website')->field($field)->where($where)->where($where2)->orderRaw($order)->limit($limit_str)->select();
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
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;

        $total = $this
            ->join('tmpwebsite t','t.name1 = website_name')
            ->where($where)
            ->count();

        $list = $this
            ->join('tmpwebsite t','t.name1 = website_name')
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
        $paging = $lp['paging'];
        $pageurl = $lp['pageurl'];
        $level = $lp['level'];
        $wd = $lp['wd'];
        $tag = $lp['tag'];
        $class = $lp['class'];
        $name = $lp['name'];
        $area = $lp['area'];
        $lang = $lp['lang'];
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
        $refermonth = $lp['refermonth'];
        $referweek = $lp['referweek'];
        $referday = $lp['referday'];
        $refer = $lp['refer'];

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
            if(!empty($param['area'])) {
                $area = $param['area'];
            }
            if(!empty($param['lang'])) {
                $lang = $param['lang'];
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
                $pageurl = 'website/type';
            }
            $param['page'] = 'PAGELINK';
            if($pageurl=='website/type' || $pageurl=='website/show'){
                $type = intval( $GLOBALS['type_id'] );
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type];
                $flag='type';
                if($pageurl == 'website/show'){
                    $flag='show';
                }
                $pageurl = mac_url_type($type_info,$param,$flag);
            }
            else{
                $pageurl = mac_url($pageurl,$param);
            }
        }

        $where['website_status'] = ['eq',1];
        if(!empty($level)) {
            if($level=='all'){
                $level = '1,2,3,4,5,6,7,8,9';
            }
            $where['website_level'] = ['in',explode(',',$level)];
        }
        if(!empty($ids)) {
            if($ids!='all'){
                $where['website_id'] = ['in',explode(',',$ids)];
            }
        }
        if(!empty($not)){
            $where['website_id'] = ['not in',explode(',',$not)];
        }
        if(!empty($letter)){
            if(substr($letter,0,1)=='0' && substr($letter,2,1)=='9'){
                $letter='0,1,2,3,4,5,6,7,8,9';
            }
            $where['website_letter'] = ['in',explode(',',$letter)];
        }

        if(!empty($timeadd)){
            $s = intval(strtotime($timeadd));
            $where['website_time_add'] =['gt',$s];
        }
        if(!empty($timehits)){
            $s = intval(strtotime($timehits));
            $where['website_time_hits'] =['gt',$s];
        }
        if(!empty($time)){
            $s = intval(strtotime($time));
            $where['website_time'] =['gt',$s];
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
                $where['website_hits_month'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsweek)){
            $tmp = explode(' ',$hitsweek);
            if(count($tmp)==1){
                $where['website_hits_week'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hitsday)){
            $tmp = explode(' ',$hitsday);
            if(count($tmp)==1){
                $where['website_hits_day'] = ['gt', $tmp];
            }
            else{
                $where['website_hits_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($hits)){
            $tmp = explode(' ',$hits);
            if(count($tmp)==1){
                $where['website_hits'] = ['gt', $tmp];
            }
            else{
                $where['website_hits'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($refermonth)){
            $tmp = explode(' ',$refermonth);
            if(count($tmp)==1){
                $where['website_refer_month'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_month'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($referweek)){
            $tmp = explode(' ',$referweek);
            if(count($tmp)==1){
                $where['website_refer_week'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_week'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($referday)){
            $tmp = explode(' ',$referday);
            if(count($tmp)==1){
                $where['website_refer_day'] = ['gt', $tmp];
            }
            else{
                $where['website_refer_day'] = [$tmp[0],$tmp[1]];
            }
        }
        if(!empty($refer)){
            $tmp = explode(' ',$refer);
            if(count($tmp)==1){
                $where['website_refer'] = ['gt', $tmp];
            }
            else{
                $where['website_refer'] = [$tmp[0],$tmp[1]];
            }
        }

        if(!empty($area)){
            $where['website_area'] = ['in',explode(',',$area) ];
        }
        if(!empty($lang)){
            $where['website_lang'] = ['in',explode(',',$lang) ];
        }
        if(!empty($name)){
            $where['website_name'] = ['in',explode(',',$name) ];
        }
        if(!empty($wd)) {
            $where['website_name|website_en'] = ['like', '%' . $wd . '%'];
        }
        if(!empty($tag)) {
            $where['website_tag'] = ['like', mac_like_arr($tag),'OR'];
        }
        if(!empty($class)) {
            $where['website_class'] = ['like',mac_like_arr($class),'OR'];
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

        if(!in_array($by, ['id', 'time','time_add','score','hits','hits_day','hits_week','hits_month','up','down','level','rnd','in','referer','referer_day','referer_week','referer_month'])) {
            $by = 'time';
        }
        if(!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        $where_cache = $where;
        if(!empty($randi)){
            unset($where_cache['website_id']);
            $where_cache['order'] = 'rnd';
        }


        if($by=='in' && !empty($name) ){
            $order = ' find_in_set(website_name, \''.$name.'\'  ) ';
        }
        else{
            if($by=='in' && empty($name) ){
                $by = 'time';
            }
            $order= 'website_'.$by .' ' . $order;
        }

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' .md5('website_listcache_'.http_build_query($where_cache).'_'.$order.'_'.$page.'_'.$num.'_'.$start.'_'.$pageurl);
        $res = Cache::get($cach_name);
        if(empty($cachetime)){
            $cachetime = $GLOBALS['config']['app']['cache_time'];
        }
        if($GLOBALS['config']['app']['cache_core']==0 || empty($res)) {
            $res = $this->listData($where,$order,$page,$num,$start,'*',1,$totalshow);
            if($GLOBALS['config']['app']['cache_core']==1){
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
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'website_detail_'.$where['website_id'][1].'_'.$where['website_en'][1];
        if($where['website_id'][0]=='eq' || $where['website_en'][0]=='eq'){
            $data_cache = true;
        }
        if($GLOBALS['config']['app']['cache_core']==1 && $data_cache) {
            $info = Cache::get($key);
        }
        if($GLOBALS['config']['app']['cache_core']==0 || $cache==0 || empty($info['website_id'])) {
            $info = $this->field($field)->where($where)->find();
            if (empty($info)) {
                return ['code' => 1002, 'msg' => lang('obtain_err')];
            }
            $info = $info->toArray();
            if(!empty($info['website_pic_screenshot'])){
                $info['website_pic_screenshot_list'] = mac_screenshot_list($info['website_pic_screenshot']);
            }
            //分类
            if (!empty($info['type_id'])) {
                $type_list = model('Type')->getCache('type_list');
                $info['type'] = $type_list[$info['type_id']];
                $info['type_1'] = $type_list[$info['type']['type_pid']];
            }
            if($GLOBALS['config']['app']['cache_core']==1 && $data_cache && $cache==1) {
                Cache::set($key, $info);
            }
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Website');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        $key = 'website_detail_'.$data['website_id'];
        Cache::rm($key);
        $key = 'website_detail_'.$data['website_en'];
        Cache::rm($key);
        $key = 'website_detail_'.$data['website_id'].'_'.$data['website_en'];
        Cache::rm($key);

        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$data['type_id']];
        $data['type_id_1'] = $type_info['type_pid'];

        if(empty($data['website_en'])){
            $data['website_en'] = Pinyin::get($data['website_name']);
        }
        if(empty($data['website_letter'])){
            $data['website_letter'] = strtoupper(substr($data['website_en'],0,1));
        }
        if(!empty($data['website_pic_screenshot'])){
            $data['website_pic_screenshot'] = str_replace( array(chr(10),chr(13)), array('','#'),$data['website_pic_screenshot']);
        }
        if($data['uptime']==1){
            $data['website_time'] = time();
        }
        if($data['uptag']==1){
            $data['website_tag'] = mac_get_tag($data['website_name'], $data['website_content']);
        }

        unset($data['uptime']);
        unset($data['uptag']);

        // xss过滤
        $filter_fields = [
            'website_name',
            'website_sub',
            'website_en',
            'website_color',
            'website_jumpurl',
            'website_pic',
            'website_logo',
            'website_area',
            'website_lang',
            'website_tag',
            'website_class',
            'website_remarks',
            'website_tpl',
            'website_blurb',
        ];
        foreach ($filter_fields as $filter_field) {
            if (!isset($data[$filter_field])) {
                continue;
            }
            $data[$filter_field] = mac_filter_xss($data[$filter_field]);
        }

        if(!empty($data['website_id'])){
            $where=[];
            $where['website_id'] = ['eq',$data['website_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['website_time_add'] = time();
            $data['website_time'] = time();
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
            $pic = $path.$v['website_pic'];
            if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count( explode("./",$pic) ) ==1){
                unlink($pic);
            }
            if($GLOBALS['config']['view']['website_detail'] ==2 ){
                $lnk = mac_url_website_detail($v);
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

        $list = $this->field('website_id,website_name,website_en')->where($where)->select();
        foreach($list as $k=>$v){
            $key = 'website_detail_'.$v['website_id'];
            Cache::rm($key);
            $key = 'website_detail_'.$v['website_en'];
            Cache::rm($key);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function updateToday($flag='website')
    {
        $today = strtotime(date('Y-m-d'));
        $where = [];
        $where['website_time'] = ['gt',$today];
        if($flag=='type'){
            $ids = $this->where($where)->column('type_id');
        }
        else{
            $ids = $this->where($where)->column('website_id');
        }
        if(empty($ids)){
            $ids = [];
        }else{
            $ids = array_unique($ids);
        }
        return ['code'=>1,'msg'=>lang('obtain_ok'),'data'=> join(',',$ids) ];
    }

    public function visit($param)
    {
        $ip = mac_get_ip_long();
        $max_cc = $GLOBALS['config']['website']['refer_visit_num'];
        if(empty($max_cc)){
            $max_cc=1;
        }
        $todayunix = strtotime("today");
        $where = [];
        $where['user_id'] = 0;
        $where['visit_ip'] = $ip;
        $where['visit_time'] = ['gt', $todayunix];
        $cc = model('visit')->where($where)->count();
        if ($cc>= $max_cc){
            return ['code' => 102, 'msg' =>lang('model/website/refer_max')];
        }

        $data = [];
        $data['user_id'] = 0;
        $data['visit_ip'] = $ip;
        $data['visit_time'] = time();
        $data['visit_ly'] = htmlspecialchars($param['url']);
        $res = model('visit')->saveData($data);

        if ($res['code'] > 1) {
            return ['code' => 103, 'msg' =>lang('model/website/visit_err')];
        }

        return ['code'=>1,'msg'=>lang('model/website/visit_ok')];
    }

}