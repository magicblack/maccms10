<?php
namespace app\index\controller;
use think\Db;
use think\Request;
class Type extends Base
{
    /**
     *  获取分类树
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
        // 查询第一级
        $where['type_pid'] = 0;

        if (isset($param['type_id'])) {
            $where['type_id'] = (int)$param['type_id'];
        }

        // 数据获取
        $total = model('Type')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "type_sort DESC";
            $field = '*';
            $list = model('Type')->getListByCond(0, PHP_INT_MAX, $where, $order, $field, []);
            foreach ($list as $index => $item) {
                $child_total = Db::table('mac_type')->where(['type_pid' => $item['type_id']])->count();
                if ($child_total > 0) {
                    $child = Db::table('mac_type')->where(['type_pid' => $item['type_id']])->order('type_sort ASC')->select();
                    $list[$index]['child'] = $child;
                }
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => $total,
                'rows'   => $list,
            ],
        ]);
    }
}
