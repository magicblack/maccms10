<?php
namespace app\index\controller;
use think\Controller;
use app\common\util\SearchService;

class Vod extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->label_fetch('vod/index');
    }

    public function type()
    {
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('vod',$info['type_tpl'],'type') );
    }

    public function show()
    {
        $this->check_show();
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('vod',$info['type_tpl_list'],'show') );
    }

    public function ajax_show()
    {
        $this->check_ajax();
        $this->check_show(1);
        $info = $this->label_type();
        return $this->label_fetch('vod/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        SearchService::logFromParam(1, $param);
        $this->label_search($param);
        return $this->label_fetch('vod/search');
    }

    /**
     * 移动端独立搜索落地页（无检索条件，不触发 search 频次/验证码）
     */
    public function search_hub()
    {
        if ($GLOBALS['config']['app']['search'] == 0) {
            return $this->error(lang('search_close'));
        }
        $param = mac_param_url();
        $this->label_search($param);
        return $this->label_fetch('vod/search_hub');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param,1);
        SearchService::logFromParam(1, $param);
        $this->label_search($param);
        return $this->label_fetch('vod/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_vod_detail();
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==2){
            return $this->label_fetch('vod/copyright');
        }
        if(!empty($info['vod_pwd']) && session('1-1-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/detail_pwd');
        }
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/ajax_detail');
    }

    public function copyright()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/copyright');
    }

    public function role()
    {
        $info = $this->label_vod_role();
        return $this->label_fetch('vod/role');
    }

    public function play()
    {
        $info = $this->label_vod_play('play');
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==3){
            return $this->label_fetch('vod/copyright');
        }
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl_play'],'play') );
    }

    public function player()
    {
        $info = $this->label_vod_play('play',[],0,1);
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==4){
            return $this->label_fetch('vod/copyright');
        }
        if(!empty($info['vod_pwd_play']) && session('1-4-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/player_pwd');
        }
        return $this->label_fetch('vod/player');
    }

    public function down()
    {
        $info = $this->label_vod_play('down');
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl_down'],'down') );
    }

    public function downer()
    {
        $info = $this->label_vod_play('down');
        if(!empty($info['vod_pwd_down']) && session('1-5-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/downer_pwd');
        }
        return $this->label_fetch('vod/downer');
    }

    public function rss()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/rss');
    }

    public function plot()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/plot');
    }

    /**
     * 最近更新列表页（全部分类按更新时间排序）
     */
    public function last_vod()
    {
        $param = mac_param_url();
        $this->assign('param', $param);
        $obj = ['type_name' => '最近更新'];
        $this->assign('obj', $obj);
        return $this->label_fetch('vod/last_vod');
    }

}
