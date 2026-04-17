<?php

namespace app\api\validate;

use think\Validate;

class Vod extends Validate
{
    protected $rule = [
        'vod_id'     => 'require|number|between:0,' . PHP_INT_MAX,
        'id'     => 'number|between:0,' . PHP_INT_MAX,
        'offset'     => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,500',
        'orderby'    => 'in:hits,up,pubdate,hits_week,hits_month,hits_day,score',
        'type_id'    => 'number|between:0,' . PHP_INT_MAX,
        'vod_letter' => 'max:10',
        'vod_name'   => 'max:50',
        'vod_tag'    => 'max:20',
        'vod_blurb'  => 'max:20',
        'vod_class'  => 'max:10',
        'vod_area'   => 'max:20',
        'vod_year'   => 'max:10',
        'vod_lang'   => 'max:20',
        'vod_level'  => 'max:50',
        'vod_state'  => 'max:20',
        'vod_isend'  => 'number|in:0,1',
        'vod_actor'  => 'max:128',
        // year,area,class
        'type_id_1'    => 'require|number|between:0,' . PHP_INT_MAX,
    ];

//    protected $message  =   [
//        'name.require' => '名称必须',
//        'name.max'     => '名称最多不能超过25个字符',
//        'age.number'   => '年龄必须是数字',
//        'age.between'  => '年龄只能在1-120之间',
//        'email'        => '邮箱格式错误',
//    ];

    protected $scene = [
        'get_list' => [
            'id',
            'offset',
            'limit',
            'orderby',
            'type_id',
//            'type_id_1',
            'vod_letter',
            'vod_name',
            'vod_tag',
            'vod_blurb',
            'vod_class',
            'vod_area',
            'vod_year',
            'vod_lang',
            'vod_level',
            'vod_state',
            'vod_isend',
            'vod_actor',
        ],
        'get_detail' => [
            'vod_id',
        ],
        'get_year' => [
            'type_id_1',
        ],
        'get_class' => [
            'type_id_1',
        ],
        'get_area' => [
            'type_id_1',
        ],
        'get_banner' => [],
        'get_hot' => [],
        'get_latest_by_type' => ['type_id'],
        'get_rank' => [],
    ];
}