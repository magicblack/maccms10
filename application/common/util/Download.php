<?php
namespace app\common\util;

class Download
{
    var $filepath;
    var $downname;
    var $ErrInfo;
    var $is_attachment = false;
    var $_LANG = array(
            'err' => '错误',
            'args_empty' => '参数错误。',
            'file_not_exists' => '文件不存在！',
            'file_not_readable' => '文件不可读！',
        );
    var $MIMETypes = array( 
            'ez' => 'application/andrew-inset', 
            'hqx' => 'application/mac-binhex40', 
            'cpt' => 'application/mac-compactpro', 
            'doc' => 'application/msword', 
            'bin' => 'application/octet-stream', 
            'dms' => 'application/octet-stream', 
            'lha' => 'application/octet-stream', 
            'lzh' => 'application/octet-stream', 
            'exe' => 'application/octet-stream', 
            'class' => 'application/octet-stream', 
            'so' => 'application/octet-stream', 
            'dll' => 'application/octet-stream', 
            'oda' => 'application/oda', 
            'pdf' => 'application/pdf', 
            'ai' => 'application/postscrīpt', 
            'eps' => 'application/postscrīpt', 
            'ps' => 'application/postscrīpt', 
            'smi' => 'application/smil', 
            'smil' => 'application/smil', 
            'mif' => 'application/vnd.mif', 
            'xls' => 'application/vnd.ms-excel', 
            'ppt' => 'application/vnd.ms-powerpoint', 
            'wbxml' => 'application/vnd.wap.wbxml', 
            'wmlc' => 'application/vnd.wap.wmlc', 
            'wmlsc' => 'application/vnd.wap.wmlscrīptc', 
            'bcpio' => 'application/x-bcpio', 
            'vcd' => 'application/x-cdlink', 
            'pgn' => 'application/x-chess-pgn', 
            'cpio' => 'application/x-cpio', 
            'csh' => 'application/x-csh', 
            'dcr' => 'application/x-director', 
            'dir' => 'application/x-director', 
            'dxr' => 'application/x-director', 
            'dvi' => 'application/x-dvi', 
            'spl' => 'application/x-futuresplash', 
            'gtar' => 'application/x-gtar', 
            'hdf' => 'application/x-hdf', 
            'js' => 'application/x-javascrīpt', 
            'skp' => 'application/x-koan', 
            'skd' => 'application/x-koan', 
            'skt' => 'application/x-koan', 
            'skm' => 'application/x-koan', 
            'latex' => 'application/x-latex', 
            'nc' => 'application/x-netcdf', 
            'cdf' => 'application/x-netcdf', 
            'sh' => 'application/x-sh', 
            'shar' => 'application/x-shar', 
            'swf' => 'application/x-shockwave-flash', 
            'sit' => 'application/x-stuffit', 
            'sv4cpio' => 'application/x-sv4cpio', 
            'sv4crc' => 'application/x-sv4crc', 
            'tar' => 'application/x-tar', 
            'tcl' => 'application/x-tcl', 
            'tex' => 'application/x-tex', 
            'texinfo' => 'application/x-texinfo', 
            'texi' => 'application/x-texinfo', 
            't' => 'application/x-troff', 
            'tr' => 'application/x-troff', 
            'roff' => 'application/x-troff', 
            'man' => 'application/x-troff-man', 
            'me' => 'application/x-troff-me', 
            'ms' => 'application/x-troff-ms', 
            'ustar' => 'application/x-ustar', 
            'src' => 'application/x-wais-source', 
            'xhtml' => 'application/xhtml+xml', 
            'xht' => 'application/xhtml+xml', 
            'zip' => 'application/zip', 
            'au' => 'audio/basic', 
            'snd' => 'audio/basic', 
            'mid' => 'audio/midi', 
            'midi' => 'audio/midi', 
            'kar' => 'audio/midi', 
            'mpga' => 'audio/mpeg', 
            'mp2' => 'audio/mpeg', 
            'mp3' => 'audio/mpeg',
            'wma' => 'audio/mpeg', 
            'aif' => 'audio/x-aiff', 
            'aiff' => 'audio/x-aiff', 
            'aifc' => 'audio/x-aiff', 
            'm3u' => 'audio/x-mpegurl', 
            'ram' => 'audio/x-pn-realaudio', 
            'rm' => 'audio/x-pn-realaudio', 
            'rpm' => 'audio/x-pn-realaudio-plugin', 
            'ra' => 'audio/x-realaudio', 
            'wav' => 'audio/x-wav', 
            'pdb' => 'chemical/x-pdb', 
            'xyz' => 'chemical/x-xyz', 
            'bmp' => 'image/bmp', 
            'gif' => 'image/gif', 
            'ief' => 'image/ief', 
            'jpeg' => 'image/jpeg', 
            'jpg' => 'image/jpeg', 
            'jpe' => 'image/jpeg', 
            'png' => 'image/png', 
            'tiff' => 'image/tiff', 
            'tif' => 'image/tiff', 
            'djvu' => 'image/vnd.djvu', 
            'djv' => 'image/vnd.djvu', 
            'wbmp' => 'image/vnd.wap.wbmp', 
            'ras' => 'image/x-cmu-raster', 
            'pnm' => 'image/x-portable-anymap', 
            'pbm' => 'image/x-portable-bitmap', 
            'pgm' => 'image/x-portable-graymap', 
            'ppm' => 'image/x-portable-pixmap', 
            'rgb' => 'image/x-rgb', 
            'xbm' => 'image/x-xbitmap', 
            'xpm' => 'image/x-xpixmap', 
            'xwd' => 'image/x-xwindowdump', 
            'igs' => 'model/iges', 
            'iges' => 'model/iges', 
            'msh' => 'model/mesh', 
            'mesh' => 'model/mesh', 
            'silo' => 'model/mesh', 
            'wrl' => 'model/vrml', 
            'vrml' => 'model/vrml', 
            'css' => 'text/css', 
            'html' => 'text/html', 
            'htm' => 'text/html', 
            'asc' => 'text/plain', 
            'txt' => 'text/plain', 
            'rtx' => 'text/richtext', 
            'rtf' => 'text/rtf', 
            'sgml' => 'text/sgml', 
            'sgm' => 'text/sgml', 
            'tsv' => 'text/tab-separated-values', 
            'wml' => 'text/vnd.wap.wml', 
            'wmls' => 'text/vnd.wap.wmlscrīpt', 
            'etx' => 'text/x-setext', 
            'xsl' => 'text/xml', 
            'xml' => 'text/xml', 
            'mpeg' => 'video/mpeg', 
            'mpg' => 'video/mpeg', 
            'mpe' => 'video/mpeg', 
            'qt' => 'video/quicktime', 
            'mov' => 'video/quicktime', 
            'mxu' => 'video/vnd.mpegurl', 
            'avi' => 'video/x-msvideo', 
            'movie' => 'video/x-sgi-movie', 
            'wmv' => 'application/x-mplayer2',
            'ice' => 'x-conference/x-cooltalk', 
        ); 
    
