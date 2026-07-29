<?php
namespace app\api\controller;

use think\Request;

class Dynamics extends Base
{
    use PublicApi;

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

    public function feed(Request $request)
    {
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('dynamics/login_required')]);
        }
        $param = $request->param();
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $res = model('Dynamics')->feedData($user['user_id'], $page, $limit);
        return json($res);
    }

    public function user_dynamics(Request $request)
    {
        $param = $request->param();
        $uid = intval($param['uid'] ?? 0);
        if ($uid < 1) {
            $user = $this->_checkLoginForApi();
            if (!$user) {
                return json(['code' => 1010, 'msg' => lang('dynamics/login_required')]);
            }
            $uid = $user['user_id'];
        }
        $page = isset($param['page']) ? max(1, intval($param['page'])) : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;
        $res = model('Dynamics')->userData($uid, $page, $limit);
        return json($res);
    }
}