<?php
namespace app\common\util;

/**
 * OpenAPI 3.0 规范生成器
 *
 * 依据《说明文档/API接口说明V2.txt》将 api.php 的纯文本接口说明转换为 OpenAPI 3.0 规范，
 * 供后台「API 文档」页面内嵌的 Swagger UI 在线浏览与调试使用。
 *
 * 设计要点：
 * - 纯数据生成，无数据库 / 全局状态依赖，方便回归测试与缓存
 * - servers[].url 由调用方注入实际站点的 api.php 入口，保证「Try it out」直连本站
 * - 端点按文档章节分组（tags），GET 用 query 参数，POST 用 x-www-form-urlencoded 表单
 */
class OpenApiSpec
{
    /**
     * 构建完整 OpenAPI 规范数组
     * @param string $baseUrl api.php 入口地址，例如 /api.php 或 https://demo.com/api.php
     * @return array
     */
    public function build($baseUrl = '/api.php')
    {
        if (!is_string($baseUrl) || $baseUrl === '') {
            $baseUrl = '/api.php';
        }

        return array(
            'openapi' => '3.0.3',
            'info' => array(
                'title' => 'MacCMS V10 API',
                'description' => '苹果CMS V10 对外 API（基于当前实现，详见《说明文档/API接口说明V2.txt》）。'
                    . '基础路径为 api.php，默认返回 JSON；多数模块接口受后台「API 公开接口(PublicApi)」开关约束，'
                    . '部分接口需用户登录（携带 Cookie/Session）。',
                'version' => '2.0.0',
            ),
            'servers' => array(
                array('url' => $baseUrl, 'description' => '当前站点 api.php 入口'),
            ),
            'tags' => $this->tags(),
            'paths' => $this->paths(),
            'components' => $this->components(),
        );
    }

    /**
     * 标签（与文档章节对应）
     * @return array
     */
    private function tags()
    {
        $list = array(
            array('Search', '搜索：统一搜索、联想、各模块关键字检索'),
            array('Provide', 'Provide 聚合接口（采集/对接用，兼容老格式）'),
            array('Vod', '视频'),
            array('Art', '文章'),
            array('Actor', '演员'),
            array('Role', '角色'),
            array('Comment', '评论'),
            array('Gbook', '留言'),
            array('Link', '友情链接'),
            array('Topic', '专题/话题'),
            array('Type', '分类'),
            array('User', '用户（资料 + 当前登录用户账户行为）'),
            array('Ulog', '用户行为日志 / 播放进度'),
            array('Website', '网址导航'),
            array('Manga', '漫画'),
            array('Novel', '小说'),
            array('Config', '配置 / 主题 / 广告'),
            array('Auth', '认证与权限'),
            array('Order', '充值订单'),
            array('Payment', '支付 / 充值'),
            array('Cash', '提现管理'),
            array('Chatroom', '聊天室'),
            array('Danmaku', '弹幕'),
            array('Task', '任务与签到'),
            array('Live', '直播'),
            array('Receive', '数据接收/推送入库（需密码）'),
            array('Sycms', 'SyCMS 远程采集转换'),
            array('System', '系统/内部接口（定时任务、微信接入）'),
        );
        $tags = array();
        foreach ($list as $t) {
            $tags[] = array('name' => $t[0], 'description' => $t[1]);
        }
        return $tags;
    }

    /**
     * 汇总所有路径
     * @return array
     */
    private function paths()
    {
        $groups = array(
            $this->pathsSearch(),
            $this->pathsProvide(),
            $this->pathsVod(),
            $this->pathsArt(),
            $this->pathsActor(),
            $this->pathsRole(),
            $this->pathsComment(),
            $this->pathsGbook(),
            $this->pathsLink(),
            $this->pathsTopic(),
            $this->pathsType(),
            $this->pathsUser(),
            $this->pathsUlog(),
            $this->pathsWebsite(),
            $this->pathsManga(),
            $this->pathsNovel(),
            $this->pathsConfig(),
            $this->pathsAuth(),
            $this->pathsOrder(),
            $this->pathsPayment(),
            $this->pathsCash(),
            $this->pathsChatroom(),
            $this->pathsDanmaku(),
            $this->pathsTask(),
            $this->pathsLive(),
            $this->pathsReceive(),
            $this->pathsSycms(),
            $this->pathsSystem(),
        );
        $paths = array();
        foreach ($groups as $g) {
            foreach ($g as $route => $ops) {
                $paths[$route] = $ops;
            }
        }
        return $paths;
    }

    /* ---------------- 通用复用结构 ---------------- */

