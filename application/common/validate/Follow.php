<?php
namespace app\common\validate;
use think\Validate;

class Follow extends Validate
{
    protected $rule = [
        'user_id'    => 'require|number|gt:0',
        'follow_uid' => 'require|number|gt:0',
        'follow_id'  => 'require|number',
    ];

    protected $scene = [
        'save' => ['user_id', 'follow_uid'],
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'user_id.require'    => lang('validate/follow_user_require'),
            'user_id.number'     => lang('validate/follow_user_number'),
            'user_id.gt'         => lang('validate/follow_user_gt'),
            'follow_uid.require' => lang('validate/follow_target_require'),
            'follow_uid.number'  => lang('validate/follow_target_number'),
            'follow_uid.gt'      => lang('validate/follow_target_gt'),
            'follow_id.require'  => lang('validate/follow_id_require'),
            'follow_id.number'   => lang('validate/follow_id_number'),
        ];
        parent::__construct($rules, $message, $field);
    }
}