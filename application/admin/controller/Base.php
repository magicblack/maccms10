<?php
namespace app\admin\controller;
use think\Controller;
use app\common\controller\All;
use app\common\util\BulkTableIo;
use think\Cache;
use app\common\util\Dir;
use think\Db;

class Base extends All
{
    var $_admin;
    var $_pagesize;
    var $_makesize;

    public function __construct()
    {
        parent::__construct();

        // 校验Update.php文件完整性
        $update_file = APP_PATH . 'admin/controller/Update.php';
        $expected_hash = config('version.update_hash');
        if (!empty($expected_hash) && is_file($update_file) && md5_file($update_file) !== $expected_hash) {
            exit(lang('admin/update/core_file_error'));
        }

        //判断用户登录状态
        if(in_array($this->_cl,['Index']) && in_array($this->_ac,['login'])) {

        }
        elseif(ENTRANCE=='api' && in_array($this->_cl,['Timming']) && in_array($this->_ac,['index'])){

        }
        else {
            $res = model('Admin')->checkLogin();
            if ($res['code'] > 1) {
                return $this->redirect('index/login');
            }
            $this->_admin = $res['info'];
            $this->_pagesize = $GLOBALS['config']['app']['pagesize'];
            $this->_makesize = $GLOBALS['config']['app']['makesize'];

            if($this->_cl!='Update' && !$this->check_auth($this->_cl,$this->_ac)){
                return $this->error(lang('permission_denied'));
            }
        }
        $this->assign('cl',$this->_cl);
        $this->assign('MAC_VERSION',config('version')['code']);
    }

    public function check_auth($c,$a)
    {
        $c = strtolower($c);
        $a = strtolower($a);

        // UEditor AI proxy: logged-in admin only; API key never sent to browser.
        if ($c === 'upload' && ($a === 'ueditor_ai' || $a === 'ueditorai')) {
            return true;
        }

        if ($c === 'assistant' && in_array($a, ['chat', 'ping'], true)) {
            $assistantCfg = config('maccms.admin_assistant');
            $scope = is_array($assistantCfg) && isset($assistantCfg['access_scope'])
                ? strtolower(trim((string)$assistantCfg['access_scope']))
                : 'all';
            if ($scope === 'super' && (string)$this->_admin['admin_id'] !== '1') {
                return false;
            }
            return true;
        }

        $auths = $this->_admin['admin_auth'] . ',index/index,index/welcome,index/logout,';
        $cur = ','.$c.'/'.$a.',';
        if($this->_admin['admin_id'] =='1'){
            return true;
        }
        elseif(strpos($auths,$cur)===false){
            return false;
        }
        else{
            return true;
        }
    }

    public function _cache_clear()
    {
        if(ENTRANCE=='admin') {
            //播放器配置缓存
            $vodplayer = config('vodplayer');
            $voddowner = config('voddowner');
            $vodserver = config('vodserver');
            $player = [];
            foreach ($vodplayer as $k => $v) {
                $player[$k] = [
                    'show' => (string)$v['show'],
                    'des' => (string)$v['des'],
                    'ps' => (string)$v['ps'],
                    'parse' => (string)$v['parse'],
                ];
            }
            $downer = [];
            foreach ($voddowner as $k => $v) {
                $downer[$k] = [
                    'show' => (string)$v['show'],
                    'des' => (string)$v['des'],
                    'ps' => (string)$v['ps'],
                    'parse' => (string)$v['parse'],
                ];
            }

            $server = [];
            foreach ($vodserver as $k => $v) {
                $server[$k] = [
                    'show' => (string)$v['show'],
                    'des' => (string)$v['des']
                ];
            }
            $content = 'MacPlayerConfig.player_list=' . json_encode($player) . ',MacPlayerConfig.downer_list=' . json_encode($downer) . ',MacPlayerConfig.server_list=' . json_encode($server) . ';';
            $path = './static/js/playerconfig.js';
            if (!file_exists($path)) {
                $path .= '.bak';
            }
            $fc = @file_get_contents($path);
            if(!empty($fc)){
	            $jsb = mac_get_body($fc, '//缓存开始', '//缓存结束');
	            $fc = str_replace($jsb, "\r\n" . $content . "\r\n", $fc);
	            @fwrite(fopen('./static/js/playerconfig.js', 'wb'), $fc);
            }
        }

        Dir::delDir(RUNTIME_PATH.'cache/');
        Dir::delDir(RUNTIME_PATH.'log/');
        Dir::delDir(RUNTIME_PATH.'temp/');

        Cache::clear();

        return true;
    }

