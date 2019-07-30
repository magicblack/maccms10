<?php

namespace think;

use think\Config;
use think\View;

/**
 * 插件基类
 * Class Addons
 * @author Byron Sampson <xiaobo.sun@qq.com>
 * @package think\addons
 */
abstract class Addons
{

    // 视图实例对象
    protected $view = null;
    // 当前错误信息
    protected $error;
    // 插件目录
    public $addons_path = '';
    // 插件配置作用域
    protected $configRange = 'addonconfig';
    // 插件信息作用域
    protected $infoRange = 'addoninfo';

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        $name = $this->getName();
        // 获取当前插件目录
        $this->addons_path = ADDON_PATH . $name . DS;

        // 初始化视图模型
        $config = ['view_path' => $this->addons_path];
        $config = array_merge(Config::get('template'), $config);
        $this->view = new View($config, Config::get('view_replace_str'));

        // 控制器初始化
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }

    /**
     * 读取基础配置信息
     * @param string $name
     * @return array
     */
    final public function getInfo($name = '')
    {
        if (empty($name)) {
            $name = $this->getName();
        }
        if (Config::has($name, $this->infoRange)) {
            return Config::get($name, $this->infoRange);
        }
        $info_file = $this->addons_path . 'info.ini';
        if (is_file($info_file)) {
            $info = Config::parse($info_file, '', $name, $this->infoRange);
            $info['url'] = addon_url($name);
        }
        Config::set($name, $info, $this->infoRange);

        return $info;
    }

    /**
     * 获取插件的配置数组
     * @param string $name 可选模块名
     * @return array
     */
    final public function getConfig($name = '')
    {
        if (empty($name)) {
            $name = $this->getName();
        }
        if (Config::has($name, $this->configRange)) {
            return Config::get($name, $this->configRange);
        }
        $config = [];
        $config_file = $this->addons_path . 'config.php';
        if (is_file($config_file)) {
            $temp_arr = include $config_file;
            foreach ($temp_arr as $key => $value) {
                $config[$value['name']] = $value['value'];
            }
            unset($temp_arr);
        }
        Config::set($name, $config, $this->configRange);

        return $config;
    }

    /**
     * 设置配置数据
     * @param $name
     * @param array $value
     * @return array
     */
    final public function setConfig($name = '', $value = [])
    {
        if (empty($name)) {
            $name = $this->getName();
        }
        $config = $this->getConfig($name);
        $config = array_merge($config, $value);
        Config::set($name, $config, $this->configRange);
        return $config;
    }

    /**
     * 设置插件信息数据
     * @param $name
     * @param array $value
     * @return array
     */
    final public function setInfo($name = '', $value = [])
    {
        if (empty($name)) {
            $name = $this->getName();
        }
        $info = $this->getInfo($name);
        $info = array_merge($info, $value);
        Config::set($name, $info, $this->infoRange);
        return $info;
    }

    /**
     * 获取完整配置列表
     * @param string $name
     * @return array
     */
    final public function getFullConfig($name = '')
    {
        $fullConfigArr = [];
        if (empty($name)) {
            $name = $this->getName();
        }
        $config_file = $this->addons_path . 'config.php';
        if (is_file($config_file)) {
            $fullConfigArr = include $config_file;
        }
        return $fullConfigArr;
    }

    /**
     * 获取当前模块名
     * @return string
     */
    final public function getName()
    {
        $data = explode('\\', get_class($this));
        return strtolower(array_pop($data));
    }

    /**
     * 检查基础配置信息是否完整
     * @return bool
     */
    final public function checkInfo()
    {
        $info = $this->getInfo();
        $info_check_keys = ['name', 'title', 'intro', 'author', 'version', 'state'];
        foreach ($info_check_keys as $value) {
            if (!array_key_exists($value, $info)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $template 模板文件名或者内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     * @throws \Exception
     */
    public function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        if (!is_file($template)) {
            $template = '/' . $template;
        }
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->fetch($template, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     */
    public function display($content, $vars = [], $replace = [], $config = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->display($content, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @return mixed
     */
    public function show($content, $vars = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);

        echo $this->view->fetch($content, $vars, [], [], true);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return void
     */
    public function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
    }

    /**
     * 获取当前错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
}
