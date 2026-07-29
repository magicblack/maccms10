<?php
namespace app\admin\controller;

class Follow extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];
        $param['limit'] = min(intval($param['limit']), 100);

        $where = [];
        if (in_array($param['status'] ?? '', ['0', '1'], true)) {
            $where['follow_status'] = ['eq', $param['status']];
        }
        if (!empty($param['uid'])) {
            $where['user_id'] = ['eq', $param['uid']];
        }
        if (!empty($param['follow_uid'])) {
            $where['follow_uid'] = ['eq', $param['follow_uid']];
        }
        if (in_array($param['mutual'] ?? '', ['0', '1'], true)) {
            $where['follow_mutual'] = ['eq', $param['mutual']];
        }

        $order = 'follow_id desc';
        $res = model('Follow')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('follow/admin_title'));
        return $this->fetch('follow/index');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        $all = $param['all'] ?? '';

        if (!empty($ids) || !empty($all)) {
            $where = [];
            $where['follow_id'] = ['in', $ids];
            if ($all == 1) {
                $where['follow_id'] = ['gt', 0];
            }
            $res = model('Follow')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        $col = $param['col'] ?? '';
        $val = $param['val'] ?? '';

        if (!empty($ids) && in_array($col, ['follow_status'], true) && in_array($val, ['0', '1'], true)) {
            $where = [];
            $where['follow_id'] = ['in', $ids];
            $res = model('Follow')->fieldData($where, $col, $val);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }
}