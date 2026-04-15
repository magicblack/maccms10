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
        'offset'      => 'number|between:0,' . PHP_INT_MAX,
        'limit'      => 'number|between:1,' . PHP_INT_MAX,
        'rid'      => 'number|between:1,' . PHP_INT_MAX,
        'orderby' => 'in:time,up,down'
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
        'get_list' => [
            'offset',
            'limit',
            'rid',
            'orderby',
        ],
    ];
}