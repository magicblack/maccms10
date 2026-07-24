<?php
namespace app\admin\controller;

use app\common\util\MonitorCollector;
use app\common\util\MonitorMetric;
use app\common\util\MonitorState;
use think\Cache;
use think\Db;

/**
 * 监控与告警后台。
 *
 * ★ 新后台控制器约定 ★
 * 构造函数里把 view_path 指向 admin/view_new/，且 fetch() 一律用模块相对形式
 * （fetch('monitor/index')），推荐不用跨模块的 admin@ 前缀。
 *
 * 说明：旧版后台 view/ 已移除，admin 模块统一使用 view_new/。
 * thinkphp/library/think/Controller.php 与 Think 驱动的 admin@ 解析均已写死
 * 指向 admin/view_new/，不再依赖 site.new_version（该键已废弃）。
 * 模块相对形式仍是最直观、自给自足的写法。
 */

class Monitor extends Base
{
    /** 后台实时面板的轮询结果缓存秒数：多个管理员同时开着页面只打一次 DB */
    const LIVE_CACHE_SEC = 5;

    /** cron 心跳超过这个秒数就认为监控没在跑 */
    const HEARTBEAT_DEAD_SEC = 300;

    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    /**
     * 服务器性能曲线页
     */
    public function index()
    {
        $this->assign('title', lang('admin/monitor/title_server'));
        $this->assign('heartbeat', $this->heartbeatStatus());
        $this->assign('cron_url', $this->cronUrl());
        $this->assign('capabilities', MonitorCollector::capabilities());
        $this->assign('skipped', $this->skippedReasons());
        return $this->fetch('monitor/index');
    }

    /**
     * 近实时面板
     */
    public function realtime()
    {
        $this->assign('title', lang('admin/monitor/title_realtime'));
        $this->assign('heartbeat', $this->heartbeatStatus());
        return $this->fetch('monitor/realtime');
    }

    /**
     * 配置页
     */
    public function setting()
    {
        $cfg = $this->monitorConfig();
        $this->assign('title', lang('admin/monitor/title_setting'));
        $this->assign('cfg', $cfg);
        $this->assign('cron_url', $this->cronUrl());
        $this->assign('heartbeat', $this->heartbeatStatus());
        return $this->fetch('monitor/setting');
    }

    /**
     * 曲线数据。
     *
     * 返回 uPlot 需要的列式格式 [[ts...],[v1...],[v2...]]，
     * 而不是对象数组 —— 既省带宽，前端也不用再转置一次。
     */
    public function series()
    {
        $range = (string)input('range/s', '6h');
        $keys = (string)input('keys/s', '');

        $windows = [
            '1h'  => 3600,
            '6h'  => 21600,
            '24h' => 86400,
            '72h' => 259200,
            '7d'  => 604800,
            '30d' => 2592000,
        ];
        if (!isset($windows[$range])) {
            $range = '6h';
        }
        $span = $windows[$range];

        $metricKeys = [];
        foreach (explode(',', $keys) as $k) {
            $k = trim($k);
            // 指标键的字符集：字母数字点下划线横杠，加上维度分隔符 | 与路径里的 /
            if ($k !== '' && preg_match('#^[A-Za-z0-9_.\-|/]{1,64}$#', $k)) {
                $metricKeys[] = $k;
            }
        }
        if (empty($metricKeys)) {
            return json(['code' => 0, 'msg' => lang('admin/monitor/no_metric'), 'data' => []]);
        }

        $to = time();
        $from = $to - $span;
        $res = MonitorMetric::fetchSeries($metricKeys, $from, $to, 'auto');
        $step = $res['granularity'] === 'hour' ? 3600 : 60;

        // 生成对齐的时间轴，缺失点补 null（uPlot 会画成断线，
        // 这正是我们要的：cron 漏跑的那几分钟应该看得出来是断的，而不是被插值连成一条平滑的假线）
        $start = intval(floor($from / $step) * $step);
        $axis = [];
        for ($t = $start; $t <= $to; $t += $step) {
            $axis[] = $t;
        }

        $out = [$axis];
        foreach ($metricKeys as $k) {
            $vals = [];
            $pts = isset($res['series'][$k]) ? $res['series'][$k] : [];
            foreach ($axis as $t) {
                $vals[] = isset($pts[$t]) ? floatval($pts[$t]) : null;
            }
            $out[] = $vals;
        }

        return json([
            'code' => 1,
            'msg'  => '',
            'data' => [
                'keys'        => $metricKeys,
                'granularity' => $res['granularity'],
                'step'        => $step,
                'data'        => $out,
            ],
        ]);
    }

