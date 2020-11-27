<?php
namespace app\common\validate;
use think\Validate;

class Topic extends Validate
{
    protected $rule =   [
        'topic_name'  => 'require',
        'topic_tpl' => 'require',

    ];

    protected $message  =   [
        'topic_name.require' => 'validate/require_name',
        'topic_tpl.require'   => 'validate/require_tpl'
    ];

    protected $scene = [
        'add'=> ['topic_name','topic_tpl'],
        'edit'=> ['topic_name','topic_tpl'],
    ];
}