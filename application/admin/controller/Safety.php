<?php
namespace app\admin\controller;
use think\Db;

class Safety extends Base
{

    var $_files;
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {

    }

    protected function listDir($dir){
        if(is_dir($dir)){
            if ($dh = opendir($dir)) {
                while (($file= readdir($dh)) !== false){
                    $tmp = str_replace('//','/',mac_convert_encoding($dir.$file, "UTF-8", "GB2312"));
                    if((is_dir($dir."/".$file)) && $file!="." && $file!=".."){
                        $this->listDir($dir."/".$file."/");
                    } else{
                        if($file!="." && $file!=".."){
                            $this->_files[$tmp] = ['md5'=>md5_file($dir.$file)];
                        }
                    }
                }
                closedir($dh);
            }
        }
    }

    public function file()
    {
        $param = input();
        if($param['ck']){
            $ft = $param['ft'];
            if(empty($ft)){
                $ft = ['1','2'];
            }
            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
            $url = base64_decode("aHR0cDovL3VwZGF0ZS5tYWNjbXMubGEv") . "v10/mac_files_".config('version')['code'].'.html';
            $html = mac_curl_get($url);
            $json = json_decode($html,true);
            if(!$json){
                return $this->error(lang('admin/safety/file_msg1'));
            }

            $this->listDir('./');
            if(!is_array($this->_files)){
                return $this->error(lang('admin/safety/file_msg2'));
            }

            foreach($this->_files as $k=>$v){
                $color = '';
                $msg = 'ok';
                if(empty($json[$k]) && in_array('1',$ft)){
                    $color = 'BlueViolet';
                    $msg = lang('admin/safety/file_msg3');
                }
                elseif(!empty($json[$k]) && $v['md5'] != $json[$k]['md5'] && in_array('2',$ft)){
                    $color = 'red';
                    $msg = lang('admin/safety/file_msg4');
                }
                if($color!='') {
                    //$this->_files[$k]['jc'] = $color;
                    mac_echo($k . '---' . "<font color=$color>" . $msg . '</font>');
                }
            }
            exit;
        }
        return $this->fetch('admin@safety/file');
    }

    public function data()
    {
        $param = input();
        if ($param['ck']) {
            $pre = config('database.prefix');
            $schema = Db::query('select * from information_schema.columns where table_schema = ?', [config('database.database')]);
            $col_list = [];
            $sql = '';
            foreach ($schema as $k => $v) {
                $col_list[$v['TABLE_NAME']][$v['COLUMN_NAME']] = $v;
            }
            $tables = ['actor', 'art', 'gbook', 'link', 'topic', 'type', 'vod'];
            $param['tbi'] = intval($param['tbi']);
            if ($param['tbi'] >= count($tables)) {
                mac_echo(lang('admin/safety/data_clear_ok'));
                die;
            }

            $check_arr = ["<script","<iframe","{php}","{:"];
            $rel_val = [
                [
                    "/<script[\s\S]*?<\/(.*)>/is",
                    "/<script[\s\S]*?>/is",
                ],
                [
                    "/<iframe[\s\S]*?<\/(.*)>/is",
                    "/<iframe[\s\S]*?>/is",
                ],
                [
                    "/{php}[\s\S]*?{\/php}/is",
                ],
                [
                    "/{:[\s\S]*?}/is",
                ]
            ];
            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');


            foreach ($col_list as $k1 => $v1) {
                $pre_tb = str_replace($pre, '', $k1);
                $si = array_search($pre_tb, $tables);
                if ($pre_tb !== $tables[$param['tbi']]) {
                    continue;
                }
                mac_echo(lang('admin/safety/data_check_tip1',[$k1]));
                $where = [];
                foreach ($v1 as $k2 => $v2) {
                    if (strpos($v2['DATA_TYPE'], 'int') === false) {
                        $where[$k2] = ['like', mac_like_arr(join(',', $check_arr)), 'OR'];
                    }
                }
                if (!empty($where)) {
                    $field = array_keys($where);
                    $field[] = $tables[$si] . '_id';
                    $list = Db::name($pre_tb)->field($field)->whereOr($where)->fetchSql(false)->select();

                    mac_echo(lang('admin/safety/data_check_tip2',[count($list)]));
                    foreach ($list as $k3 => $v3) {
                        $update = [];
                        $col_id = $tables[$si] . '_id';
                        $col_name = $tables[$si] . '_name';
                        $val_id = $v3[$col_id];;
                        $val_name = strip_tags($v3[$col_name]);
                        $ck = false;
                        $where2 = [];
                        $where2[$col_id] = $val_id;
                        foreach ($v3 as $k4 => $v4) {
                            if ($k4 != $col_id) {
                                $val = $v4;
                                foreach ($check_arr as $kk => $vv) {
                                    foreach($rel_val[$kk] as $k5=>$v5){
                                        $val = preg_replace($v5, "", $val);
                                    }
                                }
                                if ($val !== $v4) {
                                    $update[$k4] = $val;
                                    $ck = true;
                                }
                            }
                        }

                        if ($ck) {
                            $r = Db::name($pre_tb)->where($where2)->update($update);
                            mac_echo($val_id . '、' . $val_name . ' ok');
                        }
                    }
                }
            }

            $param['tbi']++;
            $url = url('safety/data') . '?' . http_build_query($param);
            mac_jump($url, 3);
            exit;
        }
        return $this->fetch('admin@safety/data');
    }
}
