<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Actor extends Base
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
                'msg' => '参数错误: ' . $validate->getError(),
            ]);
        }
        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;
        // 查询条件组装（0/1 均展示：采集/默认入库常见 actor_status=0，仅 eq 1 会导致「库里有数据但接口为空」）
        $where = [];
        $where['actor_status'] = ['in', [0, 1]];

        if (isset($param['type_id']) && (int)$param['type_id'] > 0) {
            $where['type_id'] = (int)$param['type_id'];
        }

        if (isset($param['sex'])) {
            $where['actor_sex'] = $param['sex'];
        }

        if (isset($param['area']) && strlen($param['area']) > 0) {
            $where['actor_area'] = ['like', '%' . $this->format_sql_string($param['area']) . '%'];
        }

        if (isset($param['letter']) && strlen($param['letter']) > 0) {
            $where['actor_letter'] = ['like', '%' . $this->format_sql_string($param['letter']) . '%'];
        }

        if (isset($param['level']) && strlen($param['level']) > 0) {
            $where['actor_level'] = ['like', '%' . $this->format_sql_string($param['level']) . '%'];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['actor_name'] = ['like', '%' . $this->format_sql_string($param['name']) . '%'];
        }

        if (isset($param['blood']) && strlen($param['blood']) > 0) {
            $where['actor_blood'] = ['like', '%' . $this->format_sql_string($param['blood']) . '%'];
        }

        if (isset($param['starsign']) && strlen($param['starsign']) > 0) {
            $where['actor_starsign'] = ['like', '%' . $this->format_sql_string($param['starsign']) . '%'];
        }

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['actor_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        } elseif (isset($param['time_end'])) {
            $where['actor_time'] = ['<', (int)$param['time_end']];
        } elseif (isset($param['time_start'])) {
            $where['actor_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Actor')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "actor_time DESC";
            if (!empty($param['orderby'])) {
                $order = 'actor_' . $param['orderby'] . " DESC";
            }
            $field = 'actor_id,actor_name,actor_en,actor_alias,actor_sex,actor_pic,actor_remarks,actor_hits,actor_hits_month,actor_hits_week,actor_hits_day,actor_time,type_id';
            $list = model('Actor')->getListByCond($offset, $limit, $where, $order, $field, false);
            foreach ($list as &$row) {
                $row['actor_pic'] = mac_url_img($row['actor_pic'] ?? '');
                $row['actor_link'] = mac_url_actor_detail($row);
            }
            unset($row);
        }
        // 返回
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'info' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'rows' => $list,
            ],
        ]);
    }

    /**
     * 视频演员详情
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
                'msg' => '参数错误: ' . $validate->getError(),
            ]);
        }

        $res = Db::table('mac_actor')
            ->where('actor_id', (int) $param['actor_id'])
            ->where('actor_status', 'in', [0, 1])
            ->find();
        if (empty($res)) {
            return json(['code' => 1001, 'msg' => '数据不存在']);
        }

        // 处理图片 URL
        $res['actor_pic'] = mac_url_img($res['actor_pic']);
        $res['actor_pic_thumb'] = mac_url_img($res['actor_pic_thumb'] ?? '');
        $res['actor_link'] = mac_url_actor_detail($res);

        // 返回
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'info' => $res
        ]);
    }

    /**
     * 获取推荐明星
     * 对应首页推荐明星区块
     *
     * @param Request $request
     * @return \think\response\Json
     *
     * 参数说明:
     *   ids - 可选，指定明星ID，多个用逗号分隔
     *   num - 可选，数量，默认8
     *   by  - 可选，排序字段，默认 time，可选: hits,hits_day,hits_week,hits_month,time
     */
    public function get_recommend(Request $request)
    {
        $param = $request->param();
        $ids = isset($param['ids']) ? trim($param['ids']) : '';
        $num = isset($param['num']) ? (int)$param['num'] : 8;
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;
        $by = isset($param['by']) ? trim($param['by']) : 'time';

        $allowBy = ['hits', 'hits_day', 'hits_week', 'hits_month', 'time'];
        if (!in_array($by, $allowBy)) {
            $by = 'time';
        }

        $where = [];
        $where['actor_status'] = ['eq', 1];
        if (!empty($ids)) {
            $idArr = array_map('intval', explode(',', $ids));
            $where['actor_id'] = ['in', $idArr];
        }

        $list = Db::table('mac_actor')
            ->field('actor_id,actor_name,actor_pic,actor_sex,actor_area,actor_hits,actor_hits_month,actor_time')
            ->where($where)
            ->order('actor_' . $by . ' desc')
            ->limit($start, $num)
            ->select();

        foreach ($list as &$v) {
            $v['actor_pic'] = mac_url_img($v['actor_pic']);
            $v['actor_link'] = mac_url_actor_detail($v);
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
