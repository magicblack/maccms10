<?php

namespace addons\aicontent;

use think\Addons;

/**
 * AI Content Assistant Plugin
 *
 * Integrates multiple AI providers (Claude, OpenAI, Gemini, DeepSeek, Qwen, GLM)
 * to generate and enrich MaCMS content: descriptions, tags, SEO titles.
 */
class Aicontent extends Addons
{
    public $info = [
        'name'    => 'aicontent',
        'title'   => 'AI内容助理',
        'intro'   => 'AI Content Assistant',
        'author'  => 'Yutaka Yoshi',
        'version' => '1.0.0',
        'state'   => 1,
    ];

    /**
     * Called on plugin installation.
     * Creates the mac_ai_task database table and deploys static assets.
     */
    public function install(): bool
    {
        $sqlFile = $this->addon_path . 'install.sql';
        if (is_file($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if ($statement) {
                    \think\Db::execute($statement);
                }
            }
        }
        $this->deployAssets();
        return true;
    }

    /**
     * Called when the plugin is enabled.
     * Ensures static assets are in place.
     */
    public function enable(): bool
    {
        $this->deployAssets();
        return true;
    }

    /**
     * Return a per-session CSRF token for this plugin's POST endpoints.
     * Generated once and stored in the ThinkPHP session so both the page render
     * and subsequent API requests always see the same value.
     */
    public static function generateCsrfToken(): string
    {
        $token = session('aicontent_csrf_token');
        if (empty($token)) {
            $token = bin2hex(random_bytes(16));
            session('aicontent_csrf_token', $token);
        }
        return (string) $token;
    }

    /**
     * Copies assets/ → ROOT_PATH/static/addons/aicontent/
     * so that JS, CSS, and images are web-accessible.
     * Only copies files that are missing or older than the source.
     */
    private function deployAssets(): void
    {
        $srcBase = $this->addon_path . 'assets' . DS;
        $dstBase = ROOT_PATH . 'static' . DS . 'addons' . DS . 'aicontent' . DS;

        if (!is_dir($srcBase)) {
            return;
        }

        $this->copyDir($srcBase, $dstBase);
    }

