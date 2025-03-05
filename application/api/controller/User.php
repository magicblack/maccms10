<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class User extends Base
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
     *  获取用户列表
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

        if (isset($param['id'])) {
            $where['user_id'] = (int)$param['id'];
        }

        if (isset($param['group_id'])) {
            $where['group_id'] = (int)$param['group_id'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['user_reg_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['user_reg_time'] = ['<=', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['user_reg_time'] = ['>=', (int)$param['time_start']];
        }

        if (isset($param['phone']) && strlen($param['phone']) > 0) {
            $where['user_phone'] = ['like', '%' . format_sql_string($param['phone']) . '%'];
        }

        if (isset($param['qq']) && strlen($param['qq']) > 0) {
            $where['user_qq'] = ['like', '%' . format_sql_string($param['qq']) . '%'];
        }

        if (isset($param['email']) && strlen($param['email']) > 0) {
            $where['user_email'] = ['like', '%' . format_sql_string($param['email']) . '%'];
        }

        if (isset($param['nickname']) && strlen($param['nickname']) > 0) {
            $where['user_nickname'] = ['like', '%' . format_sql_string($param['nickname']) . '%'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['user_name'] = ['like', '%' . format_sql_string($param['name']) . '%'];
        }

        // 数据获取
        $total = model('User')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "user_reg_time DESC";
            $field = 'user_id,user_name,user_nick_name,user_phone,user_reg_time';
            if (strlen($param['orderby']) > 0) {
                $order = 'user_' . $param['orderby'] . " DESC";
            }
            $list = model('User')->getListByCond($offset, $limit, $where, $order, $field, []);
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
     * 用户详细信息
     *
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_detail(Request $request)
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

        $result = Db::table('mac_user')->where(['user_id' => $param['id']])->find();

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $result
        ]);

    }
}