<?php
namespace app\common\view;

/**
 * 主題契約校驗（Theme Spec v2）。
 *
 * 用途有二：
 *   1. 安裝／發布主題前的閘門 —— 缺鉤子的主題裝上去會「頁面正常但功能靜默失效」，
 *      那是最難排查的一類問題，必須在裝進去之前就攔下來。
 *   2. AI 產線的自動校驗 —— 產出的主題先過這一關再談渲染冒煙。
 *
 * 兩種嚴格度（$opts['mode']）：
 *   lenient（預設）—— 給本地開發與存量主題安裝用。允許主題自帶 runtime／元件庫副本，
 *                     T1 模板缺失只出 warning。
 *   strict         —— 給 AI 產線、主題市場上架、官方底座自身用。必須用 @shared 共用層，
 *                     T0 + T1 模板全齊。
 *
 * 為什麼要分兩級：收斂到 template/_shared/ 是規範的方向，但存量主題（twigspike/phub）
 * 已經各自複製了一份 runtime 與元件庫，一刀切成 error 會讓它們裝不上去。
 * lenient 讓它們繼續能跑，strict 定義「新主題應該長什麼樣」。
 *
 * 契約全文見 docs/theme-spec-v2.md。
 */
class ThemeContract
{
    /**
     * T0 核心模板：使用者正常瀏覽必經，缺了就是 500。兩種模式都是 error。
     *
     * 清單來源是 application/index/controller/*.php 裡真的會 fetch 到的模板名，
     * 不是憑印象列的。舊版契約只要求 3 個（index/index、vod/type、vod/detail），
     * 與現實差了一個數量級 —— 過了閘的主題照樣一點就 500。
     *
     * @var array
     */
    private static $templatesT0 = [
        'index/index',
        'vod/type', 'vod/show', 'vod/detail', 'vod/play', 'vod/down', 'vod/search',
        'art/index', 'art/type', 'art/show', 'art/detail', 'art/read',
        'actor/index', 'actor/type', 'actor/show', 'actor/detail',
        'manga/index', 'manga/type', 'manga/show', 'manga/detail', 'manga/play',
        'topic/index', 'topic/detail',
        'role/index', 'role/show', 'role/detail',
        'website/index', 'website/type', 'website/show', 'website/detail',
        'plot/index', 'plot/detail',
        'live/show', 'live/index', 'live/play',
        'map/index', 'label/rank', 'comment/index', 'gbook/index',
        'user/index', 'user/login', 'user/reg', 'user/info',
        'user/findpass', 'user/findpass_msg', 'user/favs', 'user/plays', 'user/plog',
        'public/jump', 'public/msg', 'public/404', 'public/403', 'public/500',
        'public/notempty', 'public/close', 'public/browser', 'public/offline',
    ];

    /**
     * T1 條件必要模板：站長開了對應功能才會走到，但多數功能預設就開著。
     * strict = error，lenient = warning。
     *
     * public/verify 特別值得一提：後台一開「列表頁／搜尋頁驗證碼」，
     * Base::check_show() 與 check_search() 就會 fetch 它 —— 缺了不是某個角落壞掉，
     * 是整個列表頁與搜尋頁直接 500。
     *
     * @var array
     */
    private static $templatesT1 = [
        'public/verify',
        'comment/ajax', 'gbook/ajax', 'gbook/report',
        'vod/copyright', 'vod/detail_pwd', 'vod/player', 'vod/player_pwd',
        'vod/plot', 'vod/last_vod', 'vod/search_hub',
        'art/detail_pwd',
        'manga/detail_pwd',
        'website/search', 'website/rss',
        'rss/index', 'rss/baidu', 'rss/google', 'rss/so', 'rss/sogou', 'rss/bing', 'rss/sm',
        'index/ai_chat',
        'user/buy', 'user/pay', 'user/payment_weixin',
        'user/upgrade', 'user/benefits', 'user/checkin', 'user/popedom',
        'user/notify', 'user/dynamics', 'user/downs',
        'user/mall', 'user/mall_orders', 'user/cash', 'user/reward',
        'user/orders', 'user/cards', 'user/invite', 'user/bind',
        'user/ajax_login', 'user/ajax_info', 'user/ajax_upgrade',
    ];

