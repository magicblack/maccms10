<?php

namespace app\common\util;

/**
 * Meilisearch：索引维护与搜索。
 */
class MeilisearchService
{
    /** @var array<string, array{ok:bool,hits:array,estimatedTotalHits:int}> 单次请求内相同参数去重，避免列表与 AI 联想等对 Meili 重复打网 */
    private static $searchMemo = [];

    public static function cfg()
    {
        $c = $GLOBALS['config']['meilisearch'] ?? [];
        return is_array($c) ? $c : [];
    }

    public static function enabled()
    {
        $c = self::cfg();
        return !empty($c['enabled']) && (string)$c['enabled'] === '1'
            && !empty($c['host'])
            && !empty($c['index_uid']);
    }

    public static function host()
    {
        return rtrim((string)(self::cfg()['host'] ?? ''), '/');
    }

    public static function indexUid()
    {
        return (string)(self::cfg()['index_uid'] ?? 'maccms_contents');
    }

    public static function apiKey()
    {
        return (string)(self::cfg()['api_key'] ?? '');
    }

    public static function timeout()
    {
        return max(1, (int)(self::cfg()['timeout'] ?? 8));
    }

    public static function sslVerify()
    {
        $v = self::cfg()['ssl_verify'] ?? '1';
        return (string)$v !== '0';
    }

    public static function syncOnSave()
    {
        $c = self::cfg();
        return !isset($c['sync_on_save']) || (string)$c['sync_on_save'] !== '0';
    }

    /**
     * 已发布内容统一过滤（与索引文档字段 kind / recycle / status 一致）。
     * 供 AI 搜索、AI 聊天、内部联想等与 Meilisearch 共用。
     */
    public static function filterPublishedKind($kind)
    {
        $k = strtolower((string)$kind);
        if (!in_array($k, ['vod', 'art', 'manga', 'topic', 'actor', 'role', 'website'], true)) {
            return '';
        }
        return 'kind = "' . $k . '" AND recycle = 0 AND status = 1';
    }

