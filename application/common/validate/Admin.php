<?php
namespace app\common\validate;
use think\Validate;

class Admin extends Validate
{
    protected $rule =   [
        'admin_name'  => 'require|max:30',
        'admin_pwd'   => 'require',
        '__token__'  =>  'require|token',
    ];

    protected $message  =   [
        'admin_name.require' => '名称必须',
        'admin_name.max'     => '名称最多不能超过30个字符',
        'admin_pwd.require'   => '密码必须',
        '__token__.require' => '非法提交',
        '__token__.token'   => '请不要重复提交表单'
    ];

    protected $scene = [
        'add'  =>  ['__token__','admin_name','admin_pwd'],
        'edit'  =>  ['__token__','admin_name'],
    ];

}