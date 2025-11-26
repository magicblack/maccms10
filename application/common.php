<?php
/*
'软件名称：苹果CMS  源码库：https://github.com/magicblack
'--------------------------------------------------------
'Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
'遵循Apache2开源协议发布，并提供免费使用。
'--------------------------------------------------------
*/

use think\View;

error_reporting(E_ERROR | E_PARSE );



function get_array_unique_id_list($list, $need_sort = false) {
    $list = array_unique($list);
    $list = array_map('intval', $list);
    $list = array_filter($list);
    $list = array_values($list);
    $need_sort && sort($list);
    return $list;
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

//访问日志记录，根目录创建log目录
function slog($logs)
{
    $ymd = date('Y-m-d-H');
    $now = date('Y-m-d H:i:s');
    $toppath = "./log/$ymd.txt";
    $ts = @fopen($toppath,"a+");
    @fputs($ts, $now .' '. $logs ."\r\n");
    @fclose($ts);
}
//foreach($_GET as $k=>$v){ $getData .= $k.'='.$v.'&'; }
//foreach($_POST as $k=>$v){ $postData .= $k.'='.$v.'&'; }
//foreach($_COOKIE as $k=>$v){ $cookieData .= $k.'='.$v.'&'; }
//$log = $_SERVER['PHP_SELF'] . '---get:' .$getData .'---post:' . $postData .'---'. json_encode($_POST).'---cookie:' . $cookieData ;
//slog($log);

// 是否IP
function mac_string_is_ip($string) {
    return preg_match('/^(\d{1,3}\.){3}\d{1,3}(:\d{1,5})?$/', $string) === 1;
}

// 应用公共文件
function mac_return($msg,$code=1,$data=''){
    if(is_array($msg)){
        return json_encode($msg);
    }
    else {
        $rs = ['code' => $code, 'msg' => $msg, 'data'=>'' ];
        if(is_array($data)) $rs['data'] = $data;
        return json_encode($rs);
    }
}

function mac_run_statistics()
{
    $t2 = microtime(true) - MAC_START_TIME;
    $size = memory_get_usage();
    $memory = mac_format_size($size);
    unset($unit);
    return 'Processed in: '.round($t2,4).' second(s),&nbsp;' . $memory . ' Mem On.';
}

function mac_format_size($s=0)
{
    if($s==0){ return '0 kb'; }
    $unit=array('b','kb','mb','gb','tb','pb');
    return round($s/pow(1024,($i=floor(log($s,1024)))),2).' '.$unit[$i];
}

function mac_read_file($f)
{
    return @file_get_contents($f);
}

function mac_write_file($f,$c='')
{
    $dir = dirname($f);
    if(!is_dir($dir)){
        mac_mkdirss($dir);
    }
    return @file_put_contents($f, $c);
}

function mac_mkdirss($path,$mode=0777)
{
    if (!is_dir(dirname($path))){
        mac_mkdirss(dirname($path));
    }
    if(!file_exists($path)){
        return mkdir($path,$mode);
    }
    return true;
}

function mac_rmdirs($dirname, $withself = true)
{
    if (!is_dir($dirname))
        return false;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo)
    {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    if ($withself)
    {
        @rmdir($dirname);
    }
    return true;
}

function mac_arr2file($f,$arr='')
{
    if(is_array($arr)){
        $con = var_export($arr,true);
    } else{
        $con = $arr;
    }
    $con = "<?php\nreturn $con;";
    mac_write_file($f, $con);
    // opcache清理以实时生效配置
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($f, true);
    }
}

function mac_replace_text($txt,$type=1)
{
    if($type==1){
        return str_replace('#',Chr(13),$txt);
    }
    return str_replace(chr(13),'#',str_replace(chr(10),'',$txt));
}

function mac_compress_html($s){
    $s = str_replace(array("\r\n","\n","\t"), array('','','') , $s);
    $pattern = array (
        "/> *([^ ]*) *</",
        "/[\s]+/",
        "/<!--[\\w\\W\r\\n]*?-->/",
        // "/\" /",
        "/ \"/",
        "'/\*[^*]*\*/'"
    );
    $replace = array (
        ">\\1<",
        " ",
        "",
        //"\"",
        "\"",
        ""
    );
    return preg_replace($pattern, $replace, $s);
}

function mac_build_regx($regstr,$regopt)
{
    return '/'.str_replace([
        '/',
        '$',
        '+',
        '-',
        '{',
    ],[
        '\/',
        '\$',
        '\+',
        '\-',
        '\{',
    ],$regstr).'/'.$regopt;
}

function mac_reg_replace($str,$rule,$value)
{
    $res='';
    $rule = mac_build_regx($rule,"is");
    if (!empty($str)){
        $res = preg_replace($rule,$value,$str);
    }
    return $res;
}

function mac_reg_match($str,$rule)
{
    $res='';
    $rule = mac_build_regx($rule,"is");
    preg_match_all($rule,$str,$mc);
    $mfv=$mc[1];
    foreach($mfv as $f=>$v){
        $res = trim(preg_replace("/[ \r\n\t\f]{1,}/"," ",$v));
        break;
    }
    unset($mc);
    return $res;
}

function mac_redirect($url,$obj='')
{
    echo '<script>'.$obj.'location.href="' .$url .'";</script>';
    exit;
}

function mac_alert($str)
{
    echo '<script>alert("' .$str. '\t\t");history.go(-1);</script>';
}

function mac_alert_url($str,$url)
{
    echo '<script>alert("' .$str. '\t\t");location.href="' .$url .'";</script>';
}

function mac_jump($url,$sec=0)
{
    echo '<script>setTimeout(function (){location.href="'.$url.'";},'.($sec*1000).');</script><span>'.lang('pause').''.$sec.''.lang('continue_in_second').'  >>>  </span><a href="'.$url.'" >'.lang('browser_jump').'</a><br>';
}

function mac_echo($str)
{
    echo $str.'<br>';
    ob_flush();flush();
}

function mac_day($t,$f='',$c='#FF0000')
{
    if(empty($t)) { return ''; }
    if(is_numeric($t)){
        $t = date('Y-m-d H:i:s',$t);
    }
    $now = date('Y-m-d',time());
    if($f=='color' && strpos(','.$t,$now)>0){
        return '<font color="' .$c. '">' .$t. '</font>';
    }
    return  $t;
}

function mac_friend_date($time)
{
    if (!$time)
        return false;
    $fdate = '';
    $d = time() - intval($time);
    $ld = $time - mktime(0, 0, 0, 0, 0, date('Y')); //得出年
    $md = $time - mktime(0, 0, 0, date('m'), 0, date('Y')); //得出月
    $byd = $time - mktime(0, 0, 0, date('m'), date('d') - 2, date('Y')); //前天
    $yd = $time - mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')); //昨天
    $dd = $time - mktime(0, 0, 0, date('m'), date('d'), date('Y')); //今天
    $td = $time - mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')); //明天
    $atd = $time - mktime(0, 0, 0, date('m'), date('d') + 2, date('Y')); //后天
    if ($d == 0) {
        $fdate = lang('just');
    } else {
        switch ($d) {
            case $d < $atd:
                $fdate = date('Y'.lang('year').'m'.lang('month').'d'.lang('day'), $time);
                break;
            case $d < $td:
                $fdate = lang('day_after_tomorrow') . date('H:i', $time);
                break;
            case $d < 0:
                $fdate = lang('tomorrow') . date('H:i', $time);
                break;
            case $d < 60:
                $fdate = $d . lang('seconds_ago');
                break;
            case $d < 3600:
                $fdate = floor($d / 60) . lang('minutes_ago');
                break;
            case $d < $dd:
                $fdate = floor($d / 3600) . lang('hours_ago');
                break;
            case $d < $yd:
                $fdate = lang('yesterday') . date('H:i', $time);
                break;
            case $d < $byd:
                $fdate = lang('day_before_yesterday') . date('H:i', $time);
                break;
            case $d < $md:
                $fdate = date('m'.lang('month').'d'.lang('day').' H:i', $time);
                break;
            case $d < $ld:
                $fdate = date('m'.lang('month').'d'.lang('day'), $time);
                break;
            default:
                $fdate = date('Y'.lang('year').'m'.lang('month').'d'.lang('day'), $time);
                break;
        }
    }
    return $fdate;
}

function mac_get_time_span($sn)
{
    $lastTime = session($sn);

    if(empty($lastTime)){
        $lastTime= "1228348800";
    }
    $res = time() - intval($lastTime);
    session($sn,time());
    return $res;
}

function mac_get_rndstr($length=32,$f='')
{
    $pattern = "234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if($f=='num'){
        $pattern = '1234567890';
    }
    elseif($f=='letter'){
        $pattern = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    }
    $len = strlen($pattern) -1;
    $res='';
    for($i=0; $i<$length; $i++){
        $res .= $pattern[mt_rand(0,$len)];
    }
    // 开头为0的随机替换为1~9，优化导出格式问题
    if (str_starts_with($res, '0')) {
        $res = mt_rand(1, 9) . substr($res, 1);
    }
    return $res;
}

function mac_convert_encoding($str,$nfate,$ofate){
    if ($ofate=="UTF-8"){ return $str; }
    if ($ofate=="GB2312"){ $ofate="GBK"; }

    if(function_exists("mb_convert_encoding")){
        $str=mb_convert_encoding($str,$nfate,$ofate);
    }
    else{
        $ofate.="//IGNORE";
        $str=iconv($nfate ,$ofate ,$str);
    }
    return $str;
}

function mac_get_refer()
{
    return trim(urldecode($_SERVER["HTTP_REFERER"]));
}

function mac_extends_list($flag)
{
    $path = './application/common/extend/'.$flag;
    $file_list = glob($path . '/*.php',GLOB_NOSORT );
    $res=[];
    $res['ext_list'] = [];
    $res['ext_html'] = '';
    foreach($file_list as $k=>$v) {
        $cl = str_replace([$path . '/', '.php'], '', $v);
        $cp = 'app\\common\\extend\\'.$flag.'\\' . $cl;
        if (class_exists($cp)) {
            $c = new $cp;
            $res['ext_list'][$cl] = $c->name;
            if(file_exists( './application/admin/view/extend/'.$flag.'/'.strtolower($cl) .'.html')) {
                $res['ext_html'] .= View::instance()->fetch('admin@extend/'.$flag.'/' . strtolower($cl));
            }
        }
    }
    return $res;
}

