<?php
namespace app\common\behavior;

use think\Cache;
use think\Exception;

class Init
{
    public function run(&$params)
    {
        // 主题配置已在 App::init() 中通过 extra 扫描加载，此处不再重复 include mctheme.php
        // 同步到 $GLOBALS 供模板与 mac_tpl_* 直接读取，避免重复 config() 解析
        $GLOBALS['mctheme'] = config('mctheme') ?: ['theme' => []];

        $config = config('maccms');
        if (!isset($config['app']) || !is_array($config['app'])) {
            $config['app'] = [];
        }
        if (!array_key_exists('security_headers_base', $config['app'])) {
            $config['app']['security_headers_base'] = '1';
        }
        if (!isset($config['meilisearch']) || !is_array($config['meilisearch'])) {
            $config['meilisearch'] = [
                'enabled' => '0',
                'host' => 'http://127.0.0.1:7700',
                'api_key' => '',
                'index_uid' => 'maccms_contents',
                'timeout' => '8',
                'sync_on_save' => '1',
                'search_only_wd' => '1',
            ];
        }
        if (!isset($config['template_cloud']) || !is_array($config['template_cloud'])) {
            $config['template_cloud'] = [
                'status' => '0',
                'catalog_url' => 'https://api.maccms.ai/templates/catalog.json',
                'cache_ttl' => '10800',
            ];
        }
        if (!isset($config['addon_cloud']) || !is_array($config['addon_cloud'])) {
            $config['addon_cloud'] = [
                'status' => '0',
                'catalog_url' => 'https://api.maccms.ai/addons/catalog.json',
                'cache_ttl' => '10800',
                'rate_limit' => '10',
                'audit_max' => '200',
                'legacy_catalog' => '1',
                'mock' => '0',
            ];
        } else {
            // 只补缺失键，不覆盖站长已设值（含显式 0）
            if (!array_key_exists('legacy_catalog', $config['addon_cloud'])) {
                $config['addon_cloud']['legacy_catalog'] = '1';
            }
            if (!array_key_exists('mock', $config['addon_cloud'])) {
                $config['addon_cloud']['mock'] = '0';
            }
        }
        $domain = config('domain');

        $isMobile = 0;
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|meizu|cldc|midp|iphone|wap|mobile|android)/i";
        if((preg_match($uachar, $ua))) {
            $isMobile = 1;
        }

        $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $resolved = self::resolveSite($config['site'], $domain, $http_host, $isMobile);
        $config['site'] = $resolved['site'];

        $TMP_ISWAP = $resolved['is_wap'];
        $TMP_TEMPLATEDIR = $resolved['template_dir'];
        $TMP_HTMLDIR = $resolved['html_dir'];
        $TMP_ADSDIR = $resolved['ads_dir'];

        define('MAC_URL','http'.'://'.'www'.'.'.'maccms'.'.'.'la'.'/');
        define('MAC_NAME','苹果CMS');
        define('MAC_PATH', $config['site']['install_dir'] .'');
        define('MAC_MOB', $TMP_ISWAP);
        define('MAC_ROOT_TEMPLATE', ROOT_PATH .'template/'.$TMP_TEMPLATEDIR.'/'. $TMP_HTMLDIR .'/');
        define('MAC_PATH_TEMPLATE', MAC_PATH.'template/'.$TMP_TEMPLATEDIR.'/');
        define('MAC_PATH_TPL', MAC_PATH_TEMPLATE. $TMP_HTMLDIR  .'/');
        define('MAC_PATH_ADS', MAC_PATH_TEMPLATE. $TMP_ADSDIR  .'/');
        define('MAC_PAGE_SP', $config['path']['page_sp'] .'');
        define('MAC_PLAYER_SORT', $config['app']['player_sort'] );
        define('MAC_ADDON_PATH', ROOT_PATH . 'addons' . '/');
        define('MAC_ADDON_PATH_STATIC', ROOT_PATH . 'static/addons/');

