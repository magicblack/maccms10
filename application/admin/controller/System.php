<?php
namespace app\admin\controller;
use think\Db;
use think\Config;
use think\Cache;
use think\Exception;

class System extends Base
{

    public function test_email()
    {
        $post = input();
        $conf = [
            'host' => $post['host'],
            'username' => $post['username'],
            'password' => $post['password'],
            'port' => $post['port'],
            'nick' => $post['nick'],
            'test' => $post['test'],
        ];
        $res = mac_send_mail($conf['test'], '苹果CMS发送邮件测试', '当您看到这封邮件说明邮件配置正确了！感谢使用苹果CMS相关产品！', $conf);
        if ($res==true) {
            return json(['code' => 1, 'msg' => '测试成功']);
        }
        return json(['code' => 1001, 'msg' => '测试失败：'.$res]);
    }

    public function test_cache()
    {
        $param = input();

        if (!isset($param['type']) || empty($param['host']) || empty($param['port'])) {
            return $this->error('参数错误!');
        }

        $options = [
            'type' => $param['type'] == 1 ? 'memcache' : 'redis',
            'port' => $param['port'],
            'username' => $param['username'],
            'password' => $param['password']
        ];

        Cache::connect($options);
        Cache::set('test', 'test');

        return json(['code' => 1, 'msg' => '测试成功']);
    }

    public function config()
    {
        if (Request()->isPost()) {
            $config = input();

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
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }


        $templates = glob('./template' . '/*', GLOB_ONLYDIR);
        foreach ($templates as $k => &$v) {
            $v = str_replace('./template/', '', $v);
        }
        $this->assign('templates', $templates);

        $usergroup = Db::name('group')->select();
        $this->assign('usergroup', $usergroup);

        $config = config('maccms');
        $this->assign('config', $config);
        $this->assign('title', '网站参数配置');
        return $this->fetch('admin@system/config');
    }



    public function configurl()
    {
        if (Request()->isPost()) {
            $config = input();
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
                    if (strpos($a, ':id') !== false) {
                        //$rule['id'] = '\w+';
                    }
                    $route[trim($a[0])] = [trim($a[1]), [], $rule];
                }
            }

            $res = mac_arr2file(APP_PATH . 'route.php', $route);
            if ($res === false) {
                return $this->error('保存路由配置失败，请重试!');
            }

