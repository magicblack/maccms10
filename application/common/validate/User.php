<?php
namespace app\common\validate;
use think\Validate;

class User extends Validate
{
    protected $rule =   [
        'user_name'  => 'require|min:6',
        'user_pwd'   => 'require',
    ];

    protected $message  =   [
        'user_name.require' => 'validate/require_name',
        'user_name.min'     => 'validate/require_name_min',
        'user_pwd.require'   => 'validate/require_pass',
    ];

    protected $scene = [
        'add'  =>  ['user_name','user_pwd'],
        'edit'  =>  ['user_name'],
    ];

}