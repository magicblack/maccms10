<?php
namespace app\common\validate;
use think\Validate;

class Group extends Validate
{
    protected $rule =   [
        'group_name'  => 'require|max:30',
    ];

    protected $message  =   [
        'group_name.require' => '名称必须',
        'group_name.max'     => '名称最多不能超过30个字符',
    ];

    protected $scene = [
        'add'=> ['group_name'],
        'edit'=> ['group_name'],
    ];
}