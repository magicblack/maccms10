<?php
namespace app\api\controller;
use think\Controller;

class Timming extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input('get.','','trim,urldecode');
        $name = $param['name'];
        if(empty($name)){
            //return $this->error('参数错误!');
        }

        $list = config('timming');
        foreach($list as $k=>$v){
            if(!empty($name) && $v['name'] !=$name){
                continue;
            }

            if(!empty($v['runtime'])) { $oldweek= date('w',$v['runtime']); $oldhours= date('H',$v['runtime']); }
            $curweek= date('w',time()) ;	$curhours= date("H",time());
            if(strlen($oldhours)==1 && intval($oldhours) <10){ $oldhours= '0'.$oldhours; }
            if(strlen($curhours)==1 && intval($curhours) <10){ $curhours= substr($curhours,1,1); }
            $last = (!empty($v['runtime']) ? date('Y-m-d H:i:s',$v['runtime']) : lang('api/never'));
            $status = $v['status'] == '1' ?  lang('open'): lang('close');

            //测试
            //$v['runtime']=0;

            if( $v['status']=='1' &&
                ( empty($v['runtime']) || ($oldweek."-".$oldhours) != ($curweek."-".$curhours) && strpos($v['weeks'],$curweek)!==false && strpos($v['hours'],$curhours)!==false  || $param['enforce'] =='1')
               ) {
                mac_echo( lang('api/task_tip_exec',[$v['name'] ,$status,$last]));
                $list[$k]['runtime'] = time();

                $res = mac_arr2file( APP_PATH .'extra/timming.php', $list);
                if($res===false){
                    return $this->error(lang('write_err_config'));
                }
                $this->reset();
                $file = $v['file'];
                $this->$file($v['param']);
                die;
            }
            else{
                mac_echo(lang('api/task_tip_jump',[$v['name'] ,$status,$last]));
            }
        }
    }

    private function reset()
    {
        foreach($_REQUEST as $k=>$v){
            $_REQUEST[$k]='';
        }
    }

    protected function collect($param)
    {
        @parse_str($param,$output);
        $request = controller('admin/collect');
        $request->api($output);
    }

    protected function make($param)
    {
        @parse_str($param,$output);
        $request = controller('admin/make');
        $request->make($output);
    }

    protected function cj($param)
    {
        @parse_str($param,$output);
        $request = controller('admin/cj');
        $request->col_all($output);
    }

    protected function cache($param)
    {
        @parse_str($param,$output);
        $request = controller('admin/index');
        $request->clear();
    }

    protected function urlsend($param)
    {
        @parse_str($param,$output);
        $request = controller('admin/urlsend');
        $request->push($output);
    }
}
