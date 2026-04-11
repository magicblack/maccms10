<?php
namespace app\common\model;

use think\Db;

/**
 * 软删除回收站：{prefix}_recycle_time 为 0 表示正常，>0 为移入回收站的时间戳
 * where 中可使用 _recycle：active（默认）、recycle、all
 */
trait RecycleBinTrait
{
    /** @return string 如 vod_recycle_time；返回空字符串表示不使用回收站 */
    abstract protected function getRecycleTimeField(): string;

    protected function ensureRecycleColumnExists()
    {
        $f = $this->getRecycleTimeField();
        if ($f === '') {
            return;
        }
        static $done = [];
        $table = $this->getTable();
        if (isset($done[$table])) {
            return;
        }
        try {
            $rows = Db::query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE \'' . str_replace('\'', '\\\'', $f) . '\'');
        } catch (\Exception $e) {
            $done[$table] = true;
            return;
        }
        if (!empty($rows)) {
            $done[$table] = true;
            return;
        }
        try {
            Db::execute('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $f) . '` int(10) unsigned NOT NULL DEFAULT \'0\'');
        } catch (\Exception $e) {
        }
        $done[$table] = true;
    }

    protected function mergeRecycleWhere(array $where)
    {
        $f = $this->getRecycleTimeField();
        if ($f === '') {
            return $where;
        }
        $this->ensureRecycleColumnExists();
        $mode = 'active';
        if (isset($where['_recycle'])) {
            $mode = (string)$where['_recycle'];
            unset($where['_recycle']);
        }
        if ($mode === 'all') {
            return $where;
        }
        if ($mode === 'recycle') {
            $where[$f] = ['>', 0];
            return $where;
        }
        if (!array_key_exists($f, $where)) {
            $where[$f] = 0;
        }
        return $where;
    }

    public function recycleData($where)
    {
        $f = $this->getRecycleTimeField();
        if ($f === '') {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $this->ensureRecycleColumnExists();
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        if (!is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $where = $this->mergeRecycleWhere($where);
        $cnt = (int)$this->where($where)->count();
        if ($cnt < 1) {
            return ['code' => 1002, 'msg' => lang('del_err')];
        }
        $t = time();
        $res = $this->where($where)->update([$f => $t]);
        if ($res === false) {
            return ['code' => 1002, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('recycle_ok')];
    }

    public function restoreData($where)
    {
        $f = $this->getRecycleTimeField();
        if ($f === '') {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $this->ensureRecycleColumnExists();
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        if (!is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $where['_recycle'] = 'recycle';
        $where = $this->mergeRecycleWhere($where);
        $cnt = (int)$this->where($where)->count();
        if ($cnt < 1) {
            return ['code' => 1002, 'msg' => lang('del_err')];
        }
        $res = $this->where($where)->update([$f => 0]);
        if ($res === false) {
            return ['code' => 1002, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('recycle_restore_ok')];
    }
}
