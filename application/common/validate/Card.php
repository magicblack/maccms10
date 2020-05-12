<?php
namespace app\common\validate;
use think\Validate;

class Card extends Validate
{
    protected $rule =   [
        'card_no'  => 'require|max:16',
        'card_pwd'   => 'require|max:8',
        'card_money'   => 'require',
        'card_point'   => 'require',
    ];

    protected $message  =   [
        'card_no.require' => '卡号必须',
        'card_no.max'     => '卡号最多不能超过16个字符',
        'card_pwd.require'   => '密码必须',
        'card_pwd.max'   => '密码最多不能超过8个字符',
        'card_money.require'   => '面值必须',
        'card_point.require'   => '点数必须',

    ];

    protected $scene = [
        'add'=> ['card_no','card_pwd','card_money','card_point'],
        'edit'=> ['card_no','card_pwd','card_money','card_point'],
    ];
}