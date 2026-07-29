<?php
namespace app\common\model;
use think\Db;

class Ulog extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'ulog';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];


    public function listData($where,$order,$page=1,$limit=20,$start=0)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $start = $start ? (int)$start : 0;
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        $total = $this->where($where)->count();
        $list = Db::name('Ulog')->where($where)->order($order)->limit($limit_str)->select();

        $user_ids=[];
        foreach($list as $k=>&$v){
            if($v['user_id'] >0){
                $user_ids[$v['user_id']] = $v['user_id'];
            }

            if($v['ulog_mid']==12){
                // 漫画收藏 / 历史
                $manga_info = model('Manga')->infoData(['manga_id'=>['eq',$v['ulog_rid']]],'*',1);
                if (!empty($manga_info['info'])) {
                    $manga_info['info']['link'] = mac_url_manga_detail($manga_info['info']);
                    $v['data'] = [
                        'id'   => $manga_info['info']['manga_id'],
                        'name' => $manga_info['info']['manga_name'],
                        'pic'  => mac_url_img($manga_info['info']['manga_pic']),
                        'link' => $manga_info['info']['link'],
                        'type' => [
                            'type_id'   => $manga_info['info']['type']['type_id'],
                            'type_name' => $manga_info['info']['type']['type_name'],
                            'link'      => mac_url_type($manga_info['info']['type']),
                        ],
                    ];
                }
            }

            if($v['ulog_mid']==1){
                $vod_info = model('Vod')->infoData(['vod_id'=>['eq',$v['ulog_rid']]],'*',1);

                if($v['ulog_sid']>0 && $v['ulog_nid']>0){
                    if($v['ulog_type']==5){
                        $vod_info['info']['link'] = mac_url_vod_down($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                    else{
                        $vod_info['info']['link'] = mac_url_vod_play($vod_info['info'],['sid'=>$v['ulog_sid'],'nid'=>$v['ulog_nid']]);
                    }
                }
                else{
                    $vod_info['info']['link'] = mac_url_vod_detail($vod_info['info']);
                }
                $v['data'] = [
                    'id'=>$vod_info['info']['vod_id'],
                    'name'=>$vod_info['info']['vod_name'],
                    'pic'=>mac_url_img($vod_info['info']['vod_pic']),
                    'link'=>$vod_info['info']['link'],
                    'type'=>[
                        'type_id'=>$vod_info['info']['type']['type_id'],
                        'type_name'=>$vod_info['info']['type']['type_name'],
                        'link'=>mac_url_type($vod_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==2){
                $art_info = model('Art')->infoData(['art_id'=>['eq',$v['ulog_rid']]],'*',1);
                $art_info['info']['link'] = mac_url_art_detail($art_info['info']);
                $v['data'] = [
                    'id'=>$art_info['info']['art_id'],
                    'name'=>$art_info['info']['art_name'],
                    'pic'=>mac_url_img($art_info['info']['art_pic']),
                    'link'=>$art_info['info']['link'],
                    'type'=>[
                        'type_id'=>$art_info['info']['type']['type_id'],
                        'type_name'=>$art_info['info']['type']['type_name'],
                        'link'=>mac_url_type($art_info['info']['type']),
                    ],

                ];
            }
            elseif($v['ulog_mid']==3){
                $topic_info = model('Topic')->infoData(['topic_id'=>['eq',$v['ulog_rid']]],'*',1);
                $topic_info['info']['link'] = mac_url_topic_detail($topic_info['info']);
                $v['data'] = [
                    'id'=>$topic_info['info']['topic_id'],
                    'name'=>$topic_info['info']['topic_name'],
                    'pic'=>mac_url_img($topic_info['info']['topic_pic']),
                    'link'=>$topic_info['info']['link'],
                    'type'=>[],
                ];
            }
            elseif($v['ulog_mid']==8){
                $actor_info = model('Actor')->infoData(['actor_id'=>['eq',$v['ulog_rid']]],'*',1);
                $actor_info['info']['link'] = mac_url_actor_detail($actor_info['info']);
                $v['data'] = [
                    'id'=>$actor_info['info']['actor_id'],
                    'name'=>$actor_info['info']['actor_name'],
                    'pic'=>mac_url_img($actor_info['info']['actor_pic']),
                    'link'=>$actor_info['info']['link'],
                    'type'=>[],
                ];
            }
        }

        if(!empty($user_ids)){
            $where2=[];
            $where['user_id'] = ['in', $user_ids];
            $order='user_id desc';
            $user_list = model('User')->listData($where2,$order,1,999);
            $user_list = mac_array_rekey($user_list['list'],'user_id');

            foreach($list as $k=>&$v){
                $list[$k]['user_name'] = $user_list[$v['user_id']]['user_name'];
            }
        }

        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    /**
     * 继续观看列表（带播放进度）
     * 基于播放历史记录 ulog_mid=1 且 ulog_type=4，按最近播放时间倒序
     *
     * @param int   $user_id 用户ID
     * @param int   $page    页码
     * @param int   $limit   每页条数
     * @param array $options 可选项 hide_finished=1 时过滤已看完(percent>=95)的记录
     * @return array ['code'=>1,'page'=>..,'pagecount'=>..,'limit'=>..,'total'=>..,'list'=>[...]]
     */
    public function continueWatchData($user_id, $page = 1, $limit = 12, $options = [])
    {
        $user_id = intval($user_id);
        $page = $page > 0 ? intval($page) : 1;
        $limit = $limit > 0 ? intval($limit) : 12;
        $hide_finished = !empty($options['hide_finished']);

        if ($user_id < 1) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }

        $where = [
            'user_id'   => ['eq', $user_id],
            'ulog_mid'  => ['eq', 1],
            'ulog_type' => ['eq', 4],
        ];
        // 已看完定义：duration>0 且 point/duration >= 95%
        $finished_sql = '(ulog_duration <= 0 OR ulog_point * 100 < ulog_duration * 95)';

        $total_query = Db::name('Ulog')->where($where);
        $rows_query = Db::name('Ulog')
            ->field('ulog_id,ulog_rid,ulog_sid,ulog_nid,ulog_point,ulog_duration,ulog_time')
            ->where($where);
        if ($hide_finished) {
            $total_query->where($finished_sql);
            $rows_query->where($finished_sql);
        }

        $total = $total_query->count();
        $rows = $rows_query
            ->order('ulog_time desc')
            ->limit(($limit * ($page - 1)) . ',' . $limit)
            ->select();

        $list = [];
        if (!empty($rows)) {
            // 批量取影片信息，避免 N+1 查询；已删除的影片自动过滤
            $vod_ids = [];
            foreach ($rows as $v) {
                $vod_ids[intval($v['ulog_rid'])] = intval($v['ulog_rid']);
            }
            $vod_list = Db::name('Vod')
                ->field('vod_id,type_id,type_id_1,vod_name,vod_en,vod_letter,vod_pic,vod_remarks,vod_play_from,vod_play_url,vod_time')
                ->where('vod_id', 'in', array_values($vod_ids))
                ->select();
            $vod_list = mac_array_rekey($vod_list, 'vod_id');

            $type_list = model('Type')->getCache('type_list');

            foreach ($rows as $v) {
                $vod_id = intval($v['ulog_rid']);
                if (!isset($vod_list[$vod_id])) {
                    continue;
                }
                $vod = $vod_list[$vod_id];

                $sid = intval($v['ulog_sid']);
                $nid = intval($v['ulog_nid']);
                $point = intval($v['ulog_point']);
                $duration = intval($v['ulog_duration']);

                $percent = 0;
                if ($duration > 0) {
                    $percent = intval(floor($point * 100 / $duration));
                    if ($percent > 100) {
                        $percent = 100;
                    }
                }

                // 解析集数名称，如 "第08集"
                $episode_name = '';
                if ($sid > 0 && $nid > 0 && !empty($vod['vod_play_url'])) {
                    $play_url_arr = explode('$$$', $vod['vod_play_url']);
                    if (isset($play_url_arr[$sid - 1])) {
                        $episode_arr = explode('#', $play_url_arr[$sid - 1]);
                        if (isset($episode_arr[$nid - 1])) {
                            $tmp = explode('$', $episode_arr[$nid - 1]);
                            $episode_name = trim($tmp[0]);
                        }
                    }
                }

                // 续播直达链接；无 sid/nid 时退回详情页
                if ($sid > 0 && $nid > 0) {
                    $link_play = mac_url_vod_play($vod, ['sid' => $sid, 'nid' => $nid]);
                } else {
                    $link_play = mac_url_vod_detail($vod);
                }

                $type_info = [];
                if (isset($type_list[$vod['type_id']])) {
                    $type = $type_list[$vod['type_id']];
                    $type_info = [
                        'type_id'   => intval($type['type_id']),
                        'type_name' => $type['type_name'],
                        'link'      => mac_url_type($type),
                    ];
                }

                $list[] = [
                    'ulog_id'      => intval($v['ulog_id']),
                    'vod_id'       => $vod_id,
                    'vod_name'     => $vod['vod_name'],
                    'vod_pic'      => mac_url_img($vod['vod_pic']),
                    'vod_remarks'  => $vod['vod_remarks'],
                    'type'         => $type_info,
                    'sid'          => $sid,
                    'nid'          => $nid,
                    'point'        => $point,
                    'duration'     => $duration,
                    'percent'      => $percent,
                    'finished'     => ($duration > 0 && $percent >= 95) ? 1 : 0,
                    'episode_name' => $episode_name,
                    'link_play'    => $link_play,
                    'link_detail'  => mac_url_vod_detail($vod),
                    'time'         => intval($v['ulog_time']),
                ];
            }
        }

        return [
            'code'      => 1,
            'msg'       => lang('data_list'),
            'page'      => $page,
            'pagecount' => ceil($total / $limit),
            'limit'     => $limit,
            'total'     => $total,
            'list'      => $list,
        ];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    /**
     * 判断用户是否已拥有某项权限（已购买）。
     * 与 infoData 的区别：仅对视频下载(mid=1,type=5)忽略 ulog_points（价格），
     * 使「金币购买(记原价)」与「额度兑换(记0)」都能命中同一下载权限记录；
     * 其余类型保持含价格的精确匹配，避免 0 价记录（观看进度/收藏）被误判为已购。
     * ulog_time 一律不参与匹配。
     */
    public function hasBought($where)
    {
        if (empty($where) || !is_array($where)) {
            return false;
        }
        // 仅视频下载(mid=1,type=5)放宽价格匹配：额度兑换记 ulog_points=0、金币购买记原价都应算已购。
        // 其余类型（播放 type=4 的观看进度、文章/漫画 type=1 收藏等 0 价记录）保持精确匹配（含价格），
        // 防「免费内容事后被站长改为付费」后残留的 0 价记录被误判为已购而白嫖。
        $isVideoDownload = (intval(isset($where['ulog_mid']) ? $where['ulog_mid'] : 0) == 1
            && intval(isset($where['ulog_type']) ? $where['ulog_type'] : 0) == 5);
        unset($where['ulog_time']);
        if ($isVideoDownload) {
            unset($where['ulog_points']);
        }
        return $this->where($where)->count() > 0;
    }

    public function saveData($data)
    {
        $data['user_id'] = intval(cookie('user_id'));
        $data['ulog_time'] = time();

        $validate = \think\Loader::validate('Ulog');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if($data['user_id']==0 || !in_array($data['ulog_mid'],['1','2','3','8','12']) || !in_array($data['ulog_type'],['1','2','3','4','5']) ) {
            return ['code'=>1002,'msg'=>lang('param_err')];
        }

        if(!empty($data['ulog_id'])){
            $where=[];
            $where['ulog_id'] = ['eq',$data['ulog_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1004,'msg'=>lang('save_err').'：'.$this->getError() ];
        }

        // 收藏(ulog_type=2)且为新增时写动态
        if (empty($data['ulog_id']) && intval($data['ulog_type']) === 2) {
            try {
                $dyn_text = '';
                $content_name = $this->_getContentName(intval($data['ulog_mid']), intval($data['ulog_rid']));
                if ($content_name) {
                    $dyn_text = lang('dynamics/type_fav') . ': ' . $content_name;
                }
                model('Dynamics')->saveData([
                    'user_id'       => intval($data['user_id']),
                    'dynamics_type' => 'fav',
                    'dyn_mid'       => intval($data['ulog_mid']),
                    'dyn_rid'       => intval($data['ulog_rid']),
                    'dyn_text'      => $dyn_text,
                ]);
            } catch (\Exception $e) {
            }
        }

        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    private function _getContentName($mid, $rid)
    {
        $table_map = [1 => 'vod', 2 => 'art', 3 => 'topic', 8 => 'actor', 12 => 'manga'];
        if (!isset($table_map[$mid])) {
            return '';
        }
        $table = $table_map[$mid];
        $pk = $table . '_id';
        $name_col = $table . '_name';
        $row = \think\Db::name($table)->where($pk, $rid)->field($name_col)->find();
        return $row ? $row[$name_col] : '';
    }

    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

}