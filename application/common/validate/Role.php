<?php
namespace app\common\validate;
use think\Validate;

class Role extends Validate
{
    protected $rule =   [
        'role_name'  => 'require|max:255',
        'role_actor'  => 'require|max:255',
    ];

    protected $message  =   [
        'role_name.require' => '名称必须',
        'role_name.max'     => '名称最多不能超过255个字符',
        'role_actor.require' => '演员必须',
        'role_actor.max'     => '演员最多不能超过255个字符',
    ];

    protected $scene = [
        'add'  =>  ['role_name','role_actor'],
        'edit'  =>  ['role_name','role_actor'],
    ];

}