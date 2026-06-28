<?php
namespace app\common\model;

class MallGoods extends Base
{
    protected $name = 'mall_goods';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = $this->where($where)->order($order)->limit($limit_str)->select();
        foreach ($list as &$row) {
            $row['mall_goods_ext_arr'] = $this->decodeExt($row['mall_goods_ext'] ?? '');
        }

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    public function infoData($where, $field = '*')
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $info = $info->toArray();
        $info['mall_goods_ext_arr'] = $this->decodeExt($info['mall_goods_ext'] ?? '');

        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function saveData($data)
    {
        $data = $this->normalizeData($data);
        $validate = \think\Loader::validate('MallGoods');
        if (!$validate->check($data)) {
            return ['code' => 1001, 'msg' => lang('param_err') . '：' . $validate->getError()];
        }
        $check = $this->validateExt($data);
        if ($check['code'] > 1) {
            return $check;
        }

        $data['mall_goods_time'] = time();
        if (!empty($data['mall_goods_id'])) {
            $where = [];
            $where['mall_goods_id'] = ['eq', $data['mall_goods_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        } else {
            $data['mall_goods_time_add'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if ($res === false) {
            return ['code' => 1002, 'msg' => lang('save_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('save_ok')];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('del_ok')];
    }

    public function fieldData($where, $col, $val)
    {
        if (!isset($col) || !isset($val)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $data = [];
        $data[$col] = $val;
        $data['mall_goods_time'] = time();
        $res = $this->allowField(true)->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('set_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    public function decodeExt($ext)
    {
        $arr = json_decode((string)$ext, true);
        return is_array($arr) ? $arr : [];
    }

    private function normalizeData($data)
    {
        $data['mall_goods_name'] = htmlspecialchars(urldecode(trim($data['mall_goods_name'] ?? '')));
        $data['mall_goods_type'] = trim($data['mall_goods_type'] ?? '');
        $data['mall_goods_points'] = intval($data['mall_goods_points'] ?? 0);
        $data['mall_goods_stock'] = intval($data['mall_goods_stock'] ?? 0);
        $data['mall_goods_status'] = intval($data['mall_goods_status'] ?? 0) === 1 ? 1 : 0;
        $data['mall_goods_sort'] = intval($data['mall_goods_sort'] ?? 0);

        $ext = [];
        if ($data['mall_goods_type'] === 'vip') {
            $ext['group_id'] = intval($data['vip_group_id'] ?? ($data['group_id'] ?? 0));
            $ext['days'] = intval($data['vip_days'] ?? ($data['days'] ?? 0));
        } elseif ($data['mall_goods_type'] === 'card') {
            $ext['card_mode'] = in_array(($data['card_mode'] ?? 'generate'), ['generate', 'assign'], true) ? $data['card_mode'] : 'generate';
            $ext['card_money'] = intval($data['card_money'] ?? 0);
            $ext['card_points'] = intval($data['card_points'] ?? 0);
            $ext['role_no'] = in_array(($data['role_no'] ?? ''), ['letter', 'num'], true) ? $data['role_no'] : '';
            $ext['role_pwd'] = in_array(($data['role_pwd'] ?? ''), ['letter', 'num'], true) ? $data['role_pwd'] : '';
        } elseif ($data['mall_goods_type'] === 'download_quota') {
            $ext['quota'] = intval($data['download_quota'] ?? ($data['quota'] ?? 0));
        }
        $data['mall_goods_ext'] = json_encode($ext, JSON_UNESCAPED_UNICODE);

        return $data;
    }

    private function validateExt($data)
    {
        if (!in_array($data['mall_goods_type'], ['vip', 'card', 'download_quota'], true)) {
            return ['code' => 1002, 'msg' => lang('mall/type_invalid')];
        }
        $ext = $this->decodeExt($data['mall_goods_ext'] ?? '');
        if ($data['mall_goods_type'] === 'vip') {
            $group_id = intval($ext['group_id'] ?? 0);
            $days = intval($ext['days'] ?? 0);
            $group_list = (new Group())->getCache();
            if ($group_id < 3 || !isset($group_list[$group_id]) || intval($group_list[$group_id]['group_status']) !== 1 || $days < 1) {
                return ['code' => 1003, 'msg' => lang('mall/vip_config_invalid')];
            }
        } elseif ($data['mall_goods_type'] === 'card') {
            if (intval($ext['card_points'] ?? 0) < 1 || intval($ext['card_money'] ?? 0) < 0) {
                return ['code' => 1004, 'msg' => lang('mall/card_config_invalid')];
            }
        } elseif ($data['mall_goods_type'] === 'download_quota') {
            if (intval($ext['quota'] ?? 0) < 1) {
                return ['code' => 1005, 'msg' => lang('mall/download_quota_invalid')];
            }
        }
        return ['code' => 1, 'msg' => 'ok'];
    }
}
