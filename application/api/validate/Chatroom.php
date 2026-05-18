<?php
namespace app\api\validate;
use think\Validate;

class Chatroom extends Validate
{
    protected $rule = [
        'vod_id'       => 'require|number',
        'content'      => 'require|max:500',
        'chat_id'      => 'require|number',
        'after_id'     => 'number',
        'limit'        => 'number|between:1,100',
    ];

    protected $scene = [
        'get_list' => ['vod_id'],
        'send'     => ['vod_id', 'content'],
        'report'   => ['chat_id'],
    ];

    public function __construct(array $rules = [], array $message = [], array $field = [])
    {
        $this->message = [
            'vod_id.require'   => lang('validate/chatroom_vod_require'),
            'vod_id.number'    => lang('validate/chatroom_vod_number'),
            'content.require'  => lang('validate/chatroom_content_require'),
            'content.max'      => lang('validate/chatroom_content_max'),
            'chat_id.require'  => lang('validate/chatroom_id_require'),
            'chat_id.number'   => lang('validate/chatroom_id_number'),
        ];
        parent::__construct($rules, $message, $field);
    }
}
