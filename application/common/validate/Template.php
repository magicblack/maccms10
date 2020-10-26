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
        'fname.require' => '名称必须',
        'fpath.require'   => '路径必须',
    ];

}