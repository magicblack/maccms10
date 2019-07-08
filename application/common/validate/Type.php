<?php
namespace app\common\validate;
use think\Validate;

class Type extends Validate
{
    protected $rule =   [
        'type_name'  => 'require|max:20',

    ];

    protected $message  =   [
        'type_name.require' => '名称必须',
        'type_name.max'     => '名称最多不能超过20个字符',
    ];

    protected $scene = [
        'add'=> ['type_name'],
        'edit'=> ['type_name'],
    ];
}