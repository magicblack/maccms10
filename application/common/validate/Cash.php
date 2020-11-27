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
        'user_id.require'     => 'validate/require_user',
        'cash_money.require'   => 'validate/require_money',
        'cash_points.require' =>'validate/require_points',
        'cash_bank_name.require' =>'validate/require_bank_name',
        'cash_payee_name.require' =>'validate/require_payee_name',
        'cash_no.require' =>'validate/require_bank_no',

    ];

    protected $scene = [
        'add'  =>  ['user_id','cash_money','cash_points','cash_bank_name','cash_payee_name','cash_bank_no'],
        'edit'  => ['user_id','cash_money','cash_points'],
    ];

}