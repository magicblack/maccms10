<?php

namespace app\api\controller;

use app\common\util\CsrfGuard;
use think\Db;
use think\Request;
use think\Url;

class User extends Base
{
    use PublicApi;
    use CsrfGuard;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();

    }

    public function index()
    {

    }

    /**
     * 获取当前登录用户的邀请码及邀请信息
     * 需要用户已登录（通过Cookie）
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_my_invite(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json([
                'code' => 1401,
                'msg'  => lang('api/please_login_first'),
            ]);
        }

        $user_id = intval($check['info']['user_id']);

        $user = Db::name('User')
            ->field('user_id,user_name,user_nick_name,user_invite_code,user_invite_count,user_reg_time')
            ->where('user_id', $user_id)
            ->find();

        if (!$user) {
            return json([
                'code' => 1002,
                'msg'  => lang('api/user_not_found'),
            ]);
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $user,
        ]);
    }

    /**
     * 获取当前登录用户的邀请下线列表（含二级下线）
     * - 必须已登录；数据以会话用户为准
     * - 可选传入 user_id，须与会话用户一致（用于与 URL ?uid= 对齐）
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_invite_list(Request $request)
    {
        $param = $request->param();

        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_validate', [$validate->getError()]),
            ]);
        }

        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json([
                'code' => 1401,
                'msg'  => lang('api/please_login_first'),
            ]);
        }
        $user_id = intval($check['info']['user_id']);
        if (!empty($param['user_id']) && intval($param['user_id']) !== $user_id) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_uid_mismatch'),
            ]);
        }

        $page  = isset($param['page'])  ? max(1, intval($param['page']))          : 1;
        $limit = isset($param['limit']) ? min(100, max(1, intval($param['limit']))) : 20;

        $user = Db::name('User')
            ->field('user_id,user_name,user_nick_name,user_invite_code,user_invite_count,user_reg_time')
            ->where('user_id', $user_id)
            ->find();

        if (!$user) {
            return json([
                'code' => 1002,
                'msg'  => lang('api/user_not_found'),
            ]);
        }

        $total = Db::name('User')->where('user_pid', $user_id)->count();

        $invitees_raw = Db::name('User')
            ->field('user_id,user_name,user_nick_name,user_invite_code,user_invite_count,user_reg_time,user_pid')
            ->where('user_pid', $user_id)
            ->order('user_reg_time desc')
            ->page($page)
            ->limit($limit)
            ->select();

        $invitees = is_array($invitees_raw) ? $invitees_raw : (is_object($invitees_raw) ? $invitees_raw->toArray() : []);

        if (!empty($invitees)) {
            $level1_ids = array_column($invitees, 'user_id');

            $sub_list_raw = Db::name('User')
                ->field('user_id,user_name,user_nick_name,user_invite_code,user_invite_count,user_reg_time,user_pid')
                ->where('user_pid', 'in', $level1_ids)
                ->order('user_reg_time desc')
                ->select();

            $sub_list = is_array($sub_list_raw) ? $sub_list_raw : (is_object($sub_list_raw) ? $sub_list_raw->toArray() : []);

            $sub_map = [];
            foreach ($sub_list as $sub) {
                $sub_map[$sub['user_pid']][] = $sub;
            }

            foreach ($invitees as &$invitee) {
                $invitee['sub_invitees']       = isset($sub_map[$invitee['user_id']]) ? $sub_map[$invitee['user_id']] : [];
                $invitee['sub_invitees_count'] = count($invitee['sub_invitees']);
            }
            unset($invitee);
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'user'  => $user,
                'page'  => $page,
                'limit' => $limit,
                'total' => intval($total),
                'list'  => $invitees ?: [],
            ],
        ]);
    }

    /**
     *  获取用户列表
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        // 参数校验
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_validate', [$validate->getError()]),
            ]);
        }

        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;

        // 查询条件组装
        $where = [];

        if (isset($param['id'])) {
            $where['user_id'] = (int)$param['id'];
        }

        if (isset($param['group_id'])) {
            $where['group_id'] = (int)$param['group_id'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['user_reg_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['user_reg_time'] = ['<=', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['user_reg_time'] = ['>=', (int)$param['time_start']];
        }

        if (isset($param['phone']) && strlen($param['phone']) > 0) {
            $where['user_phone'] = ['like', '%' . $this->format_sql_string($param['phone']) . '%'];
        }

        if (isset($param['qq']) && strlen($param['qq']) > 0) {
            $where['user_qq'] = ['like', '%' . $this->format_sql_string($param['qq']) . '%'];
        }

        if (isset($param['email']) && strlen($param['email']) > 0) {
            $where['user_email'] = ['like', '%' . $this->format_sql_string($param['email']) . '%'];
        }

        if (isset($param['nickname']) && strlen($param['nickname']) > 0) {
            $where['user_nick_name'] = ['like', '%' . $this->format_sql_string($param['nickname']) . '%'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['user_name'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        // 数据获取
        $total = model('User')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "user_reg_time DESC";
            $field = 'user_id,user_name,user_nick_name,user_phone,user_reg_time';
            if (strlen($param['orderby']) > 0) {
                $order = 'user_' . $param['orderby'] . " DESC";
            }
            $list = model('User')->getListByCond($offset, $limit, $where, $order, $field, []);
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'offset' => $offset,
                'limit'  => $limit,
                'total'  => $total,
                'rows'   => $list,
            ],
        ]);
    }

    /**
     * 用户详细信息
     *
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_detail(Request $request)
    {
        // 参数校验
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_validate', [$validate->getError()]),
            ]);
        }

        $userId = (int)$param['id'];
        $result = Db::name('User')
            ->field(model('User')->publicApiDetailFields())
            ->where(['user_id' => $userId, 'user_status' => 1])
            ->find();
        if (empty($result)) {
            return json([
                'code' => 1002,
                'msg'  => lang('api/user_not_found'),
            ]);
        }
        $result['user_portrait'] = mac_get_user_portrait($userId);
        $result = model('User')->stripSensitiveFields($result);

        // 返回
        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $result
        ]);

    }

    /**
     * 登录/注册一体化
     * POST api.php/user/login_or_register
     * 参数: user_name, user_pwd, [invite_code]
     *
     * - 帐号存在 → 校验密码 → 登录
     * - 帐号不存在 → 自动创建帐号 → 登录
     * - 返回 action 字段标识本次操作是 "login" 还是 "register"
     */
    public function login_or_register(Request $request)
    {
        // IP 速率限制：防止暴力破解
        $rlCheck = $this->_checkLoginRateLimit();
        if ($rlCheck !== true) {
            return $rlCheck;
        }

        $param = $request->param();
        if (empty($param['user_name']) || empty($param['user_pwd'])) {
            return json(['code' => 1001, 'msg' => lang('api/user_name_pwd_empty')]);
        }

        $res = model('User')->loginOrRegister($param);

        if ($res['code'] > 1) {
            return json($res);
        }

        $info = $res['info'];
        return json([
            'code'   => 1,
            'msg'    => $res['msg'],
            'action' => $res['action'],  // "login" 或 "register"
            'info'   => [
                'user_id'        => $info['user_id'],
                'user_name'      => $info['user_name'],
                'user_nick_name' => $info['user_nick_name'],
                'user_email'     => $info['user_email'],
                'user_phone'     => $info['user_phone'],
                'group_id'       => $info['group_id'],
                'user_points'    => $info['user_points'],
                'user_exp'       => $info['user_exp'],
                'user_reg_time'  => $info['user_reg_time'],
                'user_portrait'  => mac_get_user_portrait($info['user_id']),
                'user_invite_code' => $info['user_invite_code'],
            ],
        ]);
    }

    /**
     * 用户登录
     * api.php/user/login (POST)
     * 参数: user_name, user_pwd, [type=name|email|phone]
     */
    public function login(Request $request)
    {
        // IP 速率限制：防止暴力破解
        $rlCheck = $this->_checkLoginRateLimit();
        if ($rlCheck !== true) {
            return $rlCheck;
        }

        $param = $request->param();
        if (empty($param['user_name']) || empty($param['user_pwd'])) {
            return json(['code' => 1001, 'msg' => lang('api/user_name_pwd_empty')]);
        }
        $res = model('User')->login(['user_name' => $param['user_name'], 'user_pwd' => $param['user_pwd']]);
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        return json(['code' => 1, 'msg' => lang('model/user/login_ok'), 'info' => [
            'user_id'       => $info['user_id'],
            'user_name'     => $info['user_name'],
            'user_nick_name'=> $info['user_nick_name'],
            'user_email'    => $info['user_email'],
            'user_phone'    => $info['user_phone'],
            'group_id'      => $info['group_id'],
            'user_points'   => $info['user_points'],
            'user_exp'      => $info['user_exp'],
            'user_reg_time' => $info['user_reg_time'],
            'user_portrait' => mac_get_user_portrait($info['user_id']),
        ]]);
    }

    /**
     * 用户注册
     * api.php/user/register (POST)
     * 参数: user_name, user_pwd, user_pwd2, [user_email, user_phone, invite_code]
     */
    public function register(Request $request)
    {
        $param = $request->param();
        if (empty($param['user_name']) || empty($param['user_pwd']) || empty($param['user_pwd2'])) {
            return json(['code' => 1002, 'msg' => lang('model/user/input_require')]);
        }
        $res = model('User')->register($param);
        return json($res);
    }

    /**
     * 用户登出
     * api.php/user/logout
     */
    public function logout(Request $request)
    {
        cookie('user_id', null);
        cookie('user_name', null);
        cookie('user_pwd', null);
        cookie('user_token', null);
        session(null);
        return json(['code' => 1, 'msg' => lang('api/logged_out')]);
    }

    /**
     * 获取当前登录用户信息
     * api.php/user/get_info
     */
    public function get_info(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $info = Db::name('User')
            ->field('user_id,user_name,user_nick_name,user_email,user_phone,user_qq,group_id,user_points,user_exp,user_integral,user_invite_code,user_invite_count,user_reg_time,user_status')
            ->where('user_id', $uid)->find();
        if (!$info) return json(['code' => 1002, 'msg' => lang('api/user_not_found')]);
        $info['user_portrait'] = mac_get_user_portrait($uid);
        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info]);
    }

    /**
     * 获取 Web Push 公钥与开关状态（前端订阅前调用）
     * api.php/user/push_vapid (GET)
     * 返回 enabled 与 vapid_public，供 PushManager.subscribe 使用。
     */
    public function push_vapid(Request $request)
    {
        $cfg = config('maccms.push');
        $enabled = (is_array($cfg) && !empty($cfg['enable']) && !empty($cfg['vapid_public'])) ? 1 : 0;
        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'enabled'      => $enabled,
                'vapid_public' => $enabled ? (string)$cfg['vapid_public'] : '',
            ],
        ]);
    }

    /**
     * 保存 Web Push 订阅（需登录）
     * api.php/user/push_subscribe (POST)
     * 参数：endpoint, keys[p256dh], keys[auth]（或平铺 p256dh/auth）
     */
    public function push_subscribe(Request $request)
    {
        $cfg = config('maccms.push');
        if (!is_array($cfg) || empty($cfg['enable'])) {
            return json(['code' => 1003, 'msg' => lang('api/push_disabled')]);
        }
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);

        // 与其他写接口一致：登录态写操作需校验 CSRF，防止跨站伪造订阅
        $csrfErr = $this->checkCsrf();
        if ($csrfErr !== null) return json($csrfErr);

        // 限流：抑制刷订阅写库（每分钟 20 次）
        $limited = $this->apiRateLimit('push_subscribe', $uid, 20, 60);
        if ($limited !== true) {
            return $limited;
        }

        $param = $request->param();
        $endpoint = isset($param['endpoint']) ? trim((string)$param['endpoint']) : '';
        $p256dh = '';
        $auth = '';
        if (isset($param['keys']) && is_array($param['keys'])) {
            $p256dh = isset($param['keys']['p256dh']) ? trim((string)$param['keys']['p256dh']) : '';
            $auth   = isset($param['keys']['auth']) ? trim((string)$param['keys']['auth']) : '';
        }
        if ($p256dh === '' && isset($param['p256dh'])) $p256dh = trim((string)$param['p256dh']);
        if ($auth === '' && isset($param['auth'])) $auth = trim((string)$param['auth']);

        $res = model('PushSubscription')->saveSubscription([
            'user_id'    => $uid,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'user_agent' => (string)$request->header('user-agent'),
        ]);
        if ($res['code'] != 1) {
            return json(['code' => 1001, 'msg' => $res['msg']]);
        }
        return json(['code' => 1, 'msg' => $res['msg']]);
    }

    /**
     * 取消 Web Push 订阅（需登录）
     * api.php/user/push_unsubscribe (POST)
     * 参数：endpoint
     */
    public function push_unsubscribe(Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);

        // 与其他写接口一致：登录态写操作需校验 CSRF
        $csrfErr = $this->checkCsrf();
        if ($csrfErr !== null) return json($csrfErr);

        $endpoint = trim((string)$request->param('endpoint', ''));
        if ($endpoint === '') {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        // 显式传入 $uid：仅退订当前登录用户自己的订阅，避免越权删除他人订阅
        $res = model('PushSubscription')->deleteByEndpoint($endpoint, $uid);
        return json(['code' => 1, 'msg' => $res['msg']]);
    }


    /**
     * 更新用户资料
     * api.php/user/update_info (POST)
     * 参数: [user_nick_name, user_email, user_phone, user_qq, user_old_pwd, user_new_pwd]
     */
    public function update_info(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $update = [];
        if (!empty($param['user_nick_name'])) $update['user_nick_name'] = htmlspecialchars(trim($param['user_nick_name']));
        if (!empty($param['user_email'])) $update['user_email'] = htmlspecialchars(trim($param['user_email']));
        if (!empty($param['user_phone'])) $update['user_phone'] = htmlspecialchars(trim($param['user_phone']));
        if (!empty($param['user_qq'])) $update['user_qq'] = htmlspecialchars(trim($param['user_qq']));
        // 修改密码（使用 bcrypt，与 model 层 User::saveData / login / register 保持一致）
        if (!empty($param['user_new_pwd']) && !empty($param['user_old_pwd'])) {
            $userInfo = Db::name('User')->field('user_pwd')->where('user_id', $uid)->find();
            if (!mac_verify_password($param['user_old_pwd'], $userInfo['user_pwd'])) {
                return json(['code' => 1012, 'msg' => lang('model/user/old_pass_err')]);
            }
            $update['user_pwd'] = mac_hash_password_for_column($param['user_new_pwd'], 'user', 'user_pwd');
        }
        if (empty($update)) return json(['code' => 1001, 'msg' => lang('api/no_update_needed')]);
        Db::name('User')->where('user_id', $uid)->update($update);
        return json(['code' => 1, 'msg' => lang('update_ok')]);
    }

    /**
     * 获取用户行为日志（收藏/历史/想看/播放/下载）
     * api.php/user/get_ulog?type=2&page=1&limit=20
     * type: 1=浏览 2=收藏 3=想看 4=播放 5=下载
     */
    public function get_ulog(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $page = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));
        $where = ['user_id' => $uid];
        if (!empty($param['type'])) $where['ulog_type'] = intval($param['type']);
        if (!empty($param['mid'])) $where['ulog_mid'] = intval($param['mid']);
        $order = 'ulog_time desc';
        $res = model('Ulog')->listData($where, $order, $page, $limit);
        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $res]);
    }

    /**
     * 添加/更新用户行为日志（收藏/播放记录等）
     * api.php/user/add_ulog (POST)
     * 参数: mid, rid, type, [sid=0, nid=0]
     */
    public function add_ulog(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $data = [
            'ulog_mid'  => intval($param['mid'] ?? 0),
            'ulog_rid'  => intval($param['rid'] ?? 0),
            'ulog_type' => intval($param['type'] ?? 0),
            'ulog_sid'  => intval($param['sid'] ?? 0),
            'ulog_nid'  => intval($param['nid'] ?? 0),
            'user_id'   => $uid,
        ];
        if ($data['ulog_mid'] < 1 || $data['ulog_rid'] < 1 || $data['ulog_type'] < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $data['ulog_points'] = 0;
        // 视频播放/下载：按真实定价核算，禁止伪造 0 分购买记录
        if ($data['ulog_mid'] == 1 && $data['ulog_type'] > 3) {
            $where2 = [];
            $where2['vod_id'] = $data['ulog_rid'];
            $res = model('Vod')->infoData($where2);
            if ($res['code'] > 1) {
                return json($res);
            }
            $flag = $data['ulog_type'] == 4 ? 'play' : 'down';
            $data['ulog_points'] = $res['info']['vod_points_' . $flag];
        }
        $data['ulog_points'] = intval($data['ulog_points']);
        $__ualMap = [2 => 'fav', 3 => 'want', 4 => 'play', 5 => 'down'];
        // 已存在则更新时间
        $existing = model('Ulog')->infoData($data);
        if ($existing['code'] == 1) {
            $updated = model('Ulog')->where($data)->update(['ulog_time' => time()]);
            // update() 返回 false 才是写库失败；受影响行数为 0（时间戳值未变）仍算成功。失败不埋点。
            if ($updated !== false && isset($__ualMap[$data['ulog_type']])) {
                // 只有原有行为日志更新成功后才记录对应风控行为。
                \app\common\model\UserAccessLog::record($__ualMap[$data['ulog_type']], [
                    'user_id' => $uid,
                    'mid'     => $data['ulog_mid'],
                    'rid'     => $data['ulog_rid'],
                ]);
            }
            return json(['code' => 1, 'msg' => lang('update_ok')]);
        }
        if ($data['ulog_points'] > 0) {
            return json(['code' => 2001, 'msg' => lang('index/ulog_fee')]);
        }
        $res = model('Ulog')->saveData($data);
        if (isset($res['code']) && intval($res['code']) === 1 && isset($__ualMap[$data['ulog_type']])) {
            // 付费播放/下载在上方已被拦截；走到这里代表行为日志确实写入成功。
            \app\common\model\UserAccessLog::record($__ualMap[$data['ulog_type']], [
                'user_id' => $uid,
                'mid'     => $data['ulog_mid'],
                'rid'     => $data['ulog_rid'],
            ]);
        }
        return json($res);
    }

    /**
     * 删除用户行为日志（取消收藏/删除历史等）
     * api.php/user/del_ulog (POST)
     * 参数: ids=1,2,3 或 ulog_id=1
     */
    public function del_ulog(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        // 清空某类日志：all=1 且 type=1..5（与 index user/ulog_del 一致）
        if (!empty($param['all']) && (string)$param['all'] === '1') {
            $type = isset($param['type']) ? (string)$param['type'] : '';
            if (!in_array($type, ['1', '2', '3', '4', '5'], true)) {
                return json(['code' => 1001, 'msg' => lang('api/param_type_required')]);
            }
            $where = ['user_id' => $uid, 'ulog_type' => intval($type)];
            $return = model('Ulog')->delData($where);
            return json($return);
        }
        $ids = [];
        if (!empty($param['ids'])) {
            $ids = array_filter(array_map('intval', explode(',', $param['ids'])));
        } elseif (!empty($param['ulog_id'])) {
            $ids = [intval($param['ulog_id'])];
        }
        if (empty($ids)) {
            return json(['code' => 1001, 'msg' => lang('api/param_ids_required')]);
        }
        Db::name('ulog')->where(['user_id' => $uid, 'ulog_id' => ['in', $ids]])->delete();
        return json(['code' => 1, 'msg' => lang('del_ok')]);
    }

    /**
     * 获取积分日志
     * api.php/user/get_plog?page=1&limit=20&filter=income|expense（可选，与 index user/plog 一致）
     */
    public function get_plog(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $page = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));
        $where = ['user_id' => $uid];
        $filter = isset($param['filter']) ? trim($param['filter']) : '';
        if ($filter === 'income') {
            $where['plog_type'] = ['in', [1, 2, 3, 4, 5, 6, 10, 11]];
        } elseif ($filter === 'expense') {
            $where['plog_type'] = ['in', [7, 8, 9]];
        }
        $order = 'plog_id desc';
        $res = model('Plog')->listData($where, $order, $page, $limit);
        $list = isset($res['list']) ? $res['list'] : [];
        $out = [];
        foreach ($list as $row) {
            $r = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : []);
            $pt = isset($r['plog_type']) ? intval($r['plog_type']) : 0;
            $r['plog_type_text'] = ($pt >= 1 && $pt <= 11) ? mac_get_plog_type_text($pt) : '';
            $r['order_status'] = isset($r['order_status']) ? intval($r['order_status']) : 0;
            $out[] = $r;
        }
        $list = $out;
        $res['list'] = $list;
        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => [
            'page' => $res['page'],
            'limit' => $res['limit'],
            'total' => intval($res['total']),
            'pagecount' => intval($res['pagecount']),
            'list' => $list,
            'rows' => $list,
        ]]);
    }

    /**
     * 删除积分日志
     * api.php/user/del_plog (POST) 参数同 index user/plog_del：ids、all=1 清空
     */
    public function del_plog(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->post();
        $idsRaw = isset($param['ids']) ? htmlspecialchars(urldecode(trim($param['ids']))) : '';
        $all = isset($param['all']) ? $param['all'] : '';
        if (empty($idsRaw) && empty($all)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        $where = ['user_id' => $uid];
        if ((string)$all !== '1') {
            $arr = [];
            foreach (explode(',', $idsRaw) as $v) {
                $v = abs(intval($v));
                if ($v > 0) {
                    $arr[$v] = $v;
                }
            }
            if (empty($arr)) {
                return json(['code' => 1001, 'msg' => lang('param_err')]);
            }
            $where['plog_id'] = ['in', array_values($arr)];
        }
        $return = model('Plog')->delData($where);
        return json($return);
    }

    /**
     * 当前用户充值订单列表（与 index user/orders 数据源一致）
     * api.php/user/get_orders?page=1&limit=20
     */
    public function get_orders(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $page = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));
        $where = ['o.user_id' => $uid];
        $order = 'o.order_id desc';
        $res = model('Order')->listData($where, $order, $page, $limit);
        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $res]);
    }

    /**
     * 找回密码 - 发送验证码
     * api.php/user/find_password (POST)
     * 参数: user_email 或 user_phone
     */
    public function find_password(Request $request)
    {
        $param = $request->param();
        if (empty($param['user_email']) && empty($param['user_phone'])) {
            return json(['code' => 1001, 'msg' => lang('api/findpass_need_contact')]);
        }
        $res = model('User')->reg_msg($param);
        return json($res);
    }

    /**
     * 批量检查用户收藏状态
     * 对应首页 Banner 区的收藏按钮，判断用户是否已收藏某些影片
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   vod_ids  - 必须，影片ID列表，多个用逗号分隔，如 "1,2,3"
     *   mid      - 可选，模型ID，默认1(视频)，2=文章，3=专题，8=明星
     *   ulog_type - 可选，日志类型，默认2(收藏)
     */
    public function get_favorites_status(Request $request)
    {
        // 需要用户登录
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json([
                'code' => 1401,
                'msg'  => lang('api/please_login_first'),
            ]);
        }

        $user_id = intval($check['info']['user_id']);
        $param = $request->param();

        if (empty($param['vod_ids'])) {
            return json(['code' => 1001, 'msg' => lang('api/param_vod_ids_required')]);
        }

        $vodIds = array_map('intval', explode(',', $param['vod_ids']));
        $mid = isset($param['mid']) ? (int)$param['mid'] : 1;
        $ulogType = isset($param['ulog_type']) ? (int)$param['ulog_type'] : 2;

        // 查询该用户对应 mid 和 type 的收藏记录
        $where = [
            'user_id'   => $user_id,
            'ulog_mid'  => $mid,
            'ulog_type' => $ulogType,
            'ulog_rid'  => ['in', $vodIds],
        ];

        $favorites = Db::name('ulog')
            ->field('ulog_id,ulog_rid')
            ->where($where)
            ->select();

        // 构建 rid => ulog_id 的映射
        $favMap = [];
        foreach ($favorites as $fav) {
            $favMap[$fav['ulog_rid']] = $fav['ulog_id'];
        }

        // 返回每个 vod_id 的收藏状态
        $result = [];
        foreach ($vodIds as $id) {
            $result[] = [
                'rid'      => $id,
                'is_fav'   => isset($favMap[$id]) ? 1 : 0,
                'ulog_id'  => isset($favMap[$id]) ? $favMap[$id] : 0,
            ];
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'total' => count($result),
                'rows'  => $result,
            ],
        ]);
    }

    /**
     * 获取分销推广下线列表
     * GET api.php/user/get_reward_list?level=1&page=1&limit=20
     *
     * @param  level  int  可选，下线层级：1=一级(默认), 2=二级, 3=三级
     * @param  page   int  可选，页码，默认1
     * @param  limit  int  可选，每页条数，默认20，最大100
     * @return JSON   {code:1, msg:'获取成功', info:{page, pagecount, limit, total, list:[...]}}
     */
    public function get_reward_list(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        }
        $uid = intval($check['info']['user_id']);

        $param = $request->param();
        $page  = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));
        $level = intval($param['level'] ?? 1);

        $where = [];
        if ($level == 2) {
            $where['user_pid_2'] = ['eq', $uid];
        } elseif ($level == 3) {
            $where['user_pid_3'] = ['eq', $uid];
        } else {
            $where['user_pid'] = ['eq', $uid];
        }

        $order = 'user_id desc';
        $res   = model('User')->listData($where, $order, $page, $limit);

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $res,
        ]);
    }

    /**
     * 充值/升级合并页：升级区数据 JSON（会员信息 + 可购套餐）
     * GET api.php/user/ajax_upgrade_data
     */
    public function ajax_upgrade_data(Request $request)
    {
        if ($request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $group_list = model('Group')->getCache();
        $scale = max(1, intval($GLOBALS['config']['pay']['scale']));
        $groups = [];
        $now = time();
        foreach ($group_list as $vo) {
            if (!is_array($vo)) {
                continue;
            }
            if (intval($vo['group_id']) <= 2 || intval($vo['group_status']) !== 1) {
                continue;
            }
            $gid = intval($vo['group_id']);
            $row = [
                'group_id' => $gid,
                'group_name' => (string)$vo['group_name'],
            ];
            foreach (['day', 'week', 'month', 'year'] as $pl) {
                $plan = model('Group')->vipPlanPrice($vo, $pl);
                $effPoints = intval($plan['effective_points']);
                $row['group_points_' . $pl] = $effPoints;
                $row['price_' . $pl] = round($effPoints / $scale, 2);
                // 活动倒计时：活动有效时返回剩余秒数，否则 0
                $countdown = 0;
                $startKey = 'group_activity_start_time_' . $pl;
                $endKey = 'group_activity_end_time_' . $pl;
                $start = intval($vo[$startKey] ?? 0);
                $end = intval($vo[$endKey] ?? 0);
                if (intval($plan['is_activity']) === 1 && $end > $now) {
                    $countdown = $end - $now;
                }
                $row['activity_countdown_' . $pl] = $countdown;
                $row['is_activity_' . $pl] = intval($plan['is_activity']);
            }
            $groups[] = $row;
        }

        $u = $GLOBALS['user'];
        $uid = intval($u['user_id'] ?? 0);
        $gidUser = intval($u['group_id'] ?? 1);

        if ($uid < 1) {
            $expireMode = 'guest';
            $expireDate = null;
            $memberMode = 'guest';
            $memberName = null;
        } else {
            if ($gidUser < 3) {
                $expireMode = 'permanent';
                $expireDate = null;
            } else {
                $expireMode = 'date';
                $expireDate = (string)mac_day($u['user_end_time'] ?? '');
            }
            $memberMode = 'member';
            $glName = (string)(($u['group']['group_name'] ?? '') ?: ($group_list[$gidUser]['group_name'] ?? ''));
            $memberName = $glName;
        }

        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'is_login' => $uid > 0,
                'user_points' => intval($u['user_points'] ?? 0),
                'user_group_id' => $gidUser,
                'expire_mode' => $expireMode,
                'expire_date' => $expireDate,
                'member_mode' => $memberMode,
                'member_name' => $memberName,
                'groups' => $groups,
            ],
        ]);
    }

    /**
     * 会员现金升级：创建 UPG 订单
     * POST api.php/user/upgrade_order_create  参数 group_id, long
     */
    public function upgrade_order_create(Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        if ($GLOBALS['user']['user_id'] < 1) {
            return json(['code' => 1002, 'msg' => lang('index/not_login')]);
        }

        $csrfErr = $this->checkCsrf();
        if ($csrfErr !== null) return json($csrfErr);

        $limited = $this->apiRateLimit('upgrade_order_create', intval($GLOBALS['user']['user_id']), 20, 60);
        if ($limited !== true) {
            return $limited;
        }

        $param = $request->param();
        $group_id = intval($param['group_id'] ?? 0);
        $long = trim((string)($param['long'] ?? ''));
        $points_long = ['day' => 86400, 'week' => 86400 * 7, 'month' => 86400 * 30, 'year' => 86400 * 365];
        if (!array_key_exists($long, $points_long) || $group_id < 3) {
            return json(['code' => 1003, 'msg' => lang('param_err')]);
        }

        $group_list = model('Group')->getCache();
        if (!isset($group_list[$group_id])) {
            return json(['code' => 1004, 'msg' => lang('model/user/group_not_found')]);
        }
        $group_info = $group_list[$group_id];
        if (empty($group_info) || intval($group_info['group_status']) !== 1) {
            return json(['code' => 1004, 'msg' => lang('model/user/group_not_found')]);
        }

        // 活动价优先：active 时覆盖正常套餐积分
        $plan = model('Group')->vipPlanPrice($group_info, $long);
        $point = intval($plan['effective_points']);
        $isActivity = intval($plan['is_activity']);
        if ($point < 1) {
            return json(['code' => 1005, 'msg' => lang('api/plan_not_available')]);
        }
        $scale = max(1, intval($GLOBALS['config']['pay']['scale']));
        $price = round($point / $scale, 2);
        $normalPoint = intval($group_info['group_points_' . $long]);

        // 优惠券抵扣（可选）
        $couponUserId = intval($param['coupon_user_id'] ?? 0);
        $priced = model('Coupon')->calculateOrderPrice('vip', $price, [
            'user_id'        => intval($GLOBALS['user']['user_id']),
            'coupon_user_id' => $couponUserId,
            'group_id'       => $group_id,
            'long'           => $long,
        ]);
        if ($priced['code'] > 1) {
            return json($priced);
        }
        $payPrice = floatval($priced['info']['pay_price']);

        $remarks = [
            'biz' => 'member_upgrade',
            'group_id' => $group_id,
            'group_name' => $group_info['group_name'],
            'long' => $long,
            'upgrade_points' => $normalPoint,
            'upgrade_effective_points' => $point,
            'is_activity' => $isActivity,
            'coupon' => ['coupon_user_id' => $couponUserId],
            'pricing' => [
                'scene' => 'vip',
                'original_price' => $priced['info']['original_price'],
                'coupon_discount' => $priced['info']['coupon_discount'],
                'pay_price' => $priced['info']['pay_price'],
            ],
        ];

        $data = [];
        $data['user_id'] = intval($GLOBALS['user']['user_id']);
        $data['order_code'] = 'UPG' . mac_get_uniqid_code();
        $data['order_price'] = $payPrice;
        $data['order_points'] = $point;
        $data['order_remarks'] = json_encode($remarks, JSON_UNESCAPED_UNICODE);

        $res = model('Order')->saveData($data);
        if ($res['code'] > 1) {
            return json($res);
        }

        // 预占券到本订单
        if ($couponUserId > 0) {
            $orderId = intval(\think\Db::name('Order')->where('order_code', $data['order_code'])->value('order_id'));
            $resv = model('CouponUser')->reserveForOrder($couponUserId, intval($GLOBALS['user']['user_id']), $orderId, $data['order_code']);
            if ($resv['code'] > 1) {
                // 预占失败：清理刚创建的废弃订单，避免污染用户订单列表
                \think\Db::name('Order')->where('order_code', $data['order_code'])->delete();
                return json($resv);
            }
        }

        $this_order = model('Order')->infoData(['order_code' => $data['order_code'], 'user_id' => $data['user_id']]);
        if ($this_order['code'] > 1) {
            return json($this_order);
        }

        $pay_url = Url::build('index/user/pay', ['order_code' => $data['order_code']]);

        return json([
            'code' => 1,
            'msg' => lang('save_ok'),
            'data' => [
                'order_id' => intval($this_order['info']['order_id']),
                'order_code' => $data['order_code'],
                'order_price' => $payPrice,
                'order_points' => $point,
                'pay_url' => $pay_url,
            ],
        ]);
    }

    /**
     * 登录接口 IP 速率限制：每个 IP 每 60 秒最多 10 次登录尝试
     * 使用 cache() 实现，与 UEditor AI 限流方案一致
     *
     * @return true|\think\response\Json  通过返回 true，被限制返回 JSON 响应
     */
    private function _checkLoginRateLimit()
    {
        $ip = mac_get_ip_long();
        $key = 'api_login_rl_' . $ip;
        $limit = 10;   // 每窗口最大尝试次数
        $window = 60;  // 窗口时间（秒）

        $count = (int) cache($key);
        if ($count >= $limit) {
            return json(['code' => 1020, 'msg' => lang('api/login_rate_limited', [(string) $window])]);
        }
        if ($count === 0) {
            cache($key, 1, $window);
        } else {
            cache($key, $count + 1, $window);
        }
        return true;
    }

    /**
     * 获取站内通知列表
     * api.php/user/get_notify_list?page=1&limit=20&type=order|vip|...
     */
    public function get_notify_list(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->param();
        $page = max(1, intval($param['page'] ?? 1));
        $limit = max(1, min(100, intval($param['limit'] ?? 20)));
        $model = model('Notify');
        $type = isset($param['type']) ? trim($param['type']) : '';
        $res = $model->listForUser($uid, $type, $page, $limit);
        if ($res['code'] > 1) return json($res);
        $unread = $model->countUnread($uid);

        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => [
            'page' => intval($res['page']),
            'pagecount' => intval($res['pagecount']),
            'limit' => intval($res['limit']),
            'total' => intval($res['total']),
            'unread' => intval($unread),
            'list' => $res['list'],
            'rows' => $res['list'],
        ]]);
    }

    /**
     * 获取未读通知数
     * api.php/user/get_notify_unread
     */
    public function get_notify_unread(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $count = model('Notify')->countUnread(intval($check['info']['user_id']));
        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => ['unread' => intval($count)]]);
    }

    /**
     * 标记通知已读
     * api.php/user/read_notify (POST) ids=1,2,3 或 all=1 全部已读
     */
    public function read_notify(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->post();
        $all = isset($param['all']) ? $param['all'] : '';
        if ((string)$all === '1') {
            return json(model('Notify')->markAllRead($uid));
        }
        $idsRaw = isset($param['ids']) ? htmlspecialchars(urldecode(trim($param['ids']))) : '';
        $ids = [];
        foreach (explode(',', $idsRaw) as $v) {
            $v = abs(intval($v));
            if ($v > 0) $ids[$v] = $v;
        }
        if (empty($ids)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        return json(model('Notify')->markRead($uid, array_values($ids)));
    }

    /**
     * 删除站内通知
     * api.php/user/del_notify (POST) ids=1,2,3
     */
    public function del_notify(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        $uid = intval($check['info']['user_id']);
        $param = $request->post();
        $idsRaw = isset($param['ids']) ? htmlspecialchars(urldecode(trim($param['ids']))) : '';
        $ids = [];
        foreach (explode(',', $idsRaw) as $v) {
            $v = abs(intval($v));
            if ($v > 0) $ids[$v] = $v;
        }
        if (empty($ids)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }
        return json(model('Notify')->deleteForUser($uid, array_values($ids)));
    }
}
