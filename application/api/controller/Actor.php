<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Actor extends Base
{
    use PublicApi;
    public function __construct()
    {
        parent::__construct();
        $this->check_config();

    }

    public function index()
    {

    }

    /**
     *  获取列表
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        // 参数校验
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg' => '参数错误: ' . $validate->getError(),
            ]);
        }
        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;
        // 查询条件组装
        $where = [];

        if (isset($param['type_id'])) {
            $where['type_id'] = (int)$param['type_id'];
        }

        if (isset($param['sex'])) {
            $where['actor_sex'] = $param['sex'];
        }

        if (isset($param['area']) && strlen($param['area']) > 0) {
            $where['actor_area'] = ['like', '%' . format_sql_string($param['area']) . '%'];
        }

        if (isset($param['letter']) && strlen($param['letter']) > 0) {
            $where['actor_letter'] = ['like', '%' . format_sql_string($param['letter']) . '%'];
        }

        if (isset($param['level']) && strlen($param['level']) > 0) {
            $where['actor_level'] = ['like', '%' . format_sql_string($param['level']) . '%'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['actor_name'] = ['like', '%' . format_sql_string($param['name']) . '%'];
        }

        if (isset($param['blood']) && strlen($param['blood']) > 0) {
            $where['actor_blood'] = ['like', '%' . format_sql_string($param['blood']) . '%'];
        }

        if (isset($param['starsign']) && strlen($param['starsign']) > 0) {
            $where['actor_starsign'] = ['like', '%' . format_sql_string($param['starsign']) . '%'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['actor_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        } elseif (isset($param['time_end'])) {
            $where['actor_time'] = ['<', (int)$param['time_end']];
        } elseif (isset($param['time_start'])) {
            $where['actor_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Actor')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "actor_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'actor_' . $param['orderby'] . " DESC";
            }
            $field = 'actor_id,actor_name,actor_en,actor_alias,actor_sex,actor_hits_month,actor_hits_week,actor_hits_day,actor_time';
            $list = model('Actor')->getListByCond($offset, $limit, $where, $order, $field, []);
        }
        // 返回
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'info' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'rows' => $list,
            ],
        ]);
    }

    /**
     * 视频演员详情
     *
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_detail(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg' => '参数错误: ' . $validate->getError(),
            ]);
        }

        $res = Db::table('mac_actor')->where(['actor_id' => $param['actor_id']])->select();

        // 返回
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'info' => $res
        ]);
    }
}