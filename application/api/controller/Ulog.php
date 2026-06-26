<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * 续播 / 播放进度记忆
 *
 * 进度统一记录在 mac_ulog 表的播放历史记录上：
 * ulog_mid 为 1 表示视频，ulog_type 为 4 表示播放历史。
 * 续播相关字段：ulog_point 已观看秒数，ulog_duration 影片总时长秒，
 * ulog_sid 最后使用的播放源，ulog_nid 集数。
 * 同一影片同一集只记一条，换源时仅更新为最后一次使用的播放源。
 */
class Ulog extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {

    }

    /**
     * 上报播放进度
     * POST api.php/ulog/progress
     * 参数 vod_id, sid, nid, point, duration
     * 前端建议每 10 到 15 秒节流上报一次
     */
    public function progress(Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            // 未登录时前端使用 localStorage 兜底
            return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        }
        $uid = intval($check['info']['user_id']);

        $param    = $request->post();
        $vodId    = intval($param['vod_id'] ?? 0);
        $sid      = intval($param['sid'] ?? 0);
        $nid      = intval($param['nid'] ?? 0);
        $point    = intval($param['point'] ?? 0);
        $duration = intval($param['duration'] ?? 0);

        if ($vodId < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        // 数值容错
        if ($point < 0) {
            $point = 0;
        }
        if ($duration < 0) {
            $duration = 0;
        }
        if ($duration > 0 && $point > $duration) {
            $point = $duration;
        }

        $res = $this->saveProgress($uid, $vodId, $sid, $nid, $point, $duration, false);
        if ($res === false) {
            return json(['code' => 1004, 'msg' => lang('save_err')]);
        }

        return json([
            'code' => 1,
            'msg'  => lang('save_ok'),
            'info' => [
                'vod_id'   => $vodId,
                'sid'      => $sid,
                'nid'      => $nid,
                'point'    => $point,
                'duration' => $duration,
            ],
        ]);
    }

    /**
     * 读取某影片的上次播放进度
     * GET api.php/ulog/get_progress
     * 参数 vod_id 必填，nid 可选用于指定集数
     * 不传 nid 时返回该影片最近一次播放的记录
     */
    public function get_progress(Request $request)
    {
        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            // 未登录时前端使用 localStorage 兜底
            return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        }
        $uid = intval($check['info']['user_id']);

        $param = $request->param();
        $vodId = intval($param['vod_id'] ?? 0);
        if ($vodId < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $where = [
            'user_id'   => $uid,
            'ulog_mid'  => 1,
            'ulog_type' => 4,
            'ulog_rid'  => $vodId,
        ];
        if (isset($param['nid']) && $param['nid'] !== '') {
            $where['ulog_nid'] = intval($param['nid']);
        }

        $row = Db::name('ulog')
            ->field('ulog_id,ulog_rid,ulog_sid,ulog_nid,ulog_point,ulog_duration,ulog_time')
            ->where($where)
            ->order('ulog_time desc')
            ->find();

        if (empty($row)) {
            return json([
                'code' => 1,
                'msg'  => lang('obtain_ok'),
                'info' => null,
            ]);
        }

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => [
                'vod_id'   => intval($row['ulog_rid']),
                'sid'      => intval($row['ulog_sid']),
                'nid'      => intval($row['ulog_nid']),
                'point'    => intval($row['ulog_point']),
                'duration' => intval($row['ulog_duration']),
                'time'     => intval($row['ulog_time']),
            ],
        ]);
    }

    /**
     * 登录后合并本地 localStorage 播放进度
     * POST api.php/ulog/merge
     * 参数 list 为 JSON 字符串或数组，每项包含 vod_id, sid, nid, point, duration
     * 合并规则：本地 point 大于库内 point 时才覆盖，库内不存在则新增
     * 单次最多处理 50 条
     */
    public function merge(Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $check = model('User')->checkLogin();
        if ($check['code'] > 1) {
            return json(['code' => 1401, 'msg' => lang('api/please_login_first')]);
        }
        $uid = intval($check['info']['user_id']);

        $param = $request->post();
        $list  = $param['list'] ?? '';
        if (is_string($list)) {
            $list = json_decode($list, true);
        }
        if (empty($list) || !is_array($list)) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        // 限制单次处理条数
        $list = array_slice($list, 0, 50);

        $success = 0;
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $vodId    = intval($item['vod_id'] ?? 0);
            $sid      = intval($item['sid'] ?? 0);
            $nid      = intval($item['nid'] ?? 0);
            $point    = intval($item['point'] ?? 0);
            $duration = intval($item['duration'] ?? 0);

            if ($vodId < 1) {
                continue;
            }
            if ($point < 0) {
                $point = 0;
            }
            if ($duration < 0) {
                $duration = 0;
            }
            if ($duration > 0 && $point > $duration) {
                $point = $duration;
            }

            // 合并时仅当本地进度更靠后才覆盖
            $res = $this->saveProgress($uid, $vodId, $sid, $nid, $point, $duration, true);
            if ($res !== false) {
                $success++;
            }
        }

        return json([
            'code' => 1,
            'msg'  => lang('save_ok'),
            'info' => [
                'total'   => count($list),
                'success' => $success,
            ],
        ]);
    }

    /**
     * 上报一次线路播放失败（前端播放器 onerror 自动切换线路时调用）
     * POST api.php/ulog/report_fail
     * 参数：
     *   vod_id    必填，影片ID
     *   sid       线路序号(第几个播放源,从0开始)
     *   nid       集数序号(可选,默认0)
     *   play_from 播放器/来源标识(可选,如 dplayer)
     *   vod_name  影片名称(可选,后台列表冗余显示)
     *   switched  是否已成功切换到下一线路(0/1,可选)
     * 无需登录；同一(vod_id,sid,nid)在 30 秒内重复上报会被节流忽略，防止刷量。
     */
    public function report_fail(Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        $param = $request->post();
        $vodId = intval($param['vod_id'] ?? 0);
        $sid   = intval($param['sid'] ?? 0);
        $nid   = intval($param['nid'] ?? 0);

        if ($vodId < 1) {
            return json(['code' => 1001, 'msg' => lang('param_err')]);
        }

        // 防刷：同一线路集数 30 秒内仅记一次
        $ckey = 'pf_' . $vodId . '_' . $sid . '_' . $nid;
        if (cookie($ckey)) {
            return json(['code' => 1, 'msg' => lang('save_ok'), 'info' => ['throttled' => 1]]);
        }
        cookie($ckey, 1, 30);

        $data = [
            'vod_id'    => $vodId,
            'vod_sid'   => $sid,
            'vod_nid'   => $nid,
            'play_from' => (string)($param['play_from'] ?? ''),
            'vod_name'  => (string)($param['vod_name'] ?? ''),
            'switched'  => intval($param['switched'] ?? 0),
            'ip'        => $request->ip(),
        ];

        $res = model('VodPlayFail')->reportFail($data);
        return json($res);
    }

    /**
     * 写入或更新单条播放进度记录
     *
     * @param int  $uid       用户ID
     * @param int  $vodId     影片ID
     * @param int  $sid       播放源
     * @param int  $nid       集数
     * @param int  $point     已观看秒数
     * @param int  $duration  总时长
     * @param bool $onlyAhead 是否仅在本地进度更靠后时才覆盖，合并场景使用
     * @return int|false 影响行数或主键，失败返回 false
     */
    private function saveProgress($uid, $vodId, $sid, $nid, $point, $duration, $onlyAhead = false)
    {
        $now = time();

        // 同一影片同一集只记一条，不区分 sid
        $where = [
            'user_id'   => $uid,
            'ulog_mid'  => 1,
            'ulog_type' => 4,
            'ulog_rid'  => $vodId,
            'ulog_nid'  => $nid,
        ];

        $exist = Db::name('ulog')
            ->field('ulog_id,ulog_point')
            ->where($where)
            ->find();

        if (!empty($exist)) {
            // 合并场景下本地进度不比库内新则跳过，视为成功
            if ($onlyAhead && $point <= intval($exist['ulog_point'])) {
                return 0;
            }
            $update = [
                'ulog_sid'      => $sid,
                'ulog_point'    => $point,
                'ulog_duration' => $duration > 0 ? $duration : Db::raw('ulog_duration'),
                'ulog_time'     => $now,
            ];
            return Db::name('ulog')->where('ulog_id', intval($exist['ulog_id']))->update($update);
        }

        $data = [
            'user_id'       => $uid,
            'ulog_mid'      => 1,
            'ulog_type'     => 4,
            'ulog_rid'      => $vodId,
            'ulog_sid'      => $sid,
            'ulog_nid'      => $nid,
            'ulog_points'   => 0,
            'ulog_point'    => $point,
            'ulog_duration' => $duration,
            'ulog_time'     => $now,
        ];
        return Db::name('ulog')->insert($data);
    }
}
