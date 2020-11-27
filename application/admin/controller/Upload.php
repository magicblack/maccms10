<?php
namespace app\admin\controller;
use think\Db;



class Upload extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $param = input();
        $this->assign('path',$param['path']);
        $this->assign('id',$param['id']);

        $this->assign('title',lang('upload_pic'));
        return $this->fetch('admin@upload/index');
    }

    public function test()
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        if($temp_file){
            echo lang('admin/upload/test_write_ok').'：' . $temp_file;
        }
        else{
            echo lang('admin/upload/test_write_err').'：' . sys_get_temp_dir() ;
        }
    }

    public function upload()
    {
        
		$param = input();
        $param['from'] = empty($param['from']) ? '' : $param['from'];
        $param['input'] = empty($param['input']) ? 'file' : $param['input'];
        $param['flag'] = empty($param['flag']) ? 'vod' : $param['flag'];
        $param['thumb'] = empty($param['thumb']) ? '0' : $param['thumb'];
        $param['thumb_class'] = empty($param['thumb_class']) ? '' : $param['thumb_class'];
        $param['user_id'] = empty($param['user_id']) ? '0' : $param['user_id'];

        $config = config('maccms.site');
        $pre= $config['install_dir'];

        if(!empty($param['from'])){
            $cp = 'app\\common\\extend\\editor\\' . ucfirst($param['from']);
            if (class_exists($cp)) {
                $c = new $cp;
                $c->front($param);
            }
            else{
                return self::upload_return(lang('admin/upload/not_find_extend'), '');
            }
        }
        else{
            $pre='';
        }

        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($param['input']);

        $data = [];
        if (empty($file)) {
            return self::upload_return(lang('admin/upload/no_input_file'), $param['from']);
        }
        if ($file->getMime() == 'text/x-php') {
            return self::upload_return(lang('admin/upload/forbidden_ext'), $param['from']);
        }

        $upload_image_ext = 'jpg,jpeg,png,gif';
        $upload_file_ext = 'doc,docx,xls,xlsx,ppt,pptx,pdf,wps,txt,rar,zip,torrent';
        $upload_media_ext = 'rm,rmvb,avi,mkv,mp4,mp3';
        $sys_max_filesize = ini_get('upload_max_filesize');
        $config = config('maccms.upload');

        // 格式、大小校验
        if ($file->checkExt($upload_image_ext)) {
            $type = 'image';
        }
        elseif ($file->checkExt($upload_file_ext)) {
            $type = 'file';
        }
        elseif ($file->checkExt($upload_media_ext)) {
            $type = 'media';
        }
        else {
            return self::upload_return(lang('admin/upload/forbidden_ext'), $param['from']);
        }

        if($param['flag']=='user'){
            $uniq = $param['user_id'] % 10;
            // 上传附件路径
            $_upload_path = ROOT_PATH . 'upload' . '/user/'  . $uniq .'/';
            // 附件访问路径
            $_save_path = 'upload'. '/user/' . $uniq .'/';
            $_save_name = $param['user_id'] . '.jpg';

            if(!file_exists($_save_path)){
                mac_mkdirss($_save_path);
            }

            $upfile = $file->move($_upload_path,$_save_name);

            if (!is_file($_upload_path.$_save_name)) {
                return self::upload_return(lang('admin/upload/upload_faild'), $param['from']);
            }
            $file = $_save_path.str_replace('\\', '/', $_save_name);
            $config= [
                'thumb_type'=>6,
                'thumb_size'=> $GLOBALS['config']['user']['portrait_size'],
            ];

            $new_thumb = $param['user_id'] .'.jpg';
            $new_file = $_save_path . $new_thumb;
            try {
                $image = \think\Image::open('./' . $file);
                $t_size = explode('x', strtolower($GLOBALS['config']['user']['portrait_size']));
                if (!isset($t_size[1])) {
                    $t_size[1] = $t_size[0];
                }
                $res = $image->thumb($t_size[0], $t_size[1], 6)->save('./' . $new_file);

                $file_count = 1;
                $file_size = round($upfile->getInfo('size')/1024, 2);
                $data = [
                    'file'  => $new_file,
                    'type'  => $type,
                    'size'  => $file_size,
                    'flag' => $param['flag'],
                    'ctime' => request()->time(),
                    'thumb_class'=>$param['thumb_class'],
                ];


                return self::upload_return(lang('admin/upload/upload_success'), $param['from'], 1, $data);
            }
            catch(\Exception $e){
                return self::upload_return(lang('admin/upload/make_thumb_faild'), $param['from']);
            }
            exit;
        }
        // 上传附件路径
        $_upload_path = ROOT_PATH . 'upload' . '/' . $param['flag'] . '/' ;
        // 附件访问路径
        $_save_path = 'upload'. '/' . $param['flag'] . '/';
        $ymd = date('Ymd');

        $n_dir = $ymd;
        for($i=1;$i<=100;$i++){
            $n_dir = $ymd .'-'.$i;
            $path1 = $_upload_path . $n_dir. '/';
            if(file_exists($path1)){
                $farr = glob($path1.'*.*');
                if($farr){
                    $fcount = count($farr);
                    if($fcount>999){
                        continue;
                    }
                    else{
                        break;
                    }
                }
                else{
                    break;
                }
            }
            else{
                break;
            }
        }

        $savename = $n_dir . '/' . md5(microtime(true));
        $upfile = $file->move($_upload_path,$savename);

        if (!is_file($_upload_path.$upfile->getSaveName())) {
            return self::upload_return(lang('admin/upload/upload_faild'), $param['from']);
        }

        //附件访问地址
        //$_file_path = $_save_path.$upfile->getSaveName();

        $file_count = 1;
        $file_size = round($upfile->getInfo('size')/1024, 2);
        $data = [
            'file'  => $_save_path.str_replace('\\', '/', $upfile->getSaveName()),
            'type'  => $type,
            'size'  => $file_size,
            'flag' => $param['flag'],
            'ctime' => request()->time(),
            'thumb_class'=>$param['thumb_class'],
        ];

        $data['thumb'] = [];
        if ($type == 'image') {
            //水印
            if ($config['watermark'] == 1) {
                model('Image')->watermark($data['file'],$config,$param['flag']);
            }
            // 缩略图
            if ($param['thumb']==1 &&  $config['thumb'] == 1) {
                $dd = model('Image')->makethumb($data['file'],$config,$param['flag']);
                if(is_array($dd)){
                    $data = array_merge($data,$dd);
                }
            }
        }
		unset($upfile);

        if ($config['mode'] == 2) {
            $config['mode'] = 'upyun';
        }
        elseif ($config['mode'] == 3){
            $config['mode'] = 'qiniu';
        }
        elseif ($config['mode'] == 4) {
            $config['mode'] = 'ftp';
        }
        elseif ($config['mode'] == 5) {
            $config['mode'] = 'weibo';
        }

        $config['mode'] = strtolower($config['mode']);

        if(!in_array($config['mode'],['local','remote'])){
            $data['file'] = model('Upload')->api($data['file'],$config);
            if(!empty($data['thumb'])){
                $data['thumb'][0]['file'] = model('Upload')->api($data['thumb'][0]['file'],$config);
            }
        }
        if(!empty($param['from'])){
            if(substr($data['file'],0,4)!='http' && substr($data['file'],0,4)!='mac:'){
                $data['file']  =  $pre. $data['file'];
            }
            else{
                $data['file']  = mac_url_content_img($data['file']);
            }
        }
        return self::upload_return(lang('admin/upload/upload_success'), $param['from'], 1, $data);
    }


    private function upload_return($info='',$from='',$status=0,$data=[])
    {
        $arr = [];

        if(!empty($from)){
            $cp = 'app\\common\\extend\\editor\\' . ucfirst($from);
            if (class_exists($cp)) {
                $c = new $cp;
                $arr = $c->back($info,$status,$data);
            }
        }
        else{
            $arr['msg'] = $info;
            $arr['code'] = $status;
            $arr['data'] = $data;
        }
        return $arr;
    }

}
