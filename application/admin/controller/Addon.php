<?php
namespace app\admin\controller;
use think\Db;
use think\addons\AddonException;
use think\addons\Service;
use think\Cache;
use think\Config;
use think\Exception;
use app\common\util\Dir;

class Addon extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();

        $this->assign('title',lang('admin/addon/title'));
        return $this->fetch('admin@addon/index');
    }

    public function config()
    {
        $param = input();
        $name = $param['name'];
        if(empty($name)){
            return $this->error(lang('param_err'));
        }

        if (!is_dir(ADDON_PATH . $name)) {
            return $this->error(lang('get_dir_err'));
        }

        $info = get_addon_info($name);
        $config = get_addon_fullconfig($name);
        if (!$info){
            return $this->error(lang('get_addon_info_err'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if(empty($params)){
                return $this->error(lang('param_err'));
            }
            foreach ($config as $k => &$v) {
                if (isset($params[$v['name']])) {
                    if ($v['type'] == 'array') {
                        $params[$v['name']] = is_array($params[$v['name']]) ? $params[$v['name']] : (array)json_decode($params[$v['name']], true);
                        $value = $params[$v['name']];
                    } else {
                        $value = is_array($params[$v['name']]) ? implode(',', $params[$v['name']]) : $params[$v['name']];
                    }
                    $v['value'] = $value;
                }
            }

            try {
                //更新配置文件
                set_addon_fullconfig($name, $config);
                Service::refresh();
                return $this->success(lang('save_ok'));
            } catch (Exception $e) {
                return $this->error($e->getMessage());
            }
        }

        $this->assign('info',$info);
        $this->assign('config',$config);

        return $this->fetch('admin@addon/config');
    }

    public function info()
    {

    }

    public function downloaded()
    {
        $offset = (int)$this->request->get("offset");
        $limit = (int)$this->request->get("limit");
        $filter = $this->request->get("filter");
        $search = $this->request->get("search");
        $search = htmlspecialchars(strip_tags($search));
        $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'onlineaddons';
        $onlineaddons = Cache::get($key);
        if (!is_array($onlineaddons)) {
            $onlineaddons = [];
            $response = mac_curl_get( "h"."t"."t"."p:/"."/a"."p"."i"."."."m"."a"."c"."c"."m"."s."."c"."o"."m"."/" . 'addon/index');
            $json = !empty($response) ? json_decode($response, true) : [];
            if (!empty($json['rows'])) {
                foreach ($json['rows'] as $row) {
                    $onlineaddons[$row['name']] = $row;
                }
            }
            Cache::set($key, $onlineaddons, 600);
        }
        $filter = (array)json_decode($filter, true);
        $addons = get_addon_list();
        $list = [];
        foreach ($addons as $k => $v) {
            if ($search && stripos($v['name'], $search) === FALSE && stripos($v['intro'], $search) === FALSE)
                continue;

            if (isset($onlineaddons[$v['name']])) {
                $v = array_merge($onlineaddons[$v['name']], $v);
            } else {
                if(!isset($v['category_id'])) {
                    $v['category_id'] = 0;
                }
                if(!isset($v['flag'])) {
                    $v['flag'] = '';
                }
                if(!isset($v['banner'])) {
                    $v['banner'] = '';
                }
                if(!isset($v['image'])) {
                    $v['image'] = '';
                }
                if(!isset($v['donateimage'])) {
                    $v['donateimage'] = '';
                }
                if(!isset($v['demourl'])) {
                    $v['demourl'] = '';
                }
                if(!isset($v['price'])) {
                    $v['price'] = '0.00';
                }
            }
            $v['url'] = addon_url($v['name']);
            $v['createtime'] = filemtime(ADDON_PATH . $v['name']);
            $v['install'] = '1';
            if ($filter && isset($filter['category_id']) && is_numeric($filter['category_id']) && $filter['category_id'] != $v['category_id']) {
                continue;
            }
            $list[] = $v;
        }
        $total = count($list);
        if ($limit) {
            $list = array_slice($list, $offset, $limit);
        }
        $result = array("total" => $total, "rows" => $list);

        $callback = $this->request->get('callback') ? "jsonp" : "json";
        return $callback($result);
    }

    /**
     * 安装
     */
    public function install()
    {
        $param = input();
        $name = $param['name'];
        $force = (int)$param['force'];
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'version'   => $version,
                'faversion' => $faversion
            ];
            Service::install($name, $force, $extend);
            $info = get_addon_info($name);
            $info['config'] = get_addon_config($name) ? 1 : 0;
            $info['state'] = 1;
            return $this->success(lang('install_err'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode());
        }
    }

    /**
     * 卸载
     */
    public function uninstall()
    {
        $param = input();
        $name = $param['name'];
        $force = (int)$param['force'];
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            if( strpos($name,".")!==false ||  strpos($name,"/")!==false ||  strpos($name,"\\")!==false  ) {
                $this->error(lang('admin/addon/path_err'));
                return;
            }


            Service::uninstall($name, $force);
            return $this->success(lang('uninstall_ok'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 禁用启用
     */
    public function state()
    {
        $param = input();
        $name = $param['name'];
        $action = $param['action'];
        $force = (int)$param['force'];
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            $action = $action == 'enable' ? $action : 'disable';
            //调用启用、禁用的方法
            Service::$action($name, $force);
            Cache::rm('__menu__');
            return $this->success(lang('opt_ok'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 本地上传
     */
    public function local()
    {
        $param = input();
        $validate = \think\Loader::validate('Token');
        if(!$validate->check($param)){
            return $this->error($validate->getError());
        }
        echo 'closed';exit;
        $file = $this->request->file('file');
        $addonTmpDir = RUNTIME_PATH . 'addons' . DS;
        if (!is_dir($addonTmpDir)) {
            @mkdir($addonTmpDir, 0755, true);
        }
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'zip'])->move($addonTmpDir);
        if ($info) {
            $tmpName = substr($info->getFilename(), 0, stripos($info->getFilename(), '.'));
            $tmpAddonDir = ADDON_PATH . $tmpName . DS;
            $tmpFile = $addonTmpDir . $info->getSaveName();
            try {
                Service::unzip($tmpName);
                @unlink($tmpFile);
                $infoFile = $tmpAddonDir . 'info.ini';
                if (!is_file($infoFile)) {
                    throw new Exception(lang('admin/addon/lack_config_err'));
                }

                $config = Config::parse($infoFile, '', $tmpName);
                $name = isset($config['name']) ? $config['name'] : '';
                if (!$name) {
                    throw new Exception(lang('admin/addon/name_empty_err'));
                }

                $newAddonDir = ADDON_PATH . $name . DS;
                if (is_dir($newAddonDir)) {
                    throw new Exception(lang('admin/addon/haved_err'));
                }

                //重命名插件文件夹
                rename($tmpAddonDir, $newAddonDir);
                try {
                    //默认禁用该插件
                    $info = get_addon_info($name);
                    if ($info['state']) {
                        $info['state'] = 0;
                        set_addon_info($name, $info);
                    }

                    //执行插件的安装方法
                    $class = get_addon_class($name);
                    if (class_exists($class)) {
                        $addon = new $class();
                        $addon->install();
                    }

                    //导入SQL
                    Service::importsql($name);

                    $info['config'] = get_addon_config($name) ? 1 : 0;
                    return $this->success(lang('install_ok'));
                } catch (Exception $e) {
                    if (Dir::delDir($newAddonDir) === false) {

                    }
                    throw new Exception($e->getMessage());
                }
            } catch (Exception $e) {
                @unlink($tmpFile);
                if (Dir::delDir($tmpAddonDir) === false) {

                }
                return $this->error($e->getMessage());
            }
        } else {
            // 上传失败获取错误信息
            return $this->error($file->getError());
        }
    }

    public function add()
    {
        return $this->fetch('admin@addon/add');
    }
    /**
     * 更新插件
     */
    public function upgrade()
    {
        $name = $this->request->post("name");
        if (!$name) {
            return $this->error(lang('param_err'));
        }
        try {
            $uid = $this->request->post("uid");
            $token = $this->request->post("token");
            $version = $this->request->post("version");
            $faversion = $this->request->post("faversion");
            $extend = [
                'uid'       => $uid,
                'token'     => $token,
                'version'   => $version,
                'faversion' => $faversion
            ];
            //调用更新的方法
            Service::upgrade($name, $extend);
            Cache::rm('__menu__');
            return $this->success(lang('update_ok'));
        } catch (AddonException $e) {
            return $this->result($e->getData(), $e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
