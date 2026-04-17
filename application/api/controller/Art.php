<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Art extends Base
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
        // 查询条件组装
        $where = [];

        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;

        if (isset($param['type_id']) && (int)$param['type_id'] > 0) {
            $where['type_id|type_id_1'] = ['eq', (int)$param['type_id']];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['art_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['art_time'] = ['<=', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['art_time'] = ['>=', (int)$param['time_start']];
        }

        if (isset($param['letter'])) {
            $where['art_letter'] = $param['letter'];
        }

        if (isset($param['status'])) {
            $where['art_status'] = (int)$param['status'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['art_name'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        if (isset($param['sub']) && strlen($param['sub']) > 0) {
            $where['art_sub'] = ['like', '%' . $this->format_sql_string($param['sub']) . '%'];
        }

        if (isset($param['blurb']) && strlen($param['blurb']) > 0) {
            $where['art_blurb'] = ['like', '%' . $this->format_sql_string($param['blurb']) . '%'];
        }

        if (isset($param['title']) && strlen($param['title']) > 0) {
            $where['art_title'] = ['like', '%' . $this->format_sql_string($param['title']) . '%'];
        }

        if (isset($param['content']) && strlen($param['content']) > 0) {
            $where['art_content'] = ['like', '%' . $this->format_sql_string($param['content']) . '%'];
        }

        if (isset($param['class']) && strlen($param['class']) > 0) {
            $where['art_class'] = ['like', '%' . $this->format_sql_string($param['class']) . '%'];
        }
        if (isset($param['tag']) && strlen($param['tag']) > 0) {
            $where['art_tag'] = ['like', '%' . $this->format_sql_string($param['tag']) . '%'];
        }
        if (isset($param['level']) && strlen($param['level']) > 0) {
            $where['art_level'] = ['in', $this->format_sql_string($param['level'])];
        }

        // 数据获取
        $total = model('Art')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "art_time DESC";
            $field = 'art_id,art_name,art_sub,art_en,art_pic,art_blurb,art_time,art_time_add,art_hits,art_points,art_points_detail,art_remarks,art_author,type_id';
            if (strlen($param['orderby']) > 0) {
                $order = 'art_' . $param['orderby'] . " DESC";
            }
            $list = model('Art')->getListByCond($offset, $limit, $where, $order, $field, []);
            $type_list = model('Type')->getCache('type_list');
            foreach ($list as &$v) {
                if (!empty($v['type_id']) && isset($type_list[$v['type_id']])) {
                    $v['type'] = $type_list[$v['type_id']];
                    $pid = isset($v['type']['type_pid']) ? (int) $v['type']['type_pid'] : 0;
                    $v['type_1'] = ($pid > 0 && isset($type_list[$pid])) ? $type_list[$pid] : ['type_id' => 0, 'type_en' => ''];
                }
                $v['art_link'] = mac_url_art_detail($v);
                if (!empty($v['art_pic'])) {
                    $v['art_pic'] = mac_url_img($v['art_pic']);
                }
                if (isset($v['art_time']) && is_numeric($v['art_time'])) {
                    $v['art_time'] = date('Y-m-d H:i:s', (int)$v['art_time']);
                }
                $v['art_read_points'] = mac_content_read_points_amount('art', $v);
            }
            unset($v);
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
     * 视频文章详情
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

        $aid = (int)$param['art_id'];
        $data = model('Art')->infoData(['art_id' => ['eq', $aid]], '*', 0);
        if ($data['code'] != 1 || empty($data['info'])) {
            return json(['code' => 1001, 'msg' => $data['msg'] ?? '数据不存在']);
        }
        $info = $data['info'];

        $info['art_pic'] = mac_url_img($info['art_pic'] ?? '');
        $info['art_pic_thumb'] = mac_url_img($info['art_pic_thumb'] ?? '');
        $info['art_pic_slide'] = mac_url_img($info['art_pic_slide'] ?? '');
        $info['art_link'] = mac_url_art_detail($info);

        if (!empty($info['art_page_list']) && is_array($info['art_page_list'])) {
            $slim = [];
            foreach ($info['art_page_list'] as $page => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $p = isset($row['page']) ? (int)$row['page'] : (int)$page;
                $slim[$p] = [
                    'page' => $p,
                    'title' => $row['title'] ?? '',
                    'note' => $row['note'] ?? '',
                ];
            }
            $info['art_page_list'] = $slim;
            $info['art_page_total'] = count($slim);
        } else {
            $info['art_page_list'] = [];
            $info['art_page_total'] = 0;
        }
        unset($info['art_content']);

        $tid = (int)($info['type_id'] ?? 0);
        $info['art_prev'] = null;
        $info['art_next'] = null;
        if ($tid > 0 && $aid > 0) {
            $prev = Db::table('mac_art')->where(['art_status' => 1, 'type_id' => $tid])->where('art_id', '<', $aid)
                ->order('art_id', 'desc')->field('art_id,art_name,art_en')->find();
            $next = Db::table('mac_art')->where(['art_status' => 1, 'type_id' => $tid])->where('art_id', '>', $aid)
                ->order('art_id', 'asc')->field('art_id,art_name,art_en')->find();
            if (!empty($prev)) {
                $prev['art_link'] = mac_url_art_detail($prev);
                $info['art_prev'] = $prev;
            }
            if (!empty($next)) {
                $next['art_link'] = mac_url_art_detail($next);
                $info['art_next'] = $next;
            }
        }

        $uid = (int) ($GLOBALS['user']['user_id'] ?? 0);
        $aid = (int) ($info['art_id'] ?? 0);
        $fav = mac_user_fav_state($uid, 2, $aid);
        $info['is_fav'] = $fav['is_fav'];
        $info['fav_ulog_id'] = $fav['fav_ulog_id'];
        $info['user_has_up'] = mac_user_has_digg(2, $aid);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $info,
        ]);
    }

    /**
     * 单页正文（供 uni-app / SPA 原生渲染小说阅读页）
     * GET api.php/art/get_read_page 参数：art_id，page 可选默认 1
     */
    public function get_read_page(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene('get_read_page')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $artId = (int) $param['art_id'];
        $page  = isset($param['page']) ? (int) $param['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $data = model('Art')->infoData(['art_id' => ['eq', $artId]], '*', 0);
        if ($data['code'] != 1 || empty($data['info'])) {
            return json(['code' => 1002, 'msg' => $data['msg'] ?? '数据不存在']);
        }
        $info = $data['info'];
        if ((int) ($info['art_status'] ?? 0) != 1) {
            return json(['code' => 1002, 'msg' => '数据不存在']);
        }

        $popParam = ['id' => $artId, 'page' => $page];
        $popedom  = $this->check_user_popedom($info['type_id'], 3, $popParam, 'art_read', $info);

        $pageList  = $info['art_page_list'] ?? [];
        $pageTotal = count($pageList);
        if ($pageTotal < 1) {
            return json(['code' => 1002, 'msg' => '暂无正文']);
        }
        if ($page > $pageTotal) {
            $page = $pageTotal;
        }
        $cur = $pageList[$page] ?? $pageList[(string) $page] ?? null;
        if (empty($cur) || !is_array($cur)) {
            return json(['code' => 1002, 'msg' => '该页不存在']);
        }

        $html = '';
        if ($popedom['code'] == 1 && !empty($cur['content'])) {
            $html = mac_url_content_img($cur['content']);
        }

        $canRead = ($popedom['code'] == 1) ? 1 : 0;
        $out     = [
            'can_read'     => $canRead,
            'deny_code'    => (int) ($popedom['code'] ?? 0),
            'deny_msg'     => $canRead ? '' : (string) ($popedom['msg'] ?? ''),
            'points_hint'  => isset($popedom['points']) ? (int) $popedom['points'] : 0,
            'art_id'       => $artId,
            'art_name'     => (string) ($info['art_name'] ?? ''),
            'page'         => $page,
            'page_total'   => $pageTotal,
            'title'        => (string) ($cur['title'] ?? ''),
            'note'         => (string) ($cur['note'] ?? ''),
            'content_html' => $html,
            'has_prev'     => $page > 1,
            'has_next'     => $page < $pageTotal,
        ];

        return json(['code' => 1, 'msg' => 'ok', 'info' => $out]);
    }

    /**
     * 获取热门文章/小说
     * 对应首页热门小说区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num     - 可选，数量，默认6
     *   type_id - 可选，分类ID
     *   start   - 可选，偏移量，默认0
     *   by      - 可选，排序字段，默认 time，可选: hits,hits_day,hits_week,hits_month,time
     */
    /**
     * 文章顶/踩
     */
    public function digg(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['art_id' => $id];
        $model = model('Art');
        $type = trim($param['type'] ?? '');
        if ($type) {
            $cookie = 'art-digg-' . $id;
            if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
            if ($type == 'up') { $model->where($where)->setInc('art_up'); cookie($cookie, 't', 30); }
            elseif ($type == 'down') { $model->where($where)->setInc('art_down'); cookie($cookie, 't', 30); }
        }
        $res = $model->infoData($where, 'art_up,art_down');
        if ($res['code'] > 1) return json($res);
        return json(['code' => 1, 'msg' => 'ok', 'data' => ['up' => $res['info']['art_up'] ?? 0, 'down' => $res['info']['art_down'] ?? 0]]);
    }

    /**
     * 更新/获取文章点击数
     */
    public function update_hits(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['art_id' => $id];
        $field = 'art_hits,art_hits_day,art_hits_week,art_hits_month,art_time_hits';
        $res = model('Art')->infoData($where, $field);
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        if (($param['type'] ?? '') == 'update') {
            $update = ['art_hits'=>$info['art_hits'],'art_hits_day'=>$info['art_hits_day'],'art_hits_week'=>$info['art_hits_week'],'art_hits_month'=>$info['art_hits_month']];
            $new = getdate(); $old = getdate($info['art_time_hits']);
            $update['art_hits_month'] = ($new['year']==$old['year'] && $new['mon']==$old['mon']) ? $update['art_hits_month']+1 : 1;
            $ws = mktime(0,0,0,$new["mon"],$new["mday"],$new["year"]) - ($new["wday"]*86400);
            $we = mktime(23,59,59,$new["mon"],$new["mday"],$new["year"]) + ((6-$new["wday"])*86400);
            $update['art_hits_week'] = ($info['art_time_hits']>=$ws && $info['art_time_hits']<=$we) ? $update['art_hits_week']+1 : 1;
            $update['art_hits_day'] = ($new['year']==$old['year'] && $new['mon']==$old['mon'] && $new['mday']==$old['mday']) ? $update['art_hits_day']+1 : 1;
            $update['art_hits']++; $update['art_time_hits'] = time();
            model('Art')->where($where)->update($update);
            return json(['code'=>1,'msg'=>'ok','data'=>['hits'=>$update['art_hits'],'hits_day'=>$update['art_hits_day'],'hits_week'=>$update['art_hits_week'],'hits_month'=>$update['art_hits_month']]]);
        }
        return json(['code'=>1,'msg'=>'ok','data'=>['hits'=>$info['art_hits'],'hits_day'=>$info['art_hits_day'],'hits_week'=>$info['art_hits_week'],'hits_month'=>$info['art_hits_month']]]);
    }

    /**
     * 文章评分
     */
    public function update_score(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $where = ['art_id' => $id];
        $res = model('Art')->infoData($where, 'art_score,art_score_num,art_score_all');
        if ($res['code'] > 1) return json($res);
        $info = $res['info'];
        $score = intval($param['score'] ?? 0);
        if ($score > 0) {
            $cookie = 'art-score-' . $id;
            if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
            $num = intval($info['art_score_num']) + 1;
            $all = intval($info['art_score_all']) + $score;
            $avg = number_format($all / $num, 1, '.', '');
            model('Art')->where($where)->update(['art_score_num'=>$num,'art_score_all'=>$all,'art_score'=>$avg]);
            cookie($cookie, 't', 30);
            return json(['code'=>1,'msg'=>lang('score_ok'),'data'=>['score'=>$avg,'score_num'=>$num,'score_all'=>$all]]);
        }
        return json(['code'=>1,'msg'=>'ok','data'=>['score'=>$info['art_score']??0,'score_num'=>$info['art_score_num']??0,'score_all'=>$info['art_score_all']??0]]);
    }

    public function get_hot(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 6;
        $start = isset($param['start']) ? (int)$param['start'] : 0;
        $typeId = isset($param['type_id']) ? (int)$param['type_id'] : 0;
        $by = isset($param['by']) ? trim($param['by']) : 'time';

        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'time'];
        if (!in_array($by, $allowBy)) {
            $by = 'time';
        }

        $where = [];
        $where['art_status'] = ['eq', 1];
        if ($typeId > 0) {
            $where['type_id|type_id_1'] = ['eq', $typeId];
        }

        $list = Db::table('mac_art')
            ->field('art_id,art_name,art_sub,art_pic,art_author,art_blurb,art_time,art_hits,art_hits_month,art_points,art_remarks,type_id')
            ->where($where)
            ->order('art_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['art_pic'] = mac_url_img($v['art_pic']);
            $v['art_time_text'] = date('m-d', $v['art_time']);
            $v['art_link'] = mac_url_art_detail($v);
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

    /**
     * 获取最新文章/资讯
     * 对应首页最新小说 + 最新影视资讯区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num     - 可选，数量，默认24
     *   type_id - 可选，分类ID
     */
    public function get_latest(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 24;
        $typeId = isset($param['type_id']) ? (int)$param['type_id'] : 0;
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;

        $where = [];
        $where['art_status'] = ['eq', 1];
        if ($typeId > 0) {
            $where['type_id|type_id_1'] = ['eq', $typeId];
        }

        $list = Db::table('mac_art')
            ->field('art_id,art_name,art_sub,art_pic,art_author,art_blurb,art_remarks,art_points,art_hits,art_time,type_id')
            ->where($where)
            ->order('art_time desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['art_pic'] = mac_url_img($v['art_pic']);
            $v['art_time_text'] = date('m-d', $v['art_time']);
            $v['art_link'] = mac_url_art_detail($v);
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

}
