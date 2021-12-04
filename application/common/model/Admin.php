<?php
namespace app\common\model;
use think\Db;

class Admin extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'admin';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getAdminStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('disable'),1=>lang('enable')];
        return $arr[$data['admin_status']];
    }

    public function listData($where,$order,$page,$limit=20)
    {
        $total = $this->where($where)->count();
        $list = Db::name('Admin')->where($where)->order($order)->page($page)->limit($limit)->select();
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
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

        $info['admin_pwd'] = '';
        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        if(!empty($data['admin_auth'])){
            $data['admin_auth'] = ','.join(',',$data['admin_auth']).',';
        }
        else{
            $data['admin_auth'] = '';
        }
        $validate = \think\Loader::validate('Admin');
        if(!empty($data['admin_id'])){
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }

            if(empty($data['admin_pwd'])){
                unset($data['admin_pwd']);
            }
            else{
                $data['admin_pwd'] = md5($data['admin_pwd']);
            }
            $where=[];
            $where['admin_id'] = ['eq',$data['admin_id']];
            $res = $this->where($where)->update($data);
        }
        else{
            if(!$validate->scene('edit')->check($data)){
                return ['code'=>1002,'msg'=>lang('param_err').'：'.$validate->getError() ];
            }

            $data['admin_pwd'] = md5($data['admin_pwd']);
            $res = $this->insert($data);
        }
        if(false === $res){
            return ['code'=>1003,'msg'=>''.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
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
        $res = $this->where($where)->update($data);
        if($res===false){
            return ['code'=>1002,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function login($data)
    {
        if(empty($data['admin_name']) || empty($data['admin_pwd'])  ) {
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        if($GLOBALS['config']['app']['admin_login_verify'] !='0'){
            if(!captcha_check($data['verify'])){
                return ['code'=>1002,'msg'=>lang('verify_err')];
            }
        }


        $where=[];
        $where['admin_name'] = ['eq',$data['admin_name']];
        $where['admin_pwd'] = ['eq',md5($data['admin_pwd'])];
        $where['admin_status'] = ['eq',1];

        $row = $this->where($where)->find();

        if(empty($row)){
            return ['code'=>1003,'msg'=>lang('access_or_pass_err')];
        }
        $random = md5(rand(10000000,99999999));
        $update['admin_login_ip'] = mac_get_ip_long();
        $update['admin_login_time'] = time();
        $update['admin_login_num'] = $row['admin_login_num'] + 1;
        $update['admin_random'] = $random;
        $update['admin_last_login_time'] = $row['admin_login_time'];
        $update['admin_last_login_ip'] = $row['admin_login_ip'];

        $res = $this->where($where)->update($update);
        if($res===false){
            return ['code'=>1004,'msg'=>lang('model/admin/update_login_err')];
        }

        session('admin_auth','1');
        session('admin_info',$row->toArray());

        //cookie('admin_id',$row['admin_id']);
        //cookie('admin_name',$row['admin_name']);
        //cookie('admin_check',md5($random .'-'. $row['admin_name'] .'-'.$row['admin_id'] .'-'.mac_get_client_ip() ) );

        return ['code'=>1,'msg'=>lang('model/admin/login_ok')];
    }

    public function logout()
    {
        session('admin_auth',null);
        session('admin_info',null);
        //cookie('admin_id',null);
        //cookie('admin_name',null);
        //cookie('admin_check',null);

        return ['code'=>1,'msg'=>lang('model/admin/logout_ok')];
    }

    public function checkLogin()
    {
        if(session('admin_auth')!=='1'){
            return ['code'=>1009,'msg'=>lang('model/admin/not_login')];
        }
        $info = session('admin_info');
        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('model/admin/not_login')];
        }
        return ['code'=>1,'msg'=>lang('model/admin/haved_login'),'info'=>$info];
    }

    public function checkLogin2()
    {
        $admin_id = cookie('admin_id');
        $admin_name = cookie('admin_name');
        $admin_check = cookie('admin_check');

        if(empty($admin_id) || empty($admin_name) || empty($admin_check)){
            return ['code'=>1001, 'msg'=>lang('model/admin/not_login')];
        }

        $where = [];
        $where['admin_id'] = $admin_id;
        $where['admin_name'] = $admin_name;
        $where['admin_status'] =1 ;

        $info = $this->where($where)->find();
        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('model/admin/not_login')];
        }
        $info = $info->toArray();

        $login_check = md5($info['admin_random'] .'-'. $info['admin_name'] .'-'.$info['admin_id'] .'-'.mac_get_client_ip() ) ;
        if($login_check != $admin_check){
            return ['code'=>1003,'msg'=>lang('model/admin/not_login')];
        }
        return ['code'=>1,'msg'=>lang('model/admin/haved_login'),'info'=>$info];
    }

}