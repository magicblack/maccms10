<?php
namespace app\common\validate;
use think\Validate;

class Group extends Validate
{
    protected $rule =   [
        'group_name'  => 'require',
    ];

    protected $message  =   [
        'group_name.require' => 'validate/require_name',
    ];

    protected $scene = [
        'add'=> ['group_name'],
        'edit'=> ['group_name'],
    ];
}