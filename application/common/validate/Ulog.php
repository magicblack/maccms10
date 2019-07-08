<?php
namespace app\common\validate;
use think\Validate;

class Ulog extends Validate
{
    protected $rule =   [
        'user_id'   => 'require',
        'ulog_mid'   => 'require',
        'ulog_type'   => 'require',
        'ulog_rid'   => 'require',
    ];

    protected $message  =   [
        'user_id.require'     => '用户必须',
        'ulog_mid.require'   => '模块必须',
        'ulog_type.require'   => '类型必须',
        'ulog_rid.require'   => '关联ID必须',

    ];

    protected $scene = [
        'add'  =>  ['user_id','ulog_mid','ulog_type','ulog_rid'],
        'edit'  =>  ['user_id','ulog_mid','ulog_type','ulog_rid'],
    ];

}