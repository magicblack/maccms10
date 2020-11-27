<?php

use think\App;
use think\Cache;
use think\Config;
use think\Exception;
use think\Hook;
use think\Loader;
use think\Route;

// 插件目录
define('ADDON_PATH', ROOT_PATH . 'addons' . DS);

// 定义路由
Route::any('addons/:addon/[:controller]/[:action]', "\\think\\addons\\Route@execute");

// 如果插件目录不存在则创建
if (!is_dir(ADDON_PATH)) {
    @mkdir(ADDON_PATH, 0755, true);
}

// 注册类的根命名空间
Loader::addNamespace('addons', ADDON_PATH);

// 监听addon_init
Hook::listen('addon_init');

// 闭包自动识别插件目录配置
Hook::add('app_init', function () {
    // 获取开关
    $autoload = (bool)Config::get('addons.autoload', false);
    // 非正是返回
    if (!$autoload) {
        return;
    }
    // 当debug时不缓存配置
    $config = App::$debug ? [] : Cache::get('addons', []);
    if (empty($config)) {
        $config = get_addon_autoload_config();
        Cache::set('addons', $config);
    }
});

// 闭包初始化行为
Hook::add('app_init', function () {
    //注册路由
    $routeArr = (array)Config::get('addons.route');
    $domains = [];
    $rules = [];
    $execute = "\\think\\addons\\Route@execute?addon=%s&controller=%s&action=%s";
    foreach ($routeArr as $k => $v) {
        if (is_array($v)) {
            $addon = $v['addon'];
            $domain = $v['domain'];
            $drules = [];
            foreach ($v['rule'] as $m => $n) {
                list($addon, $controller, $action) = explode('/', $n);
                $drules[$m] = sprintf($execute . '&indomain=1', $addon, $controller, $action);
            }
            //$domains[$domain] = $drules ? $drules : "\\addons\\{$k}\\controller";
            $domains[$domain] = $drules ? $drules : [];
            $domains[$domain][':controller/[:action]'] = sprintf($execute . '&indomain=1', $addon, ":controller", ":action");
        } else {
            if (!$v) {
                continue;
            }
            list($addon, $controller, $action) = explode('/', $v);
            $rules[$k] = sprintf($execute, $addon, $controller, $action);
        }
    }
    Route::rule($rules);
    if ($domains) {
        Route::domain($domains);
    }

    // 获取系统配置
    $hooks = App::$debug ? [] : Cache::get('hooks', []);
    if (empty($hooks)) {
        $hooks = (array)Config::get('addons.hooks');
        // 初始化钩子
        foreach ($hooks as $key => $values) {
            if (is_string($values)) {
                $values = explode(',', $values);
            } else {
                $values = (array)$values;
            }
            $hooks[$key] = array_filter(array_map('get_addon_class', $values));
        }
        Cache::set('hooks', $hooks);
    }
    //如果在插件中有定义app_init，则直接执行
    if (isset($hooks['app_init'])) {
        foreach ($hooks['app_init'] as $k => $v) {
            Hook::exec($v, 'app_init');
        }
    }
    Hook::import($hooks, true);
});

/**
 * 处理插件钩子
 * @param string $hook   钩子名称
 * @param mixed  $params 传入参数
 * @return void
 */
function hook($hook, $params = [])
{
    Hook::listen($hook, $params);
}

/**
 * 移除空目录
 * @param string $dir 目录
 */
function remove_empty_folder($dir)
{
    try {
        $isDirEmpty = !(new \FilesystemIterator($dir))->valid();
        if ($isDirEmpty) {
            @rmdir($dir);
            remove_empty_folder(dirname($dir));
        }
    } catch (\UnexpectedValueException $e) {

    } catch (\Exception $e) {

    }
}

/**
 * 获得插件列表
 * @return array
 */
function get_addon_list()
{
    $results = scandir(ADDON_PATH);
    $list = [];
    foreach ($results as $name) {
        if ($name === '.' or $name === '..') {
            continue;
        }
        if (is_file(ADDON_PATH . $name)) {
            continue;
        }
        $addonDir = ADDON_PATH . $name . DS;
        if (!is_dir($addonDir)) {
            continue;
        }

        if (!is_file($addonDir . ucfirst($name) . '.php')) {
            continue;
        }

        //这里不采用get_addon_info是因为会有缓存
        //$info = get_addon_info($name);
        $info_file = $addonDir . 'info.ini';
        if (!is_file($info_file)) {
            continue;
        }

        $info = Config::parse($info_file, '', "addon-info-{$name}");
        if (!isset($info['name'])) {
            continue;
        }
        $info['url'] = addon_url($name);
        $list[$name] = $info;
    }
    return $list;
}

