<?php
namespace app\common\model;
use think\Db;

class Follow extends Base {
    protected $name = 'user_follow';
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

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0, $field = '*', $name_field = 'follow_uid')
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->where($where)->count();
        $list = Db::name('user_follow')->field($field)->where($where)->order($order)->limit($limit_str)->select();

        if (!empty($list)) {
            $user_ids = [];
            foreach ($list as $k => $v) {
                if ($v['user_id'] > 0) {
                    $user_ids[$v['user_id']] = $v['user_id'];
                }
                if ($v['follow_uid'] > 0) {
                    $user_ids[$v['follow_uid']] = $v['follow_uid'];
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
                // $name_field 决定列表展示哪一侧的用户：
                // following 列表( where user_id=当前用户 )展示被关注者 -> follow_uid
                // fans 列表( where follow_uid=当前用户 )展示关注者 -> user_id
                $col = in_array($name_field, ['user_id', 'follow_uid'], true) ? $name_field : 'follow_uid';
                foreach ($list as $k => $v) {
                    $uid = (int)$v[$col];
                    $u = isset($user_map[$uid]) ? $user_map[$uid] : null;
                    $list[$k]['follow_user_name'] = $u ? ($u['user_nick_name'] ?: $u['user_name']) : '';
                    $list[$k]['follow_portrait'] = mac_get_user_portrait($uid);
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
        $follow_uid = intval($data['follow_uid']);
        if ($user_id < 1 || $follow_uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        if ($user_id == $follow_uid) {
            return ['code' => 1002, 'msg' => lang('follow/cannot_self')];
        }
        $validate = new \app\common\validate\Follow();
        if (!$validate->scene('save')->check(['user_id' => $user_id, 'follow_uid' => $follow_uid])) {
            return ['code' => 1001, 'msg' => $validate->getError()];
        }
        $target = Db::name('user')->where('user_id', $follow_uid)->field('user_id')->find();
        if (empty($target)) {
            return ['code' => 1003, 'msg' => lang('follow/user_not_found')];
        }
        $now = time();
        $mutual = 0;
        $is_new_follow = false;
        Db::startTrans();
        try {
            // 自身行同样在事务内加锁读取：并发的两次「关注」只有一次能看到 follow_status!=1，
            // 由它承担写动态/发通知，另一次判定为重复点击而跳过
            $exist = $this->where(['user_id' => $user_id, 'follow_uid' => $follow_uid])->lock(true)->find();
            $is_new_follow = empty($exist) || (int)$exist['follow_status'] !== 1;
            // reverse 行必须在事务内加锁读取：借助 uk_user_follow 唯一索引的间隙锁，
            // 阻塞 A→B 与 B→A 并发关注时对方 reverse 行的并发插入，避免双向 mutual 都写成 0
            $reverse = $this->where(['user_id' => $follow_uid, 'follow_uid' => $user_id, 'follow_status' => 1])->lock(true)->find();
            $mutual = $reverse ? 1 : 0;
            if ($exist) {
                $this->where(['follow_id' => $exist['follow_id']])->update([
                    'follow_status' => 1,
                    'follow_mutual' => $mutual,
                    'follow_time'   => $now,
                ]);
            } else {
                $this->insert([
                    'user_id'       => $user_id,
                    'follow_uid'    => $follow_uid,
                    'follow_status' => 1,
                    'follow_mutual' => $mutual,
                    'follow_time'   => $now,
                ]);
            }
            if ($reverse) {
                $this->where(['follow_id' => $reverse['follow_id']])->update(['follow_mutual' => 1]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 1004, 'msg' => lang('save_err')];
        }
        // 广播不设闸：推的是当前互关状态，前端靠它刷新按钮，重复推送是幂等的状态同步
        $this->_fireBroadcast($user_id, $follow_uid, $mutual);

        // 通知与动态是持久化的用户可见记录，只在真实发生「未关注→已关注」的状态转换时写一次。
        // 已在关注中再点一次不得刷屏；取关后重新关注仍算一次新的转换，照常写。
        if ($is_new_follow) {
            $this->_notifyFollow($user_id, $follow_uid);

            // 写动态
            try {
                $from_user = Db::name('user')->where('user_id', $user_id)->field('user_name,user_nick_name')->find();
                $nick = !empty($from_user['user_nick_name']) ? $from_user['user_nick_name'] : $from_user['user_name'];
                $to_user = Db::name('user')->where('user_id', $follow_uid)->field('user_name,user_nick_name')->find();
                $to_nick = !empty($to_user['user_nick_name']) ? $to_user['user_nick_name'] : $to_user['user_name'];
                model('Dynamics')->saveData([
                    'user_id'       => $user_id,
                    'dynamics_type' => 'follow',
                    'dyn_mid'       => 0,
                    'dyn_rid'       => $follow_uid,
                    'dyn_text'      => $nick . ' → ' . $to_nick,
                ]);
            } catch (\Exception $e) {
            }
        }

        return ['code' => 1, 'msg' => lang('follow/ok'), 'info' => ['mutual' => $mutual]];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            \think\Log::error('[social] Follow::delData ' . $this->getError());
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
            \think\Log::error('[social] Follow::fieldData ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('set_err')];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    public function unfollow($user_id, $follow_uid)
    {
        $user_id = intval($user_id);
        $follow_uid = intval($follow_uid);
        if ($user_id < 1 || $follow_uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $row = $this->where(['user_id' => $user_id, 'follow_uid' => $follow_uid, 'follow_status' => 1])->find();
        if (empty($row)) {
            return ['code' => 1002, 'msg' => lang('follow/not_following')];
        }
        Db::startTrans();
        try {
            $this->where(['follow_id' => $row['follow_id']])->update(['follow_status' => 0, 'follow_mutual' => 0]);
            $reverse = $this->where(['user_id' => $follow_uid, 'follow_uid' => $user_id, 'follow_status' => 1])->find();
            if ($reverse) {
                $this->where(['follow_id' => $reverse['follow_id']])->update(['follow_mutual' => 0]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => 1003, 'msg' => lang('del_err')];
        }
        return ['code' => 1, 'msg' => lang('follow/unfollow_ok')];
    }

    public function isFollowing($user_id, $follow_uid)
    {
        $user_id = intval($user_id);
        $follow_uid = intval($follow_uid);
        if ($user_id < 1 || $follow_uid < 1) {
            return false;
        }
        $row = $this->where(['user_id' => $user_id, 'follow_uid' => $follow_uid, 'follow_status' => 1])->find();
        return !empty($row);
    }

    public function isMutual($user_id, $follow_uid)
    {
        $user_id = intval($user_id);
        $follow_uid = intval($follow_uid);
        if ($user_id < 1 || $follow_uid < 1) {
            return false;
        }
        $row = $this->where(['user_id' => $user_id, 'follow_uid' => $follow_uid, 'follow_status' => 1, 'follow_mutual' => 1])->find();
        return !empty($row);
    }

    public function getFollowingIds($user_id)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return [];
        }
        $rows = $this->where(['user_id' => $user_id, 'follow_status' => 1])->column('follow_uid');
        return $rows ? array_map('intval', $rows) : [];
    }

    public function followingCount($user_id)
    {
        return (int)$this->where(['user_id' => intval($user_id), 'follow_status' => 1])->count();
    }

    /**
     * 粉丝ID列表：谁关注了 $user_id（follow_uid = $user_id 且 follow_status=1）。
     * 用于动态实时推送时向每个粉丝自己的 user_<fid> 房间扇出。
     */
    public function getFansIds($user_id)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return [];
        }
        $rows = $this->where(['follow_uid' => $user_id, 'follow_status' => 1])->column('user_id');
        return $rows ? array_map('intval', $rows) : [];
    }

    public function fansCount($user_id)
    {
        return (int)$this->where(['follow_uid' => intval($user_id), 'follow_status' => 1])->count();
    }

    /**
     * 公开用户资料：供资料弹层使用。
     * 只返回公开字段，不含 email / 手机号 / IP 等隐私信息。
     *
     * @param int $uid       被查看的用户
     * @param int $viewer_id 观察者用户ID，0 表示游客
     * @return array
     */
    public function profileData($uid, $viewer_id = 0)
    {
        $uid = intval($uid);
        $viewer_id = intval($viewer_id);
        if ($uid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        // 字段白名单：绝不 select 隐私列
        $user = Db::name('user')
            ->field('user_id,user_name,user_nick_name,user_reg_time,user_status')
            ->where('user_id', $uid)
            ->find();
        if (empty($user)) {
            return ['code' => 1003, 'msg' => lang('follow/user_not_found')];
        }
        if (intval($user['user_status']) !== 1) {
            return ['code' => 1007, 'msg' => lang('follow/user_disabled')];
        }

        $is_self = ($viewer_id > 0 && $viewer_id === $uid) ? 1 : 0;
        $following = 0;
        $mutual = 0;
        if ($viewer_id > 0 && !$is_self) {
            $following = $this->isFollowing($viewer_id, $uid) ? 1 : 0;
            $mutual = $this->isMutual($viewer_id, $uid) ? 1 : 0;
        }

        // 与好友动态同一套类型白名单；userData 内部已按同一条件统计过 total，
        // 直接复用避免重复 COUNT，也让白名单只有一个出处
        $recent = model('Dynamics')->userData($uid, 1, 3);
        $dynamics_count = isset($recent['total']) ? (int)$recent['total'] : 0;

        return [
            'code' => 1,
            'msg' => 'ok',
            'info' => [
                'user_id' => (int)$user['user_id'],
                'display_name' => $user['user_nick_name'] ? $user['user_nick_name'] : $user['user_name'],
                'user_portrait' => mac_get_user_portrait($uid),
                'user_reg_time' => (int)$user['user_reg_time'],
                'following_count' => $this->followingCount($uid),
                'fans_count' => $this->fansCount($uid),
                'dynamics_count' => $dynamics_count,
                'following' => $following,
                'mutual' => $mutual,
                'is_self' => $is_self,
                'is_login' => $viewer_id > 0 ? 1 : 0,
                'recent_dynamics' => isset($recent['list']) ? $recent['list'] : [],
            ],
        ];
    }

    private function _notifyFollow($from_uid, $to_uid)
    {
        try {
            $from_user = Db::name('user')->where('user_id', $from_uid)->field('user_name,user_nick_name')->find();
            $nick = !empty($from_user['user_nick_name']) ? $from_user['user_nick_name'] : $from_user['user_name'];
            model('Notify')->send($to_uid, 'follow', lang('follow/notify_title'), lang('follow/notify_content', [$nick]), '');
        } catch (\Exception $e) {
        }
    }

    private function _fireBroadcast($user_id, $follow_uid, $mutual)
    {
        if (function_exists('hook')) {
            hook('social_broadcast', [
                'kind'      => 'follow',
                'follow_uid' => $follow_uid,
                'data'      => [
                    'from_uid' => (int)$user_id,
                    'to_uid'   => (int)$follow_uid,
                    'mutual'   => (int)$mutual,
                ],
            ]);
        }
    }
}