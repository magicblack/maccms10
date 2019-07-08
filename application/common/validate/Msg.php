<?php
namespace app\common\validate;
use think\Validate;

class Msg extends Validate
{
    protected $rule =   [
        'msg_to'  => 'require|max:30',
        'msg_code'  => 'require|max:10',
        'msg_type'  => 'require',
    ];

    protected $message  =   [
        'msg_to.require' => '接收地址必须',
        'msg_to.max'     => '接收地址最多不能超过30个字符',
        'msg_code.require' => '验证码必须',
        'msg_code.max'     => '验证码最多不能超过10个字符',
        'msg_type.require' => '类型必须',
    ];

    protected $scene = [
        'add'  =>  ['msg_to','msg_code','msg_type'],
        'edit'  =>  ['msg_to','msg_code','msg_type'],
    ];

}