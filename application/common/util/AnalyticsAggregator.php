<?php
namespace app\common\util;

use think\Db;

class AnalyticsAggregator
{
    public static function run($mode = 'hour', $timePoint = '')
    {
        if ($mode === 'day') {
            return self::runDay($timePoint);
        }
        return self::runHour($timePoint);
    }

    public static function runDay($statDate = '')
    {
        $targetDate = empty($statDate) ? date('Y-m-d', strtotime('-1 day')) : $statDate;
        return self::aggregateDay($targetDate);
    }

    public static function runHour($hourTime = '')
    {
        $hourTs = empty($hourTime) ? strtotime(date('Y-m-d H:00:00', strtotime('-1 hour'))) : strtotime($hourTime);
        return self::aggregateHour($hourTs);
    }

    /**
     * 按天统计独立访客（UV），供后台首页「本日 / 近七日用户访问」直接读取。
     * uv 口径与 aggregateDay() 保持一致：count(distinct visitor_id)。
     *
     * 取数优先级：
     *   1. 历史日优先读已聚合的 analytics_day_overview（analytics_day 定时任务产出）。
     *      明细表 pageview 上没有 (stat_date, visitor_id) 复合索引，count(distinct visitor_id)
     *      只能靠 idx_stat_date 定位后回表，长范围会临时表+filesort；后台首页每次打开都要查，
     *      不能直接扫明细（同 admin/controller/Analytics.php 的封顶思路）。
     *   2. 聚合没覆盖的日子才回退扫明细表。正常站点只有「今天」落在这里。
     *
     * 「今天」永远走明细实时算，不信 day_overview 里今天的行：管理员手动触发
     * mode=day&date=今天 会把半天的数据落库，之后首页就会一直停在那个半截数字。
     *
     * 日期桶零填充：无数据的日子返回 0，而不是直接跳格，否则前端折线图会错位。
     *
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d
     * @return array ['days'=>['Y-m-d',...],'count'=>[int,...],'sum'=>int]
     */
    public static function dailyUv($startDate, $endDate)
    {
        $startTs = strtotime($startDate . ' 00:00:00');
        $endTs = strtotime($endDate . ' 00:00:00');
        if ($startTs === false || $endTs === false || $startTs <= 0) {
            return ['days' => [], 'count' => [], 'sum' => 0];
        }
        // 起止倒置（前端乱传）时退化为单日桶，不能让逐日循环跑不完
        if ($endTs < $startTs) {
            $endTs = $startTs;
        }
        // 扫描窗口封顶 31 天，与 admin/controller/Analytics.php 对同一张 pageview 大表的
        // 既有约定一致：聚合表没覆盖的日子要回退扫明细，而 count(distinct visitor_id)
        // 无对应复合索引，长范围会触发临时表+filesort。存量站升级当天聚合表还是空的，
        // 整个区间都会落到明细上，放宽窗口等于把后台首页变成慢查询入口。
        $maxTs = strtotime('+30 day', $startTs);
        if ($endTs > $maxTs) {
            $endTs = $maxTs;
        }

        $days = [];
        // 用 strtotime('+1 day') 而不是 +86400，跨夏令时才不会丢日子
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $days[] = date('Y-m-d', $ts);
        }

        $today = date('Y-m-d');
        $map = [];
        try {
            $overviewRows = Db::name('AnalyticsDayOverview')
                ->field('stat_date,uv')
                ->where('stat_date', 'between', [$days[0], $days[count($days) - 1]])
                ->select();
            foreach ($overviewRows as $row) {
                $day = substr((string)$row['stat_date'], 0, 10);
                if ($day < $today) {
                    $map[$day] = intval($row['uv']);
                }
            }
        } catch (\Exception $e) {
            // 聚合表缺失只是失去加速，回退扫明细即可，不能让整个指标取不到
            $map = [];
        }

