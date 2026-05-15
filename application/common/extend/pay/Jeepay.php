<?php
namespace app\common\extend\pay;

/**
 * JeePay 支付插件
 * 基于 Jeepay 支付网关
 */
class Jeepay {

    public $name = 'JeePay';
    public $ver = '1.0';

    /**
     * 发起支付（统一下单）
     */
    public function submit($user, $order, $param, $return_only = false)
    {
        $jeepay_config = $GLOBALS['config']['pay']['jeepay'];
        $api_url = rtrim(trim($jeepay_config['api_url']), '/');
        $mch_no  = trim($jeepay_config['mch_no']);
        $app_id  = trim($jeepay_config['appid']);
        $app_secret = trim($jeepay_config['appkey']);

        // 确定支付方式 wayCode
        $way_code = 'ALI_WAP'; // 默认
        $way_codes_str = trim($jeepay_config['way_codes'] ?? '');
        $available_ways = array_filter(array_map('trim', explode(',', $way_codes_str)));

        if (!empty($param['paytype'])) {
            // 前端传入的 paytype 对应 wayCode
            $paytype = strtoupper(trim($param['paytype']));
            if (in_array($paytype, $available_ways)) {
                $way_code = $paytype;
            }
        } elseif (!empty($available_ways)) {
            $way_code = $available_ways[0];
        }

        // 金额转换：元 -> 分
        $amount = intval(round(floatval($order['order_price']) * 100));
        if ($amount < 1) {
            $amount = 1;
        }

        // 构建回调地址（使用后台配置的站点地址，禁止使用 HTTP_HOST 防伪造）
        $site_url = trim($GLOBALS['config']['site']['site_url'] ?? '');
        if (empty($site_url)) {
            if ($return_only) {
                return '<script>alert("site_url not configured");history.back();</script>';
            }
            echo 'site_url not configured';
            exit;
        }
        $base_url = $GLOBALS['http_type'] . rtrim($site_url, '/');
        $notify_url = $base_url . '/index.php/payment/notify/pay_type/jeepay';
        $return_url = $base_url . '/index.php/user/bindpay';

        // 构建请求参数
        $data = [
            'version'    => '1.0',
            'signType'   => 'MD5',
            'reqTime'    => (string) round(microtime(true) * 1000),
            'mchNo'      => $mch_no,
            'appId'      => $app_id,
            'mchOrderNo' => $order['order_code'],
            'wayCode'    => $way_code,
            'amount'     => $amount,
            'currency'   => 'CNY',
            'clientIp'   => mac_get_client_ip(),
            'subject'    => sprintf(lang('pay/jeepay/recharge'), $user['user_id']),
            'body'       => lang('pay/jeepay/recharge_body'),
            'notifyUrl'  => $notify_url,
            'returnUrl'  => $return_url,
            'channelExtra' => json_encode(['payDataType' => 'payurl']),
        ];

        // 生成签名
        $data['sign'] = $this->makeSign($data, $app_secret);

        // 发送请求
        $url = $api_url . '/api/pay/unifiedOrder';
        $response = $this->httpPostJson($url, $data);

        if ($response === false) {
            if ($return_only) {
                return '<script>alert("' . addslashes(lang('pay/jeepay/request_fail')) . '");history.back();</script>';
            }
            echo lang('pay/jeepay/request_fail');
            exit;
        }

        $result = json_decode($response, true);

        if (!$result || $result['code'] != 0) {
            $err_msg = $result['msg'] ?? 'unknown error';
            if ($return_only) {
                return '<script>alert("' . addslashes(sprintf(lang('pay/jeepay/pay_fail'), $err_msg)) . '");history.back();</script>';
            }
            echo sprintf(lang('pay/jeepay/pay_fail'), htmlspecialchars($err_msg));
            exit;
        }

        // 获取支付链接
        $pay_data = $result['data'] ?? [];
        $pay_url = '';

        if (!empty($pay_data['payData'])) {
            $pay_url = $pay_data['payData'];
        }

        if (empty($pay_url)) {
            if ($return_only) {
                return '<script>alert("' . addslashes(lang('pay/jeepay/no_pay_url')) . '");history.back();</script>';
            }
            echo lang('pay/jeepay/no_pay_url');
            exit;
        }

        if ($return_only) {
            return '<script>location.href="' . $pay_url . '";</script>';
        }
        mac_redirect($pay_url);
    }

    /**
     * 支付回调通知
     */
    public function notify()
    {
        // 只接受 POST 请求，拒绝 GET/Cookie 参数注入
        $param = $_POST;
        if (empty($param)) {
            echo 'invalid request';
            exit;
        }

        $jeepay_config = $GLOBALS['config']['pay']['jeepay'];
        $app_secret = trim($jeepay_config['appkey']);

        // 取出签名
        $sign = $param['sign'] ?? '';
        if (empty($sign)) {
            echo 'sign is empty';
            exit;
        }

        // 验证签名
        $calc_sign = $this->makeSign($param, $app_secret);
        if ($calc_sign !== $sign) {
            echo 'sign verify failed';
            exit;
        }

        // 检查订单状态 state=2 为支付成功
        $state = intval($param['state'] ?? 0);
        $mch_order_no = $param['mchOrderNo'] ?? '';

        if (empty($mch_order_no)) {
            echo 'mchOrderNo is empty';
            exit;
        }

        // 只处理 state=2（支付成功），其他状态返回 fail 让网关继续重试
        if ($state != 2) {
            echo 'fail';
            exit;
        }

        // 金额复核：防止改价攻击
        $callback_amount = intval($param['amount'] ?? 0);
        $order = \think\Db::name('order')->where('order_code', $mch_order_no)->find();
        if (empty($order)) {
            echo 'order not found';
            exit;
        }
        $expect_amount = intval(round(floatval($order['order_price']) * 100));
        if ($callback_amount !== $expect_amount) {
            echo 'amount mismatch';
            exit;
        }

        // 币种校验
        $currency = strtoupper(trim($param['currency'] ?? ''));
        if ($currency !== '' && $currency !== 'CNY') {
            echo 'currency mismatch';
            exit;
        }

        // 支付成功，更新订单
        $res = model('Order')->notify($mch_order_no, 'jeepay');
        if ($res['code'] > 1) {
            echo 'fail';
            exit;
        }
        echo 'SUCCESS';
    }

    /**
     * JeePay 签名算法
     * 1. 删除 sign 字段
     * 2. 删除值为 null 或空字符串的字段
     * 3. 按参数名不区分大小写升序排序
     * 4. 拼接 key=value& 形式
     * 5. 末尾追加 key=appSecret
     * 6. MD5 转大写
     */
    private function makeSign($params, $app_secret)
    {
        unset($params['sign']);

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        uksort($filtered, 'strcasecmp');

        $pairs = [];
        foreach ($filtered as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        $signStr = implode('&', $pairs) . '&key=' . $app_secret;
        return strtoupper(md5($signStr));
    }

    /**
     * HTTP POST JSON 请求
     */
    private function httpPostJson($url, $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // TLS 证书校验（默认开启，后台可关闭用于自签名证书场景）
        $verify_ssl = isset($GLOBALS['config']['pay']['jeepay']['verify_ssl'])
            ? intval($GLOBALS['config']['pay']['jeepay']['verify_ssl'])
            : 1;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) $verify_ssl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify_ssl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200 || $response === false) {
            return false;
        }

        return $response;
    }
}
