<?php
namespace app\common\validate;
use think\Validate;

class Admin extends Validate
{
    protected $rule =   [
        'admin_name'  => 'require|max:30',
        'admin_pwd'   => 'require',
    ];

    protected $message  =   [
        'admin_name.require' => '名称必须',
        'admin_name.max'     => '名称最多不能超过30个字符',
        'admin_pwd.require'   => '密码必须',
    ];

    protected $scene = [
        'add'  =>  ['admin_name','admin_pwd'],
        'edit'  =>  ['admin_name'],
    ];

}