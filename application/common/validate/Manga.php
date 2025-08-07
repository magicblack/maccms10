<?php
namespace app\common\validate;
use think\Validate;

class Manga extends Validate
{
    protected $rule =   [
        'manga_name'  => 'require',
        'type_id'  => 'require',
    ];

    protected $message  =   [
        'manga_name.require' => 'validate/require_name',
        'type_id.require' => 'validate/require_type',
    ];

    protected $scene = [
        'add'  =>  ['manga_name','type_id'],
        'edit'  =>  ['manga_name','type_id'],
    ];

}