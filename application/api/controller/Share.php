<?php
namespace app\api\controller;

use think\Request;
use app\common\util\CsrfGuard;

class Share extends Base
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

    public function record(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('share/login_required')]);
        }
        $csrfErr = $this->checkCsrf();
        if ($csrfErr) {
            return json($csrfErr);
        }
        if (!$this->_rateCheck('share_rate_' . $user['user_id'], 3)) {
            return json(['code' => 1003, 'msg' => lang('share/rate_limit')]);
        }
        $param = $request->param();
        $mid = intval($param['mid'] ?? 0);
        $rid = intval($param['rid'] ?? 0);
        $platform = trim($param['platform'] ?? '');
        if ($mid < 1 || $rid < 1 || empty($platform)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $res = model('ShareLog')->saveData([
            'user_id' => $user['user_id'],
            'share_mid' => $mid,
            'share_rid' => $rid,
            'share_platform' => $platform,
        ]);
        if ($res['code'] == 1) {
            try {
                model('TaskLog')->addProgress($user['user_id'], 'share_vod', 1);
            } catch (\Exception $e) {
            }
        }
        return json($res);
    }

    public function history(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('share/login_required')]);
        }
        $param = $request->param();
        // 安全：仅返回当前登录用户自己的分享历史，忽略外部传入的 uid（防 IDOR）
        $uid = $user['user_id'];
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $where = ['user_id' => $uid];
        $res = model('ShareLog')->listData($where, 'share_time desc', $page, $limit);
        return json($res);
    }
}