<?php
namespace app\common\validate;
use think\Validate;

class Collect extends Validate
{
    protected $rule =   [
        'collect_name'  => 'require|max:30',
        'collect_url'   => 'require',
    ];

    protected $message  =   [
        'collect_name.require' => '名称必须',
        'collect_name.max'     => '名称最多不能超过30个字符',
        'collect_url.require'   => '接口地址必须',
    ];

    protected $scene = [
        'add'  =>  ['collect_name','collect_url'],
        'edit'  =>  ['collect_name','collect_url'],
    ];

}