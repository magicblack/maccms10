<?php
namespace app\common\util;

class Collection {

    protected static $url,$config;

    /**
     * 采集内容
     * @param string $url    采集地址
     * @param array $config  配置参数
     * @param integer $page  分页采集模式
     */
    public static function get_content($url, $config, $page = 0) {
        set_time_limit(300);
        static $oldurl = array();
        $page = intval($page) ? intval($page) : 0;
        if ($html = self::get_html($url, $config)) {

            if (empty($page)) {
                //获取标题
                if ($config['title_rule']) {
                    if(strpos($config['title_rule'],'[内容]')===false){
                        $data['title'] = $config['title_rule'];
                    }
                    else{
                        $title_rule = self::replace_sg($config['title_rule']);
                        $data['title'] = self::replace_item(self::cut_html($html, $title_rule[0], $title_rule[1]), $config['title_html_rule']);
                    }
                }

                //获取分类
                if ($config['type_rule']) {
                    if(strpos($config['type_rule'],'[内容]')===false){
                        $data['type'] = $config['type_rule'];
                    }
                    else{
                        $type_rule =  self::replace_sg($config['type_rule']);
                        $data['type'] = self::replace_item(self::cut_html($html, $type_rule[0], $type_rule[1]), $config['type_html_rule']);
                    }
                }

                if (empty($data['time'])) $data['time'] = time();

                //对自定义数据进行采集
                $config['customize_config']  = json_decode($config['customize_config'],true);

                if($config['customize_config']){
                    foreach ($config['customize_config'] as $k=>$v) {
                        if (empty($v['rule'])) continue;
                        if(strpos($v['rule'],'[内容]')===false){
                            $data[$v['en_name']] = $v['rule'];
                        }
                        else{
                            $rule =  self::replace_sg($v['rule']);
                            $data[$v['en_name']] = self::replace_item(self::cut_html($html, $rule[0], $rule[1]), $v['html_rule']);
                        }
                    }
                }
            }

            //获取内容
            if ($config['content_rule']) {
                if(strpos($config['content_rule'],'[内容]')===false){
                    $data['content'] = $config['content_rule'];
                }
                else{
                    $content_rule =  self::replace_sg($config['content_rule']);
                    $data['content'] = self::replace_item(self::cut_html($html, $content_rule[0], $content_rule[1]), $config['content_html_rule']);
                }

            }


            //处理分页
            if (in_array($page, array(0,2)) && !empty($config['content_page_start']) && !empty($config['content_page_end'])) {
                $oldurl[] = $url;
                $tmp[] = $data['content'];
                $page_html = self::cut_html($html, $config['content_page_start'], $config['content_page_end']);
                //上下页模式
                if ($config['content_page_rule'] == 2 && in_array($page, array(0,2)) && $page_html) {
                    preg_match_all('/<a [^>]*href=[\'"]?([^>\'" ]*)[\'"]?[^>]*>([^<\/]*)<\/a>/i', $page_html, $out);
                    if (!empty($out[1]) && !empty($out[2])) {
                        foreach ($out[2] as $k=>$v) {
                            if (strpos($v, $config['content_nextpage']) === false) continue;
                            if ($out[1][$k] == '#') continue;
                            $out[1][$k] = self::url_check($out[1][$k], $url, $config);
                            if (in_array($out[1][$k], $oldurl)) continue;
                            $oldurl[] = $out[1][$k];
                            $results = self::get_content($out[1][$k], $config, 2);
                            if (!in_array($results['content'], $tmp)) $tmp[] = $results['content'];
                        }
                    }
                }

                //全部罗列模式
                if ($config['content_page_rule'] == 1 && $page == 0 && $page_html) {
                    preg_match_all('/<a [^>]*href=[\'"]?([^>\'" ]*)[\'"]?/i', $page_html, $out);
                    if (is_array($out[1]) && !empty($out[1])) {

                        $out = array_unique($out[1]);
                        foreach ($out as $k=>$v) {
                            if ($out[1][$k] == '#') continue;
                            $v = self::url_check($v, $url, $config);
                            $results = self::get_content($v, $config, 1);
                            if (!in_array($results['content'], $tmp) && $results['content']!="") $tmp[] = $results['content'];
                        }
                    }

                }
                $data['content'] = $config['content_page'] == 1 ? implode('[page]', $tmp) : implode('', $tmp);
            }

            if ($page == 0) {
                self::$url = $url;
                self::$config = $config;
                $data['content'] = preg_replace_callback('/<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>/i', array('collection','download_img_callback'), $data['content']);
                //下载内容中的图片到本地
                if (empty($page) && !empty($data['content']) && $config['down_attachment'] == 1) {

                }
            }
            return $data;
        }
    }

    /**
     * 转换图片地址为绝对路径，为下载做准备。
     * @param array $out 图片地址
     */
    protected static function download_img_callback($matches) {
        return self::download_img($matches[0], $matches[1]);
    }
    protected static function download_img($old, $out) {
        if (!empty($old) && !empty($out) && strpos($out, '://') === false) {
            return str_replace($out, self::url_check($out, self::$url, self::$config), $old);
        } else {
            return $old;
        }
    }

