<?php
namespace app\api\controller;

use think\Request;

class Chatroom extends Base
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
     * 获取聊天室消息列表（支持增量拉取）
     *
     * GET /api.php/chatroom/get_list
     * 参数:
     *   vod_id   - 必须，影片ID
     *   after_id - 可选，上次获取的最后一条chat_id（增量拉取）
     *   limit    - 可选，数量，默认50，最大100
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        $param = $request->param();
        $validate = new \app\api\validate\Chatroom();
        if (!$validate->scene('get_list')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        // 轮询频率限制：登录用户 2s，匿名用户 3s
        $user = $this->_checkLoginForApi();
        $user_id = $user ? $user['user_id'] : null;
        $interval = $user_id ? 2 : 3;
        if (!model('Chatroom')->checkReadRate($user_id, $interval)) {
            return json(['code' => 1004, 'msg' => lang('chatroom/rate_limit')]);
        }

        $vod_id = (int)$param['vod_id'];
        $after_id = isset($param['after_id']) ? (int)$param['after_id'] : 0;
        $limit = isset($param['limit']) ? min((int)$param['limit'], 100) : 50;

        $res = model('Chatroom')->getNewMessages($vod_id, $after_id, $limit);

        return json($res);
    }

    /**
     * 发送聊天消息
     *
     * POST /api.php/chatroom/send
     * 参数:
     *   vod_id  - 必须，影片ID
     *   content - 必须，聊天内容（最长500字）
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function send(Request $request)
    {
        // 检查登录
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('chatroom/login_required')]);
        }

        $param = $request->param();
        $validate = new \app\api\validate\Chatroom();
        if (!$validate->scene('send')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        $vod_id = (int)$param['vod_id'];
        $content = trim($param['content']);

        if (empty($content)) {
            return json(['code' => 1002, 'msg' => lang('chatroom/content_empty')]);
        }

        // IP黑名单检查
        $blacks = config('blacks');
        if (!empty($blacks['black_ip_list']) && is_array($blacks['black_ip_list'])) {
            $ip = mac_get_client_ip();
            if (in_array($ip, $blacks['black_ip_list'])) {
                return json(['code' => 1005, 'msg' => lang('chatroom/ip_banned')]);
            }
        }

        // 频率限制
        if (!model('Chatroom')->checkSendRate($user['user_id'], $vod_id, 3)) {
            return json(['code' => 1003, 'msg' => lang('chatroom/rate_limit')]);
        }

        $data = [];
        $data['vod_id'] = $vod_id;
        $data['user_id'] = $user['user_id'];
        $data['user_name'] = !empty($user['user_nick_name']) ? $user['user_nick_name'] : $user['user_name'];
        $data['chat_content'] = $content;

        $res = model('Chatroom')->saveData($data);

        return json($res);
    }

    /**
     * 举报聊天消息
     *
     * POST /api.php/chatroom/report
     * 参数:
     *   chat_id - 必须，聊天消息ID
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function report(Request $request)
    {
        // 检查登录
        $user = $this->_checkLoginForApi();
        if (!$user) {
            return json(['code' => 1010, 'msg' => lang('chatroom/login_required')]);
        }

        $param = $request->param();
        $validate = new \app\api\validate\Chatroom();
        if (!$validate->scene('report')->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('param_err') . ': ' . $validate->getError(),
            ]);
        }

        $chat_id = (int)$param['chat_id'];

        $where = [];
        $where['chat_id'] = ['eq', $chat_id];
        $info = model('Chatroom')->infoData($where);
        if ($info['code'] > 1) {
            return json(['code' => 1002, 'msg' => lang('chatroom/msg_not_found')]);
        }

        // 已禁用的消息无需再举报
        if (isset($info['info']['chat_status']) && $info['info']['chat_status'] != 1) {
            return json(['code' => 1003, 'msg' => lang('chatroom/disabled')]);
        }

        // 同一用户同一消息 24 小时内只能举报一次（Cache 去重）
        $cache_key = 'chatroom_report_' . $user['user_id'] . '_' . $chat_id;
        if (\think\Cache::get($cache_key)) {
            return json(['code' => 1, 'msg' => lang('chatroom/already_reported')]);
        }
        \think\Cache::set($cache_key, 1, 86400);

        \think\Db::name('chatroom')->where('chat_id', $chat_id)->setInc('chat_report', 1);

        return json(['code' => 1, 'msg' => lang('chatroom/report_ok')]);
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
