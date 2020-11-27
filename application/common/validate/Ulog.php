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
        'user_id.require'     => 'validate/require_user',
        'ulog_mid.require'   => 'validate/require_id',
        'ulog_type.require'   => 'validate/require_type',
        'ulog_rid.require'   => 'validate/require_rid',

    ];

    protected $scene = [
        'add'  =>  ['user_id','ulog_mid','ulog_type','ulog_rid'],
        'edit'  =>  ['user_id','ulog_mid','ulog_type','ulog_rid'],
    ];

}