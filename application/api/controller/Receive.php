<?php
namespace app\api\controller;
use think\Controller;

class Receive extends Base
{
    var $_param;

    public function __construct()
    {
        parent::__construct();
        $this->_param = input();


        if($GLOBALS['config']['interface']['status'] != 1){
            echo json_encode(['code'=>3001,'msg'=>'接口关闭err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if($GLOBALS['config']['interface']['pass'] != $this->_param['pass']){
            echo json_encode(['code'=>3002,'msg'=>'非法使用err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if( strlen($GLOBALS['config']['interface']['pass']) <16){
            echo json_encode(['code'=>3003,'msg'=>'安全起见入库密码必须大于等于16位'],JSON_UNESCAPED_UNICODE);
            exit;
        }

    }

    public function index()
    {

    }

    public function vod()
    {
        $info = $this->_param;

        if(empty($info['vod_name'])){
            echo json_encode(['code'=>2001,'msg'=>'名称必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2002,'msg'=>'分类名称和分类id至少填写1项err'],JSON_UNESCAPED_UNICODE);
            exit;
        }

        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['vodtype'][$info['type_name']];
        }

        $data['data'][] = $info;
        $res = model('Collect')->vod_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    public function art()
    {
        $info = $this->_param;

        if(empty($info['art_name'])){
            echo json_encode(['code'=>2001,'msg'=>'名称必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if(empty($info['type_id']) && empty($info['type_name'])){
            echo json_encode(['code'=>2002,'msg'=>'分类名称和分类id至少填写1项err'],JSON_UNESCAPED_UNICODE);
            exit;
        }

        $inter = mac_interface_type();
        if(empty($info['type_id'])) {
            $info['type_id'] = $inter['arttype'][$info['type_name']];
        }
        $data['data'][] = $info;
        $res = model('Collect')->art_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    public function actor()
    {
        $info = $this->_param;

        if(empty($info['actor_name'])){
            echo json_encode(['code'=>2001,'msg'=>'演员名必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if(empty($info['actor_sex'])){
            echo json_encode(['code'=>2002,'msg'=>'性别必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data['data'][] = $info;
        $res = model('Collect')->actor_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }

    public function role()
    {
        $info = $this->_param;

        if(empty($info['role_name'])){
            echo json_encode(['code'=>2001,'msg'=>'角色名称必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if(empty($info['role_actor'])){
            echo json_encode(['code'=>2002,'msg'=>'演员名必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        if(empty($info['vod_name'])){
            echo json_encode(['code'=>2003,'msg'=>'视频名必须err'],JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data['data'][] = $info;
        $res = model('Collect')->role_data([],$data,0);
        echo json_encode($res,JSON_UNESCAPED_UNICODE);
    }
}
