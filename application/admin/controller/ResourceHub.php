<?php
namespace app\admin\controller;
use think\Db;
use think\Cache;
use app\common\util\Pinyin;
use app\common\util\ResourceHubSites;
use app\common\util\CollectHealth;
use app\common\util\HttpClient;

/**
 * 资源站中心控制器
 * 聚合全网资源站，提供一键采集、自动分类绑定、自动播放器配置等功能
 */
class ResourceHub extends Base
{
    public function __construct()
    {
        parent::__construct();
        // admin 模块统一使用新版后台模板目录 view_new/。
        // Init 会把 template.view_path 指向前台模板目录，模块相对 fetch 需显式指定，否则会白屏。
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');

    }

    /**
     * 验证远端 URL 是否安全（防止 SSRF）
     * 只允许 http/https，禁止内网 IP、回环地址、云元数据地址
     * @return bool
     */
    private function validateRemoteUrl($url)
    {
        $parts = parse_url((string)$url);
        if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        if (empty($parts['host'])) {
            return false;
        }
        // 与内容/接口检测同一公网判定（含 CGNAT、链路本地、数字写法 IP、仅 IPv6 主机）
        return HttpClient::resolvePublicIp($parts['host']) !== '';
    }

    /**
     * 安全的远端请求方法（防止 SSRF）
     * 复用 validateRemoteUrl 校验后发起请求
     */
    private function fetchRemote($url)
    {
        if (!$this->validateRemoteUrl($url)) {
            return false;
        }
        // 采集站等第三方源可能自签/过期/域名不匹配，显式关闭 TLS 校验以保兼容；
        // 连接钉在已校验的公网 IP，跳转逐跳重新校验（mac_curl_get 会自动跟随 302 绕过首跳校验）
        return HttpClient::safeGet($url, 30, false);
    }

    /**
     * 从云端拉取资源站目录（加密传输 + 本地缓存），逻辑见 ResourceHubSites::cloud()
     * @return array
     */
    private function fetchCloudSites()
    {
        return ResourceHubSites::cloud();
    }

    /**
     * 载入用户自定义资源站（独立文件，与云端隔离）
     * @return array
     */
    private function loadCustomSites()
    {
        return ResourceHubSites::custom();
    }

    /**
     * 储存用户自定义资源站
     * @param array $sites
     * @return bool
     */
    private function saveCustomSites(array $sites)
    {
        return ResourceHubSites::saveCustom($sites);
    }

    /**
     * 即时读取分类绑定配置（直接读文件，绕过 config 缓存 / 多进程 opcache 旧值）
     * 自动绑定属于「写回整个文件」的操作，必须基于磁盘最新内容，
     * 否则可能用旧的绑定覆盖掉用户手动调整的绑定。
     * @return array
     */
    private function loadBindConfig()
    {
        $file = APP_PATH . 'extra/bind.php';
        if (is_file($file)) {
            // 清理 opcache，确保读到最新文件内容
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
            $data = include $file;
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * 资源站目录首页
     */
    public function index()
    {
        // 云端拉取
        $cloudSites = $this->fetchCloudSites();
        // 用户自定义
        $customSites = $this->loadCustomSites();

        // 按分类分组
        $categoryNames = [
            'main' => lang('admin/resourcehub/cat_main') ?: '综合资源站（推荐）',
            'vod' => lang('admin/resourcehub/cat_vod') ?: '影视资源站',
            'short' => lang('admin/resourcehub/cat_short') ?: '短剧资源站',
            'anime' => lang('admin/resourcehub/cat_anime') ?: '动漫资源站',
            'art' => lang('admin/resourcehub/cat_art') ?: '资讯资源站',
            'midnight' => lang('admin/resourcehub/cat_midnight') ?: '午夜资源站',
            'custom' => lang('admin/resourcehub/cat_custom') ?: '自定义资源站',
        ];

        $grouped = [];

        // 整合云端站点
        foreach ($cloudSites as $site) {
            $cat = $site['category'] ?? 'vod';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'title' => $categoryNames[$cat] ?? $cat,
                    'sites' => [],
                ];
            }
            $grouped[$cat]['sites'][] = [
                'name' => $site['name'] ?? '',
                'url' => $site['url'] ?? '',
                'type' => $site['type'] ?? '2',
                'mid' => '1',
                'desc' => $site['remark'] ?? '',
                'recommend' => $site['recommend'] ?? 0,
                'verified' => $site['verified'] ?? false,
            ];
        }

        // 整合自定义站点（独立分类）
        if (!empty($customSites)) {
            $grouped['custom'] = [
                'title' => $categoryNames['custom'],
                'sites' => [],
            ];
            foreach ($customSites as $site) {
                $grouped['custom']['sites'][] = [
                    'name' => $site['name'] ?? '',
                    'url' => $site['url'] ?? '',
                    'type' => $site['type'] ?? '2',
                    'mid' => $site['mid'] ?? '1',
                    'desc' => $site['desc'] ?? '',
                    'recommend' => 0,
                    'verified' => false,
                ];
            }
        }

        // 按固定顺序排列
        $ordered = [];
        foreach (['main', 'vod', 'short', 'anime', 'art', 'midnight', 'custom'] as $key) {
            if (isset($grouped[$key]) && !empty($grouped[$key]['sites'])) {
                $ordered[$key] = $grouped[$key];
            }
        }

        // 接口可用性状态（无记录视为启用）
        $healthAll = CollectHealth::all();
        foreach ($ordered as $zk => $zone) {
            foreach ($zone['sites'] as $si => $site) {
                $hk = CollectHealth::key($site['url']);
                $ordered[$zk]['sites'][$si]['health_key'] = $hk;
                $ordered[$zk]['sites'][$si]['health'] = isset($healthAll[$hk]) ? $healthAll[$hk] : null;
            }
        }

        // 云端拉取失败提示
        $cloudError = (empty($cloudSites) && empty($customSites));

        $this->assign('sites', $ordered);
        $this->assign('cloud_error', $cloudError);
        $this->assign('title', lang('admin/resourcehub/title'));
        return $this->fetch('resourcehub/index');
    }

