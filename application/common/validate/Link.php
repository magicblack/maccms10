<?php
namespace app\common\validate;
use think\Validate;

class Link extends Validate
{
    protected $rule =   [
        'link_name'  => 'require',
        'link_url'   => 'require',
    ];

    protected $message  =   [
        'link_name.require' => 'validate/require_name',
        'link_url.require'   => 'validate/require_url',
    ];

    protected $scene = [
        'add'=> ['link_name','link_url'],
        'edit'=> ['link_name','link_url'],
    ];
}