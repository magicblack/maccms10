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
}