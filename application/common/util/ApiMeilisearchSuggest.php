<?php

namespace app\common\util;

use think\Db;

/**
 * API 搜索建议：Meilisearch + filterPublishedKind + refinePrimaryIdsForPublished，
 * 与列表/详情同一套已发布/未回收过滤；失败时回退 model listData（含各模型 recycle 合并）。
 */
class ApiMeilisearchSuggest
{
    /** @var array<string, array<string, mixed>> */
    private static $kindMeta = [
        'vod' => [
            'model' => 'Vod',
            'table' => 'Vod',
            'pk' => 'vod_id',
            'status' => 'vod_status',
            'recycle' => 'vod_recycle_time',
            'field' => 'vod_id,vod_name,vod_en,vod_pic,vod_time,type_id,type_id_1',
            'like' => 'vod_name|vod_en',
            'list' => 'vod',
        ],
        'art' => [
            'model' => 'Art',
            'table' => 'Art',
            'pk' => 'art_id',
            'status' => 'art_status',
            'recycle' => 'art_recycle_time',
            'field' => 'art_id,art_name,art_en,art_pic,art_time,type_id,type_id_1',
            'like' => 'art_name|art_en',
            'list' => 'std',
        ],
        'actor' => [
            'model' => 'Actor',
            'table' => 'Actor',
            'pk' => 'actor_id',
            'status' => 'actor_status',
            'recycle' => null,
            'field' => 'actor_id,actor_name,actor_en,actor_pic,actor_time,type_id',
            'like' => 'actor_name|actor_en',
            'list' => 'std',
        ],
        'topic' => [
            'model' => 'Topic',
            'table' => 'Topic',
            'pk' => 'topic_id',
            'status' => 'topic_status',
            'recycle' => null,
            'field' => 'topic_id,topic_name,topic_en,topic_sub,topic_pic,topic_time',
            'like' => 'topic_name|topic_en|topic_sub',
            'list' => 'topic',
        ],
        'role' => [
            'model' => 'Role',
            'table' => 'Role',
            'pk' => 'role_id',
            'status' => 'role_status',
            'recycle' => null,
            'field' => 'role_id,role_name,role_en,role_pic,role_time,type_id',
            'like' => 'role_name|role_en',
            'list' => 'std',
            'fallback_addition' => 0,
        ],
        'website' => [
            'model' => 'Website',
            'table' => 'Website',
            'pk' => 'website_id',
            'status' => 'website_status',
            'recycle' => null,
            'field' => 'website_id,website_name,website_en,website_pic,website_time,type_id',
            'like' => 'website_name|website_en',
            'list' => 'std',
        ],
        'manga' => [
            'model' => 'Manga',
            'table' => 'Manga',
            'pk' => 'manga_id',
            'status' => 'manga_status',
            'recycle' => 'manga_recycle_time',
            'field' => 'manga_id,manga_name,manga_en,manga_sub,manga_pic,manga_time,type_id,type_id_1',
            'like' => 'manga_name|manga_en|manga_sub',
            'list' => 'std',
        ],
    ];

    /**
     * @param string $kind vod|art|actor|topic|role|website|manga
     *
     * @return array<string, mixed>|null
     */
    private static function meta($kind)
    {
        $k = strtolower((string)$kind);

        return self::$kindMeta[$k] ?? null;
    }

    private static function sqlLikeContains($wd)
    {
        return '%' . addcslashes((string)$wd, '%_\\') . '%';
    }