    /**
     * 实时面板的轮询端点。
     *
     * 沿用 api/controller/Chatroom.php::get_list() 的范式：
     * 短 TTL 缓存 + 多管理员共享，避免每个开着页面的人各打一次 DB。
     */
    public function live()
    {
        $flag = isset($GLOBALS['config']['app']['cache_flag'])
            ? (string)$GLOBALS['config']['app']['cache_flag'] : 'mac';
        $cacheKey = $flag . '_mon_live';

        $cached = Cache::get($cacheKey);
        if (!empty($cached) && is_array($cached)) {
            return json($cached);
        }

        $now = time();
        $from = $now - 3600;

        $keys = [
            'biz.online', 'biz.pv5m', 'http.req', 'http.5xx',
            'sys.load1', 'sys.mem.used_pct', 'sys.cpu.pct',
        ];
        $res = MonitorMetric::fetchSeries($keys, $from, $now, 'min');
        $series = $res['series'];

        // p95 必须从合并后的直方图算，绝不能对每分钟的 p95 取平均 ——
        // 分位数的平均没有任何统计意义。
        $buckets = MonitorMetric::fetchLatencyHistogram($now - 300, $now);
        $p95 = MonitorMetric::estimatePercentile($buckets, 0.95);

        $req5m = 0;
        $err5m = 0;
        foreach ($series['http.req'] as $t => $v) {
            if ($t >= $now - 300) {
                $req5m += $v;
            }
        }
        foreach ($series['http.5xx'] as $t => $v) {
            if ($t >= $now - 300) {
                $err5m += $v;
            }
        }

        $payload = [
            'code' => 1,
            'msg'  => '',
            'data' => [
                'ts'          => $now,
                'online'      => intval($this->lastOf($series['biz.online'])),
                'pv5m'        => intval($this->lastOf($series['biz.pv5m'])),
                'online_est'  => !$this->analyticsAvailable(),
                'qps'         => $req5m > 0 ? round($req5m / 300.0, 2) : 0,
                'err_rate'    => $req5m > 0 ? round(100.0 * $err5m / $req5m, 2) : 0,
                'p95_ms'      => $p95 === null ? null : round($p95['value'], 0),
                'p95_clamped' => $p95 === null ? false : $p95['clamped'],
                'load1'       => $this->lastOf($series['sys.load1']),
                'mem_pct'     => $this->lastOf($series['sys.mem.used_pct']),
                'cpu_pct'     => $this->lastOf($series['sys.cpu.pct']),
                'spark'       => [
                    'req'  => $this->sparkOf($series['http.req'], $from, $now),
                    'cpu'  => $this->sparkOf($series['sys.cpu.pct'], $from, $now),
                    'mem'  => $this->sparkOf($series['sys.mem.used_pct'], $from, $now),
                    'load' => $this->sparkOf($series['sys.load1'], $from, $now),
                ],
                'heartbeat'   => $this->heartbeatStatus(),
            ],
        ];

        Cache::set($cacheKey, $payload, self::LIVE_CACHE_SEC);
        return json($payload);
    }

