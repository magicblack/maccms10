<?php

namespace addons\socialws\server;

use GatewayWorker\Lib\Gateway;

class Events
{
    /**
     * 握手阶段：解析登录态，把 client_id -> user_id 绑定存入 Gateway 的 session。
     * 浏览器发起 WebSocket 连接时会携带 Cookie，GatewayWorker 在 onWebSocketConnect
     * 时机可读到 $_SERVER['HTTP_COOKIE']。复用站点的 user 登录 cookie 还原 uid，
     * 并查库校验 user_check = md5(user_random.'-'.user_name.'-'.user_id.'-')，
     * 防止攻击者伪造 cookie 窃听他人个人房间（私信/关注/动态推送）。
     */
    public static function onWebSocketConnect($client_id, $data)
    {
        $uid = self::resolveUidFromCookie(isset($data['server']) ? $data['server'] : []);
        if ($uid > 0) {
            $_SESSION['uid'] = $uid;
        }
    }

    public static function onMessage($client_id, $message)
    {
        $data = json_decode($message, true);
        if (!is_array($data) || empty($data['type'])) {
            return;
        }

        switch ($data['type']) {
            case 'ping':
                Gateway::sendToClient($client_id, json_encode(['type' => 'pong']));
                break;

            case 'subscribe':
                $joined = [];
                $denied = [];
                if (!empty($data['rooms']) && is_array($data['rooms'])) {
                    foreach ($data['rooms'] as $room) {
                        $key = self::roomKey($room);
                        if ($key === '') {
                            continue;
                        }
                        if (!self::canSubscribe($key, $room)) {
                            $denied[] = $key;
                            continue;
                        }
                        Gateway::joinGroup($client_id, $key);
                        $joined[] = $key;
                    }
                }
                Gateway::sendToClient($client_id, json_encode(['type' => 'subscribed', 'rooms' => $joined, 'denied' => $denied]));
                break;

            case 'unsubscribe':
                if (!empty($data['rooms']) && is_array($data['rooms'])) {
                    foreach ($data['rooms'] as $room) {
                        $key = self::roomKey($room);
                        if ($key !== '') {
                            Gateway::leaveGroup($client_id, $key);
                        }
                    }
                }
                break;
        }
    }

    /**
     * 订阅鉴权：个人房间(user_<uid>)只允许本人订阅；
     * 公开房间(chat_xxx / dm_xxx)任何人均可订阅。
     */
    private static function canSubscribe($key, $room)
    {
        // 公开房间：聊天室 / 弹幕
        if (strpos($key, 'chat_') === 0 || strpos($key, 'dm_') === 0) {
            return true;
        }
        // 个人房间：user_<uid> —— 仅本人可订阅
        if (strpos($key, 'user_') === 0) {
            $target_uid = (int)substr($key, 5);
            $my_uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
            return $my_uid > 0 && $my_uid === $target_uid;
        }
        return false;
    }

    /**
     * 从握手 Cookie 还原当前登录 user_id，并查库校验 user_check 完整性。
     * 校验公式：user_check = md5(user_random.'-'.user_name.'-'.user_id.'-')
     * 与 application/common/model/User.php::checkLogin() 一致。
     * 查库结果在常驻进程内存缓存 60 秒，避免每次握手都打 DB。
     */
    private static function resolveUidFromCookie(array $server)
    {
        $cookie_raw = isset($server['HTTP_COOKIE']) ? $server['HTTP_COOKIE'] : '';
        if (empty($cookie_raw)) {
            return 0;
        }
        $cookies = [];
        foreach (explode(';', $cookie_raw) as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2) {
                $cookies[urldecode($parts[0])] = urldecode($parts[1]);
            }
        }
        $uid = isset($cookies['user_id']) ? (int)$cookies['user_id'] : 0;
        $check = isset($cookies['user_check']) ? $cookies['user_check'] : '';
        if ($uid < 1 || empty($check)) {
            return 0;
        }

