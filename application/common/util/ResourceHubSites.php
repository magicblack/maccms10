<?php
namespace app\common\util;

use think\Cache;

/**
 * 资源站中心站点来源：云端目录 + 用户自定义资源站
 * 后台资源站中心与定时可用性检测（CollectHealth）共用
 */
class ResourceHubSites
{
    // ─── 云端资源站目录配置 ─────────────────────────────
    /** 云端接口地址（返回加密 JSON） */
    const CLOUD_API_URL = 'https://api.maccms.ai/sites.json';
    /** 加密密钥（AES-256-CBC），与云端脚本对齐 */
    const CLOUD_ENCRYPT_KEY = 'maccms_rh_2024_s3cr3t_k3y!@#$%^&';
    /** 本地缓存时间（秒），预设 3 小时 */
    const CLOUD_CACHE_TTL = 10800;

    /**
     * 从云端拉取资源站目录（加密传输 + 本地缓存）
     * 增强：
     *   1. 缓存未过期时直接返回，不请求远端
     *   2. 缓存过期后请求远端，通过 hash 比对判断数据是否有更新，无更新则延长缓存不覆盖
     *   3. 严格验证远端数据格式，格式错误时保留旧缓存数据
     * @return array 资源站列表，失败返回空数组（若有旧缓存则返回旧缓存）
     */
    public static function cloud()
    {
        $cacheKey     = 'resource_hub_cloud_sites';
        $cacheHashKey = 'resource_hub_cloud_sites_hash';

        $cached = Cache::get($cacheKey);

        // 缓存有效时直接返回
        if (!empty($cached) && is_array($cached)) {
            return $cached;
        }

        // 缓存过期，尝试从远端拉取
        try {
            $raw = @file_get_contents(self::CLOUD_API_URL);
            if (empty($raw)) {
                // 远端无响应，返回旧缓存（若有）
                return self::fallbackCloudCache($cacheKey);
            }

            $payload = json_decode($raw, true);
            if (!$payload || empty($payload['data']) || empty($payload['iv'])) {
                // 远端格式错误（外层 JSON 结构不符），保留旧缓存
                return self::fallbackCloudCache($cacheKey);
            }

            // AES-256-CBC 解密
            $decrypted = openssl_decrypt(
                base64_decode($payload['data']),
                'AES-256-CBC',
                self::CLOUD_ENCRYPT_KEY,
                OPENSSL_RAW_DATA,
                base64_decode($payload['iv'])
            );

            if ($decrypted === false) {
                // 解密失败（密钥不匹配或数据损毁），保留旧缓存
                return self::fallbackCloudCache($cacheKey);
            }

            $sites = json_decode($decrypted, true);

            // ── 格式严格验证 ──
            if (!self::validateCloudSitesFormat($sites)) {
                // 远端数据格式错误，不更新，保留旧缓存
                return self::fallbackCloudCache($cacheKey);
            }

            // ── Hash 比对：远端没更新就不覆盖 ──
            $newHash = md5($decrypted);
            $oldHash = Cache::get($cacheHashKey);
            if ($oldHash === $newHash && !empty($cached)) {
                // 数据未变化，仅延长缓存时间
                Cache::set($cacheKey, $cached, self::CLOUD_CACHE_TTL);
                return $cached;
            }

            // 数据有更新或首次拉取，写入缓存 + 备份缓存
            Cache::set($cacheKey, $sites, self::CLOUD_CACHE_TTL);
            Cache::set($cacheKey . '_backup', $sites, self::CLOUD_CACHE_TTL * 10);
            Cache::set($cacheHashKey, $newHash, self::CLOUD_CACHE_TTL * 3);
            return $sites;

        } catch (\Exception $e) {
            return self::fallbackCloudCache($cacheKey);
        }
    }

    /**
     * 回退：尝试从持久化缓存文件读取旧数据
     * 若完全无数据则返回空数组
     */
    private static function fallbackCloudCache($cacheKey)
    {
        // 尝试读取过期但仍存在的缓存（部分缓存驱动支持）
        $old = Cache::get($cacheKey . '_backup');
        if (!empty($old) && is_array($old)) {
            // 延长主缓存，避免频繁请求失败的远端
            Cache::set($cacheKey, $old, self::CLOUD_CACHE_TTL);
            return $old;
        }
        return [];
    }

    /**
     * 严格验证云端资源站数据格式
     * 必须是数组，每个元素都需包含必要字段
     * @param mixed $sites
     * @return bool
     */
    private static function validateCloudSitesFormat($sites)
    {
        if (!is_array($sites) || empty($sites)) {
            return false;
        }

        // 必要字段（每个资源站至少需要这些字段）
        $requiredFields = ['name', 'url'];

        foreach ($sites as $site) {
            if (!is_array($site)) {
                return false;
            }
            foreach ($requiredFields as $field) {
                if (!isset($site[$field]) || $site[$field] === '') {
                    return false;
                }
            }
            // url 必须是合法的 http/https 地址
            if (!preg_match('#^https?://#i', $site['url'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 载入用户自定义资源站（独立文件，与云端隔离）
     * @return array
     */
    public static function custom()
    {
        $file = APP_PATH . 'extra/resource_sites_custom.php';
        if (is_file($file)) {
            $data = include $file;
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * 储存用户自定义资源站
     * @param array $sites
     * @return bool
     */
    public static function saveCustom(array $sites)
    {
        return mac_arr2file(APP_PATH . 'extra/resource_sites_custom.php', $sites);
    }
}
