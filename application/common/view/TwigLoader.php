<?php
namespace app\common\view;

use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\Source;

/**
 * 容器化的 Twig 檔案 loader。
 *
 * 相對於上游 FilesystemLoader 增加兩件事：
 *
 * 1. **realpath 容器化**（緩解 CVE-2022-39261，high，上游 2.15.3 才修）。
 *    上游的 validateName() 只做字串層的 `..` 計數，遇到 symlink、編碼變體、
 *    或 namespace 拼接時仍可能解析到主題目錄之外。這裡改為在檔案真實解析之後，
 *    用 realpath() 斷言結果落在主題根目錄內；這是結果導向的檢查，
 *    覆蓋整類問題而不只是上游修補的那一個觸發路徑。
 *
 * 2. **原始碼安全掃描**（見 TwigGuard）。掛在 getSourceContext() 上，
 *    extends / include / embed 鏈上每個模板都會經過。
 */
class TwigLoader extends FilesystemLoader
{
    /** @var array 允許的根目錄 realpath 清單，每項結尾帶目錄分隔符 */
    private $containRoots = [];

    /**
     * @param string|array $paths 模板根目錄（第一個是主題自己的 html/）
     * @param string|null  $rootPath
     */
    public function __construct($paths = [], $rootPath = null)
    {
        parent::__construct($paths, $rootPath);
        $this->allow(is_array($paths) ? $paths : [$paths]);
    }

    /**
     * 把目錄加入允許清單。共用元件庫（template/_shared/twig）用
     * addPath() 掛成命名空間後，也必須在這裡登記，否則會被容器化檢查擋掉。
     *
     * @param array $paths
     * @return void
     */
    public function allow(array $paths)
    {
        foreach ($paths as $p) {
            if ($p === '' || $p === null) {
                continue;
            }
            $real = realpath($p);
            if ($real === false) {
                continue;
            }
            $real = rtrim($real, '/\\') . DIRECTORY_SEPARATOR;
            if (!in_array($real, $this->containRoots, true)) {
                $this->containRoots[] = $real;
            }
        }
    }

    /**
     * 允許的根目錄清單（realpath，結尾帶分隔符）。
     * 空陣列表示未能解析任何根目錄，此時所有載入都會被拒絕。
     * @return array
     */
    public function getContainRoots()
    {
        return $this->containRoots;
    }

    /**
     * 相容舊呼叫：回傳主題自己的根目錄。
     * @return string
     */
    public function getContainRoot()
    {
        return isset($this->containRoots[0]) ? $this->containRoots[0] : '';
    }

    /**
     * 解析模板檔案路徑，並斷言其真實路徑落在主題根目錄內。
     *
     * @param string $name
     * @param bool   $throw
     * @return string|null|false
     * @throws LoaderError
     */
    protected function findTemplate($name, $throw = true)
    {
        $path = parent::findTemplate($name, $throw);
        if ($path === null || $path === false) {
            return $path;
        }

        if (empty($this->containRoots)) {
            if ($throw) {
                throw new LoaderError('主題根目錄無法解析，拒絕載入任何模板。');
            }
            return false;
        }

        $real = realpath($path);
        $inside = false;
        if ($real !== false) {
            foreach ($this->containRoots as $root) {
                if (strpos($real, $root) === 0) {
                    $inside = true;
                    break;
                }
            }
        }
        if (!$inside) {
            if ($throw) {
                throw new LoaderError(sprintf(
                    '模板 "%s" 解析到允許的目錄之外，已拒絕載入。',
                    $name
                ));
            }
            return false;
        }

        return $path;
    }

    /**
     * 取得模板原始碼，並在交給編譯器之前過一次安全掃描。
     *
     * @param string $name
     * @return Source
     * @throws LoaderError
     */
    public function getSourceContext($name)
    {
        $source = parent::getSourceContext($name);
        TwigGuard::scan($source->getCode(), $name);
        return $source;
    }
}
