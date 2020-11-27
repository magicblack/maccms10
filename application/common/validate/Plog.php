<?php
namespace app\common\validate;
use think\Validate;

class Plog extends Validate
{
    protected $rule =   [
        'user_id'   => 'require',
        'plog_type'   => 'require',
    ];

    protected $message  =   [
        'user_id.require'     => 'validate/require_user',
        'plog_type.require'   => 'validate/require_type',

    ];

    protected $scene = [
        'add'  =>  ['user_id','plog_type','plog_points'],
        'edit'  => ['user_id','plog_type','plog_points'],
    ];

}