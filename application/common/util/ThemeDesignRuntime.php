<?php
namespace app\common\util;

/**
 * 主题设计器前台运行时：读 theme-design.json、首页 blocks/sections、override CSS。
 */
class ThemeDesignRuntime
{
    /**
     * 读取 theme-design.json / theme-design.draft.json。
     *
     * @return array
     */
    public static function config()
    {
        static $cache = array();

        $preview = ThemePreviewAuth::isActive() ? 1 : 0;
        $tpl = isset($GLOBALS['config']['site']['template_dir'])
            ? (string) $GLOBALS['config']['site']['template_dir'] : '';

        $key = ($preview ? 'draft' : 'pub') . '|' . $tpl;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $data = array();
        if ($tpl !== '' && defined('ROOT_PATH')) {
            $file = ROOT_PATH . 'template/' . $tpl . '/theme-design' . ($preview ? '.draft' : '') . '.json';
            if (is_file($file)) {
                $json = @file_get_contents($file);
                if ($json !== false && $json !== '') {
                    $arr = json_decode($json, true);
                    if (is_array($arr)) {
                        $data = $arr;
                    }
                }
            }
        }

        $cache[$key] = $data;
        return $cache[$key];
    }

    /**
     * 首页 section 设计属性（num / layout 等）。
     *
     * @param string $key
     * @return array
     */
    public static function homepageSection($key)
    {
        static $map = null;
        if ($map === null) {
            $map = array();
            $td = self::config();
            if (!empty($td['homepage']['sections']) && is_array($td['homepage']['sections'])) {
                foreach ($td['homepage']['sections'] as $s) {
                    if (!empty($s['key'])) {
                        $map[$s['key']] = $s;
                    }
                }
            }
        }
        $key = (string) $key;

        return isset($map[$key]) ? $map[$key] : array();
    }

    /**
     * 首页 Page Builder blocks[]。
     *
     * @return array
     */
    public static function homepageBlocks()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $td = self::config();
        $home = isset($td['homepage']) && is_array($td['homepage']) ? $td['homepage'] : array();
        if (!empty($home['page']['blocks']) && is_array($home['page']['blocks'])) {
            $cache = $home['page']['blocks'];
            return $cache;
        }

        $kit = new PageBuilderKit();
        $def = $kit->defaultHomepagePage();
        $cache = isset($def['blocks']) ? $def['blocks'] : array();
        return $cache;
    }

    /**
     * 主题设计器 override CSS 完整 URL；不存在时返回空字符串。
     *
     * @return string
     */
    public static function overrideCssHref()
    {
        $preview = ThemePreviewAuth::isActive();
        $tpl = isset($GLOBALS['config']['site']['template_dir'])
            ? (string) $GLOBALS['config']['site']['template_dir'] : '';
        if ($tpl === '' || !defined('ROOT_PATH')) {
            return '';
        }
        $suffix = $preview ? '.draft' : '';
        $file = ROOT_PATH . 'template/' . $tpl . '/asset/css/theme-override' . $suffix . '.css';
        if (!is_file($file)) {
            return '';
        }
        $base = '';
        if (isset($GLOBALS['MAC_PATH_TEMPLATE'])) {
            $base = (string) $GLOBALS['MAC_PATH_TEMPLATE'];
        } elseif (isset($GLOBALS['config']['site']['install_dir'])) {
            $base = (string) $GLOBALS['config']['site']['install_dir'] . 'template/' . $tpl . '/';
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

        return $base . 'asset/css/theme-override' . $suffix . '.css';
    }

    /**
     * Page Builder 预览：库中无网址数据时的占位记录。
     *
     * @param array $param
     * @return array
     */
    public static function previewStubWebsite($param = array())
    {
        $id = isset($param['id']) ? (int) $param['id'] : 1;
        if ($id < 1) {
            $id = 1;
        }

        return array(
            'website_id' => $id,
            'website_name' => 'Preview Website',
            'website_sub' => '',
            'website_en' => 'preview-website',
            'website_tpl' => '',
            'website_status' => 1,
            'type_id' => 0,
            'type' => array(
                'type_id' => 0,
                'type_pid' => 0,
                'type_tpl_detail' => 'detail',
            ),
        );
    }
}