        $GLOBALS['MAC_ROOT_TEMPLATE'] = ROOT_PATH .'template/'.$TMP_TEMPLATEDIR.'/'. $TMP_HTMLDIR .'/';
        $GLOBALS['MAC_PATH_TEMPLATE'] = MAC_PATH.'template/'.$TMP_TEMPLATEDIR.'/';
        $GLOBALS['MAC_PATH_TPL'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_HTMLDIR  .'/';
        $GLOBALS['MAC_PATH_ADS'] = $GLOBALS['MAC_PATH_TEMPLATE']. $TMP_ADSDIR  .'/';

        $GLOBALS['http_type'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

        if(ENTRANCE=='index'){
            config('dispatch_success_tmpl','public/jump');
            config('dispatch_error_tmpl','public/jump');
        }

        config('template.view_path', 'template/' . $TMP_TEMPLATEDIR .'/' . $TMP_HTMLDIR .'/');

        if(ENTRANCE=='admin'){
            if(!file_exists('./template/' . $TMP_TEMPLATEDIR .'/' . $TMP_HTMLDIR .'/')){
                config('template.view_path','');
            }
        }
        if(intval($config['app']['search_len'])<1){
            $config['app']['search_len'] = 50;
        }
        config('url_route_on',$config['rewrite']['route_status']);
        if(empty($config['app']['pathinfo_depr'])){
            $config['app']['pathinfo_depr'] = '/';
        }
        config('pathinfo_depr',$config['app']['pathinfo_depr']);

        if(intval($config['app']['cache_time'])<1){
            $config['app']['cache_time'] = 60;
        }
        config('cache.expire', $config['app']['cache_time'] );


        if(!in_array($config['app']['cache_type'],['file','memcache','memcached','redis'])){
            $config['app']['cache_type'] = 'file';
        }
        if(!empty($config['app']['lang'])){
            config('default_lang', $config['app']['lang']);
        }

        config('cache.type', $config['app']['cache_type']);
        config('cache.timeout',1000);
        config('cache.host',$config['app']['cache_host']);
        config('cache.port',$config['app']['cache_port']);
        config('cache.username',$config['app']['cache_username']);
        config('cache.password',$config['app']['cache_password']);
        if($config['app']['cache_type'] == 'redis' && isset($config['app']['cache_db']) && intval($config['app']['cache_db']) > 0){
            config('cache.select', intval($config['app']['cache_db']));
        }
        if($config['app']['cache_type'] != 'file'){
            $opt = config('cache');
            Cache::$handler = null;
        }

        // 应用层修正静态资源替换串，避免改 thinkphp/View.php。
        // View 构造中历史写法 "$root . $static_path = '/static_new/'" 因运算符优先级
        // 实际变成给 $static_path 赋带尾斜杠的值，模板 __STATIC__/js 会渲染成 //js。
        // view_replace_str 在 View::instance 时后合并，可覆盖内核默认值。
        $req = \think\Request::instance();
        $base = $req->root();
        $root = strpos($base, '.') ? ltrim(dirname($base), DS) : $base;
        if ($root !== '') {
            $root = '/' . ltrim($root, '/');
        }
        // 旧版后台已移除，后台统一使用新版静态资源目录 static_new。
        // 不再依赖 site.new_version 判断（该键已废弃，避免 PHP7/PHP8 对 '' != 0 判定差异导致的目录漂移）。
        $staticPath = '/static_new';
        $staticReplace = array(
            '__STATIC__' => $root . $staticPath,
            '__CSS__'    => $root . $staticPath . '/css',
            '__JS__'     => $root . $staticPath . '/js',
        );
        $existReplace = config('view_replace_str');
        if (!is_array($existReplace)) {
            $existReplace = array();
        }
        config('view_replace_str', array_merge($existReplace, $staticReplace));

        $GLOBALS['config'] = $config;
    }

    /**
     * 站群（多域名）匹配。
     * 先按域名精确命中站群条目；未命中时，只有多域模式（mob_status=1）才反查
     * 该域名是否是某个站群条目自己的手机站域名，避免每次请求都无谓遍历。
     *
     * @param array  $domain_list config('domain')
     * @param string $host        当前 HTTP_HOST
     * @param mixed  $mob_status  站点手机端模式：0 关闭 / 1 多域 / 2 单域自适应
     * @return array|null ['entry'=>条目, 'is_wap_domain'=>0|1]
     */
    public static function matchDomain($domain_list, $host, $mob_status)
    {
        if (!is_array($domain_list) || empty($domain_list) || empty($host)) {
            return null;
        }
        if (!empty($domain_list[$host]) && is_array($domain_list[$host])) {
            return ['entry' => $domain_list[$host], 'is_wap_domain' => 0];
        }
        if ($mob_status == 1) {
            // 存量条目（手工编辑过 extra/domain.php、或插件写入）里的手机站域名未必
            // 已规范化，可能存着 http://m.a.com/ 这种带 scheme 和尾斜杠的值。
            // 两边都过一遍与写入侧同一个 mac_domain_host()，否则永远比不中。
            $host_lc = mac_domain_host($host);
            if ($host_lc !== '') {
                foreach ($domain_list as $one) {
                    if (is_array($one) && !empty($one['site_wapurl'])
                        && mac_domain_host($one['site_wapurl']) === $host_lc) {
                        return ['entry' => $one, 'is_wap_domain' => 1];
                    }
                }
            }
        }
        return null;
    }

    /**
     * 取站群条目自己配置的手机模板目录，未配置（缺键 / 空 / 'no'）一律返回空串。
     * 必须在与全局 site 配置 array_merge 之前取，否则全局 mob_template_dir
     * （默认 'default'，非空）会漏进来，让「该站未配手机模板」的判定失效。
     *
     * @param array $entry 站群条目原始数组
     * @return string
     */
    public static function domainMobTemplate($entry)
    {
        if (!is_array($entry) || !isset($entry['mob_template_dir'])) {
            return '';
        }
        $dir = trim($entry['mob_template_dir']);
        if ($dir === 'no') {
            return '';
        }
        return $dir;
    }

    /**
     * 选定本次请求使用的模板目录。
     * 站群场景下有两种切换信号：多域模式命中该条目自己的手机站域名，
     * 或自适应模式下该条目自己配了手机模板。两者都没有的存量条目保持 PC 模板。
     *
     * @param array  $site           已合并站群条目的 site 配置
     * @param int    $is_mobile      手机 UA
     * @param int    $is_domain      是否命中站群条目
     * @param int    $is_wap_domain  命中的是该条目的手机站域名
     * @param string $domain_mob_tpl 该条目自己配置的手机模板（空=未配置）
     * @param string $host           当前 HTTP_HOST
     * @return array ['is_wap'=>, 'template_dir'=>, 'html_dir'=>, 'ads_dir'=>]
     */
    public static function pickTemplate($site, $is_mobile, $is_domain, $is_wap_domain, $domain_mob_tpl, $host)
    {
        $is_wap = 0;
        $mob_status = isset($site['mob_status']) ? $site['mob_status'] : 0;

        if ($is_mobile) {
            if ($is_domain) {
                if ($mob_status == 1 && $is_wap_domain) {
                    // 访客正落在该条目自己的手机站域名上，站长意图已经明确：
                    // 即便条目没选手机模板也要切，此时 resolveSite 已把 mob_template_dir
                    // 备成全局手机模板（全局也没配才等于该站 PC 模板）。
                    // 否则「填了手机站域名却仍吐 PC 模板」，站长只会认为该字段不生效。
                    $is_wap = 1;
                } elseif ($mob_status == 2 && $domain_mob_tpl !== '') {
                    // 自适应模式没有域名这层显式信号，只认条目自己配的手机模板，
                    // 不能让所有存量站群条目在手机 UA 下突然改用全局手机模板。
                    $is_wap = 1;
                }
            } else {
                $wapurl = isset($site['site_wapurl']) ? $site['site_wapurl'] : '';
                if ($mob_status == 2 || ($mob_status == 1 && $wapurl !== '' && $host === $wapurl)) {
                    $is_wap = 1;
                }
            }
        }

        if ($is_wap) {
            return [
                'is_wap' => 1,
                'template_dir' => $site['mob_template_dir'],
                'html_dir' => $site['mob_html_dir'],
                'ads_dir' => $site['mob_ads_dir'],
            ];
        }
        return [
            'is_wap' => 0,
            'template_dir' => $site['template_dir'],
            'html_dir' => $site['html_dir'],
            'ads_dir' => $site['ads_dir'],
        ];
    }

    /**
     * 合并站群条目并选定模板目录。run() 的域名/手机端处理全部走这里，
     * 抽成无副作用的静态方法，便于在不引导框架、不连数据库的情况下回归测试。
     *
     * @param array  $site        全局 site 配置
     * @param array  $domain_list config('domain')
     * @param string $host        当前 HTTP_HOST
     * @param int    $is_mobile   手机 UA
     * @return array
     */
    public static function resolveSite($site, $domain_list, $host, $is_mobile)
    {
        $is_domain = 0;
        $is_wap_domain = 0;
        $domain_mob_tpl = '';

        $mob_status = isset($site['mob_status']) ? $site['mob_status'] : 0;
        $hit = self::matchDomain($domain_list, $host, $mob_status);

        if ($hit !== null) {
            $is_domain = 1;
            $is_wap_domain = $hit['is_wap_domain'];
            $domain_mob_tpl = self::domainMobTemplate($hit['entry']);
            $entry_wapurl = isset($hit['entry']['site_wapurl']) ? mac_domain_host($hit['entry']['site_wapurl']) : '';
            // 合并前留存全局模板：条目未配时要还给它，否则后台按 ac2=wap
            // 生成手机静态页（Make.php 直接读 site.mob_template_dir）会误用该站的 PC 模板。
            // html/ads 目录必须与模板目录成套留存：模板根是 template/{模板}/{html}/，
            // 回落用全局模板却配条目的 html 目录，会拼出 template/mobile/cn/ 这种不存在的组合。
            $global_mob_tpl = isset($site['mob_template_dir']) ? trim($site['mob_template_dir']) : '';
            $global_tpl = isset($site['template_dir']) ? trim($site['template_dir']) : '';
            $global_mob_html = isset($site['mob_html_dir']) ? $site['mob_html_dir'] : '';
            $global_mob_ads = isset($site['mob_ads_dir']) ? $site['mob_ads_dir'] : '';

            $site = array_merge($site, $hit['entry']);

            // 条目模板留在“请选择”(no) 或为空时回落全局模板，否则模板根会指向不存在的 template/no/
            $entry_tpl = isset($site['template_dir']) ? trim($site['template_dir']) : '';
            if ($entry_tpl === '' || $entry_tpl === 'no') {
                $site['template_dir'] = $global_tpl;
            }

            // 未配手机站域名的条目回落到本站域名，前端 Adaptive() 判定 url==wapurl 便不会跳转
            $site['site_wapurl'] = $entry_wapurl !== '' ? $entry_wapurl : $site['site_url'];
            // 模板目录与 html/ads 目录必须同源，否则模板根 template/{模板}/{html}/ 不成立
            if ($domain_mob_tpl !== '') {
                // 条目自己配了手机模板 -> 配条目自己的 html/ads
                $site['mob_template_dir'] = $domain_mob_tpl;
                $site['mob_html_dir'] = $site['html_dir'];
                $site['mob_ads_dir'] = $site['ads_dir'];
            } elseif ($global_mob_tpl !== '' && $global_mob_tpl !== 'no') {
                // 回落全局手机模板 -> html/ads 也必须取全局的
                $site['mob_template_dir'] = $global_mob_tpl;
                $site['mob_html_dir'] = $global_mob_html;
                $site['mob_ads_dir'] = $global_mob_ads;
            } else {
                // 全局也没配 -> 等同该站 PC 模板，整套用条目自己的
                $site['mob_template_dir'] = $site['template_dir'];
                $site['mob_html_dir'] = $site['html_dir'];
                $site['mob_ads_dir'] = $site['ads_dir'];
            }
        }

        $res = self::pickTemplate($site, $is_mobile, $is_domain, $is_wap_domain, $domain_mob_tpl, $host);
        $res['site'] = $site;
        $res['is_domain'] = $is_domain;
        $res['is_wap_domain'] = $is_wap_domain;

        return $res;
    }
}
