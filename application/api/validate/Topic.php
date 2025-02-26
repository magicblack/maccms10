<?php

namespace app\api\validate;

use think\Validate;

class Topic extends Validate
{
    protected $rule = [
        'topic_id'     => 'require|number|between:0,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'time_start'      => 'number|between:0,' . PHP_INT_MAX,
        'time_end'      => 'number|between:0,' . PHP_INT_MAX,
        'orderby'    => 'in:id,time,time_add,score,hits,hits_day,hits_week,hits_month,up,down,level',
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'orderby',
            'time_start',
            'time_end',
        ],
        'get_detail' => [
            'topic_id',
        ],
    ];
}