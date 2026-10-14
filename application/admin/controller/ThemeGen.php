<?php
namespace app\admin\controller;

use app\common\util\ThemeGenerator;

/**
 * AI 主題產線的後台入口。
 *
 * 產線本身在 app\common\util\ThemeGenerator，這裡只做三件事：
 *   1. 收站長填的 brief；
 *   2. 交給產線（strict 模式過閘）；
 *   3. 把閘的結果原樣回顯 —— 失敗時把 errors 一條條給站長看，
 *      而不是丟一句「生成失敗」讓人無從判斷是模型不行還是 brief 沒寫清楚。
 *
 * 產線的安全邊界在 ThemeGenerator：模型輸出一律過白名單消毒、先寫暫存區、
 * 過閘才搬進 template/。本控制器不重複那些判斷，也不繞過它們。
 */
class ThemeGen extends Base
{
    public function index()
    {
        if (Request()->isPost()) {
            return $this->generate();
        }

        $this->assign('parents', self::parentOptions());
        // Init 會把 template.view_path 指到前台主題目錄，模組相對的 fetch()
        // 會去 template/default/html/ 找而整頁空白。用 admin@ 前綴讓框架自己解到
        // view_new（thinkphp/library/think/view/driver/Think.php:125-126 已針對
        // admin 模組打過補丁）。
        return $this->fetch('admin@themegen/index');
    }

    /**
     * 執行產線。回 JSON，由前端渲染結果。
     *
     * @return \think\response\Json
     */
    protected function generate()
    {
        $param = input('post.');

        $spec = [
            'dir'    => isset($param['dir']) ? $param['dir'] : '',
            'name'   => isset($param['name']) ? mac_filter_xss($param['name']) : '',
            'parent' => isset($param['parent']) ? $param['parent'] : '',
            'style'  => isset($param['style']) ? mac_filter_xss($param['style']) : '',
            'colors' => isset($param['colors']) ? mac_filter_xss($param['colors']) : '',
            'layout' => isset($param['layout']) ? mac_filter_xss($param['layout']) : '',
            'notes'  => isset($param['notes']) ? mac_filter_xss($param['notes']) : '',
        ];

        if (ThemeGenerator::normalizeDir($spec['dir']) === '') {
            return json(['code' => 0, 'msg' => lang('admin/themegen/dir_err')]);
        }

        $res = ThemeGenerator::run($spec);
        if ($res['code'] !== 1) {
            return json([
                'code' => 0,
                'msg'  => lang('admin/themegen/gen_err'),
                'data' => ['errors' => $res['errors']],
            ]);
        }

        return json([
            'code' => 1,
            'msg'  => lang('admin/themegen/gen_ok'),
            'data' => [
                'dir'      => $res['dir'],
                'warnings' => isset($res['gate']['warnings']) ? $res['gate']['warnings'] : [],
            ],
        ]);
    }

    /**
     * 可以當底座的主題：template/ 下有 html/ 的 Twig 主題。
     *
     * 只列 Twig 主題 —— 拿 taglib 主題當 parent，模板語法對不上，
     * 回落到的每一頁都會是語法錯誤。
     *
     * @return array
     */
    public static function parentOptions()
    {
        $out = [];
        $dirs = glob(ROOT_PATH . 'template' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (!is_array($dirs)) {
            return $out;
        }
        foreach ($dirs as $dir) {
            $base = basename($dir);
            // 下劃線開頭的是共用資產目錄（template/_shared/），不是主題
            if (substr($base, 0, 1) === '_' || !is_dir($dir . DIRECTORY_SEPARATOR . 'html')) {
                continue;
            }
            $ini = @parse_ini_file($dir . DIRECTORY_SEPARATOR . 'info.ini', false, INI_SCANNER_RAW);
            if (!is_array($ini) || strtolower(trim(isset($ini['engine']) ? $ini['engine'] : '')) !== 'twig') {
                continue;
            }
            $out[$base] = isset($ini['name']) && $ini['name'] !== '' ? trim($ini['name']) : $base;
        }
        return $out;
    }
}
