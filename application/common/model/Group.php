<?php
namespace app\common\model;
use think\Cache;
use think\Db;

class Group extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'group';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getGroupStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['group_status']];
    }

    public function listData($where,$order)
    {
        $total = $this->where($where)->count();
        $tmp = Db::name('Group')->where($where)->order($order)->select();

        $list = [];
        foreach($tmp as $k=>$v){
            $v['group_popedom'] = json_decode($v['group_popedom'],true);
            $list[$v['group_id']] = $v;
        }

        return ['code'=>1,'msg'=>lang('data_list'),'total'=>$total,'list'=>$list];
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
        $info['group_popedom'] = json_decode($info['group_popedom'],true);
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        if(!empty($data['group_type'])){
            $data['group_type'] = ','.join(',',$data['group_type']) .',';
        }else{
            $data['group_type'] = '';
        }

        if(!empty($data['group_popedom'])){
            $data['group_popedom'] = json_encode($data['group_popedom']);
        }
        else{
            $data['group_popedom'] ='';
        }

        $validate = \think\Loader::validate('Group');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }
        if(!empty($data['group_id'])){
            $where=[];
            $where['group_id'] = ['eq',$data['group_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function delData($where)
    {
        $cc = model('User')->countData($where);
        if($cc>0){
            return ['code'=>1002,'msg'=>lang('del_err').'：'.lang('model/group/have_user') ];
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        $this->setCache();
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
        $this->setCache();
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function setCache()
    {
        $res = $this->listData([],'group_id asc');
        $list = $res['list'];
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.'group_list';
        Cache::set($key,$list);

    }

    public function getCache($flag='group_list')
    {
        $key = $GLOBALS['config']['app']['cache_flag']. '_'.$flag;
        $cache = Cache::get($key);
        if(empty($cache)){
            $this->setCache();
            $cache = Cache::get($key);
        }
        return $cache;
    }

}