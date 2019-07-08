<?php
namespace app\common\validate;
use think\Validate;

class Actor extends Validate
{
    protected $rule =   [
        'actor_name'  => 'require|max:255',
    ];

    protected $message  =   [
        'actor_name.require' => '名称必须',
        'actor_name.max'     => '名称最多不能超过255个字符',
    ];

    protected $scene = [
        'add'  =>  ['actor_name'],
        'edit'  =>  ['actor_name'],
    ];

}