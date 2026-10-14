<?php
namespace app\common\view;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Lexer;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\Token;

/**
 * Twig 模板原始碼安全掃描。
 *
 * 本專案釘用 Twig 2.12.5（最後一個支援 PHP 7.0 的版本，見 docs/twig-engine-feasibility.md §5）。
 * 該版本帶有若干 2.x 全線永無修補版的 advisory，其中兩條在「模板本身可能不完全可信」
 * （AI 生成 / 模板市場分發）的場景下必須由本層擋掉：
 *
 *   1. CVE-2026-46633（critical）—— `{% use %}` 的模板名參與編譯字串拼接，可造成
 *      PHP 程式碼注入。影響 >=2.0.0,<3.0.0（2.x 全線），上游只在 3.26.0 修復。
 *      本專案的主題不需要 horizontal reuse，直接整個標籤禁用。
 *
 *   2. CVE-2022-23614（high）—— `sort` filter 接受非閉包的 callable，等同讓模板
 *      呼叫任意 PHP 函式，繞過我們在 TwigMaccms 建立的函式白名單。
 *      上游在 2.14.11 修復。本專案禁用該 filter，排序一律在控制器/模型側完成。
 *      同一修補（2.14.11）連帶收緊了 `filter` / `map` / `reduce` —— 這三個在
 *      2.12.5 同樣把非閉包 callable 直接當函式呼叫（CoreExtension 的 twig_array_map
 *      做 `$arrow($v,$k)`，twig_array_filter / twig_array_reduce 透傳給
 *      array_filter / array_reduce），`{{ x|map('system') }}` 就是一次白名單繞過。
 *      本專案一併禁用，陣列轉換/過濾/歸約一律在控制器/模型側完成。
 *
 *   3. `{% sandbox %}` —— 2.12.5 的 sandbox 本身帶多條 bypass advisory，
 *      我們的隔離手段是「白名單註冊 + 本掃描」，不依賴 sandbox，故一併禁用以免誤用。
 *
 * 掃描掛在 loader 的 getSourceContext() 上，因此 extends / include / embed 鏈上
 * 每一個被載入的模板都會過一次，不會被間接引用繞開。
 *
 * ## 為什麼掃 token 而不是掃原始碼
 *
 * 本類早期用正則直接掃原始碼，並自行用 `/\{#.*?#\}/s` 把註解剝掉（註解在詞法階段
 * 就被丟棄、永不求值，不剝的話「在註解裡說明為什麼禁用某構造」的契約文件會誤傷自己）。
 *
 * 那個做法有一個致命前提：掃描器對「哪裡是註解」的判斷必須與 Twig 的詞法器完全一致。
 * 事實上不一致 —— Twig 先匹配 `{{`，所以 `{{ "{#" }}` 裡的 `{#` 只是字串字面量，
 * 不是註解起點；而純正則的剝除會把它當註解起點，一路吃到後面某個 `#}`，
 * 把中間整段惡意程式碼從**掃描目標**裡刪掉，Twig 卻照常執行它。實測
 * `{{ "{#" }}{{ {'路徑': '內容'}|map('file_put_contents') }}{{ "#}" }}` 可以穿過掃描
 * 並真的寫出檔案 —— 也就是任意 PHP 函式 + 任意實參。
 *
 * 這類歧義沒有「把正則寫得更嚴一點」的解：只要掃描器自己實作一套詞法，
 * 它與真正的詞法器之間就永遠有縫。所以改為直接用 Twig 自己的 Lexer 取 token 流再判：
 *
 *   - 註解在詞法階段就消失了，反向誤傷的問題自動解決，不必再剝註解；
 *   - `{% verbatim %}` 內容變成純 TEXT token，本來就不求值，也自動不誤判；
 *   - 判定對象從「長得像什麼的字串」變成「Twig 真的會求值的 filter / tag 名」。
 */
class TwigGuard
{
    /**
     * 禁用的標籤名（`{% xxx %}` 的 xxx）。鍵為標籤名，值為拒絕理由。
     */
    private static $denyTags = [
        'use'     => '{% use %} 已禁用：Twig 2.x 存在未修補的模板名 PHP 程式碼注入（CVE-2026-46633）',
        'sandbox' => '{% sandbox %} 已禁用：本專案以白名單註冊隔離，不使用 sandbox',
    ];

