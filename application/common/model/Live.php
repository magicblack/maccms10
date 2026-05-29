<?php
namespace app\common\model;

use think\Db;
use think\Cache;

class Live extends Base
{
    protected $name = 'live';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto   = [];
    protected $insert = [];
    protected $update = [];

    public function countData($where)
    {
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        return $this->where($where)->count();
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0, $field = '*')
    {
        $page  = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list  = Db::name('Live')->field($field)->where($where)->order($order)->limit($limit_str)->select();

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
        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function saveData($data)
    {
        if (empty($data['live_name'])) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $data['live_time'] = time();
        if (!empty($data['live_id'])) {
            $where = [];
            $where['live_id'] = ['eq', (int)$data['live_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        } else {
            $data['live_time_add'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if (false === $res) {
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
        $res = $this->allowField(true)->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('set_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 解析播放地址：格式 名称1$地址1#名称2$地址2
     */
    public function parseUrlList($str)
    {
        $list = [];
        if (empty($str)) {
            return $list;
        }
        $parts = explode('#', $str);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $arr = explode('$', $p, 2);
            if (count($arr) === 1) {
                $list[] = ['name' => 'HD', 'url' => $arr[0]];
            } else {
                $list[] = ['name' => $arr[0] !== '' ? $arr[0] : 'HD', 'url' => $arr[1]];
            }
        }
        return $list;
    }

    // ==================== 分类 CRUD ====================

    public function categoryList($where = [], $order = 'cate_sort asc, cate_id asc')
    {
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        return Db::name('live_category')->where($where)->order($order)->select();
    }

    public function categoryInfo($where)
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $info = Db::name('live_category')->where($where)->find();
        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function categorySave($data)
    {
        if (empty($data['cate_name'])) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $data['cate_time'] = time();
        if (!empty($data['cate_id'])) {
            $res = Db::name('live_category')->where('cate_id', (int)$data['cate_id'])->update($data);
        } else {
            $data['cate_time_add'] = time();
            $res = Db::name('live_category')->insert($data);
        }
        if ($res === false) {
            return ['code' => 1002, 'msg' => lang('save_err')];
        }
        return ['code' => 1, 'msg' => lang('save_ok')];
    }

    public function categoryDel($ids)
    {
        if (empty($ids)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $ids = array_map('intval', $ids);
        Db::name('live_category')->where('cate_id', 'in', $ids)->delete();
        Db::name('live')->where('cate_id', 'in', $ids)->update(['cate_id' => 0]);
        return ['code' => 1, 'msg' => lang('del_ok')];
    }
}
