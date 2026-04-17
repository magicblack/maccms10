<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * 提现管理 API
 *
 * 提供用户积分提现的申请、列表查询、删除等功能。
 * 所有接口均需用户登录（Cookie/Session 认证）。
 */
class Cash extends Base
{
    use PublicApi;

    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    /**
     * 辅助：检查登录
     */
    private function _checkLogin()
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1401, 'msg' => '未登录，请先登录'])];
        }
        $uid  = intval($check['info']['user_id']);
        $user = Db::name('User')->where('user_id', $uid)->find();
        if (!$user) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1002, 'msg' => '用户不存在'])];
        }
        return ['ok' => true, 'user_id' => $uid, 'user' => $user, 'response' => null];
    }

    /**
     * 获取提现列表
     * GET /api.php/cash/get_list?page=1&limit=20
     *
     * @param  page    int  可选，页码，默认1
     * @param  limit   int  可选，每页条数，默认20，最大100
     * @param  status  int  可选，提现状态筛选（0=待审核，1=已审核）
     * @return JSON    {code:1, msg:'获取成功', info:{page, pagecount, limit, total, list:[...]}}
     */
    public function get_list(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => '参数错误: ' . $validate->getError()]);
        }

        $page  = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));

        $where = ['user_id' => $auth['user_id']];

        if (isset($param['status']) && $param['status'] !== '') {
            $where['cash_status'] = intval($param['status']);
        }

        $order = 'cash_id desc';
        $res   = model('Cash')->listData($where, $order, $page, $limit);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res,
        ]);
    }

    /**
     * 获取提现详情
     * GET /api.php/cash/get_detail?cash_id=1
     *
     * @param  cash_id  int  必填，提现记录ID
     * @return JSON     {code:1, msg:'获取成功', info:{...}}
     */
    public function get_detail(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => '参数错误: ' . $validate->getError()]);
        }

        $cash_id = intval($param['cash_id'] ?? 0);

        $where = [
            'cash_id' => $cash_id,
            'user_id' => $auth['user_id'],
        ];

        $res = model('Cash')->infoData($where);
        return json($res);
    }

    /**
     * 提交提现申请
     * POST /api.php/cash/create
     *
     * @param  cash_money      float   必填，提现金额（单位：元）
     * @param  cash_bank_name  string  必填，银行名称
     * @param  cash_bank_no    string  必填，银行账号
     * @param  cash_payee_name string  必填，收款人姓名
     * @return JSON            {code:1, msg:'保存成功'}
     *
     * 说明：
     * - 提现需后台开启提现功能（cash_status=1）
     * - 提现金额不能低于后台设置的最小提现金额（cash_min）
     * - 提现所需积分 = 提现金额 × 提现兑换比例（cash_ratio）
     * - 提现后对应积分会冻结，待管理员审核后正式扣除
     */
    public function create(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => '参数错误: ' . $validate->getError()]);
        }

        $param['user_id'] = $auth['user_id'];
        $res = model('Cash')->saveData($param);
        return json($res);
    }

    /**
     * 删除提现记录
     * POST /api.php/cash/del
     *
     * @param  ids  string  可选，提现记录ID列表，逗号分隔（与 all 二选一）
     * @param  all  string  可选，传 "1" 表示删除全部
     * @return JSON {code:1, msg:'删除成功'}
     *
     * 说明：
     * - 仅能删除当前登录用户的提现记录
     * - 未审核的提现记录删除后，冻结积分会自动恢复
     */
    public function del(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => '参数错误: ' . $validate->getError()]);
        }

        $ids   = htmlspecialchars(urldecode(trim($param['ids'] ?? '')));
        $all   = $param['all'] ?? '';

        if (empty($ids) && $all != '1') {
            return json(['code' => 1001, 'msg' => '参数错误: ids 或 all=1 必须']);
        }

        $where = ['user_id' => $auth['user_id']];

        if ($all != '1') {
            $arr = array_filter(array_map('intval', explode(',', $ids)));
            if (empty($arr)) {
                return json(['code' => 1001, 'msg' => '参数错误: ids 格式不正确']);
            }
            $where['cash_id'] = ['in', implode(',', $arr)];
        }

        $res = model('Cash')->delData($where);
        return json($res);
    }

    /**
     * 获取提现配置信息
     * GET /api.php/cash/get_config
     *
     * @return JSON  {code:1, msg:'获取成功', info:{cash_status, cash_min, cash_ratio}}
     *
     * 说明：
     * - cash_status: 提现功能开关（0=关闭, 1=开启）
     * - cash_min: 最小提现金额（单位：元）
     * - cash_ratio: 兑换比例（1元 = 多少积分）
     */
    public function get_config(Request $request)
    {
        $user_config = $GLOBALS['config']['user'] ?? [];

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'cash_status' => intval($user_config['cash_status'] ?? 0),
                'cash_min'    => floatval($user_config['cash_min'] ?? 0),
                'cash_ratio'  => intval($user_config['cash_ratio'] ?? 1),
            ],
        ]);
    }
}
