<?php
namespace app\admin\controller;
use think\Db;

class Notify extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : intval($param['limit']);

        $where = [];
        $where['user_id'] = ['eq', 0];
        if (in_array($param['type'], ['system', 'order', 'vip', 'activity', 'reply', 'announce'], true)) {
            $where['notify_type'] = ['eq', $param['type']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['notify_title'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'notify_id desc';
        $res = model('Notify')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', lang('admin/notify/title'));
        return $this->fetch('notify/index');
    }

    public function info()
    {
        $param = input();
        $info = [];
        if (!empty($param['id'])) {
            $where = [];
            $where['notify_id'] = ['eq', intval($param['id'])];
            $res = model('Notify')->infoData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            $info = $res['info'];
        }
        $this->assign('info', $info);
        $this->assign('title', lang('admin/notify/broadcast_title'));
        return $this->fetch('notify/info');
    }

    public function broadcast()
    {
        $param = input('post.');
        $type = isset($param['notify_type']) ? trim($param['notify_type']) : '';
        $title = isset($param['notify_title']) ? trim($param['notify_title']) : '';
        $content = isset($param['notify_content']) ? trim($param['notify_content']) : '';
        $link = isset($param['notify_link']) ? trim($param['notify_link']) : '';

        if (!in_array($type, ['system', 'order', 'vip', 'activity', 'reply', 'announce'], true)) {
            return $this->error(lang('notify/type_invalid'));
        }
        $title = mac_filter_xss($title);
        $content = mac_filter_xss($content);
        $link = mac_filter_xss($link);
        if (empty($title) || empty($content)) {
            return $this->error(lang('param_err'));
        }

        $res = model('Notify')->broadcast($type, $title, $content, $link);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg'], url('notify/index'));
    }

    public function del()
    {
        $param = input();
        $ids = isset($param['ids']) ? $param['ids'] : '';
        $all = isset($param['all']) ? $param['all'] : '';
        if ($all == 1) {
            $where = [];
            $where['user_id'] = ['eq', 0];
            $where['notify_id'] = ['gt', 0];
            $res = model('Notify')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        if (!empty($ids)) {
            $where = [];
            $where['notify_id'] = ['in', $ids];
            $res = model('Notify')->delData($where);
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
        if (empty($param['id']) || !isset($param['col']) || !isset($param['val'])) {
            return $this->error(lang('param_err'));
        }
        if (!in_array($param['col'], ['notify_read'], true)) {
            return $this->error(lang('param_err'));
        }
        $where = [];
        $where['notify_id'] = ['eq', intval($param['id'])];
        $res = model('Notify')->fieldData($where, $param['col'], $param['val']);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg']);
    }
}
