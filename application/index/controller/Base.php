<?php
namespace app\index\controller;
use think\Controller;
use app\common\controller\All;
use ip_limit\IpLocationQuery;
class Base extends All
{
    var $_group;
    var $_user;

    public function __construct()
    {
        parent::__construct();

        $this->capture_invite_ref();
        $this->check_ip_limit();
        $this->check_site_status();
        $this->label_maccms();
        $this->check_browser_jump();
        $this->label_user();
        $this->init_content_lang();
    }

    /**
     * 解析本次请求的内容语言（GET lang -> cookie -> Accept-Language -> 默认语言），
     * 存到 ContentLang::currentLang 供 Vod/Art 的 infoData() 读取，并派给模板方便渲染切换器。
     */
    protected function init_content_lang()
    {
        $lang = mac_get_content_lang();
        \app\common\model\ContentLang::setCurrent($lang);
        $this->assign('content_lang', $lang);
        $this->assign('content_lang_default', mac_content_lang_default());
        $this->assign('content_lang_list', mac_content_lang_allow_list());
    }

    /**
     * 推广/邀请关系全站捕获：无论访客从哪个页面（首页、详情页等）携带
     * ?uid= 或 ?invite_code= 进站，都记录到 Cookie，供后续在任意页面
     * 完成的注册（含登录/注册一体化弹窗）使用，而不仅限于 /user/reg。
     */
    protected function capture_invite_ref()
    {
        $param = input();
        if (!empty($param['uid'])) {
            cookie('uid', intval($param['uid']));
        }
        if (!empty($param['invite_code'])) {
            cookie('invite_code', trim($param['invite_code']));
        }
    }

    protected function check_ip_limit()
    {
       
        // 获取IP限制配置
        $mainland_ip_limit = $GLOBALS['config']['site']['mainland_ip_limit'] ?? "0";

        // 如果为0，不限制，直接通过
        if ($mainland_ip_limit == "0") {
            return;
        }
        
        // 获取用户真实IP
        $user_ip = mac_get_client_ip();
        try {
            $ipQuery = new IpLocationQuery();
            $country_code = $ipQuery->queryProvince($user_ip);
            // 根据配置进行限制
            if ($mainland_ip_limit == "1") {
                // 只允许中国大陆IP
                if ($country_code === "") {
                    $this->echo_site_closed();
                    return;
                }
            } elseif ($mainland_ip_limit == "2") {
                // 不允许中国大陆IP
                if ($country_code !== "") {
                    $this->echo_site_closed();
                    return;
                }
            }
            
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // 局域网IP或无效IP，直接通过
            return;
        } catch (\Exception $e) {
            // 其他异常
            return;
        }
    }

    public function _empty()
    {
        header("HTTP/1.0 404 Not Found");
        echo  '<script>setTimeout(function (){location.href="'.MAC_PATH.'";},'.(2000).');</script>';
        $msg = lang('page_not_found');
        abort(404,$msg);
        exit;
    }

    protected function check_show($aj=0)
    {
        if($GLOBALS['config']['app']['show'] ==0){
            echo $this->error(lang('show_close'));
            exit;
        }
        if($GLOBALS['config']['app']['show_verify'] ==1 && $aj==0){
            if(empty(session('show_verify'))){
                mac_no_cahche();
                $this->assign('type','show');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    protected function check_ajax()
    {
        if($GLOBALS['config']['app']['ajax_page'] ==0){
            echo $this->error(lang('ajax_close'));
            exit;
        }
    }

    protected function check_search($param,$aj=0)
    {
        if($GLOBALS['config']['app']['search'] ==0){
            echo $this->error(lang('search_close'));
            exit;
        }
        $page = isset($param['page']) ? (int)$param['page'] : 1;
        // 同一检索条件下切换排序（by/order）、翻页、重复点击不算新搜索，不受间隔限制；
        // 只有检索条件本身变化才计入频次
        $sig_param = is_array($param) ? $param : [];
        unset($sig_param['page'], $sig_param['by'], $sig_param['order']);
        ksort($sig_param);
        $search_sig = md5(request()->controller() . '|' . json_encode($sig_param));
        $same_search = session('last_search_sig') === $search_sig;
        session('last_search_sig', $search_sig);
        // 主题设计预览会频繁刷新，跳过搜索间隔限制
        if (!(function_exists('mac_td_preview_mode') && mac_td_preview_mode())
            && $page === 1
            && !$same_search
            && mac_get_time_span("last_searchtime") < $GLOBALS['config']['app']['search_timespan']) {
            // lang('seconds') 是「秒前」（相对时间），这里不能复用
            $span = (int) $GLOBALS['config']['app']['search_timespan'];
            echo $this->error(lang('search_frequently') . $span . lang('second_unit'));
            exit;
        }
        if($GLOBALS['config']['app']['search_verify'] ==1 && $aj ==0){
            if(empty(session('search_verify'))){
                mac_no_cahche();
                $this->assign('type','search');
                echo $this->label_fetch('public/verify');
                exit;
            }
        }
    }

    protected function check_site_status()
    {
        if ($GLOBALS['config']['site']['site_status'] == 0) {
            $this->echo_site_closed($GLOBALS['config']['site']['site_close_tip']);
        }
    }

    /**
     * 站点关闭/维护：走 label_fetch 渲染 public/close（含 maccms 变量与 Twig 路径）。
     * 主题设计预览模式豁免，便于后台预览草稿。
     *
     * @param string $closeTip
     */
    private function echo_site_closed($closeTip = '')
    {
        if (function_exists('mac_td_preview_mode') && mac_td_preview_mode()) {
            return;
        }
        if ($closeTip === '' && !empty($GLOBALS['config']['site']['site_close_tip'])) {
            $closeTip = (string)$GLOBALS['config']['site']['site_close_tip'];
        }
        $this->label_maccms();
        $this->assign('close_tip', $closeTip);
        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
        exit($this->label_fetch('public/close', 0));
    }

    protected function check_browser_jump()
    {
        if (ENTRANCE=='index' && $GLOBALS['config']['app']['browser_junmp'] == 1) {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            if(strpos($agent, 'QQ/')||strpos($agent, 'MicroMessenger')!==false){
                echo $this->fetch('public/browser');
                die;
            }
        }
    }
}