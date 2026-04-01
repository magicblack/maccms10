<?php

namespace addons\aicontent\controller;

use think\addons\Controller;
use addons\aicontent\model\AiTask;
use addons\aicontent\service\ContentGenerator;
use addons\aicontent\service\ModelFactory;

/**
 * AJAX API controller for the AI Content Assistant plugin.
 * All responses are JSON.
 * Routes under: /addons/aicontent/api/
 */
class Api extends Controller
{
    protected $noNeedLogin = [];
    protected $noNeedRight = [];

    public function _initialize()
    {
        parent::_initialize();

        // Verify MaCMS admin session — Api routes go through index.php which
        // does not run the built-in Begin behaviour that normally enforces login.
        if (session('admin_auth') !== '1' || empty(session('admin_info'))) {
            echo json_encode(['code' => 0, 'message' => lang('Unauthorized. Please log in to the admin panel.')]);
            exit;
        }

        if ($this->request->isPost()) {
            $token    = input('_csrf_token', '');
            $expected = \addons\aicontent\Aicontent::generateCsrfToken();
            if (!hash_equals($expected, $token)) {
                echo json_encode(['code' => 0, 'message' => lang('Invalid request token.')]);
                exit;
            }
        }
    }

    /**
     * Generate content for a single item.
     *
     * POST /addons/aicontent/api/generate
     * Body params:
     *   - content_type  string  video|article|topic
     *   - content_id    int     (0 for ad-hoc generation without saving)
     *   - content_name  string
     *   - provider      string  (optional, uses config default)
     *   - model         string  (optional, uses config default)
     *   - data          array   template variables (title, type, year, area, actor, director)
     */
    public function generate()
    {
        if (!$this->rateLimit('generate', 20, 60)) {
            return $this->json(false, lang('Rate limit exceeded. Please wait a moment.'));
        }

        $contentType = input('content_type', 'video');
        $contentId   = (int) input('content_id', 0);
        $contentName = input('content_name', '');
        $provider    = input('provider', '') ?: null;
        $model       = input('model', '')    ?: null;
        $data        = input('data/a', []);

        // Ensure title is set
        if (empty($data['title']) && !empty($contentName)) {
            $data['title'] = $contentName;
        }

        if (empty($data['title'])) {
            return $this->json(false, lang('Title is required for content generation.'));
        }

        // Determine actual provider/model from config if not provided
        $config           = get_addon_config('aicontent');
        $resolvedProvider = $provider ?? ($config['default_provider'] ?? 'claude');
        $resolvedModel    = $model    ?? ($config['default_model']    ?? 'claude-sonnet-4-6');

        // Create task record (only for real content items)
        $task = null;
        if ($contentId > 0) {
            $task = AiTask::createTask(
                $contentType,
                $contentId,
                $contentName,
                $resolvedProvider,
                $resolvedModel
            );
        }

        try {
            $generator = new ContentGenerator($resolvedProvider, $resolvedModel);
            $result    = $generator->generate($data, $contentType);

            if ($task) {
                $task->markDone(json_encode($result, JSON_UNESCAPED_UNICODE));
            }

            return $this->json(true, lang('Content generated successfully.'), $result);

        } catch (\Throwable $e) {
            if ($task) {
                $task->markError($e->getMessage());
            }
            return $this->json(false, $this->safeError($e, lang('Content generation failed. Please check your AI provider configuration.')));
        }
    }

