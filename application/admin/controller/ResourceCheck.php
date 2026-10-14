<?php
namespace app\admin\controller;

use app\common\util\ContentCheck;

/**
 * 内容资源检测：日志查看 + 管理员恢复被停用的内容
 */
class ResourceCheck extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function index()
    {
        $param = input();
        $page = max(1, intval($param['page']));
        $limit = intval($param['limit']) < 1 ? 50 : intval($param['limit']);
        // 查看单条内容（带 id）时默认搜索全部日期，否则默认今天
        if (isset($param['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['date'])) {
            $date = $param['date'];
        } elseif (!empty($param['id']) && (!isset($param['date']) || $param['date'] === '' || $param['date'] === 'all')) {
            $date = '';
        } else {
            $date = date('Y-m-d');
        }
        $filter = [
            'module' => isset($param['module']) && isset(ContentCheck::$modules[$param['module']]) ? $param['module'] : '',
            'kind' => isset($param['kind']) && in_array($param['kind'], ['summary', 'fail', 'partial', 'disable', 'admin_disable', 'enable', 'recollect'], true) ? $param['kind'] : '',
            'id' => isset($param['id']) ? intval($param['id']) : 0,
            'wd' => isset($param['wd']) ? trim((string)$param['wd']) : '',
        ];
        $res = ContentCheck::readLogs($date, $filter, $page, $limit);

        // 当日检测进度
        $cursor = ContentCheck::cursorInfo();
        $progress = '';
        if (!empty($cursor['date'])) {
            $cursor['date'] = substr($cursor['date'], 0, 4) . '-' . substr($cursor['date'], 4, 2) . '-' . substr($cursor['date'], 6, 2);
            $names = array_keys(ContentCheck::$modules);
            $cur = isset($names[$cursor['idx']]) ? $names[$cursor['idx']] : '';
            $progress = !empty($cursor['done'])
                ? lang('admin/content_check/progress_done', [$cursor['date']])
                : lang('admin/content_check/progress_running', [$cursor['date'], $cur !== '' ? lang('admin/content_check/m_' . $cur) : '-', intval($cursor['last_id'])]);
        }

        // 按单条内容查看时，显示其当前状态并可直接启用 / 停用
        $record = null;
        if ($filter['id'] > 0 && $filter['module'] !== '' && ContentCheck::$modules[$filter['module']]['cols'] && ContentCheck::hasColumns($filter['module'])) {
            $m = $filter['module'];
            $record = \think\Db::name($m)->field($m . '_id id,' . $m . '_name name,' . $m . '_status status,' . $m . '_check_state state,' . $m . '_check_reason reason,' . $m . '_check_time time')->where($m . '_id', $filter['id'])->find();
        }
        $this->assign('record', $record);
        $this->assign('mid', $filter['module'] !== '' ? ContentCheck::$modules[$filter['module']]['mid'] : 0);
        $this->assign('list', $res['list']);
        $this->assign('total', $res['total']);
        $this->assign('page', $page);
        $this->assign('limit', $limit);
        $this->assign('dates', ContentCheck::logDates());
        $this->assign('modules', array_keys(ContentCheck::$modules));
        $this->assign('progress', $progress);
        $param = array_merge($filter, ['date' => $date]);
        $this->assign('filter', $param);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        $this->assign('title', lang('admin/content_check/log_title'));
        return $this->fetch('resource_check/index');
    }

    /**
     * 恢复启用（mid + ids，可批量；reason 可选，写入日志）
     */
    public function enable()
    {
        // 启停内容为写操作：强制 CSRF 令牌（不依赖全局 security_csrf_admin）
        if (!$this->adminCsrfOk()) {
            return json(['code' => 0, 'msg' => lang('token_err')]);
        }
        list($m, $ids) = $this->targetIds();
        if ($m === '') {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        $n = ContentCheck::enable($m, $ids, $this->_admin['admin_name'], mb_substr(trim(input('post.reason', '', 'trim')), 0, 200));
        if ($n < 1) {
            return json(['code' => 0, 'msg' => lang('admin/content_check/enable_none')]);
        }
        return json(['code' => 1, 'msg' => lang('admin/content_check/enable_ok', [$n])]);
    }

    /**
     * 管理员停用（mid + ids，可批量；reason 必填）
     */
    public function disable()
    {
        // 启停内容为写操作：强制 CSRF 令牌（不依赖全局 security_csrf_admin）
        if (!$this->adminCsrfOk()) {
            return json(['code' => 0, 'msg' => lang('token_err')]);
        }
        list($m, $ids) = $this->targetIds();
        $reason = mb_substr(trim(input('post.reason', '', 'trim')), 0, 200);
        if ($m === '') {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        if ($reason === '') {
            return json(['code' => 0, 'msg' => lang('admin/content_check/reason_required')]);
        }
        $n = ContentCheck::disable($m, $ids, $this->_admin['admin_name'], $reason);
        if ($n < 1) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        return json(['code' => 1, 'msg' => lang('admin/content_check/disable_ok', [$n])]);
    }

    /**
     * 列表批量提交 ids[]，单条提交 ids=1 或 ids=1,2
     * @return array [module, ids]
     */
    private function targetIds()
    {
        if (!Request()->isPost()) {
            return ['', []];
        }
        $m = ContentCheck::moduleByMid(input('mid'));
        $ids = [];
        foreach ((array)input('ids/a') as $v) {
            $ids = array_merge($ids, explode(',', (string)$v));
        }
        $ids = array_filter(array_map('intval', $ids));
        return empty($ids) ? ['', []] : [$m, $ids];
    }
}
