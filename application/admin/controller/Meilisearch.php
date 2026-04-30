<?php

namespace app\admin\controller;

use app\common\util\MeilisearchService;
use app\common\util\MeilisearchSync;
use app\common\util\MeilisearchHttp;
use app\common\util\OpenccConverter;

class Meilisearch extends Base
{
    public function __construct()
    {
        parent::__construct();
        if ((string)$this->_admin['admin_id'] !== '1') {
            $this->error(lang('admin/meilisearch/super_admin_only'));
        }
    }

    public function index()
    {
        $cfg = MeilisearchService::cfg();
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $cfg = array_merge([
            'enabled' => '0',
            'host' => '',
            'api_key' => '',
            'index_uid' => 'maccms_contents',
            'timeout' => '8',
            'ssl_verify' => '1',
            'sync_on_save' => '1',
            'search_only_wd' => '1',
        ], $cfg);
        $h = MeilisearchService::health();
        $stats = ['numberOfDocuments' => 0];
        if ((string)$cfg['enabled'] === '1') {
            $uid = rawurlencode((string)$cfg['index_uid']);
            $statsRes = MeilisearchHttp::request(
                rtrim((string)$cfg['host'], '/'),
                'GET',
                '/indexes/' . $uid . '/stats',
                (string)$cfg['api_key'],
                null,
                max(1, (int)$cfg['timeout']),
                (string)$cfg['ssl_verify'] !== '0'
            );
            if (!empty($statsRes['ok']) && is_array($statsRes['data'] ?? null)) {
                $stats['numberOfDocuments'] = (int)($statsRes['data']['numberOfDocuments'] ?? 0);
            }
        }
        $this->assign('health', $h);
        $this->assign('stats', $stats);
        $this->assign('opencc_available', OpenccConverter::available());
        $this->assign('cfg', $cfg);
        $this->assign('meili_key_saved', trim((string)$cfg['api_key']) !== '' ? 1 : 0);
        $this->assign('meili_key_tail', trim((string)$cfg['api_key']) !== '' ? substr((string)$cfg['api_key'], -6) : '');
        return $this->fetch('admin@meilisearch/index');
    }

    public function status()
    {
        return json(MeilisearchService::health());
    }

    /**
     * 保存 Meilisearch 配置到 maccms.php。
     */
    public function save()
    {
        if (!request()->isPost()) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }
        $post = input('post.');
        $meili = isset($post['meilisearch']) && is_array($post['meilisearch']) ? $post['meilisearch'] : [];
        $sanitize = function ($v) {
            return trim(strip_tags((string)$v));
        };
        $cfgOld = config('maccms');
        $cfgNew = $cfgOld;
        $row = [
            'enabled' => isset($meili['enabled']) && (string)$meili['enabled'] === '1' ? '1' : '0',
            'host' => rtrim($sanitize(isset($meili['host']) ? $meili['host'] : ''), '/'),
            'index_uid' => $sanitize(isset($meili['index_uid']) ? $meili['index_uid'] : 'maccms_contents'),
            'timeout' => (string)max(1, intval(isset($meili['timeout']) ? $meili['timeout'] : 8)),
            'ssl_verify' => isset($meili['ssl_verify']) && (string)$meili['ssl_verify'] === '0' ? '0' : '1',
            'sync_on_save' => isset($meili['sync_on_save']) && (string)$meili['sync_on_save'] === '0' ? '0' : '1',
            'search_only_wd' => isset($meili['search_only_wd']) && (string)$meili['search_only_wd'] === '0' ? '0' : '1',
        ];
        if ($row['index_uid'] === '') {
            $row['index_uid'] = 'maccms_contents';
        }
        $newKey = isset($meili['api_key']) ? trim((string)$meili['api_key']) : '';
        if ($newKey !== '') {
            $row['api_key'] = $newKey;
        } else {
            $latest = is_file(APP_PATH . 'extra/maccms.php') ? include APP_PATH . 'extra/maccms.php' : [];
            if (isset($latest['meilisearch']['api_key']) && trim((string)$latest['meilisearch']['api_key']) !== '') {
                $row['api_key'] = (string)$latest['meilisearch']['api_key'];
            } else {
                $row['api_key'] = isset($cfgOld['meilisearch']['api_key']) ? (string)$cfgOld['meilisearch']['api_key'] : '';
            }
        }
        $cfgNew['meilisearch'] = $row;
        $res = mac_arr2file(APP_PATH . 'extra/maccms.php', $cfgNew);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('save_err')]);
        }
        return json(['code' => 1, 'msg' => lang('save_ok')]);
    }

    /**
     * 一键自检：Meilisearch 健康、索引统计、OpenCC 可用性、示例查询。
     */
    public function selfcheck()
    {
        $cfg = MeilisearchService::cfg();
        $health = MeilisearchService::health();
        $enabled = MeilisearchService::enabled();
        $opencc = OpenccConverter::available();
        $sampleQuery = trim((string)input('wd', ''));
        if ($sampleQuery === '') {
            $rawHot = (string)($GLOBALS['config']['app']['search_hot'] ?? '');
            $rawHot = str_replace('，', ',', $rawHot);
            $first = trim((string)strtok($rawHot, ','));
            $sampleQuery = $first !== '' ? $first : '测试';
        }

        $stats = ['ok' => false, 'status' => 0, 'data' => null];
        $sampleSearch = ['ok' => false, 'status' => 0, 'data' => null];
        if ($enabled) {
            $uid = rawurlencode(MeilisearchService::indexUid());
            $stats = MeilisearchHttp::request(
                MeilisearchService::host(),
                'GET',
                '/indexes/' . $uid . '/stats',
                MeilisearchService::apiKey(),
                null,
                MeilisearchService::timeout(),
                MeilisearchService::sslVerify()
            );
            $sampleSearch = MeilisearchHttp::request(
                MeilisearchService::host(),
                'POST',
                '/indexes/' . $uid . '/search',
                MeilisearchService::apiKey(),
                ['q' => $sampleQuery, 'limit' => 5],
                MeilisearchService::timeout(),
                MeilisearchService::sslVerify()
            );
        }

        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'cfg' => [
                    'enabled' => !empty($cfg['enabled']) ? 1 : 0,
                    'host' => (string)($cfg['host'] ?? ''),
                    'index_uid' => (string)($cfg['index_uid'] ?? ''),
                    'timeout' => (int)($cfg['timeout'] ?? 0),
                    'ssl_verify' => isset($cfg['ssl_verify']) && (string)$cfg['ssl_verify'] === '0' ? 0 : 1,
                ],
                'health' => $health,
                'index_stats' => [
                    'ok' => !empty($stats['ok']),
                    'status' => (int)($stats['status'] ?? 0),
                    'data' => $stats['data'] ?? null,
                    'error' => (string)($stats['error'] ?? ''),
                ],
                'opencc' => ['available' => $opencc ? 1 : 0],
                'sample_query' => $sampleQuery,
                'sample_search' => [
                    'ok' => !empty($sampleSearch['ok']),
                    'status' => (int)($sampleSearch['status'] ?? 0),
                    'data' => $sampleSearch['data'] ?? null,
                    'error' => (string)($sampleSearch['error'] ?? ''),
                ],
            ],
        ]);
    }

    /**
     * 全量重建索引（仅视频+文章+漫画已发布且未进回收站）。
     */
    public function sync()
    {
        if (request()->isPost() || request()->isAjax()) {
            $r = MeilisearchSync::fullReindex();
            return json($r);
        }
        $this->assign('tip', lang('admin/meilisearch/sync_tip'));
        return $this->fetch('admin@meilisearch/sync');
    }
}
