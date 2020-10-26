<?php
namespace app\common\validate;
use think\Validate;

class Token extends Validate
{
    protected $rule =   [
        '__token__'  =>  'require|token',
    ];

    protected $message  =   [
        '__token__.require' => '非法提交',
        '__token__.token'   => '请不要重复提交表单'
    ];

}