    /**
     * 保存配置。
     *
     * 只改 monitor 段，其余键原样写回 —— 绝不整文件覆盖，
     * 否则会把站长的数据库密码、支付密钥、定时任务全部打回默认值。
     */
    public function setting_save()
    {
        $param = input('post.');

        $file = APP_PATH . 'extra/maccms.php';
        $config = config('maccms');
        if (!is_array($config)) {
            return json(['code' => 0, 'msg' => lang('admin/monitor/config_read_fail')]);
        }
        if (!isset($config['monitor']) || !is_array($config['monitor'])) {
            $config['monitor'] = [];
        }

        $bools = ['enabled', 'req_metrics_enabled', 'allow_shell', 'webhook_allow_private', 'access_track_enabled'];
        foreach ($bools as $k) {
            if (isset($param[$k])) {
                $config['monitor'][$k] = ((string)$param[$k] === '1') ? '1' : '0';
            }
        }

        // 通知渠道凭据。这些是敏感值，只保存在服务端配置里，绝不输出到前台。
        // 注意：只改 monitor 段，其余键原样写回 —— 绝不整文件覆盖 maccms.php，
        // 否则会把站长的数据库密码、支付密钥、定时任务全部打回默认值。
        $creds = [
            'notify_user_ids', 'alert_emails', 'webhook_url', 'webhook_secret',
            'telegram_token', 'telegram_chat_id', 'dingtalk_token', 'dingtalk_secret',
            'wecom_key', 'serverchan_key',
        ];
        foreach ($creds as $k) {
            if (isset($param[$k])) {
                $config['monitor'][$k] = mb_substr(trim((string)$param[$k]), 0, 255);
            }
        }

        // webhook 地址先过一遍 SSRF 守卫，让站长在保存时就看到问题，
        // 而不是等到真出事、告警发不出去才发现。
        //
        // 守卫读的是 $GLOBALS 里的 webhook_allow_private，所以必须先把本次提交的
        // 新值同步过去 —— 否则站长「同时打开内网放行 + 填内网地址」的合法操作会被误拒。
        if (isset($param['webhook_url'])) {
            $wu = trim((string)$param['webhook_url']);
            if ($wu !== '') {
                if (!isset($GLOBALS['config']['monitor']) || !is_array($GLOBALS['config']['monitor'])) {
                    $GLOBALS['config']['monitor'] = [];
                }
                $GLOBALS['config']['monitor']['webhook_allow_private'] = $config['monitor']['webhook_allow_private'];
                $guard = \app\common\extend\push\PushHttp::guardUrl($wu);
                if ($guard !== null) {
                    return json(['code' => 0, 'msg' => $guard['msg']]);
                }
            }
        }

        if (isset($param['notify_budget_hour'])) {
            $config['monitor']['notify_budget_hour'] = (string)min(500, max(1, intval($param['notify_budget_hour'])));
        }

        $ints = [
            'access_cc_threshold'   => [10, 100000],
            'access_err4_threshold' => [5, 100000],
            'access_track_max_ip'   => [50, 2000],
            'retain_access_days'    => [1, 365],
        ];
        foreach ($ints as $k => $range) {
            if (isset($param[$k])) {
                $config['monitor'][$k] = (string)min($range[1], max($range[0], intval($param[$k])));
            }
        }
        if (isset($param['ban_whitelist'])) {
            $config['monitor']['ban_whitelist'] = mac_filter_xss(trim((string)$param['ban_whitelist']));
        }

        if (isset($param['req_sample_rate'])) {
            $config['monitor']['req_sample_rate'] = (string)min(100, max(1, intval($param['req_sample_rate'])));
        }
        if (isset($param['slow_ms'])) {
            $config['monitor']['slow_ms'] = (string)min(60000, max(50, intval($param['slow_ms'])));
        }
        if (isset($param['retain_min_days'])) {
            $config['monitor']['retain_min_days'] = (string)min(14, max(1, intval($param['retain_min_days'])));
        }
        if (isset($param['retain_hour_days'])) {
            $config['monitor']['retain_hour_days'] = (string)min(730, max(7, intval($param['retain_hour_days'])));
        }
        if (isset($param['disk_mounts'])) {
            $config['monitor']['disk_mounts'] = mac_filter_xss(trim((string)$param['disk_mounts']));
        }
        if (isset($param['heartbeat_url'])) {
            $url = trim((string)$param['heartbeat_url']);
            if ($url !== '') {
                // heartbeat_url 每分钟被 cron 主动请求，与 webhook_url 结构相同，
                // 必须走同款 SSRF 守卫（拦私网/回环/云元数据），否则可被配成内网探针。
                if (!isset($GLOBALS['config']['monitor']) || !is_array($GLOBALS['config']['monitor'])) {
                    $GLOBALS['config']['monitor'] = [];
                }
                $GLOBALS['config']['monitor']['webhook_allow_private'] = $config['monitor']['webhook_allow_private'];
                $guard = \app\common\extend\push\PushHttp::guardUrl($url);
                if ($guard !== null) {
                    return json(['code' => 0, 'msg' => $guard['msg']]);
                }
            }
            $config['monitor']['heartbeat_url'] = $url;
        }

        if (mac_arr2file($file, $config) === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }
        return json(['code' => 1, 'msg' => lang('save_ok')]);
    }