    /**
     * 检测资源站API是否可用
     */
    public function check()
    {
        $param = input();
        $url = $param['url'];
        if (empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $test_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=list&pg=1';
        $html = $this->fetchRemote($test_url);
        if (empty($html)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
        }

        // 尝试解析JSON
        $json = json_decode($html, true);
        if ($json && isset($json['list'])) {
            return json(['code' => 1, 'msg' => lang('admin/resourcehub/check_ok'), 'data' => ['total' => $json['total'] ?? 0]]);
        }

        // 尝试解析XML
        $xml = @simplexml_load_string($html);
        if ($xml) {
            return json(['code' => 1, 'msg' => lang('admin/resourcehub/check_ok'), 'data' => ['total' => (string)$xml->list->attributes()->recordcount]]);
        }

        return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
    }

    /**
     * 获取资源站分类列表
     */
    public function getTypes()
    {
        $param = input();
        $url = $param['url'];
        $type = $param['type'] ?? '2';

        if (empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $api_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=list&pg=1';
        $html = $this->fetchRemote($api_url);
        if (empty($html)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/get_types_fail')]);
        }

        $types = [];
        if ($type == '2') {
            // JSON 格式
            $json = json_decode($html, true);
            if ($json && isset($json['class'])) {
                foreach ($json['class'] as $v) {
                    $name = mac_filter_xss(trim($v['type_name'] ?? ''));
                    if (empty($name) || mb_strlen($name) > 30) continue;
                    $types[] = [
                        'id' => $v['type_id'],
                        'name' => $name,
                        'pid' => $v['type_pid'] ?? 0,
                    ];
                }
            }
        } else {
            // XML 格式
            $xml = @simplexml_load_string($html);
            if ($xml && $xml->class) {
                foreach ($xml->class->ty as $v) {
                    $name = mac_filter_xss(trim((string)$v));
                    if (empty($name) || mb_strlen($name) > 30) continue;
                    $types[] = [
                        'id' => (string)$v->attributes()->id,
                        'name' => $name,
                        'pid' => 0,
                    ];
                }
            }
        }

        // 获取本地分类
        $local_types = model('Type')->getCache('type_list');
        $local_type_names = [];
        if (!empty($local_types)) {
            foreach ($local_types as $lt) {
                $local_type_names[] = $lt['type_name'];
            }
        }

        // 标记是否已存在
        foreach ($types as &$t) {
            $t['exists'] = in_array($t['name'], $local_type_names);
        }

        return json(['code' => 1, 'msg' => 'ok', 'data' => ['types' => $types, 'local_types' => $local_types]]);
    }

    /**
     * 一键同步分类到本地
     */
    public function syncTypes()
    {
        $param = input('post.');
        $type_names = $param['type_names'] ?? [];
        $pid = intval($param['pid'] ?? 0);

        if (empty($type_names)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 强制从数据库读取，避免缓存导致重复新增
        Cache::rm('cache_type');
        $local_types_db = Db::name('type')->select();
        $local_type_names = [];
        if (!empty($local_types_db)) {
            foreach ($local_types_db as $lt) {
                $local_type_names[$lt['type_name']] = $lt['type_id'];
            }
        }

        $added = 0;
        $skipped = 0;
        foreach ($type_names as $name) {
            $name = mac_filter_xss(trim($name));
            if (empty($name) || mb_strlen($name) > 30) continue;

            // 检查是否已存在（精确匹配）
            if (isset($local_type_names[$name])) {
                $skipped++;
                continue;
            }

            // 再次从数据库确认（防止并发重复）
            $exists = Db::name('type')->where('type_name', $name)->find();
            if ($exists) {
                $skipped++;
                $local_type_names[$name] = $exists['type_id'];
                continue;
            }

            // 新增分类
            $data = [
                'type_name' => $name,
                'type_en' => Pinyin::get($name),
                'type_pid' => $pid,
                'type_mid' => 1,
                'type_status' => 1,
                'type_sort' => 0,
                'type_extend' => '',
                'type_key' => '',
                'type_des' => '',
                'type_title' => '',
                'type_union' => '',
                'type_tpl' => '',
                'type_tpl_list' => '',
                'type_tpl_detail' => '',
                'type_tpl_play' => '',
                'type_tpl_down' => '',
                'type_logo' => '',
                'type_pic' => '',
            ];

            $res = Db::name('type')->insert($data);
            if ($res) {
                $added++;
                // 加入本地列表，防止同批次重复
                $local_type_names[$name] = Db::name('type')->getLastInsID();
            }
        }

        // 清除分类缓存
        Cache::rm('cache_type');
        
        return json([
            'code' => 1,
            'msg' => sprintf(lang('admin/resourcehub/sync_types_ok'), $added, $skipped)
        ]);
    }

    /**
     * 自动绑定分类
     */
    public function autoBind()
    {
        $param = input('post.');
        $url = $param['url'] ?? '';
        $type = $param['type'] ?? '2';

        if (empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 获取资源站分类
        $api_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=list&pg=1';
        $html = $this->fetchRemote($api_url);
        if (empty($html)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/get_types_fail')]);
        }

        $remote_types = [];
        if ($type == '2') {
            $json = json_decode($html, true);
            if ($json && isset($json['class'])) {
                foreach ($json['class'] as $v) {
                    $remote_types[$v['type_id']] = $v['type_name'];
                }
            }
        } else {
            $xml = @simplexml_load_string($html);
            if ($xml && $xml->class) {
                foreach ($xml->class->ty as $v) {
                    $remote_types[(string)$v->attributes()->id] = (string)$v;
                }
            }
        }

        if (empty($remote_types)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/no_remote_types')]);
        }

        // 强制从数据库读取本地分类（不使用缓存，确保最新）
        Cache::rm('cache_type');
        $local_types_db = Db::name('type')->select();
        $local_type_map = [];
        $local_type_list = [];
        if (!empty($local_types_db)) {
            foreach ($local_types_db as $lt) {
                $local_type_map[$lt['type_name']] = $lt['type_id'];
                $local_type_list[] = $lt;
            }
        }

        // 读取自定义规则
        $config = config('maccms.collect');
        $custom_rules = [];
        if (!empty($config['vod_type_map'])) {
            $rules = explode("\n", $config['vod_type_map']);
            foreach ($rules as $rule) {
                $rule = trim($rule);
                if (empty($rule) || strpos($rule, '=') === false) continue;
                list($local_name, $remote_name) = explode('=', $rule, 2);
                $custom_rules[trim($remote_name)] = trim($local_name);
            }
        }

        // 执行绑定
        $cjflag = md5($url);
        // 即时从磁盘读取最新绑定，避免用旧值覆盖用户手动调整的绑定
        $bind_config = $this->loadBindConfig();
        $bound = 0;
        $changed = false;

        foreach ($remote_types as $remote_id => $remote_name) {
            $bind_key = $cjflag . '_' . $remote_id;

            // 如果已绑定则跳过（绝不覆盖用户已设定的绑定）
            if (isset($bind_config[$bind_key]) && $bind_config[$bind_key] > 0) {
                $bound++;
                continue;
            }

            // 1. 精确匹配
            if (isset($local_type_map[$remote_name])) {
                $bind_config[$bind_key] = $local_type_map[$remote_name];
                $bound++;
                $changed = true;
                continue;
            }

            // 2. 自定义规则
            if (isset($custom_rules[$remote_name]) && isset($local_type_map[$custom_rules[$remote_name]])) {
                $bind_config[$bind_key] = $local_type_map[$custom_rules[$remote_name]];
                $bound++;
                $changed = true;
                continue;
            }

            // 3. 模糊匹配：去除常见后缀/前缀再比对
            $matched_id = $this->fuzzyMatchType($remote_name, $local_type_list);
            if ($matched_id > 0) {
                $bind_config[$bind_key] = $matched_id;
                $bound++;
                $changed = true;
                continue;
            }
        }

        // 仅当确实新增了绑定时才写回文件，避免无意义的整文件覆盖
        if ($changed) {
            $res = mac_arr2file(APP_PATH . 'extra/bind.php', $bind_config);
            if ($res === false) {
                return json(['code' => 0, 'msg' => lang('write_err_config')]);
            }
        }

        return json([
            'code' => 1,
            'msg' => sprintf(lang('admin/resourcehub/auto_bind_ok'), $bound, count($remote_types))
        ]);
    }

    /**
     * 模糊匹配分类名称
     * 匹配优先级：去后缀精确匹配 → 同义词匹配 → 包含匹配（最弱）
     * 同义词表从 application/extra/type_synonyms.php 载入，用户可自行扩展
     */
    private function fuzzyMatchType($remote_name, $local_type_list)
    {
        $remote_name = trim($remote_name);
        if (empty($remote_name)) return 0;

        // 常见后缀，去除后再匹配
        $suffixes = ['片', '劇', '剧', '类', '類'];
        $remote_stripped = $remote_name;
        foreach ($suffixes as $suffix) {
            if (mb_substr($remote_name, -mb_strlen($suffix)) === $suffix && mb_strlen($remote_name) > mb_strlen($suffix)) {
                $remote_stripped = mb_substr($remote_name, 0, -mb_strlen($suffix));
                break;
            }
        }

        // 载入外部同义词配置
        $synonyms_file = APP_PATH . 'extra/type_synonyms.php';
        $synonyms = is_file($synonyms_file) ? include $synonyms_file : [];

        // === 第一轮：去后缀精确匹配（强规则） ===
        foreach ($local_type_list as $lt) {
            $local_name = $lt['type_name'];
            $local_id = $lt['type_id'];

            $local_stripped = $local_name;
            foreach ($suffixes as $suffix) {
                if (mb_substr($local_name, -mb_strlen($suffix)) === $suffix && mb_strlen($local_name) > mb_strlen($suffix)) {
                    $local_stripped = mb_substr($local_name, 0, -mb_strlen($suffix));
                    break;
                }
            }

            // 去后缀后精确匹配
            if ($remote_stripped === $local_stripped && !empty($remote_stripped)) {
                return $local_id;
            }
            if ($remote_stripped === $local_name || $remote_name === $local_stripped) {
                return $local_id;
            }
        }

        // === 第二轮：同义词匹配（中等规则） ===
        foreach ($local_type_list as $lt) {
            $local_name = $lt['type_name'];
            $local_id = $lt['type_id'];

            $local_stripped = $local_name;
            foreach ($suffixes as $suffix) {
                if (mb_substr($local_name, -mb_strlen($suffix)) === $suffix && mb_strlen($local_name) > mb_strlen($suffix)) {
                    $local_stripped = mb_substr($local_name, 0, -mb_strlen($suffix));
                    break;
                }
            }

            foreach ($synonyms as $key => $values) {
                $all_names = array_merge([$key], $values);
                $remote_in = in_array($remote_name, $all_names) || in_array($remote_stripped, $all_names);
                $local_in = in_array($local_name, $all_names) || in_array($local_stripped, $all_names);
                if ($remote_in && $local_in) {
                    return $local_id;
                }
            }
        }

        // === 第三轮：包含匹配（最弱规则，收紧条件） ===
        // 要求：短边 ≥ 3 字符，且短边 / 长边 ≥ 60%
        foreach ($local_type_list as $lt) {
            $local_name = $lt['type_name'];
            $local_id = $lt['type_id'];

            if (mb_strlen($remote_name) >= 3 && mb_strlen($local_name) >= 3) {
                $shortLen = min(mb_strlen($remote_name), mb_strlen($local_name));
                $longLen = max(mb_strlen($remote_name), mb_strlen($local_name));
                if ($longLen > 0 && ($shortLen / $longLen) >= 0.6) {
                    if (mb_strpos($remote_name, $local_name) !== false || mb_strpos($local_name, $remote_name) !== false) {
                        return $local_id;
                    }
                }
            }

            // 去后缀后的包含匹配（同样收紧）
            $local_stripped = $local_name;
            foreach ($suffixes as $suffix) {
                if (mb_substr($local_name, -mb_strlen($suffix)) === $suffix && mb_strlen($local_name) > mb_strlen($suffix)) {
                    $local_stripped = mb_substr($local_name, 0, -mb_strlen($suffix));
                    break;
                }
            }

            if (mb_strlen($remote_stripped) >= 3 && mb_strlen($local_stripped) >= 3) {
                $shortLen = min(mb_strlen($remote_stripped), mb_strlen($local_stripped));
                $longLen = max(mb_strlen($remote_stripped), mb_strlen($local_stripped));
                if ($longLen > 0 && ($shortLen / $longLen) >= 0.6) {
                    if (mb_strpos($remote_stripped, $local_stripped) !== false || mb_strpos($local_stripped, $remote_stripped) !== false) {
                        return $local_id;
                    }
                }
            }
        }

        return 0;
    }

    /**
     * 自动添加播放器配置
     */
    public function autoPlayer()
    {
        $param = input('post.');
        $url = $param['url'] ?? '';
        $type = $param['type'] ?? '2';

        if (empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 获取资源站数据，检测播放器标识
        $api_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=detail&pg=1';
        $html = $this->fetchRemote($api_url);
        if (empty($html)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/get_data_fail')]);
        }

        $player_samples = $this->detectPlayerFroms($html, $type);
        if (empty($player_samples)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/no_player_found')]);
        }

        $res = $this->installAutoPlayers($player_samples);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }

        $player_froms = array_keys($player_samples);
        $msg = sprintf(lang('admin/resourcehub/auto_player_ok'), $res['added'], count($player_froms));
        if (!empty($res['iframe'])) {
            $msg .= ' ' . sprintf(lang('admin/resourcehub/auto_player_iframe'), implode(', ', $res['iframe']));
        }
        if ($res['repaired'] > 0) {
            $msg .= ' ' . sprintf(lang('admin/resourcehub/auto_player_repaired'), $res['repaired']);
        }
        return json([
            'code' => 1,
            'msg' => $msg,
            'data' => ['players' => $player_froms, 'added' => $res['added'], 'repaired' => $res['repaired'], 'iframe' => $res['iframe']]
        ]);
    }

    /**
     * 从资源站 ac=detail 的返回里收集播放器标识，以及每个标识下的若干条样例播放地址。
     *
     * @return array [from => [url, ...]]，保持资源站给出的顺序
     */
    private function detectPlayerFroms($html, $type)
    {
        $samples = [];
        $push = function ($from, $url_str) use (&$samples) {
            $from = trim((string)$from);
            if ($from === '') {
                return;
            }
            if (!isset($samples[$from])) {
                $samples[$from] = [];
            }
            foreach (explode('#', (string)$url_str) as $ep) {
                if (count($samples[$from]) >= 5) {
                    break;
                }
                $ep = trim($ep);
                if ($ep === '') {
                    continue;
                }
                $pos = strpos($ep, '$');
                $u = trim($pos === false ? $ep : substr($ep, $pos + 1));
                // “名称$地址$来源”三段式时只取地址
                if (($p2 = strpos($u, '$')) !== false) {
                    $u = substr($u, 0, $p2);
                }
                if ($u !== '') {
                    $samples[$from][] = $u;
                }
            }
        };

        if ($type == '2') {
            $json = json_decode($html, true);
            if ($json && !empty($json['list'])) {
                foreach ($json['list'] as $item) {
                    if (empty($item['vod_play_from'])) {
                        continue;
                    }
                    $froms = explode('$$$', $item['vod_play_from']);
                    $urls = explode('$$$', (string)($item['vod_play_url'] ?? ''));
                    foreach ($froms as $i => $from) {
                        $push($from, $urls[$i] ?? '');
                    }
                }
            }
        } else {
            $xml = @simplexml_load_string($html);
            if ($xml && $xml->list->video) {
                foreach ($xml->list->video as $video) {
                    $found = false;
                    if (isset($video->dl->dd)) {
                        foreach ($video->dl->dd as $dd) {
                            $flag = (string)$dd['flag'];
                            if ($flag !== '') {
                                $push($flag, (string)$dd);
                                $found = true;
                            }
                        }
                    }
                    if (!$found) {
                        $dt = (string)$video->dt;
                        foreach (explode('$$$', $dt) as $from) {
                            $push($from, '');
                        }
                    }
                }
            }
        }
        return $samples;
    }

    /**
     * 根据样例地址判断该线路应使用的播放器类型。
     *   dplayer：直链媒体（m3u8/mp4/flv…），用本站 DPlayer 直接播放
     *   iframe ：普通网页地址（资源站“云播/分享页”，自带播放器），只能整页嵌入
     * 判断不出来（非 URL 的 ID 类地址等）时按 dplayer 处理，保持旧行为。
     */
    private function detectPlayerKind(array $urls)
    {
        $media = 0;
        $page = 0;
        foreach ($urls as $u) {
            $path = (string)parse_url($u, PHP_URL_PATH);
            if (preg_match('/\.(m3u8|mp4|flv|mpd|m4v|webm|mkv|mov|ts)$/i', $path)) {
                $media++;
            } elseif (preg_match('#^(https?:)?//#i', $u)) {
                $page++;
            }
        }
        return ($page > $media) ? 'iframe' : 'dplayer';
    }

    /**
     * 把侦测到的线路写入播放器配置（extra/vodplayer.php + static/player/{from}.js），
     * 并立即刷新前台 playerconfig.js。
     *
     * 已存在的播放器配置不覆盖，但播放器 JS 会按线路类型校正：
     *   - JS 缺失/为空且未开启解析（ps≠1）→ 前台什么都不渲染，按类型生成；
     *   - JS 是本站自带模板（DPlayer 或 iframe 的任一版本）但类型不对 → 改写为正确模板
     *     （例如分享页线路用了 DPlayer、或管理员复制 DPlayer 改编码给云播线路用）。
     * 管理员自己写的 JS（非自带模板）一律不动。
     *
     * @param array $player_samples detectPlayerFroms() 的返回
     * @return array|false ['added'=>int,'repaired'=>int,'iframe'=>[from...]]；写配置失败返回 false
     */
    private function installAutoPlayers(array $player_samples)
    {
        $vodplayer_list = config('vodplayer') ?: [];
        $added = 0;
        $repaired = 0;
        $iframe = [];
        $changed = false;

        foreach ($player_samples as $from => $urls) {
            // 安全检查：播放器标识只允许字母/数字/下划线/短横线（防止目录穿越）
            if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $from)) {
                continue;
            }
            $kind = $this->detectPlayerKind($urls);
            $js_path = ROOT_PATH . 'static/player/' . $from . '.js';
            $exists = isset($vodplayer_list[$from]);

            if (!$exists) {
                $vodplayer_list[$from] = [
                    'status' => '1',
                    'from' => $from,
                    'show' => sprintf(lang('admin/resourcehub/auto_player_show'), $from),
                    'des' => lang('admin/resourcehub/auto_player_des'),
                    'target' => '_self',
                    'ps' => '0',
                    'parse' => '',
                    // 直链线路排在网页线路前面：前台按 sort 排序线路，默认播放第一条
                    'sort' => $kind === 'iframe' ? '799' : '800',
                    'tip' => lang('admin/resourcehub/auto_player_tip'),
                    'id' => $from,
                ];
                $changed = true;
                $added++;
                if ($kind === 'iframe') {
                    $iframe[] = $from;
                }
            } elseif ((string)($vodplayer_list[$from]['ps'] ?? '0') === '1') {
                // 走解析接口时前台用 parse.js，不读该 JS
                continue;
            }

            $js = is_file($js_path) ? (string)@file_get_contents($js_path) : '';
            $current = trim($js) === '' ? '' : $this->stockPlayerJsKind($js);
            if ($current === $kind) {
                continue;
            }
            if ($current === null) {
                // 自定义 JS，保留
                continue;
            }
            if (@file_put_contents($js_path, $this->generatePlayerJs($from, $kind)) !== false && $exists) {
                $repaired++;
                if ($kind === 'iframe') {
                    $iframe[] = $from;
                }
            }
        }

        if ($changed) {
            $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $vodplayer_list);
            if ($res === false) {
                return false;
            }
            config('vodplayer', $vodplayer_list);
            cache('cache_data', '1');
        }
        if ($changed || $repaired > 0) {
            $this->_player_config_refresh();
        }
        return ['added' => $added, 'repaired' => $repaired, 'iframe' => $iframe];
    }

    /**
     * 判断播放器 JS 是否为本站自带模板，返回其类型：
     *   'dplayer' —— 嵌入 /static/player/dplayer.html 的单行模板（含历史各版本：有无 ?v=、有无 allowfullscreen）
     *   'iframe'  —— 直接 iframe 嵌入 MacPlayer.PlayUrl 的模板（static/player/iframe.js）
     *   null      —— 管理员自定义的 JS
     */
    private function stockPlayerJsKind($js)
    {
        $js = trim((string)$js);
        if (strlen($js) > 1024) {
            return null;
        }
        if (preg_match('#^MacPlayer\.Html\s*=\s*\'<iframe border="0" src="\'\+maccms\.path\+\'/static/player/dplayer\.html(\?v=[\w.]+)?"[^\']*></iframe>\';\s*MacPlayer\.Show\(\);?$#', $js)) {
            return 'dplayer';
        }
        if (preg_match('#^MacPlayer\.Html\s*=\s*\'<iframe width="100%" height="100%" src="\'\+MacPlayer\.PlayUrl\+\'"[^\']*></iframe>\';\s*MacPlayer\.Show\(\);?$#', $js)) {
            return 'iframe';
        }
        return null;
    }

    /**
     * 生成播放器JS文件内容
     *   dplayer：与内置 static/player/dplayer.js 相同
     *   iframe ：与内置 static/player/iframe.js 相同（整页嵌入资源站自带播放页）
     */
    private function generatePlayerJs($from, $kind = 'dplayer')
    {
        if ($kind === 'iframe') {
            $tpl = ROOT_PATH . 'static/player/iframe.js';
            $fallback = 'MacPlayer.Html = \'<iframe width="100%" height="100%" src="\'+MacPlayer.PlayUrl+\'" frameborder="0" border="0" marginwidth="0" marginheight="0" scrolling="no" allowfullscreen="allowfullscreen" mozallowfullscreen="mozallowfullscreen" msallowfullscreen="msallowfullscreen" oallowfullscreen="oallowfullscreen" webkitallowfullscreen="webkitallowfullscreen" security="restricted" sandbox="allow-same-origin allow-forms allow-scripts"></iframe>\';
MacPlayer.Show();
';
        } else {
            $tpl = ROOT_PATH . 'static/player/dplayer.js';
            $fallback = 'MacPlayer.Html = \'<iframe border="0" src="\'+maccms.path+\'/static/player/dplayer.html" width="100%" height="100%" marginWidth="0" frameSpacing="0" marginHeight="0" frameBorder="0" scrolling="no" allowfullscreen="true" allow="fullscreen" vspale="0" noResize></iframe>\';
MacPlayer.Show();
';
        }
        $js = is_file($tpl) ? (string)@file_get_contents($tpl) : '';
        return trim($js) !== '' ? $js : $fallback;
    }

    /**
     * 一键添加资源站到采集列表
     */
    public function addToCollect()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        $param = input('post.');
        $name = $param['name'] ?? '';
        $url = $param['url'] ?? '';
        $type = $param['type'] ?? '2';
        $mid = $param['mid'] ?? '1';

        if (empty($name) || empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        // 入库 / 定时任务 / 自定义站的地址后续会被服务端请求，入口处同样做 SSRF 校验
        if (!$this->validateRemoteUrl($url)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
        }

        // 检查是否已存在
        $exists = Db::name('collect')->where('collect_url', $url)->find();
        if ($exists) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/already_exists')]);
        }

        $data = [
            'collect_name' => $name,
            'collect_url' => $url,
            'collect_type' => $type,
            'collect_mid' => $mid,
            'collect_opt' => '1',
            'collect_filter' => '0',
            'collect_filter_from' => '',
            'collect_filter_year' => '',
            'collect_sync_pic_opt' => '0',
            'collect_param' => '',
        ];

        $res = Db::name('collect')->insert($data);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('save_err')]);
        }

        return json(['code' => 1, 'msg' => lang('admin/resourcehub/add_collect_ok')]);
    }

    /**
     * 一键添加定时任务
     */
    public function addTimming()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        $param = input('post.');
        $name = $param['name'] ?? '';
        $url = $param['url'] ?? '';
        $type = $param['type'] ?? '2';
        $mid = $param['mid'] ?? '1';
        $hours = $param['hours'] ?? '2';

        if (empty($name) || empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        // 入库 / 定时任务 / 自定义站的地址后续会被服务端请求，入口处同样做 SSRF 校验
        if (!$this->validateRemoteUrl($url)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
        }

        $cjflag = md5($url);
        $task_name = 'resourcehub_' . $cjflag;

        // 构建采集URL
        $collect_url = http_build_query([
            'ac' => 'cj',
            'cjflag' => $cjflag,
            'cjurl' => $url,
            'h' => $hours,
            't' => '',
            'ids' => '',
            'wd' => '',
            'type' => $type,
            'mid' => $mid,
            'opt' => '1',
            'sync_pic_opt' => '0',
            'filter' => '0',
            'filter_from' => '',
            'filter_year' => '',
            'param' => '',
        ]);

        $timming_list = config('timming') ?: [];

        // 检测同名任务是否已存在，防止静默覆盖用户已调整的配置
        if (isset($timming_list[$task_name])) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/timming_exists')]);
        }

        // 注意：定时任务结构需与 api/Timming 控制器对齐
        //   file  -> 调度时要调用的方法名（如 collect/make/cj 等）
        //   param -> 传给该方法的查询字符串（parse_str 解析）
        // 早期版本误用 type/url 字段，导致 api/Timming 中 $v['file'] 为空
        $timming_list[$task_name] = [
            'id' => $task_name,
            'status' => '1',
            'name' => $task_name,
            'des' => '自动采集 - ' . $name,
            'file' => 'collect',
            'param' => $collect_url,
            'weeks' => '1,2,3,4,5,6,0',
            'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
            'runtime' => 0,
        ];


        $res = mac_arr2file(APP_PATH . 'extra/timming.php', $timming_list);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }

        return json(['code' => 1, 'msg' => lang('admin/resourcehub/add_timming_ok')]);
    }

    /**
     * 一键采集（快速入口）
     * 强制 POST 防止 GET 链接触发；URL 必须通过 SSRF 校验
     */
    public function quickCollect()
    {
        // 强制 POST 请求，防止通过 GET 链接触发
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }

        $param = input('post.');
        $url = $param['url'] ?? '';
        $type = $param['type'] ?? '2';
        $mid = $param['mid'] ?? '1';
        // 默认不带时间过滤（与经典采集页"进入资源库"链接、以及检测按钮拉取的 total 保持一致），
        // 否则进入后默认只拉最近24小时数据，页数/数量会与检测结果对不上
        $h = $param['h'] ?? '';

        if (empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // SSRF 安全校验：只允许外网 http/https URL
        if (!$this->validateRemoteUrl($url)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
        }

        $cjflag = md5($url);

        // 先自动绑定分类
        $this->autoBindSilent($url, $type);

        // 先自动添加播放器
        $this->autoPlayerSilent($url, $type);

        // 重定向到采集页面
        $collect_params = [
            'ac' => 'list',
            'cjflag' => $cjflag,
            'cjurl' => $url,
            'h' => $h,
            't' => '',
            'ids' => '',
            'wd' => '',
            'type' => $type,
            'mid' => $mid,
            'opt' => '1',
            'sync_pic_opt' => '0',
            'filter' => '0',
            'filter_from' => '',
            'filter_year' => '',
            'param' => '',
        ];

        $redirect_url = url('collect/api') . '?' . http_build_query($collect_params);
        return $this->redirect($redirect_url);
    }

    /**
     * 静默自动绑定分类
     */
    private function autoBindSilent($url, $type)
    {
        $api_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=list&pg=1';
        $html = $this->fetchRemote($api_url);
        if (empty($html)) return;

        $remote_types = [];
        if ($type == '2') {
            $json = json_decode($html, true);
            if ($json && isset($json['class'])) {
                foreach ($json['class'] as $v) {
                    $remote_types[$v['type_id']] = $v['type_name'];
                }
            }
        } else {
            $xml = @simplexml_load_string($html);
            if ($xml && $xml->class) {
                foreach ($xml->class->ty as $v) {
                    $remote_types[(string)$v->attributes()->id] = (string)$v;
                }
            }
        }

        if (empty($remote_types)) return;

        // 强制从数据库读取，确保最新
        Cache::rm('cache_type');
        $local_types_db = Db::name('type')->select();
        $local_type_map = [];
        $local_type_list = [];
        if (!empty($local_types_db)) {
            foreach ($local_types_db as $lt) {
                $local_type_map[$lt['type_name']] = $lt['type_id'];
                $local_type_list[] = $lt;
            }
        }

        $cjflag = md5($url);
        // 即时从磁盘读取最新绑定，避免用旧值覆盖用户手动调整的绑定
        $bind_config = $this->loadBindConfig();
        $changed = false;

        foreach ($remote_types as $remote_id => $remote_name) {
            $bind_key = $cjflag . '_' . $remote_id;
            // 已绑定的项目绝不覆盖（仅补全未绑定的）
            if (isset($bind_config[$bind_key]) && $bind_config[$bind_key] > 0) continue;

            // 精确匹配
            if (isset($local_type_map[$remote_name])) {
                $bind_config[$bind_key] = $local_type_map[$remote_name];
                $changed = true;
                continue;
            }

            // 模糊匹配
            $matched_id = $this->fuzzyMatchType($remote_name, $local_type_list);
            if ($matched_id > 0) {
                $bind_config[$bind_key] = $matched_id;
                $changed = true;
            }
        }

        // 仅当确实新增了绑定时才写回文件，避免无意义的整文件覆盖
        if ($changed) {
            mac_arr2file(APP_PATH . 'extra/bind.php', $bind_config);
        }
    }

    /**
     * 静默自动添加播放器
     */
    private function autoPlayerSilent($url, $type)
    {
        $api_url = $url . (strpos($url, '?') === false ? '?' : '&') . 'ac=detail&pg=1';
        $html = $this->fetchRemote($api_url);
        if (empty($html)) return;

        $player_samples = $this->detectPlayerFroms($html, $type);
        if (empty($player_samples)) return;

        $this->installAutoPlayers($player_samples);
    }

    /**
     * 添加自定义资源站（存到独立文件，与云端列表隔离）
     */
    public function addCustomSite()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request')]);
        }
        $param = input('post.');
        $name = trim($param['name'] ?? '');
        $url = trim($param['url'] ?? '');
        $type = $param['type'] ?? '2';
        $mid = $param['mid'] ?? '1';
        $desc = trim($param['desc'] ?? '');

        if (empty($name) || empty($url)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        // 入库 / 定时任务 / 自定义站的地址后续会被服务端请求，入口处同样做 SSRF 校验
        if (!$this->validateRemoteUrl($url)) {
            return json(['code' => 0, 'msg' => lang('admin/resourcehub/check_fail')]);
        }

        $sites = $this->loadCustomSites();
        $sites[] = [
            'name' => $name,
            'url' => $url,
            'type' => $type,
            'mid' => $mid,
            'desc' => $desc,
        ];

        $res = $this->saveCustomSites($sites);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }

        return json(['code' => 1, 'msg' => lang('admin/resourcehub/add_site_ok')]);
    }

    /**
     * 删除自定义资源站
     */
    public function delCustomSite()
    {
        $param = input('post.');
        $index = intval($param['index'] ?? -1);

        if ($index < 0) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $sites = $this->loadCustomSites();
        if (isset($sites[$index])) {
            array_splice($sites, $index, 1);
            $res = $this->saveCustomSites($sites);
            if ($res === false) {
                return json(['code' => 0, 'msg' => lang('write_err_config')]);
            }
        }

        return json(['code' => 1, 'msg' => lang('del_ok')]);
    }

    /**
     * 多窗口采集
     */
    public function multiCollect()
    {
        $param = input();
        $this->assign('param', $param);
        $this->assign('title', lang('admin/resourcehub/multi_collect'));
        return $this->fetch('resourcehub/multi_collect');
    }

    /**
     * 视频海报管理
     */
    public function poster()
    {
        $param = input();
        $page = intval(isset($param['page']) ? $param['page'] : 1);
        $param['page'] = $page < 1 ? 1 : $page;
        $limit = intval($param['limit'] ?? 40);
        if ($limit < 1) $limit = 40;
        if ($limit > 200) $limit = 200;
        $param['limit'] = $limit;
        $filter = $param['filter'] ?? 'all';

        // 基础条件
        $where = [];
        $where['vod_status'] = ['eq', 1];
        $where['vod_pic'] = ['neq', ''];

        // 筛选条件
        if ($filter == 'recommended') {
            $where['vod_level'] = ['gt', 0];
        } elseif ($filter == 'not_recommended') {
            $where['vod_level'] = ['eq', 0];
        }

        // 排序：已推荐优先，然后按点击量
        $order = 'vod_level desc, vod_hits desc';
        $list = Db::name('vod')->where($where)->order($order)->page($param['page'])->limit($param['limit'])->select();
        $total = Db::name('vod')->where($where)->count();
        $total_pages = $total > 0 ? (int)ceil($total / $limit) : 1;
        if ($param['page'] > $total_pages) {
            $param['page'] = $total_pages;
        }

        // 统计已推荐数量
        $recommended_count = Db::name('vod')->where(['vod_status' => ['eq', 1], 'vod_pic' => ['neq', ''], 'vod_level' => ['gt', 0]])->count();

        $this->assign('list', $list);
        $this->assign('total', $total);
        $this->assign('total_pages', $total_pages);
        $this->assign('page', $param['page']);
        $this->assign('limit', $param['limit']);
        $this->assign('filter', $filter);
        $this->assign('recommended_count', $recommended_count);
        $this->assign('title', lang('admin/resourcehub/poster_title'));
        return $this->fetch('resourcehub/poster');
    }

    /**
     * 设置视频为推荐（海报轮播）
     */
    public function setRecommend()
    {
        $param = input('post.');
        $ids = $param['ids'] ?? '';
        $level = intval($param['level'] ?? 9);

        if (empty($ids)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $res = Db::name('vod')->where('vod_id', 'in', $ids)->update(['vod_level' => $level]);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('save_err')]);
        }

        return json(['code' => 1, 'msg' => lang('admin/resourcehub/set_recommend_ok')]);
    }

    /**
     * 预览重复分类（两步式：第一步）
     * 判重维度：type_name + type_pid + type_mid（同名、同父、同媒介才算重复）
     * 返回每组重复项及其引用量、维护状态，供用户选择保留哪一条
     */
    public function previewDuplicateTypes()
    {
        // 判重维度：type_name + type_pid + type_mid
        $duplicates = Db::name('type')
            ->field('type_name, type_pid, type_mid, COUNT(*) as cnt')
            ->group('type_name, type_pid, type_mid')
            ->having('cnt > 1')
            ->select();

        if (empty($duplicates)) {
            return json(['code' => 1, 'msg' => '没有发现重复的分类', 'data' => []]);
        }

        $groups = [];
        foreach ($duplicates as $dup) {
            $items = Db::name('type')
                ->where('type_name', $dup['type_name'])
                ->where('type_pid', $dup['type_pid'])
                ->where('type_mid', $dup['type_mid'])
                ->order('type_id asc')
                ->select();

            $group = [
                'type_name' => $dup['type_name'],
                'type_pid' => $dup['type_pid'],
                'type_mid' => $dup['type_mid'],
                'items' => [],
            ];

            foreach ($items as $item) {
                $tid = $item['type_id'];

                // 统计引用量
                $vod_count = Db::name('vod')->where('type_id', $tid)->count();
                $vod_count_1 = Db::name('vod')->where('type_id_1', $tid)->count();
                $child_count = Db::name('type')->where('type_pid', $tid)->count();

                // 检测是否已维护（关键字段非空即视为已维护）
                $maintained = (
                    !empty($item['type_sort']) ||
                    !empty($item['type_logo']) ||
                    !empty($item['type_pic']) ||
                    !empty($item['type_des']) ||
                    !empty($item['type_title']) ||
                    !empty($item['type_tpl']) ||
                    !empty($item['type_tpl_list']) ||
                    !empty($item['type_tpl_detail']) ||
                    !empty($item['type_tpl_play']) ||
                    !empty($item['type_tpl_down']) ||
                    !empty($item['type_extend'])
                );

                $group['items'][] = [
                    'type_id' => $tid,
                    'type_name' => $item['type_name'],
                    'type_en' => $item['type_en'],
                    'type_pid' => $item['type_pid'],
                    'type_mid' => $item['type_mid'],
                    'type_sort' => $item['type_sort'],
                    'vod_count' => $vod_count + $vod_count_1,
                    'child_count' => $child_count,
                    'maintained' => $maintained,
                ];
            }

            // 推荐保留：引用最多 + 已维护的优先
            usort($group['items'], function ($a, $b) {
                if ($a['maintained'] !== $b['maintained']) {
                    return $b['maintained'] - $a['maintained'];
                }
                return $b['vod_count'] - $a['vod_count'];
            });
            $group['recommended_keep'] = $group['items'][0]['type_id'];

            $groups[] = $group;
        }

        return json(['code' => 1, 'msg' => sprintf('发现 %d 组重复分类', count($groups)), 'data' => $groups]);
    }

    /**
     * 执行清理重复分类（两步式：第二步）
     * 接收用户选择的 keep_id / delete_ids 列表
     * 删除前备份到 runtime/log/
     */
    public function cleanDuplicateTypes()
    {
        // 支持 JSON body 和 form-data 两种提交方式
        $raw = file_get_contents('php://input');
        $param = json_decode($raw, true);
        if (empty($param)) {
            $param = input('post.');
        }
        $actions = $param['actions'] ?? [];

        // actions 格式: [{ keep_id: 1, delete_ids: [2, 3] }, ...]
        if (empty($actions) || !is_array($actions)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        // 备份准备
        $backup_data = [];
        $timestamp = date('Ymd_His');
        $total_deleted = 0;
        $total_updated_vod = 0;

        foreach ($actions as $action) {
            $keep_id = intval($action['keep_id'] ?? 0);
            $delete_ids = $action['delete_ids'] ?? [];

            if ($keep_id <= 0 || empty($delete_ids)) {
                continue;
            }

            // 确认 keep_id 存在
            $keep_type = Db::name('type')->where('type_id', $keep_id)->find();
            if (!$keep_type) {
                continue;
            }

            foreach ($delete_ids as $del_id) {
                $del_id = intval($del_id);
                if ($del_id <= 0 || $del_id === $keep_id) {
                    continue;
                }

                $del_type = Db::name('type')->where('type_id', $del_id)->find();
                if (!$del_type) {
                    continue;
                }

                // 备份被删除的分类完整数据
                $backup_data[] = $del_type;

                // 将引用旧 type_id 的视频更新为保留的 type_id
                $vod_updated = Db::name('vod')->where('type_id', $del_id)->update(['type_id' => $keep_id]);
                $total_updated_vod += ($vod_updated ?: 0);

                // 更新 type_id_1（父类）引用
                Db::name('vod')->where('type_id_1', $del_id)->update(['type_id_1' => $keep_id]);

                // 更新子分类的 type_pid
                Db::name('type')->where('type_pid', $del_id)->update(['type_pid' => $keep_id]);

                // 删除重复分类
                Db::name('type')->where('type_id', $del_id)->delete();
                $total_deleted++;
            }
        }

        // 写入备份日志
        if (!empty($backup_data)) {
            $log_dir = RUNTIME_PATH . 'log/';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            $log_file = $log_dir . 'clean_type_' . $timestamp . '.log';
            $log_content = "=== 分类清理备份 ===\n";
            $log_content .= "时间: " . date('Y-m-d H:i:s') . "\n";
            $log_content .= "删除数量: " . $total_deleted . "\n";
            $log_content .= "影响视频: " . $total_updated_vod . "\n\n";
            $log_content .= json_encode($backup_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            @file_put_contents($log_file, $log_content);
        }

        // 清除分类缓存
        Cache::rm('cache_type');

        return json([
            'code' => 1,
            'msg' => sprintf('清理完成！删除了 %d 个重复分类，更新了 %d 条视频记录', $total_deleted, $total_updated_vod)
        ]);
    }
}
