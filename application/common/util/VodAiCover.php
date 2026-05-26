<?php
namespace app\common\util;

use think\Cache;
use think\Db;
use think\Log;

/**
 * AI-generated poster / cover for VOD (OpenAI Images API).
 * Defaults target GPT Image models (gpt-image-1, etc.); legacy dall-e-* may still work on some gateways.
 */
class VodAiCover
{
    /** @var int */
    const RATE_LIMIT_PER_MINUTE = 5;
    /** @var int */
    const RATE_LIMIT_PER_HOUR = 50;

    /**
     * Per-admin rate limit for expensive image generation (see AdminAssistantService::consumeRateLimit).
     *
     * @return bool true if request may proceed, false if limit exceeded
     */
    public static function consumeGenerateRateLimit($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return false;
        }
        $minBucket = (int) floor(time() / 60);
        $keyMin = 'admin_vod_aicover_rl_min:' . $adminId . ':' . $minBucket;
        $nMin = (int) Cache::get($keyMin, 0);
        if ($nMin >= self::RATE_LIMIT_PER_MINUTE) {
            return false;
        }
        $hourBucket = (int) floor(time() / 3600);
        $keyHour = 'admin_vod_aicover_rl_hour:' . $adminId . ':' . $hourBucket;
        $nHour = (int) Cache::get($keyHour, 0);
        if ($nHour >= self::RATE_LIMIT_PER_HOUR) {
            return false;
        }
        Cache::set($keyMin, $nMin + 1, 70);
        Cache::set($keyHour, $nHour + 1, 3700);

