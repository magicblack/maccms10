<?php
namespace app\common\validate;
use think\Validate;

class Gbook extends Validate
{
    protected $rule =   [
        'gbook_name'  => 'require',
        'gbook_content'   => 'require',

    ];

    protected $message  =   [
        'gbook_name.require' => 'validate/require_nick',
        'gbook_content.require'   => 'validate/require_content',
    ];

    protected $scene = [
        'add'=> ['gbook_name','gbook_content'],
        'edit'=> ['gbook_name','gbook_content'],
    ];
}