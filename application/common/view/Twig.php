<?php
namespace app\common\view;

use think\App;
use think\Request;
use think\Loader;
use think\exception\TemplateNotFoundException;

/**
 * ThinkPHP 5 的 Twig 視圖驅動。
 *
 * 掛載方式見 application/common/behavior/Init.php：主題在自己的 info.ini 裡宣告
 * `engine = twig`，Init 才把 template.type 指向本類。沒有宣告的主題走原本的
 * think\view\driver\Think，因此存量 taglib 模板完全不受影響（雙引擎並存）。
 *
 * TP5 的 View::fetch() 會 ob_start() 之後呼叫本類的 fetch()，並期望驅動直接 echo，
 * 所以這裡輸出而不是回傳（與 think\view\driver\Think 一致）。
 *
 * 依賴：vendor/twig/twig（2.12.5）。該套件不在 composer 的 autoload 映射中
 * （本倉庫 vendor 與 composer.json 已長期不同步，且無 composer.lock，
 * 跑 composer 會移除既有套件），故在此自行註冊 PSR-4。
 */
class Twig
{
    /** @var \Twig\Environment|null */
    private $twig;

    /** @var \think\view\driver\Think|null 非主題模板（字串模板、後台 view_new、插件視圖）的委派對象 */
    private $thinkEngine;

    /**
     * @var bool 靜態生成逃生口，見 setThemeRender()
     */
    private static $themeRender = false;

    /**
     * 允許在 admin 入口下渲染前台主題的 Twig 模板。
     *
     * 平時 shouldDelegate() 對 ENTRANCE=admin 一律委派回 think —— 後台
     * view_new/*.html 是 taglib 模板，交給 Twig loader 會整個後台白屏
     * （issue78 的教訓）。但靜態生成（admin/Make）本來就跑在後台入口下，
     * 渲染的卻是前台主題模板，需要一個窄的、由呼叫端顯式開關的例外。
     *
     * 只鬆綁「入口」這一條；跨模組 `@`、admin/view_new 的 view_path、
     * 已解析成 .html 絕對路徑這三條委派規則都還在，後台仍然安全。
     *
     * @param bool $on
     * @return bool 開關的前值，供呼叫端還原（務必用 try/finally）
     */
    public static function setThemeRender($on)
    {
        $prev = self::$themeRender;
        self::$themeRender = (bool) $on;
        return $prev;
    }

    /** @var array */
    protected $config = [
        'view_path'   => '',
        'view_suffix' => 'twig',
        'view_depr'   => DS,
        'tpl_cache'   => true,
        'auto_rule'   => 1,
        // 編譯快取根目錄。實際使用的是其下帶隨機後綴的子目錄，見 cacheDir()
        'twig_cache_root'      => '',
        'twig_strict_variables' => false,
        'twig_autoescape'       => 'html',
        'tpl_replace_string'    => [],
    ];

    public function __construct($config = [])
    {
        self::registerAutoload();
        $this->config = array_merge($this->config, $config);

        if (empty($this->config['twig_cache_root'])) {
            $this->config['twig_cache_root'] = RUNTIME_PATH . 'twig' . DS;
        }
    }