/**
 * 获得插件自动加载的配置
 * @param bool $truncate 是否清除手动配置的钩子
 * @return array
 */
function get_addon_autoload_config($truncate = false)
{
    // 读取addons的配置
    $config = (array)Config::get('addons');
    if ($truncate) {
        // 清空手动配置的钩子
        $config['hooks'] = [];
    }

    // 伪静态优先级
    $priority = isset($config['priority']) && $config['priority'] ? is_array($config['priority']) ? $config['priority'] : explode(',', $config['priority']) : [];

    $route = [];
    // 读取插件目录及钩子列表
    $base = get_class_methods("\\think\\Addons");
    $base = array_merge($base, ['install', 'uninstall', 'enable', 'disable']);

    $url_domain_deploy = Config::get('url_domain_deploy');
    $addons = get_addon_list();
    $domain = [];

    $priority = array_merge($priority, array_keys($addons));

    $orderedAddons = array();
    foreach ($priority as $key) {
        if (!isset($addons[$key])) {
            continue;
        }
        $orderedAddons[$key] = $addons[$key];
    }

    foreach ($orderedAddons as $name => $addon) {
        if (!$addon['state']) {
            continue;
        }

        // 读取出所有公共方法
        $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . ucfirst($name));
        // 跟插件基类方法做比对，得到差异结果
        $hooks = array_diff($methods, $base);
        // 循环将钩子方法写入配置中
        foreach ($hooks as $hook) {
            $hook = Loader::parseName($hook, 0, false);
            if (!isset($config['hooks'][$hook])) {
                $config['hooks'][$hook] = [];
            }
            // 兼容手动配置项
            if (is_string($config['hooks'][$hook])) {
                $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
            }
            if (!in_array($name, $config['hooks'][$hook])) {
                $config['hooks'][$hook][] = $name;
            }
        }
        $conf = get_addon_config($addon['name']);
        if ($conf) {
            $conf['rewrite'] = isset($conf['rewrite']) && is_array($conf['rewrite']) ? $conf['rewrite'] : [];
            $rule = array_map(function ($value) use ($addon) {
                return "{$addon['name']}/{$value}";
            }, array_flip($conf['rewrite']));
            if ($url_domain_deploy && isset($conf['domain']) && $conf['domain']) {
                $domain[] = [
                    'addon'  => $addon['name'],
                    'domain' => $conf['domain'],
                    'rule'   => $rule
                ];
            } else {
                $route = array_merge($route, $rule);
            }
        }
    }
    $config['route'] = $route;
    $config['route'] = array_merge($config['route'], $domain);
    return $config;
}

/**
 * 获取插件类的类名
 * @param string $name  插件名
 * @param string $type  返回命名空间类型
 * @param string $class 当前类名
 * @return string
 */
function get_addon_class($name, $type = 'hook', $class = null)
{
    $name = Loader::parseName($name);
    // 处理多级控制器情况
    if (!is_null($class) && strpos($class, '.')) {
        $class = explode('.', $class);

        $class[count($class) - 1] = Loader::parseName(end($class), 1);
        $class = implode('\\', $class);
    } else {
        $class = Loader::parseName(is_null($class) ? $name : $class, 1);
    }
    switch ($type) {
        case 'controller':
            $namespace = "\\addons\\" . $name . "\\controller\\" . $class;
            break;
        default:
            $namespace = "\\addons\\" . $name . "\\" . $class;
    }
    return class_exists($namespace) ? $namespace : '';
}

/**
 * 读取插件的基础信息
 * @param string $name 插件名
 * @return array
 */
function get_addon_info($name)
{
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    return $addon->getInfo($name);
}

/**
 * 获取插件类的配置数组
 * @param string $name 插件名
 * @return array
 */
function get_addon_fullconfig($name)
{
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    return $addon->getFullConfig($name);
}

/**
 * 获取插件类的配置值值
 * @param string $name 插件名
 * @return array
 */
function get_addon_config($name)
{
    $addon = get_addon_instance($name);
    if (!$addon) {
        return [];
    }
    return $addon->getConfig($name);
}

/**
 * 获取插件的单例
 * @param string $name 插件名
 * @return mixed|null
 */
