<?php
namespace app\common\validate;
use think\Validate;

class Actor extends Validate
{
    protected $rule =   [
        'actor_name'  => 'require',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'actor_name.require' => 'validate/require_name',
        'type_id.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['actor_name','type_id'],
        'edit'  =>  ['actor_name','type_id'],
    ];

}