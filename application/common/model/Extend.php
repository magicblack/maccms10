<?php
namespace app\common\model;
use think\Db;
use think\Cache;

class Extend extends Base {


    public function dataCount()
    {
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'data_count';
        $data = Cache::get($key);
        if(empty($data)){
            $totay = strtotime(date('Y-m-d'));

            //视频
            $where = [];
            $where['vod_status'] = ['eq',1];
            $tmp = model('Vod')->field('type_id_1,type_id,count(vod_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['vod_all'] += intval($v['cc']);
                $list['type_all_'.$v['type_id']] = $v->toArray();
            }

            $where['vod_time'] = ['egt',$totay];
            $tmp = model('Vod')->field('type_id_1,type_id,count(vod_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['vod_today'] += intval($v['cc']);
                $list['type_today_'.$v['type_id']] = $v->toArray();
            }
            $data['vod_min'] = model('Vod')->min('vod_id');

            //文章
            $where = [];
            $where['art_status'] = ['eq',1];
            $tmp = model('Art')->field('type_id_1,type_id,count(art_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['art_all'] += intval($v['cc']);
                $list['type_all_'.$v['type_id']] = $v->toArray();
            }
            $where['art_time'] = ['egt',$totay];
            $tmp = model('Art')->field('type_id_1,type_id,count(art_id) as cc')->where($where)->group('type_id_1,type_id')->select();
            foreach($tmp as $k=>$v){
                $data['art_today'] += intval($v['cc']);
                $list['type_today_'.$v['type_id']] = $v->toArray();
            }
            $data['art_min'] = model('Art')->min('art_id');

            //分类
            foreach($list as $k=>$v) {
                $data[$k]=$v['cc'];

                if(strpos($k,'type_all')!==false){
                    $data['type_all_' . $v['type_id_1']] += $v['cc'];
                }
                if(strpos($k,'type_today')!==false){
                    $data['type_today_' . $v['type_id_1']] += $v['cc'];
                }
            }

            //专题
            $where = [];
            $where['topic_status'] = ['eq',1];
            $tmp = model('Topic')->where($where)->count();
            $data['topic_all'] = $tmp;
            $where['topic_time'] = ['egt',$totay];
            $tmp = model('Topic')->where($where)->count();
            $data['topic_today'] = $tmp;
            $data['tpoic_min'] = model('Topic')->min('topic_id');


            //演员库
            $where = [];
            $where['actor_status'] = ['eq',1];
            $tmp = model('Actor')->where($where)->count();
            $data['actor_all'] = $tmp;
            $where['actor_time'] = ['egt',$totay];
            $tmp = model('Actor')->where($where)->count();
            $data['actor_today'] = $tmp;
            $data['actor_min'] = model('Actor')->min('actor_id');

            //角色库
            $where = [];
            $where['role_status'] = ['eq',1];
            $tmp = model('Role')->where($where)->count();
            $data['role_all'] = $tmp;
            $where['role_time'] = ['egt',$totay];
            $tmp = model('Role')->where($where)->count();
            $data['role_today'] = $tmp;
            $data['role_min'] = model('Role')->min('role_id');

            //网址库
            $where = [];
            $where['website_status'] = ['eq',1];
            $tmp = model('Website')->where($where)->count();
            $data['website_all'] = $tmp;
            $where['website_time'] = ['egt',$totay];
            $tmp = model('Website')->where($where)->count();
            $data['website_today'] = $tmp;
            $data['website_min'] = model('Website')->min('website_id');

            Cache::set($key,$data,$GLOBALS['config']['app']['cache_time']);
        }
        return $data;
    }

    public function areaData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_area'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['area'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['area_name' => $v];
            }
        }

        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('area_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function langData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_lang'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['lang'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['lang_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('lang_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function classData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_class'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['class'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['class_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('class_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function yearData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_year'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['year'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['year_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('year_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function versionData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_version'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['version'];
            }
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['version_name' => $v];
            }
        }

        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('version_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function stateData($lp)
    {
        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        $config = config('maccms.app');
        $data_str = $config['vod_extend_state'];
        if($tid>0){
            $type_list = model('Type')->getCache('tree_list');
            $type_info = $type_list[$tid];
            if(!empty($type_info)){
                $type_extend = json_decode($type_info['type_extend'],true);
                $data_str = $type_extend['state'];
            }
        }

        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        $tmp = explode(',',$data_str);
        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['state_name' => $v];
            }
        }
        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('state_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }

    public function letterData($lp)
    {
        $data_str = 'A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,0-9';
        $tmp = explode(',',$data_str);

        $order = $lp['order'];
        $start = intval(abs($lp['start']));
        $num = intval(abs($lp['num']));
        $tid = intval($lp['tid']);

        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        if(empty($num)){
            $num = 20;
        }
        if($start>1){
            $start--;
        }

        if($tid>0){

        }

        if($order=='desc'){
            $tmp = array_reverse($tmp);
        }
        $list = [];
        foreach($tmp as $k=>$v){
            if($k>=$start && $k<$num){
                $list[] = ['letter_name' => $v];
            }
        }

        $list = array_filter($list);
        $total = count($list);

        $cach_name = $GLOBALS['config']['app']['cache_flag']. '_' . md5('letter_listcache_'.join('&',$lp).'_'.$order.'_'.$num.'_'.$start);

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>1,'limit'=>$num,'total'=>$total,'list'=>$list];
    }



}