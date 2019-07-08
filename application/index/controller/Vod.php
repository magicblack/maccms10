<?php
namespace app\index\controller;
use think\Controller;

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
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('vod',$info['type_tpl_list'],'show') );
    }

    public function ajax_show()
    {
        $info = $this->label_type();
        return $this->label_fetch('vod/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->assign('param',$param);
        return $this->label_fetch('vod/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->assign('param',$param);
        return $this->label_fetch('vod/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_vod_detail();
        if($info['vod_copyright']==1 && !empty($info['vod_jumpurl']) && $GLOBALS['config']['app']['copyright_status']==2){
            return $this->label_fetch('vod/copyright');
        }
        if(!empty($info['vod_pwd']) && session('1-1-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/detail_pwd');
        }
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl'],'detail') );
    }

    public function ajax_detail()
    {
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

}
