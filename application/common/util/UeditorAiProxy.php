<?php
namespace app\common\util;

/**
 * Server-side UEditor AI: calls upstream using ai_seo only (never exposed to browser).
 */
class UeditorAiProxy
{
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const MAX_PROMPT_CHARS = 32000;

    /**
     * @param array $ai ai_seo from maccms
     * @return array{ok:bool,text:string,error:string,log_detail:string}
     */
    public static function complete(array $ai, $systemPrompt, $userPrompt)
    {
        $systemPrompt = self::clip((string) $systemPrompt);
        $userPrompt = self::clip((string) $userPrompt);
        if ($systemPrompt === '' && $userPrompt === '') {
            return ['ok' => false, 'text' => '', 'error' => 'empty prompt', 'log_detail' => 'empty prompt'];
        }

        $key = isset($ai['api_key']) ? trim((string) $ai['api_key']) : '';
        if ($key === '') {
            return ['ok' => false, 'text' => '', 'error' => 'api key not configured', 'log_detail' => 'no api key'];
        }

        $timeout = max(5, min(120, (int) (isset($ai['timeout']) ? $ai['timeout'] : 30)));
        $model = isset($ai['model']) ? trim((string) $ai['model']) : 'gpt-4o-mini';
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        $provider = isset($ai['provider']) ? strtolower(trim((string) $ai['provider'])) : 'openai';

        if (in_array($provider, ['claude', 'anthropic'], true)) {
            return self::callAnthropic($ai, $key, $model, $systemPrompt, $userPrompt, $timeout);
        }

        return self::callOpenAiCompatible($ai, $key, $model, $systemPrompt, $userPrompt, $timeout);
    }

    private static function clip($s)
    {
        if (function_exists('mb_strlen') && mb_strlen($s) > self::MAX_PROMPT_CHARS) {
            return mb_substr($s, 0, self::MAX_PROMPT_CHARS);
        }
        if (strlen($s) > self::MAX_PROMPT_CHARS) {
            return substr($s, 0, self::MAX_PROMPT_CHARS);
        }

        return $s;
    }

    private static function callOpenAiCompatible(array $ai, $key, $model, $systemPrompt, $userPrompt, $timeout)
    {
        $base = isset($ai['api_base']) ? rtrim(trim((string) $ai['api_base']), '/') : '';
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }
        $url = $base . '/chat/completions';

        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt !== '' ? $userPrompt : $systemPrompt];

        $payload = [
            'model' => $model,
            'stream' => false,
            'temperature' => 0.7,
            'messages' => $messages,
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ];

        $res = self::httpPostJson($url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), $timeout);
        if ($res['curl_error'] !== '') {
            return [
                'ok' => false,
                'text' => '',
                'error' => self::safeClientMessage('upstream transport error'),
                'log_detail' => 'curl: ' . $res['curl_error'],
            ];
        }
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return [
                'ok' => false,
                'text' => '',
                'error' => self::parseUpstreamError($res['body'], $res['status']),
                'log_detail' => 'http ' . $res['status'] . ' ' . self::truncateForLog($res['body']),
            ];
        }

        $json = json_decode((string) $res['body'], true);
        if (!is_array($json)) {
            return ['ok' => false, 'text' => '', 'error' => 'invalid upstream response', 'log_detail' => 'invalid json'];
        }
        $text = '';
        if (isset($json['choices'][0]['message']['content'])) {
            $text = (string) $json['choices'][0]['message']['content'];
        }
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'empty model output', 'log_detail' => 'no choices content'];
        }

        return ['ok' => true, 'text' => $text, 'error' => '', 'log_detail' => 'ok'];
    }

    private static function callAnthropic(array $ai, $key, $model, $systemPrompt, $userPrompt, $timeout)
    {
        $base = isset($ai['api_base']) ? rtrim(trim((string) $ai['api_base']), '/') : '';
        if ($base === '') {
            $url = 'https://api.anthropic.com/v1/messages';
        } elseif (substr($base, -9) === '/messages') {
            $url = $base;
        } else {
            $url = $base . '/messages';
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt !== '' ? $userPrompt : $systemPrompt,
                ],
            ],
        ];
        if ($systemPrompt !== '' && $userPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
        ];

        $res = self::httpPostJson($url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE), $timeout);
        if ($res['curl_error'] !== '') {
            return [
                'ok' => false,
                'text' => '',
                'error' => self::safeClientMessage('upstream transport error'),
                'log_detail' => 'curl: ' . $res['curl_error'],
            ];
        }
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return [
                'ok' => false,
                'text' => '',
                'error' => self::parseAnthropicError($res['body'], $res['status']),
                'log_detail' => 'http ' . $res['status'] . ' ' . self::truncateForLog($res['body']),
            ];
        }

        $json = json_decode((string) $res['body'], true);
        if (!is_array($json)) {
            return ['ok' => false, 'text' => '', 'error' => 'invalid upstream response', 'log_detail' => 'invalid json'];
        }
        $text = '';
        if (!empty($json['content'][0]['text'])) {
            $text = (string) $json['content'][0]['text'];
        }
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'empty model output', 'log_detail' => 'no content text'];
        }

        return ['ok' => true, 'text' => $text, 'error' => '', 'log_detail' => 'ok'];
    }

    private static function httpPostJson($url, array $headers, $body, $timeout)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $response === false ? '' : (string) $response,
            'curl_error' => $err !== '' ? preg_replace('/https?:\/\/\S+/', '[url]', $err) : '',
        ];
    }

    private static function parseUpstreamError($body, $status)
    {
        $json = json_decode((string) $body, true);
        $msg = '';
        if (is_array($json)) {
            if (isset($json['error']['message'])) {
                $msg = (string) $json['error']['message'];
            } elseif (isset($json['message'])) {
                $msg = (string) $json['message'];
            }
        }
        if ($msg === '') {
            return self::safeClientMessage('upstream error HTTP ' . $status);
        }

        return self::safeClientMessage($msg);
    }

    private static function parseAnthropicError($body, $status)
    {
        $json = json_decode((string) $body, true);
        $msg = '';
        if (is_array($json) && isset($json['error']['message'])) {
            $msg = (string) $json['error']['message'];
        }
        if ($msg === '') {
            return self::safeClientMessage('Anthropic error HTTP ' . $status);
        }

        return self::safeClientMessage($msg);
    }

    private static function safeClientMessage($msg)
    {
        $msg = (string) $msg;
        if (preg_match('/sk-[a-zA-Z0-9_-]{10,}/', $msg)) {
            return 'upstream request failed';
        }
        if (preg_match('/https?:\/\/\S+/', $msg)) {
            return 'upstream request failed';
        }

        return function_exists('mb_substr') ? mb_substr($msg, 0, 300) : substr($msg, 0, 300);
    }

    private static function truncateForLog($body)
    {
        $s = preg_replace('/sk-[a-zA-Z0-9_-]+/', '[key]', (string) $body);

        return function_exists('mb_substr') ? mb_substr($s, 0, 500) : substr($s, 0, 500);
    }
}
