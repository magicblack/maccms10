<?php
namespace app\index\controller;

use app\common\util\AiChatRateLimit;
use app\common\util\AiChatService;
use app\common\util\SearchService;
use think\Cache;

class Ajax extends Base
{
    var $_param;
    
    public function __construct()
    {
        parent::__construct();

        $this->_param = mac_param_url();
    }

    public function index()
    {

    }

    //加载最多不超过20页数据，防止非法采集。每页条数可以是10,20,30
    public function data()
    {
        $mid = $this->_param['mid'];
        $limit = $this->_param['limit'];
        $page = $this->_param['page'];
        $type_id = $this->_param['tid'];
        if( !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        if( !in_array($limit,['10','20','30']) ) {
            $limit =10;
        }
        if($page < 1 || $page > 20){
            $page =1;
        }

        $pre = mac_get_mid_code($mid);
        $order= $pre.'_time desc';
        $where=[];
        $where[$pre.'_status'] = [ 'eq',1];
        if(!empty($type_id)) {
            if(in_array($mid, ['1', '2'])){
                $type_list = model('Type')->getCache('type_list');
                $type_info = $type_list[$type_id];
                if(!empty($type_info)) {
                    $ids = $type_info['type_pid'] == 0 ? $type_info['childids'] : $type_info['type_id'];
                    $where['type_id|type_id_1'] = ['in', $ids];
                }
            }
        }
        $field='*';
        $res = model($pre)->listData($where,$order,$page,$limit,0,$field);
        if($res['code']==1) {
            foreach ($res['list'] as $k => &$v) {
                unset($v[$pre.'_time_hits'],$v[$pre.'_time_make']);
                $v[$pre.'_time'] = date('Y-m-d H:i:s',$v[$pre.'_time']);
                $v[$pre.'_time_add'] = date('Y-m-d H:i:s',$v[$pre.'_time_add']);
                if($mid=='1'){
                    unset($v['vod_play_from'],$v['vod_play_server'],$v['vod_play_note'],$v['vod_play_url']);
                    unset($v['vod_down_from'],$v['vod_down_server'],$v['vod_down_note'],$v['vod_down_url']);

                    $v['detail_link'] = mac_url_vod_detail($v);
                }
                elseif($mid=='2'){
                    $v['detail_link'] = mac_url_art_detail($v);
                }
                elseif($mid=='3'){
                    $v['detail_link'] = mac_url_topic_detail($v);
                }
                elseif($mid=='8'){
                    $v['detail_link'] = mac_url_actor_detail($v);
                }
                elseif($mid=='9'){
                    $v['detail_link'] = mac_url_role_detail($v);
                }
                elseif($mid=='11'){
                    $v['detail_link'] = mac_url_website_detail($v);
                }
                $v[$pre.'_pic'] = mac_url_img($v[$pre.'_pic']);
                $v[$pre.'_pic_thumb'] = mac_url_img($v[$pre.'_pic_thumb']);
                $v[$pre.'_pic_slide'] = mac_url_img($v[$pre.'_pic_slide']);
            }
        }
        return json($res);
    }

    public function suggest()
    {
        if($GLOBALS['config']['app']['search'] !='1'){
            return json(['code'=>999,'msg'=>lang('suggest_close')]);
        }

        $mid = $this->_param['mid'];
        $wd = isset($this->_param['wd']) ? $this->_param['wd'] : '';
        if (is_string($wd)) {
            $wd = trim(urldecode($wd));
        } else {
            $wd = '';
        }
        $wd = mac_filter_xss($wd);
        if (mb_strlen($wd, 'UTF-8') > 64) {
            $wd = mb_substr($wd, 0, 64, 'UTF-8');
        }
        $limit = intval($this->_param['limit']);

        if( $wd=='' || !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $mids = [1=>'vod',2=>'art',3=>'topic',8=>'actor',9=>'role',11=>'website'];
        $pre = $mids[$mid];
        if($limit<1){
            $limit = 20;
        }
        $limit = min(50, $limit);
        $url = mac_url_search(['wd'=>'mac_wd'],$pre);

        $appCfg = $GLOBALS['config']['app'];
        $minChars = max(1, intval(isset($appCfg['search_suggest_min_chars']) ? $appCfg['search_suggest_min_chars'] : 2));
        if (mb_strlen($wd, 'UTF-8') < $minChars) {
            return json([
                'code' => 1,
                'msg' => lang('data_list'),
                'page' => 1,
                'pagecount' => 0,
                'limit' => $limit,
                'total' => 0,
                'list' => [],
                'url' => $url,
            ]);
        }

        $ip = request()->ip(0, true);
        $uid = intval($GLOBALS['user']['user_id'] ?? 0);
        $sessionKey = (string)request()->cookie(session_name(), '');
        $visitorSeed = $uid > 0 ? ('u:' . $uid) : ('s:' . $sessionKey);
        $visitorId = md5($visitorSeed . '|' . (string)request()->header('user-agent', ''));
        $rateLimit = max(0, intval(isset($appCfg['search_suggest_rate_ip']) ? $appCfg['search_suggest_rate_ip'] : 90));
        $rateWin = max(1, intval(isset($appCfg['search_suggest_rate_window_sec']) ? $appCfg['search_suggest_rate_window_sec'] : 60));
        if (!SearchService::consumeSuggestRate($ip, $rateLimit, $rateWin, $visitorId)) {
            return json([
                'code' => 1,
                'msg' => lang('data_list'),
                'page' => 1,
                'pagecount' => 0,
                'limit' => $limit,
                'total' => 0,
                'list' => [],
                'url' => $url,
            ]);
        }

        $orderMode = isset($appCfg['search_suggest_order']) ? $appCfg['search_suggest_order'] : 'popular';
        $cacheSec = max(30, intval(isset($appCfg['search_suggest_cache_sec']) ? $appCfg['search_suggest_cache_sec'] : 180));
        $cacheKey = 'search:suggest:v2:' . md5($mid . '|' . mb_strtolower($wd, 'UTF-8') . '|' . $limit . '|' . $orderMode);
        $debounceSec = max(1, intval(isset($appCfg['search_suggest_debounce_sec']) ? $appCfg['search_suggest_debounce_sec'] : 1));
        $ipDebounceKey = 'search:suggest:debounce:' . md5($ip . '|' . $mid . '|' . mb_strtolower($wd, 'UTF-8') . '|' . $limit);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $cached['url'] = $url;
            return json($cached);
        }
        $debounced = Cache::get($ipDebounceKey);
        if (is_array($debounced)) {
            $debounced['url'] = $url;
            return json($debounced);
        }

        $where = [];
        $where[$pre.'_name|'.$pre.'_en'] = ['like','%'.$wd.'%'];
        $order = SearchService::suggestOrder($pre, $orderMode);
        $field = $pre.'_id as id,'.$pre.'_name as name,'.$pre.'_en as en,'.$pre.'_pic as pic';

        if ($pre === 'topic') {
            $res = model('Topic')->listData($where, $order, 1, $limit, 0, $field, 0);
        } else {
            $res = model($pre)->listData($where, $order, 1, $limit, 0, $field, 0, 0);
        }
        if($res['code']==1) {
            foreach ($res['list'] as $k => $v) {
                $res['list'][$k]['pic'] = mac_url_img($v['pic']);
            }
        }
        $res['url'] = $url;
        Cache::set($cacheKey, $res, $cacheSec);
        Cache::set($ipDebounceKey, $res, $debounceSec);
        return json($res);
    }

    /**
     * 热门搜索词（聚合自 search_query_log）+ 后台配置的备选热词。
     */
    public function search_hot()
    {
        if ($GLOBALS['config']['app']['search'] != '1') {
            return json(['code' => 999, 'msg' => lang('suggest_close')]);
        }
        $limit = isset($this->_param['limit']) ? intval($this->_param['limit']) : 15;
        $days = isset($this->_param['days']) ? intval($this->_param['days']) : 30;
        $limit = max(1, min(50, $limit));
        $days = max(1, min(365, $days));
        $cacheSec = max(15, intval(isset($GLOBALS['config']['app']['search_hot_cache_sec']) ? $GLOBALS['config']['app']['search_hot_cache_sec'] : 60));
        $cacheKey = 'search:hot:v1:' . md5($limit . '|' . $days . '|' . (string)($GLOBALS['config']['app']['search_hot'] ?? ''));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return json($cached);
        }
        $hot = SearchService::hotWords($limit, $days);
        $raw = isset($GLOBALS['config']['app']['search_hot']) ? (string)$GLOBALS['config']['app']['search_hot'] : '';
        $raw = str_replace('，', ',', $raw);
        $configHot = [];
        foreach (explode(',', $raw) as $w) {
            $w = trim(mac_filter_xss($w));
            if ($w !== '') {
                $configHot[] = ['word' => $w, 'count' => 0];
            }
        }
        $payload = ['code' => 1, 'data' => ['hot' => $hot, 'config_hot' => $configHot]];
        Cache::set($cacheKey, $payload, $cacheSec);
        return json($payload);
    }

