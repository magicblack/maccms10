<?php
namespace app\common\util;

class HttpClient
{
    /**
     * 回归测试注入点：非 null 时 egressOk() 直接返回它（bool），不发真实请求。
     * 断路器的「本机断网」分支只有外网不通时才走得到，测试里没法真的断网。生产代码不得赋值。
     * @var bool|null
     */
    public static $egressOverride = null;

    /**
     * @param bool $followRedirects 默认不跟随：AI 等调用带 API 金钥，跳转会把金钥连同请求带到任意主机
     *                              （含内网 / 云元数据）。仅请求固定公共站点（豆瓣 / TMDB 等）的调用方显式传 true。
     */
    public static function curlPostWithTimeout($url, $data, $heads, $timeout, $isPost = true, $followRedirects = false)
    {
        $ch = @curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects ? 1 : 0);
        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, min(10, intval($timeout))));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(3, intval($timeout)));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }
        if (count($heads) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $heads);
        }
        $response = @curl_exec($ch);
        @curl_close($ch);
        return $response;
    }

    /**
     * SSRF 安全 GET：仅 http/https；每一跳先 resolvePublicIp 校验为公网再用 CURLOPT_RESOLVE 钉住，
     * 跳转不交给 curl（未经校验会绕过公网判定），由本方法逐跳重新校验后再请求，最多 $maxRedirects 次。
     *
     * @param bool $verifySsl 第三方采集站常见自签 / 过期证书，调用方可显式关闭（连接仍钉在已校验公网 IP）
     * @return string|false 响应体；地址不合规 / 非公网 / 请求失败返回 false
     */
    public static function safeGet($url, $timeout = 20, $verifySsl = true, $maxRedirects = 3)
    {
        $url = (string)$url;
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $parts = parse_url($url);
            if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true) || empty($parts['host'])) {
                return false;
            }
            $ip = self::resolvePublicIp($parts['host']);
            if ($ip === '') {
                return false;
            }
            $ch = @curl_init();
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RESOLVE, self::pinResolve($url, $ip));
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, min(15, intval($timeout))));
            curl_setopt($ch, CURLOPT_TIMEOUT, max(3, intval($timeout)));
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl ? true : false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
            $body = @curl_exec($ch);
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $next = in_array($code, array(301, 302, 303, 307, 308), true) ? (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL) : '';
            @curl_close($ch);
            if ($next === '') {
                return $body;
            }
            $url = $next;
        }
        return false;
    }

    /**
     * 安全 GET：**不跟随重定向**（CURLOPT_FOLLOWLOCATION=0）。
     * 供 URL 主题探测使用——调用方已对首跳主机做过 SSRF 校验，
     * 关闭重定向可避免公网 URL 被 302 到内网/云元数据地址而绕过校验。
     */
    public static function curlGetNoRedirect($url, $timeout, $heads = array(), $resolve = array())
    {
        $ch = @curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        // 把 host 钉到调用方已校验为公网的 IP，堵住「校验时解析公网、连接时解析内网」
        // 的 DNS-rebinding TOCTOU 窗口（SSRF）。格式：host:port:ip
        if (is_array($resolve) && count($resolve) > 0) {
            curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(2, min(10, intval($timeout))));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(3, intval($timeout)));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (is_array($heads) && count($heads) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $heads);
        }
        $response = @curl_exec($ch);
        @curl_close($ch);
        return $response;
    }

    /**
     * 把主机名解析为「确认为公网」的 IPv4（SSRF 防护，fail-closed）。
     * 返回具体 IP 供调用方用 CURLOPT_RESOLVE 钉住连接（见 pinResolve），堵 DNS rebinding。
     *
     * @param string $host
     * @param string $reason 失败原因：unresolved（解析不到）/ blocked（内网、保留、回环、链路本地、CGNAT、仅 IPv6）
     * @return string 公网 IP；失败回 ''
     */
    public static function resolvePublicIp($host, &$reason = '')
    {
        static $cache = array();
        $reason = '';
        $host = strtolower(trim((string)$host));
        if (isset($cache[$host])) {
            $reason = $cache[$host][1];
            return $cache[$host][0];
        }
        $ip = '';
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || substr($host, -9) === '.internal'
            || strpos($host, '[') !== false || strpos($host, ':') !== false) {
            // 空 / 本机名 / IPv6 字面量：一律不连
            $reason = 'blocked';
        } else {
            // gethostbyname 也会把 2130706433、0x7f.1 这类数字写法规整成点分 IPv4，下面统一按解析结果判
            $addr = @gethostbyname($host);
            if (!filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // 只有 AAAA 记录的主机无法用 IPv4 校验 + 钉住，按 blocked（不可检测）处理，不当作失效
                $reason = (function_exists('dns_get_record') && @dns_get_record($host, DNS_AAAA)) ? 'blocked' : 'unresolved';
            } elseif (!self::isPublicIpv4($addr)) {
                $reason = 'blocked';
            } else {
                $ip = $addr;
            }
        }
        $cache[$host] = array($ip, $reason);
        return $ip;
    }

    /**
     * IPv4 是否公网：排除私有段、保留段（含回环 127/8、链路本地 169.254/16 云元数据、0/8、240/4）、
     * CGNAT 100.64/10（部分云厂商内网用）。
     */
    public static function isPublicIpv4($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $long = ip2long($ip);
        foreach (array(array('100.64.0.0', 10), array('169.254.0.0', 16), array('127.0.0.0', 8), array('0.0.0.0', 8)) as $net) {
            $mask = -1 << (32 - $net[1]);
            if (($long & $mask) === (ip2long($net[0]) & $mask)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 本机外网是否可达（批量检测的断路器用，fail-safe）。
     * 批量探测「全部连不上」时，用它区分「对方全挂了」与「本机断网 / DNS 故障」——
     * 后者若照常计失败，连续两轮就会把全站内容 / 采集接口自动停用。
     * 并发探测几个常见站点（国内外各有，任一返回 HTTP 响应即算可达），结果缓存 60 秒。
     *
     * @return bool
     */
    public static function egressOk()
    {
        if (self::$egressOverride !== null) {
            return (bool)self::$egressOverride;
        }
        static $cache = null;
        if ($cache !== null && time() - $cache[1] < 60) {
            return $cache[0];
        }
        $urls = array(
            'https://www.baidu.com/',
            'https://www.qq.com/',
            'https://www.cloudflare.com/cdn-cgi/trace',
            'https://www.google.com/generate_204',
        );
        $mh = curl_multi_init();
        $handles = array();
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }
        // 任一站点有响应即可提前结束，不必等被墙 / 超时的那几个
        $ok = false;
        do {
            curl_multi_exec($mh, $running);
            while ($info = curl_multi_info_read($mh)) {
                if (intval(curl_getinfo($info['handle'], CURLINFO_HTTP_CODE)) > 0) {
                    $ok = true;
                }
            }
            if (!$ok && $running > 0) {
                curl_multi_select($mh, 1);
            }
        } while (!$ok && $running > 0);
        foreach ($handles as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        $cache = array($ok, time());
        return $ok;
    }

    /**
     * 由 URL + 已校验 IP 构造 CURLOPT_RESOLVE 条目（host:port:ip）
     * @return array
     */
    public static function pinResolve($url, $ip)
    {
        $parts = parse_url((string)$url);
        if (empty($parts['host']) || $ip === '') {
            return array();
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'http' ? 80 : 443);
        return array($parts['host'] . ':' . $port . ':' . $ip);
    }
}