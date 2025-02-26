<?php

namespace app\api\validate;

use think\Validate;

class Gbook extends Validate
{
    protected $rule = [
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'id'      => 'number|between:1,' . PHP_INT_MAX,
        'rid'      => 'number|between:1,' . PHP_INT_MAX,
        'user_id'      => 'number|between:1,' . PHP_INT_MAX,
        'status'      => 'number|between:0,10',
        'name'      => 'max:20',
        'content'      => 'max:20',
        'orderby'      => 'in:id,time,reply_time',
        'time_start'      => 'number|between:0,' . PHP_INT_MAX,
        'time_end'      => 'number|between:0,' . PHP_INT_MAX,
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'id',
            'rid',
            'user_id',
            'status',
            'name',
            'orderby',
        ],
    ];
}