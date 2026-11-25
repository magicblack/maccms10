<?php
namespace app\admin\controller {
    class Base
    {
        public function __construct() {}
        protected function error($message) { return [0, $message]; }
        protected function success($message) { return [1, $message]; }
        protected function adminCsrfOk()
        {
            return \app\common\behavior\CsrfGuard::tokenAccepted($GLOBALS['token'], 'expected', 'legacy');
        }
    }
}
namespace {
    function Request()
    {
        return new class {
            public function method($original) { return $GLOBALS['method']; }
        };
    }
    function lang($key) { return $key; }
    function input() { return $GLOBALS['params']; }
    function model($model)
    {
        return new class($model) {
            private $model;
            public function __construct($model) { $this->model = $model; }
            public function __call($method, $args)
            {
                $GLOBALS['calls'][] = [$this->model, $method, $args];
                return ['code' => 1, 'msg' => 'saved'];
            }
        };
    }
    function verifyCashUser($passed, $message)
    {
        if (!$passed) {
            throw new \RuntimeException($message);
        }
        echo "OK: $message\n";
    }

    $root = __DIR__ . '/../../application';
    require $root . '/common/behavior/CsrfGuard.php';
    require $root . '/admin/controller/Cash.php';
    require $root . '/admin/controller/User.php';
    $cases = [
        [new \app\admin\controller\Cash(), 'Cash', 'audit', ['ids' => 1], 'auditData', [['cash_id' => ['in', 1]]]],
        [new \app\admin\controller\Cash(), 'Cash', 'del', ['ids' => [1, 2]], 'delData', [['cash_id' => ['in', [1, 2]]]]],
        [new \app\admin\controller\Cash(), 'Cash', 'del', ['ids' => 1, 'all' => 1], 'delData', [['cash_id' => ['gt', 0]]]],
        [new \app\admin\controller\User(), 'User', 'del', ['ids' => 1], 'delData', [['user_id' => ['in', 1]]]],
        [new \app\admin\controller\User(), 'User', 'field', ['ids' => 1, 'col' => 'user_status', 'val' => '0'],
            'fieldData', [['user_id' => ['in', 1]], 'user_status', '0']],
    ];
    foreach ($cases as list($controller, $model, $action, $params, $operation, $args)) {
        $label = $model . '::' . $action . (isset($params['all']) ? ' clear all' : '');
        $calls = [];
        $method = 'GET';
        $token = 'expected';
        verifyCashUser($controller->$action() === [0, 'illegal_request'] && $calls === [], "$label rejects GET before calling the model");
        $method = 'POST';
        foreach (['', 'invalid'] as $token) {
            verifyCashUser($controller->$action() === [0, 'token_err'] && $calls === [], "$label rejects an empty or incorrect token");
        }
        foreach (['expected', 'legacy'] as $token) {
            $calls = [];
            verifyCashUser($controller->$action() === [1, 'saved'] && $calls === [[$model, $operation, $args]],
                "$label preserves arguments and response for a valid token");
        }
    }
}
