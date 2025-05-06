<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Link extends Base
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
            $where['link_id'] = (int)$param['id'];
        }

        if (isset($param['type'])) {
            $where['link_type'] = (int)$param['type'];
        }

        if (isset($param['sort'])) {
            $where['link_sort'] = (int)$param['sort'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['link_name'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['link_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['link_time'] = ['<=', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['link_time'] = ['>=', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Link')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "link_time DESC";
            $field = '*';
            if (strlen($param['orderby']) > 0) {
                $order = 'link_' . $param['orderby'] . " DESC";
            }
            $list = model('Link')->getListByCond($offset, $limit, $where, $order, $field, []);
            foreach ($list as &$item) {
                $item['link_name'] = htmlspecialchars($item['link_name'], ENT_QUOTES, 'UTF-8');
                $item['link_logo'] = htmlspecialchars($item['link_logo'], ENT_QUOTES, 'UTF-8');
                $item['link_url'] = htmlspecialchars($item['link_url'], ENT_QUOTES, 'UTF-8');
            }
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