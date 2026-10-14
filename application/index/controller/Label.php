<?php
namespace app\index\controller;
use think\Controller;

class Label extends Base
{
    /**
     * 排行榜独立页：分类列表与首页 module/index_theme_vars 中 $index_rank_types 规则一致，
     * 便于调用相同的 vod/get_rank 接口并得到同一批栏目。
     * 优先级：theme.rank.hid（与首页一致）> theme.rank.id（旧独立页配置）> 等价于 hid 为空时的 parent。
     */
    protected function labelRankTypesForPage(): array
    {
        $mc = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme'])
            ? $GLOBALS['mctheme']
            : (config('mctheme') ?: ['theme' => []]);
        $rankCfg = (isset($mc['theme']['rank']) && is_array($mc['theme']['rank'])) ? $mc['theme']['rank'] : [];
        $rankHid = isset($rankCfg['hid']) ? trim((string) $rankCfg['hid']) : '';
        $rankIdLegacy = isset($rankCfg['id']) ? trim((string) $rankCfg['id']) : '';
        if ($rankHid !== '') {
            $rankTypeIdsRaw = $rankHid;
        } elseif ($rankIdLegacy !== '') {
            $rankTypeIdsRaw = $rankIdLegacy;
        } else {
            $rankTypeIdsRaw = 'parent';
        }

        $typeModel = model('Type');
        $typeListCache = $typeModel->getCache('type_list');
        $rankTypeList = [];
        if (!is_array($typeListCache)) {
            return [];
        }
        if ($rankTypeIdsRaw === '' || $rankTypeIdsRaw === 'parent') {
            foreach ($typeListCache as $typeInfo) {
                if (!is_array($typeInfo)) {
                    continue;
                }
                if ((int) ($typeInfo['type_pid'] ?? 0) !== 0) {
                    continue;
                }
                if ((int) ($typeInfo['type_mid'] ?? 0) !== 1) {
                    continue;
                }
                if (isset($typeInfo['type_status']) && (int) $typeInfo['type_status'] !== 1) {
                    continue;
                }
                $rankTypeList[] = $typeInfo;
            }
            usort($rankTypeList, function ($a, $b) {
                return ((int) ($a['type_sort'] ?? 0)) <=> ((int) ($b['type_sort'] ?? 0));
            });
            $rankTypeList = array_slice($rankTypeList, 0, 6);
        } else {
            $ids = array_filter(array_map('intval', explode(',', $rankTypeIdsRaw)));
            foreach ($ids as $rid) {
                $info = $typeModel->getCacheInfo($rid);
                if (!empty($info) && (int) ($info['type_mid'] ?? 0) === 1) {
                    $rankTypeList[] = $info;
                }
            }
            $rankTypeList = array_slice($rankTypeList, 0, 6);
        }

        $out = [];
        foreach ($rankTypeList as $typeInfo) {
            $out[] = [
                'type_id'   => (int) ($typeInfo['type_id'] ?? 0),
                'type_name' => (string) ($typeInfo['type_name'] ?? ''),
            ];
        }

        return $out;
    }

    public function __construct()
    {
        parent::__construct();

        $dispatch = request()->dispatch();
        if (isset($dispatch['module'])) {
            $file = $dispatch['module'][2];
            $param = mac_param_url();
            if (!empty($param['file'])) {
                $file = $param['file'];
            }
            $file = str_replace('\\', '/', $file);
            // 副檔名跟著主題引擎走：Think 主題是 .html，Twig 主題是 .twig
            // （由 behavior\Init 依 info.ini 的 engine 設定 template.view_suffix）。
            // 原本硬寫死 .html，Twig 主題的 label/rank.twig 永遠過不了這個存在性檢查。
            $suffix = (string) config('template.view_suffix');
            if ($suffix === '') {
                $suffix = 'html';
            }
            $tplRoute = 'label/' . $file;
            $tplExists = is_file($GLOBALS['MAC_ROOT_TEMPLATE'] . 'label/' . $file . '.' . $suffix);
            if (!$tplExists && function_exists('mac_td_preview_mode') && mac_td_preview_mode()
                && function_exists('mac_pb_theme_has_page_tpl')) {
                $previewTheme = function_exists('mac_pb_theme_template_dir') ? mac_pb_theme_template_dir() : '';
                if ($previewTheme !== '') {
                    $tplExists = mac_pb_theme_has_page_tpl($previewTheme, $tplRoute, 'html');
                }
            }
            if (!$tplExists || strpos($file, '/') !== false) {
                return $this->error(lang('illegal_request'));
            }
            if ($file === 'rank') {
                $types = $this->labelRankTypesForPage();
                if (count($types) === 0) {
                    $types = [['type_id' => 0, 'type_name' => '全站']];
                }
                $mc = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme'])
                    ? $GLOBALS['mctheme']
                    : (config('mctheme') ?: ['theme' => []]);
                $rankCfg = (isset($mc['theme']['rank']) && is_array($mc['theme']['rank'])) ? $mc['theme']['rank'] : [];
                $vodNum = max(1, min(50, (int) ($rankCfg['num'] ?? 6)));
                $rankTitleSuffix = isset($rankCfg['title']) ? trim((string) $rankCfg['title']) : '';
                if ($rankTitleSuffix === '') {
                    $rankTitleSuffix = '排行榜';
                }
                $this->assign('label_rank_types', $types);
                $this->assign('label_rank_config', [
                    'vod_num'           => $vodNum,
                    'rank_title_suffix' => $rankTitleSuffix,
                ]);
            }
            $view = function_exists('mac_page_view') ? mac_page_view($tplRoute) : $tplRoute;
            echo $this->label_fetch($view);
        }
        exit;
    }

    public function index()
    {

    }

}
