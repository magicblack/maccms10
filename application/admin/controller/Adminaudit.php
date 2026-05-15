<?php
namespace app\admin\controller;

use app\common\util\SensitiveDataCrypto;
use think\Db;
use think\Request;

class Adminaudit extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : intval($param['limit']);
        $where = [];

        if (!empty($param['admin_id'])) {
            $where['admin_id'] = (int)$param['admin_id'];
        }
        if (!empty($param['wd'])) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim((string)$param['wd'])) . '%';
            $where['audit_route|admin_name|audit_uri'] = ['like', $like];
        }
        if (!empty($param['method'])) {
            $where['audit_method'] = strtoupper(trim((string)$param['method']));
        }

        $order = 'audit_id desc';
        $res = model('AdminAuditLog')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('admin/adminaudit/title'));

        return $this->fetch('admin@adminaudit/index');
    }

    public function info(Request $request)
    {
        $id = (int)$request->param('id', 0);
        if ($id < 1) {
            return $this->error(lang('param_err'));
        }
        $row = Db::name('AdminAuditLog')->where('audit_id', $id)->find();
        if (empty($row)) {
            return $this->error(lang('obtain_err'));
        }
        if (isset($row['audit_payload'])) {
            $dec = SensitiveDataCrypto::decryptString($row['audit_payload']);
            $row['audit_payload'] = ($dec === false)
                ? (string)lang('admin/adminaudit/decrypt_failed')
                : $dec;
        }
        $this->assign('info', $row);
        $this->assign('title', lang('admin/adminaudit/detail'));

        return $this->fetch('admin@adminaudit/info');
    }
}
