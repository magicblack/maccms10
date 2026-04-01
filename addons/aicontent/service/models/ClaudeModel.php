<?php

namespace addons\aicontent\service\models;

/**
 * Anthropic Claude integration.
 * API docs: https://docs.anthropic.com/en/api/messages
 */
class ClaudeModel extends BaseModel
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2023-06-01';

    public function generate(string $prompt): string
    {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::VERSION,
        ];

        $response = $this->httpPost(self::API_URL, $headers, $payload);

        if ($response['status'] !== 200) {
            $error = $this->parseJson($response['body']);
            $msg   = $error['error']['message'] ?? $response['body'];
            throw new \RuntimeException("Claude API error [{$response['status']}]: {$msg}");
        }

        $data = $this->parseJson($response['body']);

        return $data['content'][0]['text'] ?? '';
    }

    public function getAvailableModels(): array
    {
        return [
            'claude-opus-4-6'    => 'Claude Opus 4.6 (Most powerful)',
            'claude-sonnet-4-6'  => 'Claude Sonnet 4.6 (Balanced)',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Fast & cheap)',
        ];
    }
}
