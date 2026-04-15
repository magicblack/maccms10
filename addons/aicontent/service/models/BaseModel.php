<?php

namespace addons\aicontent\service\models;

/**
 * Abstract base class for all AI provider integrations.
 * Each concrete model must implement generate() and getAvailableModels().
 */
abstract class BaseModel
{
    /** @var string API key for this provider */
    protected $apiKey;

    /** @var string Model identifier (e.g. claude-sonnet-4-6) */
    protected $model;

    /** @var int Maximum tokens to generate */
    protected $maxTokens;

    /** @var int HTTP request timeout in seconds */
    protected $timeout;

    public function __construct(string $apiKey, string $model, int $maxTokens = 1500, int $timeout = 30)
    {
        $this->apiKey    = $apiKey;
        $this->model     = $model;
        $this->maxTokens = $maxTokens;
        $this->timeout   = $timeout;
    }

    /**
     * Send a prompt and return the text response.
     *
     * @param  string $prompt
     * @return string Raw text from the AI
     * @throws \RuntimeException on HTTP or API error
     */
    abstract public function generate(string $prompt): string;

    /**
     * Return list of available models for this provider.
     * Format: ['model-id' => 'Display Name']
     *
     * @return array<string, string>
     */
    abstract public function getAvailableModels(): array;

    /**
     * Verify that the API key works by sending a minimal request.
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->generate('Reply with the single word: OK');
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Shared cURL helper used by all concrete models.
     *
     * @param  string $url
     * @param  array  $headers  Key-value header pairs
     * @param  array  $payload  Will be JSON-encoded as POST body
     * @return array  ['status' => int, 'body' => string]
     */
    protected function httpPost(string $url, array $headers, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $this->buildHeaders($headers),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE        => false,  // prevent key-bearing URLs appearing in logs
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            // Strip any URL (which may contain API keys as query params) from the error string
            $safeError = preg_replace('/https?:\/\/\S+/', '[API_URL]', $error);
            throw new \RuntimeException("cURL error: {$safeError}");
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * Convert associative header array to indexed array of "Key: Value" strings.
     */
    private function buildHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $out[] = "{$key}: {$value}";
        }
        return $out;
    }

    /**
     * Parse JSON response body, throw on error.
     *
     * @throws \RuntimeException
     */
    protected function parseJson(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }
        return $data;
    }
}
