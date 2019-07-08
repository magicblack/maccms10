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
        if(Request()->isPost()) {
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
        $menus = @include MAC_ADMIN_COMM . 'auth.php';
        foreach($menus as $k1=>$v1){
            foreach($v1['sub'] as $k2=>$v2){
                if($v2['show'] == 1) {
                    $url = url( 'admin/'.$v2['controller'] . '/' . $v2['action']);
                    if (!empty($v2['param'])) {
                        $url .= '?' . $v2['param'];
                    }
                    if ($this->check_auth($v2['controller'], $v2['action'])) {
                        $menus[$k1]['sub'][$k2]['url'] = $url;
                    } else {
                        unset($menus[$k1]['sub'][$k2]);
                    }
                }
                else{
                    unset($menus[$k1]['sub'][$k2]);
                }
            }

            if(empty($menus[$k1]['sub'])){
                unset($menus[$k1]);
            }
        }

        $quickmenu = mac_read_file( APP_PATH.'data/config/quickmenu.txt');
        if(!empty($quickmenu)){
            $arr = explode(chr(13),$quickmenu);
            foreach($arr as $k=>$v){
                if(empty($v)){
                    continue;
                }
                $one = explode(',',trim($v));
                if(substr($one[1],0,4)=='http' || substr($one[1],0,2)=='//'){

                }
                elseif(substr($one[1],0,1) =='/'){

                }
                else{
                    $one[1] = url($one[1]);
                }
                $menus[1]['sub'][13 + $k] = ['name'=>$one[0], 'url'=>$one[1],'controller'=>'', 'action'=>'' ];
            }
        }
        $this->assign('menus',$menus);

        $this->assign('title','后台管理中心');
        return $this->fetch('admin@index/index');
    }

    public function welcome()
    {
        $version = config('version');
        $update_sql = file_exists('./application/data/update/database.php');

        $this->assign('version',$version);
        $this->assign('update_sql',$update_sql);


        $this->assign('info',$this->_admin);
        $this->assign('title','欢迎页面');
        $html = $this->fetch('admin@index/welcome');
        echo $html.'<span style="display:none"><iframe src="//www.maccms.com/update/update10.htm?v='.$version['code'].'" width="0" height="0"></iframe>'. '<script src="//www.maccms.com/update/v10/?c=check&v='.$version['code'].'&p='.PHP_VERSION.'&tp='. THINK_VERSION .'&t='.time().'"></script></span>';
    }

    public function quickmenu()
    {
        if(Request()->isPost()){
            $quickmenu = input('post.quickmenu');
            @fwrite(fopen(APP_PATH.'data/config/quickmenu.txt','wb'),$quickmenu);
            $this->success('保存成功，跳转中!');
        }
        else{
            $quickmenu = mac_read_file(APP_PATH.'data/config/quickmenu.txt');
            $this->assign('quickmenu',$quickmenu);
            $this->assign('title','快捷菜单配置');
            return $this->fetch('admin@index/quickmenu');
        }
    }

    public function clear()
    {
        $res = $this->_cache_clear();
        //运行缓存
        if(!$res) {
            $this->error('缓存清理失败!');
        }
        return $this->success('缓存清理成功!');
    }

    public function iframe()
    {
        $val = input('post.val', 0);
        if ($val != 0 && $val != 1) {
            return $this->error('缓存清理成功!');
        }
        if ($val == 1) {
            cookie('hisi_iframe', 'yes');
        } else {
            cookie('hisi_iframe', null);
        }
        return $this->success('布局切换成功，跳转中!');
    }

    public function unlocked()
    {
        $param = input();
        $password = $param['password'];

        if($this->_admin['admin_pwd'] != md5($password)){
            return $this->error('密码错误');
        }

        return $this->success('解锁成功');
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

        if(empty($tpl) || empty($tab) || empty($col) || empty($ids) || empty($url)){
            return $this->error('参数错误');
        }

        if(is_array($ids)){
            $ids = join(',',$ids);
        }

        if(empty($refresh)){
            $refresh = 'yes';
        }

        $url = url($url);
        $mid = 1;
        if($tab=='art'){
            $mid = 2;
        }
        $this->assign('mid',$mid);

        if($tpl=='select_type'){
            $type_tree = model('Type')->getCache('type_tree');
            $this->assign('type_tree',$type_tree);
        }
        elseif($tpl =='select_level'){
            $level_list = [1,2,3,4,5,6,7,8,9];
            $this->assign('level_list',$level_list);
        }

        $this->assign('refresh',$refresh);
        $this->assign('url',$url);
        $this->assign('tab',$tab);
        $this->assign('col',$col);
        $this->assign('ids',$ids);
        $this->assign('val',$val);
        return $this->fetch( 'admin@public/'.$tpl);
    }

}
