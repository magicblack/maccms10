<?php
namespace app\admin\controller;
use think\Db;
use think\Cache;
use app\common\util\CollectHealth;

class Collect extends Base
{
    private $_cjretry = 0;

    public function __construct()
    {
        parent::__construct();
        //header('X-Accel-Buffering: no');
    }

    public function index()
    {
        $config = config('maccms');
        if (empty($config['collect']['manga'])) {
            $config['collect']['manga'] = [
                'status' => '0',
                'hits_start' => '',
                'hits_end' => '',
                'updown_start' => '',
                'updown_end' => '',
                'score' => '0',
                'pic' => '0',
                'psernd' => '0',
                'psesyn' => '0',
                'filter' => '',
                'thesaurus' => '',
                'words' => '',
                'inrule' => ',a',
                'uprule' => ',a',
            ];
            mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        }

        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? 100 : $param['limit'];
        $where = [];

        $order = 'collect_id desc';
        $res = model('Collect')->listData($where, $order, $param['page'], $param['limit']);

        // 接口可用性状态（无记录视为启用）
        $health_all = CollectHealth::all();
        foreach ($res['list'] as $k => $v) {
            $hk = CollectHealth::key($v['collect_url']);
            $res['list'][$k]['health'] = isset($health_all[$hk]) ? $health_all[$hk] : null;
            $res['list'][$k]['health_key'] = $hk;
        }

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_vod';
        $collect_break_vod = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_art';
        $collect_break_art = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_actor';
        $collect_break_actor = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_role';
        $collect_break_role = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_website';
        $collect_break_website = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_manga';
        $collect_break_manga = Cache::get($key);


        $this->assign('collect_break_vod', $collect_break_vod);
        $this->assign('collect_break_art', $collect_break_art);
        $this->assign('collect_break_actor', $collect_break_actor);
        $this->assign('collect_break_role', $collect_break_role);
        $this->assign('collect_break_website', $collect_break_website);
        $this->assign('collect_break_manga', $collect_break_manga);

        $this->assign('title',lang('admin/collect/title'));
        return $this->fetch('admin@collect/index');
    }

