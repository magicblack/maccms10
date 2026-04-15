<?php
namespace app\common\validate;
use think\Validate;

class Ajax extends Validate
{
    protected $rule = [
        'type_id'      => 'number|between:1,' . PHP_INT_MAX,
        'vod_id'     => 'require|number|between:0,' . PHP_INT_MAX,
        'id'     => 'number|between:0,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'orderby'    => 'in:hits,up,pubdate,hits_week,hits_month,hits_day,score',
        'vod_letter' => 'max:1',
        'vod_name'   => 'max:50',
        'vod_tag'    => 'max:20',
        'vod_blurb'  => 'max:20',
        'vod_class'  => 'max:10',
        // year,area,class
        'type_id_1'    => 'require|number|between:0,' . PHP_INT_MAX,
    ];

    protected $message = [
        
    ];

    protected $scene = [
        
    ];
}