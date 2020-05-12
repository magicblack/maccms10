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
        'user_id.require' => '用户必须',
        'order_code.require'   => '单号必须',
        'order_price.require'   => '价格必须',
        'order_points.require'   => '点数必须',
    ];

    protected $scene = [
        'add'  =>  ['user_id','order_status','order_code','order_price','order_points'],
        'edit'  =>  ['user_id','order_status','order_code','order_price','order_points'],
    ];

}