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
        $this->assign('title','数据库管理');
        return $this->fetch('admin@database/'.$group);
    }

    public function export($ids = '', $start = 0)
    {
        if ($this->request->isPost()) {
            if (empty($ids)) {
                return $this->error('请选择您要备份的数据表！');
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
                return $this->error('检测到有一个备份任务正在执行，请稍后再试！');
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
                            return $this->error('备份出错！');
                        }
                        $start = $database->backup($table, $start[0]);
                    }
                }
                // 备份完成，删除锁定文件
                unlink($lock);
            }
            return $this->success('备份完成。');
        }
        return $this->error('备份出错！');
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
            return $this->error('请选择您要恢复的备份文件！');
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
                        return $this->error('数据恢复出错！');
                    }
                    $start = $database->import($start[0]);
                }
            }
            return $this->success('数据恢复完成。');
        }
        return $this->error('备份文件可能已经损坏，请检查！');
    }

    public function optimize($ids = '')
    {
        if (empty($ids)) {
            return $this->error('请选择您要优化的数据表！');
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        $tables = implode('`,`', $table);
        $res = Db::query("OPTIMIZE TABLE `{$tables}`");
        if ($res) {
            return $this->success('数据表优化完成。');
        }
        return $this->error('数据表优化失败！');
    }

    public function repair($ids = '')
    {
        if (empty($ids)) {
            return $this->error('请选择您要修复的数据表！');
        }

        if (!is_array($ids)) {
            $table[] = $ids;
        } else {
            $table = $ids;
        }

        $tables = implode('`,`', $table);
        $res = Db::query("REPAIR TABLE `{$tables}`");
        if ($res) {
            return $this->success('数据表修复完成。');
        }
        return $this->error('数据表修复失败！');
    }


    public function del($id = '')
    {
        if (empty($id)) {
            return $this->error('请选择您要删除的备份文件！');
        }

        $name  = date('Ymd-His', $id) . '-*.sql*';
        $path = trim($GLOBALS['config']['db']['backup_path']).DS.$name;
        array_map("unlink", glob($path));
        if(count(glob($path)) && glob($path)){
            return $this->error('备份文件删除失败，请检查权限！');
        }
        return $this->success('备份文件删除成功。');
    }

    public function sql()
    {
        if($this->request->isPost()){
            $param=input();
            $sql = trim($param['sql']);

            if(!empty($sql)){
                $sql = str_replace('{pre}',config('database.prefix'),$sql);
                //查询语句返回结果集
                if(strtolower(substr($sql,0,6))=="select"){

                }
                else{
                    Db::execute($sql);
                }
            }
            $this->success('执行成功');
        }
        return $this->fetch('admin@database/sql');
    }

    public function columns()
    {
        $param = input();
        $table = $param['table'];
        if(!empty($table)){
            $list = Db::query('SHOW COLUMNS FROM '.$table);
            $this->success('获取成功',null, $list);
        }
        $this->error('参数错误');
    }


    public function rep()
    {

        if($this->request->isPost()){

            $param = input();
            $table = $param['table'];
            $field = $param['field'];
            $findstr = $param['findstr'];
            $tostr = $param['tostr'];
            $where = $param['where'];

            if(!empty($table) && !empty($field) && !empty($findstr) && !empty($tostr)){
                $sql = "UPDATE ".$table." set ".$field."=Replace(".$field.",'".$findstr."','".$tostr."') where 1=1 ". $where;
                Db::execute($sql);
                return $this->success('执行成功');
            }

            return $this->error('参数错误');
        }
        $list = Db::query("SHOW TABLE STATUS");
        $this->assign('list',$list);
        return $this->fetch('admin@database/rep');
    }

}
