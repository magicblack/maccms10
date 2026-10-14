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
if (!function_exists('mb_ord')) {
    /**
     * PHP 7.2+ 才有 mb_ord；Twig EscaperExtension 在 7.0/7.1 上会致命。
     * vendor/twig 依赖 symfony/polyfill-mbstring，但主库未随 composer 安装该包。
     */
    function mb_ord($string, $encoding = null)
    {
        $string = (string) $string;
        $encoding = $encoding === null ? 'UTF-8' : (string) $encoding;
        if ($encoding !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $string = mb_convert_encoding($string, 'UTF-8', $encoding);
        }
        if ($string === '') {
            return 0;
        }
        if (strlen($string) === 1) {
            return ord($string);
        }
        $s = unpack('C*', substr($string, 0, 4));
        if (!$s) {
            return 0;
        }
        $code = $s[1];
        if ($code >= 0xF0) {
            return (($code - 0xF0) << 18) + (($s[2] - 0x80) << 12) + (($s[3] - 0x80) << 6) + $s[4] - 0x80;
        }
        if ($code >= 0xE0) {
            return (($code - 0xE0) << 12) + (($s[2] - 0x80) << 6) + $s[3] - 0x80;
        }
        if ($code >= 0xC0) {
            return (($code - 0xC0) << 6) + $s[2] - 0x80;
        }

        return $code;
    }
}

/**
 * 视频分类封面方向：使用后台「页面配置」theme[list_cover][vod]；子分类沿父链查找。
 *
 * @param int $typeId 当前分类 ID
 * @return string h|v
 */
function mac_tpl_vod_type_cover($typeId)
{
    $typeId = (int) $typeId;
    $tplconfig = $GLOBALS['mctheme'];
    $theme = isset($tplconfig['theme']) && is_array($tplconfig['theme']) ? $tplconfig['theme'] : [];

    $pageRows = $theme['list_cover']['vod'] ?? [];
    if (!is_array($pageRows)) {
        $pageRows = [];
    }
    $pageMap = [];
    foreach ($pageRows as $row) {
        if (!is_array($row) || empty($row['id'])) {
            continue;
        }
        $id = (string) $row['id'];
        $c = isset($row['cover']) ? (string) $row['cover'] : 'v';
        $pageMap[$id] = ($c === 'h') ? 'h' : 'v';
    }

    $typeList = model('Type')->getCache('type_list');
    if (!is_array($typeList)) {
        $typeList = [];
    }

    $resolve = function ($startId, array $map) use ($typeList) {
        $tid = (int) $startId;
        while ($tid > 0) {
            if (isset($map[(string) $tid])) {
                return $map[(string) $tid];
            }
            if (!isset($typeList[$tid])) {
                break;
            }
            $tid = (int) ($typeList[$tid]['type_pid'] ?? 0);
        }

        return null;
    };

    $hit = $resolve($typeId, $pageMap);
    if ($hit !== null) {
        return $hit;
    }

    return 'v';
}

/**
 * 漫画列表封面方向：theme[list_cover][manga]。
 *
 * @return string h|v
 */
function mac_tpl_manga_cover()
{
    $tplconfig = $GLOBALS['mctheme'];
    $theme = isset($tplconfig['theme']) && is_array($tplconfig['theme']) ? $tplconfig['theme'] : [];
    $raw = isset($theme['list_cover']['manga']) ? (string) $theme['list_cover']['manga'] : 'v';

    return ($raw === 'h') ? 'h' : 'v';
}

/**
 * 小说/资讯列表封面方向：theme[list_cover][art]。
 *
 * @return string h|v
 */
function mac_tpl_art_cover()
{
    $tplconfig = $GLOBALS['mctheme'];
    $theme = isset($tplconfig['theme']) && is_array($tplconfig['theme']) ? $tplconfig['theme'] : [];
    $raw = isset($theme['list_cover']['art']) ? (string) $theme['list_cover']['art'] : 'v';

    return ($raw === 'h') ? 'h' : 'v';
}

/**
 * 分类封面比例可选值（后台「分类管理」type_extend[cover]）。空串 = 跟随模板/组件设置。
 *
 * @return array ratio => [w, h]
 */
function mac_type_cover_options()
{
    return [
        '3-4'   => [3, 4],
        '2-3'   => [2, 3],
        '9-16'  => [9, 16],
        '20-29' => [20, 29],
        '1-1'   => [1, 1],
        '16-9'  => [16, 9],
        '69-40' => [69, 40],
    ];
}

/**
 * 分类单独设置的封面比例：type_extend.cover，子分类未设置时沿父链继承。
 * 用于竖屏短剧等分类在列表/播放记录/收藏/详情页按自身比例显示封面（spike 主题）。
 *
 * @param int $typeId 分类 ID
 * @return array 未设置返回 []；否则 ['ratio' => '3-4', 'w' => 3, 'h' => 4, 'orient' => 'v'|'h']
 */
function mac_type_cover($typeId)
{
    static $memo = [];
    $typeId = (int) $typeId;
    if ($typeId < 1) {
        return [];
    }
    if (isset($memo[$typeId])) {
        return $memo[$typeId];
    }
    $options = mac_type_cover_options();
    $typeList = model('Type')->getCache('type_list');
    if (!is_array($typeList)) {
        $typeList = [];
    }
    $out = [];
    $tid = $typeId;
    $guard = 0;
    while ($tid > 0 && isset($typeList[$tid]) && $guard++ < 10) {
        $ext = $typeList[$tid]['type_extend'] ?? [];
        if (!is_array($ext)) {
            $ext = json_decode((string) $ext, true) ?: [];
        }
        $raw = isset($ext['cover']) ? trim((string) $ext['cover']) : '';
        if ($raw !== '' && isset($options[$raw])) {
            list($w, $h) = $options[$raw];
            $out = ['ratio' => $raw, 'w' => $w, 'h' => $h, 'orient' => ($w > $h) ? 'h' : 'v'];
            break;
        }
        $tid = (int) ($typeList[$tid]['type_pid'] ?? 0);
    }
    $memo[$typeId] = $out;

    return $out;
}

/**
 * vod_duration 自由文本 → 秒；无法识别返回 0。
 * 各采集源格式不一：爬虫 "1:46:46" / "36:24"，资源站 "81分钟" / "USA: 105 分" / "1小时30分钟" /
 * "90 min" / "45分钟/集"，后台手填纯数字按分钟。播放进度（播放记录进度条）以此为片长兜底。
 *
 * @param string $raw
 * @return int
 */
function mac_vod_duration_seconds($raw)
{
    $s = trim(str_replace(['：', '　'], [':', ' '], (string) $raw));
    if ($s === '') {
        return 0;
    }
    $sec = 0;
    if (preg_match('/(?<!\d)(\d{1,2}):(\d{1,2}):(\d{1,2})(?!\d)/', $s, $m)) {
        $sec = $m[1] * 3600 + $m[2] * 60 + $m[3];
    } elseif (preg_match('/^(\d{1,3}):(\d{1,2})$/', $s, $m)) {
        $sec = $m[1] * 60 + $m[2];
    } elseif (preg_match('/^\d{1,4}$/', $s)) {
        $sec = intval($s) * 60;
    } else {
        $h = preg_match('/(\d+(?:\.\d+)?)\s*(?:小时|小時|时|時|hours?|hrs?|h)(?![a-z])/iu', $s, $m) ? floatval($m[1]) : 0;
        $mi = preg_match('/(\d+(?:\.\d+)?)\s*(?:分钟|分鐘|分|minutes?|mins?|m)(?![a-z])/iu', $s, $m) ? floatval($m[1]) : 0;
        $se = preg_match('/(\d+)\s*(?:秒|seconds?|secs?|s)(?![a-z])/iu', $s, $m) ? intval($m[1]) : 0;
        $sec = (int) round($h * 3600 + $mi * 60 + $se);
    }
    // 过滤 "Germany: 1" 之类残缺值与离谱值
    return ($sec >= 10 && $sec <= 86400) ? (int) $sec : 0;
}

/**
 * 播放页进度记忆的片长依据（kit/shared/js/vod-play-progress.js 读取）：
 * - durationSec：vod_duration 解析出的秒数
 * - probe：片长未知时，同一集在其他线路的 m3u8 地址（资源站常见「分享页线路 + m3u8 线路」成对出现），
 *   前端读 m3u8 累加 EXTINF 得到真实片长，跨域分享页线路的估算进度才能换算成百分比
 *
 * @param array $vod 含 vod_duration / vod_play_list 的影片信息
 * @param int   $sid 当前线路
 * @param int   $nid 当前集
 * @return array ['durationSec' => int, 'probe' => string[]]
 */
function mac_vod_progress_meta($vod, $sid, $nid)
{
    $out = ['durationSec' => 0, 'probe' => []];
    if (!is_array($vod)) {
        return $out;
    }
    $out['durationSec'] = mac_vod_duration_seconds($vod['vod_duration'] ?? '');
    if ($out['durationSec'] > 0) {
        return $out;
    }
    $sid = intval($sid);
    $nid = intval($nid);
    $lines = $vod['vod_play_list'] ?? [];
    if (!is_array($lines) || $nid < 1) {
        return $out;
    }
    $cur = $lines[$sid] ?? [];
    $curName = trim((string) ($cur['urls'][$nid]['name'] ?? ''));
    $curCount = intval($cur['url_count'] ?? 0);
    // 当前线路本身是 m3u8（第三方解析页线路）优先，其次其他线路的同一集
    $order = [$sid => $cur] + $lines;
    foreach ($order as $k => $line) {
        if (empty($line['urls'][$nid]['url'])) {
            continue;
        }
        $ep = $line['urls'][$nid];
        // 只认同一集：集名一致，或集名缺失时两条线路集数相同
        $name = trim((string) ($ep['name'] ?? ''));
        if (intval($k) !== $sid
            && ($curName !== '' && $name !== '' ? $name !== $curName : intval($line['url_count'] ?? 0) !== $curCount)) {
            continue;
        }
        $url = trim((string) $ep['url']);
        if (preg_match('#^(https?:)?//[^\s"\'<>]+\.m3u8(\?[^\s"\'<>]*)?$#i', $url)) {
            $out['probe'][] = $url;
            if (count($out['probe']) >= 2) {
                break;
            }
        }
    }
    return $out;
}

/**
 * 主题「视频卡片/列表点击进播放页」是否开启（theme.playlink.btn）。
 * 与 $GLOBALS['mctheme'] / assign('tplconfig') 同源，避免模板里多层数组判断不一致。
 */
function mac_tpl_vod_playlink_on()
{
    $mc = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme']) ? $GLOBALS['mctheme'] : (config('mctheme') ?: []);
    $theme = isset($mc['theme']) && is_array($mc['theme']) ? $mc['theme'] : [];
    if (empty($theme['playlink']) || !is_array($theme['playlink'])) {
        return false;
    }
    $btn = $theme['playlink']['btn'] ?? null;
    if ($btn === null || $btn === '') {
        return false;
    }
    return $btn === '1' || $btn === 1 || $btn === true;
}

/**
 * 播放页「热门标签墙」数据：全站 maccms.app.search_hot + 本片 vod_tag / vod_class / 分类名。
 * 须在 $GLOBALS['config'] 已由 Init 行为赋值后调用（与模板 {php} 不同，避免匿名函数内 `}` 与 Think 标签 `}` 冲突导致整块 PHP 编译失败）。
 *
 * @param array $info mac_label_vod_detail 的 info
 * @return array{enabled:bool,json:string}
 */
function mac_vod_play_tagwall_payload($info)
{
    $info = is_array($info) ? $info : [];
    $split = function ($raw, $pattern) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split($pattern, $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts)));
    };
    $hotRaw = '';
    if (!empty($GLOBALS['config']['app']['search_hot'])) {
        $hotRaw = (string) $GLOBALS['config']['app']['search_hot'];
    }
    $hot = array_slice($split($hotRaw, '/[,，\s]+/u'), 0, 48);
    $local = [];
    if (!empty($info['vod_tag'])) {
        $local = array_merge($local, $split($info['vod_tag'], '/[,，\/|、\s]+/u'));
    }
    if (!empty($info['vod_class'])) {
        $local = array_merge($local, $split($info['vod_class'], '/[,，\/|、\s]+/u'));
    }
    if (count($local) === 0 && !empty($info['type']['type_name'])) {
        $local[] = trim((string) $info['type']['type_name']);
    }
    $local = array_slice(array_values(array_unique(array_filter($local))), 0, 40);
    $enabled = (count($hot) + count($local)) > 0;
    $json = json_encode(['hot' => $hot, 'local' => $local], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

    return ['enabled' => $enabled, 'json' => $json];
}