    /**
     * 禁用的 filter 名。涵蓋 `{{ x|map(...) }}` 與 `{% filter map(...) %}` 兩種用法 ——
     * 後者不帶 `|`，早期的正則掃不到。
     */
    private static $denyFilters = [
        'sort'   => '|sort filter 已禁用：Twig 2.12.5 允許非閉包 callable，可繞過函式白名單（CVE-2022-23614）',
        'filter' => '|filter / |map / |reduce filter 已禁用：Twig 2.12.5 允許非閉包 callable（如 |map(\'system\')），可繞過函式白名單（CVE-2022-23614 同批修補）；陣列處理請在控制器/模型側完成',
        'map'    => '|filter / |map / |reduce filter 已禁用：Twig 2.12.5 允許非閉包 callable（如 |map(\'system\')），可繞過函式白名單（CVE-2022-23614 同批修補）；陣列處理請在控制器/模型側完成',
        'reduce' => '|filter / |map / |reduce filter 已禁用：Twig 2.12.5 允許非閉包 callable（如 |map(\'system\')），可繞過函式白名單（CVE-2022-23614 同批修補）；陣列處理請在控制器/模型側完成',
    ];

    /**
     * 只 lint、不阻擋渲染的規則（filter 鏈形態，見 lintChainViolation()）。
     *
     * 與 $denyTags / $denyFilters 的分界：那些會讓模板取得**任意 PHP 執行能力**，
     * 也就是能逃出我們的白名單隔離，載入當下就必須攔死；本清單這些是**轉義風險**，
     * 危險但逃不出隔離。
     *
     * 為什麼不一起攔死：TwigGuard::scan() 掛在 loader 上，攔下去就是整頁空白。
     * 站長升級核心之後，原本能用的頁面會突然變白頁，而且是 HTTP 200 + 空 body，
     * 完全看不出原因 —— 用一次核心升級換掉站長的會員區，代價遠大於這條規則要防的風險。
     * 所以改為：主題契約閘（安裝／上架／AI 產線）報 error 擋住新主題帶進來，
     * 既有主題照常渲染，由 Phase F 逐一修掉。
     */
    const LINT_JSON_RAW =
        '|json_encode|raw 已禁用：json_encode 不轉義 /，字串內的 </script> 可逃逸出腳本區塊；'
        . '請改用 |e(\'js\') 包在引號內，或用 c.jsvar 輸出全域變數';

    /** @var Lexer|null */
    private static $lexer = null;

    /**
     * 掃描模板原始碼，命中禁用構造即拋 LoaderError（帶上模板名一併回報）。
     *
     * 只檢查禁用清單 —— 見 LINT_JSON_RAW 的說明：lint 級規則不在這裡攔，
     * 否則等於用一次升級把站長既有的頁面變成白頁。
     *
     * @param string $source 模板原始碼
     * @param string $name   模板名（僅用於錯誤訊息）
     * @return void
     * @throws LoaderError
     */
    public static function scan($source, $name)
    {
        if ($source === '' || $source === null) {
            return;
        }
        $hit = self::collect($source, $name, false);
        if (!empty($hit)) {
            throw new LoaderError(sprintf('模板 "%s" 被安全掃描拒絕 —— %s', $name, $hit[0]));
        }
    }

    /**
     * 供測試與主題校驗使用：回傳命中的理由清單（不拋例外）。
     *
     * 這裡把禁用清單與 lint 清單一起查 —— 契約閘是「主題能不能裝進來 / 能不能上架」
     * 的關卡，兩類問題都該擋在門外；已經裝在站上的主題則只有禁用清單會被 scan() 攔。
     *
     * @param string $source
     * @return array 命中的理由，未命中為空陣列
     */
    public static function violations($source)
    {
        if ($source === '' || $source === null) {
            return [];
        }
        return self::collect($source, 'source', true);
    }

