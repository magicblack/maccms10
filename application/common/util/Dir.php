<?php
namespace app\common\util;
class Dir {

    private $_values = array();
    public $error = "";

    /**
     * 架构函数
     * @param string $path  目录路径
     */
    public function __construct($path = '', $pattern = '*') {
        if (!$path) return false;
        if (substr($path, -1) != "/") $path .= "/";
        $this->listFile($path, $pattern);
    }


    /**
     * 生成目录
     * @param  string  $path 目录
     * @param  integer $mode 权限
     * @return boolean
     */
    public static function create($path, $mode = 0755) {
      if(is_dir($path)) return TRUE;
      $path = str_replace("\\", "/", $path);
      if(substr($path, -1) != '/') $path = $path.'/';
      $temp = explode('/', $path);
      $cur_dir = '';
      $max = count($temp) - 1;
      for($i=0; $i<$max; $i++) {
        $cur_dir .= $temp[$i].'/';
        if (@is_dir($cur_dir)) continue;
        @mkdir($cur_dir, $mode, true);
        @chmod($cur_dir, $mode);
      }
      return is_dir($path);
    }

    /**
     * 取得目录下面的文件信息
     * @param mixed $pathname 路径
     */
    public static function listFile($pathname, $pattern = '*') {
        static $_listDirs = array();
        $guid = md5($pathname . $pattern);
        if (!isset($_listDirs[$guid])) {
            $dir = array();
            $list = glob($pathname . $pattern);
            foreach ($list as $i => $file) {
                //$dir[$i]['filename']    = basename($file);
                //basename取中文名出问题.改用此方法
                //编码转换.把中文的调整一下.
                $dir[$i]['filename'] = preg_replace('/^.+[\\\\\\/]/', '', $file);
                $dir[$i]['pathname'] = realpath($file);
                $dir[$i]['owner'] = fileowner($file);
                $dir[$i]['perms'] = fileperms($file);
                $dir[$i]['inode'] = fileinode($file);
                $dir[$i]['group'] = filegroup($file);
                $dir[$i]['path'] = dirname($file);
                $dir[$i]['atime'] = fileatime($file);
                $dir[$i]['ctime'] = filectime($file);
                $dir[$i]['size'] = filesize($file);
                $dir[$i]['type'] = filetype($file);
                $dir[$i]['ext'] = is_file($file) ? strtolower(substr(strrchr(basename($file), '.'), 1)) : '';
                $dir[$i]['mtime'] = filemtime($file);
                $dir[$i]['isDir'] = is_dir($file);
                $dir[$i]['isFile'] = is_file($file);
                $dir[$i]['isLink'] = is_link($file);
                //$dir[$i]['isExecutable']= function_exists('is_executable')?is_executable($file):'';
                $dir[$i]['isReadable'] = is_readable($file);
                $dir[$i]['isWritable'] = is_writable($file);
            }
            $cmp_func = create_function('$a,$b', '
            $k  =  "isDir";
            if($a[$k]  ==  $b[$k])  return  0;
            return  $a[$k]>$b[$k]?-1:1;
            ');
            // 对结果排序 保证目录在前面
            usort($dir, $cmp_func);
            $this->_values = $dir;
            $_listDirs[$guid] = $dir;
        } else {
            $this->_values = $_listDirs[$guid];
        }
    }

    /**
     * 返回数组中的当前元素（单元）
     * @return array
     */
    public static function current($arr) {
        if (!is_array($arr)) {
            return false;
        }
        return current($arr);
    }

    /**
     * 文件上次访问时间
     * @return integer
     */
    public static function getATime() {
        $current = $this->current($this->_values);
        return $current['atime'];
    }

    /**
     * 取得文件的 inode 修改时间
     * @return integer
     */
    public static function getCTime() {
        $current = $this->current($this->_values);
        return $current['ctime'];
    }

    /**
     * 遍历子目录文件信息
     * @return DirectoryIterator
     */
    public static function getChildren() {
        $current = $this->current($this->_values);
        if ($current['isDir']) {
            return new Dir($current['pathname']);
        }
        return false;
    }

    /**
     * 取得文件名
     * @return string
     */
    public static function getFilename() {
        $current = $this->current($this->_values);
        return $current['filename'];
    }

    /**
     * 取得文件的组
     * @return integer
     */
    public static function getGroup() {
        $current = $this->current($this->_values);
        return $current['group'];
    }

    /**
     * 取得文件的 inode
     * @return integer
     */
    public static function getInode() {
        $current = $this->current($this->_values);
        return $current['inode'];
    }

    /**
     * 取得文件的上次修改时间
     * @return integer
     */
    public static function getMTime() {
        $current = $this->current($this->_values);
        return $current['mtime'];
    }

    /**
     * 取得文件的所有者
     * @return string
     */
    function getOwner() {
        $current = $this->current($this->_values);
        return $current['owner'];
    }

    /**
     * 取得文件路径，不包括文件名
     * @return string
     */
    public static function getPath() {
        $current = $this->current($this->_values);
        return $current['path'];
    }

    /**
     * 取得文件的完整路径，包括文件名
     * @return string
     */
    public static function getPathname() {
        $current = $this->current($this->_values);
        return $current['pathname'];
    }

    /**
     * 取得文件的权限
     * @return integer
     */
    public static function getPerms() {
        $current = $this->current($this->_values);
        return $current['perms'];
    }

    /**
     * 取得文件的大小
     * @return integer
     */
    public static function getSize() {
        $current = $this->current($this->_values);
        return $current['size'];
    }

    /**
     * 取得文件类型
     * @return string
     */
    public static function getType() {
        $current = $this->current($this->_values);
        return $current['type'];
    }

    /**
     * 是否为目录
     * @return boolen
     */
    public static function isDir() {
        $current = $this->current($this->_values);
        return $current['isDir'];
    }

    /**
     * 是否为文件
     * @return boolen
     */
    public static function isFile() {
        $current = $this->current($this->_values);
        return $current['isFile'];
    }

    /**
     * 文件是否为一个符号连接
     * @return boolen
     */
    public static function isLink() {
        $current = $this->current($this->_values);
        return $current['isLink'];
    }

    /**
     * 文件是否可以执行
     * @return boolen
     */
    public static function isExecutable() {
        $current = $this->current($this->_values);
        return $current['isExecutable'];
    }

    /**
     * 文件是否可读
     * @return boolen
     */
    public static function isReadable() {
        $current = $this->current($this->_values);
        return $current['isReadable'];
    }

    /**
     * 获取foreach的遍历方式
     * @return string
     */
    public static function getIterator() {
        return new ArrayObject($this->_values);
    }

    // 返回目录的数组信息
    public static function toArray() {
        return $this->_values;
    }

    // 静态方法
    /**
     * 判断目录是否为空
     * @return void
     */
    public static function isEmpty($directory) {
        $handle = opendir($directory);
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
    }

    /**
     * 取得目录中的结构信息
     * @return void
     */
    public static function getList($directory) {
        $scandir = scandir($directory);
        $dir = [];
        foreach ($scandir as $k => $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            $dir[] = $v;
        }
        return $dir;
    }

    /**
     * 删除目录（包括下面的文件）
     * @return void
     */
    public static function delDir($directory, $subdir = true) {
        if (is_dir($directory) == false) {
            return false;
        }
        $handle = opendir($directory);
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
                is_dir("$directory/$file") ?
                                Dir::delDir("$directory/$file") :
                                @unlink("$directory/$file");
            }
        }
        if (readdir($handle) == false) {
            closedir($handle);
            rmdir($directory);
        }
    }

    /**
     * 删除目录下面的所有文件，但不删除目录
     * @return void
     */
    public static function del($directory) {
        if (is_dir($directory) == false) {
            return false;
        }
        $handle = opendir($directory);
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != ".." && is_file("$directory/$file")) {
                unlink("$directory/$file");
            }
        }
        closedir($handle);
    }

    /**
     * 复制目录
     * @return void
     */
    public static function copyDir($source, $destination) {
        if (is_dir($source) == false) {
            return false;
        }
        if (is_dir($destination) == false) {
            mkdir($destination, 0755);
        }
        $handle = opendir($source);
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                if (is_dir("$source/$file")) {
                    Dir::copyDir("$source/$file", "$destination/$file");
                } else {
                    copy("$source/$file", "$destination/$file");
                }
            }
        }
        closedir($handle);
    }

}

?>