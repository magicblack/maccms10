<?php

namespace addons\socialws;

use think\Addons;

class Socialws extends Addons
{
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    /**
     * Hook: app_init — fires early in the request bootstrap, before
     * App::routeCheck() runs.
     * maccms disables ThinkPHP route checking (url_route_on=false) by default,
     * which prevents the fastadmin-addons Route::any('addons/:addon/...') from matching.
     * Only enable route checking when the request targets this addon, to avoid
     * side-effects on MaCMS's own URL resolution for all other requests.
     */
    public function appInit()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($uri, 'addons/socialws') !== false) {
            \think\App::route(true);
        }
    }

    /**
     * Hook: social_broadcast — fired by Chatroom::saveData() / Danmaku::saveData()
     * after a new chat/danmaku row is inserted. Pushes it to the matching
     * GatewayWorker room. Never throws — a down/unreachable daemon must not
     * affect the HTTP request that already committed the DB write.
     */
    public function socialBroadcast($params)
    {
        if (empty($params['kind']) || empty($params['data'])) {
            return;
        }

        if (!class_exists('\GatewayWorker\Lib\Gateway')) {
            return;
        }

        // 计算目标房间放进 try：dynamics 扇出需查粉丝(DB)，DB 异常也不得
        // 冒泡影响已提交写入的 HTTP 请求（保持本方法「绝不抛出」的契约）。
        try {
            $rooms = $this->targetRooms($params);
            if (empty($rooms)) {
                return;
            }
            $config = $this->getConfig();
            \GatewayWorker\Lib\Gateway::$registerAddress = $config['register_address'];
            \GatewayWorker\Lib\Gateway::$connectTimeout = 0.2;
            foreach ($rooms as $room) {
                \GatewayWorker\Lib\Gateway::sendToGroup($room, json_encode([
                    'type' => $params['kind'],
                    'room' => $room,
                    'data' => $params['data'],
                ]));
            }
        } catch (\Throwable $e) {
            \think\Log::record('[socialws] broadcast fail: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * 广播目标房间列表。除 dynamics 外均为单房间(roomKey)。
     * dynamics 必须扇出到发布者每个粉丝自己的 user_<fid> 房间：Events::canSubscribe
     * 只允许用户订阅自身 user_ 房间，若推到发布者本人房间(user_<actor>)关注者收不到。
     * 粉丝各自订阅自己的 user_<自己uid>（与 pm/follow 同一房间）即可实时收到动态。
     */
    private function targetRooms($params)
    {
        if ($params['kind'] === 'dynamics') {
            if (!isset($params['user_id'])) {
                return [];
            }
            $fan_ids = (new \app\common\model\Follow())->getFansIds((int)$params['user_id']);
            $rooms = [];
            foreach ($fan_ids as $fid) {
                $rooms[] = 'user_' . (int)$fid;
            }
            return $rooms;
        }
        $room = $this->roomKey($params);
        return $room === '' ? [] : [$room];
    }

    /**
     * Room key formula — MUST stay identical to Events::roomKey() in
     * addons/socialws/server/Events.php (duplicated deliberately; the two
     * runtimes don't share a code layer).
     */
    private function roomKey($params)
    {
        $kind = $params['kind'];
        if ($kind === 'chat') {
            if (!isset($params['vod_id'])) {
                return '';
            }
            return 'chat_' . (int)$params['vod_id'];
        }
        if ($kind === 'danmaku') {
            if (!isset($params['vod_id']) || !isset($params['sid']) || !isset($params['nid'])) {
                return '';
            }
            return 'dm_' . (int)$params['vod_id'] . '_' . (int)$params['sid'] . '_' . (int)$params['nid'];
        }
        // 推送到收信人的个人房间，前端订阅 user_<自己的uid> 即可收到
        if ($kind === 'follow') {
            if (!isset($params['follow_uid'])) {
                return '';
            }
            return 'user_' . (int)$params['follow_uid'];
        }
        if ($kind === 'dynamics') {
            // dynamics 推送给关注者的个人房间
            if (!isset($params['user_id'])) {
                return '';
            }
            return 'user_' . (int)$params['user_id'];
        }
        if ($kind === 'pm') {
            // pm 推送给收信人的个人房间
            if (!isset($params['to_uid'])) {
                return '';
            }
            return 'user_' . (int)$params['to_uid'];
        }
        return '';
    }
}
