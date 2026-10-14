<?php
namespace app\common\util;

/**
 * Twig 主题模板运行时辅助：广告位、主题资产 URL、首页 hotvod Tab。
 */
class ThemeTemplateHelper
{
    /**
     * 首页热门推荐 Tab（latest2–latest5）：读取 theme.hotvod.tabs。
     *
     * @param array $theme mctheme.theme
     * @return array<int, array{id:string,name:string,icon_l:string,icon_b:string}>
     */
    public static function indexHotvodTabs(array $theme)
    {
        $tabsCfg = isset($theme['hotvod']['tabs']) && is_array($theme['hotvod']['tabs']) ? $theme['hotvod']['tabs'] : array();
        if (count($tabsCfg) === 0) {
            return array();
        }

        $typeModel = model('Type');
        $out = array();

        for ($i = 0; $i < 4; $i++) {
            if (!isset($tabsCfg[$i]) || !is_array($tabsCfg[$i])) {
                continue;
            }
            $row = $tabsCfg[$i];
            $id = isset($row['id']) ? trim((string) $row['id']) : '';
            if ($id === '') {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($name === '') {
                $vo2 = $typeModel->getCacheInfo((int) $id);
                $name = !empty($vo2['type_name']) ? (string) $vo2['type_name'] : '';
            }

            $out[] = array(
                'id' => $id,
                'name' => $name,
                'icon_l' => isset($row['icon_l']) ? trim((string) $row['icon_l']) : '',
                'icon_b' => isset($row['icon_b']) ? trim((string) $row['icon_b']) : '',
            );
        }

        return $out;
    }

    /**
     * 主题广告位有效条目。
     *
     * @param string $slot     广告位 key，如 banner1 / banner_swiper
     * @param array  $required 必填字段，缺任一则该条不展示
     * @return array
     */
    public static function adSlot($slot, array $required = array())
    {
        $slot = (string) $slot;
        if ($slot === '') {
            return array();
        }

        $theme = array();
        if (isset($GLOBALS['mctheme']['theme']) && is_array($GLOBALS['mctheme']['theme'])) {
            $theme = $GLOBALS['mctheme']['theme'];
        } else {
            $cfg = config('mctheme');
            if (isset($cfg['theme']) && is_array($cfg['theme'])) {
                $theme = $cfg['theme'];
            }
        }

        if (empty($theme['ad_slots'][$slot]['ad_data']) || !is_array($theme['ad_slots'][$slot]['ad_data'])) {
            return array();
        }

        $now = time();
        $rows = array();
        foreach ($theme['ad_slots'][$slot]['ad_data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $active = isset($row['active']) ? strtolower(trim((string) $row['active'])) : 'off';
            if ($active !== 'on') {
                continue;
            }
            $expireAt = isset($row['date']) ? trim((string) $row['date']) : '';
            if ($expireAt !== '') {
                $expireTs = strtotime($expireAt);
                if ($expireTs !== false && $expireTs < $now) {
                    continue;
                }
            }
            $ok = true;
            foreach ($required as $field) {
                if (empty($row[$field])) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * 解析主题资产的 URL：子主题 → 父主题 → kit/shared。
     *
     * @param string $rel 相对 asset/ 的路径，如 img/logo.png
     * @return string
     */
    public static function themeAsset($rel)
    {
        $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }

        $base = defined('MAC_PATH') ? MAC_PATH : '/';

        $theme = self::currentTheme();
        if ($theme === '') {
            return $base . 'template/_shared/kit/shared/' . $rel;
        }

        $candidates = array(array('template/' . $theme . '/asset/', ROOT_PATH . 'template/' . $theme . '/asset/'));

        $parent = \app\common\behavior\Init::themeParent($theme);
        if ($parent !== '') {
            $candidates[] = array('template/' . $parent . '/asset/', ROOT_PATH . 'template/' . $parent . '/asset/');
        }
        $candidates[] = array('template/_shared/kit/shared/', ROOT_PATH . 'template/_shared/kit/shared/');

        foreach ($candidates as $one) {
            if (is_file($one[1] . $rel)) {
                return $base . $one[0] . $rel;
            }
        }

        return $base . $candidates[0][0] . $rel;
    }
    /**
     * 当前前台主题目录名（template/<theme>），不合法时返回空串。
     *
     * @return string
     */
    public static function currentTheme()
    {
        $theme = '';
        $viewPath = (string) config('template.view_path');
        if ($viewPath !== '') {
            $parts = array_values(array_filter(explode('/', str_replace('\\', '/', trim($viewPath, '/')))));
            $count = count($parts);
            if ($count >= 2) {
                $theme = $parts[$count - 2];
            }
        }
        if ($theme === '') {
            $site = config('maccms.site');
            $theme = isset($site['template_dir']) ? (string) $site['template_dir'] : '';
        }
        if ($theme === '' || strpos($theme, '/') !== false || strpos($theme, '.') !== false) {
            return '';
        }
        return $theme;
    }

    /** @var array<string,string>|null 前端 i18n 中文字典（strings_zh.properties） */
    private static $zhStrings = null;

    /**
     * 前端 i18n 中文字典：优先当前主题已同步的副本，回落 template/_shared/kit。
     *
     * @return array<string,string>
     */
    public static function zhStrings()
    {
        if (self::$zhStrings !== null) {
            return self::$zhStrings;
        }
        self::$zhStrings = array();
        $files = array();
        $theme = self::currentTheme();
        if ($theme !== '') {
            $files[] = ROOT_PATH . 'template/' . $theme . '/page-builder-components/_shared/language/strings_zh.properties';
        }
        $files[] = ROOT_PATH . 'template/_shared/kit/shared/language/strings_zh.properties';
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || $line[0] === '!') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                self::$zhStrings[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
            }
            break;
        }
        return self::$zhStrings;
    }

    /**
     * 文案是否仍是 i18n key 的默认中文：是则返回 key（可挂 data-lang），否则返回空串。
     *
     * 前端 runtime 会把每个 [data-lang] 的 textContent 换成字典值（中文也换）。
     * 站长在后台/搭建器改过的标题、改名后的分类名若仍挂着 key，就会先显示新名字、
     * 再被字典覆盖回旧名字（「过渡状态，改名不生效」）。自定义文案一律不挂 key。
     *
     * @param mixed  $text 实际要输出的文案
     * @param string $key  i18n key
     * @return string
     */
    public static function i18nKey($text, $key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }
        $text = trim((string) $text);
        if ($text === '') {
            return $key;
        }
        $map = self::zhStrings();
        if (!isset($map[$key])) {
            // 字典里没有该 key 时保持旧行为
            return $key;
        }
        $zh = $map[$key];
        if ($text === $zh) {
            return $key;
        }
        // data-lang-arg：「共%s章」类模板，按模式比对
        if (strpos($zh, '%s') !== false) {
            $re = '/^' . str_replace('%s', '.*', preg_quote($zh, '/')) . '$/su';
            if (preg_match($re, $text)) {
                return $key;
            }
        }
        return '';
    }

    /**
     * 内容模块入口名（频道条 / 底栏 / 小说胶囊 Tab 等）：
     * 模块只有一个启用中的顶级分类、且站长把它改了名时用分类名（已按内容语言 overlay），
     * 否则用内置文案 + i18n key。
     *
     * @param int $mid 1=视频 2=小说 12=漫画
     * @return array{name:string,lang:string,type_id:int}
     */
    public static function moduleLabel($mid)
    {
        $defaults = array(
            1 => array('视频', 'string_vod'),
            2 => array('小说', 'string_art'),
            12 => array('漫画', 'string_manga'),
        );
        $mid = (int) $mid;
        if (!isset($defaults[$mid])) {
            return array('name' => '', 'lang' => '', 'type_id' => 0);
        }
        $name = $defaults[$mid][0];
        $lang = $defaults[$mid][1];
        $typeId = 0;
        $tops = function_exists('mac_top_types') ? mac_top_types($mid) : array();
        if (count($tops) === 1) {
            $typeId = (int) $tops[0]['type_id'];
            $typeName = isset($tops[0]['type_name']) ? trim((string) $tops[0]['type_name']) : '';
            if ($typeName !== '' && $typeName !== $name) {
                $name = $typeName;
                $lang = '';
            }
        }
        return array('name' => $name, 'lang' => $lang, 'type_id' => $typeId);
    }
}
