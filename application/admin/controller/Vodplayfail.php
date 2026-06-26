<?php
namespace app\admin\controller;
use think\Db;

// 视频线路播放失败统计（播放失败自动切换线路）
class Vodplayfail extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page'] ?? 0) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit'] ?? 0) < 1 ? $this->_pagesize : intval($param['limit']);

        $where = [];
        if (isset($param['vod_id']) && $param['vod_id'] !== '') {
            $where['vod_id'] = ['eq', intval($param['vod_id'])];
        }
        if (!empty($param['wd'])) {
            $where['vod_name'] = ['like', '%' . $param['wd'] . '%'];
        }

        // 排序：默认按累计失败次数降序，可切换最近失败时间
        $order = 'fail_count desc,last_fail_time desc';
        if (($param['order'] ?? '') === 'time') {
            $order = 'last_fail_time desc';
        }

        $res = model('VodPlayFail')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', lang('admin/vodplayfail/title'));
        return $this->fetch('admin@vodplayfail/index');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        $all = $param['all'] ?? 0;
        if (!empty($ids)) {
            $where = [];
            $where['fail_id'] = ['in', $ids];
            if ($all == 1) {
                $where['fail_id'] = ['gt', 0];
            }
            $res = model('VodPlayFail')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }
}
