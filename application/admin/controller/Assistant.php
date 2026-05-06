<?php
namespace app\admin\controller;

use app\common\util\AdminAssistantService;

class Assistant extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * JSON chat endpoint (raw JSON body).
     */
    public function chat()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 1000, 'msg' => lang('admin/assistant/err_post')]);
        }

        $svc = new AdminAssistantService();
        if (!$svc->isEnabled()) {
            return json(['code' => 1003, 'msg' => lang('admin/assistant/err_disabled')]);
        }
        $cfg = $svc->getMergedConfig();
        $perMin = max(1, min(120, intval($cfg['rate_per_minute'])));
        $adminId = isset($this->_admin['admin_id']) ? (int)$this->_admin['admin_id'] : 0;
        if (!$svc->consumeRateLimit($adminId, $perMin)) {
            return json(['code' => 1009, 'msg' => lang('admin/assistant/err_rate')]);
        }

        $raw = $this->request->getContent();
        $in = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $in = is_array($decoded) ? $decoded : [];
        }
        $q = isset($in['message']) ? (string)$in['message'] : '';
        $history = isset($in['history']) && is_array($in['history']) ? $in['history'] : [];

        $out = $svc->chat($q, $history);
        return json($out);
    }

    /**
     * Whether the floating widget should call the API (still requires enabled + key for real replies).
     */
    public function ping()
    {
        $svc = new AdminAssistantService();
        return json([
            'code' => 1,
            'enabled' => $svc->isEnabled() ? 1 : 0,
        ]);
    }
}