/**
 * 首页热门推荐 Tab（latest2–latest5）：读取 theme.hotvod.tabs。
 * 无配置时返回空数组，不做默认兜底。
 *
 * @param array $theme mctheme.theme
 * @return array<int, array{id:string,name:string,icon_l:string,icon_b:string}>
 */
function mac_theme_index_hotvod_tabs(array $theme)
{
    return \app\common\util\ThemeTemplateHelper::indexHotvodTabs($theme);
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

/**
 * 安全删除“上传目录”内的文件，防止路径穿越（../）导致任意文件删除。
 *
 * 背景：历史代码使用
 *   $pic = './'.$v['xxx_pic'];
 *   if(file_exists($pic) && (substr($pic,0,8) == "./upload") || count(explode("./",$pic))==1){ unlink($pic); }
 * 由于 && 优先级高于 ||，等价于 (file_exists && 前缀=="./upload") || (count==1)。
 * 只要文件存在且以 ./upload 开头即可删除，导致 ./upload/../xxx 这类穿越路径被删。
 * 同时 $pic 恒以 "./" 开头，count(explode("./",$pic)) 恒 >=2，"|| count==1" 实为死代码。
 *
 * 本函数只删除“真实解析后仍位于 ./upload 目录内”的文件：
 *  - 拒绝远程地址（http/https 等协议）
 *  - 拒绝任何包含 .. 的路径
 *  - 强制路径必须位于 upload/ 之下
 *  - 使用 realpath 解析后再次校验是否落在 upload 目录内
 *
 * @param string $relative 数据库中存储的相对路径，如 upload/vod/2024/a.jpg
 * @return bool 是否成功删除
 */
function mac_safe_unlink_upload($relative)
{
    if (!is_string($relative) || $relative === '') {
        return false;
    }
    // 远程地址（http://、https://、ftp:// 等）不做本地删除
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $relative)) {
        return false;
    }
    // 统一分隔符并去除首部斜杠
    $relative = str_replace('\\', '/', $relative);
    $relative = ltrim($relative, '/');
    // 任何包含 .. 的路径直接拒绝，杜绝路径穿越
    if ($relative === '' || strpos($relative, '..') !== false) {
        return false;
    }
    // 必须位于 upload 目录下
    if ($relative !== 'upload' && strpos($relative, 'upload/') !== 0) {
        return false;
    }
    $base = realpath('./upload');
    $target = realpath('./' . $relative);
    if ($base === false || $target === false) {
        return false;
    }
    // 解析后的真实路径必须仍在 upload 目录内（防止软链接等绕过）
    if ($target !== $base && strpos($target, $base . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }
    if (!is_file($target)) {
        return false;
    }
    return @unlink($target);
}

function mac_arr2file($f,$arr='')
{
    if(is_array($arr)){
        $con = var_export($arr,true);
    } else{
        $con = $arr;
    }
    $con = "<?php\nreturn $con;";
    // 必须把写入结果透传给调用方：file_put_contents 失败时返回 false，
    // 否则 configxxx 等配置页即便磁盘写入失败（磁盘满/权限/只读挂载）也会误报 save_ok。
    $res = mac_write_file($f, $con);
    // opcache清理以实时生效配置
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($f, true);
    }
    return $res;
}

/**
 * 保存配置到 extra 文件（用于主题配置等）
 * @param string $f 文件路径
 * @param array $arr 配置数组
 * @return bool
 */
function mac_save_config_data($f, $arr = '')
{
    if (!is_array($arr)) {
        return false;
    }
    // 必须透传 mac_arr2file 的写入结果：磁盘满/权限/只读挂载导致写入失败时返回 false，
    // 否则 TplConfig 等主题配置页会在实际未落盘的情况下误报保存成功。
    return mac_arr2file($f, $arr);
}

function mac_replace_text($txt,$type=1)
{
    if($type==1){
        return str_replace('#',Chr(13),$txt);
    }
    return str_replace(chr(13),'#',str_replace(chr(10),'',$txt));
}

/**
 * 用 bcrypt 生成密码哈希（含每条哈希独立盐）。写入侧统一用此函数，
 * 永不再产生 md5/无盐哈希。读取侧用 mac_verify_password 兼容遗留形态。
 *
 * @param string $raw 明文密码
 * @return string 60 字符的 bcrypt 哈希（$2y$10$...）
 */
function mac_hash_password($raw)
{
    return password_hash((string)$raw, PASSWORD_BCRYPT);
}

/**
 * 校验明文密码是否匹配存储哈希。兼容 bcrypt 与三种遗留形态：
 *   - bcrypt（$2y$/$2a$/$2b$ 前缀）→ password_verify
 *   - md5(trim(pwd))              → hash_equals
 *   - md5(htmlspecialchars(urldecode(trim(pwd))))  → hash_equals
 *   - 极少数旧库 user_pwd 列为明文 → 直接比对
 * 全部使用常量时间比较，防时序侧信道。此函数只读，永不写回。
 *
 * @param string $raw       用户提交的明文密码
 * @param string $stored    数据库存储的哈希
 * @return bool
 */
function mac_verify_password($raw, $stored)
{
    $stored = (string)$stored;
    if ($stored === '') {
        return false;
    }

    // 新形态：bcrypt
    if (strncmp($stored, '$2', 2) === 0) {
        return password_verify((string)$raw, $stored);
    }

    // 遗留形态：两种 md5 变体
    $raw_str = (string)$raw;
    $formatted = htmlspecialchars(urldecode(trim($raw_str)));
    if (hash_equals($stored, md5($raw_str))) {
        return true;
    }
    if (hash_equals($stored, md5($formatted))) {
        return true;
    }

    // 遗留形态：极少数旧数据 user_pwd 列为明文（User::info / changePwd 历史兼容分支）。
    // 仅当 stored 本身不是 32 位 md5 hex 时才比对明文——否则「提交等于哈希值的字符串」
    // 会被 stored===raw 命中，重新引入 OR 分支被移除的绕过面（漏洞 8）。
    if (!preg_match('/^[0-9a-f]{32}$/', $stored) && ($stored === $raw_str || $stored === $formatted)) {
        return true;
    }

    return false;
}

/**
 * 判断存储哈希是否需要升级为 bcrypt。
 *   - bcrypt → password_needs_rehash（成本因子变更时也触发）
 *   - md5/明文 → 永远需要升级
 * 登录成功后调用此函数；若返回 true 则用 mac_hash_password 覆写，实现惰性迁移。
 *
 * @param string $stored 数据库存储的哈希
 * @return bool
 */
function mac_password_needs_rehash($stored)
{
    $stored = (string)$stored;
    if ($stored === '') {
        return false;
    }
    if (strncmp($stored, '$2', 2) === 0) {
        return password_needs_rehash($stored, PASSWORD_BCRYPT);
    }
    // md5 / 明文 一律需要升级
    return true;
}

/**
 * 判断密码列能否容纳 60 字符的 bcrypt 哈希。
 * 升级窗口期内（代码已升级但 DB 增量脚本未跑）user_pwd/admin_pwd 可能仍是
 * char(32)/varchar(32)，此时惰性迁移写回 60 字符 bcrypt 会在非严格 SQL 模式下
 * 被静默截断成 32 字符，导致该用户此后永久无法登录。写回前先用本函数确认列宽，
 * 不足则跳过迁移（旧 md5 哈希由 mac_verify_password 兼容，登录不受影响，
 * 迁移延后到 DB 升级完成后自然进行）。结果按 table.column 用 static 缓存，
 * 避免每次登录重复查询列信息。
 *
 * @param string $table  模型层的无前缀表名（如 'user'、'admin'）
 * @param string $column 密码列名（如 'user_pwd'、'admin_pwd'）
 * @return bool          列可容纳 >= 60 字符返回 true，否则 false
 */
function mac_pwd_column_fits_hash($table, $column)
{
    static $cache = array();
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $fits = false;
    try {
        // $table/$column 均为代码内常量，非用户输入；SHOW COLUMNS 无法参数化表名。
        // 用 WHERE Field = '列名' 精确匹配，避免 LIKE 把列名里的 '_' 当作通配符
        // （如 user_pwd 的 '_' 会误匹配 userXpwd 等同形列）。
        $full = config('database.prefix') . $table;
        $rows = \think\Db::query("SHOW COLUMNS FROM `{$full}` WHERE Field = '" . addslashes($column) . "'");
        if (!empty($rows) && isset($rows[0]['Type'])) {
            $type = strtolower($rows[0]['Type']);
            if (preg_match('/(?:var)?char\s*\(\s*(\d+)\s*\)/', $type, $m)) {
                $fits = ((int)$m[1] >= 60);
            } elseif (strpos($type, 'text') !== false || strpos($type, 'blob') !== false) {
                // text/blob 家族容量远超 60 字符
                $fits = true;
            }
        }
    } catch (\Exception $e) {
        // 查询失败时保守跳过迁移，宁可延后也不冒截断风险
        $fits = false;
    }
    $cache[$key] = $fits;
    return $fits;
}

/**
 * 按目标密码列的实际宽度选择哈希形态，供所有写入侧（注册/改密/后台新增管理员等）调用。
 * 升级窗口期内（代码已升级但 DB 增量脚本未跑）user_pwd/admin_pwd 可能仍是
 * char(32)/varchar(32)，此时直接写 60 字符 bcrypt 会在非严格 SQL 模式下被静默截断、
 * 严格模式下直接报错，导致新注册/改密的账号密码永久损坏。
 *   - 列宽足够（>= 60）→ 返回 mac_hash_password 的 bcrypt 哈希；
 *   - 列宽不足        → 回退返回 md5((string)$raw)。
 * 回退的 md5 形态被 mac_verify_password 兼容，账号在窗口期仍可正常登入；且
 * mac_password_needs_rehash 会将其判定为需升级，待 DB 升级加宽列后，由既有惰性
 * 迁移路径（登录成功时写回）自然升级为 bcrypt。故 md5 仅是窗口期临时形态，
 * 不构成长期弱哈希风险。
 *
 * @param string $raw    明文密码
 * @param string $table  模型层的无前缀表名（如 'user'、'admin'）
 * @param string $column 密码列名（如 'user_pwd'、'admin_pwd'）
 * @return string        列宽足够返回 60 字符 bcrypt，否则返回 32 字符 md5
 */
