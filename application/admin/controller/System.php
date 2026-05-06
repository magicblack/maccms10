<?php
namespace app\admin\controller;
use app\common\util\ExternalSyncRunner;
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
            'host' => $param['host'],
            'port' => $param['port'],
            'username' => $param['username'],
            'password' => $param['password']
        ];

        if ($param['type'] == 'redis' && isset($param['db']) && intval($param['db']) > 0) {
            $options['select'] = intval($param['db']);
        }

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
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);
            $invalidSubmitMsg = '提交数据不完整，请刷新页面后重试';
            if (
                !isset($config['site']) || !is_array($config['site']) ||
                !isset($config['app']) || !is_array($config['app'])
            ) {
                return $this->error($invalidSubmitMsg);
            }
            $requiredSiteKeys = ['site_name', 'site_url', 'template_dir', 'mob_template_dir'];
            foreach ($requiredSiteKeys as $requiredKey) {
                if (!isset($config['site'][$requiredKey]) || trim((string)$config['site'][$requiredKey]) === '') {
                    return $this->error($invalidSubmitMsg);
                }
            }
            if (!isset($config['app']['pathinfo_depr']) || trim((string)$config['app']['pathinfo_depr']) === '') {
                return $this->error($invalidSubmitMsg);
            }


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

            $config['app']['search_vod_rule'] = join('|', !empty($config['app']['search_vod_rule']) ? (array)$config['app']['search_vod_rule'] : []);
            $config['app']['search_art_rule'] = join('|', !empty($config['app']['search_art_rule']) ? (array)$config['app']['search_art_rule'] : []);
            $config['app']['vod_search_optimise'] = join('|', !empty($config['app']['vod_search_optimise']) ? (array)$config['app']['vod_search_optimise'] : []);
            $config['app']['vod_search_optimise_cache_minutes'] = (int)$config['app']['vod_search_optimise_cache_minutes'];

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
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
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
        $config['app']['vod_search_optimise_cache_minutes'] = model('VodSearch')->getResultCacheMinutes($config);
        if (!isset($config['ai_seo']) || !is_array($config['ai_seo'])) {
            $config['ai_seo'] = [
                'enabled' => '0',
                'auto_generate' => '1',
                'template_inject' => '1',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_base' => 'https://api.openai.com/v1',
                'api_key' => '',
                'timeout' => '20',
            ];
        }
        $apiKey = isset($config['ai_seo']['api_key']) ? trim((string) $config['ai_seo']['api_key']) : '';
        $this->assign('ai_seo_key_saved', $apiKey !== '' ? 1 : 0);
        $this->assign('ai_seo_key_tail', $apiKey !== '' ? substr($apiKey, -6) : '');

        $this->assign('form_action_configseo', (string) url('configseo'));
        $this->assign('form_action_configaiseo', (string) url('configaiseo'));
        $tab = (string) input('tab', '');
        $allowTab = ['', 'seo', 'aiseo'];
        $this->assign('config_merge_tab', in_array($tab, $allowTab, true) ? $tab : '');
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
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
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

        return $this->redirect( url('configupload', ['tab' => 'url']) );
    }

    public function configuser()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['user'] = $config['user'];
            
            if(isset($config['user']['invite_reward']) && is_array($config['user']['invite_reward'])) {
                $invite_reward = [];
                foreach($config['user']['invite_reward'] as $k => $v) {
                    if(!empty($v['count'])) {
                        $invite_reward[$v['count']] = [
                            'group_id' => intval($v['group_id']),
                            'long' => $v['long'],
                            'points' => intval($v['points'])
                        ];
                    }
                }
                $config_new['user']['invite_reward'] = $invite_reward;
            }
            
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $config = config('maccms');
        
        $invite_reward_form = [];
        if(isset($config['user']['invite_reward']) && is_array($config['user']['invite_reward'])) {
            foreach($config['user']['invite_reward'] as $count => $reward) {
                $invite_reward_form[] = [
                    'count' => $count,
                    'group_id' => $reward['group_id'],
                    'long' => $reward['long'],
                    'points' => $reward['points']
                ];
            }
        }
        
        while(count($invite_reward_form) < 3) {
            $invite_reward_form[] = ['count' => '', 'group_id' => '3', 'long' => 'month', 'points' => 0];
        }
        $this->assign('invite_reward_form', $invite_reward_form);
        
        $group_list = \think\Db::name('group')->select();
        $this->assign('group_list', $group_list);
        
        $this->assign('form_action_configcomment', (string) url('configcomment'));
        $tab = (string) input('tab', '');
        $allowTab = ['', 'comment'];
        $this->assign('config_merge_tab', in_array($tab, $allowTab, true) ? $tab : '');
        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/configuser/title'));
        return $this->fetch('admin@system/configuser');
    }

    public function configupload()
    {
        $phar_status = file_exists(ROOT_PATH . 'extend/aws/src/Aws/aws.phar');
        if (Request()->isPost()){
            $config = input('','','htmlentities');
            $invalidSubmitMsg = '提交数据不完整，请刷新页面后重试';
            if (
                !isset($config['upload']) || !is_array($config['upload']) ||
                !isset($config['upload']['mode']) || trim((string)$config['upload']['mode']) === '' ||
                !isset($config['upload']['protocol']) || trim((string)$config['upload']['protocol']) === '' ||
                !isset($config['upload']['keep_local'])
            ) {
                return $this->error($invalidSubmitMsg);
            }
            if($config['upload']['mode'] == 'S3' && $phar_status == false){
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['upload'] = $config['upload'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        if ($phar_status) {
            $aws_phar = 'Yes';
        }else{
            $aws_phar = 'No';
        }
        $this->assign('aws_phar',$aws_phar);
        $extends = mac_extends_list('upload');
        $this->assign('extends',$extends);

        $fp = './static/js/playerconfig.js';
        if (!file_exists($fp)) {
            $fp .= '.bak';
        }
        $fc = file_exists($fp) ? @file_get_contents($fp) : '';
        $play = [];
        if ($fc) {
            $jsb = trim(mac_get_body($fc, '//参数开始', '//参数结束'));
            if (strlen($jsb) > 17) {
                $jsb = substr($jsb, 16, strlen($jsb) - 17);
                $play = (array) json_decode($jsb, true);
            }
        }
        $this->assign('play', $play);

        $this->assign('form_action_configurl', (string) url('configurl'));
        $this->assign('form_action_configplay', (string) url('configplay'));
        $tab = (string) input('tab', '');
        $allowTab = ['', 'url', 'play'];
        $this->assign('config_merge_tab', in_array($tab, $allowTab, true) ? $tab : '');
        $this->assign('title', lang('admin/system/configupload/title'));
        return $this->fetch('admin@system/configupload');
    }

    public function configcomment()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');
            $invalidSubmitMsg = '提交数据不完整，请刷新页面后重试';
            // 评论留言独立表单只提交 gbook / comment，不包含会员配置 user[*]
            if (
                !isset($config['gbook']) || !is_array($config['gbook']) ||
                !isset($config['comment']) || !is_array($config['comment'])
            ) {
                return $this->error($invalidSubmitMsg);
            }

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['gbook'] = $config['gbook'];
            $config_new['comment'] = $config['comment'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configuser', ['tab' => 'comment']) );
    }

    public function configweixin()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['weixin'] = $config['weixin'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configconnect', ['tab' => 'weixin']) );
    }

    public function configpay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['pay'] = $config['pay'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configconnect', ['tab' => 'pay']) );
    }

    public function configconnect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['connect'] = $config['connect'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $this->assign('config', config('maccms'));
        $this->assign('http_type', $GLOBALS['http_type']);
        $extends = mac_extends_list('pay');
        $this->assign('extends', $extends);
        $this->assign('form_action_configweixin', (string) url('configweixin'));
        $this->assign('form_action_configpay', (string) url('configpay'));
        $tab = (string) input('tab', '');
        $allowTab = ['', 'weixin', 'pay'];
        $this->assign('config_merge_tab', in_array($tab, $allowTab, true) ? $tab : '');
        $this->assign('title', lang('admin/system/configconnect/title'));
        return $this->fetch('admin@system/configconnect');
    }

    public function configemail()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['email'] = $config['email'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
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
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);


            $config_new['sms'] = $config['sms'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
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
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['api'] = $config['api'];

            $config_new['api']['vod']['auth'] = mac_replace_text($config_new['api']['vod']['auth'], 2);
            $config_new['api']['art']['auth'] = mac_replace_text($config_new['api']['art']['auth'], 2);
            $config_new['api']['actor']['auth'] = mac_replace_text($config_new['api']['actor']['auth'], 2);
            $config_new['api']['manga']['auth'] = mac_replace_text($config_new['api']['manga']['auth'], 2);
            $config_new['api']['publicapi']['auth'] = mac_replace_text($config_new['api']['publicapi']['auth'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configcollect', ['tab' => 'api']) );
    }

    public function configinterface()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
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
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }

            //保存缓存
            mac_interface_type();

            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configcollect', ['tab' => 'interface']) );
    }

    public function configcollect()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
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
            if (empty($config_new['collect']['manga']['inrule'])) {
                $config_new['collect']['manga']['inrule'] = ['a'];
            }
            if (empty($config_new['collect']['manga']['uprule'])) {
                $config_new['collect']['manga']['uprule'] = [];
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
            $config_new['collect']['manga']['inrule'] = ',' . join(',', $config_new['collect']['manga']['inrule']);
            $config_new['collect']['manga']['uprule'] = ',' . join(',', $config_new['collect']['manga']['uprule']);

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
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }


        $config = config('maccms');
        if (!isset($config['api']['publicapi'])) {
            $config['api']['publicapi'] = [
                'status' => '0',
                'charge' => '0',
                'auth' => '',
            ];
        }
        if (!isset($config['api']['manga'])) {
            $config['api']['manga'] = [
                'status' => '0',
                'charge' => '0',
                'pagesize' => '20',
                'imgurl' => '',
                'typefilter' => '',
                'datafilter' => 'manga_status=1',
                'cachetime' => '',
                'auth' => '',
            ];
        }
        $this->assign('form_action_configinterface', (string) url('configinterface'));
        $this->assign('form_action_configapi', (string) url('configapi'));
        $tab = (string) input('tab', '');
        $allowTab = ['', 'api', 'interface'];
        $this->assign('config_merge_tab', in_array($tab, $allowTab, true) ? $tab : '');
        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/configcollect/title'));
        return $this->fetch('admin@system/configcollect');
    }

    public function configplay()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['play'] = $config['play'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
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
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('configupload', ['tab' => 'play']) );
    }

    public function configseo()
    {
        if (Request()->isPost()) {
            $config = input('','','htmlentities');

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($config)){
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($config['__token__']);

            $config_new['seo'] = $config['seo'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('config', ['tab' => 'seo']) );
    }

    public function configaiseo()
    {
        if (Request()->isPost()) {
            $post = input('post.', '', 'htmlentities');
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($post)) {
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($post['__token__']);

            $config_old = config('maccms');
            $ai = isset($post['ai_seo']) && is_array($post['ai_seo']) ? $post['ai_seo'] : [];

            $sanitize = function ($v) {
                return trim(strip_tags((string)$v));
            };

            $row = [
                'enabled' => isset($ai['enabled']) && (string)$ai['enabled'] === '1' ? '1' : '0',
                'auto_generate' => isset($ai['auto_generate']) && (string)$ai['auto_generate'] === '1' ? '1' : '0',
                'template_inject' => isset($ai['template_inject']) && (string)$ai['template_inject'] === '1' ? '1' : '0',
                'provider' => $sanitize(isset($ai['provider']) ? $ai['provider'] : 'openai'),
                'model' => $sanitize(isset($ai['model']) ? $ai['model'] : 'gpt-4o-mini'),
                'api_base' => $sanitize(isset($ai['api_base']) ? $ai['api_base'] : 'https://api.openai.com/v1'),
                'timeout' => (string)max(5, intval(isset($ai['timeout']) ? $ai['timeout'] : 20)),
            ];
            if ($row['api_base'] === '') {
                $row['api_base'] = 'https://api.openai.com/v1';
            }

            $newKey = isset($ai['api_key']) ? trim((string)$ai['api_key']) : '';
            if ($newKey !== '') {
                $row['api_key'] = $newKey;
            } else {
                // Read from latest config file to avoid accidental key loss when config cache is stale.
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                if (isset($latest['ai_seo']['api_key']) && $latest['ai_seo']['api_key'] !== '') {
                    $row['api_key'] = (string)$latest['ai_seo']['api_key'];
                } else {
                    $row['api_key'] = isset($config_old['ai_seo']['api_key']) ? $config_old['ai_seo']['api_key'] : '';
                }
            }

            $config_new = $config_old;
            $config_new['ai_seo'] = $row;

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        return $this->redirect( url('config', ['tab' => 'aiseo']) );
    }

    public function configaisearch()
    {
        if (Request()->isPost()) {
            $post = input('post.', '', 'htmlentities');
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($post)) {
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($post['__token__']);

            $config_old = config('maccms');
            $ai = isset($post['ai_search']) && is_array($post['ai_search']) ? $post['ai_search'] : [];

            $sanitize = function ($v) {
                return trim(strip_tags((string)$v));
            };

            $module = isset($ai['module']) && is_array($ai['module']) ? $ai['module'] : [];
            $ext = isset($ai['external_sources']) && is_array($ai['external_sources']) ? $ai['external_sources'] : [];
            $tmdb = isset($ext['sources']['tmdb']) && is_array($ext['sources']['tmdb']) ? $ext['sources']['tmdb'] : [];
            $douban = isset($ext['sources']['douban']) && is_array($ext['sources']['douban']) ? $ext['sources']['douban'] : [];
            $imdb = isset($ext['sources']['imdb']) && is_array($ext['sources']['imdb']) ? $ext['sources']['imdb'] : [];
            $row = [
                'enabled' => isset($ai['enabled']) && (string)$ai['enabled'] === '1' ? '1' : '0',
                'provider' => $sanitize(isset($ai['provider']) ? $ai['provider'] : 'openai'),
                'model' => $sanitize(isset($ai['model']) ? $ai['model'] : 'gpt-4o-mini'),
                'response_language' => (function () use ($ai) {
                    $lang = strtolower(trim((string)(isset($ai['response_language']) ? $ai['response_language'] : 'auto')));
                    $allow = ['auto', 'zh', 'en', 'ja', 'ko', 'fr', 'es', 'de', 'pt'];
                    return in_array($lang, $allow, true) ? $lang : 'auto';
                })(),
                'api_base' => $sanitize(isset($ai['api_base']) ? $ai['api_base'] : 'https://api.openai.com/v1'),
                'timeout' => (string)max(3, intval(isset($ai['timeout']) ? $ai['timeout'] : 12)),
                'max_terms' => (string)max(1, intval(isset($ai['max_terms']) ? $ai['max_terms'] : 4)),
                'min_query_len' => (string)max(1, intval(isset($ai['min_query_len']) ? $ai['min_query_len'] : 2)),
                'debug_log' => isset($ai['debug_log']) && (string)$ai['debug_log'] === '1' ? '1' : '0',
                'external_enabled' => isset($ai['external_enabled']) && (string)$ai['external_enabled'] === '1' ? '1' : '0',
                'external_domains' => $sanitize(isset($ai['external_domains']) ? $ai['external_domains'] : 'wikipedia.org,imdb.com,douban.com'),
                'external_max_links' => (string)max(1, min(10, intval(isset($ai['external_max_links']) ? $ai['external_max_links'] : 3))),
                'semantic_enabled' => isset($ai['semantic_enabled']) && (string)$ai['semantic_enabled'] === '1' ? '1' : '0',
                'embedding_model' => $sanitize(isset($ai['embedding_model']) ? $ai['embedding_model'] : 'text-embedding-3-small'),
                'semantic_weight' => (function () use ($ai) {
                    $w = floatval(isset($ai['semantic_weight']) ? $ai['semantic_weight'] : '0.45');
                    if ($w < 0) {
                        $w = 0;
                    }
                    if ($w > 1) {
                        $w = 1;
                    }
                    return (string)$w;
                })(),
                'semantic_candidates' => (string)max(8, min(64, intval(isset($ai['semantic_candidates']) ? $ai['semantic_candidates'] : 40))),
                'rate_limit_enabled' => isset($ai['rate_limit_enabled']) && (string)$ai['rate_limit_enabled'] === '1' ? '1' : '0',
                'rate_limit_window' => (string)max(10, min(3600, intval(isset($ai['rate_limit_window']) ? $ai['rate_limit_window'] : 60))),
                'rate_limit_max' => (string)max(1, min(500, intval(isset($ai['rate_limit_max']) ? $ai['rate_limit_max'] : 20))),
                'max_question_chars' => (string)max(0, min(8000, intval(isset($ai['max_question_chars']) ? $ai['max_question_chars'] : 800))),
                'module' => [
                    'vod' => isset($module['vod']) && (string)$module['vod'] === '1' ? '1' : '0',
                    'art' => isset($module['art']) && (string)$module['art'] === '1' ? '1' : '0',
                    'manga' => isset($module['manga']) && (string)$module['manga'] === '1' ? '1' : '0',
                    'topic' => isset($module['topic']) && (string)$module['topic'] === '1' ? '1' : '0',
                    'actor' => isset($module['actor']) && (string)$module['actor'] === '1' ? '1' : '0',
                    'role' => isset($module['role']) && (string)$module['role'] === '1' ? '1' : '0',
                    'plot' => isset($module['plot']) && (string)$module['plot'] === '1' ? '1' : '0',
                    'website' => isset($module['website']) && (string)$module['website'] === '1' ? '1' : '0',
                ],
                'external_sources' => [
                    'enabled' => isset($ext['enabled']) && (string)$ext['enabled'] === '1' ? '1' : '0',
                    'use_live' => isset($ext['use_live']) && (string)$ext['use_live'] === '1' ? '1' : '0',
                    'use_cache' => isset($ext['use_cache']) && (string)$ext['use_cache'] === '1' ? '1' : '0',
                    'cache_ttl' => (string)max(60, intval(isset($ext['cache_ttl']) ? $ext['cache_ttl'] : 21600)),
                    'merge_limit' => (string)max(1, min(12, intval(isset($ext['merge_limit']) ? $ext['merge_limit'] : 4))),
                    'sync_interval' => (string)max(300, intval(isset($ext['sync_interval']) ? $ext['sync_interval'] : 21600)),
                    'sources' => [
                        'tmdb' => [
                            'enabled' => isset($tmdb['enabled']) && (string)$tmdb['enabled'] === '1' ? '1' : '0',
                            'base_url' => $sanitize(isset($tmdb['base_url']) ? $tmdb['base_url'] : 'https://api.themoviedb.org/3'),
                            'image_base_url' => $sanitize(isset($tmdb['image_base_url']) ? $tmdb['image_base_url'] : 'https://image.tmdb.org/t/p/w500'),
                            'language' => $sanitize(isset($tmdb['language']) ? $tmdb['language'] : 'zh-CN'),
                            'region' => $sanitize(isset($tmdb['region']) ? $tmdb['region'] : 'CN'),
                        ],
                        'douban' => [
                            'enabled' => isset($douban['enabled']) && (string)$douban['enabled'] === '1' ? '1' : '0',
                            'search_url' => $sanitize(isset($douban['search_url']) ? $douban['search_url'] : 'https://movie.douban.com/j/subject_suggest?q=__query__'),
                            'recent_url' => $sanitize(isset($douban['recent_url']) ? $douban['recent_url'] : 'https://movie.douban.com/j/search_subjects?type=movie&tag=%E7%83%AD%E9%97%A8&page_limit=__limit__&page_start=0'),
                            'referer' => $sanitize(isset($douban['referer']) ? $douban['referer'] : 'https://movie.douban.com/'),
                            'user_agent' => $sanitize(isset($douban['user_agent']) ? $douban['user_agent'] : 'Mozilla/5.0'),
                        ],
                        'imdb' => [
                            'enabled' => isset($imdb['enabled']) && (string)$imdb['enabled'] === '1' ? '1' : '0',
                            'search_url' => $sanitize(isset($imdb['search_url']) ? $imdb['search_url'] : 'https://v3.sg.media-imdb.com/suggestion/__prefix__/__query__.json'),
                            'recent_seed_query' => $sanitize(isset($imdb['recent_seed_query']) ? $imdb['recent_seed_query'] : 'popular'),
                            'user_agent' => $sanitize(isset($imdb['user_agent']) ? $imdb['user_agent'] : 'Mozilla/5.0'),
                        ],
                    ],
                ],
                'anilist_enabled' => isset($ai['anilist_enabled']) && (string)$ai['anilist_enabled'] === '1' ? '1' : '0',
                'google_books_enabled' => isset($ai['google_books_enabled']) && (string)$ai['google_books_enabled'] === '1' ? '1' : '0',
                'catalog_per_source' => (string)max(1, min(8, intval(isset($ai['catalog_per_source']) ? $ai['catalog_per_source'] : 3))),
            ];
            if ($row['api_base'] === '') {
                $row['api_base'] = 'https://api.openai.com/v1';
            }
            if ($row['embedding_model'] === '') {
                $row['embedding_model'] = 'text-embedding-3-small';
            }

            $newKey = isset($ai['api_key']) ? trim((string)$ai['api_key']) : '';
            if ($newKey !== '') {
                $row['api_key'] = $newKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                if (isset($latest['ai_search']['api_key']) && $latest['ai_search']['api_key'] !== '') {
                    $row['api_key'] = (string)$latest['ai_search']['api_key'];
                } else {
                    $row['api_key'] = isset($config_old['ai_search']['api_key']) ? $config_old['ai_search']['api_key'] : '';
                }
            }
            $newTmdbKey = isset($tmdb['api_key']) ? trim((string)$tmdb['api_key']) : '';
            if ($newTmdbKey !== '') {
                $row['external_sources']['sources']['tmdb']['api_key'] = $newTmdbKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                $oldTmdbKey = '';
                if (isset($latest['ai_search']['external_sources']['sources']['tmdb']['api_key'])) {
                    $oldTmdbKey = (string)$latest['ai_search']['external_sources']['sources']['tmdb']['api_key'];
                } elseif (isset($config_old['ai_search']['external_sources']['sources']['tmdb']['api_key'])) {
                    $oldTmdbKey = (string)$config_old['ai_search']['external_sources']['sources']['tmdb']['api_key'];
                }
                $row['external_sources']['sources']['tmdb']['api_key'] = $oldTmdbKey;
            }
            $newDoubanKey = isset($douban['api_key']) ? trim((string)$douban['api_key']) : '';
            if ($newDoubanKey !== '') {
                $row['external_sources']['sources']['douban']['api_key'] = $newDoubanKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                $oldDoubanKey = '';
                if (isset($latest['ai_search']['external_sources']['sources']['douban']['api_key'])) {
                    $oldDoubanKey = (string)$latest['ai_search']['external_sources']['sources']['douban']['api_key'];
                } elseif (isset($config_old['ai_search']['external_sources']['sources']['douban']['api_key'])) {
                    $oldDoubanKey = (string)$config_old['ai_search']['external_sources']['sources']['douban']['api_key'];
                }
                $row['external_sources']['sources']['douban']['api_key'] = $oldDoubanKey;
            }
            $newImdbKey = isset($imdb['api_key']) ? trim((string)$imdb['api_key']) : '';
            if ($newImdbKey !== '') {
                $row['external_sources']['sources']['imdb']['api_key'] = $newImdbKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                $oldImdbKey = '';
                if (isset($latest['ai_search']['external_sources']['sources']['imdb']['api_key'])) {
                    $oldImdbKey = (string)$latest['ai_search']['external_sources']['sources']['imdb']['api_key'];
                } elseif (isset($config_old['ai_search']['external_sources']['sources']['imdb']['api_key'])) {
                    $oldImdbKey = (string)$config_old['ai_search']['external_sources']['sources']['imdb']['api_key'];
                }
                $row['external_sources']['sources']['imdb']['api_key'] = $oldImdbKey;
            }
            $newGoogleBooksKey = isset($ai['google_books_api_key']) ? trim((string)$ai['google_books_api_key']) : '';
            if ($newGoogleBooksKey !== '') {
                $row['google_books_api_key'] = $newGoogleBooksKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                $oldGb = '';
                if (isset($latest['ai_search']['google_books_api_key']) && $latest['ai_search']['google_books_api_key'] !== '') {
                    $oldGb = (string)$latest['ai_search']['google_books_api_key'];
                } elseif (isset($config_old['ai_search']['google_books_api_key'])) {
                    $oldGb = (string)$config_old['ai_search']['google_books_api_key'];
                }
                $row['google_books_api_key'] = $oldGb;
            }

            $config_new = $config_old;
            $config_new['ai_search'] = $row;

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $config = config('maccms');
        if (!isset($config['ai_search']) || !is_array($config['ai_search'])) {
            $config['ai_search'] = [
                'enabled' => '0',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'response_language' => 'auto',
                'api_base' => 'https://api.openai.com/v1',
                'api_key' => '',
                'timeout' => '12',
                'max_terms' => '4',
                'min_query_len' => '2',
                'debug_log' => '0',
                'external_enabled' => '0',
                'external_domains' => 'wikipedia.org,imdb.com,douban.com',
                'external_max_links' => '3',
                'semantic_enabled' => '0',
                'embedding_model' => 'text-embedding-3-small',
                'semantic_weight' => '0.45',
                'semantic_candidates' => '40',
                'rate_limit_enabled' => '1',
                'rate_limit_window' => '60',
                'rate_limit_max' => '20',
                'max_question_chars' => '800',
                'module' => [
                    'vod' => '1',
                    'art' => '1',
                    'manga' => '1',
                    'topic' => '0',
                    'actor' => '0',
                    'role' => '0',
                    'plot' => '0',
                    'website' => '0',
                ],
                'external_sources' => [
                    'enabled' => '0',
                    'use_live' => '1',
                    'use_cache' => '1',
                    'cache_ttl' => '21600',
                    'merge_limit' => '4',
                    'sync_interval' => '21600',
                    'sources' => [
                        'tmdb' => [
                            'enabled' => '0',
                            'api_key' => '',
                            'base_url' => 'https://api.themoviedb.org/3',
                            'image_base_url' => 'https://image.tmdb.org/t/p/w500',
                            'language' => 'zh-CN',
                            'region' => 'CN',
                        ],
                        'douban' => [
                            'enabled' => '0',
                            'api_key' => '',
                            'search_url' => 'https://movie.douban.com/j/subject_suggest?q=__query__',
                            'recent_url' => 'https://movie.douban.com/j/search_subjects?type=movie&tag=%E7%83%AD%E9%97%A8&page_limit=__limit__&page_start=0',
                            'referer' => 'https://movie.douban.com/',
                            'user_agent' => 'Mozilla/5.0',
                        ],
                        'imdb' => [
                            'enabled' => '0',
                            'api_key' => '',
                            'search_url' => 'https://v3.sg.media-imdb.com/suggestion/__prefix__/__query__.json',
                            'recent_seed_query' => 'popular',
                            'user_agent' => 'Mozilla/5.0',
                        ],
                    ],
                ],
                'anilist_enabled' => '0',
                'google_books_enabled' => '0',
                'google_books_api_key' => '',
                'catalog_per_source' => '3',
            ];
        }

        $aiSearchFill = [
            'response_language' => 'auto',
            'rate_limit_enabled' => '1',
            'rate_limit_window' => '60',
            'rate_limit_max' => '20',
            'max_question_chars' => '800',
            'anilist_enabled' => '0',
            'google_books_enabled' => '0',
            'google_books_api_key' => '',
            'catalog_per_source' => '3',
        ];
        foreach ($aiSearchFill as $k => $v) {
            if (!isset($config['ai_search'][$k])) {
                $config['ai_search'][$k] = $v;
            }
        }
        if (!isset($config['ai_search']['module']) || !is_array($config['ai_search']['module'])) {
            $config['ai_search']['module'] = [];
        }
        $config['ai_search']['module'] = array_merge([
            'vod' => '1',
            'art' => '1',
            'manga' => '1',
            'topic' => '0',
            'actor' => '0',
            'role' => '0',
            'plot' => '0',
            'website' => '0',
        ], $config['ai_search']['module']);
        if (!isset($config['ai_search']['external_sources']) || !is_array($config['ai_search']['external_sources'])) {
            $config['ai_search']['external_sources'] = [];
        }
        $config['ai_search']['external_sources'] = array_merge([
            'enabled' => '0',
            'use_live' => '1',
            'use_cache' => '1',
            'cache_ttl' => '21600',
            'merge_limit' => '4',
            'sync_interval' => '21600',
            'sources' => [],
        ], $config['ai_search']['external_sources']);
        if (!isset($config['ai_search']['external_sources']['sources']['tmdb']) || !is_array($config['ai_search']['external_sources']['sources']['tmdb'])) {
            $config['ai_search']['external_sources']['sources']['tmdb'] = [];
        }
        $config['ai_search']['external_sources']['sources']['tmdb'] = array_merge([
            'enabled' => '0',
            'api_key' => '',
            'base_url' => 'https://api.themoviedb.org/3',
            'image_base_url' => 'https://image.tmdb.org/t/p/w500',
            'language' => 'zh-CN',
            'region' => 'CN',
        ], $config['ai_search']['external_sources']['sources']['tmdb']);
        if (!isset($config['ai_search']['external_sources']['sources']['douban']) || !is_array($config['ai_search']['external_sources']['sources']['douban'])) {
            $config['ai_search']['external_sources']['sources']['douban'] = [];
        }
        $config['ai_search']['external_sources']['sources']['douban'] = array_merge([
            'enabled' => '0',
            'api_key' => '',
            'search_url' => 'https://movie.douban.com/j/subject_suggest?q=__query__',
            'recent_url' => 'https://movie.douban.com/j/search_subjects?type=movie&tag=%E7%83%AD%E9%97%A8&page_limit=__limit__&page_start=0',
            'referer' => 'https://movie.douban.com/',
            'user_agent' => 'Mozilla/5.0',
        ], $config['ai_search']['external_sources']['sources']['douban']);
        if (!isset($config['ai_search']['external_sources']['sources']['imdb']) || !is_array($config['ai_search']['external_sources']['sources']['imdb'])) {
            $config['ai_search']['external_sources']['sources']['imdb'] = [];
        }
        $config['ai_search']['external_sources']['sources']['imdb'] = array_merge([
            'enabled' => '0',
            'api_key' => '',
            'search_url' => 'https://v3.sg.media-imdb.com/suggestion/__prefix__/__query__.json',
            'recent_seed_query' => 'popular',
            'user_agent' => 'Mozilla/5.0',
        ], $config['ai_search']['external_sources']['sources']['imdb']);

        $apiKey = isset($config['ai_search']['api_key']) ? trim((string)$config['ai_search']['api_key']) : '';
        $this->assign('ai_search_key_saved', $apiKey !== '' ? 1 : 0);
        $this->assign('ai_search_key_tail', $apiKey !== '' ? substr($apiKey, -6) : '');
        $tmdbApiKey = trim((string)$config['ai_search']['external_sources']['sources']['tmdb']['api_key']);
        $this->assign('tmdb_key_saved', $tmdbApiKey !== '' ? 1 : 0);
        $this->assign('tmdb_key_tail', $tmdbApiKey !== '' ? substr($tmdbApiKey, -6) : '');
        $doubanApiKey = trim((string)$config['ai_search']['external_sources']['sources']['douban']['api_key']);
        $this->assign('douban_key_saved', $doubanApiKey !== '' ? 1 : 0);
        $this->assign('douban_key_tail', $doubanApiKey !== '' ? substr($doubanApiKey, -6) : '');
        $imdbApiKey = trim((string)$config['ai_search']['external_sources']['sources']['imdb']['api_key']);
        $this->assign('imdb_key_saved', $imdbApiKey !== '' ? 1 : 0);
        $this->assign('imdb_key_tail', $imdbApiKey !== '' ? substr($imdbApiKey, -6) : '');
        $gbKey = isset($config['ai_search']['google_books_api_key']) ? trim((string)$config['ai_search']['google_books_api_key']) : '';
        $this->assign('google_books_key_saved', $gbKey !== '' ? 1 : 0);
        $this->assign('google_books_key_tail', $gbKey !== '' ? substr($gbKey, -6) : '');

        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/configaisearch/title'));
        return $this->fetch('admin@system/configaisearch');
    }

    public function configassistant()
    {
        if (Request()->isPost()) {
            $post = input('post.', '', 'htmlentities');
            $validate = \think\Loader::validate('Token');
            if (!$validate->check($post)) {
                $err = $validate->getError();
                $msg = is_scalar($err) ? (string)$err : lang('param_err');
                return $this->ajaxErrorWithFreshToken($msg);
            }
            unset($post['__token__']);

            $config_old = config('maccms');
            $as = isset($post['admin_assistant']) && is_array($post['admin_assistant']) ? $post['admin_assistant'] : [];
            $sanitize = function ($v) {
                return trim(strip_tags((string)$v));
            };
            $assistantRow = [
                'enabled' => isset($as['enabled']) && (string)$as['enabled'] === '1' ? '1' : '0',
                'access_scope' => (function () use ($as) {
                    $scope = isset($as['access_scope']) ? strtolower(trim((string)$as['access_scope'])) : 'all';
                    return in_array($scope, ['all', 'super'], true) ? $scope : 'all';
                })(),
                'use_ai_search_credentials' => isset($as['use_ai_search_credentials']) && (string)$as['use_ai_search_credentials'] === '1' ? '1' : '0',
                'provider' => $sanitize(isset($as['provider']) ? $as['provider'] : 'openai'),
                'model' => $sanitize(isset($as['model']) ? $as['model'] : ''),
                'api_base' => $sanitize(isset($as['api_base']) ? $as['api_base'] : ''),
                'timeout' => (string)max(8, intval(isset($as['timeout']) ? $as['timeout'] : 45)),
                'max_tokens' => (string)max(256, min(4096, intval(isset($as['max_tokens']) ? $as['max_tokens'] : 1200))),
                'include_env_snapshot' => isset($as['include_env_snapshot']) && (string)$as['include_env_snapshot'] === '1' ? '1' : '0',
                'rate_per_minute' => (string)max(1, min(120, intval(isset($as['rate_per_minute']) ? $as['rate_per_minute'] : 20))),
                'retrieve_chunks' => (string)max(1, min(12, intval(isset($as['retrieve_chunks']) ? $as['retrieve_chunks'] : 8))),
            ];
            $newAsKey = isset($as['api_key']) ? trim((string)$as['api_key']) : '';
            if ($newAsKey !== '') {
                $assistantRow['api_key'] = $newAsKey;
            } else {
                $cfgFile = APP_PATH . 'extra/maccms.php';
                $latest = is_file($cfgFile) ? include $cfgFile : [];
                if (isset($latest['admin_assistant']['api_key']) && $latest['admin_assistant']['api_key'] !== '') {
                    $assistantRow['api_key'] = (string)$latest['admin_assistant']['api_key'];
                } else {
                    $assistantRow['api_key'] = isset($config_old['admin_assistant']['api_key']) ? $config_old['admin_assistant']['api_key'] : '';
                }
            }

            $config_new = $config_old;
            $config_new['admin_assistant'] = $assistantRow;
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $config = config('maccms');
        if (!isset($config['admin_assistant']) || !is_array($config['admin_assistant'])) {
            $config['admin_assistant'] = [];
        }
        $config['admin_assistant'] = array_merge([
            'enabled' => '0',
            'access_scope' => 'all',
            'use_ai_search_credentials' => '1',
            'provider' => 'openai',
            'model' => '',
            'api_base' => '',
            'api_key' => '',
            'timeout' => '45',
            'max_tokens' => '1200',
            'include_env_snapshot' => '1',
            'rate_per_minute' => '20',
            'retrieve_chunks' => '8',
        ], $config['admin_assistant']);
        $asKey = isset($config['admin_assistant']['api_key']) ? trim((string)$config['admin_assistant']['api_key']) : '';
        $this->assign('admin_assistant_key_saved', $asKey !== '' ? 1 : 0);
        $this->assign('admin_assistant_key_tail', $asKey !== '' ? substr($asKey, -6) : '');
        $this->assign('config', $config);
        $this->assign('title', lang('admin/system/configassistant/title'));
        return $this->fetch('admin@system/configassistant');
    }

    public function aisearchsync()
    {
        $post = input('post.', '', 'htmlentities');
        $token = isset($post['__token__']) ? (string)$post['__token__'] : '';
        $sessionToken = (string)session('__token__');
        if ($token === '' || $sessionToken === '' || !$this->safeHashEquals($sessionToken, $token)) {
            $fresh = \think\Request::instance()->token('__token__');
            return json(['code' => 1001, 'msg' => lang('token_err'), 'data' => ['__token__' => $fresh]]);
        }
        $provider = isset($post['provider']) ? trim((string)$post['provider']) : '';
        $config = config('maccms');
        $extCfg = isset($config['ai_search']['external_sources']) && is_array($config['ai_search']['external_sources'])
            ? $config['ai_search']['external_sources']
            : [];
        if ((string)(isset($extCfg['enabled']) ? $extCfg['enabled'] : '0') !== '1') {
            return json(['code' => 1002, 'msg' => lang('admin/system/configaisearch/ext_sync_disabled')]);
        }
        $runner = new ExternalSyncRunner();
        $result = $runner->runDueJobs($extCfg, $provider);
        return json(['code' => 1, 'msg' => lang('admin/system/configaisearch/ext_sync_ok'), 'data' => $result]);
    }

    private function safeHashEquals($knownString, $userString)
    {
        if (function_exists('hash_equals')) {
            return hash_equals((string)$knownString, (string)$userString);
        }
        $a = (string)$knownString;
        $b = (string)$userString;
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $res = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $res |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $res === 0;
    }

    public function configlang(){
        $param = input();
        $config = config('maccms');
        if (!isset($config['app'])) {
            $config['app'] = [];
        }
        $config['app']['lang'] = $param['lang'];
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        if ($res === false) {
            return $this->ajaxErrorWithFreshToken(lang('save_err'));
        }
        return json(['code' => 1, 'msg' => 'ok']);
    }

    public function configVersion(){
        $param = input();
        $config = config('maccms');
        if (!isset($config['site'])) {
            $config['site'] = [];
        }
        $config['site']['new_version'] = $param['version'];
        if (!is_writable(APP_PATH . 'extra/maccms.php')) {
            return $this->error(APP_PATH . 'extra/maccms.php' . lang('install/write_read_err'));
        }
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config);
        if ($res === false) {
            return $this->ajaxErrorWithFreshToken(lang('save_err'));
        }
        return json(['code' => 1, 'msg' => 'ok']);
    }

    /**
     * AJAX form error with a new __token__ (ThinkPHP deletes the session token when validation runs).
     */
    private function ajaxErrorWithFreshToken($msg)
    {
        $t = \think\Request::instance()->token('__token__');
        return $this->error($msg, null, ['__token__' => $t]);
    }

}
