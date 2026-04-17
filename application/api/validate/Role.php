<?php

namespace app\api\validate;

use think\Validate;

class Role extends Validate
{
    protected $rule = [
        'role_id' => 'require|number|between:1,' . PHP_INT_MAX,
        'offset'  => 'number|between:0,' . PHP_INT_MAX,
        'limit'   => 'number|between:1,500',
        'rid'     => 'number|between:1,' . PHP_INT_MAX,
        'name'    => 'max:50',
        'letter'  => 'max:1',
        'level'   => 'max:50',
        'actor'   => 'max:128',
        'orderby' => 'in:id,time,time_add,hits,hits_day,hits_week,hits_month,score,up,down,level',
    ];

    protected $message = [
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'rid',
            'name',
            'letter',
            'level',
            'actor',
            'orderby',
        ],
        'get_detail' => [
            'role_id',
        ],
        'get_recommend' => [],
    ];
}
