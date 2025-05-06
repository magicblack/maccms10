<?php

namespace app\api\controller;

use think\Controller;
use think\Cache;
use think\Db;
use think\Request;
use think\Validate;

class Vod extends Base
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
     *  获取视频列表
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
        if (isset($param['id'])) {
            $where['vod_id'] = (int)$param['id'];
        }
//        if (isset($param['type_id_1'])) {
//            $where['type_id_1'] = (int)$param['type_id_1'];
//        }
        if (!empty($param['vod_letter'])) {
            $where['vod_letter'] = $param['vod_letter'];
        }
        if (isset($param['vod_tag']) && strlen($param['vod_tag']) > 0) {
            $where['vod_tag'] = ['like', '%' . $this->format_sql_string($param['vod_tag']) . '%'];
        }
        if (isset($param['vod_name']) && strlen($param['vod_name']) > 0) {
            $where['vod_name'] = ['like', '%' . $this->format_sql_string($param['vod_name']) . '%'];
        }
        if (isset($param['vod_blurb']) && strlen($param['vod_blurb']) > 0) {
            $where['vod_blurb'] = ['like', '%' . $this->format_sql_string($param['vod_blurb']) . '%'];
        }
        if (isset($param['vod_class']) && strlen($param['vod_class']) > 0) {
            $where['vod_class'] = ['like', '%' . $this->format_sql_string($param['vod_class']) . '%'];
        }
        if (isset($param['vod_area']) && strlen($param['vod_area']) > 0) {
            $where['vod_area'] = $this->format_sql_string($param['vod_area']);
        }
        if (isset($param['vod_year']) && strlen($param['vod_year']) > 0) {
            $where['vod_year'] = $this->format_sql_string($param['vod_year']);
        }
        // 数据获取
        $total = model('Vod')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "vod_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'vod_' . $param['orderby'] . " DESC";
            }
            $field = 'vod_id,vod_name,vod_actor,vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_time,vod_remarks,vod_score,vod_area,vod_year';
//            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field, []);
            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field);
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
     * 视频详细信息
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

        $res = Db::table('mac_vod')->where(['vod_id' => $param['vod_id']])->select();

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res
        ]);
    }

    /**
     * 获取视频的年份
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_year(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_year')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_year'])){
                array_push($return,$item['vod_year']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }

    /**
     * 获取该视频类型名称
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_class(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_class')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_class'])){
                array_push($return,$item['vod_class']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }

    /**
     * 获取该视频类型的地区
     *
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_area(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $result = Db::table('mac_vod')->distinct(true)->field('vod_area')->where(['type_id_1' => $param['type_id_1']])->select();
        $return = [];
        foreach ($result as $index => $item) {
            if (!empty($item['vod_area'])){
                array_push($return,$item['vod_area']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($return),
                'rows'   => $return,
            ],
        ]);
    }
}