    /**
     * 重新生成 cron token。
     *
     * 换 token 会让站长已配置的 crontab 立刻失效，所以这是显式操作，
     * 页面上必须提示「换完记得更新 crontab」。
     */
    public function cron_token_reset()
    {
        $file = APP_PATH . 'extra/maccms.php';
        $config = config('maccms');
        if (!is_array($config)) {
            return json(['code' => 0, 'msg' => lang('admin/monitor/config_read_fail')]);
        }
        if (!isset($config['monitor']) || !is_array($config['monitor'])) {
            $config['monitor'] = [];
        }
        $config['monitor']['cron_token'] = mac_get_rndstr(32);

        if (mac_arr2file($file, $config) === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }
        return json([
            'code' => 1,
            'msg'  => lang('save_ok'),
            'data' => ['cron_url' => $this->cronUrl($config['monitor']['cron_token'])],
        ]);
    }

    // ------------------------------------------------------------------
    // 告警规则与事件
    // ------------------------------------------------------------------

    /**
     * 告警规则列表
     */
    public function rule()
    {
        $list = Db::name('MonitorAlertRule')->order('rule_status desc,rule_id asc')->select();
        $this->assign('title', lang('admin/monitor/title_rule'));
        $this->assign('list', $list);
        // 数组必须由控制器 assign，模板里不能把内联数组字面量当 {foreach} 的 name——
        // 那会被编译成「常量 instanceof」，PHP < 7.3 直接致命。
        $this->assign('push_channels', ['notify', 'email', 'webhook', 'telegram', 'dingtalk', 'wecom', 'serverchan']);
        $this->assign('heartbeat', $this->heartbeatStatus());
        return $this->fetch('monitor/rule');
    }

    /**
     * 规则编辑表单
     */
    public function rule_info()
    {
        $id = intval(input('id/d', 0));
        $info = $id > 0 ? Db::name('MonitorAlertRule')->where('rule_id', $id)->find() : [];
        if (empty($info)) {
            $info = [
                'rule_id' => 0, 'rule_name' => '', 'rule_status' => 1, 'rule_source' => 'metric',
                'rule_metric' => '', 'rule_agg' => 'avg', 'rule_window_min' => 5, 'rule_op' => 'gt',
                'rule_threshold' => 0, 'rule_for_min' => 3, 'rule_severity' => 2,
                'rule_silence_min' => 30, 'rule_recover_min' => 3, 'rule_channels' => 'notify',
                'rule_detect_mode' => 'threshold', 'rule_detect_param' => '',
            ];
        }
        $this->assign('title', lang('admin/monitor/title_rule'));
        $this->assign('info', $info);
        $this->assign('channels', ['notify', 'email', 'webhook', 'telegram', 'dingtalk', 'wecom', 'serverchan']);
        // 同上：聚合方式/比较符列表也从控制器 assign，避免模板内联数组字面量。
        $this->assign('agg_list', ['avg', 'max', 'min', 'sum', 'last', 'p95']);
        $this->assign('op_list', ['gt', 'gte', 'lt', 'lte']);
        $this->assign('analytics_metrics', array_merge(
            \app\common\util\AnalyticsAnomaly::HOURLY_METRICS,
            \app\common\util\AnalyticsAnomaly::DAILY_METRICS
        ));
        return $this->fetch('monitor/rule_info');
    }

