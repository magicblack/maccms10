<?php

namespace addons\aicontent\service\models;

/**
 * OpenAI ChatGPT integration.
 * API docs: https://platform.openai.com/docs/api-reference/chat
 */
class OpenAiModel extends BaseModel
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

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
            throw new \RuntimeException("OpenAI API error [{$response['status']}]: {$msg}");
        }

        $data = $this->parseJson($response['body']);

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function getAvailableModels(): array
    {
        return [
            'gpt-4o'              => 'GPT-4o (Latest)',
            'gpt-4o-mini'         => 'GPT-4o Mini (Fast)',
            'gpt-4-turbo'         => 'GPT-4 Turbo',
            'gpt-3.5-turbo'       => 'GPT-3.5 Turbo (Economical)',
        ];
    }
}
