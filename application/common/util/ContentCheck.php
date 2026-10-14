<?php
namespace app\common\util;

use think\Db;
use think\Cache;
use app\common\util\MeilisearchSync;

/**
 * 内容资源可用性检测（视频播放地址 / 漫画章节图片 / 封面图）
 *
 * 每天对已审核（status=1）的内容做一轮检测，分多次定时任务续跑（游标存于 LOG_DIR/.cursor.json）。
 *   - 视频：每个播放源取第一集；全部播放源失效才算失败，部分失效仅记录原因
 *   - 漫画：取首话、末话的第一张图；全部失效才算失败
 *   - 文章（小说）/明星/角色/网址：只检测封面，失败只记录不停用
 * 连续 FAIL_LIMIT 天失败的视频 / 漫画自动停用（status=0, check_state=3），不会自动恢复；
 * 采集更新了播放/章节地址时重置检测状态并恢复（见 recollectReset）。
 * 自动停用受定时任务参数 auto_disable 控制（新登记的任务默认 0，只记录不下架）；
 * 一批地址全部网络层失败且外网探测也不通时视为本机断网，整批不写状态（断路器，见 looksLikeOutage）。
 *
 * 字段（vod/manga/art）：{p}_check_state 0未检测 1正常 2失败 3已自动停用 4管理员停用；{p}_check_reason；{p}_check_time
 * 管理员停用（4）不会被采集更新自动恢复，只能由管理员恢复
 * 日志：application/data/content_check_log/YYYYMMDD.log，每行一条 JSON，保留 LOG_KEEP_DAYS 天
 */
class ContentCheck
{
    const STATE_UNCHECKED = 0;
    const STATE_OK = 1;
    const STATE_FAILING = 2;
    const STATE_DISABLED = 3;
    const STATE_ADMIN_DISABLED = 4;

    /** 连续失败多少天后自动停用 */
    const FAIL_LIMIT = 2;
    const LOG_KEEP_DAYS = 30;
    const CONCURRENCY = 20;
    /** 单次定时任务最长运行秒数，到点保存游标，下次续跑 */
    const TIME_BUDGET = 240;
    const BATCH = 100;
    /** 只读取响应前若干字节（判断 m3u8 头 / 是否为网页） */
    const READ_BYTES = 4096;
    /** 手动跟随重定向的最大跳数（每一跳都重新做公网校验） */
    const MAX_REDIRECTS = 3;

    /**
     * mid：模块 ID；disable：失败是否自动停用；cols：是否有 check_* 字段；
     * kind：检测方式（play 播放地址 / chapter 漫画章节 / pic 封面）
     */
    public static $modules = [
        'vod' => ['mid' => 1, 'disable' => true, 'cols' => true, 'kind' => 'play'],
        'manga' => ['mid' => 12, 'disable' => true, 'cols' => true, 'kind' => 'chapter'],
        'art' => ['mid' => 2, 'disable' => false, 'cols' => true, 'kind' => 'pic'],
        'actor' => ['mid' => 8, 'disable' => false, 'cols' => false, 'kind' => 'pic'],
        'role' => ['mid' => 9, 'disable' => false, 'cols' => false, 'kind' => 'pic'],
        'website' => ['mid' => 11, 'disable' => false, 'cols' => false, 'kind' => 'pic'],
    ];

    /** 本次运行内的地址结果缓存 / 不可达主机 */
    private static $urlCache = [];
    private static $deadHosts = [];

    private static function logDir()
    {
        return APP_PATH . 'data/content_check_log/';
    }

    public static function moduleByMid($mid)
    {
        foreach (self::$modules as $m => $cfg) {
            if ($cfg['mid'] == $mid) {
                return $m;
            }
        }
        return '';
    }

