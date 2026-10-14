<?php
/**
 * 回归测试公共引导：加载 ThinkPHP 基础 + 断言工具 + 「按列」源码扫描工具。
 *
 * 列断言 = 枚举入口全集 → 逐个断言某属性存在。新加一个入口而忘了配套属性时当场红，
 * 而不是只防被点名的那一格复发。扫描前一律用 token_get_all 剥掉注释：本仓库注释里
 * 常原样写出被讨论的调用名（如「不要用 CURLOPT_FOLLOWLOCATION=1」），拿原文匹配会读到注释。
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$__root = dirname(__DIR__, 3);
defined('ROOT_PATH') or define('ROOT_PATH', $__root . DIRECTORY_SEPARATOR);
defined('APP_PATH') or define('APP_PATH', ROOT_PATH . 'application' . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'thinkphp/base.php';
require_once ROOT_PATH . 'thinkphp/helper.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// base.php 装了 think\Error 的处理器：未捕获异常 / 被转成异常的 notice 会渲染错误页后以 exit 0 结束，
// 测试里的崩溃就成了「静默通过」。换成测试自己的：一律打印 FAIL 并 exit(1)。
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) {
        return false;   // @ 抑制的照旧忽略
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});
set_exception_handler(function ($e) {
    fwrite(STDERR, 'FAIL: uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
});

$GLOBALS['__rt_fail'] = 0;
$GLOBALS['__rt_pass'] = 0;

/** 不立即退出：一次跑完整列，所有红格一起打印 */
function rt_check($condition, $message)
{
    if ($condition) {
        $GLOBALS['__rt_pass']++;
        return true;
    }
    $GLOBALS['__rt_fail']++;
    fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
    return false;
}

function rt_same($expected, $actual, $message)
{
    if (rt_check($expected === $actual, $message)) {
        return true;
    }
    fwrite(STDERR, '  expected: ' . var_export($expected, true) . PHP_EOL);
    fwrite(STDERR, '  actual:   ' . var_export($actual, true) . PHP_EOL);
    return false;
}

function rt_exempt($cell, $reason)
{
    if (trim((string)$reason) === '') {
        rt_check(false, "EXEMPT {$cell} has no reason (exemptions must cite a spec clause or backlog item)");
        return;
    }
    echo "EXEMPT {$cell}: {$reason}" . PHP_EOL;
}

function rt_done($name)
{
    $f = $GLOBALS['__rt_fail'];
    $p = $GLOBALS['__rt_pass'];
    if ($f > 0) {
        fwrite(STDERR, "FAILED: {$name} ({$f} failed, {$p} passed)" . PHP_EOL);
        exit(1);
    }
    echo "OK: {$name} ({$p} assertions)" . PHP_EOL;
    exit(0);
}

/** 调用私有/受保护的静态或实例方法 */
function rt_invoke($classOrObj, $method, array $args = [])
{
    $ref = new ReflectionMethod($classOrObj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs(is_object($classOrObj) ? $classOrObj : null, $args);
}

/** 不跑构造函数拿实例（控制器构造会读会话/配置，测试只需调用纯逻辑方法） */
function rt_bare($class)
{
    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
}

/**
 * 剥注释后按「命名函数」切分源码。
 * @return array [name => ['body' => string, 'public' => bool]]（body 不含注释；闭包留在所属命名函数体内）
 */
function rt_functions($file)
{
    $code = file_get_contents($file);
    $tokens = token_get_all($code);
    $out = [];
    $n = count($tokens);
    $vis = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
            $vis[] = $t[0];
            continue;
        }
        if (!is_array($t) || $t[0] !== T_FUNCTION) {
            if (!(is_array($t) && in_array($t[0], [T_WHITESPACE, T_STATIC, T_ABSTRACT, T_FINAL, T_COMMENT, T_DOC_COMMENT], true))) {
                $vis = [];
            }
            continue;
        }
        // 下一个非空白 token 是名字才是命名函数；是 ( 则是闭包
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue;
        }
        $name = $tokens[$j][1];
        $isPublic = empty($vis) || in_array(T_PUBLIC, $vis, true);
        $vis = [];
        // 找函数体 {...}（接口/抽象方法没有体）
        for ($k = $j; $k < $n && $tokens[$k] !== '{' && $tokens[$k] !== ';'; $k++) {
        }
        if ($k >= $n || $tokens[$k] === ';') {
            continue;
        }
        $depth = 0;
        $body = '';
        for (; $k < $n; $k++) {
            $tk = $tokens[$k];
            $s = is_array($tk) ? $tk[1] : $tk;
            if (is_array($tk) && in_array($tk[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($tk === '{' || (is_array($tk) && in_array($tk[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($tk === '}') {
                $depth--;
            }
            $body .= $s;
            if ($depth === 0) {
                break;
            }
        }
        $out[$name] = ['body' => $body, 'public' => $isPublic];
        $i = $k;
    }
    return $out;
}
