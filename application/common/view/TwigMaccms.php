<?php
namespace app\common\view;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig 擴充：把 maccms 的資料標籤與 mac_* 輔助函式以**白名單**方式暴露給模板。
 *
 * 設計要點：
 *
 * - 資料函式（mac_vod / mac_art / …）內部呼叫的是 taglib 完全相同的 model 方法
 *   （見 application/common/taglib/Maccms.php），因此取數、快取、分頁行為與
 *   `{maccms:vod}` 一致，不重寫任何業務邏輯。
 * - 參數預設值直接從 taglib 的 $tags 宣告反射取得，保持單一事實來源；
 *   listCacheData() 內部是直接讀 $lp['xxx'] 而不做 isset 判斷，
 *   少填一個鍵就會在 PHP 7.0 下噴 Notice，所以必須補齊全部宣告過的屬性。
 * - **不提供通用 PHP 呼叫橋**。模板只能用這裡註冊過的函式。
 */
class TwigMaccms extends AbstractExtension
{
    /** taglib 標籤名 => [模型類, 方法名] */
    private static $dataMap = [
        'vod'     => ['Vod', 'listCacheData'],
        'art'     => ['Art', 'listCacheData'],
        'manga'   => ['Manga', 'listCacheData'],
        'type'    => ['Type', 'listCacheData'],
        'actor'   => ['Actor', 'listCacheData'],
        'role'    => ['Role', 'listCacheData'],
        'topic'   => ['Topic', 'listCacheData'],
        'website' => ['Website', 'listCacheData'],
        'comment' => ['Comment', 'listCacheData'],
        'gbook'   => ['Gbook', 'listCacheData'],
        'link'    => ['Link', 'listCacheData'],
        'area'    => ['Extend', 'areaData'],
        'lang'    => ['Extend', 'langData'],
        'year'    => ['Extend', 'yearData'],
        'class'   => ['Extend', 'classData'],
        'version' => ['Extend', 'versionData'],
        'state'   => ['Extend', 'stateData'],
        'letter'  => ['Extend', 'letterData'],
    ];

    /**
     * 允許模板直接呼叫的 mac_* 輔助函式。
     * 清單來自 template/default 中 `{:xxx()}` 的實測用量（見可行性報告 §3）。
     * 值為 true 表示輸出是已處理過的 HTML，不再經 autoescape。
     */
    private static $helpers = [
        'mac_url'                  => false,
        'mac_url_page'             => false,
        'mac_url_img'              => false,
        'mac_url_type'             => false,
        'mac_url_vod_detail'       => false,
        'mac_url_vod_play'         => false,
        'mac_url_vod_down'         => false,
        'mac_url_vod_search'       => false,
        'mac_url_art_detail'       => false,
        'mac_url_manga_detail'     => false,
        'mac_url_manga_play'       => false,
        'mac_url_actor_detail'     => false,
        'mac_url_topic_detail'     => false,
        'mac_url_plot_detail'      => false,
        'mac_url_website_detail'   => false,
        'mac_url_website_index'    => false,
        'mac_url_role_detail'      => false,
        'mac_url_content_img'      => false,
        'mac_substring'            => false,
        // 简介纯文本：解多层實體（&amp;nbsp;）+ 去標籤，輸出仍由 Twig 轉義
        'mac_plain_text'           => false,
        'mac_txt_merge'            => false,
        'mac_default'              => false,
        'mac_friend_date'          => false,
        'mac_escape'               => false,
        'mac_param_url'            => false,
        'mac_get_user_portrait'    => false,
        'mac_tpl_vod_type_cover'   => false,
        'mac_tpl_art_cover'        => false,
        'mac_tpl_manga_cover'      => false,
        'mac_theme_index_hotvod_tabs' => false,
        'mac_td_section'           => false,
        'mac_homepage_blocks'      => false,
        'mac_td_preview_mode'      => false,
        'mac_theme_design'         => false,
        'mac_theme_override_css_href' => false,
        // Phase 1 補齊的資料契約（取代 template/default 裡的 {php} 區塊）
        'mac_user_fav_map'         => false,
        'mac_user_fav_id'          => false,
        'mac_user_like_map'        => false,
        'mac_user_like_id'         => false,
        'mac_content_like_count'   => false,
        'mac_ad_slot'              => false,
        'mac_top_types'            => false,
        'mac_i18n_key'             => false,
        'mac_module_label'         => false,
        'mac_type_cover'           => false,
        // 播放进度片长依据（vod_duration 解析 + 同集 m3u8 探测地址），见 TwigPageCompiler::emitNp2PlayShellHeadTags
        'mac_vod_progress_meta'    => false,
        'mac_pb_feed'              => false,
        'mac_seo_meta'             => false,
        // 主題繼承：資產（CSS/JS/圖）沒有 loader 的搜尋路徑可回落，
        // 子主題要沿用底座的圖就得靠它算 URL
        'mac_theme_asset'          => false,
        // 直播沒有 taglib 標籤，default 靠自家 live-show.js 打 API；
        // 這三個讓任何主題都能伺服器直出，見 application/common.php
        'mac_live_list'            => false,
        'mac_live_cates'           => false,
        'mac_live_info'            => false,
        // 排行榜多榜單（後台 TplConfig 的 theme.rank.boards），供 spike 的
        // rank_theme_boards 元件直出，見 application/common.php
        'mac_rank_boards'          => false,
        // 邮箱/短信通道是否已配置，前台绑定、找回密码入口据此显隐（#272）
        'mac_msg_channels'         => false,
        // 以下回傳的是已組好的 HTML 片段，必須標記為 safe 否則會被二次轉義
        'mac_filter_html'          => true,
        'mac_url_create'           => true,
        'mac_em_replace'           => true,
        'mac_day'                  => true,
    ];

