<?php
namespace app\common\validate;
use think\Validate;

class User extends Validate
{
    protected $rule =   [
        'user_name'  => 'require|max:30|min:6',
        'user_pwd'   => 'require',
    ];

    protected $message  =   [
        'user_name.require' => '名称必须',
        'user_name.max'     => '名称最多不能超过30个字符',
        'user_name.min'     => '名称最少不能低于6个字符',
        'user_pwd.require'   => '密码必须',
    ];

    protected $scene = [
        'add'  =>  ['user_name','user_pwd'],
        'edit'  =>  ['user_name'],
    ];

}