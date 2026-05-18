<?php
namespace app\common\model;
use think\Db;

class Chatroom extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'chatroom';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto   = [];
    protected $insert = [];
    protected $update = [];

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 50, $start = 0, $field = '*')
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 50;
        $start = $start ? (int)$start : 0;

        if (!is_array($where)) {
            $where = json_decode($where, true);
        }

        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('Chatroom')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    /**
     * 增量获取聊天消息（用于前端智能轮询）
     * after_id=0 时返回最近 N 条（按 chat_id DESC 取再反转，确保用户看到最新消息）
     * after_id>0 时增量拉取新消息（ASC）
     * 结果带 5s 短缓存，同房间多用户轮询不会每次打 DB
     *
     * @param int $vod_id 影片ID
     * @param int $after_id 上次获取的最后一条chat_id
     * @param int $limit 数量限制
     * @return array
     */
    public function getNewMessages($vod_id, $after_id = 0, $limit = 50)
    {
        $vod_id = (int)$vod_id;
        $after_id = (int)$after_id;
        $limit = (int)$limit;

        // 短缓存 key：同房间 + 同 after_id + 同 limit 共享缓存，减少 DB 压力
        $cache_flag = isset($GLOBALS['config']['app']['cache_flag']) ? $GLOBALS['config']['app']['cache_flag'] : 'mac';
        $cache_key = $cache_flag . '_chatroom_msg_' . $vod_id . '_' . $after_id . '_' . $limit;
        $cached = \think\Cache::get($cache_key);
        if ($cached !== false && $cached !== null) {
            return $cached;
        }

        $where = [];
        $where['vod_id'] = ['eq', $vod_id];
        $where['chat_status'] = ['eq', 1];

        if ($after_id > 0) {
            // 增量拉取：获取 after_id 之后的新消息
            $where['chat_id'] = ['gt', $after_id];

            $list = Db::name('Chatroom')
                ->field('chat_id,vod_id,user_id,user_name,chat_content,chat_time')
                ->where($where)
                ->order('chat_id ASC')
                ->limit($limit)
                ->select();
        } else {
            // 首次加载：获取最近 N 条，按 DESC 取再反转，确保看到最新消息
            $list = Db::name('Chatroom')
                ->field('chat_id,vod_id,user_id,user_name,chat_content,chat_time')
                ->where($where)
                ->order('chat_id DESC')
                ->limit($limit)
                ->select();
            // 反转为时间正序，前端按从旧到新显示
            if (!empty($list)) {
                $list = array_reverse($list);
            }
        }

        $last_id = 0;
        if (!empty($list)) {
            $last_id = $list[count($list) - 1]['chat_id'];
            foreach ($list as &$v) {
                $v['user_portrait'] = mac_get_user_portrait($v['user_id']);
                $v['chat_time_text'] = date('H:i:s', $v['chat_time']);
            }
            unset($v);
        }

        $result = ['code' => 1, 'msg' => 'ok', 'info' => [
            'rows'    => $list,
            'last_id' => $last_id,
            'total'   => count($list),
        ]];

        // 缓存 5 秒，同房间内多用户同时轮询共用结果
        \think\Cache::set($cache_key, $result, 5);

        return $result;
    }

    public function infoData($where, $field = '*')
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if (empty($info)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function saveData($data)
    {
        // xss过滤
        if (isset($data['chat_content'])) {
            $data['chat_content'] = mac_filter_xss($data['chat_content']);
            // 长度保险：DB 限制 varchar(500)，XSS 过滤后可能变长
            if (mb_strlen($data['chat_content'], 'UTF-8') > 500) {
                $data['chat_content'] = mb_substr($data['chat_content'], 0, 500, 'UTF-8');
            }
        }

        // 黑名单关键字过滤
        $blacks = config('blacks');
        if (!empty($blacks['black_keyword_list']) && is_array($blacks['black_keyword_list'])) {
            foreach ($blacks['black_keyword_list'] as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword) && strpos($data['chat_content'], $keyword) !== false) {
                    return ['code' => 1003, 'msg' => lang('content_contain_sensitive')];
                }
            }
        }

        if (!empty($data['chat_id'])) {
            $where = [];
            $where['chat_id'] = ['eq', $data['chat_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        } else {
            $data['chat_time'] = time();
            $data['chat_ip'] = mac_get_ip_long();
            $res = $this->allowField(true)->insert($data);
        }

        if (false === $res) {
            return ['code' => 1002, 'msg' => lang('save_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('save_ok')];
    }

    public function delData($where)
    {
        // 删除前先查出受影响的 vod_id，用于清缓存
        $this->_clearChatroomCache($where);
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('del_ok')];
    }

    public function fieldData($where, $col, $val)
    {
        if (!isset($col) || !isset($val)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('set_err') . '：' . $this->getError()];
        }
        // status 变更时清缓存（管理员禁用/启用消息后前台立即生效）
        if ($col === 'chat_status') {
            $this->_clearChatroomCache($where);
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 清除受影响聊天消息所在房间的短缓存
     * 聊天室缓存 key 带 after_id+limit，无法精确匹配全部 key，
     * 因此按 vod_id 前缀批量清除（利用 cache_flag 前缀）
     * @param array $where
     */
    private function _clearChatroomCache($where)
    {
        $flag = isset($GLOBALS['config']['app']['cache_flag']) ? $GLOBALS['config']['app']['cache_flag'] : 'mac';
        $rows = Db::name('chatroom')->where($where)->field('DISTINCT vod_id')->select();
        if (!empty($rows)) {
            foreach ($rows as $r) {
                // 清除该房间首次加载缓存（after_id=0 是最常用场景）
                // 其余增量缓存 TTL 仅 5 秒，自然过期即可
                $cache_key = $flag . '_chatroom_msg_' . $r['vod_id'] . '_0_50';
                \think\Cache::rm($cache_key);
            }
        }
    }

    /**
     * 检查读取(轮询)频率限制
     * 登录用户按 user_id 节流，匿名用户按 IP 节流
     * @param int|null $user_id 登录用户ID，null 表示匿名
     * @param int $interval 最小间隔秒数（登录用户默认2s，匿名默认3s）
     * @return bool true=允许访问
     */
    public function checkReadRate($user_id = null, $interval = 2)
    {
        if ($user_id) {
            $cache_key = 'chatroom_read_rate_u_' . $user_id;
        } else {
            $cache_key = 'chatroom_read_rate_ip_' . mac_get_client_ip();
        }
        return $this->_atomicRateCheck($cache_key, $interval);
    }

    /**
     * 检查发送频率限制（尽量原子化，防止并发 TOCTOU 漏检）
     * @param int $user_id
     * @param int $vod_id
     * @param int $interval 最小间隔秒数
     * @return bool true=允许发送
     */
    public function checkSendRate($user_id, $vod_id, $interval = 3)
    {
        $cache_key = 'chatroom_rate_' . $user_id . '_' . $vod_id;
        return $this->_atomicRateCheck($cache_key, $interval);
    }

    /**
     * 原子化频率检查：Redis 后端用 SETNX，文件缓存回退到 has+set
     * @param string $cache_key
     * @param int $interval 秒
     * @return bool true=允许, false=限流
     */
    private function _atomicRateCheck($cache_key, $interval)
    {
        // 尝试原子 SETNX（仅 Redis 后端）
        try {
            $handler = \think\Cache::handler();
            if ($handler instanceof \Redis) {
                $ok = $handler->set($cache_key, 1, ['NX', 'EX' => $interval]);
                return (bool)$ok;
            }
        } catch (\Exception $e) {
            // handler 不可用，回退到通用方案
        }
        // 通用方案：has+set
        if (\think\Cache::has($cache_key)) {
            return false;
        }
        \think\Cache::set($cache_key, 1, $interval);
        return true;
    }
}