    /**
     * 模型 listCacheData() 會讀、但 taglib 的 $tags['attr'] **沒有宣告**的鍵。
     *
     * 這是 maccms 既有的不一致：taglib 解析器只把宣告過的屬性填進 $tag，
     * 所以 `{maccms:vod num="6"}` 走到 Vod::listCacheData() 時，
     * $lp['wd'] / $lp['actor'] / $lp['director'] / $lp['name'] / $lp['plot']
     * 全是未定義索引 —— default 主題每次列表渲染都在噴這些 Notice，
     * 只是正式站關掉 display_errors 所以看不見（實測 mac_vod 一次呼叫 42 個）。
     *
     * 不去動模型（production-stable 路徑，改動風險大於收益），改在 Twig 這側
     * 把預設值補齊：既消掉 Notice，也讓 mac_vod({wd: '…'}) 這種
     * 「模型支援但 taglib 沒暴露」的查詢能用 —— 搜尋頁需要它。
     *
     * 來源：掃 application/common/model/*.php 的 `$lp['key']` 讀取點。
     */
    private static $modelExtraKeys = [
        'vod'     => ['wd', 'actor', 'director', 'name', 'plot'],
        'art'     => ['wd', 'name'],
        'manga'   => ['wd', 'name'],
        'actor'   => ['wd', 'name', 'area', 'sex', 'blood', 'starsign', 'type', 'typenot',
                      'level', 'letter', 'hits', 'hitsday', 'hitsweek', 'hitsmonth',
                      'time', 'timeadd', 'timehits', 'cachetime', 'half', 'not', 'ids'],
        'role'    => ['wd', 'name', 'actor', 'rid', 'level', 'letter',
                      'hits', 'hitsday', 'hitsweek', 'hitsmonth',
                      'time', 'timeadd', 'timehits', 'cachetime', 'half', 'not', 'ids'],
        'topic'   => ['wd', 'class', 'tag', 'level', 'letter',
                      'hits', 'hitsday', 'hitsweek', 'hitsmonth',
                      'time', 'timeadd', 'timehits', 'cachetime', 'half', 'not', 'ids'],
        'website' => ['wd', 'name', 'refer', 'referday', 'referweek', 'refermonth',
                      'area', 'lang', 'class', 'tag', 'type', 'typenot', 'level', 'letter',
                      'hits', 'hitsday', 'hitsweek', 'hitsmonth',
                      'time', 'timeadd', 'timehits', 'cachetime', 'half', 'not', 'ids'],
        'type'    => ['flag', 'format', 'names'],
        'comment' => ['uid', 'pid'],
        'gbook'   => ['uid'],
    ];

    /** @var array|null taglib 屬性預設值快取：標籤名 => [屬性 => ''] */
    private static $attrDefaults = null;

    public function getName()
    {
        return 'maccms';
    }

    public function getFunctions()
    {
        $fns = [];

        foreach (array_keys(self::$dataMap) as $tag) {
            $name = 'mac_' . $tag;
            if ($tag === 'comment') {
                // 與 taglib 一致：未顯式給 mid/rid 時，沿用控制器 assign 的 comment_mid / comment_rid
                $fns[] = new TwigFunction($name, [$this, 'dataComment'], ['needs_context' => true]);
                continue;
            }
            $fns[] = new TwigFunction($name, function ($args = []) use ($tag) {
                return TwigMaccms::fetchData($tag, $args);
            });
        }

        $fns[] = new TwigFunction('mac_paging', [$this, 'paging']);

        foreach (self::$helpers as $fn => $isHtml) {
            $opt = $isHtml ? ['is_safe' => ['html']] : [];
            $fns[] = new TwigFunction($fn, $fn, $opt);
        }

        // lang() 與 url() 是框架層函式，名稱保持與模板既有寫法一致
        $fns[] = new TwigFunction('lang', 'lang');
        $fns[] = new TwigFunction('url', 'url');

        // 表單是否要出驗證碼。給共用元件庫自己查，主題不必知道有這回事 ——
        // 站長把驗證碼打開、主題卻沒渲染欄位的話，提交一律回「验证码错误」，
        // 而且是靜默的：頁面看起來完全正常，只有送出時才壞。
        $fns[] = new TwigFunction('mac_verify_on', [__CLASS__, 'verifyOn']);

        return $fns;
    }

