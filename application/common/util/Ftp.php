<?php
namespace app\common\util;
//$config = array('ftp_host'=>'xxx.xxx.xxx.xxx','ftp_port'=>21,'ftp_user'=>'xxx','ftp_pwd' =>'xxxxx','ftp_dir'=>'/');
//$ftp=new ftp();
//$ftp->config($config);
//$ftp->connect();
//$ftp->put(ROOT_PATH.$file, $file);
//@unlink($this->getPicFile($file));
class Ftp{
	protected $_config = array( 'ftp_host'=>'www.test.com', 'ftp_port'=>'21', 'ftp_user'=>'maccms', 'ftp_pwd' =>'maccms', 'ftp_timeout'=>'30', 'ftp_dir' =>'/', 'ftp_pasv'=>1 );
	protected $_conn = null; 
	protected $_rs = null; 
	public function __construct($config=array()){ 
 		!function_exists('ftp_connect') && die('FTP模块不支持!'); 
 		$this->config($config);
	} 
	public function config($config=array()){ 
 		$this->_config = array_merge($this->_config, $config); 
	} 
	function connect(){ 
		$this->_conn = @ftp_connect($this->_config['ftp_host'],$this->_config['ftp_port'],$this->_config['ftp_timeout']); 
		if(!$this->_conn){
			return -1;//FTP服务器连接失败! 请检查服务器地址和端口
		}
		$this->_rs = @ftp_login($this->_conn, $this->_config['ftp_user'], $this->_config['ftp_pwd']); 
		if(!$this->_rs){ 
			return -2; //FTP登录错误! 请检查用户名和密码
		} 
		$this->_config['ftp_pasv'] && $this->pasv(true); 
		if(!$this->chdir($this->_config['ftp_dir'])){ 
			return -3; //切换到FTP当前目录失败! 请检查目录是否存在
		} 
		return $this; 
	}
	function chdir($dir){
		return @ftp_chdir($this->_conn,$dir);
	}
	function is_file($file){
		$buff = @ftp_mdtm($this->_conn, $file); 
		if($buff != -1){ 
			return true; 
		}else{
			return false; 
		} 
	}
	function pasv($mode=true){ 
		return @ftp_pasv($this->_conn, true);
	}
	function put($local_file, $remote_file, $mode='B'){ 
		if($mode == 'B'){ 
			$mode = FTP_BINARY; 
		}else{
			$mode = FTP_ASCII;
		}
		$this->mkdirs(dirname($remote_file)); 
		$rs = @ftp_put($this->_conn, $remote_file, $local_file, $mode); 
		return $rs; 
	}
	function mkdirs($dir){
		$dir = str_replace("\\",'/',$dir); 
		$dirs = explode('/', $dir); 
		$total = count($dirs);
		foreach($dirs as $val){
			if($val == '.'){
				continue;
			}
			if($this->chdir($val) == false){
				if(!$this->mkdir($val)){
					return false;//创建失败
				} 
				$this->chdir($val); 
			}
		}
		$this->chdir($this->_config['ftp_dir']);
		return true; 
	} 
	function mkdir($dir){ 
		return @ftp_mkdir($this->_conn, $dir); 
	} 
	function unlink($file){ 
		return @ftp_delete($this->_conn, $file); 
	}
	function rename($old_name, $new_name){ 
		return @ftp_rename($this->_conn, $old_name, $new_name); 
	}
	function rmdir($dir){ 
		return @ftp_rmdir($this->_conn, $dir);
	}
	function rmdirs($dir, $flag=1){
		$res = $this->rmdir($dir) || $this->unlink($dir); 
		if(!$res){ 
			$files = $this->nlist($dir); 
			if(empty($files)){ 
				return true; 
			} 
			foreach($files as $file){ 
				$file = basename($file); 
				$this->rmdirs($dir.'/'.$file); 
			} 
			if($flag){ 
				$this->rmdirs($dir); 
			} 
		} 
		return true; 
	} 
	function nlist($dir){ 
		return @ftp_nlist($this->_conn, $dir); 
	}
	function bye() {
   		return ftp_close($this->_conn);
	}	
} 
?>