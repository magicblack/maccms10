<?php

namespace app\api\validate;

use think\Validate;

class Live extends Validate
{
    protected $rule = [
        'live_id'    => 'require|number|between:1,' . PHP_INT_MAX,
        'cate_id'    => 'number|between:0,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'orderby'    => 'in:sort,hits,hits_day,hits_week,time,id',
        'name'       => 'max:100',
        'level'      => 'number|between:0,9',
    ];

    protected $message = [

    ];

    protected $scene = [
        'get_list' => [
            'cate_id',
            'offset',
            'limit',
            'orderby',
            'name',
            'level',
        ],
        'get_detail' => [
            'live_id',
        ],
        'get_category' => [],
    ];
}
