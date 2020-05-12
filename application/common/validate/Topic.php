<?php
namespace app\common\validate;
use think\Validate;

class Topic extends Validate
{
    protected $rule =   [
        'topic_name'  => 'require|max:30',
        'topic_tpl' => 'require',

    ];

    protected $message  =   [
        'topic_name.require' => '名称必须',
        'topic_name.max'     => '名称最多不能超过30个字符',
        'topic_tpl.require'   => '分类页模板必须'
    ];

    protected $scene = [
        'add'=> ['topic_name','topic_tpl'],
        'edit'=> ['topic_name','topic_tpl'],
    ];
}