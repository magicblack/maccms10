<?php

namespace app\api\controller;

use think\Controller;
use think\Cache;
use think\Db;
use think\Request;
use think\Validate;

class Topic extends Base
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

        if (isset($param['time_end']) && isset($param['time_start'])) {
            $where['topic_time'] = ['between', [(int)$param['time_start'], (int)$param['time_end']]];
        }elseif (isset($param['time_end'])) {
            $where['topic_time'] = ['<', (int)$param['time_end']];
        }elseif (isset($param['time_start'])) {
            $where['topic_time'] = ['>', (int)$param['time_start']];
        }

        // 数据获取
        $total = model('Topic')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "topic_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'topic_' . $param['orderby'] . " DESC";
            }
            $field = 'topic_id,topic_name,topic_en,topic_pic_slide,topic_content';
            $list = model('Topic')->getListByCond($offset, $limit, $where, $order, $field, []);
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
     *  获取列表与推荐信息视频文章
     *
     * @param Request $request
     * @return \think\response\Json
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

        $result = Db::table('mac_topic')->where(['topic_id' => $param['topic_id']])->find();

        if ($result)
        {
            $topic_rel_vod = [];
            $topic_rel_art = [];

            if (!empty($result['topic_rel_vod']))
            {
                $topic_rel_vod_arr = explode(',',$result['topic_rel_vod']);
                foreach ($topic_rel_vod_arr as $index => $item) {
                    $vod = Db::table('mac_vod')->where(['vod_id' => $item])->column('vod_id,vod_name,vod_en,vod_pic,vod_actor,vod_director,vod_blurb,vod_content,vod_play_url');
                    if ($vod) {
                        array_push($topic_rel_vod,$vod);
                    }
                }

                $result['topic_rel_vod'] = $topic_rel_vod;
            }

            if (!empty($result['topic_rel_art']))
            {
                $topic_rel_art_arr = explode(',',$result['topic_rel_art']);
                foreach ($topic_rel_art_arr as $index => $item) {
                    $vod = Db::table('mac_art')->where(['art_id' => $item])->column('art_id,type_id,art_name,art_sub,art_en,art_blurb,art_content');
                    if ($vod) {
                        array_push($topic_rel_art,$vod);
                    }
                }

                $result['topic_rel_art'] = $topic_rel_art;
            }
        }

        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $result,
        ]);
    }
}