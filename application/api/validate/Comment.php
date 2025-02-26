<?php

namespace app\api\validate;

use think\Validate;

class Comment extends Validate
{
    protected $rule = [
        'offset'      => 'number|between:1,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,' . PHP_INT_MAX,
        'rid'      => 'number|between:1,' . PHP_INT_MAX,
        'orderby' => 'in:time,up,down'
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'rid',
            'orderby',
        ],
    ];
}