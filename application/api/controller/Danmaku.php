<?php
namespace app\api\controller;

use think\Request;

class Danmaku extends Base
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
     * 获取某影片某集的弹幕列表（一次性加载）
     *
     * GET /api.php/danmaku/get_list
     * 参数:
     *   vod_id - 必须，影片ID
     *   sid    - 必须，播放源ID
     *   nid    - 必须，集数ID
     *   limit  - 可选，数量，默认1000
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        $param = $request->param();
        $validate = new \app\api\validate\Danmaku();
        if (!$validate->scene('get_list')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        $vod_id = (int)$param['vod_id'];
        $sid = (int)$param['sid'];
        $nid = (int)$param['nid'];
        $limit = isset($param['limit']) ? min((int)$param['limit'], 2000) : 1000;

        $res = model('Danmaku')->getEpisodeDanmaku($vod_id, $sid, $nid, $limit);

        return json($res);
    }

    /**
     * DPlayer兼容格式弹幕接口
     *
     * GET  /api.php/danmaku/dplayer?id={vod_id}-{sid}-{nid}  获取弹幕
     * POST /api.php/danmaku/dplayer  发送弹幕（DPlayer标准POST格式）
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function dplayer(Request $request)
    {
        if ($request->isGet()) {
            // 获取弹幕 - DPlayer标准格式
            $id = $request->param('id', '');
            $parts = explode('-', $id);
            if (count($parts) < 3) {
                return json(['code' => 1, 'data' => []]);
            }

            $vod_id = (int)$parts[0];
            $sid = (int)$parts[1];
            $nid = (int)$parts[2];

            $res = model('Danmaku')->getDplayerDanmaku($vod_id, $sid, $nid);
            return json($res);
        }

        if ($request->isPost()) {
            // 发送弹幕 - DPlayer标准POST
            $user = $this->_checkLoginForApi();
            if (!$user) {
                return json(['code' => 1, 'msg' => lang('danmaku/login_required')]);
            }

            $param = $request->param();
            $id = isset($param['id']) ? $param['id'] : '';
            $parts = explode('-', $id);
            if (count($parts) < 3) {
                return json(['code' => 1, 'msg' => lang('danmaku/param_err')]);
            }

            $vod_id = (int)$parts[0];
            $sid = (int)$parts[1];
            $nid = (int)$parts[2];

            // 校验影片是否存在且可用
            $vod_check = $this->_checkVodAvailable($vod_id);
            if ($vod_check !== true) {
                return json(['code' => 1, 'msg' => $vod_check['msg']]);
            }

            // 公共发送前校验（IP黑名单 + 频率限制）
            $preCheck = $this->_preSendCheck($user);
            if ($preCheck !== true) {
                // DPlayer 期望 code=1 表示失败
                return json(['code' => 1, 'msg' => $preCheck['msg']]);
            }

            $data = [];
            $data['vod_id'] = $vod_id;
            $data['vod_sid'] = $sid;
            $data['vod_nid'] = $nid;
            $data['user_id'] = $user['user_id'];
            $data['user_name'] = !empty($user['user_nick_name']) ? $user['user_nick_name'] : $user['user_name'];
            $data['danmaku_time'] = isset($param['time']) ? (float)$param['time'] : 0;
            $data['danmaku_type'] = isset($param['type']) ? (int)$param['type'] : 0;
            $data['danmaku_color'] = isset($param['color']) ? trim($param['color']) : '#FFFFFF';
            $data['danmaku_text'] = isset($param['text']) ? trim($param['text']) : '';

            if (empty($data['danmaku_text'])) {
                return json(['code' => 1, 'msg' => lang('danmaku/content_empty')]);
            }

            $res = model('Danmaku')->saveData($data);
            // DPlayer期望code=0为成功
            return json(['code' => ($res['code'] == 1) ? 0 : 1, 'msg' => $res['msg']]);
        }

        return json(['code' => 1, 'msg' => lang('param_err')]);
    }

    /**
     * 发送弹幕
     *
     * POST /api.php/danmaku/send
     * 参数:
     *   vod_id - 必须，影片ID
     *   sid    - 必须，播放源ID
     *   nid    - 必须，集数ID
     *   time   - 必须，影片播放到的秒数
     *   text   - 必须，弹幕内容（最长200字）
     *   type   - 可选，0=滚动 1=顶部 2=底部，默认0
     *   color  - 可选，弹幕颜色，默认#FFFFFF
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function send(Request $request)
    {
        // 检查登录
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('danmaku/login_required')]);
        }

        $param = $request->param();
        $validate = new \app\api\validate\Danmaku();
        if (!$validate->scene('send')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        // 校验影片是否存在且可用
        $vod_check = $this->_checkVodAvailable((int)$param['vod_id']);
        if ($vod_check !== true) {
            return json($vod_check);
        }

        // 公共发送前校验（IP黑名单 + 频率限制）
        $preCheck = $this->_preSendCheck($user);
        if ($preCheck !== true) {
            return json($preCheck);
        }

        $data = [];
        $data['vod_id'] = (int)$param['vod_id'];
        $data['vod_sid'] = (int)$param['sid'];
        $data['vod_nid'] = (int)$param['nid'];
        $data['user_id'] = $user['user_id'];
        $data['user_name'] = !empty($user['user_nick_name']) ? $user['user_nick_name'] : $user['user_name'];
        $data['danmaku_time'] = (float)$param['time'];
        $data['danmaku_type'] = isset($param['type']) ? (int)$param['type'] : 0;
        $data['danmaku_color'] = isset($param['color']) ? trim($param['color']) : '#FFFFFF';
        $data['danmaku_text'] = trim($param['text']);

        if (empty($data['danmaku_text'])) {
            return json(['code' => 1002, 'msg' => lang('danmaku/content_empty')]);
        }

        // 验证弹幕类型
        if (!in_array($data['danmaku_type'], [0, 1, 2])) {
            $data['danmaku_type'] = 0;
        }

        $res = model('Danmaku')->saveData($data);

        return json($res);
    }

    /**
     * 举报弹幕
     *
     * POST /api.php/danmaku/report
     * 参数:
     *   danmaku_id - 必须，弹幕ID
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function report(Request $request)
    {
        // 检查登录
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('danmaku/login_required')]);
        }

        $param = $request->param();
        $validate = new \app\api\validate\Danmaku();
        if (!$validate->scene('report')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        $danmaku_id = (int)$param['danmaku_id'];

        $where = [];
        $where['danmaku_id'] = ['eq', $danmaku_id];
        $info = model('Danmaku')->infoData($where);
        if ($info['code'] > 1) {
            return json(['code' => 1002, 'msg' => lang('danmaku/not_found')]);
        }

        // 已禁用的弹幕无需再举报
        if (isset($info['info']['danmaku_status']) && $info['info']['danmaku_status'] != 1) {
            return json(['code' => 1003, 'msg' => lang('danmaku/disabled')]);
        }

        // 同一用户同一弹幕 24 小时内只能举报一次（Cache 去重）
        $cache_key = 'danmaku_report_' . $user['user_id'] . '_' . $danmaku_id;
        if (\think\Cache::get($cache_key)) {
            return json(['code' => 1, 'msg' => lang('danmaku/already_reported')]);
        }
        \think\Cache::set($cache_key, 1, 86400);

        \think\Db::name('danmaku')->where('danmaku_id', $danmaku_id)->setInc('danmaku_report', 1);

        return json(['code' => 1, 'msg' => lang('danmaku/report_ok')]);
    }

    /**
     * 发送弹幕前公共校验：IP黑名单 + 频率限制
     * send() 和 dplayer() POST 共用，避免两个入口防护不对称
     * @param array $user 当前登录用户信息
     * @return array|true true=通过, array=错误响应体
     */
    private function _preSendCheck($user)
    {
        // IP黑名单检查
        $blacks = config('blacks');
        if (!empty($blacks['black_ip_list']) && is_array($blacks['black_ip_list'])) {
            $ip = mac_get_client_ip();
            if (in_array($ip, $blacks['black_ip_list'])) {
                return ['code' => 1005, 'msg' => lang('danmaku/ip_banned')];
            }
        }

        // 频率限制
        if (!model('Danmaku')->checkSendRate($user['user_id'], 5)) {
            return ['code' => 1003, 'msg' => lang('danmaku/rate_limit')];
        }

        return true;
    }

    /**
     * 轻量级校验影片是否存在且可用（status=1）
     * 不走 Vod::infoData 避免加载播放列表等重数据
     * @param int $vod_id
     * @return array|true  true=可用, array=错误响应
     */
    private function _checkVodAvailable($vod_id)
    {
        $vod = \think\Db::name('vod')->field('vod_id,vod_status')->where('vod_id', (int)$vod_id)->find();
        if (empty($vod)) {
            return ['code' => 1004, 'msg' => lang('danmaku/vod_not_found')];
        }
        if ($vod['vod_status'] != 1) {
            return ['code' => 1006, 'msg' => lang('danmaku/vod_not_available')];
        }
        return true;
    }

    /**
     * 检查用户登录状态（API用）
     * @return array|false
     */
    private function _checkLoginForApi()
    {
        $res = model('User')->checkLogin();
        if ($res['code'] == 1) {
            return $res['info'];
        }
        return false;
    }
}
