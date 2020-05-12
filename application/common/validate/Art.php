<?php
namespace app\common\validate;
use think\Validate;

class Art extends Validate
{
    protected $rule =   [
        'art_name'  => 'require|max:255',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'art_name.require' => '名称必须',
        'art_name.max'     => '名称最多不能超过255个字符',
        'type_id.require' => '分类必须',
    ];

    protected $scene = [
        'add'  =>  ['art_name','type_id'],
        'edit'  =>  ['art_name','type_id'],
    ];

}