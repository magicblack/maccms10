<?php

namespace addons\aicontent\service;

/**
 * Orchestrates content generation:
 * - Builds prompts from templates and content data
 * - Calls the AI model
 * - Parses and returns structured results
 */
class ContentGenerator
{
    private \addons\aicontent\service\models\BaseModel $model;
    private array $config;

    public function __construct(string $provider = null, string $modelName = null)
    {
        $this->config = get_addon_config('aicontent');
        $this->model  = ModelFactory::create($provider, $modelName);
    }

    /**
     * Generate content for a single item.
     *
     * @param  array  $data  Content fields: title, type, year, area, actor, director, etc.
     * @param  string $contentType  'video' | 'article' | 'topic'
     * @return array  ['description' => '', 'tags' => [], 'seo_title' => '']
     * @throws \RuntimeException
     */
    public function generate(array $data, string $contentType = 'video'): array
    {
        $prompt = $this->buildPrompt($data, $contentType);
        $raw    = $this->model->generate($prompt);
        return $this->parseResponse($raw);
    }

    /**
     * Enhance an existing draft text for a specific field.
     *
     * @param  string $draft       The user's current draft text
     * @param  string $field       'blurb' | 'content'
     * @param  array  $context     ['title' => '...']
     * @param  string $contentType 'video' | 'article'
     * @return string              Enhanced plain text
     */
    public function enhance(string $draft, string $field, array $context, string $contentType): string
    {
        $lang    = $this->config['language'] ?? 'zh-cn';
        $title   = $context['title'] ?? '';
        $typeLabel = $contentType === 'article' ? 'article' : 'video/film';

        $fieldLabel = $field === 'content'
            ? 'full description'
            : 'short summary';

        $langInstruction = (strpos($lang, 'zh') === 0)
            ? 'Write in Chinese (中文).'
            : 'Write in ' . strtoupper($lang) . '.';

        $prompt = "You are a professional content editor for a media platform.\n\n"
            . "The user has written a draft {$fieldLabel} for a {$typeLabel}"
            . ($title ? " titled \"{$title}\"" : '') . ".\n\n"
            . "Draft:\n{$draft}\n\n"
            . "Please enhance this draft: fix grammar, improve clarity, make it more engaging and professional. "
            . "Keep the same meaning and approximate length. {$langInstruction} "
            . "Return ONLY the enhanced text with no explanation, no quotes, no prefix.";

        return trim($this->model->generate($prompt));
    }

    /**
     * Build the prompt by substituting template variables with actual data.
     */
    public function buildPrompt(array $data, string $contentType): string
    {
        $templateKey = 'prompt_' . $contentType;

        // Fall back to video template if specific type not found
        $template = $this->config[$templateKey]
            ?? $this->config['prompt_video']
            ?? $this->getDefaultTemplate($contentType);

        // Replace {variable} placeholders
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        // Remove any unreplaced placeholders
        $template = preg_replace('/\{[a-z_]+\}/', 'N/A', $template);

        return $template;
    }

    /**
     * Parse the AI response — expects JSON, but gracefully handles plain text.
     *
     * @return array ['description' => string, 'tags' => array, 'seo_title' => string]
     */
    public function parseResponse(string $raw): array
    {
        $raw = trim($raw);

        // Strip markdown code fences if present
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return [
                'description' => $decoded['description'] ?? '',
                'tags'        => $this->normalizeTags($decoded['tags'] ?? []),
                'seo_title'   => $decoded['seo_title']   ?? '',
                'raw'         => $raw,
            ];
        }

        // Fallback: treat entire response as description
        return [
            'description' => $raw,
            'tags'        => [],
            'seo_title'   => '',
            'raw'         => $raw,
        ];
    }

    /**
     * Ensure tags is always a flat string array.
     */
    private function normalizeTags($tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('strval', $tags)));
        }
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode(',', $tags)));
        }
        return [];
    }

    /**
     * Default prompt template used when none is configured.
     */
    private function getDefaultTemplate(string $contentType): string
    {
        if ($contentType === 'article') {
            return "Based on the article titled \"{title}\" in the category \"{type}\", generate content in JSON format with keys: description, tags (array), seo_title. Reply ONLY with valid JSON.";
        }
        return "Based on the video titled \"{title}\" ({year}, {area}), starring {actor}, directed by {director}, genre: {type}, generate content in JSON format with keys: description, tags (array), seo_title. Reply ONLY with valid JSON.";
    }
}
