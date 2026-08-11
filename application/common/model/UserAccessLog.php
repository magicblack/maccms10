<?php
namespace app\common\model;

use think\Db;
use think\Cache;

/**
 * 用户访问风控日志（mac_user_access_log）。
 *
 * ★ 为什么独立成表，而不是往 mac_ulog 加 IP/UA 字段 ★
 * mac_ulog 是「收藏/想看/播放进度/购买凭证」多语意合表，且多处代码把整行 $data 当
 * WHERE 条件做去重与「是否已购」判定（index/api 的 add_ulog、Ulog::hasBought）。
 * 一旦在其中掺入会随请求变化的 IP/UA，换个网络/浏览器就命中不到旧行 —— 收藏重复、
 * 进度炸行、已购凭证重复生成甚至重复扣分。所以风控日志必须是「只追加」的独立事件表，
 * 与业务凭证物理隔离。
 *
 * ★ 记录原则 ★
 * 1. 只记登录用户 + 登录失败/注册尝试；纯游客浏览交给 monitor_abnormal_access，避免表量失控。
 * 2. 记录失败绝不影响主业务 —— record() 内部整体 try/catch，任何异常只吞掉写 trace。
 * 3. 缓存节流：成功/关键操作按身份+事件+IP+UA；登录失败按 IP+目标账号，避免刷表且保留喷洒线索。
 * 4. 到期匿名化 + 清理：原始 IP 转 /24 网段、清空原始 UA（保留 ua_hash 供关联），超保留期整行删除。
 */
class UserAccessLog extends Base
{
    // 设置数据表（不含前缀）
    protected $name = 'user_access_log';

    protected $createTime = '';
    protected $updateTime = '';

    /** 合法事件类型白名单 */
    private static $actions = [
        'login', 'login_fail', 'register', 'play', 'down',
        'fav', 'want', 'buy', 'api_token', 'comment',
    ];

    /**
     * 读取 monitor 段配置（本功能的开关/节流/留存都挂在 monitor 段，
     * 与既有 access_track / ban_whitelist / IpBanRepository 同一处管理）。
     */
    private static function cfg()
    {
        return isset($GLOBALS['config']['monitor']) && is_array($GLOBALS['config']['monitor'])
            ? $GLOBALS['config']['monitor'] : [];
    }

    /** 功能总开关，默认开（风控不能靠站长记得手动开） */
    public static function enabled()
    {
        $cfg = self::cfg();
        return (string)(isset($cfg['user_access_log_enabled']) ? $cfg['user_access_log_enabled'] : '1') === '1';
    }

    /** 节流分钟数，0 表示不节流 */
    private static function throttleMin()
    {
        $cfg = self::cfg();
        $n = intval(isset($cfg['user_access_throttle_min']) ? $cfg['user_access_throttle_min'] : 10);
        return max(0, min(1440, $n));
    }

    /**
     * login_fail 二级封顶——统计窗口秒数（默认 3600=1 小时）。
     * cfg 缺省即用默认值，无需强制在后台新增配置项即可生效。
     */
    private static function failIpCapWindow()
    {
        $cfg = self::cfg();
        $n = intval(isset($cfg['user_access_fail_ip_window']) ? $cfg['user_access_fail_ip_window'] : 3600);
        return max(60, min(86400, $n));
    }

    /**
     * login_fail 二级封顶——同一 IP 在窗口内可写入的最大 login_fail 行数（默认 200，0=不封顶）。
     *
     * 一级节流键 md5(action|ip|user_name) 刻意含 user_name，以保留「同源喷洒多账号」的撞库
     * 识别信号——这正是该功能存在的意义，绝不能把不同目标账号合并成一条。但也因此，攻击者用
     * 字典轮换用户名即可绕过一级节流、把表刷满。这里对「同 IP」再加一层硬上限：上限保留得
     * 足够高（不摧毁多账号识别信号），仅拦截字典喷洒式的刷表 DoS。
     */
    private static function failIpCapMax()
    {
        $cfg = self::cfg();
        $n = intval(isset($cfg['user_access_fail_ip_cap']) ? $cfg['user_access_fail_ip_cap'] : 200);
        return max(0, min(100000, $n));
    }