        // 只对聚合没覆盖的日子查明细，用 IN 走 idx_stat_date，不去扫已覆盖的日子
        $missing = [];
        foreach ($days as $day) {
            if (!isset($map[$day])) {
                $missing[] = $day;
            }
        }
        if (!empty($missing)) {
            $rows = Db::name('AnalyticsPageview')
                ->field('stat_date,count(distinct visitor_id) as uv')
                ->where('stat_date', 'in', $missing)
                ->group('stat_date')
                ->select();
            foreach ($rows as $row) {
                $map[substr((string)$row['stat_date'], 0, 10)] = intval($row['uv']);
            }
        }

        $count = [];
        $sum = 0;
        foreach ($days as $day) {
            $uv = isset($map[$day]) ? $map[$day] : 0;
            $count[] = $uv;
            $sum += $uv;
        }

        return ['days' => $days, 'count' => $count, 'sum' => $sum];
    }

    public static function aggregateHour($hourTs)
    {
        if (empty($hourTs) || $hourTs <= 0) {
            return ['code' => 0, 'msg' => 'invalid hour'];
        }
        $start = intval($hourTs);
        $end = $start + 3599;
        $statHour = date('Y-m-d H:00:00', $start);
        $now = time();

        $base = Db::name('AnalyticsPageview')
            ->alias('p')
            ->join('__ANALYTICS_SESSION__ s', 's.session_id = p.session_id', 'left')
            ->field('count(*) as pv,count(distinct p.visitor_id) as uv,count(distinct p.session_id) as session_cnt')
            ->where('p.ts', 'between', [$start, $end])
            ->find();

        self::upsertHourDim($statHour, 'all', '', $base, $now);

        $deviceRows = Db::name('AnalyticsPageview')
            ->alias('p')
            ->join('__ANALYTICS_SESSION__ s', 's.session_id = p.session_id', 'left')
            ->field("ifnull(nullif(s.device_type,''),'other') as dim_key,count(*) as pv,count(distinct p.visitor_id) as uv,count(distinct p.session_id) as session_cnt")
            ->where('p.ts', 'between', [$start, $end])
            ->group('dim_key')
            ->select();
        foreach ($deviceRows as $row) {
            self::upsertHourDim($statHour, 'device', $row['dim_key'], $row, $now);
        }

        return ['code' => 1, 'msg' => 'hour aggregated', 'hour' => $statHour];
    }

    public static function aggregateDay($statDate)
    {
        $start = strtotime($statDate . ' 00:00:00');
        $end = strtotime($statDate . ' 23:59:59');
        if ($start === false || $end === false) {
            return ['code' => 0, 'msg' => 'invalid date'];
        }
        $now = time();

        $pv = intval(Db::name('AnalyticsPageview')->where('stat_date', $statDate)->count());
        $uv = intval(Db::name('AnalyticsPageview')->where('stat_date', $statDate)->count('distinct visitor_id'));
        $sessionAgg = Db::name('AnalyticsSession')
            ->field('count(*) as session_cnt,ifnull(sum(duration_sec),0) as duration_sum,ifnull(sum(is_bounce),0) as bounce_cnt')
            ->where('started_at', 'between', [$start, $end])
            ->find();
        $sessionCnt = intval($sessionAgg['session_cnt']);
        $durationSum = intval($sessionAgg['duration_sum']);
        $bounceCnt = intval($sessionAgg['bounce_cnt']);
        $userMetrics = self::buildUserMetrics($start, $end);
        $retentionRates = self::buildRetentionRates($start, $end, $now);
        $orderAgg = Db::name('Order')
            ->field('count(*) as order_paid_cnt,ifnull(sum(order_price),0) as order_paid_amount')
            ->where('order_status', 1)
            ->where('order_pay_time', 'between', [$start, $end])
            ->find();

        $overviewData = [
            'pv' => $pv,
            'uv' => $uv,
            'session_cnt' => $sessionCnt,
            'new_reg' => $userMetrics['new_reg'],
            'user_login_dau' => $userMetrics['user_login_dau'],
            'user_active_mau' => $userMetrics['user_active_mau'],
            'order_paid_cnt' => intval($orderAgg['order_paid_cnt']),
            'order_paid_amount' => floatval($orderAgg['order_paid_amount']),
            'recharge_amount' => floatval($orderAgg['order_paid_amount']),
            'ad_impression' => intval(Db::name('AnalyticsEvent')->where('stat_date', $statDate)->where('event_code', 'ad_impression')->count()),
            'ad_click' => intval(Db::name('AnalyticsEvent')->where('stat_date', $statDate)->where('event_code', 'ad_click')->count()),
            'avg_session_duration_sec' => $sessionCnt > 0 ? intval(round($durationSum / $sessionCnt)) : 0,
            'bounce_rate' => $sessionCnt > 0 ? round($bounceCnt * 100 / $sessionCnt, 2) : 0,
            'retention_d1' => $retentionRates['retention_d1'],
            'retention_d7' => $retentionRates['retention_d7'],
            'retention_d30' => $retentionRates['retention_d30'],
            'pv_web' => self::countPvByDevice('web', $statDate),
            'pv_h5' => self::countPvByDevice('h5', $statDate),
            'pv_android' => self::countPvByDevice('android', $statDate),
            'pv_ios' => self::countPvByDevice('ios', $statDate),
            'pv_other' => self::countPvByDevice('other', $statDate),
            'updated_at' => $now,
        ];
        Db::startTrans();
        try {
            Db::name('AnalyticsDayOverview')->where('stat_date', $statDate)->delete();
            $overviewData['stat_date'] = $statDate;
            Db::name('AnalyticsDayOverview')->insert($overviewData);

            Db::name('AnalyticsDayDim')->where('stat_date', $statDate)->delete();
            self::insertDayDimsBySession($statDate, $start, $end, 'device', 'device_type', $now);
            self::insertDayDimsBySession($statDate, $start, $end, 'os', 'os', $now);
            self::insertDayDimsBySession($statDate, $start, $end, 'browser', 'browser', $now);
            self::insertDayDimsBySession($statDate, $start, $end, 'channel', 'channel', $now);
            self::insertDayDimsBySession($statDate, $start, $end, 'region', 'region_code', $now);
            self::insertDayDimsByCategory($statDate, $start, $end, $now);

            Db::name('AnalyticsContentDay')->where('stat_date', $statDate)->delete();
            // 分组 key 必须与 mac_analytics_content_day 的主键 (stat_date, mid, content_id) 一致，
            // 否则同一 mid+rid 出现两个 type_id（改分类/前端埋点 type_id=0 混入）时会产生两条
            // 主键相同的 insert，第二条报重复键错误，被下面的 catch 整天 rollback。
            // type_id 用 max() 折叠成一个代表值，优先取真实分类而不是 0。
            $contentRows = Db::name('AnalyticsPageview')
                ->alias('p')
                ->join('__ANALYTICS_SESSION__ s', 's.session_id = p.session_id', 'left')
                ->field('p.mid,p.rid as content_id,max(p.type_id) as type_id,count(*) as view_pv,count(distinct p.visitor_id) as view_uv,count(*) as play_or_read_cnt,ifnull(avg(p.stay_ms),0) as avg_stay_ms,ifnull(sum(case when s.is_bounce = 1 then 1 else 0 end),0) as bounce_cnt')
                ->where('p.stat_date', $statDate)
                ->where('p.mid', 'in', [1, 2, 8])
                ->where('p.rid', '>', 0)
                ->group('p.mid,p.rid')
                ->select();

            // 内容级转化/互动只能来自 mac_ulog —— mac_order 没有任何 mid/rid 字段，
            // 它是充值订单表，与「买了哪部片」无关。
            // ulog_type: 1=积分解锁/购买 2=收藏 3=想看
            $ulogRows = Db::name('Ulog')
                ->field('ulog_mid as mid,ulog_rid as content_id,ulog_type,count(*) as cnt,ifnull(sum(ulog_points),0) as points')
                ->where('ulog_time', 'between', [$start, $end])
                ->where('ulog_type', 'in', [1, 2, 3])
                ->where('ulog_rid', '>', 0)
                ->group('ulog_mid,ulog_rid,ulog_type')
                ->select();
            $ulogMap = [];
            foreach ($ulogRows as $u) {
                $k = intval($u['mid']) . ':' . intval($u['content_id']);
                if (!isset($ulogMap[$k])) {
                    $ulogMap[$k] = ['order_cnt' => 0, 'order_amount' => 0, 'collect_add' => 0, 'want_add' => 0];
                }
                $type = intval($u['ulog_type']);
                if ($type === 1) {
                    $ulogMap[$k]['order_cnt'] = intval($u['cnt']);
                    $ulogMap[$k]['order_amount'] = floatval($u['points']);
                } elseif ($type === 2) {
                    $ulogMap[$k]['collect_add'] = intval($u['cnt']);
                } elseif ($type === 3) {
                    $ulogMap[$k]['want_add'] = intval($u['cnt']);
                }
            }

            // 内容行取「有 pageview」与「有 ulog 互动」两者的并集：收藏/购买可能发生在列表页，
            // 当天该内容没有任何 detail 页 pageview，若只以 pageview 建行会把这笔转化静默丢弃。
            $pvKeys = [];
            foreach ($contentRows as $row) {
                $pvKeys[intval($row['mid']) . ':' . intval($row['content_id'])] = true;
            }

            $batch = [];
            foreach ($contentRows as $row) {
                $k = intval($row['mid']) . ':' . intval($row['content_id']);
                $extra = isset($ulogMap[$k]) ? $ulogMap[$k] : ['order_cnt' => 0, 'order_amount' => 0, 'collect_add' => 0, 'want_add' => 0];
                $batch[] = [
                    'stat_date' => $statDate,
                    'mid' => intval($row['mid']),
                    'content_id' => intval($row['content_id']),
                    'type_id' => intval($row['type_id']),
                    'view_pv' => intval($row['view_pv']),
                    'view_uv' => intval($row['view_uv']),
                    'play_or_read_cnt' => intval($row['play_or_read_cnt']),
                    'avg_stay_ms' => intval($row['avg_stay_ms']),
                    'bounce_cnt' => intval($row['bounce_cnt']),
                    'collect_add' => $extra['collect_add'],
                    'want_add' => $extra['want_add'],
                    'order_cnt' => $extra['order_cnt'],
                    'order_amount' => $extra['order_amount'],
                    'updated_at' => $now,
                ];
            }

            // ulog-only 行（当天无 pageview）：先按 mid 批量回填 type_id，避免逐行查询(N+1)
            $ulogOnlyKeys = [];
            foreach ($ulogMap as $k => $extra) {
                if (!isset($pvKeys[$k])) {
                    $ulogOnlyKeys[] = $k;
                }
            }
            $typeIdMap = self::lookupContentTypeIds($ulogOnlyKeys);
            foreach ($ulogOnlyKeys as $k) {
                $extra = $ulogMap[$k];
                list($midStr, $contentIdStr) = explode(':', $k);
                $batch[] = [
                    'stat_date' => $statDate,
                    'mid' => intval($midStr),
                    'content_id' => intval($contentIdStr),
                    'type_id' => isset($typeIdMap[$k]) ? $typeIdMap[$k] : 0,
                    'view_pv' => 0,
                    'view_uv' => 0,
                    'play_or_read_cnt' => 0,
                    'avg_stay_ms' => 0,
                    'bounce_cnt' => 0,
                    'collect_add' => $extra['collect_add'],
                    'want_add' => $extra['want_add'],
                    'order_cnt' => $extra['order_cnt'],
                    'order_amount' => $extra['order_amount'],
                    'updated_at' => $now,
                ];
            }

            // 分块批量写入，缩短事务持有时间、减少数据库往返
            foreach (array_chunk($batch, 500) as $chunk) {
                Db::name('AnalyticsContentDay')->insertAll($chunk);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => 0, 'msg' => 'day aggregate failed: ' . $e->getMessage(), 'date' => $statDate];
        }

        return ['code' => 1, 'msg' => 'day aggregated', 'date' => $statDate];
    }

    private static function upsertHourDim($statHour, $dimType, $dimKey, $row, $now)
    {
        $prefix = Db::getConfig('prefix');
        $table = $prefix . 'analytics_hour_dim';
        Db::execute(
            "INSERT INTO `{$table}` (`stat_hour`,`dim_type`,`dim_key`,`pv`,`uv`,`session_cnt`,`updated_at`) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `pv`=VALUES(`pv`),`uv`=VALUES(`uv`),`session_cnt`=VALUES(`session_cnt`),`updated_at`=VALUES(`updated_at`)",
            [
                $statHour,
                (string)$dimType,
                (string)$dimKey,
                intval($row['pv']),
                intval($row['uv']),
                intval($row['session_cnt']),
                intval($now),
            ]
        );
    }

    private static function insertDayDimsBySession($statDate, $start, $end, $dimType, $field, $now)
    {
        $rows = Db::name('AnalyticsPageview')
            ->alias('p')
            ->join('__ANALYTICS_SESSION__ s', 's.session_id = p.session_id', 'left')
            ->field("ifnull(nullif(s.`{$field}`,''),'other') as dim_key,count(*) as pv,count(distinct p.visitor_id) as uv,count(distinct p.session_id) as session_cnt")
            ->where('p.ts', 'between', [$start, $end])
            ->group('dim_key')
            ->select();
        foreach ($rows as $row) {
            Db::name('AnalyticsDayDim')->insert([
                'stat_date' => $statDate,
                'dim_type' => $dimType,
                'dim_key' => (string)$row['dim_key'],
                'pv' => intval($row['pv']),
                'uv' => intval($row['uv']),
                'session_cnt' => intval($row['session_cnt']),
                'new_reg' => 0,
                'dau' => 0,
                'order_paid_cnt' => 0,
                'order_paid_amount' => 0,
                'ad_click' => 0,
                'updated_at' => $now,
            ]);
        }
    }

    private static function insertDayDimsByCategory($statDate, $start, $end, $now)
    {
        $rows = Db::name('AnalyticsPageview')
            ->field("concat('type_',type_id) as dim_key,count(*) as pv,count(distinct visitor_id) as uv,count(distinct session_id) as session_cnt")
            ->where('ts', 'between', [$start, $end])
            ->where('type_id', '>', 0)
            ->group('type_id')
            ->select();
        foreach ($rows as $row) {
            Db::name('AnalyticsDayDim')->insert([
                'stat_date' => $statDate,
                'dim_type' => 'category',
                'dim_key' => (string)$row['dim_key'],
                'pv' => intval($row['pv']),
                'uv' => intval($row['uv']),
                'session_cnt' => intval($row['session_cnt']),
                'new_reg' => 0,
                'dau' => 0,
                'order_paid_cnt' => 0,
                'order_paid_amount' => 0,
                'ad_click' => 0,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 按内容 key（"mid:content_id"）批量回填 type_id，返回 [key => type_id]。
     * 按 mid 分组后每个模块一次 whereIn 查询，取代逐行查询，消除 N+1。
     */
    private static function lookupContentTypeIds($keys)
    {
        $modelMap = [1 => ['Vod', 'vod_id'], 2 => ['Art', 'art_id'], 8 => ['Manga', 'manga_id']];
        $byMid = [];
        foreach ($keys as $k) {
            list($midStr, $cidStr) = explode(':', $k);
            $mid = intval($midStr);
            if (!isset($modelMap[$mid])) {
                continue;
            }
            $byMid[$mid][] = intval($cidStr);
        }
        $result = [];
        foreach ($byMid as $mid => $ids) {
            list($modelName, $pk) = $modelMap[$mid];
            $ids = array_values(array_unique($ids));
            // column('type_id', $pk) 返回 [pk值 => type_id]
            $rows = Db::name($modelName)->where($pk, 'in', $ids)->column('type_id', $pk);
            foreach ($ids as $cid) {
                $result[$mid . ':' . $cid] = isset($rows[$cid]) ? intval($rows[$cid]) : 0;
            }
        }
        return $result;
    }

    private static function countPvByDevice($device, $statDate)
    {
        $query = Db::name('AnalyticsPageview')
            ->alias('p')
            ->join('__ANALYTICS_SESSION__ s', 's.session_id = p.session_id', 'left')
            ->where('p.stat_date', $statDate);
        if ($device === 'other') {
            $query->whereRaw("coalesce(nullif(s.device_type,''),'other') not in ('web','h5','android','ios')");
        } else {
            $query->where('s.device_type', $device);
        }
        return intval($query->count());
    }

    private static function buildUserMetrics($start, $end)
    {
        $mauStart = strtotime('-29 day', $start);
        return [
            'new_reg' => intval(Db::name('User')->where('user_reg_time', 'between', [$start, $end])->count()),
            'user_login_dau' => intval(Db::name('User')->where('user_login_time', 'between', [$start, $end])->count()),
            'user_active_mau' => intval(Db::name('User')->where('user_login_time', 'between', [$mauStart, $end])->count()),
        ];
    }

    private static function buildRetentionRates($start, $end, $now)
    {
        $rates = [
            'retention_d1' => 0.00,
            'retention_d7' => 0.00,
            'retention_d30' => 0.00,
        ];
        $map = [
            1 => 'retention_d1',
            7 => 'retention_d7',
            30 => 'retention_d30',
        ];
        foreach ($map as $returnDay => $field) {
            $rates[$field] = self::calcRetentionRate($returnDay, $start, $end, $now);
        }
        return $rates;
    }

    private static function calcRetentionRate($returnDay, $targetStart, $targetEnd, $now)
    {
        $cohortStart = strtotime("-{$returnDay} day", $targetStart);
        $cohortEnd = strtotime("-{$returnDay} day", $targetEnd);
        $cohortDate = date('Y-m-d', $cohortStart);

        $cohortTotal = intval(Db::name('User')->where('user_reg_time', 'between', [$cohortStart, $cohortEnd])->count());
        if ($cohortTotal <= 0) {
            self::upsertRetentionCohort($cohortDate, $returnDay, 0, $now);
            return 0.00;
        }

        $returned = intval(Db::name('User')
            ->alias('u')
            ->join('__ANALYTICS_SESSION__ s', 's.user_id = u.user_id', 'inner')
            ->where('u.user_reg_time', 'between', [$cohortStart, $cohortEnd])
            ->where('s.started_at', 'between', [$targetStart, $targetEnd])
            ->count('distinct u.user_id'));

        self::upsertRetentionCohort($cohortDate, $returnDay, $returned, $now);
        return round($returned * 100 / $cohortTotal, 2);
    }

    private static function upsertRetentionCohort($cohortDate, $returnDay, $userCnt, $now)
    {
        $prefix = Db::getConfig('prefix');
        $table = $prefix . 'analytics_retention_cohort';
        Db::execute(
            "INSERT INTO `{$table}` (`cohort_date`,`cohort_type`,`return_day`,`user_cnt`,`updated_at`) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE `user_cnt`=VALUES(`user_cnt`),`updated_at`=VALUES(`updated_at`)",
            [$cohortDate, 'register', intval($returnDay), intval($userCnt), intval($now)]
        );
    }
}
