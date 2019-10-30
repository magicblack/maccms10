<?php
namespace app\common\validate;
use think\Validate;

class Website extends Validate
{
    protected $rule =   [
        'website_name'  => 'require|max:255',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'website_name.require' => '名称必须',
        'website_name.max'     => '名称最多不能超过255个字符',
        'type_id.require' => '分类必须',
    ];

    protected $scene = [
        'add'  =>  ['website_name','type_id'],
        'edit'  =>  ['website_name','type_id'],
    ];

}