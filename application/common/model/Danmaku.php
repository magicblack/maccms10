<?php
namespace app\common\model;
use think\Db;

class Danmaku extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'danmaku';

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

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0, $field = '*')
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;

        if (!is_array($where)) {
            $where = json_decode($where, true);
        }

        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('Danmaku')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    /**
     * 获取某影片某集的所有弹幕（一次性加载）
     * @param int $vod_id 影片ID
     * @param int $sid 播放源ID
     * @param int $nid 集数ID
     * @param int $limit 数量限制
     * @return array
     */
    public function getEpisodeDanmaku($vod_id, $sid, $nid, $limit = 1000)
    {
        $cache_key = $GLOBALS['config']['app']['cache_flag'] . '_danmaku_' . $vod_id . '_' . $sid . '_' . $nid;
        $cached = \think\Cache::get($cache_key);
        if ($cached) {
            return $cached;
        }

        $where = [];
        $where['vod_id'] = ['eq', (int)$vod_id];
        $where['vod_sid'] = ['eq', (int)$sid];
        $where['vod_nid'] = ['eq', (int)$nid];
        $where['danmaku_status'] = ['eq', 1];

        $list = Db::name('Danmaku')
            ->field('danmaku_id,danmaku_time,danmaku_type,danmaku_color,danmaku_text,user_name,danmaku_send_time')
            ->where($where)
            ->order('danmaku_time ASC')
            ->limit($limit)
            ->select();

        $result = ['code' => 1, 'msg' => 'ok', 'info' => [
            'total' => count($list),
            'rows'  => $list,
        ]];

        // 缓存5分钟
        \think\Cache::set($cache_key, $result, 300);

        return $result;
    }

    /**
     * 获取DPlayer格式弹幕数据（复用 getEpisodeDanmaku 缓存，保持两个接口行为一致）
     * @param int $vod_id
     * @param int $sid
     * @param int $nid
     * @return array DPlayer标准格式
     */
    public function getDplayerDanmaku($vod_id, $sid, $nid)
    {
        // 复用 getEpisodeDanmaku 的 5 分钟缓存，避免同一份数据两个接口缓存行为不一致
        $res = $this->getEpisodeDanmaku($vod_id, $sid, $nid, 1000);
        $rows = isset($res['info']['rows']) ? $res['info']['rows'] : [];

        // DPlayer标准格式: [time, type, color, author, text]
        $data = [];
        foreach ($rows as $v) {
            $data[] = [
                (float)$v['danmaku_time'],
                (int)$v['danmaku_type'],
                $v['danmaku_color'],
                $v['user_name'],
                $v['danmaku_text'],
            ];
        }

        return ['code' => 0, 'data' => $data];
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
        if (isset($data['danmaku_text'])) {
            $data['danmaku_text'] = mac_filter_xss($data['danmaku_text']);
            // 长度保险：DB 限制 varchar(200)，XSS 过滤后可能变长
            if (mb_strlen($data['danmaku_text'], 'UTF-8') > 200) {
                $data['danmaku_text'] = mb_substr($data['danmaku_text'], 0, 200, 'UTF-8');
            }
        }

        // 弹幕颜色白名单校验，仅允许 #hex 格式，防止 CSS 注入
        if (isset($data['danmaku_color'])) {
            $color = trim($data['danmaku_color']);
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                $color = '#FFFFFF';
            }
            $data['danmaku_color'] = $color;
        }

        // 黑名单关键字过滤
        $blacks = config('blacks');
        if (!empty($blacks['black_keyword_list']) && is_array($blacks['black_keyword_list'])) {
            foreach ($blacks['black_keyword_list'] as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword) && strpos($data['danmaku_text'], $keyword) !== false) {
                    return ['code' => 1003, 'msg' => lang('content_contain_sensitive')];
                }
            }
        }

        if (!empty($data['danmaku_id'])) {
            $where = [];
            $where['danmaku_id'] = ['eq', $data['danmaku_id']];
            $res = $this->allowField(true)->where($where)->update($data);
            // 更新时也清除缓存（编辑弹幕内容后前台立即生效）
            $this->_clearEpisodeCache($where);
        } else {
            $data['danmaku_send_time'] = time();
            $data['danmaku_ip'] = mac_get_ip_long();
            $res = $this->allowField(true)->insert($data);

            // 清除该集弹幕缓存
            $cache_key = $GLOBALS['config']['app']['cache_flag'] . '_danmaku_' . $data['vod_id'] . '_' . $data['vod_sid'] . '_' . $data['vod_nid'];
            \think\Cache::rm($cache_key);
        }

        if (false === $res) {
            return ['code' => 1002, 'msg' => lang('save_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('save_ok')];
    }

    public function delData($where)
    {
        // 删除前先查出受影响的集数，用于清缓存
        $this->_clearEpisodeCache($where);
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
        // status 变更时清缓存（管理员禁用/启用弹幕后前台立即生效）
        if ($col === 'danmaku_status') {
            $this->_clearEpisodeCache($where);
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 清除受影响弹幕所在集数的缓存
     * 查询 where 命中的弹幕，取出所有不重复的 (vod_id, vod_sid, vod_nid) 并逐一清缓存
     * @param array $where
     */
    private function _clearEpisodeCache($where)
    {
        $flag = isset($GLOBALS['config']['app']['cache_flag']) ? $GLOBALS['config']['app']['cache_flag'] : 'mac';
        $rows = Db::name('danmaku')->where($where)->field('DISTINCT vod_id, vod_sid, vod_nid')->select();
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $cache_key = $flag . '_danmaku_' . $r['vod_id'] . '_' . $r['vod_sid'] . '_' . $r['vod_nid'];
                \think\Cache::rm($cache_key);
            }
        }
    }

    /**
     * 检查发送频率限制（尽量原子化，防止并发 TOCTOU 漏检）
     * Redis 后端使用 SETNX 原子操作；文件缓存回退到 has+set（窗口更小）
     * @param int $user_id
     * @param int $interval 最小间隔秒数
     * @return bool true=允许发送
     */
    public function checkSendRate($user_id, $interval = 5)
    {
        $cache_key = 'danmaku_rate_' . $user_id;
        // 尝试原子 SETNX（仅 Redis 后端）
        try {
            $handler = \think\Cache::handler();
            if ($handler instanceof \Redis) {
                // SET key 1 EX interval NX — 仅当 key 不存在时才设置，原子操作
                $ok = $handler->set($cache_key, 1, ['NX', 'EX' => $interval]);
                return (bool)$ok; // true=设置成功(允许), false=key已存在(限流)
            }
        } catch (\Exception $e) {
            // handler 不可用，回退到通用方案
        }
        // 通用方案：has+set（窗口比 get+compare+set 更小）
        if (\think\Cache::has($cache_key)) {
            return false;
        }
        \think\Cache::set($cache_key, 1, $interval);
        return true;
    }
}
