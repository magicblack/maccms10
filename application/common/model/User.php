<?php
namespace app\common\model;

use think\Db;
use think\View;
use app\common\validate\User as UserValidate;

class User extends Base
{
    // 设置数据表（不含前缀）
    protected $name = 'user';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    public $_guest_group = 1;
    public $_def_group = 2;

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        $total = $this->where($where)->count();
        $list = Db::name('User')->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    public function infoData($where, $field='*')
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 1001, 'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $info = $info->toArray();

        //用户组
        $group_list = model('Group')->getCache('group_list');
        $group_ids = explode(',', $info['group_id']);
        $info['group'] = $group_list[$group_ids[0]];
        $info['groups'] = [];
        foreach($group_ids as $gid){
            if(isset($group_list[$gid])){
                $info['groups'][] = $group_list[$gid];
            }
        }


        $info['user_pwd'] = '';
        return ['code' => 1, 'msg' =>lang('obtain_ok'), 'info' => $info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('User');

        if (isset($data['user_start_time']) && !is_numeric($data['user_start_time'])) {
            $data['user_start_time'] = strtotime($data['user_start_time']);
        }
        if (isset($data['user_end_time']) && !is_numeric($data['user_end_time'])) {
            $data['user_end_time'] = strtotime($data['user_end_time']);
        }

        // 选择VIP会员组（group_id > 2）时，包时截止时间必须大于当前时间
        $check_group_id = isset($data['group_id']) ? $data['group_id'] : 0;
        // 支持多组逗号分隔，取最大值判断
        $max_group_id = 0;
        if (!empty($check_group_id)) {
            $group_ids_arr = explode(',', $check_group_id);
            $max_group_id = max(array_map('intval', $group_ids_arr));
        }
        if ($max_group_id > 2) {
            $end_time_val = isset($data['user_end_time']) ? intval($data['user_end_time']) : 0;
            if ($end_time_val <= time()) {
                return ['code' => 1001, 'msg' => lang('model/user/vip_end_time_must_future')];
            }
        }

        if (!empty($data['user_id'])) {
            if (!$validate->scene('edit')->check($data)) {
                return ['code' => 1001, 'msg' => lang('param_err').'：' . $validate->getError()];
            }

            if (empty($data['user_pwd'])) {
                unset($data['user_pwd']);
            } else {
                $data['user_pwd'] = md5($data['user_pwd']);
            }
            $where = [];
            $where['user_id'] = ['eq', $data['user_id']];
            $res = $this->where($where)->update($data);
        } else {
            if (!$validate->scene('edit')->check($data)) {
                return ['code' => 1002, 'msg' => lang('param_err').'：' . $validate->getError()];
            }

            $data['user_pwd'] = md5($data['user_pwd']);
            $res = $this->insert($data);
            // 新增用户后自动生成邀请码
            if ($res !== false) {
                $nid = $this->getLastInsID();
                if ($nid > 0) {
                    $invite_code = $this->generateUniqueInviteCode($nid);
                    $this->where('user_id', $nid)->update(['user_invite_code' => $invite_code]);
                }
            }
        }
        if (false === $res) {
            return ['code' => 1003, 'msg' => '' . $this->getError()];
        }
        return ['code' => 1, 'msg' =>lang('save_ok')];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err').'：' . $this->getError()];
        }
        return ['code' => 1, 'msg'=>lang('del_ok')];
    }

    public function fieldData($where, $col, $val)
    {
        if (!isset($col) || !isset($val)) {
            return ['code' => 1001, 'msg'=>lang('param_err')];
        }
        $data = [];
        $data[$col] = $val;
        $res = $this->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1002, 'msg' => lang('set_err').'：' . $this->getError()];
        }
        return ['code' => 1, 'msg' =>lang('set_ok')];
    }

    public function register($param)
    {
        $config = config('maccms');

        $data = [];
        $password_raw = trim($param['user_pwd']);
        $data['user_name'] = htmlspecialchars(urldecode(trim($param['user_name'])));
        $data['user_pwd'] = htmlspecialchars(urldecode(trim($param['user_pwd'])));
        $data['user_pwd2'] = htmlspecialchars(urldecode(trim($param['user_pwd2'])));
        $data['verify'] = $param['verify'];
        $uid = $param['uid'];
        $is_from_3rdparty = !empty($param['user_openid_qq']) || !empty($param['user_openid_weixin']);


        if ($config['user']['status'] == 0 || $config['user']['reg_open'] == 0) {
            return ['code' => 1001, 'msg' => lang('model/user/not_open_reg')];
        }
        if (empty($data['user_name']) || empty($data['user_pwd']) || empty($data['user_pwd2'])) {
            return ['code' => 1002, 'msg' => lang('model/user/input_require')];
        }
        if (!$is_from_3rdparty && !captcha_check($data['verify']) && $config['user']['reg_verify'] == 1) {
            return ['code' => 1003, 'msg' => lang('verify_err')];
        }
        if ($data['user_pwd'] != $data['user_pwd2']) {
            return ['code' => 1004, 'msg' => lang('model/user/pass_not_pass2')];
        }
        $row = $this->where('user_name', $data['user_name'])->find();
        if (!empty($row)) {
            return ['code' => 1005, 'msg' => lang('model/user/haved_reg')];
        }
        if (!preg_match("/^[a-zA-Z\d]*$/i", $data['user_name'])) {
            return ['code' => 1006, 'msg' => lang('model/user/name_contain')];
        }

        $validate = \think\Loader::validate('User');
        if (!$validate->scene('add')->check($data)) {
            return ['code' => 1007, 'msg' => lang('param_err').'：' . $validate->getError()];
        }

        $filter = $GLOBALS['config']['user']['filter_words'];
        if(!empty($filter)) {
            $filter_arr = explode(',', $filter);
            $f_name = str_replace($filter_arr, '', $data['user_name']);
            if ($f_name != $data['user_name']) {
                return ['code' => 1008, 'msg' =>lang('model/user/name_filter',[$filter])];
            }
        }

        $ip = mac_get_ip_long();
        if( $GLOBALS['config']['user']['reg_num'] > 0){
            $where2=[];
            $where2['user_reg_ip'] = ['eq', $ip];
            $where2['user_reg_time'] = ['gt', strtotime('today')];
            $cc = $this->where($where2)->count();
            if($cc >= $GLOBALS['config']['user']['reg_num']){
                return ['code' => 1009, 'msg' => lang('model/user/ip_limit',[$GLOBALS['config']['user']['reg_num']])];
            }
        }

        $fields = [];
        $fields['user_name'] = $data['user_name'];
        $fields['user_pwd'] = md5($password_raw);
        $fields['group_id'] = $this->_def_group;
        $fields['user_points'] = intval($config['user']['reg_points']);
        $fields['user_status'] = intval($config['user']['reg_status']);
        $fields['user_reg_time'] = time();
        $fields['user_reg_ip'] = $ip;
        $fields['user_openid_qq'] = (string)$param['user_openid_qq'];
        $fields['user_openid_weixin'] = (string)$param['user_openid_weixin'];

        if (!$is_from_3rdparty) {
            // https://github.com/magicblack/maccms10/issues/418
            if($config['user']['reg_phone_sms'] == '1'){
                $param['type'] = 3;
                $res = $this->check_msg($param);
                if($res['code'] >1){
                    return $res;
                }
                $fields['user_phone'] = $param['to'];

                $update=[];
                $update['user_phone'] = '';
                $where2=[];
                $where2['user_phone'] = $param['to'];

                $row = $this->where($where2)->find();
                if (!empty($row)) {
                    return ['code' => 1011, 'msg' =>lang('model/user/phone_haved')];
                }
                //$this->where($where2)->update($update);
            }
            elseif($config['user']['reg_email_sms'] == '1'){
                $param['type'] = 3;
                $res = $this->check_msg($param);
                if($res['code'] >1){
                    return $res;
                }
                $fields['user_email'] = $param['to'];

                $update=[];
                $update['user_email'] = '';
                $where2=[];
                $where2['user_email'] = $param['to'];

                $row = $this->where($where2)->find();
                if (!empty($row)) {
                    return ['code' => 1012, 'msg' => lang('model/user/email_haved')];
                }
                //$this->where($where2)->update($update);
            }
        }

        $res = $this->insert($fields);
        if ($res === false) {
            return ['code' => 1010, 'msg' => lang('model/user/reg_err')];
        }
        $nid = $this->getLastInsID();
        
        $invite_code = $this->generateUniqueInviteCode($nid);
        $this->where('user_id', $nid)->update(['user_invite_code' => $invite_code]);
        
        $invite_code_param = trim($param['invite_code'] ?? '');
        $uid = intval($uid);
        
        if (!empty($invite_code_param)) {
            $uid = $this->getUserIdByInviteCode($invite_code_param);
        }
        
        if($uid > 0) {
            $where2 = [];
            $where2['user_id'] = $uid;
            $invite = $this->where($where2)->find();
            if ($invite) {
                $where=[];
                $where['user_id'] = $nid;
                $update=[];
                $update['user_pid'] = $invite['user_id'];
                $update['user_pid_2'] = $invite['user_pid'];
                $update['user_pid_3'] = $invite['user_pid_2'];
                $r1 = $this->where($where)->update($update);
                $r2 = false;
                $config['user']['invite_reg_num'] = intval($config['user']['invite_reg_num']);

                if($config['user']['invite_reg_points']>0){
                    $r2 = $this->where($where2)->setInc('user_points', $config['user']['invite_reg_points']);
                }

                if($r2!==false) {
                    //积分日志
                    $data = [];
                    $data['user_id'] = $uid;
                    $data['plog_type'] = 2;
                    $data['plog_points'] = $config['user']['invite_reg_points'];
                    model('Plog')->saveData($data);
                }
                $this->addInviteCount($uid);
            }
        }
        return ['code' => 1, 'msg' => lang('model/user/reg_ok')];
    }

    public function regcheck($t, $str)
    {
        $where = [];
        if ($t == 'user_name') {
            $where['user_name'] = $str;
            $row = $this->where($where)->find();
            if (!empty($row)) {
                return ['code' => 1001, 'msg' => lang('registered')];
            }
        } elseif ($t == 'user_email') {
            $where['user_email'] = $str;
            $row = $this->where($where)->find();
            if (!empty($row)) {
                return ['code' => 1001, 'msg' =>  lang('registered')];
            }
        } elseif ($t == 'verify') {
            if (!captcha_check($str)) {
                return ['code' => 1002, 'msg' => lang('verify_err')];
            }
        }
        return ['code' => 1, 'msg' => 'ok'];
    }

    public function info($param)
    {
        $pwd_old = isset($param['user_pwd']) ? trim($param['user_pwd']) : '';
        $pwd1 = isset($param['user_pwd1']) ? trim($param['user_pwd1']) : '';
        $pwd2 = isset($param['user_pwd2']) ? trim($param['user_pwd2']) : '';
        $wantPwdChange = ($pwd_old !== '' || $pwd1 !== '' || $pwd2 !== '');

        if ($wantPwdChange) {
            if ($pwd_old === '') {
                return ['code' => 1001, 'msg' => lang('model/user/input_old_pass')];
            }
            $password_raw = $pwd_old;
            $password_formatted = htmlspecialchars(urldecode($pwd_old));
            // 必须用库里的哈希：$GLOBALS['user'] 经模板/接口传递时可能不含 user_pwd，导致误判「原密码错误」
            $uid = intval($GLOBALS['user']['user_id'] ?? 0);
            if ($uid < 1) {
                return ['code' => 1002, 'msg' => lang('model/user/not_login')];
            }
            $storedHash = $this->where('user_id', $uid)->value('user_pwd');
            $storedHash = $storedHash === null ? '' : (string) $storedHash;
            $try = [md5($password_raw), md5($password_formatted)];
            // 与 login() 一致：兼容仅 md5(trim) 与 htmlspecialchars(urldecode(trim)) 后再 md5 两种入库方式
            $ok = false;
            foreach ($try as $h) {
                if ($h !== '' && hash_equals($storedHash, $h)) {
                    $ok = true;
                    break;
                }
            }
            // 与 login() 第二分支一致：极少数旧数据 user_pwd 列为明文
            if (!$ok && $storedHash !== '' && ($storedHash === $password_raw || $storedHash === $password_formatted)) {
                $ok = true;
            }
            if (!$ok) {
                return ['code' => 1002, 'msg' => lang('model/user/old_pass_err')];
            }
            if ($pwd1 === '' || $pwd2 === '') {
                return ['code' => 1003, 'msg' => lang('model/user/input_require')];
            }
            if ($pwd1 !== $pwd2) {
                return ['code' => 1004, 'msg' => lang('model/user/pass_not_same_pass2')];
            }
        }

        $data = [];
        $data['user_id'] = $GLOBALS['user']['user_id'];
        $data['user_name'] = $GLOBALS['user']['user_name'];
        if(!empty($param['user_nick_name'])){
            $data['user_nick_name'] = htmlspecialchars(urldecode(trim($param['user_nick_name'])));
        }
        $data['user_qq'] = htmlspecialchars(urldecode(trim($param['user_qq'])));
        $data['user_question'] = htmlspecialchars(urldecode(trim($param['user_question'])));
        $data['user_answer'] = htmlspecialchars(urldecode(trim($param['user_answer'])));
        if ($wantPwdChange && $pwd2 !== '') {
            $data['user_pwd'] = $pwd2;
        }
        return $this->saveData($data);
    }

    /**
     * 登录注册一体化：帐号存在则校验密码登录，不存在则自动注册并登录
     * @param array $param [user_name, user_pwd, invite_code(可选)]
     * @return array
     */
    public function loginOrRegister($param)
    {
        $config = config('maccms');
        $password_raw = trim($param['user_pwd']);
        $user_name = htmlspecialchars(urldecode(trim($param['user_name'])));

        if (empty($user_name) || empty($password_raw)) {
            return ['code' => 1001, 'msg' => lang('model/user/input_require')];
        }

        // 查找用户是否已存在
        $row = $this->where('user_name', $user_name)->find();

        if (!empty($row)) {
            // ---- 帐号存在：校验密码 ----
            $pwd_hash = md5($password_raw);
            if ($row['user_pwd'] !== $pwd_hash) {
                return ['code' => 1003, 'msg' => lang('pass_err')];
            }
            if ($row['user_status'] != 1) {
                return ['code' => 1004, 'msg' => lang('model/user/account_disabled')];
            }

            // 会员过期降级
            $login_group_ids = explode(',', $row['group_id']);
            $update = [];
            if (max($login_group_ids) > 2 && $row['user_end_time'] < time()) {
                $row['group_id'] = 2;
                $update['group_id'] = 2;
            }

            $random = md5(rand(10000000, 99999999));
            $update['user_random'] = $random;
            $update['user_login_ip'] = mac_get_ip_long();
            $update['user_login_time'] = time();
            $update['user_login_num'] = $row['user_login_num'] + 1;
            $update['user_last_login_time'] = $row['user_login_time'];
            $update['user_last_login_ip'] = $row['user_login_ip'];

            $this->where('user_id', $row['user_id'])->update($update);

            $this->_setLoginCookie($row, $random);

            $info = $this->where('user_id', $row['user_id'])->find();
            if ($info) { $info = $info->toArray(); }
            $info['user_pwd'] = '';

            return ['code' => 1, 'msg' => lang('model/user/login_ok'), 'action' => 'login', 'info' => $info];
        }

        // ---- 帐号不存在：自动注册 ----
        if ($config['user']['status'] == 0) {
            return ['code' => 1005, 'msg' => lang('model/user/user_feature_closed')];
        }

        // 用户名格式校验：仅英文+数字
        if (!preg_match("/^[a-zA-Z\d]{3,30}$/i", $user_name)) {
            return ['code' => 1006, 'msg' => lang('model/user/name_alnum_3_30')];
        }

        // 敏感词过滤
        $filter = !empty($GLOBALS['config']['user']['filter_words']) ? $GLOBALS['config']['user']['filter_words'] : '';
        if (!empty($filter)) {
            $filter_arr = explode(',', $filter);
            $f_name = str_replace($filter_arr, '', $user_name);
            if ($f_name != $user_name) {
                return ['code' => 1008, 'msg' => lang('model/user/name_has_sensitive_word')];
            }
        }

        // IP 注册限制
        $ip = mac_get_ip_long();
        if (!empty($GLOBALS['config']['user']['reg_num']) && $GLOBALS['config']['user']['reg_num'] > 0) {
            $where2 = [];
            $where2['user_reg_ip'] = ['eq', $ip];
            $where2['user_reg_time'] = ['gt', strtotime('today')];
            $cc = $this->where($where2)->count();
            if ($cc >= $GLOBALS['config']['user']['reg_num']) {
                return ['code' => 1009, 'msg' => lang('model/user/reg_daily_limit_reached')];
            }
        }

        // 密码长度校验
        if (strlen($password_raw) < 6) {
            return ['code' => 1010, 'msg' => lang('model/user/pass_length_err')];
        }

        // 创建用户
        $random = md5(rand(10000000, 99999999));
        $fields = [];
        $fields['user_name'] = $user_name;
        $fields['user_pwd'] = md5($password_raw);
        $fields['group_id'] = $this->_def_group;
        $fields['user_points'] = intval($config['user']['reg_points']);
        $fields['user_status'] = intval($config['user']['reg_status']);
        $fields['user_reg_time'] = time();
        $fields['user_reg_ip'] = $ip;
        $fields['user_random'] = $random;
        $fields['user_login_time'] = time();
        $fields['user_login_ip'] = $ip;
        $fields['user_login_num'] = 1;

        $res = $this->insert($fields);
        if ($res === false) {
            return ['code' => 1011, 'msg' => lang('model/user/reg_fail_try_later')];
        }
        $nid = $this->getLastInsID();

        // 生成邀请码
        $invite_code = $this->generateUniqueInviteCode($nid);
        $this->where('user_id', $nid)->update(['user_invite_code' => $invite_code]);

        // 处理邀请码
        $invite_code_param = trim($param['invite_code'] ?? '');
        if (!empty($invite_code_param)) {
            $uid = $this->getUserIdByInviteCode($invite_code_param);
            if ($uid > 0) {
                $invite = $this->where('user_id', $uid)->find();
                if ($invite) {
                    $upd = [];
                    $upd['user_pid'] = $invite['user_id'];
                    $upd['user_pid_2'] = $invite['user_pid'];
                    $upd['user_pid_3'] = $invite['user_pid_2'];
                    $this->where('user_id', $nid)->update($upd);

                    if (!empty($config['user']['invite_reg_points']) && $config['user']['invite_reg_points'] > 0) {
                        $this->where('user_id', $uid)->setInc('user_points', $config['user']['invite_reg_points']);
                        $pdata = [];
                        $pdata['user_id'] = $uid;
                        $pdata['plog_type'] = 2;
                        $pdata['plog_points'] = $config['user']['invite_reg_points'];
                        model('Plog')->saveData($pdata);
                    }
                    $this->addInviteCount($uid);
                }
            }
        }

        // 注册后自动登录
        $row = $this->where('user_id', $nid)->find();
        if ($row) {
            $this->_setLoginCookie($row, $random);
            $info = $row->toArray();
            $info['user_pwd'] = '';
            return ['code' => 1, 'msg' => lang('model/user/reg_ok_logged_in'), 'action' => 'register', 'info' => $info];
        }

        return ['code' => 1, 'msg' => lang('index/reg_ok'), 'action' => 'register'];
    }

    /**
     * 设置登录 Cookie（loginOrRegister / login 共用）
     */
    private function _setLoginCookie($row, $random)
    {
        $group_list = model('Group')->getCache('group_list');
        $group_ids = explode(',', $row['group_id']);
        $group = [];
        foreach ($group_ids as $gid) {
            if (isset($group_list[$gid])) {
                $group[] = $group_list[$gid];
            }
        }

        cookie('user_id', $row['user_id'], ['expire' => 2592000]);
        cookie('user_name', $row['user_name'], ['expire' => 2592000]);
        cookie('group_id', !empty($group[0]['group_id']) ? $group[0]['group_id'] : $this->_def_group, ['expire' => 2592000]);
        cookie('group_name', !empty($group[0]['group_name']) ? $group[0]['group_name'] : '', ['expire' => 2592000]);
        cookie('user_check', md5($random . '-' . $row['user_name'] . '-' . $row['user_id'] . '-'), ['expire' => 2592000]);
        cookie('user_portrait', mac_get_user_portrait($row['user_id']), ['expire' => 2592000]);
    }

    public function login($param)
    {
        $data = [];
        $password_raw = trim($param['user_pwd']);
        $data['user_name'] = htmlspecialchars(urldecode(trim($param['user_name'])));
        $data['user_pwd'] = htmlspecialchars(urldecode(trim($param['user_pwd'])));
        $data['verify'] = $param['verify'];
        $data['openid'] = htmlspecialchars(urldecode(trim($param['openid'])));
        $data['col'] = htmlspecialchars(urldecode(trim($param['col'])));

        if (empty($data['openid'])) {
            if (empty($data['user_name']) || empty($data['user_pwd'])) {
                return ['code' => 1001, 'msg' => lang('model/user/input_require')];
            }
            if ($GLOBALS['config']['user']['login_verify'] ==1 && !captcha_check($data['verify'])) {
                return ['code' => 1002, 'msg' => lang('verify_err')];
            }
            $where = [];
            $pattern = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/';
            if (!preg_match($pattern, $data['user_name'])) {
                $where['user_name'] = ['eq', $data['user_name']];
            } else {
                $where['user_email'] = ['eq', $data['user_name']];
            }
            // https://github.com/magicblack/maccms10/issues/781 兼容密码
            $where['user_pwd'] = [['eq', md5($password_raw)], ['eq', $data['user_pwd']], 'or'];
        } else {
            if (empty($data['openid']) || empty($data['col'])) {
                return ['code' => 1001, 'msg' => lang('model/user/input_require')];
            }
            if (!in_array($data['col'], ['user_openid_qq', 'user_openid_weixin'])) {
                return ['code' => 1002, 'msg' => lang('param_err') . ': col'];
            }
            $where[$data['col']] = $data['openid'];
        }
        $where['user_status'] = ['eq', 1];
        $row = $this->where($where)->find();

        if(empty($row)) {
            return ['code' => 1003, 'msg' => lang('model/user/not_found')];
        }

        $login_group_ids = explode(',', $row['group_id']);
        if(max($login_group_ids) > 2 &&  $row['user_end_time'] < time()) {
            $row['group_id'] = 2;
            $update['group_id'] = 2;
        }

        $random = md5(rand(10000000, 99999999));
        $update['user_random'] = $random;
        $update['user_login_ip'] = mac_get_ip_long();
        $update['user_login_time'] = time();
        $update['user_login_num'] = $row['user_login_num'] + 1;
        $update['user_last_login_time'] = $row['user_login_time'];
        $update['user_last_login_ip'] = $row['user_login_ip'];

        $res = $this->where($where)->update($update);
        if ($res === false) {
            return ['code' => 1004, 'msg' => lang('model/user/update_login_err')];
        }

        //用户组
        $group_list = model('Group')->getCache('group_list');
        $group_ids = explode(',', $row['group_id']);
        $group = [];
        foreach($group_ids as $gid){
            if(isset($group_list[$gid])){
                $group[] = $group_list[$gid];
            }
        }

        cookie('user_id', $row['user_id'],['expire'=>2592000] );
        cookie('user_name', $row['user_name'],['expire'=>2592000] );
        cookie('group_id', $group[0]['group_id'],['expire'=>2592000] );
        cookie('group_name', $group[0]['group_name'],['expire'=>2592000] );
        cookie('user_check', md5($random . '-' .$row['user_name'] . '-' . $row['user_id'] .'-' ),['expire'=>2592000] );
        cookie('user_portrait', mac_get_user_portrait($row['user_id']),['expire'=>2592000] );

        return ['code' => 1, 'msg' => lang('model/user/login_ok')];
    }

    public function expire()
    {
        $where=[];
        // 只处理VIP会员组（group_id > 2）且 user_end_time 已过期（排除 user_end_time=0 的普通用户）
        $where['group_id'] = ['gt', 2];
        $where['user_end_time'] = ['between', [1, time()]];

        $update=[];
        $update['group_id'] = '2';

        $res = $this->where($where)->update($update);
        if ($res === false) {
            return ['code' => 101, 'msg' => lang('model/user/update_expire_err')];
        }
        return ['code' => 1, 'msg' => lang('model/user/update_expire_ok')];
    }

    public function logout()
    {
        cookie('user_id', null);
        cookie('user_name', null);
        cookie('group_id', null);
        cookie('group_name', null);
        cookie('user_check', null);
        cookie('user_portrait', null);
        return ['code' => 1, 'msg' =>lang('model/user/logout_ok')];
    }

    public function checkLogin()
    {
        $user_id = cookie('user_id');
        $user_name = cookie('user_name');
        $user_check = cookie('user_check');

        $user_id = htmlspecialchars(urldecode(trim($user_id)));
        $user_name = htmlspecialchars(urldecode(trim($user_name)));
        $user_check = htmlspecialchars(urldecode(trim($user_check)));

        if (empty($user_id) || empty($user_name) || empty($user_check)) {
            return ['code' => 1001, 'msg' => lang('model/user/not_login')];
        }

        $where = [];
        $where['user_id'] = $user_id;
        $where['user_name'] = $user_name;
        $where['user_status'] = 1;

        $info = $this->field('*')->where($where)->find();
        if(empty($info)) {
            return ['code' => 1002, 'msg' => lang('model/user/not_login')];
        }
        $info = $info->toArray();
        $login_check = md5($info['user_random'] . '-' . $info['user_name']. '-' . $info['user_id'] .'-' );
        if($login_check != $user_check) {
            return ['code' => 1003, 'msg' => lang('model/user/not_login')];
        }

        $group_list = model('Group')->getCache('group_list');
        $group_ids = explode(',', $info['group_id']);
        $user_groups = [];
        $user_group_types = [];
        foreach($group_ids as $gid){
            if(isset($group_list[$gid])){
                $user_groups[] = $group_list[$gid];
                if (!empty($group_list[$gid]['group_type'])) {
                    $user_group_types = array_merge($user_group_types, explode(',', $group_list[$gid]['group_type']));
                }
            }
        }

        if (!empty($user_groups)) {
            $info['group'] = $user_groups[0];
            $info['group']['group_type'] = implode(',', array_unique(array_filter($user_group_types)));
            $info['groups'] = $user_groups;

            $all_names = [];
            foreach($user_groups as $g){
                $all_names[] = $g['group_name'];
            }
            $info['group']['group_name'] = implode(',', $all_names);

        } else {
            $info['group'] = $group_list[1];
        }

        //会员截止日期
        if (max($group_ids) > 2 && $info['user_end_time'] < time()) {
            //用户组
            $info['group'] = $group_list[2];

            $update = [];
            $update['group_id'] = 2;

            $res = $this->where($where)->update($update);
            if($res === false){
                return ['code' => 1004, 'msg' => lang('model/user/update_expire_err')];
            }

            $info['group_id'] = 2;
            $info['groups'] = [$group_list[2]];
            cookie('group_id', $info['group']['group_id'], ['expire'=>2592000] );
            cookie('group_name', $info['group']['group_name'],['expire'=>2592000] );
        }


        return ['code' => 1, 'msg' => lang('model/user/haved_login'), 'info' => $info];
    }

    public function resetPwd()
    {

    }

    public function findpass($param)
    {
        $data = [];
        $password_raw = trim($param['user_pwd']);
        $data['user_name'] = htmlspecialchars(urldecode(trim($param['user_name'])));
        $data['user_question'] = htmlspecialchars(urldecode(trim($param['user_question'])));
        $data['user_answer'] = htmlspecialchars(urldecode(trim($param['user_answer'])));
        $data['user_pwd'] = htmlspecialchars(urldecode(trim($param['user_pwd'])));
        $data['user_pwd2'] = htmlspecialchars(urldecode(trim($param['user_pwd2'])));
        $data['verify'] = $param['verify'];

        if (empty($data['user_name']) || empty($data['user_question']) || empty($data['user_answer']) || empty($data['user_pwd']) || empty($data['user_pwd2']) || empty($data['verify'])) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        if (!captcha_check($data['verify'])) {
            return ['code' => 1002, 'msg' => lang('verify_err')];
        }

        if ($data['user_pwd'] != $data['user_pwd2']) {
            return ['code' => 1003, 'msg' => lang('model/user/pass_not_same_pass2')];
        }


        $where = [];
        $where['user_name'] = $data['user_name'];
        $where['user_question'] = $data['user_question'];
        $where['user_answer'] = $data['user_answer'];

        $info = $this->where($where)->find();
        if (empty($info)) {
            return ['code' => 1004, 'msg' => lang('model/user/findpass_not_found')];
        }

        $update = [];
        $update['user_pwd'] = md5($password_raw);

        $where = [];
        $where['user_id'] = $info['user_id'];
        $res = $this->where($where)->update($update);

        if (false === $res) {
            return ['code' => 1005, 'msg' => '' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('model/user/findpass_ok')];

    }

    public function popedom($type_id, $popedom, $group_ids = 1)
    {
        $group_list = model('Group')->getCache();
        $group_ids = explode(',', $group_ids);
        
        foreach($group_ids as $group_id) {
            if(!isset($group_list[$group_id])) {
                continue;
            }
            $group_info = $group_list[$group_id];
            
            if (strpos(',' . $group_info['group_type'], ',' . $type_id . ',') !== false && !empty($group_info['group_popedom'][$type_id][$popedom]) !== false) {
                return true;
            }
        }
        return false;
    }

    public function upgrade($param)
    {
        $group_id = intval($param['group_id']);
        $long = $param['long'];
        $points_long = ['day'=>86400,'week'=>86400*7,'month'=>86400*30,'year'=>86400*365];

        if (!array_key_exists($long, $points_long)) {
            return ['code'=>1001,'msg'=>'非法操作'];
        }

        if($group_id <3){
            return ['code'=>1002,'msg'=>lang('model/user/select_diy_group_err')];
        }

        $group_list = model('Group')->getCache();
        $group_info = $group_list[$group_id];
        if(empty($group_info)){
            return ['code'=>1003,'msg'=>lang('model/user/group_not_found')];
        }

        if($group_info['group_status'] == 0){
            return ['code'=>1004,'msg'=>lang('model/user/group_is_close')];
        }

        $point = $group_info['group_points_'.$long];
        if($GLOBALS['user']['user_points'] < $point){
            return ['code'=>1005,'msg'=>lang('model/user/potins_not_enough')];
        }

        $sj = $points_long[$long];
        $end_time = time() + $sj;
        if($GLOBALS['user']['user_end_time'] > time() ){
            $end_time = $GLOBALS['user']['user_end_time'] + $sj;
        }

        $where = [];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        
        $data = [];
        $data['user_points'] = $GLOBALS['user']['user_points'] - $point;
        $data['user_end_time'] = $end_time;
        $data['group_id'] = $group_id;

        $res = $this->where($where)->update($data);
        if($res===false){
            return ['code'=>1009,'msg'=>lang('model/user/update_group_err')];
        }

        //积分日志
        $data = [];
        $data['user_id'] = $GLOBALS['user']['user_id'];
        $data['plog_type'] = 7;
        $data['plog_points'] = $point;
        model('Plog')->saveData($data);
        //分销日志
        $this->reward($point);

        cookie('group_id', $group_info['group_id'],['expire'=>2592000] );
        cookie('group_name', $group_info['group_name'],['expire'=>2592000] );

        return ['code'=>1,'msg'=>lang('model/user/update_group_ok')];
    }

    public function upgradeByPaidOrder($order, $user)
    {
        $remarks = json_decode($order['order_remarks'], true);
        if (empty($remarks) || !is_array($remarks) || ($remarks['biz'] ?? '') !== 'member_upgrade') {
            return ['code' => 1001, 'msg' => lang('model/user/order_not_member_upgrade')];
        }

        $group_id = intval($remarks['group_id'] ?? 0);
        $long = trim($remarks['long'] ?? '');
        $point = intval($remarks['upgrade_points'] ?? 0);
        $points_long = ['day' => 86400, 'week' => 86400 * 7, 'month' => 86400 * 30, 'year' => 86400 * 365];
        if ($group_id < 3 || !isset($points_long[$long]) || $point < 1) {
            return ['code' => 1002, 'msg' => lang('model/user/upgrade_param_invalid')];
        }

        $group_list = model('Group')->getCache();
        if (!isset($group_list[$group_id]) || intval($group_list[$group_id]['group_status']) !== 1) {
            return ['code' => 1003, 'msg' => lang('model/user/group_not_found')];
        }

        if (intval($user['user_points']) < $point) {
            return ['code' => 1004, 'msg' => lang('model/user/potins_not_enough')];
        }

        $sj = $points_long[$long];
        $end_time = time() + $sj;
        if (intval($user['user_end_time']) > time()) {
            $end_time = intval($user['user_end_time']) + $sj;
        }

        $where = ['user_id' => intval($user['user_id'])];
        $data = [];
        $data['user_points'] = intval($user['user_points']) - $point;
        $data['user_end_time'] = $end_time;
        $data['group_id'] = $group_id;
        $res = $this->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1005, 'msg' => lang('model/user/update_group_err')];
        }

        $plog = [];
        $plog['user_id'] = intval($user['user_id']);
        $plog['plog_type'] = 7;
        $plog['plog_points'] = $point;
        $plog['plog_remarks'] = '支付后自动升级会员：' . ($group_list[$group_id]['group_name'] ?? '');
        model('Plog')->saveData($plog);

        // 为了复用原有分销逻辑，临时填充全局用户上下文
        $prevUser = $GLOBALS['user'] ?? null;
        $GLOBALS['user'] = $user;
        $this->reward($point);
        $GLOBALS['user'] = $prevUser;

        cookie('group_id', $group_id, ['expire' => 2592000]);
        cookie('group_name', $group_list[$group_id]['group_name'] ?? '', ['expire' => 2592000]);

        return ['code' => 1, 'msg' => lang('model/user/update_group_ok')];
    }

    public function check_msg($param)
    {
        $param['to'] = htmlspecialchars(urldecode(trim($param['to'])));
        $param['code'] = htmlspecialchars(urldecode(trim($param['code'])));
        if(!in_array($param['ac'],['email','phone']) || empty($param['to']) || empty($param['code']) || empty($param['type'])){
            return ['code'=>9001,'msg'=>lang('param_err')];
        }
        // https://github.com/magicblack/maccms10/issues/792 邮箱增加黑白名单校验
        if ($param['ac'] == 'email' && in_array($param['type'], [1, 3])) {
            $result = UserValidate::validateEmail($param['to']);
            if ($result['code'] > 1) {
                return $result;
            }
        }
        //msg_type  1绑定2找回3注册
        $stime = strtotime('-5 min');
        if($param['ac']=='email' && intval($GLOBALS['config']['email']['time'])>0){
            $stime = strtotime('-'.$GLOBALS['config']['email']['time'].' min');
        }

        $where=[];
        $where['user_id'] = intval($GLOBALS['user']['user_id']);
        $where['msg_time'] = ['gt',$stime];
        $where['msg_code'] = ['eq',$param['code']];
        $where['msg_type'] = ['eq', $param['type'] ];
        $res = model('msg')->infoData($where);
        if($res['code'] >1){
            return ['code'=>9002,'msg'=>lang('model/user/msg_not_found')];
        }
        return  ['code'=>1,'msg'=>'ok'];
    }

    public function send_msg($param)
    {
        $param['to'] = htmlspecialchars(urldecode(trim($param['to'])));
        $param['code'] = htmlspecialchars(urldecode(trim($param['code'])));

        $type_arr = [
            1=>['des'=>lang('bind'),'flag'=>'bind'],
            2=>['des'=>lang('findpass'),'flag'=>'findpass'],
            3=>['des'=>lang('register'),'flag'=>'reg'],
        ];
        if(!in_array($param['ac'],['email','phone']) || !isset($type_arr[$param['type']]) || empty($param['to'])  || empty($param['type'])){
            return ['code'=>9001,'msg'=>lang('param_err')];
        }
        // https://github.com/magicblack/maccms10/issues/792 邮箱增加黑白名单校验
        if ($param['ac'] == 'email' && in_array($param['type'], [1, 3])) {
            $result = UserValidate::validateEmail($param['to']);
            if ($result['code'] > 1) {
                return $result;
            }
        }

        $type_des = $type_arr[$param['type']]['des'];
        $type_flag = $type_arr[$param['type']]['flag'];


        $to = $param['to'];
        $code = mac_get_rndstr(6,'num');
        $r=0;

        $stime = strtotime('-5 min');
        if($param['ac']=='email' && intval($GLOBALS['config']['email']['time'])>0){
            $stime = strtotime('-'.$GLOBALS['config']['email']['time'].' min');
        }
        $where=[];
        $where['user_id'] = intval($GLOBALS['user']['user_id']);
        $where['msg_time'] = ['gt',$stime];
        $where['msg_type'] = ['eq', $param['type'] ];
        $where['msg_to'] = ['eq', $param['to'] ];
        $res = model('msg')->infoData($where);
        if($res['code'] ==1){
            return ['code'=>9002,'msg'=>lang('model/user/do_not_send_frequently')];
        }
        $res_msg= ','.lang('please_try_again');
        if($param['ac']=='email'){
            $title = $GLOBALS['config']['email']['tpl']['user_'.$type_flag.'_title'];
            $msg = $GLOBALS['config']['email']['tpl']['user_'.$type_flag.'_body'];
            View::instance()->assign(['code'=>$code,'time'=>$GLOBALS['config']['email']['time']]);
            $title =  View::instance()->display($title);
            $msg =  View::instance()->display($msg);
            $msg = htmlspecialchars_decode($msg);
            $res_send = mac_send_mail($to, $title, $msg);
            $res_code = $res_send['code'];
            $res_msg = $res_send['msg'];
        }
        else{
            $msg = $GLOBALS['config']['sms']['content'];
            $msg = str_replace(['[用户]','[类型]','[时长]','[验证码]'],[$GLOBALS['user']['user_name'],$type_des,'5',$code],$msg);
            $res_send = mac_send_sms($to,$code,$type_flag,$type_des,$msg);
            $res_code = $res_send['code'];
            $res_msg = $res_send['msg'];
        }
        
        if($res_code==1){
            $data=[];
            $data['user_id'] = intval($GLOBALS['user']['user_id']);
            $data['msg_type'] = $param['type'];
            $data['msg_status'] = 0;
            $data['msg_to'] = $to;
            $data['msg_code'] = $code;
            $data['msg_content'] = $msg;
            $data['msg_time'] = time();
            $res = model('msg')->saveData($data);

            return ['code'=>1,'msg'=>lang('model/user/msg_send_ok')];
        }
        else{
            return ['code'=>9009,'msg'=>lang('model/user/msg_send_err').'：'.$res_msg];
        }
    }

    public function bind($param)
    {
        $param['type'] = 1;
        $res = $this->check_msg($param);
        if($res['code'] >1){
            return ['code'=>$res['code'],'msg'=>$res['msg']];
        }

        $update=[];
        $update2=[];
        $where2=[];
        if($param['ac']=='email') {
            $update['user_email'] = $param['to'];
            $update2['user_email'] = '';
            $where2['user_email'] = $param['to'];
        }
        else{
            $update['user_phone'] = $param['to'];
            $update2['user_phone'] = '';
            $where2['user_phone'] = $param['to'];
        }
        $this->where($where2)->update($update2);

        $where=[];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>2003,'msg'=>lang('model/user/update_bind_err')];
        }
        return ['code'=>1,'msg'=>lang('model/user/update_bind_ok')];
    }

    public function unbind($param)
    {
        if(!in_array($param['ac'],['email','phone']) ){
            return ['code'=>2001,'msg'=>lang('param_err')];
        }
        $col = 'user_email';
        if($param['ac']=='phone'){
            $col = 'user_phone';
        }
        $update=[];
        $update[$col] = '';
        $where=[];
        $where['user_id'] = $GLOBALS['user']['user_id'];
        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>2002,'msg'=>lang('model/user/update_bind_err')];
        }
        return ['code'=>1,'msg'=>lang('model/user/update_unbind_ok')];
    }

    public function bindmsg($param)
    {
        $param['type'] = 1;
        return $this->send_msg($param);
    }

    public function findpass_msg($param)
    {
        $param['type'] = 2;
        return $this->send_msg($param);
    }

    public function reg_msg($param)
    {
        $param['type'] = 3;
        return $this->send_msg($param);
    }


    public function findpass_reset($param)
    {
        $to = htmlspecialchars(urldecode(trim($param['user_email'])));
        if(empty($to)){
            $to = htmlspecialchars(urldecode(trim($param['to'])));
        }

        $password_raw = trim($param['user_pwd']);
        $param['code'] = htmlspecialchars(urldecode(trim($param['code'])));
        $param['user_pwd'] = htmlspecialchars(urldecode(trim($param['user_pwd'])));
        $param['user_pwd2'] = htmlspecialchars(urldecode(trim($param['user_pwd2'])));


        if (strlen($param['user_pwd']) < 6) {
            return ['code' => 2002, 'msg' => lang('model/user/pass_length_err')];
        }
        if ($param['user_pwd'] != $param['user_pwd2']) {
            return ['code' => 2003, 'msg' => lang('model/user/pass_not_same_pass2')];
        }

        $param['type'] = 2;
        $res = $this->check_msg($param);
        if($res['code'] >1){
            return ['code'=>$res['code'],'msg'=>$res['msg']];
        }

        if($param['ac']=='email') {

            $pattern = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/';
            if(!preg_match( $pattern, $to)){
                return ['code'=>2005,'msg'=>lang('model/user/email_format_err')];
            }

            $where = [];
            $where['user_email'] = $to;
            $user = $this->where($where)->find();
            if (!$user) {
                return ['code' => 2006, 'msg' => lang('model/user/email_err')];
            }
        }
        else{
            $pattern = "/^1{1}\d{10}$/";
            if(!preg_match($pattern,$to)){
                return ['code'=>2007,'msg'=>lang('model/user/phone_format_err')];
            }

            $where = [];
            $where['user_phone'] = $to;
            $user = $this->where($where)->find();
            if (!$user) {
                return ['code' => 2008, 'msg' =>lang('model/user/phone_err')];
            }
        }

        $update = [];
        $update['user_pwd'] = md5($password_raw);
        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>2009,'msg'=>lang('model/user/pass_reset_err')];
        }
        return ['code'=>1,'msg'=>lang('model/user/pass_reset_ok')];
    }

    public function visit($param)
    {
        $param['uid'] = abs(intval($param['uid']));
        if ($param['uid'] == 0) {
            return ['code' => 101, 'msg' =>lang('model/user/id_err')];
        }

        $ip = mac_get_ip_long();
        $max_cc = $GLOBALS['config']['user']['invite_visit_num'];
        if(empty($max_cc)){
            $max_cc=1;
        }
        $todayunix = strtotime("today");
        $where = [];
        $where['user_id'] = $param['uid'];
        $where['visit_ip'] = $ip;
        $where['visit_time'] = ['gt', $todayunix];
        $cc = model('visit')->where($where)->count();
        if ($cc>= $max_cc){
            return ['code' => 102, 'msg' => lang('model/user/visit_tip')];
        }

        $data = [];
        $data['user_id'] = $param['uid'];
        $data['visit_ip'] = $ip;
        $data['visit_time'] = time();
        $data['visit_ly'] = htmlspecialchars(mac_get_refer());
        $res = model('visit')->saveData($data);

        if ($res['code'] > 1) {
            return ['code' => 103, 'msg' => lang('model/user/visit_err')];
        }

        $res = $this->where('user_id', $param['uid'])->setInc('user_points', intval($GLOBALS['config']['user']['invite_visit_points']));
        if($res) {
            //积分日志
            $data = [];
            $data['user_id'] = $param['uid'];
            $data['plog_type'] = 3;
            $data['plog_points'] = intval($GLOBALS['config']['user']['invite_visit_points']);
            model('Plog')->saveData($data);
        }

        return ['code'=>1,'msg'=>lang('model/user/visit_ok')];
    }

    public function reward($fee_points=0)
    {
        //三级分销
        if($fee_points>0 && $GLOBALS['config']['user']['reward_status'] == '1'){

            if(!empty($GLOBALS['config']['user']['reward_ratio']) && !empty($GLOBALS['user']['user_pid'])){
                $points = floor($fee_points / 100 * $GLOBALS['config']['user']['reward_ratio']);
                if($points>0){
                    $where=[];
                    $where['user_id'] = $GLOBALS['user']['user_pid'];
                    $r = model('User')->where($where)->setInc('user_points',$points);
                    if($r){
                        $data = [];
                        $data['user_id'] = $GLOBALS['user']['user_pid'];
                        $data['plog_type'] = 4;
                        $data['plog_points'] = $points;
                        $data['plog_remarks'] = lang('model/user/reward_tip',[$GLOBALS['user']['user_id'],$GLOBALS['user']['user_name'],$fee_points,$points]);
                        model('Plog')->saveData($data);
                    }
                }
            }
            if(!empty($GLOBALS['config']['user']['reward_ratio_2']) && !empty($GLOBALS['user']['user_pid_2'])){
                $points = floor($fee_points / 100 * $GLOBALS['config']['user']['reward_ratio_2']);
                if($points>0){
                    $where=[];
                    $where['user_id'] = $GLOBALS['user']['user_pid_2'];
                    $r = model('User')->where($where)->setInc('user_points',$points);
                    if($r){
                        $data = [];
                        $data['user_id'] = $GLOBALS['user']['user_pid_2'];
                        $data['plog_type'] = 5;
                        $data['plog_points'] = $points;
                        $data['plog_remarks'] =lang('model/user/reward_tip',[$GLOBALS['user']['user_id'],$GLOBALS['user']['user_name'],$fee_points,$points]);
                        model('Plog')->saveData($data);
                    }
                }
            }
            if(!empty($GLOBALS['config']['user']['reward_ratio_3']) && !empty($GLOBALS['user']['user_pid_3'])){
                $points = floor($fee_points / 100 * $GLOBALS['config']['user']['reward_ratio_3']);
                if($points>0){
                    $where=[];
                    $where['user_id'] = $GLOBALS['user']['user_pid_3'];
                    $r = model('User')->where($where)->setInc('user_points',$points);
                    if($r){
                        $data = [];
                        $data['user_id'] = $GLOBALS['user']['user_pid_3'];
                        $data['plog_type'] = 6;
                        $data['plog_points'] = $points;
                        $data['plog_remarks'] = lang('model/user/reward_tip',[$GLOBALS['user']['user_id'],$GLOBALS['user']['user_name'],$fee_points,$points]);
                        model('Plog')->saveData($data);
                    }
                }
            }
        }

        return ['code'=>1,'msg'=>lang('model/user/reward_ok')];
    }

    /**
     * 根据用户ID生成邀请码
     * @param int $user_id
     * @return string
     */
    public function generateInviteCode($user_id)
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $charsLength = strlen($chars);
        for ($i = 0; $i < 5; $i++) {
            $code .= $chars[random_int(0, $charsLength - 1)];
        }
        return $code;
    }
    
    /**
     * 检查邀请码是否已存在
     * @param string $code
     * @return bool
     */
    public function checkInviteCodeExists($code)
    {
        $count = $this->where('user_invite_code', $code)->count();
        return $count > 0;
    }
    
    /**
     * 生成唯一的不重复邀请码
     * @param int $user_id
     * @return string
     */
    public function generateUniqueInviteCode($user_id)
    {
        $max_attempts = 10;
        for ($i = 0; $i < $max_attempts; $i++) {
            $code = $this->generateInviteCode($user_id);
            if (!$this->checkInviteCodeExists($code)) {
                return $code;
            }
        }
        
        $fallback_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        if ($this->checkInviteCodeExists($fallback_code)) {
            $fallback_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        }
        
        return $fallback_code;
    }

    /**
     * 根据邀请码获取用户ID
     * @param string $invite_code
     * @return int
     */
    public function getUserIdByInviteCode($invite_code)
    {
        $info = $this->where('user_invite_code', $invite_code)->find();
        return $info ? $info['user_id'] : 0;
    }

    /**
     * 处理邀请奖励
     * 当被邀请人注册时，自动为邀请人发放奖励
     * @param int $user_id 邀请人用户ID
     */
    public function processInviteReward($user_id)
    {
        $maccms_config = config('maccms');
        $config = $maccms_config['user'];
        
        if (empty($config['invite_reward_status'])) {
            return;
        }
        
        $invite_reward = $config['invite_reward'];
        if (empty($invite_reward) || !is_array($invite_reward)) {
            return;
        }
        
        $sorted_reward = [];
        foreach ($invite_reward as $count => $reward) {
            $count = intval($count);
            if ($count > 0) {
                $sorted_reward[$count] = $reward;
            }
        }
        ksort($sorted_reward);
        
        // 使用事务 + SELECT FOR UPDATE 行锁，防止并发重复发放奖励
        \think\Db::startTrans();
        try {
            $where = [];
            $where['user_id'] = $user_id;
            // lock(true) = SELECT ... FOR UPDATE，锁定该行直到事务结束
            $user_info = $this->where($where)->lock(true)->find();
            
            if (!$user_info) {
                \think\Db::rollback();
                return;
            }
            
            $invite_count = intval($user_info['user_invite_count']);
            $current_reward_level = intval($user_info['user_invite_reward_level']);
            
            $update = [];
            $updated = false;
            
            $current_end_time = ($user_info['user_end_time'] > time())
                ? $user_info['user_end_time'] : time();
            
            foreach ($sorted_reward as $count => $reward) {
                if ($count > $current_reward_level && $invite_count >= $count) {
                    $group_id = intval($reward['group_id']);
                    $long = $reward['long'];
                    $points = intval($reward['points']);
                    
                    if ($group_id >= 2) {
                        $points_long = ['day'=>86400,'week'=>86400*7,'month'=>86400*30,'year'=>86400*365];
                        
                        if (isset($points_long[$long])) {
                            $current_groups = explode(',', $user_info['group_id']);
                            $current_max_group = max(array_map('intval', $current_groups));
                            
                            if ($group_id > $current_max_group) {
                                $new_groups = array_unique(array_merge($current_groups, [$group_id]));
                                $new_groups = array_filter($new_groups, function($v) { return intval($v) > 0; });
                                sort($new_groups, SORT_NUMERIC);
                                $update['group_id'] = implode(',', $new_groups);
                            }
                            
                            $sj = $points_long[$long];
                            $current_end_time += $sj;
                            
                            $update['user_end_time'] = intval($current_end_time);
                            $updated = true;
                            
                            $data = [];
                            $data['user_id'] = $user_id;
                            $data['plog_type'] = 8;
                            $data['plog_points'] = 0;
                            $data['plog_remarks'] = '邀请奖：邀请' . $invite_count . '人，获得VIP ' . ($long == 'day' ? '1天' : ($long == 'week' ? '1周' : ($long == 'month' ? '1个月' : '1年')));
                            model('Plog')->saveData($data);
                        }
                    }
                    
                    if ($points > 0) {
                        $this->where($where)->setInc('user_points', $points);
                        
                        $data = [];
                        $data['user_id'] = $user_id;
                        $data['plog_type'] = 8;
                        $data['plog_points'] = $points;
                        $data['plog_remarks'] = '邀请奖励：邀请' . $invite_count . '人，获得' . $points . '积分';
                        model('Plog')->saveData($data);
                    }
                    
                    $update['user_invite_reward_level'] = $count;
                    $update['user_invite_reward_time'] = time();
                    $updated = true;
                }
            }
            
            if ($updated) {
                $this->where($where)->update($update);
            }
            
            \think\Db::commit();
        } catch (\Exception $e) {
            \think\Db::rollback();
        }
    }

    /**
     * 增加邀请计数并处理奖励
     * @param int $user_id
     */
    public function addInviteCount($user_id)
    {
        $this->where('user_id', $user_id)->setInc('user_invite_count', 1);
        
        $this->processInviteReward($user_id);
    }

}
