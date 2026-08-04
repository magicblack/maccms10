<?php
namespace app\admin\controller;
use think\Db;
use think\Config;
use think\Cache;

class Domain extends Base
{

    public function index()
    {
        if (Request()->isPost()) {
            $config = input();

            $tmp = $config['domain'];
            $domain=[];



            foreach ($tmp['site_url'] as $k=>$v){

                // 与 import() 一致地逐字段兜底：表单少提交一列（旧缓存页面、插件改过的
                // 表单）时不能整页 Undefined index，缺的按空值存
                $domain[$v] =[
                   'site_url'=>$v,
                    'site_name'=>isset($tmp['site_name'][$k]) ? $tmp['site_name'][$k] : '',
                    'site_keywords'=>isset($tmp['site_keywords'][$k]) ? $tmp['site_keywords'][$k] : '',
                    'site_description'=>isset($tmp['site_description'][$k]) ? $tmp['site_description'][$k] : '',
                    'template_dir'=>isset($tmp['template_dir'][$k]) ? $tmp['template_dir'][$k] : '',
                    'html_dir'=>isset($tmp['html_dir'][$k]) ? $tmp['html_dir'][$k] : '',
                    'ads_dir'=>isset($tmp['ads_dir'][$k]) ? $tmp['ads_dir'][$k] : '',
                    'map_dir'=>isset($tmp['map_dir'][$k]) ? $tmp['map_dir'][$k] : '',
                    'mob_template_dir'=>isset($tmp['mob_template_dir'][$k]) ? $tmp['mob_template_dir'][$k] : '',
                    'site_wapurl'=>isset($tmp['site_wapurl'][$k]) ? mac_domain_host($tmp['site_wapurl'][$k]) : '',
                ];

            }


            $res = mac_arr2file(APP_PATH . 'extra/domain.php', $domain);
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

        $config = config('domain');
        if(!is_array($config)){
            $config = [];
        }
        // 存量条目没有 wap 字段，补空值避免模板取不到下标
        foreach($config as &$one){
            if(!isset($one['mob_template_dir'])){
                $one['mob_template_dir'] = '';
            }
            if(!isset($one['site_wapurl'])){
                $one['site_wapurl'] = '';
            }
        }
        unset($one);
        $this->assign('domain_list', $config);
        $this->assign('title', lang('admin/domain/title'));
        return $this->fetch('admin@domain/index');
    }

    public function del()
    {
        $param = input();
        if(!empty($param['ids'])){
            $list = config('domain');
            unset($list[$param['ids']]);
            $res = mac_arr2file( APP_PATH .'extra/domain.php', $list);
            if($res===false){
                return $this->error(lang('del_err'));
            }
        }
        return $this->success(lang('del_ok'));
    }

    public function export()
    {
        $list = config('domain');
        $html = '';
        foreach($list as $k=>$v){
            $html .= $v['site_url'].'$'.$v['site_name'].'$'.$v['site_keywords'].'$'.$v['site_description'].'$'.$v['template_dir'].'$'.$v['html_dir'].'$'.$v['ads_dir'].'$'.$v['map_dir'].'$'.(isset($v['mob_template_dir']) ? $v['mob_template_dir'] : '').'$'.(isset($v['site_wapurl']) ? $v['site_wapurl'] : '')."\n";
        }

        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=mac_domains.txt");
        echo $html;
    }

    public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = file_get_contents($info->getpathName());
            @unlink($info->getpathName());
            if($data){
                $list = explode(chr(10),$data);

                $domain =[];

                foreach($list as $k=>$v){
                    if(!empty($v)) {
                        // 兼容旧版导出文件（7 / 8 段），缺失的字段按空值补齐
                        $one = explode('$', trim($v));
                        $domain[$one[0]] = [
                            'site_url' => $one[0],
                            'site_name' => isset($one[1]) ? $one[1] : '',
                            'site_keywords' => isset($one[2]) ? $one[2] : '',
                            'site_description' => isset($one[3]) ? $one[3] : '',
                            'template_dir' => isset($one[4]) ? $one[4] : '',
                            'html_dir' => isset($one[5]) ? $one[5] : '',
                            'ads_dir'=> isset($one[6]) ? $one[6] : '',
                            'map_dir'=> isset($one[7]) ? $one[7] : '',
                            'mob_template_dir'=> isset($one[8]) ? $one[8] : '',
                            'site_wapurl'=> isset($one[9]) ? mac_domain_host($one[9]) : '',
                        ];
                    }
                }

                $res = mac_arr2file( APP_PATH .'extra/domain.php', $domain);
                if($res===false){
                    return $this->error(lang('write_err_config'));
                }
            }
            return $this->success(lang('import_ok'));
        }
        else{
            return $this->error($file->getError());
        }
    }
}
