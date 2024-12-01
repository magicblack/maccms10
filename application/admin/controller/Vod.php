<?php
namespace app\admin\controller;
use think\Cache;
use think\Db;

class Vod extends Base
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

        $where = [];
        if(!empty($param['type'])){
            $where['type_id|type_id_1'] = ['eq',$param['type']];
        }
        if(!empty($param['level'])){
            $where['vod_level'] = ['eq',$param['level']];
        }
        if(in_array($param['status'],['0','1'])){
            $where['vod_status'] = ['eq',$param['status']];
        }
        if(in_array($param['copyright'],['0','1'])){
            $where['vod_copyright'] = ['eq',$param['copyright']];
        }
        if(in_array($param['isend'],['0','1'])){
            $where['vod_isend'] = ['eq',$param['isend']];
        }
        if(!empty($param['lock'])){
            $where['vod_lock'] = ['eq',$param['lock']];
        }
        if(!empty($param['state'])){
            $where['vod_state'] = ['eq',$param['state']];
        }
        if(!empty($param['area'])){
            $where['vod_area'] = ['eq',$param['area']];
        }
        if(!empty($param['lang'])){
            $where['vod_lang'] = ['eq',$param['lang']];
        }
        if(in_array($param['plot'],['0','1'])){
            $where['vod_plot'] = ['eq',$param['plot']];
        }

        if(!empty($param['url'])){
            if($param['url']==1){
                $where['vod_play_url'] = '';
            }
        }
        if(!empty($param['points'])){
            $where['vod_points_play|vod_points_down'] = ['gt', 0];
        }
        if(!empty($param['pic'])){
            if($param['pic'] == '1'){
                $where['vod_pic'] = ['eq',''];
            }
            elseif($param['pic'] == '2'){
                $where['vod_pic'] = ['like','http%'];
            }
            elseif($param['pic'] == '3'){
                $where['vod_pic'] = ['like','%#err%'];
            }
        }
        if(!empty($param['weekday'])){
            $where['vod_weekday'] = ['like','%'.$param['weekday'].'%'];
        }
        if(!empty($param['wd'])){
            $param['wd'] = urldecode($param['wd']);
            $param['wd'] = mac_filter_xss($param['wd']);
            $where['vod_name|vod_actor|vod_sub'] = ['like','%'.$param['wd'].'%'];
        }
        if(!empty($param['player'])){
            if($param['player']=='no'){
                $where['vod_play_from'] = [['eq', ''], ['eq', 'no'], 'or'];
            }
            else {
                $where['vod_play_from'] = ['like', '%' . $param['player'] . '%'];
            }
        }
        if(!empty($param['downer'])){
            if($param['downer']=='no'){
                $where['vod_down_from'] = [['eq', ''], ['eq', 'no'], 'or'];
            }
            else {
                $where['vod_down_from'] = ['like', '%' . $param['downer'] . '%'];
            }
        }
        if(!empty($param['server'])){
            $where['vod_play_server|vod_down_server'] = ['like','%'.$param['server'].'%'];
        }
        $order='vod_time desc';
        if(in_array($param['order'],['vod_id','vod_hits','vod_hits_month','vod_hits_week','vod_hits_day'])){
            $order = $param['order'] .' desc';
        }

        if(!empty($param['repeat'])){
            if(!empty($param['cache'])){
                model('Vod')->createRepeatCache();
                return $this->success(lang('update_ok'));
            }

            if($param['page'] ==1){
                //使用缓存查看是否创建过缓存表
                $cacheResult = Cache::get('vod_repeat_table_created_time',0);
                //缓存时间超过7天和没有创建过缓存都会重建缓存
                if( $cacheResult == 0 || time() - $cacheResult > 604800){
                    model('Vod')->createRepeatCache();
                }
            }
            $order='vod_name asc';
            $res = model('Vod')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            $res = model('Vod')->listData($where,$order,$param['page'],$param['limit']);
        }


        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['vod_detail'] >0 && $v['vod_time_make'] < $v['vod_time']){
                $v['ismake'] = 0;
            }
        }

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //播放器
        $this->assignBaseListByConfig();
        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/index');
    }

    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits'])
                && empty($param['ck_points']) && empty($param['ck_copyright'])
            ){
                return $this->error(lang('param_err'));
            }


            if($param['ck_del']==2 && empty($param['player'])){
                return $this->error(lang('admin/vod/del_play_must_select_play'));
            }
            if($param['ck_del']==3 && empty($param['downer'])){
                return $this->error(lang('admin/vod/del_down_must_select_down'));
            }

            $where = [];
            if(!empty($param['type'])){
                $where['type_id'] = ['eq',$param['type']];
            }
            if(!empty($param['level'])){
                $where['vod_level'] = ['eq',$param['level']];
            }
            if(in_array($param['status'],['0','1'])){
                $where['vod_status'] = ['eq',$param['status']];
            }
            if(in_array($param['copyright'],['0','1'])){
                $where['vod_copyright'] = ['eq',$param['copyright']];
            }
            if(in_array($param['isend'],['0','1'])){
                $where['vod_isend'] = ['eq',$param['isend']];
            }

            if(!empty($param['lock'])){
                $where['vod_lock'] = ['eq',$param['lock']];
            }
            if(!empty($param['state'])){
                $where['vod_state'] = ['eq',$param['state']];
            }

            if(!empty($param['area'])){
                $where['vod_area'] = ['eq',$param['area']];
            }
            if(!empty($param['lang'])){
                $where['vod_lang'] = ['eq',$param['lang']];
            }

            if(!empty($param['url'])){
                if($param['url']==1){
                    $where['vod_play_url'] = '';
                }
            }
            if(!empty($param['pic'])){
                if($param['pic'] == '1'){
                    $where['vod_pic'] = ['eq',''];
                }
                elseif($param['pic'] == '2'){
                    $where['vod_pic'] = ['like','http%'];
                }
                elseif($param['pic'] == '3'){
                    $where['vod_pic'] = ['like','%#err%'];
                }
            }
            if(!empty($param['wd'])){
                $param['wd'] = htmlspecialchars(urldecode($param['wd']));
                $where['vod_name'] = ['like','%'.$param['wd'].'%'];
            }

            if(!empty($param['weekday'])){
                $where['vod_weekday'] = ['like','%'.$param['weekday'].'%'];
            }

            if(!empty($param['player'])){
                if($param['player']=='no'){
                    $where['vod_play_from'] = [['eq', ''], ['eq', 'no'], 'or'];
                }
                else {
                    $where['vod_play_from'] = ['like', '%' . $param['player'] . '%'];
                }
            }
            if(!empty($param['downer'])){
                if($param['downer']=='no'){
                    $where['vod_down_from'] = [['eq', ''], ['eq', 'no'], 'or'];
                }
                else {
                    $where['vod_down_from'] = ['like', '%' . $param['downer'] . '%'];
                }
            }

            if($param['ck_del'] == 1){
                $res = model('Vod')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('vod/batch') ,3);
                exit;
            }


            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;
            }
            if(empty($param['total'])) {
                $param['total'] = model('Vod')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_opt_ok'));
                mac_jump( url('vod/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            $page = $param['page_count'] - $param['page'] + 1;
            $order='vod_id desc';
            $res = model('Vod')->listData($where,$order,$page,$param['limit']);

            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['vod_id'] = $v['vod_id'];

                $update = [];
                $des = $v['vod_id'].','.$v['vod_name'];

                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['vod_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['vod_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                if(!empty($param['ck_copyright']) && isset($param['val_copyright'])){
                    $update['vod_copyright'] = $param['val_copyright'];
                    $des .= '&nbsp;'.lang('copyright').'：'.($param['val_copyright'] ==1 ? '['.lang('open').']':'['.lang('close').'') .'；';
                }
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['vod_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('unlock').']').'；';
                }
                if(!empty($param['ck_hits']) && $param['val_hits_min']!='' && $param['val_hits_max']!='' ){
                    $update['vod_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['vod_hits'].'；';
                }
                if(!empty($param['ck_points']) && $param['val_points_play']!=''  ){
                    $update['vod_points_play'] = $param['val_points_play'];
                    $des .= '&nbsp;'.lang('points_play').'：'.$param['val_points_play'].'；';
                }
                if(!empty($param['ck_points']) && $param['val_points_down']!='' ){
                    $update['vod_points_down'] = $param['val_points_down'];
                    $des .= '&nbsp;'.lang('points_down').'：'.$param['val_points_down'].'；';
                }

                if($param['ck_del'] == 2 || $param['ck_del'] ==3){
                    if($param['ck_del']==2) {
                        $pre = 'vod_play';
                        $par = 'player';
                        $des .= '&nbsp;'.lang('play_group').'：';
                    }
                    elseif($param['ck_del']==3){
                        $pre = 'vod_down';
                        $par='downer';
                        $des .= '&nbsp;'.lang('down_group').'：';
                    }


                    if($param[$par] == $v[$pre.'_from']){
                        $update[$pre.'_from'] = '';
                        $update[$pre.'_server'] = '';
                        $update[$pre.'_note'] = '';
                        $update[$pre.'_url'] = '';
                        $des .= lang('del_empty').'；';
                    }
                    else{
                        $vod_from_arr = explode('$$$',$v[$pre.'_from']);
                        $vod_server_arr = explode('$$$',$v[$pre.'_server']);
                        $vod_note_arr = explode('$$$',$v[$pre.'_note']);
                        $vod_url_arr = explode('$$$',$v[$pre.'_url']);

                        $key = array_search($param[$par],$vod_from_arr);
                        if($key!==false){
                            unset($vod_from_arr[$key]);
                            unset($vod_server_arr[$key]);
                            unset($vod_note_arr[$key]);
                            unset($vod_url_arr[$key]);

                            $update[$pre.'_from'] = join('$$$',$vod_from_arr);
                            $update[$pre.'_server'] = join('$$$',$vod_server_arr);
                            $update[$pre.'_note'] = join('$$$',$vod_note_arr);
                            $update[$pre.'_url'] = join('$$$',$vod_url_arr);
                            $des .= lang('del'). '；';
                        }
                        else{
                            $des .= lang('jump_over').'；';
                        }
                    }
                }

                mac_echo($des);
                $res2 = model('Vod')->where($where2)->update($update);

            }
            $param['page']++;
            $url = url('vod/batch') .'?'. http_build_query($param);
            mac_jump( $url ,3);
            exit;
        }

        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //播放器
        $this->assignBaseListByConfig();
        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/batch');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Vod')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['vod_id'] = $id;
        $res = model('Vod')->infoData($where);


        $info = $res['info'];
        $this->assign('info',$info);

        //分类
        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //地区、语言
        $config = config('maccms.app');
        $area_list = explode(',',$config['vod_area']);
        $lang_list = explode(',',$config['vod_lang']);
        $this->assign('area_list',$area_list);
        $this->assign('lang_list',$lang_list);

        //用户组
        $group_list = model('Group')->getCache('group_list');
        $this->assign('group_list',$group_list);

        //播放器
        $this->assignBaseListByConfig();

        //播放组、下载租
        $this->assign('vod_play_list',(array)$info['vod_play_list']);
        $this->assign('vod_down_list',(array)$info['vod_down_list']);
        $this->assign('vod_plot_list',(array)$info['vod_plot_list']);


        $this->assign('title',lang('admin/vod/title'));
        return $this->fetch('admin@vod/info');
    }

    public function iplot()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = model('Vod')->savePlot($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['vod_id'] = $id;
        $res = model('Vod')->infoData($where);


        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('vod_plot_list',$info['vod_plot_list']);


        $this->assign('title',lang('admin/vod/plot/title'));
        return $this->fetch('admin@vod/iplot');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['vod_id'] = ['in',$ids];
            $res = model('Vod')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            Cache::rm('vod_repeat_table_created_time');
            return $this->success($res['msg']);
        }
        elseif(!empty($param['repeat'])){
            if($param['retain']=='max') {
                // 保留最大ID - 先用子查询找出要保留的ID
                $sql = 'DELETE FROM '.config('database.prefix').'vod WHERE vod_id IN (
                SELECT * FROM (
                    SELECT v1.vod_id
                    FROM '.config('database.prefix').'vod v1
                    INNER JOIN '.config('database.prefix').'vod v2 
                    ON v1.vod_name = v2.vod_name AND v1.vod_id < v2.vod_id
                ) tmp
            )';
            } else {
                // 保留最小ID - 先用子查询找出要保留的ID
                $sql = 'DELETE FROM '.config('database.prefix').'vod WHERE vod_id IN (
                SELECT * FROM (
                    SELECT v1.vod_id
                    FROM '.config('database.prefix').'vod v1
                    INNER JOIN '.config('database.prefix').'vod v2 
                    ON v1.vod_name = v2.vod_name AND v1.vod_id > v2.vod_id
                ) tmp
            )';
            }

            $res = model('Vod')->execute($sql);
            if($res===false){
                return $this->success(lang('del_err'));
            }
            Cache::rm('vod_repeat_table_created_time');
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

        if(!empty($ids) && in_array($col,['vod_status','vod_lock','vod_level','vod_hits','type_id','vod_copyright'])){
            $where=[];
            $where['vod_id'] = ['in',$ids];
            $update = [];
            if(empty($start)) {
                $update[$col] = $val;
                if($col == 'type_id'){
                    $type_list = model('Type')->getCache();
                    $id1 = intval($type_list[$val]['type_pid']);
                    $update['type_id_1'] = $id1;
                }
                $res = model('Vod')->fieldData($where, $update);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['vod_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Vod')->fieldData($where, $update);
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
        $res = model('Vod')->updateToday($flag);
        return json($res);
    }

    private function assignBaseListByConfig() {
        $player_list = config('vodplayer');
        $downer_list = config('voddowner');
        $server_list = config('vodserver');
        $player_list = mac_multisort($player_list,'sort',SORT_DESC,'status','1');
        $downer_list = mac_multisort($downer_list,'sort',SORT_DESC,'status','1');
        $server_list = mac_multisort($server_list,'sort',SORT_DESC,'status','1');
        $this->assign('player_list',$player_list);
        $this->assign('downer_list',$downer_list);
        $this->assign('server_list',$server_list);
    }
}
