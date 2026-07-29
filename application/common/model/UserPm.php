<?php
namespace app\common\model;
use think\Db;

class UserPm extends Base {
    protected $name = 'user_pm';
    protected $createTime = '';
    protected $updateTime = '';
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
        $list = Db::name('user_pm')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        if (!empty($list)) {
            $user_ids = [];
            foreach ($list as $v) {
                $user_ids[$v['from_uid']] = $v['from_uid'];
                $user_ids[$v['to_uid']] = $v['to_uid'];
            }
            if (!empty($user_ids)) {
                $users = Db::name('user')
                    ->field('user_id,user_name,user_nick_name')
                    ->where('user_id', 'in', array_values($user_ids))
                    ->select();
                $user_map = [];
                foreach ($users as $u) {
                    $user_map[$u['user_id']] = $u;
                }
                foreach ($list as $k => $v) {
                    $fu = isset($user_map[$v['from_uid']]) ? $user_map[$v['from_uid']] : null;
                    $tu = isset($user_map[$v['to_uid']]) ? $user_map[$v['to_uid']] : null;
                    $list[$k]['from_name'] = $fu ? ($fu['user_nick_name'] ? $fu['user_nick_name'] : $fu['user_name']) : '';
                    $list[$k]['to_name'] = $tu ? ($tu['user_nick_name'] ? $tu['user_nick_name'] : $tu['user_name']) : '';
                    $list[$k]['from_portrait'] = mac_get_user_portrait($v['from_uid']);
                    $list[$k]['to_portrait'] = mac_get_user_portrait($v['to_uid']);
                }
            }
        }

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
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
        $from_uid = intval($data['from_uid']);
        $to_uid = intval($data['to_uid']);
        $content = trim($data['pm_content']);

