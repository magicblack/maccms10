<?php
namespace app\common\model;
use think\Db;
use think\Log;

class Notify extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'notify';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $total = Db::name('Notify')->where($where)->count();
        $list = Db::name('Notify')->where($where)->order($order)->limit($limit_str)->select();

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Notify');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        $data['notify_time'] = time();
        if(!empty($data['notify_id'])){
            $where=[];
            $where['notify_id'] = ['eq',$data['notify_id']];
            $res = $this->allowField(true)->where($where)->update($data);
            $notify_id = intval($data['notify_id']);
        }
        else{
            $res = $this->allowField(true)->insertGetId($data);
            $notify_id = intval($res);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok'),'info'=>['notify_id'=>$notify_id]];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function send($user_id, $type, $title, $content, $link = '')
    {
        $data = [];
        $data['user_id'] = intval($user_id);
        $data['notify_type'] = $type;
        $data['notify_title'] = $title;
        $data['notify_content'] = $content;
        $data['notify_read'] = 0;
        $data['notify_link'] = $link;
        return $this->saveData($data);
    }

    /**
     * 给被回复的评论作者发通知。已对回复者与原作者同人、原作者未登录等情况做静默跳过。
     * 通知失败不影响评论主流程，仅写日志。
     */
    public function sendReplyNotify($parentId, $replyUserId)
    {
        $parentId = intval($parentId);
        $replyUserId = intval($replyUserId);
        if ($parentId < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $parent = Db::name('Comment')->where('comment_id', $parentId)->find();
        if (empty($parent)) {
            return ['code' => 1002, 'msg' => lang('obtain_err')];
        }
        $parentUserId = intval($parent['user_id']);
        if ($parentUserId < 1 || $parentUserId === $replyUserId) {
            return ['code' => 1, 'msg' => lang('obtain_ok')];
        }
        try {
            return $this->send(
                $parentUserId,
                'reply',
                lang('notify/reply_title'),
                lang('notify/reply_content'),
                ''
            );
        } catch (\Exception $e) {
            Log::error('Notify::sendReplyNotify parent=' . $parentId . ' err=' . $e->getMessage());
            return ['code' => 1002, 'msg' => $e->getMessage()];
        }
    }

    public function broadcast($type, $title, $content, $link = '')
    {
        $data = [];
        $data['user_id'] = 0;
        $data['notify_type'] = $type;
        $data['notify_title'] = $title;
        $data['notify_content'] = $content;
        $data['notify_read'] = 0;
        $data['notify_link'] = $link;
        return $this->saveData($data);
    }

    public function countUnread($user_id)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return 0;
        }
        $direct = Db::name('Notify')->where('user_id', $user_id)->where('notify_read', 0)->count();

        $annReadIds = Db::name('NotifyRead')->where('user_id', $user_id)->column('notify_id');
        $annQuery = Db::name('Notify')->where('user_id', 0)->where('notify_read', 0);
        if (!empty($annReadIds)) {
            $annQuery->where('notify_id', 'not in', $annReadIds);
        }
        $ann = $annQuery->count();

        return intval($direct) + intval($ann);
    }

    public function listForUser($user_id, $type = '', $page = 1, $limit = 20)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit > 0 ? (int)$limit : 20;
        $limit = min(100, $limit);
        $offset = $limit * ($page - 1);

        $where = [];
        $where['user_id'] = ['in', [0, $user_id]];
        if (in_array($type, ['system', 'order', 'vip', 'activity', 'reply', 'announce'], true)) {
            $where['notify_type'] = ['eq', $type];
        }

        $total = Db::name('Notify')->where($where)->count();
        $list = Db::name('Notify')
            ->where($where)
            ->order('notify_id desc')
            ->limit($offset . ',' . $limit)
            ->select();

        $annIds = [];
        foreach ($list as $row) {
            if (intval($row['user_id']) === 0) {
                $annIds[] = intval($row['notify_id']);
            }
        }
        if (!empty($annIds)) {
            $readIds = Db::name('NotifyRead')
                ->where('user_id', $user_id)
                ->where('notify_id', 'in', $annIds)
                ->column('notify_id');
            $readMap = [];
            foreach ($readIds as $readId) {
                $readMap[intval($readId)] = 1;
            }
            foreach ($list as $k => $row) {
                if (intval($row['user_id']) === 0 && isset($readMap[intval($row['notify_id'])])) {
                    $list[$k]['notify_read'] = 1;
                }
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function markRead($user_id, $notify_ids)
    {
        $user_id = intval($user_id);
        if ($user_id < 1 || empty($notify_ids)) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $ids = [];
        foreach ((array)$notify_ids as $v) {
            $v = intval($v);
            if ($v > 0) {
                $ids[$v] = $v;
            }
        }
        if (empty($ids)) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $idList = array_values($ids);

        $directRes = $this->where('user_id', $user_id)
            ->where('notify_id', 'in', $idList)
            ->where('notify_read', 0)
            ->update(['notify_read' => 1]);
        if ($directRes === false) {
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        $now = time();
        $annIds = Db::name('Notify')->where('user_id', 0)
            ->where('notify_id', 'in', $idList)
            ->column('notify_id');
        if (!empty($annIds)) {
            $alreadyRead = Db::name('NotifyRead')
                ->where('user_id', $user_id)
                ->where('notify_id', 'in', $annIds)
                ->column('notify_id');
            $toInsert = array_diff($annIds, $alreadyRead);
            if (!empty($toInsert)) {
                $rows = [];
                foreach ($toInsert as $annId) {
                    $rows[] = ['user_id' => $user_id, 'notify_id' => $annId, 'read_time' => $now];
                }
                Db::name('NotifyRead')->insertAll($rows);
            }
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function markAllRead($user_id)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $directRes = $this->where('user_id', $user_id)->where('notify_read', 0)->update(['notify_read' => 1]);
        if ($directRes === false) {
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }

        $alreadyRead = Db::name('NotifyRead')->where('user_id', $user_id)->column('notify_id');
        $annQuery = Db::name('Notify')->where('user_id', 0)->where('notify_read', 0);
        if (!empty($alreadyRead)) {
            $annQuery->where('notify_id', 'not in', $alreadyRead);
        }
        $annIds = $annQuery->column('notify_id');
        if (!empty($annIds)) {
            $now = time();
            $rows = [];
            foreach ($annIds as $annId) {
                $rows[] = ['user_id' => $user_id, 'notify_id' => $annId, 'read_time' => $now];
            }
            Db::name('NotifyRead')->insertAll($rows);
        }

        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function deleteForUser($user_id, $notify_ids = [], $all = false)
    {
        $user_id = intval($user_id);
        if ($user_id < 1) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $where = [];
        $where['user_id'] = ['eq', $user_id];
        if (!$all) {
            if (empty($notify_ids)) {
                return ['code'=>1001,'msg'=>lang('param_err')];
            }
            $ids = [];
            foreach ((array)$notify_ids as $v) {
                $v = intval($v);
                if ($v > 0) {
                    $ids[$v] = $v;
                }
            }
            if (empty($ids)) {
                return ['code'=>1001,'msg'=>lang('param_err')];
            }
            $where['notify_id'] = ['in', array_values($ids)];
        }

        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function sendVipExpirationReminders($days = 3, $limit = 500)
    {
        $days = max(1, intval($days));
        $limit = max(1, intval($limit));
        $now = time();
        $windowEnd = $now + $days * 86400;

        $users = Db::name('User')
            ->where('user_end_time', '>', $now)
            ->where('user_end_time', '<=', $windowEnd)
            ->order('user_end_time asc')
            ->limit($limit)
            ->field('user_id, user_end_time, group_id')
            ->select();

        $sent = 0;
        foreach ($users as $u) {
            // group_id 为逗号分隔的会员组串，与系统其它处一致按最大组号判定是否 VIP（>2）
            $maxGroup = 0;
            foreach (explode(',', (string)$u['group_id']) as $gid) {
                $gid = intval($gid);
                if ($gid > $maxGroup) {
                    $maxGroup = $gid;
                }
            }
            if ($maxGroup <= 2) {
                continue;
            }
            $uid = intval($u['user_id']);
            $endTime = intval($u['user_end_time']);
            $title = lang('notify/vip_expire_title');
            $content = lang('notify/vip_expire_content', [date('Y-m-d H:i', $endTime)]);

            $exists = Db::name('Notify')
                ->where('user_id', $uid)
                ->where('notify_type', 'vip')
                ->where('notify_title', $title)
                ->where('notify_content', $content)
                ->find();
            if (!empty($exists)) {
                continue;
            }

            try {
                $res = $this->send($uid, 'vip', $title, $content, '/user/upgrade');
                if (isset($res['code']) && intval($res['code']) === 1) {
                    $sent++;
                }
            } catch (\Exception $e) {
                Log::error('Notify::sendVipExpirationReminders uid=' . $uid . ' err=' . $e->getMessage());
            }
        }

        return ['code'=>1,'msg'=>lang('save_ok'),'info'=>['sent'=>$sent]];
    }

}