function mac_send_sms($to,$code,$type_flag,$type_des,$msg)
{
    if(empty($GLOBALS['config']['sms']['type'])){
        return ['code'=>9005,'msg'=> lang('sms_not_config')];
    }
    $pattern = "/^1[345789][0-9]{9}$/";
    if(!preg_match($pattern,$to)){
        return ['code'=>999,'msg'=>lang('phone_format_err')];
    }
    if(empty($code)){
        return ['code'=>998,'msg'=>lang('title_not_empty')];
    }
    if(empty($type_flag)){
        return ['code'=>997,'msg'=>lang('tpl_not')];
    }


    $cp = 'app\\common\\extend\\sms\\' . ucfirst($GLOBALS['config']['sms']['type']);
    if (class_exists($cp)) {
        $c = new $cp;
        return $c->submit($to,$code,$type_flag,$type_des,$msg);
    }
    else{
        return ['code'=>991,'msg'=>lang('sms_not')];
    }
}

function mac_send_mail($to,$title,$body,$conf=[])
{
    if(empty($GLOBALS['config']['email']['type'])){
        return ['code'=>9005,'msg'=>lang('email_not_config')];
    }
    $pattern = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/';
    if(!preg_match( $pattern, $to)){
        return ['code'=>999,'msg'=>lang('email_format_err')];
    }
    if(empty($title)){
        return ['code'=>998,'msg'=>lang('title_not_empty')];
    }
    if(empty($body)){
        return ['code'=>997,'msg'=>lang('body_not_empty')];
    }

    $cp = 'app\\common\\extend\\email\\' . ucfirst($GLOBALS['config']['email']['type']);
    if (class_exists($cp)) {
        $c = new $cp;
        return $c->submit($to,$title,$body,$conf);
    }
    else{
        return ['code'=>991,'msg'=>lang('email_not')];
    }
}

function mac_check_back_link($url)
{
    $res=[];
    $res['code'] = 0;
    $res['msg'] = lang('param_err');

    if(empty($url)){
        return json($res);
    }

    $site_url = $GLOBALS['config']['site']['site_url'];
    $site_wapurl = $GLOBALS['config']['site']['site_wapurl'];
    $html = mac_curl_get($url);
    $msg = '';
    $code = 1;

    $ok = lang('back_link').lang('normal');
    $err = lang('back_link').lang('abnormal');

    $msg .= '['.$site_url.']';
    if(strpos($html,$site_url)!==false){
        $code=1;
        $msg .=$ok;
    }
    else{
        $code=101;
        $msg .=$err;
    }

    $msg .= '，['.$site_wapurl.']';
    if(strpos($html,$site_wapurl)!==false){
        $code =1;
        $msg .=$ok;
    }
    else{
        $code=101;
        $msg .=$err;
    }
    $res['code'] = $code;
    $res['msg'] = $msg;

    return $res;
}

function mac_list_to_tree($list, $pk='id',$pid = 'pid',$child = 'child',$root=0)
{
    $tree = array();
    if(is_array($list)) {
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] =& $list[$key];
        }

        foreach ($list as $key => $data) {
            $parentId = $data[$pid];

            if ($root == $parentId) {
                $tree[] =& $list[$key];

            }else{
                if (isset($refer[$parentId])) {
                    $parent =& $refer[$parentId];
                    $parent[$child][] =& $list[$key];
                }
            }
        }
    }
    return $tree;
}

function mac_str_correct($str,$from,$to)
{
    return str_replace($from,$to,$str);
}

function mac_buildregx($regstr,$regopt)
{
    return '/'.str_replace('/','\/',$regstr).'/'.$regopt;
}

function mac_em_replace($s)
{
    return preg_replace("/\[em:(\d{1,})?\]/","<img src=\"". MAC_PATH ."static/images/face/$1.gif\" border=0/>",$s);
}

function mac_page_param($record_total, $page_size, $page_current, $page_url,$page_half=5)
{
    $page_param = array();
    $page_num = array();

    if ($record_total == 0) {
        return ['record_total'=>0];
    }
    if(empty($page_half)){
        $page_half=5;
    }

    $page_param['record_total'] = $record_total;
    $page_param['page_current'] = $page_current;

    $page_total = ceil($record_total / $page_size);
    $page_param['page_total'] = $page_total;
    $page_param['page_sp'] = MAC_PAGE_SP;

    $page_prev = $page_current - 1;
    if ($page_prev <= 0) {
        $page_prev = 1;
    }
    $page_next = $page_current + 1;
    if ($page_next > $page_total) {
        $page_next = $page_total;
    }
    $page_param['page_prev'] = $page_prev;
    $page_param['page_next'] = $page_next;

    if ($page_total <= $page_half) {
        for ($i = 1; $i <= $page_total; $i++) {
            $page_num[$i] = $i;
        }
    } else {
        $page_num_left = floor($page_half / 2);
        $page_num_right = $page_total - $page_half;

        if ($page_current <= $page_num_left) {
            for ($i = 1; $i <= $page_half; $i++) {
                $page_num[$i] = $i;
            }
        } elseif ($page_current > $page_num_right) {
            for ($i = ($page_num_right + 0); $i <= $page_total; $i++) {
                $page_num[$i] = $i;
            }
        } else {
            for ($i = ($page_current - $page_num_left); $i <= ($page_current + $page_num_left); $i++) {
                $page_num[$i] = $i;
            }
        }
    }
    $page_param['page_num'] = $page_num;
    $page_param['page_url'] = $page_url;

    return $page_param;
}

// CurlPOST数据提交-----------------------------------------
function mac_curl_post($url,$data,$heads=array(),$cookie='')
{
    $ch = @curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLINFO_CONTENT_LENGTH_UPLOAD,strlen($data));
    curl_setopt($ch, CURLOPT_HEADER,0);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    if(!empty($cookie)){
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if(count($heads)>0){
        curl_setopt ($ch, CURLOPT_HTTPHEADER , $heads );
    }
    $response = @curl_exec($ch);
    if(curl_errno($ch)){//出错则显示错误信息
        //print curl_error($ch);
    }
    curl_close($ch); //关闭curl链接
    return $response;//显示返回信息
}
// CurlPOST数据提交-----------------------------------------
function mac_curl_get($url,$heads=array(),$cookie='')
{
    $ch = @curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HEADER,0);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
    if(!empty($cookie)){
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if(count($heads)>0){
        curl_setopt ($ch, CURLOPT_HTTPHEADER , $heads );
    }
    $response = @curl_exec($ch);
    if(curl_errno($ch)){//出错则显示错误信息
        //print curl_error($ch);die;
    }
    curl_close($ch); //关闭curl链接
    return $response;//显示返回信息
}


function mac_substring($str, $lenth, $start=0)
{
    $len = strlen($str);
    $r = array();
    $n = 0;
    $m = 0;

    for($i=0;$i<$len;$i++){
        $x = substr($str, $i, 1);
        $a = base_convert(ord($x), 10, 2);
        $a = substr( '00000000 '.$a, -8);

        if ($n < $start){
            if (substr($a, 0, 1) == 0) {
            }
            else if (substr($a, 0, 3) == 110) {
                $i += 1;
            }
            else if (substr($a, 0, 4) == 1110) {
                $i += 2;
            }
            $n++;
        }
        else{
            if (substr($a, 0, 1) == 0) {
                $r[] = substr($str, $i, 1);
            }else if (substr($a, 0, 3) == 110) {
                $r[] = substr($str, $i, 2);
                $i += 1;
            }else if (substr($a, 0, 4) == 1110) {
                $r[] = substr($str, $i, 3);
                $i += 2;
            }else{
                $r[] = ' ';
            }
            if (++$m >= $lenth){
                break;
            }
        }
    }
    return  join('',$r);
}


function mac_array2xml($arr,$level=1)
{
    $s = $level == 1 ? "<xml>" : '';
    foreach($arr as $tagname => $value) {
        if (is_numeric($tagname)) {
            $tagname = $value['TagName'];
            unset($value['TagName']);
        }
        if(!is_array($value)) {
            $s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
        } else {
            $s .= "<{$tagname}>" . mac_array2xml($value, $level + 1)."</{$tagname}>";
        }
    }
    $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    return $level == 1 ? $s."</xml>" : $s;
}


function mac_xml2array($xml)
{
    libxml_disable_entity_loader(true);
    $result= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $result;
}

function mac_array_rekey($arr,$key)
{
    $list = [];
    foreach($arr as $k=>$v){
        $list[$v[$key]] = $v;
    }
    return $list;
}

function mac_array_filter($arr,$str)
{
    if(!is_array($arr)){
        $arr = explode(',',$arr);
    }
    $arr = array_filter($arr);
    if(empty($arr)){
        return false;
    }
    //方式一
    $new_str = str_replace($arr,'*',$str);
    //$badword1 = array_combine($arr,array_fill(0,count($arr),'*'));
    //$new_str = strtr($str, $badword1);
    return $new_str != $str;
}

