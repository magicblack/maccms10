<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Art extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function data()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : $param['limit'];

        $where = [];
        if (!empty($param['type'])) {
            $where['type_id'] = ['eq', $param['type']];
        }
        if (!empty($param['level'])) {
            $where['art_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'], ['0', '1'])) {
            $where['art_status'] = ['eq', $param['status']];
        }
        if (!empty($param['lock'])) {
            $where['art_lock'] = ['eq', $param['lock']];
        }
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['art_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['art_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['art_pic'] = ['like','%#err%'];
            }
        }
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $where['art_name'] = ['like','%'.$param['wd'].'%'];
        }

        if(!empty($param['repeat'])){
            if($param['page'] ==1){
                Db::query('DROP TABLE IF EXISTS '.config('database.prefix').'tmpart');
                Db::query('CREATE TABLE IF NOT EXISTS `'.config('database.prefix').'tmpart` as (SELECT min(art_id)as id1,art_name as name1 FROM '.config('database.prefix').'art GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='art_name asc';
            $res = model('Art')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            $order='art_time desc';
            $res = model('Art')->listData($where,$order,$param['page'],$param['limit']);
        }

        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['art_detail'] >0 && $v['art_time_make'] < $v['art_time']){
                $v['ismake'] = 0;
            }
        }

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title', '文章管理');
        return $this->fetch('admin@art/index');
    }

    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) ){
                return $this->error('没有选择任何参数');
            }
            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['art_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['art_status'] = ['eq',$param['status']];
            }
            if(!empty($param['lock'])){
                $where['art_lock'] = ['eq',$param['lock']];
            }
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['art_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['art_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['art_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $where['art_name'] = ['like','%'.$param['wd'].'%'];
            }


            if($param['ck_del'] == 1){
                $res = model('Art')->delData($where);
                mac_echo('批量删除完毕');
                mac_jump( url('art/batch') ,3);
                exit;
            }

            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;
            }
            if(empty($total)) {
                $total = model('Art')->countData($where);
                $page_count = ceil($total / $param['limit']);
            }

            if($param['page'] > $page_count) {
                mac_echo('批量设置完毕');
                mac_jump( url('art/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>共".$total."条数据需要处理，每页".$param['limit']."条，共".$page_count."页，正在处理第".$param['page']."页数据</font>");

            $order='art_id desc';
            $res = model('Art')->listData($where,$order,$param['page'],$param['limit']);

            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['art_id'] = $v['art_id'];

                $update = [];
                $des = $v['art_id'].','.$v['art_name'];

                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['art_level'] = $param['val_level'];
                    $des .= '&nbsp;推荐值：'.$param['val_level'].'；';
                }
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['art_status'] = $param['val_status'];
                    $des .= '&nbsp;状态：'.($param['val_status'] ==1 ? '[已审核]':'[未审核]') .'；';
                }
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['art_lock'] = $param['val_lock'];
                    $des .= '&nbsp;推荐值：'.($param['val_lock']==1 ? '[锁定]':'[解锁]').'；';
                }
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['art_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;人气：'.$update['art_hits'].'；';
                }
                mac_echo($des);
                $res2 = model('Art')->where($where2)->update($update);

            }
            $param['page']++;
            $url = url('art/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title','文章批量操作');
        return $this->fetch('admin@art/batch');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $param['art_content'] = str_replace( $GLOBALS['config']['upload']['protocol'].':','mac:',$param['art_content']);
            $res = model('Art')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['art_id'] = ['eq',$id];
        $res = model('Art')->infoData($where);

        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('art_page_list',$info['art_page_list']);

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title','文章信息');
        return $this->fetch('admin@art/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['art_id'] = ['in',$ids];
            $res = model('Art')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        elseif(!empty($param['repeat'])){
            $st = ' not in ';
            if($param['retain']=='max'){
                $st=' in ';
            }
            $sql = 'delete from '.config('database.prefix').'art where art_name in(select name1 from '.config('database.prefix').'tmpart) and art_id '.$st.'(select id1 from '.config('database.prefix').'tmpart)';
            $res = model('Art')->execute($sql);
            if($res===false){
                return $this->success('删除失败');
            }
            return $this->success('删除成功');
        }
        return $this->error('参数错误');
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];
        $start = $param['start'];
        $end = $param['end'];


        if(!empty($ids) && in_array($col,['art_status','art_lock','art_level','art_hits','type_id'])){
            $where=[];
            $where['art_id'] = ['in',$ids];
            if(empty($start)) {
                $res = model('Art')->fieldData($where, $col, $val);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['art_id'] = ['eq',$v];
                    $res = model('Art')->fieldData($where, $col, $val);
                }
            }
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error('参数错误');
    }

    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Art')->updateToday($flag);
        return json($res);
    }

}
