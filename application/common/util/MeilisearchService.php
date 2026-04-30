<?php

namespace app\common\util;

/**
 * Meilisearch：索引维护与搜索。
 */
class MeilisearchService
{
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
        return ['ok' => !empty($create['ok']), 'created' => true, 'response' => $create['data'] ?? null];
    }

    public static function updateSettings()
    {
        if (!self::enabled()) {
            return ['ok' => false];
        }
        $uid = rawurlencode(self::indexUid());
        $body = [
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
                'year', 'area', 'lang', 'state', 'version',
            ],
            'sortableAttributes' => ['hits_month', 'ts'],
            // 排序策略：相关度优先，其次热度（月点击），最后时间（更新时间）。
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

        // 文档侧已写入 title_t2s/title_s2t 等（MeilisearchDocuments），searchableAttributes 含这些字段，
        // 单次 q 即可跨简繁匹配，无需在搜索路径调用 OpenCC。
        $queries = [(string)$q];
        $compact = preg_replace('/\s+/u', '', (string)$q);
        if (is_string($compact) && $compact !== '' && $compact !== (string)$q) {
            $queries[] = $compact;
        }
        $lower = strtolower((string)$q);
        if ($lower !== '' && !in_array($lower, $queries, true)) {
            $queries[] = $lower;
        }
        $queries = array_values(array_unique($queries));

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
                return ['ok' => true, 'hits' => $hits, 'estimatedTotalHits' => $est];
            }
        }

        if ($lastFailed !== null) {
            return ['ok' => false, 'hits' => [], 'estimatedTotalHits' => 0];
        }
        return ['ok' => true, 'hits' => [], 'estimatedTotalHits' => 0];
    }
}