        // 内存缓存：校验结果 60 秒内复用，减少 DB 压力。
        // 缓存键必须是 (uid, user_check) 组合，不能只用 uid：
        //  - 只用 uid 时正结果会被复用 —— 受害者握手成功后的 60 秒内，攻击者带
        //    `user_id=<受害者ID>; user_check=<任意值>` 就会被认成受害者，
        //    canSubscribe() 随即放行 user_<uid> 个人房间（私信/动态推送）；
        //  - 负结果同样会被复用 —— 攻击者拿错误 check 打某个 uid，就能把本人顶掉 60 秒。
        // user_check 是 md5(user_random.'-'.user_name.'-'.user_id.'-')，攻击者无法伪造，
        // 因此「命中组合键」等价于「本次已出示过同一份凭证」，两个方向都关死。
        static $cache = [];
        $now = time();
        // check 取 md5 而非原值：cookie 长度由攻击者控制，直接做数组键会让
        // 常驻进程按对方给的字节数占内存（条数有上界，字节数没有）
        $cache_key = $uid . '|' . md5($check);
        if (isset($cache[$cache_key]) && $cache[$cache_key]['exp'] > $now) {
            return $cache[$cache_key]['ok'] ? $uid : 0;
        }

        // 组合键的取值空间由攻击者控制，必须给常驻进程的内存设上界：
        // 超过阈值先清过期项，清完仍超就整体重置（大不了多打几次 DB）
        if (count($cache) > 2000) {
            foreach ($cache as $k => $v) {
                if ($v['exp'] <= $now) {
                    unset($cache[$k]);
                }
            }
            if (count($cache) > 2000) {
                $cache = [];
            }
        }

        $ok = self::verifyUserCheck($uid, $check);
        $cache[$cache_key] = ['ok' => $ok, 'exp' => $now + 60];
        return $ok ? $uid : 0;
    }

    /**
     * 查库校验 user_check = md5(user_random.'-'.user_name.'-'.user_id.'-')
     * 常驻进程读取站点 application/database.php 配置，用 PDO 做一次性轻量查询。
     */
    private static function verifyUserCheck($uid, $user_check)
    {
        static $db_cfg = null;
        if ($db_cfg === null) {
            $cfg_file = __DIR__ . '/../../../application/database.php';
            if (!is_file($cfg_file)) {
                return false;
            }
            $db_cfg = include $cfg_file;
            if (!is_array($db_cfg)) {
                return false;
            }
        }
        try {
            $dsn = 'mysql:host=' . $db_cfg['hostname'] . ';port=' . $db_cfg['hostport'] . ';dbname=' . $db_cfg['database'] . ';charset=utf8mb4';
            $pdo = new \PDO($dsn, $db_cfg['username'], $db_cfg['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ]);
            $pre = isset($db_cfg['prefix']) ? $db_cfg['prefix'] : 'mac_';
            $stmt = $pdo->prepare('SELECT user_name, user_random FROM ' . $pre . 'user WHERE user_id = :uid LIMIT 1');
            $stmt->bindValue(':uid', $uid, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (empty($row) || empty($row['user_random']) || empty($row['user_name'])) {
                return false;
            }
            $expected = md5($row['user_random'] . '-' . $row['user_name'] . '-' . $uid . '-');
            return hash_equals($expected, (string)$user_check);
        } catch (\Exception $e) {
            // DB 不可用时拒绝所有个人房间订阅，宁可保守也不放行
            return false;
        }
    }

    /**
     * Room key formula — MUST stay identical to Socialws::roomKey() in
     * addons/socialws/Socialws.php (duplicated deliberately; no shared code
     * layer between the resident process and the web request).
     */
    private static function roomKey($r)
    {
        if (!is_array($r) || empty($r['kind'])) {
            return '';
        }
        if ($r['kind'] === 'chat') {
            if (!isset($r['vod_id'])) {
                return '';
            }
            return 'chat_' . (int)$r['vod_id'];
        }
        if ($r['kind'] === 'danmaku') {
            if (!isset($r['vod_id']) || !isset($r['sid']) || !isset($r['nid'])) {
                return '';
            }
            return 'dm_' . (int)$r['vod_id'] . '_' . (int)$r['sid'] . '_' . (int)$r['nid'];
        }
        if ($r['kind'] === 'follow') {
            if (!isset($r['follow_uid'])) {
                return '';
            }
            return 'user_' . (int)$r['follow_uid'];
        }
        if ($r['kind'] === 'dynamics') {
            if (!isset($r['user_id'])) {
                return '';
            }
            return 'user_' . (int)$r['user_id'];
        }
        if ($r['kind'] === 'pm') {
            if (!isset($r['to_uid'])) {
                return '';
            }
            return 'user_' . (int)$r['to_uid'];
        }
        return '';
    }
}
