<?php
namespace app\common\util;

class Pinyin
{
    private static $pinyins = null;

    public function __construct() {

    }

    public static function get($str, $ret_format = 'all', $placeholder = '', $allow_chars = '/[a-zA-Z\d ]/') {

        if (null === self::$pinyins) {
            $data = file_get_contents('./static/data/pinyin.dat');

            $rows = explode('|', $data);

            self::$pinyins = array();
            foreach($rows as $v) {
                list($py, $vals) = explode(':', $v);
                $chars = explode(',', $vals);

                foreach ($chars as $char) {
                    self::$pinyins[$char] = $py;
                }
            }
        }

        $str = trim($str);
        $len = mb_strlen($str, 'UTF-8');
        $rs = '';
        for ($i = 0; $i < $len; $i++) {
            $chr = mb_substr($str, $i, 1, 'UTF-8');
            $asc = ord($chr);
            if ($asc < 0x80) { // 0-127
                if (preg_match($allow_chars, $chr)) { // 用参数控制正则
                    $rs .= $chr; // 0-9 a-z A-Z 空格
                } else { // 其他字符用填充符代替
                    $rs .= $placeholder;
                }
            } else { // 128-255
                if (isset(self::$pinyins[$chr])) {
                    $rs .= 'first' === $ret_format ? self::$pinyins[$chr][0] : (self::$pinyins[$chr] . '');
                } else {
                    $rs .= $placeholder;
                }
            }

            if ('one' === $ret_format && '' !== $rs) {
                return $rs[0];
            }
        }
        $rs = str_replace([' ','+','/','\\','|','\'','?','%','#','&','=','!','(',')',';',':','<','>'],'',$rs);
        return $rs;
    }

}