function mac_parse_sql($sql='',$limit=0,$prefix=[])
{
    // 被替换的前缀
    $from = '';
    // 要替换的前缀
    $to = '';

    // 替换表前缀
    if (!empty($prefix)) {
        $to   = current($prefix);
        $from = current(array_flip($prefix));
    }

    if ($sql != '') {
        // 纯sql内容
        $pure_sql = [];

        // 多行注释标记
        $comment = false;

        // 按行分割，兼容多个平台
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $sql = explode("\n", trim($sql));
        $cnm = base64_decode('YeeJiOadg+aJgOaciW1hZ2ljYmxhY2vvvIzmupDnoIFodHRwczovL2dpdGh1Yi5jb20vbWFnaWNibGFjaw==');
        // 循环处理每一行
        foreach ($sql as $key => $line) {
            // 跳过空行
            if ($line == '') {
                continue;
            }

            // 跳过以#或者--开头的单行注释
            if (preg_match("/^(#|--)/", $line)) {
                continue;
            }

            // 跳过以/**/包裹起来的单行注释
            if (preg_match("/^\/\*(.*?)\*\//", $line)) {
                continue;
            }

            // 多行注释开始
            if (substr($line, 0, 2) == '/*') {
                $comment = true;
                continue;
            }

            // 多行注释结束
            if (substr($line, -2) == '*/') {
                $comment = false;
                continue;
            }

            // 多行注释没有结束，继续跳过
            if ($comment) {
                continue;
            }

            // 替换表前缀
            if ($from != '') {
                $line = str_replace('`'.$from, '`'.$to, $line);
            }
            if ($line == 'BEGIN;' || $line =='COMMIT;') {
                continue;
            }
            // sql语句
            array_push($pure_sql, $line);
        }

        // 只返回一条语句
        if ($limit == 1) {
            return implode("",$pure_sql);
        }


        // 以数组形式返回sql语句
        $pure_sql = implode("\n",$pure_sql);
        $pure_sql = explode(";\n", $pure_sql);
        return $pure_sql;
    } else {
        return $limit == 1 ? '' : [];
    }
}

function mac_interface_type()
{
    $key = $GLOBALS['config']['app']['cache_flag']. '_'. 'interface_type';
    $data = think\Cache::get($key);
    if(empty($data)){
        $config = config('maccms.interface');
        $vodtype = str_replace([chr(10),chr(13)],['','#'],$config['vodtype']);
        $arttype = str_replace([chr(10),chr(13)],['','#'],$config['arttype']);
        $actortype = str_replace([chr(10),chr(13)],['','#'],$config['actortype']);
        $websitetype = str_replace([chr(10),chr(13)],['','#'],$config['websitetype']);

        $data =[];
        $type_arr = explode('#',$vodtype);
        foreach($type_arr as $k=>$v){
            list($from, $to) = explode('=', $v);
            $data['vodtype'][$to] = $from;
        }

        $type_arr = explode('#',$arttype);
        foreach($type_arr as $k=>$v){
            list($from, $to) = explode('=', $v);
            $data['arttype'][$to] = $from;
        }

        $type_arr = explode('#',$actortype);
        foreach($type_arr as $k=>$v){
            list($from, $to) = explode('=', $v);
            $data['actortype'][$to] = $from;
        }

        $type_arr = explode('#',$websitetype);
        foreach($type_arr as $k=>$v){
            list($from, $to) = explode('=', $v);
            $data['websitetype'][$to] = $from;
        }

        think\Cache::set($key,$data);
    }

    $type_list = model('Type')->getCache('type_list');
    $type_names = [];
    foreach($type_list as $k=>$v){
        $type_names[$v['type_name']] = $v['type_id'];
    }

    foreach($data['vodtype'] as $k=>$v){
        $data['vodtype'][$k] = (int)$type_names[$v];
    }
    foreach($data['arttype'] as $k=>$v){
        $data['arttype'][$k] = (int)$type_names[$v];
    }
    foreach($data['actortype'] as $k=>$v){
        $data['actortype'][$k] = (int)$type_names[$v];
    }
    foreach($data['websitetype'] as $k=>$v){
        $data['websitetype'][$k] = (int)$type_names[$v];
    }
    return $data;
}

function mac_rep_pse_rnd($psearr,$txt,$id=0)
{
    if(empty($psearr)){
        return $txt;
    }
    $i=count($psearr);
    if(empty($txt)){
        if(empty($id)){
            $r = mt_rand(0,$i-1);
        }
        else{
            $r = $id % $i;
        }
        $res= $psearr[$r];
    }
    else{
        if(empty($id)){
            $id = crc32($txt);
        }
        $j=mb_strpos($txt,"<br>");
        $k=mb_strlen($txt);
        if($j==0){ $j=mb_strpos($txt,"<br/>"); }
        if($j==0){ $j=mb_strpos($txt,"<br />"); }
        if($j==0){ $j=mb_strpos($txt,"</p>"); }
        if($j==0){ $j=mb_strpos($txt,"。"); }
        if($j==0){ $j=mb_strpos($txt,"！"); }
        if($j==0){ $j=mb_strpos($txt,"!"); }
        if($j==0){ $j=mb_strpos($txt,"？"); }
        if($j==0){ $j=mb_strpos($txt,"?"); }
        if($j>0){
            $res= mac_substring($txt,$j-1) . $psearr[$id % $i] . mac_substring($txt,$k-$j,$j);
        }
        else{
            $res= $psearr[$id % $i]. $txt;
        }
    }
    return $res;
}

function mac_txt_explain($txt, $decode = false)
{
    // 先将HTML实体中的#临时替换为特殊占位符
    $placeholder = '___HTML_ENTITY_HASH___';
    $txt = preg_replace('/&#(\d+);/', $placeholder . '$1;', $txt);
    $txt = preg_replace('/&#x([0-9a-fA-F]+);/', $placeholder . 'x$1;', $txt);
    // 安全地按#分割
    $txtarr = explode('#', $txt);
    // 还原HTML实体中的#
    foreach($txtarr as &$item) {
        $item = str_replace($placeholder, '&#', $item);
    }
    unset($item);
    $data=[];
    foreach($txtarr as $v){
        if (stripos($v, '=') === false) {
            continue;
        }
        list($from, $to) = explode('=', $v, 2);
        if ($decode === true && stripos($from, '&') !== false && stripos($from, ';') !== false) {
            $from = html_entity_decode($from, ENT_QUOTES, 'UTF-8');
        }
        if ($decode === true && stripos($to, '&') !== false && stripos($to, ';') !== false) {
            $to = html_entity_decode($to, ENT_QUOTES, 'UTF-8');
        }
        $data['from'][] = $from;
        $data['to'][] = $to;
    }
    return $data;
}

function mac_rep_pse_syn($psearr,$txt)
{
    if(empty($txt)){ $txt=""; }
    if(is_array($psearr['from']) && is_array($psearr['to'])){
        $txt = str_replace($psearr['from'],$psearr['to'],$txt);
    }
    return $txt;
}

function mac_get_tag($title,$content){
    $url = base64_decode('aHR0cDovL2FwaS5kcGxheWVyc3RhdGljLmNvbQ==').'/keyword/index?name='.rawurlencode($title).'&txt='.rawurlencode($title).rawurlencode(mac_substring(strip_tags($content),200));
    $data = mac_curl_get($url);
    $json = @json_decode($data,true);
    if($json){
        if($json['code']==1){
            return implode(',',$json['data']);
        }
    }
    return false;
}

function mac_get_client_ip()
{
    static $final;
    if (!is_null($final)) {
        return $final;
    }
    $ips = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ips[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_ALI_CDN_REAL_IP'])) {
        $ips[] = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ips[] = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_PROXY_USER'])) {
        $ips[] = $_SERVER['HTTP_PROXY_USER'];
    }
    $real_ip = getenv('HTTP_X_REAL_IP');
    if (!empty($real_ip)) {
        $ips[] = $real_ip;
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ips[] = $_SERVER['REMOTE_ADDR'];
    }
    // 选第一个最合法的，或最后一个正常的IP
    foreach ($ips as $ip) {
        $verifyResult = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE);
        if (!$verifyResult){
            continue;
        }
        $verifyResult && $final = $ip;
    }
    empty($final) && $final = '0.0.0.0';
    return $final;
}

function mac_get_ip_long($ip_addr = '')
{
    $ip_addr = !empty($ip_addr) ? $ip_addr : mac_get_client_ip();
    $ip_long = sprintf('%u',ip2long($ip_addr));
    // 排除不正确的IP
    if ($ip_long < 0 || $ip_long >= 0xFFFFFFFF) {
        $ip_long = 0;
    }
    return $ip_long;
}

function mac_get_uniqid_code($code_prefix='')
{
    $code_prefix = strtoupper($code_prefix);
    $now_date = date('YmdHis');
    $now_time = rand(100000, 999999);
    return $code_prefix . $now_date . $now_time;
}

function mac_escape($string, $in_encoding = 'UTF-8',$out_encoding = 'UCS-2') {
    $return = '';
    if (function_exists('mb_get_info')) {
        for($x = 0; $x < mb_strlen ( $string, $in_encoding ); $x ++) {
            $str = mb_substr ( $string, $x, 1, $in_encoding );
            if (strlen ( $str ) > 1) { // 多字节字符
                $return .= '%u' . strtoupper ( bin2hex ( mb_convert_encoding ( $str, $out_encoding, $in_encoding ) ) );
            } else {
                $return .= '%' . strtoupper ( bin2hex ( $str ) );
            }
        }
    }
    return $return;
}
function mac_unescape($str)
{
    $ret = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i ++)
    {
        if ($str[$i] == '%' && $str[$i + 1] == 'u')
        {
            $val = hexdec(substr($str, $i + 2, 4));
            if ($val < 0x7f)
                $ret .= chr($val);
            else
                if ($val < 0x800)
                    $ret .= chr(0xc0 | ($val >> 6)) .
                        chr(0x80 | ($val & 0x3f));
                else
                    $ret .= chr(0xe0 | ($val >> 12)) .
                        chr(0x80 | (($val >> 6) & 0x3f)) .
                        chr(0x80 | ($val & 0x3f));
            $i += 5;
        } else
            if ($str[$i] == '%')
            {
                $ret .= urldecode(substr($str, $i, 3));
                $i += 2;
            } else
                $ret .= $str[$i];
    }
    return $ret;
}