    /**
     * 得到需要采集的网页列表页
     * @param array $config 配置参数
     * @param integer $num  返回数
     */
    public static function url_list(&$config, $num = '') {
        $url = array();

        switch ($config['sourcetype']) {
            case '1'://序列化
                $num = empty($num) ? $config['pagesize_end'] : $num;
                if($num<$config['pagesize_start']) $num=$config['pagesize_start'];
                $p=0;
                for ($i = $config['pagesize_start']; $i <= $num; $i = $i + $config['par_num']) {
                    $url[$p] = str_replace('(*)', $i, $config['urlpage']);
                    $p++;
                }
                break;
            case '2'://多网址
                $url = explode("\r\n", $config['urlpage']);
                break;
            case '3'://单一网址
            case '4'://RSS
                $url[] = $config['urlpage'];
                break;
        }
        return $url;
    }

    /**
     * 获取文章网址
     * @param string $url           采集地址
     * @param array $config         配置
     */
    public static function get_url_lists($url, &$config) {
        if ($html = self::get_html($url, $config)) {
            if ($config['sourcetype'] == 4) { //RSS
                $xml = pc_base::load_sys_class('xml');
                $html = $xml->xml_unserialize($html);
                if (pc_base::load_config('system', 'charset') == 'gbk') {
                    $html = array_iconv($html, 'utf-8', 'gbk');
                }
                $data = array();
                if (is_array($html['rss']['channel']['item']))foreach ($html['rss']['channel']['item'] as $k=>$v) {
                    $data[$k]['url'] = $v['link'];
                    $data[$k]['title'] = $v['title'];
                }
            } else {
                $html = self::cut_html($html, $config['url_start'], $config['url_end']);

                $html = str_replace(array("\r", "\n"), '', $html);
                $html = str_replace(array("</a>", "</A>"), "</a>\n", $html);

                preg_match_all('/<a ([^>]*)>([^\/a>].*)<\/a>/i', $html, $out);
                //$out[1] = array_unique($out[1]);
                //$out[2] = array_unique($out[2]);

                $data = array();
                foreach ($out[1] as $k=>$v) {
                    if (preg_match('/href=[\'"]?([^\'" ]*)[\'"]?/i', $v, $match_out)) {
                        if ($config['url_contain']) {
                            if (strpos($match_out[1], $config['url_contain']) === false) {
                                continue;
                            }
                        }

                        if ($config['url_except']) {
                            if (strpos($match_out[1], $config['url_except']) !== false) {
                                continue;
                            }
                        }
                        $url2 = $match_out[1];
                        $url2 = self::url_check($url2, $url, $config);

                        $data[$k]['url'] = $url2;
                        $data[$k]['title'] = strip_tags($out[2][$k]);
                    } else {
                        continue;
                    }
                }

            }
            return $data;
        } else {
            return false;
        }
    }

    /**
     * 获取远程HTML
     * @param string $url    获取地址
     * @param array $config  配置
     */
    protected static function get_html($url, &$config) {
        if (!empty($url) && $html = mac_curl_get($url)) {
            if ('UTF-8' != $config['sourcecharset'] && $config['sourcetype'] != 4) {
                $html = iconv($config['sourcecharset'], 'UTF-8//TRANSLIT//IGNORE', $html);
            }
            return $html;
        } else {
            return false;
        }
    }

    /**
     *
     * HTML切取
     * @param string $html    要进入切取的HTML代码
     * @param string $start   开始
     * @param string $end     结束
     */
    protected static function cut_html($html, $start, $end) {
        if (empty($html)) return false;
        $html = str_replace(array("\r", "\n"), "", $html);
        $start = str_replace(array("\r", "\n"), "", $start);
        $end = str_replace(array("\r", "\n"), "", $end);
        $html = explode(trim($start), $html);
        if(is_array($html)) $html = explode(trim($end), $html[1]);
        return trim($html[0]);
    }

    /**
     * 过滤代码
     * @param string $html  HTML代码
     * @param array $config 过滤配置
     */
    protected static function replace_item($html, $config) {
        if (empty($config)) return $html;
        $config = explode("\n", $config);
        $patterns = $replace = array();
        $p = 0;
        foreach ($config as $k=>$v) {
            if (empty($v)) continue;
            $c = explode('[|]', $v);
            $patterns[$k] = '/'.str_replace('/', '\/', $c[0]).'/i';
            $replace[$k] = $c[1];
            $p = 1;
        }
        return $p ? @preg_replace($patterns, $replace, $html) : false;
    }

    /**
     * 替换采集内容
     * @param $html 采集规则
     */
    protected static function replace_sg($html) {
        $list = explode('[内容]', $html);
        if (is_array($list)) foreach ($list as $k=>$v) {
            $list[$k] = str_replace(array("\r", "\n"), '', trim($v));
        }
        return $list;
    }

    /**
     * URL地址检查
     * @param string $url      需要检查的URL
     * @param string $baseurl  基本URL
     * @param array $config    配置信息
     */
    protected static function url_check($url, $baseurl, $config) {
        $urlinfo = parse_url($baseurl);

        $baseurl = $urlinfo['scheme'].'://'.$urlinfo['host'].(substr($urlinfo['path'], -1, 1) === '/' ? substr($urlinfo['path'], 0, -1) : str_replace('\\', '/', dirname($urlinfo['path']))).'/';
        if (strpos($url, '://') === false) {
            if ($url[0] == '/') {
                $url = $urlinfo['scheme'].'://'.$urlinfo['host'].$url;
            } else {
                if ($config['page_base']) {
                    $url = $config['page_base'].$url;
                } else {
                    $url = $baseurl.$url;
                }
            }
        }
        return $url;
    }
}