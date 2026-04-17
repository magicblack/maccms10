<?php

namespace app\api\controller;

use think\Db;
use think\Request;

/**
 * Role 角色前台 JSON 接口
 *
 * 提供 role/get_list 和 role/get_detail 接口，
 * 对应前台模板中 {maccms:role} 标签的数据需求。
 */
class Role extends Base
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
     * 获取角色列表
     * GET /api.php/role/get_list
     *
     * 参数说明:
     *   offset  - 可选，偏移量，默认0
     *   limit   - 可选，每页条数，默认20
     *   rid     - 可选，关联视频ID（role_rid）
     *   name    - 可选，角色名称模糊搜索
     *   letter  - 可选，首字母筛选
     *   level   - 可选，推荐等级筛选，多个用逗号分隔
     *   actor   - 可选，配音/演员筛选
     *   orderby - 可选，排序字段，默认 time
     *             可选: id,time,time_add,hits,hits_day,hits_week,hits_month,score,up,down,level
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_list(Request $request)
    {
        $param = $request->param();

        // 参数校验
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit  = isset($param['limit']) ? (int)$param['limit'] : 20;

        // 查询条件组装
        $where = [];
        $where['role_status'] = ['eq', 1];

        if (isset($param['rid']) && strlen($param['rid']) > 0) {
            $where['role_rid'] = (int)$param['rid'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['role_name|role_en'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        if (isset($param['letter']) && strlen($param['letter']) > 0) {
            $where['role_letter'] = strtoupper(substr($this->format_sql_string($param['letter']), 0, 1));
        }

        if (isset($param['level']) && strlen($param['level']) > 0) {
            $where['role_level'] = ['in', $this->format_sql_string($param['level'])];
        }

        if (isset($param['actor']) && strlen($param['actor']) > 0) {
            $where['role_actor'] = ['like', '%' . $this->format_sql_string($param['actor']) . '%'];
        }

        // 数据获取
        $total = model('Role')->countData($where);
        $list = [];

        if ($total > 0) {
            // 排序
            $by = 'time';
            if (!empty($param['orderby'])) {
                $allowBy = ['id', 'time', 'time_add', 'hits', 'hits_day', 'hits_week', 'hits_month', 'score', 'up', 'down', 'level'];
                if (in_array($param['orderby'], $allowBy)) {
                    $by = $param['orderby'];
                }
            }
            $order = 'role_' . $by . ' DESC';

            $field = 'role_id,role_name,role_en,role_pic,role_actor,role_remarks,role_content,role_rid,role_letter,role_level,role_hits,role_hits_day,role_hits_week,role_hits_month,role_score,role_time,role_time_add';

            $list = Db::name('role')
                ->field($field)
                ->where($where)
                ->order($order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as &$v) {
                $v['role_pic'] = mac_url_img($v['role_pic'] ?? '');
                $v['role_link'] = mac_url_role_detail($v);
            }
            unset($v);

            $rids = [];
            foreach ($list as $row) {
                if (!empty($row['role_rid'])) {
                    $rids[] = (int)$row['role_rid'];
                }
            }
            $rids = array_unique(array_filter($rids));
            $vodTitleMap = [];
            if (!empty($rids)) {
                $vodTitleMap = Db::name('vod')->where('vod_id', 'in', $rids)->column('vod_name', 'vod_id');
            }
            foreach ($list as &$v) {
                $rid = (int)($v['role_rid'] ?? 0);
                $v['vod_name'] = ($rid && isset($vodTitleMap[$rid])) ? $vodTitleMap[$rid] : '';
            }
            unset($v);
        }

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
     * 获取角色详情
     * GET /api.php/role/get_detail
     *
     * 参数说明:
     *   role_id - 必须，角色ID
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_detail(Request $request)
    {
        $param = $request->param();

        // 参数校验
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $roleId = intval($param['role_id']);

        $res = Db::table('mac_role')->where(['role_id' => $roleId])->find();
        if (empty($res)) {
            return json(['code' => 1001, 'msg' => '数据不存在']);
        }

        // 处理图片 URL
        $res['role_pic'] = mac_url_img($res['role_pic'] ?? '');
        $res['role_link'] = mac_url_role_detail($res);

        // 关联视频信息
        $res['vod_info'] = null;
        if (!empty($res['role_rid'])) {
            $vodInfo = Db::name('vod')
                ->field('vod_id,vod_name,vod_sub,vod_pic,vod_remarks,vod_score,vod_year,vod_area,vod_class,type_id,type_id_1')
                ->where('vod_id', intval($res['role_rid']))
                ->find();
            if ($vodInfo) {
                $vodInfo['vod_pic'] = mac_url_img($vodInfo['vod_pic'] ?? '');
                $vodInfo['vod_link'] = mac_url_vod_detail($vodInfo);
                $res['vod_info'] = $vodInfo;
            }
        }

        // 清理 HTML 标签
        if (!empty($res['role_content'])) {
            $res['role_content'] = str_replace('mac:', $GLOBALS['config']['upload']['protocol'] . ':', $res['role_content']);
        }

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res,
        ]);
    }

    /**
     * 获取推荐角色
     * GET /api.php/role/get_recommend
     *
     * 参数说明:
     *   rid   - 可选，关联视频ID
     *   num   - 可选，数量，默认8
     *   by    - 可选，排序字段，默认 time
     *   level - 可选，推荐等级
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_recommend(Request $request)
    {
        $param = $request->param();
        $num   = isset($param['num']) ? (int)$param['num'] : 8;
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;
        $by    = isset($param['by']) ? trim($param['by']) : 'time';
        $rid   = isset($param['rid']) ? (int)$param['rid'] : 0;
        $level = isset($param['level']) ? trim($param['level']) : '';

        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'time', 'score'];
        if (!in_array($by, $allowBy)) {
            $by = 'time';
        }

        $where = [];
        $where['role_status'] = ['eq', 1];

        if ($rid > 0) {
            $where['role_rid'] = $rid;
        }
        if (!empty($level)) {
            $where['role_level'] = ['in', $level];
        }

        $list = Db::name('role')
            ->field('role_id,role_name,role_en,role_pic,role_actor,role_remarks,role_rid,role_hits,role_hits_month,role_score,role_time')
            ->where($where)
            ->order('role_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['role_pic'] = mac_url_img($v['role_pic'] ?? '');
            $v['role_link'] = mac_url_role_detail($v);
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
}
