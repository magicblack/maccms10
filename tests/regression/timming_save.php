<?php

namespace app\admin\controller {
    class Base
    {
        public $view = [];

        public function __construct() {}
        protected function assign($key, $value) { $this->view[$key] = $value; }
        protected function fetch($view) { return $view; }
        protected function success($message) { return ['code' => 1, 'msg' => $message]; }
        protected function ajaxErrorWithFreshToken($message)
        {
            return ['code' => 0, 'msg' => $message, 'data' => ['__token__' => 'fresh']];
        }
    }
}

namespace think {
    class Loader
    {
        public static function validate($name) { return new self; }
        public function check($form) { return isset($form['__token__']); }
        public function getError() { return 'invalid_token'; }
    }
}

namespace {
    use app\admin\controller\Timming;
    use app\common\util\TimmingConfig;

    function input() { return $GLOBALS['form']; }
    function Request() { return new FixtureRequest; }
    function config($key) { return include APP_PATH . 'extra/timming.php'; }
    function lang($key) { return $key; }

    class FixtureRequest
    {
        public function isPost() { return $GLOBALS['post']; }
    }

    function checkSave($condition, $message)
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        echo 'OK: ' . $message . PHP_EOL;
    }

    $directory = sys_get_temp_dir() . '/maccms-timming-save-' . bin2hex(random_bytes(8));
    define('APP_PATH', $directory . '/');
    mkdir(APP_PATH . 'extra', 0700, true);
    $file = APP_PATH . 'extra/timming.php';
    $lock = $file . '.lock';
    $baseline = "<?php return ['builtin' => ['name' => 'builtin', 'runtime' => 0]];";
    file_put_contents($file, $baseline);

    require __DIR__ . '/../../application/common/util/TimmingConfig.php';
    require $argv[1] ?? __DIR__ . '/../../application/admin/controller/Timming.php';

    set_error_handler(function ($severity, $message, $source, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $source, $line);
        }
    });

    try {
        $controller = new Timming();
        $form = [];
        $post = false;
        checkSave($controller->info() === 'admin@timming/info', 'open Add without an id');
        checkSave($controller->view['info']['weeks'] === '' && $controller->view['info']['hours'] === '', 'render an empty schedule without warnings');

        $post = true;
        $form = ['name' => ' ', 'file' => 'collect', '__token__' => 'new'];
        $response = $controller->info();
        checkSave($response['msg'] === 'name_empty' && file_get_contents($file) === $baseline, 'reject an empty name without writing');

        $form = ['name' => 'xigua', 'file' => 'collect', 'weeks' => ['1', '2'], 'hours' => ['09'],
            'status' => '0', 'param' => 'ac=cj', 'interval' => '60', '__token__' => 'new'];
        mkdir($lock);
        $response = $controller->info();
        rmdir($lock);
        checkSave($response['msg'] === 'write_err_config' && $response['data']['__token__'] === 'fresh'
            && file_get_contents($file) === $baseline, 'report write failure and allow retry without losing existing tasks');

        $form['__token__'] = $response['data']['__token__'];
        checkSave($controller->info()['code'] === 1, 'retry succeeds after write failure is cleared');
        $list = include $file;
        checkSave($list['xigua']['weeks'] === '1,2' && $list['xigua']['hours'] === '09' && $list['xigua']['interval'] === 60
            && !isset($list['xigua']['__token__']), 'persist real form fields without the token');
        checkSave(TimmingConfig::recordRuntime($file, 'builtin', ['name' => 'builtin', 'runtime' => 0], 123) === true,
            'scheduler writes from the pre-save snapshot');
        $controller->index();
        checkSave(isset($controller->view['list']['xigua']), 'new task remains in the list after scheduler writes');
    } finally {
        restore_error_handler();
        unlink($file);
        if (is_file($lock)) {
            unlink($lock);
        } elseif (is_dir($lock)) {
            rmdir($lock);
        }
        rmdir(APP_PATH . 'extra');
        rmdir($directory);
    }
}