    /**
     * 复用组件
     * @return array
     */
    private function components()
    {
        return array(
            'schemas' => array(
                'ApiResult' => array(
                    'type' => 'object',
                    'description' => '统一返回结构：code=1 成功，info/data 为业务数据；列表 info 常含 offset/limit/total/rows 或 list。',
                    'properties' => array(
                        'code' => array('type' => 'integer', 'example' => 1),
                        'msg' => array('type' => 'string', 'example' => '获取成功'),
                        'info' => array('type' => 'object', 'nullable' => true),
                        'data' => array('type' => 'object', 'nullable' => true),
                    ),
                ),
            ),
            'responses' => array(
                'ApiResult' => array(
                    'description' => '统一返回结构',
                    'content' => array(
                        'application/json' => array(
                            'schema' => array('$ref' => '#/components/schemas/ApiResult'),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * 默认 200 响应
     * @return array
     */
    private function responses()
    {
        return array(
            '200' => array('$ref' => '#/components/responses/ApiResult'),
        );
    }

    /**
     * 构建一个 query 参数
     * @param string $name
     * @param string $type integer|string|number|boolean
     * @param bool $required
     * @param string $desc
     * @return array
     */
    private function q($name, $type, $required, $desc)
    {
        return array(
            'name' => $name,
            'in' => 'query',
            'required' => (bool)$required,
            'description' => $desc,
            'schema' => array('type' => $type),
        );
    }

    /**
     * 构建 GET 操作
     * @param string $tag
     * @param string $summary
     * @param string $desc
     * @param array $params q() 数组
     * @return array
     */
    private function get($tag, $summary, $desc, $params)
    {
        return array(
            'get' => array(
                'tags' => array($tag),
                'summary' => $summary,
                'description' => $desc,
                'parameters' => $params,
                'responses' => $this->responses(),
            ),
        );
    }

    /**
     * 构建 POST 操作（x-www-form-urlencoded 表单）
     * @param string $tag
     * @param string $summary
     * @param string $desc
     * @param array $fields 每项 array(name, type, required, desc)
     * @return array
     */
    private function post($tag, $summary, $desc, $fields)
    {
        $op = array(
            'tags' => array($tag),
            'summary' => $summary,
            'description' => $desc,
            'responses' => $this->responses(),
        );
        if (!empty($fields)) {
            $props = array();
            $required = array();
            foreach ($fields as $f) {
                $props[$f[0]] = array('type' => $f[1], 'description' => $f[3]);
                if (!empty($f[2])) {
                    $required[] = $f[0];
                }
            }
            $schema = array('type' => 'object', 'properties' => $props);
            if (!empty($required)) {
                $schema['required'] = $required;
            }
            $op['requestBody'] = array(
                'required' => true,
                'content' => array(
                    'application/x-www-form-urlencoded' => array('schema' => $schema),
                ),
            );
        }
        return array('post' => $op);
    }

    /* ---------------- 各模块路径 ---------------- */

    private function pathsSearch()
    {
        return array(
            '/search/index' => $this->get('Search', '统一搜索（跨模块）', '一次请求同时搜索视频/文章/漫画等，受后台搜索开关及 PublicApi 约束。', array(
                $this->q('wd', 'string', true, '搜索关键字（trim 后非空，最长 50 字）'),
                $this->q('module', 'string', false, '搜索范围 all|vod|art|manga，默认 all'),
                $this->q('limit', 'integer', false, '每个模块返回数量 1~50，默认 10'),
                $this->q('page', 'integer', false, '页码，默认 1'),
            )),
            '/search/suggest' => $this->get('Search', '统一搜索联想（自动完成）', '跨模块快速联想，按各模块点击量排序。', array(
                $this->q('wd', 'string', true, '关键字'),
                $this->q('limit', 'integer', false, '每个模块返回数量 1~10，默认 5'),
            )),
            '/vod/suggest' => $this->get('Search', '视频搜索联想', '按 vod_name、vod_en 模糊匹配，附加视频搜索结果页 url。', array(
                $this->q('wd', 'string', true, '关键字（trim 后非空）'),
                $this->q('limit', 'integer', false, '1~20，默认 10'),
            )),
        );
    }

    private function pathsProvide()
    {
        $provideParams = array(
            $this->q('ac', 'string', false, 'list|detail|videolist（默认 list）'),
            $this->q('at', 'string', false, 'json|xml（默认 json）'),
            $this->q('t', 'integer', false, '分类 ID'),
            $this->q('ids', 'string', false, 'ID 列表，逗号分隔'),
            $this->q('pg', 'integer', false, '页码，默认 1'),
            $this->q('pagesize', 'integer', false, '每页条数，最大 100'),
            $this->q('wd', 'string', false, '搜索关键字'),
            $this->q('h', 'integer', false, '最近 N 小时内（如 24）'),
            $this->q('year', 'string', false, '年份（如 2020 或 2018-2022）'),
            $this->q('isend', 'integer', false, '完结筛选（1 完结，0 连载）'),
            $this->q('from', 'string', false, '播放器筛选（逗号分隔）'),
            $this->q('sort_direction', 'string', false, 'desc|asc，默认 desc'),
        );
        $simple = array(
            $this->q('ac', 'string', false, 'list|detail（默认 list）'),
            $this->q('t', 'integer', false, '分类 ID'),
            $this->q('ids', 'string', false, 'ID 列表，逗号分隔'),
            $this->q('pg', 'integer', false, '页码，默认 1'),
            $this->q('pagesize', 'integer', false, '每页条数，最大 100'),
            $this->q('wd', 'string', false, '搜索关键字'),
            $this->q('h', 'integer', false, '最近 N 小时内'),
        );
        return array(
            '/provide/vod' => $this->get('Provide', '视频聚合接口', 'ac=list 返回精简字段并附分类 class；videolist/detail 返回完整字段。at=xml 返回 V1 XML 结构。', $provideParams),
            '/provide/art' => $this->get('Provide', '文章聚合接口', '语义与 Vod 聚合接口类似。', $simple),
            '/provide/actor' => $this->get('Provide', '演员聚合接口', '语义与 Vod 聚合接口类似。', $simple),
            '/provide/role' => $this->get('Provide', '角色聚合接口', '语义与 Vod 聚合接口类似。', $simple),
            '/provide/website' => $this->get('Provide', '网址聚合接口', '语义与 Vod 聚合接口类似。', $simple),
            '/provide/manga' => $this->get('Provide', '漫画聚合接口', '语义与 Vod 聚合接口类似。', $simple),
            '/provide/comment' => $this->get('Provide', '评论聚合接口（预留）', '当前为空实现，后续可用于采集/对接评论数据。', array()),
        );
    }

    private function pathsVod()
    {
        return array(
            '/vod/update_hits' => $this->get('Vod', '更新/获取点击数', 'type=update 自增并返回新值；不传只获取当前值。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('type', 'string', false, '传 "update" 则自增'),
            )),
            '/vod/digg' => $this->get('Vod', '影片顶/踩', 'Cookie 防重复（30 秒内同 id 不可重复）。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('type', 'string', false, 'up=顶，down=踩；不传只获取'),
            )),
            '/vod/update_score' => $this->get('Vod', '影片评分', 'Cookie 防重复。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('score', 'integer', false, '1-10 评分值；不传只获取'),
            )),
            '/vod/verify_pwd' => $this->get('Vod', '验证播放/下载密码', '有 5 秒频率限制；成功后写入 session。返回码 1001/1002/1003/1012。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('pwd', 'string', true, '密码'),
                $this->q('type', 'integer', true, '1=访问密码，4=播放密码，5=下载密码'),
            )),
            '/vod/get_play_info' => $this->get('Vod', '获取播放页信息', '返回 play_list、current、play_url 等。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('sid', 'integer', false, '播放源编号，默认 1'),
                $this->q('nid', 'integer', false, '集数编号，默认 1'),
            )),
            '/vod/get_down_info' => $this->get('Vod', '获取下载页信息', '返回 down_list、current 等。', array(
                $this->q('id', 'integer', true, '影片 vod_id'),
                $this->q('sid', 'integer', false, '下载源编号，默认 1'),
                $this->q('nid', 'integer', false, '集数编号，默认 1'),
            )),
            '/vod/get_list' => $this->get('Vod', '视频列表条件搜索', '每条含 vod_link、vod_pic（已处理 URL）、type_is_vip_exclusive 等。', array(
                $this->q('id', 'integer', false, '影片 ID'),
                $this->q('offset', 'integer', false, '偏移，默认 0'),
                $this->q('limit', 'integer', false, '1~500，默认 20'),
                $this->q('orderby', 'string', false, 'hits|up|pubdate|hits_week|hits_month|hits_day|score'),
                $this->q('type_id', 'integer', false, '分类 ID（同时匹配父分类 type_id_1）'),
                $this->q('vod_letter', 'string', false, '首字母筛选（<=10）'),
                $this->q('vod_name', 'string', false, '标题模糊搜索（<=50）'),
                $this->q('vod_tag', 'string', false, '标签模糊搜索（<=20）'),
                $this->q('vod_blurb', 'string', false, '简介模糊搜索（<=20）'),
                $this->q('vod_class', 'string', false, '类型模糊搜索（<=10）'),
                $this->q('vod_area', 'string', false, '地区精确匹配（<=20）'),
                $this->q('vod_year', 'string', false, '年份精确匹配（<=10）'),
                $this->q('vod_lang', 'string', false, '语言精确匹配（<=20）'),
                $this->q('vod_level', 'string', false, '推荐等级（in 逗号分隔，<=50）'),
                $this->q('vod_state', 'string', false, '连载状态精确匹配（<=20）'),
                $this->q('vod_isend', 'integer', false, '完结筛选 0|1'),
                $this->q('vod_actor', 'string', false, '演员模糊搜索（<=128）'),
            )),
            '/vod/get_detail' => $this->get('Vod', '视频详情', '', array(
                $this->q('vod_id', 'integer', true, '影片 vod_id'),
            )),
            '/vod/get_year' => $this->get('Vod', '视频年份列表', '', array(
                $this->q('type_id_1', 'integer', true, '顶级分类 ID'),
            )),
            '/vod/get_class' => $this->get('Vod', '视频类型列表', '', array(
                $this->q('type_id_1', 'integer', true, '顶级分类 ID'),
            )),
            '/vod/get_area' => $this->get('Vod', '视频地区列表', '', array(
                $this->q('type_id_1', 'integer', true, '顶级分类 ID'),
            )),
            '/vod/get_banner' => $this->get('Vod', 'Banner 推荐', '每条含 vod_pic_slide、is_fav 等。', array(
                $this->q('num', 'integer', false, '数量，默认 5'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('level', 'string', false, '推荐等级，默认 9，多个逗号分隔'),
            )),
            '/vod/get_hot' => $this->get('Vod', '热门推荐', '', array(
                $this->q('num', 'integer', false, '数量，默认 6'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('level', 'string', false, '推荐等级，多个逗号分隔'),
                $this->q('by', 'string', false, '排序，默认 hits_month'),
            )),
            '/vod/get_latest_by_type' => $this->get('Vod', '按分类最新', '返回 info.total、info.today_new_count、rows。', array(
                $this->q('type_id', 'integer', true, '分类 ID'),
                $this->q('num', 'integer', false, '数量，默认 24'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
            )),
            '/vod/get_rank' => $this->get('Vod', '排行榜', '', array(
                $this->q('type_id', 'integer', false, '分类 ID，不传查全站'),
                $this->q('num', 'integer', false, '数量，默认 10'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('by', 'string', false, '排序，默认 hits_month'),
            )),
        );
    }

    private function pathsArt()
    {
        return array(
            '/art/get_list' => $this->get('Art', '文章列表条件搜索', '标题类检索主要使用 name（art_name like）。', array(
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('tag', 'string', false, '标签模糊搜索（<=50）'),
                $this->q('orderby', 'string', false, 'id|time|time_add|score|hits|hits_day|hits_week|hits_month|up|down|level'),
                $this->q('letter', 'string', false, '首字母筛选（<=1）'),
                $this->q('status', 'integer', false, '1~10'),
                $this->q('name', 'string', false, '标题模糊搜索（<=100）'),
                $this->q('sub', 'string', false, '副标题模糊搜索（<=100）'),
                $this->q('blurb', 'string', false, '简介模糊搜索（<=100）'),
                $this->q('title', 'string', false, '（<=50）'),
                $this->q('content', 'string', false, '内容模糊搜索（<=100）'),
                $this->q('class', 'string', false, '分类名称模糊搜索（<=50）'),
                $this->q('level', 'string', false, '推荐等级（in 逗号分隔，<=50）'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
            )),
            '/art/get_detail' => $this->get('Art', '文章详情', '', array(
                $this->q('art_id', 'integer', true, '文章 art_id'),
            )),
            '/art/get_read_page' => $this->get('Art', '文章阅读单页', '返回 can_read、content_html、page_total 等。', array(
                $this->q('art_id', 'integer', true, '文章 art_id'),
                $this->q('page', 'integer', false, '页码，默认 1'),
            )),
            '/art/get_latest' => $this->get('Art', '最新文章', '每条含 art_link、art_pic、art_time_text。', array(
                $this->q('num', 'integer', false, '数量，默认 24'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
            )),
            '/art/get_hot' => $this->get('Art', '热门文章', '', array(
                $this->q('num', 'integer', false, '数量，默认 6'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('by', 'string', false, '排序，默认 time'),
            )),
            '/art/digg' => $this->get('Art', '文章顶/踩', 'Cookie 防重复（30 秒）。', array(
                $this->q('id', 'integer', true, '文章 art_id'),
                $this->q('type', 'string', false, 'up=顶，down=踩'),
            )),
            '/art/update_hits' => $this->get('Art', '更新/获取文章点击数', 'type=update 自增并返回新值。', array(
                $this->q('id', 'integer', true, '文章 art_id'),
                $this->q('type', 'string', false, '传 "update" 则自增'),
            )),
            '/art/update_score' => $this->get('Art', '文章评分', 'Cookie 防重复。', array(
                $this->q('id', 'integer', true, '文章 art_id'),
                $this->q('score', 'integer', false, '1-10 评分值'),
            )),
        );
    }

    private function pathsActor()
    {
        return array(
            '/actor/get_list' => $this->get('Actor', '演员列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '每页条数'),
                $this->q('id', 'integer', false, '演员 ID'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('sex', 'string', false, '男|女'),
                $this->q('area', 'string', false, '地区（<=255）'),
                $this->q('letter', 'string', false, '首字母（<=1）'),
                $this->q('level', 'string', false, '推荐等级（<=1）'),
                $this->q('name', 'string', false, '姓名（<=64）'),
                $this->q('blood', 'string', false, '血型（<=10）'),
                $this->q('starsign', 'string', false, '星座（<=255）'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
                $this->q('orderby', 'string', false, 'hits|hits_month|hits_week|hits_day|time|level'),
            )),
            '/actor/get_detail' => $this->get('Actor', '演员详情', '', array(
                $this->q('actor_id', 'integer', true, '演员 actor_id'),
            )),
            '/actor/get_recommend' => $this->get('Actor', '推荐明星', '每条含 actor_link、actor_pic。', array(
                $this->q('ids', 'string', false, '指定明星 ID，逗号分隔'),
                $this->q('num', 'integer', false, '数量，默认 8'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('by', 'string', false, '排序，默认 time'),
            )),
        );
    }

    private function pathsRole()
    {
        return array(
            '/role/get_list' => $this->get('Role', '角色列表', '', array(
                $this->q('offset', 'integer', false, '偏移，默认 0'),
                $this->q('limit', 'integer', false, '1~500，默认 20'),
                $this->q('rid', 'integer', false, '关联视频 ID'),
                $this->q('name', 'string', false, '角色名称模糊搜索（<=50）'),
                $this->q('letter', 'string', false, '首字母（<=1）'),
                $this->q('level', 'string', false, '推荐等级，逗号分隔（<=50）'),
                $this->q('actor', 'string', false, '配音/演员筛选（<=50）'),
                $this->q('orderby', 'string', false, 'id|time|time_add|hits|hits_day|hits_week|hits_month|score|up|down|level'),
            )),
            '/role/get_detail' => $this->get('Role', '角色详情', '返回含关联视频信息 vod_info。', array(
                $this->q('role_id', 'integer', true, '角色 role_id'),
            )),
            '/role/get_recommend' => $this->get('Role', '推荐角色', '', array(
                $this->q('rid', 'integer', false, '关联视频 ID'),
                $this->q('num', 'integer', false, '数量，默认 8'),
                $this->q('by', 'string', false, '排序，默认 time'),
                $this->q('level', 'string', false, '推荐等级'),
            )),
        );
    }

    private function pathsComment()
    {
        return array(
            '/comment/get_list' => $this->get('Comment', '评论列表', 'rows 主楼数组，每条含 sub 子楼数组。', array(
                $this->q('rid', 'integer', true, '资源 id（模板 data-id）'),
                $this->q('mid', 'integer', true, '模块 id（模板 data-mid）'),
                $this->q('offset', 'integer', false, '偏移，默认 0'),
                $this->q('limit', 'integer', false, '1~100，默认 20'),
                $this->q('orderby', 'string', false, 'time|up|down|id，默认 time'),
            )),
            '/comment/submit' => $this->post('Comment', '提交评论', '后台可强制登录/审核；有频率 Cookie 限制。', array(
                array('comment_mid', 'string', true, '模型 1|2|3|8|9|11|12'),
                array('comment_rid', 'integer', true, '资源 id'),
                array('comment_content', 'string', true, '正文'),
                array('comment_pid', 'integer', false, '父评论 id，默认 0'),
                array('comment_name', 'string', false, '游客昵称（已登录从 Cookie 取）'),
            )),
            '/comment/report' => $this->get('Comment', '举报评论', '', array(
                $this->q('id', 'integer', true, 'comment_id'),
            )),
            '/comment/digg' => $this->get('Comment', '评论顶/踩', '', array(
                $this->q('id', 'integer', true, 'comment_id'),
                $this->q('type', 'string', false, 'up|down'),
            )),
        );
    }

    private function pathsGbook()
    {
        return array(
            '/gbook/get_list' => $this->get('Gbook', '留言列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('id', 'integer', false, '留言 ID'),
                $this->q('rid', 'integer', false, '资源 ID'),
                $this->q('user_id', 'integer', false, '用户 ID'),
                $this->q('status', 'integer', false, '0~10'),
                $this->q('name', 'string', false, '昵称（<=20）'),
                $this->q('content', 'string', false, '内容模糊（<=20）'),
                $this->q('orderby', 'string', false, 'id|time|reply_time'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
            )),
            '/gbook/submit' => $this->post('Gbook', '提交留言', '后台可强制登录/审核；有频率 Cookie 限制。', array(
                array('gbook_content', 'string', true, '留言内容'),
                array('gbook_name', 'string', false, '游客昵称（已登录从 Cookie 取）'),
            )),
            '/gbook/report' => $this->get('Gbook', '举报留言', '', array(
                $this->q('id', 'integer', true, 'gbook_id'),
            )),
        );
    }

    private function pathsLink()
    {
        return array(
            '/link/get_list' => $this->get('Link', '友情链接列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('id', 'integer', false, '链接 ID'),
                $this->q('type', 'integer', false, '类型'),
                $this->q('name', 'string', false, '名称（<=100）'),
                $this->q('sort', 'integer', false, '排序'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
                $this->q('orderby', 'string', false, 'id|time|time_add'),
            )),
        );
    }

    private function pathsTopic()
    {
        return array(
            '/topic/get_list' => $this->get('Topic', '专题列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
                $this->q('orderby', 'string', false, 'id|time|time_add|score|hits|hits_day|hits_week|hits_month|up|down|level'),
            )),
            '/topic/get_detail' => $this->get('Topic', '专题详情', '', array(
                $this->q('topic_id', 'integer', true, '专题 topic_id'),
            )),
            '/topic/get_recommend' => $this->get('Topic', '推荐专题', '每条含 topic_link、topic_pic、topic_pic_slide。', array(
                $this->q('num', 'integer', false, '数量，默认 5'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('by', 'string', false, '排序，默认 time，可选 time|hits'),
            )),
        );
    }

    private function pathsType()
    {
        return array(
            '/type/get_list' => $this->get('Type', '分类树列表', '顶级含 children。', array(
                $this->q('type_id', 'integer', false, '传入则筛选该顶级'),
            )),
            '/type/get_all_list' => $this->get('Type', '分类顶栏（全部顶级）', '', array()),
            '/type/get_nav_types' => $this->get('Type', '导航栏分类', '每条含 type_extend（JSON 解析后）、children。', array(
                $this->q('ids', 'string', false, '指定分类 ID，逗号分隔'),
                $this->q('num', 'integer', false, '限制返回数量'),
                $this->q('mid', 'integer', false, '筛选模型 ID（type_mid）'),
                $this->q('parent', 'integer', false, '传 1 只返回父级分类'),
            )),
            '/type/get_type_with_children' => $this->get('Type', '分类及子分类', '返回父分类对象含 children。', array(
                $this->q('type_id', 'integer', true, '父分类 ID'),
                $this->q('num', 'integer', false, '子分类数量限制'),
            )),
        );
    }

    private function pathsUser()
    {
        return array(
            '/user/get_list' => $this->get('User', '用户资料列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('name', 'string', false, '用户名（<=50）'),
                $this->q('nickname', 'string', false, '昵称（<=50）'),
                $this->q('email', 'string', false, '邮箱（<=100）'),
                $this->q('qq', 'string', false, 'QQ（<=20）'),
                $this->q('phone', 'string', false, '手机（<=20）'),
                $this->q('time_start', 'integer', false, '起始时间戳'),
                $this->q('time_end', 'integer', false, '结束时间戳'),
                $this->q('group_id', 'integer', false, '用户组 ID'),
                $this->q('orderby', 'string', false, 'login_time|reg_time|points'),
            )),
            '/user/get_detail' => $this->get('User', '用户资料详情', '', array(
                $this->q('id', 'integer', true, '用户 ID'),
            )),
            '/user/get_reward_list' => $this->get('User', '分销推广下线列表', '需登录。', array(
                $this->q('level', 'integer', false, '下线层级 1|2|3，默认 1'),
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20，最大 100'),
            )),
            '/user/login' => $this->post('User', '登录', '', array(
                array('user_name', 'string', true, '用户名'),
                array('user_pwd', 'string', true, '密码'),
            )),
            '/user/register' => $this->post('User', '注册', '', array(
                array('user_name', 'string', true, '用户名'),
                array('user_pwd', 'string', true, '密码'),
                array('user_pwd2', 'string', true, '确认密码'),
                array('user_email', 'string', false, '邮箱'),
                array('user_phone', 'string', false, '手机'),
                array('invite_code', 'string', false, '邀请码'),
            )),
            '/user/logout' => $this->get('User', '退出登录', '任意方式调用。', array()),
            '/user/get_info' => $this->get('User', '当前用户资料', '需登录；返回常用字段 + user_portrait。', array()),
            '/user/update_info' => $this->post('User', '更新资料', '需登录；改密传 user_old_pwd + user_new_pwd。', array(
                array('user_nick_name', 'string', false, '昵称'),
                array('user_email', 'string', false, '邮箱'),
                array('user_phone', 'string', false, '手机'),
                array('user_qq', 'string', false, 'QQ'),
                array('user_old_pwd', 'string', false, '旧密码（改密时）'),
                array('user_new_pwd', 'string', false, '新密码（改密时）'),
            )),
            '/user/get_ulog' => $this->get('User', '用户行为日志', '需登录。', array(
                $this->q('page', 'integer', false, '页码'),
                $this->q('limit', 'integer', false, '每页条数'),
                $this->q('type', 'integer', false, '1浏览 2收藏 3想看 4播放 5下载'),
                $this->q('mid', 'integer', false, '模型 ID'),
            )),
            '/user/get_read_chapters' => $this->get('User', '已读章节列表', '需登录；返回某文章/小说或漫画作品实际读过的章节（供阅读进度还原）。', array(
                $this->q('mid', 'integer', true, '模型 ID（2=文章/小说 Art，12=漫画 Manga）'),
                $this->q('rid', 'integer', true, '作品 ID'),
                $this->q('sid', 'integer', false, '卷/分组 ID：漫画(mid=12)必填且需 ≥1；文章/小说(mid=2)内部固定为 1，可不传'),
            )),
            '/user/add_ulog' => $this->post('User', '添加/更新行为日志', '需登录，仅 POST。CSRF：Cookie 会话须带 CSRF（__token__ 或 X-CSRF-Token，或同站 X-Requested-With）；Bearer JWT 客户端凭证在 Authorization 头、不依赖 Cookie，免会话型 CSRF。type=4 且 mid∈{2,12} 记为章节阅读进度。', array(
                array('mid', 'integer', true, '模型 ID'),
                array('rid', 'integer', true, '资源 ID'),
                array('type', 'integer', true, '行为类型（1浏览 2收藏 3想看 4播放/阅读 5下载）'),
                array('sid', 'integer', false, '播放源/卷分组'),
                array('nid', 'integer', false, '集数/章节'),
            )),
            '/user/del_ulog' => $this->post('User', '删除行为日志', '需登录；all=1 且 type=1..5 清空该类。', array(
                array('ids', 'string', false, 'ulog_id 列表，逗号分隔'),
                array('all', 'integer', false, '传 1 清空'),
                array('type', 'integer', false, '清空时指定类型'),
            )),
            '/user/get_plog' => $this->get('User', '积分日志', '需登录。', array(
                $this->q('page', 'integer', false, '页码'),
                $this->q('limit', 'integer', false, '每页条数'),
                $this->q('filter', 'string', false, 'income|expense'),
            )),
            '/user/del_plog' => $this->post('User', '删除积分日志', '需登录。', array(
                array('ids', 'string', false, '积分日志 ID 列表'),
                array('all', 'integer', false, '传 1 清空'),
            )),
            '/user/get_orders' => $this->get('User', '充值订单列表', '需登录。', array(
                $this->q('page', 'integer', false, '页码'),
                $this->q('limit', 'integer', false, '每页条数'),
            )),
            '/user/find_password' => $this->post('User', '找回密码发码', '', array(
                array('user_email', 'string', false, '邮箱'),
                array('user_phone', 'string', false, '手机'),
            )),
            '/user/get_my_invite' => $this->get('User', '邀请信息', '需登录。', array()),
            '/user/get_invite_list' => $this->get('User', '邀请记录列表', '需登录。', array()),
            '/user/get_favorites_status' => $this->get('User', '批量收藏状态', '需登录；返回 rows[{rid,is_fav,ulog_id}]。', array(
                $this->q('vod_ids', 'string', true, '视频 ID，逗号分隔'),
                $this->q('mid', 'integer', false, '模型，默认 1'),
                $this->q('ulog_type', 'integer', false, '默认 2 收藏'),
            )),
            '/user/login_or_register' => $this->post('User', '登录/注册一体化', '帐号存在则登录，不存在则自动创建。有 IP 速率限制。', array(
                array('user_name', 'string', true, '用户名'),
                array('user_pwd', 'string', true, '密码'),
                array('invite_code', 'string', false, '邀请码'),
            )),
            '/user/ajax_upgrade_data' => $this->get('User', '升级页数据', '不强制登录；返回会员信息 + 可购套餐。', array()),
            '/user/upgrade_order_create' => $this->post('User', '会员现金升级（创建 UPG 订单）', '需登录；订单号前缀 UPG。', array(
                array('group_id', 'integer', true, '目标用户组 ID（>=3）'),
                array('long', 'string', true, 'day|week|month|year'),
            )),
        );
    }

    private function pathsUlog()
    {
        return array(
            '/ulog/progress' => $this->post('Ulog', '上报播放进度', '需登录；未登录返回 code=1401。建议 10~15 秒节流上报。', array(
                array('vod_id', 'integer', true, '影片 ID'),
                array('sid', 'integer', false, '播放源'),
                array('nid', 'integer', false, '集数'),
                array('point', 'integer', false, '已观看秒数'),
                array('duration', 'integer', false, '总时长秒'),
            )),
            '/ulog/get_progress' => $this->get('Ulog', '读取上次播放进度', '需登录；无记录时 info=null。', array(
                $this->q('vod_id', 'integer', true, '影片 ID'),
                $this->q('nid', 'integer', false, '指定集数；不传返回最近一集'),
            )),
            '/ulog/merge' => $this->post('Ulog', '登录后合并本地进度', '需登录；单次最多 50 条。', array(
                array('list', 'string', true, 'JSON 字符串或数组，每项 vod_id,sid,nid,point,duration'),
            )),
            '/ulog/report_fail' => $this->post('Ulog', '上报线路播放失败', '无需登录；同一(vod_id,sid,nid) 30 秒内重复上报被节流。', array(
                array('vod_id', 'integer', true, '影片 ID'),
                array('sid', 'integer', false, '线路序号，从 0 开始，默认 0'),
                array('nid', 'integer', false, '集数序号，从 0 开始，默认 0'),
                array('play_from', 'string', false, '播放器/来源标识'),
                array('vod_name', 'string', false, '影片名称（会做长度截断）'),
                array('switched', 'integer', false, '是否已切换 1|0，默认 0'),
            )),
        );
    }

    private function pathsWebsite()
    {
        return array(
            '/website/get_list' => $this->get('Website', '网址列表', '', array(
                $this->q('offset', 'integer', false, '偏移'),
                $this->q('limit', 'integer', false, '1~500'),
                $this->q('type_id', 'integer', false, '分类 ID（1~100）'),
                $this->q('name', 'string', false, '名称（<=20）'),
                $this->q('sub', 'string', false, '副标题（<=20）'),
                $this->q('en', 'string', false, '英文名（<=20）'),
                $this->q('status', 'integer', false, '1~9'),
                $this->q('letter', 'string', false, '首字母（<=1）'),
                $this->q('area', 'string', false, '地区（<=10）'),
                $this->q('lang', 'string', false, '语言（<=10）'),
                $this->q('level', 'integer', false, '推荐等级 1~9'),
                $this->q('start_time', 'integer', false, '起始时间戳'),
                $this->q('end_time', 'integer', false, '结束时间戳'),
                $this->q('tag', 'string', false, '标签（<=20）'),
                $this->q('orderby', 'string', false, 'id|time|time_add|score|hits|up|down|level'),
            )),
            '/website/get_detail' => $this->get('Website', '网址详情', '', array(
                $this->q('website_id', 'integer', true, '网址 website_id'),
            )),
        );
    }

    private function pathsManga()
    {
        return array(
            '/manga/get_list' => $this->get('Manga', '漫画列表', '', array(
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20'),
                $this->q('t', 'integer', false, '分类 ID（type_id）'),
                $this->q('ids', 'string', false, 'ID 列表，逗号分隔'),
                $this->q('wd', 'string', false, '搜索关键字（manga_name 模糊）'),
                $this->q('order', 'string', false, '排序，默认 manga_time desc'),
            )),
            '/manga/get_detail' => $this->get('Manga', '漫画详情', '', array(
                $this->q('id', 'integer', true, '漫画 ID'),
            )),
            '/manga/get_chapter' => $this->get('Manga', '漫画单话阅读', '返回 can_read、images、episode_total 等。', array(
                $this->q('id', 'integer', true, '漫画 ID'),
                $this->q('sid', 'integer', false, '默认 1'),
                $this->q('nid', 'integer', false, '默认 1'),
            )),
            '/manga/get_latest' => $this->get('Manga', '最新漫画', '每条含 manga_link、manga_pic、manga_time_text。', array(
                $this->q('num', 'integer', false, '数量，默认 24'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
            )),
            '/manga/get_hot' => $this->get('Manga', '热门漫画', '', array(
                $this->q('num', 'integer', false, '数量，默认 6'),
                $this->q('start', 'integer', false, '偏移量，默认 0'),
                $this->q('by', 'string', false, '排序，默认 hits_month'),
            )),
        );
    }

    private function pathsNovel()
    {
        return array(
            '/novel/get_list' => $this->get('Novel', '小说列表', '受 PublicApi 约束。', array(
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20'),
                $this->q('t', 'integer', false, '分类 ID（type_id）'),
                $this->q('ids', 'string', false, 'ID 列表，逗号分隔'),
                $this->q('wd', 'string', false, '搜索关键字（novel_name 模糊）'),
                $this->q('order', 'string', false, '排序，默认 novel_time desc'),
            )),
            '/novel/get_detail' => $this->get('Novel', '小说详情', '', array(
                $this->q('id', 'integer', true, '小说 ID（novel_id）'),
            )),
        );
    }

    private function pathsConfig()
    {
        return array(
            '/config/get_config' => $this->get('Config', '获取配置', '返回 site_banner、site_app_launch_image 等。', array()),
            '/config/get_extra_var' => $this->get('Config', '预留参数', '扩展分类/地区/年份等，与后台「预留参数」一致。', array()),
            '/config/get_tpl_config' => $this->get('Config', '站点壳层 + config.json', 'tpl_config 来自 template/{模板目录}/config.json。', array()),
            '/config/get_mctheme' => $this->get('Config', '主题配置', '与 PC 模板 assign 同源，含 theme.ad_slots、theme.ads。', array()),
            '/config/get_ads_files' => $this->get('Config', '广告脚本文件清单', '扫描 template/{模板目录}/{ads_dir}/*.js。', array()),
        );
    }

    private function pathsAuth()
    {
        return array(
            '/auth/me' => $this->get('Auth', '获取当前用户状态', '未登录也返回 code=1，但 info.is_login=0。', array()),
            '/auth/permission' => $this->get('Auth', '获取资源权限', 'deny_reason: NOT_LOGIN/VIP_REQUIRED/POINTS_NOT_ENOUGH/RESOURCE_OFFLINE/RESOURCE_NOT_FOUND/GROUP_PERMISSION_DENIED。', array(
                $this->q('mid', 'integer', true, '资源模块 1=视频 2=文章 6=漫画'),
                $this->q('id', 'integer', true, '资源 id'),
                $this->q('action', 'string', false, 'play|read|download|comment|favorite；不传返回全部权限位'),
            )),
        );
    }

    private function pathsOrder()
    {
        return array(
            '/order/get_list' => $this->get('Order', '订单列表', '需登录。', array(
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20，最大 100'),
                $this->q('status', 'integer', false, '0=未支付 1=已支付'),
            )),
            '/order/get_detail' => $this->get('Order', '订单详情', '需登录；order_id 与 order_code 二选一。', array(
                $this->q('order_id', 'integer', false, '订单 ID'),
                $this->q('order_code', 'string', false, '订单号'),
            )),
            '/order/check_status' => $this->get('Order', '查询订单状态', '', array(
                $this->q('order_code', 'string', true, '订单号'),
            )),
            '/order/create' => $this->post('Order', '创建充值订单', '需登录；成功返回 order_code（前缀 PAY）等。', array(
                array('price', 'number', true, '充值金额（元）'),
            )),
        );
    }

    private function pathsPayment()
    {
        return array(
            '/payment/get_config' => $this->get('Payment', '获取支付配置', '未登录也返回支付方式列表。返回 min、scale、methods、card_config 等。', array()),
            '/payment/gopay' => $this->post('Payment', '发起支付（收银台）', 'Content-Type 为 application/x-www-form-urlencoded。payment_data 形态：qrcode/data/form/unknown。', array(
                array('order_code', 'string', true, '订单号'),
                array('order_id', 'integer', true, '订单 ID'),
                array('payment', 'string', true, '支付方式小写 alipay|weixin|codepay|zhapay|epay 等'),
                array('paytype', 'string', false, '子通道（与 get_config methods[].paytypes[].value 对应）'),
            )),
            '/payment/notify' => array(
                'get' => array(
                    'tags' => array('Payment'),
                    'summary' => '支付回调通知（GET）',
                    'description' => '由第三方支付平台异步调用，不需要用户登录。回调地址 /api.php/payment/notify/pay_type/{type}。',
                    'parameters' => array(
                        $this->q('pay_type', 'string', true, '支付类型 alipay|weixin 等'),
                    ),
                    'responses' => $this->responses(),
                ),
                'post' => array(
                    'tags' => array('Payment'),
                    'summary' => '支付回调通知（POST）',
                    'description' => '由第三方支付平台异步调用，不需要用户登录。',
                    'parameters' => array(
                        $this->q('pay_type', 'string', true, '支付类型 alipay|weixin 等'),
                    ),
                    'responses' => $this->responses(),
                ),
            ),
            '/payment/use_card' => $this->post('Payment', '卡密充值', '', array(
                array('card_no', 'string', true, '充值卡卡号'),
                array('card_pwd', 'string', true, '充值卡密码'),
            )),
            '/payment/buy_popedom' => $this->post('Payment', '积分购买内容权限', '已购买不重复扣费；积分不足返回 code=1005。', array(
                array('mid', 'integer', true, '模型 1=视频 2=文章'),
                array('id', 'integer', true, '资源 ID'),
                array('type', 'integer', true, '1=文章阅读 4=播放 5=下载'),
                array('sid', 'integer', false, '播放源编号'),
                array('nid', 'integer', false, '集编号'),
            )),
            '/payment/upgrade' => $this->post('Payment', '会员升级', '积分 = 对应用户组 group_points_{long}；有效期内时间叠加。', array(
                array('group_id', 'integer', true, '目标用户组 ID（>=3）'),
                array('long', 'string', true, 'day|week|month|year'),
            )),
            '/payment/get_groups' => $this->get('Payment', '获取可升级用户组列表', '只返回 group_id>=3 且已启用的付费用户组。', array()),
            '/payment/get_cards' => $this->get('Payment', '充值卡使用记录', '', array(
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20，最大 100'),
            )),
        );
    }

    private function pathsCash()
    {
        return array(
            '/cash/get_config' => $this->get('Cash', '获取提现配置', '需登录。返回 cash_status、cash_min、cash_ratio。', array()),
            '/cash/get_list' => $this->get('Cash', '提现列表', '需登录。', array(
                $this->q('page', 'integer', false, '页码，默认 1'),
                $this->q('limit', 'integer', false, '每页条数，默认 20，最大 100'),
                $this->q('status', 'integer', false, '0=待审核 1=已审核'),
            )),
            '/cash/get_detail' => $this->get('Cash', '提现详情', '需登录。', array(
                $this->q('cash_id', 'integer', true, '提现记录 ID'),
            )),
            '/cash/create' => $this->post('Cash', '提交提现申请', '需登录；需后台开启提现；提现后积分冻结待审核。', array(
                array('cash_money', 'number', true, '提现金额（元）'),
                array('cash_bank_name', 'string', true, '银行名称'),
                array('cash_bank_no', 'string', true, '银行账号'),
                array('cash_payee_name', 'string', true, '收款人姓名'),
            )),
            '/cash/del' => $this->post('Cash', '删除提现记录', '需登录；未审核记录删除后冻结积分自动恢复。', array(
                array('ids', 'string', false, '提现记录 ID 列表，逗号分隔'),
                array('all', 'string', false, '传 "1" 删除全部'),
            )),
        );
    }

    private function pathsChatroom()
    {
        return array(
            '/chatroom/get_list' => $this->get('Chatroom', '获取聊天消息列表', '支持增量拉取。受 PublicApi 约束。', array(
                $this->q('vod_id', 'integer', true, '影片 ID'),
                $this->q('after_id', 'integer', false, '上次最后一条 chat_id（增量）'),
                $this->q('limit', 'integer', false, '数量，默认 50，最大 100'),
            )),
            '/chatroom/send' => $this->post('Chatroom', '发送聊天消息', '需登录；有 IP 黑名单与频率限制（3 秒）。', array(
                array('vod_id', 'integer', true, '影片 ID'),
                array('content', 'string', true, '聊天内容（最长 500 字）'),
            )),
            '/chatroom/report' => $this->post('Chatroom', '举报聊天消息', '需登录。', array(
                array('chat_id', 'integer', true, '聊天消息 ID'),
            )),
        );
    }

    private function pathsDanmaku()
    {
        return array(
            '/danmaku/get_list' => $this->get('Danmaku', '获取弹幕列表', '一次性加载。受 PublicApi 约束。', array(
                $this->q('vod_id', 'integer', true, '影片 ID'),
                $this->q('sid', 'integer', true, '播放源 ID'),
                $this->q('nid', 'integer', true, '集数 ID'),
                $this->q('limit', 'integer', false, '数量，默认 1000，最大 2000'),
            )),
            '/danmaku/dplayer' => array(
                'get' => array(
                    'tags' => array('Danmaku'),
                    'summary' => 'DPlayer 兼容格式弹幕（获取）',
                    'description' => '返回 DPlayer 标准格式 {code:0|1,data:[...]}。',
                    'parameters' => array(
                        $this->q('id', 'string', true, '格式 {vod_id}-{sid}-{nid}（连字符拼接）'),
                    ),
                    'responses' => $this->responses(),
                ),
                'post' => array(
                    'tags' => array('Danmaku'),
                    'summary' => 'DPlayer 兼容格式弹幕（发送）',
                    'description' => '需登录；DPlayer 约定 code=0 为成功。有频率限制（5 秒）。',
                    'requestBody' => array(
                        'required' => true,
                        'content' => array(
                            'application/x-www-form-urlencoded' => array(
                                'schema' => array(
                                    'type' => 'object',
                                    'required' => array('id', 'text'),
                                    'properties' => array(
                                        'id' => array('type' => 'string', 'description' => '{vod_id}-{sid}-{nid}'),
                                        'time' => array('type' => 'number', 'description' => '秒数'),
                                        'type' => array('type' => 'integer', 'description' => '0滚动/1顶部/2底部'),
                                        'color' => array('type' => 'string', 'description' => '颜色'),
                                        'text' => array('type' => 'string', 'description' => '弹幕内容'),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'responses' => $this->responses(),
                ),
            ),
            '/danmaku/send' => $this->post('Danmaku', '发送弹幕', '需登录；有 IP 黑名单与频率限制（5 秒）。', array(
                array('vod_id', 'integer', true, '影片 ID'),
                array('sid', 'integer', true, '播放源 ID'),
                array('nid', 'integer', true, '集数 ID'),
                array('time', 'number', true, '播放到的秒数'),
                array('text', 'string', true, '弹幕内容（最长 200 字）'),
                array('type', 'integer', false, '0=滚动 1=顶部 2=底部，默认 0'),
                array('color', 'string', false, '颜色，默认 #FFFFFF'),
            )),
            '/danmaku/report' => $this->post('Danmaku', '举报弹幕', '需登录。', array(
                array('danmaku_id', 'integer', true, '弹幕 ID'),
            )),
        );
    }

    private function pathsTask()
    {
        return array(
            '/task/get_task_list' => $this->get('Task', '获取任务列表及完成状态', '需登录。返回 daily_tasks、newbie_tasks、sign_info 等。', array()),
            '/task/daily_sign' => $this->post('Task', '每日签到', '需登录。', array()),
            '/task/get_sign_info' => $this->get('Task', '获取签到信息（含里程碑）', '需登录。', array()),
            '/task/claim_sign_milestone' => $this->post('Task', '领取签到里程碑奖励', '需登录。', array(
                array('milestone_id', 'integer', true, '里程碑 ID'),
            )),
            '/task/claim_reward' => $this->post('Task', '领取任务奖励', '需登录。', array(
                array('task_id', 'integer', true, '任务 ID'),
            )),
            '/task/report_progress' => $this->post('Task', '上报每日任务进度', '需登录。', array(
                array('task_action', 'string', true, 'watch_vod|share_vod|post_comment'),
            )),
        );
    }

    private function pathsLive()
    {
        return array(
            '/live/get_category' => $this->get('Live', '获取直播分类列表', '仅返回 cate_status=1 的分类。受 PublicApi 约束。', array()),
            '/live/get_list' => $this->get('Live', '获取直播频道列表', '仅返回 live_status=1 的频道；含 cate_name、live_url_list。', array(
                $this->q('cate_id', 'integer', false, '分类 ID 筛选'),
                $this->q('offset', 'integer', false, '偏移，默认 0'),
                $this->q('limit', 'integer', false, '1~500，默认 20'),
                $this->q('orderby', 'string', false, 'sort|hits|hits_day|hits_week|time|id'),
                $this->q('name', 'string', false, '频道名称模糊（<=100）'),
                $this->q('level', 'integer', false, '推荐等级（>=该值）0~9'),
            )),
            '/live/get_detail' => $this->get('Live', '获取直播频道详情', '自动增加点击量；不存在或禁用返回 code=1002。', array(
                $this->q('live_id', 'integer', true, '频道 ID'),
            )),
        );
    }

    private function pathsReceive()
    {
        return array(
            '/receive/vod' => $this->get('Receive', '推送视频', '需后台开启接口并配置 >=16 位密码。', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('vod_name', 'string', true, '影片名称'),
                $this->q('type_id', 'integer', false, '分类 ID（与 type_name 二选一）'),
                $this->q('type_name', 'string', false, '分类名称（与 type_id 二选一）'),
            )),
            '/receive/art' => $this->get('Receive', '推送文章', '', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('art_name', 'string', true, '文章名称'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('type_name', 'string', false, '分类名称'),
            )),
            '/receive/actor' => $this->get('Receive', '推送演员', '', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('actor_name', 'string', true, '演员名称'),
                $this->q('actor_sex', 'string', true, '性别'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('type_name', 'string', false, '分类名称'),
            )),
            '/receive/role' => $this->get('Receive', '推送角色', '', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('role_name', 'string', true, '角色名称'),
                $this->q('role_actor', 'string', true, '配音/演员'),
                $this->q('vod_name', 'string', false, '关联影片名（与 douban_id 二选一）'),
                $this->q('douban_id', 'string', false, '豆瓣 ID（与 vod_name 二选一）'),
            )),
            '/receive/website' => $this->get('Receive', '推送网址', '', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('website_name', 'string', true, '网址名称'),
                $this->q('type_id', 'integer', false, '分类 ID'),
                $this->q('type_name', 'string', false, '分类名称'),
            )),
            '/receive/comment' => $this->get('Receive', '推送评论', '', array(
                $this->q('pass', 'string', true, '接口密码'),
                $this->q('comment_name', 'string', true, '昵称'),
                $this->q('comment_content', 'string', true, '评论内容'),
                $this->q('comment_mid', 'integer', true, '模型 ID'),
                $this->q('rel_name', 'string', false, '关联资源名（与 douban_id 二选一）'),
                $this->q('douban_id', 'string', false, '豆瓣 ID（与 rel_name 二选一）'),
            )),
        );
    }

    private function pathsSycms()
    {
        return array(
            '/sycms/index' => $this->get('Sycms', 'SyCMS 主入口', '远程采集转换为 MacCMS 标准格式。不继承 PublicApi 检查。', array(
                $this->q('ac', 'string', false, 'list|detail|class，默认 list'),
                $this->q('t', 'integer', false, '分类 ID'),
                $this->q('pg', 'integer', false, '页码'),
                $this->q('limit', 'integer', false, '每页数量，最大 100'),
                $this->q('wd', 'string', false, '搜索关键字'),
                $this->q('ids', 'string', false, '视频 ID（用于 detail）'),
            )),
            '/sycms/getClassList' => $this->get('Sycms', '获取分类列表', '', array()),
            '/sycms/getVideoList' => $this->get('Sycms', '获取视频列表', '', array()),
            '/sycms/getVideoDetail' => $this->get('Sycms', '获取视频详情', '', array(
                $this->q('ids', 'string', false, '视频 ID'),
            )),
            '/sycms/test' => $this->get('Sycms', '测试连接', '', array()),
        );
    }

    private function pathsSystem()
    {
        return array(
            '/timming/index' => $this->get('System', '执行定时任务', '由服务器 cron 调用，非面向前端用户。', array(
                $this->q('name', 'string', false, '任务名称（指定执行某条；不传遍历全部）'),
                $this->q('enforce', 'string', false, '传 "1" 强制执行（忽略时间窗口）'),
            )),
            '/wechat/index' => array(
                'get' => array(
                    'tags' => array('System'),
                    'summary' => '微信公众号接入（验证）',
                    'description' => 'GET 含 echostr 参数：微信服务器验证。需后台配置微信参数且 status=1。',
                    'parameters' => array(
                        $this->q('echostr', 'string', false, '微信服务器验证字符串'),
                        $this->q('signature', 'string', false, '微信签名'),
                        $this->q('timestamp', 'string', false, '时间戳'),
                        $this->q('nonce', 'string', false, '随机数'),
                    ),
                    'responses' => $this->responses(),
                ),
                'post' => array(
                    'tags' => array('System'),
                    'summary' => '微信公众号接入（消息）',
                    'description' => 'POST：接收微信用户消息并自动回复。',
                    'responses' => $this->responses(),
                ),
            ),
        );
    }
}
