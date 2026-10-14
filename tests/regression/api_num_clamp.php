<?php
/**
 * 回归：公开 API 列表接口的 num / start 收口 + 列表章节数备注缓存。
 * 来源：PR #98 review P1-3（?num=99999 + 逐行扫十几 MB 正文 = 匿名放大）。
 *
 * 跑法：php tests/regression/api_num_clamp.php
 */
require __DIR__ . '/lib/bootstrap.php';

// ─── 列 num_clamp：api 控制器里每个读请求 num 的 public 方法都必须走 Base::clampNumStart() ───
// 豁免（格级：控制器::方法）
$numExempt = [
    'Type::get_nav_types'          => '查 mac_type 分类表，行数即站点分类数（几十行），num 只做截断，无放大面',
    'Type::get_type_with_children' => '同上，子分类查询受分类表规模约束',
];
$sites = 0;
foreach (glob(APP_PATH . 'api/controller/*.php') as $file) {
    $cls = basename($file, '.php');
    foreach (rt_functions($file) as $fn => $f) {
        if (!$f['public'] || !preg_match('/\[\s*[\'"]num[\'"]\s*\]/', $f['body'])) {
            continue;
        }
        $cell = "{$cls}::{$fn}";
        if (isset($numExempt[$cell])) {
            rt_exempt("num_clamp:{$cell}", $numExempt[$cell]);
            continue;
        }
        $sites++;
        rt_check(false, "[num_clamp] {$cell}: reads request num directly — use list(\$num, \$start) = \$this->clampNumStart(\$param, <default>)");
    }
}
// 反向确认列没跑空：收口调用点数量不应少于已知 10 个
$calls = 0;
foreach (glob(APP_PATH . 'api/controller/*.php') as $file) {
    foreach (rt_functions($file) as $fn => $f) {
        if ($f['public'] && strpos($f['body'], '$this->clampNumStart(') !== false) {
            $calls++;
        }
    }
}
rt_check($calls >= 10, "[num_clamp] expected >= 10 endpoints using clampNumStart, found {$calls} (column scanner broken or endpoints reverted)");

// ─── 行为：clampNumStart ───
$base = rt_bare('app\\api\\controller\\Art');
foreach ([
    [[], 24, [24, 0], 'defaults'],
    [['num' => 99999], 24, [60, 0], 'num capped at 60'],
    [['num' => 60], 24, [60, 0], 'num at cap'],
    [['num' => 0], 24, [24, 0], 'num 0 → default'],
    [['num' => -3], 6, [6, 0], 'negative num → default'],
    [['num' => 'abc'], 6, [6, 0], 'non-numeric num → default'],
    [['num' => 5, 'start' => -5], 6, [5, 0], 'negative start → 0'],
    [['num' => 5, 'start' => 7], 6, [5, 7], 'start passes through'],
] as $c) {
    rt_same($c[2], rt_invoke($base, 'clampNumStart', [$c[0], $c[1]]), "clampNumStart: {$c[3]}");
}
rt_same([100, 0], rt_invoke($base, 'clampNumStart', [['num' => 999], 10, 100]), 'clampNumStart: custom max');

// ─── 行为：章节数缓存（ID + 更新时间为键，未命中才回库，查不到正文的记 0 不重复回库） ───
require_once APP_PATH . 'common.php';
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mac_rt_cache_' . getmypid() . DIRECTORY_SEPARATOR;
\think\Config::set('cache', ['type' => 'File', 'path' => $cacheDir, 'prefix' => '', 'expire' => 0]);
$GLOBALS['config']['app']['cache_flag'] = 'rt' . getmypid();

$rows = [
    ['art_id' => 11, 'art_time' => 1000, 'art_remarks' => '共99章'],
    ['art_id' => 12, 'art_time' => 2000, 'art_remarks' => '共5章'],
];
list($hit, $miss) = mac_rows_chapter_total_cache_get('art', $rows, [11, 12]);
rt_same([], $hit, 'chapter cache: cold → no hits');
rt_same([11, 12], array_keys($miss), 'chapter cache: cold → both miss');

$set = mac_rows_chapter_total_cache_set($miss, [11 => 31]);   // 12 查不到正文
rt_same([11 => 31], $set, 'chapter cache: set returns only positive totals');

list($hit, $miss) = mac_rows_chapter_total_cache_get('art', $rows, [11, 12]);
rt_same([11 => 31], $hit, 'chapter cache: warm → hit for 11');
rt_same([], $miss, 'chapter cache: known-empty 12 must not miss again (would re-query DB every request)');

$rows[0]['art_time'] = 1001;   // 内容更新 → 更新时间变 → 键失效
list($hit, $miss) = mac_rows_chapter_total_cache_get('art', $rows, [11, 12]);
rt_same([11], array_keys($miss), 'chapter cache: updated item invalidates its own key only');

list($hit, $miss) = mac_rows_chapter_total_cache_get('art', $rows, [12]);
rt_same([], array_keys($miss), 'chapter cache: only requested ids are considered');

array_map('unlink', glob($cacheDir . '*/*') ?: []);
array_map('rmdir', glob($cacheDir . '*', GLOB_ONLYDIR) ?: []);
@rmdir($cacheDir);

// 两个 rows 辅助函数都必须走缓存，回库只查未命中的 ID
$common = rt_functions(APP_PATH . 'common.php');
foreach (['mac_art_rows_chapter_remarks', 'mac_manga_rows_chapter_remarks'] as $fn) {
    $b = $common[$fn]['body'];
    rt_check(strpos($b, 'mac_rows_chapter_total_cache_get(') !== false, "[chapter_cache] {$fn} must consult the chapter-total cache");
    rt_check(strpos($b, 'array_keys($miss)') !== false && strpos($b, 'implode(\',\', array_unique($ids))') === false, "[chapter_cache] {$fn} must only query DB for cache misses");
}

rt_done('api num clamp + chapter-count cache');
