<?php

namespace app\api\controller;

use think\Controller;
use think\Cache;
use think\Db;
use think\Request;
use think\Validate;

class Topic extends Base
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
     *  获取列表
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
        // 查询条件组装
        $where = [];
        $where['topic_status'] = ['eq', 1];

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['topic_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['topic_time'] = ['<', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['topic_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Topic')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "topic_time DESC";
            if (!empty($param['orderby'])) {
                $order = 'topic_' . $param['orderby'] . " DESC";
            }
            $field = 'topic_id,topic_name,topic_en,topic_sub,topic_pic,topic_pic_slide,topic_blurb,topic_rel_vod,topic_content,topic_time,topic_hits,topic_up,topic_down';
            $list = model('Topic')->getListByCond($offset, $limit, $where, $order, $field, []);
            foreach ($list as &$v) {
                $v['topic_pic'] = mac_url_img($v['topic_pic'] ?? '');
                $v['topic_link'] = mac_url_topic_detail($v);
                $rel = isset($v['topic_rel_vod']) ? (string) $v['topic_rel_vod'] : '';
                $v['topic_rel_count'] = $rel === '' ? 0 : count(array_filter(explode(',', $rel)));
            }
            unset($v);
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
     *  获取列表与推荐信息视频文章
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_detail(Request $request)
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

        $result = Db::table('mac_topic')->where(['topic_id' => $param['topic_id']])->find();

        if ($result)
        {
            // 处理图片 URL
            $result['topic_pic'] = mac_url_img($result['topic_pic'] ?? '');

            $topic_rel_vod = [];
            $topic_rel_art = [];

            if (!empty($result['topic_rel_vod']))
            {
                $topic_rel_vod_arr = explode(',',$result['topic_rel_vod']);
                foreach ($topic_rel_vod_arr as $index => $item) {
                    $vod = Db::table('mac_vod')->where(['vod_id' => $item])->field('vod_id,vod_name,vod_en,vod_pic,vod_actor,vod_director,vod_blurb,vod_remarks,vod_score,vod_year,vod_area,vod_class,type_id,type_id_1')->find();
                    if ($vod) {
                        $vod['vod_pic'] = mac_url_img($vod['vod_pic']);
                        $vod['vod_link'] = mac_url_vod_detail($vod);
                        array_push($topic_rel_vod,$vod);
                    }
                }

                $result['topic_rel_vod'] = $topic_rel_vod;
            }

            if (!empty($result['topic_rel_art']))
            {
                $topic_rel_art_arr = explode(',',$result['topic_rel_art']);
                foreach ($topic_rel_art_arr as $index => $item) {
                    $art = Db::table('mac_art')->where(['art_id' => $item])->field('art_id,type_id,art_name,art_sub,art_en,art_pic,art_blurb,art_remarks,art_time')->find();
                    if ($art) {
                        $art['art_pic'] = mac_url_img($art['art_pic'] ?? '');
                        $art['art_link'] = mac_url_art_detail($art);
                        array_push($topic_rel_art,$art);
                    }
                }

                $result['topic_rel_art'] = $topic_rel_art;
            }

            $uid = (int) ($GLOBALS['user']['user_id'] ?? 0);
            $tid = (int) ($result['topic_id'] ?? 0);
            $fav = mac_user_fav_state($uid, 3, $tid);
            $result['is_fav'] = $fav['is_fav'];
            $result['fav_ulog_id'] = $fav['fav_ulog_id'];
            $result['user_has_up'] = mac_user_has_digg(3, $tid);
        }

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $result,
        ]);
    }

    /**
     * 获取推荐/精选专题
     * 对应首页精选专题区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   ids - 可选，专题 ID 列表（逗号分隔），与 num 个格子顺序一一对应；0 或无效表示该格占位
     *   num - 可选，数量，默认5；传 ids 时表示期望返回行数（与首页七巧板一致）
     *   by  - 可选，排序字段，默认 time，可选: time,hits（仅未传 ids 时生效）
     */
    public function get_recommend(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 5;
        $num = max(1, min(20, $num));
        $idsRaw = isset($param['ids']) ? trim((string) $param['ids']) : '';

        if ($idsRaw !== '') {
            $parts = explode(',', $idsRaw);
            $orderedIds = [];
            foreach ($parts as $p) {
                if (count($orderedIds) >= $num) {
                    break;
                }
                $orderedIds[] = max(0, (int) trim($p));
            }
            while (count($orderedIds) < $num) {
                $orderedIds[] = 0;
            }
            $orderedIds = array_slice($orderedIds, 0, $num);

            $wantIds = array_values(array_unique(array_filter($orderedIds, function ($id) {
                return $id > 0;
            })));

            $byId = [];
            if (!empty($wantIds)) {
                $dbList = Db::table('mac_topic')
                    ->field('topic_id,topic_name,topic_en,topic_sub,topic_pic,topic_pic_slide,topic_blurb,topic_rel_vod,topic_time,topic_hits,topic_status')
                    ->where('topic_id', 'in', $wantIds)
                    ->select();
                foreach ($dbList as $row) {
                    $byId[(int) $row['topic_id']] = $row;
                }
            }

            $placeholder = function () {
                return [
                    'topic_empty'     => 1,
                    'topic_id'        => 0,
                    'topic_name'      => '',
                    'topic_en'        => '',
                    'topic_sub'       => '',
                    'topic_pic'       => '',
                    'topic_pic_slide' => '',
                    'topic_blurb'     => '',
                    'topic_rel_vod'   => '',
                    'topic_time'      => 0,
                    'topic_hits'      => 0,
                    'topic_link'      => '',
                ];
            };

            $list = [];
            foreach ($orderedIds as $tid) {
                if ($tid <= 0) {
                    $list[] = $placeholder();
                    continue;
                }
                $row = isset($byId[$tid]) ? $byId[$tid] : null;
                if (!$row || (int) $row['topic_status'] !== 1) {
                    $list[] = $placeholder();
                    continue;
                }
                unset($row['topic_status']);
                $row['topic_pic'] = mac_url_img($row['topic_pic']);
                $row['topic_pic_slide'] = mac_url_img($row['topic_pic_slide']);
                $row['topic_link'] = mac_url_topic_detail($row);
                $row['topic_empty'] = 0;
                $list[] = $row;
            }

            return json([
                'code' => 1,
                'msg'  => '获取成功',
                'info' => [
                    'total' => count($list),
                    'rows'  => $list,
                ],
            ]);
        }

        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;
        $by = isset($param['by']) ? trim($param['by']) : 'time';

        $allowBy = ['time', 'hits'];
        if (!in_array($by, $allowBy)) {
            $by = 'time';
        }

        $where = [];
        $where['topic_status'] = ['eq', 1];

        $list = Db::table('mac_topic')
            ->field('topic_id,topic_name,topic_en,topic_sub,topic_pic,topic_pic_slide,topic_blurb,topic_rel_vod,topic_time,topic_hits')
            ->where($where)
            ->order('topic_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['topic_pic'] = mac_url_img($v['topic_pic']);
            $v['topic_pic_slide'] = mac_url_img($v['topic_pic_slide']);
            $v['topic_link'] = mac_url_topic_detail($v);
            $v['topic_empty'] = 0;
        }
        unset($v);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total' => count($list),
                'rows'  => $list,
            ],
        ]);
    }
}
