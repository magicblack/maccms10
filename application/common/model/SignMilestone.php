<?php
namespace app\common\model;
use think\Db;

class SignMilestone extends Base {
    protected $name = 'sign_milestone';
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
        $list = Db::name('SignMilestone')->field($field)->where($where)->order($order)->limit($limit_str)->select();
        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    public function infoData($where, $field = '*')
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $info = $info->toArray();
        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function saveData($data)
    {
        $data['milestone_time'] = time();
        if (!empty($data['milestone_id'])) {
            $where = [];
            $where['milestone_id'] = ['eq', $data['milestone_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        } else {
            $data['milestone_time_add'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if (false === $res) {
            return ['code' => 1002, 'msg' => lang('save_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('save_ok')];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('del_ok')];
    }

    public function fieldData($where, $col, $val)
    {
        if (!isset($col) || !isset($val)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('set_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 获取所有启用的里程碑，按天数升序
     */
    public function getActiveMilestones()
    {
        return Db::name('SignMilestone')
            ->where(['milestone_status' => 1])
            ->order('milestone_days asc')
            ->select();
    }

    /**
     * 获取用户的里程碑达成状态
     * @param int $user_id
     * @param int $serial_days 当前连续签到天数
     * @return array 里程碑列表（含 status: 0=未达成, 1=可领取, 2=已领取）
     */
    public function getMilestonesWithStatus($user_id, $serial_days)
    {
        $milestones = $this->getActiveMilestones();

        // 获取用户已领取的里程碑
        $claimed = Db::name('SignMilestoneLog')
            ->where('user_id', $user_id)
            ->column('milestone_id');
        $claimed_map = array_flip($claimed);

        $result = [];
        $next_milestone = null;
        foreach ($milestones as $m) {
            if (isset($claimed_map[$m['milestone_id']])) {
                $m['status'] = 2; // 已领取
            } elseif ($serial_days >= $m['milestone_days']) {
                $m['status'] = 1; // 可领取
            } else {
                $m['status'] = 0; // 未达成
                if ($next_milestone === null) {
                    $next_milestone = [
                        'days' => (int)$m['milestone_days'],
                        'points' => (int)$m['milestone_points'],
                    ];
                }
            }
            $result[] = $m;
        }

        return [
            'milestones' => $result,
            'next_milestone' => $next_milestone,
        ];
    }

    /**
     * 领取里程碑奖励
     */
    public function claimMilestone($user_id, $milestone_id, $serial_days)
    {
        $milestone = Db::name('SignMilestone')
            ->where(['milestone_id' => $milestone_id, 'milestone_status' => 1])
            ->find();

        if (!$milestone) {
            return ['code' => 1001, 'msg' => lang('milestone/not_found')];
        }

        // 检查连续天数是否达标
        if ($serial_days < $milestone['milestone_days']) {
            return ['code' => 1002, 'msg' => lang('milestone/not_reached')];
        }

        // 检查是否已领取
        $exists = Db::name('SignMilestoneLog')->where([
            'user_id' => $user_id,
            'milestone_id' => $milestone_id,
        ])->find();
        if ($exists) {
            return ['code' => 1003, 'msg' => lang('milestone/already_claimed')];
        }

        $points = (int)$milestone['milestone_points'];

        Db::startTrans();
        try {
            Db::name('SignMilestoneLog')->insert([
                'user_id' => $user_id,
                'milestone_id' => $milestone_id,
                'milestone_days' => $milestone['milestone_days'],
                'log_points' => $points,
                'log_time' => time(),
            ]);

            $inc = Db::name('User')->where('user_id', $user_id)->setInc('user_points', $points);
            if ($inc === false) {
                throw new \Exception('user_points');
            }

            $plog = [
                'user_id' => $user_id,
                'plog_type' => 10,
                'plog_points' => $points,
                'plog_remarks' => lang('milestone/reward_log', [$milestone['milestone_days'], $points]),
            ];
            $plogRes = model('Plog')->saveData($plog);
            if (empty($plogRes['code']) || (int)$plogRes['code'] !== 1) {
                throw new \Exception('plog');
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 1004, 'msg' => lang('save_err')];
        }

        return [
            'code' => 1,
            'msg' => lang('milestone/claim_ok'),
            'info' => ['points' => $points, 'milestone_days' => (int)$milestone['milestone_days']],
        ];
    }
}
