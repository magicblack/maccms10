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
        // 资源检测筛选（0 未检测 / 1 正常 / 2 检测失败 / 3 已自动停用 / 4 管理员停用）；字段由升级脚本补，未升级时不筛选
        if (\app\common\util\ContentCheck::hasColumns('art') && in_array($param['check'] ?? '', ['0', '1', '2', '3', '4'], true)) {
            $where['art_check_state'] = ['eq', intval($param['check'])];
        }
        if (!empty($param['type'])) {
            $where['type_id|type_id_1'] = ['eq', $param['type']];
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
            $param['wd'] = mac_filter_xss($param['wd']);
            $like = mac_search_wd_like($param['wd']);
            if ($like) {
                $where['art_name'] = $like;
            }
        }
        if (!empty($param['recycle'])) {
            $where['art_recycle_time'] = ['>', 0];
        }

        if(!empty($param['repeat'])){
            if($param['page'] ==1){
                Db::execute('DROP TABLE IF EXISTS '.config('database.prefix').'tmpart');
                Db::execute('CREATE TABLE `'.config('database.prefix').'tmpart` (`id1` int unsigned DEFAULT NULL, `name1` varchar(1024) NOT NULL DEFAULT \'\') ENGINE=MyISAM');
                Db::execute('INSERT INTO `'.config('database.prefix').'tmpart` (SELECT min(art_id)as id1,art_name as name1 FROM '.config('database.prefix').'art WHERE art_recycle_time = 0 GROUP BY name1 HAVING COUNT(name1)>1)');
            }
            $order='art_name asc';
            $res = model('Art')->listRepeatData($where,$order,$param['page'],$param['limit']);
        }
        else{
            $order='art_time desc';
            $res = model('Art')->listData($where,$order,$param['page'],$param['limit']);
        }

        $artIds = array_column($res['list'], 'art_id');
        $artSeoStatusMap = [];
        if (!empty($artIds)) {
            $seoRows = Db::name('seo_ai_result')
                ->field('seo_obj_id,seo_status')
                ->where('seo_mid', 2)
                ->where('seo_obj_id', 'in', $artIds)
                ->where('seo_status', 'in', [1, 2])
                ->select();
            foreach ((array)$seoRows as $seoRow) {
                $artSeoStatusMap[intval($seoRow['seo_obj_id'])] = intval($seoRow['seo_status']);
            }
        }

        foreach($res['list'] as $k=>&$v){
            $v['ismake'] = 1;
            if($GLOBALS['config']['view']['art_detail'] >0 && $v['art_time_make'] < $v['art_time']){
                $v['ismake'] = 0;
            }
            $v['seo_ai_status'] = isset($artSeoStatusMap[$v['art_id']]) ? intval($artSeoStatusMap[$v['art_id']]) : 0;
        }

        // 未升级站点缺 check_* 字段：补默认值，列表模板直接读取
        \app\common\util\ContentCheck::fillDefaults('art', $res['list']);
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree', $type_tree);

        $this->assign('title', lang('admin/art/title'));
        return $this->fetch('admin@art/index');
    }

    public function batch()
    {
        $param = input();
        if (!empty($param)) {

            mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

            if(empty($param['ck_del']) && empty($param['ck_level']) && empty($param['ck_status']) && empty($param['ck_lock']) && empty($param['ck_hits']) && empty($param['ck_type']) && empty($param['ck_replace']) ){
                return $this->error(lang('param_err'));
            }
            $where = $this->artBatchFilterWhere($param);
            if($param['ck_del'] == 1){
                $res = model('Art')->recycleData($where);
                mac_echo($res['code'] == 1 ? lang('recycle_ok') : $res['msg']);
                mac_jump( url('art/batch') ,3);
                exit;
            }
            if($param['ck_del'] == 4){
                $res = model('Art')->delData($where);
                mac_echo(lang('multi_del_ok'));
                mac_jump( url('art/batch') ,3);
                exit;
            }

            // 批量改分类前先校验目标分类：必须存在且属于文章模块(type_mid=2)。
            // 批量入口一次能改整批数据，不能只信前端下拉框——表单可被直接构造，
            // 写进不存在或跨模块的 type_id 后，前台按分类取数会整批丢失。
            // 这里取一次缓存即可，不必在每行循环里重复取。
            $batch_type_id = 0;
            $batch_type_pid = 0;
            // 判定与 Vod.php 的批量分类入口保持同形：val_type 缺字段（构造表单只提交
            // ck_type）时不能直接取下标，PHP 7.0 下会报 Undefined index notice
            if(!empty($param['ck_type']) && !empty($param['val_type'])){
                $batch_type_id = intval($param['val_type']);
                $type_list = model('Type')->getCache();
                if($batch_type_id < 1 || !isset($type_list[$batch_type_id]) || intval($type_list[$batch_type_id]['type_mid']) != 2){
                    return $this->error(lang('select_type_must'));
                }
                $batch_type_pid = intval($type_list[$batch_type_id]['type_pid']);
            }

            if(empty($param['page'])){
                $param['page'] = 1;
            }
            if(empty($param['limit'])){
                $param['limit'] = 100;
            }
            if(empty($param['total'])) {
                $param['total'] = model('Art')->countData($where);
                $param['page_count'] = ceil($param['total'] / $param['limit']);
            }

            if($param['page'] > $param['page_count']) {
                mac_echo(lang('multi_set_ok'));
                mac_jump( url('art/batch') ,3);
                exit;
            }
            mac_echo( "<font color=red>".lang('admin/batch_tip',[$param['total'],$param['limit'],$param['page_count'],$param['page']])."</font>");

            $page = $param['page_count'] - $param['page'] + 1;
            $order='art_id desc';
            $res = model('Art')->listData($where,$order,$page,$param['limit']);

            foreach($res['list'] as  $k=>$v){
                $where2 = [];
                $where2['art_id'] = $v['art_id'];

                $update = [];
                $des = $v['art_id'].','.$v['art_name'];

                if(!empty($param['ck_level']) && !empty($param['val_level'])){
                    $update['art_level'] = $param['val_level'];
                    $des .= '&nbsp;'.lang('level').'：'.$param['val_level'].'；';
                }
                if(!empty($param['ck_status']) && isset($param['val_status'])){
                    $update['art_status'] = $param['val_status'];
                    $des .= '&nbsp;'.lang('status').'：'.($param['val_status'] ==1 ? '['.lang('reviewed').']':'['.lang('reviewed_not').']') .'；';
                }
                if(!empty($param['ck_lock']) && isset($param['val_lock'])){
                    $update['art_lock'] = $param['val_lock'];
                    $des .= '&nbsp;'.lang('lock').'：'.($param['val_lock']==1 ? '['.lang('lock').']':'['.lang('unlock').']').'；';
                }
                if(!empty($param['ck_hits']) && !empty($param['val_hits_min']) && !empty($param['val_hits_max']) ){
                    $update['art_hits'] = rand($param['val_hits_min'],$param['val_hits_max']);
                    $des .= '&nbsp;'.lang('hits').'：'.$update['art_hits'].'；';
                }

                // 新增：批量修改分类
                if($batch_type_id > 0){
                    $update['type_id'] = $batch_type_id;
                    $update['type_id_1'] = $batch_type_pid;
                    $des .= '&nbsp;'.lang('type').'：'.$batch_type_id.'；';
                }

                // 新增：批量替换功能
                if(!empty($param['ck_replace']) && !empty($param['replace_field']) && isset($param['replace_search'])){
                    $field = $param['replace_field'];
                    $replaceres = $this->batch_replace($field,$v,$param['replace_search'],$param['replace_with'],'art');
                    if(isset($replaceres[$field])) $update[$field] = $replaceres[$field];

                    if(!empty($replaceres['des'])) $des .= $replaceres['des'];
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

        $this->assign('title',lang('admin/art/title'));
        return $this->fetch('admin@art/batch');
    }

    private function artBatchFilterWhere(&$param)
    {
        $where = [];
        if (!empty($param['type'])) {
            $where['type_id'] = ['eq', $param['type']];
        }
        if (!empty($param['level'])) {
            $where['art_level'] = ['eq', $param['level']];
        }
        if (in_array($param['status'] ?? '', ['0', '1'])) {
            $where['art_status'] = ['eq', $param['status']];
        }
        if (!empty($param['lock'])) {
            $where['art_lock'] = ['eq', $param['lock']];
        }
        if (!empty($param['pic'])) {
            if ($param['pic'] == '1') {
                $where['art_pic'] = ['eq', ''];
            } elseif ($param['pic'] == '2') {
                $where['art_pic'] = ['like', 'http%'];
            } elseif ($param['pic'] == '3') {
                $where['art_pic'] = ['like', '%#err%'];
            }
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $like = mac_search_wd_like($param['wd']);
            if ($like) {
                $where['art_name'] = $like;
            }
        }
        if (!empty($param['recycle'])) {
            $where['art_recycle_time'] = ['>', 0];
        }
        return $where;
    }

    public function exportData()
    {
        $param = input();
        $where = $this->artBatchFilterWhere($param);
        $this->base_export($param,'art',$where);
    }

    public function importData()
    {
        $this->base_import('art');
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $contentLang = $param['content_lang'] ?? [];
            unset($param['content_lang']);
            $res = model('Art')->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            $artId = (int)($res['art_id'] ?? 0);
            if ($artId > 0 && is_array($contentLang)) {
                //默认语言以原始行为准，只写非默认语言的译文（见 mac_content_lang_overlay 注释）
                $defaultLang = mac_content_lang_default();
                foreach ($contentLang as $langCode => $fields) {
                    if ((string)$langCode === $defaultLang || !is_array($fields)) {
                        continue;
                    }
                    //页内容是分页数组，与默认语言一样以 $$$ 拼接成单个字符串再存
                    if (isset($fields['art_content']) && is_array($fields['art_content'])) {
                        $fields['art_content'] = join('$$$', $fields['art_content']);
                        $fields['art_title'] = join('$$$', (array)($fields['art_title'] ?? []));
                        $fields['art_note'] = join('$$$', (array)($fields['art_note'] ?? []));
                    }
                    model('ContentLang')->saveFields('art', $artId, (string)$langCode, $fields, 'manual', 1);
                }
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['art_id'] = ['eq',$id];
        $where['_recycle'] = 'all';
        $res = model('Art')->infoData($where);

        $info = $res['info'];
        $this->assign('info',$info);
        $this->assign('art_page_list',(array)$info['art_page_list']);
        $seoAiStatus = 0;
        if (!empty($info['art_id'])) {
            $seoAi = model('SeoAiResult')->getByObject(2, intval($info['art_id']));
            if (!empty($seoAi)) {
                $seoAiStatus = intval($seoAi['seo_status']);
            }
        }
        $this->assign('seo_ai_status', $seoAiStatus);

        $type_tree = model('Type')->getCache('type_tree');
        $this->assign('type_tree',$type_tree);

        //内容级多语言：默认语言永远排第一位（即便后台没勾选它），其后跟已启用的其它语言
        $content_lang_default = mac_content_lang_default();
        $content_lang_enabled = array_values(array_unique(array_merge([$content_lang_default], mac_content_lang_allow_list())));
        $content_lang_data = [];
        $content_lang_page_list = [];
        foreach ($content_lang_enabled as $langCode) {
            if ($langCode !== $content_lang_default) {
                $content_lang_page_list[$langCode] = [];
            }
        }
        if (!empty($info['art_id'])) {
            foreach ($content_lang_enabled as $langCode) {
                $fields = model('ContentLang')->getFields('art', $info['art_id'], $langCode);
                $content_lang_data[$langCode] = $fields;
                if ($langCode !== $content_lang_default) {
                    $content_lang_page_list[$langCode] = mac_art_list(
                        $fields['art_title'] ?? '',
                        $fields['art_note'] ?? '',
                        $fields['art_content'] ?? ''
                    );
                }
            }
            //默认语言分页列表已由 Art::infoData() 按原始行算好并 assign，这里只处理其它语言

            //其它语言分页数应跟默认语言保持一致，尚未翻译的页用空白页补齐，避免切换语言tab时页数对不上
            $pageTotal = count((array)$info['art_page_list']);
            foreach ($content_lang_enabled as $langCode) {
                if ($langCode === $content_lang_default) {
                    continue;
                }
                for ($i = count($content_lang_page_list[$langCode] ?? []) + 1; $i <= $pageTotal; $i++) {
                    $content_lang_page_list[$langCode][$i] = ['page' => $i, 'title' => '', 'note' => '', 'content' => ''];
                }
            }
        }
        $this->assign('content_lang_default', $content_lang_default);
        $this->assign('content_lang_enabled', $content_lang_enabled);
        $this->assign('content_lang_multi', count($content_lang_enabled) > 1);
        $this->assign('content_lang_data', $content_lang_data);
        $this->assign('content_lang_page_list', $content_lang_page_list);
        $this->assign('content_translate_available', mac_content_translate_available());

        $this->assign('title',lang('admin/art/title'));
        return $this->fetch('admin@art/info');
    }

    /**
     * 调用已安装的翻译插件（若有）翻译单个字段；没有插件注册时返回空字符串，前端据此禁用按钮。
     */
    public function contentLangTranslate()
    {
        $text = (string)input('post.text', '');
        $from = (string)input('post.from', mac_content_lang_default());
        $to = (string)input('post.to', '');
        if ($text === '' || $to === '') {
            return $this->error(lang('param_err'));
        }
        if (!mac_content_translate_rate_limit($this->_admin['admin_id'])) {
            return $this->error(lang('frequently'));
        }
        $result = mac_content_translate($text, $from, $to, ['content_type' => 'art']);
        if ($result === '') {
            return $this->error(lang('admin/vod/content_lang_translate_err'));
        }
        return json(['code' => 1, 'msg' => 'ok', 'data' => $result]);
    }

    public function aiSeoGenerate()
    {
        if (!Request()->isPost()) {
            return json(['code' => 0, 'msg' => lang('illegal_request'), 'data' => []]);
        }
        $id = intval(input('post.id'));
        if ($id <= 0) {
            return json(['code' => 0, 'msg' => lang('param_err'), 'data' => []]);
        }
        try {
            $res = \app\common\util\SeoAi::generateByMidObj(2, $id);
        } catch (\Exception $e) {
            \think\Log::error('AI SEO generate failed (art_id=' . $id . '): ' . $e->getMessage());
            return json(['code' => 0, 'msg' => $e->getMessage(), 'data' => []]);
        }
        if (empty($res['code']) || intval($res['code']) !== 1) {
            return json(['code' => 0, 'msg' => isset($res['msg']) ? $res['msg'] : lang('save_err'), 'data' => []]);
        }
        return json(['code' => 1, 'msg' => lang('save_ok'), 'data' => isset($res['data']) ? $res['data'] : []]);
    }

    public function restore()
    {
        $param = input();
        $ids = $param['ids'];
        if (empty($ids)) {
            return $this->error(lang('param_err'));
        }
        $where = ['art_id' => ['in', $ids]];
        $res = model('Art')->restoreData($where);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg']);
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];
        $purge = !empty($param['purge']);

        if(!empty($ids)){
            $where=[];
            $where['art_id'] = ['in',$ids];
            if ($purge) {
                $res = model('Art')->delData($where);
            } else {
                $res = model('Art')->recycleData($where);
            }
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
            return $this->error(lang('select_type_must'));
        }
        // 与 batch() 同一套白名单：目标分类必须存在且属于文章模块(type_mid=2)。
        // field() 同样是一次改整批数据的入口，表单可被直接构造，写进不存在或跨模块的
        // type_id 后前台按分类取数会整批丢失。取一次缓存供下面复用。
        $type_pid = 0;
        if($col == 'type_id'){
            $type_list = model('Type')->getCache();
            $type_id = intval($val);
            if($type_id < 1 || !isset($type_list[$type_id]) || intval($type_list[$type_id]['type_mid']) != 2){
                return $this->error(lang('select_type_must'));
            }
            $type_pid = intval($type_list[$type_id]['type_pid']);
        }

        if(!empty($ids) && in_array($col,['art_status','art_lock','art_level','art_hits','type_id'])){
            $where=[];
            $where['art_id'] = ['in',$ids];
            $update = [];
            if(empty($start)) {
                $update[$col] = $val;
                if($col == 'type_id'){
                    $update['type_id_1'] = $type_pid;
                }
                $res = model('Art')->fieldData($where, $update);
            }
            else{
                if(empty($end)){$end = 9999;}
                $ids = explode(',',$ids);
                foreach($ids as $k=>$v){
                    $val = rand($start,$end);
                    $where['art_id'] = ['eq',$v];
                    $update[$col] = $val;
                    $res = model('Art')->fieldData($where, $update);
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
        $res = model('Art')->updateToday($flag);
        return json($res);
    }

}
