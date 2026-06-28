<?php
namespace app\common\validate;

use think\Validate;

class MallGoods extends Validate
{
    protected $rule = [
        'mall_goods_name' => 'require|max:100',
        'mall_goods_type' => 'require|in:vip,card,download_quota',
        'mall_goods_points' => 'require|number|egt:1',
        'mall_goods_stock' => 'require|number|egt:0',
    ];

    protected $message = [
        'mall_goods_name.require' => 'mall/goods_name_required',
        'mall_goods_type.require' => 'mall/goods_type_required',
        'mall_goods_type.in' => 'mall/type_invalid',
        'mall_goods_points.require' => 'mall/goods_points_required',
        'mall_goods_points.egt' => 'mall/goods_points_invalid',
        'mall_goods_stock.require' => 'mall/goods_stock_required',
        'mall_goods_stock.egt' => 'mall/goods_stock_invalid',
    ];
}