function mac_hash_password_for_column($raw, $table, $column)
{
    if (mac_pwd_column_fits_hash($table, $column)) {
        return mac_hash_password($raw);
    }
    return md5((string)$raw);
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
    echo '<script>window.__macJumped=1;setTimeout(function (){location.href="'.$url.'";},'.($sec*1000).');</script><span>'.lang('pause').''.$sec.''.lang('continue_in_second').'  >>>  </span><a href="'.$url.'" >'.lang('browser_jump').'</a><br>';
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
            if(file_exists( './application/admin/view_new/extend/'.$flag.'/'.strtolower($cl) .'.html')) {
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

/**
 * 邮箱 / 短信验证码通道是否可用（前台绑定、找回密码入口据此显隐，#272）。
 * 邮件出厂配置自带占位账号 test@qq.com，只看 type 非空会误判为已配置。
 *
 * @return array ['email' => bool, 'phone' => bool]
 */
function mac_msg_channels()
{
    static $res = null;
    if ($res !== null) {
        return $res;
    }
    $email = isset($GLOBALS['config']['email']) ? $GLOBALS['config']['email'] : [];
    $email_ok = false;
    if (!empty($email['type'])) {
        $conf = isset($email[strtolower($email['type'])]) ? $email[strtolower($email['type'])] : [];
        $user = isset($conf['username']) ? trim($conf['username']) : '';
        $email_ok = !empty($conf['host']) && $user !== '' && !empty($conf['password']) && strtolower($user) !== 'test@qq.com';
    }
    $res = [
        'email' => $email_ok,
        'phone' => !empty($GLOBALS['config']['sms']['type']),
    ];
    return $res;
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

/**
 * 推送告警到指定渠道。
 *
 * driver 定位方式与 mac_send_mail() / mac_send_sms() 保持一致：
 * app\common\extend\push\<Channel>::submit()。
 *
 * @param string $channel webhook|telegram|dingtalk|wecom|serverchan
 * @param string $title
 * @param string $content
 * @param array  $context 结构化字段（webhook 会原样带进 JSON）
 * @param array  $config  monitor 配置段
 * @return array ['code'=>1|非1,'msg'=>string]
 */
function mac_send_push($channel, $title, $content, array $context = [], array $config = [])
{
    $channel = preg_replace('/[^a-z0-9]/', '', strtolower((string)$channel));
    if ($channel === '') {
        return ['code' => 999, 'msg' => lang('admin/monitor/push_channel_invalid')];
    }
    $cp = 'app\\common\\extend\\push\\' . ucfirst($channel);
    if (!class_exists($cp)) {
        return ['code' => 991, 'msg' => lang('admin/monitor/push_driver_not_found')];
    }
    $c = new $cp;
    return $c->submit($title, $content, $context, $config);
}

/**
 * 规范化域名输入为纯主机名（可带端口），用于与 $_SERVER['HTTP_HOST'] 比对。
 * 站长常按习惯填 http://m.a.com/，原样存下来会与 HTTP_HOST 永远匹配不上；
 * 同时限定字符集，避免把引号等注入到模板输出的 JS 变量里。
 * 非法输入返回空串（等同未配置）。
 */
function mac_domain_host($url)
{
    $host = strtolower(trim((string)$url));
    if ($host === '') {
        return '';
    }
    $host = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $host);
    $host = ltrim($host, '/');
    $host = substr($host, 0, strcspn($host, '/?#'));
    if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        return '';
    }
    return $host;
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
    // 友链校验目标为用户填写的第三方站点，可能自签/过期/域名不匹配，显式关闭校验以保兼容。
    $html = mac_curl_get($url, [], '', false);
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

/**
 * 当前生效主题的模板引擎（think|twig）。
 *
 * 主题目录从 $GLOBALS['MAC_ROOT_TEMPLATE'] 反推而不是直接读 config：
 * 后台生成 WAP 静态页时 admin/Make 会把这个全局改指向 mob_template_dir，
 * 只有跟着它走才不会拿 PC 主题的引擎去渲染手机主题的模板。
 *
 * @return string
 */
function mac_tpl_engine()
{
    $root = isset($GLOBALS['MAC_ROOT_TEMPLATE']) ? (string)$GLOBALS['MAC_ROOT_TEMPLATE'] : '';
    if ($root === '') {
        return 'think';
    }
    $parts = preg_split('#[\\\\/]+#', trim(str_replace('\\', '/', $root), '/'));
    $count = count($parts);
    if ($count < 2) {
        return 'think';
    }
    // 形状是 template/<主题>/<html_dir>/，主题名取倒数第二段
    return \app\common\behavior\Init::themeEngine($parts[$count - 2]);
}

/**
 * 当前主题的模板后缀（含点）。供「模板不存在」一类提示使用，
 * 别再对 Twig 主题固定报 .html 误导站长。
 *
 * @return string
 */
function mac_tpl_suffix()
{
    return mac_tpl_engine() === 'twig' ? '.twig' : '.html';
}

function mac_tpl_file($templateFile)
{
    if (empty($templateFile)) {
        return '';
    }
    $templateFile = str_replace('.html', '', $templateFile);
    $base = $GLOBALS['MAC_ROOT_TEMPLATE'] . $templateFile;

    // Twig 主题（info.ini 宣告 engine=twig）的模板是 .twig。旧的静态生成链路
    // 只认 .html，会把 PB 主题的 RSS/SiteMap/网站地图判成「模板不存在」。
    // 仍保留 .html 回落：主题可以为「生成专用」页单独放一份 Think 语法模板。
    if (mac_tpl_engine() === 'twig' && is_file($base . '.twig')) {
        return $base . '.twig';
    }
    return $base . '.html';
}

function mac_tpl_exists($templateFile)
{
    $path = mac_tpl_file($templateFile);
    return $path !== '' && is_file($path);
}

function mac_make_paging_size($html, $tag, $default = 0)
{
    if ($html === false || $html === '' || $html === null) {
        return $default;
    }
    $tag = preg_quote($tag, '/');
    $labelRule = '\{maccms:' . $tag . '([^}]*paging="yes"[^}]*)\}';
    $labelRule = mac_buildregx($labelRule, 'is');
    if (!preg_match_all($labelRule, $html, $arr)) {
        return $default;
    }
    foreach ($arr[1] as $attrs) {
        if (preg_match('/\bnum=["\'](\d+)["\']/', $attrs, $numMatch)) {
            return intval($numMatch[1]);
        }
    }
    return $default;
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
    $page_param['page_num_min'] = count($page_num) ? min($page_num) : 1;
    $page_param['page_num_max'] = count($page_num) ? max($page_num) : 1;
    $page_param['page_url'] = $page_url;

    return $page_param;
}

// CurlPOST数据提交-----------------------------------------
function mac_curl_post($url,$data,$heads=array(),$cookie='',$verify_ssl=true)
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
    if($verify_ssl){
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // 禁跟随重定向：防止 302 空空 host 校验 / DNS rebinding（纵深防御）。
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    }
    else{
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    if(!empty($cookie)){
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if(count($heads)>0){
        curl_setopt ($ch, CURLOPT_HTTPHEADER , $heads );
    }
    $response = @curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch); //关闭curl链接
    if($verify_ssl && $errno){
        return false;
    }
    return $response;//显示返回信息
}
// CurlPOST数据提交-----------------------------------------
function mac_curl_get($url,$heads=array(),$cookie='',$verify_ssl=true)
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
    if($verify_ssl){
        // 强制对端 TLS 校验：阻断 MITM 替换升级包/校验值（漏洞 7）。
        // CA 依赖 libcurl 系统默认（Debian/RedHat 均自带 /etc/ssl/certs/ca-certificates.crt）。
        // 禁跟随重定向：防止 302 空空 host 校验 / DNS rebinding（纵深防御）。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    }
    else{
        // 默认分支保持原行为，兼容采集/插件商店等第三方源（证书可能自签/过期/域名不匹配）。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
    }
    if(!empty($cookie)){
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if(count($heads)>0){
        curl_setopt ($ch, CURLOPT_HTTPHEADER , $heads );
    }
    $response = @curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch); //关闭curl链接
    if($verify_ssl && $errno){
        // 强制校验模式下 TLS 失败（对端不受信/被篡改）-> 显式失败，禁止继续升级流程。
        return false;
    }
    return $response;//显示返回信息
}


function mac_substring($str, $lenth, $start=0)
{
    $str = mac_scalar_string($str);
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
        $mangatype = str_replace([chr(10),chr(13)],['','#'],isset($config['mangatype']) ? $config['mangatype'] : '');

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

        if(!empty($mangatype)){
            $type_arr = explode('#',$mangatype);
            foreach($type_arr as $k=>$v){
                if(strpos($v,'=')!==false){
                    list($from, $to) = explode('=', $v);
                    $data['mangatype'][$to] = $from;
                }
            }
        }
        if(empty($data['mangatype'])){
            $data['mangatype'] = [];
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
    if(!empty($data['mangatype'])){
        foreach($data['mangatype'] as $k=>$v){
            $data['mangatype'][$k] = (int)$type_names[$v];
        }
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
    $final = '0.0.0.0';

    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

    $trustedProxies = [];
    $tpCfg = config('maccms.trusted_proxies');
    if (is_string($tpCfg)) {
        $tpCfg = trim($tpCfg);
        if ($tpCfg !== '') {
            foreach (explode(',', $tpCfg) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $trustedProxies[] = $p;
                }
            }
        }
    } elseif (is_array($tpCfg)) {
        foreach ($tpCfg as $p) {
            $p = trim((string)$p);
            if ($p !== '') {
                $trustedProxies[] = $p;
            }
        }
    }

    $isTrustedProxy = false;
    if ($remoteAddr !== '' && !empty($trustedProxies)) {
        foreach ($trustedProxies as $proxy) {
            if (mac_ip_in_cidr($remoteAddr, $proxy)) {
                $isTrustedProxy = true;
                break;
            }
        }
    }

    if ($isTrustedProxy) {
        $headerCandidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_ALI_CDN_REAL_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_PROXY_USER',
        ];
        foreach ($headerCandidates as $key) {
            $val = isset($_SERVER[$key]) ? trim((string)$_SERVER[$key]) : '';
            if ($key === 'HTTP_X_REAL_IP' && $val === '') {
                $val = trim((string)getenv('HTTP_X_REAL_IP'));
            }
            if ($val === '') {
                continue;
            }
            if (filter_var($val, FILTER_VALIDATE_IP) !== false) {
                $final = $val;
                return $final;
            }
            if (strpos($val, ',') !== false) {
                foreach (explode(',', $val) as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                        $final = $candidate;
                        return $final;
                    }
                }
            }
        }
    }

    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
        $final = $remoteAddr;
    }

    return $final;
}

/**
 * 判断 IP 是否落在给定的信任代理条目内。条目可以是精确 IP（IPv4/IPv6）
 * 或 CIDR 网段（如 172.16.0.0/12、2001:db8::/32）。兼容 PHP 7.0。
 *
 * @param string $ip    待判定的 IP
 * @param string $range 精确 IP 或 CIDR
 * @return bool
 */
function mac_ip_in_cidr($ip, $range)
{
    $ip = trim((string)$ip);
    $range = trim((string)$range);
    if ($ip === '' || $range === '') {
        return false;
    }
    // 无掩码：按精确 IP 比较
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }

    $parts = explode('/', $range, 2);
    $subnet = trim($parts[0]);
    $bits = isset($parts[1]) ? (int)trim($parts[1]) : -1;

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    // inet_pton 失败或不同协议族（二进制长度不同）直接判否
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }
    if ($bits === 0) {
        return true;
    }

    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;
    if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
        return false;
    }
    if ($remainder > 0) {
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        if ((ord($ipBin[$bytes]) & ord($mask)) !== (ord($subnetBin[$bytes]) & ord($mask))) {
            return false;
        }
    }
    return true;
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

/**
 * 与 index/ajax/digg 一致：当前请求是否已对该内容点过赞（Cookie）
 */
function mac_user_has_digg($mid, $id)
{
    $mid = (int) $mid;
    $id = (int) $id;
    if ($id < 1 || $mid < 1) {
        return 0;
    }
    $pre = mac_get_mid_code($mid);
    if ($pre === null || $pre === '') {
        return 0;
    }
    $cookie = $pre . '-digg-' . $id;

    return !empty(cookie($cookie)) ? 1 : 0;
}

/**
 * 登录用户是否已收藏（ulog_type=2）
 *
 * @return array{is_fav:int,fav_ulog_id:int}
 */
function mac_user_fav_state($userId, $ulogMid, $rid)
{
    $userId = (int) $userId;
    $ulogMid = (int) $ulogMid;
    $rid = (int) $rid;
    if ($userId < 1 || $rid < 1 || $ulogMid < 1) {
        return ['is_fav' => 0, 'fav_ulog_id' => 0];
    }
    $row = model('Ulog')->where([
        'user_id'   => $userId,
        'ulog_mid'  => $ulogMid,
        'ulog_type' => 2,
        'ulog_rid'  => $rid,
    ])->field('ulog_id')->find();
    if (!empty($row['ulog_id'])) {
        return ['is_fav' => 1, 'fav_ulog_id' => (int) $row['ulog_id']];
    }

    return ['is_fav' => 0, 'fav_ulog_id' => 0];
}

/**
 * 當前登入用戶的收藏映射：[ulog_rid => ulog_id]，按請求快取。
 *
 * 存在的理由：template/default 的卡片組件（widget/vod_box.html、vod_box_show.html、
 * vod_box_banner.html）各自在**卡片迴圈內**用 {php} 直接查 ulog，
 * 一頁 24 張卡片就是 24 次全量查詢。Twig 主題沒有 {php} 逃生口，
 * 這個資料必須由這裡以「一次查詢 + 請求級快取」的方式提供。
 *
 * 只讀當前登入用戶自己的收藏，不接受外部 user_id，避免變成越權查詢入口。
 *
 * @param int $ulogMid 內容模型 id（1=視頻 2=文章 8=漫畫…）
 * @return array [rid => ulog_id]
 */
function mac_user_fav_map($ulogMid = 1)
{
    static $cache = [];

    $ulogMid = (int) $ulogMid;
    $userId = 0;
    if (!empty($GLOBALS['user']['user_id'])) {
        $userId = (int) $GLOBALS['user']['user_id'];
    }
    if ($userId < 1 || $ulogMid < 1) {
        return [];
    }

    $key = $userId . '-' . $ulogMid;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $map = [];
    try {
        $map = model('Ulog')->where([
            'user_id'   => $userId,
            'ulog_mid'  => $ulogMid,
            'ulog_type' => 2,
        ])->column('ulog_id', 'ulog_rid');
    } catch (\Exception $e) {
        $map = [];
    }
    if (!is_array($map)) {
        $map = [];
    }

    $cache[$key] = $map;
    return $map;
}

