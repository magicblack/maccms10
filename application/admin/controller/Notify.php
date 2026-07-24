<?php
namespace app\admin\controller;
use think\Db;

class Notify extends Base
{
    public function __construct()
    {
        parent::__construct();
        // admin 模块统一使用新版后台模板目录 view_new/。
        // Init 会把 template.view_path 指向前台模板目录，模块相对 fetch 需显式指定，否则会白屏。
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');

    }

    public function index()
    {
        $param = input();
        $param['page'] = intval($param['page']) < 1 ? 1 : intval($param['page']);
        $param['limit'] = intval($param['limit']) < 1 ? $this->_pagesize : intval($param['limit']);

        $where = [];
        $where['user_id'] = ['eq', 0];
        if (in_array($param['type'], ['system', 'order', 'vip', 'activity', 'reply', 'announce'], true)) {
            $where['notify_type'] = ['eq', $param['type']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = htmlspecialchars(urldecode($param['wd']));
            $where['notify_title'] = ['like', '%' . $param['wd'] . '%'];
        }

        $order = 'notify_id desc';
        $res = model('Notify')->listData($where, $order, $param['page'], $param['limit']);

        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $res['page']);
        $this->assign('limit', $res['limit']);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        $this->assign('title', lang('admin/notify/title'));
        return $this->fetch('notify/index');
    }

    public function info()
    {
        $param = input();
        $info = [];
        if (!empty($param['id'])) {
            $where = [];
            $where['notify_id'] = ['eq', intval($param['id'])];
            $res = model('Notify')->infoData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            $info = $res['info'];
        }
        $pushCfg = config('maccms.push');
        $this->assign('push_enabled', (is_array($pushCfg) && !empty($pushCfg['enable'])) ? 1 : 0);
        $this->assign('info', $info);
        $this->assign('title', lang('admin/notify/broadcast_title'));
        return $this->fetch('notify/info');
    }

    public function broadcast()
    {
        $param = input('post.');
        $type = isset($param['notify_type']) ? trim($param['notify_type']) : '';
        $title = isset($param['notify_title']) ? trim($param['notify_title']) : '';
        $content = isset($param['notify_content']) ? trim($param['notify_content']) : '';
        $link = isset($param['notify_link']) ? trim($param['notify_link']) : '';

        // 人工广播仅允许运营类：公告 / 活动；系统·订单·VIP·回复由业务代码触发
        if (!in_array($type, ['announce', 'activity'], true)) {
            return $this->error(lang('notify/type_invalid'));
        }
        $title = mac_filter_xss($title);
        $content = mac_filter_xss($content);
        $link = $this->sanitizeNotifyLink($link);
        if ($link === false) {
            return $this->error(lang('param_err'));
        }
        if (empty($title) || empty($content)) {
            return $this->error(lang('param_err'));
        }

        $res = model('Notify')->broadcast($type, $title, $content, $link);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }

        // Web Push：仅当后台勾选“同时推送”且推送功能已启用时触发。
        // 只做「入队」（毫秒级），真正的逐条 HTTPS POST 由定时任务 timming/pushbroadcast
        // 分批异步派发，避免在管理员请求内同步阻塞导致订阅量大时超时/502。
        $alsoPush = isset($param['also_push']) && $param['also_push'] == 1;
        $pushCfg = config('maccms.push');
        if ($alsoPush && is_array($pushCfg) && !empty($pushCfg['enable'])) {
            try {
                $body = trim(preg_replace('/\s+/u', ' ', strip_tags($content)));
                if (mb_strlen($body, 'UTF-8') > 120) {
                    $body = mb_substr($body, 0, 120, 'UTF-8') . '...';
                }
                \app\common\util\PushDispatcher::enqueueBroadcast([
                    'title' => $title,
                    'body'  => $body,
                    'url'   => $link,
                ]);
            } catch (\Exception $e) {
                \think\Log::record('push_broadcast_error:' . $e->getMessage(), 'error');
            }
        }


        return $this->success($res['msg'], url('notify/index'));
    }

    public function del()
    {
        $param = input();
        $ids = isset($param['ids']) ? $param['ids'] : '';
        $all = isset($param['all']) ? $param['all'] : '';
        if ($all == 1) {
            $where = [];
            $where['user_id'] = ['eq', 0];
            $where['notify_id'] = ['gt', 0];
            $res = model('Notify')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        if (!empty($ids)) {
            $where = [];
            $where['notify_id'] = ['in', $ids];
            $res = model('Notify')->delData($where);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        return $this->error(lang('param_err'));
    }

    public function field()
    {
        $param = input();
        if (empty($param['id']) || !isset($param['col']) || !isset($param['val'])) {
            return $this->error(lang('param_err'));
        }
        if (!in_array($param['col'], ['notify_read'], true)) {
            return $this->error(lang('param_err'));
        }
        $where = [];
        $where['notify_id'] = ['eq', intval($param['id'])];
        $res = model('Notify')->fieldData($where, $param['col'], $param['val']);
        if ($res['code'] > 1) {
            return $this->error($res['msg']);
        }
        return $this->success($res['msg']);
    }

    /**
     * 跳转链接白名单：空、站内相对路径（非 //）、http(s)
     * 拒绝 javascript: / data: / 协议相对 URL 等
     * @param string $link
     * @return string|false
     */
    private function sanitizeNotifyLink($link)
    {
        $link = trim(mac_scalar_string($link));
        if ($link === '') {
            return '';
        }
        // 站内相对路径：以单个 / 开头，且不是 //host
        if (isset($link[0]) && $link[0] === '/' && (!isset($link[1]) || $link[1] !== '/')) {
            return mac_filter_xss($link);
        }
        if (preg_match('/^https?:\/\//i', $link)) {
            return mac_filter_xss($link);
        }
        return false;
    }
}
