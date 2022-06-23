<?php
namespace app\admin\controller;
use http\Cookie;
use think\Db;
use think\Config;
use think\Cache;
use think\View;

class System extends Base
{

    public function test_email()
    {
        $post = input();
        $conf = [
            'nick' => $post['nick'],
        ];
        $type = strtolower($post['type']);
        $to = $post['test'];
        $conf['host'] = $GLOBALS['config']['email'][$type]['host'];
        $conf['port'] = $GLOBALS['config']['email'][$type]['port'];
        $conf['username'] = $GLOBALS['config']['email'][$type]['username'];
        $conf['password'] = $GLOBALS['config']['email'][$type]['password'];
        $conf['secure'] = $GLOBALS['config']['email'][$type]['secure'];
        $this->label_maccms();

        $title = $GLOBALS['config']['email']['tpl']['test_title'];
        $msg = $GLOBALS['config']['email']['tpl']['test_body'];
        $code = mac_get_rndstr(6,'num');
        View::instance()->assign(['code'=>$code,'time'=>$GLOBALS['config']['email']['time']]);
        $title =  View::instance()->display($title);
        $msg =  View::instance()->display($msg);
        $msg = htmlspecialchars_decode($msg);
        $res = mac_send_mail($to, $title, $msg, $conf);
        if ($res['code']==1) {
            return json(['code' => 1, 'msg' => lang('test_ok')]);
        }
        return json(['code' => 1001, 'msg' => lang('test_err').'：'.$res['msg']]);
    }

    public function test_cache()
    {
        $param = input();

        if (!isset($param['type']) || empty($param['host']) || empty($param['port'])) {
            return $this->error(lang('param_err'));
        }

        $options = [
            'type' => $param['type'],
            'port' => $param['port'],
            'username' => $param['username'],
            'password' => $param['password']
        ];

        $hd = Cache::connect($options);
        $hd->set('test', 'test');

        return json(['code' => 1, 'msg' => lang('test_ok')]);
    }

