<?php
namespace app\common\model;

use think\Db;

class AdminAuditLog extends Base
{
    protected $name = 'admin_audit_log';

    protected $createTime = '';
    protected $updateTime = '';

    /**
     * @param array<string,mixed> $row
     */
    public static function insertRow(array $row)
    {
        $row['audit_time'] = isset($row['audit_time']) ? (int)$row['audit_time'] : time();
        try {
            Db::name('AdminAuditLog')->strict(false)->insert($row);
        } catch (\Throwable $e) {
            // 表未创建或 DB 异常时不影响主请求
        }
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start > 0 ? (int)$start : 0;
        if (!is_array($where)) {
            $where = [];
        }
        $total = Db::name('AdminAuditLog')->where($where)->count();
        $list = Db::name('AdminAuditLog')->where($where)->order($order)->page($page)->limit($limit)->select();

        return [
            'code'      => 1,
            'msg'       => lang('data_list'),
            'page'      => $page,
            'pagecount' => $limit > 0 ? (int)ceil($total / $limit) : 0,
            'limit'     => $limit,
            'total'     => $total,
            'list'      => is_array($list) ? $list : [],
        ];
    }
}