    /**
     * Recursively copies $src directory into $dst.
     * Only copies files that are missing or older than the source.
     */
    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . $item;
            $dstPath = $dst . $item;
            if (is_dir($srcPath)) {
                $this->copyDir($srcPath . DS, $dstPath . DS);
            } elseif (!is_file($dstPath) || filemtime($srcPath) > filemtime($dstPath)) {
                copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * Recursively removes a directory and all its contents.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DS . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Called on plugin uninstallation.
     * Drops the mac_ai_task table and removes deployed static assets.
     */
    public function uninstall(): bool
    {
        \think\Db::execute('DROP TABLE IF EXISTS `mac_ai_task`');

        $target = ROOT_PATH . 'static' . DS . 'addons' . DS . 'aicontent';
        if (is_link($target)) {
            unlink($target);
        } elseif (is_dir($target)) {
            $this->removeDir($target);
        }

        return true;
    }

    /**
     * Return a JSON string of all JS-facing translations for the current locale.
     * Inject into pages as: <script>window.AI_LANG = <?= Aicontent::jsLangJson() ?>;</script>
     */
    public static function jsLangJson(): string
    {
        $map = [
            'write_first'       => 'Write something in this field first, then click AI to enhance it.',
            'enhanced'          => 'Enhanced!',
            'enhance_failed'    => 'Enhancement failed.',
            'enhance_title'     => 'Enhance with AI',
            'enter_key_first'   => 'Enter the API key first.',
            'please_enter_key'  => 'Please enter the API key first.',
            'testing'           => 'Testing...',
            'generating'        => 'Generating...',
            'generation_failed' => 'Generation failed.',
            'copied'            => 'Copied!',
            'failed'            => 'Failed',
            'seo_title_label'   => 'SEO Title: ',
            'description_label' => 'Description: ',
            'tags_label'        => 'Tags: ',
            'ok'                => '✓ OK',
            'fail'              => '✗ Fail',
            'connected'         => '✓ Connected',
            'delete_confirm'    => 'Delete this task record?',
            'generate_btn'      => 'Generate Content',
        ];

        $result = [];
        foreach ($map as $jsKey => $langKey) {
            $result[$jsKey] = lang($langKey);
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Hook: app_init
     * Registers the plugin's service namespace so ThinkPHP's autoloader
     * can find classes under addons\aicontent\service\*.
     */
    public function appInit(): void
    {
        // Only deploy assets when the target directory is missing (first boot or
        // manual deletion). install() and enable() handle the normal deploy path;
        // running a full scandir+filemtime pass on every request is wasteful.
        $dstBase = ROOT_PATH . 'static' . DS . 'addons' . DS . 'aicontent' . DS;
        if (!is_dir($dstBase)) {
            $this->deployAssets();
        }

        // Load plugin translations into ThinkPHP's lang pool so that lang('key')
        // and {:lang('key')} in templates return the correct locale strings.
        // ThinkPHP 5.0 does not expose getLangSet(); read the cookie that MaCMS
        // sets when the admin switches language, then fall back to the app config.
        $locale = \think\Cookie::get('think_lang')
               ?: \think\Config::get('default_lang')
               ?: 'en';
        $langFile = ADDON_PATH . 'aicontent' . DS . 'lang' . DS . $locale . '.php';
        if (!is_file($langFile)) {
            // Normalize: zh-* → zh-cn, anything else → en
            $fallback = (strpos($locale, 'zh') === 0) ? 'zh-cn' : 'en';
            $langFile = ADDON_PATH . 'aicontent' . DS . 'lang' . DS . $fallback . '.php';
        }
        if (is_file($langFile)) {
            \think\Lang::load($langFile);
        }

        \think\Loader::addNamespace(
            'addons\\aicontent\\service',
            ADDON_PATH . 'aicontent' . DS . 'service' . DS
        );

        // maccms disables ThinkPHP route checking (url_route_on=false) by default,
        // which prevents the fastadmin-addons Route::any('addons/:addon/...') from matching.
        // Only enable route checking when the request targets this addon, to avoid
        // side-effects on MaCMS's own URL resolution for all other requests.
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, 'addons/aicontent') !== false) {
            \think\App::route(true);
        }
    }

    /**
     * Hook: action_begin
     * Sets a flag when we are on a content edit page.
     * The actual injection is done in viewFilter() below.
     */
    public function actionBegin(&$params): void
    {
        $request    = \think\Request::instance();
        $controller = strtolower($request->controller());
        $action     = strtolower($request->action());
        $module     = strtolower($request->module());

        if ($module !== 'admin') {
            return;
        }

        $editControllers = ['vod', 'art', 'topic'];
        $editActions     = ['info'];

        if (in_array($controller, $editControllers) && in_array($action, $editActions)) {
            if (!defined('AICONTENT_INJECT')) {
                define('AICONTENT_INJECT', true);
            }
        }
    }

    /**
     * Hook: view_filter
     * Appends the "AI Generate" button script to the HTML output
     * of content edit pages.
     * Per the MaCMS plugin docs, view_filter receives the rendered HTML
     * by reference and this method modifies it directly.
     */
    public function viewFilter(string &$content): void
    {
        // ── CSRF token ────────────────────────────────────────────────────────
        // Inject only on pages that actually interact with this plugin's API:
        //   - Content edit pages (vod/art/topic info) — flagged by AICONTENT_INJECT
        //   - Addon controller pages (config, generate, index, addon list)
        $req = \think\Request::instance();
        $needsToken = defined('AICONTENT_INJECT')
            || (strtolower($req->module()) === 'admin'
                && strtolower($req->controller()) === 'addon');
        if ($needsToken && strpos($content, '</body>') !== false) {
            $token = self::generateCsrfToken();
            $content = str_replace(
                '</body>',
                "<script>window.AI_CSRF_TOKEN='{$token}';</script></body>",
                $content
            );
        }

        // ── Addon list page ───────────────────────────────────────────────────
        // Fix logo image URL: info.ini uses /static/addons/... which is root-relative,
        // but MaCMS may be installed in a subdirectory. Prepend ROOT_PATH in JS.
        // Also compact the addon-card action buttons so English labels don't wrap.
        $req = \think\Request::instance();
        if (strtolower($req->controller()) === 'addon'
            && strtolower($req->action()) === 'index') {
            $fix = <<<'JS'
<style>
/* Compact addon-card action buttons so English labels fit in one row */
.add-btn { display:flex !important; flex-wrap:nowrap !important; gap:3px !important; }
.add-btn > a,
.add-btn > button,
.add-btn > span { flex:1 !important; padding-left:4px !important; padding-right:4px !important;
                  text-align:center !important; min-width:0 !important;
                  font-size:12px !important; white-space:nowrap !important;
                  overflow:hidden !important; text-overflow:ellipsis !important; }
</style>
<script>
(function () {
    function fixAddonImages() {
        document.querySelectorAll('img.add-logo').forEach(function (img) {
            var src = img.getAttribute('src');
            if (src && src.indexOf('/static/addons/') === 0) {
                img.src = ROOT_PATH + src;
            }
        });
    }
    // Compact button groups that MaCMS may render with class names other than .add-btn
    function fixButtonLayout() {
        var selectors = [
            '.add-btn',
            '.layui-card-body .layui-btn-group',
            '.addons-item .operate',
            '.addons-item [class*="btn-group"]',
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (group) {
                group.style.cssText += ';display:flex!important;flex-wrap:nowrap!important;gap:3px!important;';
                group.querySelectorAll('a,button,span').forEach(function (btn) {
                    if (btn.classList && (btn.className.indexOf('btn') !== -1 || btn.tagName === 'A')) {
                        btn.style.cssText += ';flex:1!important;padding:0 4px!important;text-align:center!important;min-width:0!important;font-size:12px!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;';
                    }
                });
            });
        });
    }
    var observer = new MutationObserver(function () {
        fixAddonImages();
        fixButtonLayout();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(function () { fixAddonImages(); fixButtonLayout(); }, 300);
    setTimeout(function () { fixAddonImages(); fixButtonLayout(); }, 1200);
})();
</script>
JS;
            $content = str_replace('</body>', $fix . '</body>', $content);
            return;
        }

        // ── Config page detection ─────────────────────────────────────────────
        // Primary: check for id="ai-provider-select" injected via config.php extend.
        // Fallback: detect by URL (controller=addon, action=config, name=aicontent)
        // in case MaCMS does not forward the extend attribute to the rendered <select>.
        $isOurConfigPage = strpos($content, 'id="ai-provider-select"') !== false;
        if (!$isOurConfigPage) {
            $req = \think\Request::instance();
            if (strtolower($req->controller()) === 'addon'
                && strtolower($req->action()) === 'config'
                && strtolower($req->param('name', '')) === 'aicontent') {
                $isOurConfigPage = true;
            }
        }
        if ($isOurConfigPage) {
            $this->injectConfigPageJs($content);
            return;
        }

        // ── Content edit pages ────────────────────────────────────────────────
        if (!defined('AICONTENT_INJECT')) {
            return;
        }

        // Guard: skip if the addon is disabled (state=0).
        // maccms disable() may leave hooks registered while setting state=0.
        $addonInfo = get_addon_info('aicontent');
        if (empty($addonInfo) || (int)($addonInfo['state'] ?? 0) !== 1) {
            return;
        }

        // Guard: skip if no API key is configured for the active provider.
        $cfg      = get_addon_config('aicontent');
        $provider = $cfg['default_provider'] ?? 'claude';
        if (empty($cfg[$provider . '_key'])) {
            return;
        }

        $request     = \think\Request::instance();
        $controller  = strtolower($request->controller());
        $isArticle   = ($controller === 'art');
        $contentType = $isArticle ? 'article' : 'video';
        $titleField  = $isArticle ? 'art_name' : 'vod_name';
        // The enhance URL must go through index.php (ROOT_PATH), NOT manage.php (ADMIN_PATH).
        // manage.php triggers Begin.php behavior which redirects any non-admin module → 302.
        // index.php (ENTRANCE='index') skips that check; the addon route resolves correctly.

        // Fields to skip (non-content technical fields)
        $skipPatterns = ['pic', 'img', 'thumb', 'screenshot', 'poster', 'url',
                         'play', 'down', 'from', 'server', 'color', 'letter',
                         'en_', '_en', 'sub', 'rel_', 'class', 'note', 'remarks'];

        $btn = '<button type="button" class="layui-btn layui-btn-xs layui-btn-normal ai-enhance-btn" '
             . 'style="margin-top:4px;display:inline-block" '
             . 'data-target="%s">&#10024; AI</button>';

        // Inject button after every eligible textarea
        $content = preg_replace_callback(
            '/<textarea([^>]*)>(.*?)<\/textarea>/s',
            function ($m) use ($skipPatterns, $btn) {
                $attrs = $m[1];
                preg_match('/name="([^"]*)"/', $attrs, $nm);
                preg_match('/id="([^"]*)"/',   $attrs, $im);
                $name  = isset($nm[1]) ? $nm[1] : '';
                $id    = isset($im[1]) ? $im[1] : '';
                $check = strtolower($name . ' ' . $id);
                foreach ($skipPatterns as $p) {
                    if (strpos($check, $p) !== false) return $m[0];
                }
                $target = $id ?: $name;
                return $m[0] . sprintf($btn, htmlspecialchars($target));
            },
            $content
        );

        // Inject button after every eligible text input
        $content = preg_replace_callback(
            '/<input([^>]+type="text"[^>]*)>/i',
            function ($m) use ($skipPatterns, $btn) {
                $attrs = $m[1];
                preg_match('/name="([^"]*)"/', $attrs, $nm);
                preg_match('/id="([^"]*)"/',   $attrs, $im);
                $name  = isset($nm[1]) ? $nm[1] : '';
                $id    = isset($im[1]) ? $im[1] : '';
                $check = strtolower($name . ' ' . $id);
                foreach ($skipPatterns as $p) {
                    if (strpos($check, $p) !== false) return $m[0];
                }
                $target = $id ?: $name;
                return $m[0] . sprintf($btn, htmlspecialchars($target));
            },
            $content
        );

        // Use ROOT_PATH and ADMIN_PATH JS globals (set by maccms admin head template)
        $jsLang = self::jsLangJson();
        $script = <<<JS
<script>
window.AI_LANG = {$jsLang};
(function () {
    var s = document.createElement('script');
    s.src = ROOT_PATH + '/static/addons/aicontent/js/aicontent.js';
    s.onload = function () {
        AiContent.initEnhance({
            enhanceUrl  : ROOT_PATH + '/index.php/addons/aicontent/api/enhance',
            titleField  : '{$titleField}',
            contentType : '{$contentType}'
        });
    };
    document.head.appendChild(s);
})();
</script>
JS;
        $content = str_replace('</body>', $script . '</body>', $content);
    }

    /**
     * Inject provider→model auto-fill and API key show/hide JS into the
     * maccms generic addon config page.
     * Called by viewFilter() when the current page is admin/addon/config?name=aicontent.
     */
    private function injectConfigPageJs(string &$content): void
    {
        $jsLang = self::jsLangJson();
        $script = "<script>window.AI_LANG = {$jsLang};</script>\n" . <<<'JS'
<script>
(function () {
    var AI_MODELS = {
        claude:   ['claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5-20251001'],
        openai:   ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
        gemini:   ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
        deepseek: ['deepseek-chat', 'deepseek-reasoner'],
        qwen:     ['qwen-plus', 'qwen-turbo', 'qwen-max', 'qwen-long'],
        glm:      ['glm-4', 'glm-4-flash', 'glm-3-turbo']
    };

    var KEY_PROVIDERS = {
        'claude_key':   'claude',
        'openai_key':   'openai',
        'gemini_key':   'gemini',
        'deepseek_key': 'deepseek',
        'qwen_key':     'qwen',
        'glm_key':      'glm'
    };

    // Walk up the DOM tree to find the form row element
    function findRow(el) {
        var p = el;
        while (p && p !== document.body) {
            if (p.classList &&
                (p.classList.contains('layui-form-item') || p.tagName === 'TR')) {
                return p;
            }
            p = p.parentElement;
        }
        return el.parentElement;
    }

    // Replace the plain text input for model with a proper <select> dropdown
    function buildModelSelect(provider, savedModel) {
        var modelInput = document.querySelector('input[name="row[default_model]"]');
        if (!modelInput) return;

        var sel = document.createElement('select');
        sel.id        = 'ai-cfg-model-sel';
        sel.name      = 'row[default_model]';
        sel.className = modelInput.className;
        sel.setAttribute('lay-filter', 'ai-cfg-model');

        (AI_MODELS[provider] || []).forEach(function (m) {
            var opt = document.createElement('option');
            opt.value       = m;
            opt.textContent = m;
            if (m === savedModel) opt.selected = true;
            sel.appendChild(opt);
        });

        modelInput.parentNode.replaceChild(sel, modelInput);

        // Ask Layui to style the new select (if available)
        if (window.layui) {
            layui.use('form', function () { layui.form.render('select'); });
        }
    }

    // Update the model dropdown options when provider changes
    function updateModelOptions(provider) {
        var sel = document.getElementById('ai-cfg-model-sel');
        if (!sel) return;

        var models = AI_MODELS[provider] || [];
        sel.innerHTML = '';
        models.forEach(function (m) {
            var opt = document.createElement('option');
            opt.value       = m;
            opt.textContent = m;
            sel.appendChild(opt);
        });

        // Re-render Layui styled select to reflect updated options
        if (window.layui) {
            layui.use('form', function () { layui.form.render('select'); });
        }
    }

    // Show only the key row that belongs to the active provider; hide the rest
    function showKeyRow(provider) {
        Object.keys(KEY_PROVIDERS).forEach(function (keyName) {
            var input = document.querySelector('input[name="row[' + keyName + ']"]');
            if (!input) return;
            var row = findRow(input);
            if (row) row.style.display = (KEY_PROVIDERS[keyName] === provider) ? '' : 'none';
        });
    }

    function init() {
        var providerSel = document.querySelector('select[name="row[default_provider]"]');
        if (!providerSel) return;

        var modelInput = document.querySelector('input[name="row[default_model]"]');
        var savedModel = modelInput ? modelInput.value : '';

        // 1. Swap the text input → styled select dropdown
        buildModelSelect(providerSel.value, savedModel);

        // 2. Hide all key rows except the active provider's
        showKeyRow(providerSel.value);

        // 3. Native change listener (fires when Layui is absent or on manual DOM select change)
        providerSel.addEventListener('change', function () {
            showKeyRow(this.value);
            updateModelOptions(this.value);
        });

        // 4. Layui styled-select event (fires for the custom Layui dropdown UI)
        if (window.layui) {
            layui.use('form', function () {
                var form = layui.form;
                if (!providerSel.getAttribute('lay-filter')) {
                    providerSel.setAttribute('lay-filter', 'ai-provider-select');
                }
                form.render('select');
                form.on('select(ai-provider-select)', function (data) {
                    showKeyRow(data.value);
                    updateModelOptions(data.value);
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 400); });
    } else {
        setTimeout(init, 400);
    }
})();
</script>
JS;
        $content = str_replace('</body>', $script . '</body>', $content);
    }
}
