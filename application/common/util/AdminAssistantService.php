<?php
namespace app\common\util;

use think\Cache;

/**
 * Admin-only help bot: retrieves local markdown knowledge, optional env snapshot, calls OpenAI-compatible chat.
 */
class AdminAssistantService
{
    const KNOWLEDGE_DIR = 'data/admin_assistant';

    public function getMergedConfig()
    {
        $cfg = config('maccms.admin_assistant');
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $ai = config('maccms.ai_search');
        if (!is_array($ai)) {
            $ai = [];
        }
        $defaults = [
            'enabled' => '0',
            'use_ai_search_credentials' => '1',
            'provider' => 'openai',
            'model' => '',
            'api_base' => '',
            'api_key' => '',
            'timeout' => '45',
            'max_tokens' => '1200',
            'include_env_snapshot' => '1',
            'rate_per_minute' => '20',
            'retrieve_chunks' => '8',
        ];
        $cfg = array_merge($defaults, $cfg);
        if ((string)$cfg['use_ai_search_credentials'] === '1') {
            if (trim((string)$cfg['api_base']) === '') {
                $cfg['api_base'] = trim((string)(isset($ai['api_base']) ? $ai['api_base'] : ''));
            }
            if (trim((string)$cfg['api_key']) === '') {
                $cfg['api_key'] = trim((string)(isset($ai['api_key']) ? $ai['api_key'] : ''));
            }
            if (trim((string)$cfg['model']) === '') {
                $cfg['model'] = trim((string)(isset($ai['model']) ? $ai['model'] : 'gpt-4o-mini'));
            }
        }
        if (trim((string)$cfg['api_base']) === '') {
            $cfg['api_base'] = 'https://api.openai.com/v1';
        }
        if (trim((string)$cfg['model']) === '') {
            $cfg['model'] = 'gpt-4o-mini';
        }
        return $cfg;
    }

    public function isEnabled()
    {
        $cfg = $this->getMergedConfig();
        return (string)$cfg['enabled'] === '1' && trim((string)$cfg['api_key']) !== '';
    }

