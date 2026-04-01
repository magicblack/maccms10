<?php

namespace addons\aicontent\service;

use addons\aicontent\service\models\BaseModel;
use addons\aicontent\service\models\ClaudeModel;
use addons\aicontent\service\models\OpenAiModel;
use addons\aicontent\service\models\GeminiModel;
use addons\aicontent\service\models\DeepSeekModel;
use addons\aicontent\service\models\QwenModel;
use addons\aicontent\service\models\GlmModel;

/**
 * Factory that instantiates the correct AI model class based on provider name.
 */
class ModelFactory
{
    /**
     * Provider slug → class map.
     */
    private static array $providerMap = [
        'claude'   => ClaudeModel::class,
        'openai'   => OpenAiModel::class,
        'gemini'   => GeminiModel::class,
        'deepseek' => DeepSeekModel::class,
        'qwen'     => QwenModel::class,
        'glm'      => GlmModel::class,
    ];

    /**
     * The API key config keys per provider.
     */
    private static array $keyMap = [
        'claude'   => 'claude_key',
        'openai'   => 'openai_key',
        'gemini'   => 'gemini_key',
        'deepseek' => 'deepseek_key',
        'qwen'     => 'qwen_key',
        'glm'      => 'glm_key',
    ];

    /**
     * Create a model instance using plugin config.
     *
     * @param  string|null $provider  Override provider (falls back to config default)
     * @param  string|null $model     Override model name (falls back to config default)
     * @return BaseModel
     * @throws \InvalidArgumentException for unknown providers
     * @throws \RuntimeException         if API key is not configured
     */
    public static function create(?string $provider = null, ?string $model = null): BaseModel
    {
        $config   = get_addon_config('aicontent');
        $provider = $provider ?: ($config['default_provider'] ?? 'claude');
        $model    = $model    ?: ($config['default_model']    ?? 'claude-sonnet-4-6');

        if (!isset(self::$providerMap[$provider])) {
            throw new \InvalidArgumentException("Unknown AI provider: {$provider}");
        }

        $keyName = self::$keyMap[$provider];
        $apiKey  = $config[$keyName] ?? '';

        if (empty($apiKey)) {
            throw new \RuntimeException("API key for provider '{$provider}' is not configured.");
        }

        $maxTokens = (int) ($config['max_tokens']      ?? 1500);
        $timeout   = (int) ($config['request_timeout'] ?? 30);

        $class = self::$providerMap[$provider];
        return new $class($apiKey, $model, $maxTokens, $timeout);
    }

    /**
     * Return all registered provider slugs with display names.
     *
     * @return array<string, string>  ['claude' => 'Claude (Anthropic)', ...]
     */
    public static function getProviders(): array
    {
        return [
            'claude'   => 'Claude (Anthropic)',
            'openai'   => 'OpenAI (GPT)',
            'gemini'   => 'Google Gemini',
            'deepseek' => 'DeepSeek',
            'qwen'     => 'Alibaba Qwen',
            'glm'      => 'Zhipu GLM',
        ];
    }

    /**
     * Return available models for a given provider (without needing an API key).
     *
     * @return array<string, string>
     */
    public static function getModelsForProvider(string $provider): array
    {
        if (!isset(self::$providerMap[$provider])) {
            return [];
        }
        // Instantiate with dummy values just to call getAvailableModels()
        $class    = self::$providerMap[$provider];
        $instance = new $class('dummy', 'dummy');
        return $instance->getAvailableModels();
    }
}
