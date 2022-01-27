<?php
namespace app\admin\controller;
use think\Db;
use app\common\util\Collection as cjOper;

class Cj extends Base
{
    var $_isall=0;

    public function __construct()
    {
        parent::__construct();
    }

    //列表
    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <1 ? $this->_pagesize : $param['limit'];
        $where=[];

        $order='nodeid desc';
        $res = model('Cj')->listData('cj_node',$where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/cj/title'));

        return $this->fetch('admin@cj/index');
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input();
            $data = $param['data'];
            $data['urlpage'] = (string)$param['urlpage'.$data['sourcetype']];
            if(!empty($data['customize_config'])){
                $customize_config = $data['customize_config'];
                unset($data['customize_config']);
                foreach ($customize_config['name'] as $k => $v) {
                    if (empty($v) || empty($customize_config['name'][$k])) continue;
                    $data['customize_config'][] = array('name'=>$customize_config['name'][$k], 'en_name'=>$customize_config['en_name'][$k], 'rule'=>$customize_config['rule'][$k], 'html_rule'=>$customize_config['html_rule'][$k]);
                }
                $data['customize_config'] = json_encode($data['customize_config'],JSON_FORCE_OBJECT);
            }
            $res = model('Cj')->saveData($data);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['nodeid'] = ['eq',$id];
        $res = model('Cj')->infoData('cj_node',$where);
        if(!empty($res['info']['customize_config'])){
            $res['info']['customize_config'] = json_decode($res['info']['customize_config'],true);
        }
        $this->assign('data',$res['info']);
        $this->assign('title',lang('admin/cj/title'));
        return $this->fetch('admin@cj/info');
    }

    public function program()
    {
        $param = input();
        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }

