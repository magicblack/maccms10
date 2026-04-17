<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * 充值订单管理 API
 *
 * 提供订单列表、详情、状态查询（创建订单走 index 模块 POST user/buy）。
 * 所有接口均需用户登录（Cookie/Session 认证）。
 */
class Order extends Base
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
     * 创建充值订单
     * POST /api.php/order/create
     *
     * @param  price  float  必填，充值金额（单位：元）
     * @return JSON   {code:1, msg:'订单创建成功', info:{order_code, order_price, order_points, order_time}}
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

        $price = floatval($param['price'] ?? 0);

        $pay_config = config('maccms.pay');
        if (!empty($pay_config['min']) && $price < $pay_config['min']) {
            return json(['code' => 1002, 'msg' => '最小充值金额不能低于' . $pay_config['min'] . '元']);
        }

        $data = [];
        $data['user_id']      = $auth['user_id'];
        $data['order_code']   = 'PAY' . mac_get_uniqid_code();
        $data['order_price']  = $price;
        $data['order_time']   = time();
        $data['order_points'] = intval(($pay_config['scale'] ?? 1) * $price);

        $res = model('Order')->saveData($data);
        if ($res['code'] > 1) {
            return json($res);
        }

        return json([
            'code' => 1,
            'msg'  => '订单创建成功',
            'info' => [
                'order_code'   => $data['order_code'],
                'order_price'  => $data['order_price'],
                'order_points' => $data['order_points'],
                'order_time'   => $data['order_time'],
            ],
        ]);
    }

    /**
     * 获取用户订单列表
     * GET /api.php/order/get_list?page=1&limit=20&status=
     *
     * @param  page    int     可选，页码，默认1
     * @param  limit   int     可选，每页条数，默认20，最大100
     * @param  status  int     可选，订单状态筛选（0=未支付，1=已支付）
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

        $where = [];
        $where['o.user_id'] = $auth['user_id'];

        if (isset($param['status']) && $param['status'] !== '') {
            $where['order_status'] = intval($param['status']);
        }

        $order = 'o.order_id desc';
        $res   = model('Order')->listData($where, $order, $page, $limit);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res,
        ]);
    }

    /**
     * 获取订单详情
     * GET /api.php/order/get_detail?order_id=1  或 ?order_code=PAYxxx
     *
     * @param  order_id    int     可选，订单ID（与 order_code 二选一）
     * @param  order_code  string  可选，订单号
     * @return JSON        {code:1, msg:'获取成功', info:{...}}
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

        $where = [];
        $where['user_id'] = $auth['user_id'];

        if (!empty($param['order_id'])) {
            $where['order_id'] = intval($param['order_id']);
        } elseif (!empty($param['order_code'])) {
            $where['order_code'] = htmlspecialchars(urldecode(trim($param['order_code'])));
        } else {
            return json(['code' => 1001, 'msg' => '参数错误: order_id 或 order_code 必须']);
        }

        $res = model('Order')->infoData($where);
        return json($res);
    }

    /**
     * 查询订单支付状态
     * GET /api.php/order/check_status?order_code=PAYxxx
     *
     * @param  order_code  string  必填，订单号
     * @return JSON        {code:1, msg:'...', info:{order_code, order_status, order_status_text, order_pay_type, order_pay_time}}
     */
    public function check_status(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => '参数错误: ' . $validate->getError()]);
        }

        $order_code = htmlspecialchars(urldecode(trim($param['order_code'] ?? '')));

        if (empty($order_code)) {
            return json(['code' => 1001, 'msg' => '参数错误: order_code 必须']);
        }

        $where = [];
        $where['order_code'] = $order_code;
        $where['user_id']    = $auth['user_id'];

        $res = model('Order')->infoData($where);
        if ($res['code'] > 1) {
            return json($res);
        }

        $info = $res['info'];
        $status_text = $info['order_status'] == 1 ? '已支付' : '未支付';

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'order_code'        => $info['order_code'],
                'order_price'       => $info['order_price'],
                'order_points'      => $info['order_points'],
                'order_status'      => intval($info['order_status']),
                'order_status_text' => $status_text,
                'order_pay_type'    => $info['order_pay_type'] ?? '',
                'order_pay_time'    => $info['order_pay_time'] ?? 0,
                'order_time'        => $info['order_time'],
            ],
        ]);
    }
}
