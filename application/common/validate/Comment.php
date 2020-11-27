<?php
namespace app\common\validate;
use think\Validate;

class Comment extends Validate
{
    protected $rule =   [
        'comment_name'  => 'require',
        'comment_content'   => 'require',
        'comment_mid' => 'require',
        'comment_rid' => 'require',
    ];

    protected $message  =   [
        'comment_name.require' => 'validate/require_nick',
        'comment_content.require'   => 'validate/require_content',
        'comment_mid.require'   => 'validate/require_mid',
        'comment_rid.require'   => 'validate/require_rid',
    ];

    protected $scene = [
        'add'=> ['comment_name','comment_content','comment_mid','comment_rid'],
        'edit'=> ['comment_name','comment_content'],
    ];
}