    /**
     * 詞法分析後逐 token 判定。
     *
     * @param string $source
     * @param string $name     模板名（詞法錯誤訊息用）
     * @param bool   $withLint 是否一併查 lint 級規則
     * @return array 命中的理由（去重）
     */
    private static function collect($source, $name, $withLint)
    {
        $tokens = self::tokenize($source, $name);
        if ($tokens === null) {
            // 詞法分析失敗（模板本身語法就是壞的）。這時無法判定 Twig 會求值什麼，
            // 一律 fail-closed 走保守的原始碼掃描：寧可對一個本來就編不過的模板
            // 多報一條，也不要讓「先寫壞語法再修好」變成繞過掃描的手法。
            return self::rawFallback($source);
        }

        $hits = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            $type = $t->getType();
            $value = (string) $t->getValue();

            // `{% xxx %}`：標籤名是 BLOCK_START 之後的第一個 NAME
            if ($type === Token::BLOCK_START_TYPE && isset($tokens[$i + 1])
                && $tokens[$i + 1]->getType() === Token::NAME_TYPE) {
                $tag = (string) $tokens[$i + 1]->getValue();
                if (isset(self::$denyTags[$tag])) {
                    $hits[self::$denyTags[$tag]] = true;
                    continue;
                }
                // `{% filter map('system') %}` 與 `{% apply map('system') %}`：兩個標籤的鏈上
                // 同樣是真正的 filter 呼叫，而且不帶 `|`。掃到 BLOCK_END 為止的每個 NAME 都要查。
                // （apply 自 2.9 起是 filter 標籤的別名，漏掃它等於放棄第二條入口。）
                if ($tag === 'filter' || $tag === 'apply') {
                    for ($j = $i + 2; $j < $n; $j++) {
                        if ($tokens[$j]->getType() === Token::BLOCK_END_TYPE) {
                            break;
                        }
                        if ($tokens[$j]->getType() !== Token::NAME_TYPE) {
                            continue;
                        }
                        $f = (string) $tokens[$j]->getValue();
                        if (isset(self::$denyFilters[$f])) {
                            $hits[self::$denyFilters[$f]] = true;
                        }
                    }
                }
                continue;
            }

            // `x|map(...)`：filter 名是 `|` 之後的 NAME
            if ($type === Token::PUNCTUATION_TYPE && $value === '|' && isset($tokens[$i + 1])
                && $tokens[$i + 1]->getType() === Token::NAME_TYPE) {
                $f = (string) $tokens[$i + 1]->getValue();
                if (isset(self::$denyFilters[$f])) {
                    $hits[self::$denyFilters[$f]] = true;
                }
                if ($withLint && $f === 'json_encode' && self::followedByRaw($tokens, $i + 2)) {
                    $hits[self::LINT_JSON_RAW] = true;
                }
            }
        }

