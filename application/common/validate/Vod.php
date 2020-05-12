<?php
namespace app\common\validate;
use think\Validate;

class Vod extends Validate
{
    protected $rule =   [
        'vod_name'  => 'require|max:255',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'vod_name.require' => '名称必须',
        'vod_name.max'     => '名称最多不能超过255个字符',
        'type_id.require' => '分类必须',
    ];

    protected $scene = [
        'add'  =>  ['vod_name','type_id'],
        'edit'  =>  ['vod_name','type_id'],
    ];

}