<?php
namespace app\index\controller;
use think\Controller;
use think\Request;
use think\Db;

class Vod extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->label_fetch('vod/index');
    }

    public function type()
    {
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('vod',$info['type_tpl'],'type') );
    }

    public function show()
    {
        $this->check_show();
        $info = $this->label_type();
        return $this->label_fetch( mac_tpl_fetch('vod',$info['type_tpl_list'],'show') );
    }

    public function ajax_show()
    {
        $this->check_ajax();
        $this->check_show(1);
        $info = $this->label_type();
        return $this->label_fetch('vod/ajax_show');
    }

    public function search()
    {
        $param = mac_param_url();
        $this->check_search($param);
        $this->label_search($param);
        return $this->label_fetch('vod/search');
    }

    public function ajax_search()
    {
        $param = mac_param_url();
        $this->check_ajax();
        $this->check_search($param,1);
        $this->label_search($param);
        return $this->label_fetch('vod/ajax_search');
    }

    public function detail()
    {
        $info = $this->label_vod_detail();
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==2){
            return $this->label_fetch('vod/copyright');
        }
        if(!empty($info['vod_pwd']) && session('1-1-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/detail_pwd');
        }
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl'],'detail') );
    }

    public function ajax_detail()
    {
        $this->check_ajax();
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/ajax_detail');
    }

    public function copyright()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/copyright');
    }

    public function role()
    {
        $info = $this->label_vod_role();
        return $this->label_fetch('vod/role');
    }

    public function play()
    {
        $info = $this->label_vod_play('play');
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==3){
            return $this->label_fetch('vod/copyright');
        }
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl_play'],'play') );
    }

    public function player()
    {
        $info = $this->label_vod_play('play',[],0,1);
        if($info['vod_copyright']==1 && $GLOBALS['config']['app']['copyright_status']==4){
            return $this->label_fetch('vod/copyright');
        }
        if(!empty($info['vod_pwd_play']) && session('1-4-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/player_pwd');
        }
        return $this->label_fetch('vod/player');
    }

    public function down()
    {
        $info = $this->label_vod_play('down');
        return $this->label_fetch( mac_tpl_fetch('vod',$info['vod_tpl_down'],'down') );
    }

    public function downer()
    {
        $info = $this->label_vod_play('down');
        if(!empty($info['vod_pwd_down']) && session('1-5-'.$info['vod_id'])!='1'){
            return $this->label_fetch('vod/downer_pwd');
        }
        return $this->label_fetch('vod/downer');
    }

    public function rss()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/rss');
    }

    public function plot()
    {
        $info = $this->label_vod_detail();
        return $this->label_fetch('vod/plot');
    }

    /**
     *  获取视频列表
     *
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_vod_list(Request $request)
    {
        // 参数校验
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }
        $offset = isset($param['offset']) ? (int)$param['offset'] : 0;
        $limit = isset($param['limit']) ? (int)$param['limit'] : 20;
        // 查询条件组装
        $where = [];
        if (isset($param['type_id'])) {
            $where['type_id'] = (int)$param['type_id'];
        }
        if (isset($param['id'])) {
            $where['vod_id'] =(int) $param['id'];
        }
//        if (isset($param['type_id_1'])) {
//            $where['type_id_1'] = (int)$param['type_id_1'];
//        }
        if (!empty($param['vod_letter'])) {
            $where['vod_letter'] = $param['vod_letter'];
        }
        if (isset($param['vod_tag']) && strlen($param['vod_tag']) > 0) {
            $where['vod_tag'] = ['like', '%' . $this->format_sql_string($param['vod_tag']) . '%'];
        }
        if (isset($param['vod_name']) && strlen($param['vod_name']) > 0) {
            $where['vod_name'] = ['like', '%'.$this->format_sql_string($param['vod_name']).'%'];
        }
        if (isset($param['vod_blurb']) && strlen($param['vod_blurb']) > 0) {
            $where['vod_blurb'] = ['like', '%' . $this->format_sql_string($param['vod_blurb']) . '%'];
        }
        if (isset($param['vod_class']) && strlen($param['vod_class']) > 0) {
            $where['vod_class'] = ['like', '%' . $this->format_sql_string($param['vod_class']) . '%'];
        }
        if (isset($param['vod_area']) && strlen($param['vod_area']) > 0) {
            $where['vod_area'] = $this->format_sql_string($param['vod_area']);
        }
        if (isset($param['vod_year']) && strlen($param['vod_year']) > 0) {
            $where['vod_year'] = $this->format_sql_string($param['vod_year']);
        }
        // 数据获取
        $total = model('Vod')->getCountByCond($where);
        $list = [];
        if ($total > 0) {
            // 排序
            $order = "vod_time DESC";
            if (strlen($param['orderby']) > 0) {
                $order = 'vod_' . $param['orderby'] . " DESC";
            }
            $field = 'vod_id,vod_name,vod_actor,vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_time,vod_remarks,vod_score,vod_area,vod_year,vod_tag,vod_pic,vod_pic_thumb,vod_pic_slide,vod_douban_score';
//            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field, []);
            $list = model('Vod')->getListByCond($offset, $limit, $where, $order, $field);
            //把vod_time 字段转换为时间字符串
            foreach ($list as &$value) {
                $value['vod_time'] = date('Y-m-d H:i:s', $value['vod_time']);
            }
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => [
                'offset' => $offset,
                'limit'  => $limit,
                'total'  => $total,
                'rows'   => $list,
            ],
        ]);
    }
    /**
     * 视频详细信息
     *
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_vod_detail(Request $request)
    {
        $param = $request->param();
        $validate = validate($request->controller());
        if (!$validate->scene($request->action())->check($param)) {
            return json([
                'code' => 1001,
                'msg'  => '参数错误: ' . $validate->getError(),
            ]);
        }

        $res = Db::table('mac_vod')->where(['vod_id' => $param['vod_id']])->find();
        //判断vod_rel_vod 字段是否为空
        if (!empty($res['vod_rel_vod'])) {
            $field = 'vod_id,vod_name,vod_actor,vod_hits,vod_hits_day,vod_hits_week,vod_hits_month,vod_time,vod_remarks,vod_score,vod_area,vod_year,vod_tag,vod_pic,vod_pic_thumb,vod_pic_slide,vod_douban_score';
            $res['vod_rel_vod_list'] = Db::table('mac_vod')->where(['vod_id' => ['in', $res['vod_rel_vod']]])->field($field)->select();
        }
        // 返回
        return json([
            'code' => 1,
            'msg'  => '获取成功',
            'info' => $res
        ]);
    }
    protected function format_sql_string($str)
    {
        $str = preg_replace('/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|WHERE|FROM|JOIN|INTO|VALUES|SET|AND|OR|NOT|EXISTS|HAVING|GROUP BY|ORDER BY|LIMIT|OFFSET)\b/i', '', $str);
        $str = preg_replace('/[^\w\s\-\.]/', '', $str);
        $str = trim(preg_replace('/\s+/', ' ', $str));
        return $str;
    }
}
