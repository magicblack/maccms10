<?php
namespace app\common\validate;

use think\Validate;

class Notify extends Validate
{
    protected $rule = [
        'notify_type'   => 'require|max:20',
        'notify_title'  => 'require|max:255',
        'notify_content'=> 'require',
    ];

    protected $message = [
        'notify_type.require'    => 'notify/type_required',
        'notify_type.max'         => 'notify/type_invalid',
        'notify_title.require'   => 'notify/title_required',
        'notify_title.max'        => 'notify/title_too_long',
        'notify_content.require' => 'notify/content_required',
    ];

    protected $scene = [
        'add'       => ['notify_type', 'notify_title', 'notify_content'],
        'edit'      => ['notify_type', 'notify_title', 'notify_content'],
        'broadcast' => ['notify_type', 'notify_title', 'notify_content'],
    ];
}