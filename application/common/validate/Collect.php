<?php
namespace app\common\validate;
use think\Validate;

class Collect extends Validate
{
    protected $rule =   [
        'collect_name'  => 'require',
        'collect_url'   => 'require',
    ];

    protected $message  =   [
        'collect_name.require' => 'validate/require_name',
        'collect_url.require'   => 'validate/require_url',
    ];

    protected $scene = [
        'add'  =>  ['collect_name','collect_url'],
        'edit'  =>  ['collect_name','collect_url'],
    ];

}