        if ($from_uid < 1 || $to_uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        if ($from_uid == $to_uid) {
            return ['code' => 1002, 'msg' => lang('pm/cannot_self')];
        }
        if (empty($content)) {
            return ['code' => 1003, 'msg' => lang('pm/content_empty')];
        }

        // XSS 过滤 + 长度截断（先处理，再用处理后的值做 validate）
        $content = mac_filter_xss($content);
        if (mb_strlen($content, 'UTF-8') > 500) {
            $content = mb_substr($content, 0, 500, 'UTF-8');
        }

        $validate = new \app\common\validate\UserPm();
        if (!$validate->scene('save')->check(['from_uid' => $from_uid, 'to_uid' => $to_uid, 'pm_content' => $content])) {
            return ['code' => 1001, 'msg' => $validate->getError()];
        }

        // 互关校验
        $is_mutual = model('Follow')->isMutual($from_uid, $to_uid);
        if (!$is_mutual) {
            return ['code' => 1004, 'msg' => lang('pm/mutual_required')];
        }

        // 目标用户存在性
        $target = Db::name('user')->where('user_id', $to_uid)->field('user_id')->find();
        if (empty($target)) {
            return ['code' => 1005, 'msg' => lang('pm/user_not_found')];
        }

        // 黑名单关键字
        $blacks = config('blacks');
        if (!empty($blacks['black_keyword_list']) && is_array($blacks['black_keyword_list'])) {
            foreach ($blacks['black_keyword_list'] as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword) && strpos($content, $keyword) !== false) {
                    return ['code' => 1006, 'msg' => lang('content_contain_sensitive')];
                }
            }
        }

        $data['pm_content'] = $content;
        $data['pm_time'] = time();
        $data['pm_ip'] = mac_get_ip_long();
        $data['pm_read'] = 0;
        $data['pm_status'] = 1;

        $res = $this->allowField(true)->insertGetId($data);
        if (false === $res) {
            \think\Log::error('[social] UserPm::saveData ' . $this->getError());
            return ['code' => 1007, 'msg' => lang('save_err')];
        }

        // 通知收信人
        try {
            $from_user = Db::name('user')->where('user_id', $from_uid)->field('user_name,user_nick_name')->find();
            $nick = !empty($from_user['user_nick_name']) ? $from_user['user_nick_name'] : $from_user['user_name'];
            model('Notify')->send($to_uid, 'pm', lang('pm/conversation'), $nick . ': ' . mb_substr($content, 0, 100, 'UTF-8'), '');
        } catch (\Exception $e) {
        }

        if (function_exists('hook')) {
            hook('social_broadcast', [
                'kind' => 'pm',
                'to_uid' => $to_uid,
                'data' => [
                    'pm_id' => (int)$res,
                    'from_uid' => $from_uid,
                    'to_uid' => $to_uid,
                    'pm_content' => $content,
                    'pm_time' => $data['pm_time'],
                    'pm_time_text' => date('H:i:s', $data['pm_time']),
                ],
            ]);
        }

        return ['code' => 1, 'msg' => lang('pm/send_ok'), 'info' => ['pm_id' => $res]];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            \think\Log::error('[social] UserPm::delData ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('del_err')];
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
            \think\Log::error('[social] UserPm::fieldData ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('set_err')];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 获取两个用户之间的会话消息
     */
    public function conversation($uid_a, $uid_b, $page = 1, $limit = 50)
    {
        $uid_a = intval($uid_a);
        $uid_b = intval($uid_b);
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 50;

        if ($uid_a < 1 || $uid_b < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $map1 = ['from_uid' => $uid_a, 'to_uid' => $uid_b];
        $map2 = ['from_uid' => $uid_b, 'to_uid' => $uid_a];

        // 双向会话条件：必须把每个方向各自包成一个 AND 组再 OR。
        // whereOr(数组) 在 TP5 里是把数组内各字段之间用 OR 连接（见 Builder::buildWhere），
        // 会退化成 (a→b) OR from_uid=b OR to_uid=a，从而串入与第三方的私信。
        // 两个方向都显式包一层闭包，不依赖 AND/OR 优先级，后续再加方向时不易写错。
        $cond = function ($query) use ($map1, $map2) {
            $query->where(function ($q) use ($map1) {
                $q->where($map1);
            })->whereOr(function ($q) use ($map2) {
                $q->where($map2);
            });
        };

        $offset = $limit * ($page - 1);
        $total = Db::name('user_pm')
            ->where('pm_status', 1)
            ->where($cond)
            ->count();

        $list = Db::name('user_pm')
            ->field('pm_id,from_uid,to_uid,pm_content,pm_time,pm_read,pm_status')
            ->where('pm_status', 1)
            ->where($cond)
            ->order('pm_id DESC')
            ->limit($offset, $limit)
            ->select();

        if (!empty($list)) {
            $list = array_reverse($list);
            foreach ($list as $k => &$v) {
                $v['from_portrait'] = mac_get_user_portrait($v['from_uid']);
                $v['pm_time_text'] = date('Y-m-d H:i:s', $v['pm_time']);
            }
            unset($v);
        }

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    /**
     * 标记会话中对方发来的消息为已读
     */
    public function markRead($uid, $from_uid)
    {
        $uid = intval($uid);
        $from_uid = intval($from_uid);
        if ($uid < 1 || $from_uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $res = $this->allowField(true)
            ->where(['to_uid' => $uid, 'from_uid' => $from_uid, 'pm_read' => 0])
            ->update(['pm_read' => 1]);
        if ($res === false) {
            \think\Log::error('[social] UserPm::markRead ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('set_err')];
        }
        return ['code' => 1, 'msg' => lang('pm/mark_read')];
    }

    /**
     * 未读消息数
     */
    public function unreadCount($uid)
    {
        $uid = intval($uid);
        if ($uid < 1) {
            return 0;
        }
        return (int)$this->where(['to_uid' => $uid, 'pm_read' => 0, 'pm_status' => 1])->count();
    }

    /**
     * 会话列表：与当前用户有过私信往来的用户去重列表
     * 使用查询构造器生成 UNION 子查询 + SQL 层分页，避免全量加载后 PHP array_slice
     */
    public function conversationList($uid, $page = 1, $limit = 20)
    {
        $uid = intval($uid);
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;

        if ($uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $pre = config('database.prefix');
        $tbl = $pre . 'user_pm';
        $offset = $limit * ($page - 1);

        // 用参数绑定避免拼接，SQL 层分页
        $inner = "SELECT to_uid AS peer_uid, pm_id FROM `{$tbl}` WHERE from_uid = ? AND pm_status = 1
                  UNION ALL
                  SELECT from_uid AS peer_uid, pm_id FROM `{$tbl}` WHERE to_uid = ? AND pm_status = 1";
        $agg = "SELECT peer_uid, MAX(pm_id) AS last_pm_id FROM ($inner) AS t GROUP BY peer_uid";

        $count_sql = "SELECT COUNT(*) AS cnt FROM ($agg) AS agg";
        $count_row = Db::query($count_sql, [$uid, $uid]);
        $total = isset($count_row[0]['cnt']) ? (int)$count_row[0]['cnt'] : 0;

        $page_sql = $agg . " ORDER BY last_pm_id DESC LIMIT " . $offset . "," . $limit;
        $page_rows = Db::query($page_sql, [$uid, $uid]);

        $list = [];
        if (!empty($page_rows)) {
            $peer_ids = [];
            $last_ids = [];
            foreach ($page_rows as $r) {
                $peer_ids[$r['peer_uid']] = $r['peer_uid'];
                $last_ids[] = $r['last_pm_id'];
            }
            $last_msgs = Db::name('user_pm')->where('pm_id', 'in', $last_ids)->select();
            $last_map = [];
            foreach ($last_msgs as $m) {
                $last_map[$m['pm_id']] = $m;
            }
            $users = Db::name('user')
                ->field('user_id,user_name,user_nick_name')
                ->where('user_id', 'in', array_values($peer_ids))
                ->select();
            $user_map = [];
            foreach ($users as $u) {
                $user_map[$u['user_id']] = $u;
            }
            foreach ($page_rows as $r) {
                $peer_uid = $r['peer_uid'];
                $msg = isset($last_map[$r['last_pm_id']]) ? $last_map[$r['last_pm_id']] : null;
                $u = isset($user_map[$peer_uid]) ? $user_map[$peer_uid] : null;
                $unread = (int)Db::name('user_pm')
                    ->where(['to_uid' => $uid, 'from_uid' => $peer_uid, 'pm_read' => 0, 'pm_status' => 1])
                    ->count();
                $list[] = [
                    'peer_uid' => $peer_uid,
                    'peer_name' => $u ? ($u['user_nick_name'] ? $u['user_nick_name'] : $u['user_name']) : '',
                    'peer_portrait' => mac_get_user_portrait($peer_uid),
                    'last_pm_id' => $r['last_pm_id'],
                    'last_content' => $msg ? mb_substr($msg['pm_content'], 0, 100, 'UTF-8') : '',
                    'last_time' => $msg ? $msg['pm_time'] : 0,
                    'unread' => $unread,
                ];
            }
        }

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }
}