<?php

namespace app\common\util;

/**
 * 最小 Meilisearch REST 客户端（无 Composer 依赖）。
 */
class MeilisearchHttp
{
    public static function request($baseUrl, $method, $path, $apiKey, $body = null, $timeout = 8, $sslVerify = true)
    {
        $baseUrl = rtrim((string)$baseUrl, '/');
        $url = $baseUrl . $path;
        $sslVerify = (bool)$sslVerify;
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, max(1, (int)$timeout));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify ? 1 : 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                return ['ok' => false, 'status' => 0, 'error' => $err ?: 'curl failed', 'data' => null];
            }
            $data = json_decode($raw, true);
            return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'error' => $err, 'data' => $data];
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => max(1, (int)$timeout),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $sslVerify,
                'verify_peer_name' => $sslVerify,
                'allow_self_signed' => !$sslVerify,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'error' => '', 'data' => $data];
    }

    /**
     * 并发执行多条同 host 的请求（顺序与 $jobs 一致），用于 Meilisearch 多查询搜索等场景。
     *
     * @param array<int,array{method:string,path:string,body?:mixed}> $jobs
     * @return array<int,array{ok:bool,status:int,error:string,data:mixed}>
     */
    public static function requestParallel($baseUrl, array $jobs, $apiKey, $timeout, $sslVerify = true)
    {
        if ($jobs === []) {
            return [];
        }
        $timeout = max(1, (int)$timeout);
        $sslVerify = (bool)$sslVerify;
        if (count($jobs) === 1) {
            $j = $jobs[0];
            $m = (string)($j['method'] ?? 'GET');
            $p = (string)($j['path'] ?? '/');

            return [self::request($baseUrl, $m, $p, $apiKey, $j['body'] ?? null, $timeout, $sslVerify)];
        }
        if (!function_exists('curl_multi_init') || !function_exists('curl_init')) {
            $out = [];
            foreach ($jobs as $j) {
                $m = (string)($j['method'] ?? 'GET');
                $p = (string)($j['path'] ?? '/');
                $out[] = self::request($baseUrl, $m, $p, $apiKey, $j['body'] ?? null, $timeout, $sslVerify);
            }

            return $out;
        }

        $baseUrl = rtrim((string)$baseUrl, '/');
        $mh = curl_multi_init();
        if ($mh === false) {
            $out = [];
            foreach ($jobs as $j) {
                $m = (string)($j['method'] ?? 'GET');
                $p = (string)($j['path'] ?? '/');
                $out[] = self::request($baseUrl, $m, $p, $apiKey, $j['body'] ?? null, $timeout, $sslVerify);
            }

            return $out;
        }

        $results = [];
        $handles = [];
        foreach ($jobs as $i => $job) {
            $path = (string)($job['path'] ?? '/');
            $url = $baseUrl . $path;
            $method = (string)($job['method'] ?? 'GET');
            $body = $job['body'] ?? null;
            $headers = ['Content-Type: application/json'];
            if ($apiKey !== '') {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            }
            $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ch = curl_init($url);
            if ($ch === false) {
                $results[$i] = self::request($baseUrl, $method, $path, $apiKey, $body, $timeout, $sslVerify);
                continue;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify ? 1 : 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        if ($handles !== []) {
            $running = null;
            do {
                $stat = curl_multi_exec($mh, $running);
                if ($running > 0) {
                    curl_multi_select($mh, 1.0);
                }
            } while ($running > 0 && $stat === CURLM_OK);

            foreach ($handles as $i => $ch) {
                $raw = curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = (string)curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                if ($raw === false || $raw === '') {
                    $results[$i] = ['ok' => false, 'status' => $code, 'error' => $err ?: 'curl failed', 'data' => null];
                    continue;
                }
                $data = json_decode($raw, true);
                $results[$i] = ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'error' => $err, 'data' => $data];
            }
        }
        curl_multi_close($mh);
        ksort($results, SORT_NUMERIC);

        return array_values($results);
    }
}