function get_addon_instance($name)
{
    static $_addons = [];
    if (isset($_addons[$name])) {
        return $_addons[$name];
    }
    $class = get_addon_class($name);
    if (class_exists($class)) {
        $_addons[$name] = new $class();
        return $_addons[$name];
    } else {
        return null;
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param string      $url    地址 格式：插件名/控制器/方法
 * @param array       $vars   变量参数
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 * @return bool|string
 */
function addon_url($url, $vars = [], $suffix = true, $domain = false)
{
    $url = ltrim($url, '/');
    $addon = substr($url, 0, stripos($url, '/'));
    if (!is_array($vars)) {
        parse_str($vars, $params);
        $vars = $params;
    }
    $params = [];
    foreach ($vars as $k => $v) {
        if (substr($k, 0, 1) === ':') {
            $params[$k] = $v;
            unset($vars[$k]);
        }
    }
    $val = "@addons/{$url}";
    $config = get_addon_config($addon);
    $dispatch = think\Request::instance()->dispatch();
    $indomain = isset($dispatch['var']['indomain']) && $dispatch['var']['indomain'] ? true : false;
    $domainprefix = $config && isset($config['domain']) && $config['domain'] ? $config['domain'] : '';
    $domain = $domainprefix && Config::get('url_domain_deploy') ? $domainprefix : $domain;
    $rewrite = $config && isset($config['rewrite']) && $config['rewrite'] ? $config['rewrite'] : [];
    if ($rewrite) {
        $path = substr($url, stripos($url, '/') + 1);
        if (isset($rewrite[$path]) && $rewrite[$path]) {
            $val = $rewrite[$path];
            array_walk($params, function ($value, $key) use (&$val) {
                $val = str_replace("[{$key}]", $value, $val);
            });
            $val = str_replace(['^', '$'], '', $val);
            if (substr($val, -1) === '/') {
                $suffix = false;
            }
        } else {
            // 如果采用了域名部署,则需要去掉前两段
            if ($indomain && $domainprefix) {
                $arr = explode("/", $val);
                $val = implode("/", array_slice($arr, 2));
            }
        }
    } else {
        // 如果采用了域名部署,则需要去掉前两段
        if ($indomain && $domainprefix) {
            $arr = explode("/", $val);
            $val = implode("/", array_slice($arr, 2));
        }
        foreach ($params as $k => $v) {
            $vars[substr($k, 1)] = $v;
        }
    }
    $url = url($val, [], $suffix, $domain) . ($vars ? '?' . http_build_query($vars) : '');
    $url = preg_replace("/\/((?!index)[\w]+)\.php\//i", "/", $url);
    return $url;
}

/**
 * 设置基础配置信息
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
function set_addon_info($name, $array)
{
    $file = ADDON_PATH . $name . DIRECTORY_SEPARATOR . 'info.ini';
    $addon = get_addon_instance($name);
    $array = $addon->setInfo($name, $array);
    if (!isset($array['name']) || !isset($array['title']) || !isset($array['version'])) {
        throw new Exception("插件配置写入失败");
    }
    $res = array();
    foreach ($array as $key => $val) {
        if (is_array($val)) {
            $res[] = "[$key]";
            foreach ($val as $skey => $sval) {
                $res[] = "$skey = " . (is_numeric($sval) ? $sval : $sval);
            }
        } else {
            $res[] = "$key = " . (is_numeric($val) ? $val : $val);
        }
    }
    if ($handle = fopen($file, 'w')) {
        fwrite($handle, implode("\n", $res) . "\n");
        fclose($handle);
        //清空当前配置缓存
        Config::set($name, null, 'addoninfo');
    } else {
        throw new Exception("文件没有写入权限");
    }
    return true;
}

/**
 * 写入配置文件
 * @param string  $name      插件名
 * @param array   $config    配置数据
 * @param boolean $writefile 是否写入配置文件
 * @return bool
 * @throws Exception
 */
function set_addon_config($name, $config, $writefile = true)
{
    $addon = get_addon_instance($name);
    $addon->setConfig($name, $config);
    $fullconfig = get_addon_fullconfig($name);
    foreach ($fullconfig as $k => &$v) {
        if (isset($config[$v['name']])) {
            $value = $v['type'] !== 'array' && is_array($config[$v['name']]) ? implode(',', $config[$v['name']]) : $config[$v['name']];
            $v['value'] = $value;
        }
    }
    if ($writefile) {
        // 写入配置文件
        set_addon_fullconfig($name, $fullconfig);
    }
    return true;
}

/**
 * 写入配置文件
 *
 * @param string $name  插件名
 * @param array  $array 配置数据
 * @return boolean
 * @throws Exception
 */
function set_addon_fullconfig($name, $array)
{
    $file = ADDON_PATH . $name . DIRECTORY_SEPARATOR . 'config.php';
    if (!is_really_writable($file)) {
        throw new Exception("文件没有写入权限");
    }
    if ($handle = fopen($file, 'w')) {
        fwrite($handle, "<?php\n\n" . "return " . var_export($array, true) . ";\n");
        fclose($handle);
    } else {
        throw new Exception("文件没有写入权限");
    }
    return true;
}
