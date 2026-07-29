<?php
namespace app\common\model;
use think\Db;

class Dynamics extends Base {
    protected $name = 'dynamics';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto   = [];
    protected $insert = [];
    protected $update = [];

    /**
     * 适合公开展示的动态类型白名单。
     * chat（聊天）属于特定聊天室语境，danmaku（弹幕）量大且碎片化，
     * 二者都不进入好友动态流与个人公开动态；写入侧 saveData 仍接受全部 7 种类型，
     * 后台管理列表也仍可查看全量。
     */
    const PUBLIC_TYPES = ['fav', 'comment', 'reply', 'follow', 'share'];

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0, $field = 'dynamics_id,user_id,dynamics_type,dyn_mid,dyn_rid,dyn_pid,dyn_text,dyn_time')
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('dynamics')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        if (!empty($list)) {
            $user_ids = [];
            foreach ($list as $v) {
                if ($v['user_id'] > 0) {
                    $user_ids[$v['user_id']] = $v['user_id'];
                }
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
                    $u = isset($user_map[$v['user_id']]) ? $user_map[$v['user_id']] : null;
                    $list[$k]['user_name'] = $u ? ($u['user_nick_name'] ? $u['user_nick_name'] : $u['user_name']) : '';
                    $list[$k]['user_portrait'] = mac_get_user_portrait($v['user_id']);
                    $list[$k]['dynamics_type_text'] = lang('dynamics/type_' . $v['dynamics_type']);
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
        $user_id = intval($data['user_id']);
        $type = trim($data['dynamics_type']);
        if ($user_id < 1 || empty($type)) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $valid_types = ['fav', 'comment', 'reply', 'follow', 'share', 'danmaku', 'chat'];
        if (!in_array($type, $valid_types)) {
            return ['code' => 1002, 'msg' => lang('param_err')];
        }
        $validate = new \app\common\validate\Dynamics();
        if (!$validate->scene('save')->check(['user_id' => $user_id, 'dynamics_type' => $type])) {
            return ['code' => 1001, 'msg' => $validate->getError()];
        }
        $data['dyn_time'] = time();
        // 汇聚点统一 XSS 过滤：所有动态来源(fav/comment/reply/follow/share)的 dyn_text 在此中和
        if (isset($data['dyn_text'])) {
            $data['dyn_text'] = mac_filter_xss($data['dyn_text']);
        }
        $res = $this->allowField(true)->insertGetId($data);
        if (false === $res) {
            \think\Log::error('[social] Dynamics::saveData ' . $this->getError());
            return ['code' => 1003, 'msg' => lang('save_err')];
        }

        // chat/danmaku 不进入好友动态实时推送：原因与它们被 feedData()/userData() 排除在动态流之外一致，
        // 否则 WS 实时流会与分页动态流永久不一致（chat 自身的 kind=>'chat' 广播由 Chatroom 模型单独触发，不受此影响）。
        if (function_exists('hook') && in_array($type, self::PUBLIC_TYPES, true)) {
            hook('social_broadcast', [
                'kind' => 'dynamics',
                'user_id' => $user_id,
                'data' => [
                    'dynamics_id' => (int)$res,
                    'user_id' => $user_id,
                    'dynamics_type' => $type,
                    'dyn_mid' => intval($data['dyn_mid']),
                    'dyn_rid' => intval($data['dyn_rid']),
                    'dyn_text' => $data['dyn_text'],
                    'dyn_time' => $data['dyn_time'],
                ],
            ]);
        }

        return ['code' => 1, 'msg' => lang('save_ok'), 'info' => ['dynamics_id' => $res]];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            \think\Log::error('[social] Dynamics::delData ' . $this->getError());
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
            \think\Log::error('[social] Dynamics::fieldData ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('set_err')];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    /**
     * 动态流：获取登录用户关注的人的动态
     * @param int $user_id 当前用户ID
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function feedData($user_id, $page = 1, $limit = 20)
    {
        $user_id = intval($user_id);
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;

        if ($user_id < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $follow_ids = model('Follow')->getFollowingIds($user_id);
        if (empty($follow_ids)) {
            return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => 0, 'limit' => $limit, 'total' => 0, 'list' => []];
        }

        $offset = $limit * ($page - 1);
        // total 与 list 必须用同一套类型白名单，否则 pagecount 会算错
        $total = $this->where('user_id', 'in', $follow_ids)
            ->where('dynamics_type', 'in', self::PUBLIC_TYPES)
            ->count();
        $list = Db::name('dynamics')
            ->field('dynamics_id,user_id,dynamics_type,dyn_mid,dyn_rid,dyn_pid,dyn_text,dyn_time')
            ->where('user_id', 'in', $follow_ids)
            ->where('dynamics_type', 'in', self::PUBLIC_TYPES)
            ->order('dyn_time DESC, dynamics_id DESC')
            ->limit($offset, $limit)
            ->select();

        if (!empty($list)) {
            $user_ids = [];
            foreach ($list as $v) {
                $user_ids[$v['user_id']] = $v['user_id'];
            }
            $users = Db::name('user')
                ->field('user_id,user_name,user_nick_name')
                ->where('user_id', 'in', array_values($user_ids))
                ->select();
            $user_map = [];
            foreach ($users as $u) {
                $user_map[$u['user_id']] = $u;
            }
            foreach ($list as $k => $v) {
                $u = isset($user_map[$v['user_id']]) ? $user_map[$v['user_id']] : null;
                $list[$k]['user_name'] = $u ? ($u['user_nick_name'] ? $u['user_nick_name'] : $u['user_name']) : '';
                $list[$k]['user_portrait'] = mac_get_user_portrait($v['user_id']);
                $list[$k]['dynamics_type_text'] = lang('dynamics/type_' . $v['dynamics_type']);
            }
        }

        return ['code' => 1, 'msg' => lang('data_list'), 'page' => $page, 'pagecount' => ceil($total / $limit), 'limit' => $limit, 'total' => $total, 'list' => $list];
    }

    /**
     * 用户自己的动态
     */
    public function userData($user_id, $page = 1, $limit = 20)
    {
        $user_id = intval($user_id);
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;

        if ($user_id < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        // 个人公开动态与 feed 采用同一套白名单；listData 内部 count 与 list 共用 $where，天然一致
        $where = [
            'user_id' => $user_id,
            'dynamics_type' => ['in', self::PUBLIC_TYPES],
        ];
        return $this->listData($where, 'dyn_time DESC, dynamics_id DESC', $page, $limit);
    }
}