    /**
     * 保存规则
     */
    public function rule_save()
    {
        $param = input('post.');

        $name = mac_filter_xss(trim((string)(isset($param['rule_name']) ? $param['rule_name'] : '')));
        $metric = trim((string)(isset($param['rule_metric']) ? $param['rule_metric'] : ''));
        if ($name === '') {
            return json(['code' => 0, 'msg' => lang('admin/monitor/rule_name_required')]);
        }
        // 指标键会被直接拼进 SQL 的绑定参数，但仍然做白名单校验，
        // 避免脏数据让后续的图表查询与告警评估拿到永远查不到的键
        if ($metric === '' || !preg_match('#^[A-Za-z0-9_.\-|/]{1,64}$#', $metric)) {
            return json(['code' => 0, 'msg' => lang('admin/monitor/rule_metric_invalid')]);
        }

        $source = ((string)(isset($param['rule_source']) ? $param['rule_source'] : 'metric') === 'analytics')
            ? 'analytics' : 'metric';

        $agg = in_array((string)$param['rule_agg'], ['avg', 'max', 'min', 'sum', 'last', 'p95'], true)
            ? (string)$param['rule_agg'] : 'avg';
        $op = in_array((string)$param['rule_op'], ['gt', 'gte', 'lt', 'lte'], true)
            ? (string)$param['rule_op'] : 'gt';

        $detectMode = 'threshold';
        $detectParam = '';
        if ($source === 'analytics') {
            if (!\app\common\util\AnalyticsAnomaly::isSupported($metric)) {
                return json(['code' => 0, 'msg' => lang('admin/monitor/analytics_metric_unsupported')]);
            }
            $detectMode = in_array((string)$param['rule_detect_mode'], ['zscore', 'zerodrop', 'yoy', 'mom'], true)
                ? (string)$param['rule_detect_mode'] : 'zscore';

            // detect_param 必须是合法 JSON，否则规则会静默地退回默认值 ——
            // 站长以为自己调了阈值，其实没生效，这比报错更糟
            $raw = trim((string)(isset($param['rule_detect_param']) ? $param['rule_detect_param'] : ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    return json(['code' => 0, 'msg' => lang('admin/monitor/detect_param_invalid')]);
                }
                $detectParam = mb_substr($raw, 0, 500);
            }
        }

        $channels = \app\common\util\AlertNotifier::parseChannels(
            isset($param['rule_channels']) ? (is_array($param['rule_channels']) ? implode(',', $param['rule_channels']) : $param['rule_channels']) : ''
        );

        $data = [
            'rule_name'         => mb_substr($name, 0, 100),
            'rule_status'       => ((string)(isset($param['rule_status']) ? $param['rule_status'] : '0') === '1') ? 1 : 0,
            'rule_source'       => $source,
            'rule_metric'       => $metric,
            'rule_agg'          => $agg,
            'rule_window_min'   => min(1440, max(1, intval($param['rule_window_min']))),
            'rule_op'           => $op,
            'rule_threshold'    => floatval($param['rule_threshold']),
            // analytics 规则每小时评估一次，「持续 N 分钟」对它没有意义（详见 AlertEngine::handleHit）
            'rule_for_min'      => ($source === 'analytics') ? 0 : min(1440, max(0, intval($param['rule_for_min']))),
            'rule_severity'     => min(3, max(1, intval($param['rule_severity']))),
            'rule_silence_min'  => min(1440, max(0, intval($param['rule_silence_min']))),
            'rule_recover_min'  => min(1440, max(0, intval($param['rule_recover_min']))),
            'rule_channels'     => implode(',', $channels),
            'rule_detect_mode'  => $detectMode,
            'rule_detect_param' => $detectParam,
            'rule_time'         => time(),
        ];

        $id = intval(input('post.rule_id/d', 0));
        if ($id > 0) {
            Db::name('MonitorAlertRule')->where('rule_id', $id)->update($data);
        } else {
            $data['rule_time_add'] = time();
            $id = intval(Db::name('MonitorAlertRule')->insertGetId($data));
        }
        return json(['code' => 1, 'msg' => lang('save_ok'), 'data' => ['rule_id' => $id]]);
    }

