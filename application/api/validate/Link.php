<?php

namespace app\api\validate;

use think\Validate;

class Link extends Validate
{
    protected $rule = [
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'id'      => 'number|between:1,' . PHP_INT_MAX,
        'type'      => 'number|between:1,' . PHP_INT_MAX,
        'name'      => 'max:100',
        'sort'      => 'number|between:1,' . PHP_INT_MAX,
        'time_start'      => 'number|between:1,' . PHP_INT_MAX,
        'time_end'      => 'number|between:1,' . PHP_INT_MAX,
        'orderby'  => 'in:id,time,time_add',
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'id',
            'type',
            'name',
            'sort',
            'time_start',
            'time_end',
            'orderby',
        ],
    ];
}