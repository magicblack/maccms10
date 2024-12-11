<?php
namespace app\admin\controller;
use think\Db;

class Comment extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];

        $where=[];
        if(in_array($param['status'],['0','1'],true)){
            $where['comment_status'] = ['eq',$param['status']];
        }
        if(in_array($param['mid'],['1','2','3'])){
            $where['comment_mid'] = ['eq',$param['mid']];
        }
        if(!empty($param['uid'])){
            $where['user_id'] = ['eq',$param['uid'] ];
        }
        if(!empty($param['report'])){
            if($param['report'] == 1){
                $where['comment_report'] = ['eq',0];
            }
            else{
                $where['comment_report'] = ['gt',0];
            }
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['comment_name|comment_content'] = ['like','%'.$param['wd'].'%'];
        }

        $order='comment_id desc';
        $res = model('Comment')->listData($where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/comment/title'));
        return $this->fetch('admin@comment/index');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input();
            $res = model('Comment')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['comment_id'] = ['eq',$id];
        $res = model('Comment')->infoData($where);

        $this->assign('info',$res['info']);
        $this->assign('title',lang('admin/comment/title'));
        return $this->fetch('admin@comment/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids) || !empty($all)){
            $where=[];
            $where['comment_id'] = ['in',$ids];
            if($all==1){
                $where['comment_id'] = ['gt',0];
            }
            $res = model('Comment')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['comment_status']) ){
            $where=[];
            $where['comment_id'] = ['in',$ids];

            $res = model('Comment')->fieldData($where,$col,$val);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    /**
     * 黑名单关键字配置
     */
    public function blacklist()
    {
        if ($this->request->isPost()) {
            $keywords = $this->request->post('keywords');
            // 按行分割关键字
            $keywords_array = array_filter(explode("\n", $keywords));
            // 过滤空行
            $keywords_array = array_map('trim', $keywords_array);
            $blcaks = config('blacks');
            $blcaks['black_keyword_list'] = $keywords_array;
            $res = mac_arr2file( APP_PATH .'extra/blacks.php', $blcaks);
            if($res===false){
                return $this->error(lang('write_err_config'));
            }
            return $this->success(lang('save_ok'));
        }
        $blcaks = config('blacks');
        $black_keyword_list = implode("\n", $blcaks['black_keyword_list']);
        $this->assign('black_keyword_list', $black_keyword_list);
        return $this->fetch('admin@comment/blacklist');
    }
    /**
     * 黑名单IP配置
     */
    public function blacklist_ip()
    {
        if ($this->request->isPost()) {
            $keywords = $this->request->post('ip');
            // 按行分割关键字
            $keywords_array = array_filter(explode("\n", $keywords));
            // 过滤空行
            $keywords_array = array_map('trim', $keywords_array);
            //使用mac_string_is_ip方法过滤掉非ip的内容
            $keywords_array = array_filter($keywords_array, function ($value) {
                return mac_string_is_ip($value);
            });
            $blcaks = config('blacks');
            $blcaks['black_ip_list'] = $keywords_array;
            $res = mac_arr2file( APP_PATH .'extra/blacks.php', $blcaks);
            if($res===false){
                return $this->error(lang('write_err_config'));
            }
            return $this->success(lang('save_ok'));
        }
        $blcaks = config('blacks');
        $black_keyword_list = implode("\n", $blcaks['black_ip_list']);
        $this->assign('black_ip_list', $black_keyword_list);
        return $this->fetch('admin@comment/blacklist_ip');
    }

}
