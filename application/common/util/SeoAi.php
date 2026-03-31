<?php
namespace app\common\util;

class SeoAi
{
    public static function generateByMidObj($mid, $objId)
    {
        $mid = intval($mid);
        $objId = intval($objId);
        if ($mid === 1) {
            $res = model('Vod')->infoData(['vod_id' => ['eq', $objId]], '*', 0);
            if ($res['code'] !== 1 || empty($res['info'])) {
                return ['code' => 0, 'msg' => 'vod not found'];
            }
            return self::generateForVod($res['info']);
        }
        if ($mid === 2) {
            $res = model('Art')->infoData(['art_id' => ['eq', $objId]], '*', 0);
            if ($res['code'] !== 1 || empty($res['info'])) {
                return ['code' => 0, 'msg' => 'art not found'];
            }
            return self::generateForArt($res['info']);
        }
        return ['code' => 0, 'msg' => 'unsupported mid'];
    }

    public static function generateForVod($vod)
    {
        $payload = [
            'mid' => 1,
            'obj_id' => intval($vod['vod_id']),
            'name' => (string)$vod['vod_name'],
            'subtitle' => (string)$vod['vod_sub'],
            'blurb' => (string)$vod['vod_blurb'],
            'content' => strip_tags((string)$vod['vod_content']),
            'class' => (string)$vod['vod_class'],
            'tag' => (string)$vod['vod_tag'],
            'year' => (string)$vod['vod_year'],
            'area' => (string)$vod['vod_area'],
            'lang' => (string)$vod['vod_lang'],
        ];
        return self::generateAndSave($payload);
    }

    public static function generateForArt($art)
    {
        $payload = [
            'mid' => 2,
            'obj_id' => intval($art['art_id']),
            'name' => (string)$art['art_name'],
            'subtitle' => (string)$art['art_sub'],
            'blurb' => (string)$art['art_blurb'],
            'content' => strip_tags(str_replace('$$$', '', (string)$art['art_content'])),
            'class' => (string)$art['art_class'],
            'tag' => (string)$art['art_tag'],
            'year' => date('Y', intval($art['art_time'])),
            'area' => '',
            'lang' => '',
        ];
        return self::generateAndSave($payload);
    }

