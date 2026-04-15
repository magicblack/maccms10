<?php
namespace app\common\validate;
use think\Validate;

class Type extends Validate
{
    protected $rule =   [
        'type_id'      => 'number|between:1,' . PHP_INT_MAX,
        'type_name'  => 'require',

    ];

    protected $message  =   [
        'type_name.require' => 'validate/require_name',
    ];

    protected $scene = [
        'get_list' => [
            'type_id',
        ],
        'add'=> ['type_name'],
        'edit'=> ['type_name'],
    ];
}