    /**
     * 删除规则。同时清掉它的活跃事件与 pending 状态，
     * 否则一条已删除的规则会留下永远无法恢复的僵尸告警。
     */
    public function rule_del()
    {
        $id = intval(input('id/d', 0));
        if ($id <= 0) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        Db::name('MonitorAlertEvent')->where('rule_id', $id)->where('event_end_ts', 0)->delete();
        Db::name('MonitorAlertRule')->where('rule_id', $id)->delete();
        MonitorState::purgeByPrefix('alert.pending.' . $id);
        MonitorState::purgeByPrefix('alert.recover.' . $id);
        return json(['code' => 1, 'msg' => lang('del_ok')]);
    }

    /**
     * 启用/停用
     */
    public function rule_field()
    {
        $id = intval(input('post.id/d', 0));
        $status = ((string)input('post.status/s', '0') === '1') ? 1 : 0;
        if ($id <= 0) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        Db::name('MonitorAlertRule')->where('rule_id', $id)->update(['rule_status' => $status, 'rule_time' => time()]);
        return json(['code' => 1, 'msg' => lang('save_ok')]);
    }

    /**
     * 一键启用推荐规则。
     *
     * 种子规则默认全部停用：阈值高度依赖具体机器配置，
     * 强行默认启用只会制造噪音。所以给一个显式的一键开关。
     */
    public function rule_enable_recommended()
    {
        $n = Db::name('MonitorAlertRule')
            ->where('rule_status', 0)
            ->where('rule_source', 'metric')
            ->update(['rule_status' => 1, 'rule_time' => time()]);
        return json(['code' => 1, 'msg' => lang('save_ok'), 'data' => ['enabled' => intval($n)]]);
    }

    /**
     * 测试推送渠道。真正发一条出去，让站长当场确认配置是对的 ——
     * 而不是等到真出事时才发现 webhook 地址填错了。
     */
    public function rule_test()
    {
        $channel = strtolower(trim((string)input('post.channel/s', '')));
        $channels = \app\common\util\AlertNotifier::parseChannels($channel);
        if (empty($channels)) {
            return json(['code' => 0, 'msg' => lang('admin/monitor/push_channel_invalid')]);
        }
        $ch = $channels[0];

        $fakeEvent = [
            'event_metric'    => 'sys.cpu.pct',
            'event_severity'  => 2,
            'event_value'     => 88.8,
            'event_threshold' => 85,
            'event_summary'   => lang('admin/monitor/test_push_summary'),
            'event_start_ts'  => time(),
            'event_end_ts'    => 0,
        ];
        $fakeRule = ['rule_name' => lang('admin/monitor/test_push_rule'), 'rule_op' => 'gt', 'rule_channels' => $ch];

        // 测试推送不受熔断影响：站长正是要靠它来确认「修好了没有」
        \app\common\util\PushCircuit::reset($ch);
        \app\common\util\AlertNotifier::resetRun();
        $res = \app\common\util\AlertNotifier::notify($fakeEvent, $fakeRule, false, null, time());

        if (!empty($res['sent'])) {
            return json(['code' => 1, 'msg' => lang('admin/monitor/test_push_ok')]);
        }
        $reason = array_merge($res['failed'], $res['skipped']);
        return json(['code' => 0, 'msg' => implode('; ', $reason)]);
    }

    /**
     * 告警事件历史
     */
    public function event()
    {
        $page = max(1, intval(input('page/d', 1)));
        $limit = 20;
        $status = intval(input('status/d', 0));

        $where = [];
        if (in_array($status, [1, 2, 3], true)) {
            $where['event_status'] = $status;
        }

        $total = Db::name('MonitorAlertEvent')->where($where)->count();
        $list = Db::name('MonitorAlertEvent')
            ->where($where)
            ->order('event_start_ts desc')
            ->limit(($page - 1) * $limit, $limit)
            ->select();

        $this->assign('title', lang('admin/monitor/title_event'));
        $this->assign('list', $list);
        $this->assign('total', $total);
        $this->assign('page', $page);
        $this->assign('limit', $limit);
        $this->assign('status', $status);
        $this->assign('firing', Db::name('MonitorAlertEvent')->where('event_status', 1)->count());
        return $this->fetch('monitor/event');
    }

