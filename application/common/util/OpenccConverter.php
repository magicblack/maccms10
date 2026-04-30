<?php

namespace app\common\util;

/**
 * OpenCC 转换封装：优先 PHP 扩展 ext-opencc，其次系统 opencc 命令，均不可用时返回原文。
 * 进程内与跨请求（think\Cache）缓存转换结果。
 */
class OpenccConverter
{
    private static $shellChecked = false;
    private static $shellAvailable = false;
    private static $cache = [];
    /** @var array<string, mixed> opencc_open 句柄，false 表示该 config 不可用 */
    private static $extOd = [];

    /** 跨请求缓存 TTL（秒），简繁映射稳定可设较长 */
    private static $persistTtl = 2592000;
    /** shell opencc 可用性缓存 TTL（秒） */
    private static $shellAvailableTtl = 86400;

    /**
     * 简体 -> 繁体（OpenCC s2t）。
     */
    public static function s2t($text)
    {
        return self::convert($text, 's2t');
    }

    /**
     * 繁体 -> 简体（OpenCC t2s）。
     */
    public static function t2s($text)
    {
        return self::convert($text, 't2s');
    }

    /**
     * @param string $config opencc 配置名，例如 s2t / t2s
     */
    public static function convert($text, $config)
    {
        $text = (string)$text;
        $config = trim((string)$config);
        if ($text === '' || $config === '') {
            return $text;
        }
        $key = $config . ':' . md5($text);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $persistKey = 'opencc:' . $config . ':' . md5($text);
        if (class_exists('\think\Cache', false)) {
            try {
                $cached = \think\Cache::get($persistKey);
                if (is_string($cached)) {
                    self::$cache[$key] = $cached;

                    return $cached;
                }
            } catch (\Throwable $e) {
                // 未初始化缓存时忽略
            }
        }

        $out = self::convertOnce($text, $config);
        if ($out === null || $out === '') {
            $out = $text;
        }
        self::$cache[$key] = $out;
        if (class_exists('\think\Cache', false)) {
            try {
                \think\Cache::set($persistKey, $out, self::$persistTtl);
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }

    /**
     * @return string|null
     */
    private static function convertOnce($text, $config)
    {
        $ext = self::convertWithExtension($text, $config);
        if ($ext !== null) {
            return $ext;
        }
        if (!self::isShellAvailable()) {
            return $text;
        }

        return self::execOpencc($text, $config);
    }

    /**
     * ext-opencc（opencc4php）：opencc_open / opencc_convert。
     *
     * @return string|null 扩展不可用或失败时返回 null
     */
    private static function convertWithExtension($text, $config)
    {
        if (!extension_loaded('opencc') || !function_exists('opencc_open') || !function_exists('opencc_convert')) {
            return null;
        }
        $cfgFile = $config . '.json';
        if (!array_key_exists($config, self::$extOd)) {
            $od = @opencc_open($cfgFile);
            self::$extOd[$config] = ($od !== false && $od !== null) ? $od : false;
        }
        $od = self::$extOd[$config];
        if ($od === false || $od === null) {
            return null;
        }
        $converted = @opencc_convert($text, $od);
        if (!is_string($converted)) {
            $converted = @opencc_convert($od, $text);
        }

        return is_string($converted) ? $converted : null;
    }

    /**
     * 扩展或 shell 任一可用即视为可用（供后台状态展示）。
     */
    public static function available()
    {
        if (extension_loaded('opencc') && function_exists('opencc_open') && function_exists('opencc_convert')) {
            return true;
        }

        return self::isShellAvailable();
    }

    private static function isShellAvailable()
    {
        if (self::$shellChecked) {
            return self::$shellAvailable;
        }
        self::$shellChecked = true;
        $persistKey = 'opencc:shell_available';
        if (class_exists('\think\Cache', false)) {
            try {
                $cached = \think\Cache::get($persistKey);
                if ($cached === 1 || $cached === '1' || $cached === true) {
                    self::$shellAvailable = true;
                    return true;
                }
                if ($cached === 0 || $cached === '0' || $cached === false) {
                    self::$shellAvailable = false;
                    return false;
                }
            } catch (\Throwable $e) {
                // 未初始化缓存时忽略
            }
        }
        if (!function_exists('shell_exec')) {
            self::$shellAvailable = false;
            if (class_exists('\think\Cache', false)) {
                try {
                    \think\Cache::set($persistKey, 0, self::$shellAvailableTtl);
                } catch (\Throwable $e) {
                }
            }

            return false;
        }
        try {
            $ret = @shell_exec('opencc -V 2>&1');
            self::$shellAvailable = is_string($ret) && trim($ret) !== '';
        } catch (\Throwable $e) {
            self::$shellAvailable = false;
        }
        if (class_exists('\think\Cache', false)) {
            try {
                \think\Cache::set($persistKey, self::$shellAvailable ? 1 : 0, self::$shellAvailableTtl);
            } catch (\Throwable $e) {
            }
        }

        return self::$shellAvailable;
    }

    /**
     * 通过临时文件调用 opencc，避免命令行转义导致的文本损坏。
     *
     * @return string|null
     */
    private static function execOpencc($text, $config)
    {
        try {
            $tmpIn = tempnam(sys_get_temp_dir(), 'mcc_in_');
            $tmpOut = tempnam(sys_get_temp_dir(), 'mcc_out_');
            if ($tmpIn === false || $tmpOut === false) {
                return null;
            }
            file_put_contents($tmpIn, $text);
            $cmd = 'opencc -c ' . escapeshellarg($config . '.json') . ' -i ' . escapeshellarg($tmpIn) . ' -o ' . escapeshellarg($tmpOut) . ' 2>&1';
            @shell_exec($cmd);
            $out = @file_get_contents($tmpOut);
            @unlink($tmpIn);
            @unlink($tmpOut);
            return is_string($out) ? trim($out) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
