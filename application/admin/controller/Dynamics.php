<?php
namespace app\admin\controller;

class Dynamics extends Base
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
        if (!empty($param['uid'])) {
            $where['user_id'] = ['eq', $param['uid']];
        }
        if (!empty($param['type'])) {
            $where['dynamics_type'] = ['eq', $param['type']];
        }

        $order = 'dynamics_id desc';
        $res = model('Dynamics')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('dynamics/admin_title'));
        return $this->fetch('dynamics/index');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        $all = $param['all'] ?? '';

        if (!empty($ids) || !empty($all)) {
            $where = [];
            $where['dynamics_id'] = ['in', $ids];
            if ($all == 1) {
                $where['dynamics_id'] = ['gt', 0];
            }
            $res = model('Dynamics')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }
}