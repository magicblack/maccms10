<?php

namespace app\admin\controller;

use app\common\util\TemplateCloudService;

/**
 * AI 模板市场（完整主题包）
 * 与 ResourceHub 资源站采集无关，独立云端目录与安装流程。
 */
class TemplateMarket extends Base
{
    /** @var TemplateCloudService */
    protected $cloud;

    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
        $this->cloud = new TemplateCloudService();
    }

    /**
     * 模板市场首页
     */
    public function index()
    {
        $result = $this->cloud->fetchCatalog(false);
        $items = $result['items'];
        $local = $this->cloud->listLocalTemplates();
        $activeDir = $this->cloud->getActiveTemplateDir();

        $enriched = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dir = isset($item['dir']) ? (string) $item['dir'] : '';
            $installed = isset($local[$dir]);
            $tags = $item['tags'] ?? [];
            if (is_string($tags)) {
                $tags = array_filter(array_map('trim', explode(',', $tags)));
            } elseif (!is_array($tags)) {
                $tags = [];
            }
            $enriched[] = array_merge($item, [
                'tags' => $tags,
                'installed' => $installed,
                'active' => ($dir !== '' && $dir === $activeDir),
                'local_version' => $installed ? ($local[$dir]['version'] ?? '') : '',
            ]);
        }

        $cloudErrorMsg = lang('admin/template_market/cloud_fetch_fail');
        if ($result['error'] === 'signature') {
            $cloudErrorMsg = lang('admin/template_market/signature_invalid');
        }
        $this->assign('items', $enriched);
        $this->assign('local_templates', $local);
        $this->assign('active_dir', $activeDir);
        $this->assign('cloud_error', $this->cloud->isEnabled() && $result['error'] !== '' && empty($items));
        $this->assign('cloud_error_msg', $cloudErrorMsg);
        $this->assign('cloud_enabled', $this->cloud->isEnabled());
        $this->assign('title', lang('admin/template_market/title'));
        return $this->fetch('template_market/index');
    }

    /**
     * 刷新云端目录（AJAX）
     */
    public function refresh()
    {
        if ($err = $this->guardWriteRequest()) {
            return json($err);
        }

        $result = $this->cloud->fetchCatalog(true);
        if (empty($result['items'])) {
            $msg = $result['error'] === 'signature'
                ? lang('admin/template_market/signature_invalid')
                : lang('admin/template_market/cloud_fetch_fail');
            return json([
                'code' => 0,
                'msg' => $msg,
            ]);
        }
        return json([
            'code' => 1,
            'msg' => lang('admin/template_market/refresh_ok'),
            'data' => ['count' => count($result['items'])],
        ]);
    }

    /**
     * 安装主题包（AJAX）
     */
    public function install()
    {
        if ($err = $this->guardWriteRequest()) {
            return json($err);
        }

        $id = input('post.id', '');
        if ($id === '') {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $catalog = $this->cloud->fetchCatalog(true);
        $item = null;
        foreach ($catalog['items'] as $row) {
            if (is_array($row) && isset($row['id']) && (string) $row['id'] === (string) $id) {
                $item = $row;
                break;
            }
        }
        if ($item === null) {
            return json(['code' => 0, 'msg' => lang('admin/template_market/not_found')]);
        }

        $res = $this->cloud->installPackage($item);
        return json([
            'code' => $res['code'],
            'msg' => $res['msg'],
            'data' => $res['data'],
        ]);
    }

    /**
     * 启用已安装模板（AJAX）
     */
    public function activate()
    {
        if ($err = $this->guardWriteRequest()) {
            return json($err);
        }

        $dir = input('post.dir', '');
        $res = $this->cloud->activateTemplate($dir);
        return json([
            'code' => $res['code'],
            'msg' => $res['msg'],
        ]);
    }

    /**
     * 写操作：仅 POST + CSRF token
     * @return array|null 失败时返回 json 数组，成功返回 null
     */
    protected function guardWriteRequest()
    {
        if (!request()->isPost()) {
            return ['code' => 0, 'msg' => lang('illegal_request')];
        }
        $param = input('post.');
        $validate = \think\Loader::validate('Token');
        if (!$validate->check($param)) {
            return ['code' => 0, 'msg' => lang($validate->getError())];
        }
        return null;
    }
}
