<?php

namespace app\api\validate;

use think\Validate;

class Comment extends Validate
{
    protected $rule = [
        'offset'   => 'number|egt:0',
        'limit'    => 'number|between:1,100',
        'rid'      => 'number|between:1,' . PHP_INT_MAX,
        'mid'      => 'number|between:1,99',
        'orderby'  => 'in:time,up,down,id',
    ];

    protected $message = [
        
    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'rid',
            'mid',
            'orderby',
        ],
    ];
}