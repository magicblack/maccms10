<?php

namespace app\common\model;

use think\Config as ThinkConfig;
use think\Model;
use think\Db;
use think\Cache;

class Base extends Model
{
    protected $tablePrefix;
    protected $primaryId;
    protected $readFromMaster;

    //自定义初始化
    protected function initialize()
    {
        //需要调用`Model`的`initialize`方法
        parent::initialize();
        // 自定义的初始化
        $this->tablePrefix = isset($this->tablePrefix) ? $this->tablePrefix : ThinkConfig::get('database.prefix');
        $this->primaryId = isset($this->primaryId) ? $this->primaryId : $this->name . '_id';
        $this->readFromMaster = isset($this->readFromMaster) ? $this->readFromMaster : false;
        // 表创建或修改
        if (method_exists($this, 'createTableIfNotExists')) {
            $this->createTableIfNotExists();
        }
    }

    public function getCountByCond($cond)
    {
        $query_object = $this;
        if ($this->readFromMaster === true) {
            $query_object = $query_object->master();
        }
        return (int)$query_object->where($cond)->count();
    }

    public function getListByCond($offset, $limit, $cond, $orderby = '', $fields = "*", $transform = false)
    {
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);

        if (empty($orderby)) {
            $orderby = $this->primaryId . " DESC";
        } else {
            if (strpos($orderby, $this->primaryId) === false) {
                $orderby .= ", " . $this->primaryId . " DESC";
            }
        }

        $query_object = $this;
        if ($this->readFromMaster === true) {
            $query_object = $query_object->master();
        }
        $list = $query_object->where($cond)->field($fields)->order($orderby)->limit($offset, $limit)->select();
        if (!$list) {
            return [];
        }
        $final = [];
        foreach ($list as $row) {
            $row_array = $row->getData();
            if ($transform !== false) {
                $row_array = $this->transformRow($row_array, $transform);
            }
            $final[] = $row_array;
        }
        return $final;
    }

    public function transformRow($row, $extends = []) {
        return $row;
    }

    /**
     * 作废本模型的列表缓存。
     *
     * 各模型的 listCacheData() 把列表结果缓存 cache_time（默认 1 小时），而写路径
     * 历来只用 Cache::rm() 清 <表名>_detail_* 详情缓存，列表缓存没人动 —— 全站只有
     * 后台首页「清空缓存」与采集流程会 Cache::clear()。于是后台改完数据，前台列表
     * 最多要等一小时才变：改推荐位首页幻灯片不换（站点问题清单 #4）、加了友链首页
     * 不显示（#57），演员/文章/专题/角色/漫画/网址/分类同理。
     *
     * 用标签而不是整站清空：改友链没有理由把影片列表缓存一起打掉。标签默认取
     * $this->name（不含前缀的表名），与 listCacheData() 里 Cache::tag() 的写入端成对。
     *
     * @param string $tag 缓存标签，默认取本模型表名
     * @return void
     */
    public function clearListCache($tag = '')
    {
        $tag = $tag !== '' ? $tag : (string) $this->name;
        if ($tag === '') {
            return;
        }
        Cache::clear($tag);
    }

    /**
     * 清 infoData() 的详情缓存。key 必须和 infoData() 里 Cache::get()/set() 用的
     * 格式完全一致（带 cache_flag 前缀、<表名>_id 和 <表名>_en 都拼在同一个 key 里），
     * 否则 rm() 清的是另一个字符串，等于没清——编辑保存后前台仍命中旧缓存
     * （例：后台给小说换了封面，个人中心收藏列表走 infoData(...,1) 一直显示旧的空图）。
     * 详情页只会用 id 或 en 单边查（哪边留空取决于伪静态设置），
     * 所以两种组合都要清，救的是「保存前用另一种方式被缓存过」的情况。
     *
     * @param int|string $id
     * @param string     $en
     * @return void
     */
    protected function clearDetailCache($id, $en)
    {
        $flag = $GLOBALS['config']['app']['cache_flag'];
        $prefix = $flag . '_' . strtolower($this->name) . '_detail_';
        Cache::rm($prefix . $id . '_' . $en);
        Cache::rm($prefix . $id . '_');
        Cache::rm($prefix . '_' . $en);
    }

    /**
     * 列表/详情缓存里冗余存了一份 type/type_1（listData()/infoData() 建缓存时从分类缓存拷过来），
     * 后台改分类名只作废 type 标签，这份副本要等内容缓存过期才变：导航已是新名、
     * 卡片/详情还是旧名。缓存键也不含内容语言，副本还会被先渲染的语言“冻住”。
     * 所以命中缓存后按当前分类缓存（已随 setCache() 刷新、且按内容语言取 overlay 变体）重新挂一次，
     * 只是数组查找，无额外查询。
     *
     * @param array      $row      含 type_id 的内容行
     * @param array|null $typeList 列表场景由调用方传入，避免逐行读缓存
     * @return array
     */
    protected function refreshTypeRelation($row, $typeList = null)
    {
        if (!is_array($row) || empty($row['type_id'])) {
            return $row;
        }
        if ($typeList === null) {
            $typeList = model('Type')->getCache('type_list');
        }
        if (!is_array($typeList) || empty($typeList[$row['type_id']])) {
            return $row;
        }
        $row['type'] = $typeList[$row['type_id']];
        $pid = isset($row['type']['type_pid']) ? $row['type']['type_pid'] : 0;
        $row['type_1'] = isset($typeList[$pid]) ? $typeList[$pid] : null;
        return $row;
    }

    /**
     * refreshTypeRelation() 的列表版，作用于 listCacheData() 的返回结构。
     *
     * @param array $res ['list'=>[...]]
     * @return array
     */
    protected function refreshListTypeRelation($res)
    {
        if (empty($res['list']) || !is_array($res['list'])) {
            return $res;
        }
        $typeList = model('Type')->getCache('type_list');
        foreach ($res['list'] as $k => $row) {
            $res['list'][$k] = $this->refreshTypeRelation($row, $typeList);
        }
        return $res;
    }

    /**
     * 原子 upsert：按唯一键 INSERT ... ON DUPLICATE KEY UPDATE，一条 SQL 完成插入或更新，
     * 取代「先 find 再 insert/update」的两次查询，并规避并发首插撞唯一键。
     * 列名取自入参数组，占位符参数化，无 SQL 注入面。
     * @param array $row        插入并在冲突时更新的列
     * @param array $insertOnly 仅插入时写入、冲突时保留的列（如 time_add）
     * @return int 影响行数（新插入=1，更新=2，无变化=0）
     */
    protected function upsertByUnique(array $row, array $insertOnly = [])
    {
        $all = array_merge($row, $insertOnly);
        $cols = array_keys($all);
        $table = $this->tablePrefix . $this->name;
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colSql = '`' . implode('`,`', $cols) . '`';
        $updates = [];
        foreach (array_keys($row) as $c) {
            $updates[] = "`{$c}`=VALUES(`{$c}`)";
        }
        $sql = "INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders}) "
             . "ON DUPLICATE KEY UPDATE " . implode(',', $updates);
        return Db::execute($sql, array_values($all));
    }
}