    /**
     * 确认告警（人已看到，但问题还没解决）
     */
    public function event_ack()
    {
        $id = intval(input('post.id/d', 0));
        if ($id <= 0) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        Db::name('MonitorAlertEvent')->where('event_id', $id)->where('event_status', 1)->update(['event_status' => 3]);
        return json(['code' => 1, 'msg' => lang('save_ok')]);
    }

    // ------------------------------------------------------------------
    // 异常访问
    // ------------------------------------------------------------------

    /**
     * 异常访问列表。
     *
     * 按 IP 聚合最近 N 小时的记录（一个 IP 在多个分钟里都可疑时，
     * 后台关心的是「这个 IP 总共干了什么」，而不是逐分钟流水）。
     */
    public function access()
    {
        $hours = min(168, max(1, intval(input('hours/d', 24))));
        $level = intval(input('level/d', -1));
        $page = max(1, intval(input('page/d', 1)));
        $limit = 30;

        $pre = Db::getConfig('prefix');
        $from = time() - ($hours * 3600);

        $where = 'WHERE `stat_min` >= ?';
        $bind = [$from];
        if (in_array($level, [0, 1, 2], true)) {
            $where .= ' AND `access_level` = ?';
            $bind[] = $level;
        }

        $totalRow = Db::query(
            "SELECT COUNT(DISTINCT `access_ip`) AS c FROM `{$pre}monitor_abnormal_access` {$where}",
            $bind
        );
        $total = !empty($totalRow) ? intval($totalRow[0]['c']) : 0;

        $listBind = $bind;
        $listBind[] = ($page - 1) * $limit;
        $listBind[] = $limit;
        $list = Db::query(
            "SELECT `access_ip`, SUM(`hit_cnt`) AS hit_cnt, SUM(`err4_cnt`) AS err4_cnt, SUM(`err5_cnt`) AS err5_cnt,"
            . " SUM(`scan_cnt`) AS scan_cnt, SUM(`bad_ua_cnt`) AS bad_ua_cnt, SUM(`blocked_cnt`) AS blocked_cnt,"
            . " MAX(`risk_score`) AS risk_score, MAX(`access_level`) AS access_level,"
            . " MAX(`updated_at`) AS updated_at,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(`last_ua` ORDER BY `stat_min` DESC SEPARATOR '\\n'), '\\n', 1) AS last_ua,"
            . " SUBSTRING_INDEX(GROUP_CONCAT(`last_path` ORDER BY `stat_min` DESC SEPARATOR '\\n'), '\\n', 1) AS last_path"
            . " FROM `{$pre}monitor_abnormal_access` {$where}"
            . " GROUP BY `access_ip` ORDER BY `risk_score` DESC, hit_cnt DESC LIMIT ?, ?",
            $listBind
        );

        $banned = \app\common\util\IpBanRepository::listBanned();
        foreach ($list as &$row) {
            $row['is_banned'] = in_array((string)$row['access_ip'], $banned, true) ? 1 : 0;
        }
        unset($row);

        $this->assign('title', lang('admin/monitor/title_access'));
        $this->assign('list', $list);
        $this->assign('total', $total);
        $this->assign('page', $page);
        $this->assign('limit', $limit);
        $this->assign('hours', $hours);
        $this->assign('level', $level);
        $this->assign('banned_count', count($banned));
        $this->assign('track_enabled', $this->monitorConfig()['access_track_enabled']);
        return $this->fetch('monitor/access');
    }

    /**
     * 封禁 IP。
     *
     * 真正让封禁生效的是 app_begin 上的 IpBlock 行为 ——
     * 在它之前，blacks.php 的 black_ip_list 只挡评论/弹幕/聊天，
     * 被「封」的扫描器照样能刷首页和 API。
     */
    public function access_ban()
    {
        $ip = trim((string)input('post.ip/s', ''));
        $res = \app\common\util\IpBanRepository::ban($ip);
        return json($res);
    }

