<?php
namespace app\common\util;

use think\Db;

/**
 * 采集接口可用性（自定义接口 + 资源站中心）
 *
 * 状态：application/extra/collect_health.php，按 md5(接口地址) 索引；无记录视为启用。
 * 日志：application/data/collect_log/YYYYMMDD.log，每行一条 JSON，保留 LOG_KEEP_DAYS 天。
 *
 * 每小时检测只会「停用」（连续 FAIL_LIMIT 次失败），不会自动恢复；恢复需管理员在后台手动启用。
 * 已停用的接口，定时采集任务会跳过并记录日志。
 * 自动停用受定时任务参数 auto_disable 控制（新登记的任务默认 0）；本机断网时本轮不计失败（见 looksLikeOutage）。
 */
class CollectHealth
{
    /** 连续失败多少次后自动停用 */
    const FAIL_LIMIT = 2;
    /** 日志保留天数 */
    const LOG_KEEP_DAYS = 30;
    /** 并发检测数 */
    const PROBE_CONCURRENCY = 10;
    /** 手动跟随重定向的最大跳数（每一跳都重新做公网校验） */
    const MAX_REDIRECTS = 3;
    /**
     * 证书校验失败的 curl 错误码：51 旧版 PEER_CERTIFICATE / 60 PEER_FAILED_VERIFICATION（含 CA 不受信、过期、自签）/
     * 77 本机 CA 证书包读取失败。只有这几种才降级为不校验重试，握手失败、超时等其它错误照常记失败。
     */
    const CERT_ERRNOS = [51, 60, 77];

    const KIND_CHECK = 'check';
    const KIND_RUN = 'schedule_run';
    const KIND_SKIP = 'schedule_skip';
    const KIND_TOGGLE = 'toggle';

    public static function key($url)
    {
        return md5(trim((string)$url));
    }

    private static function statusFile()
    {
        return APP_PATH . 'extra/collect_health.php';
    }

    private static function logDir()
    {
        return APP_PATH . 'data/collect_log/';
    }

    /**
     * 直接读文件（绕过 config 缓存 / opcache 旧值），保证写回时基于最新内容
     * @return array
     */
    public static function all()
    {
        $file = self::statusFile();
        if (!is_file($file)) {
            return [];
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        $data = include $file;
        return is_array($data) ? $data : [];
    }

    public static function get($url)
    {
        $all = self::all();
        $key = self::key($url);
        return isset($all[$key]) ? $all[$key] : null;
    }

    public static function isEnabled($url)
    {
        $row = self::get($url);
        return $row === null || (string)$row['status'] === '1';
    }

    /**
     * 加锁读-改-写状态文件，避免每小时检测与后台手动切换互相覆盖
     * @param callable $fn function(array &$all)
     * @return bool
     */
    private static function mutate(callable $fn)
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fp = @fopen($dir . '.status.lock', 'c');
        if ($fp) {
            flock($fp, LOCK_EX);
        }
        $all = self::all();
        $fn($all);
        $res = mac_arr2file(self::statusFile(), $all);
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $res !== false;
    }

    private static function emptyRow($url, $name, $source)
    {
        return [
            'url' => $url,
            'name' => $name,
            'source' => $source,
            'status' => '1',
            'disabled_by' => '',
            'reason' => '',
            'fail_count' => 0,
            'last_check' => 0,
            'last_error' => '',
            'last_ok' => 0,
            'updated' => 0,
        ];
    }

    /**
     * 管理员手动启用 / 停用
     * @return bool
     */
    public static function setStatus($url, $status, $name = '', $source = '', $admin = '')
    {
        $url = trim((string)$url);
        $key = self::key($url);
        $status = $status ? '1' : '0';
        $ok = self::mutate(function (&$all) use ($key, $url, $name, $source, $status, $admin) {
            $row = isset($all[$key]) ? $all[$key] : self::emptyRow($url, $name, $source);
            if ($name !== '') {
                $row['name'] = $name;
            }
            if ($source !== '') {
                $row['source'] = $source;
            }
            $row['status'] = $status;
            if ($status === '1') {
                $row['disabled_by'] = '';
                $row['reason'] = '';
                $row['fail_count'] = 0;
            } else {
                $row['disabled_by'] = 'admin';
                $row['reason'] = lang('admin/collect_health/reason_admin', [$admin]);
            }
            $row['updated'] = time();
            $all[$key] = $row;
        });
        if ($ok) {
            self::log(self::KIND_TOGGLE, $url, $name, 'ok', lang($status === '1' ? 'admin/collect_health/log_enabled' : 'admin/collect_health/log_disabled', [$admin]));
        }
        return $ok;
    }

