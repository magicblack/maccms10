<?php
/**
 * 回归：采集接口检测（CollectHealth）/ 内容资源检测（ContentCheck）的 SSRF 防护、断路器、自动停用开关。
 * 来源：PR #98 review P1-1（SSRF）、P1-2（断网全站下架 + 默认开启破坏性停用）。
 *
 * 跑法：php tests/regression/collect_probe_safety.php   （不连真实外网；私网目标在连接前就被拦下）
 */
require __DIR__ . '/lib/bootstrap.php';

use app\common\util\HttpClient;
use app\common\util\ContentCheck;
use app\common\util\CollectHealth;

// ─── 列 ssrf_probe：探测「采集源下发的不可信地址」的工具，每个建 curl 句柄的函数都必须钉 IP、不自动跟随、限协议 ───
// 登记：读取采集源 / 资源站中心下发地址并发起请求的工具文件。新增同类探测工具必须登记到这里。
$probeFiles = [
    'ContentCheck'  => APP_PATH . 'common/util/ContentCheck.php',
    'CollectHealth' => APP_PATH . 'common/util/CollectHealth.php',
];
// 列 ssl_verify：每个句柄都必须设置 VERIFYPEER，且不得写死关闭。允许按参数切换（证书失败降级），
// 但该参数必须默认开启，降级只能由证书类错误触发（见下方 CollectHealth 断言）。无豁免。

foreach ($probeFiles as $cls => $file) {
    $fns = rt_functions($file);
    $all = implode("\n", array_column($fns, 'body'));
    rt_check(strpos($all, 'HttpClient::resolvePublicIp(') !== false, "[ssrf_probe] {$cls}: must resolve+validate hosts via HttpClient::resolvePublicIp()");
    rt_check(strpos($all, 'CURLINFO_REDIRECT_URL') !== false, "[ssrf_probe] {$cls}: redirects must be walked manually (re-queued through the public-IP check), CURLINFO_REDIRECT_URL not used");
    $sites = 0;
    foreach ($fns as $fn => $f) {
        if (strpos($f['body'], 'curl_init(') === false) {
            continue;
        }
        $sites++;
        $cell = "{$cls}::{$fn}";
        $b = $f['body'];
        rt_check(strpos($b, 'CURLOPT_RESOLVE') !== false, "[ssrf_probe] {$cell}: curl handle not pinned with CURLOPT_RESOLVE (DNS rebinding)");
        rt_check(strpos($b, 'CURLOPT_PROTOCOLS') !== false, "[ssrf_probe] {$cell}: CURLOPT_PROTOCOLS not restricted to http/https");
        rt_check((bool)preg_match('/CURLOPT_FOLLOWLOCATION\s*,\s*(0|false)\s*\)/i', $b), "[ssrf_probe] {$cell}: CURLOPT_FOLLOWLOCATION must be explicitly 0");
        rt_check(!preg_match('/CURLOPT_FOLLOWLOCATION\s*,\s*(1|true)\s*\)/i', $b), "[ssrf_probe] {$cell}: CURLOPT_FOLLOWLOCATION enabled — redirect target bypasses the public-IP check");
        rt_check(strpos($b, 'CURLOPT_SSL_VERIFYPEER') !== false, "[ssl_verify] {$cell}: CURLOPT_SSL_VERIFYPEER not set");
        rt_check(!preg_match('/CURLOPT_SSL_VERIFYPEER\s*,\s*(0|false)\s*\)/i', $b), "[ssl_verify] {$cell}: CURLOPT_SSL_VERIFYPEER hard-disabled");
        rt_check(!preg_match('/CURLOPT_SSL_VERIFYHOST\s*,\s*(0|false)\s*\)/i', $b), "[ssl_verify] {$cell}: CURLOPT_SSL_VERIFYHOST hard-disabled");
    }
    rt_check($sites > 0, "[ssrf_probe] {$cls}: no curl_init site found — registry stale?");
}