    /**
     * 為 vendor/twig/twig 註冊 PSR-4 自動載入（只註冊一次）。
     */
    public static function registerAutoload()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $base = ROOT_PATH . 'vendor' . DS . 'twig' . DS . 'twig' . DS . 'src' . DS;
        spl_autoload_register(function ($class) use ($base) {
            if (strncmp($class, 'Twig\\', 5) !== 0) {
                return;
            }
            $rel = str_replace('\\', DS, substr($class, 5));
            $file = $base . $rel . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    /**
     * Twig 是否可用（vendor 是否已就位）。Init 用它做降級判斷。
     * @return bool
     */
    public static function available()
    {
        return is_file(ROOT_PATH . 'vendor/twig/twig/src/Environment.php');
    }

    /**
     * 編譯快取目錄。加每站隨機後綴，降低被直接猜到路徑的可能；
     * 同時寫入 .htaccess（Apache）。nginx 需在站點配置 deny，見安裝文檔。
     *
     * @return string
     */
    private function cacheDir()
    {
        $root = rtrim($this->config['twig_cache_root'], '/\\') . DS;
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $htaccess = $root . '.htaccess';
        if (!is_file($htaccess)) {
            // Apache 2.2 與 2.4 兩種語法並存，避免其中一種造成 500
            file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }

        $saltFile = $root . '.salt';
        if (is_file($saltFile)) {
            $salt = trim(file_get_contents($saltFile));
        } else {
            $salt = '';
        }
        if (!preg_match('/^[a-f0-9]{16}$/', $salt)) {
            $salt = bin2hex(random_bytes(8));
            file_put_contents($saltFile, $salt);
        }

        $dir = $root . 'c_' . $salt . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * 由子主題的 view_path 推出父主題的模板目錄。
     *
     * view_path 的形狀是 template/<主題>/<html_dir>/，所以主題名取倒數第二段，
     * html_dir 取最後一段 —— 手機站的 html_dir 可能不叫 html，父主題要用同一個
     * 目錄名才對得上（PC 找 parent/html/，手機找 parent/<mob_html_dir>/）。
     *
     * @param string $viewPath
     * @return string|null 父主題模板目錄（結尾不帶分隔符），無繼承回傳 null
     */
    private function parentViewPath($viewPath)
    {
        $parts = preg_split('#[\\\\/]+#', trim(str_replace('\\', '/', $viewPath), '/'));
        $count = count($parts);
        if ($count < 2) {
            return null;
        }
        $htmlDir = $parts[$count - 1];
        $theme   = $parts[$count - 2];

        $parent = \app\common\behavior\Init::themeParent($theme);
        if ($parent === '') {
            return null;
        }

        $dir = ROOT_PATH . 'template' . DS . $parent . DS . $htmlDir;
        if (!is_dir($dir)) {
            // 父主題沒有同名的 html_dir（例如子主題有手機版、父主題沒有），
            // 退回父主題的 html/，總比整頁 500 好。
            $dir = ROOT_PATH . 'template' . DS . $parent . DS . 'html';
        }
        return is_dir($dir) ? rtrim($dir, '/\\') : null;
    }

    /**
     * 惰性建立 Twig Environment。
     * @return \Twig\Environment
     */
    private function twig()
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $viewPath = $this->config['view_path'];
        if ($viewPath === '' || !is_dir($viewPath)) {
            throw new TemplateNotFoundException('twig view_path not exists:' . $viewPath, $viewPath);
        }

        $loader = new TwigLoader([rtrim($viewPath, '/\\')]);

        // 主題繼承：父主題的 html/ 掛成**回落**搜尋路徑。
        //
        // Twig 的 FilesystemLoader 按 path 順序找檔，先命中先用，所以子主題放了
        // 同名檔就自然覆蓋父主題那份，沒放就用父主題的。這正是「只放要改的檔」
        // 所需要的全部機制 —— 不必新增任何模板語法。
        //
        // 資產（CSS/JS/圖）不走這條路：那是 web server 直接服務的 HTTP 路徑，
        // 沒有回落可言。子主題要用父主題的圖，得用 mac_theme_asset() 取 URL。
        //
        // parent_view_path 是給 AI 產線用的旁路：暫存主題放在 runtime/theme_stage/ 下，
        // parentViewPath() 那條「從 template/<主題>/<html_dir>/ 倒推主題名」的推導對它無效，
        // 所以由 ThemeGate 讀完暫存區的 info.ini 之後直接把父主題目錄傳進來。
        $parentHtml = isset($this->config['parent_view_path']) && $this->config['parent_view_path'] !== ''
            ? rtrim($this->config['parent_view_path'], '/\\')
            : $this->parentViewPath($viewPath);
        if ($parentHtml !== null && is_dir($parentHtml)) {
            $loader->addPath($parentHtml);
            $loader->allow([$parentHtml]);
        }

        // Page Builder 组件同步在主题根 page-builder-components/，不在 html/ 下。
        $themeRoot = dirname(rtrim($viewPath, '/\\'));
        if ($themeRoot !== '' && is_dir($themeRoot)) {
            $loader->addPath($themeRoot);
            $loader->allow([$themeRoot]);
        }

        // 共用元件庫：主題用 {% import "@shared/components.twig" as c %} 取用。
        // 掛成命名空間而不是加進主搜尋路徑，避免與主題自己的檔名衝突；
        // 目錄同時登記進 loader 的容器化允許清單，否則會被路徑斷言擋掉。
        $shared = ROOT_PATH . 'template' . DS . '_shared' . DS . 'twig';
        if (is_dir($shared)) {
            $loader->addPath($shared, 'shared');
            $loader->allow([$shared]);
        }

        $options = [
            'debug'            => App::$debug,
            'charset'          => 'UTF-8',
            'strict_variables' => (bool) $this->config['twig_strict_variables'],
            'autoescape'       => $this->config['twig_autoescape'],
            'cache'            => $this->config['tpl_cache'] ? $this->cacheDir() : false,
            'auto_reload'      => true,
        ];

        $env = new \Twig\Environment($loader, $options);
        $env->addExtension(new TwigMaccms());
        if (App::$debug) {
            $env->addExtension(new \Twig\Extension\DebugExtension());
        }

        $this->twig = $env;
        return $this->twig;
    }

    /**
     * 惰性建立委派用的 think 驅動。
     *
     * `template.type` 是全進程唯一的設定，但本驅動只對「當前主題目錄」有效，
     * 所以凡是不屬於主題的模板都得交還給原引擎，否則換一次前台主題就會把
     * 後台 / 插件 / 站長訊息模板一起打爆。view_suffix 固定回 html —— 這些
     * 模板本來就是 Think 語法的 .html。
     *
     * @return \think\view\driver\Think
     */
    private function thinkEngine()
    {
        if ($this->thinkEngine === null) {
            $cfg = $this->config;
            $cfg['view_suffix'] = 'html';
            $this->thinkEngine = new \think\view\driver\Think($cfg);
        }
        return $this->thinkEngine;
    }

    /**
     * 這個模板該不該交還給 think 驅動。
     *
     * 兩種形狀會走到這裡，都不是主題模板：
     *
     * 1. **跨模組 `admin@index/login`** —— Twig 的 loader 被容器化在主題目錄內，
     *    天然到不了別的模組。
     * 2. **已經解析好的絕對檔案路徑** —— think\Controller::fetch() 對 `admin@`
     *    的處理就是先拼成 `application/admin/view_new/<x>.html` 再交給引擎
     *    （見 thinkphp/library/think/Controller.php），插件的 Addons::fetch()
     *    也直接傳檔案路徑。副檔名不是主題的 twig，就不是主題模板。
     *
     * 注意不能只看「開頭是不是 /」：parseName() 把前導 `/` 當作「相對主題根目錄」
     * 的寫法（ThemeGate 的 compile('/'.$name) 就靠它），所以必須用 is_file()
     * 確認那真的是一個檔案。
     *
     * @param string $template
     * @return bool
     */
    private function shouldDelegate($template)
    {
        $template = (string) $template;
        if ($template === '') {
            return false;
        }
        // 後台模組模板一律 .html，不走主題 Twig loader。
        // 例外：靜態生成明確要求渲染主題模板時（見 setThemeRender()）不在此攔截，
        // 交給下面幾條規則判斷 —— 它們仍會擋掉 admin@ 與 view_new。
        if (defined('ENTRANCE') && ENTRANCE === 'admin' && !self::$themeRender) {
            return true;
        }
        if (strpos($template, '@') !== false) {
            return true;
        }
        $viewPath = str_replace('\\', '/', (string) $this->config['view_path']);
        if ($viewPath !== '' && strpos($viewPath, 'admin/view_new') !== false) {
            return true;
        }
        if (is_file($template)) {
            $ext = strtolower(pathinfo($template, PATHINFO_EXTENSION));
            return $ext !== strtolower(ltrim($this->config['view_suffix'], '.'));
        }
        return false;
    }

    /**
     * 模板規則 → 相對於主題根目錄的 Twig 模板名。
     * 規則與 think\view\driver\Think::parseTemplate 對齊，差別只在副檔名與
     * 不支援跨模組 `@`（Twig 主題被 loader 限制在主題目錄內）。
     *
     * @param string $template
     * @return string
     */
    private function parseName($template)
    {
        $template = (string) $template;
        if (strpos($template, '@') !== false) {
            throw new TemplateNotFoundException('twig theme does not support cross-module template: ' . $template, $template);
        }

        $depr = '/';
        if (strpos($template, '/') !== 0) {
            $template = str_replace(['\\', ':'], $depr, $template);
            $request = Request::instance();
            $controller = Loader::parseName($request->controller());
            if ($controller) {
                if ($template === '') {
                    $template = str_replace('.', $depr, $controller) . $depr
                        . (1 == $this->config['auto_rule'] ? Loader::parseName($request->action(true)) : $request->action());
                } elseif (strpos($template, $depr) === false) {
                    $template = str_replace('.', $depr, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['\\', ':'], $depr, substr($template, 1));
        }

        $template = ltrim($template, '/');
        $suffix = ltrim($this->config['view_suffix'], '.');
        $ext = pathinfo($template, PATHINFO_EXTENSION);
        // index/index.draft 的 pathinfo 扩展名是 draft 而非 twig，须仍补 .twig
        if ($ext === '' || $ext !== $suffix) {
            $template .= '.' . $suffix;
        }
        return $template;
    }

    /**
     * 檢測模板是否存在。
     * @param string $template
     * @return bool
     */
    public function exists($template)
    {
        if ($this->shouldDelegate($template)) {
            return $this->thinkEngine()->exists($template);
        }
        try {
            return $this->twig()->getLoader()->exists($this->parseName($template));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 只編譯不渲染。供主題校驗閘（ThemeGate）在不需要請求上下文的情況下
     * 檢出語法錯誤與被 TwigGuard 拒絕的構造。
     *
     * @param string $template 模板規則或相對路徑
     * @return void
     * @throws \Exception 編譯失敗時拋出（訊息含模板名與行號）
     */
    public function compile($template)
    {
        $this->twig()->load($this->parseName($template));
    }

    /**
     * 渲染成字串（不輸出）。供校驗閘做渲染冒煙。
     *
     * @param string $template
     * @param array  $data
     * @return string
     */
    public function render($template, $data = [])
    {
        return $this->twig()->render($this->parseName($template), $data);
    }

    /**
     * 渲染模板檔案（直接輸出，由 View::fetch 的 ob 捕獲）。
     *
     * @param string $template
     * @param array  $data
     * @param array  $config
     * @return void
     */
    public function fetch($template, $data = [], $config = [])
    {
        if (!empty($config)) {
            $this->config($config);
        }
        if ($this->shouldDelegate($template)) {
            $this->thinkEngine()->fetch($template, $data, $config);
            return;
        }
        $name = $this->parseName($template);
        $html = $this->twig()->render($name, $data);
        echo $this->applyReplace($html);
    }

    /**
     * 渲染字串模板。
     *
     * 委派回 think\view\driver\Think —— 這條路徑的呼叫點是站長在後台配置的
     * 訊息/郵件模板（application/common/model/User.php、admin/controller/System.php），
     * 內容是 Think 語法且屬站長既有資料，不能因為前台換了引擎就失效。
     *
     * @param string $content
     * @param array  $data
     * @param array  $config
     * @return void
     */
    public function display($content, $data = [], $config = [])
    {
        $this->thinkEngine()->display($content, $data, $config);
    }

    /**
     * 套用 View 層的字串替換（對齊 Think 引擎的 tpl_replace_string 行為）。
     * @param string $html
     * @return string
     */
    private function applyReplace($html)
    {
        $replace = $this->config['tpl_replace_string'];
        if (empty($replace) || !is_array($replace)) {
            return $html;
        }
        return strtr($html, $replace);
    }

    /**
     * 配置或讀取模板引擎參數。TP5 的 View::fetch 會用它讀寫 tpl_replace_string。
     *
     * @param string|array $name
     * @param mixed        $value
     * @return mixed
     */
    public function config($name, $value = null)
    {
        if (is_array($name)) {
            $this->config = array_merge($this->config, $name);
            $this->twig = null;
            $this->thinkEngine = null;
            return null;
        }
        if (is_null($value)) {
            return isset($this->config[$name]) ? $this->config[$name] : null;
        }
        $this->config[$name] = $value;
        if (in_array($name, ['view_path', 'view_suffix', 'tpl_cache'], true)) {
            $this->twig = null;
            // 委派對象是照當時的 config 建的，view_path 一變就得跟著重建，
            // 否則插件那類「先改 template.view_path 再 fetch」的呼叫會拿到舊路徑
            $this->thinkEngine = null;
        }
        return null;
    }
}
