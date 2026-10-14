<?php
namespace app\common\view;

/**
 * 主題校驗閘。
 *
 * AI 產線與主題市場安裝共用的最後一道關卡：**沒過閘的主題不准寫進 template/**。
 *
 * 四道檢查，任何一道紅就整體失敗：
 *
 *   1. contract —— 契約靜態檢查（必要模板、行為鉤子、|raw 白名單、禁用構造）
 *                  由 ThemeContract::validate() 負責
 *   2. compile  —— 逐個模板真編譯一次。語法錯誤、寫錯的 extends/include、
 *                  以及被 TwigGuard 拒絕的構造都在這裡爆出來
 *   3. calls    —— 函式白名單。模板只准呼叫 TwigMaccms 註冊過的函式，
 *                  叫了沒註冊的（無論筆誤還是刻意）一律攔下
 *   4. smoke    —— 三類頁面各渲染一次，證明拿真實資料也能跑完
 *
 * 為什麼 compile 和 contract 都要：contract 是字串掃描（快、能給出可讀原因），
 * compile 是真跑編譯器（慢、但能抓出掃描看不出來的結構性錯誤）。兩者互補。
 */
class ThemeGate
{
    /** 冒煙渲染的頁面與所需的最小上下文 */
    private static $smokePages = [
        'index/index' => [],
        'vod/type'    => ['obj' => ['type_id' => 1, 'type_name' => 'Smoke', 'type_pid' => 0, 'type_en' => 'smoke']],
        'vod/detail'  => ['obj' => [
            'vod_id' => 1, 'vod_name' => 'Smoke', 'vod_pic' => '', 'vod_remarks' => '',
            'vod_actor' => '', 'vod_director' => '', 'vod_content' => '', 'vod_blurb' => '',
            'vod_up' => 0, 'vod_down' => 0, 'type_id' => 1,
            'type' => ['type_id' => 1, 'type_name' => 'Smoke', 'type_en' => 'smoke'],
        ]],
    ];

