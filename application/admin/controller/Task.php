<?php
namespace app\admin\controller;
use think\Db;

class Task extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    // 任务列表
    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if (in_array($param['status'], ['0', '1'], true)) {
            $where['task_status'] = ['eq', $param['status']];
        }
        if (!empty($param['type'])) {
            $where['task_type'] = ['eq', intval($param['type'])];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['task_name|task_action'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'task_type asc, task_sort asc, task_id asc';
        $res = model('Task')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('task/admin_title'));
        return $this->fetch('admin@task/index');
    }

    // 任务编辑
    public function info()
    {
        if (request()->isPost()) {
            $param = input();
            $res = model('Task')->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $param = input();
        $info = [];
        if (!empty($param['id'])) {
            $where = [];
            $where['task_id'] = ['eq', $param['id']];
            $res = model('Task')->infoData($where);
            if ($res['code'] == 1) {
                $info = $res['info'];
            }
        }
        $this->assign('info', $info);
        $this->assign('title', lang('task/admin_title'));
        return $this->fetch('admin@task/info');
    }

    // 删除任务
    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        if (!empty($ids)) {
            $where = [];
            $where['task_id'] = ['in', $ids];
            $res = model('Task')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    // 修改状态
    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];
        if (!empty($ids) && in_array($col, ['task_status'])) {
            $where = [];
            $where['task_id'] = ['in', $ids];
            $res = model('Task')->fieldData($where, $col, $val);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    // 任务记录列表
    public function log()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if (!empty($param['uid'])) {
            $where['user_id'] = ['eq', intval($param['uid'])];
        }
        if (!empty($param['task_id'])) {
            $where['task_id'] = ['eq', intval($param['task_id'])];
        }
        if (in_array($param['status'], ['0', '1', '2'], true)) {
            $where['log_status'] = ['eq', $param['status']];
        }

        $order = 'log_id desc';
        $res = model('TaskLog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('task/admin_log_title'));
        return $this->fetch('admin@task/log');
    }

    // 删除任务记录
    public function log_del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];
        if (!empty($ids) || !empty($all)) {
            $where = [];
            $where['log_id'] = ['in', $ids];
            if ($all == 1) {
                $where['log_id'] = ['gt', 0];
            }
            $res = model('TaskLog')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }
}
