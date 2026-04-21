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
            self::insertDayDimsBySession($statDate, $start, $end, 'channel', 'channel', $now);
            self::insertDayDimsBySession($statDate, $start, $end, 'region', 'region_code', $now);
            self::insertDayDimsByCategory($statDate, $start, $end, $now);

            Db::name('AnalyticsContentDay')->where('stat_date', $statDate)->delete();
            $contentRows = Db::name('AnalyticsPageview')
                ->field('mid,rid as content_id,type_id,count(*) as view_pv,count(distinct visitor_id) as view_uv,count(*) as play_or_read_cnt,ifnull(avg(stay_ms),0) as avg_stay_ms')
                ->where('stat_date', $statDate)
                ->where('mid', 'in', [1, 2, 8])
                ->where('rid', '>', 0)
                ->group('mid,rid,type_id')
                ->select();
            foreach ($contentRows as $row) {
                Db::name('AnalyticsContentDay')->insert([
                    'stat_date' => $statDate,
                    'mid' => intval($row['mid']),
                    'content_id' => intval($row['content_id']),
                    'type_id' => intval($row['type_id']),
                    'view_pv' => intval($row['view_pv']),
                    'view_uv' => intval($row['view_uv']),
                    'play_or_read_cnt' => intval($row['play_or_read_cnt']),
                    'avg_stay_ms' => intval($row['avg_stay_ms']),
                    'bounce_cnt' => 0,
                    'collect_add' => 0,
                    'want_add' => 0,
                    'order_cnt' => 0,
                    'order_amount' => 0,
                    'updated_at' => $now,
                ]);
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
