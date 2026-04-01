<?php

namespace addons\aicontent\service\models;

/**
 * Google Gemini integration.
 * API docs: https://ai.google.dev/api/generate-content
 */
class GeminiModel extends BaseModel
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function generate(string $prompt): string
    {
        $url = self::API_BASE . urlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = $this->httpPost($url, $headers, $payload);

        if ($response['status'] !== 200) {
            $error = $this->parseJson($response['body']);
            // Use only the parsed message — never echo the raw body, which may contain
            // reflected URL fragments that expose the API key in error logs.
            $msg   = $error['error']['message'] ?? 'Unknown Gemini API error';
            throw new \RuntimeException("Gemini API error [{$response['status']}]: {$msg}");
        }

        $data = $this->parseJson($response['body']);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function getAvailableModels(): array
    {
        return [
            'gemini-2.0-flash'        => 'Gemini 2.0 Flash (Fast)',
            'gemini-1.5-pro'          => 'Gemini 1.5 Pro (Powerful)',
            'gemini-1.5-flash'        => 'Gemini 1.5 Flash (Economical)',
        ];
    }
}
