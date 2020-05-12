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
        'user_id.require' => '会员编码必须',
        'visit_ip.require' => 'IP必须',
        'visit_time.require' => '时间必须',
    ];

    protected $scene = [
        'add'  =>  ['user_id','visit_ip','visit_time'],
        'edit'  =>  ['user_id','visit_ip','visit_time'],
    ];

}