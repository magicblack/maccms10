<?php
namespace app\common\util;

class HttpClient
{
    public static function curlPostWithTimeout($url, $data, $heads, $timeout, $isPost = true)
    {
        $ch = @curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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
}