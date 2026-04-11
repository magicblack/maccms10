<?php
namespace app\admin\controller;
use think\Db;
use think\Log;
use app\common\util\UeditorAiCsrf;
use app\common\util\UeditorAiProxy;



class Upload extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $this->assign('path',$param['path']);
        $this->assign('id',$param['id']);

        $this->assign('title',lang('upload_pic'));
        return $this->fetch('admin@upload/index');
    }

    public function test()
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        if($temp_file){
            echo lang('admin/upload/test_write_ok').'：' . $temp_file;
        }
        else{
            echo lang('admin/upload/test_write_err').'：' . sys_get_temp_dir() ;
        }
    }

    public function upload($p=[])
    {
		return model('Upload')->upload($p);
    }

    /**
     * UEditorPlus AI dialog proxy (JSON). Key and api_base only from ai_seo server-side.
     */
    public function ueditorAi()
    {
        /* 对话框与后台可能不同 iframe/静态域，读不到 UE_AI_CSRF；用同源 GET + Cookie 取 session 内令牌再 POST */
        if ($this->request->isGet() && (string) input('fetch_csrf', '') === '1') {
            return json(['code' => 0, 'msg' => 'ok', 'data' => ['token' => UeditorAiCsrf::token()]]);
        }

        if (!$this->request->isPost()) {
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/bad_method'), 'data' => null]);
        }

        $raw = $this->request->getContent();
        $in = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $in = is_array($decoded) ? $decoded : [];
        }

        $csrf = isset($in['_csrf_token']) ? (string) $in['_csrf_token'] : '';
        if ($csrf === '' && method_exists($this->request, 'cookie')) {
            $ck = $this->request->cookie('ueditor_ai_csrf');
            if ($ck !== null && $ck !== '') {
                $csrf = (string) $ck;
            }
        }
        if (!UeditorAiCsrf::validate($csrf)) {
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/invalid_token'), 'data' => null]);
        }

        if (!$this->ueditorAiRateLimit(30, 60)) {
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/rate_limit'), 'data' => null]);
        }

        $system = isset($in['system_prompt']) ? (string) $in['system_prompt'] : '';
        $user = isset($in['user_prompt']) ? (string) $in['user_prompt'] : '';

        $config = config('maccms');
        $ai = isset($config['ai_seo']) && is_array($config['ai_seo']) ? $config['ai_seo'] : [];
        $enabled = isset($ai['enabled']) ? (string) $ai['enabled'] : '0';
        if ($enabled !== '1') {
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/disabled'), 'data' => null]);
        }
        if (trim((string) (isset($ai['api_key']) ? $ai['api_key'] : '')) === '') {
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/no_key'), 'data' => null]);
        }

        $provider = isset($ai['provider']) ? strtolower(trim((string) $ai['provider'])) : '';
        try {
            $result = UeditorAiProxy::complete($ai, $system, $user);
        } catch (\Throwable $e) {
            Log::error('ueditor_ai exception: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => lang('admin/ueditor_ai/upstream_fail'), 'data' => null]);
        }

        if (!$result['ok']) {
            Log::error('ueditor_ai upstream fail admin_id=' . (isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : '')
                . ' provider=' . $provider . ' detail=' . (isset($result['log_detail']) ? $result['log_detail'] : ''));

            return json(['code' => 1, 'msg' => $result['error'], 'data' => null]);
        }

        Log::write(
            'ueditor_ai ok admin_id=' . (isset($this->_admin['admin_id']) ? $this->_admin['admin_id'] : '') . ' provider=' . $provider,
            'log'
        );

        return json(['code' => 0, 'msg' => 'ok', 'data' => ['text' => $result['text']]]);
    }

    private function ueditorAiRateLimit($limit, $window)
    {
        $adminId = isset($this->_admin['admin_id']) ? (string) $this->_admin['admin_id'] : '0';
        $key = 'ueditor_ai_rl_' . $adminId;
        $count = (int) cache($key);
        if ($count >= $limit) {
            return false;
        }
        if ($count === 0) {
            cache($key, 1, $window);
        } else {
            cache($key, $count + 1, $window);
        }

        return true;
    }


}
