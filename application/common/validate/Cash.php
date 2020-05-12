<?php
namespace app\common\validate;
use think\Validate;

class Cash extends Validate
{
    protected $rule =   [
        'user_id'   => 'require',
        'cash_money'   => 'require',
        'cash_points' => 'require',
        'cash_bank_name' => 'require',
        'cash_payee_name' => 'require',
        'cash_bank_no' => 'require',
    ];

    protected $message  =   [
        'user_id.require'     => '用户必须',
        'cash_money.require'   => '金额必须',
        'cash_points.require' =>'积分必须',
        'cash_bank_name.require' =>'银行名称必须',
        'cash_payee_name.require' =>'收款人姓名必须',
        'cash_no.require' =>'银行账号必须',

    ];

    protected $scene = [
        'add'  =>  ['user_id','cash_money','cash_points','cash_bank_name','cash_payee_name','cash_bank_no'],
        'edit'  => ['user_id','cash_money','cash_points'],
    ];

}