    /**
     * 需要检测的全部接口：自定义接口（mac_collect）+ 资源站中心（云端 + 自定义资源站），按地址去重
     * @return array [key => ['url','name','source','param']]
     */
    public static function endpoints()
    {
        $list = [];
        try {
            $rows = Db::name('collect')->field('collect_name,collect_url,collect_param')->select();
        } catch (\Exception $e) {
            $rows = [];
        }
        foreach ((array)$rows as $r) {
            $url = trim((string)$r['collect_url']);
            if ($url === '') {
                continue;
            }
            $list[self::key($url)] = ['url' => $url, 'name' => (string)$r['collect_name'], 'source' => 'custom', 'param' => (string)$r['collect_param']];
        }
        $hub = array_merge(ResourceHubSites::cloud(), ResourceHubSites::custom());
        foreach ($hub as $site) {
            $url = trim((string)(isset($site['url']) ? $site['url'] : ''));
            if ($url === '' || isset($list[self::key($url)])) {
                continue;
            }
            $list[self::key($url)] = ['url' => $url, 'name' => (string)(isset($site['name']) ? $site['name'] : ''), 'source' => 'hub', 'param' => ''];
        }
        return $list;
    }

    /**
     * 每小时检测：探测全部接口，记录日志，连续失败 FAIL_LIMIT 次则自动停用
     * @param bool $autoDisable 连续失败是否自动停用（关闭时只累计失败次数并记录日志）
     * @return array ['total','ok','fail','disabled','outage']
     */
    public static function runCheck($autoDisable = true)
    {
        @set_time_limit(0);
        $endpoints = self::endpoints();
        $results = self::probeMany($endpoints);
        $stat = ['total' => count($endpoints), 'ok' => 0, 'fail' => 0, 'disabled' => 0, 'outage' => 0];

        // 断路器：没有任何接口可用且存在网络层失败时探测外网；外网也不通 = 本机断网 / DNS 故障，
        // 本轮不累计失败次数（否则两小时就把全部接口停用，且不会自动恢复）
        if (self::looksLikeOutage($results)) {
            $stat['outage'] = 1;
            $stat['fail'] = count($endpoints);
            self::log(self::KIND_CHECK, '', '', 'fail', lang('admin/collect_health/breaker'));
            return $stat;
        }

        $now = time();
        $logs = [];

        self::mutate(function (&$all) use ($endpoints, $results, $now, $autoDisable, &$stat, &$logs) {
            foreach ($endpoints as $key => $ep) {
                $res = $results[$key];
                $row = isset($all[$key]) ? $all[$key] : self::emptyRow($ep['url'], $ep['name'], $ep['source']);
                $row['url'] = $ep['url'];
                $row['name'] = $ep['name'];
                $row['source'] = $ep['source'];
                $row['last_check'] = $now;
                if (!empty($res['skip'])) {
                    // 内网 / 保留地址不探测：既不算可用也不算失败，fail_count 保持不变
                    $logs[] = [$ep, 'skip', $res['msg']];
                } elseif ($res['ok']) {
                    $stat['ok']++;
                    $row['fail_count'] = 0;
                    $row['last_error'] = '';
                    $row['last_ok'] = $now;
                    $logs[] = [$ep, 'ok', $res['msg']];
                } else {
                    $stat['fail']++;
                    $row['fail_count'] = intval($row['fail_count']) + 1;
                    $row['last_error'] = $res['msg'];
                    $msg = lang('admin/collect_health/check_fail_n', [$row['fail_count'], $res['msg']]);
                    if (!$autoDisable && (string)$row['status'] === '1' && $row['fail_count'] >= self::FAIL_LIMIT) {
                        $msg .= ' ' . lang('admin/collect_health/auto_off');
                    } elseif ((string)$row['status'] === '1' && $row['fail_count'] >= self::FAIL_LIMIT) {
                        $row['status'] = '0';
                        $row['disabled_by'] = 'auto';
                        $row['reason'] = lang('admin/collect_health/reason_auto', [$row['fail_count'], $res['msg']]);
                        $row['updated'] = $now;
                        $stat['disabled']++;
                        $msg .= ' → ' . lang('admin/collect_health/log_auto_disabled');
                    }
                    $logs[] = [$ep, 'fail', $msg];
                }
                $all[$key] = $row;
            }
        });

        foreach ($logs as $l) {
            self::log(self::KIND_CHECK, $l[0]['url'], $l[0]['name'], $l[1], $l[2]);
        }
        self::purgeLogs();
        return $stat;
    }

