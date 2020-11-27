<?php
namespace app\common\validate;
use think\Validate;

class Visit extends Validate
{
    protected $rule =   [
        'user_id'  => 'require',
        'visit_ip'  => 'require',
        'visit_time'  => 'require',
    ];

    protected $message  =   [
        'user_id.require' => 'validate/require_user',
        'visit_ip.require' => 'validate/require_ip',
        'visit_time.require' => 'validate/require_time',
    ];

    protected $scene = [
        'add'  =>  ['user_id','visit_ip','visit_time'],
        'edit'  =>  ['user_id','visit_ip','visit_time'],
    ];

}