    public function config()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);


            $ads_dir='ads';
            $mob_ads_dir='ads';
            $path = ROOT_PATH .'template/'.$config['site']['template_dir'].'/info.ini';
            $cc = Config::load($path,'ini');
            if(!empty($cc['adsdir'])){
                $ads_dir = $cc['adsdir'];
            }

            $path = ROOT_PATH .'template/'.$config['site']['mob_template_dir'].'/info.ini';
            $cc = Config::load($path,'ini');
            if(!empty($cc['adsdir'])){
                $mob_ads_dir = $cc['adsdir'];
            }
            $config['site']['ads_dir'] = $ads_dir;
            $config['site']['mob_ads_dir'] = $mob_ads_dir;

            if(empty($config['app']['cache_flag'])){
                $config['app']['cache_flag'] = substr(md5(time()),0,10);
            }

            $config['app']['search_vod_rule'] = join('|', $config['app']['search_vod_rule']);
            $config['app']['search_art_rule'] = join('|', $config['app']['search_art_rule']);
            $config['app']['vod_search_optimise'] = join('|', !empty($config['app']['vod_search_optimise']) ? (array)$config['app']['vod_search_optimise'] : []);

            $config['extra'] = [];
            if(!empty($config['app']['extra_var'])){
                $extra_var = str_replace(array(chr(10),chr(13)), array('','#'),$config['app']['extra_var']);
                $tmp = explode('#',$extra_var);
                foreach($tmp as $a){
                    if(strpos($a,'$$$')!==false){
                        $tmp2 = explode('$$$',$a);
                        $config['extra'][$tmp2[0]] = $tmp2[1];
                    }
                }
                unset($tmp,$tmp2);
            }

            $config['site']['site_tj'] = html_entity_decode($config['site']['site_tj']);
            $config_new['site'] = $config['site'];
            $config_new['app'] = $config['app'];
            $config_new['extra'] = $config['extra'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);


            $tj = $config_new['site']['site_tj'];
            if(strpos($tj,'document.w') ===false){
                $tj = 'document.write(\'' . str_replace("'","\'",$tj) . '\')';
            }
            $res = @fwrite(fopen('./static/js/tj.js', 'wb'), $tj);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }


        $templates = glob('./template' . '/*', GLOB_ONLYDIR);
        foreach ($templates as $k => &$v) {
            $v = str_replace('./template/', '', $v);
        }
        $this->assign('templates', $templates);

        $langs = glob('./application/lang/*.php');
        foreach ($langs as $k => &$v) {
            $v = str_replace(['./application/lang/','.php'],['',''],$v);
        }
        $this->assign('langs', $langs);

        $usergroup = Db::name('group')->select();
        $this->assign('usergroup', $usergroup);

        $editors = mac_extends_list('editor');
        $this->assign('editors',$editors);

        $config = config('maccms');
        // 默认get+post
        if (!isset($config['app']['input_type'])) {
            $config['app']['input_type'] = 1;
        }
        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/config/title'));
        return $this->fetch('admin@system/config');
    }


    public function configurl()
    {
        if (Request()->isPost()) {
            $config = input();

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['view'] = $config['view'];
            $config_new['path'] = $config['path'];
            $config_new['rewrite'] = $config['rewrite'];

            //写路由规则文件
            $route = [];
            $route['__pattern__'] = [

                'id'=>'[\s\S]*?',
                'ids'=>'[\s\S]*?',
                'wd' => '[\s\S]*',
                'en'=>'[\s\S]*?',
                'state' => '[\s\S]*?',
                'area' => '[\s\S]*',
                'year'=>'[\s\S]*?',
                'lang' => '[\s\S]*?',
                'letter'=>'[\s\S]*?',
                'actor' => '[\s\S]*?',
                'director' => '[\s\S]*?',
                'tag' => '[\s\S]*?',
                'class' => '[\s\S]*?',
                'order'=>'[\s\S]*?',
                'by'=>'[\s\S]*?',
                'file'=>'[\s\S]*?',
                'name'=>'[\s\S]*?',
                'url'=>'[\s\S]*?',
                'type'=>'[\s\S]*?',
                'sex' => '[\s\S]*?',
                'version' => '[\s\S]*?',
                'blood' => '[\s\S]*?',
                'starsign' => '[\s\S]*?',
                'page'=>'\d+',
                'ajax'=>'\d+',
                'tid'=>'\d+',
                'mid'=>'\d+',
                'rid'=>'\d+',
                'pid'=>'\d+',
                'sid'=>'\d+',
                'nid'=>'\d+',
                'uid'=>'\d+',
                'level'=>'\d+',
                'score'=>'\d+',
                'limit'=>'\d+',
            ];
            $rows = explode(chr(13), str_replace(chr(10), '', $config['rewrite']['route']));
            foreach ($rows as $r) {
                if (strpos($r, '=>') !== false) {
                    $a = explode('=>', $r);
                    $rule = [];
//                    if (strpos($a, ':id') !== false) {
                        //$rule['id'] = '\w+';
//                    }
                    $route[trim($a[0])] = [trim($a[1]), [], $rule];
                }
            }

            $res = mac_arr2file(APP_PATH . 'route.php', $route);
            if ($res === false) {
                return $this->error(lang('write_err_route'));
            }

            //写扩展配置
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('write_err_config'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configurl/title'));
        return $this->fetch('admin@system/configurl');
    }

    public function configuser()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['user'] = $config['user'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configuser/title'));
        return $this->fetch('admin@system/configuser');
    }

    public function configupload()
    {
        if (Request()->isPost()){
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['upload'] = $config['upload'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('upload');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configupload/title'));
        return $this->fetch('admin@system/configupload');
    }

    public function configcomment()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['gbook'] = $config['gbook'];
            $config_new['comment'] = $config['comment'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configcomment/title'));
        return $this->fetch('admin@system/configcomment');
    }

    public function configweixin()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['weixin'] = $config['weixin'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configweixin/title'));
        return $this->fetch('admin@system/configweixin');
    }

    public function configpay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['pay'] = $config['pay'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('http_type',$GLOBALS['http_type']);
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('pay');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configpay/title'));
        return $this->fetch('admin@system/configpay');
    }

    public function configconnect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['connect'] = $config['connect'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configconnect/title'));
        return $this->fetch('admin@system/configconnect');
    }

    public function configemail()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['email'] = $config['email'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('email');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configemail/title'));
        return $this->fetch('admin@system/configemail');
    }

    public function configsms()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);


            $config_new['sms'] = $config['sms'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }
        $this->assign('config', config('maccms'));

        $extends = mac_extends_list('sms');
        $this->assign('extends',$extends);

        $this->assign('title', lang('admin/system/configsms/title'));
        return $this->fetch('admin@system/configsms');
    }

    public function configapi()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['api'] = $config['api'];

            $config_new['api']['vod']['auth'] = mac_replace_text($config_new['api']['vod']['auth'], 2);
            $config_new['api']['art']['auth'] = mac_replace_text($config_new['api']['art']['auth'], 2);
            $config_new['api']['actor']['auth'] = mac_replace_text($config_new['api']['actor']['auth'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configapi/title'));
        return $this->fetch('admin@system/configapi');
    }

    public function configinterface()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            if($config['interface']['status']==1 && strlen($config['interface']['pass']) < 16){
                return $this->error(lang('admin/system/configinterface/pass_check'));
            }

            $config_new['interface'] = $config['interface'];
            $config_new['interface']['vodtype'] = mac_replace_text($config_new['interface']['vodtype'], 2);
            $config_new['interface']['arttype'] = mac_replace_text($config_new['interface']['arttype'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }

            //保存缓存
            mac_interface_type();

            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configinterface/title'));
        return $this->fetch('admin@system/configinterface');
    }

    public function configcollect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['collect'] = $config['collect'];
            if (empty($config_new['collect']['vod']['inrule'])) {
                $config_new['collect']['vod']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['vod']['uprule'])) {
                $config_new['collect']['vod']['uprule'] = [];
            }
            if (empty($config_new['collect']['art']['inrule'])) {
                $config_new['collect']['art']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['art']['uprule'])) {
                $config_new['collect']['art']['uprule'] = [];
            }
            if (empty($config_new['collect']['actor']['inrule'])) {
                $config_new['collect']['actor']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['actor']['uprule'])) {
                $config_new['collect']['actor']['uprule'] = [];
            }
            if (empty($config_new['collect']['role']['inrule'])) {
                $config_new['collect']['role']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['role']['uprule'])) {
                $config_new['collect']['role']['uprule'] = [];
            }
            if (empty($config_new['collect']['website']['inrule'])) {
                $config_new['collect']['website']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['website']['uprule'])) {
                $config_new['collect']['website']['uprule'] = [];
            }
            if (empty($config_new['collect']['comment']['inrule'])) {
                $config_new['collect']['comment']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['comment']['uprule'])) {
                $config_new['collect']['comment']['uprule'] = [];
            }

            $config_new['collect']['vod']['inrule'] = ',' . join(',', $config_new['collect']['vod']['inrule']);
            $config_new['collect']['vod']['uprule'] = ',' . join(',', $config_new['collect']['vod']['uprule']);
            $config_new['collect']['art']['inrule'] = ',' . join(',', $config_new['collect']['art']['inrule']);
            $config_new['collect']['art']['uprule'] = ',' . join(',', $config_new['collect']['art']['uprule']);
            $config_new['collect']['actor']['inrule'] = ',' . join(',', $config_new['collect']['actor']['inrule']);
            $config_new['collect']['actor']['uprule'] = ',' . join(',', $config_new['collect']['actor']['uprule']);
            $config_new['collect']['role']['inrule'] = ',' . join(',', $config_new['collect']['role']['inrule']);
            $config_new['collect']['role']['uprule'] = ',' . join(',', $config_new['collect']['role']['uprule']);
            $config_new['collect']['website']['inrule'] = ',' . join(',', $config_new['collect']['website']['inrule']);
            $config_new['collect']['website']['uprule'] = ',' . join(',', $config_new['collect']['website']['uprule']);
            $config_new['collect']['comment']['inrule'] = ',' . join(',', $config_new['collect']['comment']['inrule']);
            $config_new['collect']['comment']['uprule'] = ',' . join(',', $config_new['collect']['comment']['uprule']);

            $config_new['collect']['vod']['namewords'] = mac_replace_text($config_new['collect']['vod']['namewords'], 2);
            $config_new['collect']['vod']['thesaurus'] = mac_replace_text($config_new['collect']['vod']['thesaurus'], 2);
            $config_new['collect']['vod']['playerwords'] = mac_replace_text($config_new['collect']['vod']['playerwords'], 2);
            $config_new['collect']['vod']['areawords'] = mac_replace_text($config_new['collect']['vod']['areawords'], 2);
            $config_new['collect']['vod']['langwords'] = mac_replace_text($config_new['collect']['vod']['langwords'], 2);
            $config_new['collect']['vod']['words'] = mac_replace_text($config_new['collect']['vod']['words'], 2);
            $config_new['collect']['art']['thesaurus'] = mac_replace_text($config_new['collect']['art']['thesaurus'], 2);
            $config_new['collect']['art']['words'] = mac_replace_text($config_new['collect']['art']['words'], 2);
            $config_new['collect']['actor']['thesaurus'] = mac_replace_text($config_new['collect']['actor']['thesaurus'], 2);
            $config_new['collect']['actor']['words'] = mac_replace_text($config_new['collect']['actor']['words'], 2);
            $config_new['collect']['role']['thesaurus'] = mac_replace_text($config_new['collect']['role']['thesaurus'], 2);
            $config_new['collect']['role']['words'] = mac_replace_text($config_new['collect']['role']['words'], 2);
            $config_new['collect']['website']['thesaurus'] = mac_replace_text($config_new['collect']['website']['thesaurus'], 2);
            $config_new['collect']['website']['words'] = mac_replace_text($config_new['collect']['website']['words'], 2);
            $config_new['collect']['comment']['thesaurus'] = mac_replace_text($config_new['collect']['comment']['thesaurus'], 2);
            $config_new['collect']['comment']['words'] = mac_replace_text($config_new['collect']['comment']['words'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }


        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configcollect/title'));
        return $this->fetch('admin@system/configcollect');
    }

    public function configplay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['play'] = $config['play'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }

            $path = './static/js/playerconfig.js';
            if (!file_exists($path)) {
                $path .= '.bak';
            }
            $fc = @file_get_contents($path);
            $jsb = mac_get_body($fc, '//参数开始', '//参数结束');
            $content = 'MacPlayerConfig=' . json_encode($config['play']) . ';';
            $fc = str_replace($jsb, "\r\n" . $content . "\r\n", $fc);
            $res = @fwrite(fopen('./static/js/playerconfig.js', 'wb'), $fc);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $fp = './static/js/playerconfig.js';
        if (!file_exists($fp)) {
            $fp .= '.bak';
        }
        $fc = file_get_contents($fp);
        $jsb = trim(mac_get_body($fc, '//参数开始', '//参数结束'));
        $jsb = substr($jsb, 16, strlen($jsb) - 17);

        $play = json_decode($jsb, true);
        $this->assign('play', $play);
        $this->assign('title', lang('admin/system/configplay/title'));
        return $this->fetch('admin@system/configplay');
    }

    public function configseo()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                return $this->error($validate->getError());
            }
            unset($config['__token__']);

            $config_new['seo'] = $config['seo'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', lang('admin/system/configseo/title'));
        return $this->fetch('admin@system/configseo');
    }


}
