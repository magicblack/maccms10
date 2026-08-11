<?php
namespace app\admin\controller;

use think\Db;

/**
 * 用户访问风控日志（mac_user_access_log）后台查看。
 *
 * 与「访问日志(Ulog / mac_ulog)」刻意分表：mac_ulog 是收藏/想看/进度/购买凭证的
 * 多语意合表，掺入随请求变化的 IP/UA 会破坏其去重与「是否已购」判定。此处只做
 * 「查看 / 删除 / 关联研判 / 关联封禁」，不改动写入(UserAccessLog::record())与清理定时任务。
 */
class UserAccessLog extends Base
{
    /** 与 model 白名单保持一致，供筛选下拉使用 */
    private $actions = [
        'login', 'login_fail', 'register', 'play', 'down',
        'fav', 'want', 'buy', 'api_token', 'comment',
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 風控日誌的破壞性操作即使 security_csrf_admin 關閉也必須有 CSRF 保護。
     * 優先接受穩定 admin_csrf，並兼容升級前頁面使用的 Session __token__。
     */
    private function checkAdminCsrf()
    {
        $post = request()->post();
        $bodyToken = isset($post['__token__']) && is_string($post['__token__'])
            ? $post['__token__'] : '';
        $headerToken = (string)request()->header('X-CSRF-Token');
        $stable = function_exists('mac_admin_csrf_token')
            ? (string)mac_admin_csrf_token() : (string)\think\Session::get('admin_csrf');
        $legacy = \think\Session::has('__token__') ? (string)\think\Session::get('__token__') : '';

        $bodyOk = ($bodyToken !== '' && $stable !== '' && hash_equals($stable, $bodyToken))
            || ($bodyToken !== '' && $legacy !== '' && hash_equals($legacy, $bodyToken));
        $headerOk = ($headerToken !== '' && $stable !== '' && hash_equals($stable, $headerToken))
            || ($headerToken !== '' && $legacy !== '' && hash_equals($legacy, $headerToken));
        if (!$bodyOk && !$headerOk) {
            return false;
        }

        // 穩定 token 不消耗；只有真正使用 legacy token 時才清理舊 Session 值。
        if ($legacy !== '' && (($bodyToken !== '' && hash_equals($legacy, $bodyToken))
            || ($headerToken !== '' && hash_equals($legacy, $headerToken)))) {
            \think\Session::delete('__token__');
        }
        return true;
    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : intval($param['limit']);

        $where = [];
        if (isset($param['uid']) && $param['uid'] !== '') {
            $where['user_id'] = ['eq', intval($param['uid'])];
        }
        if (!empty($param['action']) && in_array($param['action'], $this->actions, true)) {
            $where['log_action'] = ['eq', $param['action']];
        }
        if (!empty($param['ip'])) {
            $ip = trim((string)$param['ip']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $ip) . '%';
            $where['log_ip'] = ['like', $like];
        }
        if (!empty($param['wd'])) {
            $wd = trim((string)$param['wd']);
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $wd) . '%';
            $where['user_name'] = ['like', $like];
        }
        if (isset($param['anonymized']) && in_array((string)$param['anonymized'], ['0', '1'], true)) {
            $where['log_anonymized'] = ['eq', intval($param['anonymized'])];
        }

        $order = 'log_id desc';
        $res = model('UserAccessLog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $this->assign('actions', $this->actions);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('admin/useraccesslog/title'));

        return $this->fetch('admin@user_access_log/index');
    }

    /**
     * 关联研判：以某个 user_id 为中心，展示其近期使用的 IP，以及
     * 与其共用相同 IP / 相同 UA 指纹的其他账号（credential stuffing / 小号识别）。
     */
    public function info()
    {
        $param = input();
        $uid = intval(isset($param['uid']) ? $param['uid'] : 0);
        if ($uid < 1) {
            return $this->error(lang('param_err'));
        }

        $model = model('UserAccessLog');
        $related = $model->relatedAccounts($uid, 7);
        $ips = $model->relatedIps($uid, 30);

        // 关联封禁：给每个 IP 标注是否已在封禁名单，供研判页直接封禁/解封。
        // relatedIps() 已过滤 log_anonymized=0，故这里的 IP 均为可封禁的单个明文 IP；
        // 匿名化残留的 /24 网段不会出现在此列表。
        $banned = \app\common\util\IpBanRepository::listBanned();
        foreach ($ips as &$ipRow) {
            $ipRow['is_banned'] = in_array((string)$ipRow['log_ip'], $banned, true) ? 1 : 0;
        }
        unset($ipRow);

        // 补齐关联账号的用户名，便于人工研判（去识别化后 IP 段仍保留，用户名不受影响）
        $names = [];
        $ids = [];
        foreach ($related as $r) {
            $ids[] = $r['user_id'];
        }
        if (!empty($ids)) {
            $names = Db::name('user')->where('user_id', 'in', $ids)->column('user_name', 'user_id');
        }
        foreach ($related as &$r) {
            $r['user_name'] = isset($names[$r['user_id']]) ? $names[$r['user_id']] : '';
        }
        unset($r);

        $self = Db::name('user')->where('user_id', $uid)->value('user_name');

        $this->assign('uid', $uid);
        $this->assign('self_name', $self ? $self : '');
        $this->assign('related', $related);
        $this->assign('ips', $ips);
        $this->assign('title', lang('admin/useraccesslog/related_title'));

        return $this->fetch('admin@user_access_log/info');
    }

    /**
     * 删除风控日志（单条 / 批量 / 清空）。
     *
     * 破坏性操作一律只接受 POST：GET 会被 <img>/预取等跨站手段自动触发，
     * 且后台全局 CsrfGuard 只校验 POST（GET 不在其保护面内）。改为 POST 后，
     * 前端经 jQuery 会自动带上 X-CSRF-Token 头、表单也内嵌稳定 __token__，
     * security_csrf_admin 开启时即受统一 CSRF 校验保护。
     */
    public function del()
    {
        if (!request()->isPost()) {
            return $this->error(lang('illegal_request'));
        }
        if (!$this->checkAdminCsrf()) {
            return $this->error(lang('token_err'));
        }
        $param = input('post.');
        $all = intval(isset($param['all']) ? $param['all'] : 0);

        $where = [];
        if ($all === 1) {
            // 清空全部：显式全表条件，不依赖调用方传入的 ids。
            $where['log_id'] = ['gt', 0];
        } else {
            // ids 可能是 "1,2,3" 或 ids[] 数组；一律清洗成正整数集合，
            // 杜绝把模糊/非法输入直接拼进 in 条件。
            $raw = isset($param['ids']) ? $param['ids'] : '';
            if (is_array($raw)) {
                $raw = implode(',', $raw);
            }
            $ids = [];
            foreach (explode(',', (string)$raw) as $one) {
                $one = intval(trim($one));
                if ($one > 0) {
                    $ids[] = $one;
                }
            }
            if (empty($ids)) {
                return $this->error(lang('param_err'));
            }
            $where['log_id'] = ['in', $ids];
        }

        $res = model('UserAccessLog')->delData($where);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg']);
    }


    /**
     * 关联封禁：把研判页里某个可疑 IP 加入封禁名单。
     *
     * 复用 monitor 的 IpBanRepository：白名单/自身 IP/私网/站点 IP 保护、
     * 幂等、只改 blacks.php 的 black_ip_list（绝不整文件覆盖）都在库里统一处理，
     * 这里只做入口转发，避免风控与监控两处各写一套封禁逻辑而产生分歧。
     */
    public function ban()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        if (!$this->checkAdminCsrf()) {
            return json(['code' => 0, 'msg' => lang('token_err')]);
        }
        $ip = trim((string)input('post.ip/s', ''));
        $res = \app\common\util\IpBanRepository::ban($ip);
        return json($res);
    }

    public function unban()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        if (!$this->checkAdminCsrf()) {
            return json(['code' => 0, 'msg' => lang('token_err')]);
        }
        $ip = trim((string)input('post.ip/s', ''));
        $res = \app\common\util\IpBanRepository::unban($ip);
        return json($res);
    }
}
