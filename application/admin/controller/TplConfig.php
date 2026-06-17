<?php

namespace app\admin\controller;

class TplConfig extends Base
{
    /**
     * 默认模板主题配置
     */
    public function theme()
    {
        if (request()->isPost()) {
            $tplconfig = input();
            if (empty($tplconfig) || !isset($tplconfig['theme'])) {
                return $this->error(lang('param_err'));
            }
            $tplconfig = $tplconfig['theme'];
            if (!isset($tplconfig['banner']) || !is_array($tplconfig['banner'])) {
                $tplconfig['banner'] = [];
            }
            $stylePc = isset($tplconfig['banner']['style_pc']) ? (string)$tplconfig['banner']['style_pc'] : '1';
            $styleH5 = isset($tplconfig['banner']['style_h5']) ? (string)$tplconfig['banner']['style_h5'] : '1';
            $tplconfig['banner']['style_pc'] = in_array($stylePc, ['1', '2', '3', '4'], true) ? $stylePc : '1';
            $tplconfig['banner']['style_h5'] = in_array($styleH5, ['1', '2', '3', '4'], true) ? $styleH5 : '1';
            if (isset($tplconfig['fnav']['ym']) && is_array($tplconfig['fnav']['ym'])) {
                $tplconfig['fnav']['ym'] = join('|', $tplconfig['fnav']['ym']);
            }
            if (isset($tplconfig['show']['filter']) && is_array($tplconfig['show']['filter'])) {
                $tplconfig['show']['filter'] = join('|', $tplconfig['show']['filter']);
            }
            if (isset($tplconfig['ad_slots'])) {
                $tplconfig['ad_slots'] = $this->normalizeAdSlots($tplconfig['ad_slots']);
            }
            if (isset($tplconfig['type']['hom']) && is_array($tplconfig['type']['hom'])) {
                foreach ($tplconfig['type']['hom'] as $hk => $homRow) {
                    if (!is_array($homRow)) {
                        continue;
                    }
                    $cv = isset($homRow['cover']) ? (string) $homRow['cover'] : 'v';
                    $tplconfig['type']['hom'][$hk]['cover'] = ($cv === 'h') ? 'h' : 'v';
                }
            }
            if (!isset($tplconfig['list_cover']) || !is_array($tplconfig['list_cover'])) {
                $tplconfig['list_cover'] = [];
            }
            if (!isset($tplconfig['play']) || !is_array($tplconfig['play'])) {
                $tplconfig['play'] = [];
            }
            $tplconfig['play']['chatroom'] = (isset($tplconfig['play']['chatroom']) && (string)$tplconfig['play']['chatroom'] === '0') ? '0' : '1';
            $vodRows = [];
            if (isset($tplconfig['list_cover']['vod']) && is_array($tplconfig['list_cover']['vod'])) {
                $vodRows = $tplconfig['list_cover']['vod'];
            } elseif (isset($tplconfig['type']['list_cover']) && is_array($tplconfig['type']['list_cover'])) {
                // 兼容旧结构，避免历史配置迁移期间丢失
                $vodRows = $tplconfig['type']['list_cover'];
            }
            $listCoverVod = [];
            foreach ($vodRows as $row) {
                if (!is_array($row) || !isset($row['id']) || (string) $row['id'] === '') {
                    continue;
                }
                $cv = isset($row['cover']) ? (string) $row['cover'] : 'v';
                $listCoverVod[] = [
                    'id' => (string) (int) $row['id'],
                    'cover' => ($cv === 'h') ? 'h' : 'v',
                ];
            }
            $tplconfig['list_cover']['vod'] = $listCoverVod;
            unset($tplconfig['type']['list_cover']);
            if (isset($tplconfig['manga']['hbtn'])) {
                $tplconfig['manga']['hbtn'] = ((string) $tplconfig['manga']['hbtn'] === '1') ? '1' : '0';
            }
            if (isset($tplconfig['manga']['hnum'])) {
                $tplconfig['manga']['hnum'] = ((string) $tplconfig['manga']['hnum'] === '12') ? '12' : '6';
            }
            $mangaCoverRaw = isset($tplconfig['list_cover']['manga'])
                ? (string) $tplconfig['list_cover']['manga']
                : (isset($tplconfig['manga']['cover']) ? (string) $tplconfig['manga']['cover'] : 'v');
            $tplconfig['list_cover']['manga'] = ($mangaCoverRaw === 'h') ? 'h' : 'v';
            if (isset($tplconfig['manga']['cover'])) {
                unset($tplconfig['manga']['cover']);
            }
            if (isset($tplconfig['art']['hbtn'])) {
                $tplconfig['art']['hbtn'] = ((string) $tplconfig['art']['hbtn'] === '1') ? '1' : '0';
            }
            if (isset($tplconfig['art']['hnum'])) {
                $tplconfig['art']['hnum'] = ((string) $tplconfig['art']['hnum'] === '12') ? '12' : '6';
            }
            $artCoverRaw = isset($tplconfig['list_cover']['art'])
                ? (string) $tplconfig['list_cover']['art']
                : (isset($tplconfig['art']['cover']) ? (string) $tplconfig['art']['cover'] : 'v');
            $tplconfig['list_cover']['art'] = ($artCoverRaw === 'h') ? 'h' : 'v';
            if (isset($tplconfig['art']['cover'])) {
                unset($tplconfig['art']['cover']);
            }
            if (isset($tplconfig['topic']['hbtn'])) {
                $tplconfig['topic']['hbtn'] = ((string) $tplconfig['topic']['hbtn'] === '1') ? '1' : '0';
            }
            if (isset($tplconfig['links']['btn'])) {
                $tplconfig['links']['btn'] = ((string) $tplconfig['links']['btn'] === '1') ? '1' : '0';
            }
            if (!isset($tplconfig['badge']) || !is_array($tplconfig['badge'])) {
                $tplconfig['badge'] = [];
            }
            $tplconfig['badge']['serial_btn'] = (isset($tplconfig['badge']['serial_btn']) && (string) $tplconfig['badge']['serial_btn'] === '0') ? '0' : '1';
            $tplconfig['badge']['cover_sub_btn'] = (isset($tplconfig['badge']['cover_sub_btn']) && (string) $tplconfig['badge']['cover_sub_btn'] === '0') ? '0' : '1';
            if (isset($tplconfig['rank']['hbtn'])) {
                $tplconfig['rank']['hbtn'] = ((string) $tplconfig['rank']['hbtn'] === '1') ? '1' : '0';
            }
            if (isset($tplconfig['rank']['btn'])) {
                $tplconfig['rank']['btn'] = ((string) $tplconfig['rank']['btn'] === '1') ? '1' : '0';
            }
            if (isset($tplconfig['nav']) && is_array($tplconfig['nav'])) {
                if (isset($tplconfig['nav']['id'])) {
                    $navIds = preg_replace('/\s+/', '', (string) $tplconfig['nav']['id']);
                    $navIds = trim($navIds, ',');
                    $tplconfig['nav']['id'] = $navIds;
                }
                $tplconfig['nav']['zdybtn'] = (isset($tplconfig['nav']['zdybtn']) && (string) $tplconfig['nav']['zdybtn'] === '1') ? '1' : '0';
                foreach (['zdybtn1', 'zdybtn2', 'zdybtn3', 'zdybtn4'] as $zdyKey) {
                    if (isset($tplconfig['nav'][$zdyKey])) {
                        $tplconfig['nav'][$zdyKey] = ((string) $tplconfig['nav'][$zdyKey] === '1') ? '1' : '0';
                    }
                }
            }
            unset($tplconfig['topic']['hnum']);
            unset($tplconfig['topic']['htitle']);
            $topicCellsIn = isset($tplconfig['topic']['cells']) && is_array($tplconfig['topic']['cells'])
                ? $tplconfig['topic']['cells'] : [];
            $topicCellsNorm = [];
            for ($ti = 0; $ti < 5; $ti++) {
                $trow = isset($topicCellsIn[$ti]) && is_array($topicCellsIn[$ti]) ? $topicCellsIn[$ti] : [];
                $tidRaw = isset($trow['topic_id']) ? (int) $trow['topic_id'] : 0;
                $topicCellsNorm[] = [
                    'topic_id' => (string) ($tidRaw > 0 ? $tidRaw : 0),
                    'title' => isset($trow['title']) ? trim((string) $trow['title']) : '',
                    'sub' => isset($trow['sub']) ? trim((string) $trow['sub']) : '',
                ];
            }
            $tplconfig['topic']['cells'] = $topicCellsNorm;
            if (isset($tplconfig['contact']) && is_array($tplconfig['contact'])) {
                unset($tplconfig['contact']['sdk_js']);
            }
            $tplconfig_new = ['theme' => $tplconfig];
            $tplconfig_old = isset($GLOBALS['mctheme']) && is_array($GLOBALS['mctheme']) ? $GLOBALS['mctheme'] : [];
            $tplconfig_new = array_merge($tplconfig_old, $tplconfig_new);
            $res = mac_save_config_data(APP_PATH . 'extra/mctheme.php', $tplconfig_new);
            if ($res === false) {
                return $this->error(lang('save_err'));
            }
            // 与 ThinkPHP 配置、$GLOBALS 同步，当前请求内模板与 mac_tpl_* 立即生效
            \think\Config::set($tplconfig_new, 'mctheme');
            $GLOBALS['mctheme'] = $tplconfig_new;
            return $this->success(lang('save_ok'));
        }

        $tplconfig = isset($GLOBALS['mctheme']) ? $GLOBALS['mctheme'] : (config('mctheme') ?: ['theme' => []]);
        $type_tree = model('Type')->getCache('type_tree');
        if (!is_array($type_tree)) {
            $type_tree = [];
        }
        $this->assign('tplconfig', $tplconfig);
        $this->assign('type_tree', $type_tree);
        $this->assign('theme_type_options', $this->buildThemeTypeOptions($type_tree));
        $this->assign('theme_type_index', $this->buildThemeTypeIndex($type_tree));
        $this->assignThemeUxI18n();
        $this->assign('title', lang('menu/theme/config'));
        return $this->fetch('admin@tplconfig/theme');
    }

