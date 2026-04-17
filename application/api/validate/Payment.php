<?php

namespace app\api\validate;

use think\Validate;

class Payment extends Validate
{
    protected $rule = [
        'order_code' => 'require|max:30',
        'order_id'   => 'require|number|between:1,' . PHP_INT_MAX,
        'payment'    => 'require|max:30',
        'card_no'    => 'require|max:16',
        'card_pwd'   => 'require|max:8',
        'mid'        => 'require|number|in:1,2',
        'id'         => 'require|number|between:1,' . PHP_INT_MAX,
        'type'       => 'require|number|in:1,4,5',
        'sid'        => 'number|between:0,' . PHP_INT_MAX,
        'nid'        => 'number|between:0,' . PHP_INT_MAX,
        'group_id'   => 'require|number|between:3,' . PHP_INT_MAX,
        'long'       => 'require|in:day,week,month,year',
        'page'       => 'number|between:1,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,100',
    ];

    protected $message = [
        'order_code.require' => '订单号不能为空',
        'order_id.require'   => '订单ID不能为空',
        'payment.require'    => '支付方式不能为空',
        'card_no.require'    => '卡号不能为空',
        'card_pwd.require'   => '卡密码不能为空',
        'mid.require'        => '模型ID不能为空',
        'mid.in'             => '模型ID只能为1(视频)或2(文章)',
        'id.require'         => '资源ID不能为空',
        'type.require'       => '操作类型不能为空',
        'type.in'            => '操作类型只能为1(阅读)、4(播放)或5(下载)',
        'group_id.require'   => '用户组ID不能为空',
        'group_id.between'   => '用户组ID必须大于等于3',
        'long.require'       => '时长周期不能为空',
        'long.in'            => '时长周期只能为day、week、month或year',
    ];

    protected $scene = [
        'gopay' => [
            'order_code',
            'order_id',
            'payment',
        ],
        'use_card' => [
            'card_no',
            'card_pwd',
        ],
        'buy_popedom' => [
            'mid',
            'id',
            'type',
            'sid',
            'nid',
        ],
        'upgrade' => [
            'group_id',
            'long',
        ],
        'get_groups' => [],
        'get_cards' => [
            'page',
            'limit',
        ],
    ];
}
