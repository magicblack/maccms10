<?php
namespace app\common\model;
use think\Db;
use think\Config;

class Addon extends Base {

    public function onlineData($page=1)
    {
        $html = mac_curl_get( base64_decode('6aKE55WZ5Yqf6IO9').'store/?page=' . $page);
        $json = json_decode($html, true);
        if (!$json) {
            return ['code' => 1001, 'msg' => lang('obtain_err')];
        }
        return $json;
    }

    public function localData()
    {
        $results = glob(ADDON_PATH.'*');

        $list = [];
        foreach ($results as $addonDir) {
            if ($addonDir === '.' or $addonDir === '..')
                continue;

            if (!is_dir($addonDir))
                continue;

            $info_file = $addonDir .DS. 'info.ini';
            if (!is_file($info_file))
                continue;
            $name = str_replace(ADDON_PATH,'',$addonDir);
            $info = Config::parse($info_file, '', "addon-info-{$name}");
            $info['url'] = mac_url($name);
            $info['install'] = 1;
            $list[$name] = $info;
        }
        return ['code'=>1,'list'=>$list];
    }



}