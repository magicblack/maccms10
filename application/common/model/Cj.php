<?php
namespace app\common\model;
use think\Db;
use app\common\util\Pinyin;

class Cj extends Base {

    public function listData($tab,$where,$order,$page,$limit=20)
    {
        $total = Db::name($tab)->where($where)->count();
        $list = Db::name($tab)->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($tab,$where=[],$field='*')
    {
        if(empty($tab) || empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = Db::name($tab)->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
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
            $data['urlpage'] = isset($data['urlpage']) ? (string)$data['urlpage'] : '';
            $data['page_base'] = isset($data['page_base']) ? (string)$data['page_base'] : '';
            $res = Db::name('cj_node')->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
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
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }


}