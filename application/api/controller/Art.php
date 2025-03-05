<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Art extends Base
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

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['art_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['art_time'] = ['<=', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['art_time'] = ['>=', (int)$param['time_start']];
        }

        if (isset($param['letter'])) {
            $where['art_letter'] = $param['letter'];
        }

        if (isset($param['status'])) {
            $where['art_status'] = (int)$param['status'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['art_name'] = ['like', '%' . format_sql_string($param['name']) . '%'];
        }

        if (isset($param['sub']) && strlen($param['sub']) > 0) {
            $where['art_sub'] = ['like', '%' . format_sql_string($param['sub']) . '%'];
        }

        if (isset($param['blurb']) && strlen($param['blurb']) > 0) {
            $where['art_blurb'] = ['like', '%' . format_sql_string($param['blurb']) . '%'];
        }

        if (isset($param['title']) && strlen($param['title']) > 0) {
            $where['art_title'] = ['like', '%' . format_sql_string($param['title']) . '%'];
        }

        if (isset($param['content']) && strlen($param['content']) > 0) {
            $where['art_content'] = ['like', '%' . format_sql_string($param['content']) . '%'];
        }

        // 数据获取
        $total = model('Art')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "art_time DESC";
            $field = 'art_id,art_name,art_sub,art_en,art_blurb,art_time,art_time_add';
            if (strlen($param['orderby']) > 0) {
                $order = 'art_' . $param['orderby'] . " DESC";
            }
            $list = model('Art')->getListByCond($offset, $limit, $where, $order, $field, []);
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

    /**
     * 视频文章详情
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
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $res = Db::table('mac_art')->where(['art_id' => $param['art_id']])->select();

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res
        ]);
    }

}