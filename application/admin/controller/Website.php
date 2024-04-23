<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Pinyin;

class Website extends Base
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
            $where['type_id|type_id_1'] = ['eq', $param['type']];
        }
        if (!empty($param['level'])) {
            $where['website_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'], ['0', '1'])) {
            $where['website_status'] = ['eq', $param['status']];
        }
        if (!empty($param['lock'])) {
            $where['website_lock'] = ['eq', $param['lock']];
        }
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['website_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['website_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['website_pic'] = ['like','%#err%'];
            }
        }
        if(!empty($param['wd'])){
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['website_name'] = ['like','%'.$param['wd'].'%'];
        }

        if(!empty($param['repeat'])){
            if($param['page'] ==1){
                Db::execute('DROP TABLE IF EXISTS '.config('database.prefix').'tmpwebsite');
                Db::execute('CREATE TABLE `'.config('database.prefix').'tmpwebsite` (`id1` int unsigned DEFAULT NULL, `name1` varchar(1024) NOT NULL DEFAULT \'\') ENGINE=MyISAM');
                Db::execute('INSERT INTO `'.config('database.prefix').'tmpwebsite` (SELECT min(website_id)as id1,website_name as name1 FROM '.config('database.prefix').'website GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='website_name asc';
            $res = model('Website')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            $order='website_time desc';
            $res = model('Website')->listData($where,$order,$param['page'],$param['limit']);
        }

        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['website_detail'] >0 && $v['website_time_make'] < $v['website_time']){
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

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/index');
    }

    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) ){
                return $this->error(lang('param_err'));
            }
            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['website_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['website_status'] = ['eq',$param['status']];
            }
            if(!empty($param['lock'])){
                $where['website_lock'] = ['eq',$param['lock']];
            }
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['website_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['website_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['website_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['website_name'] = ['like','%'.$param['wd'].'%'];
            }


            if($param['ck_del'] == 1){
                $res = model('Website')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('website/batch') ,3);
                exit;
            }

            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;
            }
            if(empty($param['total'])) {
                $param['total'] = model('Website')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_opt_ok'));
                mac_jump( url('website/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            $order='website_id desc';
            $res = model('Website')->listData($where,$order,$param['page'],$param['limit']);

            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['website_id'] = $v['website_id'];

                $update = [];
                $des = $v['website_id'].','.$v['website_name'];

                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['website_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['website_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['website_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('ublock').']').'；';
                }
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['website_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['website_hits'].'；';
                }
                mac_echo($des);
                $res2 = model('Website')->where($where2)->update($update);

            }
            $param['page']++;
            $url = url('website/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/batch');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $param['website_content'] = str_replace( $GLOBALS['config']['upload']['protocol'].':','mac:',$param['website_content']);
            $res = model('Website')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['website_id'] = ['eq',$id];
        $res = model('Website')->infoData($where);

        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('website_page_list',$info['website_page_list']);

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        $this->assign('title',lang('admin/website/title'));
        return $this->fetch('admin@website/info');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['website_id'] = ['in',$ids];
            $res = model('Website')->delData($where);
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
            $sql = 'delete from '.config('database.prefix').'website where website_name in(select name1 from '.config('database.prefix').'tmpwebsite) and website_id '.$st.'(select id1 from '.config('database.prefix').'tmpwebsite)';
            $res = model('Website')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            return $this->success(lang('del_ok'));
        }
        return $this->error(lang('param_err'));
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];
        $start = $param['start'];
        $end = $param['end'];
        if ($col == 'type_id' && $val==''){
            return $this->error("请选择分类提交");
        }

        if(!empty($ids) && in_array($col,['website_status','website_lock','website_level','website_hits','type_id'])){
            $where=[];
            $where['website_id'] = ['in',$ids];
            $update = [];
            if(empty($start)) {
                $update[$col] = $val;
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Website')->fieldData($where, $update);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['website_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Website')->fieldData($where, $update);
                }
            }
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function updateToday()
    {
        $param = input();
        $flag = $param['flag'];
        $res = model('Website')->updateToday($flag);
        return json($res);
    }

}