    /**
     * 跑完整校驗閘。
     *
     * @param string $themeDir 主題目錄名（template/ 下一層）
     * @param array  $opts     ['skip_smoke'=>bool] 無 DB 環境時可跳過冒煙
     *                         ['mode'=>'strict'|'lenient'] 契約嚴格度，預設 lenient
     * @return array ['code'=>1|0, 'stages'=>[...], 'errors'=>[], 'warnings'=>[]]
     */
    public static function run($themeDir, array $opts = [])
    {
        $stages = [];
        $errors = [];
        $warnings = [];

        // base 讓 AI 產線先對 runtime/ 下的暫存目錄跑閘，過了才搬進 template/
        $base = empty($opts['base'])
            ? ROOT_PATH . 'template' . DIRECTORY_SEPARATOR
            : rtrim($opts['base'], '/\\') . DIRECTORY_SEPARATOR;

        $mode = isset($opts['mode']) ? $opts['mode'] : 'lenient';

        // ---- 1. 契約 ----
        $contract = ThemeContract::validate($themeDir, $base, ['mode' => $mode]);
        $stages['contract'] = $contract['code'] === 1 ? 'pass' : 'fail';
        $errors = array_merge($errors, self::prefix('contract', $contract['errors']));
        $warnings = array_merge($warnings, $contract['warnings']);

        // 契約沒過就不必往下跑了：後面幾道都依賴主題結構成立
        if ($contract['code'] !== 1) {
            return self::result($stages, $errors, $warnings);
        }
        // 非 Twig 主題契約會直接放行，此處也不繼續
        if (!isset($contract['stats']['engine']) || $contract['stats']['engine'] !== 'twig') {
            return self::result($stages, $errors, $warnings);
        }

        $themeRoot = $base . $themeDir . DIRECTORY_SEPARATOR;
        $htmlDir   = $themeRoot . 'html' . DIRECTORY_SEPARATOR;

        // 繼承主題的 view_path 可能不在 template/ 下（AI 產線的暫存區就是），
        // 引擎自己那條倒推主題名的推導對它無效，所以把父主題目錄明確傳進去。
        $parentHtml = ThemeContract::parentHtmlDir($themeRoot, $base);

        $engine = new Twig([
            'view_path'        => $htmlDir,
            'view_suffix'      => 'twig',
            'tpl_cache'        => false,
            'parent_view_path' => $parentHtml === null ? '' : $parentHtml,
        ]);

        // ---- 2. 逐個模板編譯 ----
        $names = self::templateNames($htmlDir);
        $compileErrors = [];
        foreach ($names as $name) {
            try {
                // 前綴 '/' 讓 parseName 走「根相對」分支，
                // 否則位於 html/ 根層的模板（如 shell.twig）會被自動補上控制器名
                $engine->compile('/' . $name);
            } catch (\Exception $e) {
                $compileErrors[] = $name . '.twig：' . $e->getMessage();
            } catch (\Throwable $e) {
                $compileErrors[] = $name . '.twig：' . $e->getMessage();
            }
        }
        $stages['compile'] = empty($compileErrors) ? 'pass' : 'fail';
        $errors = array_merge($errors, self::prefix('compile', $compileErrors));

        // ---- 3. 函式白名單 ----
        $callErrors = self::checkCalls($htmlDir, $themeRoot, $parentHtml);
        $stages['calls'] = empty($callErrors) ? 'pass' : 'fail';
        $errors = array_merge($errors, self::prefix('calls', $callErrors));

        // ---- 4. 渲染冒煙 ----
        if (!empty($opts['skip_smoke'])) {
            $stages['smoke'] = 'skip';
        } elseif (!empty($compileErrors)) {
            // 編譯都沒過，冒煙必然失敗，不重複報同一批錯
            $stages['smoke'] = 'skip';
        } else {
            $smokeErrors = [];
            foreach (self::$smokePages as $tpl => $data) {
                try {
                    $html = $engine->render('/' . $tpl, $data);
                    if (strlen($html) < 200) {
                        $smokeErrors[] = $tpl . '：渲染結果過短（' . strlen($html) . ' bytes），疑似空頁';
                    }
                    foreach (self::renderedHookIssues($tpl, $html) as $issue) {
                        $smokeErrors[] = $issue;
                    }
                } catch (\Exception $e) {
                    $smokeErrors[] = $tpl . '：' . $e->getMessage();
                } catch (\Throwable $e) {
                    $smokeErrors[] = $tpl . '：' . $e->getMessage();
                }
            }
            $stages['smoke'] = empty($smokeErrors) ? 'pass' : 'fail';
            $errors = array_merge($errors, self::prefix('smoke', $smokeErrors));
        }

        return self::result($stages, $errors, $warnings);
    }

    /**
     * 渲染後才驗得到的鉤子。
     *
     * 契約那一關是掃原始碼，對繼承主題有個結構性的漏洞：皮膚覆寫了
     * layout/base.twig 卻漏掉 c.runtime()／c.jsvar()，源碼掃描仍會在**父主題**的檔案裡
     * 找到這兩個鉤子而判定通過 —— 但實際每一頁都是壞的（收藏／評論／登入／懶載／多語
     * 全部失效，播放頁的播放器不啟動），而且頁面看起來完全正常。
     *
     * 這兩件事只有在渲染後的 HTML 上才驗得準，所以放在冒煙這一關。
     * 只挑「與資料無關、由版型自己輸出」的兩個：其餘鉤子（data-original 之類）
     * 要有內容才會出現，拿空庫去驗會誤判。
     *
     * @param string $tpl
     * @param string $html
     * @return array
     */
    private static function renderedHookIssues($tpl, $html)
    {
        $issues = [];
        // 片段類模板（ajax_*、layout/*）不是完整頁面，不該要求外殼鉤子
        if (strpos($html, '</body>') === false) {
            return $issues;
        }
        if (strpos($html, 'mac-theme-runtime.js') === false) {
            $issues[] = $tpl . '：渲染後沒有引入行為 runtime —— 收藏／評論／登入／懶載入／多語全部靜默失效'
                . '（版型的 </body> 前要有 {{ c.runtime(maccms.path) }}）';
        }
        if (!preg_match('/(?:var|window\.)\s*maccms\s*=/', $html)) {
            $issues[] = $tpl . '：渲染後沒有全域 maccms 物件 —— 播放頁的播放器不會啟動，視頻無法播放'
                . '（版型的 <head> 裡要有 {{ c.jsvar(maccms, csrf_token, user.user_id) }}）';
        }
        return $issues;
    }

