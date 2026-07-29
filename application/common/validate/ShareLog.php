<?php
namespace app\common\validate;
use think\Validate;

class ShareLog extends Validate
{
    protected $rule = [
        'share_mid'      => 'require|number|gt:0',
        'share_rid'      => 'require|number|gt:0',
        'share_platform' => 'require|max:30',
    ];

    protected $scene = [
        'save' => ['share_mid', 'share_rid', 'share_platform'],
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'share_mid.require'      => lang('validate/share_mid_require'),
            'share_mid.number'       => lang('param_err'),
            'share_mid.gt'           => lang('param_err'),
            'share_rid.require'      => lang('validate/share_rid_require'),
            'share_rid.number'       => lang('param_err'),
            'share_rid.gt'           => lang('param_err'),
            'share_platform.require' => lang('validate/share_platform_require'),
            'share_platform.max'     => lang('param_err'),
        ];
        parent::__construct($rules, $message, $field);
    }
}