<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Website extends Base
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

        if (isset($param['type_id'])) {
            $where['type_id'] = (int)$param['type_id'];
        }

        if (isset($param['status'])) {
            $where['website_status'] = (int)$param['status'];
        }

        if (isset($param['level'])) {
            $where['website_level'] = (int)$param['level'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['website_name'] = ['like', '%' . format_sql_string($param['name']) . '%'];
        }

        if (isset($param['sub']) && strlen($param['sub']) > 0) {
            $where['website_sub'] = ['like', '%' . format_sql_string($param['sub']) . '%'];
        }

        if (isset($param['en']) && strlen($param['en']) > 0) {
            $where['website_en'] = ['like', '%' . format_sql_string($param['en']) . '%'];
        }

        if (isset($param['letter']) && strlen($param['letter']) == 1) {
            $where['website_letter'] = $param['letter'];
        }

        if (isset($param['area']) && strlen($param['area']) > 0) {
            $where['website_area'] = ['like', '%' . format_sql_string($param['area']) . '%'];
        }

        if (isset($param['lang']) && strlen($param['lang']) > 0) {
            $where['website_lang'] = ['like', '%' . format_sql_string($param['lang']) . '%'];
        }

        if (isset($param['tag']) && strlen($param['tag']) > 0) {
            $where['website_tag'] = ['like', '%' . format_sql_string($param['tag']) . '%'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['website_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['website_time'] = ['<', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['website_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Website')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "website_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'website_' . $param['orderby'] . " DESC";
            }
            $field = '*';
            $list = model('Website')->getListByCond($offset, $limit, $where, $order, $field, []);
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
     * 查询详情
     *
     * @return \think\response\Json
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

        $res = Db::table('mac_website')->where(['website_id' => $param['website_id']])->select();

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res
        ]);
    }
}