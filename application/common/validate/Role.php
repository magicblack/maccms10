<?php
namespace app\common\validate;
use think\Validate;

class Role extends Validate
{
    protected $rule =   [
        'role_name'  => 'require',
        'role_actor'  => 'require',
    ];

    protected $message  =   [
        'role_name.require' => 'validate/require_name',
        'role_actor.require' => 'validate/require_actor',
    ];

    protected $scene = [
        'add'  =>  ['role_name','role_actor'],
        'edit'  =>  ['role_name','role_actor'],
    ];

}