            //写扩展配置
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存配置文件失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', 'url参数配置');
        return $this->fetch('admin@system/configurl');
    }

    public function configuser()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['user'] = $config['user'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '会员参数配置');
        return $this->fetch('admin@system/configuser');
    }

    public function configupload()
    {
        if (Request()->isPost()){
            $config = input();
            $config_new['upload'] = $config['upload'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));

        $path = './application/common/extend/upload';
        $file_list = glob($path . '/*.php',GLOB_NOSORT );
        $ext_list = [];
        $ext_html = '';
        foreach($file_list as $k=>$v) {
            $cl = str_replace([$path . '/', '.php'], '', $v);
            $cp = 'app\\common\\extend\\upload\\' . $cl;

            if (class_exists($cp)) {
                $c = new $cp;
                $ext_list[$cl] = $c->name;

                if(file_exists( './application/admin/view/extend/upload/'.strtolower($cl) .'.html')) {
                    $ext_html .= $this->fetch('admin@extend/upload/' . strtolower($cl));
                }
            }
        }
        $this->assign('ext_list',$ext_list);
        $this->assign('ext_html',$ext_html);

        $this->assign('title', '附件参数配置');
        return $this->fetch('admin@system/configupload');
    }

    public function configcomment()
    {
        if (Request()->isPost()) {
            $config = input();

            $config_new['gbook'] = $config['gbook'];
            $config_new['comment'] = $config['comment'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '评论留言配置');
        return $this->fetch('admin@system/configcomment');
    }


    public function configweixin()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['weixin'] = $config['weixin'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '微信对接配置');
        return $this->fetch('admin@system/configweixin');
    }

    public function configpay()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['pay'] = $config['pay'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('http_type',$GLOBALS['http_type']);
        $this->assign('config', config('maccms'));

        $path = './application/common/extend/pay';
        $file_list = glob($path . '/*.php',GLOB_NOSORT );
        $ext_list = [];
        $ext_html = '';
        foreach($file_list as $k=>$v) {
            $cl = str_replace([$path . '/', '.php'], '', $v);
            $cp = 'app\\common\\extend\\pay\\' . $cl;

            if (class_exists($cp)) {
                $c = new $cp;
                $ext_list[$cl] = $c->name;

                if(file_exists( './application/admin/view/extend/pay/'.strtolower($cl) .'.html')) {
                    $ext_html .= $this->fetch('admin@extend/pay/' . strtolower($cl));
                }
            }
        }
        $this->assign('ext_list',$ext_list);
        $this->assign('ext_html',$ext_html);


        $this->assign('title', '在线支付配置');
        return $this->fetch('admin@system/configpay');
    }

    public function configconnect()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['connect'] = $config['connect'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '整合登录配置');
        return $this->fetch('admin@system/configconnect');
    }

    public function configemail()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['email'] = $config['email'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '邮件发送配置');
        return $this->fetch('admin@system/configemail');
    }

    public function configsms()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['sms'] = $config['sms'];

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $path = './application/common/extend/sms';
        $file_list = glob($path . '/*.php',GLOB_NOSORT );
        $ext_list = [];
        foreach($file_list as $k=>$v) {
            $cl = str_replace([$path . '/', '.php'], '', $v);
            $cp = 'app\\common\\extend\\sms\\' . $cl;

            if (class_exists($cp)) {
                $c = new $cp;
                $ext_list[$cl] = $c->name;
            }
        }
        $this->assign('ext_list',$ext_list);

        $this->assign('config', config('maccms'));
        $this->assign('title', '短信发送配置');
        return $this->fetch('admin@system/configsms');
    }

    public function configapi()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['api'] = $config['api'];

            $config_new['api']['vod']['auth'] = mac_replace_text($config_new['api']['vod']['auth'], 2);
            $config_new['api']['art']['auth'] = mac_replace_text($config_new['api']['art']['auth'], 2);
            $config_new['api']['actor']['auth'] = mac_replace_text($config_new['api']['actor']['auth'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '采集接口API配置');
        return $this->fetch('admin@system/configapi');
    }


    public function configinterface()
    {
        if (Request()->isPost()) {
            $config = input();

            if($config['interface']['status']==1 && strlen($config['interface']['pass']) < 16){
                return $this->error('保存失败，安全起见入库密码必须大于等于16位!');
            }

            $config_new['interface'] = $config['interface'];
            $config_new['interface']['vodtype'] = mac_replace_text($config_new['interface']['vodtype'], 2);
            $config_new['interface']['arttype'] = mac_replace_text($config_new['interface']['arttype'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }

            //保存缓存
            mac_interface_type();

            return $this->success('保存成功!');
        }

        $this->assign('config', config('maccms'));
        $this->assign('title', '站外入库配置');
        return $this->fetch('admin@system/configinterface');
    }

    public function configcollect()
    {
        if (Request()->isPost()) {
            $config = input();
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

            $config_new['collect']['vod']['inrule'] = ',' . join(',', $config_new['collect']['vod']['inrule']);
            $config_new['collect']['vod']['uprule'] = ',' . join(',', $config_new['collect']['vod']['uprule']);
            $config_new['collect']['art']['inrule'] = ',' . join(',', $config_new['collect']['art']['inrule']);
            $config_new['collect']['art']['uprule'] = ',' . join(',', $config_new['collect']['art']['uprule']);
            $config_new['collect']['actor']['inrule'] = ',' . join(',', $config_new['collect']['actor']['inrule']);
            $config_new['collect']['actor']['uprule'] = ',' . join(',', $config_new['collect']['actor']['uprule']);

            $config_new['collect']['vod']['thesaurus'] = mac_replace_text($config_new['collect']['vod']['thesaurus'], 2);
            $config_new['collect']['vod']['words'] = mac_replace_text($config_new['collect']['vod']['words'], 2);
            $config_new['collect']['art']['thesaurus'] = mac_replace_text($config_new['collect']['art']['thesaurus'], 2);
            $config_new['collect']['art']['words'] = mac_replace_text($config_new['collect']['art']['words'], 2);
            $config_new['collect']['actor']['thesaurus'] = mac_replace_text($config_new['collect']['actor']['thesaurus'], 2);
            $config_new['collect']['actor']['words'] = mac_replace_text($config_new['collect']['actor']['words'], 2);

            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }


        $this->assign('config', config('maccms'));
        $this->assign('title', '采集参数配置');
        return $this->fetch('admin@system/configcollect');
    }


    public function configplay()
    {
        if (Request()->isPost()) {
            $config = input();
            $config_new['play'] = $config['play'];
            $config_old = config('maccms');
            $config_new = array_merge($config_old, $config_new);

            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('保存失败，请重试!');
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
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功！');
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
        $this->assign('title', '播放器参数配置');
        return $this->fetch('admin@system/configplay');
    }




}
