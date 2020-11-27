<?php
namespace app\common\validate;
use think\Validate;

class Token extends Validate
{
    protected $rule =   [
        '__token__'  =>  'require|token',
    ];

    protected $message  =   [
        '__token__.require' => 'illegal_request',
        '__token__.token'   => 'token_err'
    ];

}