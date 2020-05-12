<?php
namespace app\common\validate;
use think\Validate;

class Cj extends Validate
{
    protected $rule =   [
        'name'  => 'require|max:30',
        'sourcecharset'   => 'require',
        'sourcetype'   => 'require',
        'urlpage' =>'require'
    ];

    protected $message  =   [
        'name.require' => '名称必须',
        'name.max'     => '名称最多不能超过30个字符',
        'sourcecharset.require'   => '目标网址编码类型必须',
        'sourcetype.require'   => '目前网址类型必须',
        'urlpage.require'  => '目前网址必须',
    ];

    protected $scene = [
        'add'  =>  ['name','sourcecharset'],
        'edit'  =>  ['name','sourcecharset'],
    ];

}