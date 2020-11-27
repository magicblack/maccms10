<?php

namespace think\addons;

use think\Config;
use think\exception\HttpException;
use think\Hook;
use think\Loader;
use think\Request;

/**
 * 插件执行默认控制器
 * @package think\addons
 */
class Route
{

    /**
     * 插件执行
     */
    public function execute($addon = null, $controller = null, $action = null)
    {
        $request = Request::instance();
        // 是否自动转换控制器和操作名
        $convert = Config::get('url_convert');
        $filter = $convert ? 'strtolower' : 'trim';

        $addon = $addon ? trim(call_user_func($filter, $addon)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';

        Hook::listen('addon_begin', $request);
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            $info = get_addon_info($addon);
            if (!$info) {
                throw new HttpException(404, __('addon %s not found', $addon));
            }
            if (!$info['state']) {
                throw new HttpException(500, __('addon %s is disabled', $addon));
            }
            $dispatch = $request->dispatch();
            if (isset($dispatch['var']) && $dispatch['var']) {
                //$request->route($dispatch['var']);
            }

            // 设置当前请求的控制器、操作
            $request->controller($controller)->action($action);

            // 监听addon_module_init
            Hook::listen('addon_module_init', $request);
            // 兼容旧版本行为,即将移除,不建议使用
            Hook::listen('addons_init', $request);

            $class = get_addon_class($addon, 'controller', $controller);
            if (!$class) {
                throw new HttpException(404, __('addon controller %s not found', Loader::parseName($controller, 1)));
            }

            $instance = new $class($request);

            $vars = [];
            if (is_callable([$instance, $action])) {
                // 执行操作方法
                $call = [$instance, $action];
            } elseif (is_callable([$instance, '_empty'])) {
                // 空操作
                $call = [$instance, '_empty'];
                $vars = [$action];
            } else {
                // 操作不存在
                throw new HttpException(404, __('addon action %s not found', get_class($instance) . '->' . $action . '()'));
            }

            Hook::listen('addon_action_begin', $call);

            return call_user_func_array($call, $vars);
        } else {
            abort(500, lang('addon can not be empty'));
        }
    }

}
