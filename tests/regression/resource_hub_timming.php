<?php

namespace app\admin\controller {
    class Base { public $request; }
}

namespace think {
    class Cache
    {
        public static function rm($key) {}
    }

    class Db
    {
        public static function name($table) { return new self; }
        public function select() { return [['type_id' => 12, 'type_name' => 'Drama']]; }
    }
}

namespace app\common\util {
    class HttpClient
    {
        public static function resolvePublicIp($host) { return '93.184.216.34'; }
        public static function safeGet($url, $timeout, $verifyTls)
        {
            return '{"class":[{"type_id":7,"type_name":"Drama"}]}';
        }
    }
}

namespace {
    use app\admin\controller\ResourceHub;

    function input($key) { return $GLOBALS['form']; }
    function config($key) { return []; }
    function lang($key) { return $key; }
    function json($value) { return $value; }

    function verify($condition, $message)
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        echo 'OK: ' . $message . PHP_EOL;
    }

    $root = __DIR__ . '/../..';
    require $root . '/application/common.php';
    require $root . '/application/common/util/TimmingConfig.php';
    require isset($argv[2]) ? $argv[2] : $root . '/application/admin/controller/ResourceHub.php';

    define('APP_PATH', sys_get_temp_dir() . '/maccms-resource-hub-' . bin2hex(random_bytes(8)) . '/');
    mkdir(APP_PATH . 'extra', 0700, true);
    $bind = APP_PATH . 'extra/bind.php';
    $timming = APP_PATH . 'extra/timming.php';
    $lock = $timming . '.lock';
    $existing = ['builtin' => ['name' => 'builtin', 'runtime' => 123]];
    file_put_contents($bind, "<?php return ['manual' => 42];");
    mac_arr2file($timming, $existing);
    $originalTimming = file_get_contents($timming);
    $form = ['url' => 'https://example.com/api', 'name' => "Fixture's source", 'hours' => '3'];

    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message, $file, $line) {
        if ($severity & error_reporting()) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    });

    try {
        $controller = (new ReflectionClass(ResourceHub::class))->newInstanceWithoutConstructor();
        $controller->request = new class {
            public function isPost() { return true; }
        };
        if (($argv[1] ?? null) !== 'schedule') {
            verify($controller->autoBind()['code'] === 1, 'auto-bind succeeds');
            verify((include $bind) === ['manual' => 42, md5($form['url']) . '_7' => 12], 'auto-bind persists the new match and preserves manual bindings');
            verify(file_get_contents($timming) === $originalTimming && !file_exists($lock), 'auto-bind leaves the scheduler file and lock untouched');
        }
        if (($argv[1] ?? null) !== 'bind') {
            $savedBind = file_get_contents($bind);
            verify($controller->addTimming()['code'] === 1, 'add task succeeds');
            $tasks = include $timming;
            $task = 'resourcehub_' . md5($form['url']);
            parse_str($tasks[$task]['param'], $arguments);
            verify(array_keys($tasks) === ['builtin', $task] && $tasks['builtin'] === $existing['builtin']
                && $tasks[$task]['file'] === 'collect' && $tasks[$task]['des'] === '自动采集 - ' . $form['name']
                && $arguments['cjurl'] === $form['url'] && $arguments['h'] === '3', 'scheduler config is valid PHP and preserves both tasks');
            $written = file_get_contents($timming);
            $form['name'] = 'Duplicate';
            $result = $controller->addTimming();
            verify($result['code'] === 0 && $result['msg'] === 'admin/resourcehub/timming_exists'
                && file_get_contents($timming) === $written, 'duplicate is rejected without modifying the file');
            unlink($lock);
            mkdir($lock);
            $form['url'] .= '/other';
            $result = $controller->addTimming();
            verify($result['code'] === 0 && $result['msg'] === 'write_err_config'
                && file_get_contents($timming) === $written, 'lock failure returns a write error and preserves tasks');
            verify(file_get_contents($bind) === $savedBind, 'scheduling leaves bindings untouched');
        }
    } finally {
        restore_error_handler();
        unlink($bind);
        unlink($timming);
        if (is_file($lock)) {
            unlink($lock);
        } elseif (is_dir($lock)) {
            rmdir($lock);
        }
        rmdir(APP_PATH . 'extra');
        rmdir(APP_PATH);
    }
}
