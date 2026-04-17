<?php
namespace app\api\validate;
use think\Validate;

class Task extends Validate
{
    protected $rule = [
        'task_id' => 'require|number',
        'task_action' => 'require|alphaDash',
    ];

    protected $message = [
        'task_id.require' => 'task_id required',
        'task_id.number' => 'task_id must be number',
        'task_action.require' => 'task_action required',
        'task_action.alphaDash' => 'task_action invalid',
    ];

    protected $scene = [
        'claim_reward' => ['task_id'],
        'report_progress' => ['task_action'],
    ];
}
