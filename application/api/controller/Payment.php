<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * 支付/充值 API
 *
 * 提供支付配置查询、发起支付、支付回调通知、卡密充值、
 * 积分购买内容权限、会员升级等功能。
 */
class Payment extends Base
{
    use PublicApi;

    public function __construct()
    {
        parent::__construct();
        // notify 回调不做 API 开关检查，其他方法需要
        $ac = request()->action();
        if ($ac !== 'notify') {
            $this->check_config();
        }
    }

    /**
     * 支付通道是否已在后台填齐必填项（与 extend/pay 内实际使用字段一致）
     *
     * @param string $key 小写，如 alipay、weixin
     * @param array  $cfg pay 下单项配置
     */
    public static function isPayChannelReady($key, array $cfg)
    {
        $key = strtolower((string) $key);
        $t = function ($v) {
            return trim((string) ($v ?? ''));
        };
        switch ($key) {
            case 'alipay':
                return $t($cfg['appid'] ?? '') !== '' && $t($cfg['account'] ?? '') !== '';
            case 'weixin':
                return $t($cfg['appid'] ?? '') !== '' && $t($cfg['mchid'] ?? '') !== '' && $t($cfg['appkey'] ?? '') !== '';
            case 'epay':
                return $t($cfg['api_url'] ?? '') !== '' && $t($cfg['appid'] ?? '') !== '' && $t($cfg['appkey'] ?? '') !== '';
            case 'codepay':
            case 'zhapay':
                return $t($cfg['appid'] ?? '') !== '' && $t($cfg['appkey'] ?? '') !== '';
            default:
                return $t($cfg['appid'] ?? '') !== '';
        }
    }

    /**
     * 与后台支付设置（extend/pay 扩展 Tab + maccms.pay）一致：不硬编码通道、不与扩展重复
     *
     * @return array<int, array{key:string,name:string,enabled:int,paytypes?:array}>
     */
    public static function payMethodsForConfig(array $payConfig)
    {
        $extends = mac_extends_list('pay');
        $split = function ($s) {
            $p = preg_split('/\s*,\s*/', (string) $s, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($p)) {
                return [];
            }

            return array_values(array_unique(array_map('trim', $p)));
        };
        $methods = [];
        foreach ($extends['ext_list'] ?? [] as $classKey => $displayName) {
            $key = strtolower((string) $classKey);
            $cfg = (isset($payConfig[$key]) && is_array($payConfig[$key])) ? $payConfig[$key] : [];
            $enabled = self::isPayChannelReady($key, $cfg) ? 1 : 0;
            $row = [
                'key'     => $key,
                'name'    => is_string($displayName) ? $displayName : $key,
                'enabled' => $enabled,
            ];
            if ($key === 'codepay') {
                $map = [
                    '1' => ['支付宝', 'Alipay'],
                    '2' => ['QQ钱包', 'QQ Wallet'],
                    '3' => ['微信', 'WeChat'],
                ];
                $pts = [];
                foreach ($split($cfg['type'] ?? '') as $v) {
                    $lab = isset($map[$v]) ? $map[$v] : [$v, $v];
                    $pts[] = ['value' => $v, 'label' => $lab[0], 'label_en' => $lab[1]];
                }
                $row['paytypes'] = $pts;
            } elseif ($key === 'zhapay') {
                $map = [
                    '1' => ['微信', 'WeChat'],
                    '2' => ['支付宝', 'Alipay'],
                ];
                $pts = [];
                foreach ($split($cfg['type'] ?? '') as $v) {
                    $lab = isset($map[$v]) ? $map[$v] : [$v, $v];
                    $pts[] = ['value' => $v, 'label' => $lab[0], 'label_en' => $lab[1]];
                }
                $row['paytypes'] = $pts;
            }
            $methods[] = $row;
        }
        usort($methods, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });

