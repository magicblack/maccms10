<?php
namespace app\admin\controller;
use think\Db;
use think\View;

class Make extends Base
{
    var $_param;

    public function __construct()
    {
        parent::__construct();
        $this->_param = input();
        $GLOBALS['ismake'] = '1';
    }

    protected function buildHtml($htmlfile='',$htmlpath='',$templateFile='') {
        if(empty($htmlfile) || empty($htmlpath) || empty($templateFile)){
            return false;
        }
        $content    =   $this->label_fetch($templateFile);
        if(substr($htmlfile,strlen($htmlfile)-1,1)=='/'){
            $htmlfile .= 'index';
        }
        if(strpos($htmlfile,'.') ===false){
            $htmlfile .= '.'. $GLOBALS['config']['path']['suffix'];
        }
        if(strpos($htmlfile,'?')!==false){
            $htmlfile = substr($htmlfile,0,strpos($htmlfile,'?'));
        }
        $htmlfile   =   $htmlpath.$htmlfile;

        $dir   =  dirname($htmlfile);
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        if(file_put_contents($htmlfile,$content) === false) {
            return false;
        } else {
            return true;
        }
    }

    protected function echoLink($des,$url='',$color='',$wrap=1)
    {
        if(empty($url)){
            echo  "<font color=$color>" .$des .'</font>'. ($wrap==1? '<br>':'&nbsp;');
        }
        else{
            echo  '<a target="_blank" href="'. $url .'">'. "<font color=$color>" . $des .'</font></a>'. ($wrap==1? '<br>':'&nbsp;');
        }
        ob_flush();flush();
    }

    public function opt()
    {
        //分类列表
        $type_list = model('Type')->getCache('type_list');
        $this->assign('type_list',$type_list);

        $vod_type_list = [];
        $vod_type_ids = [];
        $art_type_list = [];
        $art_type_ids = [];
        foreach($type_list as $k=>$v){
            if($v['type_mid'] == 1){
                $vod_type_list[$k] = $v;
            }
            if($v['type_mid'] == 2){
                $art_type_list[$k] = $v;
            }
        }
        $vod_type_ids = array_keys($vod_type_list);
        $art_type_ids = array_keys($art_type_list);

        $this->assign('vod_type_list',$vod_type_list);
        $this->assign('art_type_list',$art_type_list);

        $this->assign('vod_type_ids',join(',',$vod_type_ids));
        $this->assign('art_type_ids',join(',',$art_type_ids));



        //当日视频分类ids
        $res = model('Vod')->updateToday('type');
        $this->assign('vod_type_ids_today',$res['data']);

        //当日文章分类ids
        $res = model('Art')->updateToday('type');
        $this->assign('art_type_ids_today',$res['data']);


        //专题列表
        $where = [];
        $where['topic_status'] = ['eq',1];
        $order = 'topic_id desc';
        $topic_list = model('Topic')->listData($where,$order,1,999);
        $this->assign('topic_list',$topic_list['list']);
        $topic_ids = join(',',array_keys($topic_list['list']));
        $this->assign('topic_ids',$topic_ids);

        //自定义页面
        $label_list = [];
        $path = MAC_ROOT_TEMPLATE .'label';
        if(is_dir($path)){
            $farr = glob($path.'/*');
            foreach($farr as $f){
                if(is_file($f)){
                    $f = str_replace($path."/","",$f);
                    $label_list[] = $f;
                }
            }
            unset($farr);
        }
        $this->assign('label_list',$label_list);
        $this->assign('label_ids',join(',',$label_list));


        $this->assign('title','生成静态管理');
        return $this->fetch('admin@make/opt');

    }

    public function make($pp=[])
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        if(!empty($pp)){
            $this->_param = $pp;
        }