/*特殊字段的值转换*/
function mac_get_mid_code($data)
{
    $arr = [
        1  => 'vod',
        2  => 'art',
        3  => 'topic',
        4  => 'comment',
        5  => 'gbook',
        6  => 'user',
        7  => 'label',
        8  => 'actor',
        9  => 'role',
        10 => 'plot',
        11 => 'website',
        12 => 'manga',
    ];
    return $arr[$data];
}
function mac_get_mid_text($data)
{
    $arr = [
        1  => lang('vod'),
        2  => lang('art'),
        3  => lang('topic'),
        4  => lang('comment'),
        5  => lang('gbook'),
        6  => lang('user'),
        7  => lang('label'),
        8  => lang('actor'),
        9  => lang('role'),
        10 => lang('plot'),
        11 => lang('website'),
        12 => lang('manga'),
    ];
    return $arr[$data];
}
function mac_get_mid($controller)
{
    $controller=strtolower($controller);
    $arr = [
        'vod'     => 1,
        'art'     => 2,
        'topic'   => 3,
        'comment' => 4,
        'gbook'   => 5,
        'user'    => 6,
        'label'   => 7,
        'actor'   => 8,
        'role'    => 9,
        'plot'    => 10,
        'website' => 11,
        'manga'   => 12,
    ];
    return $arr[$controller];
}
function mac_get_aid($controller,$action='')
{
    $controller=strtolower($controller);
    $action=strtolower($action);
    $key = $controller.'/'.$action;

    $arr=['index'=>1,'map'=>2,'rss'=>3,'gbook'=>4,'comment'=>5,'user'=>6,'label'=>7,'vod'=>10,'art'=>20,'topic'=>30,'actor'=>80,'role'=>90,'plot'=>100,'website'=>110];
    $res = $arr[$controller];

    // https://github.com/magicblack/maccms10/issues/960
    $arr=[
        'vod/type'=>11,'vod/show'=>12,'vod/search'=>13,'vod/detail'=>14,'vod/play'=>15,'vod/down'=>16,'vod/role'=>17,'vod/plot'=>18,
        'art/type'=>21,'art/show'=>22,'art/search'=>23,'art/detail'=>24,
        'manga/type'=>121,'manga/show'=>122,'manga/search'=>123,'manga/detail'=>124,
        'topic/search'=>33,'topic/detail'=>34,
        'actor/type'=>81,'actor/show'=>82,'actor/search'=>83,'actor/detail'=>84,
        'role/show'=>92,'role/search'=>93,'role/detail'=>94,
        'plot/search'=>103,'plot/detail'=>104,
        'website/type'=>111,'website/show'=>112,'website/search'=>113,'website/detail'=>114,
    ];
    if(!empty($arr[$key])){
        $res= $arr[$key];
    }
    return $res;
}

function mac_get_user_status_text($data)
{
    $arr = [
        0 => lang('disable'),
        1 => lang('enable'),
    ];
    return $arr[$data];
}
function mac_get_user_flag_text($data)
{
    $arr = [
        0 => lang('counting_points'),
        1 => lang('counting_times'),
        2 => lang('counting_ips'),
    ];
    return $arr[$data];
}

function mac_get_ulog_type_text($data)
{
    $arr = [
        1 => lang('browse'),
        2 => lang('collect'),
        3 => lang('want_see'),
        4 => lang('play'),
        5 => lang('down'),
    ];
    return $arr[$data];
}

function mac_get_plog_type_text($data)
{
    $arr = [
        1 => lang('integral_recharge'),
        2 => lang('registration_promotion'),
        3 => lang('visit_promotion'),
        4 => lang('one_level_distribution'),
        5 => lang('two_level_distribution'),
        6 => lang('three_level_distribution'),
        7 => lang('points_upgrade'),
        8 => lang('integral_consumption'),
        9 => lang('integral_withdrawal'),
    ];
    return $arr[$data];
}

function mac_get_card_sale_status_text($data)
{
    $arr = [
        0 => lang('not_sale'),
        1 => lang('sold'),
    ];
    return $arr[$data];
}

function mac_get_card_use_status_text($data)
{
    $arr = [
        0 => lang('not_used'),
        1 => lang('used'),
    ];
    return $arr[$data];
}

function mac_get_order_status_text($data)
{
    $arr = [
        0 => lang('not_paid'),
        1 => lang('paid'),
    ];
    return $arr[$data];
}

function mac_get_user_portrait($user_id)
{
    $res = MAC_PATH . 'static_new/images/touxiang.png';
    if(!empty($user_id)){
        $res2 = 'upload/user/'.($user_id % 10 ). '/'.$user_id.'.jpg';
        if(file_exists(ROOT_PATH . $res2)){
            $res = MAC_PATH . $res2;
        }
    }
    return $res;
}

function mac_filter_html($str)
{
    return strip_tags($str);
}

function mac_filter_xss($str)
{
    // 识别URL类型，跳过HTML实体转义
    // 判断是否为URL格式：http://、https://、//、mac: 开头，或包含 :// 的字符串
    $trimmed_str = trim($str);
    if (!empty($trimmed_str)) {
        // 检查是否为URL格式
        $is_url = false;

        // 检查是否以常见协议开头（最严格的判断，优先级最高）
        if (preg_match('/^(https?:\/\/|ftp:\/\/|\/\/|mac:)/i', $trimmed_str)) {
            $is_url = true;
        }
        // 检查是否包含 :// 协议标识符（包含协议但可能不是常见协议）
        elseif (strpos($trimmed_str, '://') !== false) {
            $is_url = true;
        }
        // 注意：这个条件相对宽松，但只去除HTML标签仍然安全
        elseif (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}(\/|\?|&|=|$)/', $trimmed_str)) {
            $is_url = true;
        }

        if ($is_url) {
            // URL类型：只去除HTML标签，不进行HTML实体转义
            // strip_tags() 会去除所有HTML标签，确保安全性
            return trim(strip_tags($trimmed_str));
        }
    }

    // 普通文本：正常进行XSS过滤
    return trim(htmlspecialchars(strip_tags($trimmed_str), ENT_QUOTES));
}

function mac_restore_htmlfilter($str) {
    if (stripos($str, '&amp;') !== false) {
        return htmlspecialchars_decode($str, ENT_QUOTES);
    }
    return $str;
}

function mac_format_text($str, $allow_space = false)
{
    $finder = array('/', '，', '|', '、', ',,', ',,,');
    if ($allow_space === false) {
        $finder[] = ' ';
    }
    return str_replace($finder, ',', $str);
}
function mac_format_count($str)
{
    $arr = explode(',',$str);
    return count($arr);
}

function mac_txt_merge($txt,$str)
{
    if(empty($str)){
        return $txt;
    }
    if($GLOBALS['config']['collect']['vod']['class_filter'] !='0') {
        if (mb_strlen($str) > 2) {
            $str = str_replace([lang('slice')], [''], $str);
        }
        if (mb_strlen($str) > 2) {
            $str = str_replace([lang('drama')], [''], $str);
        }
    }
    $txt = mac_format_text($txt);
    $str = mac_format_text($str);
    $arr1 = explode(',',$txt);
    $arr2 = explode(',',$str);
    $arr = array_merge($arr1,$arr2);
    return join(',',array_unique( array_filter($arr)));
}

function mac_array_check_num($arr)
{
    if(!is_array($arr)){
        return false;
    }
    $res = true;
    foreach($arr as $a){
        if(!is_numeric($a)){
            $res=false;
            break;
        }
    }
    return $res;
}

function mac_like_arr($s)
{
    $tmp = explode(',',$s);
    $like_arr = [];
    foreach($tmp as $v){
        $like_arr[] = '%'.$v.'%';
    }
    return $like_arr;
}

function mac_art_list($art_title,$art_note,$art_content)
{
    $art_title_list = [];
    $art_note_list = [];
    $art_content_list = [];
    if(!empty($art_title)) {
        $art_title_list = explode('$$$', $art_title);
    }
    if(!empty($art_note)) {
        $art_note_list = explode('$$$', $art_note);
    }
    if(!empty($art_content)) {
        $art_content_list = explode('$$$', $art_content);
    }
    $res_list = [];
    foreach($art_content_list as $k=>$v){
        $res_list[$k+1] = [
            'page'=> $k+1,
            'title'=>$art_title_list[$k],
            'note'=>$art_note_list[$k],
            'content'=>$v,
        ];
    }
    return $res_list;
}

function mac_plot_list($vod_plot_name,$vod_plot_detail)
{
    $vod_plot_name_list = [];
    $vod_plot_detail_list = [];

    if(!empty($vod_plot_name)) {
        $vod_plot_name_list = explode('$$$', $vod_plot_name);
    }
    if(!empty($vod_plot_detail)) {
        $vod_plot_detail_list = explode('$$$', $vod_plot_detail);
    }

    $res_list = [];
    foreach($vod_plot_name_list as $k=>$v){

        $res_list[$k + 1] = [
                'name' => $vod_plot_name_list[$k],
                'detail' => $vod_plot_detail_list[$k],
        ];
    }
    return $res_list;

}



function mac_play_list($vod_play_from,$vod_play_url,$vod_play_server,$vod_play_note,$flag='play')
{
    $vod_play_from_list = [];
    $vod_play_url_list = [];
    $vod_play_server_list = [];
    $vod_play_note_list = [];

    if(!empty($vod_play_from)) {
        $vod_play_from_list = explode('$$$', $vod_play_from);
    }
    if(!empty($vod_play_url)) {
        $vod_play_url_list = explode('$$$', $vod_play_url);
    }
    if(!empty($vod_play_server)) {
        $vod_play_server_list = explode('$$$', $vod_play_server);
    }
    if(!empty($vod_play_note)) {
        $vod_play_note_list = explode('$$$', $vod_play_note);
    }

    if($flag=='play'){
        $player_list = config('vodplayer');
    }
    else{
        $player_list = config('voddowner');
    }
    $server_list = config('vodserver');

    $res_list = [];
    $sort=[];
    foreach($vod_play_from_list as $k=>$v){
        $server = (string)$vod_play_server_list[$k];
        $urls = mac_play_list_one($vod_play_url_list[$k],$v);

        $player_info = $player_list[$v];
        $server_info = $server_list[$server];
        if($player_info['status'] == '1') {
            $sort[] = $player_info['sort'];
            $res_list[$k + 1] = [
                'sid' => $k + 1,
                'player_info' => $player_info,
                'server_info' => $server_info,
                'from' => $v,
                'url' => $vod_play_url_list[$k],
                'server' => $server,
                'note' => $vod_play_note_list[$k],
                'url_count' => count($urls),
                'urls' => $urls,
            ];
        }
    }

    if( (ENTRANCE!='admin' && MAC_PLAYER_SORT=='1') ||  $GLOBALS['ismake']=='1' ){
        array_multisort($sort, SORT_DESC, SORT_FLAG_CASE , $res_list);
        $tmp=[];
        foreach($res_list as $k=>$v){
            $tmp[$v['sid']] = $v;
        }
        $res_list = $tmp;
    }
    return $res_list;
}