        return $methods;
    }

    /**
     * 辅助：检查登录
     */
    private function _checkLogin()
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1401, 'msg' => lang('api/please_login_first')])];
        }
        $uid  = intval($check['info']['user_id']);
        $user = Db::name('User')->where('user_id', $uid)->find();
        if (!$user) {
            return ['ok' => false, 'user_id' => 0, 'user' => null,
                    'response' => json(['code' => 1002, 'msg' => lang('api/user_not_found')])];
        }
        return ['ok' => true, 'user_id' => $uid, 'user' => $user, 'response' => null];
    }

    /**
     * 获取支付配置（可用支付方式列表）
     * GET /api.php/payment/get_config
     *
     * @return JSON  {code:1, msg:'获取成功', info:{min, scale, methods, card_config, is_login, user_points}}
     */
    public function get_config(Request $request)
    {
        $pay_config = config('maccms.pay');

        $loginCheck = model('User')->checkLogin();
        $isLogin    = (intval($loginCheck['code']) === 1) && intval($loginCheck['info']['user_id'] ?? 0) > 0;
        $userPoints = $isLogin ? intval($loginCheck['info']['user_points'] ?? 0) : 0;

        $methods = self::payMethodsForConfig($pay_config);

        // 卡密充值
        $card_config = [
            'enabled'  => !empty($pay_config['card']['url']) ? 1 : 0,
            'card_url' => $pay_config['card']['url'] ?? '',
        ];

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'min'          => floatval($pay_config['min'] ?? 1),
                'scale'        => intval($pay_config['scale'] ?? 1),
                'methods'      => $methods,
                'card_config'  => $card_config,
                'is_login'     => $isLogin ? 1 : 0,
                'user_points'  => $userPoints,
            ],
        ]);
    }
    /**
     * 发起支付（跳转第三方支付）
     * POST /api.php/payment/gopay
     *
     * @param  order_code  string  必填，订单号
     * @param  order_id    int     必填，订单ID
     * @param  payment     string  必填，支付方式（alipay/weixin/codepay/epay/zhapay 等）
     * @return JSON        {code:1, msg:'...', info:{payment, payment_data:{...}}}
     *
     * 说明：
     * - 微信支付返回 code_url（用于生成二维码）
     * - 支付宝等返回 pay_url（构造好的跳转链接）或 html（表单HTML）
     * - 前端根据 payment 类型决定展示方式
     */
    public function gopay(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => lang('api/param_validate', [$validate->getError()])]);
        }

        $order_code = htmlspecialchars(urldecode(trim($param['order_code'] ?? '')));
        $order_id   = intval($param['order_id'] ?? 0);
        $payment    = strtolower(htmlspecialchars(urldecode(trim($param['payment'] ?? ''))));

        if (empty($order_code) || empty($order_id) || empty($payment)) {
            return json(['code' => 1001, 'msg' => lang('api/param_order_fields')]);
        }

        $pay_config = config('maccms.pay');
        if (empty($pay_config[$payment]['appid'])) {
            return json(['code' => 1002, 'msg' => lang('api/payment/payment_disabled')]);
        }

        // 核实订单
        $where = [];
        $where['order_id']   = $order_id;
        $where['order_code'] = $order_code;
        $where['user_id']    = $auth['user_id'];
        $res = model('Order')->infoData($where);
        if ($res['code'] > 1) {
            return json(['code' => 1003, 'msg' => lang('api/payment/order_not_found')]);
        }
        if ($res['info']['order_status'] == 1) {
            return json(['code' => 1004, 'msg' => lang('api/payment/order_paid')]);
        }

        $order_info = $res['info'];

        // 调用支付扩展
        $cp = 'app\\common\\extend\\pay\\' . ucfirst($payment);
        if (!class_exists($cp)) {
            return json(['code' => 1005, 'msg' => lang('api/payment/payment_missing', [$payment])]);
        }

        // API 模式：传入 return_only=true，让支付扩展返回 HTML 而非 echo+die
        $c = new $cp;
        $payment_res = $c->submit($auth['user'], $order_info, $param, true);

        // 根据不同支付方式构建返回
        $payment_data = [];

        if ($payment === 'weixin' && is_array($payment_res) && !empty($payment_res['code_url'])) {
            // 微信支付：返回二维码 URL
            $payment_data = [
                'type'     => 'qrcode',
                'code_url' => $payment_res['code_url'],
                'total_fee' => $payment_res['total_fee'] ?? $order_info['order_price'],
                'out_trade_no' => $payment_res['out_trade_no'] ?? $order_code,
            ];
        } elseif ($payment === 'weixin' && $payment_res === false) {
            return json(['code' => 1006, 'msg' => lang('api/payment/weixin_qr_fail')]);
        } elseif (is_string($payment_res) && !empty($payment_res)) {
            // 支付宝返回 HTML 表单 / 其他支付返回 JS 跳转脚本
            $payment_data = [
                'type' => 'html',
                'html' => $payment_res,
            ];
        } elseif (is_array($payment_res)) {
            // 其他返回数组的支付方式
            $payment_data = [
                'type' => 'data',
                'data' => $payment_res,
            ];
        } else {
            $payment_data = [
                'type' => 'unknown',
                'data' => $payment_res,
            ];
        }

        return json([
            'code' => 1,
            'msg'  => lang('api/payment/pay_started_ok'),
            'info' => [
                'payment'      => $payment,
                'order_code'   => $order_code,
                'order_price'  => $order_info['order_price'],
                'payment_data' => $payment_data,
            ],
        ]);
    }

    /**
     * 支付回调通知（第三方支付服务器调用）
     * GET/POST /api.php/payment/notify?pay_type=alipay
     *
     * 说明：此接口由第三方支付平台异步调用，不需要用户登录。
     * 可将回调地址配置为 /api.php/payment/notify/pay_type/{type}
     * 或 /api.php/payment/notify?pay_type={type}
     */
    public function notify()
    {
        $param = input();
        $pay_type = $param['pay_type'] ?? '';

        if (empty($pay_type)) {
            echo 'pay_type is required';
            exit;
        }

        $pay_config = config('maccms.pay');
        if (empty($pay_config[$pay_type]['appid'])) {
            echo lang('index/payment_status');
            exit;
        }

        $cp = 'app\\common\\extend\\pay\\' . ucfirst($pay_type);
        if (class_exists($cp)) {
            $c = new $cp;
            $c->notify();
        } else {
            echo lang('index/payment_not');
            exit;
        }
    }

    /**
     * 卡密充值
     * POST /api.php/payment/use_card
     *
     * @param  card_no   string  必填，充值卡卡号
     * @param  card_pwd  string  必填，充值卡密码
     * @return JSON      {code:1, msg:'充值成功，增加积分【xxx】'}
     */
    public function use_card(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => lang('api/param_validate', [$validate->getError()])]);
        }

        $card_no  = htmlspecialchars(urldecode(trim($param['card_no'] ?? '')));
        $card_pwd = htmlspecialchars(urldecode(trim($param['card_pwd'] ?? '')));

        $res = model('Card')->useData($card_no, $card_pwd, $auth['user']);
        return json($res);
    }

    /**
     * 积分购买内容权限（观看/下载/阅读付费内容）
     * POST /api.php/payment/buy_popedom
     *
     * @param  mid   int  必填，模型（1=视频, 2=文章）
     * @param  id    int  必填，资源ID（vod_id 或 art_id）
     * @param  type  int  必填，操作类型（1=文章阅读, 4=播放, 5=下载）
     * @param  sid   int  可选，播放源编号
     * @param  nid   int  可选，集编号
     * @return JSON  {code:1, msg:'购买成功'}
     */
    public function buy_popedom(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => lang('api/param_validate', [$validate->getError()])]);
        }

        $data  = [];
        $data['ulog_mid'] = intval($param['mid'] ?? 1) <= 0 ? 1 : intval($param['mid']);
        $data['ulog_rid'] = intval($param['id'] ?? 0);
        $data['ulog_sid'] = intval($param['sid'] ?? 0);
        $data['ulog_nid'] = intval($param['nid'] ?? 0);

        $data['ulog_type'] = intval($param['type']);
        $data['user_id']   = $auth['user_id'];

        // 查询资源信息以获取所需积分
        if ($param['type'] == '1') {
            // 文章
            $where = ['art_id' => $data['ulog_rid']];
            $res = model('Art')->infoData($where);
            if ($res['code'] > 1) {
                return json($res);
            }
            $col = 'art_points_detail';
            if ($GLOBALS['config']['user']['art_points_type'] == '1') {
                $col = 'art_points';
                $data['ulog_sid'] = 0;
                $data['ulog_nid'] = 0;
            }
        } else {
            // 视频
            $where = ['vod_id' => $data['ulog_rid']];
            $res = model('Vod')->infoData($where);
            if ($res['code'] > 1) {
                return json($res);
            }
            $col = 'vod_points_' . ($param['type'] == '4' ? 'play' : 'down');
            if ($GLOBALS['config']['user']['vod_points_type'] == '1') {
                $col = 'vod_points';
                $data['ulog_sid'] = 0;
                $data['ulog_nid'] = 0;
            }
        }
        $data['ulog_points'] = intval($res['info'][$col]);

        // 检查是否已购买
        $exists = model('Ulog')->infoData($data);
        if ($exists['code'] == 1) {
            return json(['code' => 1, 'msg' => lang('api/payment/already_owned')]);
        }

        // 检查积分是否足够（先做快速检查，事务内再做原子扣除）
        if ($data['ulog_points'] > $auth['user']['user_points']) {
            return json([
                'code' => 1005,
                'msg'  => lang('api/payment/points_need_remain', [(string) $data['ulog_points'], (string) $auth['user']['user_points']]),
                'info' => [
                    'need_points'    => $data['ulog_points'],
                    'current_points' => intval($auth['user']['user_points']),
                ],
            ]);
        }

        // 使用事务 + 条件更新防止并发刷积分
        Db::startTrans();
        try {
            // 带条件的原子扣除：只有积分足够时才扣除
            $affected = Db::name('user')
                ->where('user_id', $auth['user_id'])
                ->where('user_points', '>=', $data['ulog_points'])
                ->setDec('user_points', $data['ulog_points']);

            if ($affected === 0 || $affected === false) {
                Db::rollback();
                return json(['code' => 1005, 'msg' => lang('api/payment/points_insufficient')]);
            }

            // 积分日志
            $plog = [];
            $plog['user_id']     = $auth['user_id'];
            $plog['plog_type']   = 8;
            $plog['plog_points'] = $data['ulog_points'];
            model('Plog')->saveData($plog);

            // 分销佣金
            model('User')->reward($data['ulog_points']);

            // 写入购买记录
            $save_res = model('Ulog')->saveData($data);

            Db::commit();
            return json($save_res);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1006, 'msg' => lang('api/payment/operation_retry')]);
        }
    }

    /**
     * 会员升级（用积分升级用户组/VIP）
     * POST /api.php/payment/upgrade
     *
     * @param  group_id  int     必填，目标用户组ID（>=3）
     * @param  long      string  必填，时长周期（day|week|month|year）
     * @return JSON      {code:1, msg:'升级成功'}
     *
     * 说明：
     * - 积分 = 对应用户组的 group_points_{long} 字段值
     * - 用户必须拥有足够积分
     * - 升级后 user_end_time 自动延长
     */
    public function upgrade(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => lang('api/param_validate', [$validate->getError()])]);
        }

        $res = model('User')->upgrade($param);
        return json($res);
    }

    /**
     * 获取可升级的用户组列表（含积分价格）
     * GET /api.php/payment/get_groups
     *
     * @return JSON  {code:1, msg:'获取成功', info:[{group_id, group_name, group_points_day, ...}]}
     */
    public function get_groups(Request $request)
    {
        $group_list = model('Group')->getCache();

        $result = [];
        foreach ($group_list as $g) {
            // 只返回自定义付费组（group_id >= 3）
            if ($g['group_id'] < 3) continue;
            if ($g['group_status'] == 0) continue;

            $result[] = [
                'group_id'           => $g['group_id'],
                'group_name'         => $g['group_name'],
                'group_status'       => $g['group_status'],
                'group_points_day'   => $g['group_points_day'] ?? 0,
                'group_points_week'  => $g['group_points_week'] ?? 0,
                'group_points_month' => $g['group_points_month'] ?? 0,
                'group_points_year'  => $g['group_points_year'] ?? 0,
            ];
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $result,
        ]);
    }

    /**
     * 获取用户充值卡使用记录
     * GET /api.php/payment/get_cards?page=1&limit=20
     *
     * @param  page   int  可选，页码，默认1
     * @param  limit  int  可选，每页条数，默认20，最大100
     * @return JSON   {code:1, msg:'获取成功', info:{page, pagecount, limit, total, list:[...]}}
     */
    public function get_cards(Request $request)
    {
        $auth = $this->_checkLogin();
        if (!$auth['ok']) return $auth['response'];

        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json(['code' => 1001, 'msg' => lang('api/param_validate', [$validate->getError()])]);
        }

        $page  = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));

        $where = [];
        $where['user_id']         = $auth['user_id'];
        $where['card_use_status'] = 1;

        $order = 'card_id desc';
        $res   = model('Card')->listData($where, $order, $page, $limit);

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $res,
        ]);
    }
}
