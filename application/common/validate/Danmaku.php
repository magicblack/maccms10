<?php
namespace app\common\validate;
use think\Validate;

class Danmaku extends Validate
{
    protected $rule = [
        'vod_id'       => 'require|number',
        'sid'          => 'require|number',
        'nid'          => 'require|number',
        'time'         => 'require|float',
        'text'         => 'require|max:200',
        'type'         => 'number|in:0,1,2',
        'danmaku_id'   => 'require|number',
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'vod_id.require'     => lang('validate/danmaku_vod_require'),
            'vod_id.number'      => lang('validate/danmaku_vod_number'),
            'sid.require'        => lang('validate/danmaku_sid_require'),
            'sid.number'         => lang('validate/danmaku_sid_number'),
            'nid.require'        => lang('validate/danmaku_nid_require'),
            'nid.number'         => lang('validate/danmaku_nid_number'),
            'time.require'       => lang('validate/danmaku_time_require'),
            'time.float'         => lang('validate/danmaku_time_float'),
            'text.require'       => lang('validate/danmaku_text_require'),
            'text.max'           => lang('validate/danmaku_text_max'),
            'type.in'            => lang('validate/danmaku_type_in'),
            'danmaku_id.require' => lang('validate/danmaku_id_require'),
            'danmaku_id.number'  => lang('validate/danmaku_id_number'),
        ];
        parent::__construct($rules, $message, $field);
    }
}