    /**
     * 函式白名單檢查：模板裡出現的函式呼叫必須都在 TwigMaccms 註冊過。
     *
     * Twig 本身遇到未註冊函式會在編譯期就報錯，所以這一道嚴格說是冗餘的 ——
     * 但它給出的訊息是「叫了哪個沒註冊的函式」，比 Twig 的通用報錯好讀得多，
     * 而且能一次列出全部而不是停在第一個。對 AI 產線的回饋迴圈很重要。
     *
     * @param string      $htmlDir
     * @param string      $themeRoot  主題根目錄（結尾帶分隔符），用來讀主題自帶的元件庫
     * @param string|null $parentHtml 父主題的 html/，繼承主題會用到父主題定義的 macro
     * @return array
     */
    private static function checkCalls($htmlDir, $themeRoot = null, $parentHtml = null)
    {
        $registered = self::registeredFunctions($htmlDir, $parentHtml);
        $errors = [];

        foreach (self::twigFiles($htmlDir) as $file) {
            $src = file_get_contents($file);
            $src = preg_replace('/\{#.*?#\}/s', '', $src); // 去掉 Twig 註解

            // 只在 Twig 標籤內部找函式呼叫。
            // 不能整檔掃 —— 主題的 <style> 裡滿是 CSS 函式（var() / repeat() / minmax() /
            // translateX() …），整檔掃會把它們全部誤判成未註冊函式。
            $expr = '';
            if (preg_match_all('/\{\{(.*?)\}\}|\{%(.*?)%\}/s', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $one) {
                    $expr .= ' ' . (isset($one[2]) && $one[2] !== '' ? $one[2] : $one[1]);
                }
            }
            if ($expr === '') {
                continue;
            }
            // 去掉字串字面量，避免把字串裡的括號當成呼叫
            $expr = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/s', "''", $expr);
            // macro 呼叫寫作 c.name()，這裡只關心點號後面的名字，前綴不計
            $expr = preg_replace('/\b[a-z_][a-z0-9_]*\s*\.\s*(?=[a-z_])/i', '', $expr);

            if (!preg_match_all('/\b([a-z_][a-z0-9_]*)\s*\(/i', $expr, $m2)) {
                continue;
            }
            foreach (array_unique($m2[1]) as $fn) {
                if (isset($registered[$fn])) {
                    continue;
                }
                $errors[] = str_replace(ROOT_PATH, '', $file) . '：呼叫了未註冊的函式 ' . $fn . '()';
            }
        }
        return $errors;
    }

