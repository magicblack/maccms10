<?php
/**
 * 回归：请求路径（控制器 / 模型 / 工具类）不得跑 ALTER TABLE。schema 变更一律走
 * install.sql（全新安装）+ data/update/database.php（存量升级，幂等）。
 * 来源：PR #98 review P2「ContentCheck::ensureColumns() 在 cron HTTP 入口与后台列表里 ALTER 大表」。
 *
 * 跑法：php tests/regression/no_request_ddl.php
 */
require __DIR__ . '/lib/bootstrap.php';

// ─── 列 no_request_ddl ───
// 豁免（格级：相对路径::函数）。均为 PR #98 之前 master 已有的站点，不在本次范围；新增站点不得加进来。
$ddlExempt = [
    'common/model/SeoAiResult.php::ensureUuidColumn'          => 'master 既有（PR #98 前），backlog：迁到 data/update/database.php',
    'common/model/RecycleBinTrait.php::ensureRecycleColumnExists' => 'master 既有（PR #98 前），backlog：迁到 data/update/database.php',
    'common/model/Vod.php::cacheRepeatWithName'               => '临时工作表 vod_repeat（同函数内 DROP/CREATE 后加索引），非 schema 变更',
    'common/model/Vod.php::createRepeatCache'                 => '临时工作表 vod_repeat（同函数内 DROP/CREATE 后加索引），非 schema 变更',
];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_PATH, FilesystemIterator::SKIP_DOTS));
$scanned = 0;
foreach ($it as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (substr($path, -4) !== '.php' || preg_match('#/application/(data|install|extra|lang)/#', $path)) {
        continue;
    }
    $rel = substr($path, strlen(str_replace('\\', '/', APP_PATH)));
    $code = file_get_contents($path);
    if (stripos($code, 'ALTER TABLE') === false) {
        continue;
    }
    foreach (rt_functions($path) as $fn => $f) {
        $scanned++;
        if (!preg_match('/ALTER\s+TABLE/i', $f['body'])) {
            continue;
        }
        $cell = "{$rel}::{$fn}";
        if (isset($ddlExempt[$cell])) {
            rt_exempt("no_request_ddl:{$cell}", $ddlExempt[$cell]);
            unset($ddlExempt[$cell]);
            continue;
        }
        rt_check(false, "[no_request_ddl] {$cell}: ALTER TABLE on the request path — move it to install.sql + data/update/database.php");
    }
}
rt_check($scanned > 0, '[no_request_ddl] scanner found no candidate functions (column broken?)');
foreach ($ddlExempt as $cell => $why) {
    rt_check(false, "[no_request_ddl] stale exemption {$cell} (site gone — remove it from the list)");
}

// ContentCheck 的检测字段必须由升级脚本补（双写 + 幂等）
$upd = file_get_contents(APP_PATH . 'data/update/database.php');
$sql = file_get_contents(APP_PATH . 'install/sql/install.sql');
foreach (['vod', 'art', 'manga'] as $m) {
    rt_check(strpos($sql, "`{$m}_check_state`") !== false, "[schema] install.sql must define {$m}_check_state");
}
rt_check((bool)preg_match("/empty\(\\\$col_list\[\\\$pre \. \\\$module\]\[\\\$module \. '_check_state'\]\)/", $upd), '[schema] data/update/database.php must add *_check_state idempotently (guarded by $col_list)');

// 后台列表（模板直接读 *_check_*）必须补默认值，筛选必须先判字段在不在
foreach (['Vod' => 'vod', 'Art' => 'art', 'Manga' => 'manga'] as $c => $m) {
    $fns = rt_functions(APP_PATH . "admin/controller/{$c}.php");
    $b = isset($fns['data']) ? $fns['data']['body'] : '';
    $fill = strpos($b, "ContentCheck::fillDefaults('{$m}', \$res['list'])");
    $assign = strpos($b, "assign('list'");
    rt_check($fill !== false && $assign !== false && $fill < $assign, "[no_request_ddl] admin {$c}::data must call ContentCheck::fillDefaults('{$m}', ...) before assigning the list");
    rt_check(strpos($b, "ContentCheck::hasColumns('{$m}')") !== false && strpos($b, 'ContentCheck::ensureColumns') === false, "[no_request_ddl] admin {$c}::data must gate the ?check= filter on hasColumns() (no runtime DDL)");
}

// ─── 行为：未升级站点（缺字段）时后台列表补默认值；已升级时不改行 ───
$scenario = isset($argv[1]) ? $argv[1] : '';
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mac_rt_ddl_' . getmypid() . DIRECTORY_SEPARATOR;
if ($scenario === '') {
    // 每个场景要一个全新进程（hasColumns 的结果存在函数 static 里）
    foreach (['missing', 'present'] as $sc) {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $sc . ' 2>&1', $out, $code);
        rt_check($code === 0, "fillDefaults scenario '{$sc}' failed:\n  " . implode("\n  ", $out));
        $out = [];
    }
    rt_done('no request-path DDL');
}

\think\Config::set('cache', ['type' => 'File', 'path' => $cacheDir, 'prefix' => '', 'expire' => 0]);
$present = $scenario === 'present';
// hasColumns() 先读这个缓存键；预置它即可不连库模拟「已升级 / 未升级」
\think\Cache::set('content_check_cols_v2', ['vod' => $present, 'manga' => $present, 'art' => $present], 0);
$rows = [['vod_id' => 1, 'vod_name' => 'a'], ['vod_id' => 2, 'vod_name' => 'b', 'vod_check_state' => 2]];
\app\common\util\ContentCheck::fillDefaults('vod', $rows);
if ($present) {
    rt_same([['vod_id' => 1, 'vod_name' => 'a'], ['vod_id' => 2, 'vod_name' => 'b', 'vod_check_state' => 2]], $rows, 'present: rows untouched');
    rt_same(true, \app\common\util\ContentCheck::hasColumns(), 'present: hasColumns()');
} else {
    rt_same(0, $rows[0]['vod_check_state'], 'missing: default check_state filled');
    rt_same('', $rows[0]['vod_check_reason'], 'missing: default check_reason filled');
    rt_same(0, $rows[0]['vod_check_time'], 'missing: default check_time filled');
    rt_same(2, $rows[1]['vod_check_state'], 'missing: existing values are not overwritten');
    rt_same(false, \app\common\util\ContentCheck::hasColumns('vod'), 'missing: hasColumns(vod)');
    rt_same(0, \app\common\util\ContentCheck::enable('vod', [1, 2], 'rt'), 'missing: admin enable is a clean no-op');
}
array_map('unlink', glob($cacheDir . '*/*') ?: []);
array_map('rmdir', glob($cacheDir . '*', GLOB_ONLYDIR) ?: []);
@rmdir($cacheDir);
rt_done("fillDefaults scenario {$scenario}");
