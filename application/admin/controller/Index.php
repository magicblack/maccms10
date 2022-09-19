<?php

namespace app\admin\controller;

use think\Hook;

class Index extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function login()
    {
        if (Request()->isPost()) {
            $data = input('post.');
            $res = model('Admin')->login($data);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        Hook::listen("admin_login_init", $this->request);
        return $this->fetch('admin@index/login');
    }

    public function logout()
    {
        $res = model('Admin')->logout();
        $this->redirect('index/login');
    }

    public function index()
    {
        $this->assign('title', lang('admin/index/title'));


        return $this->fetch('admin@index/index');
    }
    
    public function pearconfig(){
        $config = [ "logo" => [ "title" => lang('admin/index/index/name'), "image" => "/static/images/logo.png" ], "menu" => [ "data" => "../../admin/index/memu", "method" => "GET", "accordion" => true, "collaspe" => false, "control" => true, "controlWidth" => 500, "select" => "11", "async" => true ], "tab" => [ "enable" => true, "keepState" => true, "session" => false, "max" => "30", "index" => [ "id" => "11", "href" => "../../admin/index/welcome.html", "title" => "首页" ] ], "theme" => [ "defaultColor" => "2", "defaultMenu" => "dark-theme", "defaultHeader" => "light-theme", "allowCustom" => true, "banner" => true ], "colors" => [[ "id" => "1", "color" => "#2d8cf0", "second" => "#ecf5ff" ], [ "id" => "2", "color" => "#36b368", "second" => "#f0f9eb" ], [ "id" => "3", "color" => "#f6ad55", "second" => "#fdf6ec" ], [ "id" => "4", "color" => "#f56c6c", "second" => "#fef0f0" ], [ "id" => "5", "color" => "#3963bc", "second" => "#ecf5ff" ] ], "other" => [ "keepLoad" => "1200", "autoHead" => true ], "header" => [ "message" => false ] ];
        return json($config);
    }

    public function memu()
    {
        $menus = @include MAC_ADMIN_COMM . 'auth.php';

        foreach ($menus as $k1 => $v1) {
            $menus[$k1]['id'] = $k1;
            $menus[$k1]['type'] = 0;
            foreach ($v1['children'] as $k2 => $v2) {
                $menus[$k1]['children'][$k2]['id'] = $k2;
                $menus[$k1]['children'][$k2]['type'] = 1;
                if ($v2['show'] == 1) {
                    if (strpos($v2['action'], 'javascript') !== false) $url = $v2['action'];
                    else $url = url('admin/' . $v2['controller'] . '/' . $v2['action']);
                    if (!empty($v2['param'])) $url .= '?' . $v2['param'];
                    if ($this->check_auth($v2['controller'], $v2['action'])) $menus[$k1]['children'][$k2]['href'] = $url;
                    else unset($menus[$k1]['children'][$k2]);
                } else {
                    unset($menus[$k1]['children'][$k2]);
                }
            }

            if (empty($menus[$k1]['children'])) unset($menus[$k1]);
        }

        $quickmenu = config('quickmenu');
        if (empty($quickmenu)) {
            $quickmenu = mac_read_file(APP_PATH . 'data/config/quickmenu.txt');
            $quickmenu = explode(chr(13), $quickmenu);
        }
        if (!empty($quickmenu)) {
            $menus[1]['children'][] = ['id' => 13, 'type' => 1, 'title' => lang('admin/index/quick_tit'), 'href' => 'javascript:void(0);return false;'];

            foreach ($quickmenu as $k => $v) {
                if (empty($v)) continue;
                
                $one = explode(',', trim($v));
                if (substr($one[1], 0, 4) == 'http' || substr($one[1], 0, 2) == '//') {
                } elseif (substr($one[1], 0, 1) == '/'|| strpos($one[1], 'javascript:') !== false) {
                } elseif (strpos($one[1], '###') !== false ) {
                    $one[0] = '====不支持分隔符====';
                    $one[1] = 'javascript:void(0);return false;';
                } else {
                    $one[1] = url($one[1]);
                }
                $menus[1]['children'][] = ['id' => 14 + $k,'type' => 1, 'title' => $one[0], 'href' => $one[1]];
            }
        }
        //$this->assign('menus',$menus);

        foreach ($menus as $kkp => $pear) {
            unset($menus[$kkp]['children']);
            $menus[$kkp]['children'] = array_values($pear['children']);
        }

        header("Content-Type:application/json; charset=utf-8");
        return json(array_values($menus));
    }

    public function welcome()
    {
        $version = config('version');
        $update_sql = file_exists('./application/data/update/database.php');

        $this->assign('version', $version);
        $this->assign('update_sql', $update_sql);
        $this->assign('mac_lang', config('default_lang'));

        $this->assign('admin', $this->_admin);
        $this->assign('title', lang('admin/index/welcome/title'));
        return $this->fetch('admin@index/welcome');
    }

    public function quickmenu()
    {
        if (Request()->isPost()) {
            $param = input();
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($param)) {
                return $this->error($validate->getError());
            }
            $quickmenu = input('post.quickmenu');
            $quickmenu = str_replace(chr(10), '', $quickmenu);
            $menu_arr = explode(chr(13), $quickmenu);
            $res = mac_arr2file(APP_PATH . 'extra/quickmenu.php', $menu_arr);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        } else {
            $config_menu = config('quickmenu');
            if (empty($config_menu)) {
                $quickmenu = mac_read_file(APP_PATH . 'data/config/quickmenu.txt');
            } else {
                $quickmenu = array_values($config_menu);
                $quickmenu = join(chr(13), $quickmenu);
            }
            $this->assign('quickmenu', $quickmenu);
            $this->assign('title', lang('admin/index/quickmenu/title'));
            return $this->fetch('admin@index/quickmenu');
        }
    }

    public function checkcache()
    {
        $res = 'no';
        $r = cache('cache_data');
        if ($r == '1') {
            $res = 'haved';
        }
        echo $res;
    }

    public function clear()
    {
        $res = $this->_cache_clear();
        //运行缓存
        if (!$res) {
            $this->error(lang('admin/index/clear_err'));
        }
        return $this->success(lang('admin/index/clear_ok'));
    }

    public function iframe()
    {
        $val = input('post.val', 0);
        if ($val != 0 && $val != 1) {
            return $this->error(lang('admin/index/clear_ok'));
        }
        if ($val == 1) {
            cookie('is_iframe', 'yes');
        } else {
            cookie('is_iframe', null);
        }
        return $this->success(lang('admin/index/iframe'));
    }

    public function unlocked()
    {
        $param = input();
        $password = $param['password'];

        if ($this->_admin['admin_pwd'] != md5($password)) {
            return $this->error(lang('admin/index/pass_err'));
        }

        return $this->success(lang('admin/index/unlock_ok'));
    }

    public function check_back_link()
    {
        $param = input();
        $res = mac_check_back_link($param['url']);
        return json($res);
    }

    public function select()
    {
        $param = input();
        $tpl = $param['tpl'];
        $tab = $param['tab'];
        $col = $param['col'];
        $ids = $param['ids'];
        $url = $param['url'];
        $val = $param['val'];

        $refresh = $param['refresh'];

        if (empty($tpl) || empty($tab) || empty($col) || empty($ids) || empty($url)) {
            return $this->error(lang('param_err'));
        }

        if (is_array($ids)) {
            $ids = join(',', $ids);
        }

        if (empty($refresh)) {
            $refresh = 'yes';
        }

        $url = url($url);
        $mid = 1;
        if ($tab == 'art') {
            $mid = 2;
        } elseif ($tab == 'actor') {
            $mid = 8;
        } elseif ($tab == 'website') {
            $mid = 11;
        }
        $this->assign('mid', $mid);

        if ($tpl == 'select_type') {
            $type_tree = model('Type')->getCache('type_tree');
            $this->assign('type_tree', $type_tree);
        } elseif ($tpl == 'select_level') {
            $level_list = [1, 2, 3, 4, 5, 6, 7, 8, 9];
            $this->assign('level_list', $level_list);
        }

        $this->assign('refresh', $refresh);
        $this->assign('url', $url);
        $this->assign('tab', $tab);
        $this->assign('col', $col);
        $this->assign('ids', $ids);
        $this->assign('val', $val);
        return $this->fetch('admin@public/' . $tpl);
    }
}
