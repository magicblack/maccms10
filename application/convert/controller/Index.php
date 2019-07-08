<?php
namespace app\convert\controller;
use think\Controller;
use think\Db;
use think\Cache;
use app\common\util\Dir;


class Index extends Controller
{
    public function index($step = 0)
    {
        switch ($step) {
            case 2:
                return self::step2();
                break;
            case 3:
                return self::step3();
                break;
            case 4:
                return self::step4();
                break;
            case 5:
                return self::step5();
                break;
            default:
                session('install_error', false);
                return $this->fetch('convert@/index/index');
                break;
        }
    }

    /**
     * 第二步：环境检测
     * @return mixed
     */
    private function step2()
    {
        $data = [];
        $data['env'] = self::checkNnv();
        $data['dir'] = self::checkDir();
        $data['func'] = self::checkFunc();
        $this->assign('data', $data);
        return $this->fetch('convert@index/step2');
    }

    /**
     * 第三步：初始化配置
     * @return mixed
     */
    private function step3()
    {
        if ($this->request->isPost()) {
            $data = input('post.');
            $data['type'] = 'mysql';
            $data['charset'] = 'utf8';
            $data['dsn'] = '';


            $rule = [
                'cmsname|待转换系统' => 'require',
                'hostname|服务器地址' => 'require',
                'hostport|数据库端口' => 'require|number',
                'database|数据库名称' => 'require',
                'username|数据库账号' => 'require',
                'prefix|数据库前缀' => 'require|regex:^[a-z0-9]{1,20}[_]{1}',
            ];
            $validate = $this->validate($data, $rule);
            if (true !== $validate) {
                return $this->error($validate);
            }
            $database = $data['database'];

            // 创建数据库连接
            $db_connect = Db::connect($data);
            $rc=false;
            $list=[];
            // 检测数据库连接
            try {
                $list = $db_connect->query('SHOW TABLE STATUS');
            }
            catch (\Exception $e) {
                return $this->error('数据库连接失败，请检查数据库配置！');
            }

            foreach($list as $k=>$v){
                if(strpos($v['Name'],$data['prefix'])!==false){
                    $rc=true;
                    break;
                }
            }
            if($rc==false){
                return $this->error('数据库连接成功，但没有前缀的表！','');
            }

            session('database', $data);

            return $this->success('数据库连接成功', '');
        } else {
            return $this->error('非法访问');
        }
    }

    /**
     * 第四步：转换数据
     * @return mixed
     */
    private function step4()
    {
        $data = session('database');
        if (empty($data)) {
            return $this->error('非法访问');
        }

        $html = $this->fetch('convert@index/step4');
        echo $html;
        ob_flush();flush();

        $this->data = $data;
        $this->db = Db::connect($data);
        $this->data_clear();
        $this->ps = 1000;

        echo '<script>showmessage(\'开始转转'.$data['cmsname'].'的数据，请稍候......\');</script>';


        switch ($data['cmsname']) {
            case 'maccms8x':
                $this->data_maccms8x();
                break;
            case 'seacms':
                $this->data_seacms();
                break;
            case 'ffcms':
                $this->data_ffcms();
                break;
            default:
                echo '未找到该系统转换方法';
                break;
        }

        echo '<script>showmessage(\'数据导入完毕，请稍候......\');</script>';

        echo '<script>showmessage(\'正在更新系统缓存，请稍候......\');</script>';
        Dir::delDir(RUNTIME_PATH.'cache/');
        Dir::delDir(RUNTIME_PATH.'log/');
        Dir::delDir(RUNTIME_PATH.'temp/');


        echo '<script>showmessage(\'数据转换结束，请数据转换的删除相关文件......\');</script>';
        echo '<script>showmessage(\'请手工删除：/convert.php......\');</script>';
        echo '<script>showmessage(\'请手工删除: /application/convert/......\');</script>';

    }


    private function data_clear()
    {
        Db::execute('truncate table `mac_art`');
        Db::execute('truncate table `mac_comment`');
        Db::execute('truncate table `mac_gbook`');
        Db::execute('truncate table `mac_link`');
        Db::execute('truncate table `mac_topic`');
        Db::execute('truncate table `mac_type`');
        Db::execute('truncate table `mac_ulog`');
        Db::execute('truncate table `mac_group`');
        Db::execute('truncate table `mac_user`');
        Db::execute('truncate table `mac_visit`');
        Db::execute('truncate table `mac_vod`');

        echo '<script>showmessage(\'正在清空原有数据，请稍候......\');</script>';
        ob_flush();flush();
    }

