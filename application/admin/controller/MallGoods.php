<?php
namespace app\admin\controller;

class MallGoods extends Base
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
        if (in_array($param['status'], ['0', '1'], true)) {
            $where['mall_goods_status'] = ['eq', $param['status']];
        }
        if (in_array($param['type'], ['vip', 'card', 'download_quota'], true)) {
            $where['mall_goods_type'] = ['eq', $param['type']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['mall_goods_name'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'mall_goods_sort asc,mall_goods_id desc';
        $res = model('MallGoods')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('mall/admin_title'));
        return $this->fetch('admin@mall_goods/index');
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = input('post.');
            $res = model('MallGoods')->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $param = input();
        $info = [
            'mall_goods_id' => 0,
            'mall_goods_name' => '',
            'mall_goods_type' => 'vip',
            'mall_goods_points' => 1,
            'mall_goods_stock' => 1,
            'mall_goods_status' => 1,
            'mall_goods_sort' => 0,
            'mall_goods_ext_arr' => [],
        ];
        if (!empty($param['id'])) {
            $res = model('MallGoods')->infoData(['mall_goods_id' => intval($param['id'])]);
            if ($res['code'] == 1) {
                $info = array_merge($info, $res['info']);
            }
        }
        $info['mall_goods_ext_arr'] = array_merge([
            'group_id' => 0,
            'days' => 30,
            'card_mode' => 'generate',
            'card_money' => 0,
            'card_points' => 1,
            'role_no' => '',
            'role_pwd' => '',
            'quota' => 1,
        ], $info['mall_goods_ext_arr']);

        $this->assign('info', $info);
        $this->assign('group_list', model('Group')->getCache());
        $this->assign('title', lang('mall/admin_title'));
        return $this->fetch('admin@mall_goods/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        if (!empty($ids)) {
            $where = [];
            $where['mall_goods_id'] = ['in', $ids];
            $res = model('MallGoods')->delData($where);
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
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];
        if (!empty($ids) && in_array($col, ['mall_goods_status'], true) && in_array($val, ['0', '1'], true)) {
            $where = [];
            $where['mall_goods_id'] = ['in', $ids];
            $res = model('MallGoods')->fieldData($where, $col, $val);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }
}
