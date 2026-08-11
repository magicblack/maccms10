<?php
namespace app\common\model;

use app\common\util\JwtService;
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

    /** 禁止通过前台 / 公开 API 输出的用户字段（含会话伪造所需 user_random） */
    private static $sensitiveFields = [
        'user_pwd',
        'user_random',
        'user_answer',
        'user_question',
        'user_reg_ip',
        'user_login_ip',
        'user_last_login_ip',
        'user_openid_qq',
        'user_openid_weixin',
    ];

    /**
     * 公开 API 用户详情允许返回的字段（白名单）。
     *
     * @return string
     */
    public function publicApiDetailFields()
    {
        return 'user_id,user_name,user_nick_name,user_phone,user_qq,user_email,group_id,user_points,user_exp,user_integral,user_invite_code,user_invite_count,user_reg_time,user_status';
    }

    /**
     * 剥离会话/凭证相关字段，避免公开接口泄露后可伪造 user_check / JWT。
     *
     * @param array|null $row
     *
     * @return array|null
     */
    public function stripSensitiveFields($row)
    {
        if (!is_array($row)) {
            return $row;
        }
        foreach (self::$sensitiveFields as $key) {
            unset($row[$key]);
        }

        return $row;
    }

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
        $info = $this->stripSensitiveFields($info);
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
                $data['user_pwd'] = mac_hash_password_for_column($data['user_pwd'], 'user', 'user_pwd');
            }
            $where = [];
            $where['user_id'] = ['eq', $data['user_id']];
            $res = $this->where($where)->update($data);
        } else {
            if (!$validate->scene('edit')->check($data)) {
                return ['code' => 1002, 'msg' => lang('param_err').'：' . $validate->getError()];
            }

            $data['user_pwd'] = mac_hash_password_for_column($data['user_pwd'], 'user', 'user_pwd');
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
        $password_raw = isset($param['user_pwd']) ? trim($param['user_pwd']) : '';
        $data['user_name'] = htmlspecialchars(urldecode(trim(isset($param['user_name']) ? $param['user_name'] : '')));
        $data['user_pwd'] = htmlspecialchars(urldecode(trim(isset($param['user_pwd']) ? $param['user_pwd'] : '')));
        $data['user_pwd2'] = htmlspecialchars(urldecode(trim(isset($param['user_pwd2']) ? $param['user_pwd2'] : '')));
        $data['verify'] = isset($param['verify']) ? $param['verify'] : '';
        $uid = isset($param['uid']) ? $param['uid'] : 0;
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

        $validate = new \app\common\validate\User();
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
        $fields['user_pwd'] = mac_hash_password_for_column($password_raw, 'user', 'user_pwd');
        $fields['group_id'] = $this->_def_group;
        $fields['user_points'] = intval($config['user']['reg_points']);
        $fields['user_status'] = intval($config['user']['reg_status']);
        $fields['user_reg_time'] = time();
        $fields['user_reg_ip'] = $ip;
        $fields['user_openid_qq'] = isset($param['user_openid_qq']) ? (string)$param['user_openid_qq'] : '';
        $fields['user_openid_weixin'] = isset($param['user_openid_weixin']) ? (string)$param['user_openid_weixin'] : '';

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
        // 风控日志（issue #149）：注册成功记录 IP/UA
        \app\common\model\UserAccessLog::record('register', ['user_id' => $nid, 'user_name' => $fields['user_name']]);

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

    /**
     * 重新鉴权：校验当前用户密码（bind/unbind 变更绑定联系方式前调用）
     * @param string $password_raw 用户提交的当前密码明文
     * @return array|null 校验通过返回 null，失败返回 ['code'=>..., 'msg'=>...]
     */
    private function verifyCurrentPassword($password_raw)
    {
        if ($password_raw === '') {
            return ['code' => 1001, 'msg' => lang('model/user/input_old_pass')];
        }
        $uid = intval($GLOBALS['user']['user_id'] ?? 0);
        if ($uid < 1) {
            return ['code' => 1002, 'msg' => lang('model/user/not_login')];
        }
        $storedHash = $this->where('user_id', $uid)->value('user_pwd');
        $storedHash = $storedHash === null ? '' : (string) $storedHash;
        // 兼容 bcrypt / md5(trim) / md5(formatted) / 明文（与 login()、info() 一致）。
        // 密码迁移到 bcrypt 后旧的 md5 比对必然失败，统一委托 mac_verify_password。
        $ok = mac_verify_password($password_raw, $storedHash);
        if (!$ok) {
            return ['code' => 1002, 'msg' => lang('model/user/old_pass_err')];
        }
        return null;
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
            // 必须用库里的哈希：$GLOBALS['user'] 经模板/接口传递时可能不含 user_pwd，导致误判「原密码错误」
            $uid = intval($GLOBALS['user']['user_id'] ?? 0);
            if ($uid < 1) {
                return ['code' => 1002, 'msg' => lang('model/user/not_login')];
            }
            $storedHash = $this->where('user_id', $uid)->value('user_pwd');
            $storedHash = $storedHash === null ? '' : (string) $storedHash;
            // 集中到 mac_verify_password：兼容 bcrypt / md5(trim) / md5(formatted) / 明文 四种历史形态
            $ok = mac_verify_password($password_raw, $storedHash);
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
            if (!mac_verify_password($password_raw, $row['user_pwd'])) {
                \app\common\model\UserAccessLog::record('login_fail', [
                    'user_id' => 0,
                    'user_name' => $user_name,
                ]);
                return ['code' => 1003, 'msg' => lang('pass_err')];
            }
            if ($row['user_status'] != 1) {
                \app\common\model\UserAccessLog::record('login_fail', [
                    'user_id' => 0,
                    'user_name' => $user_name,
                ]);
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

            // 惰性迁移：旧 md5/明文哈希在登录成功后升级为 bcrypt。
            // 升级窗口期 user_pwd 列可能仍是 char(32)/varchar(32)，先确认列宽
            // 能容纳 60 字符 bcrypt，否则跳过迁移避免被静默截断锁死账号。
            if (mac_password_needs_rehash($row['user_pwd']) && mac_pwd_column_fits_hash('user', 'user_pwd')) {
                $update['user_pwd'] = mac_hash_password($password_raw);
            }

            $this->where('user_id', $row['user_id'])->update($update);

            $this->_setLoginCookie($row, $random);
            \app\common\model\UserAccessLog::record('login', [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
            ]);

            $info = $this->where('user_id', $row['user_id'])->find();
            if ($info) {
                $info = $info->toArray();
            }
            $info = $this->stripSensitiveFields($info);

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
        $fields['user_pwd'] = mac_hash_password_for_column($password_raw, 'user', 'user_pwd');
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
        \app\common\model\UserAccessLog::record('register', [
            'user_id' => $nid,
            'user_name' => $user_name,
        ]);

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
            $info = $this->stripSensitiveFields($info);
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

    public function login($param, array $options = [])
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
            // 移除 OR 分支（issues/781 遗留兼容）：不再接受「提交等于哈希值的字符串」。
            // user_pwd 不放进 $where；先按用户名/邮箱取行，再用 mac_verify_password 校验。
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
            if (empty($data['openid'])) {
                // 查无或停用账号同样是撞库/账号喷洒的重要信号；失败事件固定 user_id=0，
                // 不把攻击者来源误关联到被尝试的账号。
                \app\common\model\UserAccessLog::record('login_fail', [
                    'user_id' => 0,
                    'user_name' => $data['user_name'],
                ]);
            }
            return ['code' => 1003, 'msg' => lang('model/user/not_found')];
        }

        // 用户名/密码登录需校验密码；openid 登录凭 openid 即可，不校验密码
        if (empty($data['openid'])) {
            if (!mac_verify_password($password_raw, $row['user_pwd'])) {
                // 风控日志（issue #149）：记录登录失败尝试的 IP/UA，用于关联封禁撞库/爆破
                \app\common\model\UserAccessLog::record('login_fail', [
                    'user_id' => 0,
                    'user_name' => isset($data['user_name']) ? $data['user_name'] : '',
                ]);
                return ['code' => 1003, 'msg' => lang('model/user/not_found')];
            }
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

        // 惰性迁移：旧 md5/明文哈希在登录成功后升级为 bcrypt。
        // 仅用户名/密码登录路径才迁移——openid 登录未校验密码，$password_raw 为空，
        // 若在此迁移会用空字符串 bcrypt 覆盖真实密码哈希，破坏密码登录。
        // 同上：列宽不足时跳过写回，避免升级窗口期 bcrypt 被截断锁死账号。
        if (empty($data['openid']) && mac_password_needs_rehash($row['user_pwd']) && mac_pwd_column_fits_hash('user', 'user_pwd')) {
            $update['user_pwd'] = mac_hash_password($password_raw);
        }

        $res = $this->where($where)->update($update);
        if ($res === false) {
            return ['code' => 1004, 'msg' => lang('model/user/update_login_err')];
        }

        // 风控日志（issue #149）：登录成功记录 IP/UA，用于关联封禁分析
        \app\common\model\UserAccessLog::record('login', ['user_id' => $row['user_id'], 'user_name' => $row['user_name']]);

        //用户组
        $group_list = model('Group')->getCache('group_list');
        $group_ids = explode(',', $row['group_id']);
        $group = [];
        foreach($group_ids as $gid){
            if(isset($group_list[$gid])){
                $group[] = $group_list[$gid];
            }
        }

        $setCookie = !isset($options['set_cookie']) || $options['set_cookie'];
        if ($setCookie) {
            cookie('user_id', $row['user_id'],['expire'=>2592000] );
            cookie('user_name', $row['user_name'],['expire'=>2592000] );
            cookie('group_id', $group[0]['group_id'],['expire'=>2592000] );
            cookie('group_name', $group[0]['group_name'],['expire'=>2592000] );
            cookie('user_check', md5($random . '-' .$row['user_name'] . '-' . $row['user_id'] .'-' ),['expire'=>2592000] );
            cookie('user_portrait', mac_get_user_portrait($row['user_id']),['expire'=>2592000] );
        }

        $out = ['code' => 1, 'msg' => lang('model/user/login_ok')];
        if (!empty($options['return_meta'])) {
            $out['meta'] = [
                'user_id'     => (int)$row['user_id'],
                'user_name'   => (string)$row['user_name'],
                'user_random' => (string)$random,
            ];
        }

        return $out;
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
        $jwt = JwtService::bearerFromRequest();
        if ($jwt !== '' && JwtService::isEnabled()) {
            $pl = JwtService::decodeAndVerify($jwt);
            if (!is_array($pl)) {
                return ['code' => 1003, 'msg' => lang('model/user/not_login')];
            }
            $uid = (int)($pl['sub'] ?? 0);
            $rnd = (string)($pl['rnd'] ?? '');
            if ($uid < 1 || $rnd === '') {
                return ['code' => 1003, 'msg' => lang('model/user/not_login')];
            }
            $whereJwt = ['user_id' => $uid, 'user_status' => 1];
            $rowJwt = $this->field('*')->where($whereJwt)->find();
            if (empty($rowJwt)) {
                return ['code' => 1002, 'msg' => lang('model/user/not_login')];
            }
            $info = $rowJwt->toArray();
            if (!isset($info['user_random']) || !hash_equals((string)$info['user_random'], $rnd)) {
                return ['code' => 1003, 'msg' => lang('model/user/not_login')];
            }

            return $this->finalizeUserLoginPayload($info, $whereJwt);
        }

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

        return $this->finalizeUserLoginPayload($info, $where);
    }

    /**
     * 组装已校验用户（Cookie 或 JWT）的会员组与过期处理。
     *
     * @param array $info        用户行
     * @param array $whereUpdate VIP 过期等更新时用于 where()
     *
     * @return array
     */
    private function finalizeUserLoginPayload(array $info, array $whereUpdate)
    {
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

            $res = $this->where($whereUpdate)->update($update);
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
        $update['user_pwd'] = mac_hash_password_for_column($password_raw, 'user', 'user_pwd');

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

    public function consumeDownQuota($user_id, $data)
    {
        $user_id = intval($user_id);
        if ($user_id < 1 || empty($data) || !is_array($data)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $quotaDeducted = false;
        Db::startTrans();
        try {
            $affected = Db::name('user')
                ->where('user_id', $user_id)
                ->where('user_down_quota', '>=', 1)
                ->setDec('user_down_quota', 1);
            if ($affected === 0 || $affected === false) {
                Db::rollback();
                return ['code' => 1005, 'msg' => lang('mall/download_quota_not_enough')];
            }

            $quotaDeducted = true;
            $data['user_id'] = $user_id;
            $data['ulog_time'] = time();
            // 额度路径消耗的是下载额度而非积分，ulog 记录积分价应清零，避免账目误解
            $data['ulog_points'] = 0;
            $insert = Db::name('ulog')->insert($data);
            if ($insert === false) {
                if ($quotaDeducted) {
                    Db::name('user')->where('user_id', $user_id)->setInc('user_down_quota', 1);
                }
                Db::rollback();
                return ['code' => 1006, 'msg' => lang('api/payment/operation_retry')];
            }

            Db::commit();
            return ['code' => 1, 'msg' => lang('mall/download_quota_used')];
        } catch (\Exception $e) {
            if ($quotaDeducted) {
                Db::name('user')->where('user_id', $user_id)->setInc('user_down_quota', 1);
            }
            Db::rollback();
            return ['code' => 1006, 'msg' => lang('api/payment/operation_retry')];
        }
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

        // 活动价优先：活动有效时用 group_activity_points_*，否则回退正常价
        $plan = model('Group')->vipPlanPrice($group_info, $long);
        $point = intval($plan['effective_points']);
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
        // 优先使用活动价快照的 effective_points，回退到 upgrade_points
        $point = intval($remarks['upgrade_effective_points'] ?? $remarks['upgrade_points'] ?? 0);
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
        $end_time = $this->calcVipEndTimeByUpgradeRule($user['user_end_time'], $sj);

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

    public function grantVipDays($user_id, $group_id, $days)
    {
        $user_id = intval($user_id);
        $group_id = intval($group_id);
        $days = intval($days);
        if ($user_id < 1 || $group_id < 3 || $days < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $group_list = (new Group())->getCache();
        if (!isset($group_list[$group_id]) || intval($group_list[$group_id]['group_status']) !== 1) {
            return ['code' => 1002, 'msg' => lang('model/user/group_not_found')];
        }

        $user = Db::name('User')->where('user_id', $user_id)->find();
        if (empty($user)) {
            return ['code' => 1003, 'msg' => lang('obtain_err')];
        }

        $end_time = $this->calcVipEndTimeByUpgradeRule($user['user_end_time'], $days * 86400);
        $res = Db::name('User')->where('user_id', $user_id)->update([
            'group_id' => $group_id,
            'user_end_time' => $end_time,
        ]);
        if ($res === false) {
            return ['code' => 1004, 'msg' => lang('model/user/update_group_err')];
        }

        return ['code' => 1, 'msg' => lang('model/user/update_group_ok'), 'info' => ['user_end_time' => $end_time, 'group_id' => $group_id, 'group_name' => $group_list[$group_id]['group_name'] ?? '']];
    }

    // Shared VIP extension rule used by upgradeByPaidOrder() and mall VIP exchange.
    private function calcVipEndTimeByUpgradeRule($current_end_time, $seconds)
    {
        $now = time();
        $base_time = intval($current_end_time) > $now ? intval($current_end_time) : $now;
        return $base_time + intval($seconds);
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
        $where['msg_to']   = ['eq', $param['to'] ];
        $where['msg_status'] = ['eq', 0];
        $res = model('msg')->infoData($where);
        if($res['code'] >1){
            return ['code'=>9002,'msg'=>lang('model/user/msg_not_found')];
        }
        return  ['code'=>1,'msg'=>'ok','msg_id'=>$res['info']['msg_id']];
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
        // 重新鉴权：变更绑定邮箱/手机前要求当前密码
        $pwd = isset($param['user_pwd']) ? trim($param['user_pwd']) : '';
        $pwdErr = $this->verifyCurrentPassword($pwd);
        if ($pwdErr !== null) {
            return $pwdErr;
        }

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
        // 重新鉴权：解绑邮箱/手机前要求当前密码
        $pwd = isset($param['user_pwd']) ? trim($param['user_pwd']) : '';
        $pwdErr = $this->verifyCurrentPassword($pwd);
        if ($pwdErr !== null) {
            return $pwdErr;
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
        $to_src = trim(isset($param['user_email']) ? $param['user_email'] : '');
        if($to_src === ''){
            $to_src = trim(isset($param['to']) ? $param['to'] : '');
        }
        $to = htmlspecialchars(urldecode($to_src));

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
        $param['to']   = $to_src;
        $res = $this->check_msg($param);
        if($res['code'] >1){
            return ['code'=>$res['code'],'msg'=>$res['msg']];
        }
        $msg_id = $res['msg_id'];

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

        // 原子作废验证码，防重放：仅当仍为未使用(0)时置为已使用(1)，避免并发重复重置
        $consumed = Db::name('msg')->where(['msg_id'=>$msg_id,'msg_status'=>0])->update(['msg_status'=>1]);
        if($consumed != 1){
            return ['code'=>9002,'msg'=>lang('model/user/msg_not_found')];
        }

        $update = [];
        $update['user_pwd'] = mac_hash_password_for_column($password_raw, 'user', 'user_pwd');
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

    public function reward($fee_points=0, $strict=false)
    {
        //三级分销
        // $strict=true（购买交易）：任一必要写入失败即返回错误码，交由调用方回滚整笔事务，
        //   避免「只给上级加了积分却漏记积分日志」的账目黑洞。
        // ★ 前置约束：$strict=true 必须在 Db::startTrans() 事务内调用。本方法自身只返回错误码、
        //   不会 rollback；strict 下若一级上级 setInc 已成功而随后 Plog::saveData 失败，会带着
        //   「已加分未记账」的中间状态返回，须由调用方 rollback 撤销（否则残留半付的分销链）。
        //   当前 strict=true 的调用方仅两处，且均在 Db::startTrans() 事务内：
        //   api/controller/Payment.php::buy_popedom() 与 index/controller/User.php::ajax_buy_popedom()。
        //   本类 upgrade() 等升级/充值流程走 $strict=false（默认），不受此约束；事务外调用请勿传 strict=true。
        // $strict=false（默认，兼容既有会员升级/充值等流程）：三级分销为尽力而为的附带奖励，
        //   内部失败自行吞掉、不阻断主流程，保持原有语义。
        if($fee_points>0 && $GLOBALS['config']['user']['reward_status'] == '1'){
            $levels = [
                ['ratio' => 'reward_ratio',   'pid' => 'user_pid',   'type' => 4],
                ['ratio' => 'reward_ratio_2', 'pid' => 'user_pid_2', 'type' => 5],
                ['ratio' => 'reward_ratio_3', 'pid' => 'user_pid_3', 'type' => 6],
            ];
            foreach ($levels as $lv) {
                if (empty($GLOBALS['config']['user'][$lv['ratio']]) || empty($GLOBALS['user'][$lv['pid']])) {
                    continue;
                }
                $points = floor($fee_points / 100 * $GLOBALS['config']['user'][$lv['ratio']]);
                if ($points <= 0) {
                    continue;
                }
                $where = ['user_id' => $GLOBALS['user'][$lv['pid']]];
                $r = model('User')->where($where)->setInc('user_points', $points);
                // setInc 返回 false 才是写库失败；受影响 0 行代表上级用户不存在（配置层面问题），
                // 非交易故障，strict 下也不应连累买家的正常交易，故仅跳过、不判失败。
                if ($r === false) {
                    if ($strict) {
                        return ['code'=>1101,'msg'=>lang('save_err')];
                    }
                    continue;
                }
                if ($r) {
                    $data = [];
                    $data['user_id'] = $GLOBALS['user'][$lv['pid']];
                    $data['plog_type'] = $lv['type'];
                    $data['plog_points'] = $points;
                    $data['plog_remarks'] = lang('model/user/reward_tip',[$GLOBALS['user']['user_id'],$GLOBALS['user']['user_name'],$fee_points,$points]);
                    $plogRes = model('Plog')->saveData($data);
                    if ($strict && (!is_array($plogRes) || intval(isset($plogRes['code']) ? $plogRes['code'] : 0) !== 1)) {
                        return ['code'=>1102,'msg'=>lang('save_err')];
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