    /**
     * check_* 字段是否已就位。字段只由升级脚本 data/update/database.php（及全新安装 install.sql）补，
     * 请求路径不跑 DDL（大表 ALTER 会长时间锁表，且 Web 账号未必有 ALTER 权限）。
     * 只读 SHOW COLUMNS；仅在全部就位时缓存，缺列时每次重查，跑完升级即生效、无需清缓存。
     * @param string|null $m 模块（vod/manga/art）；null = 全部带字段的模块
     * @return bool
     */
    public static function hasColumns($m = null)
    {
        static $state = null;
        if ($state === null) {
            $cached = Cache::get('content_check_cols_v2');
            if (is_array($cached)) {
                $state = $cached;
            } else {
                $state = [];
                $pre = config('database.prefix');
                foreach (self::$modules as $mm => $cfg) {
                    if (!$cfg['cols']) {
                        continue;
                    }
                    try {
                        $state[$mm] = !empty(Db::query("SHOW COLUMNS FROM `{$pre}{$mm}` LIKE '{$mm}\\_check\\_state'"));
                    } catch (\Exception $e) {
                        $state[$mm] = false;
                    }
                }
                if (!in_array(false, $state, true)) {
                    Cache::set('content_check_cols_v2', $state, 0);
                }
            }
        }
        if ($m === null) {
            return !in_array(false, $state, true);
        }
        return !empty($state[$m]);
    }

    /**
     * 未升级（缺 check_* 字段）的站点：给后台列表行补默认值，模板直接读 $vo['xxx_check_state'] 不会报未定义下标
     */
    public static function fillDefaults($m, array &$rows)
    {
        if (self::hasColumns($m)) {
            return;
        }
        foreach ($rows as &$r) {
            $r += [$m . '_check_state' => self::STATE_UNCHECKED, $m . '_check_reason' => '', $m . '_check_time' => 0];
        }
        unset($r);
    }

    // ─── 每日检测 ─────────────────────────────

    private static function loadCursor()
    {
        $file = self::logDir() . '.cursor.json';
        $c = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return is_array($c) ? $c : [];
    }

    private static function saveCursor(array $c)
    {
        @file_put_contents(self::logDir() . '.cursor.json', json_encode($c), LOCK_EX);
    }

    /**
     * 定时任务入口：续跑当天的检测，超出 TIME_BUDGET 后保存游标退出
     * @param bool $autoDisable 连续失败是否自动停用（关闭时只记录失败，不下架）
     * @return string 运行摘要
     */
    public static function run($autoDisable = true)
    {
        @set_time_limit(0);
        // 缺 check_* 字段（站点未跑升级）的模块降级为只记日志：不写检测状态、不自动停用（连续失败判定依赖该字段）
        $missing = [];
        foreach (self::$modules as $mm => $cfg) {
            if ($cfg['cols'] && !self::hasColumns($mm)) {
                self::$modules[$mm]['cols'] = false;
                self::$modules[$mm]['disable'] = false;
                $missing[] = $mm;
            }
        }
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // 防止上一轮尚未结束时重入
        $fp = @fopen($dir . '.run.lock', 'c');
        if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return lang('admin/content_check/running');
        }

        $start = time();
        $today = date('Ymd');
        $cursor = self::loadCursor();
        if (!isset($cursor['date']) || $cursor['date'] !== $today) {
            $cursor = ['date' => $today, 'idx' => 0, 'last_id' => 0, 'done' => false, 'stat' => []];
            self::purgeLogs();
            // 每天一轮只提示一次，避免每 5 分钟一条
            if (!empty($missing)) {
                self::log(implode(',', $missing), 0, '', 'summary', 'fail', lang('admin/content_check/need_upgrade'));
            }
        }
        $names = array_keys(self::$modules);
        $checked = 0;
        $outage = false;

