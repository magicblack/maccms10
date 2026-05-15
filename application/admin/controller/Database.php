<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Dir;
use app\common\util\Database as dbOper;

class Database extends Base
{
    var $_db_config;
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $group = input('group');
        if($group=='import'){
            //列出备份文件列表
            $path = trim( $GLOBALS['config']['db']['backup_path'], '/').DS;
            if (!is_dir($path)) {
                Dir::create($path);
            }
            $flag = \FilesystemIterator::KEY_AS_FILENAME;
            $glob = new \FilesystemIterator($path,  $flag);

            $list = [];
            foreach ($glob as $name => $file) {
                if(preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql(?:\.gz)?$/', $name)){
                    $name = sscanf($name, '%4s%2s%2s-%2s%2s%2s-%d');
                    $date = "{$name[0]}-{$name[1]}-{$name[2]}";
                    $time = "{$name[3]}:{$name[4]}:{$name[5]}";
                    $part = $name[6];

                    if(isset($list["{$date} {$time}"])){
                        $info = $list["{$date} {$time}"];
                        $info['part'] = max($info['part'], $part);
                        $info['size'] = $info['size'] + $file->getSize();
                    } else {
                        $info['part'] = $part;
                        $info['size'] = $file->getSize();
                    }

                    $extension        = strtoupper($file->getExtension());
                    $info['compress'] = ($extension === 'SQL') ? '无' : $extension;
                    $info['time']     = strtotime("{$date} {$time}");

                    $list["{$date} {$time}"] = $info;
                }
            }
        }
        else{
            $group='export';
            $list = Db::query("SHOW TABLE STATUS");
        }

