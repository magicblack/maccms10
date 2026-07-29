<?php
namespace app\common\validate;
use think\Validate;

class Dynamics extends Validate
{
    protected $rule = [
        'user_id'       => 'require|number|gt:0',
        'dynamics_type' => 'require',
    ];

    protected $scene = [
        'save' => ['user_id', 'dynamics_type'],
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'user_id.require'       => lang('param_err'),
            'user_id.number'        => lang('param_err'),
            'user_id.gt'            => lang('param_err'),
            'dynamics_type.require' => lang('param_err'),
        ];
        parent::__construct($rules, $message, $field);
    }
}