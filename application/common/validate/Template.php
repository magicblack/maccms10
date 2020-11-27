<?php
namespace app\common\validate;
use think\Validate;

class Template extends Validate
{
    protected $rule =   [
        'fname'=>'require',
        'fpath'=>'require',
    ];

    protected $message  =   [
        'fname.require' => 'validate/require_name',
        'fpath.require'   => 'validate/require_path',
    ];

}