// CollectHealth 的证书降级：参数默认校验；只在证书类错误后才不校验重试，且结果标记 cert_invalid
$chSrc = new ReflectionMethod('app\\common\\util\\CollectHealth', 'probeHandle');
$verifyParam = null;
foreach ($chSrc->getParameters() as $prm) {
    if ($prm->getName() === 'verifySsl') {
        $verifyParam = $prm;
    }
}
rt_check($verifyParam !== null && $verifyParam->isDefaultValueAvailable() && $verifyParam->getDefaultValue() === true, '[ssl_verify] CollectHealth::probeHandle($verifySsl) must default to true');
$chFns = rt_functions(APP_PATH . 'common/util/CollectHealth.php');
$pm = $chFns['probeMany']['body'];
rt_check((bool)preg_match("/elseif\\s*\\(\\s*empty\\(\\\$job\\['insecure'\\]\\)\\s*&&\\s*self::isCertError\\(/", $pm), '[ssl_verify] CollectHealth::probeMany: the insecure retry must be gated by empty($job[insecure]) && self::isCertError()');
rt_same(1, substr_count($pm, "'insecure' => true"), '[ssl_verify] CollectHealth::probeMany: exactly one place may switch a job to insecure');
rt_check(strpos($pm, "\$res['cert_invalid'] = true") !== false, '[ssl_verify] CollectHealth::probeMany: insecure results must be flagged cert_invalid');
foreach ([60 => true, 77 => true, 51 => true, 0 => false, 7 => false, 28 => false, 35 => false, 6 => false] as $errno => $want) {
    rt_same($want, \app\common\util\CollectHealth::isCertError($errno), "CollectHealth::isCertError({$errno})");
}

// ─── 行为：公网判定 ───
foreach ([
    '8.8.8.8' => true, '1.1.1.1' => true,
    '10.0.0.1' => false, '172.16.5.5' => false, '192.168.1.1' => false, '127.0.0.1' => false,
    '169.254.169.254' => false, '100.64.0.1' => false, '100.127.255.254' => false, '0.0.0.0' => false,
    '240.0.0.1' => false, '255.255.255.255' => false, 'not-an-ip' => false,
] as $ip => $want) {
    rt_same($want, HttpClient::isPublicIpv4($ip), "isPublicIpv4({$ip})");
}

foreach ([
    'localhost' => 'blocked', '127.0.0.1' => 'blocked', '2130706433' => 'blocked' /* 十进制 127.0.0.1 */,
    '169.254.169.254' => 'blocked', '10.1.2.3' => 'blocked', '100.64.9.9' => 'blocked',
    '[::1]' => 'blocked', '::1' => 'blocked', 'metadata.google.internal' => 'blocked', 'printer.local' => 'blocked',
    'no-such-host.invalid' => 'unresolved',
] as $host => $why) {
    $r = '';
    $ip = HttpClient::resolvePublicIp($host, $r);
    rt_same('', $ip, "resolvePublicIp({$host}) must not return an IP");
    rt_same($why, $r, "resolvePublicIp({$host}) reason");
}
$r = '';
rt_same('8.8.8.8', HttpClient::resolvePublicIp('8.8.8.8', $r), 'resolvePublicIp(public literal) passes through');

rt_same(['a.example:443:8.8.8.8'], HttpClient::pinResolve('https://a.example/x.m3u8', '8.8.8.8'), 'pinResolve https default port');
rt_same(['a.example:80:8.8.8.8'], HttpClient::pinResolve('http://a.example/x', '8.8.8.8'), 'pinResolve http default port');
rt_same(['a.example:8080:8.8.8.8'], HttpClient::pinResolve('http://a.example:8080/x', '8.8.8.8'), 'pinResolve explicit port');
rt_same([], HttpClient::pinResolve('http://a.example/x', ''), 'pinResolve without validated IP pins nothing');

// ─── 行为：私网 / 云元数据目标在连接前被拦下，记为 skip（不可检测），不计失效 ───
$t0 = microtime(true);
$res = ContentCheck::probeMany([
    'http://169.254.169.254/latest/meta-data/x.m3u8' => 'play',
    'http://10.0.0.8/cover.jpg' => 'image',
    'http://2130706433/a.m3u8' => 'play',
]);
foreach ($res as $u => $r) {
    rt_check(!empty($r['skip']) && empty($r['ok']), "ContentCheck::probeMany({$u}) must be skip, got " . json_encode($r));
}
$res = CollectHealth::probeMany([
    'k1' => ['url' => 'http://169.254.169.254/api.php/provide/vod/', 'name' => 'meta', 'source' => 'hub'],
    'k2' => ['url' => 'http://192.168.1.20/api.php/provide/vod/', 'name' => 'lan', 'source' => 'custom'],
]);
foreach ($res as $k => $r) {
    rt_check(!empty($r['skip']) && empty($r['ok']), "CollectHealth::probeMany({$k}) must be skip, got " . json_encode($r));
}
rt_check(microtime(true) - $t0 < 3, 'private targets must be rejected before connecting (probe took too long → a connection was attempted)');