    private static function generateAndSave($payload)
    {
        // Ensure SEO output follows current system language setting.
        $payload['target_lang'] = self::resolveTargetLanguage($payload);
        $sourceHash = sha1(json_encode($payload));
        $result = self::runGenerator($payload);
        $saveData = [
            'title' => $result['title'],
            'keywords' => $result['keywords'],
            'description' => $result['description'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'source_hash' => $sourceHash,
            'error' => $result['error'],
            'status' => $result['status'],
        ];
        model('SeoAiResult')->saveByObject($payload['mid'], $payload['obj_id'], $saveData);
        return ['code' => $result['status'] ? 1 : 0, 'msg' => $result['error'], 'data' => $saveData];
    }

    private static function runGenerator($payload)
    {
        $config = config('maccms');
        $ai = isset($config['ai_seo']) ? $config['ai_seo'] : [];
        $enabled = isset($ai['enabled']) ? intval($ai['enabled']) : 0;
        $provider = !empty($ai['provider']) ? strtolower($ai['provider']) : 'fallback';
        $model = !empty($ai['model']) ? $ai['model'] : 'gpt-4o-mini';

        if ($enabled !== 1 || empty($ai['api_key']) || $provider !== 'openai') {
            return self::fallbackResult($payload, $provider, $model, '');
        }

        $apiBase = !empty($ai['api_base']) ? rtrim($ai['api_base'], '/') : 'https://api.openai.com/v1';
        $url = $apiBase . '/chat/completions';
        $timeout = !empty($ai['timeout']) ? max(5, intval($ai['timeout'])) : 20;

        $prompt = self::buildPrompt($payload);
        $post = [
            'model' => $model,
            'temperature' => 0.4,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO assistant. Return strict JSON with keys: title,keywords,description.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($ai['api_key']),
        ];
        $resp = self::curlPost($url, json_encode($post, JSON_UNESCAPED_UNICODE), $headers, $timeout);
        if ($resp['code'] !== 1) {
            return self::fallbackResult($payload, $provider, $model, $resp['msg']);
        }

        $json = json_decode($resp['data'], true);
        $content = (string)$json['choices'][0]['message']['content'];
        $parsed = json_decode($content, true);
        if (empty($parsed) || empty($parsed['title'])) {
            return self::fallbackResult($payload, $provider, $model, 'invalid ai response');
        }

        return [
            'status' => 1,
            'provider' => $provider,
            'model' => $model,
            'title' => self::normalizeTitle($parsed['title']),
            'keywords' => self::normalizeKeywords($parsed['keywords']),
            'description' => self::normalizeDescription($parsed['description']),
            'error' => '',
        ];
    }

    private static function buildPrompt($payload)
    {
        $type = $payload['mid'] == 1 ? 'video detail page' : 'article detail page';
        $targetLang = !empty($payload['target_lang']) ? $payload['target_lang'] : 'English';
        return "Generate SEO metadata for a {$type}.\n" .
            "Language: {$targetLang}.\n" .
            "Name: {$payload['name']}\n" .
            "Subtitle: {$payload['subtitle']}\n" .
            "Category: {$payload['class']}\n" .
            "Tags: {$payload['tag']}\n" .
            "Year: {$payload['year']}\n" .
            "Area: {$payload['area']}\n" .
            "Lang: {$payload['lang']}\n" .
            "Blurb: " . self::cut($payload['blurb'], 220) . "\n" .
            "Content excerpt: " . self::cut($payload['content'], 350) . "\n" .
            "Rules:\n" .
            "1) title 50-65 chars.\n" .
            "2) description 120-160 chars.\n" .
            "3) keywords 6-12 items, comma separated.\n" .
            "4) no fake facts.\n" .
            "Return JSON only.";
    }

    private static function resolveTargetLanguage($payload)
    {
        $sysLang = strtolower((string)config('maccms.app.lang'));
        if ($sysLang === '') {
            $sysLang = strtolower((string)config('default_lang'));
        }
        if ($sysLang === '' && !empty($payload['lang'])) {
            $sysLang = strtolower((string)$payload['lang']);
        }

        // Keep prompt language explicit for stable multilingual output.
        $langMap = [
            'zh-cn' => 'Chinese (Simplified)',
            'zh-hans' => 'Chinese (Simplified)',
            'zh-tw' => 'Chinese (Traditional)',
            'zh-hk' => 'Chinese (Traditional)',
            'zh-hant' => 'Chinese (Traditional)',
            'en-us' => 'English',
            'en-gb' => 'English',
            'en' => 'English',
            'ja-jp' => 'Japanese',
            'ja' => 'Japanese',
            'ko-kr' => 'Korean',
            'ko' => 'Korean',
            'fr-fr' => 'French',
            'fr' => 'French',
            'de-de' => 'German',
            'de' => 'German',
            'es-es' => 'Spanish',
            'es' => 'Spanish',
            'pt-pt' => 'Portuguese',
            'pt-br' => 'Portuguese',
            'pt' => 'Portuguese',
        ];
        if (isset($langMap[$sysLang])) {
            return $langMap[$sysLang];
        }

        if (strpos($sysLang, 'zh') === 0) {
            return 'Chinese (Simplified)';
        }
        if (strpos($sysLang, 'en') === 0) {
            return 'English';
        }
        return 'English';
    }

    private static function fallbackResult($payload, $provider, $model, $error)
    {
        $siteName = (string)config('maccms.site.site_name');
        $title = self::normalizeTitle($payload['name'] . ($siteName ? ' - ' . $siteName : ''));
        $keywords = self::normalizeKeywords(
            implode(',', array_filter([
                $payload['name'], $payload['subtitle'], $payload['class'], $payload['tag'], $payload['year'], $payload['area'], $payload['lang']
            ]))
        );
        $description = self::normalizeDescription($payload['blurb']);
        if (empty($description)) {
            $description = self::normalizeDescription($payload['content']);
        }

        return [
            'status' => 1,
            'provider' => $provider ?: 'fallback',
            'model' => $model ?: 'fallback',
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'error' => $error,
        ];
    }

    private static function normalizeTitle($text)
    {
        $text = trim(strip_tags((string)$text));
        return self::cut($text, 255);
    }

    private static function normalizeKeywords($text)
    {
        $text = trim(strip_tags((string)$text));
        $text = str_replace(['|', '，', '、', ';'], ',', $text);
        $arr = array_filter(array_map('trim', explode(',', $text)));
        $arr = array_unique($arr);
        $arr = array_slice($arr, 0, 12);
        return self::cut(implode(',', $arr), 500);
    }

    private static function normalizeDescription($text)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
        return self::cut($text, 500);
    }

    private static function cut($text, $len)
    {
        $text = (string)$text;
        if (mb_strlen($text, 'UTF-8') <= $len) {
            return $text;
        }
        return mb_substr($text, 0, $len, 'UTF-8');
    }

    private static function curlPost($url, $data, $headers, $timeout)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($errno) {
            return ['code' => 0, 'msg' => $err, 'data' => ''];
        }
        if ($status < 200 || $status >= 300) {
            return ['code' => 0, 'msg' => 'http ' . $status, 'data' => (string)$body];
        }
        return ['code' => 1, 'msg' => '', 'data' => (string)$body];
    }
}
