<?php

namespace app\common\util;

use think\Cache;
use think\Db;

/**
 * 搜索统计、联想排序、热门词等（不依赖外部检索引擎）。
 */
class SearchService
{
    /**
     * 记录一次带关键词的搜索（去抖 + 可选关闭）。
     */
    public static function logFromParam($mid, $param)
    {
        if (!self::isQueryLogEnabled()) {
            return;
        }
        $wd = isset($param['wd']) ? trim((string)$param['wd']) : '';
        if ($wd === '') {
            return;
        }
        $wd = mac_filter_xss($wd);
        if (mb_strlen($wd, 'UTF-8') > 128) {
            $wd = mb_substr($wd, 0, 128, 'UTF-8');
        }
        if ($wd === '') {
            return;
        }
        $mid = max(1, min(255, (int)$mid));
        $userId = intval($GLOBALS['user']['user_id'] ?? 0);
        $dedupeSec = max(30, intval(self::cfg('search_query_log_dedupe_sec', 120)));
        $dkey = 'searchlog:' . $userId . ':' . $mid . ':' . md5($wd);
        if (Cache::get($dkey)) {
            return;
        }
        Cache::set($dkey, 1, $dedupeSec);
        try {
            Db::name('search_query_log')->insert([
                'user_id' => $userId,
                'mid' => $mid,
                'keyword' => $wd,
                'log_time' => time(),
            ]);
        } catch (\Throwable $e) {
            // 表未升级或写入失败时不影响搜索页
        }
    }

    public static function isQueryLogEnabled()
    {
        return intval(self::cfg('search_query_log', 1)) === 1;
    }

    /**
     * 热门搜索词（按日志聚合，带缓存）。
     */
    public static function hotWords($limit = 15, $days = 30)
    {
        $limit = max(1, min(50, (int)$limit));
        $days = max(1, min(365, (int)$days));
        $cacheKey = 'search:hot:' . $limit . ':' . $days;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $list = [];
        if (self::isQueryLogEnabled()) {
            try {
                $since = time() - $days * 86400;
                $rows = Db::name('search_query_log')
                    ->field('keyword, COUNT(*) AS cnt')
                    ->where('log_time', '>=', $since)
                    ->group('keyword')
                    ->order('cnt', 'desc')
                    ->limit($limit)
                    ->select();
                if ($rows) {
                    foreach ($rows as $row) {
                        $kw = isset($row['keyword']) ? trim((string)$row['keyword']) : '';
                        if ($kw === '') {
                            continue;
                        }
                        $list[] = ['word' => $kw, 'count' => intval($row['cnt'])];
                    }
                }
            } catch (\Throwable $e) {
                $list = [];
            }
        }
        $ttl = max(60, intval(self::cfg('search_hot_cache_sec', 600)));
        Cache::set($cacheKey, $list, $ttl);
        return $list;
    }

    /**
     * 登录用户最近不重复关键词。
     */
    public static function userHistory($userId, $limit = 15)
    {
        $userId = (int)$userId;
        $limit = max(1, min(50, (int)$limit));
        if ($userId <= 0 || !self::isQueryLogEnabled()) {
            return [];
        }
        try {
            $rows = Db::name('search_query_log')
                ->field('keyword, MAX(log_time) AS last_time')
                ->where('user_id', $userId)
                ->group('keyword')
                ->order('last_time', 'desc')
                ->limit($limit)
                ->select();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        if ($rows) {
            foreach ($rows as $row) {
                $kw = isset($row['keyword']) ? trim((string)$row['keyword']) : '';
                if ($kw === '') {
                    continue;
                }
                $out[] = ['word' => $kw, 'time' => intval($row['last_time'])];
            }
        }
        return $out;
    }

    /**
     * 清空用户搜索历史。
     */
    public static function clearUserHistory($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0 || !self::isQueryLogEnabled()) {
            return false;
        }
        try {
            Db::name('search_query_log')->where('user_id', $userId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 联想接口限流（IP + visitor 维度）。
     */
    public static function consumeSuggestRate($ip, $limitPerWindow, $windowSec, $visitorId = '')
    {
        $limitPerWindow = (int)$limitPerWindow;
        $windowSec = max(1, (int)$windowSec);
        if ($limitPerWindow <= 0) {
            return true;
        }
        $ip = trim((string)$ip);
        $visitorId = trim((string)$visitorId);
        if ($visitorId === '') {
            $visitorId = 'guest';
        }
        $bucket = intval(time() / $windowSec);
        $key = 'search:suggest:rate:' . md5($ip . '|' . $visitorId . '|' . $bucket);
        $count = intval(Cache::get($key, 0));
        if ($count >= $limitPerWindow) {
            return false;
        }
        Cache::set($key, $count + 1, $windowSec + 1);
        return true;
    }

    /**
     * 联想列表排序：popular = 月点击 + 更新时间；id = 仅按 id。
     */
    public static function suggestOrder($pre, $mode)
    {
        $mode = strtolower((string)$mode) === 'id' ? 'id' : 'popular';
        if ($mode === 'id') {
            return $pre . '_id desc';
        }
        return $pre . '_hits_month desc,' . $pre . '_time desc,' . $pre . '_id desc';
    }

    private static function cfg($key, $default = null)
    {
        $app = config('maccms.app');
        if (!is_array($app)) {
            return $default;
        }
        return array_key_exists($key, $app) ? $app[$key] : $default;
    }
}
