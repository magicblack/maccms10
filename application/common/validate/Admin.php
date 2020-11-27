<?php
namespace app\common\validate;
use think\Validate;

class Admin extends Validate
{
    protected $rule =   [
        'admin_name'  => 'require',
        'admin_pwd'   => 'require',
    ];

    protected $message  =   [
        'admin_name.require' => 'validate/require_name',
        'admin_pwd.require'   => 'validate/require_pass',
    ];

    protected $scene = [
        'add'  =>  ['admin_name','admin_pwd'],
        'edit'  =>  ['admin_name'],
    ];

}