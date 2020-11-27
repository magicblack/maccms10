<?php
namespace app\common\validate;
use think\Validate;

class Card extends Validate
{
    protected $rule =   [
        'card_no'  => 'require',
        'card_pwd'   => 'require',
    ];

    protected $message  =   [
        'card_no.require' => 'validate/require_no',
        'card_pwd.require'   => 'validate/require_pass',
    ];

    protected $scene = [
        'add'=> ['card_no','card_pwd','card_money','card_point'],
        'edit'=> ['card_no','card_pwd','card_money','card_point'],
    ];
}