    /**
     * 讀後台的驗證碼開關。
     *
     * @param string $scope comment | gbook | login | reg
     * @return bool
     */
    public static function verifyOn($scope)
    {
        // 只查兩層：TP5 的 config() 用 explode('.', $name, 2) 拆鍵，
        // 寫成 config('maccms.comment.verify') 會去找不存在的 'comment.verify'
        // 這個鍵，靜默回 null —— 驗證碼永遠判定為關閉。
        $map = [
            'comment' => ['comment', 'verify'],
            'gbook'   => ['gbook', 'verify'],
            'login'   => ['user', 'login_verify'],
            'reg'     => ['user', 'reg_verify'],
        ];
        $scope = (string) $scope;
        if (!isset($map[$scope])) {
            return false;
        }
        list($section, $key) = $map[$scope];
        $cfg = config('maccms.' . $section);
        return is_array($cfg) && isset($cfg[$key]) && (string) $cfg[$key] === '1';
    }

    /**
     * `{{ mac_comment({num: 10}) }}` —— 未給 mid/rid 時從視圖上下文取。
     *
     * @param array $context Twig 上下文
     * @param array $args
     * @return array
     */
    public function dataComment($context, $args = [])
    {
        if (!isset($args['mid']) && isset($context['comment_mid'])) {
            $args['mid'] = $context['comment_mid'];
        }
        if (!isset($args['rid']) && isset($context['comment_rid'])) {
            $args['rid'] = $context['comment_rid'];
        }
        return self::fetchData('comment', $args);
    }

    /**
     * 呼叫對應模型取資料。
     *
     * @param string $tag  taglib 標籤名
     * @param array  $args 模板傳入的參數
     * @return array
     */
    public static function fetchData($tag, $args = [])
    {
        if (!isset(self::$dataMap[$tag])) {
            return [];
        }
        if (!is_array($args)) {
            $args = [];
        }

        $lp = array_merge(self::attrDefaults($tag), $args);
        $lp = self::coerceTwigNumericArgs($lp);
        // taglib 用 paging="yes" 表示開啟；Twig 側允許布林，統一轉回字串語義
        if (isset($lp['paging']) && ($lp['paging'] === true || $lp['paging'] === 1)) {
            $lp['paging'] = 'yes';
        }

        list($model, $method) = self::$dataMap[$tag];
        $res = model($model)->$method($lp);

        return is_array($res) ? $res : [];
    }

    /**
     * 把資料函式回傳的分頁欄位轉成 mac_page_param() 的結果。
     * taglib 中這是 paging="yes" 的側效應（寫 $__PAGING__），Twig 側改為顯式呼叫。
     *
     * @param array $list 資料函式的回傳值
     * @return array
     */
    public function paging($list)
    {
        if (!is_array($list) || !isset($list['total'])) {
            return [];
        }
        $half = isset($list['half']) && $list['half'] !== '' ? $list['half'] : 5;
        return mac_page_param(
            $list['total'],
            isset($list['limit']) ? $list['limit'] : 1,
            isset($list['page']) ? $list['page'] : 1,
            isset($list['pageurl']) ? $list['pageurl'] : '',
            $half
        );
    }

    /**
     * 從 taglib 的 $tags 宣告反射出某標籤的全部屬性，值一律預設為 ''。
     * 單一事實來源：新增/調整 taglib 屬性時這裡自動跟上。
     * Twig 宏 {% set x = macro() %} 在 PHP 7.0 下可能是 Markup 对象；
     * 直接 intval($object) 会得到 1，导致分页列表每页只取 1 条。
     *
     * @param array $lp
     * @return array
     */
    private static function coerceTwigNumericArgs(array $lp)
    {
        foreach (array('num', 'start', 'half') as $key) {
            if (!isset($lp[$key]) || $lp[$key] === '') {
                continue;
            }
            if (is_object($lp[$key])) {
                $lp[$key] = (string) $lp[$key];
            }
            if (is_numeric($lp[$key])) {
                $lp[$key] = (int) $lp[$key];
            }
        }

        return $lp;
    }

    private static function attrDefaults($tag)
    {
        if (self::$attrDefaults === null) {
            self::$attrDefaults = [];
            try {
                $ref = new \ReflectionClass('app\common\taglib\Maccms');
                $prop = $ref->getProperty('tags');
                $prop->setAccessible(true);
                $tags = $prop->getValue($ref->newInstanceWithoutConstructor());
            } catch (\Exception $e) {
                $tags = [];
            }
            foreach ((array) $tags as $name => $def) {
                $attr = isset($def['attr']) ? (string) $def['attr'] : '';
                $keys = array_filter(array_map('trim', explode(',', $attr)));
                if (isset(self::$modelExtraKeys[$name])) {
                    $keys = array_merge($keys, self::$modelExtraKeys[$name]);
                }
                self::$attrDefaults[$name] = array_fill_keys($keys, '');
            }
        }
        return isset(self::$attrDefaults[$tag]) ? self::$attrDefaults[$tag] : [];
    }
}