    public function batch_replace($field,$model,$search,$replace,$type='vod')
    {
        $replaceres = [];
        if(isset($model[$field]) && $search !== ''){
            if(empty($replace)) $replace = '';
            
            $original_value = $model[$field];
            $new_value = mac_filter_xss(str_replace($search, $replace, $original_value));

            if($original_value !== $new_value){
                $replaceres[$field] = $new_value;
                $replaceres['des'] = '&nbsp;'.lang('admin/batch/replace').'['.lang('admin/batch/field_'.str_replace($type.'_','',$field)).']：'.mac_filter_xss($search).'→'.mac_filter_xss($replace).'；';
            }
            else{
                $replaceres['des'] = '&nbsp;'.lang('admin/batch/no_match').'；';
            }
        }
        return $replaceres;
    }

    public function base_export($param,$table,$where)
    {
        $max = min(BulkTableIo::MAX_EXPORT_ROWS, max(1, intval($param['max'] ?? 5000)));
        $format = (isset($param['format']) && $param['format'] === 'xlsx') ? 'xlsx' : 'csv';
        if ($format === 'xlsx' && !class_exists('ZipArchive')) {
            return $this->error(lang('admin/batch/io_need_zip'));
        }
        $fields = Db::name(ucfirst($table))->getTableFields();
        $list = Db::name(ucfirst($table))->where($where)->order("{$table}_id desc")->limit($max)->select();
        $base = $table.'_export_' . date('Ymd_His');
        if ($format === 'xlsx') {
            BulkTableIo::exportXlsxDownload($base, $fields, $list);
        } else {
            BulkTableIo::exportCsvDownload($base, $fields, $list);
        }
        exit;
    }

    public function base_import($table)
    {
        if (!request()->isPost()) {
            return $this->error(lang('illegal_request'));
        }
        $param = input('post.');
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($param)) {
            return $this->error($validate->getError());
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->error(lang('param_err'));
        }
        $info = $file->rule('uniqid')->validate(['size' => 20971520, 'ext' => 'csv,txt,xlsx']);
        if (!$info) {
            return $this->error($file->getError());
        }
        $path = $info->getPathname();
        $ext = strtolower(pathinfo($info->getInfo('name'), PATHINFO_EXTENSION));
        try {
            $parsed = BulkTableIo::parseFile($path, $ext);
        } catch (\Exception $e) {
            @unlink($path);
            return $this->error(lang('import_err'));
        }
        @unlink($path);
        $fields = Db::name(ucfirst($table))->getTableFields();
        $ok = 0;
        $fail = 0;
        $errLines = [];
        $n = 0;
        foreach ($parsed['rows'] as $idx => $row) {
            $n++;
            if ($n > BulkTableIo::MAX_IMPORT_ROWS) {
                break;
            }
            $data = BulkTableIo::filterRowKeys($row, $fields);
            if (empty($data[$table.'_name']) || !isset($data['type_id']) || $data['type_id'] === '') {
                $fail++;
                if (count($errLines) < 15) {
                    $errLines[] = lang('admin/batch/io_row', [$idx + 2]) . ' ' . lang('param_err');
                }
                continue;
            }
            $data = BulkTableIo::prepareGenericForSave($data,$table);
            $res = model(ucfirst($table))->saveData($data);
            if ($res['code'] > 1) {
                $fail++;
                if (count($errLines) < 15) {
                    $errLines[] = lang('admin/batch/io_row', [$idx + 2]) . ' ' . $res['msg'];
                }
            } else {
                $ok++;
                if($table === 'vod'){
                    Cache::rm('vod_repeat_table_created_time');
                }
            }
        }
        if ($ok === 0 && $fail === 0) {
            return $this->error(lang('import_err'));
        }
        $msg = lang('admin/batch/io_ok', [$ok]);
        if ($fail > 0) {
            $msg .= ' ' . lang('admin/batch/io_fail', [$fail]);
            if (!empty($errLines)) {
                $msg .= ' — ' . implode('；', $errLines);
            }
        }
        if ($ok === 0) {
            return $this->error($msg);
        }
        return $this->success($msg);
    }

}