    /**
     * 行為鉤子。缺了不會報錯，但對應功能會靜默失效 —— 所以一律當 error 而非 warning。
     * 鍵是鉤子的辨識用正則，值是「缺了會壞掉什麼」。
     *
     * 每項是「字面 hook」或「保證輸出該 hook 的共用 macro 呼叫」——
     * 主題可以自己寫標記，也可以用 template/_shared/twig/components.twig 的元件，
     * 兩者都算數。
     *
     * 注意這只是**原始碼層的快速檢查**；真正的把關是 e2e 對**渲染後 HTML** 的斷言
     * （tests/regression/twig_theme_e2e.php），因為 macro 是否真的輸出 hook
     * 只有渲染出來才算數。
     *
     * @var array
     */
    private static $requiredHooks = [
        'data-lang|\.lang_switch\s*\(|\.fav_button\s*\(|\.paging\s*\('
            => '前端多語（strings_zh / strings_en）將完全不生效',
        'data-original|\.lazy_img\s*\(|\.vod_card\s*\('
            => '圖片懶載入不生效',
        'mac-theme-runtime\.js|\.runtime\s*\('
            => '行為 runtime 未引入，收藏／評論／登入／懶載入／多語全部失效',
        // player.js（核心資產）在 Init() 讀全域 maccms.path、在 Play() 用 $(...)，
        // 缺任一個就丟例外，接著的 document.write 不執行 —— 播放器連 DOM 都不出現。
        // 主題自己宣告 window.maccms 也算數，故兩種寫法都認。
        'window\.maccms\s*=|var\s+maccms\s*=|\.jsvar\s*\('
            => '未輸出全域 maccms 物件與 $，播放頁的播放器不會啟動（視頻無法播放）',
    ];

    /**
     * 建議鉤子：不影響主流程，缺了只是少一塊功能。
     *
     * 與 $requiredHooks 同樣的判定方式：鍵是正則，「字面 hook」或「保證輸出該 hook
     * 的共用 macro 呼叫」皆算數。只認字面字串的話，凡是走 template/_shared 元件庫的
     * 主題都會被誤報 —— 而元件化正是規範要推的方向，檢查器不能反過來罰它。
     *
     * @var array
     */
    private static $optionalHooks = [
        'mac_ulog|\.fav_button\s*\('              => '收藏按鈕',
        'digg_link|\.digg\s*\('                   => '頂／踩',
        'data-mac-comment-form|\.comment_form\s*\(' => '評論提交',
        'data-mac-login-form|\.login_form\s*\('   => '登入表單',
        'data-mac-lang|\.lang_switch\s*\('        => '中英切換入口',
    ];

    /**
     * 可以 |raw 輸出的欄位。
     *
     * 名單的判準：**站長／採集入庫的富文本欄位**，以及控制器組好的 HTML 片段。
     * 這些在 Think 引擎下本來就不轉義，Twig 要維持同樣輸出就必須 |raw。
     * 使用者投稿內容（評論、留言）不在名單內，必須保持轉義。
     *
     * @var array
     */
    private static $rawWhitelist = [
        // 各內容型別的正文，來源同一類（後台編輯器 / 採集入庫）
        'vod_content', 'art_content', 'manga_content',
        'actor_content', 'role_content', 'topic_content', 'website_content',
        'live_content',
        // 漫畫章節圖片列表，亦為入庫 HTML
        'manga_chapter_url',
        // 控制器組好的播放器腳本，見 All::label_vod_play()
        'player_data', 'player_js',
        'head.text', 'foot.text', '.code',
    ];

    /**
     * 呼叫後可以直接 |raw 的函式。
     *
     * 判準：這些函式在 TwigMaccms 就是以 is_safe=['html'] 註冊的（輸出本來就不轉義，
     * 加 |raw 只是多此一舉），或者職責就是「吃 HTML、吐 HTML」。
     * 對它們報 XSS 是純誤判 —— 真正的風險在餵給它們的資料，而那是入庫時的事。
     *
     * @var array
     */
    private static $rawSafeCalls = [
        'mac_filter_html', 'mac_url_create', 'mac_em_replace', 'mac_day',
        'mac_url_content_img',
    ];

