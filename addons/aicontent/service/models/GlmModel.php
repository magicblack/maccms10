<?php

namespace addons\aicontent\service\models;

/**
 * Zhipu AI GLM (智谱AI) integration.
 * API is OpenAI-compatible.
 * API docs: https://open.bigmodel.cn/dev/api
 */
class GlmModel extends BaseModel
{
    private const API_URL = 'https://open.bigmodel.cn/api/paas/v4/chat/completions';

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
            throw new \RuntimeException("GLM API error [{$response['status']}]: {$msg}");
        }

        $data = $this->parseJson($response['body']);

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function getAvailableModels(): array
    {
        return [
            'glm-4-plus'     => 'GLM-4 Plus (Most powerful)',
            'glm-4'          => 'GLM-4',
            'glm-4-flash'    => 'GLM-4 Flash (Free tier)',
            'glm-4-air'      => 'GLM-4 Air (Cost-effective)',
        ];
    }
}
