<?php
namespace app\common\model;
use think\Db;

class TaskLog extends Base {
    protected $name = 'task_log';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto   = [];
    protected $insert = [];
    protected $update = [];

    public function countData($where)
    {
        return $this->where($where)->count();
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0, $field = '*')
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('TaskLog')->field($field)->where($where)->order($order)->limit($limit_str)->select();
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

    /**
     * 获取或创建用户的每日任务记录
     */
    public function getOrCreateDaily($user_id, $task_id, $task_action, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $where = [
            'user_id' => $user_id,
            'task_id' => $task_id,
            'log_date' => $date,
        ];
        $log = Db::name('TaskLog')->where($where)->find();
        if ($log) {
            return $log;
        }
        $data = [
            'user_id' => $user_id,
            'task_id' => $task_id,
            'task_action' => $task_action,
            'log_progress' => 0,
            'log_status' => 0,
            'log_points' => 0,
            'log_date' => $date,
            'log_time' => time(),
            'log_claim_time' => 0,
        ];
        Db::name('TaskLog')->insert($data);
        $data['log_id'] = Db::name('TaskLog')->getLastInsID();
        return $data;
    }

    /**
     * 获取用户新手任务记录（固定日期 2000-01-01）
     */
    public function getOrCreateNewbie($user_id, $task_id, $task_action)
    {
        return $this->getOrCreateDaily($user_id, $task_id, $task_action, '2000-01-01');
    }

    /**
     * 增加每日任务进度
     */
    public function addProgress($user_id, $task_action, $increment = 1)
    {
        $task = Db::name('Task')->where(['task_action' => $task_action, 'task_status' => 1])->find();
        if (!$task) {
            return ['code' => 1001, 'msg' => lang('task/not_found')];
        }
        $date = date('Y-m-d');
        $log = $this->getOrCreateDaily($user_id, $task['task_id'], $task_action, $date);

        if ($log['log_status'] >= 1) {
            return ['code' => 1, 'msg' => lang('task/already_done'), 'info' => $log];
        }

        $new_progress = min($log['log_progress'] + $increment, $task['task_target']);
        $update = ['log_progress' => $new_progress];
        if ($new_progress >= $task['task_target']) {
            $update['log_status'] = 1;
        }
        Db::name('TaskLog')->where('log_id', $log['log_id'])->update($update);
        $log = array_merge($log, $update);
        return ['code' => 1, 'msg' => 'ok', 'info' => $log];
    }

    /**
     * 领取任务奖励
     */
    public function claimReward($user_id, $task_id)
    {
        $task = Db::name('Task')->where(['task_id' => $task_id, 'task_status' => 1])->find();
        if (!$task) {
            return ['code' => 1001, 'msg' => lang('task/not_found')];
        }

        $date = ($task['task_type'] == 1) ? date('Y-m-d') : '2000-01-01';
        $where = [
            'user_id' => $user_id,
            'task_id' => $task_id,
            'log_date' => $date,
        ];
        $log = Db::name('TaskLog')->where($where)->find();
        if (!$log) {
            return ['code' => 1002, 'msg' => lang('task/not_completed')];
        }
        if ($log['log_status'] == 0) {
            return ['code' => 1003, 'msg' => lang('task/not_completed')];
        }
        if ($log['log_status'] == 2) {
            return ['code' => 1004, 'msg' => lang('task/already_claimed')];
        }

        // 发放积分（事务保护，与 SignLog::doSign / SignMilestone::claimMilestone 一致）
        $points = $task['task_points'];

        Db::startTrans();
        try {
            Db::name('TaskLog')->where('log_id', $log['log_id'])->update([
                'log_status' => 2,
                'log_points' => $points,
                'log_claim_time' => time(),
            ]);

            $inc = Db::name('User')->where('user_id', $user_id)->setInc('user_points', $points);
            if ($inc === false) {
                throw new \Exception('user_points');
            }

            // 积分日志 plog_type=11 任务/签到奖励（与 SignLog::doSign 一致；9 保留为提现）
            $plog = [
                'user_id' => $user_id,
                'plog_type' => 11,
                'plog_points' => $points,
                'plog_remarks' => lang('task/reward_log', [$task['task_name'], $points]),
            ];
            $plogRes = model('Plog')->saveData($plog);
            if (empty($plogRes['code']) || (int)$plogRes['code'] !== 1) {
                throw new \Exception('plog');
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 1005, 'msg' => lang('save_err')];
        }

        return ['code' => 1, 'msg' => lang('task/claim_ok'), 'info' => ['points' => $points]];
    }