    /**
     * Meili 命中后按顺序拉取 DB 行（status=1 + recycle=0 若存在列）。
     *
     * @param string          $kind 小写 kind 或与 meta 一致的前缀
     * @param int[]           $ids
     * @param array|null      $meta 已解析的 meta，避免重复查表
     *
     * @return array<int, array<string, mixed>>
     */
    public static function orderedDbRowsByIds($kind, array $ids, array $meta = null)
    {
        $k = strtolower((string)$kind);
        $m = $meta !== null ? $meta : self::meta($k);
        if (!$m || empty($ids)) {
            return [];
        }
        $table = $m['table'];
        $pk = $m['pk'];
        $st = $m['status'];
        $rc = $m['recycle'];
        $field = $m['field'];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return [];
        }
        try {
            $q = Db::name($table)->field($field)->where($st, 1)->where($pk, 'in', implode(',', $ids));
            if ($rc !== null && $rc !== '') {
                $q->where($rc, 0);
            }
            $rows = $q->select();
        } catch (\Throwable $e) {
            if ($rc !== null && $rc !== '') {
                $rows = Db::name($table)->field($field)->where($st, 1)->where($pk, 'in', implode(',', $ids))->select();
            } else {
                throw $e;
            }
        }
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $mapRows = [];
        foreach ($rows as $row) {
            $mapRows[(int)$row[$pk]] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (!empty($mapRows[$id])) {
                $ordered[] = $mapRows[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param string     $kind
     * @param string     $wd
     * @param int        $limit
     * @param array|null $meta 已解析的 meta（小写 kind），避免 suggest 路径重复 meta()
     *
     * @return array<int, array<string, mixed>>
     */
    public static function meiliOrderedDbRows($kind, $wd, $limit, array $meta = null)
    {
        $k = strtolower((string)$kind);
        $m = $meta !== null ? $meta : self::meta($k);
        if (!$m || !MeilisearchService::enabled()) {
            return [];
        }
        $filter = MeilisearchService::filterPublishedKind($k);
        if ($filter === '') {
            return [];
        }
        $sr = MeilisearchService::search($wd, $filter, $limit, 0);
        if (empty($sr['ok']) || empty($sr['hits']) || !is_array($sr['hits'])) {
            return [];
        }
        $re = '/^' . preg_quote($k, '/') . '_(\d+)$/';
        $ids = [];
        foreach ($sr['hits'] as $hit) {
            if (!empty($hit['id']) && is_string($hit['id']) && preg_match($re, $hit['id'], $mm)) {
                $ids[] = (int)$mm[1];
            }
        }
        $ids = MeilisearchListBridge::refinePrimaryIdsForPublished($ids, $k);
        if ($ids === []) {
            return [];
        }

        return self::orderedDbRowsByIds($k, $ids, $m);
    }

    /**
     * @param string     $kind
     * @param string     $wd
     * @param int        $limit
     * @param array|null $meta 已解析的 meta
     *
     * @return array{code:int,msg:string,page:int,pagecount:int,limit:int,total:int,list:array}
     */
    public static function fallbackListDataRes($kind, $wd, $limit, array $meta = null)
    {
        $k = strtolower((string)$kind);
        $m = $meta !== null ? $meta : self::meta($k);
        if (!$m) {
            return ['code' => 1001, 'msg' => 'param_err', 'page' => 1, 'pagecount' => 0, 'limit' => $limit, 'total' => 0, 'list' => []];
        }
        $like = self::sqlLikeContains($wd);
        $where = [
            $m['status'] => ['eq', 1],
            $m['like'] => ['like', $like],
        ];
        $order = $m['pk'] . ' desc';
        $model = model($m['model']);
        $field = $m['field'];
        if ($m['list'] === 'topic') {
            return $model->listData($where, $order, 1, $limit, 0, $field, 1);
        }
        if ($m['list'] === 'vod') {
            return $model->listData($where, $order, 1, $limit, 0, $field, 1, 1);
        }
        $addition = isset($m['fallback_addition']) ? (int)$m['fallback_addition'] : 1;

        return $model->listData($where, $order, 1, $limit, 0, $field, $addition, 1);
    }

    /**
     * @param string               $kind
     * @param array<string, mixed> $v
     *
     * @return array<string, mixed>
     */
    public static function toSlimItem($kind, array $v)
    {
        $k = strtolower((string)$kind);
        switch ($k) {
            case 'vod':
                return [
                    'id' => (int)($v['vod_id'] ?? 0),
                    'name' => (string)($v['vod_name'] ?? ''),
                    'en' => (string)($v['vod_en'] ?? ''),
                    'pic' => mac_url_img($v['vod_pic'] ?? ''),
                    'vod_link' => mac_url_vod_detail($v),
                ];
            case 'art':
                return [
                    'id' => (int)($v['art_id'] ?? 0),
                    'name' => (string)($v['art_name'] ?? ''),
                    'en' => (string)($v['art_en'] ?? ''),
                    'pic' => mac_url_img($v['art_pic'] ?? ''),
                    'art_link' => mac_url_art_detail($v),
                ];
            case 'actor':
                return [
                    'id' => (int)($v['actor_id'] ?? 0),
                    'name' => (string)($v['actor_name'] ?? ''),
                    'en' => (string)($v['actor_en'] ?? ''),
                    'pic' => mac_url_img($v['actor_pic'] ?? ''),
                    'actor_link' => mac_url_actor_detail($v),
                ];
            case 'topic':
                return [
                    'id' => (int)($v['topic_id'] ?? 0),
                    'name' => (string)($v['topic_name'] ?? ''),
                    'en' => (string)($v['topic_en'] ?? ''),
                    'pic' => mac_url_img($v['topic_pic'] ?? ''),
                    'topic_link' => mac_url_topic_detail($v),
                ];
            case 'role':
                return [
                    'id' => (int)($v['role_id'] ?? 0),
                    'name' => (string)($v['role_name'] ?? ''),
                    'en' => (string)($v['role_en'] ?? ''),
                    'pic' => mac_url_img($v['role_pic'] ?? ''),
                    'role_link' => mac_url_role_detail($v),
                ];
            case 'website':
                return [
                    'id' => (int)($v['website_id'] ?? 0),
                    'name' => (string)($v['website_name'] ?? ''),
                    'en' => (string)($v['website_en'] ?? ''),
                    'pic' => mac_url_img($v['website_pic'] ?? ''),
                    'website_link' => mac_url_website_detail($v),
                ];
            case 'manga':
                return [
                    'id' => (int)($v['manga_id'] ?? 0),
                    'name' => (string)($v['manga_name'] ?? ''),
                    'en' => (string)($v['manga_en'] ?? ''),
                    'pic' => mac_url_img($v['manga_pic'] ?? ''),
                    'manga_link' => mac_url_manga_detail($v),
                ];
            default:
                return [];
        }
    }

    /**
     * @param string $kind
     * @param string $wd
     * @param int    $limit
     *
     * @return array{code:int,msg:string,page:int,pagecount:int,limit:int,total:int,list:array}
     */
    public static function suggestListDataRes($kind, $wd, $limit)
    {
        $k = strtolower((string)$kind);
        $m = self::meta($k);
        if (!$m) {
            return ['code' => 1001, 'msg' => 'param_err', 'page' => 1, 'pagecount' => 0, 'limit' => $limit, 'total' => 0, 'list' => []];
        }

        $rows = self::meiliOrderedDbRows($k, $wd, $limit, $m);
        if ($rows !== []) {
            $list = [];
            foreach ($rows as $v) {
                $list[] = self::toSlimItem($k, $v);
            }

            return [
                'code' => 1,
                'msg' => lang('data_list'),
                'page' => 1,
                'pagecount' => 1,
                'limit' => $limit,
                'total' => count($list),
                'list' => $list,
            ];
        }

        $res = self::fallbackListDataRes($k, $wd, $limit, $m);
        if ($res['code'] == 1 && !empty($res['list'])) {
            $out = [];
            foreach ($res['list'] as $v) {
                $out[] = self::toSlimItem($k, $v);
            }
            $res['list'] = $out;
        }

        return $res;
    }

    /**
     * 前台 Ajax suggest：列表项为 id/name/en/pic（与历史模板一致），数据路径与 API 一致（Meili + refine + 未回收；否则 MySQL + status + LIKE）。
     *
     * @param string $kind       vod|art|topic|actor|role|website
     * @param string $orderMode  SearchService::suggestOrder 的 mode（仅 MySQL 回退时生效）
     *
     * @return array{code:int,msg:string,page?:int,pagecount?:int,limit:int,total?:int,list:array}
     */
    public static function ajaxSuggestResult($kind, $wd, $limit, $orderMode)
    {
        $k = strtolower((string)$kind);
        $m = self::meta($k);
        if (!$m) {
            return ['code' => 1001, 'msg' => lang('param_err'), 'page' => 1, 'pagecount' => 0, 'limit' => $limit, 'total' => 0, 'list' => []];
        }

        $meili = self::meiliOrderedDbRows($k, $wd, $limit, $m);
        if ($meili !== []) {
            $list = [];
            foreach ($meili as $row) {
                $list[] = self::rowToAjaxSuggestItem($k, $row);
            }
            $n = count($list);

            return [
                'code' => 1,
                'msg' => lang('data_list'),
                'page' => 1,
                'pagecount' => $limit > 0 && $n > 0 ? (int)ceil($n / $limit) : 0,
                'limit' => $limit,
                'total' => $n,
                'list' => $list,
            ];
        }

        $like = self::sqlLikeContains($wd);
        $where = [
            $m['status'] => ['eq', 1],
            $m['like'] => ['like', $like],
        ];
        $order = SearchService::suggestOrder($k, $orderMode);
        $modelName = $m['model'];
        $field = $k . '_id as id,' . $k . '_name as name,' . $k . '_en as en,' . $k . '_pic as pic';
        if ($k === 'topic') {
            $field = 'topic_id as id,topic_name as name,topic_en as en,topic_pic as pic';
            $res = model('Topic')->listData($where, $order, 1, $limit, 0, $field, 0);
        } else {
            $res = model($modelName)->listData($where, $order, 1, $limit, 0, $field, 0, 0);
        }
        if ($res['code'] == 1 && !empty($res['list'])) {
            foreach ($res['list'] as $kk => $v) {
                $res['list'][$kk]['pic'] = mac_url_img($v['pic'] ?? '');
            }
        }

        return $res;
    }

    /**
     * @param string               $kind vod|art|topic|actor|role|website
     * @param array<string, mixed> $row  表字段行（非 as 别名）
     *
     * @return array{id:int,name:string,en:string,pic:string}
     */
    private static function rowToAjaxSuggestItem($kind, array $row)
    {
        switch ($kind) {
            case 'vod':
                return [
                    'id' => (int)($row['vod_id'] ?? 0),
                    'name' => (string)($row['vod_name'] ?? ''),
                    'en' => (string)($row['vod_en'] ?? ''),
                    'pic' => mac_url_img($row['vod_pic'] ?? ''),
                ];
            case 'art':
                return [
                    'id' => (int)($row['art_id'] ?? 0),
                    'name' => (string)($row['art_name'] ?? ''),
                    'en' => (string)($row['art_en'] ?? ''),
                    'pic' => mac_url_img($row['art_pic'] ?? ''),
                ];
            case 'topic':
                return [
                    'id' => (int)($row['topic_id'] ?? 0),
                    'name' => (string)($row['topic_name'] ?? ''),
                    'en' => (string)($row['topic_en'] ?? ''),
                    'pic' => mac_url_img($row['topic_pic'] ?? ''),
                ];
            case 'actor':
                return [
                    'id' => (int)($row['actor_id'] ?? 0),
                    'name' => (string)($row['actor_name'] ?? ''),
                    'en' => (string)($row['actor_en'] ?? ''),
                    'pic' => mac_url_img($row['actor_pic'] ?? ''),
                ];
            case 'role':
                return [
                    'id' => (int)($row['role_id'] ?? 0),
                    'name' => (string)($row['role_name'] ?? ''),
                    'en' => (string)($row['role_en'] ?? ''),
                    'pic' => mac_url_img($row['role_pic'] ?? ''),
                ];
            case 'website':
                return [
                    'id' => (int)($row['website_id'] ?? 0),
                    'name' => (string)($row['website_name'] ?? ''),
                    'en' => (string)($row['website_en'] ?? ''),
                    'pic' => mac_url_img($row['website_pic'] ?? ''),
                ];
            default:
                return ['id' => 0, 'name' => '', 'en' => '', 'pic' => ''];
        }
    }
}