    /**
     * 允許出現在模板裡的呼叫名：TwigMaccms 註冊的函式 + Twig 內建 + macro 名。
     *
     * @param string|null $htmlDir    主題的 html/ 目錄，用來收主題自己定義的 macro
     * @param string|null $parentHtml  父主題的 html/，繼承來的 macro 同樣算已註冊
     * @return array name => true
     */
    private static function registeredFunctions($htmlDir = null, $parentHtml = null)
    {
        $ok = [];

        // 直接問擴充自己註冊了什麼，不要照著 $helpers / $dataMap 重建一份。
        // 重建版會漏掉不走那兩個表註冊的函式（mac_verify_on 就是），
        // 而症狀是「主題明明用了合法函式卻被判成未註冊」—— 誰新增一個
        // 特殊註冊的函式，這裡就得記得跟著改，遲早會忘。
        $ext = new TwigMaccms();
        foreach ($ext->getFunctions() as $fn) {
            $ok[$fn->getName()] = true;
        }

        // Twig 內建函式與過濾器／測試（都以 name( 形態出現）
        //
        // 刻意**不**放行的內建：
        //   template_from_string —— 從字串編譯模板，是模板注入的直接入口
        //   source               —— 讀取任意模板原始碼
        //   dump / include(函式形式) —— 除錯用途，不該出現在發布的主題裡
        // 這些在 Environment 裡本來就沒註冊（會在 compile 階段報錯），
        // 這裡不放行是為了讓 calls 階段給出可讀的原因，而不是丟一句通用編譯錯誤。
        $builtin = [
            'range', 'cycle', 'random', 'date', 'max', 'min',
            'attribute', 'block', 'parent', 'constant', 'country_names',
            'default', 'escape', 'e', 'join', 'split', 'trim', 'replace', 'format', 'number_format',
            'length', 'slice', 'merge', 'json_encode', 'keys', 'batch', 'column', 'filter', 'map',
            'reduce', 'first', 'last', 'nl2br', 'raw', 'striptags', 'title', 'upper', 'lower',
            'capitalize', 'abs', 'round', 'reverse', 'defined', 'divisible', 'empty', 'even',
            'iterable', 'odd', 'same', 'starts', 'ends', 'matches',
            // 帶參數的 Twig 測試寫成兩個字：`x is same as(y)`、`x is divisible by(3)`。
            // 第二個字後面直接接括號，掃描器會把它當成呼叫，所以要放行。
            'as', 'by',
            // 運算子後面接括號同理：`cur_mid == 2 or (aid >= 20 and aid < 30)`
            // 會被呼叫正則抓成 or(。這些是關鍵字，不是函式。
            'or', 'and', 'not', 'in', 'is', 'b-and', 'b-or', 'b-xor',
        ];
        foreach ($builtin as $fn) {
            $ok[$fn] = true;
        }

        // macro 名（以 c.xxx() 形態呼叫，正則只抓到 xxx）。
        //
        // 共用元件庫之外，**主題自己定義的 macro 也要收**，而且不能只看
        // html/lib/components.twig —— macro 放哪個檔是主題的自由，
        // twigspike2 就放在 html/_ui.twig。只認固定檔名會把主題自己的 macro
        // 全判成「呼叫了未註冊的函式」，這一階段就永遠不會綠。
        $libs = [ROOT_PATH . 'template/_shared/twig/components.twig'];
        if ($htmlDir !== null) {
            $libs = array_merge($libs, self::twigFiles($htmlDir));
        }
        // 繼承主題可以直接呼叫父主題定義的 macro（loader 會回落找到那個檔），
        // 不收進來的話「只覆寫 base」的皮膚會整批被判成呼叫未註冊函式。
        if ($parentHtml !== null && is_dir($parentHtml)) {
            $libs = array_merge($libs, self::twigFiles($parentHtml));
        }
        foreach ($libs as $components) {
            if (!is_file($components)) {
                continue;
            }
            if (preg_match_all('/\{%-?\s*macro\s+([a-z_][a-z0-9_]*)\s*\(/i', file_get_contents($components), $m)) {
                foreach ($m[1] as $name) {
                    $ok[$name] = true;
                }
            }
        }

        return $ok;
    }

    /**
     * @param string $htmlDir
     * @return array 模板名（不含副檔名，相對 html/）
     */
    private static function templateNames($htmlDir)
    {
        $out = [];
        $len = strlen($htmlDir);
        foreach (self::twigFiles($htmlDir) as $f) {
            $rel = substr($f, $len);
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            $out[] = substr($rel, 0, -5);
        }
        return $out;
    }

    /**
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

    private static function prefix($stage, array $errors)
    {
        $out = [];
        foreach ($errors as $e) {
            $out[] = '[' . $stage . '] ' . $e;
        }
        return $out;
    }

    private static function result(array $stages, array $errors, array $warnings)
    {
        return [
            'code' => empty($errors) ? 1 : 0,
            'stages' => $stages,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }
}