    public static function health()
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'disabled'];
        }
        $r = MeilisearchHttp::request(self::host(), 'GET', '/health', '', null, self::timeout(), self::sslVerify());
        return ['ok' => !empty($r['ok']), 'status' => $r['status'], 'data' => $r['data']];
    }

    public static function ensureIndex()
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'disabled'];
        }
        $uid = rawurlencode(self::indexUid());
        $r = MeilisearchHttp::request(self::host(), 'GET', '/indexes/' . $uid, self::apiKey(), null, self::timeout(), self::sslVerify());
        if (!empty($r['ok'])) {
            return ['ok' => true, 'created' => false];
        }
        $create = MeilisearchHttp::request(self::host(), 'POST', '/indexes', self::apiKey(), [
            'uid' => self::indexUid(),
            'primaryKey' => 'id',
        ], self::timeout(), self::sslVerify());
        if (!empty($create['ok'])) {
            self::updateSettings();
        }
        return ['ok' => !empty($create['ok']), 'created' => true, 'response' => $create['data'] ?? null];
    }

    /**
     * 索引 settings PATCH 请求体（searchable / filterable / sortable 等）。
     *
     * @return array<string, mixed>
     */
    public static function indexSettingsPayload()
    {
        return [
            'searchableAttributes' => [
                'title', 'subtitle', 'en', 'extra', 'tags', 'class_text',
                'title_py', 'title_initials', 'subtitle_py', 'subtitle_initials',
                'extra_py', 'extra_initials', 'tags_py', 'tags_initials',
                'title_t2s', 'title_s2t', 'subtitle_t2s', 'subtitle_s2t',
                'extra_t2s', 'extra_s2t', 'tags_t2s', 'tags_s2t',
                'blurb', 'body',
            ],
            'filterableAttributes' => [
                'kind', 'type_id', 'type_id_1', 'recycle', 'status', 'level', 'group_id', 'isend', 'plot',
                'year', 'area', 'lang', 'state', 'version', 'rid',
            ],
            'sortableAttributes' => ['hits_month', 'ts'],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'exactness',
                'desc(hits_month)',
                'desc(ts)',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => 3,
                    'twoTypos' => 6,
                ],
                'disableOnWords' => [],
                'disableOnAttributes' => [],
            ],
        ];
    }

    public static function getSettings()
    {
        if (!self::enabled()) {
            return ['ok' => false, 'data' => null];
        }
        $uid = rawurlencode(self::indexUid());
        $r = MeilisearchHttp::request(
            self::host(),
            'GET',
            '/indexes/' . $uid . '/settings',
            self::apiKey(),
            null,
            self::timeout(),
            self::sslVerify()
        );

        return ['ok' => !empty($r['ok']), 'status' => $r['status'] ?? 0, 'data' => $r['data'] ?? null, 'error' => (string)($r['error'] ?? '')];
    }

    /**
     * @param array<string, mixed> $settings GET /settings 响应
     * @return array{ok:bool,missing_filterable:array,missing_searchable:array,filterableAttributes:array,searchableAttributes:array}
     */
    public static function verifyIndexSettings(array $settings)
    {
        $requiredFilter = ['kind', 'recycle', 'status'];
        $requiredSearch = ['title', 'title_t2s', 'title_s2t'];
        $filter = is_array($settings['filterableAttributes'] ?? null) ? $settings['filterableAttributes'] : [];
        $search = is_array($settings['searchableAttributes'] ?? null) ? $settings['searchableAttributes'] : [];
        $missingFilter = array_values(array_diff($requiredFilter, $filter));
        $missingSearch = array_values(array_diff($requiredSearch, $search));

        return [
            'ok' => empty($missingFilter) && empty($missingSearch),
            'missing_filterable' => $missingFilter,
            'missing_searchable' => $missingSearch,
            'filterableAttributes' => $filter,
            'searchableAttributes' => $search,
        ];
    }

    /**
     * 等待 Meilisearch 异步任务完成（settings PATCH 等）。
     *
     * @return array{ok:bool,status:string,skipped?:bool,data?:mixed}
     */
    public static function waitForTask($taskUid, $maxWaitSec = 30)
    {
        $taskUid = (int)$taskUid;
        if ($taskUid <= 0) {
            return ['ok' => true, 'status' => 'skipped', 'skipped' => true];
        }
        $deadline = time() + max(1, (int)$maxWaitSec);
        $last = null;
        while (time() <= $deadline) {
            $r = MeilisearchHttp::request(
                self::host(),
                'GET',
                '/tasks/' . $taskUid,
                self::apiKey(),
                null,
                self::timeout(),
                self::sslVerify()
            );
            $last = $r;
            if (empty($r['ok']) || !is_array($r['data'] ?? null)) {
                break;
            }
            $status = (string)($r['data']['status'] ?? '');
            if ($status === 'succeeded') {
                return ['ok' => true, 'status' => $status, 'data' => $r['data']];
            }
            if ($status === 'failed' || $status === 'canceled') {
                return ['ok' => false, 'status' => $status, 'data' => $r['data']];
            }
            usleep(200000);
        }

        return ['ok' => false, 'status' => 'timeout', 'data' => is_array($last) ? ($last['data'] ?? null) : null];
    }

    /**
     * 一键初始化：建索引 + PATCH settings + 等待任务 + 校验 filterable/searchable。
     *
     * @param array{wait_sec?:int,filter_test?:bool} $options
     * @return array{ok:bool,msg:string,steps:array}
     */
    public static function bootstrapIndex(array $options = [])
    {
        if (!self::enabled()) {
            return ['ok' => false, 'msg' => 'disabled', 'steps' => []];
        }
        $waitSec = max(5, min(120, (int)($options['wait_sec'] ?? 30)));
        $runFilterTest = !isset($options['filter_test']) || $options['filter_test'];
        $steps = [];

        $steps['ensure_index'] = self::ensureIndex();
        if (empty($steps['ensure_index']['ok'])) {
            return ['ok' => false, 'msg' => 'ensure index failed', 'steps' => $steps];
        }

        $steps['update_settings'] = self::updateSettings();
        if (empty($steps['update_settings']['ok'])) {
            return ['ok' => false, 'msg' => 'update settings failed', 'steps' => $steps];
        }

        $taskUid = 0;
        $patchData = $steps['update_settings']['data'] ?? null;
        if (is_array($patchData) && isset($patchData['taskUid'])) {
            $taskUid = (int)$patchData['taskUid'];
        }
        $steps['wait_task'] = self::waitForTask($taskUid, $waitSec);
        if (empty($steps['wait_task']['ok'])) {
            return ['ok' => false, 'msg' => 'settings task failed or timeout', 'steps' => $steps];
        }

        $steps['get_settings'] = self::getSettings();
        if (empty($steps['get_settings']['ok'])) {
            return ['ok' => false, 'msg' => 'get settings failed', 'steps' => $steps];
        }

        $steps['verify_settings'] = self::verifyIndexSettings(is_array($steps['get_settings']['data']) ? $steps['get_settings']['data'] : []);
        if (empty($steps['verify_settings']['ok'])) {
            return ['ok' => false, 'msg' => 'settings verification failed', 'steps' => $steps];
        }

        if ($runFilterTest) {
            $steps['filter_search_test'] = self::search('软件', self::filterPublishedKind('vod'), 1, 0);
        }

        return ['ok' => true, 'msg' => 'ok', 'steps' => $steps];
    }

    public static function updateSettings()
    {
        if (!self::enabled()) {
            return ['ok' => false];
        }
        $uid = rawurlencode(self::indexUid());
        $body = self::indexSettingsPayload();
        $r = MeilisearchHttp::request(self::host(), 'PATCH', '/indexes/' . $uid . '/settings', self::apiKey(), $body, self::timeout(), self::sslVerify());
        return ['ok' => !empty($r['ok']), 'data' => $r['data'] ?? null];
    }

    public static function addDocuments(array $docs)
    {
        if (!self::enabled() || empty($docs)) {
            return ['ok' => false];
        }
        $uid = rawurlencode(self::indexUid());
        $r = MeilisearchHttp::request(self::host(), 'POST', '/indexes/' . $uid . '/documents', self::apiKey(), $docs, self::timeout(), self::sslVerify());
        return ['ok' => !empty($r['ok']), 'data' => $r['data'] ?? null, 'status' => $r['status'] ?? 0];
    }

    public static function deleteDocument($id)
    {
        if (!self::enabled() || $id === '') {
            return ['ok' => false];
        }
        $uid = rawurlencode(self::indexUid());
        $did = rawurlencode((string)$id);
        $r = MeilisearchHttp::request(self::host(), 'DELETE', '/indexes/' . $uid . '/documents/' . $did, self::apiKey(), null, self::timeout(), self::sslVerify());
        return ['ok' => !empty($r['ok']) || ($r['status'] ?? 0) === 404, 'status' => $r['status'] ?? 0];
    }

    /**
     * @return array{ok:bool,hits:array<int,array>,estimatedTotalHits:int}
     */
    public static function search($q, $filter, $limit, $offset)
    {
        if (!self::enabled()) {
            return ['ok' => false, 'hits' => [], 'estimatedTotalHits' => 0];
        }
        $memoKey = md5((string)$q . "\x1e" . (string)$filter . "\x1e" . (int)$limit . "\x1e" . (int)$offset, true);
        $memoKey = 'ms1:' . base64_encode($memoKey);
        if (isset(self::$searchMemo[$memoKey])) {
            return self::$searchMemo[$memoKey];
        }
        $uid = rawurlencode(self::indexUid());
        $baseBody = [
            'limit' => max(1, min(1000, (int)$limit)),
            'offset' => max(0, (int)$offset),
            'attributesToRetrieve' => ['id', 'kind'],
            'matchingStrategy' => 'last',
        ];
        if ($filter !== '') {
            $baseBody['filter'] = $filter;
        }

        // 索引侧 title_t2s/title_s2t + 查询侧 OpenCC 变体，双端保证繁简互通。
        $queries = OpenccConverter::searchVariants((string)$q);
        if (empty($queries)) {
            $queries = [(string)$q];
        }

        $searchPath = '/indexes/' . $uid . '/search';
        $jobs = [];
        foreach ($queries as $queryText) {
            $body = $baseBody;
            $body['q'] = $queryText;
            $jobs[] = ['method' => 'POST', 'path' => $searchPath, 'body' => $body];
        }
        $responses = MeilisearchHttp::requestParallel(self::host(), $jobs, self::apiKey(), self::timeout(), self::sslVerify());

        $lastFailed = null;
        $lastQuery = $queries[count($queries) - 1];
        foreach ($queries as $idx => $queryText) {
            $r = isset($responses[$idx]) && is_array($responses[$idx]) ? $responses[$idx] : ['ok' => false];
            if (empty($r['ok']) || !is_array($r['data'] ?? null)) {
                $lastFailed = $r;
                continue;
            }
            $hits = isset($r['data']['hits']) && is_array($r['data']['hits']) ? $r['data']['hits'] : [];
            $est = isset($r['data']['estimatedTotalHits']) ? (int)$r['data']['estimatedTotalHits'] : count($hits);
            if (!empty($hits) || $queryText === $lastQuery) {
                $out = ['ok' => true, 'hits' => $hits, 'estimatedTotalHits' => $est];
                self::$searchMemo[$memoKey] = $out;

                return $out;
            }
        }

        if ($lastFailed !== null) {
            $out = ['ok' => false, 'hits' => [], 'estimatedTotalHits' => 0];
            self::$searchMemo[$memoKey] = $out;

            return $out;
        }
        $out = ['ok' => true, 'hits' => [], 'estimatedTotalHits' => 0];
        self::$searchMemo[$memoKey] = $out;

        return $out;
    }
}
