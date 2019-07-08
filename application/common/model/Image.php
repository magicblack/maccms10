<?php
namespace app\common\model;
use think\image\Exception;

class Image extends Base {

    public function down_load($url,$config,$flag='vod')
    {
        if(substr($url,0,4)=='http'){
            return $this->down_exec($url,$config,$flag);
        }
        else{
            return $url;
        }
    }

    public function down_exec($url,$config,$flag='vod')
    {
        $upload_image_ext = 'jpg,png,gif';
        $ext = strrchr($url,'.');

        if(strpos($upload_image_ext,$ext)===false){
            $ext = '.jpg';
        }
        $img = mac_curl_get($url);
        if($img){
            $file_name = md5(uniqid()) . $ext;
            // 上传附件路径
            $_upload_path = ROOT_PATH . 'upload' . '/' . $flag . '/';
            // 附件访问路径
            $_save_path = 'upload'. '/' . $flag . '/' ;
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

            $_upload_path .= $n_dir . '/';
            $_save_path .= $n_dir . '/';

            //附件访问地址
            $_file_path = $_save_path.$file_name;
            //写入文件
            $r = mac_write_file($_upload_path.$file_name,$img);
            if(!$r){
                return $url;
            }
            // 水印
            if ($config['watermark'] == 1) {
                $this->watermark($_file_path,$config,$flag);
            }
            // 缩略图
            if ($config['thumb'] == 1) {
                $this->makethumb($_file_path,$config,$flag);
            }
            //上传到远程
            
            $_file_path = model('Upload')->api($_file_path,$config);
            
            return $_file_path;
        }
        else{
            return $url;
        }
    }

    public function watermark($file_path,$config,$flag='vod')
    {
        if(empty($config['watermark_font'])){
            $config['watermark_font'] = './static/font/test.ttf';
        }
        try {
            $image = \think\Image::open('./' . $file_path);
            $image->text($config['watermark_content']."", $config['watermark_font'], $config['watermark_size'], $config['watermark_color'],$config['watermark_location'])->save('./' . $file_path);
        }
        catch(\Exception $e){

        }
    }

    public function makethumb($file_path,$config,$flag='vod',$new=1)
    {
        $thumb_type = $config['thumb_type'];
        $data['thumb'] = [];
        if (!empty($config['thumb_size'])) {
            try {
                $image = \think\Image::open('./' . $file_path);
                // 支持多种尺寸的缩略图
                $thumbs = explode(',', $config['thumb_size']);
                foreach ($thumbs as $k => $v) {
                    $t_size = explode('x', strtolower($v));
                    if (!isset($t_size[1])) {
                        $t_size[1] = $t_size[0];
                    }
                    $new_thumb = $file_path . '_' . $t_size[0] . 'x' . $t_size[1] . '.' . strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                    if($new==0){
                        $new_thumb = $file_path;
                    }
                    $image->thumb($t_size[0], $t_size[1], $thumb_type)->save('./' . $new_thumb);
                    $thumb_size = round(filesize('./' . $new_thumb) / 1024, 2);
                    $data['thumb'][$k]['type'] = 'image';
                    $data['thumb'][$k]['flag'] = $flag;
                    $data['thumb'][$k]['file'] = $new_thumb;
                    $data['thumb'][$k]['size'] = $thumb_size;
                    $data['thumb'][$k]['ctime'] = request()->time();

                    if ($config['watermark'] == 1) {// 开启文字水印
                        $image = \think\Image::open('./' . $new_thumb);
                        $image->text($config['watermark_content'], $config['watermark_font'], $config['watermark_size'], $config['watermark_color'])->save('./' . $new_thumb);
                    }
                }
            }
            catch(\Exception $e){

            }
        }
        return $data;
    }




}