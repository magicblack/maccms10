<?php
namespace app\common\validate;
use think\Validate;

class Vod extends Validate
{
    protected $rule =   [
        'vod_name'  => 'require',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'vod_name.require' => 'validate/require_name',
        'type_id.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['vod_name','type_id'],
        'edit'  =>  ['vod_name','type_id'],
    ];

}