    /**
     * 主题配置页分类选择器：按 type_mid 分组的选项（1=视频 2=文章 12=漫画，all=全部）
     */
    protected function buildThemeTypeOptions(array $type_tree)
    {
        $midLabels = [
            1 => lang('vod'),
            2 => lang('art'),
            12 => lang('admin/tpl/config/type_mid_manga'),
        ];
        $groups = ['1' => [], '2' => [], '12' => [], 'all' => []];
        $push = function ($row, $depth = 0) use (&$groups, $midLabels) {
            $id = isset($row['type_id']) ? (string) $row['type_id'] : '';
            if ($id === '') {
                return;
            }
            $mid = isset($row['type_mid']) ? (int) $row['type_mid'] : 0;
            $name = isset($row['type_name']) ? (string) $row['type_name'] : '';
            $label = preg_replace('/^—\s*/u', '', $name);
            $item = [
                'id' => $id,
                'name' => $name,
                'label' => $label,
                'depth' => $depth,
            ];
            if (isset($groups[(string) $mid])) {
                $groups[(string) $mid][] = $item;
            }
            $prefix = isset($midLabels[$mid]) ? '[' . $midLabels[$mid] . '] ' : '';
            $groups['all'][] = [
                'id' => $id,
                'name' => $prefix . $name,
                'label' => $prefix . $label,
                'depth' => $depth,
                'mid' => $mid,
            ];
        };
        foreach ($type_tree as $vo) {
            if (!is_array($vo)) {
                continue;
            }
            $push($vo, 0);
            if (!empty($vo['child']) && is_array($vo['child'])) {
                foreach ($vo['child'] as $ch) {
                    if (!is_array($ch)) {
                        continue;
                    }
                    $chRow = $ch;
                    if (!isset($chRow['type_mid']) && isset($vo['type_mid'])) {
                        $chRow['type_mid'] = $vo['type_mid'];
                    }
                    $chRow['type_name'] = '— ' . (isset($ch['type_name']) ? (string) $ch['type_name'] : '');
                    $push($chRow, 1);
                }
            }
        }
        return $groups;
    }

