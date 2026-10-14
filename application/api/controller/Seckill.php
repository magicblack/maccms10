<?php

namespace app\api\controller;

use app\common\util\CsrfGuard;
use think\Db;
use think\Request;

/**
 * 秒杀 API
 *
 * 提供秒杀活动列表、详情与购买（积分扣减 + 权益发放）。
 */
class Seckill extends Base
{
    use PublicApi;
    use CsrfGuard;

    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    private function _checkLogin()
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1401, 'msg' => lang('model/user/not_login')])];
        }
        $uid  = intval($check['info']['user_id']);
        $user = Db::name('User')->where('user_id', $uid)->find();
        if (!$user) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1002, 'msg' => lang('model/user/not_found')])];
        }
        return ['ok' => true, 'user_id' => $uid, 'user' => $user, 'response' => null];
    }

    /**
     * 秒杀活动列表
     * GET /api.php/seckill/get_list
     */
    public function get_list(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $now = time();
        $where = [];
        $where['seckill_status'] = 1;
        $where['seckill_start_time'] = [['eq', 0], ['elt', $now], 'or'];
        $where['seckill_end_time'] = [['eq', 0], ['egt', $now], 'or'];

        $order = 'seckill_time desc';
        $res = model('Seckill')->listData($where, $order);

        // 当前用户已参与过的秒杀 ID，供前端提前置灰按钮，避免只能在点击失败后才提示
        $ids = array_column($res['list'], 'seckill_id');
        $boughtIds = [];
        if (!empty($ids)) {
            $boughtIds = Db::name('seckill_user')
                ->where('user_id', $auth['user_id'])
                ->where('seckill_id', 'in', $ids)
                ->column('seckill_id');
            $boughtIds = array_map('intval', $boughtIds);
        }

        // 附加倒计时
        $list = [];
        foreach ($res['list'] as $row) {
            $end = intval($row['seckill_end_time']);
            $row['countdown'] = ($end > 0 && $end > $now) ? ($end - $now) : 0;
            $row['stock_remaining'] = max(0, intval($row['seckill_total']) - intval($row['seckill_sold']));
            $row['already_bought'] = in_array(intval($row['seckill_id']), $boughtIds, true) ? 1 : 0;
            $list[] = $row;
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'list'  => $list,
                'total' => $res['total'],
            ],
        ]);
    }

    /**
     * 秒杀详情
     * GET /api.php/seckill/get_detail?seckill_id=1
     */
    public function get_detail(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $seckillId = intval($request->param('seckill_id', 0));
        if ($seckillId < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $res = model('Seckill')->infoData(['seckill_id' => $seckillId]);
        if (intval($res['code']) === 1 && !empty($res['info'])) {
            $bought = Db::name('seckill_user')
                ->where('user_id', $auth['user_id'])
                ->where('seckill_id', $seckillId)
                ->count();
            $res['info']['already_bought'] = $bought > 0 ? 1 : 0;
        }
        return json($res);
    }

    /**
     * 秒杀购买
     * POST /api.php/seckill/buy  参数 seckill_id
     */
    public function buy(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $csrfErr = $this->checkCsrf();
        if ($csrfErr !== null) return json($csrfErr);

        $limited = $this->apiRateLimit('seckill_buy', $auth['user_id'], 20, 60);
        if ($limited !== true) {
            return $limited;
        }

        $seckillId = intval($request->param('seckill_id', 0));
        if ($seckillId < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $res = model('Seckill')->buy($seckillId, $auth['user_id']);
        if (intval($res['code']) === 1) {
            // 刷新用户会员组 cookie（Cookie 登录用户；JWT 用户无 cookie 不受影响）
            $freshUser = Db::name('User')->where('user_id', $auth['user_id'])->find();
            if ($freshUser) {
                $groupList = model('Group')->getCache('group_list');
                $gid = intval($freshUser['group_id']);
                if (isset($groupList[$gid])) {
                    cookie('group_id', $gid, ['expire' => 2592000]);
                    cookie('group_name', $groupList[$gid]['group_name'], ['expire' => 2592000]);
                }
            }
        }
        return json($res);
    }
}
