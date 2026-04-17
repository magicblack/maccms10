<?php
namespace app\api\controller;
use think\Request;

class Task extends Base
{
    use PublicApi;

    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    /**
     * 获取任务列表及用户完成状态
     * GET /api.php/Task/get_task_list
     */
    public function get_task_list(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $user_info = $check['info'];

        $task_status = model('TaskLog')->getUserTaskStatus($user_id, $user_info);
        $sign_info = model('SignLog')->getSignInfo($user_id);

        return json([
            'code' => 1,
            'msg' => lang('obtain_ok'),
            'info' => [
                'daily_tasks' => $task_status['daily_tasks'],
                'newbie_tasks' => $task_status['newbie_tasks'],
                'sign_info' => $sign_info,
                'today_earned' => $task_status['today_earned'],
                'user_points' => intval($user_info['user_points']),
            ],
        ]);
    }

    /**
     * 每日签到
     * POST /api.php/Task/daily_sign
     */
    public function daily_sign(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $res = model('SignLog')->doSign($user_id);
        return json($res);
    }

    /**
     * 获取签到信息（含里程碑）
     * GET /api.php/Task/get_sign_info
     */
    public function get_sign_info(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $sign_info = model('SignLog')->getSignInfo($user_id);

        return json([
            'code' => 1,
            'msg' => lang('obtain_ok'),
            'info' => $sign_info,
        ]);
    }

    /**
     * 领取签到里程碑奖励
     * POST /api.php/Task/claim_sign_milestone
     * @param milestone_id 里程碑ID
     */
    public function claim_sign_milestone(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $param = $request->param();

        $milestone_id = intval($param['milestone_id']);
        if ($milestone_id <= 0) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        // 获取用户当前连续签到天数
        $sign_info = model('SignLog')->getSignInfo($user_id);
        $serial_days = $sign_info['serial_days'];

        $res = model('SignMilestone')->claimMilestone($user_id, $milestone_id, $serial_days);
        return json($res);
    }

    /**
     * 领取任务奖励
     * POST /api.php/Task/claim_reward
     * @param task_id 任务ID
     */
    public function claim_reward(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $param = $request->param();

        $task_id = intval($param['task_id']);
        if ($task_id <= 0) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $res = model('TaskLog')->claimReward($user_id, $task_id);
        return json($res);
    }

    /**
     * 上报每日任务进度
     * POST /api.php/Task/report_progress
     * @param task_action 任务动作标识 (watch_vod/share_vod/post_comment)
     */
    public function report_progress(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('task/login_required')]);
        }
        $user_id = intval($check['info']['user_id']);
        $param = $request->param();

        $task_action = trim($param['task_action']);
        $allowed = ['watch_vod', 'share_vod', 'post_comment'];
        if (!in_array($task_action, $allowed)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $res = model('TaskLog')->addProgress($user_id, $task_action, 1);
        return json($res);
    }
}
