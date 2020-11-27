<?php
namespace app\common\validate;
use think\Validate;

class Msg extends Validate
{
    protected $rule =   [
        'msg_to'  => 'require',
        'msg_code'  => 'require',
        'msg_type'  => 'require',
    ];

    protected $message  =   [
        'msg_to.require' => 'validate/require_msg_to',
        'msg_code.require' => 'validate/require_verify',
        'msg_type.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['msg_to','msg_code','msg_type'],
        'edit'  =>  ['msg_to','msg_code','msg_type'],
    ];

}