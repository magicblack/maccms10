<?php
namespace app\common\validate;
use think\Validate;

class Website extends Validate
{
    protected $rule =   [
        'website_name'  => 'require',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'website_name.require' => 'validate/require_name',
        'type_id.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['website_name','type_id'],
        'edit'  =>  ['website_name','type_id'],
    ];

}