        return array_keys($hits);
    }

    /**
     * `|json_encode` 之後（跳過它自己的參數括號）是不是緊接著 `|raw`。
     *
     * @param array $tokens
     * @param int   $i 指向 json_encode 之後的第一個 token
     * @return bool
     */
    private static function followedByRaw(array $tokens, $i)
    {
        $n = count($tokens);
        // 跳過 `(...)` 參數，允許巢狀
        if ($i < $n && $tokens[$i]->getType() === Token::PUNCTUATION_TYPE
            && (string) $tokens[$i]->getValue() === '(') {
            $depth = 0;
            for (; $i < $n; $i++) {
                if ($tokens[$i]->getType() !== Token::PUNCTUATION_TYPE) {
                    continue;
                }
                $v = (string) $tokens[$i]->getValue();
                if ($v === '(') {
                    $depth++;
                } elseif ($v === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
            }
        }
        return $i + 1 < $n
            && $tokens[$i]->getType() === Token::PUNCTUATION_TYPE
            && (string) $tokens[$i]->getValue() === '|'
            && $tokens[$i + 1]->getType() === Token::NAME_TYPE
            && (string) $tokens[$i + 1]->getValue() === 'raw';
    }

    /**
     * 供契約層等靜態分析複用：回傳剝除 Twig 註解後的原始碼。
     *
     * 用 Lexer 取 token 流再按原序重建 —— 註解在詞法階段就消失，因此
     * `{{ "{#" }}` 這類「字串字面量長得像註解起點」的構造不會被誤吞
     * （正則 `/\{#.*?#\}/s` 剝除法正是敗在這裡，見類頭註釋）。
     *
     * 重建是「語義等價」而非逐字還原：Twig 2 的 START/END token value 是空串
     * （按類型補回定界符）、token 間的原始空白不保留、STRING value 是解碼後
     * 的內容（重新加引號）。詞形 token 之間補單空格防粘連；標點緊鄰形態
     * （`c.runtime(`、`x|raw`）原樣保留，契約鉤子正則依賴它們。行號/原始排版
     * 不在此層保真。詞法失敗（模板本身語法壞的）回傳原文 —— compile 階段自然會攔它。
     *
     * @param string $source
     * @param string $name
     * @return string
     */
    public static function stripComments($source, $name = 'source')
    {
        $source = (string) $source;
        if ($source === '') {
            return $source;
        }
        $tokens = self::tokenize($source, $name);
        if ($tokens === null) {
            return $source;
        }
        $delims = [
            Token::BLOCK_START_TYPE         => '{%',
            Token::VAR_START_TYPE           => '{{',
            Token::BLOCK_END_TYPE           => '%}',
            Token::VAR_END_TYPE             => '}}',
            Token::INTERPOLATION_START_TYPE => '#{',
            Token::INTERPOLATION_END_TYPE   => '}',
        ];
        // 只在相鄰的兩個「詞形」token（NAME/NUMBER/STRING）之間補空格，
        // 避免 `set a` 粘成 `seta`；標點緊鄰（`c.runtime(`、`x|raw`、`window.maccms`）
        // 原樣保留 —— 契約層的鉤子正則（`\.runtime\(` 之類）依賴這種緊鄰形態。
        $wordish = [Token::NAME_TYPE => true, Token::NUMBER_TYPE => true, Token::STRING_TYPE => true];
        $out = '';
        $prevWordish = false;
        foreach ($tokens as $t) {
            $type = $t->getType();
            if (isset($delims[$type])) {
                $out .= $delims[$type];
                $prevWordish = false;
                continue;
            }
            if (isset($wordish[$type])) {
                if ($prevWordish) {
                    $out .= ' ';
                }
                $out .= $type === Token::STRING_TYPE
                    ? self::quoteStringToken((string) $t->getValue())
                    : (string) $t->getValue();
                $prevWordish = true;
                continue;
            }
            $out .= (string) $t->getValue();
            $prevWordish = false;
        }
        return $out;
    }

    /**
     * STRING token 的 value 已被 Lexer 解碼（引號剝掉、轉義還原），
     * 重新加引號讓 `include ['"]…['"]` 這類下游正則能命中。
     */
    private static function quoteStringToken($value)
    {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    /**
     * 取 token 陣列；詞法分析失敗回 null。
     *
     * @param string $source
     * @param string $name
     * @return array|null Token[]
     */
    private static function tokenize($source, $name)
    {
        try {
            $stream = self::lexer()->tokenize(new Source((string) $source, (string) $name));
        } catch (SyntaxError $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }

        $tokens = [];
        while (!$stream->isEOF()) {
            $tokens[] = $stream->next();
        }
        return $tokens;
    }

    /**
     * 詞法分析失敗時的保守掃描：直接查原始碼，不剝註解（寧可誤報不可漏報）。
     *
     * @param string $source
     * @return array
     */
    private static function rawFallback($source)
    {
        $hits = [];
        foreach (self::$denyTags as $tag => $reason) {
            if (preg_match('/\{%-?\s*' . preg_quote($tag, '/') . '\b/i', $source)) {
                $hits[$reason] = true;
            }
        }
        foreach (self::$denyFilters as $f => $reason) {
            if (preg_match('/\|\s*' . preg_quote($f, '/') . '\b/i', $source)
                || preg_match('/\{%-?\s*filter\b[^%]*\b' . preg_quote($f, '/') . '\b/i', $source)
                || preg_match('/\{%-?\s*apply\b[^%]*\b' . preg_quote($f, '/') . '\b/i', $source)) {
                $hits[$reason] = true;
            }
        }
        return array_keys($hits);
    }

    /**
     * 共用 Lexer。Environment 只是 Lexer 的建構相依（取運算子表），
     * 不參與渲染，所以掛一個空的 ArrayLoader 即可。
     *
     * @return Lexer
     */
    private static function lexer()
    {
        if (self::$lexer === null) {
            Twig::registerAutoload();
            self::$lexer = new Lexer(new Environment(new ArrayLoader([])));
        }
        return self::$lexer;
    }
}
