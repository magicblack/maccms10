<?php
namespace app\api\controller;

use think\Request;
use think\Db;

class Manga extends Base
{
    use PublicApi;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();
    }

    public function get_list(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? 20 : intval($param['limit']);

        $where = [];
        $where['manga_status'] = ['eq', 1];

        if (!empty($param['t'])) {
            $tid = (int)$param['t'];
            if ($tid > 0) {
                $where['type_id|type_id_1'] = ['eq', $tid];
            }
        }
        if (!empty($param['ids'])) {
            $where['manga_id'] = ['in', $param['ids']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where['manga_name'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'manga_time desc';
        if (!empty($param['order'])) {
            $order = $param['order'];
        }

        $data = model('Manga')->listData($where, $order, $param['page'], $param['limit']);
        if (!empty($data['list']) && is_array($data['list'])) {
            foreach ($data['list'] as &$v) {
                if (!empty($v['manga_pic'])) {
                    $v['manga_pic'] = mac_url_img($v['manga_pic']);
                }
                $v['manga_link'] = mac_url_manga_detail($v);
            }
            unset($v);
        }
        return json($data);
    }

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
        $where = [];
        $where['manga_status'] = ['eq', 1];

        if (!empty($param['id'])) {
            $where['manga_id'] = ['eq', $param['id']];
        }

        $data = model('Manga')->infoData($where);
        if ($data['code'] == 1 && !empty($data['info'])) {
            $info = &$data['info'];
            // 处理图片 URL
            $info['manga_pic'] = mac_url_img($info['manga_pic'] ?? '');
            $info['manga_pic_thumb'] = mac_url_img($info['manga_pic_thumb'] ?? '');
            $info['manga_pic_slide'] = mac_url_img($info['manga_pic_slide'] ?? '');
            $info['manga_link'] = mac_url_manga_detail($info);

            // 与前台模板一致：保留 model->infoData 生成的 manga_page_list（含 sid / urls / nid）
            if (!empty($info['manga_page_list']) && is_array($info['manga_page_list'])) {
                foreach ($info['manga_page_list'] as $sid => &$grp) {
                    if (empty($grp['urls']) || !is_array($grp['urls'])) {
                        continue;
                    }
                    foreach ($grp['urls'] as $nid => &$ep) {
                        if (is_array($ep)) {
                            $ep['play_link'] = mac_url_manga_play($info, ['sid' => (int)$sid, 'nid' => (int)$nid]);
                        }
                    }
                    unset($ep);
                }
                unset($grp);
            }

            unset($info['manga_chapter_url'], $info['manga_chapter_from']);
            unset($info['manga_play_server'], $info['manga_play_note']);

            $uid = (int) ($GLOBALS['user']['user_id'] ?? 0);
            $mid = (int) ($info['manga_id'] ?? 0);
            $fav = mac_user_fav_state($uid, 12, $mid);
            $info['is_fav'] = $fav['is_fav'];
            $info['fav_ulog_id'] = $fav['fav_ulog_id'];

            unset($data['info']);
            $data['info'] = $info;
        }
        return json($data);
    }

    /**
     * 单话阅读数据（供 uni-app / SPA 原生渲染，非 web-view）
     * GET api.php/manga/get_chapter 参数：id manga_id，sid nid 与前台 play 一致
     */
    public function get_chapter(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene('get_chapter')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $id  = (int) $param['id'];
        $sid = isset($param['sid']) ? (int) $param['sid'] : 1;
        $nid = isset($param['nid']) ? (int) $param['nid'] : 1;
        if ($sid < 1) {
            $sid = 1;
        }
        if ($nid < 1) {
            $nid = 1;
        }

        $where = [];
        $where['manga_status'] = ['eq', 1];
        $where['manga_id'] = ['eq', $id];

        $data = model('Manga')->infoData($where);
        if ($data['code'] != 1 || empty($data['info'])) {
            return json(['code' => 1002, 'msg' => $data['msg'] ?? '数据不存在']);
        }
        $info = $data['info'];

        $popParam = ['id' => $id, 'sid' => $sid, 'nid' => $nid];
        $popedom  = $this->check_user_popedom($info['type_id'], 3, $popParam, 'manga_play', $info);

        $plist = $info['manga_page_list'] ?? [];
        $grp   = $plist[$sid] ?? $plist[(string) $sid] ?? null;
        if (empty($grp) || empty($grp['urls']) || !is_array($grp['urls'])) {
            return json(['code' => 1002, 'msg' => '章节数据不存在']);
        }
        $ep = $grp['urls'][$nid] ?? $grp['urls'][(string) $nid] ?? null;
        if (empty($ep) || !is_array($ep)) {
            return json(['code' => 1002, 'msg' => '该话不存在']);
        }

        $epTotal = isset($grp['url_count']) ? (int) $grp['url_count'] : count($grp['urls']);
        $name    = !empty($ep['name']) ? (string) $ep['name'] : ('第' . $nid . '话');

        $images = [];
        if (!empty($ep['url'])) {
            foreach (explode(',', (string) $ep['url']) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $images[] = mac_url_img($piece);
                }
            }
        }

        $canRead = ($popedom['code'] == 1) ? 1 : 0;
        $out     = [
            'can_read'       => $canRead,
            'deny_code'      => (int) ($popedom['code'] ?? 0),
            'deny_msg'       => $canRead ? '' : (string) ($popedom['msg'] ?? ''),
            'points_hint'    => isset($popedom['points']) ? (int) $popedom['points'] : 0,
            'manga_id'       => $id,
            'manga_name'     => (string) ($info['manga_name'] ?? ''),
            'sid'            => $sid,
            'nid'            => $nid,
            'episode_name'   => $name,
            'episode_total'  => $epTotal,
            'has_prev'       => $nid > 1,
            'has_next'       => $epTotal > 0 && $nid < $epTotal,
            'images'         => $canRead ? $images : [],
        ];

        return json(['code' => 1, 'msg' => 'ok', 'info' => $out]);
    }

    /**
     * 获取热门漫画
     * 对应首页热门漫画区块，按月度点击量排序
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num   - 可选，数量，默认6
     *   start - 可选，偏移量，默认0
     *   by    - 可选，排序字段，默认 hits_month，可选: hits,hits_day,hits_week,hits_month,time
     */
    public function get_hot(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 6;
        $start = isset($param['start']) ? (int)$param['start'] : 0;
        $by = isset($param['by']) ? trim($param['by']) : 'hits_month';

        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'time'];
        if (!in_array($by, $allowBy)) {
            $by = 'hits_month';
        }

        $where = [];
        $where['manga_status'] = ['eq', 1];

        $list = Db::table('mac_manga')
            ->field('manga_id,manga_name,manga_pic,manga_blurb,manga_remarks,manga_score,manga_time,manga_hits_month,type_id')
            ->where($where)
            ->order('manga_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['manga_pic'] = mac_url_img($v['manga_pic']);
            $v['manga_time_text'] = date('m-d', $v['manga_time']);
            $v['manga_link'] = mac_url_manga_detail($v);
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
     * 获取最新漫画
     * 对应首页最新漫画区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   num - 可选，数量，默认24
     */
    public function get_latest(Request $request)
    {
        $param = $request->param();
        $num = isset($param['num']) ? (int)$param['num'] : 24;
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;

        $where = [];
        $where['manga_status'] = ['eq', 1];

        $list = Db::table('mac_manga')
            ->field('manga_id,manga_name,manga_pic,manga_blurb,manga_remarks,manga_score,manga_points,manga_time,type_id')
            ->where($where)
            ->order('manga_time desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['manga_pic'] = mac_url_img($v['manga_pic']);
            $v['manga_time_text'] = date('m-d', $v['manga_time']);
            $v['manga_link'] = mac_url_manga_detail($v);
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
