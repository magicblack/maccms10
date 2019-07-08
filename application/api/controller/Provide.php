<?php
namespace app\api\controller;
use think\Controller;
use think\Cache;

class Provide extends Base
{
    var $_param;

    public function __construct()
    {
        parent::__construct();
        $this->_param = input();
    }

    public function index()
    {

    }

    public function vod()
    {
        if($GLOBALS['config']['api']['vod']['status'] != 1){
            echo 'closed';
            exit;
        }

        if($GLOBALS['config']['api']['vod']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo '域名未授权！';
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['vod']['auth'];
                $auths = array();
                if(!empty($auth)){
                    $auths = explode('#',$auth);
                    foreach($auths as $k=>$v){
                        $auths[$k] = gethostbyname(trim($v));
                    }
                }
                if($h != 'localhost' && $h != '127.0.0.1') {
                    if(!in_array($h, $auths)){
                        echo '域名未授权！';
                        exit;
                    }
                }
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['vod']['cachetime']);
        $cach_name = 'api_vod_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['vod_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($GLOBALS['config']['api']['vod']['typefilter'])) {
                $where['type_id'] = ['in', $GLOBALS['config']['api']['vod']['typefilter']];
            }

            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['vod']['typefilter']) || strpos($GLOBALS['config']['api']['vod']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }
            if (!empty($this->_param['h'])) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d H:i:s', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['vod_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['vod_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }

            if (empty($GLOBALS['config']['api']['vod']['from']) && !empty($this->_param['from'])) {
                $GLOBALS['config']['api']['vod']['from'] = $this->_param['from'];
            }
            if (!empty($GLOBALS['config']['api']['vod']['from'])) {
                $where['vod_play_from'] = ['like', '%' . $GLOBALS['config']['api']['vod']['from'] . '%'];
            }

            if (!empty($GLOBALS['config']['api']['vod']['datafilter'])) {
                $where['_string'] .= ' ' . $GLOBALS['config']['api']['vod']['datafilter'];
            }
            if (empty($this->_param['pg'])) {
                $this->_param['pg'] = 1;
            }

            $order = 'vod_time desc';
            $field = 'vod_id,vod_name,type_id,"" as type_name,vod_en,vod_time,vod_remarks,vod_play_from,vod_time';

            if ($this->_param['ac'] == 'videolist' || $this->_param['ac'] == 'detail') {
                $field = '*';
            }
            $res = model('vod')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['vod']['pagesize'], 0, $field, 0);


            if ($this->_param['at'] == 'xml') {
                $html = $this->vod_xml($res);
            } else {
                $html = json_encode($this->vod_json($res));
            }

            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }

    public function vod_url_deal($urls,$froms,$from,$flag)
    {
        $res_xml = '';
        $res_json = [];
        $arr1 = explode("$$$",$urls); $arr1count = count($arr1);
        $arr2 = explode("$$$",$froms); $arr2count = count($arr2);
        for ($i=0;$i<$arr2count;$i++){
            if ($arr1count >= $i){
                if($from!=''){
                    if($arr2[$i]==$from){
                        $res_xml .=  '<dd flag="'. $arr2[$i] .'"><![CDATA[' . $arr1[$i]. ']]></dd>';
                        $res_json[$arr2[$i]] = $arr1[$i];
                    }
                }
                else{
                    $res_xml .=  '<dd flag="'. $arr2[$i] .'"><![CDATA[' . $arr1[$i]. ']]></dd>';
                    $res_json[$arr2[$i]] = $arr1[$i];
                }
            }
        }
        $res = str_replace(array(chr(10),chr(13)),array('','#'),$res_xml);
        return $flag=='xml' ? $res_xml : $res_json;
    }


    public function vod_json($res)
    {
        $type_list = model('Type')->getCache('type_list');
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $v['type_name'] = $type_info['type_name'];
            $v['vod_time'] = date('Y-m-d H:i:s',$v['vod_time']);

            if(substr($v["vod_pic"],0,4)=="mac:"){
                $v["vod_pic"] = str_replace('mac:','http:',$v["vod_pic"]);
            }
            elseif(!empty($v["vod_pic"]) && substr($v["vod_pic"],0,4)!="http" && substr($v["vod_pic"],0,2)!="//"){
                $v["vod_pic"] = $GLOBALS['config']['api']['vod']['imgurl'] . $v["vod_pic"];
            }

            if($this->_param['ac']=='videolist' || $this->_param['ac']=='detail'){
                if ($GLOBALS['config']['api']['vod']['from'] != '') {
                    $arr_from = explode('$$$',$v['vod_play_from']);
                    $arr_url = explode('$$$',$v['vod_play_url']);
                    $arr_server = explode('$$$',$v['vod_play_server']);
                    $arr_note = explode('$$$',$v['vod_play_note']);

                    $key = array_search($GLOBALS['config']['api']['vod']['from'],$arr_from);
                    $res['list'][$k]['vod_play_from'] = $GLOBALS['config']['api']['vod']['from'];
                    $res['list'][$k]['vod_play_url'] = $arr_url[$key];
                    $res['list'][$k]['vod_play_server'] = $arr_server[$key];
                    $res['list'][$k]['vod_play_note'] = $arr_note[$key];

                }

            }
            else {
                if ($GLOBALS['config']['api']['vod']['from'] != '') {
                    $res['list'][$k]['vod_play_from'] = $GLOBALS['config']['api']['vod']['from'];
                } else {
                    $res['list'][$k]['vod_play_from'] = str_replace('$$$', ',', $v['vod_play_from']);
                }
            }
        }


        if($this->_param['ac']!='videolist' && $this->_param['ac']!='detail') {
            $class = [];
            $typefilter  = explode(',',$GLOBALS['config']['api']['vod']['typefilter']);

            foreach ($type_list as $k=>&$v) {

                if (!empty($GLOBALS['config']['api']['vod']['typefilter'])){
                    if(in_array($v['type_id'],$typefilter)) {
                        $class[] = ['type_id' => $v['type_id'], 'type_name' => $v['type_name']];
                    }
                }
                else {
                    $class[] = ['type_id' => $v['type_id'], 'type_name' => $v['type_name']];
                }
            }
            $res['class'] = $class;
        }
        return $res;
    }



    public function vod_xml($res)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="5.1">';
        $type_list = model('Type')->getCache('type_list');

        //视频列表开始
        $xml .= '<list page="'.$res['page'].'" pagecount="'.$res['pagecount'].'" pagesize="'.$res['limit'].'" recordcount="'.$res['total'].'">';
        foreach($res['list'] as $k=>&$v){
            $type_info = $type_list[$v['type_id']];
            $xml .= '<video>';
            $xml .= '<last>'.date('Y-m-d H:i:s',$v['vod_time']).'</last>';
            $xml .= '<id>'.$v['vod_id'].'</id>';
            $xml .= '<tid>'.$v['type_id'].'</tid>';
            $xml .= '<name><![CDATA['.$v['vod_name'].']]></name>';
            $xml .= '<type>'.$type_info['type_name'].'</type>';
            if(substr($v["vod_pic"],0,4)=="mac:"){
                $v["vod_pic"] = str_replace('mac:','http:',$v["vod_pic"]);
            }
            elseif(!empty($v["vod_pic"]) && substr($v["vod_pic"],0,4)!="http"  && substr($v["vod_pic"],0,2)!="//"){
                $v["vod_pic"] = $GLOBALS['config']['api']['vod']['imgurl'] . $v["vod_pic"];
            }

            if($this->_param['ac']=='videolist' || $this->_param['ac']=='detail'){
                $tempurl = $this->vod_url_deal($v["vod_play_url"],$v["vod_play_from"],$GLOBALS['config']['api']['vod']['from'],'xml');

                $xml .= '<pic>'.$v["vod_pic"].'</pic>';
                $xml .= '<lang>'.$v['vod_lang'].'</lang>';
                $xml .= '<area>'.$v['vod_area'].'</area>';
                $xml .= '<year>'.$v['vod_year'].'</year>';
                $xml .= '<state>'.$v['vod_serial'].'</state>';
                $xml .= '<note><![CDATA['.$v['vod_remarks'].']]></note>';
                $xml .= '<actor><![CDATA['.$v['vod_actor'].']]></actor>';
                $xml .= '<director><![CDATA['.$v['vod_director'].']]></director>';
                $xml .= '<dl>'.$tempurl.'</dl>';
                $xml .= '<des><![CDATA['.$v['vod_content'].']]></des>';
            }
            else {
                if ($GLOBALS['config']['api']['vod']['from'] != ''){
                    $xml .= '<dt>' . $GLOBALS['config']['api']['vod']['from'] . '</dt>';
                }
                else{
                    $xml .= '<dt>' . str_replace('$$$', ',', $v['vod_play_from']) . '</dt>';
                }
                $xml .= '<note><![CDATA[' . $v['vod_remarks'] . ']]></note>';
            }
            $xml .= '</video>';
        }
        $xml .= '</list>';

        //视频列表结束

        if($this->_param['ac'] != 'videolist' && $this->_param['ac']!='detail') {
            //分类列表开始
            $xml .= "<class>";
            $typefilter  = explode(',',$GLOBALS['config']['api']['vod']['typefilter']);
            foreach ($type_list as $k=>&$v) {
                if($v['type_mid']==1) {
                    if (!empty($GLOBALS['config']['api']['vod']['typefilter'])){
                        if(in_array($v['type_id'],$typefilter)) {
                            $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                        }
                    }
                    else {
                        $xml .= "<ty id=\"" . $v["type_id"] . "\">" . $v["type_name"] . "</ty>";
                    }
                }
            }
            unset($rs);
            $xml .= "</class>";
            //分类列表结束

        }
        $xml .= "</rss>";
        return $xml;
    }

    public function art()
    {
        if($GLOBALS['config']['api']['art']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['art']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo '域名未授权！';
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['art']['auth'];
                $auths = array();
                if(!empty($auth)){
                    $auths = explode('#',$auth);
                    foreach($auths as $k=>$v){
                        $auths[$k] = gethostbyname(trim($v));
                    }
                }
                if($h != 'localhost' && $h != '127.0.0.1') {
                    if(!in_array($h, $auths)){
                        echo '域名未授权！';
                        exit;
                    }
                }
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['art']['cachetime']);
        $cach_name = 'api_art_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['art_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                if (empty($GLOBALS['config']['api']['art']['typefilter']) || strpos($GLOBALS['config']['api']['art']['typefilter'], $this->_param['t']) !== false) {
                    $where['type_id'] = $this->_param['t'];
                }
            }

            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['art_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['art_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['art']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['art']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'art_time desc';
            $field = 'art_id,art_name,type_id,"" as type_name,art_en,art_time,art_author,art_from,art_remarks,art_pic,art_time';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('art')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['art']['pagesize'], 0, $field, 0);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            $type_list = model('Type')->getCache('type_list');
            foreach ($res['list'] as $k => &$v) {
                $type_info = $type_list[$v['type_id']];
                $v['type_name'] = $type_info['type_name'];
                $v['art_time'] = date('Y-m-d H:i:s', $v['art_time']);

                if (substr($v["art_pic"], 0, 4) == "mac:") {
                    $v["art_pic"] = str_replace('mac:', 'http:', $v["art_pic"]);
                } elseif (!empty($v["art_pic"]) && substr($v["art_pic"], 0, 4) != "http" && substr($v["art_pic"], 0, 2) != "//") {
                    $v["art_pic"] = $GLOBALS['config']['api']['art']['imgurl'] . $v["art_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $typefilter = explode(',', $GLOBALS['config']['api']['art']['typefilter']);

                foreach ($type_list as $k => &$v) {
                    if ($v['type_mid'] == 2) {

                        if (!empty($GLOBALS['config']['api']['art']['typefilter'])) {
                            if (in_array($v['type_id'], $typefilter)) {
                                $class[] = ['type_id' => $v['type_id'], 'type_name' => $v['type_name']];
                            }
                        } else {
                            $class[] = ['type_id' => $v['type_id'], 'type_name' => $v['type_name']];
                        }
                    }
                }
                $res['class'] = $class;
            }
            $html = json_encode($res);

            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }


    public function actor()
    {
        if($GLOBALS['config']['api']['actor']['status'] != 1){
            echo 'closed';die;
        }

        if($GLOBALS['config']['api']['actor']['charge'] == 1) {
            $h = $_SERVER['REMOTE_ADDR'];
            if (!$h) {
                echo '域名未授权！';
                exit;
            }
            else {
                $auth = $GLOBALS['config']['api']['actor']['auth'];
                $auths = array();
                if(!empty($auth)){
                    $auths = explode('#',$auth);
                    foreach($auths as $k=>$v){
                        $auths[$k] = gethostbyname(trim($v));
                    }
                }
                if($h != 'localhost' && $h != '127.0.0.1') {
                    if(!in_array($h, $auths)){
                        echo '域名未授权！';
                        exit;
                    }
                }
            }
        }

        $cache_time = intval($GLOBALS['config']['api']['actor']['cachetime']);
        $cach_name = 'api_actor_'.md5(http_build_query($this->_param));
        $html = Cache::get($cach_name);
        if(empty($html) || $cache_time==0) {
            $where = [];
            if (!empty($this->_param['ids'])) {
                $where['actor_id'] = ['in', $this->_param['ids']];
            }
            if (!empty($this->_param['t'])) {
                $where['actor_area'] = $this->_param['t'];
            }
            if (!empty(intval($this->_param['h']))) {
                $todaydate = date('Y-m-d', strtotime('+1 days'));
                $tommdate = date('Y-m-d', strtotime('-' . $this->_param['h'] . ' hours'));

                $todayunix = strtotime($todaydate);
                $tommunix = strtotime($tommdate);

                $where['actor_time'] = [['gt', $tommunix], ['lt', $todayunix]];
            }
            if (!empty($this->_param['wd'])) {
                $where['actor_name'] = ['like', '%' . $this->_param['wd'] . '%'];
            }
            if (!empty($GLOBALS['config']['api']['actor']['datafilter'])) {
                $where['_string'] = $GLOBALS['config']['api']['actor']['datafilter'];
            }
            if (empty(intval($this->_param['pg']))) {
                $this->_param['pg'] = 1;
            }

            $order = 'actor_time desc';
            $field = 'actor_id,actor_name,actor_en,actor_area,actor_time,actor_alias,actor_sex,actor_pic';

            if ($this->_param['ac'] == 'detail') {
                $field = '*';
            }

            $res = model('actor')->listData($where, $order, $this->_param['pg'], $GLOBALS['config']['api']['actor']['pagesize'], 0, $field, 0);

            if ($res['code'] > 1) {
                echo $res['msg'];
                exit;
            }

            $type_list = model('Type')->getCache('type_list');
            foreach ($res['list'] as $k => &$v) {

                $v['actor_time'] = date('Y-m-d H:i:s', $v['actor_time']);

                if (substr($v["actor_pic"], 0, 4) == "mac:") {
                    $v["actor_pic"] = str_replace('mac:', 'http:', $v["actor_pic"]);
                } elseif (!empty($v["actor_pic"]) && substr($v["actor_pic"], 0, 4) != "http" && substr($v["actor_pic"], 0, 2) != "//") {
                    $v["actor_pic"] = $GLOBALS['config']['api']['actor']['imgurl'] . $v["actor_pic"];
                }

                if ($this->_param['ac'] == 'detail') {

                } else {

                }
            }

            if ($this->_param['ac'] != 'detail') {
                $class = [];
                $tmp_list = explode(',', $GLOBALS['config']['app']['actor_extend_area']);

                foreach ($tmp_list as $k => &$v) {
                    $class[] = ['type_id' => $v, 'type_name' => $v];
                }
                $res['class'] = $class;
            }
            $html = json_encode($res);

            if($cache_time>0) {
                Cache::set($cach_name, $html, $cache_time);
            }
        }
        echo $html;
        exit;
    }


}