        if($this->_param['ac'] == 'index'){
            $this->index();
        }
        elseif($this->_param['ac'] == 'map'){
            $this->map();
        }
        elseif($this->_param['ac'] == 'rss'){
            $this->rss();
        }
        elseif($this->_param['ac'] == 'type'){
            $this->type();
        }
        elseif($this->_param['ac'] == 'topic_index'){
            $this->topic_index();
        }
        elseif($this->_param['ac'] == 'topic_info'){
            $this->topic_info();
        }
        elseif($this->_param['ac'] == 'rss'){
            $this->rss();
        }
        elseif($this->_param['ac'] == 'info'){
            $this->info();
        }
        elseif($this->_param['ac'] == 'label'){
            $this->label();
        }
    }

    public function index()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        $GLOBALS['aid'] = mac_get_aid('index');
        $this->label_maccms();
        $link = 'index.html';
        $this->buildHtml($link,'./', 'index/index');
        $this->echoLink($link,'/'.$link);
        mac_jump( url('make/opt'),3 );
        exit;
    }

    public function map()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        $GLOBALS['aid'] = mac_get_aid('map');
        $this->label_maccms();
        $link = 'map.html';
        $this->buildHtml($link,'./','map/index');
        $this->echoLink($link,'/'.$link);
        mac_jump( url('make/opt'),3 );
        exit;
    }

    public function rss()
    {
        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        if(!in_array($this->_param['ac2'], ['index','baidu','google','so','sogou','bing','sm'])){
            return $this->error('参数错误');
        }
        if(empty(intval($this->_param['ps']))){
            $this->_param['ps'] = 1;
        }

        $GLOBALS['aid'] = mac_get_aid('rss');
        $this->label_maccms();

        for($i=1;$i<=$this->_param['ps'];$i++){
            $par =[];
            if($i>1){
                $par['page'] = $i;
                $_REQUEST['page'] = $i;
            }
            $link = mac_url('rss/'.$this->_param['ac2'],$par);

            $this->buildHtml($link,'./','rss/'.$this->_param['ac2']);
            $this->echoLink($link,$link);
        }
        mac_jump( url('make/opt'),3 );
        exit;
    }

    public function type()
    {
        if($this->_param['tab'] =='art'){
            $ids = $this->_param['arttype'];
            if(empty($ids) && $this->_param['ac2']=='day'){
                $res = model('Art')->updateToday('type');
                $ids = $res['data'];
            }
            $GLOBALS['mid'] = 2;
            $GLOBALS['aid'] = mac_get_aid('art','type');
        }
        else{
            $ids = $this->_param['vodtype'];
            if(empty($ids) && $this->_param['ac2']=='day'){
                $res = model('Vod')->updateToday('type');
                $ids = $res['data'];
            }
            $GLOBALS['mid'] = 1;
            $GLOBALS['aid'] = mac_get_aid('vod','type');
        }

        if($GLOBALS['config']['view'][$this->_param['tab'].'_type'] <2){
            mac_echo('浏览模式非静态，无法生成');
            exit;
        }

        $num = intval($this->_param['num']);
        $start = intval($this->_param['start']);
        $page_count = intval($this->_param['page_count']);
        $page_size = intval($this->_param['page_size']);
        $data_count = intval($this->_param['data_count']);

        if(empty($ids)){
            return $this->error('参数错误');
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }
        if ($num>=count($ids)){
            if(empty($this->_param['jump'])){
                $this->echoLink('分类页生成完毕');
                mac_jump( url('make/opt') ,3);
                exit;
            }
            else{
                $this->echoLink('分类页生成完毕，稍后继续生成首页');
                mac_jump( url('make/index',['jump'=>1]) ,3);
                exit;
            }
        }
        if($start<1){
            $start=1;
        }

        $id = $ids[$num];
        $type_list = model('Type')->getCache('type_list');
        $type_info = $type_list[$id];

        if(empty($data_count)){
            $where = [];
            $where['type_id|type_id_1'] = ['eq',$id];

            if($this->_param['tab'] =='art') {
                $where['art_status'] = ['eq', 1];
                $data_count = model('Art')->countData($where);
                $html = mac_read_file(MAC_ROOT_TEMPLATE . 'art/'.$type_info['type_tpl']);
                $labelRule = '{maccms:art(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:art}';
            }
            else{
                $where['vod_status'] = ['eq', 1];
                $data_count = model('Vod')->countData($where);
                $html = mac_read_file(MAC_ROOT_TEMPLATE . 'vod/'.$type_info['type_tpl']);
                $labelRule = '{maccms:vod(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:vod}';
            }

            $labelRule = mac_buildregx($labelRule,"");
            preg_match_all($labelRule,$html,$arr);

            for($i=0;$i<count($arr[2]);$i++) {
                $page_size = $arr[2][$i];
                break;
            }
            if(empty($page_size)){
                $page_size = 20;
                $page_count=1;
            }
            else{
                $page_count = ceil($data_count / $page_size);
            }
            if($page_count<1){ $page_count=1; }

            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;

            if($type_info['type_pid'] == 0){
                //$this->_param['page_count'] = 1;
            }
        }

        if($start > $page_count){
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $url = url('make/make') .'?'. http_build_query($this->_param);

            $this->echoLink('【'.$type_info['type_name'].'】列表页生成完毕，稍后继续');
            mac_jump( $url ,3);
            exit;
        }

        $sec_count = ceil($page_count / $GLOBALS['config']['app']['makesize']);
        $sec = ceil($start / $GLOBALS['config']['app']['makesize']);
        $this->echoLink('正在生成【'.$type_info['type_name'].'】列表页，共'.$this->_param['page_count'].'页，分'.$sec_count.'次生成，当前'.$sec.'次');
        $this->label_maccms();


        $n=1;
        for($i=$start;$i<=$page_count;$i++){
            $this->_param['start'] = $i;

            $_REQUEST['id'] = $id;
            $_REQUEST['page'] = $i;
            $this->label_type( $type_info['type_mid']==2 ? $GLOBALS['config']['view']['art_type'] : $GLOBALS['config']['view']['vod_type'] );
            $link = mac_url_type($type_info,['id'=>$id,'page'=>$i]);

            $this->buildHtml($link,'./', mac_tpl_fetch($this->_param['tab'],$type_info['type_tpl'],'type') );
            $this->echoLink('第'.$i.'页',$link);

            if($GLOBALS['config']['app']['makesize'] == $n){
                break;
            }
            $n++;
        }

        if(ENTRANCE=='api'){
            if ($num+1>=count($ids)) {
                mac_echo('定时任务完毕，本次每个分类生成' . $GLOBALS['config']['app']['makesize'] . '个列表页面，避免网站卡死！');
                die;
            }
            else{
                $this->_param['start'] = 1;
                $this->_param['num']++;
                $this->_param['data_count'] = 0;
                $this->_param['page_count'] = 0;
                $this->_param['page_size'] = 0;
                $this->type();
            }
        }

        if($this->_param['start'] >= $this->_param['page_count']){
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $this->echoLink('【'.$type_info['type_name'].'】列表页生成完毕，稍后继续');
        }
        elseif($this->_param['start'] < $this->_param['page_count']){
            $this->_param['start']++;

            $this->echoLink('让服务器休息一会，稍后继续');
        }
        $url = url('make/make') .'?'. http_build_query($this->_param);
        mac_jump( $url ,3);
    }

    public function topic_index()
    {
        $num = intval($this->_param['num']);
        $start = intval($this->_param['start']);
        $page_count = intval($this->_param['page_count']);
        $data_count = intval($this->_param['data_count']);
        $ids = $this->_param['topic'];

        $GLOBALS['mid'] = 3;
        $GLOBALS['aid'] = mac_get_aid('topic');

        if($start<1){
            $start=1;
        }
        $GLOBALS['config']['app']['makesize'] = 1;

        if($GLOBALS['config']['view']['topic_index'] <2){
            mac_echo('浏览模式非静态，无法生成');
            exit;
        }

        if(empty($data_count)){
            $where = [];
            $where['topic_status'] = ['eq', 1];
            $data_count = model('Topic')->countData($where);
            $html = mac_read_file(MAC_ROOT_TEMPLATE . 'topic/index.html');
            $labelRule = '{maccms:topic(.*?)num="(.*?)"(.*?)paging="yes"([\s\S]*?)}([\s\S]*?){/maccms:topic}';

            $labelRule = mac_buildregx($labelRule,"");
            preg_match_all($labelRule,$html,$arr);

            for($i=0;$i<count($arr[2]);$i++) {
                $page_size = $arr[2][$i];
                break;
            }
            if(empty($page_size)){
                $page_size = 20;
            }
            $page_count = ceil($data_count / $page_size);
            if($page_count<1){ $page_count=1; }

            $this->_param['start'] = $start;
            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;
        }

        if($start > $page_count){
            $this->echoLink('专题列表生成完毕');
            mac_jump( url('make/opt') ,3);
            exit;
        }

        $sec_count = ceil($page_count / $GLOBALS['config']['app']['makesize']);
        $sec = ceil($start / $GLOBALS['config']['app']['makesize']);
        $this->echoLink('正在生成专题列表页，共'.$this->_param['page_count'].'页，分'.$sec_count.'次生成，当前'.$sec.'次');

        $this->label_maccms();

        $n=1;
        for($i=$start;$i<=$page_count;$i++){
            $this->_param['start'] = $i;
            $_REQUEST['page'] = $i;

            $this->label_topic_index($data_count);
            $link = mac_url_topic_index(['page'=>$i]);
            $this->buildHtml($link,'./','topic/index');
            $this->echoLink('第'.$i.'页',$link);

            if($GLOBALS['config']['app']['makesize'] == $n){
                break;
            }
            $n++;
        }

        if($this->_param['start'] >= $page_count){
            $this->echoLink('专题列表生成完毕');
            mac_jump( url('make/opt') ,3);
            exit;
        }
        else{
            $this->_param['start']++;
            $this->echoLink('让服务器休息一会，稍后继续');
        }
        $url = url('make/make') .'?'. http_build_query($this->_param);

        mac_jump( $url ,3);
    }

    public function topic_info()
    {
        $ids = $this->_param['topic'];

        $GLOBALS['mid'] = 3;
        $GLOBALS['aid'] = mac_get_aid('topic','detail');

        if(empty($ids)){
            return $this->error('参数错误');
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }

        if($GLOBALS['config']['view']['topic_detail'] <2){
            mac_echo('浏览模式非静态，无法生成');
            exit;
        }


        $data_count = count($ids);
        $this->echoLink('正在生成专题内容页，共'.$data_count.'条');
        $this->label_maccms();

        $n=1;
        foreach($ids as $a){
            $_REQUEST['id'] = $a;

            $where = [];
            $where['topic_id'] = ['eq',$a];
            $where['topic_status'] = ['eq',1];
            $res = model('Topic')->infoData($where);
            if($res['code'] == 1) {
                $topic_info = $res['info'];

                $this->label_topic_detail($topic_info);
                $link = mac_url_topic_detail($topic_info);
                $this->buildHtml($link,'./', mac_tpl_fetch('topic',$topic_info['topic_tpl'],'detail') );
                $this->echoLink($topic_info['topic_name'],$link);
                $n++;
            }
        }

        if(!empty($ids)){
            Db::name('topic')->where(['topic_id'=>['in',$ids]])->update(['topic_time_make'=>time()]);
        }
        if($this->_param['ref'] ==1 && !empty($_SERVER["HTTP_REFERER"])){
            mac_jump($_SERVER["HTTP_REFERER"],2);
            die;
        }

        $this->echoLink('专题内容页生成完毕');
        mac_jump( url('make/opt') ,3);
    }


    public function info()
    {
        $where = [];

        $ids = $this->_param['ids'];
        if($this->_param['tab'] =='art'){
            $type_ids = $this->_param['arttype'];
            $order='art_time desc';
            $where['art_status'] = ['eq',1];

            if($GLOBALS['config']['view']['art_detail'] <2){
                mac_echo('浏览模式非静态，无法生成');
                exit;
            }

        }
        else{
            $type_ids = $this->_param['vodtype'];
            $order='vod_time desc';
            $where['vod_status'] = ['eq',1];

            if($GLOBALS['config']['view']['vod_detail'] <2 && $GLOBALS['config']['view']['vod_play'] <2 && $GLOBALS['config']['view']['vod_down'] <2){
                mac_echo('浏览模式非静态，无法生成');
                exit;
            }

        }

        $num = intval($this->_param['num']);
        $start = intval($this->_param['start']);
        $page_count = intval($this->_param['page_count']);
        $data_count = intval($this->_param['data_count']);
        if($start<1){
            $start=1;
        }
        if($page_count<1){
            $page_count=1;
        }

        $where = [];
        if(empty($ids) && empty($type_ids) && empty($this->_param['ac2'])){
            return $this->error('参数错误');
        }
        $type_name ='';


        if(!empty($type_ids)){
            if(!is_array($type_ids)){
                $type_ids = explode(',',$type_ids);
            }

            if ($num>=count($type_ids)){

                if(empty($this->_param['jump'])){
                    $this->echoLink('内容页生成完毕1');
                    mac_jump( url('make/opt') ,3);
                    exit;
                }
                else{
                    $this->echoLink('内容页生成完毕，稍后继续生成分类页');
                    mac_jump( url('make/make',['jump'=>1,'ac'=>'type','tab'=>$this->_param['tab'], $this->_param['tab'].'type'=> join(',',$type_ids) ,'ac2'=>'day']) ,3);
                    exit;
                }
            }

            $type_id = $type_ids[$num];
            $type_list = model('Type')->getCache('type_list');
            $type_info = $type_list[$type_id];

            $type_name = $type_info['type_name'];
            $where['type_id'] = ['eq',$type_id];
        }
        elseif(!empty($ids)){
            $type_name ='选择数据';
            if($start > $page_count){
                mac_echo('内容页生成完毕2');
                exit;
            }
            $where[$this->_param['tab'].'_id'] = ['in',$ids];
        }


        if($this->_param['ac2'] =='day'){
            $type_name .='今日数据';
            $where[$this->_param['tab'].'_time'] = ['gt', strtotime(date('Y-m-d'))];


            if ($num>=count($type_ids)){

            }
            if($start > $page_count){
                //$this->echoLink('内容页生成完毕3');
                //mac_jump( url('make/opt') ,3);
                //exit;
            }
        }
        elseif($this->_param['ac2'] =='nomake'){
            $type_name ='未生成数据';
            $start=1;
            $data_count=0;
            $where[$this->_param['tab'].'_time_make'] = ['exp',  Db::raw(' < '. $this->_param['tab'].'_time')];
            if($start > $page_count){
                $this->echoLink('内容页生成完毕4');
                mac_jump( url('make/opt') ,3);
                exit;
            }
        }

        if(ENTRANCE=='api'){
            $GLOBALS['config']['app']['makesize'] = 999;
        }

        if(empty($data_count)){
            if($this->_param['tab'] =='art'){
                $data_count = model('Art')->countData($where);
            }
            else{
                $data_count = model('Vod')->countData($where);
            }


            $page_count = ceil($data_count / $GLOBALS['config']['app']['makesize']);
            $page_size = $GLOBALS['config']['app']['makesize'];

            $this->_param['data_count'] = $data_count;
            $this->_param['page_count'] = $page_count;
            $this->_param['page_size'] = $page_size;
        }

        if($start > $page_count){

            $this->echoLink('【'.$type_name.'】内容页生成完毕，稍后继续');

            if($this->_param['ac2'] =='nomake' ){
                mac_jump( url('make/opt') ,3);
                die;
            }
            else{

            }

            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $url = url('make/make') .'?'. http_build_query($this->_param);


            mac_jump( $url ,3);
            exit;
        }


        $this->echoLink('正在生成【'.$type_name.'】内容页，共'.$this->_param['data_count'].'条，分'.$this->_param['page_count'].'次生成，每次'.$this->_param['page_size'].'条，当前第'.$start.'次');

        if($this->_param['tab'] =='art') {
            $res = model('Art')->listData($where, $order, $start, $GLOBALS['config']['app']['makesize']);
        }
        else{
            $res = model('Vod')->listData($where, $order, $start, $GLOBALS['config']['app']['makesize']);
        }


        $update_ids=[];
        foreach($res['list'] as $k=>$v){

            if(!empty($v['art_id'])) {

                $GLOBALS['type_id'] =$v['type_id'];
                $GLOBALS['type_pid'] = $v['type']['type_pid'];

                $GLOBALS['mid'] = 2;
                $GLOBALS['aid'] = mac_get_aid('art','detail');

                $this->label_maccms();
                $_REQUEST['id'] = $v['art_id'];
                echo mac_substring($v['art_name'],100) .'&nbsp;';

                if(!empty($v['art_content'])) {
                    $art_page_list = mac_art_list($v['art_title'], $v['art_note'], $v['art_content']);
                    $art_page_total = count($art_page_list);
                }

                for($i=1;$i<=$art_page_total;$i++){
                    $v['art_page_list'] = mac_art_list($v['art_title'], $v['art_note'], $v['art_content']);
                    $v['art_page_total'] = count($v['art_page_list']);
                    $_REQUEST['page'] = $i;

                    $info = $this->label_art_detail($v,$GLOBALS['config']['view']['art_detail']);
                    $link = mac_url_art_detail($v, ['page' => $i]);

                    $this->buildHtml($link,'./', mac_tpl_fetch('art',$info['art_tpl'],'detail') );
                    if($i==1) {
                        $this->echoLink('detail', $link);
                    }
                }
                $update_ids[] = $v['art_id'];
            }
            else{

                $GLOBALS['type_id'] =$v['type_id'];
                $GLOBALS['type_pid'] = $v['type']['type_pid'];

                $GLOBALS['mid'] = 1;
                $GLOBALS['aid'] = mac_get_aid('vod','detail');

                $_REQUEST['id'] = $v['vod_id'];
                echo $v['vod_name'].'&nbsp;';;
                if(!empty($v['vod_play_from'])) {
                    $v['vod_play_list'] = mac_play_list($v['vod_play_from'], $v['vod_play_url'], $v['vod_play_server'], $v['vod_play_note'],'play');
                    $v['vod_play_total'] =  count($v['vod_play_list']);
                }
                if(!empty($v['vod_down_from'])) {
                    $v['vod_down_list'] = mac_play_list($v['vod_down_from'], $v['vod_down_url'], $v['vod_down_server'], $v['vod_down_note'],'down');
                    $v['vod_down_total'] =  count($v['vod_down_list']);
                }

                if($GLOBALS['config']['view']['vod_detail'] == 2){
                    $this->label_maccms();
                    $info = $this->label_vod_detail($v, $GLOBALS['config']['view']['vod_detail']);
                    $link = mac_url_vod_detail($v);
                    $this->buildHtml($link, './', mac_tpl_fetch('vod', $info['vod_tpl'], 'detail'));
                    $this->echoLink('detail', $link, '', 0);
                }
                $_REQUEST['id'] = $v['vod_id'];
                
                $update_ids[] = $v['vod_id'];
                $flag = ['play','down'];
                foreach($flag as $f) {
                    $GLOBALS['aid'] = mac_get_aid('vod',$f);

                    $this->label_maccms();
                    //播放页 和 下载页
                    if ($GLOBALS['config']['view']['vod_'.$f] < 2) {

                    }
                    else{
                        if ($GLOBALS['config']['view']['vod_'.$f] == 2) {
                        	$_REQUEST['sid'] = 1;
                        	$_REQUEST['nid'] = 1;
                            $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);
                            $link =  ($f=='play' ?mac_url_vod_play($v,['sid'=>1,'nid'=>1]) : mac_url_vod_down($v,['sid'=>1,'nid'=>1]) );
                            $this->buildHtml($link, './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                            $this->echoLink($f, $link, '', 0);
                        }
                        elseif ($GLOBALS['config']['view']['vod_'.$f] == 3) {
                            for ($i = 1; $i <= $v['vod_'.$f.'_total']; $i++) {
                                for ($j = 1; $j <= $v['vod_'.$f.'_list'][$i]['url_count']; $j++) {
                                	$_REQUEST['sid'] = $i;
                                	$_REQUEST['nid'] = $j;
                                    $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);

                                    $link = ($f=='play' ? mac_url_vod_play($v, ['sid' => $i, 'nid' => $j]) : mac_url_vod_down($v, ['sid' => $i, 'nid' => $j]) );
                                    $link_sp = explode('?',$link);
                                    $this->buildHtml($link_sp[0], './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                                    if($i==1 && $j==1) {
                                        $this->echoLink('' . $f . '-' . $i . '-' . $j, $link, '', 0);
                                    }
                                }
                            }
                        }
                        elseif ($GLOBALS['config']['view']['vod_'.$f] == 4) {
                            $tmp_play_list = $v['vod_'.$f.'_list'];
                            for ($i = 1; $i <= $v['vod_'.$f.'_total']; $i++) {
                                $v['vod_'.$f.'_list'] = [];
                                $v['vod_'.$f.'_list'][$i] = $tmp_play_list[$i];
                                $info = $this->label_vod_play($f,$v,$GLOBALS['config']['view']['vod_'.$f]);
                                $link = ($f=='play' ? mac_url_vod_play($v, ['sid' => $i]) : mac_url_vod_down($v, ['sid' => $i]) );
                                $link_sp = explode('?',$link);
                                $this->buildHtml($link_sp[0], './', mac_tpl_fetch('vod', $info['vod_tpl_'.$f], $f) );
                                if($i==1) {
                                    $this->echoLink('' . $f . '-' . $i, $link, '', 0);
                                }
                            }
                        }
                    }
                }
                echo '<br>';
            }
        }

        if(!empty($update_ids)){
            Db::name($this->_param['tab'])->where([$this->_param['tab'].'_id'=>['in',$update_ids]])->update([$this->_param['tab'].'_time_make'=>time()]);
        }
        if($this->_param['ref'] ==1 && !empty($_SERVER["HTTP_REFERER"])){
            mac_jump($_SERVER["HTTP_REFERER"],2);
            die;
        }

        if($start > $page_count){
            $this->_param['start'] = 1;
            $this->_param['num']++;
            $this->_param['data_count'] = 0;
            $this->_param['page_count'] = 0;
            $this->_param['page_size'] = 0;
            $this->echoLink('【'.$type_name.'】内容页生成完毕，稍后继续');


            if($this->_param['ac2'] !=''){
                //mac_jump( url('make/opt') ,3);
                //die;
            }
            else{

            }
        }
        else{
            $this->_param['start'] = ++$start;
            $this->echoLink('让服务器休息一会，稍后继续');
        }
        $url = url('make/make') .'?'. http_build_query($this->_param);

        mac_jump( $url ,3);
    }


    public function label()
    {
        $ids = $this->_param['label'];
        $GLOBALS['aid'] = mac_get_aid('label');
        if(empty($ids)){
            return $this->error('参数错误');
        }
        if(!is_array($ids)){
            $ids = explode(',',$ids);
        }
        $data_count = count($ids);
        $this->echoLink('正在生成自定义页，共'.$data_count.'个页');
        $this->label_maccms();

        $n=1;
        foreach($ids as $a){
            $fullname = explode('.',$a)[0];
            $file = 'label/'.$a;
            $tpl = 'label/'.$fullname;

            $this->buildHtml($file ,'./', $tpl );
            $this->echoLink($file,'/'. $file);

            $n++;
        }

        $this->echoLink('自定义页生成完毕');
        mac_jump( url('make/opt') ,3);
    }


}
