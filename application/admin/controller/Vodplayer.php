<?php
namespace app\admin\controller;
use think\Db;

class VodPlayer extends Base
{
    var $_pre;
    public function __construct()
    {
        parent::__construct();
        $this->_pre = 'vodplayer';
    }

    public function index()
    {
        $list = config($this->_pre);
        $this->assign('list',$list);
        $this->assign('title','播放器管理');
        return $this->fetch('admin@vodplayer/index');
    }

    public function info()
    {
        $param = input();
        $list = config($this->_pre);
        if (Request()->isPost()) {
            unset($param['flag']);
            $code = $param['code'];
            unset($param['code']);

            if(is_numeric($param['from'])){
                $param['from'] .='_';
            }
            $list[$param['from']] = $param;
            $sort=[];
            foreach ($list as $k=>&$v){
                $sort[] = $v['sort'];
            }
            array_multisort($sort, SORT_DESC, SORT_FLAG_CASE , $list);

            $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error('保存配置文件失败，请重试!');
            }

            $res = fwrite(fopen('./static/player/' . $param['from'].'.js','wb'),$code);
            if($res===false){
                return $this->error('保存代码文件失败，请重试!');
            }

            return $this->success('保存成功!');
        }

        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }
        $this->assign('info',$info);
        $this->assign('title','信息管理');
        return $this->fetch('admin@vodplayer/info');
    }

    public function del()
    {
        $param = input();
        $list = config($this->_pre);
        unset($list[$param['ids']]);
        $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
        if($res===false){
            return $this->error('删除失败，请重试!');
        }

        return $this->success('删除成功!');
    }

    public function field()
    {
        $param = input();
        $ids = $param['ids'];
        $col = $param['col'];
        $val = $param['val'];

        if(!empty($ids) && in_array($col,['ps','status'])){
            $list = config($this->_pre);
            $ids = explode(',',$ids);
            foreach($list as $k=>&$v){
                if(in_array($k,$ids)){
                    $v[$col] = $val;
                }
            }
            $res = mac_arr2file(APP_PATH. 'extra/'.$this->_pre.'.php', $list);
            if($res===false){
                return $this->error('保存失败，请重试!');
            }
            return $this->success('保存成功!');
        }
        return $this->error('参数错误');
    }

    public function export()
    {
        $param = input();
        $list = config($this->_pre);
        $info = $list[$param['id']];
        if(!empty($info)){
            $code = file_get_contents('./static/player/' . $param['id'].'.js');
            $info['code'] = $code;
        }

        header("Content-type: application/octet-stream");
        if(strpos($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
            header("Content-Disposition: attachment; filename=mac_" . urlencode($info['from']) . '.txt');
        }
        else{
            header("Content-Disposition: attachment; filename=mac_" . $info['from'] . '.txt');
        }
        echo base64_encode(json_encode($info));
    }

    public function import()
    {
        $file = $this->request->file('file');
        $info = $file->rule('uniqid')->validate(['size' => 10240000, 'ext' => 'txt']);
        if ($info) {
            $data = json_decode(base64_decode(file_get_contents($info->getpathName())), true);
            @unlink($info->getpathName());
            if($data){

                if(empty($data['status']) || empty($data['from']) || empty($data['sort']) ){
                    return $this->error('格式错误');
                }
                $code = $data['code'];
                unset($data['code']);

                $list = config($this->_pre);
                $list[$data['from']] = $data;
                $res = mac_arr2file( APP_PATH .'extra/'.$this->_pre.'.php', $list);
                if($res===false){
                    return $this->error('保存配置文件失败，请重试!');
                }

                $res = fwrite(fopen('./static/player/' . $data['from'].'.js','wb'),$code);
                if($res===false){
                    return $this->error('保存代码文件失败，请重试!');
                }

            }
            return $this->success('导入失败，请检查文件格式');
        }
        else{
            return $this->error($file->getError());
        }
    }
}
