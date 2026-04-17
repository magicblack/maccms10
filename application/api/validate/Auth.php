<?php

namespace app\api\validate;

use think\Validate;

class Auth extends Validate
{
    protected $rule = [
        'mid'    => 'require|number|between:1,' . PHP_INT_MAX,
        'id'     => 'require|number|between:1,' . PHP_INT_MAX,
        'action' => 'in:play,read,download,comment,favorite',
    ];

    protected $message = [
    ];

    protected $scene = [
        'me' => [],
        'permission' => [
            'mid',
            'id',
            'action',
        ],
    ];
}
