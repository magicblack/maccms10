<?php

namespace app\api\controller;

use think\Controller;
use think\Cache;
use think\Db;
use think\Request;
use think\Validate;

class Vod extends Base
{
    use PublicApi;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();

    }

    public function index()
    {

    }

    /**
     *  获取视频列表
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        // 参数校验
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;
        // 查询条件组装（与前台分类一致：父类下视频多为子类 type_id + 父类 type_id_1）
        $where = [];
        $where['vod_status'] = ['eq', 1];
        if (isset($param['type_id'])) {
            $tid = (int)$param['type_id'];
            if ($tid > 0) {
                $where['type_id|type_id_1'] = ['eq', $tid];
            }
        }
        if (isset($param['id'])) {
            $where['vod_id'] = (int)$param['id'];
        }
//        if (isset($param['type_id_1'])) {
//            $where['type_id_1'] = (int)$param['type_id_1'];
//        }
        if (!empty($param['vod_letter'])) {
            $where['vod_letter'] = $param['vod_letter'];
        }
        if (isset($param['vod_tag']) && strlen($param['vod_tag']) > 0) {
            $where['vod_tag'] = ['like', '%' . $this->format_sql_string($param['vod_tag']) . '%'];
        }
        if (isset($param['vod_name']) && strlen($param['vod_name']) > 0) {
            $where['vod_name'] = ['like', '%' . $this->format_sql_string($param['vod_name']) . '%'];
        }
        if (isset($param['vod_blurb']) && strlen($param['vod_blurb']) > 0) {
            $where['vod_blurb'] = ['like', '%' . $this->format_sql_string($param['vod_blurb']) . '%'];
        }
        if (isset($param['vod_class']) && strlen($param['vod_class']) > 0) {
            $where['vod_class'] = ['like', '%' . $this->format_sql_string($param['vod_class']) . '%'];
        }
        if (isset($param['vod_area']) && strlen($param['vod_area']) > 0) {
            $where['vod_area'] = $this->format_sql_string($param['vod_area']);
        }
        if (isset($param['vod_year']) && strlen($param['vod_year']) > 0) {
            $where['vod_year'] = $this->format_sql_string($param['vod_year']);
        }
        if (isset($param['vod_lang']) && strlen($param['vod_lang']) > 0) {
            $where['vod_lang'] = $this->format_sql_string($param['vod_lang']);
        }
        if (isset($param['vod_level']) && strlen($param['vod_level']) > 0) {
            $where['vod_level'] = ['in', $this->format_sql_string($param['vod_level'])];
        }
        if (isset($param['vod_state']) && strlen($param['vod_state']) > 0) {
            $where['vod_state'] = $this->format_sql_string($param['vod_state']);
        }
        if (isset($param['vod_isend']) && strlen($param['vod_isend']) > 0) {
            $where['vod_isend'] = (int)$param['vod_isend'];
        }
        if (isset($param['vod_actor']) && strlen($param['vod_actor']) > 0) {
            $an = $this->format_sql_string($param['vod_actor']);
            if (strlen($an) > 0) {
                $where['vod_actor'] = ['like', mac_like_arr($an), 'OR'];
            }
        }
        // 数据获取
        $total = model('Vod')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "vod_time DESC";
            if (!empty($param['orderby'])) {
                $order = 'vod_' . $param['orderby'] . " DESC";
            }
            $field = 'vod_id,vod_en,vod_name,vod_sub,vod_pic,vod_actor,vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_time,vod_remarks,vod_score,vod_area,vod_year,vod_class,vod_blurb,vod_points_play,vod_isend,type_id,type_id_1';
//            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field, []);
            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field);

            // 补充 vod_pic、vod_link；主题「进播放页」时补充 vod_play_link（与 mac_url_vod_play 一致，避免前端拼 URL 与伪静态不一致）
            $playlinkOn = mac_tpl_vod_playlink_on();
            foreach ($list as &$v) {
                $v['vod_pic'] = mac_url_img($v['vod_pic'] ?? '');
                $v['vod_link'] = mac_url_vod_detail($v);
                if ($playlinkOn) {
                    $v['vod_play_link'] = mac_url_vod_play($v, ['sid' => 1, 'nid' => 1]);
                } else {
                    $v['vod_play_link'] = '';
                }
            }
            unset($v);

            // 与 model Vod::listData 一致：mac_get_vip_exclusive_type_ids()（会员组播放权限 popedom 3）
            mac_append_type_is_vip_exclusive_for_rows($list);
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'offset' => $offset,
                'limit'  => $limit,
                'total'  => $total,
                'rows'   => $list,
            ],
        ]);
    }

    /**
     * 视频详细信息
     *
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_detail(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $res = Db::table('mac_vod')->where(['vod_id' => $param['vod_id']])->find();
        if (empty($res)) {
            return json(['code' => 1001, 'msg' => '数据不存在']);
        }

        // 处理图片 URL
        $res['vod_pic'] = mac_url_img($res['vod_pic']);
        $res['vod_pic_thumb'] = mac_url_img($res['vod_pic_thumb'] ?? '');
        $res['vod_pic_slide'] = mac_url_img($res['vod_pic_slide'] ?? '');
        $res['vod_link'] = mac_url_vod_detail($res);

        // 解析播放源列表 vod_play_list
        $playList = [];
        if (!empty($res['vod_play_from']) && !empty($res['vod_play_url'])) {
            $playerConfig = config('maccms.player') ?: [];
            $froms = explode('$$$', $res['vod_play_from']);
            $urls = explode('$$$', $res['vod_play_url']);
            foreach ($froms as $k => $from) {
                $from = trim($from);
                if (empty($from)) continue;
                $episodes = [];
                $urlStr = isset($urls[$k]) ? $urls[$k] : '';
                if (!empty($urlStr)) {
                    $parts = explode('#', $urlStr);
                    foreach ($parts as $idx => $part) {
                        $part = trim($part);
                        if (empty($part)) continue;
                        $arr = explode('$', $part);
                        $episodes[] = [
                            'name' => isset($arr[0]) ? $arr[0] : '第' . ($idx + 1) . '集',
                            'url'  => isset($arr[1]) ? $arr[1] : $arr[0],
                        ];
                    }
                }
                $show = $from;
                if (isset($playerConfig[$from]) && !empty($playerConfig[$from]['show'])) {
                    $show = $playerConfig[$from]['show'];
                }
                $playList[] = [
                    'from'        => $from,
                    'player_info' => ['show' => $show, 'from' => $from],
                    'urls'        => $episodes,
                ];
            }
        }
        $res['vod_play_list'] = $playList;

        // 解析下载源列表
        $downList = [];
        if (!empty($res['vod_down_from']) && !empty($res['vod_down_url'])) {
            $froms = explode('$$$', $res['vod_down_from']);
            $urls = explode('$$$', $res['vod_down_url']);
            foreach ($froms as $k => $from) {
                $from = trim($from);
                if (empty($from)) continue;
                $episodes = [];
                $urlStr = isset($urls[$k]) ? $urls[$k] : '';
                if (!empty($urlStr)) {
                    $parts = explode('#', $urlStr);
                    foreach ($parts as $idx => $part) {
                        $part = trim($part);
                        if (empty($part)) continue;
                        $arr = explode('$', $part);
                        $episodes[] = [
                            'name' => isset($arr[0]) ? $arr[0] : '下载' . ($idx + 1),
                            'url'  => isset($arr[1]) ? $arr[1] : $arr[0],
                        ];
                    }
                }
                $downList[] = [
                    'from' => $from,
                    'urls' => $episodes,
                ];
            }
        }
        $res['vod_down_list'] = $downList;

        // 清理原始大字段（可选）
        unset($res['vod_play_url'], $res['vod_play_server'], $res['vod_play_note']);
        unset($res['vod_down_url'], $res['vod_down_server'], $res['vod_down_note']);

        // 与 get_list / model Vod 一致：mac_get_vip_exclusive_type_ids()
        $detailWrap = [$res];
        mac_append_type_is_vip_exclusive_for_rows($detailWrap);
        $res = $detailWrap[0];

        $uid = (int) ($GLOBALS['user']['user_id'] ?? 0);
        $vid = (int) ($res['vod_id'] ?? 0);
        $fav = mac_user_fav_state($uid, 1, $vid);
        $res['is_fav'] = $fav['is_fav'];
        $res['fav_ulog_id'] = $fav['fav_ulog_id'];
        $res['user_has_up'] = mac_user_has_digg(1, $vid);

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res
        ]);
    }

    /**
     * 获取视频的年份
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_year(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_year')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_year'])){
                array_push($return,$item['vod_year']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }

    /**
     * 获取该视频类型名称
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_class(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_class')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_class'])){
                array_push($return,$item['vod_class']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }

    /**
     * 获取该视频类型的地区
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_area(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_area')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_area'])){
                array_push($return,$item['vod_area']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }

    /**
     * 获取 Banner 推荐影片
     * 对应首页 Banner 轮播区，取推荐等级高的影片
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num   - 可选，数量，默认5
     *   start - 可选，偏移量，默认0（换一换分页）
     *   level - 可选，推荐等级，默认9，多个用逗号分隔
     */
    public function get_banner(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 5;
        $start = isset($param['start']) ? (int)$param['start'] : 0;
        if ($start < 0) {
            $start = 0;
        }
        $level = isset($param['level']) ? trim($param['level']) : '9';

        $where = [];
        $where['vod_status'] = ['eq', 1];
        $where['vod_level'] = ['in', $level];

        $list = Db::table('mac_vod')
            ->field('vod_id,vod_name,vod_sub,vod_pic,vod_pic_slide,vod_actor,vod_director,vod_score,vod_content,vod_blurb,vod_remarks,vod_year,vod_area,vod_class,vod_points_play,type_id,type_id_1')
            ->where($where)
            ->order('vod_time desc')
            ->limit($start, $num)
            ->select();

        $userId = intval($GLOBALS['user']['user_id'] ?? 0);
        $favMap = [];
        if ($userId > 0 && !empty($list)) {
            $vodIds = [];
            foreach ($list as $v) {
                if (!empty($v['vod_id'])) {
                    $vodIds[] = intval($v['vod_id']);
                }
            }
            if (!empty($vodIds)) {
                $favRows = model('Ulog')->where([
                    'user_id' => $userId,
                    'ulog_type' => 2,
                    'ulog_rid' => ['in', implode(',', array_unique($vodIds))],
                ])->column('ulog_id', 'ulog_rid');
                if (is_array($favRows)) {
                    $favMap = $favRows;
                }
            }
        }

        foreach ($list as &$v) {
            $v['vod_pic'] = mac_url_img($v['vod_pic']);
            $v['vod_pic_slide'] = mac_url_img($v['vod_pic_slide']);
            $v['vod_link'] = mac_url_vod_detail($v);
            $vodId = intval($v['vod_id'] ?? 0);
            $favUid = isset($favMap[$vodId]) ? intval($favMap[$vodId]) : 0;
            $v['is_fav'] = $favUid > 0 ? 1 : 0;
            $v['fav_uid'] = $favUid;
            // 清理 HTML 标签
            $v['vod_content'] = strip_tags($v['vod_content']);
            if (mb_strlen($v['vod_content']) > 100) {
                $v['vod_content'] = mb_substr($v['vod_content'], 0, 100) . '...';
            }
        }
        unset($v);
        mac_append_type_is_vip_exclusive_for_rows($list);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total' => count($list),
                'rows'  => $list,
            ],
        ]);
    }

    /**
     * 获取热门推荐影片
     * 对应首页热门推荐 Tab 区块，按月度点击量排序
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num     - 可选，数量，默认6
     *   type_id - 可选，分类ID，不传则查全站
     *   start   - 可选，偏移量，默认0
     *   level   - 可选，推荐等级筛选，多个用逗号分隔，如 "1,2,3,4,5,6,7,8,9"
     *   by      - 可选，排序字段，默认 hits_month，可选: hits,hits_day,hits_week,hits_month,score,time
     */
    public function get_hot(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 6;
        $start = isset($param['start']) ? (int)$param['start'] : 0;
        $typeId = isset($param['type_id']) ? (int)$param['type_id'] : 0;
        $level = isset($param['level']) ? trim($param['level']) : '';
        $by = isset($param['by']) ? trim($param['by']) : 'hits_month';

        // 验证排序字段
        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'score', 'time'];
        if (!in_array($by, $allowBy)) {
            $by = 'hits_month';
        }

        $where = [];
        $where['vod_status'] = ['eq', 1];
        if ($typeId > 0) {
            // 同时匹配 type_id 和 type_id_1（父分类）
            $where['type_id|type_id_1'] = ['eq', $typeId];
        }
        if (!empty($level)) {
            $where['vod_level'] = ['in', $level];
        }

        $list = Db::table('mac_vod')
            ->field('vod_id,vod_name,vod_sub,vod_pic,vod_actor,vod_director,vod_score,vod_remarks,vod_year,vod_area,vod_class,vod_blurb,vod_time,vod_hits_month,type_id,type_id_1')
            ->where($where)
            ->order('vod_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['vod_pic'] = mac_url_img($v['vod_pic']);
            $v['vod_time_text'] = date('m-d', $v['vod_time']);
            $v['vod_link'] = mac_url_vod_detail($v);
        }
        unset($v);
        mac_append_type_is_vip_exclusive_for_rows($list);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total' => count($list),
                'rows'  => $list,
            ],
        ]);
    }

    /**
     * 按分类获取最新影片
     * 对应首页各分类区块的最新影片列表
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   type_id - 必须，分类ID
     *   num     - 可选，数量，默认24
     *
     * info.today_new_count：当前分类（含子类）下，vod_time_add 或 vod_time 任一落在服务器当天自然日内的条数（去重按行，每条视频计 1）
     */
    public function get_latest_by_type(Request $request)
    {
        $param = $request->param();
        if (empty($param['type_id'])) {
            return json(['code' => 1001, 'msg' => '参数错误: type_id 必须']);
        }
        $typeId = (int)$param['type_id'];
        $num = isset($param['num']) ? (int)$param['num'] : 24;
        $num = max(1, min($num, 60));
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;

        $typeIds = mac_vod_type_filter_ids_for_list($typeId);
        if (empty($typeIds)) {
            return json([
                'code' => 1,
                'msg'  => '获取成功',
                'info' => ['total' => 0, 'today_new_count' => 0, 'rows' => []],
            ]);
        }

        $cacheFlag = $GLOBALS['config']['app']['cache_flag'] ?? 'maccms';
        $cacheTime = (int)($GLOBALS['config']['app']['cache_time'] ?? 0);
        $idsForKey = $typeIds;
        sort($idsForKey, SORT_NUMERIC);
        $cacheKey = $cacheFlag . '_api_vod_latest_by_type_' . md5($typeId . '_' . $num . '_' . $start . '_' . implode(',', $idsForKey) . '_' . date('Y-m-d'));
        if (!empty($GLOBALS['config']['app']['cache_core']) && $cacheTime > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['code'])) {
                return json($cached);
            }
        }

        // 仅列表所需字段；去掉 vod_director、vod_trysee 等，减轻行缓冲与 IO
        $fields = 'vod_id,vod_name,vod_sub,vod_pic,vod_actor,vod_score,vod_remarks,vod_year,vod_area,vod_class,vod_blurb,vod_time,vod_isend,vod_points_play,type_id,type_id_1';

        $list = Db::table('mac_vod')
            ->field($fields)
            ->where('vod_status', 1)
            ->where('type_id', 'in', $typeIds)
            ->order('vod_time', 'desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['vod_pic'] = mac_url_img($v['vod_pic']);
            $v['vod_time_text'] = date('m-d', $v['vod_time']);
            $v['vod_link'] = mac_url_vod_detail($v);
        }
        unset($v);
        mac_append_type_is_vip_exclusive_for_rows($list);

        $dayStart = (int)strtotime('today');
        $dayEnd = (int)strtotime('tomorrow');
        $todayNewCount = (int)Db::table('mac_vod')
            ->where('vod_status', 1)
            ->where('type_id', 'in', $typeIds)
            ->where(function ($query) use ($dayStart, $dayEnd) {
                $query->where(function ($q) use ($dayStart, $dayEnd) {
                    $q->where('vod_time_add', '>=', $dayStart)->where('vod_time_add', '<', $dayEnd);
                })->whereOr(function ($q) use ($dayStart, $dayEnd) {
                    $q->where('vod_time', '>=', $dayStart)->where('vod_time', '<', $dayEnd);
                });
            })
            ->count();

        $payload = [
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'             => count($list),
                'today_new_count'   => $todayNewCount,
                'rows'              => $list,
            ],
        ];
        if (!empty($GLOBALS['config']['app']['cache_core']) && $cacheTime > 0) {
            Cache::set($cacheKey, $payload, $cacheTime);
        }

        return json($payload);
    }

    /**
     * 获取排行榜数据
     * 对应首页热播排行榜区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   type_id - 可选，分类ID，不传则查全站
     *   num     - 可选，数量，默认10
     *   by      - 可选，排序字段，默认 hits_month
     */
    public function get_rank(Request $request)
    {
        $param = $request->param();
        $typeId = isset($param['type_id']) ? (int)$param['type_id'] : 0;
        $num = isset($param['num']) ? (int)$param['num'] : 10;
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;
        $by = isset($param['by']) ? trim($param['by']) : 'hits_month';

        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'score', 'time'];
        if (!in_array($by, $allowBy)) {
            $by = 'hits_month';
        }

        $where = [];
        $where['vod_status'] = ['eq', 1];
        if ($typeId > 0) {
            $where['type_id|type_id_1'] = ['eq', $typeId];
        }

        $list = Db::table('mac_vod')
            ->field('vod_id,vod_name,vod_pic,vod_score,vod_remarks,vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_year,vod_area,vod_class,vod_isend,vod_points_play,type_id,type_id_1')
            ->where($where)
            ->order('vod_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as $k => &$v) {
            $v['vod_pic'] = mac_url_img($v['vod_pic']);
            $v['rank'] = $k + 1;
            $v['vod_link'] = mac_url_vod_detail($v);
        }
        unset($v);
        mac_append_type_is_vip_exclusive_for_rows($list);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total' => count($list),
                'rows'  => $list,
            ],
        ]);
    }

    /**
     * 更新/获取点击数
     * api.php/vod/update_hits?id=1&type=update
     * type: update=更新并返回; 默认=只获取
     */
    public function update_hits(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['vod_id' => $id];
        $field = 'vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_time_hits';
        $res = model('Vod')->infoData($where, $field);
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        if ($param['type'] == 'update') {
            $update = [
                'vod_hits'       => $info['vod_hits'],
                'vod_hits_day'   => $info['vod_hits_day'],
                'vod_hits_week'  => $info['vod_hits_week'],
                'vod_hits_month' => $info['vod_hits_month'],
            ];
            $new = getdate();
            $old = getdate($info['vod_time_hits']);
            if ($new['year'] == $old['year'] && $new['mon'] == $old['mon']) {
                $update['vod_hits_month']++;
            } else {
                $update['vod_hits_month'] = 1;
            }
            $ws = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"] * 86400);
            $we = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6-$new["wday"])*86400);
            if ($info['vod_time_hits'] >= $ws && $info['vod_time_hits'] <= $we) {
                $update['vod_hits_week']++;
            } else {
                $update['vod_hits_week'] = 1;
            }
            if ($new['year']==$old['year'] && $new['mon']==$old['mon'] && $new['mday']==$old['mday']) {
                $update['vod_hits_day']++;
            } else {
                $update['vod_hits_day'] = 1;
            }
            $update['vod_hits'] += 1;
            $update['vod_time_hits'] = time();
            model('Vod')->where($where)->update($update);
            return json(['code' => 1, 'msg' => 'ok', 'data' => [
                'hits' => $update['vod_hits'], 'hits_day' => $update['vod_hits_day'],
                'hits_week' => $update['vod_hits_week'], 'hits_month' => $update['vod_hits_month'],
            ]]);
        }
        return json(['code' => 1, 'msg' => 'ok', 'data' => [
            'hits' => $info['vod_hits'], 'hits_day' => $info['vod_hits_day'],
            'hits_week' => $info['vod_hits_week'], 'hits_month' => $info['vod_hits_month'],
        ]]);
    }

    /**
     * 影片顶/踩
     * api.php/vod/digg?id=1&type=up
     * type: up=顶; down=踩; 仅查询不传type
     */
    public function digg(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['vod_id' => $id];
        $model = model('Vod');
        $type = trim($param['type'] ?? '');
        if ($type) {
            $cookie = 'vod-digg-' . $id;
            if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
            if ($type == 'up') { $model->where($where)->setInc('vod_up'); cookie($cookie, 't', 30); }
            elseif ($type == 'down') { $model->where($where)->setInc('vod_down'); cookie($cookie, 't', 30); }
        }
        $res = $model->infoData($where, 'vod_up,vod_down');
        if ($res['code'] > 1) return json($res);
        return json(['code' => 1, 'msg' => 'ok', 'data' => ['up' => $res['info']['vod_up'] ?? 0, 'down' => $res['info']['vod_down'] ?? 0]]);
    }

    /**
     * 影片评分
     * api.php/vod/update_score?id=1&score=8
     * score: 1-10 评分值；不传只获取当前评分
     */
    public function update_score(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['vod_id' => $id];
        $res = model('Vod')->infoData($where, 'vod_score,vod_score_num,vod_score_all');
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        $score = intval($param['score'] ?? 0);
        if ($score > 0) {
            $cookie = 'vod-score-' . $id;
            if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
            $num = intval($info['vod_score_num']) + 1;
            $all = intval($info['vod_score_all']) + $score;
            $avg = number_format($all / $num, 1, '.', '');
            model('Vod')->where($where)->update(['vod_score_num' => $num, 'vod_score_all' => $all, 'vod_score' => $avg]);
            cookie($cookie, 't', 30);
            return json(['code' => 1, 'msg' => lang('score_ok'), 'data' => ['score' => $avg, 'score_num' => $num, 'score_all' => $all]]);
        }
        return json(['code' => 1, 'msg' => 'ok', 'data' => ['score' => $info['vod_score'] ?? 0, 'score_num' => $info['vod_score_num'] ?? 0, 'score_all' => $info['vod_score_all'] ?? 0]]);
    }

    /**
     * 搜索建议/自动完成
     * api.php/vod/suggest?wd=战狼&limit=10
     */
    public function suggest(Request $request)
    {
        if ($GLOBALS['config']['app']['search'] != '1') return json(['code' => 999, 'msg' => lang('suggest_close')]);
        $param = $request->param();
        $wd = trim($param['wd'] ?? '');
        if (empty($wd)) return json(['code' => 1001, 'msg' => '参数错误']);
        $limit = max(1, min(20, intval($param['limit'] ?? 10)));
        $where = ['vod_name|vod_en' => ['like', '%' . $wd . '%']];
        // 需 type / type_1 / vod_time 等以正确生成伪静态详情链接；返回时再精简字段
        $field = 'vod_id,vod_name,vod_en,vod_pic,vod_time,type_id,type_id_1';
        $res = model('Vod')->listData($where, 'vod_id desc', 1, $limit, 0, $field, 1, 1);
        if ($res['code'] == 1 && !empty($res['list'])) {
            $out = [];
            foreach ($res['list'] as $v) {
                $out[] = [
                    'id'      => (int)($v['vod_id'] ?? 0),
                    'name'    => (string)($v['vod_name'] ?? ''),
                    'en'      => (string)($v['vod_en'] ?? ''),
                    'pic'     => mac_url_img($v['vod_pic'] ?? ''),
                    'vod_link'=> mac_url_vod_detail($v),
                ];
            }
            $res['list'] = $out;
        }
        $res['url'] = mac_url_search(['wd' => urlencode($wd)], 'vod');
        return json($res);
    }

    /**
     * 验证播放/下载密码
     * api.php/vod/verify_pwd?id=1&pwd=123&type=4
     * type: 1=访问密码 4=播放密码 5=下载密码
     */
    public function verify_pwd(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        $type = intval($param['type'] ?? 0);
        $pwd = trim($param['pwd'] ?? '');
        if ($id < 1 || empty($pwd) || !in_array($type, [1, 4, 5])) return json(['code' => 1001, 'msg' => lang('param_err')]);
        $key = '1-' . $type . '-' . $id;
        if (session($key) == '1') return json(['code' => 1002, 'msg' => lang('index/pwd_repeat')]);
        if (mac_get_time_span("last_pwd") < 5) return json(['code' => 1003, 'msg' => lang('index/pwd_frequently')]);
        $res = model('Vod')->infoData(['vod_id' => ['eq', $id]]);
        if ($res['code'] > 1) return json(['code' => 1011, 'msg' => $res['msg']]);
        $pwdMap = [1 => 'vod_pwd', 4 => 'vod_pwd_play', 5 => 'vod_pwd_down'];
        if ($res['info'][$pwdMap[$type]] != $pwd) return json(['code' => 1012, 'msg' => lang('pass_err')]);
        session($key, '1');
        return json(['code' => 1, 'msg' => 'ok']);
    }

    /**
     * 获取播放页信息（含源列表、权限检查）
     * api.php/vod/get_play_info?id=1&sid=1&nid=1
     */
    public function get_play_info(Request $request)
    {
        $param = $request->param();
        $id  = intval($param['id'] ?? 0);
        $sid = intval($param['sid'] ?? 1);
        $nid = intval($param['nid'] ?? 1);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误: id 必须']);
        $where = ['vod_id' => $id, 'vod_status' => ['eq', 1]];
        $res = model('Vod')->infoData($where);
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        // 解析播放源
        $playList = mac_play_list(
            $info['vod_play_from'] ?? '', $info['vod_play_url'] ?? '',
            $info['vod_play_server'] ?? '', $info['vod_play_note'] ?? '', 'play'
        );
        // 取当前集播放地址
        $currentPlay = $playList[$sid]['urls'][$nid] ?? null;
        return json(['code' => 1, 'msg' => 'ok', 'info' => [
            'vod_id'    => intval($info['vod_id']),
            'vod_name'  => $info['vod_name'] ?? '',
            'vod_pic'   => mac_url_img($info['vod_pic'] ?? ''),
            'vod_remarks' => $info['vod_remarks'] ?? '',
            'vod_score' => $info['vod_score'] ?? '',
            'vod_copyright' => intval($info['vod_copyright'] ?? 0),
            'vod_points_play' => intval($info['vod_points_play'] ?? 0),
            'vod_pwd_play'  => !empty($info['vod_pwd_play']) ? 1 : 0,
            'type_id'   => intval($info['type_id'] ?? 0),
            'play_list' => $playList,
            'current'   => $currentPlay,
            'sid'       => $sid,
            'nid'       => $nid,
            'play_url'  => mac_url_vod_play($info, ['sid' => $sid, 'nid' => $nid]),
        ]]);
    }

    /**
     * 获取下载页信息
     * api.php/vod/get_down_info?id=1&sid=1&nid=1
     */
    public function get_down_info(Request $request)
    {
        $param = $request->param();
        $id  = intval($param['id'] ?? 0);
        $sid = intval($param['sid'] ?? 1);
        $nid = intval($param['nid'] ?? 1);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误: id 必须']);
        $where = ['vod_id' => $id, 'vod_status' => ['eq', 1]];
        $res = model('Vod')->infoData($where);
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        $downList = mac_play_list(
            $info['vod_down_from'] ?? '', $info['vod_down_url'] ?? '',
            $info['vod_down_server'] ?? '', $info['vod_down_note'] ?? '', 'down'
        );
        $currentDown = $downList[$sid]['urls'][$nid] ?? null;
        return json(['code' => 1, 'msg' => 'ok', 'info' => [
            'vod_id'    => intval($info['vod_id']),
            'vod_name'  => $info['vod_name'] ?? '',
            'vod_pic'   => mac_url_img($info['vod_pic'] ?? ''),
            'vod_points_down' => intval($info['vod_points_down'] ?? 0),
            'vod_pwd_down' => !empty($info['vod_pwd_down']) ? 1 : 0,
            'type_id'   => intval($info['type_id'] ?? 0),
            'down_list' => $downList,
            'current'   => $currentDown,
            'sid'       => $sid,
            'nid'       => $nid,
        ]]);
    }
}
