<?php

namespace app\api\validate;

use think\Validate;

class Cash extends Validate
{
    protected $rule = [
        'cash_id'         => 'require|number|between:1,' . PHP_INT_MAX,
        'cash_money'      => 'require|float|gt:0',
        'cash_bank_name'  => 'require|max:60',
        'cash_bank_no'    => 'require|max:30',
        'cash_payee_name' => 'require|max:30',
        'page'            => 'number|between:1,' . PHP_INT_MAX,
        'limit'           => 'number|between:1,100',
        'status'          => 'number|in:0,1',
        'ids'             => 'max:200',
    ];

    protected $message = [
        'cash_id.require'         => '提现记录ID不能为空',
        'cash_money.require'      => '提现金额不能为空',
        'cash_money.gt'           => '提现金额必须大于0',
        'cash_bank_name.require'  => '银行名称不能为空',
        'cash_bank_no.require'    => '银行账号不能为空',
        'cash_payee_name.require' => '收款人姓名不能为空',
    ];

    protected $scene = [
        'get_list' => [
            'page',
            'limit',
            'status',
        ],
        'get_detail' => [
            'cash_id',
        ],
        'create' => [
            'cash_money',
            'cash_bank_name',
            'cash_bank_no',
            'cash_payee_name',
        ],
        'del' => [
            'ids',
        ],
        'get_config' => [],
    ];
}