    public function access_unban()
    {
        $ip = trim((string)input('post.ip/s', ''));
        $res = \app\common\util\IpBanRepository::unban($ip);
        return json($res);
    }

    // ------------------------------------------------------------------

    /**
     * dead-man switch：cron 是否还活着。
     *
     * 这是整个子系统最重要的一个 UI 元素。站内自监控有个固有盲区：
     * 机器挂了 -> PHP 挂了 -> cron 打不通 -> 告警引擎也跟着死。
     * 至少要让站长一登进后台就看到「监控本身没在跑」。
     *
     * @return array
     */
    private function heartbeatStatus()
    {
        $last = MonitorState::getNum('cron_heartbeat', 0);
        $age = $last > 0 ? (time() - $last) : -1;
        return [
            'last'  => $last,
            'age'   => $age,
            'alive' => ($last > 0 && $age <= self::HEARTBEAT_DEAD_SEC),
        ];
    }

    /**
     * @param string $token
     * @return string
     */
    private function cronUrl($token = '')
    {
        $cfg = $this->monitorConfig();
        if ($token === '') {
            $token = isset($cfg['cron_token']) ? (string)$cfg['cron_token'] : '';
        }
        $host = isset($GLOBALS['config']['site']['site_url']) ? rtrim((string)$GLOBALS['config']['site']['site_url'], '/') : '';
        return $host . '/api.php/monitor/cron?token=' . $token;
    }

    /**
     * @return array
     */
    private function monitorConfig()
    {
        $cfg = isset($GLOBALS['config']['monitor']) && is_array($GLOBALS['config']['monitor'])
            ? $GLOBALS['config']['monitor'] : [];
        $defaults = [
            'enabled'             => '1',
            'cron_token'          => '',
            'req_metrics_enabled' => '1',
            'req_sample_rate'     => '100',
            'slow_ms'             => '1000',
            'allow_shell'         => '0',
            'disk_mounts'         => '',
            'retain_min_days'     => '3',
            'retain_hour_days'    => '90',
            'heartbeat_url'       => '',
            'notify_user_ids'     => '',
            'alert_emails'        => '',
            'webhook_url'         => '',
            'webhook_secret'      => '',
            'webhook_allow_private' => '0',
            'telegram_token'      => '',
            'telegram_chat_id'    => '',
            'dingtalk_token'      => '',
            'dingtalk_secret'     => '',
            'wecom_key'           => '',
            'serverchan_key'      => '',
            'notify_budget_hour'  => '20',
            'access_track_enabled' => '0',
            'access_cc_threshold'  => '120',
            'access_err4_threshold' => '20',
            'access_track_max_ip'  => '300',
            'retain_access_days'   => '30',
            'ban_whitelist'        => '',
        ];
        foreach ($defaults as $k => $v) {
            if (!isset($cfg[$k])) {
                $cfg[$k] = $v;
            }
        }
        return $cfg;
    }

    /**
     * 采集器上一次跑下来「拿不到」的指标及原因，用于能力面板。
     *
     * @return array
     */
    private function skippedReasons()
    {
        $raw = MonitorState::getVal('capabilities', '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return bool
     */
    private function analyticsAvailable()
    {
        try {
            $pre = Db::getConfig('prefix');
            return !empty(Db::query("SHOW TABLES LIKE '{$pre}analytics_pageview'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array $points [ts => value]
     * @return float
     */
    private function lastOf(array $points)
    {
        if (empty($points)) {
            return 0;
        }
        ksort($points);
        return floatval(end($points));
    }

    /**
     * 生成 uPlot sparkline 要的列式数据（缺失点补 null，断线即断线）。
     *
     * @param array $points
     * @param int   $from
     * @param int   $to
     * @return array
     */
    private function sparkOf(array $points, $from, $to)
    {
        $axis = [];
        $vals = [];
        $start = intval(floor($from / 60) * 60);
        for ($t = $start; $t <= $to; $t += 60) {
            $axis[] = $t;
            $vals[] = isset($points[$t]) ? floatval($points[$t]) : null;
        }
        return [$axis, $vals];
    }
}
