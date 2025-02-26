<?php

namespace app\api\validate;

use think\Validate;

class Art extends Validate
{
    protected $rule = [
        'art_id'     => 'require|number|between:0,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'tag'      => 'max:100',
        'orderby'  => 'in:id,time,time_add,score,hits,hits_day,hits_week,hits_month,up,down,level',
        'letter'  => 'max:1',
        'status'  => 'number|between:1,10',
        'name'  => 'max:100',
        'sub'  => 'max:100',
        'blurb'  => 'max:100',
        'title'  => 'max:50',
        'content'  => 'max:100',
        'time_start'  => 'number|between:1,' . PHP_INT_MAX,
        'time_end'  => 'number|between:1,' . PHP_INT_MAX,
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'tag',
            'orderby',
            'letter',
            'status',
            'name',
            'sub',
            'blurb',
            'title',
            'content',
            'time_start',
            'time_end',
        ],
        'get_detail' => [
            'art_id',
        ],
    ];
}