    /**
     * 校驗一個主題目錄。
     *
     * @param string $themeDir 主題目錄名（template/ 下的一層），如 'twigspike'
     * @param string $baseDir  主題根目錄，預設 template/。AI 產線用它先校驗 runtime/ 下的暫存目錄
     * @param array  $opts     ['mode' => 'strict'|'lenient']，預設 lenient
     * @return array ['code'=>1|0, 'errors'=>[], 'warnings'=>[], 'stats'=>[]]
     */
    public static function validate($themeDir, $baseDir = null, array $opts = [])
    {
        $strict = isset($opts['mode']) && $opts['mode'] === 'strict';

        $errors = [];
        $warnings = [];
        $stats = ['templates' => 0, 'engine' => 'think', 'mode' => $strict ? 'strict' : 'lenient'];

        $themeDir = (string) $themeDir;
        if ($themeDir === '' || strpos($themeDir, '/') !== false || strpos($themeDir, '.') !== false) {
            return self::fail('非法的主題目錄名');
        }

        // baseDir 讓 AI 產線能在寫進 template/ 之前，先對 runtime/ 下的暫存目錄跑一遍校驗
        $base = $baseDir === null
            ? ROOT_PATH . 'template' . DIRECTORY_SEPARATOR
            : rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        $root = $base . $themeDir . DIRECTORY_SEPARATOR;
        if (!is_dir($root)) {
            return self::fail('主題目錄不存在：' . $themeDir);
        }

        // 只校驗 Twig 主題；taglib 主題不適用本契約
        $engine = self::engineOf($root);
        $stats['engine'] = $engine;
        if ($engine !== 'twig') {
            return [
                'code' => 1,
                'errors' => [],
                'warnings' => ['非 Twig 主題，Theme Spec v2 不適用，已跳過校驗'],
                'stats' => $stats,
            ];
        }

        $htmlDir = $root . 'html' . DIRECTORY_SEPARATOR;
        if (!is_dir($htmlDir)) {
            return self::fail('缺少模板根目錄 html/');
        }

        // 主題繼承：父主題提供的模板算數，子主題不必重複放一份
        $parentHtml = self::parentHtmlDir($root, $base);

        // ---- 必要模板 ----
        foreach (self::$templatesT0 as $tpl) {
            if (!self::hasTemplate($htmlDir, $parentHtml, $tpl)) {
                $errors[] = '缺少 T0 核心模板：html/' . $tpl . '.twig（使用者走得到，缺了就是 500）';
            }
        }
        foreach (self::$templatesT1 as $tpl) {
            if (self::hasTemplate($htmlDir, $parentHtml, $tpl)) {
                continue;
            }
            $msg = '缺少 T1 條件必要模板：html/' . $tpl . '.twig（站長開了對應功能就會 500）';
            if ($strict) {
                $errors[] = $msg;
            } else {
                $warnings[] = $msg;
            }
        }

        // ---- 掃描全部模板 ----
        $all = '';
        $files = self::twigFiles($htmlDir);
        $stats['templates'] = count($files);
        if (!$files) {
            return self::fail('html/ 下沒有任何 .twig 模板');
        }

        // 第一輪：收 @raw-slot 宣告。必須先跑完整個主題才能做呼叫端反查 ——
        // 元件宣告了什麼 slot，要等呼叫它的頁面被掃到時才用得上。
        $slotDecl = [];      // 相對路徑 => [slot 名]
        $sources  = [];      // 相對路徑 => 去掉註解後的原始碼
        foreach ($files as $file) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($htmlDir)));
            $raw = file_get_contents($file);

            $slots = self::rawSlotDecl($raw);
            if ($slots) {
                $slotDecl[$rel] = $slots;
            }

            // 剝掉 {# … #} 註解再檢查。註解不是程式碼：
            //   - 在註解裡示範反例（「別寫 xxx|raw」）不該被判成違規；
            //   - 反過來，只出現在註解裡的行為鉤子也不該算成已實作。
            // AI 產的主題註解量大，不剝的話這類誤判會很常見。
            // 剝法用 TwigGuard 的 Lexer（TWIG-CONTRACT-01）：正則剝除會被
            // `{{ "{#" }}` 這類字串字面量騙到，把後面的真實程式碼吞出掃描目標。
            $sources[$rel] = TwigGuard::stripComments($raw, $rel);
        }

        // 第二輪：安全掃描、鉤子收集、|raw 檢查
        foreach ($sources as $rel => $src) {
            $all .= "\n" . $src;

            foreach (TwigGuard::violations($src) as $v) {
                $errors[] = $rel . '：' . $v;
            }

            $declared = isset($slotDecl[$rel]) ? $slotDecl[$rel] : [];
            $res = self::rawViolations($src, $declared);

            foreach ($res['errors'] as $v) {
                $errors[] = $rel . '：' . $v;
            }
            // 沒宣告但看得出是插槽的：strict 要求補宣告，lenient 只提醒。
            // 降級不等於放行 —— 真正的風險（把使用者資料餵進插槽）由下面的
            // 呼叫端反查擋住，那一道在兩種模式下都是 error。
            foreach ($res['undeclared'] as $v) {
                if ($strict) {
                    $errors[] = $rel . '：' . $v;
                } else {
                    $warnings[] = $rel . '：' . $v;
                }
            }

            // 反查要涵蓋「宣告的」與「推斷出來的」插槽，否則沒宣告的元件等於沒人看管
            $slotDecl[$rel] = array_values(array_unique(array_merge($declared, $res['slots'])));
        }

        foreach ($sources as $rel => $src) {
            foreach (self::slotCallViolations($src, $rel, $slotDecl) as $v) {
                $errors[] = $rel . '：' . $v;
            }
        }

        // ---- 行為鉤子 ----
        // 有 parent 時要連父主題一起看：換皮主題常常只覆寫 layout/base + CSS，
        // 懶載入、收藏、評論這些鉤子全部由父主題的頁面提供。只掃自己的檔案，
        // 一套完全正確的皮膚會被判成「缺必要鉤子」—— 那正好是繼承要解決的事，
        // 檢查器不能反過來罰它。
        $hookSrc = $all;
        if ($parentHtml !== '') {
            foreach (self::twigFiles($parentHtml) as $pf) {
                $hookSrc .= "\n" . preg_replace('/\{#.*?#\}/s', '', file_get_contents($pf));
            }
        }

        foreach (self::$requiredHooks as $pattern => $why) {
            if (!preg_match('#' . $pattern . '#i', $hookSrc)) {
                $errors[] = '缺少必要行為鉤子 ' . str_replace('\\', '', $pattern) . ' —— ' . $why;
            }
        }
        foreach (self::$optionalHooks as $pattern => $what) {
            if (!preg_match('#' . $pattern . '#i', $hookSrc)) {
                $warnings[] = '未使用 ' . str_replace('\\', '', $pattern) . '，' . $what . ' 功能不可用';
            }
        }

        // ---- 共用層收斂 ----
        foreach (self::sharedLayerIssues($root, $all) as $issue) {
            if ($strict) {
                $errors[] = $issue;
            } else {
                $warnings[] = $issue;
            }
        }

        return [
            'code' => empty($errors) ? 1 : 0,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => $stats,
        ];
    }

    /**
     * 主題有沒有自帶共用層副本。
     *
     * 自帶副本的代價是「_shared 修的 bug 不會流到這套主題」，
     * 而 twigspike 與 phub 的 runtime 曾經逐字元相同 —— 分叉沒有理由，
     * 純粹是複製底座的副產物。strict 模式不接受這件事。
     *
     * @param string $root 主題根目錄（結尾帶分隔符）
     * @param string $all  全部模板原始碼
     * @return array
     */
    private static function sharedLayerIssues($root, $all)
    {
        $issues = [];

        if (is_file($root . 'asset' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'mac-theme-runtime.js')) {
            $issues[] = '主題自帶了 asset/js/mac-theme-runtime.js 副本 —— '
                . '共用層的修補不會流到這套主題，請改用 template/_shared/kit/shared/js/mac-theme-runtime.js';
        }

        if (is_file($root . 'html' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'components.twig')) {
            $issues[] = '主題自帶了 html/lib/components.twig 副本 —— '
                . '行為契約會與共用層漂移，請改用 {% import "@shared/components.twig" as c %}';
        }

        // c.runtime() 的第三個參數是 opt-out，傳了就代表改載主題自己的 runtime 副本
        if (preg_match('/\.runtime\s*\(\s*[^)]*,[^)]*,[^)]*\)/', $all)) {
            $issues[] = 'c.runtime() 傳了 tpl_path，會改載主題自帶的 runtime 副本；'
                . '收斂到共用層請只傳前兩個參數';
        }

        return $issues;
    }

    /**
     * 從模板原始碼的 Twig 註解裡讀 @raw-slot 宣告。
     *
     * 寫法（放在元件檔頭的註解裡）：
     *   {# @raw-slot: content, body_html #}
     *
     * 意思是「本檔的 content / body_html 兩個變數收的是已經是 HTML 的內容，
     * 允許 |raw 輸出」。之所以需要這個機制：元件化之後，白名單欄位會在
     * {% include ... with {content: obj.actor_content} %} 的邊界被改名，
     * 純粹比對欄位名的白名單完全追不到，於是所有元件都會被誤判成 XSS。
     *
     * 宣告不等於放行一切 —— 呼叫端傳進來的實參仍要過 slotCallViolations() 的反查。
     *
     * @param string $raw 未剝註解的原始碼
     * @return array slot 名
     */
    private static function rawSlotDecl($raw)
    {
        $slots = [];
        if (preg_match_all('/@raw-slot\s*:\s*([A-Za-z0-9_.,\s]+)/', $raw, $m)) {
            foreach ($m[1] as $line) {
                foreach (preg_split('/[,\s]+/', trim($line)) as $name) {
                    if ($name !== '') {
                        $slots[$name] = true;
                    }
                }
            }
        }
        return array_keys($slots);
    }

    /**
     * 檢查一個檔案裡的 |raw 用法。
     *
     * 分成三種結果，因為它們的可稽核程度差很多：
     *   provable  —— 白名單欄位或 raw-safe 函式呼叫。安全，不出聲。
     *   declared  —— 檔頭 {# @raw-slot: x #} 宣告過的插槽。放行，改由呼叫端反查把關。
     *   inferred  —— 看得出是插槽（裸變數，來自 include 的 with 或 macro 參數），
     *                但沒宣告。仍納入反查，另外提醒補宣告。
     *   其他      —— 一律 error。
     *
     * @param string $src      已剝註解的原始碼
     * @param array  $declared 本檔宣告的 @raw-slot 名單
     * @return array ['errors'=>[], 'undeclared'=>[], 'slots'=>[]]
     */
    private static function rawViolations($src, array $declared)
    {
        $errors = [];
        $undeclared = [];
        $slots = [];

        $setMap = self::localSets($src);

        foreach (self::rawHeads($src) as $head) {
            if ($head === '') {
                continue;
            }

            // 1. 白名單欄位（含 obj.vod_content 這種帶前綴的寫法）。
            // 用詞界匹配而非裸 strpos：否則 evil_vod_content / vod_contentxyz 這類
            // 「包含白名單子串」的表達式會被誤放行。
            foreach (self::$rawWhitelist as $allowed) {
                if (preg_match('/(^|[^a-z0-9_])' . preg_quote($allowed, '/') . '([^a-z0-9_]|$)/i', $head)) {
                    continue 2;
                }
            }

            // 2. raw-safe 函式呼叫
            if (preg_match('/^([a-z_][a-z0-9_]*)\s*\(/i', $head, $fm)
                && in_array($fm[1], self::$rawSafeCalls, true)) {
                continue;
            }

            // 3. 檔頭直接宣告過這個表達式（含 o.up_icon 這種帶點號的 macro 選項）
            $chain = trim(preg_replace('/\|.*$/s', '', $head));
            if (in_array($chain, $declared, true)) {
                $slots[] = $chain;
                continue;
            }

            // 4. 插槽：裸變數名，或經本檔的 {% set %} 一路追回去仍是裸變數名。
            //    追 set 是必要的 —— 元件普遍寫成
            //      {% set _cta_attrs = cta_attrs|default('...') %} … {{ _cta_attrs|raw }}
            //    不追就只看到一個本地變數，判不出它其實是外面傳進來的插槽。
            $bare = trim(preg_replace('/\|.*$/s', '', $head));
            $root = self::resolveSetRoot($bare, $setMap);

            if ($root !== null) {
                $slots[] = $bare;
                if ($root !== $bare) {
                    $slots[] = $root;
                }
                if (in_array($bare, $declared, true) || in_array($root, $declared, true)) {
                    continue;
                }
                $undeclared[] = '未宣告的 HTML 插槽 ' . $head
                    . ' 以 |raw 輸出，請在檔頭加 {# @raw-slot: ' . $root . ' #} 表明這是刻意的';
                continue;
            }

            $errors[] = '對白名單以外的欄位使用了 |raw（XSS 風險）：' . $head;
        }

        return [
            'errors' => $errors,
            'undeclared' => $undeclared,
            'slots' => array_values(array_unique($slots)),
        ];
    }

    /**
     * 收集本檔的 {% set X = expr %}。
     *
     * @param string $src
     * @return array name => expr
     */
    private static function localSets($src)
    {
        $out = [];
        if (preg_match_all('/\{%-?\s*set\s+([a-z_][a-z0-9_]*)\s*=\s*(.+?)\s*-?%\}/is', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $one) {
                $out[$one[1]] = $one[2];
            }
        }
        return $out;
    }

    /**
     * 把一個變數名往回追到「不是本檔 set 出來的」根名。
     * 根名若不是合法識別字（例如追到的是字面量或運算式）就回 null，代表判不出來源。
     *
     * @param string $name
     * @param array  $setMap
     * @return string|null
     */
    private static function resolveSetRoot($name, array $setMap)
    {
        if ($name === '' || !preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            return null;
        }
        $seen = [];
        $cur = $name;
        // 最多三跳。再深就不是「換個名字」而是真的在組資料，追下去只會失真。
        for ($i = 0; $i < 3; $i++) {
            if (!isset($setMap[$cur])) {
                return $cur;      // 不是本檔 set 的 → 來自 include 的 with 或 macro 參數
            }
            if (isset($seen[$cur])) {
                return null;      // 循環引用，判不出來
            }
            $seen[$cur] = true;
            $next = trim(preg_replace('/\|.*$/s', '', $setMap[$cur]));
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $next)) {
                return null;      // set 的右側不是單純換名（字面量／運算式／呼叫）
            }
            $cur = $next;
        }
        return null;
    }

    /**
     * 呼叫端反查：把什麼東西塞進了別人宣告的 HTML slot。
     *
     * 沒有這一道，@raw-slot 就是個後門 —— 元件宣告 content 是 HTML slot 之後，
     * 任何頁面都能把使用者投稿的評論內容塞進去，繞過轉義。
     *
     * @param string $src      已剝註解的原始碼
     * @param string $selfRel  本檔相對路徑（用來解析相對的 include 路徑）
     * @param array  $slotDecl 全主題的 slot 宣告表
     * @return array
     */
    private static function slotCallViolations($src, $selfRel, array $slotDecl)
    {
        $out = [];
        if (!$slotDecl) {
            return $out;
        }

        // {% set x %}…{% endset %} 是「本檔自己拼出來的標記」，作者就是主題本人，
        // 與使用者資料是兩回事。元件化之後這是最常見的傳法（把一整塊 <dl> 傳給
        // dt_header 當 body_html），不認得它會把正常寫法全判成 XSS。
        $captures = [];
        if (preg_match_all('/\{%-?\s*set\s+([a-z_][a-z0-9_]*)\s*-?%\}/i', $src, $cm)) {
            $captures = array_unique($cm[1]);
        }
        // 本檔宣告過的名稱，代表作者已經聲明「這裡刻意輸出 HTML」
        $selfDeclared = isset($slotDecl[$selfRel]) ? $slotDecl[$selfRel] : [];

        // {% include "components/x.twig" with { a: expr, b: expr } %}
        if (!preg_match_all('/\{%-?\s*include\s+[\'"]([^\'"]+)[\'"]\s+with\s*\{(.*?)\}/s', $src, $m, PREG_SET_ORDER)) {
            return $out;
        }

        foreach ($m as $one) {
            $target = ltrim($one[1], '/');
            if (!isset($slotDecl[$target])) {
                continue;
            }
            $slots = $slotDecl[$target];

            foreach (self::mapEntries($one[2]) as $key => $expr) {
                if (!in_array($key, $slots, true)) {
                    continue;
                }
                $head = trim(preg_replace('/\|.*$/s', '', $expr));
                if ($head === '' || $head === 'null') {
                    continue;
                }
                // 傳字面量（''、'<hr>'）算主題自己寫死的標記，不是使用者資料
                if (preg_match('/^[\'"]/', $head)) {
                    continue;
                }
                if (in_array($head, $captures, true) || in_array($head, $selfDeclared, true)) {
                    continue;
                }
                if (preg_match('/^([a-z_][a-z0-9_]*)\s*\(/i', $head, $fm)
                    && in_array($fm[1], self::$rawSafeCalls, true)) {
                    continue;
                }
                foreach (self::$rawWhitelist as $allowed) {
                    if (preg_match('/(^|[^a-z0-9_])' . preg_quote($allowed, '/') . '([^a-z0-9_]|$)/i', $head)) {
                        continue 2;
                    }
                }
                $out[] = '把非白名單資料傳進了 ' . $target . ' 的 HTML 插槽 ' . $key
                    . '（值：' . $head . '），該內容會以 |raw 輸出，有 XSS 風險'
                    . '（確定是自家標記就在本檔加 {# @raw-slot: ' . $head . ' #}）';
            }
        }
        return $out;
    }

    /**
     * 把 `a: expr, b: expr` 這種 Twig map 字面量拆成鍵值對。
     * 只在頂層逗號切分，括號／中括號／大括號／引號內的逗號不算分隔。
     *
     * @param string $body
     * @return array key => expr
     */
    private static function mapEntries($body)
    {
        $out = [];
        $depth = 0;
        $quote = null;
        $buf = '';
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];

            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === $quote && ($i === 0 || $body[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '\'' || $ch === '"') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
            }
            if ($ch === ',' && $depth === 0) {
                self::pushEntry($out, $buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        self::pushEntry($out, $buf);

        return $out;
    }

    /**
     * @param array  $out
     * @param string $chunk
     * @return void
     */
    private static function pushEntry(array &$out, $chunk)
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return;
        }
        $pos = strpos($chunk, ':');
        if ($pos === false) {
            return;
        }
        $key = trim(substr($chunk, 0, $pos), " \t\n\r'\"");
        $val = trim(substr($chunk, $pos + 1));
        if ($key !== '') {
            $out[$key] = $val;
        }
    }

    /**
     * 找出所有 |raw 的「來源表達式」。
     *
     * 舊版是 `/([A-Za-z0-9_.\[\]'"]+)\s*\|\s*raw\b/`，抓的是濾鏡鏈**最後一個名字**，
     * 所以 `mac_url('user/login')|json_encode|raw` 會被判成有個叫 json_encode 的欄位。
     * 這裡改成往左走完整條濾鏡鏈，取鏈首的表達式。
     *
     * @param string $src 已剝註解的原始碼
     * @return array 表達式字串
     */
    private static function rawHeads($src)
    {
        $heads = [];
        if (!preg_match_all('/\{\{(.*?)\}\}|\{%(.*?)%\}/s', $src, $tags, PREG_SET_ORDER)) {
            return $heads;
        }

        foreach ($tags as $tag) {
            $expr = (isset($tag[2]) && $tag[2] !== '') ? $tag[2] : $tag[1];
            if (strpos($expr, '|') === false) {
                continue;
            }
            foreach (self::rawHeadsInExpr($expr) as $h) {
                $heads[] = $h;
            }
        }
        return $heads;
    }

    /**
     * 在單一個 Twig 標籤內部找 |raw，並回推每一個的鏈首表達式。
     *
     * 每個括號層各自記帳（$stack）。這件事非做不可，否則
     * `mac_day(cm.comment_time)|raw` 會被判成鏈首是 `cm.comment_time)` ——
     * 因為左括號被誤當成表達式起點，而整個函式呼叫其實是同一個表達式單元。
     * 有了分層記帳，`f(x|raw)` 這種巢狀濾鏡鏈也一併能檢查到。
     *
     * @param string $expr
     * @return array
     */
    private static function rawHeadsInExpr($expr)
    {
        $out = [];
        $len = strlen($expr);
        $quote = null;
        // 每層一個 frame：bars = 該層的 | 位置，seps = 該層的表達式分隔點
        $stack = [['bars' => [], 'seps' => [-1]]];

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];
            $top = count($stack) - 1;

            if ($quote !== null) {
                if ($ch === $quote && ($i === 0 || $expr[$i - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === '\'' || $ch === '"') {
                $quote = $ch;
                continue;
            }
            if ($ch === '(' || $ch === '[' || $ch === '{') {
                // 新的一層從括號本身之後開始算表達式
                $stack[] = ['bars' => [], 'seps' => [$i]];
                continue;
            }
            if ($ch === ')' || $ch === ']' || $ch === '}') {
                if ($top > 0) {
                    self::collectRawHeads($expr, $stack[$top], $out);
                    array_pop($stack);
                }
                continue;
            }
            if ($ch === '|') {
                $stack[$top]['bars'][] = $i;
            } elseif ($ch === ',' || $ch === ':' || $ch === '?' || $ch === '=') {
                $stack[$top]['seps'][] = $i;
            }
        }

        // 最外層（以及任何未閉合的層）收尾
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            self::collectRawHeads($expr, $stack[$i], $out);
        }

        return $out;
    }

    /**
     * 從單一括號層的記帳裡取出所有 |raw 的鏈首。
     *
     * @param string $expr
     * @param array  $frame ['bars'=>[], 'seps'=>[]]
     * @param array  $out
     * @return void
     */
    private static function collectRawHeads($expr, array $frame, array &$out)
    {
        foreach ($frame['bars'] as $pos) {
            if (!preg_match('/^\|\s*raw\b/', substr($expr, $pos))) {
                continue;
            }
            // 鏈首起點：這個 | 之前、同一層裡最後一個分隔點
            $start = -1;
            foreach ($frame['seps'] as $s) {
                if ($s < $pos && $s > $start) {
                    $start = $s;
                }
            }
            // 鏈首終點：起點之後、同一層裡第一個 |
            $end = $pos;
            foreach ($frame['bars'] as $b) {
                if ($b > $start && $b < $end) {
                    $end = $b;
                }
            }
            $head = trim(substr($expr, $start + 1, $end - $start - 1));
            $head = trim($head, " \t\n\r-~");
            if ($head !== '') {
                $out[] = $head;
            }
        }
    }

    /**
     * 模板存在性檢查，父主題也算數。
     *
     * @param string      $htmlDir    子主題 html/
     * @param string|null $parentHtml 父主題 html/，無繼承時為 null
     * @param string      $tpl        模板名（不含副檔名）
     * @return bool
     */
    private static function hasTemplate($htmlDir, $parentHtml, $tpl)
    {
        $rel = str_replace('/', DIRECTORY_SEPARATOR, $tpl) . '.twig';
        if (is_file($htmlDir . $rel)) {
            return true;
        }
        return $parentHtml !== null && is_file($parentHtml . $rel);
    }

    /**
     * 解析主題繼承，回傳父主題的 html/ 目錄。
     * 與 Init::themeParent() 同一套語義，但接受任意根目錄，
     * 以便對尚未搬進 template/ 的暫存主題也能校驗。
     *
     * 公開給 ThemeGate 用：暫存主題（AI 產線）的 view_path 不在 template/ 下，
     * Twig::parentViewPath() 那條「從路徑倒推主題名」的推導對它無效，
     * 必須由閘把父主題目錄明確傳進引擎。
     *
     * @param string $root 子主題根目錄（結尾帶分隔符）
     * @param string $base 主題容器目錄（結尾帶分隔符）
     * @return string|null
     */
    public static function parentHtmlDir($root, $base)
    {
        $file = $root . 'info.ini';
        if (!is_file($file)) {
            return null;
        }
        $ini = @parse_ini_file($file, false, INI_SCANNER_RAW);
        if (!is_array($ini) || empty($ini['parent'])) {
            return null;
        }
        $parent = trim((string) $ini['parent']);
        if ($parent === '' || strpos($parent, '/') !== false
            || strpos($parent, '\\') !== false || strpos($parent, '.') !== false) {
            return null;
        }
        // 父主題一律在正式的 template/ 下找。暫存主題（AI 產線的 runtime/theme_stage/）
        // 不會、也不該自帶一份底座副本。
        $dir = ROOT_PATH . 'template' . DIRECTORY_SEPARATOR . $parent . DIRECTORY_SEPARATOR
             . 'html' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            $dir = $base . $parent . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR;
        }
        return is_dir($dir) ? $dir : null;
    }

    /**
     * 讀主題根目錄的 info.ini 判斷引擎。
     * 與 Init::themeEngine() 同一套語義，但接受任意根目錄，
     * 以便對尚未搬進 template/ 的暫存主題也能校驗。
     *
     * @param string $root 主題根目錄（結尾帶分隔符）
     * @return string 'twig' | 'think'
     */
    private static function engineOf($root)
    {
        $file = $root . 'info.ini';
        if (!is_file($file)) {
            return 'think';
        }
        $ini = @parse_ini_file($file, false, INI_SCANNER_RAW);
        if (is_array($ini) && isset($ini['engine'])
            && strtolower(trim((string) $ini['engine'])) === 'twig') {
            return 'twig';
        }
        return 'think';
    }

    /**
     * @param string $msg
     * @return array
     */
    private static function fail($msg)
    {
        return ['code' => 0, 'errors' => [$msg], 'warnings' => [], 'stats' => []];
    }

    /**
     * 遞迴收集 .twig 檔。
     * @param string $dir
     * @return array
     */
    private static function twigFiles($dir)
    {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && substr($f->getFilename(), -5) === '.twig') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);
        return $out;
    }
}