    private function data_maccms8x()
    {
        echo '<script>showmessage(\'正在导入友情链接数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('link')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[] = [
                'l_id'=>$v['l_id'],
                'link_name'=>$v['l_name'],
                'link_type'=>$v['l_type'],
                'link_url'=>$v['l_url'],
                'link_logo'=>$v['l_logo'],
                'link_sort'=>$v['l_sort'],
            ];
        }
        if(!empty($data)){
            Db::name('link')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入留言本数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('gbook')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }

            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'gbook_id' => $v['g_id'],
                    'gbook_rid' => $v['g_vid'],
                    'gbook_status' => $v['g_hide'] == 0 ? 1 : 0,
                    'gbook_name' => $v['g_name'],
                    'gbook_content' => $v['g_content'],
                    'gbook_reply' => $v['g_reply'],
                    'gbook_ip' => $v['g_ip'],
                    'gbook_time' => $v['g_time'],
                    'gbook_reply_time' => $v['g_replytime'],
                ];
            }
            if (!empty($data)) {
                Db::name('gbook')->insertAll($data);
            }
            unset($list, $data);
        }

        echo '<script>showmessage(\'正在分批次导入评论数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('comment')->page($p,$this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'comment_id' => $v['c_id'],
                    'comment_mid' => $v['c_type'] == 16 ? 1 : 2,
                    'comment_rid' => $v['c_vid'],
                    'comment_pid' => $v['c_rid'],
                    'comment_status' => $v['c_hide'] == 0 ? 1 : 0,
                    'comment_name' => $v['c_name'],
                    'comment_ip' => $v['c_ip'],
                    'comment_content' => $v['c_content'],
                    'comment_time' => $v['c_time'],
                ];
            }
            if (!empty($data)) {
                Db::name('comment')->insertAll($data);
            }
            unset($list, $data);
        }

        echo '<script>showmessage(\'正在导入会员组数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('user_group')->select();
        $data=[];
        $data[]=[
            'group_id'=>1,
            'group_name'=>'游客',
            'group_status'=>1
        ];
        foreach($list as $k=>$v){


            $data[] = [
                'group_id'=>$v['ug_id']+1,
                'group_name'=>$v['ug_name'],
                'group_status'=>1
            ];
        }
        if(!empty($data)){
            Db::name('group')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入会员数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('user')->page($p,$this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'user_id' => $v['u_id'],
                    'user_openid_qq' => $v['u_qid'],
                    'user_name' => $v['u_name'],
                    'user_pwd' => $v['u_password'],
                    'user_qq' => $v['u_qq'],
                    'user_email' => $v['u_email'],
                    'user_phone' => $v['u_phone'],
                    'user_status' => $v['u_status'],
                    'user_question' => $v['u_question'],
                    'user_answer' => $v['u_answer'],
                    'group_id' => $v['u_group'] + 1,
                    'user_points' => $v['u_points'],
                    'user_reg_time' => $v['u_regtime'],
                    'user_login_time' => $v['u_logintime'],
                    'user_login_num' => $v['u_loginnum'],
                    'user_extend' => $v['u_extend'],
                    'user_login_ip' => $v['u_loginip'],
                    'user_random' => $v['u_random'],
                    'user_end_time' => $v['u_end'],
                ];
            }
            if (!empty($data)) {
                Db::name('user')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在导入视频专题数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('vod_topic')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[$k] = [
                'topic_id'=>$v['t_id'],
                'topic_name'=>$v['t_name'],
                'topic_en'=> $v['t_enname'],
                'topic_sort'=>$v['t_sort'],
                'topic_tpl'=>$v['t_tpl'],
                'topic_pic'=>$v['t_pic'],
                'topic_content'=>$v['t_content'],
                'topic_key'=>$v['t_key'],
                'topic_des'=>$v['t_des'],
                'topic_title'=>$v['t_title'],
                'topic_status'=>$v['t_hide']==0?1:0,
                'topic_level'=>$v['t_level'],
                'topic_up'=>$v['t_up'],
                'topic_down'=>$v['t_down'],
                'topic_score'=>$v['t_score'],
                'topic_score_all'=>$v['t_scoreall'],
                'topic_score_num'=>$v['t_scorenum'],
                'topic_hits'=>$v['t_hits'],
                'topic_hits_day'=>$v['t_dayhits'],
                'topic_hits_week'=>$v['t_weekhits'],
                'topic_hits_month'=>$v['t_monthhits'],
                'topic_time_add'=>$v['t_addtime'],
                'topic_time'=>$v['t_time'],
                'topic_rel_vod'=>''
            ];
            $where2=[];
            $where2['d_topic'] = ['like', '%,'.$v['t_id'].',%'];
            $vod_list = $this->db->name('vod')->where($where2)->column("d_id");
            if(!empty($vod_list)){
                $data[$k]['topic_rel_vod'] = implode(',',$vod_list);
            }
        }
        if(!empty($data)){
            Db::name('topic')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在导入视频分类数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('vod_type')->order('t_id asc')->select();
        $data=[];
        $vod_type = [];
        $max_id = 0;
        foreach($list as $k=>$v){
            $data[] = [
                'type_id'=>$v['t_id'],
                'type_name'=>$v['t_name'],
                'type_en'=> $v['t_enname'],
                'type_mid'=>1,
                'type_pid'=>$v['t_pid'],
                'type_sort'=>$v['t_sort'],
                'type_status'=>$v['t_hide']==0?1:0,
                'type_tpl'=>'type.html',
                'type_tpl_list'=>'show.html',
                'type_tpl_detail'=>'detail.html',
                'type_tpl_play'=>'play.html',
                'type_tpl_down'=>'down.html',
                'type_key'=>$v['t_key'],
                'type_des'=>$v['t_des'],
                'type_title'=>$v['t_title']
            ];
            $vod_type[$v['t_id']] = $v['t_pid'];
            $max_id = $v['t_id'];
        }
        if(!empty($data)){
            Db::name('type')->insertAll($data);
        }
        unset($list,$data);
        $max_id++;

        echo '<script>showmessage(\'正在导入文章分类数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('art_type')->select();
        $data=[];
        $art_type = [];
        foreach($list as $k=>$v){
            $data[] = [
                'type_id'=>$v['t_id'] + $max_id,
                'type_name'=>$v['t_name'],
                'type_en'=> $v['t_enname'],
                'type_mid'=>2,
                'type_pid'=>$v['t_pid'] >0 ? $v['t_pid']+$max_id : 0,
                'type_sort'=>$v['t_sort'],
                'type_status'=>$v['t_hide']==0?1:0,
                'type_tpl'=>'type.html',
                'type_tpl_list'=>'show.html',
                'type_tpl_detail'=>'detail.html',
                'type_tpl_play'=>'',
                'type_tpl_down'=>'',
                'type_key'=>$v['t_key'],
                'type_des'=>$v['t_des'],
                'type_title'=>$v['t_title']
            ];
            $art_type[$v['t_id'] + $max_id] = $v['t_pid'] >0 ? $v['t_pid']+$max_id : 0;
        }
        if(!empty($data)){
            Db::name('type')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入文章数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('art')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }

            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'art_id' => $v['a_id'],
                    'art_name' => $v['a_name'],
                    'art_sub' => $v['a_subname'],
                    'art_en' => $v['a_enname'],
                    'art_letter' => $v['a_letter'],
                    'art_color' => $v['a_color'],
                    'art_from' => $v['a_from'],
                    'art_author' => $v['a_author'],
                    'art_tag' => $v['a_tag'],
                    'art_pic' => $v['a_pic'],
                    'type_id' => $v['a_type'] + 60,
                    'type_id_1' => $art_type[$v['a_type'] + 60],
                    'art_level' => $v['a_level'],
                    'art_status' => $v['a_hide'] == 0 ? 1 : 0,
                    'art_lock' => $v['a_lock'],
                    'art_up' => $v['a_up'],
                    'art_down' => $v['a_down'],
                    'art_hits' => $v['a_hits'],
                    'art_hits_day' => $v['a_dayhits'],
                    'art_hits_week' => $v['a_weekhits'],
                    'art_hits_month' => $v['a_monthhits'],
                    'art_time_add' => $v['a_addtime'],
                    'art_time' => $v['a_time'],
                    'art_remarks' => $v['a_remarks'],
                    'art_content' => $v['a_content'],
                ];
            }
            if (!empty($data)) {
                Db::name('art')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在分批次导入视频数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('vod')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'vod_id' => $v['d_id'],
                    'vod_name' => $v['d_name'],
                    'vod_sub' => $v['d_subname'],
                    'vod_en' => $v['d_enname'],
                    'vod_letter' => $v['d_letter'],
                    'vod_color' => $v['d_color'],
                    'vod_pic' => $v['d_pic'],
                    'vod_pic_thumb' => $v['d_picthumb'],
                    'vod_pic_slide' => $v['d_picslide'],
                    'vod_actor' => $v['d_starring'],
                    'vod_director' => $v['d_directed'],
                    'vod_tag' => $v['d_tag'],
                    'vod_remarks' => $v['d_remarks'],
                    'vod_area' => $v['d_area'],
                    'vod_lang' => $v['d_lang'],
                    'vod_year' => $v['d_year'],
                    'type_id' => $v['d_type'],
                    'type_id_1' => $vod_type[$v['d_type']],

                    'vod_status' => $v['d_hide'] == 0 ? 1 : 0,
                    'vod_lock' => $v['d_lock'],
                    'vod_serial' => $v['d_state'],
                    'vod_level' => $v['d_level'],
                    'group_id' => $v['d_usergroup'],
                    'vod_points_play' => $v['d_stint'],
                    'vod_points_down' => $v['d_stintdown'],
                    'vod_hits' => $v['d_hits'],
                    'vod_hits_day' => $v['d_dayhits'],
                    'vod_hits_week' => $v['d_weekhits'],

                    'vod_hits_month' => $v['d_monthhits'],
                    'vod_duration' => $v['d_duration'],
                    'vod_up' => $v['d_up'],
                    'vod_down' => $v['d_down'],
                    'vod_score' => $v['d_score'],
                    'vod_score_all' => $v['d_scoreall'],
                    'vod_score_num' => $v['d_scorenum'],
                    'vod_time_add' => $v['d_addtime'],
                    'vod_time' => $v['d_time'],
                    'vod_content' => $v['d_content'],
                    'vod_play_from' => $v['d_playfrom'],
                    'vod_play_server' => $v['d_playserver'],
                    'vod_play_note' => $v['d_playnote'],
                    'vod_play_url' => $v['d_playurl'],
                    'vod_down_from' => $v['d_downfrom'],
                    'vod_down_server' => $v['d_downserver'],
                    'vod_down_note' => $v['d_downnote'],
                    'vod_down_url' => $v['d_downurl'],
                ];

            }
            if (!empty($data)) {
                Db::name('vod')->insertAll($data);
            }
            unset($list, $data);
        }

    }

    private function data_seacms()
    {
        echo '<script>showmessage(\'正在导入友情链接数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('flink')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[] = [
                'l_id'=>$v['id'],
                'link_name'=>$v['webname'],
                'link_type'=>1,
                'link_url'=>$v['url'],
                'link_logo'=>$v['logo'],
                'link_sort'=>$v['sortrank'],
            ];
        }
        if(!empty($data)){
            Db::name('link')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入留言本数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('guestbook')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'gbook_id' => $v['id'],
                    'gbook_status' => $v['ischeck'],
                    'gbook_name' => $v['uname'],
                    'gbook_content' => $v['uname'],
                    'gbook_ip' => ip2long($v['ip']),
                    'gbook_time' => $v['dtime'],
                ];
            }
            if (!empty($data)) {
                Db::name('gbook')->insertAll($data);
            }
            unset($list, $data);
        }

        echo '<script>showmessage(\'正在分批次导入评论数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('comment')->page($p, $this->ps)->select();
            if (empty($list)) {
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'comment_id' => $v['id'],
                    'comment_mid' => $v['m_type'] == 0 ? 1 : 2,
                    'comment_rid' => $v['v_id'],
                    'comment_pid' => 0,
                    'comment_status' => $v['ischeck'],
                    'comment_name' => $v['username'],
                    'comment_ip' => ip2long($v['ip']),
                    'comment_content' => $v['msg'],
                    'comment_time' => $v['dtime'],
                ];
            }
            if (!empty($data)) {
                Db::name('comment')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在导入会员组数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('member_group')->select();
        $data=[];
        $data[]=[
            'group_id'=>1,
            'group_name'=>'游客',
            'group_status'=>1
        ];

        foreach($list as $k=>$v){
            $data[] = [
                'group_id'=>$v['gid']+1,
                'group_name'=>$v['gname'],
                'group_status'=>1
            ];
        }
        if(!empty($data)){
            Db::name('group')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入会员数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('member')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'user_id' => $v['id'],
                    'user_name' => $v['username'],
                    'user_pwd' => $v['password'],
                    'user_email' => $v['email'],
                    'user_status' => $v['state'],
                    'group_id' => $v['gid'] + 1,
                    'user_points' => $v['points'],
                    'user_reg_time' => $v['regtime'],
                ];
            }
            if (!empty($data)) {
                Db::name('user')->insertAll($data);
            }
            unset($list, $data);
        }




        echo '<script>showmessage(\'正在导入视频专题数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('topic')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[$k] = [
                'topic_id'=>$v['id'],
                'topic_name'=>$v['name'],
                'topic_en'=> $v['enname'],
                'topic_sort'=>$v['sort'],
                'topic_tpl'=>$v['template'],
                'topic_pic'=>$v['pic'],
                'topic_content'=>$v['des'],
                'topic_rel_vod'=> str_replace('ttttt',',',$v['vod']),
            ];
        }
        if(!empty($data)){
            Db::name('topic')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在导入分类数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('type')->select();
        $data=[];
        $types = [];
        foreach($list as $k=>$v){
            $data[] = [
                'type_id'=>$v['tid'],
                'type_name'=>$v['tname'],
                'type_en'=> $v['tenname'],
                'type_mid'=>$v['tptype']+1,
                'type_pid'=>$v['upid'],
                'type_sort'=>$v['torder'],
                'type_status'=>1,
                'type_tpl'=>'type.html',
                'type_tpl_list'=>'show.html',
                'type_tpl_detail'=>'detail.html',
                'type_tpl_play'=>'play.html',
                'type_key'=>$v['keyword'],
                'type_des'=>$v['description'],
                'type_title'=>$v['title']
            ];
            $types[$v['tid']] = $v['upid'];
        }
        if(!empty($data)){
            Db::name('type')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入文章数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('news')->page($p, $this->ps)->select();
            if (empty($list)) {
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'art_id' => $v['n_id'],
                    'art_name' => $v['n_title'],
                    'art_en' => $v['n_entitle'],
                    'art_letter' => $v['n_letter'],
                    'art_color' => $v['n_color'],
                    'art_from' => $v['n_from'],
                    'art_author' => $v['n_author'],
                    'art_tag' => $v['n_keyword'],
                    'art_pic' => $v['n_pic'],
                    'type_id' => $v['tid'],
                    'type_id_1' => $types[$v['tid']],
                    'art_level' => $v['n_commend'],
                    'art_status' => 1,
                    'art_blurb' => $v['n_outline'],
                    'art_hits' => $v['n_hit'],
                    'art_time_add' => $v['n_addtime'],
                    'art_time' => $v['n_addtime'],
                    'art_content' => $v['n_content'],

                ];
            }
            if (!empty($data)) {
                Db::name('art')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在分批次导入视频数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('data')->alias('d')
                ->field('d.*,c.body as content,p.body as play,p.body1 as down')
                ->join($this->data['prefix'] . 'content c', 'd.v_id=c.v_id')
                ->join($this->data['prefix'] . 'playdata p', 'd.v_id=p.v_id')
                ->page($p, $this->ps)
                ->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $play = $this->sea_url($v['play']);
                $down = $this->sea_url($v['down']);

                $data[] = [
                    'vod_id' => $v['v_id'],
                    'vod_name' => $v['v_name'],
                    'vod_en' => $v['v_enname'],
                    'vod_letter' => $v['v_letter'],
                    'vod_color' => $v['v_color'],
                    'vod_pic' => $v['v_pic'],
                    'vod_pic_thumb' => $v['v_spic'],
                    'vod_pic_slide' => $v['v_gpic'],
                    'vod_actor' => $v['v_actor'],
                    'vod_director' => $v['v_director'],
                    'vod_tag' => $v['v_tags'],
                    'vod_remarks' => $v['v_note'],
                    'vod_area' => $v['v_publisharea'],
                    'vod_lang' => $v['v_lang'],
                    'vod_year' => $v['v_publishyear'],
                    'type_id' => $v['tid'],
                    'type_id_1' => $types[$v['tid']],

                    'vod_status' => 1,
                    'vod_version' => $v['v_ver'],
                    'vod_total' => $v['v_total'],

                    'vod_level' => $v['v_commend'],
                    'vod_hits' => $v['v_hit'],
                    'vod_hits_day' => $v['v_dayhit'],
                    'vod_hits_week' => $v['v_weekhit'],
                    'vod_hits_month' => $v['v_monthhit'],

                    'vod_duration' => $v['v_len'],
                    'vod_up' => $v['v_digg'],
                    'vod_down' => $v['v_tread'],
                    'vod_score' => $v['v_score'],
                    'vod_score_all' => $v['v_score'] * $v['v_scorenum'],
                    'vod_score_num' => $v['v_scorenum'],
                    'vod_time_add' => $v['v_addtime'],
                    'vod_time' => $v['v_addtime'],

                    'vod_content' => $v['content'],
                    'vod_play_from' => $play['from'],
                    'vod_play_url' => $play['url'],

                    'vod_down_from' => $down['from'],
                    'vod_down_url' => $down['url'],
                ];

            }
            if (!empty($data)) {
                Db::name('vod')->insertAll($data);
            }
            unset($list, $data);
        }

    }

    private function sea_url($body)
    {
        $from = [];
        $url = [];
        $arr = explode('$$$',$body);
        $rc1=false;
        foreach($arr as $k=>$v){
            $arr2 = explode('$$',$v);
            $s2 = $arr2[1];
            $arr3 = explode('$',$s2);
            $url[] = $s2;
            foreach($arr3 as $k3=>$v3){
                $from[] = $arr3[count($arr3)-1];
                break;
            }
        }
        $from = join('$$$',$from);
        $url = join('$$$',$url);
        $res = ['from'=>$from,'url'=>$url];
        return $res;
    }

    private function data_ffcms()
    {
        echo '<script>showmessage(\'正在导入友情链接数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('link')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[] = [
                'l_id'=>$v['link_id'],
                'link_name'=>$v['link_name'],
                'link_type'=>$v['link_type'],
                'link_url'=>$v['link_url'],
                'link_logo'=>$v['link_logo'],
                'link_sort'=>$v['link_order'],
            ];
        }
        if(!empty($data)){
            Db::name('link')->insertAll($data);
        }
        unset($list,$data);



        echo '<script>showmessage(\'正在分批次导入留言本数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('forum')->where('forum_sid=5 and forum_cid=0')->page($p, $this->ps)->select();
            if (empty($list)) {
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'gbook_id' => $v['forum_id'],
                    'gbook_rid' => $v['forum_cid'],
                    'gbook_status' => $v['forum_status'],
                    'gbook_name' => $v['forum_title'],
                    'gbook_content' => $v['forum_content'],
                    'gbook_ip' => ip2long($v['forum_ip']),
                    'gbook_time' => $v['forum_addtime'],
                ];
            }
            if (!empty($data)) {
                Db::name('gbook')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在分批次导入评论数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('forum')->where('forum_cid>0')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'comment_id' => $v['forum_id'],
                    'comment_mid' => $v['forum_sid'],
                    'comment_rid' => $v['forum_cid'],
                    'comment_pid' => $v['forum_pid'],
                    'comment_status' => $v['forum_status'],
                    'comment_name' => $v['forum_title'],
                    'comment_ip' => ip2long($v['forum_ip']),
                    'comment_content' => $v['forum_content'],
                    'comment_time' => $v['forum_addtime'],
                ];
            }
            if (!empty($data)) {
                Db::name('comment')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在导入会员组数据，请稍候......\');</script>';
        ob_flush();flush();
        $data=[];

        $data[]=[
            'group_id'=>1,
            'group_name'=>'游客',
            'group_status'=>1
        ];

        $data[]=[
            'group_id'=>2,
            'group_name'=>'普通会员',
            'group_status'=>1
        ];
        $data[]=[
            'group_id'=>3,
            'group_name'=>'VIP会员',
            'group_status'=>1
        ];

        if(!empty($data)){
            Db::name('group')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在分批次导入会员数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('user')->page($p, $this->ps)->select();
            if (empty($list)) {
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();
            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'user_id' => $v['user_id'],
                    'user_name' => $v['user_name'],
                    'user_pwd' => $v['user_pwd'],
                    'user_qq' => $v['user_qq'],
                    'user_email' => $v['user_email'],
                    'user_status' => $v['user_status'],
                    'user_portrait' => $v['user_face'],
                    'user_points' => $v['user_score'],
                    'user_reg_time' => $v['user_jointime'],
                    'user_login_time' => $v['user_logtime'],
                    'user_login_num' => $v['user_lognum'],
                    'user_login_ip' => ip2long($v['user_logip']),
                ];
            }
            if (!empty($data)) {
                Db::name('user')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在导入视频专题数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('special')->select();
        $data=[];
        foreach($list as $k=>$v){
            $data[$k] = [
                'topic_id'=>$v['special_id'],
                'topic_name'=>$v['special_name'],
                'topic_en'=> $v['special_ename'],
                'topic_tpl'=>$v['special_skin'],
                'topic_pic'=>$v['special_banner'],
                'topic_content'=>$v['special_content'],
                'topic_key'=>$v['special_keywords'],
                'topic_des'=>$v['special_description'],
                'topic_title'=>$v['special_title'],
                'topic_status'=>$v['special_status'],
                'topic_level'=>$v['special_stars'],
                'topic_up'=>$v['special_up'],
                'topic_down'=>$v['special_down'],
                'topic_score'=>$v['special_gold'],
                'topic_score_all'=>$v['special_gold'] * $v['special_golder'],
                'topic_score_num'=>$v['special_golder'],
                'topic_hits'=>$v['special_hits'],
                'topic_hits_day'=>$v['special_hits_day'],
                'topic_hits_week'=>$v['special_hits_week'],
                'topic_hits_month'=>$v['special_hits_month'],
                'topic_time_add'=>$v['special_addtime'],
                'topic_time'=>$v['special_addtime']
            ];
        }
        if(!empty($data)){
            Db::name('topic')->insertAll($data);
        }
        unset($list,$data);


        echo '<script>showmessage(\'正在导入分类数据，请稍候......\');</script>';
        ob_flush();flush();
        $list = $this->db->name('list')->select();
        $data=[];
        $types=[];
        foreach($list as $k=>$v){
            $data[] = [
                'type_id'=>$v['list_id'],
                'type_name'=>$v['list_name'],
                'type_en'=> $v['list_dir'],
                'type_mid'=>$v['list_sid'],
                'type_pid'=>$v['list_pid'],
                'type_sort'=>$v['list_oid'],
                'type_status'=>$v['list_status'],
                'type_tpl'=>'type.html',
                'type_tpl_list'=>'show.html',
                'type_tpl_detail'=>'detail.html',
                'type_tpl_play'=>'play.html',
                'type_key'=>$v['list_keywords'],
                'type_des'=>$v['list_description'],
                'type_title'=>$v['list_title']
            ];
            $types[$v['list_id']] = $v['list_pid'];
        }
        if(!empty($data)){
            Db::name('type')->insertAll($data);
        }
        unset($list,$data);



        echo '<script>showmessage(\'正在分批次导入文章数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('news')->page($p, $this->ps)->select();
            if(empty($list)){
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
                $data[] = [
                    'art_id' => $v['news_id'],
                    'art_name' => $v['news_name'],
                    'art_en' => $v['news_ename'],
                    'art_letter' => $v['news_letter'],
                    'art_color' => $v['news_color'],
                    'art_author' => $v['news_inputer'],
                    'art_tag' => $v['news_keywords'],
                    'art_pic' => $v['news_pic'],
                    'type_id' => $v['news_cid'],
                    'type_id_1' => $types[$v['news_cid']],
                    'art_status' => $v['news_status'],
                    'art_jumpurl' => $v['news_jumpurl'],
                    'art_up' => $v['news_up'],
                    'art_down' => $v['news_down'],
                    'art_hits' => $v['news_hits'],
                    'art_hits_day' => $v['news_hits_day'],
                    'art_hits_week' => $v['news_hits_week'],
                    'art_hits_month' => $v['news_hits_month'],
                    'art_time_add' => $v['news_addtime'],
                    'art_time' => $v['news_addtime'],
                    'art_remarks' => $v['news_remark'],
                    'art_content' => $v['news_content'],

                ];

            }
            if (!empty($data)) {
                Db::name('art')->insertAll($data);
            }
            unset($list, $data);
        }


        echo '<script>showmessage(\'正在分批次导入视频数据，请稍候......\');</script>';
        ob_flush();flush();
        for($p=1;$p<1000;$p++) {
            $list = $this->db->name('vod')->page($p, $this->ps)->select();
            if (empty($list)) {
                break;
            }
            echo '<script>showmessage(\'+'.count($list).'条......\');</script>';
            ob_flush();flush();

            $data = [];
            foreach ($list as $k => $v) {
            	$url = $v['vod_url'];
            	$url = str_replace( ["\r\n","\r","\n"] ,'#',$url);
                $url = str_replace( "###",'#',$url);
                $url = str_replace( "##",'#',$url);

                $data[] = [
                    'vod_id' => $v['vod_id'],
                    'vod_name' => $v['vod_name'],
                    'vod_en' => $v['vod_ename'],
                    'vod_sub' => $v['vod_title'],
                    'vod_letter' => $v['vod_letter'],
                    'vod_color' => $v['vod_color'],
                    'vod_pic' => $v['vod_pic'],
                    'vod_pic_thumb' => $v['vod_pic_bg'],
                    'vod_pic_slide' => $v['vod_pic_slide'],
                    'vod_actor' => $v['vod_actor'],
                    'vod_director' => $v['vod_director'],
                    'vod_class' => $v['vod_type'],
                    'vod_tag' => $v['vod_keywords'],
                    'vod_area' => $v['vod_area'],
                    'vod_lang' => $v['vod_language'],
                    'vod_year' => $v['vod_year'],
                    'type_id' => $v['vod_cid'],
                    'type_id_1' => $types[$v['vod_cid']],
                    'vod_status' => $v['vod_status'],
                    'vod_serial' => $v['vod_continu'],
                    'vod_total' => $v['vod_total'],
                    'vod_level' => $v['vod_stars'],
                    'vod_hits' => $v['vod_hits'],
                    'vod_hits_day' => $v['vod_hits_day'],
                    'vod_hits_week' => $v['vod_hits_week'],
                    'vod_hits_month' => $v['vod_hits_month'],
                    'vod_duration' => $v['vod_length'],
                    'vod_up' => $v['vod_up'],
                    'vod_down' => $v['vod_down'],
                    'vod_score' => $v['vod_gold'],
                    'vod_score_all' => $v['vod_gold'] * $v['vod_golder'],
                    'vod_score_num' => $v['vod_golder'],
                    'vod_douban_id' => $v['vod_douban_id'],
                    'vod_douban_score' => $v['vod_douban_score'],
                    'vod_time_add' => $v['vod_addtime'],
                    'vod_time' => $v['vod_addtime'],
                    'vod_content' => $v['vod_content'],
                    'vod_play_from' => $v['vod_play'],
                    'vod_play_url' => $url,
                ];

            }
            if (!empty($data)) {
                Db::name('vod')->insertAll($data);
            }
            unset($list, $data);
        }
    }

    /**
     * 第五步：数据库安装
     * @return mixed
     */
    private function step5()
    {
        $account = input('post.account');
        $password = input('post.password');
        $install_dir = input('post.install_dir');

        $config = include APP_PATH.'database.php';
        if (empty($config['hostname']) || empty($config['database']) || empty($config['username'])) {
            return $this->error('请先点击测试数据库连接！');
        }
        if (empty($account) || empty($password)) {
            return $this->error('请填写管理账号和密码！');
        }

        $rule = [
            'account|管理员账号' => 'require|alphaNum',
            'password|管理员密码' => 'require|length:6,20',
        ];
        $validate = $this->validate(['account' => $account, 'password' => $password], $rule);
        if (true !== $validate) {
            return $this->error($validate);
        }
        if(empty($install_dir)) {
            $install_dir='/';
        }
        // 更新程序配置文件
        if($install_dir!='/') {
            $config_new = config('maccms');
            $config_new['site']['install_dir'] = $install_dir;
            $cofnig_new['app']['cache_flag'] = substr(md5(time()),0,10);
            $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $config_new);
            if ($res === false) {
                return $this->error('配置文件保存失败，请重试!');
            }
        }


        // 导入系统初始数据库结构
        // 导入SQL
        $sql_file = APP_PATH.'install/sql/install.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $sql_list = mac_parse_sql($sql, 0, ['mac_' => $config['prefix']]);
            if ($sql_list) {
                $sql_list = array_filter($sql_list);
                foreach ($sql_list as $v) {
                    try {
                        Db::execute($v);
                    } catch(\Exception $e) {
                        return $this->error('导入SQL失败，请检查install.sql的语句是否正确。'. $e);
                    }
                }
            }
        }
        // 注册管理员账号
        $data = [
            'admin_name' => $account,
            'admin_pwd' => $password,
            'admin_status' =>1,
        ];
        $res = model('Admin')->saveData($data);
        if (!$res['code']>1) {
            return $this->error('管理员账号设置失败:'.$res['msg']);
        }
        file_put_contents(APP_PATH.'data/install/install.lock', date('Y-m-d H:i:s'));

        // 获取站点根目录
        $root_dir = request()->baseFile();
        $root_dir  = preg_replace(['/install.php$/'], [''], $root_dir);
        return $this->success('系统安装成功，欢迎您使用苹果CMS建站', $root_dir.'admin.php');
    }
    
    /**
     * 环境检测
     * @return array
     */
    private function checkNnv()
    {
        $items = [
            'os'      => ['操作系统', '不限制', 'Windows/Unix', PHP_OS, 'ok'],
            'php'     => ['PHP版本', '5.5', '5.5及以上', PHP_VERSION, 'ok'],
            'gd'      => ['GD库', '2.0', '2.0及以上', '未知', 'ok'],

        ];
        if ($items['php'][3] < $items['php'][1]) {
            $items['php'][4] = 'no';
            session('install_error', true);
        }
        $tmp = function_exists('gd_info') ? gd_info() : [];
        if (empty($tmp['GD Version'])) {
            $items['gd'][3] = '未安装';
            $items['gd'][4] = 'no';
            session('install_error', true);
        } else {
            $items['gd'][3] = $tmp['GD Version'];
        }

        return $items;
    }
    
    /**
     * 目录权限检查
     * @return array
     */
    private function checkDir()
    {
        $items = [
            ['dir', './application', '读写', '读写', 'ok'],
            ['dir', './application/extra', '读写', '读写', 'ok'],
            ['dir', './application/data/', '读写', '读写', 'ok'],
            ['dir', './application/data/config', '读写', '读写', 'ok'],
            ['dir', './application/data/backup', '读写', '读写', 'ok'],
            ['dir', './application/data/update', '读写', '读写', 'ok'],
            ['file', './application/database.php', '读写', '读写', 'ok'],
            ['dir', './runtime', '读写', '读写', 'ok'],
            ['dir', './upload', '读写', '读写', 'ok'],

        ];
        foreach ($items as &$v) {
            if ($v[0] == 'dir') {// 文件夹
                if(!is_writable($v[1])) {
                    if(is_dir($v[1])) {
                        $v[3] = '不可写';
                        $v[4] = 'no';
                    } else {
                        $v[3] = '不存在';
                        $v[4] = 'no';
                    }
                    session('install_error', true);
                }
            } else {// 文件
                if(!is_writable($v[1])) {
                    $v[3] = '不可写';
                    $v[4] = 'no';
                    session('install_error', true);
                }
            }
        }
        return $items;
    }
    
    /**
     * 函数及扩展检查
     * @return array
     */
    private function checkFunc()
    {
        $items = [
            ['pdo', '支持', 'yes', '类'],
            ['pdo_mysql', '支持', 'yes', '模块'],
            ['zip', '支持', 'yes', '模块'],
            ['fileinfo', '支持', 'yes', '模块'],
            ['curl', '支持', 'yes', '模块'],
            ['xml', '支持', 'yes', '函数'],
            ['file_get_contents', '支持', 'yes', '函数'],
            ['mb_strlen', '支持', 'yes', '函数'],
            ['gzopen', '支持', 'yes', '函数'],
        ];

        if(version_compare(PHP_VERSION,'5.6.0','ge') && version_compare(PHP_VERSION,'5.7.0','lt')){
            $items[] = ['always_populate_raw_post_data','支持','yes','配置'];
        }

        foreach ($items as &$v) {
            if(('类'==$v[3] && !class_exists($v[0])) || ('模块'==$v[3] && !extension_loaded($v[0])) || ('函数'==$v[3] && !function_exists($v[0])) || ('配置'==$v[3] && ini_get('always_populate_raw_post_data')!=-1)) {
                $v[1] = '不支持';
                $v[2] = 'no';
                session('install_error', true);
            }
        }

        return $items;
    }
    
    /**
     * 生成数据库配置文件
     * @return array
     */
    private function mkDatabase(array $data)
    {
        $code = <<<INFO
<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
return [
    // 数据库类型
    'type'            => 'mysql',
    // 服务器地址
    'hostname'        => '{$data['hostname']}',
    // 数据库名
    'database'        => '{$data['database']}',
    // 用户名
    'username'        => '{$data['username']}',
    // 密码
    'password'        => '{$data['password']}',
    // 端口
    'hostport'        => '{$data['hostport']}',
    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [],
    // 数据库编码默认采用utf8
    'charset'         => 'utf8',
    // 数据库表前缀
    'prefix'          => '{$data['prefix']}',
    // 数据库调试模式
    'debug'           => false,
    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'deploy'          => 0,
    // 数据库读写是否分离 主从式有效
    'rw_separate'     => false,
    // 读写分离后 主服务器数量
    'master_num'      => 1,
    // 指定从服务器序号
    'slave_no'        => '',
    // 是否严格检查字段是否存在
    'fields_strict'   => false,
    // 数据集返回类型
    'resultset_type'  => 'array',
    // 自动写入时间戳字段
    'auto_timestamp'  => false,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 是否需要进行SQL性能分析
    'sql_explain'     => false,
    // Builder类
    'builder'         => '',
    // Query类
    'query'           => '\\think\\db\\Query',
];
INFO;
        file_put_contents(APP_PATH.'database.php', $code);
        // 判断写入是否成功
        $config = include APP_PATH.'database.php';
        if (empty($config['database']) || $config['database'] != $data['database']) {
            return $this->error('[application/database.php]数据库配置写入失败！');
            exit;
        }
    }
}