<?php
namespace app\common\validate;
use think\Validate;

class Order extends Validate
{
    protected $rule =   [
        'user_id'  => 'require',
        'order_code'   => 'require',
        'order_price'   => 'require',
        'order_points'   => 'require',
    ];

    protected $message  =   [
        'user_id.require' => 'validate/require_user',
        'order_code.require'   => 'validate/require_order_code',
        'order_price.require'   => 'validate/require_order_price',
        'order_points.require'   => 'validate/require_order_points',
    ];

    protected $scene = [
        'add'  =>  ['user_id','order_status','order_code','order_price','order_points'],
        'edit'  =>  ['user_id','order_status','order_code','order_price','order_points'],
    ];

}