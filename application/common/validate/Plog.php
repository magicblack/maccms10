<?php
namespace app\common\validate;
use think\Validate;

class Plog extends Validate
{
    protected $rule =   [
        'user_id'   => 'require',
        'plog_type'   => 'require',
        'plog_points' => 'require',
    ];

    protected $message  =   [
        'user_id.require'     => '用户必须',
        'plog_type.require'   => '类型必须',
        'plog_points.require' =>'积分必须',

    ];

    protected $scene = [
        'add'  =>  ['user_id','plog_type','plog_points'],
        'edit'  => ['user_id','plog_type','plog_points'],
    ];

}