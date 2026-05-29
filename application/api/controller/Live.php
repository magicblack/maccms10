<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Live extends Base
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
     * 获取直播分类列表
     */
    public function get_category(Request $request)
    {
        $cate_list = model('Live')->categoryList(['cate_status' => 1]);

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'list' => $cate_list,
        ]);
    }

    /**
     * 获取直播频道列表
     * 支持按分类筛选、分页
     */
    public function get_list(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_validate', [$validate->getError()]),
            ]);
        }

        $where = [];
        $where['live_status'] = ['eq', 1];

        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit  = isset($param['limit']) ? (int)$param['limit'] : 20;

        if (isset($param['cate_id']) && (int)$param['cate_id'] > 0) {
            $where['cate_id'] = ['eq', (int)$param['cate_id']];
        }

        if (isset($param['name']) && strlen($param['name']) > 0) {
            $where['live_name'] = ['like', '%' . htmlspecialchars($param['name']) . '%'];
        }

        if (isset($param['level']) && (int)$param['level'] > 0) {
            $where['live_level'] = ['egt', (int)$param['level']];
        }

        // 排序
        $orderby = isset($param['orderby']) ? $param['orderby'] : 'sort';
        $orderMap = [
            'sort'      => 'live_sort desc, live_id asc',
            'hits'      => 'live_hits desc',
            'hits_day'  => 'live_hits_day desc',
            'hits_week' => 'live_hits_week desc',
            'time'      => 'live_time desc',
            'id'        => 'live_id desc',
        ];
        $order = isset($orderMap[$orderby]) ? $orderMap[$orderby] : 'live_sort desc, live_id asc';

        $res = model('Live')->listData($where, $order, 1, $limit, $offset);

        // 附加分类信息和解析播放地址
        $cateMap = [];
        $cateRows = model('Live')->categoryList(['cate_status' => 1]);
        foreach ($cateRows as $c) {
            $cateMap[$c['cate_id']] = $c;
        }

        $liveModel = model('Live');
        foreach ($res['list'] as &$item) {
            $item['cate_name'] = isset($cateMap[$item['cate_id']]) ? $cateMap[$item['cate_id']]['cate_name'] : '';
            $item['live_url_list'] = $liveModel->parseUrlList($item['live_url'] ?? '');
            unset($item['live_url']);
        }
        unset($item);

        return json([
            'code'      => 1,
            'msg'       => lang('obtain_ok'),
            'page'      => $res['page'],
            'pagecount' => $res['pagecount'],
            'limit'     => $res['limit'],
            'total'     => $res['total'],
            'list'      => $res['list'],
        ]);
    }

    /**
     * 获取直播频道详情
     */
    public function get_detail(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => lang('api/param_validate', [$validate->getError()]),
            ]);
        }

        $live_id = (int)$param['live_id'];
        $where = ['live_id' => ['eq', $live_id], 'live_status' => ['eq', 1]];
        $res = model('Live')->infoData($where);

        if ($res['code'] > 1) {
            return json($res);
        }

        $info = $res['info'];
        $info['live_url_list'] = model('Live')->parseUrlList($info['live_url'] ?? '');
        unset($info['live_url']);

        // 附加分类信息
        if (!empty($info['cate_id'])) {
            $cate = Db::name('live_category')->where('cate_id', $info['cate_id'])->find();
            $info['cate_name'] = $cate ? $cate['cate_name'] : '';
        } else {
            $info['cate_name'] = '';
        }

        // 增加点击量
        Db::name('live')->where('live_id', $live_id)->setInc('live_hits');
        Db::name('live')->where('live_id', $live_id)->setInc('live_hits_day');
        Db::name('live')->where('live_id', $live_id)->setInc('live_hits_week');
        Db::name('live')->where('live_id', $live_id)->setInc('live_hits_month');
        Db::name('live')->where('live_id', $live_id)->update(['live_time_hits' => time()]);

        return json([
            'code' => 1,
            'msg'  => lang('obtain_ok'),
            'info' => $info,
        ]);
    }
}
