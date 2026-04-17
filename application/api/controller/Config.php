<?php
namespace app\api\controller;

use think\Request;
use think\Db;

class Config extends Base
{
    use PublicApi;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    /**
     * 解析当前 PC 模板目录（与 Init 行为一致：优先 site_tpl_dir，否则 template_dir）
     */
    private function resolveTemplateDir()
    {
        $config = config('maccms');
        $site   = isset($config['site']) ? $config['site'] : [];
        $dir    = isset($site['site_tpl_dir']) ? trim((string) $site['site_tpl_dir']) : '';
        if ($dir === '') {
            $dir = isset($site['template_dir']) ? trim((string) $site['template_dir']) : '';
        }
        return $dir !== '' ? $dir : 'default';
    }

    public function get_config(Request $request)
    {
        $config = config('maccms');

        $banners = isset($config['site']['site_banner']) ? $config['site']['site_banner'] : '';
        $banner_list = [];
        if (!empty($banners)) {
            $banner_list = explode("\n", $banners);
            foreach ($banner_list as $k => &$v) {
                $v = mac_url_img($v);
            }
        }

        $res = [
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'site_banner' => $banner_list,
                'site_app_launch_image' => isset($config['site']['site_app_launch_image']) ? mac_url_img($config['site']['site_app_launch_image']) : '',
            ]
        ];
        return json($res)->options(['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 获取预留参数
     * 后台 网站参数配置 -> 预留参数 分页中的所有设置项
     * 包含：播放器排序、加密方式、热门搜索、各类扩展分类/地区/年份/语言、
     *       过滤词、自定义参数（extra_var）等
     *
     * 逗号分隔的字段会自动转为数组
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 示例：
     *   GET /api/config/get_extra_var          => 返回预留参数分页的所有配置
     */
    public function get_extra_var(Request $request)
    {
        $config = config('maccms');
        $app = isset($config['app']) ? $config['app'] : [];

        // 将逗号分隔的字符串转为数组，过滤空值
        $toArray = function($val) {
            if (empty($val)) return [];
            return array_values(array_filter(array_map('trim', explode(',', $val)), function($v) {
                return $v !== '';
            }));
        };

        // 预留参数分页中的所有字段（逗号分隔的转为数组）
        $data = [
            'player_sort'        => isset($app['player_sort']) ? $app['player_sort'] : '',
            'encrypt'            => isset($app['encrypt']) ? $app['encrypt'] : '0',
            'search_hot'         => $toArray(isset($app['search_hot']) ? $app['search_hot'] : ''),
            'art_extend_class'   => $toArray(isset($app['art_extend_class']) ? $app['art_extend_class'] : ''),
            'vod_extend_class'   => $toArray(isset($app['vod_extend_class']) ? $app['vod_extend_class'] : ''),
            'vod_extend_state'   => $toArray(isset($app['vod_extend_state']) ? $app['vod_extend_state'] : ''),
            'vod_extend_version' => $toArray(isset($app['vod_extend_version']) ? $app['vod_extend_version'] : ''),
            'vod_extend_area'    => $toArray(isset($app['vod_extend_area']) ? $app['vod_extend_area'] : ''),
            'vod_extend_lang'    => $toArray(isset($app['vod_extend_lang']) ? $app['vod_extend_lang'] : ''),
            'vod_extend_year'    => $toArray(isset($app['vod_extend_year']) ? $app['vod_extend_year'] : ''),
            'vod_extend_weekday' => $toArray(isset($app['vod_extend_weekday']) ? $app['vod_extend_weekday'] : ''),
            'actor_extend_area'  => $toArray(isset($app['actor_extend_area']) ? $app['actor_extend_area'] : ''),
            'filter_words'       => $toArray(isset($app['filter_words']) ? $app['filter_words'] : ''),
        ];

        // 自定义参数（extra_var 解析为 key-value）
        $extra = isset($config['extra']) && is_array($config['extra']) ? $config['extra'] : [];
        if (empty($extra) && !empty($app['extra_var'])) {
            $extra_var = str_replace(array(chr(10), chr(13)), array('', '#'), $app['extra_var']);
            $tmp = explode('#', $extra_var);
            foreach ($tmp as $a) {
                if (!empty($a)) {
                    $tmp2 = explode('$$$', $a);
                    if (count($tmp2) >= 2) {
                        $extra[trim($tmp2[0])] = trim($tmp2[1]);
                    }
                }
            }
        }
        $data['extra_var'] = $extra;

        return json([
            'code' => 1,
            'msg'  => lang('api/get_ok'),
            'data' => $data,
        ])->options(['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 获取前台所需的完整配置
     * 包含：站点基本信息 + 模板主题配置（tplconfig）
     *
     * 前端首页初始化时调用一次即可拿到所有配置信息，
     * 包括各区块的显示/隐藏开关、标题、数量、导航ID等。
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_tpl_config(Request $request)
    {
        $config      = config('maccms');
        $templateDir = $this->resolveTemplateDir();

        // 读取模板目录的 config.json（tplconfig）
        $tplconfig = [];
        $configFile = './template/' . $templateDir . '/config.json';
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $tplconfig = json_decode($content, true);
            if (!is_array($tplconfig)) {
                $tplconfig = [];
            }
        }

        // 站点基本信息
        $site = [
            'site_name'        => $config['site']['site_name'] ?? '',
            'site_url'         => $config['site']['site_url'] ?? '',
            'site_logo'        => !empty($config['site']['site_logo']) ? mac_url_img($config['site']['site_logo']) : '',
            'site_wap_logo'    => !empty($config['site']['site_wap_logo']) ? mac_url_img($config['site']['site_wap_logo']) : '',
            'site_keywords'    => $config['site']['site_keywords'] ?? '',
            'site_description' => $config['site']['site_description'] ?? '',
            'site_icp'         => $config['site']['site_icp'] ?? '',
            'site_email'       => $config['site']['site_email'] ?? '',
            'template_dir'     => $templateDir,
        ];

        // 功能开关
        $features = [
            'user_status'    => intval($config['user']['status'] ?? 0),
            'gbook_status'   => intval($config['gbook']['status'] ?? 0),
            'comment_status' => intval($config['comment']['status'] ?? 0),
        ];

        // 搜索相关
        $search = [
            'search_hot' => $config['app']['search_hot'] ?? '',
        ];

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'site'       => $site,
                'features'   => $features,
                'search'     => $search,
                'tpl_config' => $tplconfig,
            ],
        ])->options(['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 获取与 PC 模板一致的「主题配置」
     * GET /api.php/config/get_mctheme
     *
     * 对应前台 assign 的 $tplconfig 数据源：config('mctheme')（application/extra/mctheme.php + 后台主题落盘）。
     * 含 theme.ad_slots、theme.ads 等，供 SPA 渲染广告位、首页模块开关。
     */
    public function get_mctheme(Request $request)
    {
        $mctheme = config('mctheme');
        if (!is_array($mctheme)) {
            $mctheme = [];
        }
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'template_dir' => $this->resolveTemplateDir(),
                'mctheme'      => $mctheme,
            ],
        ])->options(['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 枚举当前模板「广告目录」下的 *.js 文件及可访问 URL
     * GET /api.php/config/get_ads_files
     *
     * 目录：template/{模板目录}/{ads_dir}/ ，ads_dir 来自站点配置（默认 ads），与 MAC_PATH_ADS 一致。
     */
    public function get_ads_files(Request $request)
    {
        $config      = config('maccms');
        $templateDir = $this->resolveTemplateDir();
        $adsDir      = !empty($config['site']['ads_dir']) ? trim((string) $config['site']['ads_dir']) : 'ads';
        $physical    = ROOT_PATH . 'template/' . $templateDir . '/' . $adsDir;

        $files = [];
        if (is_dir($physical)) {
            foreach (glob($physical . DIRECTORY_SEPARATOR . '*.js') ?: [] as $full) {
                if (!is_file($full)) {
                    continue;
                }
                $name = basename($full);
                $rel  = 'template/' . $templateDir . '/' . $adsDir . '/' . $name;
                $files[] = [
                    'name' => $name,
                    'path' => $rel,
                    'url'  => mac_url_img($rel),
                ];
            }
        }
        usort($files, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'template_dir' => $templateDir,
                'ads_dir'      => $adsDir,
                'files'        => $files,
            ],
        ])->options(['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);
    }
}
