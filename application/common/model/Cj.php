<?php
namespace app\common\model;
use think\Db;
use app\common\util\Pinyin;

class Cj extends Base {

    public function listData($tab,$where,$order,$page,$limit=20)
    {
        $total = Db::name($tab)->where($where)->count();
        $list = Db::name($tab)->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($tab,$where=[],$field='*')
    {
        if(empty($tab) || empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>'参数错误'];
        }
        $info = Db::name($tab)->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>'获取数据失败'];
        }

        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
    }

    public function saveData($data)
    {
        $data['lastdate'] = time();
        if(!empty($data['nodeid'])){
            $where=[];
            $where['nodeid'] = ['eq',$data['nodeid']];

            $res = Db::name('cj_node')->where($where)->update($data);
        }
        else{
            $res = Db::name('cj_node')->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }

    public function delData($where)
    {
        //删除node
        $res = Db::name('cj_node')->where($where)->delete();
        //删除history
        $list = Db::name('cj_content')->field('url')->where($where)->select();
        foreach ($list as $k => $v) {
            $md5 = md5($v['url']);
            Db::name('cj_history')->where('md5',$md5)->delete();
        }
        //删除content
        $res = Db::name('cj_content')->where($where)->delete();

        if($res===false){
            return ['code'=>1001,'msg'=>'删除失败'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'删除成功'];
    }


}