function new_stripslashes($string) {
    if(!is_array($string)) return stripslashes($string);
    foreach($string as $key => $val) $string[$key] = new_stripslashes($val);
    return $string;
}

function mac_screenshot_list($screenshot)
{
    $url_list = array();
    $array_url = explode('#',$screenshot);
    foreach($array_url as $key=>$val){
        if(empty($val)) continue;

        list($title, $url) = explode('$', $val);
        if ( empty($url) ) {
            $url_list[$key+1]['name'] = ($key+1);
            $url_list[$key+1]['url'] = $title;
        }else{
            $url_list[$key+1]['name'] = $title;
            $url_list[$key+1]['url'] = $url;
        }
    }
    return $url_list;
}

function mac_play_list_one($url_one, $from_one, $server_one=''){
    $url_list = array();
    $array_url = explode('#',$url_one);
    foreach($array_url as $key=>$val){
        if(empty($val)) continue;

        list($title, $url, $from) = explode('$', $val);
        if ( empty($url) ) {
            $url_list[$key+1]['name'] = lang('the').($key+1).lang('episode');
            $url_list[$key+1]['url'] = $server_one.$title;
        }else{
            $url_list[$key+1]['name'] = $title;
            $url_list[$key+1]['url'] = $server_one.$url;
        }
        if(empty($from)){
            $from = $from_one;
        }
        $url_list[$key+1]['from'] = (string)$from;
        $url_list[$key+1]['nid'] = $key+1;
    }
    return $url_list;
}

function mac_manga_list($manga_play_from,$manga_play_url,$manga_play_server,$manga_play_note)
{
    $manga_play_from_list = [];
    $manga_play_url_list = [];
    $manga_play_server_list = [];
    $manga_play_note_list = [];

    if(!empty($manga_play_from)) {
        $manga_play_from_list = explode('$$$', $manga_play_from);
    }
    if(!empty($manga_play_url)) {
        $manga_play_url_list = explode('$$$', $manga_play_url);
    }
    if(!empty($manga_play_server)) {
        $manga_play_server_list = explode('$$$', $manga_play_server);
    }
    if(!empty($manga_play_note)) {
        $manga_play_note_list = explode('$$$', $manga_play_note);
    }

    $res_list = [];
    foreach($manga_play_from_list as $k=>$v){
        $server = (string)$manga_play_server_list[$k];
        $urls = mac_play_list_one($manga_play_url_list[$k],$v);

        $res_list[$k + 1] = [
            'sid' => $k + 1,
            'from' => $v,
            'url' => $manga_play_url_list[$k],
            'server' => $server,
            'note' => $manga_play_note_list[$k],
            'url_count' => count($urls),
            'urls' => $urls,
        ];
    }
    return $res_list;
}

function mac_filter_words($p)
{
    $config = config('maccms.app');
    $arr = explode(",",$config['filter_words']);
    if(is_array($p)){
        foreach($p as $k=>$v){
            $p[$k] = str_replace($arr,"***",$v);
        }
    }
    else{
        $p = str_replace($arr,"***",$p);
    }
    return $p;
}

function mac_long2ip($ip){
    $ip = long2ip($ip);
    $reg2 = '~(\d+)\.(\d+)\.(\d+)\.(\d+)~';
    return preg_replace($reg2, "$1.$2.*.*", $ip);
}
function mac_default($s,$def='')
{
    if(empty($s)){
        return $def;
    }
    return $s;
}
function mac_num_fill($num)
{
    if($num<10){
        $num = '0' . $num;
    }
    return $num;
}

function mac_multisort($arr,$col_sort,$sort_order,$col_status='',$status_val='')
{
    $sort=[];
    foreach($arr as $k=>$v){
        if($col_status!='' && $v[$col_status] != $status_val){
            unset($arr[$k]);
       } else {
            $sort[] = isset($v[$col_sort]) ? $v[$col_sort] : 0;
       }
    }
    array_multisort($sort, $sort_order, SORT_FLAG_CASE, $arr);
    return $arr;
}

function mac_get_body($text,$start,$end)
{
    if(empty($text)){ return false; }
    if(empty($start)){ return false; }
    if(empty($end)){ return false; }

    $start=stripslashes($start);
    $end=stripslashes($end);

    if(strpos($text,$start)!=""){
        $str = substr($text,strpos($text,$start)+strlen($start));
        $str = substr($str,0,strpos($str,$end));
    }
    else{
        $str='';
    }
    return $str;
}

function mac_find_array($text,$start,$end)
{
    $start=stripslashes($start);
    $end=stripslashes($end);
    if(empty($text)){ return false; }
    if(empty($start)){ return false; }
    if(empty($end)){ return false; }

    $start = str_replace(["(",")","'","?"],["\(","\)","\'","\?"],$start);
    $end = str_replace(["(",")","'","?"],["\(","\)","\'","\?"],$end);

    $labelRule = $start."(.*?)".$end;
    $labelRule = mac_buildregx($labelRule,"is");
    preg_match_all($labelRule,$text,$tmparr);
    $tmparrlen=count($tmparr[1]);
    $rc=false;
    $str='';
    $arr=[];
    for($i=0;$i<$tmparrlen;$i++) {
        if($rc){ $str .= "{array}"; }
        $str .= $tmparr[1][$i];
        $rc=true;
    }

    if(empty($str)) { return false ;}
    $str=str_replace($start,"",$str);
    $str=str_replace($end,"",$str);
    //$str=str_replace("\"\"","",$str);
    //$str=str_replace("'","",$str);
    //$str=str_replace(" ","",$str);
    if(empty($str)) { return false ;}
    return $str;
}

/*前台页面*/
function mac_param_url(){
    $input = input() ;
    $param = [];
    $tmp = $_REQUEST;
    
    $input = array_merge($input,$tmp);
    //$param['id'] = intval($input['id']);
    $param['page'] = intval($input['page']) <1 ? 1 : intval($input['page']);
    $param['ajax'] = intval($input['ajax']);
    $param['tid'] = intval($input['tid']);
    $param['mid'] = intval($input['mid']);
    $param['rid'] = intval($input['rid']);
    $param['pid'] = intval($input['pid']);
    $param['sid'] = intval($input['sid']);
    $param['nid'] = intval($input['nid']);
    $param['uid'] = intval($input['uid']);
    $param['level'] = intval($input['level']);
    $param['score'] = intval($input['score']);
    $param['limit'] = intval($input['limit']);

    $param['id'] = htmlspecialchars(urldecode(trim($input['id'])));
    $param['ids'] = htmlspecialchars(urldecode(trim($input['ids'])));
    $param['wd'] = htmlspecialchars(urldecode(trim($input['wd'])));
    $param['en'] = htmlspecialchars(urldecode(trim($input['en'])));
    $param['state'] = htmlspecialchars(urldecode(trim($input['state'])));
    $param['area'] = htmlspecialchars(urldecode(trim($input['area'])));
    $param['year'] = htmlspecialchars(urldecode(trim($input['year'])));
    $param['lang'] = htmlspecialchars(urldecode(trim($input['lang'])));
    $param['letter'] = htmlspecialchars(trim($input['letter']));
    $param['actor'] = htmlspecialchars(urldecode(trim($input['actor'])));
    $param['director'] = htmlspecialchars(urldecode(trim($input['director'])));
    $param['tag'] = htmlspecialchars(urldecode(trim($input['tag'])));
    $param['class'] = htmlspecialchars(urldecode(trim($input['class'])));
    $param['order'] = htmlspecialchars(urldecode(trim($input['order'])));
    $param['by'] = htmlspecialchars(urldecode(trim($input['by'])));
    $param['file'] = htmlspecialchars(urldecode(trim($input['file'])));
    $param['name'] = htmlspecialchars(urldecode(trim($input['name'])));
    $param['url'] = htmlspecialchars(urldecode(trim($input['url'])));
    $param['type'] = htmlspecialchars(urldecode(trim($input['type'])));
    $param['sex'] = htmlspecialchars(urldecode(trim($input['sex'])));
    $param['version'] = htmlspecialchars(urldecode(trim($input['version'])));
    $param['blood'] = htmlspecialchars(urldecode(trim($input['blood'])));
    $param['starsign'] = htmlspecialchars(urldecode(trim($input['starsign'])));
    $param['domain'] = htmlspecialchars(urldecode(trim($input['domain'])));

    return $param;
}

function mac_get_page($page)
{
    if(empty($page)) {
        $param = mac_param_url();
        $page = $param['page'];
    }
    return $page;
}

function mac_tpl_fetch($model,$tpl,$def='')
{
    return $model . '/' . ( empty($tpl) ? $def  : str_replace('.html','',$tpl) );
}

function mac_get_order($order,$param)
{
    if(!empty($param['order'])) {
        $order = $param['order'];
    }
    if(!in_array($order, ['asc', 'desc'])) {
        $order = 'desc';
    }
    return $order;
}

function mac_url_img($url)
{
    if(substr($url,0,4) == 'mac:'){
        $protocol = $GLOBALS['config']['upload']['protocol'];
        if(empty($protocol)){
            $protocol = 'http';
        }
        $url = str_replace('mac:', $protocol.':',$url);
    }
    elseif(substr($url,0,4) != 'http' && substr($url,0,2) != '//' && substr($url,0,1) != '/'){
        if($GLOBALS['config']['upload']['mode']=='remote'){
            $url = $GLOBALS['config']['upload']['remoteurl'] . $url;
        }
        else{
            $url = MAC_PATH . $url;
        }
    }
    elseif(!empty($GLOBALS['config']['upload']['img_key']) && preg_match('/'.$GLOBALS['config']['upload']['img_key'].'/',$url)){
        $url = $GLOBALS['config']['upload']['img_api'] . '' . $url;
    }
    $url = mac_filter_xss($url);
    $url = str_replace('&quot;&gt;', '', $url);
    $url = str_replace('&amp;', '&', $url);
    return $url;
}