    function Download($filepath='',$downname='')
    {
        if($filepath == '' AND !$this->filepath)
        {
            $this->ErrInfo = $this->_LANG['err'] . ':' . $this->_LANG['args_empty'];
            return false;
        }
        if($filepath == '') $filepath = $this->filepath;
        if(!file_exists($filepath))
        {
            $this->ErrInfo = $this->_LANG['err'] . ':' . $this->_LANG['file_not_exists'];
            return false;
        }
        if($downname == '' AND !$this->downname) $downname = $filepath;
        if($downname == '') $downname = $this->downname;
        // 文件扩展名 
        $fileExt = substr(strrchr($filepath, '.'), 1); 
        // 文件类型 
        $fileType = $this->MIMETypes[$fileExt] ? $this->MIMETypes[$fileExt] : 'application/octet-stream'; 
        // 是否是图片 
        $isImage = False; 
        /* 
        简述: getimagesize(), 详见手册 
        说明: 判定某个文件是否为图片的有效手段, 常用在文件上传验证 
        */ 
        $imgInfo = @getimagesize($filepath); 
        if ($imgInfo[2] && $imgInfo['bits']) 
        { 
            $fileType = $imgInfo['mime'];       // 支持不标准扩展名
            $isImage = True; 
        } 
        // 显示方式
        if($this->is_attachment) 
        {
            $attachment = 'attachment';     // 指定弹出下载对话框
        }
        else 
        {
            $attachment = $isImage ? 'inline' : 'attachment'; 
        }
        // 读取文件
        if (is_readable($filepath)) 
        { 
            /* 
            简述: ob_end_clean() 清空并关闭输出缓冲, 详见手册 
            说明: 关闭输出缓冲, 使文件片段内容读取至内存后即被送出, 减少资源消耗 
            */ 
            ob_end_clean(); 
            /* 
            HTTP头信息: 指示客户机可以接收生存期不大于指定时间（秒）的响应 
            */ 
            header('Cache-control: max-age=31536000'); 
            /* 
            HTTP头信息: 缓存文件过期时间(格林威治标准时) 
            */ 
            header('Expires: ' . gmdate('D, d M Y H:i:s', time()+31536000) . ' GMT'); 
            /* 
            HTTP头信息: 文件在服务期端最后被修改的时间 
            Cache-control,Expires,Last-Modified 都是控制浏览器缓存的头信息 
            在一些访问量巨大的门户, 合理的设置缓存能够避免过多的服务器请求, 一定程度下缓解服务器的压力 
            */ 
            // header('Last-Modified: ' . gmdate('D, d M Y H:i:s' , filemtime($filepath) . ' GMT')); 
            /* 
            HTTP头信息: 文档的编码(Encode)方法, 因为附件请求的文件多样化, 改变编码方式有可能损坏文件, 故为none 
            */ 
            header('Content-Encoding: none'); 
            /* 
            HTTP头信息: 告诉浏览器当前请求的文件类型. 
            1.始终指定为application/octet-stream, 就代表文件是二进制流, 始终提示下载. 
            2.指定对应的类型, 如请求的是mp3文件, 对应的MIME类型是audio/mpeg, IE就会自动启动Windows Media Player进行播放. 
            */ 
            header('Content-type: ' . $fileType); 
            /* 
            HTTP头信息: 如果为attachment, 则告诉浏览器, 在访问时弹出”文件下载”对话框, 并指定保存时文件的默认名称(可以与服务器的文件名不同) 
            如果要让浏览器直接显示内容, 则要指定为inline, 如图片, 文本 
            */ 
            header('Content-Disposition: ' . $attachment . '; filename=' . $downname); 
            /* 
            HTTP头信息: 告诉浏览器文件长度 
            (IE下载文件的时候不是有文件大小信息么?) 
            */ 
            header('Content-Length: ' . filesize($filepath)); 
            // 打开文件(二进制只读模式)
            $fp = fopen($filepath, 'rb'); 
            // 输出文件
            fpassthru($fp); 
            // 关闭文件
            fclose($fp); 
            return true;
        }
        else 
        {
            $this->error = $this->_LANG['err'] . ':' . $this->_LANG['file_not_readable'];
            return false;
        }
    }
}