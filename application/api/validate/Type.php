<?php

namespace app\api\validate;

use think\Validate;

class Type extends Validate
{
    protected $rule = [
        'type_id'      => 'number|between:1,' . PHP_INT_MAX,
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'type_id',
        ],
    ];
}