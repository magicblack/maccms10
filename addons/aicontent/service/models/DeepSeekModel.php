<?php

namespace addons\aicontent\service\models;

/**
 * DeepSeek integration.
 * API is OpenAI-compatible.
 * API docs: https://platform.deepseek.com/api-docs
 */
class DeepSeekModel extends BaseModel
{
    private const API_URL = 'https://api.deepseek.com/chat/completions';

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
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = $this->httpPost(self::API_URL, $headers, $payload);

        if ($response['status'] !== 200) {
            $error = $this->parseJson($response['body']);
            $msg   = $error['error']['message'] ?? $response['body'];
            throw new \RuntimeException("DeepSeek API error [{$response['status']}]: {$msg}");
        }

        $data = $this->parseJson($response['body']);

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function getAvailableModels(): array
    {
        return [
            'deepseek-chat'     => 'DeepSeek Chat (V3)',
            'deepseek-reasoner' => 'DeepSeek Reasoner (R1)',
        ];
    }
}
