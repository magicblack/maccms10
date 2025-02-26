<?php

namespace app\api\validate;

use think\Validate;

class Website extends Validate
{
    protected $rule = [
        'website_id'     => 'require|number|between:1,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'type_id'      => 'number|between:1,100',
        'name'      => 'max:20',
        'sub'      => 'max:20',
        'en'      => 'max:20',
        'status'      => 'number|between:1,9',
        'letter'      => 'max:1',
        'area'      => 'max:10',
        'lang'      => 'max:10',
        'level'      => 'number|between:1,9',
        'start_time'      => 'number|between:1,' . PHP_INT_MAX,
        'end_time'      => 'number|between:1,' . PHP_INT_MAX,
        'tag'      => 'max:20',
        'orderby'      => 'in|id,time,time_add,score,hits,up,down,level'
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'type_id',
            'name',
            'sub',
            'en',
            'status',
            'letter',
            'area',
            'lang',
            'level',
            'time',
            'tag',
        ],
        'get_detail' => [
            'website_id',
        ],
    ];
}