    /**
     * 登录用户搜索历史（需在搜索落地页触发过记录）。
     */
    public function search_history()
    {
        if ($GLOBALS['config']['app']['search'] != '1') {
            return json(['code' => 999, 'msg' => lang('suggest_close')]);
        }
        $uid = intval($GLOBALS['user']['user_id'] ?? 0);
        if ($uid <= 0) {
            return json(['code' => 1001, 'msg' => 'not logged in', 'list' => []]);
        }
        $limit = isset($this->_param['limit']) ? intval($this->_param['limit']) : 15;
        $list = SearchService::userHistory($uid, $limit);
        return json(['code' => 1, 'list' => $list]);
    }

    /**
     * 清空登录用户搜索历史。
     */
    public function search_history_clear()
    {
        if ($GLOBALS['config']['app']['search'] != '1') {
            return json(['code' => 999, 'msg' => lang('suggest_close')]);
        }
        $uid = intval($GLOBALS['user']['user_id'] ?? 0);
        if ($uid <= 0) {
            return json(['code' => 1001, 'msg' => 'not logged in']);
        }
        $ok = SearchService::clearUserHistory($uid);
        return json(['code' => $ok ? 1 : 1002, 'msg' => $ok ? lang('ok') : 'clear failed']);
    }

    public function desktop()
    {
        $name = $this->_param['name'];
        $url = $this->_param['url'];

        $config = config('maccms.site');
        if(empty($name)){
            $name = $config['site_name'];
            $url = "http://".$config['site_url'];
        }
        if(substr($url,0,4)!="http"){
            $url = "http://".$url;
        }
        $Shortcut = "[InternetShortcut]
        URL=".$url."
        IDList=
        IconIndex=1
        [{000214A0-0000-0000-C000-000000000046}]
        Prop3=19,2";
        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")){
            header("Content-Disposition: attachment; filename=". urlencode($name) .".url;");
        }
        else{
            header("Content-Disposition: attachment; filename=". $name .".url;");
        }
        echo $Shortcut;
    }

    public function hits()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $type = $this->_param['type'];
        if(empty($id) ||  !in_array($mid,['1','2','3','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_hits,'.$pre.'_hits_day,'.$pre.'_hits_week,'.$pre.'_hits_month,'.$pre.'_time_hits';
        $model = model($pre);

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];

        if($type == 'update'){
            //初始化值
            $update[$pre.'_hits'] = $info[$pre.'_hits'];
            $update[$pre.'_hits_day'] = $info[$pre.'_hits_day'];
            $update[$pre.'_hits_week'] = $info[$pre.'_hits_week'];
            $update[$pre.'_hits_month'] = $info[$pre.'_hits_month'];
            $new = getdate();
            $old = getdate($info[$pre.'_time_hits']);
            //月
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon']){
                $update[$pre.'_hits_month'] ++;
            }else{
                $update[$pre.'_hits_month'] = 1;
            }
            //周
            $weekStart = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"] * 86400);
            $weekEnd = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6 - $new["wday"]) * 86400);
            if($info[$pre.'_time_hits'] >= $weekStart && $info[$pre.'_time_hits'] <= $weekEnd){
                $update[$pre.'_hits_week'] ++;
            }else{
                $update[$pre.'_hits_week'] = 1;
            }
            //日
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon'] && $new['mday'] == $old['mday']){
                $update[$pre.'_hits_day'] ++;
            }else{
                $update[$pre.'_hits_day'] = 1;
            }
            //更新数据库
            $update[$pre.'_hits'] = $update[$pre.'_hits']+1;
            $update[$pre.'_time_hits'] = time();
            $model->where($where)->update($update);

            $data['hits'] = $update[$pre.'_hits'];
            $data['hits_day'] = $update[$pre.'_hits_day'];
            $data['hits_week'] = $update[$pre.'_hits_week'];
            $data['hits_month'] = $update[$pre.'_hits_month'];
        }
        else{
            $data['hits'] = $info[$pre.'_hits'];
            $data['hits_day'] = $info[$pre.'_hits_day'];
            $data['hits_week'] = $info[$pre.'_hits_week'];
            $data['hits_month'] = $info[$pre.'_hits_month'];
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function referer()
    {
        $url = $this->_param['url'];
        $type = $this->_param['type'];
        $domain = $this->_param['domain'];

        if(empty($url)) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        if(strpos($_SERVER["HTTP_REFERER"],$_SERVER['HTTP_HOST'])===false){
            return json(['code'=>1002,'msg'=>lang('param_err')]);
        }

        if(strpos($url,$domain)===false){
            return json(['code'=>1003,'msg'=>lang('param_err')]);
        }

        $pre = 'website';
        $where=[];
        $where[$pre.'_jumpurl'] =  ['like', ['http://'.$domain.'%','https://'.$domain.'%'],'OR'];
        $model = model($pre);
        $field = $pre.'_referer,'.$pre.'_referer_day,'.$pre.'_referer_week,'.$pre.'_referer_month,'.$pre.'_time_referer';
        $res = $model->infoData($where,$field);
        if($res['code']>1){
            return json($res);
        }
        $info = $res['info'];
        $id = $info[$pre.'_id'];

        //来路访问记录验证
        $res = model('Website')->visit($this->_param);
        if($res['code']>1){
            return json($res);
        }

        if($type == 'update'){
            //初始化值
            $update[$pre.'_referer'] = $info[$pre.'_referer'];
            $update[$pre.'_referer_day'] = $info[$pre.'_referer_day'];
            $update[$pre.'_referer_week'] = $info[$pre.'_referer_week'];
            $update[$pre.'_referer_month'] = $info[$pre.'_referer_month'];
            $new = getdate();
            $old = getdate($info[$pre.'_time_referer']);
            //月
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon']){
                $update[$pre.'_referer_month'] ++;
            }else{
                $update[$pre.'_referer_month'] = 1;
            }
            //周
            $weekStart = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"] * 86400);
            $weekEnd = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6 - $new["wday"]) * 86400);
            if($info[$pre.'_time_referer'] >= $weekStart && $info[$pre.'_time_referer'] <= $weekEnd){
                $update[$pre.'_referer_week'] ++;
            }else{
                $update[$pre.'_referer_week'] = 1;
            }
            //日
            if($new['year'] == $old['year'] && $new['mon'] == $old['mon'] && $new['mday'] == $old['mday']){
                $update[$pre.'_referer_day'] ++;
            }else{
                $update[$pre.'_referer_day'] = 1;
            }
            //更新数据库
            $update[$pre.'_referer'] = $update[$pre.'_referer']+1;
            $update[$pre.'_time_referer'] = time();
            $model->where($where)->update($update);

            $data['referer'] = $update[$pre.'_referer'];
            $data['referer_day'] = $update[$pre.'_referer_day'];
            $data['referer_week'] = $update[$pre.'_referer_week'];
            $data['referer_month'] = $update[$pre.'_referer_month'];
        }
        else{
            $data['referer'] = $info[$pre.'_referer'];
            $data['referer_day'] = $info[$pre.'_referer_day'];
            $data['referer_week'] = $info[$pre.'_referer_week'];
            $data['referer_month'] = $info[$pre.'_referer_month'];
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function digg()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $type = $this->_param['type'];

        if(empty($id) ||  !in_array($mid,['1','2','3','4','8','9','11']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }
        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_up,'.$pre.'_down';
        $model = model($pre);

        if($type) {
            $cookie = $pre . '-digg-' . $id;
            if(!empty(cookie($cookie))){
                return json(['code'=>1002,'msg'=>lang('index/haved')]);
            }
            if ($type == 'up') {
                $model->where($where)->setInc($pre.'_up');
                cookie($cookie, 't', 30);
            } elseif ($type == 'down') {
                $model->where($where)->setInc($pre.'_down');
                cookie($cookie, 't', 30);
            }
        }

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];
        if ($info) {
            $data['up'] = $info[$pre.'_up'];
            $data['down'] = $info[$pre.'_down'];
        }
        else{
            $data['up'] = 0;
            $data['down'] = 0;
        }
        return json(['code'=>1,'msg'=>'ok','data'=>$data]);
    }

    public function score()
    {
        $id = $this->_param['id'];
        $mid = $this->_param['mid'];
        $score = $this->_param['score'];

        if(empty($id) ||  !in_array($mid,['1','2','3','8','9','11','12']) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $pre = mac_get_mid_code($mid);
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_score,'.$pre.'_score_num,'.$pre.'_score_all';
        $model = model($pre);

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];

        if ($info) {
            if($score){
                $cookie = $pre.'-score-'.$id;
                if(!empty(cookie($cookie))){
                    return json(['code'=>1002,'msg'=>lang('index/haved')]);
                }
                $update=[];
                $update[$pre.'_score_num'] = $info[$pre.'_score_num']+1;
                $update[$pre.'_score_all'] = $info[$pre.'_score_all']+$score;
                $update[$pre.'_score'] = number_format( $update[$pre.'_score_all'] / $update[$pre.'_score_num'] ,1,'.','');
                $model->where($where)->update($update);

                $data['score'] = $update[$pre.'_score'];
                $data['score_num'] = $update[$pre.'_score_num'];
                $data['score_all'] = $update[$pre.'_score_all'];

                cookie($cookie,'t',30);
            }
            else{
                $data['score'] = $info[$pre.'_score'];
                $data['score_num'] = $info[$pre.'_score_num'];
                $data['score_all'] = $info[$pre.'_score_all'];
            }
        }else{
            $data['score'] = 0.0;
            $data['score_num'] = 0;
            $data['score_all'] = 0;
        }
        return json(['code'=>1,'msg'=>lang('score_ok'),'data'=>$data]);
    }

    public function pwd()
    {
        $mid = $this->_param['mid'];
        $id = $this->_param['id'];
        $type = $this->_param['type'];
        $pwd = input('param.pwd');

        if( empty($id) || empty($pwd) || !in_array($mid,['1','2']) || !in_array($type,['1','4','5'])){
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $key = $mid.'-'.$type.'-'.$id;
        if(session($key)=='1'){
            return json(['code'=>1002,'msg'=>lang('index/pwd_repeat')]);
        }

        if ( mac_get_time_span("last_pwd") < 5){
            return json(['code'=>1003,'msg'=>lang('index/pwd_frequently')]);
        }


        if($mid=='1'){
            $where=[];
            $where['vod_id'] = ['eq',$id];
            $info = model('Vod')->infoData($where);
            if($info['code'] >1){
                return json(['code'=>1011,'msg'=>$info['msg']]);
            }
            if($type=='1'){
                if($info['info']['vod_pwd'] != $pwd){
                    return json(['code'=>1012,'msg'=>lang('pass_err')]);
                }
            }
            elseif($type=='4'){
                if($info['info']['vod_pwd_play'] != $pwd){
                    return json(['code'=>1013,'msg'=>lang('pass_err')]);
                }
            }
            elseif($type=='5'){
                if($info['info']['vod_pwd_down'] != $pwd){
                    return json(['code'=>1014,'msg'=>lang('pass_err')]);
                }
            }
        }
        else{
            $where=[];
            $where['art_id'] = ['eq',$id];
            $info = model('Art')->infoData($where);
            if($info['code'] >1){
                return json(['code'=>1021,'msg'=>$info['msg']]);
            }
            if($info['info']['art_pwd'] != $pwd){
                return json(['code'=>1022,'msg'=>lang('pass_err')]);
            }
        }

        session($key,'1');
        return json(['code'=>1,'msg'=>'ok']);
    }

    /**
     * 漫画猜你喜欢 - 换一换 AJAX 接口
     * 参数: id=当前漫画ID, tid=分类ID(可选), num=数量(默认9)
     */
    public function guess_manga()
    {
        $id = intval($this->_param['id'] ?? 0);
        $tid = intval($this->_param['tid'] ?? 0);
        $num = intval($this->_param['num'] ?? 9);
        if ($num < 1 || $num > 20) $num = 9;

        $where = ['manga_status' => ['eq', 1]];
        if ($id > 0) $where['manga_id'] = ['neq', $id];
        if ($tid > 0) $where['type_id'] = $tid;

        $order = 'manga_hits desc, manga_id desc';
        $page = mt_rand(1, max(1, 5));
        $res = model('Manga')->listData($where, $order, $page, $num, 0, '*', 1);
        if ($res['code'] != 1) {
            return json($res);
        }

        $list = [];
        foreach ($res['list'] as $v) {
            $ep = 0;
            if (!empty($v['manga_chapter_from']) && !empty($v['manga_chapter_url'])) {
                $pl = mac_manga_list($v['manga_chapter_from'], $v['manga_chapter_url'], $v['manga_play_server'] ?? '', $v['manga_play_note'] ?? '');
                foreach ($pl as $f) {
                    if (!empty($f['urls']) && is_array($f['urls'])) $ep += count($f['urls']);
                }
            }
            $list[] = [
                'manga_id' => $v['manga_id'],
                'manga_name' => $v['manga_name'],
                'manga_pic' => mac_url_img($v['manga_pic']),
                'link' => mac_url_manga_detail($v),
                'ep_count' => (int)$ep,
            ];
        }
        return json(['code' => 1, 'msg' => lang('data_list'), 'list' => $list]);
    }

    /**
     * 文章猜你喜欢 - 换一换 AJAX 接口
     * 参数: id=当前文章ID, tid=分类ID(可选), num=数量(默认9)
     */
    public function guess_art()
    {
        $id = intval($this->_param['id'] ?? 0);
        $tid = intval($this->_param['tid'] ?? 0);
        $num = intval($this->_param['num'] ?? 9);
        if ($num < 1 || $num > 20) $num = 9;

        $where = ['art_status' => ['eq', 1]];
        if ($id > 0) $where['art_id'] = ['neq', $id];
        if ($tid > 0) $where['type_id'] = $tid;

        $order = 'art_hits desc, art_id desc';
        $page = mt_rand(1, max(1, 5));
        $res = model('Art')->listData($where, $order, $page, $num, 0, '*', 1);
        if ($res['code'] != 1) {
            return json($res);
        }

        $list = [];
        foreach ($res['list'] as $v) {
            $pageTotal = 0;
            if (!empty($v['art_content'])) {
                $pageTotal = count(explode('$$$', $v['art_content']));
            }
            $list[] = [
                'art_id' => $v['art_id'],
                'art_name' => $v['art_name'],
                'art_pic' => mac_url_img($v['art_pic']),
                'link' => mac_url_art_detail($v),
                'page_total' => (int)$pageTotal,
            ];
        }
        return json(['code' => 1, 'msg' => lang('data_list'), 'list' => $list]);
    }

    /**
     * 首页热门 tab 分段加载
     * 参数: tab=2..7
     */
    public function home_hot_tab()
    {
        $tab = intval(input('param.tab/d', 0));
        if ($tab <= 0) {
            $tab = intval($this->_param['id'] ?? 0);
        }
        if ($tab < 2 || $tab > 7) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $vars = $this->buildIndexThemeVars();
        $data = $this->buildHomeHotTabData($tab, $vars);
        return json(['code' => 1, 'msg' => 'ok', 'data' => $data]);
    }

    /**
     * 构造首页主题变量（供首页 AJAX 片段使用）
     */
    protected function buildIndexThemeVars()
    {
        $tplconfig = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme'])
            ? $GLOBALS['mctheme']
            : (config('mctheme') ?: ['theme' => []]);
        $theme = isset($tplconfig['theme']) && is_array($tplconfig['theme']) ? $tplconfig['theme'] : [];

        $mangaTheme = isset($theme['manga']) && is_array($theme['manga']) ? $theme['manga'] : [];
        $mangaHbtn = (!isset($mangaTheme['hbtn']) || (string)($mangaTheme['hbtn']) !== '0') ? 1 : 0;
        $mangaHnumInt = (isset($mangaTheme['hnum']) && (string)$mangaTheme['hnum'] === '12') ? 12 : 6;

        $artTheme = isset($theme['art']) && is_array($theme['art']) ? $theme['art'] : [];
        $artHbtn = (!isset($artTheme['hbtn']) || (string)($artTheme['hbtn']) !== '0') ? 1 : 0;
        $artHnumInt = (isset($artTheme['hnum']) && (string)$artTheme['hnum'] === '12') ? 12 : 6;

        return [
            'index_hotvod_tabs' => mac_theme_index_hotvod_tabs($theme),
            'index_manga_hbtn' => $mangaHbtn,
            'index_manga_hnum_str' => (string)$mangaHnumInt,
            'index_manga_hot_txt_num' => (string)($mangaHnumInt * 2),
            'index_manga_hot_txt_start' => (string)$mangaHnumInt,
            'index_manga_poster_class' => mac_tpl_manga_cover() === 'h' ? 'mac-poster--h' : 'mac-poster--v',
            'index_art_hbtn' => $artHbtn,
            'index_art_hnum' => (string)$artHnumInt,
            'index_art_hot_txt_num' => (string)($artHnumInt * 2),
            'index_art_hot_txt_start' => (string)$artHnumInt,
            'index_art_poster_class' => mac_tpl_art_cover() === 'h' ? 'mac-poster--h' : 'mac-poster--v',
        ];
    }

    protected function buildHomeHotTabData($tab, $vars)
    {
        if (in_array($tab, [2, 3, 4, 5], true)) {
            $tabs = isset($vars['index_hotvod_tabs']) && is_array($vars['index_hotvod_tabs']) ? $vars['index_hotvod_tabs'] : [];
            $idx = $tab - 2;
            $typeId = 0;
            if (isset($tabs[$idx]) && is_array($tabs[$idx])) {
                $typeId = intval($tabs[$idx]['id'] ?? 0);
            }
            return $this->buildVodHotTabData($tab, $typeId);
        }
        if ($tab === 6) {
            return $this->buildMangaHotTabData($tab, $vars);
        }
        return $this->buildArtHotTabData($tab, $vars);
    }

    protected function buildVodHotTabData($tab, $typeId)
    {
        if ($typeId <= 0) {
            return [
                'tab' => $tab,
                'content_type' => 'vod',
                'append_remarks' => $tab === 2 ? 1 : 0,
                'img_list' => [],
                'txt_list' => [],
            ];
        }
        $where = ['vod_status' => ['eq', 1]];
        $ids = $this->resolveTypeIds($typeId);
        $where['type_id|type_id_1'] = ['in', implode(',', $ids)];

        $model = model('Vod');
        $imgRes = $model->listData($where, 'vod_hits_month desc', 1, 6, 0, '*', 1, 1);
        $txtRes = $model->listData($where, 'vod_hits_month desc', 1, 12, 6, '*', 1, 1);

        $imgList = [];
        $userId = intval($GLOBALS['user']['user_id'] ?? 0);
        $favMap = [];
        $vodIds = [];
        if (($imgRes['code'] ?? 0) == 1 && !empty($imgRes['list'])) {
            foreach ($imgRes['list'] as $v) {
                if (!empty($v['vod_id'])) {
                    $vodIds[] = intval($v['vod_id']);
                }
            }
            if ($userId > 0 && !empty($vodIds)) {
                $favRows = model('Ulog')->where([
                    'user_id' => $userId,
                    'ulog_type' => 2,
                    'ulog_rid' => ['in', implode(',', array_unique($vodIds))],
                ])->column('ulog_id', 'ulog_rid');
                if (is_array($favRows)) {
                    $favMap = $favRows;
                }
            }
            foreach ($imgRes['list'] as $v) {
                $classBadge = '';
                if (!empty($v['vod_class'])) {
                    $parts = preg_split('/[\s,，\/|]+/u', (string)$v['vod_class'], -1, PREG_SPLIT_NO_EMPTY);
                    if (!empty($parts)) {
                        $classBadge = trim((string)$parts[0]);
                    }
                }
                $vodId = intval($v['vod_id'] ?? 0);
                $favUid = isset($favMap[$vodId]) ? intval($favMap[$vodId]) : 0;
                $imgList[] = [
                    'name' => (string)($v['vod_name'] ?? ''),
                    'pic' => mac_url_img($v['vod_pic'] ?? ''),
                    'link' => mac_url_vod_detail($v),
                    'sub' => (string)($v['vod_sub'] ?? ''),
                    'id' => $vodId,
                    'points_play' => (string)($v['vod_points_play'] ?? ''),
                    'vod_class' => (string)($v['vod_class'] ?? ''),
                    'vod_area' => (string)($v['vod_area'] ?? ''),
                    'vod_year' => (string)($v['vod_year'] ?? ''),
                    'vod_actor' => (string)($v['vod_actor'] ?? ''),
                    'vod_blurb' => (string)($v['vod_blurb'] ?? ''),
                    'vod_isend' => intval($v['vod_isend'] ?? 0),
                    'type_is_vip_exclusive' => intval($v['type_is_vip_exclusive'] ?? 0),
                    'class_badge' => $classBadge,
                    'is_fav' => $favUid > 0 ? 1 : 0,
                    'fav_uid' => $favUid,
                ];
            }
        }

        $txtList = [];
        if (($txtRes['code'] ?? 0) == 1 && !empty($txtRes['list'])) {
            foreach ($txtRes['list'] as $v) {
                $txtList[] = [
                    'id' => intval($v['vod_id'] ?? 0),
                    'name' => (string)($v['vod_name'] ?? ''),
                    'link' => mac_url_vod_detail($v),
                    'time_md' => !empty($v['vod_time']) ? date('m-d', intval($v['vod_time'])) : '',
                    'remarks' => (string)($v['vod_remarks'] ?? ''),
                ];
            }
        }

        return [
            'tab' => $tab,
            'content_type' => 'vod',
            'append_remarks' => $tab === 2 ? 1 : 0,
            'img_list' => $imgList,
            'txt_list' => $txtList,
        ];
    }

    protected function buildMangaHotTabData($tab, $vars)
    {
        if (intval($vars['index_manga_hbtn'] ?? 0) !== 1) {
            return ['tab' => $tab, 'content_type' => 'manga', 'img_list' => [], 'txt_list' => []];
        }

        $model = model('Manga');
        $imgRes = $model->listData(['manga_status' => ['eq', 1]], 'manga_hits_month desc', 1, intval($vars['index_manga_hnum_str']), 0, '*', 1, 1);
        $txtRes = $model->listData(['manga_status' => ['eq', 1]], 'manga_hits_month desc', 1, intval($vars['index_manga_hot_txt_num']), intval($vars['index_manga_hot_txt_start']), '*', 1, 1);

        $imgList = [];
        if (($imgRes['code'] ?? 0) == 1 && !empty($imgRes['list'])) {
            foreach ($imgRes['list'] as $v) {
                $imgList[] = [
                    'name' => (string)($v['manga_name'] ?? ''),
                    'pic' => mac_url_img($v['manga_pic'] ?? ''),
                    'link' => mac_url_manga_detail($v),
                    'sub' => (string)($v['manga_remarks'] ?? '连载中'),
                    'type_is_vip_exclusive' => intval($v['type_is_vip_exclusive'] ?? 0),
                    'manga_points' => (string)($v['manga_points'] ?? ''),
                ];
            }
        }

        $txtList = [];
        if (($txtRes['code'] ?? 0) == 1 && !empty($txtRes['list'])) {
            foreach ($txtRes['list'] as $v) {
                $txtList[] = [
                    'name' => (string)($v['manga_name'] ?? ''),
                    'link' => mac_url_manga_detail($v),
                    'time_md' => !empty($v['manga_time']) ? date('m-d', intval($v['manga_time'])) : '',
                ];
            }
        }

        return ['tab' => $tab, 'content_type' => 'manga', 'img_list' => $imgList, 'txt_list' => $txtList];
    }

    protected function buildArtHotTabData($tab, $vars)
    {
        if (intval($vars['index_art_hbtn'] ?? 0) !== 1) {
            return ['tab' => $tab, 'content_type' => 'art', 'img_list' => [], 'txt_list' => []];
        }

        $model = model('Art');
        $imgRes = $model->listData(['art_status' => ['eq', 1]], 'art_time desc', 1, intval($vars['index_art_hnum']), 0, '*', 1, 1);
        $txtRes = $model->listData(['art_status' => ['eq', 1]], 'art_time desc', 1, intval($vars['index_art_hot_txt_num']), intval($vars['index_art_hot_txt_start']), '*', 1, 1);

        $imgList = [];
        if (($imgRes['code'] ?? 0) == 1 && !empty($imgRes['list'])) {
            foreach ($imgRes['list'] as $v) {
                $imgList[] = [
                    'name' => (string)($v['art_name'] ?? ''),
                    'pic' => mac_url_img($v['art_pic'] ?? ''),
                    'link' => mac_url_art_detail($v),
                    'sub' => (string)($v['art_actor'] ?? ''),
                    'type_is_vip_exclusive' => intval($v['type_is_vip_exclusive'] ?? 0),
                    'art_points' => (string)($v['art_points'] ?? ''),
                    'art_remarks' => (string)($v['art_remarks'] ?? ''),
                ];
            }
        }

        $txtList = [];
        if (($txtRes['code'] ?? 0) == 1 && !empty($txtRes['list'])) {
            foreach ($txtRes['list'] as $v) {
                $txtList[] = [
                    'name' => (string)($v['art_name'] ?? ''),
                    'link' => mac_url_art_detail($v),
                    'time_md' => !empty($v['art_time']) ? date('m-d', intval($v['art_time'])) : '',
                ];
            }
        }

        return ['tab' => $tab, 'content_type' => 'art', 'img_list' => $imgList, 'txt_list' => $txtList];
    }

    protected function resolveTypeIds($typeId)
    {
        $typeId = intval($typeId);
        if ($typeId <= 0) {
            return [];
        }
        $typeList = model('Type')->getCache('type_list');
        $info = is_array($typeList) && isset($typeList[$typeId]) ? $typeList[$typeId] : [];
        if (empty($info)) {
            return [$typeId];
        }
        if (intval($info['type_pid'] ?? 0) === 0 && !empty($info['childids'])) {
            $childids = array_filter(array_map('intval', explode(',', (string)$info['childids'])));
            return empty($childids) ? [$typeId] : $childids;
        }
        return [intval($info['type_id'] ?? $typeId)];
    }

    public function verify_check()
    {
        $param = input();
        if(!in_array($param['type'],['search','show'])){
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        if (!captcha_check($param['verify'])){
            return ['code' => 1002, 'msg' => lang('verify_err')];
        }
        session($param['type'].'_verify','1');
        return json(['code'=>1,'msg'=>lang('ok')]);
    }

    // AI chat search endpoint for custom chat template
    public function ai_chat()
    {
        $service = new AiChatService();
        $payload = $this->readAiChatPayload();
        $token = isset($payload['__token__']) ? (string)$payload['__token__'] : '';
        $sessionToken = (string)session('__token__');
        if ($token === '' || $sessionToken === '' || !$this->safeHashEquals($sessionToken, $token)) {
            return json([
                'code' => 1002,
                'msg' => lang('token_err'),
                'data' => $service->emptyPayload()
            ]);
        }

        $question = trim(mac_filter_xss((string)$payload['question']));
        $mid = intval($payload['mid']);
        $limit = intval($payload['limit']);

        if ($question === '') {
            return json([
                'code' => 1001,
                'msg' => 'question is empty',
                'data' => $service->emptyPayload()
            ]);
        }

        $aiCfg = config('maccms.ai_search');
        if (!is_array($aiCfg)) {
            $aiCfg = [];
        }
        $maxChars = intval(isset($aiCfg['max_question_chars']) ? $aiCfg['max_question_chars'] : 800);
        if ($maxChars > 0 && mb_strlen($question, 'UTF-8') > $maxChars) {
            return json([
                'code' => 1003,
                'msg' => 'question exceeds maximum length',
                'data' => $service->emptyPayload()
            ]);
        }

        $rl = AiChatRateLimit::checkHit(mac_get_client_ip(), $aiCfg);
        if (!$rl['allowed']) {
            $retry = max(1, intval($rl['retry_after']));
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: '.$retry);

            return json([
                'code' => 429,
                'msg' => 'Too many requests. Please try again in '.$retry.' seconds.',
                'data' => array_merge($service->emptyPayload(), ['retry_after' => $retry])
            ]);
        }

        if (!in_array($mid, [0,1,2,3,8,9,11,12], true)) {
            $mid = 0;
        }
        if ($limit < 1) {
            $limit = 6;
        } elseif ($limit > 12) {
            $limit = 12;
        }

        try {
            $chatPayload = $service->buildPayload($question, $mid, $limit);
        } catch (\Throwable $e) {
            return json([
                'code' => 5001,
                'msg' => 'ai_chat payload build failed',
                'data' => $service->emptyPayload()
            ]);
        }

        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => $chatPayload
        ]);
    }

    private function readAiChatPayload()
    {
        $payload = input('post.');
        if (!is_array($payload)) {
            $payload = [];
        }

        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            }
        }

        return [
            'question' => isset($payload['question']) ? $payload['question'] : $this->_param['wd'],
            'mid' => isset($payload['mid']) ? $payload['mid'] : $this->_param['mid'],
            'limit' => isset($payload['limit']) ? $payload['limit'] : $this->_param['limit'],
            'session_id' => isset($payload['session_id']) ? $payload['session_id'] : '',
            '__token__' => isset($payload['__token__']) ? $payload['__token__'] : '',
        ];
    }


    private function safeHashEquals($knownString, $userString)
    {
        if (function_exists('hash_equals')) {
            return hash_equals((string)$knownString, (string)$userString);
        }
        $a = (string)$knownString;
        $b = (string)$userString;
        $lenA = strlen($a);
        $lenB = strlen($b);
        if ($lenA !== $lenB) {
            return false;
        }
        $res = 0;
        for ($i = 0; $i < $lenA; $i++) {
            $res |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $res === 0;
    }

}