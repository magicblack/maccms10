<?php
namespace app\common\model;
use think\Db;

class SignLog extends Base {
    protected $name = 'sign_log';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto   = [];
    protected $insert = [];
    protected $update = [];

    /**
     * 每日签到
     */
    public function doSign($user_id)
    {
        $today = date('Y-m-d');
        $exists = Db::name('SignLog')->where(['user_id' => $user_id, 'sign_date' => $today])->find();
        if ($exists) {
            return ['code' => 1001, 'msg' => lang('task/already_signed')];
        }

        // 计算连续天数
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterday_log = Db::name('SignLog')->where(['user_id' => $user_id, 'sign_date' => $yesterday])->find();
        $serial_days = $yesterday_log ? (int)$yesterday_log['sign_serial_days'] + 1 : 1;

        // 取签到任务积分
        $task = Db::name('Task')->where(['task_action' => 'daily_sign', 'task_status' => 1])->find();
        $base_points = $task ? (int)$task['task_points'] : 5;

        $total_points = $base_points;

        Db::startTrans();
        try {
            // 插入签到记录
            Db::name('SignLog')->insert([
                'user_id' => $user_id,
                'sign_date' => $today,
                'sign_time' => time(),
                'sign_points' => $total_points,
                'sign_serial_days' => $serial_days,
            ]);

            // 发放签到积分到用户余额（与任务中心「领取奖励」一致，并写积分流水）
            $inc = Db::name('User')->where('user_id', $user_id)->setInc('user_points', $total_points);
            if ($inc === false) {
                throw new \Exception('user_points');
            }

            $taskName = $task ? (string)$task['task_name'] : lang('task/daily_sign_task');
            $plog = [
                'user_id' => $user_id,
                'plog_type' => 11,
                'plog_points' => $total_points,
                'plog_remarks' => lang('task/reward_log', [$taskName, $total_points]),
            ];
            $plogRes = model('Plog')->saveData($plog);
            if (empty($plogRes['code']) || (int)$plogRes['code'] !== 1) {
                throw new \Exception('plog');
            }

            // 同步每日签到任务进度；若本次已达成且积分已在上方发放，则标记为已领取，避免任务中心重复领
            if ($task) {
                model('TaskLog')->addProgress($user_id, 'daily_sign', 1);
                $log = Db::name('TaskLog')->where([
                    'user_id' => $user_id,
                    'task_id' => $task['task_id'],
                    'log_date' => $today,
                ])->find();
                if ($log && (int)$log['log_status'] === 1) {
                    Db::name('TaskLog')->where('log_id', $log['log_id'])->update([
                        'log_status' => 2,
                        'log_points' => (int)$task['task_points'],
                        'log_claim_time' => time(),
                    ]);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 1002, 'msg' => lang('save_err')];
        }

        // 检查是否有新达成的里程碑
        $milestone_info = model('SignMilestone')->getMilestonesWithStatus($user_id, $serial_days);
        $claimable_count = 0;
        foreach ($milestone_info['milestones'] as $m) {
            if ($m['status'] == 1) {
                $claimable_count++;
            }
        }

        return [
            'code' => 1,
            'msg' => lang('task/sign_ok'),
            'info' => [
                'points' => $total_points,
                'base_points' => $base_points,
                'serial_days' => $serial_days,
                'claimable_milestones' => $claimable_count,
            ]
        ];
    }

    /**
     * 获取签到信息（含里程碑）
     */
    public function getSignInfo($user_id)
    {
        $today = date('Y-m-d');
        $today_log = Db::name('SignLog')->where(['user_id' => $user_id, 'sign_date' => $today])->find();
        $is_signed = !empty($today_log);

        // 连续天数
        $serial_days = 0;
        if ($is_signed) {
            $serial_days = (int)$today_log['sign_serial_days'];
        } else {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $yesterday_log = Db::name('SignLog')->where(['user_id' => $user_id, 'sign_date' => $yesterday])->find();
            $serial_days = $yesterday_log ? (int)$yesterday_log['sign_serial_days'] : 0;
        }

        // 本月签到记录
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $month_logs = Db::name('SignLog')->where([
            'user_id' => $user_id,
            'sign_date' => ['between', [$month_start, $month_end]],
        ])->order('sign_date asc')->select();

        $month_dates = [];
        $month_total_points = 0;
        foreach ($month_logs as $l) {
            $month_dates[] = $l['sign_date'];
            $month_total_points += (int)$l['sign_points'];
        }

        // 总签到次数
        $total_signs = (int)Db::name('SignLog')->where('user_id', $user_id)->count();

        // 里程碑信息
        $milestone_info = model('SignMilestone')->getMilestonesWithStatus($user_id, $serial_days);

        return [
            'is_signed_today' => $is_signed,
            'serial_days' => $serial_days,
            'total_signs' => $total_signs,
            'month_signs' => count($month_dates),
            'month_dates' => $month_dates,
            'month_total_points' => $month_total_points,
            'milestones' => $milestone_info['milestones'],
            'next_milestone' => $milestone_info['next_milestone'],
        ];
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('SignLog')->where($where)->order($order)->limit($limit_str)->select();
        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('del_ok')];
    }
}