        return true;
    }

    /**
     * @return array{code:int,msg:string,data?:array}
     */
    public static function generateByVodId($vodId, $extraPrompt = '')
    {
        $vodId = intval($vodId);
        if ($vodId <= 0) {
            return ['code' => 0, 'msg' => lang('param_err')];
        }

        $res = model('Vod')->infoData(['vod_id' => ['eq', $vodId]], '*', 0);
        if ($res['code'] !== 1 || empty($res['info'])) {
            return ['code' => 0, 'msg' => lang('obtain_err')];
        }
        $vod = $res['info'];

        $config = config('maccms');
        $ai = isset($config['ai_cover']) && is_array($config['ai_cover']) ? $config['ai_cover'] : [];
        $enabled = isset($ai['enabled']) ? (string) $ai['enabled'] : '0';
        if ($enabled !== '1') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_disabled')];
        }
        $apiKey = trim((string) (isset($ai['api_key']) ? $ai['api_key'] : ''));
        if ($apiKey === '') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_no_key')];
        }
        $provider = strtolower(trim((string) (isset($ai['provider']) ? $ai['provider'] : 'openai')));
        if ($provider !== 'openai') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_provider')];
        }

        $apiBase = !empty($ai['api_base']) ? rtrim($ai['api_base'], '/') : 'https://api.openai.com/v1';
        $model = !empty($ai['model']) ? trim((string) $ai['model']) : 'gpt-image-1';
        $timeout = max(30, intval(isset($ai['timeout']) ? $ai['timeout'] : 120));
        $size = self::sanitizeSize(isset($ai['size']) ? $ai['size'] : '1024x1536');
        $qRaw = isset($ai['quality']) ? strtolower(trim((string) $ai['quality'])) : 'medium';
        $quality = self::sanitizeQualityForModel($model, $qRaw);

        $prompt = self::buildPrompt(
            $vod,
            isset($ai['prompt_suffix']) ? (string) $ai['prompt_suffix'] : '',
            $extraPrompt
        );
        $url = $apiBase . '/images/generations';
        // Omit response_format: many OpenAI-compatible proxies error with
        // "unknown format: response_format"; official API defaults to URL anyway.
        $post = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
        ];
        if (self::modelUsesQualityParam($model)) {
            $post['quality'] = $quality;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $respBody = self::curlPostJson($url, json_encode($post, JSON_UNESCAPED_UNICODE), $headers, $timeout);
        if ($respBody === false || $respBody === '') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_empty_response')];
        }
        $json = json_decode((string) $respBody, true);
        if (!is_array($json)) {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_bad_json')];
        }
        if (!empty($json['error']['message'])) {
            return ['code' => 0, 'msg' => (string) $json['error']['message']];
        }
        $imageUrl = '';
        if (!empty($json['data'][0]['url'])) {
            $imageUrl = (string) $json['data'][0]['url'];
        } elseif (!empty($json['data'][0]['b64_json'])) {
            $raw = base64_decode((string) $json['data'][0]['b64_json'], true);
            if ($raw === false) {
                return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_decode_fail')];
            }
            $saveRel = self::allocateSavePath($vodId, 'png');
            $full = ROOT_PATH . $saveRel;
            if (!self::ensureDir(dirname($full)) || file_put_contents($full, $raw) === false) {
                return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_save_fail')];
            }

            return self::finalizeAndUpdateVod($vod, $saveRel);
        }
        if ($imageUrl === '') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_no_image_url')];
        }

        if (!self::isSafePublicHttpsImageUrl($imageUrl)) {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_bad_image_url')];
        }

        $bin = self::curlGetBinary($imageUrl, min(120, $timeout));
        if ($bin === null || $bin === '') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_download_fail')];
        }
        $saveRel = self::allocateSavePath($vodId, 'png');
        $full = ROOT_PATH . $saveRel;
        if (!self::ensureDir(dirname($full)) || file_put_contents($full, $bin) === false) {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_save_fail')];
        }

        return self::finalizeAndUpdateVod($vod, $saveRel);
    }

    /**
     * @return array{code:int,msg:string,data?:array}
     */
    public static function revertByVodId($vodId)
    {
        $vodId = intval($vodId);
        if ($vodId <= 0) {
            return ['code' => 0, 'msg' => lang('param_err')];
        }
        $row = Db::name('vod')->where('vod_id', $vodId)->field('vod_pic,vod_pic_original,vod_pic_thumb,vod_en')->find();
        if (empty($row)) {
            return ['code' => 0, 'msg' => lang('obtain_err')];
        }
        $orig = trim((string) $row['vod_pic_original']);
        if ($orig === '') {
            return ['code' => 0, 'msg' => lang('admin/ai_cover/msg_no_backup')];
        }
        $up = [
            'vod_pic' => $orig,
            'vod_pic_original' => '',
        ];
        $res = Db::name('vod')->where('vod_id', $vodId)->update($up);
        if ($res === false) {
            return ['code' => 0, 'msg' => lang('save_err')];
        }
        try {
            \app\common\util\MeilisearchSync::afterVodSave($vodId);
        } catch (\Throwable $e) {
        }
        self::bustVodDetailCache($vodId, isset($row['vod_en']) ? (string) $row['vod_en'] : '');

        return ['code' => 1, 'msg' => lang('save_ok'), 'data' => ['vod_pic' => $orig, 'vod_pic_thumb' => $row['vod_pic_thumb']]];
    }

    private static function finalizeAndUpdateVod(array $vod, $relativePath)
    {
        $uploadCfg = (array) config('maccms.upload');
        $relativePath = str_replace('\\', '/', $relativePath);

        if (!empty($uploadCfg['watermark']) && (string) $uploadCfg['watermark'] === '1') {
            try {
                model('Image')->watermark($relativePath, $uploadCfg, 'vod');
            } catch (\Throwable $e) {
                Log::error('VodAiCover watermark: ' . $e->getMessage());
            }
        }

        $thumbPath = '';
        if (!empty($uploadCfg['thumb']) && (string) $uploadCfg['thumb'] === '1') {
            try {
                $dd = model('Image')->makethumb($relativePath, $uploadCfg, 'vod');
                if (!empty($dd['thumb'][0]['file'])) {
                    $thumbPath = (string) $dd['thumb'][0]['file'];
                }
            } catch (\Throwable $e) {
                Log::error('VodAiCover makethumb: ' . $e->getMessage());
            }
        }

        if (!in_array(strtolower((string) $uploadCfg['mode']), ['local', 'remote'], true)) {
            try {
                $relativePath = model('Upload')->api($relativePath, $uploadCfg);
                if ($thumbPath !== '') {
                    $thumbPath = model('Upload')->api($thumbPath, $uploadCfg);
                }
            } catch (\Throwable $e) {
                Log::error('VodAiCover remote upload: ' . $e->getMessage());
            }
        }

        $vodId = intval($vod['vod_id']);
        $currentPic = trim((string) $vod['vod_pic']);
        $existingBackup = trim((string) (isset($vod['vod_pic_original']) ? $vod['vod_pic_original'] : ''));

        $update = ['vod_pic' => $relativePath];
        if ($thumbPath !== '') {
            $update['vod_pic_thumb'] = $thumbPath;
        }
        if ($existingBackup === '' && $currentPic !== '') {
            $update['vod_pic_original'] = $currentPic;
        }

        $ok = Db::name('vod')->where('vod_id', $vodId)->update($update);
        if ($ok === false) {
            return ['code' => 0, 'msg' => lang('save_err')];
        }

        try {
            \app\common\util\MeilisearchSync::afterVodSave($vodId);
        } catch (\Throwable $e) {
        }

        self::bustVodDetailCache($vodId, isset($vod['vod_en']) ? (string) $vod['vod_en'] : '');

        return [
            'code' => 1,
            'msg' => lang('save_ok'),
            'data' => [
                'vod_pic' => $relativePath,
                'vod_pic_thumb' => $thumbPath !== '' ? $thumbPath : (isset($vod['vod_pic_thumb']) ? $vod['vod_pic_thumb'] : ''),
                'vod_pic_original' => isset($update['vod_pic_original']) ? $update['vod_pic_original'] : $existingBackup,
            ],
        ];
    }

    private static function buildPrompt(array $vod, $suffix, $perVideoExtra = '')
    {
        $parts = [];
        $parts[] = 'Title: ' . self::clip((string) $vod['vod_name'], 200);
        if (!empty($vod['vod_sub'])) {
            $parts[] = 'Subtitle: ' . self::clip((string) $vod['vod_sub'], 120);
        }
        if (!empty($vod['vod_class'])) {
            $parts[] = 'Genre: ' . self::clip((string) $vod['vod_class'], 120);
        }
        if (!empty($vod['vod_area'])) {
            $parts[] = 'Region: ' . self::clip((string) $vod['vod_area'], 40);
        }
        if (!empty($vod['vod_year'])) {
            $parts[] = 'Year: ' . self::clip((string) $vod['vod_year'], 10);
        }
        if (!empty($vod['vod_blurb'])) {
            $parts[] = 'Summary: ' . self::clip(strip_tags((string) $vod['vod_blurb']), 400);
        } elseif (!empty($vod['vod_content'])) {
            $parts[] = 'Summary: ' . self::clip(strip_tags((string) $vod['vod_content']), 400);
        }
        $base = "Create a vertical cinematic poster illustration for the above video. No real-person photos, no text or watermarks on the image, strong composition, dramatic lighting, suitable for a streaming catalog thumbnail.\n\n"
            . implode("\n", $parts);
        $suffix = trim($suffix);
        if ($suffix !== '') {
            $base .= "\n\n" . self::clip($suffix, 500);
        }
        $perVideoExtra = trim(self::sanitizeExtraPrompt($perVideoExtra));
        if ($perVideoExtra !== '') {
            $base .= "\n\n" . self::clip($perVideoExtra, 700);
        }

        return self::clip($base, 3900);
    }

    private static function sanitizeExtraPrompt($s)
    {
        $s = mac_filter_xss((string) $s);

        return self::clip($s, 800);
    }

    private static function clip($s, $max)
    {
        $s = (string) $s;
        $cleaned = @preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
        if (!is_string($cleaned)) {
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        }
        $s = is_string($cleaned) ? $cleaned : $s;
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max, 'UTF-8');
        }

        return substr($s, 0, $max);
    }

    /**
     * GPT Image models use 1024x1536 / 1536x1024 (not DALL·E 1792). Map legacy saved sizes.
     */
    private static function sanitizeSize($size)
    {
        $size = strtolower(trim((string) $size));
        $legacy = [
            '1024x1792' => '1024x1536',
            '1792x1024' => '1536x1024',
        ];
        if (isset($legacy[$size])) {
            $size = $legacy[$size];
        }
        $allowed = ['1024x1024', '1024x1536', '1536x1024', '512x512', '256x256', 'auto'];

        return in_array($size, $allowed, true) ? $size : '1024x1536';
    }

    private static function modelUsesQualityParam($model)
    {
        $m = strtolower((string) $model);
        if (strpos($m, 'dall-e-3') !== false) {
            return true;
        }
        if (strpos($m, 'gpt-image') !== false) {
            return true;
        }

        return false;
    }

    /**
     * dall-e-3: standard | hd. GPT Image: low | medium | high | auto.
     */
    private static function sanitizeQualityForModel($model, $qRaw)
    {
        $m = strtolower((string) $model);
        $qRaw = strtolower(trim((string) $qRaw));
        if (strpos($m, 'dall-e-3') !== false) {
            return in_array($qRaw, ['hd', 'standard'], true) ? $qRaw : 'standard';
        }
        if (strpos($m, 'gpt-image') !== false) {
            $ok = ['low', 'medium', 'high', 'auto'];
            if (in_array($qRaw, $ok, true)) {
                return $qRaw;
            }
            if ($qRaw === 'hd') {
                return 'high';
            }
            if ($qRaw === 'standard') {
                return 'medium';
            }

            return 'medium';
        }

        return $qRaw !== '' ? $qRaw : 'medium';
    }

    /**
     * Prevent SSRF when downloading image URLs returned by the API (must be https + public IP).
     */
    private static function isSafePublicHttpsImageUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '' || strncasecmp($url, 'https://', 8) !== 0) {
            return false;
        }
        $parts = parse_url($url);
        if (empty($parts['host']) || !is_string($parts['host'])) {
            return false;
        }
        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($host !== '' && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::ipIsPublicInternet($host);
        }
        $ips = [];
        if (function_exists('dns_get_record')) {
            $a = @dns_get_record($host, DNS_A);
            if (is_array($a)) {
                foreach ($a as $rec) {
                    if (!empty($rec['ip'])) {
                        $ips[] = $rec['ip'];
                    }
                }
            }
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $rec) {
                    if (!empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }
        if ($ips === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!self::ipIsPublicInternet((string) $ip)) {
                return false;
            }
        }

        return true;
    }

    private static function ipIsPublicInternet($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    private static function allocateSavePath($vodId, $ext)
    {
        $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'png';
        $_upload_path = ROOT_PATH . 'upload/vod/';
        $_save_path = 'upload/vod/';
        $ymd = date('Ymd');
        $n_dir = $ymd;
        for ($i = 1; $i <= 100; $i++) {
            $n_dir = $ymd . '-' . $i;
            $path1 = $_upload_path . $n_dir . '/';
            if (file_exists($path1)) {
                $farr = glob($path1 . '*.*');
                if ($farr && count($farr) > 999) {
                    continue;
                }
                break;
            }
            break;
        }
        $base = $n_dir . '/' . md5(microtime(true) . '_' . $vodId) . '.' . strtolower($ext);

        return $_save_path . $base;
    }

    private static function ensureDir($dir)
    {
        if (is_dir($dir)) {
            return true;
        }

        return @mkdir($dir, 0777, true);
    }

    private static function bustVodDetailCache($vodId, $vodEn)
    {
        $vodId = intval($vodId);
        $vodEn = (string) $vodEn;
        \think\Cache::rm('vod_detail_' . $vodId);
        if ($vodEn !== '') {
            \think\Cache::rm('vod_detail_' . $vodEn);
            \think\Cache::rm('vod_detail_' . $vodId . '_' . $vodEn);
        }
        $flag = isset($GLOBALS['config']['app']['cache_flag']) ? (string) $GLOBALS['config']['app']['cache_flag'] : '';
        if ($flag !== '' && $vodEn !== '') {
            \think\Cache::rm($flag . '_vod_detail_' . $vodId . '_' . $vodEn);
        }
    }

    private static function curlPostJson($url, $body, array $headers, $timeout)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(30, $timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $out = curl_exec($ch);
        curl_close($ch);

        return $out;
    }

    /**
     * @return string|null
     */
    private static function curlGetBinary($url, $timeout)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(30, $timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($ch);
        curl_close($ch);

        return $out === false ? null : (string) $out;
    }
}
