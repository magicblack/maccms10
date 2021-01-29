<?php
namespace app\common\validate;
use think\Validate;

class Annex extends Validate
{
    protected $rule =   [
        'annex_file'  => 'require',
    ];

    protected $message  =   [
        'annex_file.require' => 'validate/require_name',
    ];

    protected $scene = [
        'add'  =>  ['annex_file'],
        'edit'  =>  ['annex_file'],
    ];

}