        $this->assign('list',$list);
        $this->assign('title',lang('admin/database/title'));
        return $this->fetch('admin@database/'.$group);
    }

    public function export($ids = '', $start = 0)
    {
        if ($this->request->isPost()) {
            if (empty($ids)) {
                return $this->error(lang('admin/database/select_export_table'));
            }

            if (!is_array($ids)) {
                $tables[] = $ids;
            } else {
                $tables = $ids;
            }
            $have_admin = false;
            $admin_table='';
            foreach($tables as $k=>$v){
                if(strpos($v,'_admin')!==false){
                    $have_admin=true;
                    $admin_table = $v;
                    unset($tables[$k]);
                }
            }
            if($have_admin){
                $tables[] = $admin_table;
            }

            //读取备份配置
            $config = array(
                'path'     => $GLOBALS['config']['db']['backup_path'] .DS,
                'part'     => $GLOBALS['config']['db']['part_size'] ,
                'compress' => $GLOBALS['config']['db']['compress'] ,
                'level'    => $GLOBALS['config']['db']['compress_level'] ,
            );

            //检查是否有正在执行的任务
            $lock = "{$config['path']}backup.lock";
            if(is_file($lock)){
                return $this->error(lang('admin/database/lock_check'));
            } else {
                if (!is_dir($config['path'])) {
                    Dir::create($config['path'], 0755, true);
                }
                //创建锁文件
                file_put_contents($lock, $this->request->time());
            }

            //生成备份文件信息
            $file = [
                'name' => date('Ymd-His', $this->request->time()),
                'part' => 1,
            ];

            // 创建备份文件
            $database = new dbOper($file, $config);
            if($database->create() !== false) {
                // 备份指定表
                foreach ($tables as $table) {
                    $start = $database->backup($table, $start);
                    while (0 !== $start) {
                        if (false === $start) {
                            return $this->error(lang('admin/database/backup_err'));
                        }
                        $start = $database->backup($table, $start[0]);
                    }
                }
                // 备份完成，删除锁定文件
                unlink($lock);
            }
            return $this->success(lang('admin/database/backup_ok'));
        }
        return $this->error(lang('admin/database/backup_err'));
    }

    /**
     * 恢复数据库 [参考原作者 麦当苗儿 <zuojiazi@vip.qq.com>]
     * @param string|array $ids 表名
     * @param integer $start 起始行数
     * @author 橘子俊 <364666827@qq.com>
     * @return mixed
     */
    public function import($id = '')
    {
        if (empty($id)) {
            return $this->error(lang('admin/database/select_file'));
        }

        $name  = date('Ymd-His', $id) . '-*.sql*';
        $path  = trim( $GLOBALS['config']['db']['backup_path'] , '/').DS.$name;
        $files = glob($path);
        $list  = array();
        foreach($files as $name){
            $basename = basename($name);
            $match    = sscanf($basename, '%4s%2s%2s-%2s%2s%2s-%d');
            $gz       = preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql.gz$/', $basename);
            $list[$match[6]] = array($match[6], $name, $gz);
        }
        ksort($list);

        // 检测文件正确性
        $last = end($list);
        if(count($list) === $last[0]){
            foreach ($list as $item) {
                $config = [
                    'path'     => trim($GLOBALS['config']['db']['backup_path'], '/').DS,
                    'compress' => $item[2]
                ];
                $database = new dbOper($item, $config);
                $start = $database->import(0);
                // 导入所有数据
                while (0 !== $start) {
                    if (false === $start) {
                        return $this->error(lang('admin/database/import_err'));
                    }
                    $start = $database->import($start[0]);
                }
            }
            return $this->success(lang('admin/database/import_ok'));
        }
        return $this->error(lang('admin/database/file_damage'));
    }

    public function optimize($ids = '')
    {
        if (empty($ids)) {
            return $this->error(lang('admin/database/select_optimize_table'));
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        foreach ($table as $t) {
            if (!$this->isValidTable($t)) {
                return $this->error('Table is invalid.');
            }
        }

        $tables = implode('`,`', $table);
        $res = Db::query("OPTIMIZE TABLE `{$tables}`");
        if ($res) {
            return $this->success(lang('admin/database/optimize_ok'));
        }
        return $this->error(lang('admin/database/optimize_err'));
    }

    public function repair($ids = '')
    {
        if (empty($ids)) {
            return $this->error(lang('admin/database/select_repair_table'));
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        foreach ($table as $t) {
            if (!$this->isValidTable($t)) {
                return $this->error('Table is invalid.');
            }
        }

        $tables = implode('`,`', $table);
        $res = Db::query("REPAIR TABLE `{$tables}`");
        if ($res) {
            return $this->success(lang('admin/database/repair_ok'));
        }
        return $this->error(lang('admin/database/repair_ok'));
    }

    public function del($id = '')
    {
        if (empty($id)) {
            return $this->error(lang('admin/database/select_del_file'));
        }

        $name  = date('Ymd-His', $id) . '-*.sql*';
        $path = trim($GLOBALS['config']['db']['backup_path']).DS.$name;
        array_map("unlink", glob($path));
        if(count(glob($path)) && glob($path)){
            return $this->error(lang('del_err'));
        }
        return $this->success(lang('del_ok'));
    }

    public function sql()
    {
        if($this->request->isPost()){
            $param=input();
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }

            $sql = trim($param['sql']);

            if(!empty($sql)){
                $forbidden_keywords = ['into dumpfile', 'into outfile', 'char(', 'load_file'];
                foreach ($forbidden_keywords as $keyword) {
                    if (stripos($sql, $keyword) !== false) {
                        return $this->error(lang('format_err'));
                    }
                }
                $sql = str_replace('{pre}',config('database.prefix'),$sql);
                //查询语句返回结果集
                if(
                    strtolower(substr($sql,0,6))=="select" || 
                    stripos($sql, ' outfile') !== false
                ){

                }
                else{
                    Db::execute($sql);
                }
            }
            $this->success(lang('run_ok'));
        }
        return $this->fetch('admin@database/sql');
    }

    public function columns()
    {
        $param = input();
        $table = $param['table'];
        if (!empty($table) && !$this->isValidTable($table)) {
            return $this->error('Table is invalid.');
        }
        if (!empty($table)) {
            $list = Db::query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $this->success(lang('obtain_ok'),null, $list);
        }
        $this->error(lang('param_err'));
    }

    public function rep()
    {
        if($this->request->isPost()){
            $param = input();
            $table = isset($param['table']) ? $param['table'] : '';
            $field = isset($param['field']) ? $param['field'] : '';
            $findstr = isset($param['findstr']) ? $param['findstr'] : '';
            $tostr = isset($param['tostr']) ? $param['tostr'] : '';
            $where = isset($param['where']) ? $param['where'] : '';

            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->error($validate->getError());
            }
            if ($table === '' || !$this->isValidTable($table)) {
                return $this->error('Table is invalid.');
            }
            if ($field === '' || $findstr === '' || $tostr === '') {
                return $this->error(lang('param_err'));
            }
            if (!$this->isValidField($table, $field)) {
                return $this->error('Column is invalid.');
            }
            $whereSql = $this->sanitizeRepWhereClause($where);
            if ($whereSql === false) {
                return $this->error('WHERE clause is invalid.');
            }
            $tq = '`' . str_replace('`', '``', $table) . '`';
            $fq = '`' . str_replace('`', '``', $field) . '`';
            $sql = 'UPDATE ' . $tq . ' SET ' . $fq . '=REPLACE(' . $fq . ', ?, ?) WHERE 1=1' . $whereSql;
            Db::execute($sql, [$findstr, $tostr]);
            return $this->success(lang('run_ok'));
        }
        $list = Db::query("SHOW TABLE STATUS");
        $this->assign('list',$list);
        return $this->fetch('admin@database/rep');
    }

    private function isValidTable($table) {
        $list = Db::query("SHOW TABLE STATUS");
        foreach ($list as $table_raw) {
            if ($table_raw['Name'] == $table) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $table 已通过 isValidTable 校验的表名
     */
    private function isValidField($table, $field)
    {
        if (!is_string($field) || !preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            return false;
        }
        $list = Db::query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if (!is_array($list)) {
            return false;
        }
        foreach ($list as $row) {
            if (!empty($row['Field']) && $row['Field'] === $field) {
                return true;
            }
        }
        return false;
    }

    /**
     * 附加 WHERE 仅允许 AND 开头的简单片段；无法安全绑定的表达式一律拒绝。
     *
     * @param string $where
     * @return string|false 返回可拼接到 SQL 的片段（含前导空格），或 false
     */
    private function sanitizeRepWhereClause($where)
    {
        $where = trim((string)$where);
        if ($where === '') {
            return '';
        }
        if (strlen($where) > 500) {
            return false;
        }
        $norm = preg_replace('/\s+/', ' ', strtolower($where));
        $blocked = [
            ';', '--', '/*', '*/', ' union ', ' select ', ' insert ', ' update ', ' delete ',
            ' drop ', ' create ', ' alter ', ' grant ', ' revoke ', ' exec ', ' execute ',
            'sleep(', 'benchmark(', 'load_file', 'outfile', 'dumpfile', ' information_schema',
            ' xor ', ' or 1', ' or true',
        ];
        foreach ($blocked as $b) {
            if (strpos($norm, $b) !== false) {
                return false;
            }
        }
        if (strncmp($norm, 'and ', 4) !== 0) {
            return false;
        }

        return ' ' . $where;
    }
}
