<?php
namespace app\common\validate;
use think\Validate;

class Link extends Validate
{
    protected $rule =   [
        'link_name'  => 'require|max:60',
        'link_url'   => 'require|max:255',
    ];

    protected $message  =   [
        'link_name.require' => '名称必须',
        'link_name.max'     => '名称最多不能超过60个字符',
        'link_url.require'   => '地址必须',
        'link_url.max'     => '地址最多不能超过255个字符',
    ];

    protected $scene = [
        'add'=> ['link_name','link_url'],
        'edit'=> ['link_name','link_url'],
    ];
}