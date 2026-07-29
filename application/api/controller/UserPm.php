<?php
namespace app\api\controller;

use think\Request;
use app\common\util\CsrfGuard;

class UserPm extends Base
{
    use PublicApi;
    use CsrfGuard;

    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    public function index()
    {
    }

    private function _checkLoginForApi()
    {
        $res = model('User')->checkLogin();
        if ($res['code'] == 1) {
            return $res['info'];
        }
        return false;
    }

    /**
     * 原子化频率检查：Redis 后端用 SETNX，文件缓存回退到 has+set
     */
    private function _rateCheck($key, $interval)
    {
        try {
            $handler = \think\Cache::init()->handler();
            if ($handler instanceof \Redis) {
                return (bool)$handler->set($key, 1, ['NX', 'EX' => (int)$interval]);
            }
        } catch (\Exception $e) {
        }
        if (\think\Cache::has($key)) {
            return false;
        }
        \think\Cache::set($key, 1, (int)$interval);
        return true;
    }

    public function send(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('pm/login_required')]);
        }
        $csrfErr = $this->checkCsrf();
        if ($csrfErr) {
            return json($csrfErr);
        }
        $param = $request->param();
        $to_uid = intval($param['to_uid'] ?? 0);
        $content = trim($param['content'] ?? '');
        if ($to_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        if (empty($content)) {
            return json(['code' => 1002, 'msg' => lang('pm/content_empty')]);
        }

        // 原子化频率限制（Redis SETNX 优先，避免并发 TOCTOU）
        if (!$this->_rateCheck('pm_rate_' . $user['user_id'], 3)) {
            return json(['code' => 1003, 'msg' => lang('pm/rate_limit')]);
        }

        $res = model('UserPm')->saveData([
            'from_uid' => $user['user_id'],
            'to_uid' => $to_uid,
            'pm_content' => $content,
        ]);
        return json($res);
    }

    public function conversation(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('pm/login_required')]);
        }
        $param = $request->param();
        $peer_uid = intval($param['peer_uid'] ?? 0);
        if ($peer_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 50;

        // mark read
        model('UserPm')->markRead($user['user_id'], $peer_uid);

        $res = model('UserPm')->conversation($user['user_id'], $peer_uid, $page, $limit);
        return json($res);
    }

    public function conversations(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('pm/login_required')]);
        }
        $param = $request->param();
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $res = model('UserPm')->conversationList($user['user_id'], $page, $limit);
        return json($res);
    }

    public function unread(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('pm/login_required')]);
        }
        $count = model('UserPm')->unreadCount($user['user_id']);
        return json(['code' => 1, 'msg' => 'ok', 'info' => ['unread' => $count]]);
    }
}