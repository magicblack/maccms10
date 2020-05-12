<?php
namespace app\common\extend\pay;

class Alipay {

    public $name = '支付宝';
    public $ver = '1.0';

    public function submit($user,$order,$param)
    {
        $data = array();
        $data['service'] = 'create_direct_pay_by_user';//使用即时到帐交易接口
        $data['payment_type'] = '1';//默认值为：1（商品购买）
        $data['quantity'] = '1';//数量
        $data['_input_charset'] = 'utf-8';
        $data['partner'] = trim($GLOBALS['config']['pay']['alipay']['appid']);
        $data['seller_email'] = trim($GLOBALS['config']['pay']['alipay']['account']);
        $data['out_trade_no'] = $order['order_code'];
        $data['notify_url'] = $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/payment/notify/pay_type/alipay';
        $data['return_url'] = $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/payment/notify/pay_type/alipay';
        $data['subject'] = '积分充值（UID：'.$user['user_id'].'）';
        $data['total_fee'] = sprintf("%.2f",$order['order_price']);

        //待请求参数数组
        $para = $this->buildRequestPara($data);
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='https://mapi.alipay.com/gateway.do?_input_charset=utf-8' method='POST'>";
        while (list ($key, $val) = each ($para)) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='正在提交'></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
        echo $sHtml;
        die;
    }

    public function notify()
    {
        $param = input();
        $GLOBALS['config']['pay'] = config('maccms.pay');
        unset($param['/payment/notify/pay_type/alipay']);
        unset($param['pay_type']);

        $isSign = $this->getSignVeryfy($param, $param["sign"]);
        //验证成功
        if($isSign) {
            if ($param['trade_status'] == 'TRADE_SUCCESS') {
                $res = model('Order')->notify($param['out_trade_no'],'alipay');
                if($res['code']>1){
                    echo "fail2";
                }
                else{
                    echo "success2";
                }
            }
            else {
                echo "success";
            }
        }else{
            echo "fail";
        }
    }

    public function buildRequestPara($para_temp) {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($para_temp);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

        //生成签名结果
        $mysign = $this->buildRequestMysign($para_sort);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = 'MD5';

        return $para_sort;
    }

    public function paraFilter($para) {
        $para_filter = array();
        while (list ($key, $val) = each ($para)) {
            if($key == "sign" || $key == "sign_type" || $val == "")continue;
            else	$para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }

    public function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    public function buildRequestMysign($para_sort) {
        //把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);

        $mysign = $this->md5Sign($prestr, $GLOBALS['config']['pay']['alipay']['appkey']);

        return $mysign;
    }

    public function createLinkstring($para) {
        $arg  = "";
        while (list ($key, $val) = each ($para)) {
            $arg.=$key."=".$val."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

        return $arg;
    }

    public function md5Sign($prestr, $key) {
        $prestr = $prestr . $key;
        return md5($prestr);
    }

    public function getSignVeryfy($para_temp, $sign) {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = $this->paraFilter($para_temp);

        //对待签名参数数组排序
        $para_sort = $this->argSort($para_filter);

        //把数组所有元素，按照"参数=参数值"的模式用"&"字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);

        $isSgin = false;
        $isSgin = $this->md5Verify($prestr, $sign, $GLOBALS['config']['pay']['alipay']['appkey']);

        return $isSgin;
    }

    public function md5Verify($prestr, $sign, $key) {
        $prestr = $prestr . $key;
        $mysgin = md5($prestr);
        if($mysgin == $sign) {
            return true;
        }
        else {
            return false;
        }
    }
}
