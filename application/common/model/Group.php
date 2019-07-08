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
        $arr = [0=>'禁用',1=>'启用'];
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

        return ['code'=>1,'msg'=>'数据列表','total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>'参数错误'];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>'获取数据失败'];
        }
        $info = $info->toArray();
        $info['group_popedom'] = json_decode($info['group_popedom'],true);
        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
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
            return ['code'=>1001,'msg'=>'参数错误：'.$validate->getError() ];
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
            return ['code'=>1002,'msg'=>'保存失败：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>'保存成功'];
    }

    public function delData($where)
    {
        $cc = model('User')->countData($where);
        if($cc>0){
            return ['code'=>1002,'msg'=>'删除失败：会员组下还有用户' ];
        }
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>'删除失败：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>'删除成功'];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>'参数错误'];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>'设置失败：'.$this->getError() ];
        }
        $this->setCache();
        return ['code'=>1,'msg'=>'设置成功'];
    }

    public function setCache()
    {
        $res = $this->listData([],'group_id asc');
        $list = $res['list'];
        Cache::set('group_list',$list);

    }

    public function getCache($flag='group_list')
    {
        $cache = Cache::get($flag);
        if(empty($cache)){
            $this->setCache();

            $cache = Cache::get($flag);
        }
        return $cache;
    }

}