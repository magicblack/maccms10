<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$root = dirname(__DIR__, 2);

defined('ROOT_PATH') or define('ROOT_PATH', $root . DIRECTORY_SEPARATOR);
defined('APP_PATH') or define('APP_PATH', ROOT_PATH . 'application' . DIRECTORY_SEPARATOR);

require ROOT_PATH . 'thinkphp/base.php';
require ROOT_PATH . 'thinkphp/helper.php';

// ThinkPHP 5.0 emits deprecations on current PHP versions. Keep this test focused
// on the validation regression rather than framework compatibility warnings.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function extract_method_body($source, $method)
{
    $needle = 'public function ' . $method . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return false;
    }

    $open = strpos($source, '{', $start);
    if ($open === false) {
        return false;
    }

    $depth = 0;
    $length = strlen($source);
    for ($i = $open; $i < $length; $i++) {
        if ($source[$i] === '{') {
            $depth++;
        } elseif ($source[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $open + 1, $i - $open - 1);
            }
        }
    }

    return false;
}

\think\Request::destroy();
\think\Request::instance()->module('api');

$validRegistrationData = [
    'user_name' => 'abcdef',
    'user_pwd'  => 'secret123',
];

$legacyValidate = \think\Loader::validate('User');

assert_same(
    'app\api\validate\User',
    get_class($legacyValidate),
    'Short User validator name should resolve to the current api module in api requests.'
);

assert_same(
    false,
    $legacyValidate->scene('add')->check($validRegistrationData),
    'The api User validator does not define the add scene needed by registration.'
);

$commonValidate = new \app\common\validate\User();

assert_same(
    'app\common\validate\User',
    get_class($commonValidate),
    'Registration should use the common User validator.'
);

assert_same(
    true,
    $commonValidate->scene('add')->check($validRegistrationData),
    'The common User validator add scene should accept valid registration data.'
);

\think\Config::set([
    'user' => [
        'status'   => 1,
        'reg_open' => 1,
    ],
], 'maccms');

$registerWithoutConfirmPassword = (new \app\common\model\User())->register([
    'user_name' => 'abcdef',
    'user_pwd'  => 'secret123',
]);

assert_same(
    1002,
    $registerWithoutConfirmPassword['code'],
    'Registration without user_pwd2 should fail before captcha or database checks.'
);

assert_same(
    'model/user/input_require',
    $registerWithoutConfirmPassword['msg'],
    'Registration without user_pwd2 should return the required-fields error.'
);

$userModel = file_get_contents(APP_PATH . 'common/model/User.php');
assert_true($userModel !== false, 'Unable to read common User model.');
$registerBody = extract_method_body($userModel, 'register');
assert_true($registerBody !== false, 'Unable to locate User::register() body.');

assert_true(
    strpos($registerBody, 'new \app\common\validate\User()') !== false,
    'User::register() must instantiate the common User validator explicitly.'
);

assert_true(
    strpos($registerBody, "Loader::validate('User')") === false,
    'User::register() must not use module-relative Loader::validate(\'User\').'
);

echo 'OK: user register validation regression test passed.' . PHP_EOL;
