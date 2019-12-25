<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use login\ThinkOauth;
use app\index\event\LoginEvent;
use app\common\util\Qrcode;

class User extends Base
{
    public function __construct()
    {
        parent::__construct();

        define('THIRD_LOGIN_CALLBACK',  $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/user/logincallback/type/');

        //判断用户登录状态
        $ac = request()->action();
        if (in_array($ac, ['login', 'logout', 'ajax_login', 'reg', 'findpass', 'findpass_msg', 'findpass_reset', 'reg_msg', 'oauth', 'logincallback','visit'])) {

        } else {
            if ($GLOBALS['user']['user_id'] < 1) {
                model('User')->logout();
                return $this->error('未登录', url('user/login'));
            }
            /*
            $res = model('User')->checkLogin();
            if($res['code']>1){
                model('User')->logout();
                return $this->error($res['msg'], url('user/login'));
            }
            */
            $this->assign('obj', $GLOBALS['user']);
        }
    }

    public function ajax_login()
    {
        return $this->fetch('user/ajax_login');
    }

    public function ajax_info()
    {
        return $this->fetch('user/ajax_info');
    }

    public function ajax_ulog()
    {
        $param = input();
        if ($param['ac'] == 'set') {
            $data = [];
            $data['ulog_mid'] = intval($param['mid']);
            $data['ulog_rid'] = intval($param['id']);
            $data['ulog_type'] = intval($param['type']);
            $data['ulog_sid'] = intval($param['sid']);
            $data['ulog_nid'] = intval($param['nid']);
            $data['user_id'] = $GLOBALS['user']['user_id'];

            if ($data['ulog_mid'] == 1 && $data['ulog_type'] > 3) {
                $where2 = [];
                $where2['vod_id'] = $data['ulog_rid'];
                $res = model('Vod')->infoData($where2);
                if ($res['code'] > 1) {
                    return $res;
                }
                $flag = $data['ulog_type'] == 4 ? 'play' : 'down';
                $data['ulog_points'] = $res['info']['vod_points_' . $flag];
            }
            $data['ulog_points'] = intval($data['ulog_points']);
            $res = model('Ulog')->infoData($data);
            if ($res['code'] == 1) {
                $r = model('Ulog')->where($data)->update(['ulog_time'=>time()]);
                return json($res);
            }
            if ($data['ulog_points'] == 0) {
                $res = model('Ulog')->saveData($data);
            } else {
                $res = ['code' => 2001, 'msg' => '收费收据需单独记录'];
            }
        } else {
            $where = [];
            $where['user_id'] = $GLOBALS['user']['user_id'];
            $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
            $param['limit'] = intval($param['limit']) < 1 ? 10 : intval($param['limit']);
            if(intval($param['mid'])>0){
                $where['ulog_mid'] = ['eq',intval($param['mid'])];
            }
            if(intval($param['id'])>0){
                $where['ulog_rid'] = ['eq',intval($param['id'])];
            }
            if(intval($param['type'])>0){
                $where['ulog_type'] = ['eq',intval($param['type'])];
            }
            $order = 'ulog_time desc';
            $res = model('Ulog')->listData($where, $order, $param['page'], $param['limit']);
        }
        return json($res);
    }

    public function ajax_buy_popedom()
    {
        $param = input();
        $data = [];
        $data['ulog_mid'] = intval($param['mid']) <=0 ? 1:$param['mid'];
        $data['ulog_rid'] = intval($param['id']);
        $data['ulog_sid'] = intval($param['sid']);
        $data['ulog_nid'] = intval($param['nid']);

        if (!in_array($param['mid'], ['1','2']) || !in_array($param['type'], ['1','4','5']) || empty($data['ulog_rid']) ) {
            return json(['code' => 2001, 'msg' => '参数错误']);
        }
        $data['ulog_type'] = $param['type'];
        $data['user_id'] = $GLOBALS['user']['user_id'];

        $where = [];
        if($param['type']=='1'){
            $where['art_id'] = $data['ulog_rid'];
            $res = model('Art')->infoData($where);
            if ($res['code'] > 1) {
                return json([$res]);
            }
            $col = 'art_points_detail';
            if($GLOBALS['config']['user']['art_points_type']=='1'){
                $col='art_points';
                $data['ulog_sid']=0;
                $data['ulog_nid']=0;
            }
        }
        else{
            $where['vod_id'] = $data['ulog_rid'];
            $res = model('Vod')->infoData($where);
            if ($res['code'] > 1) {
                return json([$res]);
            }
            $col = 'vod_points_' . ($param['type'] == '4' ? 'play' : 'down');
            if($GLOBALS['config']['user']['vod_points_type']=='1'){
                $col='vod_points';
                $data['ulog_sid']=0;
                $data['ulog_nid']=0;
            }
        }
        $data['ulog_points'] = intval($res['info'][$col]);

        $res = model('Ulog')->infoData($data);
        if ($res['code'] == 1) {
            return json(['code' => 1, 'msg' => '您已经购买过此条数据，无需再次支付，请刷新页面重试']);
        }

        if ($data['ulog_points'] > $GLOBALS['user']['user_points']) {
            return json(['code' => 2002, 'msg' => '对不起,查看此页面数据需要[' . $data['ulog_points'] . ']积分，您还剩下[' . $GLOBALS['user']['user_points'] . ']积分，请先充值！']);
        } else {
            $where = [];
            $where['user_id'] = $GLOBALS['user']['user_id'];
            $res = model('User')->where($where)->setDec('user_points',$data['ulog_points']);
            if ($res === false) {
                return json(['code' => 2003, 'msg' => '对不起,更新用户积分信息失败，请刷新重试！']);
            }

            //积分日志
            $data2 = [];
            $data2['user_id'] = $GLOBALS['user']['user_id'];
            $data2['plog_type'] = 8;
            $data2['plog_points'] = $data['ulog_points'];
            model('Plog')->saveData($data2);

            //分销日志
            model('User')->reward($data['ulog_points']);

            $res = model('Ulog')->saveData($data);
            return json($res);
        }
    }

    public function index()
    {
        return $this->fetch('user/index');
    }

    public function login()
    {
        if (Request()->isPost()) {
            $param = input();
            $res = model('User')->login($param);
            return json($res);
        }
        if (!empty(cookie('user_id') && !empty(cookie('user_name')))) {
            return redirect('user/index');
        }
        return $this->fetch('user/login');
    }

    public function logout()
    {
        $res = model('User')->logout();
        if (request()->isAjax()) {
            return json($res);
        } else {
            return redirect('user/login');
        }
    }

    public function oauth($type = '')
    {
        empty($type) && $this->error('参数错误');
        //加载ThinkOauth类并实例化一个对象
        $sns = ThinkOauth::getInstance($type);
        //跳转到授权页面
        $this->redirect($sns->getRequestCodeURL());
    }

    //授权回调地址
    public function logincallback($type = '', $code = '')
    {
        if (empty($type) || empty($code)) {
            return $this->error('参数错误');
        }
        //加载ThinkOauth类并实例化一个对象
        $sns = ThinkOauth::getInstance($type);
        $extend = null;

        //请妥善保管这里获取到的Token信息，方便以后API调用
        $token = $sns->getAccessToken($code, $extend);
        //获取当前登录用户信息
        if (is_array($token)) {
            $loginEvent = new LoginEvent();
            $res = $loginEvent->$type($token);
            if ($res['code'] == 1) {
                $openid = $res['info']['openid'];
                $col = 'user_openid_' . $type;
                //如果已登录,是否需要重新绑定
                $check = model('User')->checkLogin();
                if ($check['code'] == 1) {

                    if ($check['info'][$col] == $openid) {
                        //无需再次绑定
                        return json(['code' => 1001, 'msg' => '已经绑定该账号']);
                    } else {
                        //解除原有绑定
                        $where = [];
                        $where[$col] = $openid;
                        $update = [];
                        $update[$col] = '';
                        model('User')->where($where)->update($update);
                        //新绑定
                        $where = [];
                        $where['user_id'] = $GLOBALS['user']['user_id'];
                        $update = [];
                        $update[$col] = $openid;
                        model('User')->where($where)->update($update);
                        return json(['code' => 1, 'msg' => '绑定成功']);
                    }
                }

                $where = [];
                $where[$col] = $openid;
                $res2 = model('User')->infoData($where);
                //未绑定的需要先创建用户并绑定
                if ($res2['code'] > 1) {
                    $data = [];
                    $data['user_name'] = substr($openid, 0, 10);
                    $data['user_nick_name'] = htmlspecialchars(urldecode(trim($res['info']['name'])));
                    $pwd = time();
                    $data['user_pwd'] = $pwd;
                    $data['user_pwd2'] = $pwd;
                    $data[$col] = $openid;
                    $reg = model('User')->register($data);
                    if ($reg['code'] > 1) {
                        //注册失败
                        return $this->error('同步信息注册失败，请联系管理员');
                    }
                }
                //直接登录。。。
                $login = model('User')->login(['col' => $col, 'openid' => $openid]);
                if ($login['code'] > 1) {
                    return $this->error($login['msg']);
                }
                $this->redirect('user/index');
            } else {
                return $this->error($res['msg']);
            }
        } else {
            return $this->error('获取第三方用户信息失败，请重试');
        }
    }

    public function bindmsg()
    {
        $param = input();
        $res = model('User')->bindmsg($param);
        return json($res);
    }

    public function bind()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->bind($param);
            return json($res);
        }