/**
 * 某條內容的收藏記錄 id；未收藏或未登入回 0。
 * 模板用它決定收藏按鈕的狀態與 data-uid。
 *
 * @param int $rid
 * @param int $ulogMid
 * @return int
 */
function mac_user_fav_id($rid, $ulogMid = 1)
{
    $rid = (int) $rid;
    if ($rid < 1) {
        return 0;
    }
    $map = mac_user_fav_map($ulogMid);
    return isset($map[$rid]) ? (int) $map[$rid] : 0;
}

/**
 * 當前登入用戶的點讚映射：[ulog_rid => ulog_id]，按請求快取。
 * 與 mac_user_fav_map 同構（ulog_type=6=點讚），供卡片/清單頁一次查詢。
 *
 * @param int $ulogMid 內容模型 id（1=視頻 2=文章 8=漫畫…）
 * @return array [rid => ulog_id]
 */
function mac_user_like_map($ulogMid = 1)
{
    static $cache = [];

    $ulogMid = (int) $ulogMid;
    $userId = 0;
    if (!empty($GLOBALS['user']['user_id'])) {
        $userId = (int) $GLOBALS['user']['user_id'];
    }
    if ($userId < 1 || $ulogMid < 1) {
        return [];
    }

    $key = $userId . '-' . $ulogMid;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $map = [];
    try {
        $map = model('Ulog')->where([
            'user_id'   => $userId,
            'ulog_mid'  => $ulogMid,
            'ulog_type' => 6,
        ])->column('ulog_id', 'ulog_rid');
    } catch (\Exception $e) {
        $map = [];
    }
    if (!is_array($map)) {
        $map = [];
    }

    $cache[$key] = $map;
    return $map;
}

/**
 * 某條內容目前登入用戶的點讚記錄 id；未點讚或未登入回 0。
 * 模板用它決定點讚按鈕的狀態與 data-uid。
 *
 * @param int $rid
 * @param int $ulogMid
 * @return int
 */
function mac_user_like_id($rid, $ulogMid = 1)
{
    $rid = (int) $rid;
    if ($rid < 1) {
        return 0;
    }
    $map = mac_user_like_map($ulogMid);
    return isset($map[$rid]) ? (int) $map[$rid] : 0;
}

/**
 * 某條內容目前的點讚總數（不分是誰按的，未登入訪客也看得到這個數字）。
 * 直接對 ulog 表做 COUNT，與收藏一樣不額外維護冗餘計數欄位（見 mac_user_fav_map 的設計說明）。
 *
 * @param int $rid
 * @param int $ulogMid
 * @return int
 */
