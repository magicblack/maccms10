<?php

namespace addons\aicontent\controller;

use think\addons\Controller;
use addons\aicontent\model\AiTask;
use addons\aicontent\service\ModelFactory;
use addons\aicontent\Aicontent;

/**
 * Backend admin controller for the AI Content Assistant plugin.
 * Routes under: /addons/aicontent/admin/
 */
class Admin extends Controller
{
    protected $noNeedLogin  = [];
    protected $noNeedRight  = [];

    public function _initialize()
    {
        parent::_initialize();

        // Verify MaCMS admin session.
        // MaCMS requires renaming admin.php (e.g. to manage.php / abcd.php).
        // The actual entry filename is only known at runtime via $_SERVER['SCRIPT_NAME']
        // and exposed as the JS global ADMIN_PATH — there is no cookie for it.
        // For non-AJAX requests we therefore return a plain JSON error + exit,
        // since we cannot reliably construct the login URL.
        if (session('admin_auth') !== '1' || empty(session('admin_info'))) {
            echo json_encode(['code' => 0, 'message' => lang('Session expired. Please log in to the admin panel and try again.')]);
            exit;
        }

        if ($this->request->isPost()) {
            $token    = input('_csrf_token', '');
            $expected = Aicontent::generateCsrfToken();
            if (!hash_equals($expected, $token)) {
                echo json_encode(['code' => 0, 'message' => lang('Invalid request token.')]);
                exit;
            }
        }
    }

    /**
     * Dashboard — task history and stats.
     * GET /addons/aicontent/admin/index
     */
    public function index()
    {
        $page    = (int) input('page', 1);
        $history = AiTask::getHistory($page, 20);

        $stats = [
            'total'   => AiTask::count(),
            'done'    => AiTask::where('status', AiTask::STATUS_DONE)->count(),
            'pending' => AiTask::where('status', AiTask::STATUS_PENDING)->count(),
            'error'   => AiTask::where('status', AiTask::STATUS_ERROR)->count(),
        ];

        return $this->fetch('admin/index', [
            'tasks'     => $history['list'],
            'total'     => $history['total'],
            'page'      => $page,
            'stats'     => $stats,
            'providers' => ModelFactory::getProviders(),
            'jsLang'    => Aicontent::jsLangJson(),
        ]);
    }

    /**
     * Manual content generation form.
     * GET  /addons/aicontent/admin/generate
     * POST /addons/aicontent/admin/generate (handled by Api controller)
     */
    public function generate()
    {
        $config    = get_addon_config('aicontent');
        $providers = ModelFactory::getProviders();

        // Build models-per-provider JSON for JS dynamic dropdown
        $modelsMap = [];
        foreach (array_keys($providers) as $slug) {
            $modelsMap[$slug] = ModelFactory::getModelsForProvider($slug);
        }

        return $this->fetch('admin/generate', [
            'config'    => $config,
            'providers' => $providers,
            'modelsMap' => json_encode($modelsMap),
            'jsLang'    => Aicontent::jsLangJson(),
        ]);
    }

    /**
     * View a single task result.
     * GET /addons/aicontent/admin/view?id=123
     */
    public function view()
    {
        $id   = (int) input('id');
        $task = AiTask::get($id);

        if (!$task) {
            $this->error(lang('Task not found'));
        }

        $result = null;
        if ($task->result) {
            $result = json_decode($task->result, true);
        }

        return $this->fetch('admin/view', [
            'task'   => $task->toArray(),
            'result' => $result,
        ]);
    }

    /**
     * Delete a task record.
     * POST /addons/aicontent/admin/delete
     */
    public function delete()
    {
        $id   = (int) input('id');
        $task = AiTask::get($id);

        if (!$task) {
            return json(['code' => 0, 'message' => lang('Task not found')]);
        }

        $task->delete();
        return json(['code' => 1, 'message' => lang('Deleted successfully')]);
    }
}