// ─── 行为：断路器（全部网络层失败 + 外网也不通 = 本机断网，不计失败） ───
$net = ['ok' => false, 'net' => true, 'msg' => 'net'];
$http = ['ok' => false, 'msg' => 'HTTP 404'];
$ok = ['ok' => true, 'msg' => ''];
$skip = ['ok' => false, 'skip' => true, 'msg' => ''];
foreach (['ContentCheck', 'CollectHealth'] as $cls) {
    $fq = 'app\\common\\util\\' . $cls;
    $cases = [
        // [results, egressOk, expected outage]
        [[$net, $net], false, true, 'all net-fail + egress down'],
        [[$net, $net], true, false, 'all net-fail but egress up (sources really dead)'],
        [[$net, $ok], false, false, 'one source reachable'],
        [[$http, $http], false, false, 'HTTP-level failures (peer answered)'],
        [[$skip, $net], false, true, 'skip entries ignored'],
        [[$skip, $skip], false, false, 'nothing probed'],
    ];
    foreach ($cases as $c) {
        HttpClient::$egressOverride = $c[1];
        rt_same($c[2], rt_invoke($fq, 'looksLikeOutage', [$c[0]]), "[breaker] {$cls}::looksLikeOutage: {$c[3]}");
    }
}
HttpClient::$egressOverride = null;

// 断路器必须真的挂在主流程上（不是只存在一个没人调的函数）
$cc = rt_functions(APP_PATH . 'common/util/ContentCheck.php');
rt_check(strpos($cc['checkRows']['body'], 'self::looksLikeOutage(') !== false
    && strpos($cc['checkRows']['body'], 'self::looksLikeOutage(') < strpos($cc['checkRows']['body'], 'self::updateRow('),
    '[breaker] ContentCheck::checkRows must consult looksLikeOutage before writing any state');
rt_check(strpos($cc['run']['body'], '=== null') !== false, '[breaker] ContentCheck::run must stop (not advance cursor) when checkRows reports an outage');
$ch = rt_functions(APP_PATH . 'common/util/CollectHealth.php');
rt_check(strpos($ch['runCheck']['body'], 'self::looksLikeOutage(') !== false
    && strpos($ch['runCheck']['body'], 'self::looksLikeOutage(') < strpos($ch['runCheck']['body'], 'self::mutate('),
    '[breaker] CollectHealth::runCheck must consult looksLikeOutage before mutating endpoint state');
// skip 结果不能累计 fail_count
$rc = $ch['runCheck']['body'];
rt_check(strpos($rc, "\$res['skip']") !== false && strpos($rc, "\$res['skip']") < strpos($rc, "\$row['fail_count'] = intval"),
    '[skip] CollectHealth::runCheck must handle skip results before incrementing fail_count');

// ─── 列 auto_disable_gate：每个「自动停用」写点都受 auto_disable 开关控制 ───
rt_check((bool)preg_match('/\$canDisable\s*=\s*\$autoDisable\s*&&/', $cc['checkRows']['body']), '[auto_disable_gate] ContentCheck::checkRows: $canDisable must be gated by $autoDisable');
rt_check(substr_count($cc['checkRows']['body'], "_status' => 0") === 1, '[auto_disable_gate] ContentCheck::checkRows: exactly one auto-unpublish write expected (new write site must be gated + registered here)');
$gate = strpos($rc, '!$autoDisable');
$write = strpos($rc, "\$row['status'] = '0'");
rt_check($gate !== false && $write !== false && $gate < $write, '[auto_disable_gate] CollectHealth::runCheck: auto-disable write must sit behind the !$autoDisable branch');
rt_check(substr_count($rc, "\$row['status'] = '0'") === 1, '[auto_disable_gate] CollectHealth::runCheck: exactly one auto-disable write expected');

// 定时任务参数解析：显式 0 才关；新登记的内置任务默认 auto_disable=0
$timming = rt_bare('app\\api\\controller\\Timming');
foreach (['' => true, 'auto_disable=1' => true, 'auto_disable=0' => false, 'limit=5&auto_disable=0' => false, 'auto_disable=' => true] as $p => $want) {
    rt_same($want, rt_invoke($timming, 'autoDisableParam', [$p]), "Timming::autoDisableParam('{$p}')");
}
$tf = rt_functions(APP_PATH . 'api/controller/Timming.php');
rt_check(strpos($tf['ensureBuiltinTasks']['body'], "'param' => 'auto_disable=0'") !== false, '[auto_disable_gate] Timming::ensureBuiltinTasks must register built-in check tasks with auto_disable=0');
foreach (['collect_health', 'content_check'] as $task) {
    rt_check(strpos($tf[$task]['body'], 'autoDisableParam(') !== false, "[auto_disable_gate] Timming::{$task} must pass autoDisableParam() through");
}

rt_done('collect probe safety (SSRF / breaker / auto_disable)');