        while (empty($cursor['done']) && time() - $start < self::TIME_BUDGET) {
            if ($cursor['idx'] >= count($names)) {
                $cursor['done'] = true;
                break;
            }
            $m = $names[$cursor['idx']];
            $rows = self::fetchBatch($m, $cursor['last_id']);
            if (empty($rows)) {
                $st = isset($cursor['stat'][$m]) ? $cursor['stat'][$m] : [];
                self::log($m, 0, '', 'summary', 'ok', lang('admin/content_check/summary', [
                    isset($st['checked']) ? $st['checked'] : 0,
                    isset($st['fail']) ? $st['fail'] : 0,
                    isset($st['disabled']) ? $st['disabled'] : 0,
                    isset($st['partial']) ? $st['partial'] : 0,
                ]));
                $cursor['idx']++;
                $cursor['last_id'] = 0;
                self::saveCursor($cursor);
                continue;
            }
            $stat = self::checkRows($m, $rows, $autoDisable);
            if ($stat === null) {
                // 断路器：本机网络故障，本批不写任何状态、游标不前进，下次定时任务从这一批续跑
                $outage = true;
                self::log($m, 0, '', 'summary', 'fail', lang('admin/content_check/breaker'));
                break;
            }
            foreach ($stat as $k => $v) {
                $cursor['stat'][$m][$k] = (isset($cursor['stat'][$m][$k]) ? $cursor['stat'][$m][$k] : 0) + $v;
            }
            $checked += count($rows);
            $last = end($rows);
            $cursor['last_id'] = intval($last[$m . '_id']);
            self::saveCursor($cursor);
        }
        self::saveCursor($cursor);
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        if ($outage) {
            return lang('admin/content_check/breaker');
        }
        return lang('admin/content_check/run_tip', [$checked, empty($cursor['done']) ? lang('admin/content_check/pass_continue') : lang('admin/content_check/pass_done')]);
    }

    private static function fetchBatch($m, $lastId)
    {
        $cfg = self::$modules[$m];
        $fields = [$m . '_id', $m . '_name', $m . '_lock'];
        if ($cfg['kind'] === 'play') {
            $fields[] = 'vod_play_from';
            $fields[] = 'vod_play_url';
        } elseif ($cfg['kind'] === 'chapter') {
            $fields[] = 'manga_chapter_url';
        } else {
            $fields[] = $m . '_pic';
        }
        if ($cfg['cols']) {
            $fields[] = $m . '_check_state';
        }
        $where = [$m . '_id' => ['gt', $lastId], $m . '_status' => 1];
        if (in_array($m, ['vod', 'art', 'manga'], true)) {
            $where[$m . '_recycle_time'] = 0;
        }
        try {
            return Db::name($m)->field(implode(',', $fields))->where($where)->order($m . '_id asc')->limit(self::BATCH)->select();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 每条内容生成待测地址：[[label, url, type], ...]，type: play / image
     */
    private static function targets($m, array $row)
    {
        $kind = self::$modules[$m]['kind'];
        $out = [];
        if ($kind === 'play') {
            $froms = explode('$$$', (string)$row['vod_play_from']);
            $urls = explode('$$$', (string)$row['vod_play_url']);
            foreach ($urls as $i => $group) {
                $first = explode('#', trim($group))[0];
                $parts = explode('$', $first);
                $url = trim(isset($parts[1]) ? $parts[1] : $parts[0]);
                $url = self::normalizeUrl($url);
                if ($url !== '') {
                    $out[] = [isset($froms[$i]) && $froms[$i] !== '' ? $froms[$i] : ('#' . ($i + 1)), $url, 'play'];
                }
            }
        } elseif ($kind === 'chapter') {
            $group = explode('$$$', (string)$row['manga_chapter_url'])[0];
            $chapters = array_values(array_filter(explode('#', trim($group)), 'strlen'));
            if (!empty($chapters)) {
                $pick = count($chapters) > 1 ? [0, count($chapters) - 1] : [0];
                foreach ($pick as $i) {
                    $parts = explode('$', $chapters[$i]);
                    $imgs = isset($parts[1]) ? $parts[1] : $parts[0];
                    $url = self::normalizeUrl(explode(',', $imgs)[0]);
                    if ($url !== '') {
                        $out[] = [trim($parts[0]), $url, 'image'];
                    }
                }
            }
        } else {
            $url = self::normalizeUrl((string)$row[$m . '_pic']);
            if ($url !== '') {
                $out[] = [lang('admin/content_check/label_pic'), $url, 'image'];
            }
        }
        return $out;
    }

    /** 仅检测远程 http(s) 地址；本地上传 / 解析类播放（非 URL）跳过 */
    private static function normalizeUrl($url)
    {
        $url = trim((string)$url);
        if (stripos($url, 'mac://') === 0) {
            $url = 'http://' . substr($url, 6);
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        if (!preg_match('#^https?://[^/\s]+#i', $url)) {
            return '';
        }
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return '';
        }
        return $url;
    }

    /**
     * 检测一批内容并写回状态
     * @return array|null 统计 checked/fail/disabled/partial；判定为本机网络故障时返回 null 且不写任何状态
     */
    private static function checkRows($m, array $rows, $autoDisable = true)
    {
        $cfg = self::$modules[$m];
        $stat = ['checked' => 0, 'fail' => 0, 'disabled' => 0, 'partial' => 0];
        $jobs = [];
        $targets = [];
        foreach ($rows as $row) {
            $t = self::targets($m, $row);
            $targets[$row[$m . '_id']] = $t;
            foreach ($t as $x) {
                $jobs[$x[1]] = $x[2];
            }
        }
        $results = self::probeMany($jobs);
        if (self::looksLikeOutage($results)) {
            return null;
        }
        $now = time();
        $disabledIds = [];

        foreach ($rows as $row) {
            $id = intval($row[$m . '_id']);
            // 指向内网 / 仅 IPv6 等不可检测的地址不计入判定（既不算可用也不算失效）
            $t = array_values(array_filter($targets[$id], function ($x) use ($results) {
                return empty($results[$x[1]]['skip']);
            }));
            if (empty($t)) {
                continue;
            }
            $stat['checked']++;
            $failed = [];
            foreach ($t as $x) {
                $r = $results[$x[1]];
                if (!$r['ok']) {
                    $failed[] = $x[0] . ': ' . $r['msg'];
                }
            }
            $name = (string)$row[$m . '_name'];

            if (count($failed) < count($t)) {
                // 至少一个可用：正常；部分失效记录原因
                $reason = empty($failed) ? '' : lang('admin/content_check/partial', [implode('; ', $failed)]);
                if (!empty($failed)) {
                    $stat['partial']++;
                    self::log($m, $id, $name, 'partial', 'fail', $reason);
                }
                if ($cfg['cols']) {
                    self::updateRow($m, $id, [$m . '_check_state' => self::STATE_OK, $m . '_check_reason' => mb_substr($reason, 0, 250), $m . '_check_time' => $now]);
                }
                continue;
            }

            $stat['fail']++;
            $reason = implode('; ', $failed);
            if (!$cfg['cols']) {
                self::log($m, $id, $name, 'fail', 'fail', $reason);
                continue;
            }
            $prev = intval($row[$m . '_check_state']);
            $canDisable = $autoDisable && $cfg['disable'] && intval($row[$m . '_lock']) !== 1;
            if ($prev === self::STATE_FAILING && $canDisable) {
                $msg = lang('admin/content_check/reason_auto', [self::FAIL_LIMIT, $reason]);
                self::updateRow($m, $id, [$m . '_status' => 0, $m . '_check_state' => self::STATE_DISABLED, $m . '_check_reason' => mb_substr($msg, 0, 250), $m . '_check_time' => $now]);
                self::clearDetailCache($m, $id);
                $disabledIds[] = $id;
                $stat['disabled']++;
                self::log($m, $id, $name, 'disable', 'fail', $msg);
            } else {
                $msg = lang('admin/content_check/fail_n', [$prev === self::STATE_FAILING ? self::FAIL_LIMIT : 1, $reason]);
                if ($prev === self::STATE_FAILING && intval($row[$m . '_lock']) === 1 && $cfg['disable']) {
                    $msg .= ' ' . lang('admin/content_check/locked_keep');
                } elseif ($prev === self::STATE_FAILING && !$autoDisable && $cfg['disable']) {
                    $msg .= ' ' . lang('admin/content_check/auto_off');
                }
                self::updateRow($m, $id, [$m . '_check_state' => self::STATE_FAILING, $m . '_check_reason' => mb_substr($msg, 0, 250), $m . '_check_time' => $now]);
                self::log($m, $id, $name, 'fail', 'fail', $msg);
            }
        }
        self::afterStatusChange($m, $disabledIds);
        return $stat;
    }

    private static function updateRow($m, $id, array $data)
    {
        try {
            Db::name($m)->where($m . '_id', $id)->update($data);
        } catch (\Exception $e) {
        }
    }

    /** 与 common\model\Base::clearDetailCache 的缓存键保持一致 */
    private static function clearDetailCache($m, $id)
    {
        $en = (string)Db::name($m)->where($m . '_id', $id)->value($m . '_en');
        $prefix = $GLOBALS['config']['app']['cache_flag'] . '_' . $m . '_detail_';
        Cache::rm($prefix . $id . '_' . $en);
        Cache::rm($prefix . $id . '_');
        Cache::rm($prefix . '_' . $en);
    }

    /**
     * 上下架后同步前台：清列表缓存（listCacheData 按模型 $name 打 tag），并同步 Meilisearch 文档
     * （status!=1 时 MeilisearchSync 会删除文档，搜索结果里不再出现）
     */
    public static function afterStatusChange($m, array $ids)
    {
        if (empty($ids)) {
            return;
        }
        // 列表缓存 tag 是模型 $name（小写表名，如 manga），统一走模型自己的 clearListCache()，不要手拼 tag
        model(ucfirst($m))->clearListCache();
        $sync = ['vod' => 'afterVodSave', 'art' => 'afterArtSave', 'manga' => 'afterMangaSave'];
        if (isset($sync[$m])) {
            foreach ($ids as $id) {
                MeilisearchSync::{$sync[$m]}((int)$id);
            }
        }
    }

    /**
     * 断路器：本批没有任何地址可用、且存在网络层失败（解析 / 连接 / 超时）时，探测外网；
     * 外网也不通 = 本机断网或 DNS 故障，不是内容失效，本批不能计失败（否则两轮就全站下架）。
     * 只要有一个地址可用，或失败都是 HTTP 层（对方有响应），就按正常结果处理。
     */
    private static function looksLikeOutage(array $results)
    {
        $net = 0;
        foreach ($results as $r) {
            if (!empty($r['skip'])) {
                continue;
            }
            if (!empty($r['ok'])) {
                return false;
            }
            if (!empty($r['net'])) {
                $net++;
            }
        }
        return $net > 0 && !HttpClient::egressOk();
    }

    // ─── 探测 ─────────────────────────────

    /**
     * 并发探测（滚动补位），每个地址只读前 READ_BYTES 字节
     * @param array $jobs [url => 'play'|'image']
     * @return array [url => ['ok'=>bool,'msg'=>string]]
     */
    public static function probeMany(array $jobs)
    {
        $results = [];
        $queue = [];
        foreach ($jobs as $url => $type) {
            if (isset(self::$urlCache[$url])) {
                $results[$url] = self::$urlCache[$url];
                continue;
            }
            // key：原始地址（结果按它回填）；url：当前这一跳（手动跟随重定向时会变）
            $queue[] = ['key' => $url, 'url' => $url, 'type' => $type, 'hops' => 0];
        }
        if (empty($queue)) {
            return $results;
        }

        $mh = curl_multi_init();
        $active = [];
        $bufs = [];
        $finish = function (array $job, array $res) use (&$results) {
            $results[$job['key']] = self::$urlCache[$job['key']] = $res;
        };
        $start = function () use ($mh, &$active, &$queue, &$bufs, $finish) {
            while (count($active) < self::CONCURRENCY && !empty($queue)) {
                $job = array_shift($queue);
                $host = strtolower((string)parse_url($job['url'], PHP_URL_HOST));
                if (isset(self::$deadHosts[$host])) {
                    $finish($job, ['ok' => false, 'net' => true, 'msg' => self::$deadHosts[$host]]);
                    continue;
                }
                // SSRF：地址来自采集源，每一跳都先解析并确认为公网 IP，再用 CURLOPT_RESOLVE 钉住连接
                // （防 DNS rebinding）；内网 / 保留 / 云元数据地址不连接，记为「不可检测」而非失效
                $why = '';
                $ip = HttpClient::resolvePublicIp($host, $why);
                if ($ip === '') {
                    if ($why === 'unresolved') {
                        self::$deadHosts[$host] = lang('admin/content_check/err_net', [curl_strerror(CURLE_COULDNT_RESOLVE_HOST)]);
                        $finish($job, ['ok' => false, 'net' => true, 'msg' => self::$deadHosts[$host]]);
                    } else {
                        $finish($job, ['ok' => false, 'skip' => true, 'msg' => '']);
                    }
                    continue;
                }
                $ch = curl_init();
                $id = is_resource($ch) ? (int)$ch : spl_object_id($ch);
                $bufs[$id] = '';
                curl_setopt($ch, CURLOPT_URL, $job['url']);
                curl_setopt($ch, CURLOPT_RESOLVE, HttpClient::pinResolve($job['url'], $ip));
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
                // 不让 curl 自动跟随：跳转目标未经校验会绕过上面的公网判定，改为在完成回调里逐跳校验
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                // 读够即中止（返回值不等于数据长度会让 curl 以 WRITE_ERROR 结束，按已读内容判断）
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($c, $data) use (&$bufs, $id) {
                    $bufs[$id] .= $data;
                    return strlen($bufs[$id]) >= self::READ_BYTES ? 0 : strlen($data);
                });
                curl_multi_add_handle($mh, $ch);
                $active[$id] = $job + ['host' => $host];
            }
        };
        $start();
        while (!empty($active)) {
            curl_multi_exec($mh, $running);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $id = is_resource($ch) ? (int)$ch : spl_object_id($ch);
                $job = $active[$id];
                $errno = $info['result'];
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                $next = in_array($code, [301, 302, 303, 307, 308], true) ? (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL) : '';
                if ($next !== '' && $job['hops'] < self::MAX_REDIRECTS && preg_match('#^https?://#i', $next)) {
                    // 跳转：下一跳重新排队，出队时同样走公网校验 + 钉 IP
                    $queue[] = ['key' => $job['key'], 'url' => $next, 'type' => $job['type'], 'hops' => $job['hops'] + 1];
                } else {
                    $res = self::judge($job['type'], $job['url'], $bufs[$id], $code, (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE), $errno);
                    if (in_array($errno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true)) {
                        self::$deadHosts[$job['host']] = $res['msg'];
                    }
                    $finish($job, $res);
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($active[$id], $bufs[$id]);
                $start();
            }
            if (!empty($active)) {
                curl_multi_select($mh, 1);
            }
        }
        curl_multi_close($mh);
        return $results;
    }

    private static function judge($type, $url, $body, $code, $ctype, $errno)
    {
        if ($errno !== CURLE_OK && !($errno === CURLE_WRITE_ERROR && $body !== '')) {
            return ['ok' => false, 'net' => true, 'msg' => lang('admin/content_check/err_net', [curl_strerror($errno)])];
        }
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'msg' => 'HTTP ' . $code];
        }
        $isHtml = stripos($ctype, 'text/html') !== false;
        if ($type === 'image') {
            if ($isHtml) {
                return ['ok' => false, 'msg' => lang('admin/content_check/err_not_image')];
            }
            return ['ok' => true, 'msg' => ''];
        }
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        if (substr($path, -5) === '.m3u8') {
            if (strpos(ltrim($body, "\xEF\xBB\xBF \t\r\n"), '#EXTM3U') !== 0) {
                return ['ok' => false, 'msg' => lang('admin/content_check/err_not_m3u8')];
            }
        }
        return ['ok' => true, 'msg' => ''];
    }

    // ─── 采集 / 管理员 ─────────────────────────────

    /**
     * 采集更新了播放 / 章节地址时调用：失败或已自动停用的内容重置检测状态，自动停用的恢复上架
     * @param string $m    vod / manga
     * @param array|\think\Model $info 采集前的数据库记录
     * @return array 需要合并进 update 的字段
     */
    public static function recollectReset($m, $info)
    {
        if (is_object($info) && method_exists($info, 'toArray')) {
            $info = $info->toArray();
        }
        if (!is_array($info)) {
            return [];
        }
        $f = $m . '_check_state';
        if (!isset($info[$f]) || !in_array(intval($info[$f]), [self::STATE_FAILING, self::STATE_DISABLED], true)) {
            return [];
        }
        $data = [$f => self::STATE_UNCHECKED, $m . '_check_reason' => '', $m . '_check_time' => 0];
        if (intval($info[$f]) === self::STATE_DISABLED) {
            $data[$m . '_status'] = 1;
        }
        self::log($m, intval($info[$m . '_id']), (string)$info[$m . '_name'], 'recollect', 'ok', lang('admin/content_check/log_recollect'));
        return $data;
    }

    /**
     * 管理员恢复：status=1，检测状态清零（检测失败 / 自动停用 / 管理员停用的都可恢复）
     * @return int 处理条数
     */
    public static function enable($m, array $ids, $admin, $reason = '')
    {
        $rows = self::adminRows($m, $ids, [self::STATE_FAILING, self::STATE_DISABLED, self::STATE_ADMIN_DISABLED]);
        $reason = trim((string)$reason);
        foreach ($rows as $r) {
            $data = [$m . '_check_state' => self::STATE_UNCHECKED, $m . '_check_reason' => '', $m . '_check_time' => 0];
            if (in_array(intval($r[$m . '_check_state']), [self::STATE_DISABLED, self::STATE_ADMIN_DISABLED], true)) {
                $data[$m . '_status'] = 1;
            }
            self::updateRow($m, $r[$m . '_id'], $data);
            self::clearDetailCache($m, $r[$m . '_id']);
            $msg = lang('admin/content_check/log_enabled', [$admin]) . ($reason !== '' ? lang('admin/content_check/reason_suffix', [$reason]) : '');
            self::log($m, $r[$m . '_id'], $r[$m . '_name'], 'enable', 'ok', $msg);
        }
        self::afterStatusChange($m, array_column($rows, $m . '_id'));
        return count($rows);
    }

    /**
     * 管理员停用：status=0，check_state=4，记录原因（每日检测与采集更新都不会自动恢复）
     * @return int 处理条数
     */
    public static function disable($m, array $ids, $admin, $reason)
    {
        $rows = self::adminRows($m, $ids, null);
        $msg = lang('admin/content_check/reason_admin', [$admin, trim((string)$reason)]);
        $now = time();
        foreach ($rows as $r) {
            if (intval($r[$m . '_check_state']) === self::STATE_ADMIN_DISABLED) {
                continue;
            }
            self::updateRow($m, $r[$m . '_id'], [$m . '_status' => 0, $m . '_check_state' => self::STATE_ADMIN_DISABLED, $m . '_check_reason' => mb_substr($msg, 0, 250), $m . '_check_time' => $now]);
            self::clearDetailCache($m, $r[$m . '_id']);
            self::log($m, $r[$m . '_id'], $r[$m . '_name'], 'admin_disable', 'ok', $msg);
        }
        self::afterStatusChange($m, array_column($rows, $m . '_id'));
        return count($rows);
    }

    private static function adminRows($m, array $ids, $states)
    {
        if (!isset(self::$modules[$m]) || !self::$modules[$m]['cols']) {
            return [];
        }
        if (!self::hasColumns($m)) {
            return [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }
        $q = Db::name($m)->field($m . '_id,' . $m . '_name,' . $m . '_check_state')->where($m . '_id', 'in', $ids);
        if ($states !== null) {
            $q->where($m . '_check_state', 'in', $states);
        }
        return $q->select();
    }

    // ─── 日志 ─────────────────────────────

    /**
     * @param string $kind summary / fail / partial / disable / admin_disable / enable / recollect
     */
    public static function log($m, $id, $name, $kind, $result, $msg)
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = json_encode([
            'time' => time(),
            'module' => $m,
            'id' => intval($id),
            'name' => (string)$name,
            'kind' => $kind,
            'result' => $result,
            'msg' => (string)$msg,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($dir . date('Ymd') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 读取某天日志（倒序），支持按模块 / 类型 / 内容 ID / 关键字筛选
     * @return array ['list','total']
     */
    public static function readLogs($date, array $filter, $page, $limit)
    {
        // $date 为空：查全部保留天数（按单条内容查看历史时使用），新到旧
        if ($date === '') {
            $files = (array)glob(self::logDir() . '*.log');
            rsort($files);
        } else {
            $files = [self::logDir() . preg_replace('/\D/', '', $date) . '.log'];
        }
        $rows = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $r = json_decode($lines[$i], true);
                if (!is_array($r)) {
                    continue;
                }
                if (!empty($filter['module']) && $r['module'] !== $filter['module']) {
                    continue;
                }
                if (!empty($filter['kind']) && $r['kind'] !== $filter['kind']) {
                    continue;
                }
                if (!empty($filter['id']) && intval($r['id']) !== intval($filter['id'])) {
                    continue;
                }
                if (!empty($filter['wd']) && stripos($r['name'] . ' ' . $r['msg'], $filter['wd']) === false) {
                    continue;
                }
                $rows[] = $r;
            }
        }
        return ['list' => array_slice($rows, ($page - 1) * $limit, $limit), 'total' => count($rows)];
    }

    public static function logDates()
    {
        $dates = [];
        foreach ((array)glob(self::logDir() . '*.log') as $f) {
            $d = basename($f, '.log');
            if (preg_match('/^\d{8}$/', $d)) {
                $dates[] = substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
            }
        }
        rsort($dates);
        return $dates;
    }

    public static function cursorInfo()
    {
        return self::loadCursor();
    }

    public static function purgeLogs()
    {
        $cut = date('Ymd', strtotime('-' . self::LOG_KEEP_DAYS . ' days'));
        foreach ((array)glob(self::logDir() . '*.log') as $f) {
            $d = basename($f, '.log');
            if (preg_match('/^\d{8}$/', $d) && $d < $cut) {
                @unlink($f);
            }
        }
    }
}
