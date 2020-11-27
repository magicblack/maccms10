<?php
namespace app\common\validate;
use think\Validate;

class Cj extends Validate
{
    protected $rule =   [
        'name'  => 'require',
        'sourcecharset'   => 'require',
        'sourcetype'   => 'require',
        'urlpage' =>'require'
    ];

    protected $message  =   [
        'name.require' => 'validate/require_name',
        'sourcecharset.require'   => 'validate/require_sourcecharset',
        'sourcetype.require'   => 'validate/require_sourcetype',
        'urlpage.require'  => 'validate/require_urlpage',
    ];

    protected $scene = [
        'add'  =>  ['name','sourcecharset'],
        'edit'  =>  ['name','sourcecharset'],
    ];

}