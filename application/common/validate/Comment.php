<?php
namespace app\common\validate;
use think\Validate;

class Comment extends Validate
{
    protected $rule =   [
        'comment_name'  => 'require|max:60',
        'comment_content'   => 'require|max:255',
        'comment_mid' => 'require',
        'comment_rid' => 'require',
    ];

    protected $message  =   [
        'comment_name.require' => '昵称必须',
        'comment_name.max'     => '昵称最多不能超过60个字符',
        'comment_content.require'   => '内容必须',
        'comment_content.max'   => '内容最多不能超过255个字符',
        'comment_mid.require'   => '模块类型必须',
        'comment_rid.require'   => '关联id必须',
    ];

    protected $scene = [
        'add'=> ['comment_name','comment_content','comment_mid','comment_rid'],
        'edit'=> ['comment_name','comment_content'],
    ];
}