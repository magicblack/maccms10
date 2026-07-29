<?php
namespace app\api\controller;

use think\Request;
use app\common\util\CsrfGuard;

class Follow extends Base
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

    public function follow(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
        }
        $csrfErr = $this->checkCsrf();
        if ($csrfErr) {
            return json($csrfErr);
        }
        if (!$this->_rateCheck('follow_rate_' . $user['user_id'], 3)) {
            return json(['code' => 1003, 'msg' => lang('follow/rate_limit')]);
        }
        $param = $request->param();
        $follow_uid = intval($param['follow_uid'] ?? 0);
        if ($follow_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $res = model('Follow')->saveData([
            'user_id' => $user['user_id'],
            'follow_uid' => $follow_uid,
        ]);
        return json($res);
    }

    public function unfollow(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
        }
        $csrfErr = $this->checkCsrf();
        if ($csrfErr) {
            return json($csrfErr);
        }
        if (!$this->_rateCheck('unfollow_rate_' . $user['user_id'], 3)) {
            return json(['code' => 1003, 'msg' => lang('follow/rate_limit')]);
        }
        $param = $request->param();
        $follow_uid = intval($param['follow_uid'] ?? 0);
        if ($follow_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $res = model('Follow')->unfollow($user['user_id'], $follow_uid);
        return json($res);
    }

    public function following(Request $request)
    {
        $param = $request->param();
        $uid = intval($param['uid'] ?? 0);
        if ($uid < 1) {
            $user = $this->_checkLoginForApi();
            if (!$user) {
                return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
            }
            $uid = $user['user_id'];
        }
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $where = ['user_id' => $uid, 'follow_status' => 1];
        $res = model('Follow')->listData($where, 'follow_time desc', $page, $limit);
        return json($res);
    }

    public function fans(Request $request)
    {
        $param = $request->param();
        $uid = intval($param['uid'] ?? 0);
        if ($uid < 1) {
            $user = $this->_checkLoginForApi();
            if (!$user) {
                return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
            }
            $uid = $user['user_id'];
        }
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $where = ['follow_uid' => $uid, 'follow_status' => 1];
        $res = model('Follow')->listData($where, 'follow_time desc', $page, $limit, 0, '*', 'user_id');
        return json($res);
    }

    public function mutual(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
        }
        $param = $request->param();
        $follow_uid = intval($param['follow_uid'] ?? 0);
        if ($follow_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $is_mutual = model('Follow')->isMutual($user['user_id'], $follow_uid);
        return json(['code' => 1, 'msg' => 'ok', 'info' => ['mutual' => $is_mutual ? 1 : 0]]);
    }

    public function status(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('follow/login_required')]);
        }
        $param = $request->param();
        $follow_uid = intval($param['follow_uid'] ?? 0);
        if ($follow_uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $following = model('Follow')->isFollowing($user['user_id'], $follow_uid);
        $mutual = model('Follow')->isMutual($user['user_id'], $follow_uid);
        return json([
            'code' => 1,
            'msg' => 'ok',
            'info' => [
                'following' => $following ? 1 : 0,
                'mutual' => $mutual ? 1 : 0,
                'following_count' => model('Follow')->followingCount($user['user_id']),
                'fans_count' => model('Follow')->fansCount($user['user_id']),
            ],
        ]);
    }

    /**
     * 公开用户资料：游客可读。
     * 关注 / 私信等写操作仍各自要求登录，本接口不放宽任何写权限。
     */
    public function profile(Request $request)
    {
        $param = $request->param();
        $uid = intval($param['uid'] ?? 0);
        if ($uid < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $user = $this->_checkLoginForApi();
        $viewer_id = $user ? intval($user['user_id']) : 0;
        $res = model('Follow')->profileData($uid, $viewer_id);
        return json($res);
    }
}