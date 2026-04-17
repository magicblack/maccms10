<?php
namespace app\common\validate;
use think\Validate;

class Task extends Validate
{
    protected $rule = [
        'task_name' => 'require|max:100',
        'task_type' => 'require|in:1,2',
        'task_action' => 'require|alphaDash|max:50',
        'task_points' => 'require|number|egt:0',
        'task_target' => 'require|number|egt:1',
    ];

    protected $message = [
        'task_name.require' => '任务名称不能为空',
        'task_type.require' => '任务类型不能为空',
        'task_type.in' => '任务类型不正确',
        'task_action.require' => '任务标识不能为空',
        'task_points.require' => '奖励积分不能为空',
        'task_target.require' => '目标次数不能为空',
    ];

    protected $scene = [
        'add' => ['task_name', 'task_type', 'task_action', 'task_points', 'task_target'],
        'edit' => ['task_name', 'task_type', 'task_points', 'task_target'],
    ];
}
