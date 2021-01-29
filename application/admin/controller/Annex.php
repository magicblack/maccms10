<?php
namespace app\admin\controller;

use think\Db;

class Annex extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if (!empty($param['type'])) {
            $where['annex_type'] = ['eq', $param['type']];
        }
        if (!empty($param['mid'])) {
            $where['annex_mid'] = ['eq', $param['mid']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['annex_file'] = ['like','%'.$param['wd'].'%'];
        }

        $order='annex_time desc';
        $res = model('Annex')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', lang('admin/annex/title'));
        return $this->fetch('admin@annex/index');
    }

    public function file()
    {
        $path = input('path');
        $path = str_replace('\\','',$path);
        $path = str_replace('/','',$path);

        if(empty($path)){
            $path = '@upload';
        }

        if(substr($path,0,7) != "@upload") { $path = "@upload"; }
        if(count( explode("..@",$path) ) > 1) {
            $this->error(lang('illegal_request'));
            return;
        }


        $uppath = substr($path,0,strrpos($path,"@"));

        $ischild = 0;
        if ($path !="@upload"){
            $ischild = 1;
        }
        $this->assign('uppath',$uppath);
        $this->assign('ischild',$ischild);


        $num_path = 0;
        $num_file = 0;
        $sum_size = 0;
        $filters = ",,cache,break,artcollect,downdata,playdata,export,vodcollect,";
        $files = [];

        $pp = str_replace('@','/',$path);

        if(is_dir('.'.$pp)){

            $farr = glob('.'.$pp.'/*');
            if($farr){
                foreach($farr as $f){

                    if ( is_dir($f) ){

                        if(strpos($filters,",".$f.",")<=0){
                            $num_path++;
                            $tmp_path = str_replace('./upload/','@upload/',$f);
                            $tmp_path = str_replace('/','@',$tmp_path);

                            $tmp_name = str_replace($path.'@','',$tmp_path);


                            $files[] = ['isfile'=>0,'name'=>$tmp_name,'path'=>$tmp_path];
                        }
                    }
                    elseif(is_file($f)){
                        if (strpos($f,".html") <=0 && strpos($f,".htm") <=0){
                            $num_file++;
                            $fsize = filesize($f);
                            $sum_size += $fsize;
                            $fsize = mac_format_size($fsize);
                            $ftime = filemtime($f);
                            $tmp_path = mac_convert_encoding($f,"UTF-8","GB2312");

                            $tmp_path = str_replace('./upload/','@upload/',$f);
                            $tmp_path = str_replace('/','@',$tmp_path);

                            $tmp_name = str_replace($path.'@',"",$tmp_path);
                            $tmp_path = str_replace('@','/',$tmp_path);

                            $files[] = ['isfile'=>1,'name'=>$tmp_name,'path'=>$tmp_path, 'size'=>$fsize, 'time'=>$ftime];
                        }
                    }

                }
            }
        }
        $this->assign('sum_size',mac_format_size($sum_size));
        $this->assign('num_file',$num_file);
        $this->assign('num_path',$num_path);

        $this->assign('files',$files);

        $this->assign('title',lang('admin/annex/title'));
        return $this->fetch('admin@annex/file');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Annex')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['annex_id'] = ['eq',$id];
        $res = model('Annex')->infoData($where);
        $info = $res['info'];
        $this->assign('info',$info);

        $this->assign('title',lang('admin/annex/title'));
        return $this->fetch('admin@annex/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            if(is_array($ids)){
                foreach($ids as $k=>$v){
                    $ids[$k] = str_replace('./','',$v);
                }
            }
            $where=[];
            $where['annex_id|annex_file'] = ['in',$ids];
            $res = model('Annex')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function check()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        $param = input();
        $num = intval($param['num']);
        $start = intval($param['start']);
        $page_count = intval($param['page_count']);
        $data_count = intval($param['data_count']);
        if($start<1){
            $start=1;
        }
        if($page_count<1){
            $page_count=1;
        }
        $page_size = 500;
        if(empty($data_count)){
            $where=[];
            $data_count = model('Annex')->countData($where);
            $page_count = ceil($data_count / $page_size);

            $param['data_count'] = $data_count;
            $param['page_count'] = $page_count;
            $param['page_size'] = $page_size;
        }

        if($start > $page_count){
            mac_echo(lang('admin/annex/check_complete'));
            exit;
        }

        mac_echo(lang('admin/annex/info_tip',[$param['data_count'],$param['page_count'],$param['page_size'],$start]));
        $limit_str = ($page_size * ($page_count-$start)) .",".$page_size;

        $list = Db::name('Annex')->field('*')->where($where)->limit($limit_str)->fetchSql(false)->orderRaw('annex_time desc')->select();
        foreach ($list as $k3 => $v3) {
            $tmp = $v3['annex_file'];
            if(!file_exists('./'.$tmp)){
                $where=[];
                $where['annex_file'] = ['eq',$tmp];
                $r = Db::name('Annex')->where($where)->delete();
                mac_echo($tmp . '...del');
            }
        }
        $param['start'] = ++$start;
        $url = url('annex/check') .'?'. http_build_query($param);
        mac_jump( $url ,3);
    }

    public function init()
    {
        $param = input();

        if($param['ck']){
            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            $start = intval($param['start']);
            if($start<1){
                $start=1;
            }

            $pre = config('database.prefix');
            $schema = Db::query('select * from information_schema.columns where table_schema = ?', [config('database.database')]);
            $col_list = [];
            foreach ($schema as $k => $v) {
                $col_list[$v['TABLE_NAME']][$v['COLUMN_NAME']] = $v;
            }
            $tables = ['actor', 'art', 'topic', 'type', 'vod', 'website' ,'actor', 'role'];
            $param['tbi'] = intval($param['tbi']);
            if ($param['tbi'] >= count($tables)) {
                mac_echo(lang('admin/annex/check_ok'));
                die;
            }
            $tab = $tables[$param['tbi']];

            $where=[];
            $page_size = 500;
            $data_count = model($tab)->countData($where);
            $page_count = ceil($data_count / $page_size);

            if($start > $page_count){
                mac_echo(lang('admin/annex/check_jump',[$tab]));
                $param['tbi']++;
                $param['start'] = 1;
                $url = url('annex/init') . '?' . http_build_query($param);
                mac_jump($url, 3);
                exit;
            }

            mac_echo(lang('admin/annex/check_tip1',[$tab,$data_count,$page_count,$page_size,$start]));

            foreach ($col_list as $k1 => $v1) {
                $pre_tb = str_replace($pre, '', $k1);
                $si = array_search($pre_tb, $tables);
                if ($pre_tb !== $tab) {
                    continue;
                }
                $limit_str = ($page_size * ($page_count-$start)) .",".$page_size;
                $list = Db::name($pre_tb)->field('*')->limit($limit_str)->fetchSql(false)->select();

                $adds = [];
                foreach ($list as $k3 => $v3) {
                    $col_id = $tables[$si] . '_id';
                    $col_name = $tables[$si] . '_name';
                    $val_id = $v3[$col_id];;
                    $val_name = strip_tags($v3[$col_name]);
                    $ck = false;
                    $where2 = [];
                    $where2[$col_id] = $val_id;
                    $imgs = [];
                    $add = [];
                    $add['id'] = $val_id;
                    $add['name'] = $val_name;
                    $add['col_id'] = $col_id;

                    $col = $tables[$si] . '_pic';
                    $val = $v3[$col];
                    if (substr($val, 0, 6) == 'upload' && file_exists('./' . $val)) {
                        $imgs[] = ['annex_file' => $val, 'annex_time' => time(), 'annex_size' => filesize('./' . $val), 'annex_type' => 'image'];
                        $ck = true;
                    }
                    $col = $tables[$si] . '_pic_thumb';
                    $val = $v3[$col];
                    if (substr($val, 0, 6) == 'upload' && file_exists('./' . $val)) {
                        $imgs[] = ['annex_file' => $val, 'annex_time' => time(), 'annex_size' => filesize('./' . $val), 'annex_type' => 'image'];
                        $ck = true;
                    }
                    $col = $tables[$si] . '_pic_slide';
                    $val = $v3[$col];
                    if (substr($val, 0, 6) == 'upload' && file_exists('./' . $val)) {
                        $imgs[] = ['annex_file' => $val, 'annex_time' => time(), 'annex_size' => filesize('./' . $val), 'annex_type' => 'image'];
                        $ck = true;
                    }

                    $col = $tables[$si] . '_content';
                    $val = $v3[$col];
                    if (!empty($val)) {
                        $rule = mac_buildregx("<img[^>]*src\s*=\s*['" . chr(34) . "]?([\w/\-\:.]*)['" . chr(34) . "]?[^>]*>", "is");
                        preg_match_all($rule, $val, $matches);

                        $matchfieldarr = $matches[1];
                        foreach ($matchfieldarr as $f => $matchfieldstr) {
                            $img_src = trim(preg_replace("/[ \r\n\t\f]{1,}/", " ", $matchfieldstr));
                            if (substr($img_src, 0, 7) == '/upload' && file_exists('.' . $img_src)) {
                                $imgs[] = ['annex_file' => substr($img_src, 1), 'annex_time' => time(), 'annex_size' => filesize('.' . $img_src), 'annex_type' => 'image'];
                                $ck = true;
                            }
                        }
                    }
                    $add['imgs'] = $imgs;
                    $adds[] = $add;
                }
                if (!empty($adds)) {
                    $insert = [];
                    foreach ($adds as $k => $v) {
                        $des = '<font color=red>'.lang('skip').'</font>';
                        if (!empty($v['imgs'])) {
                            foreach($v['imgs'] as $k2 => $v2){
                                $where = [];
                                $where['annex_file'] = $v2['annex_file'];
                                $r = model('Annex')->infoData($where);
                                if ($r['code'] !== 1) {
                                    $insert[] = $v2;
                                    $des = '<font color=green>ok</font>';
                                }
                            }
                        }
                        mac_echo($v['name'] . '...' . $des);
                        model('Annex')->insertAll($insert);
                    }
                }
            }

            $param['start']++;
            $url = url('annex/init') . '?' . http_build_query($param);
            mac_jump($url, 3);
            exit;
        }
        return $this->fetch('admin@annex/init');
    }


}
