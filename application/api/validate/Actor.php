<?php

namespace app\api\validate;

use think\Validate;

class Actor extends Validate
{
    protected $rule = [
        'actor_id'      => 'require|number|between:1,' . PHP_INT_MAX,
        'offset'      => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,' . PHP_INT_MAX,
        'id'      => 'number|between:1,' . PHP_INT_MAX,
        'type_id'      => 'number|between:1,' . PHP_INT_MAX,
        'sex' =>  'in:男,女',
        'area' =>  'max:255',
        'letter'      => 'max:1',
        'level'      => 'max:1',
        'name'      => 'max:64',
        'blood'      => 'max:10',
        'starsign'      => 'max:255',
        'time_end'      => 'number|between:1,' . PHP_INT_MAX,
        'time_start'      => 'number|between:1,' . PHP_INT_MAX,
        'orderby' => 'in:hits,hits_month,hits_week,hits_day,time,level',

    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'id',
            'type_id',
            'sex',
            'area',
            'letter',
            'level',
            'name',
            'blood',
            'starsign',
            'orderby',
        ],
        'get_detail' => [
            'actor_id',
        ],
        'get_recommend' => [],
    ];
}