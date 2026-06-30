<?php
namespace app\common\model;

use think\Db;

class MallOrder extends Base
{
    protected $name = 'mall_order';
    protected $createTime = '';
    protected $updateTime = '';
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    public function listData($where, $order, $page = 1, $limit = 20, $start = 0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        $total = $this->alias('mo')->where($where)->count();
        $list = $this->alias('mo')
            ->field('mo.*,u.user_name')
            ->join('__USER__ u', 'mo.user_id = u.user_id', 'left')
            ->where($where)
            ->order($order)
            ->limit($limit_str)
            ->select();
        foreach ($list as &$row) {
            $row['mall_order_delivery_arr'] = $this->decodeJson($row['mall_order_delivery'] ?? '');
            $row['mall_order_snapshot_arr'] = $this->decodeJson($row['mall_order_snapshot'] ?? '');
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
        $info['mall_order_delivery_arr'] = $this->decodeJson($info['mall_order_delivery'] ?? '');
        $info['mall_order_snapshot_arr'] = $this->decodeJson($info['mall_order_snapshot'] ?? '');

        return ['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $info];
    }

    public function exchange($goods_id, $user_id, $quantity = 1)
    {
        $goods_id = intval($goods_id);
        $user_id = intval($user_id);
        $quantity = max(1, intval($quantity));
        if ($goods_id < 1 || $user_id < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $goods = Db::name('MallGoods')->where('mall_goods_id', $goods_id)->find();
        if (empty($goods) || intval($goods['mall_goods_status']) !== 1) {
            return ['code' => 1002, 'msg' => lang('mall/goods_not_available')];
        }
        if (!in_array($goods['mall_goods_type'], ['vip', 'card', 'download_quota'], true)) {
            return ['code' => 1003, 'msg' => lang('mall/type_invalid')];
        }
        if (intval($goods['mall_goods_points']) < 1 || intval($goods['mall_goods_stock']) < $quantity) {
            return ['code' => 1004, 'msg' => lang('mall/stock_not_enough')];
        }

        $total_points = intval($goods['mall_goods_points']) * $quantity;
        $user = Db::name('User')->where('user_id', $user_id)->find();
        if (empty($user)) {
            return ['code' => 1005, 'msg' => lang('api/user_not_found') ?: lang('obtain_err')];
        }
        if (intval($user['user_points']) < $total_points) {
            return ['code' => 1006, 'msg' => lang('mall/points_not_enough')];
        }

        $pointsDeducted = false;
        $stockDeducted = false;
        $plogWritten = false;
        $plog_id = 0;
        $order_id = 0;
        $order = [];
        Db::startTrans();
        try {
            $affected = Db::name('User')
                ->where('user_id', $user_id)
                ->where('user_points', '>=', $total_points)
                ->setDec('user_points', $total_points);
            if ($affected === false || intval($affected) < 1) {
                throw new \Exception(lang('mall/points_not_enough'));
            }
            $pointsDeducted = true;

            $affected = Db::name('MallGoods')
                ->where('mall_goods_id', $goods_id)
                ->where('mall_goods_stock', '>=', $quantity)
                ->setDec('mall_goods_stock', $quantity);
            if ($affected === false || intval($affected) < 1) {
                throw new \Exception(lang('mall/stock_not_enough'));
            }
            $stockDeducted = true;

            $snapshot = [
                'goods_id' => intval($goods['mall_goods_id']),
                'name' => $goods['mall_goods_name'],
                'type' => $goods['mall_goods_type'],
                'points' => intval($goods['mall_goods_points']),
                'quantity' => $quantity,
                'ext' => (new MallGoods())->decodeExt($goods['mall_goods_ext'] ?? ''),
            ];
            $order = [
                'user_id' => $user_id,
                'mall_goods_id' => intval($goods['mall_goods_id']),
                'mall_goods_name' => $goods['mall_goods_name'],
                'mall_goods_type' => $goods['mall_goods_type'],
                'mall_order_points' => $total_points,
                'mall_order_quantity' => $quantity,
                'mall_order_status' => 0,
                'mall_order_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'mall_order_delivery' => '',
                'mall_order_remarks' => '',
                'mall_order_time' => time(),
                'mall_order_complete_time' => 0,
            ];
            $order_id = Db::name('MallOrder')->insertGetId($order);
            if (!$order_id) {
                throw new \Exception(lang('save_err'));
            }

            $plog = [
                'user_id' => $user_id,
                'plog_type' => 12,
                'plog_points' => $total_points,
                'plog_remarks' => lang('mall/plog_remark', [$goods['mall_goods_name'], $quantity]),
            ];
            $plogRes = (new Plog())->saveData($plog);
            if (empty($plogRes['code']) || intval($plogRes['code']) !== 1) {
                throw new \Exception($plogRes['msg'] ?? 'plog');
            }
            $plog_id = intval($plogRes['info']['plog_id'] ?? 0);
            if ($plog_id < 1) {
                throw new \Exception(lang('save_err'));
            }
            $plogWritten = true;

            $delivery = $this->grantBenefit($goods, $user_id, $quantity);
            if ($delivery['code'] > 1) {
                throw new \Exception($delivery['msg']);
            }

            Db::name('MallOrder')->where('mall_order_id', $order_id)->update([
                'mall_order_status' => 1,
                'mall_order_delivery' => json_encode($delivery['info'], JSON_UNESCAPED_UNICODE),
                'mall_order_complete_time' => time(),
            ]);

            Db::commit();

            try {
                $notifyType = ($goods['mall_goods_type'] === 'vip') ? 'vip' : 'order';
                model('Notify')->send($user_id, $notifyType, lang('notify/exchange_ok_title'), lang('notify/exchange_ok_content', [$goods['mall_goods_name']]), '/user/mall_orders');
            } catch (\Exception $e) {
                \think\Log::error('MallOrder exchange notify uid=' . $user_id . ' err=' . $e->getMessage());
            }

            return ['code' => 1, 'msg' => lang('mall/exchange_ok'), 'info' => ['order_id' => $order_id, 'delivery' => $delivery['info']]];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($stockDeducted) {
                Db::name('MallGoods')->where('mall_goods_id', $goods_id)->setInc('mall_goods_stock', $quantity);
            }
            if ($pointsDeducted) {
                Db::name('User')->where('user_id', $user_id)->setInc('user_points', $total_points);
            }
            if ($plogWritten) {
                Db::name('Plog')->where('plog_id', $plog_id)->delete();
            }
            Db::rollback();
            if ($order_id > 0) {
                $failedOrder = [
                    'mall_order_status' => 2,
                    'mall_order_remarks' => $msg,
                    'mall_order_complete_time' => time(),
                ];
                $affected = Db::name('MallOrder')->where('mall_order_id', $order_id)->update($failedOrder);
                if ((intval($affected) < 1) && !empty($order)) {
                    $order['mall_order_id'] = $order_id;
                    $order = array_merge($order, $failedOrder);
                    Db::name('MallOrder')->insert($order);
                }
            }
            return ['code' => 2001, 'msg' => $msg ?: lang('mall/exchange_fail')];
        }
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if ($res === false) {
            return ['code' => 1001, 'msg' => lang('del_err') . '：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => lang('del_ok')];
    }

    public function decodeJson($json)
    {
        $arr = json_decode((string)$json, true);
        return is_array($arr) ? $arr : [];
    }

    private function grantBenefit($goods, $user_id, $quantity)
    {
        $ext = (new MallGoods())->decodeExt($goods['mall_goods_ext'] ?? '');
        if ($goods['mall_goods_type'] === 'vip') {
            $days = intval($ext['days'] ?? 0) * $quantity;
            $group_id = intval($ext['group_id'] ?? 0);
            $res = (new User())->grantVipDays($user_id, $group_id, $days);
            if ($res['code'] > 1) {
                return $res;
            }
            return ['code' => 1, 'msg' => 'ok', 'info' => ['type' => 'vip', 'group_id' => $group_id, 'days' => $days, 'user_end_time' => $res['info']['user_end_time'], 'group_name' => $res['info']['group_name'] ?? '']];
        }
        if ($goods['mall_goods_type'] === 'card') {
            if ($quantity !== 1) {
                return ['code' => 1001, 'msg' => lang('mall/card_quantity_once')];
            }
            return $this->deliverCard($ext, $user_id);
        }
        if ($goods['mall_goods_type'] === 'download_quota') {
            $quota = intval($ext['quota'] ?? 0) * $quantity;
            if ($quota < 1) {
                return ['code' => 1001, 'msg' => lang('mall/download_quota_invalid')];
            }
            $res = Db::name('User')->where('user_id', $user_id)->setInc('user_down_quota', $quota);
            if ($res === false) {
                return ['code' => 1002, 'msg' => lang('mall/grant_fail')];
            }
            return ['code' => 1, 'msg' => 'ok', 'info' => ['type' => 'download_quota', 'quota' => $quota]];
        }
        return ['code' => 1001, 'msg' => lang('mall/type_invalid')];
    }

    private function deliverCard($ext, $user_id)
    {
        $mode = $ext['card_mode'] ?? 'generate';
        if ($mode === 'assign') {
            $where = ['card_use_status' => 0, 'card_sale_status' => 0];
            if (intval($ext['card_points'] ?? 0) > 0) {
                $where['card_points'] = intval($ext['card_points']);
            }
            $card = Db::name('Card')->where($where)->order('card_id asc')->find();
            if (empty($card)) {
                return ['code' => 1001, 'msg' => lang('mall/card_stock_empty')];
            }
            $affected = Db::name('Card')->where([
                'card_id' => $card['card_id'],
                'card_use_status' => 0,
                'card_sale_status' => 0,
            ])->update(['card_sale_status' => 1, 'user_id' => $user_id]);
            if ($affected === false || intval($affected) < 1) {
                return ['code' => 1002, 'msg' => lang('mall/card_stock_empty')];
            }
        } else {
            $card = $this->makeCard($ext, $user_id);
            if (empty($card)) {
                return ['code' => 1003, 'msg' => lang('mall/grant_fail')];
            }
        }

        return [
            'code' => 1,
            'msg' => 'ok',
            'info' => [
                'type' => 'card',
                'card_id' => intval($card['card_id']),
                'card_no' => $card['card_no'],
                'card_pwd' => $card['card_pwd'],
                'card_points' => intval($card['card_points']),
            ],
        ];
    }

    private function makeCard($ext, $user_id)
    {
        $role_no = $ext['role_no'] ?? '';
        $role_pwd = $ext['role_pwd'] ?? '';
        for ($i = 0; $i < 5; $i++) {
            $card_no = mac_get_rndstr(16, $role_no);
            $card_pwd = mac_get_rndstr(8, $role_pwd);
            $data = [
                'card_no' => $card_no,
                'card_pwd' => $card_pwd,
                'card_money' => max(0, intval($ext['card_money'] ?? 0)),
                'card_points' => max(1, intval($ext['card_points'] ?? 0)),
                'card_sale_status' => 1,
                'card_use_status' => 0,
                'user_id' => $user_id,
                'card_add_time' => time(),
                'card_use_time' => 0,
            ];
            $exists = Db::name('Card')->where(['card_no' => $card_no])->find();
            if (!empty($exists)) {
                continue;
            }
            $id = Db::name('Card')->insertGetId($data);
            if ($id) {
                $data['card_id'] = $id;
                return $data;
            }
        }
        return null;
    }
}