    /**
     * 解析 UA。优先复用既有 AnalyticsUa（运营统计埋点也用它），
     * 不重造轮子；AnalyticsUa 不可用时静默降级为空串。
     */
    private static function parseUa($ua, $part)
    {
        try {
            $cls = '\\app\\common\\util\\AnalyticsUa';
            if (class_exists($cls) && method_exists($cls, $part)) {
                return (string)$cls::$part($ua);
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    /**
     * 记录一条访问日志。绝不抛异常影响主流程。
     *
     * @param string $action 事件类型（见 $actions 白名单）
     * @param array  $extra  可选 user_id / user_name / mid / rid
     * @return void
     */
    public static function record($action, array $extra = [])
    {
        try {
            if (!self::enabled()) {
                return;
            }
            $action = (string)$action;
            if (!in_array($action, self::$actions, true)) {
                return;
            }

            $userName = mb_substr(trim((string)(isset($extra['user_name']) ? $extra['user_name'] : '')), 0, 60);

            // login_fail 永远是「未验证的尝试」，不可采用调用方传入值或 user_id Cookie，
            // 否则攻击者可伪造 Cookie，把自己的 IP/UA 污染到任意受害者账号名下。
            if ($action === 'login_fail') {
                $userId = 0;
            } elseif (array_key_exists('user_id', $extra)) {
                $userId = intval($extra['user_id']);
            } else {
                // 只有控制器完成认证后写入的全局用户可作为隐式身份；不直接信任 Cookie。
                $userId = isset($GLOBALS['user']['user_id']) ? intval($GLOBALS['user']['user_id']) : 0;
            }
            $userId = max(0, $userId);
            // 只记录登录态；未登录仅保留「登录失败/注册」这类账号安全事件
            if ($userId < 1 && !in_array($action, ['login_fail', 'register'], true)) {
                return;
            }

            $ip = (string)mac_get_client_ip();

            $ua = '';
            try {
                if (function_exists('request')) {
                    $ua = (string)request()->header('user-agent');
                }
            } catch (\Throwable $e) {
            }
            if ($ua === '' && isset($_SERVER['HTTP_USER_AGENT'])) {
                $ua = (string)$_SERVER['HTTP_USER_AGENT'];
            }
            $ua = mb_substr($ua, 0, 255);
            $uaHash = $ua === '' ? '' : md5($ua);

            // 节流：成功/关键操作按身份+事件+IP+UA；登录失败按 IP+目标账号。
            // 失败事件不纳入 UA（客户端可随意改 UA 绕过），且必须纳入 user_name，
            // 避免同一来源针对多个账号的 credential stuffing 被合并成一条。
            $throttle = self::throttleMin();
            $ck = '';
            if ($throttle > 0) {
                $identity = $action === 'login_fail' ? strtolower($userName) : ($userId . '|' . $uaHash);
                $ck = 'ual_' . md5($action . '|' . $ip . '|' . $identity);
                if (Cache::get($ck)) {
                    return;
                }
            }

            // login_fail 二级封顶：一级节流键含 user_name（保留撞库识别信号），攻击者可用字典
            // 轮换用户名绕过、把表刷满。这里对「同 IP 同窗口内的 login_fail 行数」加硬上限，
            // 借 idx_ip_time(log_ip,log_time) 成本极低；到顶即静默丢弃，绝不影响登录主流程。
            if ($action === 'login_fail' && $ip !== '') {
                $capMax = self::failIpCapMax();
                if ($capMax > 0) {
                    try {
                        $capCnt = Db::name('user_access_log')
                            ->where('log_action', 'login_fail')
                            ->where('log_ip', $ip)
                            ->where('log_time', '>=', time() - self::failIpCapWindow())
                            ->count();
                        if ($capCnt >= $capMax) {
                            return;
                        }
                    } catch (\Throwable $e) {
                        // 计数查询失败不阻断记录：宁可多记一条，也不因风控自身故障漏记安全事件。
                        trace('UserAccessLog::record failCap ' . $e->getMessage(), 'error');
                    }
                }
            }

            $ipLong = 0;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // sprintf %u 避免 32 位平台 ip2long 负数溢出
                $ipLong = sprintf('%u', ip2long($ip));
            }

            $path = '';
            try {
                // 只记录不含 query string 的路由。绝不可用 url()：登录接口若收到
                // ?user_pwd=... 等参数，会把明文凭证持久化进风控日志与数据库备份。
                $path = mb_substr((string)request()->baseUrl(), 0, 255);
            } catch (\Throwable $e) {
            }

            $inserted = Db::name('user_access_log')->insert([
                'user_id'        => $userId,
                'user_name'      => $userName,
                'log_action'     => $action,
                'log_mid'        => max(0, intval(isset($extra['mid']) ? $extra['mid'] : 0)),
                'log_rid'        => max(0, intval(isset($extra['rid']) ? $extra['rid'] : 0)),
                'log_ip'         => mb_substr($ip, 0, 45),
                'log_ip_long'    => $ipLong,
                'log_ua'         => $ua,
                'log_ua_hash'    => $uaHash,
                'log_device'     => mb_substr(self::parseUa($ua, 'device'), 0, 20),
                'log_os'         => mb_substr(self::parseUa($ua, 'os'), 0, 30),
                'log_browser'    => mb_substr(self::parseUa($ua, 'browser'), 0, 30),
                'log_path'       => $path,
                'log_anonymized' => 0,
                'log_time'       => time(),
            ]);
            // 只有写库确实成功（insert() 未返回 false）后才建立节流缓存；数据库故障返回 false
            // 而未抛异常时不能静默吞掉后续记录窗口，否则节流期内会漏记真正发生的事件。
            if ($inserted !== false && $throttle > 0 && $ck !== '') {
                Cache::set($ck, 1, $throttle * 60);
            }
        } catch (\Throwable $e) {
            trace('UserAccessLog::record ' . $e->getMessage(), 'error');
        }
    }

    /**
     * 后台列表查询。
     */
    public function listData($where, $order = 'log_id desc', $page = 1, $limit = 20)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = max(1, min(200, $limit ? (int)$limit : 20));
        if (!is_array($where)) {
            $where = json_decode($where, true) ?: [];
        }
        // 白名单化排序：order() 传字符串时不参数化，直接拼进 SQL。目前调用方硬编码
        // 'log_id desc'，但方法签名开放 $order，一旦未来接入用户输入即成注入面，故在此兜底。
        $allowCols = ['log_id', 'log_time', 'user_id', 'log_action'];
        $orderCol = 'log_id';
        $orderDir = 'desc';
        if (is_string($order) && $order !== '') {
            $parts = preg_split('/\s+/', trim($order));
            $col = strtolower(isset($parts[0]) ? $parts[0] : '');
            if (in_array($col, $allowCols, true)) {
                $orderCol = $col;
            }
            if (isset($parts[1]) && strtolower($parts[1]) === 'asc') {
                $orderDir = 'asc';
            }
        }
        $order = $orderCol . ' ' . $orderDir;
        $limit_str = ($limit * ($page - 1)) . ',' . $limit;
        $total = Db::name('user_access_log')->where($where)->count();
        $list = Db::name('user_access_log')->where($where)->order($order)->limit($limit_str)->select();

        return [
            'code' => 1, 'msg' => lang('data_list'),
            'page' => $page, 'pagecount' => ceil($total / $limit),
            'limit' => $limit, 'total' => $total, 'list' => $list,
        ];
    }

    /**
     * 关联账号：与目标用户近 $days 天使用过「相同 IP」或「相同 UA 指纹」的其他账号。
     * 这是人工风控研判的数据基础；UA 是大量用户可共享/可伪造的弱信号，via=ua 不可单独自动封禁。
     *
     * @return array 每项 ['user_id'=>int,'via'=>'ip'|'ua'|'both']
     */
    public function relatedAccounts($userId, $days = 7)
    {
        $userId = intval($userId);
        $days = max(1, intval($days));
        if ($userId < 1) {
            return [];
        }
        $cut = time() - $days * 86400;

        $ips = Db::name('user_access_log')
            ->where('user_id', $userId)->where('log_time', '>=', $cut)
            ->where('log_ip', '<>', '')->where('log_anonymized', 0)
            ->group('log_ip')->limit(200)->column('log_ip');
        $hashes = Db::name('user_access_log')
            ->where('user_id', $userId)->where('log_time', '>=', $cut)
            ->where('log_ua_hash', '<>', '')
            ->group('log_ua_hash')->limit(200)->column('log_ua_hash');

        $via = [];
        if (!empty($ips)) {
            $r = Db::name('user_access_log')
                ->where('log_ip', 'in', $ips)
                ->where('log_time', '>=', $cut)
                ->where('log_anonymized', 0)
                ->where('user_id', '<>', $userId)->where('user_id', '>', 0)
                ->group('user_id')->limit(1000)->column('user_id');
            foreach ($r as $u) {
                $via[$u] = isset($via[$u]) ? 'both' : 'ip';
            }
        }
        if (!empty($hashes)) {
            $r = Db::name('user_access_log')
                ->where('log_ua_hash', 'in', $hashes)
                ->where('log_time', '>=', $cut)
                ->where('user_id', '<>', $userId)->where('user_id', '>', 0)
                ->group('user_id')->limit(1000)->column('user_id');
            foreach ($r as $u) {
                $via[$u] = isset($via[$u]) ? 'both' : 'ua';
            }
        }

        $out = [];
        foreach ($via as $u => $v) {
            $out[] = ['user_id' => intval($u), 'via' => $v];
        }
        return $out;
    }

    /**
     * 目标用户近 $days 天用过的 IP 列表（含次数），供后台展示与「关联封禁 IP」。
     */
    public function relatedIps($userId, $days = 30)
    {
        $userId = intval($userId);
        $days = max(1, intval($days));
        if ($userId < 1) {
            return [];
        }
        $cut = time() - $days * 86400;
        $rows = Db::name('user_access_log')
            ->field('log_ip, count(*) as cnt, max(log_time) as last_time')
            ->where('user_id', $userId)->where('log_time', '>=', $cut)
            ->where('log_ip', '<>', '')
            ->where('log_anonymized', 0)
            ->group('log_ip')->order('last_time desc')->limit(200)->select();
        return $rows ?: [];
    }

    /**
     * 到期清理 + 匿名化。定时任务 user_access_purge 调用。
     *
     * @param int $retainDays    明细保留天数，超过即整行删除
     * @param int $anonymizeDays 超过此天数的行做去识别化（IP→/24、清空原始 UA、保留 ua_hash）
     * @param int $maxRows       单次处理上限，避免长事务/锁表
     * @return array ['deleted'=>int,'anonymized'=>int]
     */
    public static function purge($retainDays, $anonymizeDays, $maxRows = 50000)
    {
        $out = ['deleted' => 0, 'anonymized' => 0];
        $retainDays = max(1, intval($retainDays));
        $anonymizeDays = max(0, intval($anonymizeDays));
        // 不变量：匿名化期限必须早于删除期限。否则匿名化窗口
        // 「log_time >= delCut 且 < anonCut」恒为空集，资料在被去识别化前就整行删除，
        // 匿名化形同虚设。这里做模型层防御性钳制，避免任何调用方（含误配置）绕过。
        if ($anonymizeDays > 0 && $anonymizeDays >= $retainDays) {
            $anonymizeDays = $retainDays - 1;
        }
        // 防止后台误填超大值造成单次长事务/大量逐行 UPDATE。
        $maxRows = max(100, min(50000, intval($maxRows)));
        $delCut = time() - $retainDays * 86400;

        // 删除与匿名化故障隔离：一段失败不应阻断另一段，避免单点异常让两种留存措施同时失效。
        try {
            $deleted = Db::name('user_access_log')
                ->where('log_time', '<', $delCut)
                ->order('log_time asc,log_id asc')
                ->limit($maxRows)->delete();
            // delete() 返回 false 是写库失败，必须写 trace 视为失败；不能被 intval(false)=0
            // 静默当成「删除 0 笔」，否则留存策略失效却毫无告警。
            if ($deleted === false) {
                trace('UserAccessLog::purge delete returned false', 'error');
            } else {
                $out['deleted'] = intval($deleted);
            }
        } catch (\Throwable $e) {
            trace('UserAccessLog::purge delete ' . $e->getMessage(), 'error');
        }

        if ($anonymizeDays > 0) {
            try {
                $anonCut = time() - $anonymizeDays * 86400;
                // 只匿名化「已过匿名化期限、但尚未进入删除期限」的资料。
                // 明确 oldest-first，确保 bounded batch 在 backlog 下仍有公平性、不会饿死旧行。
                $rows = Db::name('user_access_log')
                    ->field('log_id,log_ip')
                    ->where('log_time', '>=', $delCut)
                    ->where('log_time', '<', $anonCut)
                    ->where('log_anonymized', 0)
                    ->order('log_time asc,log_id asc')
                    ->limit($maxRows)->select();
                foreach ($rows as $r) {
                    try {
                        $updated = Db::name('user_access_log')->where('log_id', $r['log_id'])->update([
                            'log_ip'         => self::maskIp($r['log_ip']),
                            'log_ip_long'    => 0,
                            'log_ua'         => '',
                            'log_anonymized' => 1,
                        ]);
                        if ($updated !== false) {
                            $out['anonymized']++;
                        }
                    } catch (\Throwable $e) {
                        trace('UserAccessLog::purge anonymize row=' . intval($r['log_id']) . ' ' . $e->getMessage(), 'error');
                    }
                }
            } catch (\Throwable $e) {
                trace('UserAccessLog::purge anonymize ' . $e->getMessage(), 'error');
            }
        }
        return $out;
    }

    /**
     * IP 去识别化：IPv4 保留前三段(/24)，IPv6 保留前三组(/48)。
     */
    public static function maskIp($ip)
    {
        $ip = (string)$ip;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            if (count($p) === 4) {
                return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0/24';
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // 不可直接 explode(':')：压缩 IPv6（如 2001:db8::1）会产生空段并得到
            // 2001:db8:::/48。先转 16-byte binary，清零后 10 bytes，再标准化输出。
            $packed = @inet_pton($ip);
            if ($packed !== false && strlen($packed) === 16) {
                $network = substr($packed, 0, 6) . str_repeat("\0", 10);
                $masked = @inet_ntop($network);
                if ($masked !== false) {
                    return $masked . '/48';
                }
            }
        }
        return $ip;
    }

    public function delData($where)
    {
        // 走 Db::name 而非 $this->where()->delete()：TP5 的查询方法是经 __call 代理到 Query，
        // 静态分析（intelephense）无法解析会误报 Undefined method；Db::name() 返回 Query 可正常解析。
        try {
            // delete() 返回 false 代表写库失败（非异常路径），不能当成成功；受影响 0 行仍算成功。
            $affected = Db::name('user_access_log')->where($where)->delete();
            if ($affected === false) {
                trace('UserAccessLog::delData delete returned false', 'error');
                return ['code' => 1001, 'msg' => lang('del_err')];
            }
            return ['code' => 1, 'msg' => lang('del_ok')];
        } catch (\Throwable $e) {
            // 异常细节只写入 trace 便于排障；对前端仅回通用错误，
            // 绝不把 SQL/表结构/文件路径/堆栈透传到后台界面（信息泄露面）。
            trace('UserAccessLog::delData ' . $e->getMessage(), 'error');
            return ['code' => 1001, 'msg' => lang('del_err')];
        }
    }
}
