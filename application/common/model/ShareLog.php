<?php
namespace app\common\model;
use think\Db;

class ShareLog extends Base {
    protected $name = 'share_log';
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
        $list = Db::name('share_log')->field($field)->where($where)->order($order)->limit($limit_str)->select();

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
        $mid = intval($data['share_mid']);
        $rid = intval($data['share_rid']);
        $platform = trim($data['share_platform']);
        if ($user_id < 1 || $mid < 1 || $rid < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        if (empty($platform)) {
            return ['code' => 1002, 'msg' => lang('share/platform_required')];
        }
        if (!in_array($mid, [1, 2, 3, 8, 12])) {
            return ['code' => 1003, 'msg' => lang('param_err')];
        }
        $validate = new \app\common\validate\ShareLog();
        if (!$validate->scene('save')->check(['share_mid' => $mid, 'share_rid' => $rid, 'share_platform' => $platform])) {
            return ['code' => 1001, 'msg' => $validate->getError()];
        }
        $content_exists = $this->_checkContentExists($mid, $rid);
        if (!$content_exists) {
            return ['code' => 1004, 'msg' => lang('share/content_not_found')];
        }

        $data['share_time'] = time();
        $data['share_ip'] = mac_get_ip_long();
        $data['share_platform'] = mac_filter_xss($platform);
        $data['share_day'] = (int)date('Ymd', $data['share_time']);

        // 按(用户,内容,当天)去重，防刷爆 vod_share_count 与 share_log 表。
        // 权威保证是表上的 uk_user_content_day 唯一键，缓存只是省一次 DB 往返的快路径：
        // Cache::has + Cache::set 之间有窗口，并发请求会同时穿过，此时靠唯一键兜底。
        $dedup_key = 'share_dedup_' . $user_id . '_' . $mid . '_' . $rid;
        $today_key = $dedup_key . '_' . date('Ymd', $data['share_time']);
        if (\think\Cache::has($today_key)) {
            return ['code' => 1, 'msg' => lang('share/ok')];
        }

        Db::startTrans();
        try {
            $share_id = $this->allowField(true)->insertGetId($data);
            if (false === $share_id) {
                Db::rollback();
                \think\Log::error('[social] ShareLog::saveData ' . $this->getError());
                return ['code' => 1005, 'msg' => lang('save_err')];
            }
            if ($mid == 1) {
                Db::name('vod')->where('vod_id', $rid)->setInc('vod_share_count', 1);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            // 写入失败后回查一次：当天该用户对该内容已有记录 = 撞了 uk_user_content_day，
            // 与缓存命中同义，幂等返回成功（不递增计数、不写动态）。
            // 这里不靠匹配异常文案判断错误类型：TP5 把 SQLSTATE 收进 getData()，
            // getCode() 返回的是框架自己的 10501，仅凭 message 里出现 "1062"
            // 判定重复会把回显了该数值的其它错误误判成去重，静默吞掉真实失败。
            $dup = $this->where([
                'user_id'   => $user_id,
                'share_mid' => $mid,
                'share_rid' => $rid,
                'share_day' => $data['share_day'],
            ])->find();
            if (!empty($dup)) {
                \think\Cache::set($today_key, 1, 86400 + 3600);
                return ['code' => 1, 'msg' => lang('share/ok')];
            }
            \think\Log::error('[social] ShareLog::saveData ' . $e->getMessage());
            return ['code' => 1005, 'msg' => lang('save_err')];
        }

        // 计数自增成功后标记当日去重，TTL 略大于一天
        \think\Cache::set($today_key, 1, 86400 + 3600);

        if (function_exists('hook')) {
            hook('social_broadcast', [
                'kind' => 'share',
                'data' => [
                    'share_id' => (int)$share_id,
                    'user_id'  => $user_id,
                    'share_mid' => $mid,
                    'share_rid' => $rid,
                    'platform' => $platform,
                    'time'     => $data['share_time'],
                ],
            ]);
        }

        // 写动态
        try {
            $content_name = '';
            $table_map = [1 => 'vod', 2 => 'art', 3 => 'topic', 8 => 'actor', 12 => 'manga'];
            if (isset($table_map[$mid])) {
                $tbl = $table_map[$mid];
                $row = Db::name($tbl)->where($tbl . '_id', $rid)->field($tbl . '_name')->find();
                if ($row) {
                    $content_name = $row[$tbl . '_name'];
                }
            }
            model('Dynamics')->saveData([
                'user_id'       => $user_id,
                'dynamics_type' => 'share',
                'dyn_mid'       => $mid,
                'dyn_rid'       => $rid,
                'dyn_text'      => $content_name . ' [' . $platform . ']',
            ]);
        } catch (\Exception $e) {
        }

        return ['code' => 1, 'msg' => lang('share/ok')];
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            \think\Log::error('[social] ShareLog::delData ' . $this->getError());
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
            \think\Log::error('[social] ShareLog::fieldData ' . $this->getError());
            return ['code' => 1001, 'msg' => lang('set_err')];
        }
        return ['code' => 1, 'msg' => lang('set_ok')];
    }

    private function _checkContentExists($mid, $rid)
    {
        $rid = intval($rid);
        $table_map = [1 => 'vod', 2 => 'art', 3 => 'topic', 8 => 'actor', 12 => 'manga'];
        if (!isset($table_map[$mid])) {
            return false;
        }
        $table = $table_map[$mid];
        $pk = $table . '_id';
        $row = Db::name($table)->where($pk, $rid)->find();
        return !empty($row);
    }
}