<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Type extends Base
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
                    $child = Db::table('mac_type')->where(['type_pid' => $item['type_id']])->select();
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

    /**
     * 查询首页分类顶部导航栏
     *
     * @return \think\response\Json
     */
    public function get_all_list()
    {
        $list = Db::table('mac_type')->where(['type_pid'=> 0])->column('type_id,type_name,type_en');
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total'  => count($list),
                'rows'   => $list,
            ],
        ]);
    }

    /**
     * 获取导航栏分类（含子分类 + 扩展信息：地区/年代）
     * 首页 Banner 分类导航 + 顶部导航栏使用
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   ids    - 可选，指定分类ID，多个用逗号分隔，如 "26,27,20"
     *   num    - 可选，限制返回数量，默认不限制
     *   mid    - 可选，筛选模型ID (type_mid)
     *   parent - 可选，传1则只返回父级分类(type_pid=0)
     *   link_flag - 可选，type_link 使用的 mac_url_type 第三参：type（默认）| show（列表页）
     */
    public function get_nav_types(Request $request)
    {
        $param = $request->param();
        $ids = isset($param['ids']) ? trim($param['ids']) : '';
        $num = isset($param['num']) ? (int)$param['num'] : 0;
        $mid = isset($param['mid']) ? (int)$param['mid'] : 0;
        $parent = isset($param['parent']) ? (int)$param['parent'] : 0;
        $linkFlag = isset($param['link_flag']) ? trim((string) $param['link_flag']) : 'type';
        if (!in_array($linkFlag, ['type', 'show'], true)) {
            $linkFlag = 'type';
        }

        $where = [];
        if (!empty($ids)) {
            $idArr = array_map('intval', explode(',', $ids));
            $where['type_id'] = ['in', $idArr];
        }
        if ($parent) {
            $where['type_pid'] = 0;
        }
        if ($mid > 0) {
            $where['type_mid'] = $mid;
        }

        $query = Db::table('mac_type')->where($where)->order('type_sort asc');
        if ($num > 0) {
            $query = $query->limit($num);
        }
        $list = $query->select();

        // 为每个分类附加子分类和扩展信息
        foreach ($list as $k => &$v) {
            $v['type_link'] = mac_url_type($v, [], $linkFlag);
            // 解析 type_extend JSON
            if (!empty($v['type_extend'])) {
                $v['type_extend'] = is_string($v['type_extend']) ? json_decode($v['type_extend'], true) : $v['type_extend'];
            } else {
                $v['type_extend'] = [];
            }

            // 获取子分类
            $children = Db::table('mac_type')
                ->where(['type_pid' => $v['type_id']])
                ->order('type_sort asc')
                ->select();

            foreach ($children as &$child) {
                $child['type_link'] = mac_url_type($child, [], $linkFlag);
                if (!empty($child['type_extend'])) {
                    $child['type_extend'] = is_string($child['type_extend']) ? json_decode($child['type_extend'], true) : $child['type_extend'];
                } else {
                    $child['type_extend'] = [];
                }
            }
            unset($child);

            $v['children'] = $children;
        }
        unset($v);

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'total' => count($list),
                'rows'  => $list,
            ],
        ]);
    }

    /**
     * 获取指定分类及其子分类
     * 用于各区块的子分类标签显示
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   type_id - 必须，父分类ID
     *   num     - 可选，子分类数量限制，默认不限制
     */
    public function get_type_with_children(Request $request)
    {
        $param = $request->param();
        if (empty($param['type_id'])) {
            return json(['code' => 1001, 'msg' => '参数错误: type_id 必须']);
        }
        $typeId = (int)$param['type_id'];
        $num = isset($param['num']) ? (int)$param['num'] : 0;

        // 获取父分类
        $parent = Db::table('mac_type')->where(['type_id' => $typeId])->find();
        if (empty($parent)) {
            return json(['code' => 1002, 'msg' => '分类不存在']);
        }
        if (!empty($parent['type_extend'])) {
            $parent['type_extend'] = is_string($parent['type_extend']) ? json_decode($parent['type_extend'], true) : $parent['type_extend'];
        } else {
            $parent['type_extend'] = [];
        }
        $parent['type_link'] = mac_url_type($parent);

        // 获取子分类
        $childQuery = Db::table('mac_type')
            ->where(['type_pid' => $typeId])
            ->order('type_sort asc');
        if ($num > 0) {
            $childQuery = $childQuery->limit($num);
        }
        $children = $childQuery->select();
        foreach ($children as &$child) {
            $child['type_link'] = mac_url_type($child);
            if (!empty($child['type_extend'])) {
                $child['type_extend'] = is_string($child['type_extend']) ? json_decode($child['type_extend'], true) : $child['type_extend'];
            } else {
                $child['type_extend'] = [];
            }
        }
        unset($child);

        $parent['children'] = $children;

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $parent,
        ]);
    }
}
