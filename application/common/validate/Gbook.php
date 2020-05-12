<?php
namespace app\common\validate;
use think\Validate;

class Gbook extends Validate
{
    protected $rule =   [
        'gbook_name'  => 'require|max:60',
        'gbook_content'   => 'require|max:255',

    ];

    protected $message  =   [
        'gbook_name.require' => '昵称必须',
        'gbook_name.max'     => '昵称最多不能超过60个字符',
        'gbook_content.require'   => '内容必须',
        'gbook_content.max'   => '内容最多不能超过255个字符',
    ];

    protected $scene = [
        'add'=> ['gbook_name','gbook_content'],
        'edit'=> ['gbook_name','gbook_content'],
    ];
}