        if (empty($param['ac'])) {
            $param['ac'] = 'email';
        }
        $this->assign('ac', $param['ac']);
        $this->assign('param',$param);
        return $this->fetch('user/bind');
    }

    public function unbind()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->unbind($param);
            return json($res);
        }
        $this->assign('param',$param);
        return $this->fetch('user/unbind');
    }

    public function info()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->info($param);
            if ($res['code'] == 1) {
                $this->success($res['msg']);
                exit;
            }
            $this->error($res['msg']);
            exit;
        }
        $this->assign('param',$param);
        return $this->fetch('user/info');
    }

    public function regcheck()
    {
        $param = input();
        $t = htmlspecialchars(urldecode(trim($param['t'])));
        $str = htmlspecialchars(urldecode(trim($param['str'])));
        $res = model('User')->regcheck($t, $str);
        if ($res['code'] > 1) {
            return $str;
        }
        return json($res);
    }

    public function reg()
    {
        $param = input();
        if (Request()->isPost()) {
            if (!empty(cookie('uid'))) {
                $param['uid'] = intval(cookie('uid'));
            }
            $res = model('User')->register($param);
            if ($res['code'] > 1) {
                return json($res);
            }

            $GLOBALS['config']['user']['login_verify'] = '0';
            $res = model('User')->login($param);
            $res['msg'] = '注册成功，' . $res['msg'];
            return json($res);
        }
        if (!empty($param['uid'])) {
            cookie('uid', $param['uid']);
        }

        $user_config = $GLOBALS['config']['user'];
        $this->assign('user_config', $user_config);
        $this->assign('param', $param);
        return $this->fetch('user/reg');
    }

    public function reg_msg()
    {
        $param = input();
        $res = model('User')->reg_msg($param);
        return json($res);
    }


    public function portrait()
    {
        if ($GLOBALS['config']['user']['portrait_status'] == 0) {
            return json(['code' => 0, 'msg' => '未开启自定义头像功能！']);
        }

        $file = request()->file('file');
        if (empty($file)) {
            return json(['code' => 0, 'msg' => '未找到上传的文件(原因：表单名可能错误，默认表单名“file”)！']);
        }
        if ($file->getMime() == 'text/x-php') {
            return json(['code' => 0, 'msg' => '禁止上传php,html文件！']);
        }

        $upload_image_ext = 'jpg,png,gif';
        if ($file->checkExt($upload_image_ext)) {
            $type = 'image';
        } else {
            return json(['code' => 0, 'msg' => '非系统允许的上传格式！']);
        }

        $uniq = $GLOBALS['user']['user_id'] % 10;
        // 上传附件路径
        $_upload_path = ROOT_PATH . 'upload' . '/user/' . $uniq . '/';
        // 附件访问路径
        $_save_path = 'upload' . '/user/' . $uniq . '/';
        $_save_name = $GLOBALS['user']['user_id'] . '.jpg';

        if (!file_exists($_save_path)) {
            mac_mkdirss($_save_path);
        }

        $upfile = $file->move($_upload_path, $_save_name);
        if (!is_file($_upload_path . $_save_name)) {
            return json(['code' => 0, 'msg' => '文件上传失败！']);
        }
        $file = $_save_path . str_replace('\\', '/', $_save_name);
        $config = [
            'thumb_type' => 6,
            'thumb_size' => $GLOBALS['config']['user']['portrait_size'],
        ];

        $new_thumb = $GLOBALS['user']['user_id'] . '.jpg';
        $new_file = $_save_path . $new_thumb;
        try {

            $image = \think\Image::open('./' . $file);
            $t_size = explode('x', strtolower($GLOBALS['config']['user']['portrait_size']));
            if (!isset($t_size[1])) {
                $t_size[1] = $t_size[0];
            }
            $res = $image->thumb($t_size[0], $t_size[1], 6)->save('./' . $new_file);

            $update = [];
            $update['user_portrait'] = $new_file;
            $where = [];
            $where['user_id'] = $GLOBALS['user']['user_id'];
            $res = model('User')->where($where)->update($update);
            if ($res === false) {
                return json(['code' => 0, 'msg' => '更新会员头像信息失败！']);
            }
            return json(['code' => 1, 'msg' => 'ok', 'file' =>  '' . MAC_PATH . $new_file . '?' . mt_rand(1, 9999)]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '生成缩放头像图片文件失败！']);
        }
    }

    public function findpass()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->findpass($param);
            return json($res);
        }
        $this->assign('param',$param);
        return $this->fetch('user/findpass');
    }

    public function findpass_msg()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->findpass_msg($param);
            return json($res);
        }
        $param['ac_text'] = $param['ac'] == 'phone' ? '手机' : '邮箱';
        $this->assign('param', $param);
        return $this->fetch('user/findpass_msg');
    }

    public function findpass_reset()
    {
        if (Request()->isPost()) {
            $param = input();
            $res = model('User')->findpass_reset($param);
            return json($res);
        }
    }

    public function buy()
    {
        $param = input();
        if (Request()->isPost()) {
            $flag = input('param.flag');
            if ($flag == 'card') {
                $card_no = htmlspecialchars(urldecode(trim($param['card_no'])));
                $card_pwd = htmlspecialchars(urldecode(trim($param['card_pwd'])));

                $res = model('Card')->useData($card_no, $card_pwd, $GLOBALS['user']);
                return json($res);
            } else {
                $price = input('param.price');
                if (empty($price)) {
                    return json(['code' => 1001, 'msg' => '参数错误']);
                }

                if ($price < $GLOBALS['config']['pay']['min']) {
                    return json(['code' => 1002, 'msg' => '最小充值金额不能低于' . $GLOBALS['config']['pay']['min'] . '元']);
                }

                $data = [];
                $data['user_id'] = $GLOBALS['user']['user_id'];
                $data['order_code'] = 'PAY' . mac_get_uniqid_code();
                $data['order_price'] = $price;
                $data['order_time'] = time();
                $data['order_points'] = intval($GLOBALS['config']['pay']['scale'] * $price);
                $res = model('Order')->saveData($data);
                $res['data'] = $data;
                return json($res);
            }
        }
        $this->assign('param',$param);
        $this->assign('config', $GLOBALS['config']['pay']);
        return $this->fetch('user/buy');
    }

    public function pay()
    {
        $param = input();
        $order_code = htmlspecialchars(urldecode(trim($param['order_code'])));
        $where = [];
        $where['order_code'] = $order_code;
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $res = model('Order')->infoData($where);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        $this->assign('param',$param);
        $this->assign('config', $GLOBALS['config']['pay']);
        $this->assign('info', $res['info']);

        $ext_list=[];

        foreach($GLOBALS['config']['pay'] as $k=>$v){
            if(!in_array($k,['min','scale','card'])){
                if(!empty(current($v))){
                    $cp = 'app\\common\\extend\\pay\\' . ucfirst($k);
                    if (class_exists($cp)) {
                        $c = new $cp;
                        $ext_list[$k] = $c->name;
                    }
                }
            }
        }
        $this->assign('ext_list',$ext_list);

        return $this->fetch('user/pay');
    }

    public function gopay()
    {
        $param = input();

        $order_code = htmlspecialchars(urldecode(trim($param['order_code'])));
        $order_id = intval((trim($param['order_id'])));
        $payment = strtolower(htmlspecialchars(urldecode(trim($param['payment']))));

        if (empty($order_code) && empty($order_id) && empty($payment)) {
            return $this->error('参数错误');
        }

        if ($GLOBALS['config']['pay'][$payment]['appid'] == '') {
            return $this->error('该支付选项未开启');
        }

        //核实订单
        $where['order_id'] = $order_id;
        $where['order_code'] = $order_code;
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $res = model('Order')->infoData($where);
        if ($res['code'] > 1) {
            return $this->error('获取单据失败');
        }
        if ($res['info']['order_status'] == 1) {
            return $this->error('该单据已支付完成');
        }

        $this->assign('order', $res['info']);
        //跳转到相应页面
        $this->assign('param',$param);

        $cp = 'app\\common\\extend\\pay\\' . ucfirst($payment);
        if (class_exists($cp)) {
            $c = new $cp;
            $payment_res = $c->submit($this->user, $res['info'], $param);
        }
        //$payment_res = model('Pay' . $payment)->submit($this->user, $res['info'], $param);
        if ($payment == 'weixin') {
            $this->assign('payment', $payment_res);
            return $this->fetch('user/payment_weixin');
        }
    }

    public function qrcode()
    {
        ob_end_clean();
        header('Content-Type:image/png;');
        $param = input();
        $data = $param['data'];
        if(substr($data, 0, 6) == "weixin") {
            QRcode::png($data,false,QR_ECLEVEL_L,10);
        }
        else{
            return $this->error('参数错误');
        }
    }

    public function upgrade()
    {
        $param = input();
        if (Request()->isPost()) {
            $res = model('User')->upgrade($param);
            return json($res);
        }

        $group_list = model('Group')->getCache();
        $this->assign('group_list', $group_list);
        $this->assign('param',$param);
        return $this->fetch('user/upgrade');
    }

    public function popedom()
    {
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $n = 1;
        $ids = [1 => '列表页', 2 => '内容页', 3 => '播放页', 4 => '下载页', '5' => '试看'];
        foreach ($type_tree as $k1 => $v1) {
            unset($type_tree[$k1]['type_extend']);
            foreach ($ids as $a => $b) {
                $n++;
                if ($v1['type_mid'] == 2 && $a > 2) {
                    break;
                }
                $type_tree[$k1]['popedom'][$b] = model('User')->popedom($v1['type_id'], $a, $GLOBALS['user']['group_id']);
            }
            foreach ($v1['child'] as $k2 => $v2) {
                unset($type_tree[$k1]['child'][$k2]['type_extend']);
                foreach ($ids as $a => $b) {
                    $n++;
                    if ($v2['type_mid'] == 2 && $a > 2) {
                        break;
                    }
                    $type_tree[$k1]['child'][$k2]['popedom'][$b] = model('User')->popedom($v2['type_id'], $a, $GLOBALS['user']['group_id']);
                }
            }
        }

        $this->assign('type_tree', $type_tree);

        return $this->fetch('user/popedom');
    }

    public function plays()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $where['ulog_mid'] = 1;
        $where['ulog_type'] = 4;
        $order = 'ulog_time desc';
        $res = model('Ulog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '我的点播');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/plays', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/plays');
    }

    public function downs()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $where['ulog_mid'] = 1;
        $where['ulog_type'] = 5;
        $order = 'ulog_time desc';
        $res = model('Ulog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '我的下载');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/downs', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/downs');
    }

    public function favs()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        if(in_array($param['mid'],['1','2','3','8'])){
            $where['ulog_mid'] = $param['mid'];
        }
        $where['ulog_type'] = 2;
        $order = 'ulog_time desc';
        $res = model('Ulog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '我的收藏');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/favs', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/favs');
    }

    public function ulog()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        if(in_array($param['mid'],['1','2','3','8'])){
            $where['ulog_mid'] = $param['mid'];
        }
        if(in_array($param['type'],['1','2','3','4','5'])){
            $where['ulog_type'] = $param['type'];
        }

        $order = 'ulog_time desc';
        $res = model('Ulog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '我的记录');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/ulog', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/ulog');
    }

    public function ulog_del()
    {
        $param = input();
        $ids = $param['ids'];
        $type = $param['type'];
        $all = $param['all'];

        if (!in_array($type, array('1', '2', '3', '4', '5'))) {
            return json(['code' => 1001, 'msg' => '参数错误']);
        }

        if (empty($ids) && empty($all)) {
            return json(['code' => 1001, 'msg' => '参数错误']);
        }

        $arr = [];
        $ids = explode(',', $ids);
        foreach ($ids as $k => $v) {
            $v = intval(abs($v));
            $arr[$v] = $v;
        }

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $where['ulog_type'] = $type;
        if ($all != '1') {
            $where['ulog_id'] = array('in', implode(',', $arr));
        }
        $return = model('Ulog')->delData($where);
        return json($return);
    }

    public function plog()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $order = 'plog_id desc';
        $res = model('Plog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '积分记录');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/plog', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/plog');
    }

    public function plog_del()
    {
        $param = input();
        $ids = $param['ids'];
        $type = $param['type'];
        $all = $param['all'];

        if (empty($ids) && empty($all)) {
            return json(['code' => 1001, 'msg' => '参数错误']);
        }

        $arr = [];
        $ids = explode(',', $ids);
        foreach ($ids as $k => $v) {
            $v = intval(abs($v));
            $arr[$v] = $v;
        }

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        if ($all != '1') {
            $where['plog_id'] = array('in', implode(',', $arr));
        }
        $return = model('Plog')->delData($where);
        return json($return);
    }

    public function cash()
    {
        $param = input();
        if (Request()->isPost()) {
            $param['user_id'] = $GLOBALS['user']['user_id'];
            $res = model('Cash')->saveData($param);
            return json($res);
        }

        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $order = 'cash_id desc';
        $res = model('Cash')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '提现记录');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/cash', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/cash');
    }

    public function cash_del()
    {
        $param = input();
        $ids = $param['ids'];
        $type = $param['type'];
        $all = $param['all'];

        if (empty($ids) && empty($all)) {
            return json(['code' => 1001, 'msg' => '参数错误']);
        }

        $arr = [];
        $ids = explode(',', $ids);
        foreach ($ids as $k => $v) {
            $v = intval(abs($v));
            $arr[$v] = $v;
        }

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        if ($all != '1') {
            $where['cash_id'] = array('in', implode(',', $arr));
        }
        $return = model('Cash')->delData($where);
        return json($return);
    }

    public function reward()
    {
        $param = input();

        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        if($param['level']=='2'){
            $where['user_pid_2'] = ['eq',$GLOBALS['user']['user_id']];
        }
        elseif($param['level']=='3'){
            $where['user_pid_3'] = ['eq',$GLOBALS['user']['user_id']];
        }
        else{
            $where['user_pid'] = ['eq',$GLOBALS['user']['user_id']];
        }

        $order = 'user_id desc';
        $res = model('User')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        $this->assign('title', '提现记录');
        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/plog', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        return $this->fetch('user/reward');
    }

    public function orders()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['o.user_id'] = $GLOBALS['user']['user_id'];

        $order = 'o.order_id desc';
        $res = model('Order')->listData($where, $order, $param['page'], $param['limit']);

        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/orders', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        return $this->fetch('user/orders');
    }

    public function order_info()
    {
        $param = input();
        $where = [];
        $where['order_id'] = $param['order_id'];
        $res = model('Order')->infoData($where);
        if (request()->isAjax()) {
            return json($res);
        }
        $this->assign('param',$param);
        return $this->fetch('user/order_info');
    }


    public function cards()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 20 ? 20 : intval($param['limit']);

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $where['card_use_status'] = 1;

        $order = 'card_id desc';
        $res = model('Card')->listData($where, $order, $param['page'], $param['limit']);

        $pages = mac_page_param($res['total'], $param['limit'], $param['page'], url('user/cards', ['page' => 'PAGELINK']));
        $this->assign('__PAGING__', $pages);
        $this->assign('param',$param);
        $this->assign('list', $res['list']);
        return $this->fetch('user/cards');
    }

    public function comment()
    {
        $param = input();
        $this->assign('param',$param);
        return $this->fetch('user/comment');
    }

    public function gbook()
    {
        $param = input();
        $this->assign('param',$param);
        return $this->fetch('user/gbook');
    }

    public function visit()
    {
        $param = input();
        $res = model('User')->visit($param);
        $url = '/';
        if(!empty($param['url'])){
            $tempu = @parse_url($param['url']);
            if($_SERVER['HTTP_HOST'] == $tempu['host']){
                $url = $param['url'];
            }
        }
        $this->redirect($url);
    }

}