function mac_url_content_img($content)
{
    $protocol = $GLOBALS['config']['upload']['protocol'];
    if(empty($protocol)){
        $protocol = 'http';
    }
    $content = str_replace('mac:',$protocol.':',$content);
    if(!empty($GLOBALS['config']['upload']['img_key'])){
        $rule = mac_buildregx("<img[^>]*src\s*=\s*['" . chr(34) . "]?([\w/\-\:.]*)['" . chr(34) . "]?[^>]*>", "is");
        preg_match_all($rule, $content, $matches);
        if(is_array($matches[1])){
            foreach ($matches[1] as $f => $matchfieldstr) {
                $img_src = trim(preg_replace("/[ \r\n\t\f]{1,}/", " ", $matchfieldstr));
                if(preg_match('/'.$GLOBALS['config']['upload']['img_key'].'/',$img_src)){
                    $content = str_replace($img_src,$GLOBALS['config']['upload']['img_api'] . '' . $img_src,$content);
                }
            }
        }
    }
    return $content;
}

function mac_alphaID($in, $to_num=false, $pad_up=false, $passKey='')
{
    $key = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (!empty($passKey)) {
        for ($n = 0; $n<strlen($key); $n++) {
            $i[] = substr($key,$n ,1);
        }
        $len = strlen($key);
        $passhash = hash('sha256',$passKey);
        $passhash = (strlen($passhash) < $len)
            ? hash('sha512',$passKey)
            : $passhash;
        for ($n=0; $n < $len; $n++) {
            $p[] = substr($passhash, $n ,1);
        }
        array_multisort($p, SORT_DESC, $i);
        $key = implode($i);
    }
    $base = strlen($key);
    if ($to_num) {
        $out = 0;
        $len = strlen($in);
        for ($t = 0; $t < $len; $t++) {
            $char = substr($in, $t, 1);
            $pos = strpos($key, $char);
            if ($pos === false) {
                $pos = 0;
            }
            $out = $out * $base + $pos;
        }
        if (is_numeric($pad_up)) {
            if ($pad_up > 1) {
                $out -= pow($base, $pad_up - 1);
            }
        }
    } else {
        if (is_numeric($pad_up)) {
            if ($pad_up > 1) {
                $in += pow($base, $pad_up - 1);
            }
        }
        $out = "";
        // 修复部分：改用逐位计算代替浮点运算
        while ($in > 0) {
            $remainder = $in % $base;
            $out = substr($key, $remainder, 1) . $out;
            $in = ($in - $remainder) / $base;
        }
    }
    return $out;
}

