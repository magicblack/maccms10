<?php
namespace app\common\util;

use app\common\view\ThemeGate;

/**
 * AI 主題產線。
 *
 * 流程固定為：
 *
 *   規格 → 提示詞 → 模型 → JSON → **白名單消毒** → 暫存到 runtime/ → 過閘 → 才搬進 template/
 *
 * 兩條不可協商的規則：
 *
 *   1. **模型直出一律不信任**。所有輸出都要過 sanitizeFiles() 的路徑／副檔名／大小白名單，
 *      這是沿用 ThemeDesign 已驗證的做法（見該類的 sanitizeDetected / normalizeHex）。
 *   2. **沒過閘的東西不准出現在 template/**。暫存區在 runtime/theme_stage/，
 *      校驗失敗就整個刪掉，template/ 全程不被碰到。
 *
 * 模型呼叫做成可注入（run() 的 $chat 參數），因此整條產線可以在沒有 API key 的
 * 環境下端到端測試 —— 這是刻意的，校驗閘的正確性不該依賴外部服務可用。
 */
class ThemeGenerator
{
    /** 允許寫入的副檔名 → 允許出現的路徑前綴 */
    private static $allowExt = [
        'twig' => 'html/',
        'css'  => 'asset/',
    ];

    const MAX_FILES = 60;
    const MAX_FILE_BYTES = 262144;    // 256KB
    const MAX_TOTAL_BYTES = 2097152;  // 2MB

    /**
     * 產一套主題。
     *
     * @param array         $spec 主題規格，見 buildUserPrompt()
     * @param callable|null $chat function(string $system, string $user): array
     *                            回傳 ['ok'=>bool,'text'=>string,'error'=>string]
     *                            傳 null 時用 themeAiChat()（讀 maccms.theme_ai 配置）
     * @return array ['code'=>1|0,'dir'=>string,'gate'=>array,'errors'=>[]]
     */
    public static function run(array $spec, $chat = null)
    {
        $dir = self::normalizeDir(isset($spec['dir']) ? $spec['dir'] : '');
        if ($dir === '') {
            return self::fail('主題目錄名非法（僅限小寫字母開頭、字母數字底線，2-32 位）');
        }
        if (is_dir(ROOT_PATH . 'template/' . $dir)) {
            return self::fail('主題目錄已存在：' . $dir);
        }

        if ($chat === null) {
            $chat = ['\app\common\util\ThemeGenerator', 'themeAiChat'];
        }
        $res = call_user_func($chat, self::buildSystemPrompt(), self::buildUserPrompt($spec));
        if (empty($res['ok'])) {
            return self::fail('模型呼叫失敗：' . (isset($res['error']) ? $res['error'] : 'unknown'));
        }

        $files = self::parseFiles(isset($res['text']) ? $res['text'] : '');
        if ($files === null) {
            return self::fail('模型輸出不是預期的 JSON（需要 {"files":{路徑:內容}}）');
        }

        $clean = self::sanitizeFiles($files);
        if (!empty($clean['errors'])) {
            return self::fail($clean['errors']);
        }

        // ---- 暫存 ----
        $token = 'gen_' . bin2hex(random_bytes(6));
        $stageRoot = RUNTIME_PATH . 'theme_stage' . DIRECTORY_SEPARATOR;
        $stageDir = $stageRoot . $token . DIRECTORY_SEPARATOR;

        $written = self::writeFiles($stageDir, $clean['files'], $spec);
        if ($written !== true) {
            self::rmdir($stageDir);
            return self::fail($written);
        }

        // ---- 過閘（對暫存區，template/ 全程未被碰到）----
        // 一律 strict：產線是「憑空生出一套要發給站長的主題」，
        // 沒有任何理由對它比對人手寫的主題寬鬆。
        $gate = ThemeGate::run($token, ['base' => $stageRoot, 'mode' => 'strict']);
        if ($gate['code'] !== 1) {
            self::rmdir($stageDir);
            return [
                'code' => 0,
                'dir' => '',
                'gate' => $gate,
                'errors' => $gate['errors'],
            ];
        }

        // ---- 過了才搬 ----
        $target = ROOT_PATH . 'template' . DIRECTORY_SEPARATOR . $dir;
        if (!@rename($stageDir, $target)) {
            self::rmdir($stageDir);
            return self::fail('搬移到 template/ 失敗（權限？）：' . $dir);
        }

        return ['code' => 1, 'dir' => $dir, 'gate' => $gate, 'errors' => []];
    }

