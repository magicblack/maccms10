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
        return $this->label_fetch('comment/ajax');
	}

	public function saveData() {
        $param = input();

        if($GLOBALS['config']['comment']['verify'] == 1){
            if(!captcha_check($param['verify'])){
                return ['code'=>1002,'msg'=>'验证码错误'];
            }
        }

        if($GLOBALS['config']['comment']['login'] ==1){
            if(empty(cookie('user_id'))){
                return ['code' => 1003, 'msg' => '登录后才可以发表留言'];
            }
            $res = model('User')->checkLogin();
            if($res['code']>1) {
                return ['code' => 1003, 'msg' => '登录后才可以发表留言'];
            }
        }

        if(empty($param['comment_content'])){
            return ['code'=>1004,'msg'=>'留言内容不能为空'];
        }

        $cookie = 'comment_timespan';
        if(!empty(cookie($cookie))){
            return ['code'=>1005,'msg'=>'请不要频繁操作'];
        }

        $pattern = '/[^\x00-\x80]/';
        if(!preg_match($pattern,$param['comment_content'])){
            return ['code'=>1005,'msg'=>'内容必须包含中文,请重新输入'];
        }
        $param['comment_content']= htmlentities(mac_filter_words($param['comment_content']));

        if(!in_array($param['comment_mid'],['1','2','3','8','9'])){
            return ['code'=>1006,'msg'=>'模型mid错误'];
        }

        if(empty(cookie('user_id'))){
            $param['comment_name'] = '游客';
        }
        else{
            $param['comment_name'] = cookie('user_name');
            $param['user_id'] = intval(cookie('user_id'));
        }
        $param['comment_name'] = htmlentities($param['comment_name']);

        if($GLOBALS['config']['comment']['audit'] ==1){
            $param['comment_status'] = 0;
        }

        $ip = sprintf('%u',ip2long(request()->ip()));
        if($ip>2147483647){
            $ip=0;
        }
        $param['comment_ip'] = $ip;

		$res = model('Comment')->saveData($param);
        if($res['code']>1){
            return $res;
        }
        else{
            cookie($cookie, 't', $GLOBALS['config']['comment']['timespan']);
            if($GLOBALS['config']['comment']['audit'] ==1){
                $res['msg'] = '谢谢，我们会尽快审核你的评论！';
            }
            else{
                $res['msg'] = '感谢你的评论！';
            }
            return $res;
        }
	}

    public function report()
    {
        $param = input();
        $id = $param['id'];

        if(empty($id) ) {
            return json(['code'=>1001,'msg'=>'参数错误']);
        }

        $cookie = 'comment-report-' . $id;
        if(!empty(cookie($cookie))){
            return json(['code'=>1002,'msg'=>'您已提交举报了']);
        }
        $where = [];
        $where['comment_id'] = $id;
        model('comment')->where($where)->setInc('comment_report');
        cookie($cookie, 't', $GLOBALS['config']['comment']['timespan']);

        return json(['code'=>1,'msg'=>'操作成功！']);
    }

    public function digg()
    {
        $param = input();
        $id = $param['id'];
        $type = $param['type'];

        if(empty($id) ||  empty($type) ) {
            return json(['code'=>1001,'msg'=>'参数错误']);
        }

        $pre = 'comment';
        $where = [];
        $where[$pre.'_id'] = $id;
        $field = $pre.'_up,'.$pre.'_down';
        $model = model($pre);

        if($type) {
            $cookie = $pre . '-digg-' . $id;
            if(!empty(cookie($cookie))){
                return json(['code'=>1002,'msg'=>'您已参与过了']);
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
        return json(['code'=>1,'msg'=>'操作成功！','data'=>$data]);
    }

}