    public function test()
    {
        $param = input();
        $res = model('Collect')->vod($param);
        return json($res);
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }
            $res = model('Collect')->saveData($param);
            if ($res['code'] > 1) {
                return $this->ajaxErrorWithFreshToken($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where = [];
        $where['collect_id'] = ['eq', $id];
        $res = model('Collect')->infoData($where);
        $this->assign('info', $res['info']);
        $this->assign('title', lang('admin/collect/title'));
        return $this->fetch('admin@collect/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if (!empty($ids)) {
            $where = [];
            $where['collect_id'] = ['in', $ids];

            $res = model('Collect')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 启用 / 停用采集接口（自定义接口与资源站中心共用，按接口地址区分）
     */
    public function healthtoggle()
    {
        if (!Request()->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        if (!$this->adminCsrfOk()) {
            return json(['code' => 0, 'msg' => lang('token_err')]);
        }
        $url = trim(input('post.url', '', 'trim'));
        $status = input('post.status') == '1' ? 1 : 0;
        $name = trim(input('post.name', '', 'trim'));
        $source = input('post.source') == 'hub' ? 'hub' : 'custom';
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        if (!CollectHealth::setStatus($url, $status, $name, $source, $this->_admin['admin_name'])) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }
        return json(['code' => 1, 'msg' => lang($status ? 'admin/collect_health/enabled_ok' : 'admin/collect_health/disabled_ok')]);
    }

    /**
     * 采集接口检测 / 定时采集日志
     */
    public function healthlog()
    {
        $param = input();
        $page = max(1, intval($param['page']));
        $limit = intval($param['limit']) < 1 ? 50 : intval($param['limit']);
        $dates = CollectHealth::logDates();
        $date = isset($param['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['date']) ? $param['date'] : date('Y-m-d');
        $filter = [
            'key' => isset($param['key']) && preg_match('/^[0-9a-f]{32}$/', $param['key']) ? $param['key'] : '',
            'kind' => isset($param['kind']) && in_array($param['kind'], [CollectHealth::KIND_CHECK, CollectHealth::KIND_RUN, CollectHealth::KIND_SKIP, CollectHealth::KIND_TOGGLE], true) ? $param['kind'] : '',
            'result' => isset($param['result']) && in_array($param['result'], ['ok', 'fail', 'skip'], true) ? $param['result'] : '',
            'wd' => isset($param['wd']) ? trim((string)$param['wd']) : '',
        ];
        $res = CollectHealth::readLogs($date, $filter, $page, $limit);

        // 当前筛选接口的状态（从徽标跳转过来时显示原因）
        $endpoint = null;
        if ($filter['key'] !== '') {
            $all = CollectHealth::all();
            $endpoint = isset($all[$filter['key']]) ? $all[$filter['key']] : null;
        }

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $page);
        $this->assign('limit', $limit);
        $this->assign('dates', $dates);
        $this->assign('endpoint', $endpoint);
        $param = array_merge($filter, ['date' => $date]);
        $this->assign('filter', $param);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('admin/collect_health/log_title'));
        return $this->fetch('admin@collect/healthlog');
    }

    public function union()
    {
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_vod';
        $collect_break_vod = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_art';
        $collect_break_art = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_actor';
        $collect_break_actor = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_role';
        $collect_break_role = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_website';
        $collect_break_website = Cache::get($key);
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_manga';
        $collect_break_manga = Cache::get($key);

        $this->assign('collect_break_vod', $collect_break_vod);
        $this->assign('collect_break_art', $collect_break_art);
        $this->assign('collect_break_actor', $collect_break_actor);
        $this->assign('collect_break_role', $collect_break_role);
        $this->assign('collect_break_website', $collect_break_website);
        $this->assign('collect_break_manga', $collect_break_manga);

        $this->assign('title', lang('admin/collect/title'));
        return $this->fetch('admin@collect/union');
    }

    public function load()
    {
        $param = input();
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'collect_break_' . $param['flag'];
        $collect_break = Cache::get($key);
        $url = $this->_ref;
        if (!empty($collect_break)) {
            echo lang('admin/collect/load_break');
            $url = $collect_break;
        }
        mac_jump($url);
    }

    public function api($pp = [])
    {
        $param = input();
        if (!empty($pp)) {
            $param = $pp;
        }

        //分类
        $type_list = model('Type')->getCache('type_list');
        $this->assign('type_list', $type_list);

        if (!empty($param['pg'])) {
            $param['page'] = $param['pg'];
            unset($param['pg']);
        }
        // 中断重试计数只给 collectWatchdog 用，不能带进 model 拼的下一页地址
        $this->_cjretry = isset($param['_cjretry']) ? (int)$param['_cjretry'] : 0;
        unset($param['_cjretry']);
        @session_write_close();
        // 单条小说正文可达十几 MB（整页 JSON 解码后多次复制），默认 128M 会 OOM 致命退出，页面停在半截且不会跳转下一页
        $memLimit = ini_get('memory_limit');
        if ($memLimit != '-1') {
            $unit = strtolower(substr($memLimit, -1));
            $bytes = (int)$memLimit * ($unit == 'g' ? 1073741824 : ($unit == 'm' ? 1048576 : ($unit == 'k' ? 1024 : 1)));
            if ($bytes < 536870912) {
                @ini_set('memory_limit', '512M');
            }
        }
        @set_time_limit(300);

        if ($param['mid'] == '' || $param['mid'] == '1') {
            return $this->vod($param);
        } elseif ($param['mid'] == '2') {
            return $this->art($param);
        } elseif ($param['mid'] == '8') {
            return $this->actor($param);
        }
        elseif ($param['mid'] == '9') {
            return $this->role($param);
        }
        elseif ($param['mid'] == '11') {
            return $this->website($param);
        }
        elseif ($param['mid'] == '12') {
            return $this->manga($param);
        }
    }

    /**
     * 采集页看门狗：下一页只靠 *_data() 末尾的 mac_jump 跳转。单条耗时过长（大正文、慢查询、
     * 慢图片）时页面长时间无输出，CloudFront/nginx 超时断开，或 PHP 致命错误，mac_jump 都不会输出，
     * 页面就停在半截不再翻页。这里先输出一段脚本：文档加载结束却没有 mac_jump（window.__macJumped）
     * 时，重试本页；连续中断 2 次后跳过本页继续，保证整轮采集能走完。
     */
    private function collectWatchdog($param, $res)
    {
        $sec = (int)$GLOBALS['config']['app']['collect_timespan'];
        if ($sec <= 0) {
            $sec = 3;
        }
        $retry = (int)$this->_cjretry;
        $page = (int)$res['page']['page'];
        $pagecount = (int)$res['page']['pagecount'];

        if ($retry < 2) {
            $p = $param;
            $p['_cjretry'] = $retry + 1;
            $url = url('api') . '?' . http_build_query($p);
            $msg = lang('admin/collect/watchdog_retry', [$sec, $retry + 1]);
        } else {
            $p = $param;
            if ($param['ac'] == 'cjsel' || $page >= $pagecount) {
                unset($p['page'], $p['ids']);
                $p['ac'] = 'list';
            } else {
                $p['page'] = $page + 1;
            }
            $url = url('api') . '?' . http_build_query($p);
            $msg = lang('admin/collect/watchdog_skip', [$sec]);
        }
        $url = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $msg = json_encode(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        mac_echo('<script>window.addEventListener("load",function(){if(window.__macJumped){return;}var u=' . $url . ';'
            . 'var d=document.createElement("div");d.innerHTML="<span>"+' . $msg . '+"</span> &gt;&gt;&gt; ";'
            . 'var a=document.createElement("a");a.href=u;a.textContent=' . json_encode(lang('browser_jump'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';d.appendChild(a);document.body.appendChild(d);'
            . 'setTimeout(function(){location.href=u;},' . ($sec * 1000) . ');});</script>');
    }

    public function timing()
    {
        //当日视频分类ids
        $res = model('Vod')->updateToday('type');
        $this->assign('vod_type_ids_today', $res['data']);

        return $this->fetch('admin@collect/timing');
    }

    /**
     * 即时读取分类绑定配置（直接读文件，绕过 config 缓存 / 多进程 opcache 旧值）
     * 避免在保存/清理绑定时基于过期数据写回，覆盖其他进程刚写入的绑定。
     */
    private function loadBindConfig()
    {
        $file = APP_PATH . 'extra/bind.php';
        if (is_file($file)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
            $data = include $file;
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function clearbind()
    {
        $param = input();
        $config = [];
        if(!empty($param['cjflag'])){
            $bind_list = $this->loadBindConfig();
            foreach($bind_list as $k=>$v){
                if(strpos($k,$param['cjflag'])===false){
                    $config[$k] = $v;
                }
            }
        }

        $res = mac_arr2file( APP_PATH .'extra/bind.php', $config);
        if($res===false){
            return json(['code'=>0,'msg'=>lang('clear_err')]);
        }
        return json(['code'=>1,'msg'=>lang('clear_ok')]);
    }

    public function bind()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($col)){
            $config = $this->loadBindConfig();
            $config[$col] = intval($val);
            $data = [];
            $data['id'] = $col;
            $data['st'] = 0;
            $data['local_type_id'] = $val;
            $data['local_type_name'] = '';
            if(intval($val)>0){
                $data['st'] = 1;
                $type_list = model('Type')->getCache('type_list');
                $data['local_type_name'] = $type_list[$val]['type_name'];
            }

            $res = mac_arr2file( APP_PATH .'extra/bind.php', $config);
            if($res===false){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'),null, $data);
        }
        return $this->error(lang('param_err'));
    }

    public function vod($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_vod';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->vod($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);
            $this->assign('pagecount',$res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/vod');
        }
        $page_now = isset($param['page']) && strlen($param['page']) > 0 ? (int)$param['page'] : 1;
        mac_echo('<title>' . $page_now . '/' . (int)$res['page']['pagecount'] . ' collecting..</title>');
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->vod_data($param,$res );

    }

    public function art($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_art';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->art($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);
            $this->assign('pagecount',$res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/art');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->art_data($param,$res );
    }

    public function actor($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_actor';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->actor($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);
            $this->assign('pagecount',$res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/actor');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->actor_data($param,$res );
    }

    public function role($param)
    {
        if ($param['ac'] != 'list') {
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_role';
            Cache::set($key, url('collect/api') . '?' . http_build_query($param));
        }
        $res = model('Collect')->role($param);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }

        if ($param['ac'] == 'list') {

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach ($res['type'] as $k => $v) {
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if ($local_id > 0) {
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if (empty($type_name)) {
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page', $res['page']);
            $this->assign('type', $res['type']);
            $this->assign('list', $res['data']);

            $this->assign('total', $res['page']['recordcount']);
            $this->assign('page', $res['page']['page']);
            $this->assign('limit', $res['page']['pagesize']);
            $this->assign('pagecount', $res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param', $param);

            $this->assign('param_str', http_build_query($param));

            return $this->fetch('admin@collect/role');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->role_data($param,$res );
    }

    public function website($param)
    {
        if ($param['ac'] != 'list') {
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_website';
            Cache::set($key, url('collect/api') . '?' . http_build_query($param));
        }
        $res = model('Collect')->website($param);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }

        if ($param['ac'] == 'list') {

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');

            foreach ($res['type'] as $k => $v) {
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if ($local_id > 0) {
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $type_list[$local_id]['type_name'];
                    if (empty($type_name)) {
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page', $res['page']);
            $this->assign('type', $res['type']);
            $this->assign('list', $res['data']);

            $this->assign('total', $res['page']['recordcount']);
            $this->assign('page', $res['page']['page']);
            $this->assign('limit', $res['page']['pagesize']);
            $this->assign('pagecount', $res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param', $param);

            $this->assign('param_str', http_build_query($param));

            return $this->fetch('admin@collect/website');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->website_data($param,$res );
    }

    public function manga($param)
    {
        if($param['ac'] != 'list'){
            $key = $GLOBALS['config']['app']['cache_flag']. '_'.'collect_break_manga';
            Cache::set($key, url('collect/api').'?'. http_build_query($param) );
        }
        $res = model('Collect')->manga($param);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if($param['ac'] == 'list'){

            $bind_list = config('bind');
            $type_list = model('Type')->getCache('type_list');
            $manga_type_list = [];
            foreach($type_list as $k=>$v){
                if($v['type_mid'] == 12){
                    $manga_type_list[$k] = $v;
                }
            }
            $this->assign('type_list', $manga_type_list);

            foreach($res['type'] as $k=>$v){
                $key = $param['cjflag'] . '_' . $v['type_id'];
                $res['type'][$k]['isbind'] = 0;
                $local_id = intval($bind_list[$key]);
                if( $local_id>0 ){
                    $res['type'][$k]['isbind'] = 1;
                    $res['type'][$k]['local_type_id'] = $local_id;
                    $type_name = $manga_type_list[$local_id]['type_name'];
                    if(empty($type_name)){
                        $type_name = lang('unknown_type');
                    }
                    $res['type'][$k]['local_type_name'] = $type_name;
                }
            }

            $this->assign('page',$res['page']);
            $this->assign('type',$res['type']);
            $this->assign('list',$res['data']);

            $this->assign('total',$res['page']['recordcount']);
            $this->assign('page',$res['page']['page']);
            $this->assign('limit',$res['page']['pagesize']);
            $this->assign('pagecount',$res['page']['pagecount']);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param',$param);

            $this->assign('param_str',http_build_query($param)) ;

            return $this->fetch('admin@collect/manga');
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $this->collectWatchdog($param, $res);
        model('Collect')->manga_data($param,$res );
    }
}
