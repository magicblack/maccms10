<?php
namespace app\admin\controller;
use think\Db;

class Images extends Base
{
    public function __construct()
    {
        parent::__construct();
        //header('X-Accel-Buffering: no');
    }

    public function data()
    {

    }

    public function opt()
    {
        $param = input();
        $this->assign('tab',$param['tab']);
        return $this->fetch('admin@images/opt');
    }

    public function del()
    {
        $param = input();
        $fname = $param['ids'];
        if(!empty($fname)){
            foreach($fname as $a){
                $a = str_replace('\\','/',$a);

                if( (substr($a,0,8) != "./upload") || count( explode("./",$a) ) > 2) {

                }
                else{
                    $a = mac_convert_encoding($a,"UTF-8","GB2312");
                    if(file_exists($a)){ @unlink($a); }
                }
            }
        }
        return $this->success(lang('del_ok'));
    }

    public function sync()
    {
        $param = input();

        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? 10 : $param['limit'];
        $flag = "#err". date('Y-m-d',time());

        if($param['tab']=='vod'){
            $tab='vod';
            $col_id ='vod_id';
            $col_name ='vod_name';
            $col_pic= $param['col']==2 ? 'vod_content' : 'vod_pic';
            $col_time='vod_time';

        }
        elseif($param['tab']=='art'){
            $tab='art';
            $col_id ='art_id';
            $col_name ='art_name';
            $col_pic= $param['col']==2 ? 'art_content' :'art_pic';
            $col_time='art_time';
        }
        elseif($param['tab']=='topic'){
            $tab='topic';
            $col_id ='topic_id';
            $col_name ='topic_name';
            $col_pic=$param['col']==2 ? 'topic_content' :'topic_pic';
            $col_time='topic_time';
        }
        elseif($param['tab']=='actor'){
            $tab='actor';
            $col_id ='actor_id';
            $col_name ='actor_name';
            $col_pic=$param['col']==2 ? 'actor_content' :'actor_pic';
            $col_time='actor_time';
        }
        elseif($param['tab']=='role'){
            $tab='role';
            $col_id ='role_id';
            $col_name ='role_name';
            $col_pic=$param['col']==2 ? 'role_content' :'role_pic';
            $col_time='role_time';
        }
        elseif($param['tab']=='website'){
            $tab='website';
            $col_id ='website_id';
            $col_name ='website_name';
            $col_pic=$param['col']==2 ? 'website_content' :'website_pic';
            $col_time='website_time';
        }
        else{
            return $this->error(lang('param_err'));
        }

        $where = ' 1=1 ';
        if ($param['range'] =="2" && $param['date']!=""){
            $pic_fwdate = str_replace('|','-',$param['date']);
            $todayunix1 = strtotime($pic_fwdate);
            $todayunix2 = $todayunix1 +  86400;
            $where .= ' AND ('.$col_time.'>= '. $todayunix1 . ' AND '.$col_time.'<='. $todayunix2 .') ';
        }
        if($param['col'] == 2){
            $where .= ' and '. $col_pic . " like '%<img%src=\"http%' ";
        }
        else {
            if ($param['opt'] == 1) {
                $where .= " AND instr(" . $col_pic . ",'#err')=0 ";
            } elseif ($param['opt'] == 2) {
                $where .= " AND instr(" . $col_pic . ",'" . $flag . "')=0 ";
            } elseif ($param['opt'] == 3) {
                $where .= " AND instr(" . $col_pic . ",'#err')>0 ";
            }
            $where .= " AND instr(" . $col_pic . ",'http')>0  ";
        }

        $total = Db::name($tab)->where($where)->count();
        $page_count = ceil($total / $param['limit']);

        if($total==0){
            mac_echo(lang('admin/images/sync_complete'));
            exit;
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        mac_echo(lang('admin/images/sync_tip',[$total,$param['limit'],$page_count,$param['page']]));

        $list = Db::name($tab)->where($where)->page($page_count-1,$param['limit'])->select();
        $config = config('maccms.upload');

        if ($config['mode'] == '2') {
            $config['mode'] = 'upyun';
        }
        elseif ($config['mode'] == '3'){
            $config['mode'] = 'qiniu';
        }
        elseif ($config['mode'] == '4') {
            $config['mode'] = 'ftp';
        }
        elseif ($config['mode'] == '5') {
            $config['mode'] = 'weibo';
        }


        foreach($list as $k=>$v){

            mac_echo($v[$col_id].'、'.$v[$col_name]);

            if($param['col'] == 2){
                $content = $v[$col_pic];
                $rule = mac_buildregx('<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>',"is");
                preg_match_all($rule,$content,$matches);
                $matchfieldarr=$matches[1];
                $matchfieldstrarr=$matches[0];
                $matchfieldvalue="";
                foreach($matchfieldarr as $f=>$matchfieldstr)
                {
                    $matchfieldvalue=$matchfieldstrarr[$f];
                    $img_old = trim(preg_replace("/[ \r\n\t\f]{1,}/"," ",$matchfieldstr));
                    $img_url = model('Image')->down_load($img_old, $config, $param['tab']);

                    $des = '';
                    if(in_array($config['mode'],['local']) || substr($img_url,0,7)=='upload/'){
                        $img_url = MAC_PATH . $img_url;
                        $link = $img_url;
                        $link = str_replace('//', '/', $link);
                    }
                    else{
                        $link = str_replace('mac:', $config['protocol'].':', $img_url);
                    }
                    if ($img_url == $img_old) {
                        $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=red>'.lang('download_err').'!</font>';
                        $img_url .= $flag;
                        $content = str_replace($img_old,"",$content);
                    } else {
                        $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=green>'.lang('download_ok').'!</font>';
                        $content = str_replace($img_old, $img_url, $content );
                    }
                    mac_echo($des);
                }

                $where = [];
                $where[$col_id] = $v[$col_id];
                $update = [];
                $update[$col_pic] = $content;
                $st = Db::name($tab)->where($where)->update($update);
            }
            else {
                $img_old = $v[$col_pic];
                if (strpos($img_old, "#err")) {
                    $picarr = explode("#err", $img_old);
                    $img_old = $picarr[0];
                }

                $img_url = model('Image')->down_load($img_old, $config, $param['tab']);
                $des = '';
                if(in_array($config['mode'],['local']) || substr($img_url,0,7)=='upload/'){
                    $link = MAC_PATH . $img_url;
                    $link = str_replace('//', '/', $link);
                }
                else{
                    $link = str_replace('mac:', $config['protocol'].':', $img_url);
                }

                if ($img_url == $img_old) {
                    $des = '<a href="' . $img_old . '" target="_blank">' . $img_old . '</a><font color=red>'.lang('download_err').'!</font>';
                    $img_url .= $flag;
                } else {
                    $des = '<a href="' . $link . '" target="_blank">' . $link . '</a><font color=green>'.lang('download_ok').'!</font>';
                }
                mac_echo($des);

                $where = [];
                $where[$col_id] = $v[$col_id];
                $update = [];
                $update[$col_pic] = $img_url;
                $st = Db::name($tab)->where($where)->update($update);
            }
        }

        $url = url('images/sync') .'?'. http_build_query($param);
        mac_jump( $url ,3);
    }


}
