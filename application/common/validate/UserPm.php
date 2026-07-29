<?php
namespace app\common\validate;
use think\Validate;

class UserPm extends Validate
{
    protected $rule = [
        'from_uid'    => 'require|number|gt:0',
        'to_uid'      => 'require|number|gt:0',
        'pm_content'  => 'require|max:500',
    ];

    protected $scene = [
        'save' => ['from_uid', 'to_uid', 'pm_content'],
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'from_uid.require'   => lang('param_err'),
            'from_uid.number'    => lang('param_err'),
            'from_uid.gt'        => lang('param_err'),
            'to_uid.require'     => lang('validate/pm_to_require'),
            'to_uid.number'      => lang('validate/pm_to_number'),
            'to_uid.gt'          => lang('param_err'),
            'pm_content.require' => lang('validate/pm_content_require'),
            'pm_content.max'     => lang('validate/pm_content_max'),
        ];
        parent::__construct($rules, $message, $field);
    }
}