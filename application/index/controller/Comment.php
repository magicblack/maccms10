<?php
namespace app\index\controller;
use think\Controller;
use \think\Request;

class Comment extends Base
{
    public function __construct()
    {
        parent::__construct();
        //关闭中
        if($GLOBALS['config']['comment']['status'] == 0){
            echo 'comment is close';
            exit;
        }
    }

    public function index()
    {
        if (Request()->isPost()) {
            return $this->saveData();
        }
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('comment',$GLOBALS['config']['comment']);

        return $this->label_fetch('comment/index');
    }

    public function ajax() {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('comment',$GLOBALS['config']['comment']);
        return $this->label_fetch('comment/ajax',0,'json');
    }

    public function saveData() {
        $param = input();

        if($GLOBALS['config']['comment']['verify'] == 1){
            if(!captcha_check($param['verify'])){
                return ['code'=>1002,'msg'=>lang('verify_err')];
            }
        }

        if($GLOBALS['config']['comment']['login'] ==1){
            if(empty(cookie('user_id'))){
                return ['code' => 1003, 'msg' =>lang('index/require_login')];
            }
            $res = model('User')->checkLogin();
            if($res['code']>1) {
                return ['code' => 1003, 'msg' => lang('index/require_login')];
            }
        }

        if(empty($param['comment_content'])){
            return ['code'=>1004,'msg'=>lang('index/require_content')];
        }

        $cookie = 'comment_timespan';
        if(!empty(cookie($cookie))){
            return ['code'=>1005,'msg'=>lang('frequently')];
        }

        $param['comment_content']= htmlentities(mac_filter_words($param['comment_content']));
        // if(!preg_match('/[^\x00-\x80]/',$param['comment_content'])){
        //     return ['code'=>1005,'msg'=>lang('index/require_cn')];
        // }

        if(!in_array($param['comment_mid'],['1','2','3','8','9','11'])){
            return ['code'=>1006,'msg'=>lang('index/mid_err')];
        }

        if(empty(cookie('user_id'))){
            $param['comment_name'] = lang('controller/visitor');
        }
        else{
            $param['comment_name'] = cookie('user_name');
            $param['user_id'] = intval(cookie('user_id'));
            $user_data = model('User')->field('user_nick_name')->where(['user_id' => $param['user_id']])->find();
            if (!empty($user_data['user_nick_name'])) {
                $param['comment_name'] = $user_data['user_nick_name'];
            }
        }
        $param['comment_name'] = htmlentities(trim($param['comment_name']));
        $param['comment_rid'] = intval($param['comment_rid']);
        $param['comment_pid'] = intval($param['comment_pid']);
        if($GLOBALS['config']['comment']['audit'] ==1){
            $param['comment_status'] = 0;
        }

        $param['comment_ip'] = mac_get_ip_long();
        $blcaks = config('blacks');
        //判断黑名单关键字是否为空 不为空并且大于0则循环判断是否包含黑名单关键字
        if(!empty($blcaks['black_keyword_list']) && count($blcaks['black_keyword_list']) > 0){
            foreach ($blcaks['black_keyword_list'] as $key => $value) {
                if(strpos($param['comment_content'], $value) !== false){
                    return ['code'=>1007,'msg'=>lang('index/blacklist_keyword')];
                }
            }
        }
        //判断黑名单IP是否为空 不为空并且大于0则循环判断客户端ip是否包含黑名单ip
        if(!empty($blcaks['black_ip_list']) && count($blcaks['black_ip_list']) > 0){
            $client_ip = long2ip($param['comment_ip']);
            if (in_array($client_ip, $blcaks['black_ip_list'])){
                return ['code'=>1008,'msg'=>lang('index/blacklist_ip')];
            }
        }

        $res = model('Comment')->saveData($param);
        if($res['code']>1){
            return $res;
        }
        else{
            cookie($cookie, 't', $GLOBALS['config']['comment']['timespan']);
            if($GLOBALS['config']['comment']['audit'] ==1){
                $res['msg'] = lang('index/thanks_msg_audit');
            }
            else{
                $res['msg'] = lang('index/thanks_msg');
            }
            return $res;
        }
    }

    public function report()
    {
        $param = input();
        $id = intval($param['id']);

        if(empty($id) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $cookie = 'comment-report-' . $id;
        if(!empty(cookie($cookie))){
            return json(['code'=>1002,'msg'=>lang('index/haved')]);
        }
        $where = [];
        $where['comment_id'] = $id;
        model('comment')->where($where)->setInc('comment_report');
        cookie($cookie, 't', $GLOBALS['config']['comment']['timespan']);

        return json(['code'=>1,'msg'=>lang('opt_ok')]);
    }

    public function digg()
    {
        $param = input();
        $id = intval($param['id']);
        $type = $param['type'];

        if(empty($id) ||  empty($type) ) {
            return json(['code'=>1001,'msg'=>lang('param_err')]);
        }

        $pre = 'comment';
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_up,'.$pre.'_down';
        $model = model($pre);

        if($type) {
            $cookie = $pre . '-digg-' . $id;
            if(!empty(cookie($cookie))){
                return json(['code'=>1002,'msg'=>lang('index/haved')]);
            }
            if ($type == 'up') {
                $model->where($where)->setInc($pre . '_up');
            } elseif ($type == 'down') {
                $model->where($where)->setInc($pre . '_down');
            }
            cookie($cookie, 't', $GLOBALS['config']['comment']['timespan']);
        }

        $res = $model->infoData($where,$field);
        if($res['code']>1) {
            return json($res);
        }
        $info = $res['info'];
        if ($info) {
            $data = $info;
        }
        else{
            $data[$pre.'_up'] = 0;
            $data[$pre.'_down'] = 0;
        }
        return json(['code'=>1,'msg'=>lang('opt_ok'),'data'=>$data]);
    }

}
