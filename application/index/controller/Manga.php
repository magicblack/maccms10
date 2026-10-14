<?php
namespace app\index\controller;

class Manga extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->label_fetch('manga/index');
    }

    public function type()
    {
        $info = $this->label_type();
        return $this->label_fetch(mac_tpl_fetch('manga', $info['type_tpl'], 'type'));
    }

    public function show()
    {
        $this->check_show();
        $info = $this->label_type();
        return $this->label_fetch(mac_tpl_fetch('manga', $info['type_tpl_list'], 'show'));
    }

    public function detail()
    {
        $info = $this->label_manga_detail();
        if(!empty($info['manga_pwd']) && session('8-1-'.$info['manga_id'])!='1'){
            return $this->label_fetch('manga/detail_pwd');
        }
        return $this->label_fetch('manga/detail');
    }

    public function play()
    {
        $info = $this->label_manga_detail([], 0, true);
        // 与 detail() 同一访问密码门：阅读页可直接打开，不校验即可绕过详情页密码
        if(!empty($info['manga_pwd']) && session('8-1-'.$info['manga_id'])!='1'){
            return $this->label_fetch('manga/detail_pwd');
        }
        return $this->label_fetch('manga/play');
    }
}

