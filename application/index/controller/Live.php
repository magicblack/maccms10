<?php
namespace app\index\controller;

class Live extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 直播分类筛选页（频道分组 Tab + 列表）
     */
    public function index()
    {
        $this->assign('live_cate_id', (int) input('cate_id/d', 0));
        return $this->label_fetch('live/index');
    }

    /**
     * 直播筛选 / 选台列表页
     */
    public function show()
    {
        return $this->label_fetch('live/show');
    }

    /**
     * 直播播放页
     */
    public function play()
    {
        $param = mac_param_url();
        $live_id = 0;
        if (isset($param['id'])) {
            $live_id = (int)$param['id'];
        }
        $this->assign('param', $param);
        $this->assign('live_id', $live_id);
        return $this->label_fetch('live/play');
    }
}