    /**
     * 本轮是否像本机网络故障：全部接口都不可用、至少一个是网络层失败，且外网探测不通
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

    /**
     * 并发探测：请求 ac=list&pg=1，能解析出 JSON list 或 XML 即视为可用
     * 接口地址含资源站中心远端下发的站点，按不可信处理：每一跳都解析并确认为公网 IP 后用 CURLOPT_RESOLVE 钉住，
     * 重定向手动逐跳校验（与 ContentCheck::probeMany 同一套 SSRF 防护）；指向内网 / 保留地址的接口不探测，
     * 结果标记 skip（站长自建内网资源站是合法用法，不能因此累计失败被自动停用）
     * @param array $endpoints [key => ['url','param']]
     * @return array [key => ['ok'=>bool,'msg'=>string,'net'?=>bool,'skip'?=>bool]]
     */
    public static function probeMany(array $endpoints)
    {
        $results = [];
        $queue = [];
        foreach ($endpoints as $key => $ep) {
            $url = $ep['url'];
            $parts = parse_url($url);
            if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || empty($parts['host'])) {
                $results[$key] = ['ok' => false, 'msg' => lang('admin/collect_health/err_url')];
                continue;
            }
            $queue[] = ['key' => $key, 'url' => $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=list&pg=1' . (isset($ep['param']) ? $ep['param'] : ''), 'hops' => 0, 'insecure' => false];
        }

        // 滚动并发：任一请求结束即补位，避免整批等待最慢的站点
        $mh = curl_multi_init();
        $active = [];
        $start = function () use ($mh, &$active, &$queue, &$results) {
            while (count($active) < self::PROBE_CONCURRENCY && !empty($queue)) {
                $job = array_shift($queue);
                $host = strtolower((string)parse_url($job['url'], PHP_URL_HOST));
                $why = '';
                $ip = HttpClient::resolvePublicIp($host, $why);
                if ($ip === '') {
                    $results[$job['key']] = $why === 'unresolved'
                        ? ['ok' => false, 'net' => true, 'msg' => lang('admin/collect_health/err_net', [curl_strerror(CURLE_COULDNT_RESOLVE_HOST)])]
                        : ['ok' => false, 'skip' => true, 'msg' => lang('admin/collect_health/skip_private')];
                    continue;
                }
                $ch = self::probeHandle($job['url'], $ip, empty($job['insecure']));
                curl_multi_add_handle($mh, $ch);
                $active[self::handleId($ch)] = $job + ['ch' => $ch];
            }
        };
        $start();
        do {
            curl_multi_exec($mh, $running);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $job = $active[self::handleId($ch)];
                $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
                $next = in_array($code, [301, 302, 303, 307, 308], true) ? (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL) : '';
                if ($next !== '' && $job['hops'] < self::MAX_REDIRECTS && preg_match('#^https?://#i', $next)) {
                    // 跳转：下一跳重新排队，出队时同样走公网校验 + 钉 IP（证书降级状态随跳延续）
                    $queue[] = ['key' => $job['key'], 'url' => $next, 'hops' => $job['hops'] + 1, 'insecure' => $job['insecure']];
                } elseif (empty($job['insecure']) && self::isCertError($info['result'])) {
                    // 证书无效：采集流程本身不校验证书（Collect 里 mac_curl_get(..., false)），检测若就此判失败
                    // 会把「能采集」的接口误停用。同一地址降级为不校验重试一次，结果里注明证书问题供站长处理。
                    $queue[] = ['key' => $job['key'], 'url' => $job['url'], 'hops' => $job['hops'], 'insecure' => true];
                } else {
                    $err = $info['result'] !== CURLE_OK ? curl_strerror($info['result']) : '';
                    $res = self::judge(curl_multi_getcontent($ch), $code, $err);
                    if (!empty($job['insecure'])) {
                        $res['cert_invalid'] = true;
                        $res['msg'] .= ' ' . lang('admin/collect_health/cert_invalid');
                    }
                    $results[$job['key']] = $res;
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($active[self::handleId($ch)]);
                $start();
            }
            if (!empty($active)) {
                curl_multi_select($mh, 1);
            }
        } while (!empty($active));
        curl_multi_close($mh);
        return $results;
    }

