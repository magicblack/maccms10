<?php
namespace app\common\validate;
use think\Validate;

class Chatroom extends Validate
{
    protected $rule = [
        'vod_id'       => 'require|number',
        'chat_content' => 'require|max:500',
        'chat_id'      => 'require|number',
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'vod_id.require'       => lang('validate/chatroom_vod_require'),
            'vod_id.number'        => lang('validate/chatroom_vod_number'),
            'chat_content.require' => lang('validate/chatroom_content_require'),
            'chat_content.max'     => lang('validate/chatroom_content_max'),
            'chat_id.require'      => lang('validate/chatroom_id_require'),
            'chat_id.number'       => lang('validate/chatroom_id_number'),
        ];
        parent::__construct($rules, $message, $field);
    }
}