    /**
     * Batch generate content for multiple content IDs.
     *
     * POST /addons/aicontent/api/batch
     * Body params:
     *   - content_type  string
     *   - ids           array   content IDs to process
     *   - provider      string  (optional)
     *   - model         string  (optional)
     */
    public function batch()
    {
        if (!$this->rateLimit('batch', 5, 60)) {
            return $this->json(false, lang('Rate limit exceeded. Please wait a moment.'));
        }

        $contentType = input('content_type', 'video');
        $ids         = input('ids/a', []);
        $provider    = input('provider', '') ?: null;
        $model       = input('model', '')    ?: null;

        if (empty($ids)) {
            return $this->json(false, lang('No content IDs provided.'));
        }

        $config  = get_addon_config('aicontent');
        $maxSize = (int) ($config['batch_size'] ?? 10);
        $ids     = array_slice((array) $ids, 0, $maxSize);

        // Resolve provider/model
        $resolvedProvider = $provider ?? ($config['default_provider'] ?? 'claude');
        $resolvedModel    = $model    ?? ($config['default_model']    ?? 'claude-sonnet-4-6');

        // Load content records from MaCMS database
        $tableMap = [
            'video'   => 'mac_vod',
            'article' => 'mac_art',
            'topic'   => 'mac_topic',
        ];

        $table = $tableMap[$contentType] ?? 'mac_vod';

        // Field maps per content type
        $fieldMap = [
            'video'   => ['id' => 'vod_id',   'title' => 'vod_name',   'type' => 'type_name', 'year' => 'vod_year', 'area' => 'vod_area', 'actor' => 'vod_actor', 'director' => 'vod_director'],
            'article' => ['id' => 'art_id',   'title' => 'art_name',   'type' => 'type_name'],
            'topic'   => ['id' => 'topic_id', 'title' => 'topic_name', 'type' => 'type_name'],
        ];

        $fields = $fieldMap[$contentType] ?? $fieldMap['video'];
        $idCol  = $fields['id'];

        try {
            $rows = \think\Db::table($table)
                ->whereIn($idCol, $ids)
                ->select();
        } catch (\Throwable $e) {
            return $this->json(false, $this->safeError($e, lang('Failed to load content records.')));
        }

        $results = [];
        $generator = new ContentGenerator($resolvedProvider, $resolvedModel);

        foreach ($rows as $row) {
            $contentId   = $row[$idCol] ?? 0;
            $contentName = $row[$fields['title']] ?? '';

            // Map DB row to template variables
            $data = [];
            foreach ($fields as $tplKey => $dbCol) {
                if ($tplKey === 'id') continue;
                $data[$tplKey] = $row[$dbCol] ?? '';
            }
            $data['title'] = $contentName;

            $task = AiTask::createTask(
                $contentType,
                (int) $contentId,
                $contentName,
                $resolvedProvider,
                $resolvedModel
            );

            try {
                $result = $generator->generate($data, $contentType);
                $task->markDone(json_encode($result, JSON_UNESCAPED_UNICODE));

                $results[] = [
                    'id'      => $contentId,
                    'name'    => $contentName,
                    'success' => true,
                    'result'  => $result,
                ];
            } catch (\Throwable $e) {
                $task->markError($e->getMessage());
                $results[] = [
                    'id'      => $contentId,
                    'name'    => $contentName,
                    'success' => false,
                    'error'   => $this->safeError($e, lang('Generation failed for this item.')),
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $msg = sprintf(lang('Processed %d items, %d succeeded.'), count($results), $successCount);
        return $this->json(true, $msg, $results);
    }

    /**
     * Enhance an existing draft text for a specific field.
     *
     * POST /addons/aicontent/api/enhance
     * Body:
     *   - draft         string  The user's current draft text
     *   - field         string  blurb | content  (which field is being enhanced)
     *   - title         string  Content title (for context)
     *   - content_type  string  video | article
     *   - provider      string  (optional)
     *   - model         string  (optional)
     */
    public function enhance()
    {
        if (!$this->rateLimit('enhance', 30, 60)) {
            return $this->json(false, lang('Rate limit exceeded. Please wait a moment.'));
        }

        $draft       = trim(input('draft', ''));
        $field       = input('field', 'blurb');
        $title       = input('title', '');
        $contentType = input('content_type', 'video');
        $provider    = input('provider', '') ?: null;
        $model       = input('model', '')    ?: null;

        if (empty($draft)) {
            return $this->json(false, lang('Please write something first before enhancing.'));
        }

        $config           = get_addon_config('aicontent');
        $resolvedProvider = $provider ?? ($config['default_provider'] ?? 'claude');
        $resolvedModel    = $model    ?? ($config['default_model']    ?? 'claude-sonnet-4-6');

        try {
            $generator = new ContentGenerator($resolvedProvider, $resolvedModel);
            $enhanced  = $generator->enhance($draft, $field, ['title' => $title], $contentType);
            return $this->json(true, lang('Enhanced successfully.'), ['text' => $enhanced]);
        } catch (\Throwable $e) {
            return $this->json(false, $this->safeError($e, lang('Enhancement failed. Please check your AI provider configuration.')));
        }
    }

    /**
     * Test that the configured API key for a provider works.
     *
     * POST /addons/aicontent/api/testkey
     * Body: provider (string)
     */
    public function testkey()
    {
        if (!$this->rateLimit('testkey', 3, 60)) {
            return $this->json(false, lang('Rate limit exceeded. Please wait a moment.'));
        }

        $provider = input('provider', '');

        if (empty($provider)) {
            return $this->json(false, lang('Provider is required.'));
        }

        try {
            $model  = ModelFactory::create($provider);
            $ok     = $model->testConnection();
            return $this->json($ok, $ok ? lang('Connection successful.') : lang('Connection failed.'));
        } catch (\Throwable $e) {
            return $this->json(false, $this->safeError($e, lang('Connection test failed. Please verify your API key.')));
        }
    }

    /**
     * Return available models for a given provider.
     *
     * GET /addons/aicontent/api/models?provider=claude
     */
    public function models()
    {
        $provider = input('provider', '');
        $models   = ModelFactory::getModelsForProvider($provider);
        return $this->json(true, '', $models);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return a safe, client-facing error message and log the full exception.
     * Prevents API keys, file paths, SQL fragments, and stack traces from
     * leaking to the browser.
     */
    private function safeError(\Throwable $e, string $fallback = ''): string
    {
        if ($fallback === '') {
            $fallback = lang('An unexpected error occurred. Please try again.');
        }

        \think\Log::error('AiContent API error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        $msg = $e->getMessage();

        // Database errors — never expose SQL or schema details
        if (stripos($msg, 'SQLSTATE') !== false
            || stripos($msg, 'mysql') !== false
            || stripos($msg, 'sqlite') !== false) {
            return lang('A database error occurred. Please try again later.');
        }

        // Potentially sensitive patterns — fall back to generic message
        $sensitivePatterns = [
            '/sk-[a-zA-Z0-9]+/',          // OpenAI / Anthropic key fragments
            '/key[=:\s]+\S+/i',           // key=... or key: ...
            '/https?:\/\/\S+/',           // URLs (may include keys as query params)
            '/\/[a-z\/]+\.[a-z]{2,4}/i',  // file paths
        ];
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                return $fallback;
            }
        }

        // Safe to surface — truncate to avoid oversized responses
        return mb_substr($msg, 0, 200);
    }

    /**
     * Simple rate limiter keyed by action + session ID.
     * Uses ThinkPHP cache (Redis when available, otherwise file cache).
     *
     * @param string $action  Unique action identifier
     * @param int    $limit   Max requests allowed within $window seconds
     * @param int    $window  Time window in seconds
     * @return bool  true = allowed, false = rate limit exceeded
     */
    private function rateLimit(string $action, int $limit = 10, int $window = 60): bool
    {
        $sessionId = session_id() ?: md5(request()->ip());
        $key       = 'ai_rate_' . $action . '_' . $sessionId;

        $count = (int) cache($key);
        if ($count >= $limit) {
            return false;
        }

        if ($count === 0) {
            cache($key, 1, $window);
        } else {
            // Increment without resetting the existing TTL is not portable across
            // all cache drivers, so we just overwrite. The window resets on first
            // request — acceptable for a lightweight abuse guard.
            cache($key, $count + 1, $window);
        }

        return true;
    }

    private function json(bool $success, string $message = '', $data = null): \think\response\Json
    {
        return json([
            'code'    => $success ? 1 : 0,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}