    /** PHP 8 起 curl 句柄是对象，不能 (int) 转换 */
    private static function handleId($ch)
    {
        return is_resource($ch) ? (int)$ch : spl_object_id($ch);
    }

    /** curl 错误码是否属于证书校验失败（见 CERT_ERRNOS） */
    public static function isCertError($errno)
    {
        return in_array((int)$errno, self::CERT_ERRNOS, true);
    }

    /**
     * @param bool $verifySsl 默认校验证书；仅在证书校验失败后的降级重试中为 false
     */
    private static function probeHandle($url, $ip, $verifySsl = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RESOLVE, HttpClient::pinResolve($url, $ip));
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 不让 curl 自动跟随：跳转目标未经公网校验，改在 probeMany 里逐跳校验后重新排队
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl ? true : false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
        return $ch;
    }

    private static function judge($body, $code, $err)
    {
        if ($err !== '') {
            return ['ok' => false, 'net' => true, 'msg' => lang('admin/collect_health/err_net', [$err])];
        }
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'msg' => 'HTTP ' . $code];
        }
        if (trim((string)$body) === '') {
            return ['ok' => false, 'msg' => lang('admin/collect_health/err_empty')];
        }
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['list'])) {
            $total = isset($json['total']) ? $json['total'] : (is_array($json['list']) ? count($json['list']) : 0);
            return ['ok' => true, 'msg' => lang('admin/collect_health/ok_total', [$total])];
        }
        $prev = libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($xml && isset($xml->list)) {
            $total = (string)$xml->list->attributes()->recordcount;
            return ['ok' => true, 'msg' => lang('admin/collect_health/ok_total', [$total])];
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('#<(style|script)\b.*?</\1>#is', '', $body))));
        if ($text === '') {
            $text = trim(preg_replace('/\s+/u', ' ', $body));
        }
        return ['ok' => false, 'msg' => lang('admin/collect_health/err_format', [mb_substr($text, 0, 80)])];
    }

    /**
     * 追加一条日志
     * @param string $kind   check / schedule_run / schedule_skip / toggle
     * @param string $result ok / fail / skip
     */
    public static function log($kind, $url, $name, $result, $msg, $task = '')
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = json_encode([
            'time' => time(),
            'key' => self::key($url),
            'url' => (string)$url,
            'name' => (string)$name,
            'kind' => $kind,
            'result' => $result,
            'msg' => (string)$msg,
            'task' => (string)$task,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($dir . date('Ymd') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 读取某天日志（倒序），支持按接口 key / 类型 / 结果 / 关键字筛选
     * @return array ['list','total']
     */
    public static function readLogs($date, array $filter, $page, $limit)
    {
        $file = self::logDir() . preg_replace('/\D/', '', $date) . '.log';
        $rows = [];
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $r = json_decode($lines[$i], true);
                if (!is_array($r)) {
                    continue;
                }
                if (!empty($filter['key']) && $r['key'] !== $filter['key']) {
                    continue;
                }
                if (!empty($filter['kind']) && $r['kind'] !== $filter['kind']) {
                    continue;
                }
                if (!empty($filter['result']) && $r['result'] !== $filter['result']) {
                    continue;
                }
                if (!empty($filter['wd']) && stripos($r['name'] . ' ' . $r['url'] . ' ' . $r['msg'], $filter['wd']) === false) {
                    continue;
                }
                $rows[] = $r;
            }
        }
        return ['list' => array_slice($rows, ($page - 1) * $limit, $limit), 'total' => count($rows)];
    }

    /**
     * 已有日志的日期（倒序，Y-m-d）
     */
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