function mac_content_like_count($rid, $ulogMid = 1)
{
    $rid = (int) $rid;
    $ulogMid = (int) $ulogMid;
    if ($rid < 1 || $ulogMid < 1) {
        return 0;
    }
    try {
        return (int) model('Ulog')->where([
            'ulog_mid'  => $ulogMid,
            'ulog_type' => 6,
            'ulog_rid'  => $rid,
        ])->count();
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * 取某個廣告位裡「當前有效」的投放列表。
 *
 * template/default 的 ads/ads_banner1.html、ads_banner_swiper.html、ads_bottom.html、
 * ads_icon1.html 各自用 {php} 重複了同一段過濾邏輯（active=on + 未過期 + 必填欄位齊全）。
 * 這裡收斂成一份，Twig 主題直接 {% for ad in mac_ad_slot('banner1') %}。
 *
 * @param string $slot     廣告位 key，如 banner1 / banner_swiper / bottom / icon1
 * @param array  $required 必填欄位，缺任一則該條不展示（預設不限制）
 * @return array
 */
function mac_ad_slot($slot, array $required = [])
{
    return \app\common\util\ThemeTemplateHelper::adSlot($slot, $required);
}

/**
 * 解析主題資產的 URL，依 子主題 → 父主題 → template/_shared/kit/shared/ 的順序回落。
 *
 * 為什麼需要它：模板可以靠 loader 的搜尋路徑自動回落到父主題，但資產不行 ——
 * CSS/JS/圖片是 web server 直接服務的 HTTP 路徑，沒有「找不到就換一個目錄」這回事。
 * 子主題若只想換 logo，其餘沿用底座的圖，就得有個東西幫它算出正確的 URL。
 *
 * 找不到時回傳子主題的路徑（而不是空字串）：讓它在瀏覽器裡以 404 現形，
 * 比靜默輸出 src="" 好查 —— 後者在頁面上看起來只是「圖沒出來」。
 *
 * @param string $rel 相對 asset/ 的路徑，如 'img/logo.png'、'css/theme.css'
 * @return string 可直接放進 src/href 的 URL
 */
function mac_theme_asset($rel)
{
    return \app\common\util\ThemeTemplateHelper::themeAsset($rel);
}

/**
 * 某內容模型下「啟用中的頂級分類」，按 type_sort 升序。
 *
 * module/art_index_vars.html 用 {php} 對 mid=2 做了這件事，
 * index/controller/Label.php::labelRankTypesForPage() 對 mid=1 又做了一次。
 * 收斂成一份供 Twig 主題直接取用。
 *
 * @param int $mid   內容模型 id（1=視頻 2=文章 8=漫畫…）
 * @param int $limit 取前幾個，0=不限
 * @return array
 */

/**
 * Page Builder 轮播/卡片共用取数：bind 决定数据源，返回归一化 list。
 * 字段：name / pic / pic_wide / url / blurb / class / remarks / bind / type_id
 *
 * @param array $args bind(vod|manga|art) num by type order
 * @return array{list: array<int,array>, bind: string, total?: int}
 */
function mac_pb_feed($args = [])
{
    return \app\common\util\PageBuilderFeed::fetch($args);
}

/**
 * 文案仍为 i18n key 默认中文时返回 key，否则返回空串（自定义文案不挂 data-lang）。
 * 见 ThemeTemplateHelper::i18nKey()。
 */
function mac_i18n_key($text, $key)
{
    return \app\common\util\ThemeTemplateHelper::i18nKey($text, $key);
}

/**
 * 内容模块入口名 {name, lang, type_id}：唯一顶级分类被改名时跟随分类名。
 * 见 ThemeTemplateHelper::moduleLabel()。
 */
function mac_module_label($mid)
{
    return \app\common\util\ThemeTemplateHelper::moduleLabel($mid);
}

function mac_top_types($mid, $limit = 0)
{
    $mid = (int) $mid;
    $limit = (int) $limit;
    if ($mid < 1) {
        return [];
    }

    $list = model('Type')->getCache('type_list');
    if (!is_array($list)) {
        return [];
    }

    $out = [];
    foreach ($list as $info) {
        if (!is_array($info)) {
            continue;
        }
        if ((int) (isset($info['type_mid']) ? $info['type_mid'] : 0) !== $mid) {
            continue;
        }
        if ((int) (isset($info['type_pid']) ? $info['type_pid'] : -1) !== 0) {
            continue;
        }
        if (isset($info['type_status']) && (int) $info['type_status'] !== 1) {
            continue;
        }
        $out[] = $info;
    }

    usort($out, function ($a, $b) {
        $sa = (int) (isset($a['type_sort']) ? $a['type_sort'] : 0);
        $sb = (int) (isset($b['type_sort']) ? $b['type_sort'] : 0);
        if ($sa === $sb) {
            return 0;
        }
        return $sa < $sb ? -1 : 1;
    });

    if ($limit > 0) {
        $out = array_slice($out, 0, $limit);
    }
    return $out;
}

/**
 * 直播頻道列表。
 *
 * 為什麼需要這個：直播是唯一沒有 taglib 資料標籤的前台內容型別
 * （application/common/taglib/Maccms.php 沒有 {maccms:live}），
 * template/default 的 live/show 因此是**純前端**的 —— 靠
 * template/default/asset/js/live-show.js 打 api.php 再拼 DOM，
 * 並且綁死 .live-show__* 這組 class。那套 JS 屬於 default 主題資產，
 * 換主題就失效，所以無法作為「共用契約」提供給其它主題。
 *
 * 這裡補一個伺服器端資料函式，讓任何主題都能直出直播列表，
 * 條件與排序對齊 application/api/controller/Live.php 的既有行為。
 *
 * @param array $args cate/limit/page/order/level
 * @return array ['list'=>[], 'total'=>int, 'page'=>int, 'limit'=>int, 'pageurl'=>string]
 */
function mac_live_list($args = [])
{
    if (!is_array($args)) {
        $args = [];
    }
    $where = [];
    $where['live_status'] = ['eq', 1];
    if (!empty($args['cate'])) {
        $where['cate_id'] = ['eq', (int) $args['cate']];
    }
    if (!empty($args['name'])) {
        $where['live_name'] = ['like', '%' . htmlspecialchars($args['name']) . '%'];
    }

    $orderMap = [
        'sort'      => 'live_sort desc, live_id asc',
        'hits'      => 'live_hits desc',
        'hits_day'  => 'live_hits_day desc',
        'hits_week' => 'live_hits_week desc',
        'time'      => 'live_time desc',
        'id'        => 'live_id desc',
    ];
    $by    = isset($args['by']) ? (string) $args['by'] : 'sort';
    $order = isset($orderMap[$by]) ? $orderMap[$by] : $orderMap['sort'];

    $limit = isset($args['num']) && (int) $args['num'] > 0 ? (int) $args['num'] : 24;
    $page  = isset($args['page']) && (int) $args['page'] > 0 ? (int) $args['page'] : 1;

    $res = model('Live')->listData($where, $order, $page, $limit);
    $list = isset($res['list']) ? $res['list'] : [];
    if (!is_array($list)) {
        $list = [];
    }
    // 每個頻道的播放地址是 `名稱$地址#名稱$地址` 串，模板不該自己 split
    $live = model('Live');
    foreach ($list as $k => $row) {
        $raw = isset($row['live_url']) ? $row['live_url'] : '';
        $list[$k]['urls'] = $live->parseUrlList($raw);
    }

    return [
        'list'    => $list,
        'total'   => isset($res['total']) ? (int) $res['total'] : 0,
        'page'    => $page,
        'limit'   => $limit,
        'pageurl' => mac_url(
            !empty($args['pageurl']) ? (string) $args['pageurl'] : 'live/show',
            array_merge(
                array('page' => 'PAGELINK'),
                !empty($args['cate']) ? array('cate_id' => (int) $args['cate']) : array()
            )
        ),
    ];
}

/**
 * 排行榜多榜單解析：讀後台 TplConfig 的 theme.rank.boards（每個榜單自帶分類/標題/數量/
 * 啟停），供 spike 主題的 rank_theme_boards page-builder 元件直出。刻意獨立於
 * app\index\controller\Label::labelRankTypesForPage() ——後者是 default 主題舊格式
 * （type_id + type_name，全榜統一數量），兩邊資料形狀不同，共用會連帶改到 default
 * 的輸出，不想承擔那個回歸風險。
 *
 * 每個榜單依所選分類的 type_mid 解析出內容類型（1=視頻 2=文章/小說 12=漫畫），一併
 * 回傳供 rank_theme_boards → np2_rank_board → rank_list 逐層透傳；分類留空（全站）時
 * 沿用舊行為視為視頻榜。後台分類選擇器（type-picker）本就支援跨 mid 選分類，這裡不再
 * 把非視頻分類擋掉。
 *
 * 未設定過 boards（新裝或尚未保存過一次配置）時，依序退回視頻／文章／漫畫各自的一級
 * 分類（每類最多 6 個，每個 6 條），確保新裝站點排行榜頁預設就能看到三種內容類型。
 *
 * @return array [{type_id:int, title:string, num:int, mid:int}]
 */
function mac_rank_boards()
{
    $mc = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme'])
        ? $GLOBALS['mctheme']
        : (config('mctheme') ?: ['theme' => []]);
    $rankCfg = (isset($mc['theme']['rank']) && is_array($mc['theme']['rank'])) ? $mc['theme']['rank'] : [];
    $pageTitle = trim((string) ($rankCfg['title'] ?? ''));
    if ($pageTitle === '') {
        $pageTitle = '排行榜';
    }

    $typeModel = model('Type');
    $allowedMids = [1, 2, 12];
    $boardsCfg = (isset($rankCfg['boards']) && is_array($rankCfg['boards'])) ? $rankCfg['boards'] : [];
    $out = [];
    foreach ($boardsCfg as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (isset($row['status']) && (string) $row['status'] === '0') {
            continue;
        }
        $typeId = isset($row['type_id']) ? max(0, (int) $row['type_id']) : 0;
        $typeName = lang('all');
        $mid = 1;
        if ($typeId > 0) {
            $info = $typeModel->getCacheInfo($typeId);
            $infoMid = (int) ($info['type_mid'] ?? 0);
            if (empty($info) || !in_array($infoMid, $allowedMids, true)) {
                // 分类已被删除或不属于可排行的内容类型（视频/文章/漫画），跳过该榜单
                continue;
            }
            $mid = $infoMid;
            $typeName = (string) ($info['type_name'] ?? $typeName);
        }
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = $typeName . $pageTitle;
        }
        $num = isset($row['num']) ? (int) $row['num'] : 6;
        $num = max(2, min(30, $num ?: 6));
        $out[] = [
            'type_id' => $typeId,
            'title'   => $title,
            'num'     => $num,
            'mid'     => $mid,
        ];
        if (count($out) >= 20) {
            break;
        }
    }

    if (!empty($out)) {
        return $out;
    }

    // 兜底：未配置过 boards（如尚未在后台保存过一次）时，依次取视频/文章/漫画的一级分类
    $typeListCache = $typeModel->getCache('type_list');
    $fallbackByMid = [1 => [], 2 => [], 12 => []];
    if (is_array($typeListCache)) {
        foreach ($typeListCache as $typeInfo) {
            if (!is_array($typeInfo)) {
                continue;
            }
            if ((int) ($typeInfo['type_pid'] ?? 0) !== 0) {
                continue;
            }
            $mid = (int) ($typeInfo['type_mid'] ?? 0);
            if (!in_array($mid, $allowedMids, true)) {
                continue;
            }
            if (isset($typeInfo['type_status']) && (int) $typeInfo['type_status'] !== 1) {
                continue;
            }
            $fallbackByMid[$mid][] = $typeInfo;
        }
        foreach ($fallbackByMid as $mid => &$list) {
            usort($list, function ($a, $b) {
                return ((int) ($a['type_sort'] ?? 0)) <=> ((int) ($b['type_sort'] ?? 0));
            });
            $list = array_slice($list, 0, 6);
        }
        unset($list);
    }

    if (count($fallbackByMid[1]) + count($fallbackByMid[2]) + count($fallbackByMid[12]) < 1) {
        return [['type_id' => 0, 'title' => $pageTitle, 'num' => 6, 'mid' => 1]];
    }
    foreach ($allowedMids as $mid) {
        foreach ($fallbackByMid[$mid] as $typeInfo) {
            $out[] = [
                'type_id' => (int) ($typeInfo['type_id'] ?? 0),
                'title'   => (string) ($typeInfo['type_name'] ?? '') . $pageTitle,
                'num'     => 6,
                'mid'     => $mid,
            ];
        }
    }
    return $out;
}

/**
 * 直播分類（頻道分組）列表。
 *
 * @return array
 */
function mac_live_cates()
{
    $list = model('Live')->categoryList(['cate_status' => ['eq', 1]]);
    return is_array($list) ? $list : [];
}

/**
 * 單一直播頻道詳情，含解析好的播放地址清單。
 *
 * @param int $id
 * @return array 找不到時回傳空陣列
 */
function mac_live_info($id)
{
    $id = (int) $id;
    if ($id < 1) {
        return [];
    }
    $res = model('Live')->infoData(['live_id' => ['eq', $id], 'live_status' => ['eq', 1]]);
    if (empty($res['code']) || $res['code'] != 1 || empty($res['info'])) {
        return [];
    }
    $info = $res['info'];
    $info['urls'] = model('Live')->parseUrlList(isset($info['live_url']) ? $info['live_url'] : '');
    return $info;
}

/**
 * 頁面級 SEO 結構資料：canonical / og / twitter。
 *
 * 覆蓋範圍說明（v1）：只產出**與頁面結構有關**的部分。
 * 標題與描述本身走既有管道 —— 詳情頁由 All::mergeDetailSeoIntoMaccms() 寫進
 * $maccms['page_detail_*']，其餘頁面用 $maccms['site_*']，模板直接讀即可。
 *
 * ⚠️ template/default/html/widget/seos.html 另有一套按 aid 分支的 32 種
 * keywords/description 文案矩陣與完整 JSON-LD，**不在本函式範圍內**，
 * 見 docs/theme-contract-v1.md 的「已知缺口」。
 *
 * @param array|null $obj 詳情頁的內容物件（列表頁傳 null）
 * @return array
 */
function mac_seo_meta($obj = null)
{
    $site = isset($GLOBALS['config']['site']) ? $GLOBALS['config']['site'] : [];
    $siteName = isset($site['site_name']) ? (string) $site['site_name'] : '';

    $canonical = '';
    if (function_exists('request')) {
        try {
            $req = request();
            if (is_object($req) && method_exists($req, 'url')) {
                $canonical = (string) $req->url(true);
            }
        } catch (\Exception $e) {
            $canonical = '';
        }
    }

    $title = $siteName;
    $desc = isset($site['site_description']) ? (string) $site['site_description'] : '';
    $image = '';
    $ogType = 'website';

    if (is_array($obj)) {
        foreach (['vod_name', 'art_name', 'manga_name', 'topic_name', 'actor_name'] as $f) {
            if (!empty($obj[$f])) {
                $title = (string) $obj[$f] . ($siteName !== '' ? ' - ' . $siteName : '');
                $ogType = 'article';
                break;
            }
        }
        foreach (['vod_blurb', 'art_blurb', 'manga_blurb', 'topic_blurb'] as $f) {
            if (!empty($obj[$f])) {
                $desc = trim(strip_tags((string) $obj[$f]));
                break;
            }
        }
        foreach (['vod_pic', 'art_pic', 'manga_pic', 'topic_pic', 'actor_pic'] as $f) {
            if (!empty($obj[$f])) {
                $image = mac_url_img($obj[$f]);
                break;
            }
        }
    }

    return [
        'canonical' => $canonical,
        'og' => [
            'og:type'        => $ogType,
            'og:title'       => $title,
            'og:description' => $desc,
            'og:url'         => $canonical,
            'og:image'       => $image,
        ],
        'twitter' => [
            'twitter:card'        => $image !== '' ? 'summary_large_image' : 'summary',
            'twitter:title'       => $title,
            'twitter:description' => $desc,
            'twitter:image'       => $image,
        ],
    ];
}

function mac_get_aid($controller,$action='')
{
    $controller=strtolower($controller);
    $action=strtolower($action);
    $key = $controller.'/'.$action;

    $arr=['index'=>1,'map'=>2,'rss'=>3,'gbook'=>4,'comment'=>5,'user'=>6,'label'=>7,'vod'=>10,'art'=>20,'manga'=>120,'topic'=>30,'actor'=>80,'role'=>90,'plot'=>100,'website'=>110,'live'=>130];
    $res = isset($arr[$controller]) ? $arr[$controller] : 0;

    // https://github.com/magicblack/maccms10/issues/960
    $arr=[
        'vod/type'=>11,'vod/show'=>12,'vod/search'=>13,'vod/search_hub'=>13,'vod/detail'=>14,'vod/play'=>15,'vod/down'=>16,'vod/role'=>17,'vod/plot'=>18,
        'art/type'=>21,'art/show'=>22,'art/search'=>23,'art/detail'=>24,'art/read'=>25,
        'manga/type'=>121,'manga/show'=>122,'manga/search'=>123,'manga/detail'=>124,'manga/play'=>125,
        'topic/search'=>33,'topic/detail'=>34,
        'actor/type'=>81,'actor/show'=>82,'actor/search'=>83,'actor/detail'=>84,
        'role/show'=>92,'role/search'=>93,'role/detail'=>94,
        'plot/search'=>103,'plot/detail'=>104,
        'website/type'=>111,'website/show'=>112,'website/search'=>113,'website/detail'=>114,
        'live/show'=>131,'live/play'=>132,
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
        6 => lang('like'),
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
        10 => lang('plog_sign_milestone_reward'),
        11 => lang('plog_task_sign_reward'),
        12 => lang('plog_mall_exchange'),
        13 => lang('plog_seckill'),
        14 => lang('plog_invite_reward'),
        15 => lang('plog_invite_vip'),
    ];
    return isset($arr[$data]) ? $arr[$data] : '';
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

function mac_get_mall_goods_type_text($data)
{
    $arr = [
        'vip' => lang('mall/type_vip'),
        'card' => lang('mall/type_card'),
        'download_quota' => lang('mall/type_download_quota'),
    ];
    return isset($arr[$data]) ? $arr[$data] : '';
}

function mac_get_order_status_text($data)
{
    $arr = [
        0 => lang('not_paid'),
        1 => lang('paid'),
    ];
    return $arr[$data];
}

function mac_get_user_portrait($user_id = null)
{
    $res = MAC_PATH . 'static_new/images/touxiang.png';
    if ($user_id === null && !empty($GLOBALS['user']['user_id'])) {
        $user_id = (int)$GLOBALS['user']['user_id'];
    }
    if(!empty($user_id)){
        $res2 = 'upload/user/'.($user_id % 10 ). '/'.$user_id.'.jpg';
        if(file_exists(ROOT_PATH . $res2)){
            $res = MAC_PATH . $res2;
        }
    }
    return $res;
}

function mac_scalar_string($val, $default = '')
{
    if (is_string($val)) {
        return $val;
    }
    if (is_array($val) || is_object($val)) {
        return $default;
    }
    if ($val === null || $val === false) {
        return $default;
    }
    return (string)$val;
}

function mac_filter_html($str)
{
    $str = mac_scalar_string($str);
    return strip_tags($str);
}

/**
 * 简介等纯文本字段：还原 HTML 实体、去标签、合并空白。
 *
 * 资源站自己也跑过 mac_filter_xss（htmlspecialchars），正文里的 &nbsp; 进简介后
 * 变成 &amp;nbsp;，采集过来原样入库；本站后台保存同样会把 & " ' 转成实体。
 * 模板再 autoescape 一次，前台就显示成「&amp;nbsp;Petch…」这种乱码。
 * 实体可能叠了多层，循环解码到不再变化为止（上限 3 层）。
 * 返回值是未转义的纯文本，输出时交给 Twig 自动转义。
 */
function mac_plain_text($str)
{
    $str = mac_scalar_string($str);
    if ($str === '') {
        return '';
    }
    for ($i = 0; $i < 3; $i++) {
        $dec = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($dec === $str) {
            break;
        }
        $str = $dec;
    }
    // 只去掉形如 HTML 的标签；strip_tags 会把解码出来的「<速度与激情>」这类书名号文本也吞掉
    $str = preg_replace('#<!--.*?-->|</?[a-zA-Z][a-zA-Z0-9-]*(?:\s[^<>]*)?/?>#s', ' ', $str);
    // NBSP / 全角空格（中文段首缩进）一并视为空白
    $str = str_replace(["\xC2\xA0", "\xE3\x80\x80"], ' ', $str);
    $str = preg_replace('/\s+/u', ' ', $str);
    return trim((string)$str);
}

function mac_filter_xss($str)
{
    $str = mac_scalar_string($str);
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

/**
 * 后台关键词 LIKE 条件（含 OpenCC 繁简变体）。
 *
 * @param string $wd 已过滤的关键词
 * @return array ['like', '%词%']、['like', ['%词%', ...], 'OR']，或空数组（无有效关键词）
 */
function mac_search_wd_like($wd)
{
    $wd = trim((string)$wd);
    if ($wd === '') {
        return [];
    }
    $patterns = \app\common\util\OpenccConverter::likePatterns($wd);
    if (count($patterns) > 4) {
        $patterns = array_slice($patterns, 0, 4);
    }
    if (empty($patterns)) {
        return ['like', '%' . $wd . '%'];
    }
    if (count($patterns) === 1) {
        return ['like', $patterns[0]];
    }

    return ['like', $patterns, 'OR'];
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

/**
 * 小说备注中的章节数（「共N章」「更新至N章」）与实际入库章节对齐。
 * 采集源备注是源站全本章数，但接口往往只下发部分章节，导致备注与目录不符；
 * 章节以 art_content 的 $$$ 分段计（与 mac_art_list 一致）。非章节数类备注原样返回。
 */
function mac_art_chapter_remarks($art_remarks, $art_content)
{
    if ((string)$art_content === '') {
        return (string)$art_remarks;
    }
    return mac_art_chapter_remarks_by_total($art_remarks, substr_count($art_content, '$$$') + 1);
}

function mac_art_chapter_remarks_by_total($art_remarks, $chapter_total)
{
    return mac_chapter_remarks_by_total($art_remarks, $chapter_total, '章');
}

/** 章节数类备注（共/更新至/连载至 N 章|话）改写为实际章节数；$unit 为 章（小说）或 话（漫画） */
function mac_chapter_remarks_by_total($remarks, $chapter_total, $unit)
{
    $remarks = (string)$remarks;
    $chapter_total = (int)$chapter_total;
    if ($chapter_total < 1 || !preg_match('/^\s*(共|更新至|连载至)\s*\d+\s*' . preg_quote($unit, '/') . '\s*$/u', $remarks, $m)) {
        return $remarks;
    }
    return $m[1] . $chapter_total . $unit;
}

/** 漫画实际话数：manga_chapter_url 按 $$$ 分来源、# 分章节，取各来源最大话数（空段不计，与 mac_play_list_one 一致） */
function mac_manga_chapter_total($manga_chapter_url)
{
    $max = 0;
    foreach (explode('$$$', (string)$manga_chapter_url) as $group) {
        $n = 0;
        foreach (explode('#', $group) as $one) {
            if ($one !== '') {
                $n++;
            }
        }
        $max = max($max, $n);
    }
    return $max;
}

/**
 * 漫画备注中的话数（「共N话」「更新至N话」）与实际入库章节对齐。
 * 采集源备注是源站全本话数，但接口往往只下发部分章节，导致备注与章节列表不符。
 */
function mac_manga_chapter_remarks($manga_remarks, $manga_chapter_url)
{
    return mac_chapter_remarks_by_total($manga_remarks, mac_manga_chapter_total($manga_chapter_url), '话');
}

/**
 * 列表行章节数缓存（mac_art_rows_chapter_remarks / mac_manga_rows_chapter_remarks 共用）。
 * 库内计数要扫整段正文/章节地址（单本小说可达十几 MB），公开接口每次请求都现算会被放大成 DoS；
 * 按「ID + 更新时间」缓存，内容更新后键自然失效，TTL 兜底管理员改内容但不刷新时间的情况。
 * @return array [已命中的 [id => total], 未命中的 [id => cache_key]]
 */
function mac_rows_chapter_total_cache_get($m, array $rows, array $ids)
{
    $want = array_flip($ids);
    $flag = isset($GLOBALS['config']['app']['cache_flag']) ? $GLOBALS['config']['app']['cache_flag'] : 'maccms';
    $hit = [];
    $miss = [];
    foreach ($rows as $row) {
        $id = isset($row[$m . '_id']) ? (int)$row[$m . '_id'] : 0;
        if ($id < 1 || !isset($want[$id]) || isset($hit[$id]) || isset($miss[$id])) {
            continue;
        }
        $key = $flag . '_' . $m . '_chapter_total_' . $id . '_' . (isset($row[$m . '_time']) ? (int)$row[$m . '_time'] : 0);
        $v = \think\Cache::get($key);
        if ($v === false || $v === null) {
            $miss[$id] = $key;
        } elseif ((int)$v > 0) {
            $hit[$id] = (int)$v;
        }
    }
    return [$hit, $miss];
}

/**
 * 回写未命中的章节数；查不到正文的也写 0，避免同一条反复回库
 * @return array [id => total]（仅 total > 0 的）
 */
function mac_rows_chapter_total_cache_set(array $miss, array $found)
{
    $out = [];
    foreach ($miss as $id => $key) {
        $n = isset($found[$id]) ? (int)$found[$id] : 0;
        \think\Cache::set($key, $n, 3600);
        if ($n > 0) {
            $out[$id] = $n;
        }
    }
    return $out;
}

/** 列表行（未取章节地址的轻量查询）批量对齐漫画话数备注 */
function mac_manga_rows_chapter_remarks(array &$rows)
{
    $ids = [];
    foreach ($rows as $row) {
        if (!empty($row['manga_id']) && isset($row['manga_remarks']) && preg_match('/^\s*(共|更新至|连载至)\s*\d+\s*话\s*$/u', (string)$row['manga_remarks'])) {
            $ids[] = (int)$row['manga_id'];
        }
    }
    if (empty($ids)) {
        return;
    }
    list($totals, $miss) = mac_rows_chapter_total_cache_get('manga', $rows, $ids);
    if (!empty($miss)) {
        $found = [];
        try {
            // 单来源直接在库内数 #；多来源（含 $$$）才取回地址在 PHP 里逐组计数
            $sql = "SELECT manga_id, IF(LOCATE('$$$', manga_chapter_url) > 0, manga_chapter_url, NULL) AS multi, "
                . "CHAR_LENGTH(TRIM(BOTH '#' FROM manga_chapter_url)) - CHAR_LENGTH(REPLACE(TRIM(BOTH '#' FROM manga_chapter_url), '#', '')) + 1 AS n FROM "
                . config('database.prefix') . "manga WHERE manga_chapter_url <> '' AND manga_id IN (" . implode(',', array_keys($miss)) . ")";
            foreach (\think\Db::query($sql) as $r) {
                $found[$r['manga_id']] = $r['multi'] !== null ? mac_manga_chapter_total($r['multi']) : (int)$r['n'];
            }
        } catch (\Exception $e) {
            return;
        }
        $totals += mac_rows_chapter_total_cache_set($miss, $found);
    }
    foreach ($rows as $k => $row) {
        if (!empty($row['manga_id']) && isset($totals[$row['manga_id']])) {
            $rows[$k]['manga_remarks'] = mac_chapter_remarks_by_total($row['manga_remarks'], $totals[$row['manga_id']], '话');
        }
    }
}

/**
 * 列表行（未取 art_content 的轻量查询）批量对齐章节数备注：仅对章节数类备注在库内计数，不传正文。
 */
function mac_art_rows_chapter_remarks(array &$rows)
{
    $ids = [];
    foreach ($rows as $row) {
        if (!empty($row['art_id']) && isset($row['art_remarks']) && preg_match('/^\s*(共|更新至|连载至)\s*\d+\s*章\s*$/u', (string)$row['art_remarks'])) {
            $ids[] = (int)$row['art_id'];
        }
    }
    if (empty($ids)) {
        return;
    }
    list($totals, $miss) = mac_rows_chapter_total_cache_get('art', $rows, $ids);
    if (!empty($miss)) {
        $found = [];
        try {
            $sql = "SELECT art_id, (CHAR_LENGTH(art_content) - CHAR_LENGTH(REPLACE(art_content, '$$$', ''))) / 3 + 1 AS n FROM "
                . config('database.prefix') . "art WHERE art_content <> '' AND art_id IN (" . implode(',', array_keys($miss)) . ")";
            foreach (\think\Db::query($sql) as $r) {
                $found[$r['art_id']] = (int)$r['n'];
            }
        } catch (\Exception $e) {
            return;
        }
        $totals += mac_rows_chapter_total_cache_set($miss, $found);
    }
    foreach ($rows as $k => $row) {
        if (!empty($row['art_id']) && isset($totals[$row['art_id']])) {
            $rows[$k]['art_remarks'] = mac_art_chapter_remarks_by_total($row['art_remarks'], $totals[$row['art_id']]);
        }
    }
}

/** 集数类视频备注：全/共/更新至/连载至 [第] N 集 */
function mac_vod_episode_remarks_match($vod_remarks, &$m = null)
{
    return (bool)preg_match('/^\s*(全|共|更新至|连载至)\s*(第?)\s*(\d+)\s*集\s*$/u', (string)$vod_remarks, $m);
}

/**
 * 视频备注中的集数（「全N集」「更新至N集」）与实际播放地址对齐。
 * 采集源备注常是源站全集数，接口却只下发部分剧集；二次采集更新了地址却未更新备注时也会落后。
 * 集数取 vod_play_url 各播放组非空集数的最大值（与 mac_manga_chapter_total 同口径）。数字一致时原样返回（保留 第06集 这类补零）。
 */
function mac_vod_episode_remarks($vod_remarks, $vod_play_url)
{
    $vod_remarks = (string)$vod_remarks;
    if (!mac_vod_episode_remarks_match($vod_remarks, $m)) {
        return $vod_remarks;
    }
    $total = mac_manga_chapter_total($vod_play_url);
    if ($total < 1 || (int)$m[3] === $total) {
        return $vod_remarks;
    }
    return $m[1] . $m[2] . $total . '集';
}

/** 列表行（未取播放地址的轻量查询）批量对齐视频集数备注；行内已带 vod_play_url 时直接计数 */
function mac_vod_rows_episode_remarks(array &$rows)
{
    $ids = [];
    foreach ($rows as $k => $row) {
        if (empty($row['vod_id']) || !isset($row['vod_remarks']) || !mac_vod_episode_remarks_match($row['vod_remarks'])) {
            continue;
        }
        if (isset($row['vod_play_url'])) {
            $rows[$k]['vod_remarks'] = mac_vod_episode_remarks($row['vod_remarks'], $row['vod_play_url']);
        } else {
            $ids[] = (int)$row['vod_id'];
        }
    }
    if (empty($ids)) {
        return;
    }
    try {
        $urls = \think\Db::name('Vod')->where('vod_id', 'in', array_unique($ids))->column('vod_play_url', 'vod_id');
    } catch (\Exception $e) {
        return;
    }
    foreach ($rows as $k => $row) {
        if (!empty($row['vod_id']) && !isset($row['vod_play_url']) && isset($urls[$row['vod_id']])) {
            $rows[$k]['vod_remarks'] = mac_vod_episode_remarks($row['vod_remarks'], $urls[$row['vod_id']]);
        }
    }
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
    $url = mac_scalar_string($url);
    if ($url === '') {
        return '';
    }
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

/**
 * 读取「主题设计」配置（Twig 兼容壳 → ThemeDesignRuntime）。
 * @return array
 */
function mac_theme_design()
{
    return \app\common\util\ThemeDesignRuntime::config();
}

/**
 * 是否处于主题设计预览模式（Twig 兼容壳 → ThemePreviewAuth）。
 * @return bool
 */
function mac_td_preview_mode()
{
    return \app\common\util\ThemePreviewAuth::isActive();
}

function mac_pb_resolve_template($tpl)
{
    return \app\common\util\ThemePreviewRuntime::resolveTemplate($tpl);
}

function mac_pb_theme_has_page_tpl($themeDir, $tpl, $htmlDir = 'html')
{
    return \app\common\util\ThemePreviewRuntime::themeHasPageTpl($themeDir, $tpl, $htmlDir);
}

function mac_pb_should_use_site_theme($tpl)
{
    return \app\common\util\ThemePreviewRuntime::shouldUseSiteTheme($tpl);
}

function mac_pb_site_theme_view_path()
{
    return \app\common\util\ThemePreviewRuntime::siteThemeViewPath();
}

function mac_pb_ensure_page_scaffold($tpl)
{
    return \app\common\util\ThemePreviewRuntime::ensurePageScaffold($tpl);
}

function mac_page_view($route)
{
    return \app\common\util\ThemePreviewRuntime::pageView($route);
}

function mac_homepage_index_view()
{
    return \app\common\util\ThemePreviewRuntime::homepageIndexView();
}

function mac_pb_preview_stub_website($param = array())
{
    return \app\common\util\ThemeDesignRuntime::previewStubWebsite($param);
}

function mac_td_section($key)
{
    return \app\common\util\ThemeDesignRuntime::homepageSection($key);
}

function mac_homepage_default_page()
{
    $kit = new \app\common\util\PageBuilderKit();
    return $kit->defaultHomepagePage();
}

function mac_homepage_blocks()
{
    return \app\common\util\ThemeDesignRuntime::homepageBlocks();
}

function mac_pb_theme_template_dir()
{
    return \app\common\util\ThemePreviewRuntime::themeTemplateDir();
}

function mac_pb_is_packaged_component($componentId)
{
    $kit = new \app\common\util\PageBuilderKit();
    return $kit->isPackagedInActiveTheme($componentId);
}

function mac_pb_collect_block_assets($blocks)
{
    $kit = new \app\common\util\PageBuilderKit();
    return $kit->collectBlockAssetsForActiveTheme($blocks);
}

function mac_theme_override_css_href()
{
    return \app\common\util\ThemeDesignRuntime::overrideCssHref();
}

function mac_url_content_img($content)
{
    $content = mac_scalar_string($content);
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
                $file = substr($model, 6);
                if(strpos($file, '.') !== false){
                    $file = explode('.', $file)[0];
                }
                $path = !empty($config['path']['label']) ? $config['path']['label'] : 'label/{file}/index';
                if(substr($path, strlen($path) - 1, 1) == '/'){
                    $path .= 'index';
                }
                $path = str_replace('{file}', $file, $path);
            }
            else{
                $url = url($model,$param);
            }
            break;
        case 'vod/show':
        case 'art/show':
        case 'manga/show':
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
                $param['id'] = $id;
                $url = url($model, $param);
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
        case 'manga/detail':
            $replace_to = [$info['manga_id'],$info['manga_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en']
            ];
            if($config['view']['manga_detail'] == 2){
                $path = $config['path' ]['manga_detail'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['manga_id']);
            }
            else{
                switch($config['rewrite']['manga_id'])
                {
                    case 1:
                        $id = $info['manga_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['manga_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['manga_id'];
                        break;
                }

                $url = url($model,['id'=> $id ]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['manga_time']),date('m',$info['manga_time']),date('d',$info['manga_time'])]);
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
        case 'manga/play':
            $replace_to = [
                $info['manga_id'],$info['manga_en'],'',
                $info['type_id'],$info['type']['type_en'],$info['type_1']['type_id'],$info['type_1']['type_en'],
            ];
            if(isset($config['view']['manga_play']) && $config['view']['manga_play'] >= 2){
                $path = $config['path']['manga_play'];
                if(substr($path,strlen($path)-1,1)=='/'){
                    $path .= 'index';
                }
                $replace_to[] = md5($info['manga_id']);
                if($config['view']['manga_play'] == 2){
                    $path.= '.'.$config['path']['suffix'];
                    $path .= '?'.$info['manga_id'].'-'.$param['sid'].'-'.$param['nid'];
                }
                elseif($config['view']['manga_play'] == 3){
                    $path .= $config['path']['page_sp'].$param['sid'].$config['path']['page_sp'].$param['nid'];
                }
            }else{
                switch($config['rewrite']['manga_id'])
                {
                    case 1:
                        $id = $info['manga_en'];
                        break;
                    case 2:
                        $id = mac_alphaID($info['manga_id'],false,$config['rewrite']['encode_len'],$config['rewrite']['encode_key']);
                        break;
                    default:
                        $id = $info['manga_id'];
                        break;
                }
                $url = url($model,['id'=>$id,'sid'=>$param['sid'],'nid'=>$param['nid']]);
            }
            $replace_to = array_merge($replace_to,[date('Y',$info['manga_time']),date('m',$info['manga_time']),date('d',$info['manga_time']),$param['sid'],$param['nid']]);
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
                $param['id'] = $id;
                $url = url($model, $param);
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
    else if($info['type_mid'] == 12) {
        if(empty($param['id'])){
            $param['id'] = $info['type_id'];
        }
        return mac_url('manga/'.$flag,$param,$info);
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
function mac_url_label($file)
{
    $file = str_replace('\\', '/', (string)$file);
    if(strpos($file, '.') !== false){
        $file = explode('.', $file)[0];
    }
    return mac_url('label/'.$file);
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
    return mac_url('vod/index', $param);
}
function mac_url_vod_detail($info)
{
    return mac_url('vod/detail',[],$info);
}
function mac_url_manga_detail($info)
{
    return mac_url('manga/detail',[],$info);
}
function mac_url_manga_play($info,$param=[])
{
    if($param=='first'){
        if(empty($info['manga_page_list']) || !is_array($info['manga_page_list'])){
            return '';
        }
        $sid = intval(key($info['manga_page_list']));
        if(empty($info['manga_page_list'][$sid]['urls']) || !is_array($info['manga_page_list'][$sid]['urls'])){
            return '';
        }
        $nid = intval(key($info['manga_page_list'][$sid]['urls']));
        if($sid==0 || $nid==0){
            return '';
        }
        $param = [];
        $param['sid'] = $sid;
        $param['nid'] = $nid;
    }
    if(intval($param['sid'])<1){
        $param['sid'] = 1;
    }
    if(intval($param['nid'])<1){
        $param['nid'] = 1;
    }

    return mac_url('manga/play',['sid'=>$param['sid'],'nid'=>$param['nid']],$info);
}
function mac_url_manga_down($info,$param=[])
{
    if($param=='first'){
        if(empty($info['manga_page_list']) || !is_array($info['manga_page_list'])){
            return '';
        }
        $sid = intval(key($info['manga_page_list']));
        if(empty($info['manga_page_list'][$sid]['urls']) || !is_array($info['manga_page_list'][$sid]['urls'])){
            return '';
        }
        $nid = intval(key($info['manga_page_list'][$sid]['urls']));
        if($sid==0 || $nid==0){
            return '';
        }
        $param = [];
        $param['sid'] = $sid;
        $param['nid'] = $nid;
    }

    if(intval($param['sid'])<1){
        $param['sid'] = 1;
    }
    if(intval($param['nid'])<1){
        $param['nid'] = 1;
    }

    return mac_url('manga/down',['sid'=>$param['sid'],'nid'=>$param['nid']],$info);
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
    if ($res['code'] > 1 && function_exists('mac_td_preview_mode') && mac_td_preview_mode()) {
        $row = model('Website')->where('website_status', 1)->order('website_id asc')->find();
        if (!empty($row)) {
            $wid = is_array($row) ? (int) $row['website_id'] : (int) $row->getData('website_id');
            if ($wid > 0) {
                $res = model('Website')->infoData(array('website_id' => array('eq', $wid)), '*', 0);
            }
        }
        if ($res['code'] > 1 && function_exists('mac_pb_preview_stub_website')) {
            $res = array('code' => 1, 'msg' => lang('obtain_ok'), 'info' => mac_pb_preview_stub_website($param));
        }
    }

    if (!isset($res['info']) || !is_array($res['info'])) {
        return $res;
    }

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
    if ($res['code'] > 1 && function_exists('mac_td_preview_mode') && mac_td_preview_mode()) {
        $row = model('Topic')->where('topic_status', 1)->order('topic_id asc')->find();
        if (!empty($row)) {
            $tid = is_array($row) ? (int) $row['topic_id'] : (int) $row->getData('topic_id');
            if ($tid > 0) {
                $res = model('Topic')->infoData(array('topic_id' => array('eq', $tid)), '*', 0);
            }
        }
    }
    return $res;
}

// -----------------------------------------------------------------------------
// 内容级多语言（vod/art）。
// 站点默认语言的内容照旧存在 mac_vod/mac_art；其它语言的可翻译字段整体存
// mac_content_lang（按 content_type+content_id+lang_code 一行 JSON），缺失时
// 由 mac_content_lang_overlay() 回退到默认语言原文，永不返回空字段。
// -----------------------------------------------------------------------------

/**
 * 每种内容类型允许翻译覆盖的字段。
 * 只收纯展示文案：*_en（URL 重写 slug，前台路由按库里原值匹配，翻译后链接会 404）、
 * *_letter（A-Z 首字母，用于字母筛选/排序的键）等标识/索引字段一律不在此列——
 * 与 Type::langOverlayTypeList() 的排除口径一致。
 */
function mac_content_lang_fields($contentType)
{
    $map = [
        'vod'   => ['vod_name', 'vod_sub', 'vod_tag', 'vod_remarks', 'vod_class', 'vod_area', 'vod_lang', 'vod_version', 'vod_state', 'vod_blurb', 'vod_content'],
        'art'   => ['art_name', 'art_sub', 'art_tag', 'art_remarks', 'art_class', 'art_blurb', 'art_title', 'art_note', 'art_content'],
        'manga' => ['manga_name', 'manga_sub', 'manga_tag', 'manga_remarks', 'manga_class', 'manga_blurb', 'manga_content'],
        'actor' => ['actor_name', 'actor_alias', 'actor_area', 'actor_birtharea', 'actor_starsign', 'actor_school', 'actor_remarks', 'actor_works', 'actor_tag', 'actor_class', 'actor_blurb', 'actor_content'],
        'website' => ['website_name', 'website_sub', 'website_area', 'website_lang', 'website_tag', 'website_class', 'website_remarks', 'website_blurb', 'website_content'],
        'live'  => ['live_name', 'live_sub', 'live_blurb', 'live_content'],
        'type'  => ['type_name', 'type_title', 'type_key', 'type_des', 'type_extend'],
        'topic' => ['topic_name', 'topic_sub', 'topic_remarks', 'topic_type', 'topic_tag', 'topic_blurb', 'topic_content', 'topic_title', 'topic_key', 'topic_des'],
        'link'  => ['link_name'],
        'task'  => ['task_name', 'task_desc'],
    ];
    return isset($map[$contentType]) ? $map[$contentType] : [];
}

/**
 * 各内容类型翻译字段里需要走 mac_filter_xss() 的子集。
 * 必须与对应模型 saveData() 里的 $filter_fields 列表保持一致：默认语言字段在模型层已过滤，
 * 其它语言字段经 content_lang 侧信道写入时同样要过滤，否则等于给了绕过通道（见 #PR28 review）。
 * 富文本正文（*_content，topic_content 除外，master 上 topic 确实过滤）、结构化字段（*_extend）
 * 不在此列，保持与模型一致。vod/link/live/task 模型 master 上本就没有 xss 过滤，返回空数组。
 */
function mac_content_lang_xss_fields($contentType)
{
    $map = [
        'art'   => ['art_name', 'art_sub', 'art_tag', 'art_class', 'art_blurb', 'art_remarks'],
        'manga' => ['manga_name', 'manga_sub', 'manga_tag', 'manga_class', 'manga_blurb', 'manga_remarks'],
        'actor' => ['actor_name', 'actor_alias', 'actor_area', 'actor_birtharea', 'actor_starsign', 'actor_school', 'actor_remarks', 'actor_works', 'actor_tag', 'actor_class', 'actor_blurb'],
        'website' => ['website_name', 'website_sub', 'website_area', 'website_lang', 'website_tag', 'website_class', 'website_remarks', 'website_blurb'],
        'type'  => ['type_name', 'type_title', 'type_key', 'type_des'],
        'topic' => ['topic_name', 'topic_sub', 'topic_type', 'topic_title', 'topic_key', 'topic_des', 'topic_blurb', 'topic_remarks', 'topic_tag', 'topic_content'],
    ];
    return isset($map[$contentType]) ? $map[$contentType] : [];
}

/** 站点默认内容语言（与后台界面语言 app.lang 是两个不同的键，不要混用） */
function mac_content_lang_default()
{
    $known = ['zh-cn', 'zh-tw', 'en-us', 'ja-jp', 'ko-kr', 'de-de', 'fr-fr', 'es-es', 'pt-pt'];
    $val = $GLOBALS['config']['app']['content_lang_default'] ?? '';
    return in_array($val, $known, true) ? $val : 'zh-cn';
}

/** 前台已启用的内容语言列表（后台勾选，pipe 分隔存于 app.content_lang_list，与 search_vod_rule 等同一惯例） */
function mac_content_lang_allow_list()
{
    $raw = $GLOBALS['config']['app']['content_lang_list'] ?? '';
    if (empty($raw)) {
        return [];
    }
    return array_filter(array_map('trim', explode('|', $raw)));
}

/**
 * 解析当前请求应展示的内容语言：GET lang -> cookie -> Accept-Language -> 默认语言。
 * 仅在允许列表内的取值才会被采纳；GET 命中时顺带写回 cookie 以便后续请求保持。
 */
function mac_get_content_lang()
{
    $default = mac_content_lang_default();
    $allow   = mac_content_lang_allow_list();
    if (empty($allow)) {
        return $default;
    }

    $cookieName = 'mac_content_lang';
    $candidate  = '';
    $fromGet    = false;

    $get = input('get.lang', '');
    if (!empty($get)) {
        $candidate = strtolower($get);
        $fromGet   = true;
    } elseif (!empty($_COOKIE[$cookieName])) {
        $candidate = strtolower($_COOKIE[$cookieName]);
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        preg_match('/^([a-z\d\-]+)/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
        $candidate = isset($matches[1]) ? strtolower($matches[1]) : '';
    }

    if (empty($candidate) || !in_array($candidate, $allow, true)) {
        $candidate = $default;
    }

    if ($fromGet && $candidate !== ($_COOKIE[$cookieName] ?? '')) {
        try {
            setcookie($cookieName, $candidate, time() + 86400 * 30, '/');
        } catch (\Exception $e) {
            // cookie 写入失败（如已输出内容）不影响本次请求的语言解析
        }
    }

    return $candidate;
}

/**
 * 用指定语言覆盖内容行里的可翻译字段；找不到译文或字段为空时保留原文，绝不返回空值。
 * $row 需含 {$contentType}_id 主键字段。
 *
 * 默认内容语言＝原始行（mac_vod/mac_art/...）为准，不走 content_lang：采集、批量、快速编辑、
 * provide API 等写入路径只更新原始行，若默认语言也从 content_lang 取，这些路径改过之后前台会
 * 一直显示后台表单最后一次保存的旧值。只有非默认语言才做译文覆盖。
 */
function mac_content_lang_overlay($contentType, array $row, $lang = null)
{
    // 未启用任何前台内容语言时直接返回：省掉每个详情页一次 mac_content_lang 查询，
    // 也让“部署了新版代码但还没跑在线升级建表”的存量站点不会因查询缺表而白屏。
    if (empty(mac_content_lang_allow_list())) {
        return $row;
    }
    $lang = $lang ?: \app\common\model\ContentLang::getCurrent();
    // 默认语言直接用原始行，不查 content_lang（见上方注释）。
    if ($lang === mac_content_lang_default()) {
        return $row;
    }
    $fields = mac_content_lang_fields($contentType);
    if (empty($fields)) {
        return $row;
    }
    $contentId = $row[$contentType . '_id'] ?? 0;
    if (empty($contentId)) {
        return $row;
    }
    $translated = model('ContentLang')->getFields($contentType, $contentId, $lang);
    if (empty($translated)) {
        return $row;
    }
    foreach ($fields as $field) {
        if (!empty($translated[$field])) {
            $row[$field] = $translated[$field];
        }
    }
    return $row;
}

/** 是否有已安装的插件注册了 content_translate 钩子（决定后台“自动翻译”按钮是否显示） */
function mac_content_translate_available()
{
    return count(\think\Hook::get('content_translate')) > 0;
}

/**
 * 调用已注册的翻译插件把一段文本从 $from 翻成 $to；没有插件注册或插件出错时返回空字符串。
 * 约定：插件的钩子方法返回翻译结果字符串表示成功处理；返回 null 表示“不处理，交给下一个”；
 * 返回 false 会中断继续尝试下一个已注册插件（Hook::listen 的既有语义）。
 */
function mac_content_translate($text, $from, $to, $context = [])
{
    if (!mac_content_translate_available()) {
        return '';
    }
    $params = [
        'text'    => $text,
        'from'    => $from,
        'to'      => $to,
        'context' => $context,
    ];
    try {
        $result = \think\Hook::listen('content_translate', $params, null, true);
    } catch (\Exception $e) {
        return '';
    }
    return is_string($result) ? $result : '';
}

/**
 * 后台“自动翻译”动作的每管理员限流：翻译插件通常调第三方付费 API，被盗账号或误操作
 * 会刷爆配额。与 VodAiCover::consumeGenerateRateLimit() 同样的按分钟/小时双桶计数。
 * @return bool true=允许本次调用，false=已超频
 */
// 默认 40 次/分钟、600 次/小时：一条 vod 约 14 个可译字段，逐字段翻译整条 + 顺带几条
// 文章不至于撞线；被盗号刷配额会被小时桶挡住
function mac_content_translate_rate_limit($adminId, $perMinute = 40, $perHour = 600)
{
    // 没装翻译插件时没有要保护的第三方配额，直接放行（后续 mac_content_translate() 会 fast-fail），
    // 免得未配置的站点把失败请求也计进配额、20 次后误锁管理员
    if (!mac_content_translate_available()) {
        return true;
    }
    $adminId = (int)$adminId;
    if ($adminId <= 0) {
        return false;
    }
    $minKey  = 'admin_content_translate_rl_min:'  . $adminId . ':' . (int)floor(time() / 60);
    $hourKey = 'admin_content_translate_rl_hour:' . $adminId . ':' . (int)floor(time() / 3600);
    $nMin  = (int)\think\Cache::get($minKey, 0);
    if ($nMin >= $perMinute) {
        return false;
    }
    $nHour = (int)\think\Cache::get($hourKey, 0);
    if ($nHour >= $perHour) {
        return false;
    }
    \think\Cache::set($minKey, $nMin + 1, 70);
    \think\Cache::set($hourKey, $nHour + 1, 3700);
    return true;
}

/**
 * 播放/下载/服务器线路配置（vodplayer|voddowner|vodserver）不落库，是 mac_arr2file() 直接写到
 * application/extra 下的 PHP 数组文件，用字符串 key（如 from）取行，没有 mac_content_lang 需要
 * 的整型 content_id，所以不走 ContentLang 模型；译文就近存在同一条目的 content_lang[lang_code] 里，
 * 找不到译文或字段为空时保留原文，绝不返回空值。
 *
 * 注意：不要在 mac_play_list() 里直接套用——它的产物会被 Vod::infoData() 写进不区分语言的
 * vod_detail 核心缓存，先建缓存的语言会把线路译文串给其它语言。调用点应在核心缓存读取「之后」，
 * 与 mac_content_lang_overlay('vod', ...) 同一位置。
 */
function mac_config_lang_overlay(array $item, array $fields, $lang = null)
{
    $lang = $lang ?: \app\common\model\ContentLang::getCurrent();
    // 同 mac_content_lang_overlay()：默认语言以条目本身的字段为准，不做译文覆盖。
    if ($lang === mac_content_lang_default()) {
        return $item;
    }
    if (empty($item['content_lang'][$lang]) || !is_array($item['content_lang'][$lang])) {
        return $item;
    }
    foreach ($fields as $field) {
        if (!empty($item['content_lang'][$lang][$field])) {
            $item[$field] = $item['content_lang'][$lang][$field];
        }
    }
    return $item;
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
function mac_label_manga_detail($param)
{
    $where = [];
    if($GLOBALS['config']['rewrite']['manga_id']==1){
        $where['manga_en'] = ['eq',$param['id']];
    }
    else{
        if($GLOBALS['config']['rewrite']['manga_id']==2) {
            $param['id'] = mac_alphaID($param['id'], true, $GLOBALS['config']['rewrite']['encode_len'],$GLOBALS['config']['rewrite']['encode_key'] );
        }
        $where['manga_id'] = ['eq',$param['id']];
    }
    $where['manga_status'] = ['eq',1];
    $res = model('Manga')->infoData($where,'*',1);
    if($res['code'] != 1){
        return $res;
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

/**
 * VIP 专属分类 = VIP 有播放/阅读权限 - (游客 ∪ 默认会员 有播放/阅读权限)
 * 仅针对播放页权限 (popedom 3)，Vod/Art/Manga 统一用 popedom 3
 * 返回 type_id 数组
 */
function mac_get_vip_exclusive_type_ids()
{
    $cache_flag = $GLOBALS['config']['app']['cache_flag'] ?? 'maccms';
    $key = $cache_flag . '_vip_exclusive_type_ids';
    $list = \think\Cache::get($key);
    if (is_array($list)) {
        return $list;
    }
    $group_list = model('Group')->getCache('group_list');
    $type_list = model('Type')->getCache('type_list');
    if (empty($group_list) || empty($type_list)) {
        return [];
    }
    $guest_ids = [];
    $member_ids = [];
    $vip_ids = [];
    $content_mids = [1 => 1, 2 => 1, 12 => 1]; // Vod, Art, Manga 有播放/阅读页
    foreach ($type_list as $type_id => $type_info) {
        $mid = $type_info['type_mid'] ?? 0;
        if (empty($content_mids[$mid])) {
            continue;
        }
        foreach ($group_list as $gid => $group) {
            $has = strpos(',' . ($group['group_type'] ?? ''), ',' . $type_id . ',') !== false
                && !empty($group['group_popedom'][$type_id][3]);
            if (!$has) {
                continue;
            }
            if ($gid == 1) {
                $guest_ids[$type_id] = 1;
            } elseif ($gid == 2) {
                $member_ids[$type_id] = 1;
            } else {
                $vip_ids[$type_id] = 1;
            }
        }
    }
    $ab = $guest_ids + $member_ids;
    $exclusive = array_diff_key($vip_ids, $ab);
    $list = array_keys($exclusive);
    \think\Cache::set($key, $list);
    return $list;
}

/**
 * 为列表每行补充 type_is_vip_exclusive，供前台角标（SSR 模板 / CSR MacHomeCardRender）与接口 JSON 一致
 *
 * @param array $list 引用传递，行内需含 type_id（视频/文章/漫画分类 ID）
 */
function mac_append_type_is_vip_exclusive_for_rows(array &$list)
{
    if (empty($list)) {
        return;
    }
    $vip_exclusive = mac_get_vip_exclusive_type_ids();
    foreach ($list as &$row) {
        $row['type_is_vip_exclusive'] = in_array((int)($row['type_id'] ?? 0), $vip_exclusive, true) ? 1 : 0;
    }
    unset($row);
}

/**
 * 文章/漫画「阅读」实际扣费积分：与前台权限、user/ajax_buy_popedom 一致。
 * 「每数据」用 *_points；「每页/每话」优先 *_points_detail，为 0 时回退整本/整条 *_points（常见只填了 art_points 的情况）。
 *
 * @param string $pre art|manga
 * @param array  $info 详情行
 * @return int
 */
function mac_content_read_points_amount($pre, array $info)
{
    $pre = strtolower((string)$pre);
    if (!in_array($pre, ['art', 'manga'], true)) {
        return 0;
    }
    $typeKey = $pre . '_points_type';
    $ptype = isset($GLOBALS['config']['user'][$typeKey]) ? (string)$GLOBALS['config']['user'][$typeKey] : '0';
    if ($ptype === '1') {
        return (int)($info[$pre . '_points'] ?? 0);
    }
    $detail = (int)($info[$pre . '_points_detail'] ?? 0);
    if ($detail > 0) {
        return $detail;
    }

    return (int)($info[$pre . '_points'] ?? 0);
}

/**
 * 与 model Vod::listCacheData 中按分类筛选逻辑一致：自身 type_id + 直接子分类的 type_id。
 * 用于把 (type_id=X OR type_id_1=X) 改为 type_id IN (...)，便于走 type_id 索引、避免 OR 低效扫描。
 *
 * @param int $typeId 分类 ID
 * @return int[]
 */
function mac_vod_type_filter_ids_for_list($typeId)
{
    $typeId = (int)$typeId;
    if ($typeId <= 0) {
        return [];
    }
    $type_list = model('Type')->getCache('type_list');
    if (empty($type_list) || !is_array($type_list)) {
        return [$typeId];
    }
    $tmp_arr = explode(',', (string)$typeId);
    $ids = [];
    foreach ($type_list as $v2) {
        if (!is_array($v2)) {
            continue;
        }
        if (in_array($v2['type_id'] . '', $tmp_arr) || in_array($v2['type_pid'] . '', $tmp_arr)) {
            $ids[] = (int)$v2['type_id'];
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    return !empty($ids) ? $ids : [$typeId];
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

function mac_admin_csrf_token() {
    // 后台 CSRF 稳定令牌：整个会话生命周期内固定，不随页面渲染轮换。
    // 旧的 Request::token()（{$Request.token}）每次渲染都会重新生成并覆盖 Session __token__，
    // 导致多标签页/重新打开后台时先前页面的令牌失效、AJAX 被 CsrfGuard 判 403（表现为“点绑定没反应”）。
    // 这里改用会话级固定令牌，兼容多标签同时打开后台的场景。
    $t = \think\Session::get('admin_csrf');
    if (!is_string($t) || $t === '') {
        // 优先使用 CSPRNG 生成 128-bit 令牌；random_bytes 在极少数无熵源环境会抛异常，
        // 此时回退到旧的 md5(uniqid()+mt_rand())，确保功能不中断。
        try {
            $t = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $t = md5(uniqid('mac_admin_csrf_', true) . mt_rand());
        }
        \think\Session::set('admin_csrf', $t);
    }
    return $t;
}

function mac_strip_tags($string) {
    $pattern = '/&([a-zA-Z0-9#]+);/';
    
    $string = preg_replace_callback($pattern, function($matches) {
        if ($matches[0] === '&lt;') {
            return '<';
        }
        if ($matches[0] === '&gt;') {
            return '>';
        }
        if ($matches[0] === '&nbsp;') {
            return ' ';
        }
        if ($matches[0] === '&amp;') {
            return '&';
        }
        return $matches[0];
    }, $string);
    
    return strip_tags($string);
}
