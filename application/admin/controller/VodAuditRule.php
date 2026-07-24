<?php

namespace app\admin\controller;

use app\common\util\VodAuditService;

class VodAuditRule extends Base
{
    public function __construct()
    {
        parent::__construct();
        // admin 模块统一使用新版后台模板目录 view_new/。
        // Init 会把 template.view_path 指向前台模板目录，模块相对 fetch 需显式指定，否则会白屏。
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');

    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : intval($param['limit']);

        $where = [];
        if (in_array($param['status'] ?? '', ['0', '1'], true)) {
            $where['rule_status'] = ['eq', $param['status']];
        }
        if (!empty($param['type'])) {
            $where['rule_type'] = ['eq', $param['type']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['rule_name|rule_pattern|rule_remark'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'rule_sort asc,rule_id desc';
        $res = model('VodAuditRule')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('admin/vod_audit/rule_title'));
        return $this->fetch('vod_audit_rule/index');
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = input('post.');
            $res = model('VodAuditRule')->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $param = input();
        $info = [
            'rule_id' => 0,
            'rule_name' => '',
            'rule_type' => 'title_keyword',
            'rule_pattern' => '',
            'rule_action' => VodAuditService::STATUS_REJECTED,
            'rule_remark' => '',
            'rule_status' => 1,
            'rule_sort' => 0,
        ];
        if (!empty($param['id'])) {
            $res = model('VodAuditRule')->infoData(['rule_id' => intval($param['id'])]);
            if ($res['code'] == 1) {
                $info = array_merge($info, $res['info']);
            }
        }
        $this->assign('info', $info);
        $this->assign('title', lang('admin/vod_audit/rule_title'));
        return $this->fetch('vod_audit_rule/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        if (empty($ids)) {
            return $this->error(lang('param_err'));
        }
        $where = ['rule_id' => ['in', $ids]];
        $res = model('VodAuditRule')->delData($where);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg']);
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'] ?? '';
        $col = $param['col'] ?? '';
        $val = $param['val'] ?? '';
        if (empty($ids) || $col !== 'rule_status') {
            return $this->error(lang('param_err'));
        }
        $where = ['rule_id' => ['in', $ids]];
        $res = model('VodAuditRule')->fieldData($where, $col, $val);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        model('VodAuditRule')->clearRuleCache();
        return $this->success($res['msg']);
    }
}