    /**
     * 获取用户今日所有任务状态
     */
    public function getUserTaskStatus($user_id, $user_info = [])
    {
        $tasks = model('Task')->getActiveTasks();
        $today = date('Y-m-d');

        // 取今日每日任务记录
        $daily_logs = Db::name('TaskLog')->where([
            'user_id' => $user_id,
            'log_date' => $today,
        ])->select();
        $daily_map = [];
        foreach ($daily_logs as $l) {
            $daily_map[$l['task_id']] = $l;
        }

        // 取新手任务记录
        $newbie_logs = Db::name('TaskLog')->where([
            'user_id' => $user_id,
            'log_date' => '2000-01-01',
        ])->select();
        $newbie_map = [];
        foreach ($newbie_logs as $l) {
            $newbie_map[$l['task_id']] = $l;
        }

        // 组装每日任务
        $daily_result = [];
        foreach ($tasks['daily'] as $t) {
            $log = isset($daily_map[$t['task_id']]) ? $daily_map[$t['task_id']] : null;
            $t['progress'] = $log ? (int)$log['log_progress'] : 0;
            $t['status'] = $log ? (int)$log['log_status'] : 0;
            $daily_result[] = $t;
        }

        // 组装新手任务（检测型策略A）
        $newbie_result = [];
        foreach ($tasks['newbie'] as $t) {
            $log = isset($newbie_map[$t['task_id']]) ? $newbie_map[$t['task_id']] : null;
            $detected = $this->detectNewbieCompletion($t['task_action'], $user_info);

            if ($detected && (!$log || $log['log_status'] == 0)) {
                // 检测到已完成，自动更新记录
                $log_record = $this->getOrCreateNewbie($user_id, $t['task_id'], $t['task_action']);
                Db::name('TaskLog')->where('log_id', $log_record['log_id'])->update([
                    'log_progress' => $t['task_target'],
                    'log_status' => 1,
                ]);
                $t['progress'] = (int)$t['task_target'];
                $t['status'] = 1;
            } else {
                $t['progress'] = $log ? (int)$log['log_progress'] : 0;
                $t['status'] = $log ? (int)$log['log_status'] : 0;
            }
            $newbie_result[] = $t;
        }

        // 今日已获积分
        $today_earned = (int)Db::name('TaskLog')->where([
            'user_id' => $user_id,
            'log_date' => $today,
            'log_status' => 2,
        ])->sum('log_points');

        return [
            'daily_tasks' => $daily_result,
            'newbie_tasks' => $newbie_result,
            'today_earned' => $today_earned,
        ];
    }

    /**
     * 检测新手任务是否已完成（策略A - 零侵入）
     */
    protected function detectNewbieCompletion($action, $user_info)
    {
        if (empty($user_info)) return false;

        switch ($action) {
            case 'bind_phone':
                return !empty($user_info['user_phone']);
            case 'bind_email':
                return !empty($user_info['user_email']);
            case 'set_portrait':
                return !empty($user_info['user_portrait']);
            case 'complete_profile':
                return !empty($user_info['user_nick_name']);
            case 'first_pay':
                $order_count = Db::name('Order')->where([
                    'user_id' => $user_info['user_id'],
                    'order_status' => 1,
                ])->count();
                return $order_count > 0;
            default:
                return false;
        }
    }
}
