<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Comment extends Base
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
        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;
        // 查询条件组装
        $where = [];

        if (isset($param['rid'])) {
            $where['comment_rid'] = (int)$param['rid'];
        }

        // 数据获取
        $total = model('Comment')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "comment_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'comment_' . $param['orderby'] . " DESC";
            }
            $field = '*';
            $list = model('Comment')->getListByCond($offset, $limit, $where, $order, $field, []);
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