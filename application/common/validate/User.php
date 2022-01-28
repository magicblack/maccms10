<?php
namespace app\common\validate;
use think\Validate;

class User extends Validate
{
    protected $rule =   [
        'user_name'  => 'require|min:6',
        'user_pwd'   => 'require',
    ];

    protected $message  =   [
        'user_name.require' => 'validate/require_name',
        'user_name.min'     => 'validate/require_name_min',
        'user_pwd.require'   => 'validate/require_pass',
    ];

    protected $scene = [
        'add'  =>  ['user_name','user_pwd'],
        'edit'  =>  ['user_name'],
    ];

    /**
     * 校验邮箱
     * @param $email
     */
    public static function validateEmail($email)
    {
        list(, $email_host) = explode('@', $email, 2);
        // 不在白名单内，报错
        $email_white_host_sets = self::formatEmailHostSets('white');
        if (!empty($email_white_host_sets) && !isset($email_white_host_sets[$email_host])) {
            return ['code' => 1001, 'msg' => lang('model/user/email_host_not_allowed')];
        }
        // 在黑名单内，报错
        $email_black_host_sets = self::formatEmailHostSets('black');
        if (isset($email_black_host_sets[$email_host])) {
            return ['code' => 1002, 'msg' => lang('model/user/email_host_not_allowed')];
        }
        return ['code' => 1, 'msg' => 'ok'];
    }

    private static function formatEmailHostSets($type) {
        $config_string = isset($GLOBALS['config']['user']['email_' . $type . '_hosts']) ? $GLOBALS['config']['user']['email_' . $type . '_hosts'] : '';
        $email_host_sets = [];
        foreach (explode(',', str_replace("\n", ',', $config_string)) as $host) {
            $host = trim($host);
            if (strlen($host) == 0) {
                continue;
            }
            $email_host_sets[$host] = true;
        }
        return $email_host_sets;
    }
}