    /**
     * @param string $question
     * @param array $history [['role'=>'user'|'assistant','content'=>''], ...]
     * @return array{code:int,msg?:string,reply?:string,citations?:array}
     */
    public function chat($question, array $history = [])
    {
        $cfg = $this->getMergedConfig();
        if ((string)$cfg['enabled'] !== '1') {
            return ['code' => 1003, 'msg' => lang('admin/assistant/err_disabled')];
        }
        $apiKey = trim((string)$cfg['api_key']);
        if ($apiKey === '') {
            return ['code' => 1004, 'msg' => lang('admin/assistant/err_no_key')];
        }

        $question = trim(strip_tags((string)$question));
        if ($question === '') {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        if (mb_strlen($question, 'UTF-8') > 4000) {
            $question = mb_substr($question, 0, 4000, 'UTF-8');
        }
        if ($this->looksLikeSecretPaste($question)) {
            return ['code' => 1005, 'msg' => lang('admin/assistant/err_secrets')];
        }

        $chunks = $this->loadKnowledgeChunks();
        $top = $this->retrieveTopChunks($question, $chunks, max(1, min(12, intval($cfg['retrieve_chunks']))));
        $kb = $this->formatKnowledgeForPrompt($top);

        $envBlock = '';
        if ((string)$cfg['include_env_snapshot'] === '1') {
            $envBlock = $this->buildEnvSnapshot();
        }

        $system = $this->buildSystemPrompt($kb, $envBlock);
        $messages = [['role' => 'system', 'content' => $system]];
        $tail = array_slice($history, -10);
        foreach ($tail as $row) {
            if (!is_array($row)) {
                continue;
            }
            $role = isset($row['role']) ? (string)$row['role'] : '';
            $content = isset($row['content']) ? trim(strip_tags((string)$row['content'])) : '';
            if ($content === '' || mb_strlen($content, 'UTF-8') > 8000) {
                continue;
            }
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $apiBase = rtrim((string)$cfg['api_base'], '/');
        $url = $apiBase . '/chat/completions';
        $timeout = max(8, intval($cfg['timeout']));
        $maxTokens = max(256, min(4096, intval($cfg['max_tokens'])));
        $post = [
            'model' => (string)$cfg['model'],
            'temperature' => 0.3,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $body = HttpClient::curlPostWithTimeout($url, json_encode($post, JSON_UNESCAPED_UNICODE), $headers, $timeout);
        if ($body === false || $body === '') {
            return ['code' => 1006, 'msg' => lang('admin/assistant/err_network')];
        }
        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            return ['code' => 1007, 'msg' => lang('admin/assistant/err_bad_response')];
        }
        if (isset($json['error'])) {
            $em = isset($json['error']['message']) ? (string)$json['error']['message'] : 'error';
            return ['code' => 1008, 'msg' => mb_substr($em, 0, 500, 'UTF-8')];
        }
        $reply = '';
        if (isset($json['choices'][0]['message']['content'])) {
            $reply = trim((string)$json['choices'][0]['message']['content']);
        }
        if ($reply === '') {
            return ['code' => 1007, 'msg' => lang('admin/assistant/err_bad_response')];
        }

        $citations = [];
        foreach ($top as $t) {
            $citations[] = ['file' => $t['file'], 'title' => $t['title']];
        }
        return ['code' => 1, 'reply' => $reply, 'citations' => $citations];
    }

    public function consumeRateLimit($adminId, $perMinute)
    {
        $adminId = (int)$adminId;
        $perMinute = max(1, min(120, (int)$perMinute));
        if ($adminId <= 0) {
            return false;
        }
        $bucket = intval(time() / 60);
        $key = 'admin_assistant_rl:' . $adminId . ':' . $bucket;
        $n = intval(Cache::get($key, 0));
        if ($n >= $perMinute) {
            return false;
        }
        Cache::set($key, $n + 1, 70);
        return true;
    }

    private function looksLikeSecretPaste($text)
    {
        if (preg_match('/\bsk-[a-zA-Z0-9]{10,}\b/', $text)) {
            return true;
        }
        if (preg_match('/api[_-]?key\s*[:=]\s*\S{12,}/i', $text)) {
            return true;
        }
        if (preg_match('/BEGIN\s+RSA\s+PRIVATE\s+KEY/', $text)) {
            return true;
        }
        return false;
    }

    private function loadKnowledgeChunks()
    {
        $dir = APP_PATH . self::KNOWLEDGE_DIR;
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.md') ?: [] as $path) {
            $base = basename($path);
            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $title = $base;
            if (preg_match('/^#\s+(.+)$/m', $raw, $m)) {
                $title = trim($m[1]);
            }
            $out[] = [
                'file' => $base,
                'title' => $title,
                'body' => $raw,
            ];
        }
        return $out;
    }

    private function retrieveTopChunks($query, array $chunks, $k)
    {
        if (empty($chunks)) {
            return [];
        }
        $q = mb_strtolower(trim((string)$query), 'UTF-8');
        $terms = $this->buildSearchTerms($q);

        $scored = [];
        foreach ($chunks as $ch) {
            $hay = mb_strtolower($ch['title'] . "\n" . $ch['body'], 'UTF-8');
            $score = 0;
            if ($q !== '' && mb_strpos($hay, $q, 0, 'UTF-8') !== false) {
                $score += 24;
            }
            $matched = 0;
            foreach ($terms as $term) {
                if (mb_strpos($hay, $term, 0, 'UTF-8') !== false) {
                    $score += mb_strlen($term, 'UTF-8') > 2 ? 3 : 2;
                    $matched++;
                }
            }
            $score += min(10, $matched);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'chunk' => $ch];
            }
        }
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        if (empty($scored)) {
            return array_slice($chunks, 0, $k);
        }
        $pick = [];
        foreach ($scored as $row) {
            $pick[] = $row['chunk'];
            if (count($pick) >= $k) {
                break;
            }
        }
        return $pick;
    }

    private function buildSearchTerms($q)
    {
        $terms = [];
        if ($q !== '') {
            $terms[] = $q;
        }
        $words = preg_split('/[\s\p{P}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $w) {
            if (mb_strlen($w, 'UTF-8') > 1) {
                $terms[] = $w;
                if (preg_match('/^[a-z]{4,}$/', $w) && substr($w, -1) === 's') {
                    $terms[] = substr($w, 0, -1);
                }
            }
        }

        $compact = preg_replace('/[\s\p{P}]+/u', '', $q);
        if ($compact !== null && preg_match('/[\x{4e00}-\x{9fff}]/u', $compact)) {
            $len = mb_strlen($compact, 'UTF-8');
            for ($i = 0; $i < $len - 1; $i++) {
                $terms[] = mb_substr($compact, $i, 2, 'UTF-8');
            }
        }
        $terms = array_values(array_unique(array_filter($terms, function ($w) {
            return mb_strlen($w, 'UTF-8') > 1;
        })));
        return $terms;
    }

    private function formatKnowledgeForPrompt(array $top)
    {
        $parts = [];
        foreach ($top as $ch) {
            $parts[] = "### FILE: " . $ch['file'] . "\n" . $ch['body'];
        }
        return implode("\n\n---\n\n", $parts);
    }

    private function buildEnvSnapshot()
    {
        $c = $GLOBALS['config'] ?? [];
        if (!is_array($c)) {
            return '(no runtime config)';
        }
        $app = isset($c['app']) && is_array($c['app']) ? $c['app'] : [];
        $site = isset($c['site']) && is_array($c['site']) ? $c['site'] : [];
        $safe = [
            'php_version' => PHP_VERSION,
            'search_frontend' => (string)(isset($app['search']) ? $app['search'] : ''),
            'lang' => (string)(isset($app['lang']) ? $app['lang'] : ''),
            'cache_type' => (string)(isset($app['cache_type']) ? $app['cache_type'] : ''),
            'site_name' => (string)(isset($site['site_name']) ? $site['site_name'] : ''),
            'template_dir' => (string)(isset($site['template_dir']) ? $site['template_dir'] : ''),
        ];
        return json_encode($safe, JSON_UNESCAPED_UNICODE);
    }

    /**
     * All menu/* strings from application/lang/{locale}.php — must match sidebar labels for that locale.
     */
    private function buildMenuI18nBlock()
    {
        $raw = strtolower(trim((string)config('default_lang')));
        $langId = preg_replace('/[^a-z0-9_-]/', '', $raw);
        if ($langId === '') {
            $langId = 'zh-cn';
        }
        $path = APP_PATH . 'lang/' . $langId . '.php';
        if (!is_file($path)) {
            return "locale={$langId}\n(menu language file missing)";
        }
        /** @var array $dict */
        $dict = include $path;
        if (!is_array($dict)) {
            return "locale={$langId}\n(menu language file invalid)";
        }
        $lines = [];
        foreach ($dict as $key => $label) {
            if (!is_string($key) || strpos($key, 'menu/') !== 0) {
                continue;
            }
            if (!is_scalar($label)) {
                continue;
            }
            $text = trim((string)$label);
            if ($text === '') {
                continue;
            }
            $lines[$key] = $key . ': ' . $text;
        }
        if ($lines === []) {
            return "locale={$langId}\n(no menu/* keys)";
        }
        ksort($lines);

        return "locale={$langId}\n" . implode("\n", $lines);
    }

    private function buildSystemPrompt($knowledgeBlock, $envBlock)
    {
        $replyLang = trim((string)lang('admin/assistant/prompt_reply_language'));
        if ($replyLang === '' || $replyLang === 'admin/assistant/prompt_reply_language') {
            $replyLang = 'Match the admin UI language (see ENV_SNAPSHOT.lang). Write your **entire** answer in that language unless the user explicitly requests another.';
        }

        $menuLabels = $this->buildMenuI18nBlock();

        return implode("\n", [
            'You are the administrative help assistant for maccms (苹果CMS / MacCMS), a PHP content management system.',
            $replyLang,
            'Answer only using KNOWLEDGE_BASE and ENV_SNAPSHOT. If something is not covered, say so and suggest a high-level area using **only** labels from ADMIN_MENU_LABELS (never invent menu names).',
            'ADMIN_MENU_LABELS lists the exact sidebar/menu titles for this admin locale (keys are `menu/...` language-pack entries). KNOWLEDGE_BASE often cites those keys after `/` — always resolve them through ADMIN_MENU_LABELS.',
            'For every admin path you mention: use ADMIN_MENU_LABELS text only. Example: KNOWLEDGE_BASE says `menu/system` alongside `menu/config` → output `LABEL(menu/system)` + \' → \' + `LABEL(menu/config)`. If KNOWLEDGE_BASE shows Chinese or English prose like `系统 → …` alongside a `menu/...` key, **ignore that prose for names** — use ADMIN_MENU_LABELS strings instead.',
            'Do not invent or guess menu wording; only use ADMIN_MENU_LABELS plus logical ` → ` joins between parent sidebar group and submenu when KNOWLEDGE_BASE implies that structure.',
            'For how-to questions, give concrete numbered steps (1. 2. 3.) matching the workflow in KNOWLEDGE_BASE. Mention prerequisites (e.g. create category before adding content).',
            'Never request or repeat API keys, passwords, database credentials, or full config file contents.',
            'Do not instruct users to disable security, delete data blindly, or expose server paths beyond what is in KNOWLEDGE_BASE.',
            'You cannot execute changes; only explain steps.',
            '',
            'ENV_SNAPSHOT (non-secret flags only):',
            $envBlock,
            '',
            'ADMIN_MENU_LABELS:',
            $menuLabels,
            '',
            'KNOWLEDGE_BASE:',
            $knowledgeBlock,
        ]);
    }
}
