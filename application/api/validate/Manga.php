<?php
namespace app\api\validate;

use think\Validate;

class Manga extends Validate
{
    protected $rule = [
        'id'    => 'require|number',
        'sid'   => 'number',
        'nid'   => 'number',
        't'     => 'number',
        'page'  => 'number',
        'limit' => 'number',
        'ids'   => 'max:255',
        'wd'    => 'max:100',
        'order' => 'in:manga_time desc,manga_time asc,manga_hits desc,manga_hits asc',
    ];

    protected $message = [
        'id.require' => 'id is required',
        'id.number' => 'id must be a number',
    ];

    protected $scene = [
        'get_list' => ['t', 'page', 'limit', 'ids', 'wd', 'order'],
        'get_detail' => ['id'],
        'get_chapter' => ['id'],
        'get_hot' => [],
        'get_latest' => [],
    ];
}
