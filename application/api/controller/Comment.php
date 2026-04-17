<?php

namespace app\api\controller;

use think\Db;
use think\Request;

class Comment extends Base
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
        $param = array_merge(
            [
                'offset'  => 0,
                'limit'   => 20,
                'orderby' => 'time',
            ],
            $request->param()
        );
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $offset = (int) $param['offset'];
        $limit = (int) $param['limit'];
        $rid = (int) $param['rid'];
        $mid = (int) $param['mid'];
        $orderbyKey = isset($param['orderby']) ? trim((string) $param['orderby']) : 'time';
        if (!in_array($orderbyKey, ['time', 'up', 'down', 'id'], true)) {
            $orderbyKey = 'time';
        }
        $orderField = $orderbyKey === 'id' ? 'comment_id' : 'comment_' . $orderbyKey;
        $order = $orderField . ' DESC, comment_id DESC';

        $where = [
            'comment_status' => 1,
            'comment_pid'    => 0,
            'comment_rid'    => $rid,
            'comment_mid'    => $mid,
        ];

        $total = model('Comment')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            $list = model('Comment')->getListByCond($offset, $limit, $where, $order, '*', []);
            foreach ($list as $k => $v) {
                $list[$k] = $this->commentRowForApi($v, false);
                $where2 = [
                    'comment_pid'    => $v['comment_id'],
                    'comment_status' => 1,
                ];
                $sub = Db::name('Comment')->where($where2)->order($order)->select();
                $subArr = [];
                if ($sub) {
                    foreach ($sub as $row) {
                        $rowArr = is_array($row) ? $row : $row->toArray();
                        $subArr[] = $this->commentRowForApi($rowArr, true);
                    }
                }
                $list[$k]['sub'] = $subArr;
            }
        }

        $page = $limit > 0 ? (int) floor($offset / $limit) + 1 : 1;
        $pagecount = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'offset'    => $offset,
                'limit'     => $limit,
                'total'     => $total,
                'page'      => $page,
                'pagecount' => $pagecount,
                'rows'      => $list,
            ],
        ]);
    }

    /**
     * 单条评论输出给前端（含头像 URL、表情 HTML、时间展示文案）
     *
     * @param array $row
     * @param bool  $isReply
     */
    protected function commentRowForApi(array $row, $isReply)
    {
        $uid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        $row['user_portrait'] = mac_get_user_portrait($uid);
        $raw = isset($row['comment_content']) ? $row['comment_content'] : '';
        $row['comment_content'] = mac_em_replace(mac_restore_htmlfilter($raw));
        $ts = isset($row['comment_time']) ? (int) $row['comment_time'] : 0;
        $row['comment_time_iso'] = $ts > 0 ? date('c', $ts) : '';
        $row['comment_time_title'] = $ts > 0 ? date('Y-m-d H:i:s', $ts) : '';
        $row['comment_time_label'] = $ts > 0
            ? ($isReply ? date('H:i', $ts) : date('Y-m-d H:i:s', $ts))
            : '';
        return $row;
    }

    /**
     * 提交评论
     * api.php/comment/submit (POST)
     * 参数: comment_mid, comment_rid, comment_content, [comment_pid=0]
     */
    public function submit(Request $request)
    {
        $param = $request->param();
        $cmid = isset($param['comment_mid']) ? (string) $param['comment_mid'] : '';
        if (!in_array($cmid, ['1', '2', '3', '8', '9', '11', '12'], true)) {
            return json(['code' => 1006, 'msg' => lang('index/mid_err')]);
        }
        $content = trim($param['comment_content'] ?? '');
        if (empty($content)) return json(['code' => 1004, 'msg' => lang('index/require_content')]);

        $cookie = 'comment_timespan';
        if (!empty(cookie($cookie))) return json(['code' => 1005, 'msg' => lang('frequently')]);

        if ($GLOBALS['config']['comment']['login'] == 1) {
            $check = model('User')->checkLogin();
            if ($check['code'] > 1) return json(['code' => 1003, 'msg' => lang('index/require_login')]);
        }

        $data = [];
        $data['comment_mid'] = intval($param['comment_mid']);
        $data['comment_rid'] = intval($param['comment_rid'] ?? 0);
        $data['comment_pid'] = intval($param['comment_pid'] ?? 0);
        $data['comment_content'] = htmlentities(mac_filter_words($content));
        $data['comment_ip'] = mac_get_client_ip();
        $data['comment_time'] = time();

        if (!empty(cookie('user_id'))) {
            $uinfo = model('User')->field('user_nick_name,user_name')->where(['user_id' => intval(cookie('user_id'))])->find();
            $data['user_id'] = intval(cookie('user_id'));
            $data['comment_name'] = htmlentities($uinfo['user_nick_name'] ?: $uinfo['user_name']);
        } else {
            $data['user_id'] = 0;
            $data['comment_name'] = htmlentities(trim($param['comment_name'] ?? lang('controller/visitor')));
        }

        $data['comment_status'] = ($GLOBALS['config']['comment']['audit'] == 1) ? 0 : 1;
        $res = model('Comment')->saveData($data);
        cookie($cookie, 't', 30);
        return json($res);
    }

    /**
     * 举报评论
     * api.php/comment/report?id=1
     */
    public function report(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $cookie = 'comment-report-' . $id;
        if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
        model('Comment')->where(['comment_id' => $id])->setInc('comment_report');
        cookie($cookie, 't', 86400);
        return json(['code' => 1, 'msg' => 'ok']);
    }

    /**
     * 评论顶/踩
     * api.php/comment/digg?id=1&type=up
     */
    public function digg(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id'] ?? 0);
        if ($id < 1) return json(['code' => 1001, 'msg' => '参数错误']);
        $type = trim($param['type'] ?? '');
        if ($type) {
            $cookie = 'comment-digg-' . $id;
            if (!empty(cookie($cookie))) return json(['code' => 1002, 'msg' => lang('index/haved')]);
            if ($type == 'up') { model('Comment')->where(['comment_id'=>$id])->setInc('comment_up'); cookie($cookie,'t',30); }
            elseif ($type == 'down') { model('Comment')->where(['comment_id'=>$id])->setInc('comment_down'); cookie($cookie,'t',30); }
        }
        $info = Db::name('comment')->field('comment_up,comment_down')->where(['comment_id'=>$id])->find();
        return json(['code'=>1,'msg'=>'ok','data'=>['up'=>$info['comment_up']??0,'down'=>$info['comment_down']??0]]);
    }
}
