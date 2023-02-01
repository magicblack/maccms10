<?php
/*
'软件名称：苹果CMS 源码库：https://github.com/magicblack
'--------------------------------------------------------
'Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
'遵循Apache2开源协议发布，并提供免费使用。
'--------------------------------------------------------
*/
header('Content-Type:text/html;charset=utf-8');
// 检测PHP环境
if(version_compare(PHP_VERSION,'5.5.0','<'))  die('PHP版本需要>=5.5，请升级【PHP version requires > = 5.5，please upgrade】');
//超时时间
@ini_set('max_execution_time', '0');
//内存限制 取消内存限制
@ini_set("memory_limit",'-1');
// 定义应用目录
define('ROOT_PATH', __DIR__ . '/');
define('APP_PATH', __DIR__ . '/application/');
define('MAC_COMM', __DIR__.'/application/common/common/');
define('MAC_HOME_COMM', __DIR__.'/application/index/common/');
define('MAC_ADMIN_COMM', __DIR__.'/application/admin/common/');
define('MAC_START_TIME', microtime(true) );
define('BIND_MODULE','api');
define('ENTRANCE', 'api');
$in_file = rtrim($_SERVER['SCRIPT_NAME'],'/');
if(substr($in_file,strlen($in_file)-4)!=='.php'){
    $in_file = substr($in_file,0,strpos($in_file,'.php')) .'.php';
}
define('IN_FILE',$in_file);

if(!is_file('./application/data/install/install.lock')) {
    header("Location: ./install.php");
    exit;
}
if (!@mb_check_encoding($_SERVER['PATH_INFO'], 'utf-8')){
    $_SERVER['PATH_INFO']=@mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', 'GBK');
}
// 加载框架引导文件
require __DIR__ . '/thinkphp/start.php';


