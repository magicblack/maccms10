<?php
namespace app\common\validate;
use think\Validate;

class Type extends Validate
{
    protected $rule =   [
        'type_name'  => 'require',

    ];

    protected $message  =   [
        'type_name.require' => 'validate/require_name',
    ];

    protected $scene = [
        'add'=> ['type_name'],
        'edit'=> ['type_name'],
    ];
}