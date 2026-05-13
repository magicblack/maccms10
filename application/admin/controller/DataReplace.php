<?php
namespace app\admin\controller;
use think\Db;

/**
 * 数据替换控制器
 * 提供批量替换数据库中的字段内容功能
 */
class DataReplace extends Base
{
    /**
     * 允许操作的数据表白名单
     */
    private static $allowed_tables = ['vod', 'art', 'manga'];

    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function index()
    {
        $this->assign('title', lang('admin/datareplace/title'));
        return $this->fetch('datareplace/index');
    }

    /**
     * 执行替换
     */
    public function doReplace()
    {
        $param = input('post.');
        $table = trim($param['table'] ?? '');
        $field = trim($param['field'] ?? '');
        $search = $param['search'] ?? '';
        $replace = $param['replace'] ?? '';

        if (empty($table) || empty($field) || $search === '') {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 安全检查：只允许替换特定表
        if (!in_array($table, self::$allowed_tables, true)) {
            return json(['code' => 0, 'msg' => lang('admin/datareplace/table_not_allowed')]);
        }

        // 安全检查：字段名只允许字母数字下划线
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 验证字段是否存在于表中
        $prefix = config('database.prefix');
        try {
            $columns = Db::query("SHOW COLUMNS FROM `{$prefix}{$table}`");
            $validFields = array_column($columns, 'Field');
            if (!in_array($field, $validFields)) {
                return json(['code' => 0, 'msg' => lang('param_err')]);
            }
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => lang('admin/datareplace/replace_err')]);
        }

        try {
            // 使用参数化查询计数
            $count = Db::name($table)
                ->where($field, 'like', '%' . $search . '%')
                ->count();

            if ($count == 0) {
                return json(['code' => 0, 'msg' => lang('admin/datareplace/no_match')]);
            }

            // 使用参数化查询执行替换（防止 SQL 注入）
            Db::execute(
                "UPDATE `{$prefix}{$table}` SET `{$field}` = REPLACE(`{$field}`, ?, ?) WHERE `{$field}` LIKE ?",
                [$search, $replace, '%' . $search . '%']
            );

            return json(['code' => 1, 'msg' => sprintf(lang('admin/datareplace/replace_ok'), $count)]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => lang('admin/datareplace/replace_err') . ': ' . $e->getMessage()]);
        }
    }

    /**
     * 获取表的字段列表
     */
    public function getFields()
    {
        $table = input('table');
        if (empty($table)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 安全检查：只允许查询特定表
        if (!in_array($table, self::$allowed_tables, true)) {
            return json(['code' => 0, 'msg' => lang('admin/datareplace/table_not_allowed')]);
        }

        $prefix = config('database.prefix');
        try {
            $fields = Db::query("SHOW COLUMNS FROM `{$prefix}{$table}`");
            $result = [];
            foreach ($fields as $f) {
                $result[] = [
                    'field' => $f['Field'],
                    'type' => $f['Type'],
                ];
            }
            return json(['code' => 1, 'data' => $result]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
