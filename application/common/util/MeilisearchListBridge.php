<?php

namespace app\common\util;

use think\Db;

/**
 * 在 listCacheData 中用 Meilisearch 替换关键词 LIKE（保守条件，不满足则回退 MySQL）。
 */
class MeilisearchListBridge
{
    /**
     * @return array|null {where, order, total}
     */
    public static function applyForVod($where, $wd, $name, $tag, $class, $actor, $director, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ($name !== '' || $tag !== '' || $class !== '' || $actor !== '' || $director !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripVodTextSearchKeys($where);
        $filter = self::buildVodFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^vod_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'vod');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'vod_name') !== false || strpos($k, 'vod_sub') !== false || strpos($k, 'vod_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['vod_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['vod_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(vod_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForArt($where, $wd, $name, $tag, $class, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ($name !== '' || $tag !== '' || $class !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripArtTextSearchKeys($where);
        $filter = self::buildArtFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^art_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'art');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'art_name') !== false || strpos($k, 'art_sub') !== false || strpos($k, 'art_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['art_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['art_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(art_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForManga($where, $wd, $name, $tag, $class, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ($name !== '' || $tag !== '' || $class !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripMangaTextSearchKeys($where);
        $filter = self::buildMangaFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^manga_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'manga');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'manga_name') !== false || strpos($k, 'manga_sub') !== false || strpos($k, 'manga_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['manga_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['manga_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(manga_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForTopic($where, $wd, $tag, $class, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ((string)$tag !== '' || (string)$class !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripTopicTextSearchKeys($where);
        $filter = self::buildTopicFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^topic_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'topic');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'topic_name') !== false || strpos($k, 'topic_en') !== false || strpos($k, 'topic_sub') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['topic_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['topic_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(topic_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForActor($where, $wd, $name, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ((string)$name !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripActorTextSearchKeys($where);
        $filter = self::buildActorFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^actor_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'actor');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'actor_name') !== false || strpos($k, 'actor_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['actor_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['actor_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(actor_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForRole($where, $wd, $name, $actor, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ((string)$name !== '' || (string)$actor !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripRoleTextSearchKeys($where);
        $filter = self::buildRoleFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^role_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'role');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'role_name') !== false || strpos($k, 'role_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['role_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['role_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(role_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    /**
     * @return array|null
     */
    public static function applyForWebsite($where, $wd, $name, $tag, $class, $page, $num, $start, $currentOrder)
    {
        if (!MeilisearchService::enabled() || $wd === '' || trim($wd) === '') {
            return null;
        }
        $c = MeilisearchService::cfg();
        if (!empty($c['search_only_wd']) && (string)$c['search_only_wd'] === '1') {
            if ((string)$name !== '' || (string)$tag !== '' || (string)$class !== '') {
                return null;
            }
        }
        if (!is_array($where) || !empty($where['_string'])) {
            return null;
        }

        $w = self::stripWebsiteTextSearchKeys($where);
        $filter = self::buildWebsiteFilter($w);
        if ($filter === null) {
            return null;
        }

        $page = max(1, (int)$page);
        $num = max(1, (int)$num);
        $start = max(0, (int)$start);
        $offset = ($page - 1) * $num + $start;

        $sr = MeilisearchService::search($wd, $filter, $num, $offset);
        if (!$sr['ok']) {
            return null;
        }
        $hitRows = isset($sr['hits']) && is_array($sr['hits']) ? $sr['hits'] : [];
        $ids = [];
        foreach ($hitRows as $hit) {
            if (empty($hit['id']) || !is_string($hit['id'])) {
                continue;
            }
            if (preg_match('/^website_(\d+)$/', $hit['id'], $m)) {
                $ids[] = (int)$m[1];
            }
        }
        $total = max(0, (int)(isset($sr['estimatedTotalHits']) ? $sr['estimatedTotalHits'] : 0));
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }
        $ids = self::refineMeiliPrimaryIds($ids, 'website');
        if (self::shouldAbortMeiliForStaleHits($hitRows, $ids)) {
            return null;
        }

        $nw = $w;
        foreach (array_keys($nw) as $k) {
            if (strpos($k, 'website_name') !== false || strpos($k, 'website_en') !== false) {
                if (is_array($nw[$k]) && isset($nw[$k][0]) && $nw[$k][0] === 'like') {
                    unset($nw[$k]);
                }
            }
        }
        if (empty($ids)) {
            $nw['website_id'] = ['eq', -1];
            $order = $currentOrder;
        } else {
            $nw['website_id'] = ['in', implode(',', $ids)];
            $order = Db::raw('FIELD(website_id,' . implode(',', $ids) . ')');
        }

        return ['where' => $nw, 'order' => $order, 'total' => $total];
    }

    private static function stripVodTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'vod_name') !== false || strpos($k, 'vod_sub') !== false || strpos($k, 'vod_en') !== false) {
                    unset($w[$k]);
                }
            }
        }
        return $w;
    }

    private static function stripArtTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'art_name') !== false || strpos($k, 'art_sub') !== false || strpos($k, 'art_en') !== false) {
                    unset($w[$k]);
                }
            }
        }
        return $w;
    }

    private static function stripMangaTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'manga_name') !== false || strpos($k, 'manga_sub') !== false || strpos($k, 'manga_en') !== false) {
                    unset($w[$k]);
                }
            }
        }
        return $w;
    }

    private static function stripTopicTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'topic_name') !== false || strpos($k, 'topic_en') !== false || strpos($k, 'topic_sub') !== false) {
                    unset($w[$k]);
                }
            }
        }

        return $w;
    }

    private static function stripActorTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'actor_name') !== false || strpos($k, 'actor_en') !== false) {
                    unset($w[$k]);
                }
            }
        }

        return $w;
    }

    private static function stripRoleTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'role_name') !== false || strpos($k, 'role_en') !== false) {
                    unset($w[$k]);
                }
            }
        }

        return $w;
    }

    private static function stripWebsiteTextSearchKeys(array $where)
    {
        $w = $where;
        foreach (array_keys($w) as $k) {
            if (!is_array($w[$k])) {
                continue;
            }
            if (isset($w[$k][0]) && $w[$k][0] === 'like') {
                if (strpos($k, 'website_name') !== false || strpos($k, 'website_en') !== false) {
                    unset($w[$k]);
                }
            }
        }

        return $w;
    }

    /**
     * @return string|null Meilisearch filter expression
     */
    private static function buildVodFilter(array $w)
    {
        $parts = ['kind = "vod"', 'recycle = 0', 'status = 1'];
        $allowed = [
            'type_id', 'type_id|type_id_1', 'vod_level', 'vod_year', 'vod_area', 'vod_lang',
            'vod_state', 'vod_version', 'vod_isend', 'vod_plot', 'vod_time', 'vod_time_add', 'vod_time_hits',
            'group_id',
        ];
        foreach ($w as $key => $val) {
            if ($key === 'vod_status') {
                continue;
            }
            if ($key === 'vod_id' || $key === 'vod_name' || strpos($key, 'vod_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        $f = self::thinkCondToMeili('type_id', $w['type_id'] ?? null, true);
        if ($f === false) {
            return null;
        }
        if ($f !== '') {
            $parts[] = $f;
        }
        if (isset($w['type_id|type_id_1'])) {
            $t = self::parseEq($w['type_id|type_id_1']);
            if ($t === null) {
                return null;
            }
            $parts[] = '(type_id = ' . $t . ' OR type_id_1 = ' . $t . ')';
        }
        if (isset($w['vod_status'])) {
            if (!is_array($w['vod_status']) || ($w['vod_status'][0] ?? '') !== 'eq' || (int)($w['vod_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        foreach (['vod_level' => 'level', 'group_id' => 'group_id', 'vod_isend' => 'isend', 'vod_plot' => 'plot'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkCondToMeili($mk, $w[$tk], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['vod_year' => 'year', 'vod_area' => 'area', 'vod_lang' => 'lang', 'vod_state' => 'state', 'vod_version' => 'version'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkStringInOrEq($mk, $w[$tk]);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['vod_time' => 'ts', 'vod_time_add' => 'ts', 'vod_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        return implode(' AND ', $parts);
    }

    private static function buildArtFilter(array $w)
    {
        $parts = ['kind = "art"', 'recycle = 0', 'status = 1'];
        if (isset($w['art_status'])) {
            if (!is_array($w['art_status']) || ($w['art_status'][0] ?? '') !== 'eq' || (int)($w['art_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = [
            'type_id', 'type_id|type_id_1', 'art_level', 'art_time', 'art_time_add', 'art_time_hits',
        ];
        foreach ($w as $key => $val) {
            if ($key === 'art_status') {
                continue;
            }
            if ($key === 'art_id' || strpos($key, 'art_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        $f = self::thinkCondToMeili('type_id', $w['type_id'] ?? null, true);
        if ($f === false) {
            return null;
        }
        if ($f !== '') {
            $parts[] = $f;
        }
        if (isset($w['type_id|type_id_1'])) {
            $t = self::parseEq($w['type_id|type_id_1']);
            if ($t === null) {
                return null;
            }
            $parts[] = '(type_id = ' . $t . ' OR type_id_1 = ' . $t . ')';
        }
        if (isset($w['art_level'])) {
            $f = self::thinkCondToMeili('level', $w['art_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['art_time' => 'ts', 'art_time_add' => 'ts', 'art_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        return implode(' AND ', $parts);
    }

    private static function buildMangaFilter(array $w)
    {
        $parts = ['kind = "manga"', 'recycle = 0', 'status = 1'];
        if (isset($w['manga_status'])) {
            if (!is_array($w['manga_status']) || ($w['manga_status'][0] ?? '') !== 'eq' || (int)($w['manga_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = [
            'type_id', 'type_id|type_id_1', 'manga_level', 'manga_time', 'manga_time_add', 'manga_time_hits',
        ];
        foreach ($w as $key => $val) {
            if ($key === 'manga_status') {
                continue;
            }
            if ($key === 'manga_id' || strpos($key, 'manga_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        $f = self::thinkCondToMeili('type_id', $w['type_id'] ?? null, true);
        if ($f === false) {
            return null;
        }
        if ($f !== '') {
            $parts[] = $f;
        }
        if (isset($w['type_id|type_id_1'])) {
            $t = self::parseEq($w['type_id|type_id_1']);
            if ($t === null) {
                return null;
            }
            $parts[] = '(type_id = ' . $t . ' OR type_id_1 = ' . $t . ')';
        }
        if (isset($w['manga_level'])) {
            $f = self::thinkCondToMeili('level', $w['manga_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['manga_time' => 'ts', 'manga_time_add' => 'ts', 'manga_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        return implode(' AND ', $parts);
    }

    private static function buildTopicFilter(array $w)
    {
        $parts = ['kind = "topic"', 'recycle = 0', 'status = 1'];
        if (isset($w['topic_status'])) {
            if (!is_array($w['topic_status']) || ($w['topic_status'][0] ?? '') !== 'eq' || (int)($w['topic_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = ['topic_level', 'topic_time', 'topic_time_add', 'topic_time_hits'];
        foreach ($w as $key => $val) {
            if ($key === 'topic_status') {
                continue;
            }
            if ($key === 'topic_id' || strpos($key, 'topic_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        if (isset($w['topic_level'])) {
            $f = self::thinkCondToMeili('level', $w['topic_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['topic_time' => 'ts', 'topic_time_add' => 'ts', 'topic_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }

        return implode(' AND ', $parts);
    }

    private static function buildActorFilter(array $w)
    {
        $parts = ['kind = "actor"', 'recycle = 0', 'status = 1'];
        if (isset($w['actor_status'])) {
            if (!is_array($w['actor_status']) || ($w['actor_status'][0] ?? '') !== 'eq' || (int)($w['actor_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = [
            'type_id', 'type_id|type_id_1', 'actor_level', 'actor_time', 'actor_time_add', 'actor_time_hits', 'actor_area',
        ];
        foreach ($w as $key => $val) {
            if ($key === 'actor_status') {
                continue;
            }
            if ($key === 'actor_id' || strpos($key, 'actor_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        $f = self::thinkCondToMeili('type_id', $w['type_id'] ?? null, true);
        if ($f === false) {
            return null;
        }
        if ($f !== '') {
            $parts[] = $f;
        }
        if (isset($w['type_id|type_id_1'])) {
            $t = self::parseEq($w['type_id|type_id_1']);
            if ($t === null) {
                return null;
            }
            $parts[] = '(type_id = ' . $t . ' OR type_id_1 = ' . $t . ')';
        }
        if (isset($w['actor_level'])) {
            $f = self::thinkCondToMeili('level', $w['actor_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['actor_time' => 'ts', 'actor_time_add' => 'ts', 'actor_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        if (isset($w['actor_area'])) {
            $f = self::thinkStringInOrEq('area', $w['actor_area']);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }

        return implode(' AND ', $parts);
    }

    private static function buildRoleFilter(array $w)
    {
        $parts = ['kind = "role"', 'recycle = 0', 'status = 1'];
        if (isset($w['role_status'])) {
            if (!is_array($w['role_status']) || ($w['role_status'][0] ?? '') !== 'eq' || (int)($w['role_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = ['role_level', 'role_time', 'role_time_add', 'role_time_hits', 'role_rid'];
        foreach ($w as $key => $val) {
            if ($key === 'role_status') {
                continue;
            }
            if ($key === 'role_id' || strpos($key, 'role_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        if (isset($w['role_rid'])) {
            $f = self::thinkCondToMeili('rid', $w['role_rid'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        if (isset($w['role_level'])) {
            $f = self::thinkCondToMeili('level', $w['role_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['role_time' => 'ts', 'role_time_add' => 'ts', 'role_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }

        return implode(' AND ', $parts);
    }

    private static function buildWebsiteFilter(array $w)
    {
        $parts = ['kind = "website"', 'recycle = 0', 'status = 1'];
        if (isset($w['website_status'])) {
            if (!is_array($w['website_status']) || ($w['website_status'][0] ?? '') !== 'eq' || (int)($w['website_status'][1] ?? 0) !== 1) {
                return null;
            }
        }
        $allowed = [
            'type_id', 'type_id|type_id_1', 'website_level',
            'website_time', 'website_time_add', 'website_time_hits',
            'website_area', 'website_lang',
        ];
        foreach ($w as $key => $val) {
            if ($key === 'website_status') {
                continue;
            }
            if ($key === 'website_id' || strpos($key, 'website_rel') === 0) {
                return null;
            }
            if (!in_array($key, $allowed, true)) {
                return null;
            }
            if (is_array($val) && isset($val[0]) && is_array($val[0])) {
                return null;
            }
        }
        $f = self::thinkCondToMeili('type_id', $w['type_id'] ?? null, true);
        if ($f === false) {
            return null;
        }
        if ($f !== '') {
            $parts[] = $f;
        }
        if (isset($w['type_id|type_id_1'])) {
            $t = self::parseEq($w['type_id|type_id_1']);
            if ($t === null) {
                return null;
            }
            $parts[] = '(type_id = ' . $t . ' OR type_id_1 = ' . $t . ')';
        }
        if (isset($w['website_level'])) {
            $f = self::thinkCondToMeili('level', $w['website_level'], true);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        foreach (['website_time' => 'ts', 'website_time_add' => 'ts', 'website_time_hits' => 'ts'] as $tk => $mk) {
            if (!isset($w[$tk])) {
                continue;
            }
            $f = self::thinkTimeToMeili($w[$tk], $mk);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        if (isset($w['website_area'])) {
            $f = self::thinkStringInOrEq('area', $w['website_area']);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }
        if (isset($w['website_lang'])) {
            $f = self::thinkStringInOrEq('lang', $w['website_lang']);
            if ($f === false) {
                return null;
            }
            if ($f !== '') {
                $parts[] = $f;
            }
        }

        return implode(' AND ', $parts);
    }

    /**
     * @return int|null
     */
    private static function parseEq($cond)
    {
        if (!is_array($cond) || !isset($cond[0])) {
            return null;
        }
        if ($cond[0] === 'eq' && isset($cond[1])) {
            return (int)$cond[1];
        }
        return null;
    }

    /**
     * @return string|false '' if no condition
     */
    private static function thinkCondToMeili($field, $cond, $numeric = true)
    {
        if ($cond === null) {
            return '';
        }
        if (!is_array($cond) || !isset($cond[0])) {
            return false;
        }
        $op = $cond[0];
        $v = $cond[1] ?? null;
        if ($op === 'eq') {
            return $field . ' = ' . ($numeric ? (int)$v : '"' . self::escStr((string)$v) . '"');
        }
        if ($op === 'in') {
            $arr = is_array($v) ? $v : explode(',', (string)$v);
            $arr = array_map('intval', $arr);
            if (empty($arr)) {
                return '';
            }
            return $field . ' IN [' . implode(', ', $arr) . ']';
        }
        if ($op === 'not in') {
            return false;
        }
        return false;
    }

    /**
     * @return string|false
     */
    private static function thinkStringInOrEq($meiliField, $cond)
    {
        if (!is_array($cond)) {
            return false;
        }
        if ($cond[0] === 'in') {
            $arr = is_array($cond[1]) ? $cond[1] : explode(',', (string)$cond[1]);
            $quoted = [];
            foreach ($arr as $s) {
                $quoted[] = '"' . self::escStr(trim((string)$s)) . '"';
            }
            if (empty($quoted)) {
                return '';
            }
            return $meiliField . ' IN [' . implode(', ', $quoted) . ']';
        }
        if ($cond[0] === 'eq') {
            return $meiliField . ' = "' . self::escStr((string)($cond[1] ?? '')) . '"';
        }
        return false;
    }

    /**
     * @return string|false
     */
    private static function thinkTimeToMeili($cond, $meiliField)
    {
        if (!is_array($cond) || !isset($cond[0])) {
            return false;
        }
        if ($cond[0] === 'gt' && isset($cond[1])) {
            return $meiliField . ' > ' . (int)$cond[1];
        }
        return false;
    }

    /**
     * 与 MySQL 已发布、未回收行对齐，避免索引滞后时出现「Meili 有命中、列表为空」。
     * 供 API suggest、内部联想等与列表同一套过滤复用。
     *
     * @param int[]  $ids
     * @param string $kind vod|art|manga|topic|actor|role|website
     *
     * @return int[]
     */
    public static function refinePrimaryIdsForPublished(array $ids, $kind)
    {
        return self::refineMeiliPrimaryIds($ids, $kind);
    }

    /**
     * @param int[]  $ids
     * @param string $kind vod|art|manga|topic|actor|role|website
     *
     * @return int[]
     */
    private static function refineMeiliPrimaryIds(array $ids, $kind)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $map = [
            'vod'     => ['Vod', 'vod_id', 'vod_status', 'vod_recycle_time'],
            'art'     => ['Art', 'art_id', 'art_status', 'art_recycle_time'],
            'manga'   => ['Manga', 'manga_id', 'manga_status', 'manga_recycle_time'],
            'topic'   => ['Topic', 'topic_id', 'topic_status', null],
            'actor'   => ['Actor', 'actor_id', 'actor_status', null],
            'role'    => ['Role', 'role_id', 'role_status', null],
            'website' => ['Website', 'website_id', 'website_status', null],
        ];
        $kind = strtolower((string)$kind);
        if (!isset($map[$kind])) {
            return $ids;
        }
        $m = $map[$kind];
        $table = $m[0];
        $pk = $m[1];
        $st = $m[2];
        $rc = $m[3];
        $present = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $q = Db::name($table)->where($pk, 'in', implode(',', $chunk))->where($st, 1);
            if ($rc !== null && $rc !== '') {
                $q->where($rc, 0);
            }
            $col = $q->column($pk);
            if (!is_array($col)) {
                continue;
            }
            foreach ($col as $v) {
                $present[(int)$v] = true;
            }
        }
        $out = [];
        foreach ($ids as $id) {
            if (!empty($present[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param array<int, array> $hitRows
     * @param int[]             $ids
     *
     * @return bool true 表示应回退 MySQL（Meili 有行但无法用于列表）
     */
    private static function shouldAbortMeiliForStaleHits(array $hitRows, array $ids)
    {
        return !empty($hitRows) && empty($ids);
    }

    private static function escStr($s)
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $s);
    }
}
