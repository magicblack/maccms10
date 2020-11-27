<?php
namespace app\common\validate;
use think\Validate;

class Art extends Validate
{
    protected $rule =   [
        'art_name'  => 'require',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'art_name.require' => 'validate/require_name',
        'type_id.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['art_name','type_id'],
        'edit'  =>  ['art_name','type_id'],
    ];

}