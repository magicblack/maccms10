<?php

namespace app\api\controller;

use think\Request;

class Gbook extends Base
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
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        // 查询条件组装
        $where = [];

        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;

        if (isset($param['id'])) {
            $where['gbook_id'] = (int)$param['id'];
        }

        if (isset($param['rid'])) {
            $where['gbook_rid'] = (int)$param['rid'];
        }

        if (isset($param['user_id'])) {
            $where['user_id'] = (int)$param['user_id'];
        }

        if (isset($param['status'])) {
            $where['gbook_status'] = (int)$param['status'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['gbook_name'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        if (isset($param['content']) && strlen($param['content']) > 0) {
            $where['gbook_content'] = ['like', '%' . $this->format_sql_string($param['content']) . '%'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['gbook_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['gbook_time'] = ['<', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['gbook_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Gbook')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "gbook_time DESC";
            $field = '*';
            if (strlen($param['orderby']) > 0) {
                $order = 'gbook_' . $param['orderby'] . " DESC";
            }
            $list = model('Gbook')->getListByCond($offset, $limit, $where, $order, $field, []);
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'offset' => $offset,
                'limit'  => $limit,
                'total'  => $total,
                'rows'   => $list,
            ],
        ]);
    }
}