function mac_url($model,$param=[],$info=[])
{
    foreach($param as $k=>$v){
        if(empty($v)){
            unset($param[$k]);
        }
    }

    if(!isset($param['page'])) $param['page']=1;

    if($param['page'] == 1){
        $param['page']='';
    }

    ksort($param); 

    $config = $GLOBALS['config'];
    
    $is_static_mode = isset($GLOBALS['ismake']) && $GLOBALS['ismake'] == '1';
    
    // 静态生成模式标记（用于后续URL处理）
    $replace_from = ['{id}','{en}','{page}','{type_id}','{type_en}','{type_pid}','{type_pen}','{md5}','{year}','{month}','{day}','{sid}','{nid}'];
    $replace_to = [];
    $page_sp = $config['path']['page_sp'];
    $path = '';


    switch ($model)
    {
        case 'index/index':
            if($config['view']['index'] == 2){
                $path = 'index';
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
            }
            else{
                $url = url($model,$param);
                if($url=='/PAGELINK.html'){
                    $url = '/index-PAGELINK.html';
                }
            }
            break;
        case 'map/index':
            if($config['view']['map'] == 2){
                $path = 'map';
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
            }
            else{
                $url = url($model,$param);
            }
            break;
        case strpos($model,'rss/')!==false:
            if($config['view']['rss'] == 2){
                $path = $model;
                if($param['page'] !=''){
                    $path .= $page_sp . $param['page'];
                }

                $path .= '.xml';
            }
            else{
                $url = url($model,$param,'xml');
            }
            break;
        case strpos($model,'label/')!==false:
            if($config['view']['label'] == 2){
                $path = $model;
            }
            else{
                $url = url($model,$param);
            }
            break;
        case 'vod/show':
        case 'art/show':
        case 'actor/show':
        case 'website/show':
            switch($config['rewrite']['type_id'])
            {
                case 1:
                    $id = $info['type_en'];
                    break;
                case 2:
                    $id = mac_alphaID($info['type_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                    break;
                default:
                    $id = $info['type_id'];
                    break;
            }
            if(!empty($id)){
                $param['id'] = $id;
            }
            $url = url($model,$param);
            break;
        case 'vod/type':
            $replace_to = [$info['type_id'],$info['type_en'],$param['page'],
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],
            ];
            if($config['view']['vod_type'] == 2){
                $path = $config['path']['vod_type'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['type_id']);
                if($param['page'] !=''){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['type_id'])
                {
                    case 1:
                        $id = $info['type_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['type_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['type_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            break;
        case 'vod/detail':
            $replace_to = [$info['vod_id'],$info['vod_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['vod_detail'] == 2){
                $path = $config['path' ]['vod_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['vod_id']);
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }

                $url = url($model,['id'=> $id ]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time'])]);
            break;
        case 'vod/play':
            $replace_to = [
                $info['vod_id'],$info['vod_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],

            ];
            if($config['view']['vod_play'] >=2){
                $path = $config['path' ]['vod_play'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['vod_id']);
                if($config['view']['vod_play'] ==2){
                    $path.= '.'. $config['path']['suffix'];
                    $path .= '?'.$info['vod_id'] . '-' . $param['sid'] . '-' . $param['nid'] ;
                }
                elseif($config['view']['vod_play'] ==3){
                    $path .= $config['path']['page_sp'] . $param['sid'] . $config['path']['page_sp'] . $param['nid'] ;
                }
                elseif($config['view']['vod_play'] ==4){
                    $path .= $config['path']['page_sp'] .''. $param['sid'] . $config['path']['page_sp'] . '1';
                    $path.= '.'. $config['path']['suffix'];
                    $path .= '?'.$info['vod_id'] . '-' . $param['sid'] . '-' . $param['nid'] ;
                }
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'sid'=>$param['sid'],'nid'=>$param['nid']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time']),$param['sid'],$param['nid']]);
            break;
        case 'vod/down':
            $replace_to = [
                $info['vod_id'],$info['vod_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['vod_down'] >= 2){
                $path = $config['path' ]['vod_down'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['vod_id']);
                if($config['view']['vod_down'] ==2){
                    $path.= '.'. $config['path']['suffix'];
                    $path .= '?'.$info['vod_id'] . '-' . $param['sid'] . '-' . $param['nid'] ;
                }
                elseif($config['view']['vod_down'] ==3){
                    $path .= $config['path']['page_sp'] . $param['sid'] . $config['path']['page_sp'] . $param['nid'] ;
                }
                elseif($config['view']['vod_down'] ==4){
                    $path .= $config['path']['page_sp'] .''. $param['sid'] . $config['path']['page_sp'] . '1';
                    $path.= '.'. $config['path']['suffix'];
                    $path .= '?'.$info['vod_id'] . '-' . $param['sid'] . '-' . $param['nid'] ;
                }
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'sid'=>$param['sid'],'nid'=>$param['nid']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time']),$param['sid'],$param['nid']]);
            break;
        case 'vod/role':
            $replace_to = [$info['vod_id'],$info['vod_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['vod_role'] == 2){
                $path = $config['path' ]['vod_role'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }   
                $replace_to[] = md5($info['vod_id']);
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }
                $url = url($model,['id'=>$id]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time'])]);
            break;
        case 'vod/plot':
            $replace_to = [
                $info['vod_id'],$info['vod_en'],$param['page'],
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['vod_plot'] == 2){
                $path = $config['path' ]['vod_plot'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['vod_id']);
                if($param['page']!=''){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time'])]);
            break;
        case 'art/type':
            $replace_to = [$info['type_id'],$info['type_en'],$param['page'],
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],
            ];
            if($config['view']['art_type'] == 2){
                $path = $config['path']['art_type'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['type_id']);
                }
                if($param['page']!=''){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['type_id'])
                {
                    case 1:
                        $id = $info['type_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['type_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['type_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            break;
        case 'art/detail':
            $replace_to = [
                $info['art_id'],$info['art_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['art_detail'] == 2){
                $path = $config['path' ]['art_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['art_id']);
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['art_id'])
                {
                    case 1:
                        $id = $info['art_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['art_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['art_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['art_time']),date('m',$info['art_time']),date('d',$info['art_time'])]);
            break;
        case 'topic/index':
            if($config['view']['topic_index'] == 2){
                $path = $config['path' ]['topic_index'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                $url = url($model,['page'=>$param['page']]);
            }
            break;
        case 'topic/detail':
            $replace_to = [$info['topic_id'],$info['topic_en'],'','','','',''];
            if($config['view']['topic_detail'] == 2){
                $path = $config['path' ]['topic_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['topic_id']);
                }
            }
            else{
                switch($config['rewrite']['topic_id'])
                {
                    case 1:
                        $id = $info['topic_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['topic_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['topic_id'];
                        break;
                }
                $url = url($model,['id'=>$id]);
            }
            break;
        case 'actor/index':
            if($config['view']['actor_index'] == 2){
                $path = $config['path' ]['actor_index'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                $url = url($model,['page'=>$param['page']]);
            }
            break;
        case 'actor/type':
            $replace_to = [$info['type_id'],$info['type_en'],$param['page'],
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],
            ];
            if($config['view']['actor_type'] == 2){
                $path = $config['path']['actor_type'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['type_id']);
                }
                if($param['page']!=''){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['type_id'])
                {
                    case 1:
                        $id = $info['type_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['type_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['type_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            break;
        case 'actor/detail':
            $replace_to = [$info['actor_id'],$info['actor_en'],'','','','',''];
            if($config['view']['actor_detail'] == 2){
                $path = $config['path' ]['actor_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['actor_id']);
                }
            }
            else{
                switch($config['rewrite']['actor_id'])
                {
                    case 1:
                        $id = $info['actor_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['actor_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['actor_id'];
                        break;
                }
                $url = url($model,['id'=>$id]);
            }
            break;
        case 'role/index':
            if($config['view']['role_index'] == 2){
                $path = $config['path' ]['role_index'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                $url = url($model,['page'=>$param['page']]);
            }
            break;
        case 'role/detail':
            $replace_to = [$info['role_id'],$info['actor_en'],'','','','',''];
            if($config['view']['role_detail'] == 2){
                $path = $config['path' ]['role_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['role_id']);
                }
            }
            else{
                switch($config['rewrite']['role_id'])
                {
                    case 1:
                        $id = $info['role_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['role_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['role_id'];
                        break;
                }
                $url = url($model,['id'=>$id]);
            }
            break;
        case 'plot/index':
            if($config['view']['plot_index'] == 2){
                $path = $config['path' ]['plot_index'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                $url = url($model,['page'=>$param['page']]);
            }
            break;
        case 'plot/detail':
            $replace_to = [
                $info['vod_id'],$info['vod_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['plot_detail'] == 2){
                $path = $config['path' ]['plot_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['vod_id']);
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['vod_id'])
                {
                    case 1:
                        $id = $info['vod_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['vod_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['vod_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['vod_time']),date('m',$info['vod_time']),date('d',$info['vod_time'])]);
            break;
        case 'website/index':
            if($config['view']['website_index'] == 2){
                $path = $config['path' ]['website_index'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if($param['page']>1 || $param['page'] =='PAGELINK'){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                $url = url($model,['page'=>$param['page']]);
            }
            break;
        case 'website/type':
            $replace_to = [$info['type_id'],$info['type_en'],$param['page'],
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],
            ];
            if($config['view']['website_type'] == 2){
                $path = $config['path']['website_type'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['type_id']);
                }
                if($param['page']!=''){
                    $path .= $page_sp . $param['page'];
                }
            }
            else{
                switch($config['rewrite']['type_id'])
                {
                    case 1:
                        $id = $info['type_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['type_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['type_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'page'=>$param['page']]);
            }
            break;
        case 'website/detail':
            $replace_to = [$info['website_id'],$info['website_en'],'','','','',''];
            if($config['view']['website_detail'] == 2){
                $path = $config['path' ]['website_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                if(strpos($path,'{md5}')!==false){
                    $replace_to[] = md5($info['website_id']);
                }
            }
            else{
                switch($config['rewrite']['website_id'])
                {
                    case 1:
                        $id = $info['website_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['website_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['website_id'];
                        break;
                }
                $url = url($model,['id'=>$id]);
            }
            break;
        case 'gbook/index':
            $url = url($model,['page'=>$param['page']]);
            break;
        case 'comment/index':
            $url = url($model,['page'=>$param['page']]);
            break;
        default:
            $url = url($model,$param);
            break;
    }
    if(!empty($path)) {
        $path = str_replace($replace_from, $replace_to, $path);
        $path = str_replace('//', '/', $path);
        $delimiter = false;
        if(substr($path,strlen($path)-6) =='/index'){
            $delimiter = true;
            $path = substr($path,0, strlen($path)-5);
        }

        if($delimiter==false && strpos($path,'.')===false){
            $path.= '.'. $config['path']['suffix'];
        }
        $url = $path;
        if(substr($path,0,1)!='/') {
            $url = MAC_PATH . $path;
        }
    }
    else{
        if(ENTRANCE!='index'){
            $sto= MAC_PATH ;
            if($config['rewrite']['status']==0){
                $sto = MAC_PATH .'index.php/';
            }
            if(!empty(IN_FILE)){
                $url = str_replace(IN_FILE.'/',$sto,$url);
                $url = str_replace(ENTRANCE.'/','',$url);
            }
        }
        elseif($config['rewrite']['status']==0 && strpos($url,'index.php')===false){
            if(MAC_PATH !='/'){
                $url = str_replace(MAC_PATH,'/',$url);
            }
            $url = MAC_PATH. 'index.php' . $url;
        }
        elseif($config['rewrite']['status']==1 && strpos($url,'index.php')!==false){
            $url = str_replace('index.php/','',$url);
        }

        if($config['rewrite']['suffix_hide']==1){
            $url = str_replace('.html','/',$url);
            if(strpos($model,'/show')===false && strpos($model,'/search')===false) {
                $url = str_replace(['-/','_/','-.','_.'],'/',$url);
            }
        }
        else{
            if(strpos($model,'search')===false && strpos($model,'show')===false ) {
                $url = str_replace(['-.', '/.'], '.', $url);
            }
        }
    }

    // 在静态生成模式下，对生成的URL进行后处理，去掉admin/index前缀
    if($is_static_mode && !empty($url)) {
        if(strpos($url, '/index.php/') === 0) {
            $url = preg_replace('/\/index\.php\/[^\/]+\.php\/admin\-/', '/index.php/', $url);
            $url = str_replace('/admin-', '/', $url);
        }
        if(strpos($url, 'admin-') !== false) {
            $url = str_replace('admin-', '', $url);
        }
        if(strpos($url, 'index-') !== false) {
            $url = str_replace('index-', '', $url);
        }
    }

    return $url;
}
function mac_url_page($url,$num)
{
    $url = str_replace(MAC_PAGE_SP.'PAGELINK',($num>1 ? MAC_PAGE_SP.$num : ''),$url);
    $url = str_replace('PAGELINK',$num,$url);
    return $url;
}

function mac_url_create($str,$type='actor',$flag='vod',$ac='search',$sp='&nbsp;')
{
    if(!$str){
        return '未知';
    }
    $res = [];
    // 分割时，中文关键词允许空格分割，英文不用空格（英文名中间是空格分隔的问题）
    $base_finder = array(' / ', '/', '|', ',', '，', ',,');
    $str = str_replace($base_finder, ',', $str);
    $str = trim($str, ',');
    $arr = [];
    foreach (explode(',', $str) as $tag) {
        if (preg_match("/[\x{2E80}-\x{9FFF}]+/u", $tag) && str_contains($tag, ' ')) {
            foreach (explode(' ', $tag) as $tag_exp) {
                $arr[] = $tag_exp;
            }
        } else {
            $arr[] = $tag;
        }
    }
    foreach ($arr as $k => $v) {
        $res[$k] = '<a href="'.mac_url($flag.'/'.$ac,[$type=>$v]).'" target="_blank">'.$v.'</a>'.$sp;
    }
    return implode('',$res);
}

function mac_url_search($param=[],$flag='vod')
{
    return mac_url($flag.'/search',$param);
}

function mac_url_type($info,$param=[],$flag='type')
{
    $tab = 'vod';
    if($info['type_mid'] == 1){

    }
    else if($info['type_mid'] == 2) {
        $tab ='art';
    }
    else if($info['type_mid'] == 8) {
        $tab ='actor';
    }
    else if($info['type_mid'] == 11) {
        $tab ='website';
    }
    if(empty($param['id'])){
        $param['id'] = $info['type_id'];
    }

    return mac_url($tab.'/'.$flag,$param,$info);
}

function mac_url_topic_index($param=[])
{
    return mac_url('topic/index',['page'=>$param['page']]);
}

function mac_url_topic_detail($info)
{
    return mac_url('topic/detail',[],$info);
}

function mac_url_role_index($param=[])
{
    return mac_url('role/index',['page'=>$param['page']]);
}

function mac_url_role_detail($info)
{
    return mac_url('role/detail',[],$info);
}
function mac_url_actor_index($param=[])
{
    return mac_url('actor/index',['page'=>$param['page']]);
}
function mac_url_actor_detail($info)
{
    return mac_url('actor/detail',[],$info);
}
function mac_url_actor_search($param)
{
    return mac_url('actor/search',$param);
}
function mac_url_plot_index($param=[])
{
    return mac_url('plot/index',['page'=>$param['page']]);
}
function mac_url_plot_detail($info,$param=[])
{
    return mac_url('plot/detail',['page'=>$param['page']],$info);
}
function mac_url_vod_plot($info,$param=[])
{
    return mac_url('vod/plot',$param,$info);
}
function mac_url_vod_role($info,$param=[])
{
    return mac_url('vod/role',$param,$info);
}
function mac_url_website_index($param=[])
{
    return mac_url('website/index',['page'=>$param['page']]);
}
function mac_url_website_detail($info)
{
    return mac_url('website/detail',[],$info);
}
function mac_url_website_search($param)
{
    return mac_url('website/search',$param);
}
function mac_url_art_index($param=[])
{
    return mac_url('art/index',['page'=>$param['page']]);
}
function mac_url_art_detail($info,$param=[])
{
    return mac_url('art/detail',['page'=>$param['page']],$info);
}
function mac_url_art_search($param)
{
    return mac_url('art/search',$param);
}
function mac_url_vod_index($param=[])
{
    return mac_url('vod/index',['page'=>$param['page']]);
}
function mac_url_vod_detail($info)
{
    return mac_url('vod/detail',[],$info);
}
function mac_url_manga_detail($info)
{
    return mac_url('manga/detail',[],$info);
}
function mac_url_vod_search($param)
{
    return mac_url('vod/search',$param);
}
function mac_url_vod_play($info,$param=[])
{
    if($param=='first'){
        $sid = intval(key($info['vod_play_list']));
        $nid = intval(key($info['vod_play_list'][$sid]['urls']));
        if($sid==0 || $nid==0){
            return '';
        }
        $param=[];
        $param['sid'] = $sid;
        $param['nid'] = $nid;
    }
    if(intval($param['sid'])<1){
        $param['sid'] =1;
    }
    if(intval($param['nid'])<1){
        $param['nid']=1;
    }

    return mac_url('vod/play',['sid'=>$param['sid'],'nid'=>$param['nid']],$info);
}

function mac_url_vod_down($info,$param=[])
{
    if($param=='first'){
        $sid = intval(key($info['vod_down_list']));
        $nid = intval(key($info['vod_down_list'][$sid]['urls']));
        if($sid==0 || $nid==0){
            return '';
        }
        $param=[];
        $param['sid'] = $sid;
        $param['nid'] = $nid;
    }

    if(intval($param['sid'])<1){
        $param['sid'] =1;
    }
    if(intval($param['nid'])<1){
        $param['nid']=1;
    }

    return mac_url('vod/down',['sid'=>$param['sid'],'nid'=>$param['nid']],$info);
}



function mac_label_website_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['website_id']==1){
        $where['website_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['website_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['website_id'] = ['eq',$param['id']];
    }
    $where['website_status'] = ['eq',1];
    $res = model('Website')->infoData($where,'*',1);

    $GLOBALS['type_id'] = $res['info']['type_id'];
    $GLOBALS['type_pid'] = $res['info']['type']['type_pid'];
    return $res;
}
function mac_label_actor_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['actor_id']==1){
        $where['actor_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['actor_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['actor_id'] = ['eq',$param['id']];
    }
    $where['actor_status'] = ['eq',1];
    $res = model('Actor')->infoData($where,'*',1);

    $GLOBALS['type_id'] = $res['info']['type_id'];
    $GLOBALS['type_pid'] = $res['info']['type']['type_pid'];
    return $res;
}
function mac_label_role_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['role_id']==1){
        $where['role_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['role_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['role_id'] = ['eq',$param['id']];
    }
    $where['role_status'] = ['eq',1];
    $res = model('Role')->infoData($where,'*',1);

    // https://github.com/magicblack/maccms10/issues/960
    $GLOBALS['type_id'] = isset($res['info']['data']['type_id']) ? $res['info']['data']['type_id'] : 0;
    $GLOBALS['type_pid'] = isset($res['info']['data']['type_id_1']) ? $res['info']['data']['type_id_1'] : 0;
    return $res;
}
function mac_label_topic_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['topic_id']==1){
        $where['topic_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['topic_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['topic_id'] = ['eq',$param['id']];
    }
    $where['topic_status'] = ['eq',1];
    $res = model('Topic')->infoData($where,'*',1);
    return $res;
}
function mac_label_art_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['art_id']==1){
        $where['art_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['art_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['art_id'] = ['eq',$param['id']];
    }
    $where['art_status'] = ['eq',1];
    $res = model('Art')->infoData($where,'*',1);
    if($res['code'] ==1){
        if($param['page']>$res['info']['art_page_total']){ $param['page'] = $res['info']['art_page_total']; }
    }
    $GLOBALS['type_id'] = $res['info']['type_id'];
    $GLOBALS['type_pid'] = $res['info']['type']['type_pid'];

    return $res;
}
function mac_label_vod_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['vod_id']==1){
        $where['vod_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['vod_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['vod_id'] = ['eq',$param['id']];
    }
    $where['vod_status'] = ['eq',1];
    $res = model('Vod')->infoData($where,'*',1);

    $GLOBALS['type_id'] = $res['info']['type_id'];
    $GLOBALS['type_pid'] = $res['info']['type']['type_pid'];
    return $res;
}

function mac_label_vod_role($param)
{
    $where = [];
    $where['role_rid'] = $param['rid'];
    $where['role_status'] = ['eq',1];
    $order='role_sort desc,role_id desc';
    $res = model('Role')->listData($where,$order,1,999,0,'*',0,0);
    return $res;
}

function mac_label_type($param, $type_id_specified)
{
    if ($type_id_specified > 0) {
        $type_id = $type_id_specified;
    } else {
        if($GLOBALS['config']['rewrite']['type_id']==1){

        }
        else{
            if($GLOBALS['config']['rewrite']['type_id']==2) {
                $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
            }
        }
        $type_id = $param['id'];
    }
    $type_info = model('Type')->getCacheInfo($type_id);

    $GLOBALS['type_id'] =$type_info['type_id'];
    $GLOBALS['type_pid'] = $type_info['type_pid'];

    $parent = model('Type')->getCacheInfo($type_info['type_pid']);
    $type_info['parent'] = $parent;
    return $type_info;
}

function mac_data_count($tid=0,$range='all',$flag='vod')
{
    if(!in_array($flag,['vod','art','actor','role','topic','website'])) {
        $flag='vod';
    }
    if(!in_array($range,['all','today','min'])){
        $range='all';
    }

    $data = model('Extend')->dataCount();
    $key = 'type_'.$range.'_'.$tid;
    if($tid>0 && in_array($flag,['vod','art']) ){

    }
    else{
        $key = $flag.'_'.$range;
    }
    return intval($data[$key]);
}

function mac_get_popedom_filter($group_type_list, $type_list = [])
{
    if (empty($type_list)) {
        $type_list = model('Type')->getCache('type_list');
    }
    $type_keys = array_keys($type_list);
    $group_type_list = array_map('trim', explode(',', trim($group_type_list, ',')));
    $group_keys = [];
    foreach ($group_type_list as $group_type) {
        $group_keys = array_merge($group_keys, explode(',', $group_type));
    }
    $group_keys = get_array_unique_id_list($group_keys);
    $cha_keys = array_diff($type_keys, $group_keys);
    return implode(',', $cha_keys);
}

function reset_html_filename($htmlfile)
{
    $htmlpath = './';
    if(substr($htmlfile,strlen($htmlfile)-1,1)=='/'){
        $htmlfile .= 'index';
    }

    if(strpos($htmlfile,'.') ===false){
        $htmlfile .= '.'. $GLOBALS['config']['path']['suffix'];
    }

    if(strpos($htmlfile,'?')!==false){
        $htmlfile = substr($htmlfile,0,strpos($htmlfile,'?'));
    }
    $htmlfile   =   $htmlpath.$htmlfile;
    $htmlfile = str_replace('//','/', $htmlfile);

    if(MAC_PATH !='/'){
        $htmlfile = str_replace('.'.MAC_PATH, './', $htmlfile);
    }

    $htmlfile = str_replace('//','/', $htmlfile);
    return $htmlfile;
}

function mac_unicode_encode($str, $encoding = 'UTF-8', $prefix = '&#', $postfix = ';') {
    $str = iconv($encoding, 'UCS-2', $str);
    $arrstr = str_split($str, 2);
    $unistr = '';
    for($i = 0, $len = count($arrstr); $i < $len; $i++) {
        $dec = hexdec(bin2hex($arrstr[$i]));
        $unistr .= $prefix . $dec . $postfix;
    }
    return $unistr;
}
function mac_unicode_decode($unistr, $encoding = 'UTF-8', $prefix = '&#', $postfix = ';') {
    $arruni = explode($prefix, $unistr);
    $unistr = '';
    for($i = 1, $len = count($arruni); $i < $len; $i++) {
        if (strlen($postfix) > 0) {
            $arruni[$i] = substr($arruni[$i], 0, strlen($arruni[$i]) - strlen($postfix));
        }
        $temp = intval($arruni[$i]);
        $unistr .= ($temp < 256) ? chr(0) . chr($temp) : chr($temp / 256) . chr($temp % 256);
    }
    return iconv('UCS-2', $encoding, $unistr);
}

function mac_escape_param($param)
{
    if(is_array($param)){
        foreach($param as $k=>$v){
            if(!is_numeric($v) && !empty($v)){

                if($GLOBALS['config']['app']['wall_filter'] ==1){
                    $v = mac_unicode_encode($v);
                }
                elseif($GLOBALS['config']['app']['wall_filter'] ==2){
                    $v = '';
                }
                $param[$k] = $v;
            }
        }
    }
    else{
        if(!is_numeric($param) && !empty($param)){
            if($GLOBALS['config']['app']['wall_filter'] ==1){
                $param = mac_unicode_encode($param);
            }
            elseif($GLOBALS['config']['app']['wall_filter'] ==2){
                $param = '';
            }
        }
    }
    return $param;
}

function mac_search_len_check($param)
{
    $psm = array('wd','tag','class','letter','name','state','level','area','lang','version','actor','director','starsign','blood');
    foreach($psm as $v){
        if(mb_strlen($param[$v]) > $GLOBALS['config']['app']['search_len']){
            $param[$v] = mac_substring($param[$v],$GLOBALS['config']['app']['search_len']);
        }
    }
    return $param;
}

function mac_no_cahche()
{
    @header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    @header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
    @header('Cache-Control: no-cache, must-revalidate');
    @header('Pragma: no-cache');
}

function mac_filter_tags($rs)
{
    $rex = array('{:','<script','<iframe','<frameset','<object','onerror');
    if(is_array($rs)){
        foreach($rs as $k2=>$v2){
            if(!is_numeric($v2)){
                $rs[$k2] = str_ireplace($rex,'*',$rs[$k2]);
            }
        }
    }
    else{
        if(!is_numeric($rs)){
            $rs = str_ireplace($rex,'*',$rs);
        }
    }
    return $rs;
}

if (!function_exists('is_really_writable')) {

    /**
     * 判断文件或文件夹是否可写
     * @param string $file 文件或目录
     * @return    bool
     */
    function is_really_writable($file)
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return is_writable($file);
        }
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === false) {
                return false;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return true;
        } elseif (!is_file($file) or ($fp = @fopen($file, 'ab')) === false) {
            return false;
        }
        fclose($fp);
        return true;
    }
}
if (!function_exists('rmdirs')) {

    /**
     * 删除文件夹
     * @param string $dirname  目录
     * @param bool   $withself 是否删除自身
     * @return boolean
     */
    function rmdirs($dirname, $withself = true)
    {
        if (!is_dir($dirname)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        if ($withself) {
            @rmdir($dirname);
        }
        return true;
    }
}
if (!function_exists('copydirs')) {

    /**
     * 复制文件夹
     * @param string $source 源文件夹
     * @param string $dest   目标文件夹
     */
    function copydirs($source, $dest)
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        foreach (
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            if ($item->isDir()) {
                $sontDir = $dest . DS . $iterator->getSubPathName();
                if (!is_dir($sontDir)) {
                    mkdir($sontDir, 0755, true);
                }
            } else {
                copy($item, $dest . DS . $iterator->getSubPathName());
            }
        }
    }
}

