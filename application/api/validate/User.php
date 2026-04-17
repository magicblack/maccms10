<?php

namespace app\api\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'id'         => 'require|number|between:1,' . PHP_INT_MAX,
        'user_id'    => 'number|between:1,' . PHP_INT_MAX,
        'page'       => 'number|between:1,' . PHP_INT_MAX,
        'name'       => 'max:50',
        'nickname'   => 'max:50',
        'email'      => 'max:100',
        'qq'         => 'max:20',
        'phone'      => 'max:20',
        'time_start' => 'number|between:1,' . PHP_INT_MAX,
        'time_end'   => 'number|between:1,' . PHP_INT_MAX,
        'group_id'   => 'number|1,500',
        'orderby'    => 'in:login_time,reg_time,points',
    ];

    protected $message = [

    ];

    protected $scene = [
        'get_list' => [
            'offset',
            'limit',
            'name',
            'nickname',
            'email',
            'qq',
            'phone',
            'reg_time_start',
            'reg_time_end',
            'group_id',
        ],
        'get_detail' => [
            'id',
        ],
        'get_my_invite' => [],
        'get_invite_list' => [
            'user_id',
            'page',
            'limit',
        ],
        'get_favorites_status' => [],
    ];
}