        if (Request()->isPost()) {
            $program_config = [];
            foreach($param['model_field'] as $k=>$v){
                if(!empty($param['node_field'][$k])){
                    $program_config['map'][$v] = $param['node_field'][$k];
                    $program_config['funcs'][$v] = $param['funcs'][$k];
                }
            }
            $update=[];
            $update['nodeid'] = $param['id'];
            $update['program_config'] = json_encode($program_config);
            $res = model('Cj')->saveData($update);
            if($res['code']>1){
                return $this->error(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $program_config = [];
        if(!empty($res['info']['program_config'])){
            $program_config = json_decode($res['info']['program_config'],true);
        }
        $this->assign('program_config',$program_config);


        $node_field = array('title'=>lang('title'),'type'=>lang('type'), 'content'=>lang('content'));
        $customize_config = [];
        if(!empty($res['info']['customize_config'])){
            $customize_config = json_decode($res['info']['customize_config'],true);
        }

        if (is_array($customize_config)) foreach ($customize_config as $k=>$v) {
            if (empty($v['en_name']) || empty($v['name'])) continue;
            $node_field[$v['en_name']] = $v['name'];
        }
        $this->assign('node_field',$node_field);

        $table = 'vod';
        if($res['info']['mid'] =='2'){
            $table='art';
        }
        $column_list = Db::query('SHOW COLUMNS FROM '.config('database.prefix').$table);
        $this->assign('column_list',$column_list);
        $this->assign('param',$param);
        return $this->fetch('admin@cj/program');
    }

    public function col_all($param)
    {
        $this->_isall=1;
        $this->col_url($param);
    }


    //采集网址
    public function col_url($param=[]) {
        if(empty($param)){
            $param = input();
        }

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $data = $res['info'];
        $collection = new cjOper();
        $urls = $collection->url_list($data);

        $total_page = count($urls);
        if (empty($total_page)){
            return $this->error(lang('admin/cj/url_list_err'));
        }

        $param['page'] = isset($param['page']) ? intval($param['page']) : 1;

        $url_list = $urls[$param['page']-1];
        $url = $collection->get_url_lists($url_list, $data);

        $total = count($url);
        $re = 0;
        if (is_array($url) && !empty($url)) {
            foreach ($url as $v) {
                if (empty($v['url']) || empty($v['title'])) {
                    $re++;
                    continue;
                }
                $v['title'] = strip_tags($v['title']);
                $md5 = md5($v['url']);
                $where=[];
                $where['md5'] = $md5;
                $history = model('Cj')->infoData('cj_history',$where);
                if($history['code']>1){
                    Db::name('cj_history')->insert(array('md5' => $md5));
                    Db::name('cj_content')->insert(array('nodeid'=>$param['id'], 'status'=>1, 'url'=>$v['url'], 'title'=>$v['title']));
                }
                else {
                    $re++;
                }
            }
        }
        if ($total_page <= $param['page']) {
            $time = time();
            Db::name('cj_node')->where('nodeid',$param['id'])->update(array('lastdate' => $time));
        }
        if($this->_isall==1){
            mac_echo(lang('admin/cj/url_cj_complete'));
            $this->col_content($param);
            exit;
        }
        $this->assign('param',$param);
		$this->assign('url_list', $url_list);
		$this->assign('total_page', $total_page);
		$this->assign('re', $re);
		$this->assign('url', $url);
		$this->assign('page',$param['page']);
		$this->assign('total',$total);
        $this->assign('title',lang('admin/cj/url/title'));
        if($total_page > $param['page']){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/col_url') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            mac_echo(lang('admin/cj/url_cj_complete'));
        }
        return $this->fetch('admin@cj/col_url');
    }

    //采集文章
    public function col_content($param=[]) {
        if(empty($param)){
            $param = input();
        }

        $collection = new cjOper();
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $total = isset($_GET['total']) ? intval($_GET['total']) : 0;

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $data = $res['info'];

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');

        if(empty($total)){
            $total = Db::name('cj_content')->where('nodeid',$param['id'])->where('status',1)->count();
        }
        $limit = 20;
        $total_page = ceil($total/$limit);
        mac_echo(lang('admin/cj/content/tip',[$total,$total_page,$limit,$page]));

        $list = Db::name('cj_content')->where('nodeid',$param['id'])->where('status',1)->page($total_page-1,$limit)->select();

        $i = 0;
        $ids=[];
        if(!empty($list) && is_array($list)){
            foreach($list as $v){
                $html = $collection->get_content($v['url'],$data);
                Db::name('cj_content')->where('id',$v['id'])->update(['status'=>2, 'data'=>json_encode($html)]);
                $ids[] = $v['id'];
                $i++;

                mac_echo($v['url'].'&nbsp;&nbsp;'.'ok');
            }
        }
        else{
            mac_echo(lang('admin/cj/content_cj_complete'));
            exit;
        }

        if($this->_isall==1){
            mac_echo(lang('admin/cj/content_cj_complete'));
            $param['ids'] = implode(',',$ids);
            $param['limit'] = 999;
            $this->content_into($param);
            exit;
        }

        if ($total_page > $page){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/col_content') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            $time = time();
            Db::name('cj_node')->where('nodeid',$param['id'])->update(array('lastdate' => $time));
            mac_echo(lang('admin/cj/cj_complete'));
            exit;
        }
    }


    public function publish()
    {
        $param = input();

        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <20 ? $this->_pagesize : $param['limit'];
        $where=[];
        $where['nodeid'] = $param['id'];
        if(!empty($param['status'])){
            $where['status'] = ['eq',$param['status']];
        }

        $order='id desc';
        $res = model('Cj')->listData('cj_content',$where,$order,$param['page'],$param['limit']);

        $this->assign('list',$res['list']);
        $this->assign('total',$res['total']);
        $this->assign('page',$res['page']);
        $this->assign('limit',$res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign('title',lang('admin/cj/publish/title'));

        return $this->fetch('admin@cj/publish');
    }

    public function show()
    {
        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $info = Db::name('cj_content')->where($where)->find();
        if(!empty($info['data'])){
            $info['data'] = @json_decode($info['data'],true);
        }
        $this->assign('info',$info);
        $this->assign('title',lang('admin/cj/title'));
        return $this->fetch('admin@cj/show');

    }

    public function content_del()
    {
        $param = input();
        $ids = $param['ids'];
        $all = $param['all'];

        if(!empty($ids)){
            $where=[];
            $where['id'] = ['in',$ids];
            if($all=='1'){
                $where['id'] = ['gt',0];
            }
            $urls = [];
            $list = Db::name('cj_content')->field('url')->where($where)->select();
            foreach ($list as $k => $v) {
                $md5 = md5($v['url']);
                $urls[] = $md5;
            }

            $where2=[];
            $where2['md5'] = ['in',$md5];
            Db::name('cj_history')->where($where2)->delete();

            $res = Db::name('cj_content')->where($where)->delete();
            if($res===false){
                return $this->error(lang('del_err').''.$this->getError());
            }
        }
        return $this->success(lang('del_ok'));
    }

    public function content_into($param=[])
    {
        if(empty($param)){
            $param = input();
        }

        $nodeid = $param['id'];
        $ids = $param['ids'];
        $all = $param['all'];
        $param['page'] = intval($param['page']) <1 ? 1 : $param['page'];
        $param['limit'] = intval($param['limit']) <20 ? $this->_pagesize : $param['limit'];

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $node = $res['info'];
        $where=[];
        $where['nodeid'] = $nodeid;
        $where['status'] =['eq',2];
        $where['id'] = ['in',$ids];
        if($all=='1'){
            $where['id'] = ['gt',0];
        }

        mac_echo('<style type="text/css">body{font-size:12px;color: #333333;line-height:21px;}span{font-weight:bold;color:#FF0000}</style>');
        if(empty($param['total'])) {
            $param['total'] = Db::name('cj_content')->where($where)->count();
        }

        $list = Db::name('cj_content')->where($where)->page($param['page'],$param['limit'])->select();

        $total_page = ceil($param['total']/$param['limit']);
        mac_echo(lang('admin/cj/content_into/tip',[$param['total'],$total_page,$param['limit'],$param['page']]));

        $program_config =[];
        if(!empty($node['program_config'])){
            $program_config = json_decode($node['program_config'],true);
        }

        $inter = mac_interface_type();
        $update_ids = [];
        foreach($list as $k=>$v){
            $data=[];
            $content_data = json_decode($v['data'],true);
            foreach ($program_config['map'] as $a=>$b) {
                if (isset($program_config['funcs'][$a]) && function_exists($program_config['funcs'][$a])) {
                    $data['data'][$k][$a] = $program_config['funcs'][$a]($v['data'][$b]);
                }
                else {
                    $data['data'][$k][$a] = $content_data[$b];
                }
                if($b=='type' && !is_numeric($content_data[$b])) {

                    if($node['mid'] ==2 ) {
                        $data['data'][$k][$a] = $inter['arttype'][$content_data[$b]];
                    }
                    else{
                        $data['data'][$k][$a] = $inter['vodtype'][$content_data[$b]];
                    }
                }
            }


            if($node['mid'] == '2'){
                $res = model('Collect')->art_data([],$data,0);
            }
            else{
                $res = model('Collect')->vod_data([],$data,0);
            }
            if($res['code'] ==1){
                $update_ids[] = $v['id'];
            }
            mac_echo($res['msg']);
        }

        if(!empty($update_ids)){
            $where=[];
            $where['id'] = ['in',$update_ids];
            $res = Db::name('cj_content')->where($where)->update(['status' => 3]);
        }

        if($this->_isall==1){
            mac_echo(lang('admin/cj/content_into/complete'));
            exit;
        }


        if ($total_page > $param['page']){
            mac_echo(lang('server_rest'));
            $param['page'] ++;
            $link = url('cj/content_into') . '?'. http_build_query($param);
            mac_jump( $link ,3);
        }
        else{
            mac_echo(lang('import_ok'));
            exit;
        }
    }


    //序列网址测试
    public function show_url()
    {
        $param = input();
        $data = $param['data'];
        $data['urlpage'] = (string)$param['urlpage'.$data['sourcetype']];
        $collection = new cjOper();
        $urls = $collection->url_list($data);

        $this->assign('urls',$urls);

        return $this->fetch('admin@cj/show_url');
    }

    public function del()
    {
        $param = input();
        $ids = $param['ids'];

        if(!empty($ids)){
            $where=[];
            $where['nodeid'] = ['in',$ids];
            $res = model('Cj')->delData($where);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function export()
    {
        $param = input();

        $where=[];
        $where['nodeid'] = $param['id'];
        $res = model('Cj')->infoData('cj_node',$where);
        if($res['code']>1){
            return $this->error($res['msg']);
        }
        $node = $res['info'];

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_cj_" . urlencode($node['name']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_cj_" . $node['name'] . '.txt');
        }
        echo base64_encode(json_encode($node));
    }

    public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
            @unlink($info->getpathName());
            if($data){
                unset($data['nodeid']);
                $res = model('Cj')->saveData($data);
                if($res['code']>1){
                    return $this->success($res['msg']);
                }
                return $this->success($res['msg']);
            }
            return $this->success(lang('import_err'));
        }
        else{
            return $this->error($file->getError());
        }
    }
}