    /**
     * 主题配置页 JS 文案（分类选择器、排序等）
     */
    protected function assignThemeUxI18n()
    {
        $this->assign('theme_ux_i18n_json', json_encode([
            'midLabels' => [
                '1' => lang('vod'),
                '2' => lang('art'),
                '12' => lang('admin/tpl/config/type_mid_manga'),
            ],
            'midTabs' => [
                ['id' => 'all', 'title' => lang('all')],
                ['id' => '1', 'title' => lang('vod')],
                ['id' => '2', 'title' => lang('art')],
                ['id' => '12', 'title' => lang('admin/tpl/config/type_mid_manga')],
            ],
            'pickType' => lang('admin/tpl/config/type_picker_btn'),
            'pickNavMulti' => lang('admin/tpl/config/type_picker_nav_multi'),
            'pickMulti' => lang('admin/tpl/config/type_picker_multi'),
            'selectedCount' => lang('admin/tpl/config/type_picker_selected_count'),
            'removeType' => lang('admin/tpl/config/type_picker_remove'),
            'noneSelectedHint' => lang('admin/tpl/config/type_picker_none_hint'),
            'selectedHead' => lang('admin/tpl/config/type_picker_selected_head'),
            'idInvalid' => lang('admin/tpl/config/type_picker_id_invalid'),
            'emptyMulti' => lang('admin/tpl/config/type_picker_empty_multi'),
            'emptySingle' => lang('admin/tpl/config/type_picker_empty_single'),
            'closeEsc' => lang('admin/tpl/config/type_picker_close_esc'),
            'close' => lang('admin/tpl/config/type_picker_close'),
            'searchPh' => lang('admin/tpl/config/type_picker_search_ph'),
            'dragSort' => lang('admin/tpl/config/type_picker_drag_sort'),
            'clearAll' => lang('admin/tpl/config/type_picker_clear_all'),
            'unbind' => lang('admin/tpl/config/type_picker_unbind'),
            'confirm' => lang('admin/tpl/config/type_picker_confirm'),
            'reset' => lang('admin/tpl/config/type_picker_reset'),
            'multiMeta' => lang('admin/tpl/config/type_picker_multi_meta'),
            'singleMeta' => lang('admin/tpl/config/type_picker_single_meta'),
            'hotvodTabLabel' => lang('admin/tpl/config/hotvod_tab_label'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    }

    /**
     * type_id => {id, name, mid}，供前端回显 Tab 与失效 ID
     */
    protected function buildThemeTypeIndex(array $type_tree)
    {
        $index = [];
        $walk = function ($row) use (&$index, &$walk) {
            if (!is_array($row) || !isset($row['type_id'])) {
                return;
            }
            $id = (string) $row['type_id'];
            $index[$id] = [
                'id' => $id,
                'name' => isset($row['type_name']) ? (string) $row['type_name'] : '',
                'mid' => isset($row['type_mid']) ? (int) $row['type_mid'] : 0,
            ];
            if (!empty($row['child']) && is_array($row['child'])) {
                foreach ($row['child'] as $ch) {
                    if (is_array($ch)) {
                        $walk($ch);
                    }
                }
            }
        };
        foreach ($type_tree as $vo) {
            $walk($vo);
        }
        return $index;
    }

    /**
     * 广告位配置规范化（固定结构）：
     * slot => ['ad_type_id' => ?, 'ad_data' => [ ... ]]
     */
    protected function normalizeAdSlots($adSlots)
    {
        if (!is_array($adSlots)) {
            return [];
        }
        $slotMeta = [
            'banner1' => ['ad_type_id' => 1, 'area' => 'hengfu1'],
            'banner_swiper' => ['ad_type_id' => 4, 'area' => 'lunbo'],
            'icon1' => ['ad_type_id' => 2, 'area' => 'tubiao1'],
            'bottom' => ['ad_type_id' => 3, 'area' => 'dipiao'],
            'sitejs' => ['ad_type_id' => null, 'area' => 'js'],
        ];
        $fields = ['id', 'area', 'order', 'name', 'url', 'image', 'height', 'text', 'code', 'active', 'date', 'note'];
        $normalized = [];
        foreach ($slotMeta as $slot => $meta) {
            $normalized[$slot] = ['ad_type_id' => $meta['ad_type_id'], 'ad_data' => []];
            if (empty($adSlots[$slot]) || !is_array($adSlots[$slot]) || empty($adSlots[$slot]['ad_data']) || !is_array($adSlots[$slot]['ad_data'])) {
                continue;
            }
            if (array_key_exists('ad_type_id', $adSlots[$slot])) {
                $normalized[$slot]['ad_type_id'] = $adSlots[$slot]['ad_type_id'] === '' ? null : $adSlots[$slot]['ad_type_id'];
            }
            foreach ($adSlots[$slot]['ad_data'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $item = [];
                foreach ($fields as $field) {
                    $value = isset($row[$field]) ? trim((string)$row[$field]) : '';
                    if ($field === 'order') {
                        $value = (string)intval($value);
                    } elseif ($field === 'active') {
                        $value = strtolower($value) === 'on' ? 'on' : 'off';
                    }
                    $item[$field] = $value;
                }
                $item['area'] = $meta['area'];

                // 至少有一个有效业务字段才保留，避免空行写入配置
                $hasPayload = false;
                $payloadFields = ['url', 'image', 'code', 'text', 'name', 'note'];
                foreach ($payloadFields as $k) {
                    if (!empty($item[$k])) { $hasPayload = true; break; }
                }
                if ($hasPayload) {
                    $normalized[$slot]['ad_data'][] = $item;
                }
            }
        }
        return $normalized;
    }
}