    /**
     * 目錄名規範化：小寫字母開頭，僅字母數字底線，2-32 位。
     * 這同時是防目錄穿越的第一道 —— 任何含 / . \ 的輸入直接變空字串。
     *
     * @param string $dir
     * @return string 不合法時回空字串
     */
    public static function normalizeDir($dir)
    {
        $dir = strtolower(trim((string) $dir));
        if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $dir)) {
            return '';
        }
        // 下劃線開頭是共用資產／暫存區的保留前綴
        if (substr($dir, 0, 1) === '_') {
            return '';
        }
        return $dir;
    }

    /**
     * 從模型輸出裡取出 files 映射。容忍模型加了 ```json 圍欄或前後贅語。
     *
     * @param string $text
     * @return array|null
     */
    public static function parseFiles($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        // 去掉 markdown 圍欄
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);

        $data = json_decode($text, true);
        if (!is_array($data)) {
            // 退一步：抓第一個 { 到最後一個 } 之間
            $s = strpos($text, '{');
            $e = strrpos($text, '}');
            if ($s === false || $e === false || $e <= $s) {
                return null;
            }
            $data = json_decode(substr($text, $s, $e - $s + 1), true);
        }
        if (!is_array($data) || !isset($data['files']) || !is_array($data['files'])) {
            return null;
        }
        return $data['files'];
    }

    /**
     * 白名單消毒。**這是產線的安全核心**，模型輸出的任何一項不合規就整批拒絕。
     *
     * 規則：
     *   - 路徑不得含 .. \ 空位元組，不得以 / 開頭
     *   - 只允許 html/*.twig 與 asset/*.css
     *   - **不接受 .js**：主題的行為一律走 template/_shared/ 的共用 runtime。
     *     容許 AI 產 JS 等於在市場分發的產物裡開一個任意代碼執行的口子，
     *     那會直接推翻 docs/twig-engine-feasibility.md §5.2 接受 Twig 2.12.5 的前提。
     *   - 檔案數／單檔大小／總大小上限
     *
     * @param array $files 路徑 => 內容
     * @return array ['files'=>[], 'errors'=>[]]
     */
    public static function sanitizeFiles(array $files)
    {
        $errors = [];
        $out = [];
        $total = 0;

        if (count($files) > self::MAX_FILES) {
            return ['files' => [], 'errors' => ['檔案數超限：' . count($files) . ' > ' . self::MAX_FILES]];
        }

        foreach ($files as $path => $content) {
            $path = str_replace('\\', '/', trim((string) $path));

            if ($path === '' || strpos($path, "\0") !== false
                || strpos($path, '..') !== false || substr($path, 0, 1) === '/') {
                $errors[] = '非法路徑：' . $path;
                continue;
            }
            if (!preg_match('#^[a-z0-9_\-/.]+$#i', $path)) {
                $errors[] = '路徑含不允許的字元：' . $path;
                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!isset(self::$allowExt[$ext])) {
                $errors[] = '不允許的副檔名 .' . $ext . '：' . $path
                    . '（僅允許 html/*.twig 與 asset/*.css；行為 JS 一律用 template/_shared/ 的共用 runtime）';
                continue;
            }
            if (strpos($path, self::$allowExt[$ext]) !== 0) {
                $errors[] = '.' . $ext . ' 必須放在 ' . self::$allowExt[$ext] . ' 下：' . $path;
                continue;
            }

            $content = (string) $content;
            $len = strlen($content);
            if ($len > self::MAX_FILE_BYTES) {
                $errors[] = '單檔超限（' . $len . ' bytes）：' . $path;
                continue;
            }
            $total += $len;
            $out[$path] = $content;
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            $errors[] = '總大小超限：' . $total . ' > ' . self::MAX_TOTAL_BYTES;
        }
        if (empty($out) && empty($errors)) {
            $errors[] = '模型沒有產出任何合規檔案';
        }

        return ['files' => $out, 'errors' => $errors];
    }

    /**
     * 落盤到暫存目錄，並補上 info.ini（引擎宣告由我們寫，不讓模型決定）。
     *
     * @param string $stageDir
     * @param array  $files
     * @param array  $spec
     * @return true|array true 或錯誤陣列
     */
    private static function writeFiles($stageDir, array $files, array $spec)
    {
        if (!is_dir($stageDir) && !@mkdir($stageDir, 0755, true)) {
            return ['無法建立暫存目錄：' . $stageDir];
        }

        foreach ($files as $path => $content) {
            $full = $stageDir . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $dir = dirname($full);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                return ['無法建立目錄：' . $dir];
            }
            if (@file_put_contents($full, $content) === false) {
                return ['寫入失敗：' . $path];
            }
        }

        // info.ini 由產線自己寫：engine 與 parent 兩行都不能交給模型。
        // engine 漏寫，整套主題會被當成 taglib 主題去渲染；
        // parent 漏寫，回落鏈就斷了 —— 模型只產了幾個檔，其餘 90 幾頁全部 500。
        $name = isset($spec['name']) && $spec['name'] !== '' ? $spec['name'] : 'AI Theme';
        $name = str_replace(["\r", "\n"], ' ', mac_filter_xss((string) $name));
        $ini = "name\t= " . $name . "\n"
            . "lastdate= " . date('Y-m-d') . "\n"
            . "version\t= V1.0\n"
            . "engine\t= twig\n"
            . "parent\t= " . self::normalizeParent(isset($spec['parent']) ? $spec['parent'] : '') . "\n";
        if (@file_put_contents($stageDir . 'info.ini', $ini) === false) {
            return ['寫入 info.ini 失敗'];
        }

        return true;
    }

    /* ------------------------------------------------------------ 提示詞 */

    /**
     * 系統提示詞。
     *
     * 產出模式是**繼承**而不是整套主題：模型只產皮膚 CSS 與想覆寫的少數模板，
     * 其餘 98 個模板由 parent（預設 starter）供給。這樣做的理由有三：
     *   - 一套完整主題約 100 個模板，遠超 MAX_FILES=60，整套產根本塞不下；
     *   - 底座修 bug 時，繼承來的主題自動跟著修好，複製出去的那份收不到；
     *   - 檔案少，模型出錯的面積也小，過閘率高得多。
     *
     * @return string
     */
    public static function buildSystemPrompt()
    {
        return "You generate front-end SKINS for maccms10 (a PHP video/article CMS) on the Twig engine.\n"
            . "Output ONE JSON object ONLY — no prose, no markdown fences:\n"
            . "{\"files\": {\"<relative path>\": \"<file content>\", ...}}\n\n"
            . "== HOW THIS WORKS ==\n"
            . "Your theme INHERITS from a base theme (declared as `parent` in info.ini, which the\n"
            . "pipeline writes for you — do NOT emit info.ini). Template lookup order is:\n"
            . "    your theme/html/  ->  parent/html/  ->  @shared\n"
            . "So you only write the files you actually want to change. Everything else falls back\n"
            . "to the parent automatically. The parent already ships all 98 page templates.\n"
            . "A complete, shippable skin can be as small as TWO files:\n"
            . "    asset/css/skin.css        (override the design tokens)\n"
            . "    html/layout/base.twig     (site shell: header / footer / nav)\n"
            . "Add more page overrides only where the brief genuinely needs a different structure.\n\n"
            . "== HARD RULES (violating any one rejects the whole theme) ==\n"
            . "1. Only two kinds of files: `html/**/*.twig` and `asset/**/*.css`.\n"
            . "   NEVER emit JavaScript. Behaviour comes from the shared runtime (rule 3).\n"
            . "   NEVER emit info.ini — the pipeline writes it.\n"
            . "2. NEVER reference an external CDN (Google Fonts, icon fonts, jQuery, anything).\n"
            . "   Ship every asset inside your own asset/ directory. External CSS is\n"
            . "   render-blocking in mainland China and breaks the offline page outright.\n"
            . "   Reference your own assets with mac_theme_asset('css/skin.css').\n"
            . "3. If you write html/layout/base.twig, these THREE lines are mandatory:\n"
            . "     {% import \"@shared/components.twig\" as c %}\n"
            . "     {{ c.jsvar(maccms, csrf_token|default(''), user.user_id|default(0)) }}   <- inside <head>\n"
            . "     {{ c.runtime(maccms.path) }}                                             <- just before </body>\n"
            . "   c.jsvar publishes the global `maccms` object and `$`, which the core player\n"
            . "   scripts require; omit it and the video player silently never appears.\n"
            . "   c.runtime loads favourites / comments / login / lazy-load / i18n. Omit it and\n"
            . "   all of those silently stop working while the page still looks fine.\n"
            . "   Also keep these blocks so child pages still work:\n"
            . "     title, meta, head_extra, header, main, footer, body_class, foot_extra\n"
            . "4. Never copy @shared/components.twig or the runtime into your theme. Import them.\n"
            . "5. Twig autoescape is ON. |raw is allowed ONLY on these fields:\n"
            . "     vod_content art_content manga_content actor_content role_content\n"
            . "     topic_content website_content live_content manga_chapter_url\n"
            . "     player_data player_js\n"
            . "   User-submitted text (comment_content, gbook_content) must stay escaped.\n"
            . "   If a component of yours takes an HTML slot, declare it in the file header:\n"
            . "     {# @raw-slot: content, foot_html #}\n"
            . "   and only ever pass whitelisted fields into that slot.\n"
            . "6. FORBIDDEN: {% use %}, |sort, {% sandbox %}, template_from_string(), source(),\n"
            . "   dump(), and `x|json_encode|raw` (json_encode does not escape `/`, so `</script>`\n"
            . "   inside a string escapes the script block — use \"{{ x|e('js') }}\" instead).\n"
            . "7. There are NO custom Twig filters in this project. Use functions only.\n\n"
            . "== DATA FUNCTIONS (these and nothing else) ==\n"
            . "Each takes one map and returns a wrapper; rows live in `.list`:\n"
            . "  mac_vod mac_art mac_manga mac_type mac_actor mac_role mac_topic mac_website\n"
            . "  mac_comment mac_gbook mac_link mac_area mac_lang mac_year mac_class\n"
            . "  mac_version mac_state mac_letter\n"
            . "  {% for vo in mac_vod({num: 12, order: 'desc', by: 'time'}).list %}\n"
            . "  {% set res = mac_vod({num: 24, type: obj.type_id, paging: true}) %}{% set pg = mac_paging(res) %}\n\n"
            . "== URL HELPERS ==\n"
            . "  mac_url mac_url_page mac_url_img mac_url_type mac_url_create mac_url_content_img\n"
            . "  mac_url_vod_detail mac_url_vod_play mac_url_vod_down mac_url_vod_search\n"
            . "  mac_url_art_detail mac_url_manga_detail mac_url_manga_play mac_url_actor_detail\n"
            . "  mac_url_topic_detail mac_url_plot_detail mac_url_role_detail\n"
            . "  mac_url_website_detail mac_url_website_index url lang\n"
            . "Always start absolute URLs from maccms.path — a hardcoded \"/\" breaks subdirectory installs.\n\n"
            . "== OTHER HELPERS ==\n"
            . "  mac_paging mac_substring mac_default mac_friend_date mac_escape mac_param_url\n"
            . "  mac_day mac_filter_html mac_plain_text mac_em_replace mac_get_user_portrait\n"
            . "  mac_tpl_vod_type_cover mac_tpl_art_cover mac_tpl_manga_cover\n"
            . "  mac_theme_index_hotvod_tabs mac_td_section mac_user_fav_map mac_user_fav_id\n"
            . "  mac_ad_slot mac_top_types mac_seo_meta mac_theme_asset mac_i18n_key mac_module_label\n"
            . "  mac_live_list mac_live_cates mac_live_info mac_verify_on\n"
            . "mac_filter_html, mac_url_create, mac_em_replace and mac_day already return HTML —\n"
            . "do not add |raw to them.\n"
            . "Render *_blurb / *_content as plain text with mac_plain_text(obj.vod_blurb) — it decodes\n"
            . "stacked entities like &amp;nbsp; from collected data; never |raw a blurb.\n\n"
            . "== SHARED COMPONENTS (you supply the classes, they supply the behaviour) ==\n"
            . "  c.lazy_img(src, alt, {class})              c.fav_button(mid, id, {class, on_class})\n"
            . "  c.digg(mid, id, up, down, {class})         c.paging(pg, {class, item_class, active_class})\n"
            . "  c.paging_raw(__PAGING__, {...})            c.breadcrumb(items, {class})\n"
            . "  c.search_form({class})                     c.lang_switch({class})\n"
            . "  c.login_form(csrf_token, {...})            c.reg_form(csrf_token, {...})\n"
            . "  c.comment_form(mid, rid, csrf_token, {..}) c.comment_list(args, {...})\n"
            . "  c.gbook_form(csrf_token, {...})            c.pwd_form(mid, id, type, {...})\n"
            . "  c.captcha(scope, {...})                    c.player(player_data, player_js, {class})\n"
            . "  c.episodes(obj, groups, flag, param, {..}) c.episode_nav(obj.player_info, {...})\n"
            . "  c.popedom_gate(popedom, {class})           c.filter_row(obj, param, field, items, {..})\n"
            . "  c.filter_subtypes(obj, {...})              c.empty({class, tone, text, lang})\n"
            . "  c.status({...})                            c.vod_card(vo, {...})\n\n"
            . "== BEHAVIOUR HOOKS THAT MUST SURVIVE IN THE MARKUP ==\n"
            . "  data-lang on translatable text, data-original on lazy images, .mac_ulog on\n"
            . "  favourite buttons, digg_link on up/down. The components above emit all of them.\n"
            . "  Never rename the runtime-written classes: is_fav is-on is-active is-open\n"
            . "  lazyloaded page-auth has-mac-confirm.\n\n"
            . "== VARIABLES ==\n"
            . "  Everywhere: maccms (site_name, site_keywords, site_description, path, path_tpl,\n"
            . "              http_type, site_url, mid, aid), user (user_id, user_name), tplconfig,\n"
            . "              csrf_token\n"
            . "  List pages: obj (the category), param (the filters)\n"
            . "  Detail pages: obj (the content), popedom, comment_mid, comment_rid\n"
            . "  Play pages: player_data, player_js, obj.player_info (link_pre / link_next)\n"
            . "  Member pages: obj (the logged-in user), list, __PAGING__, param\n"
            . "  Never hardcode category names — categories are configured per site. Use\n"
            . "  mac_top_types(1, 8) / mac_type({...}) instead.\n";
    }

    /**
     * @param array $spec ['name'=>, 'style'=>, 'colors'=>, 'layout'=>, 'notes'=>, 'parent'=>]
     * @return string
     */
    public static function buildUserPrompt(array $spec)
    {
        $parent = self::normalizeParent(isset($spec['parent']) ? $spec['parent'] : '');
        $lines = ['Generate a maccms10 Twig SKIN that inherits from the base theme "' . $parent . '".'];
        $map = [
            'name'   => 'Theme name',
            'style'  => 'Visual style',
            'colors' => 'Colour direction',
            'layout' => 'Layout direction',
            'notes'  => 'Extra notes',
        ];
        foreach ($map as $k => $label) {
            if (!empty($spec[$k])) {
                $v = is_array($spec[$k]) ? json_encode($spec[$k], JSON_UNESCAPED_UNICODE) : (string) $spec[$k];
                $lines[] = $label . ': ' . $v;
            }
        }
        $lines[] = '';
        $lines[] = 'Write as few files as the brief allows. At minimum: one CSS file, and'
            . ' html/layout/base.twig if the shell needs to change.';
        $lines[] = 'Make it visually distinct — do not reuse class names or layout ideas from other themes.';
        return implode("\n", $lines);
    }

    /**
     * 父主題目錄名規範化。
     *
     * 預設 starter：那是官方底座，T0+T1 全齊且自身過 strict 閘，
     * 拿它當 parent 才能保證產出物「只寫幾個檔也不會 500」。
     *
     * @param string $parent
     * @return string
     */
    public static function normalizeParent($parent)
    {
        $parent = strtolower(trim((string) $parent));
        if ($parent === '' || !preg_match('/^[a-z][a-z0-9_]{1,31}$/', $parent)) {
            return 'starter';
        }
        if (!is_dir(ROOT_PATH . 'template' . DIRECTORY_SEPARATOR . $parent . DIRECTORY_SEPARATOR . 'html')) {
            return 'starter';
        }
        return $parent;
    }

    /* --------------------------------------------------------- 模型呼叫 */

    /**
     * 讀 maccms.theme_ai 配置呼叫模型（OpenAI 相容 / Anthropic 兩種形態）。
     *
     * 註：application/admin/controller/ThemeDesign.php 有一份形態相同的私有實作。
     * 沒有把它抽出來共用，是因為那是穩定的線上代碼，重構它超出本次範圍
     * （AGENTS.md：不順手改既有穩定路徑）。若日後要收斂，應作為獨立改動處理。
     *
     * @param string $system
     * @param string $user
     * @return array ['ok'=>bool,'text'=>string,'error'=>string]
     */
    public static function themeAiChat($system, $user)
    {
        $cfg = config('maccms.theme_ai');
        if (!is_array($cfg)) {
            $cfg = [];
        }
        if ((string) (isset($cfg['enabled']) ? $cfg['enabled'] : '0') !== '1') {
            return ['ok' => false, 'error' => '主題 AI 未啟用'];
        }
        $key = trim((string) (isset($cfg['api_key']) ? $cfg['api_key'] : ''));
        $base = rtrim(trim((string) (isset($cfg['api_base']) ? $cfg['api_base'] : '')), '/');
        if ($key === '' || $base === '') {
            return ['ok' => false, 'error' => '主題 AI 未配置 api_base / api_key'];
        }

        $provider = strtolower(trim((string) (isset($cfg['provider']) ? $cfg['provider'] : 'openai')));
        $model = trim((string) (isset($cfg['model']) ? $cfg['model'] : ''));
        if ($model === '') {
            $model = 'claude-opus-4-8';
        }
        $timeout = max(30, min(600, intval(isset($cfg['timeout']) ? $cfg['timeout'] : 180)));
        // 產一整套主題比產配色 token 費得多，上限單獨放大
        $maxTokens = max(4000, min(32000, intval(isset($cfg['theme_max_tokens']) ? $cfg['theme_max_tokens'] : 16000)));

        if ($provider === 'claude' || $provider === 'anthropic') {
            $url = $base . '/messages';
            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
        } else {
            $url = $base . '/chat/completions';
            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $key];
        }

        $resp = HttpClient::curlPostWithTimeout($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, $timeout);
        $data = json_decode((string) $resp, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => '模型回應無法解析'];
        }

        $text = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $blk) {
                if (is_array($blk) && isset($blk['text'])) {
                    $text .= (string) $blk['text'];
                }
            }
        } elseif (isset($data['choices'][0]['message']['content'])) {
            $text = (string) $data['choices'][0]['message']['content'];
        }

        if ($text === '') {
            $err = isset($data['error']['message']) ? (string) $data['error']['message'] : '模型未返回內容';
            return ['ok' => false, 'error' => $err];
        }
        return ['ok' => true, 'text' => $text];
    }

    /* ------------------------------------------------------------- 雜項 */

    private static function fail($errors)
    {
        return [
            'code' => 0,
            'dir' => '',
            'gate' => [],
            'errors' => is_array($errors) ? $errors : [$errors],
        ];
    }

    /**
     * @param string $dir
     * @return void
     */
    public static function rmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
