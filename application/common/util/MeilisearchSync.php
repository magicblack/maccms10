<?php

namespace app\common\util;

use think\Db;

/**
 * 写入 / 删除 Meilisearch 文档及全量同步。
 */
class MeilisearchSync
{
    public static function afterVodSave($vodId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $vodId = (int)$vodId;
        if ($vodId <= 0) {
            return;
        }
        try {
            $row = Db::name('Vod')->where('vod_id', $vodId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('vod_' . $vodId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['vod_status'] ?? 0) !== 1 || !empty($row['vod_recycle_time'])) {
                MeilisearchService::deleteDocument('vod_' . $vodId);
                return;
            }
            $doc = MeilisearchDocuments::fromVodRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
            // 静默失败，避免影响后台保存
        }
    }

    public static function afterArtSave($artId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $artId = (int)$artId;
        if ($artId <= 0) {
            return;
        }
        try {
            $row = Db::name('Art')->where('art_id', $artId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('art_' . $artId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['art_status'] ?? 0) !== 1 || !empty($row['art_recycle_time'])) {
                MeilisearchService::deleteDocument('art_' . $artId);
                return;
            }
            $doc = MeilisearchDocuments::fromArtRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function afterMangaSave($mangaId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $mangaId = (int)$mangaId;
        if ($mangaId <= 0) {
            return;
        }
        try {
            $row = Db::name('Manga')->where('manga_id', $mangaId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('manga_' . $mangaId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['manga_status'] ?? 0) !== 1 || !empty($row['manga_recycle_time'])) {
                MeilisearchService::deleteDocument('manga_' . $mangaId);
                return;
            }
            $doc = MeilisearchDocuments::fromMangaRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function deleteVod($vodId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('vod_' . (int)$vodId);
    }

    public static function deleteArt($artId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('art_' . (int)$artId);
    }

    public static function deleteManga($mangaId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('manga_' . (int)$mangaId);
    }

    /**
     * @return array{ok:bool,msg:string,vod?:int,art?:int,manga?:int}
     */
    public static function fullReindex($batch = 400)
    {
        if (!MeilisearchService::enabled()) {
            return ['ok' => false, 'msg' => 'meilisearch disabled'];
        }
        @set_time_limit(0);
        $batch = max(50, min(1000, (int)$batch));
        $en = MeilisearchService::ensureIndex();
        if (empty($en['ok'])) {
            return ['ok' => false, 'msg' => 'ensure index failed'];
        }
        MeilisearchService::updateSettings();

        $vodN = self::reindexTable('Vod', 'vod_id', function ($row) {
            return MeilisearchDocuments::fromVodRow($row);
        }, $batch);
        $artN = self::reindexTable('Art', 'art_id', function ($row) {
            return MeilisearchDocuments::fromArtRow($row);
        }, $batch);
        $mangaN = self::reindexTable('Manga', 'manga_id', function ($row) {
            return MeilisearchDocuments::fromMangaRow($row);
        }, $batch);

        return ['ok' => true, 'msg' => 'ok', 'vod' => $vodN, 'art' => $artN, 'manga' => $mangaN];
    }

    private static function reindexTable($name, $pk, callable $map, $batch)
    {
        $last = 0;
        $totalDocs = 0;
        while (true) {
            $rows = Db::name($name)
                ->where($pk, '>', $last)
                ->order($pk, 'asc')
                ->limit($batch)
                ->select();
            if (empty($rows)) {
                break;
            }
            $docs = [];
            foreach ($rows as $row) {
                $row = is_array($row) ? $row : $row->toArray();
                $last = (int)$row[$pk];
                if ($name === 'Vod') {
                    if ((int)($row['vod_status'] ?? 0) !== 1 || !empty($row['vod_recycle_time'])) {
                        continue;
                    }
                } elseif ($name === 'Art') {
                    if ((int)($row['art_status'] ?? 0) !== 1 || !empty($row['art_recycle_time'])) {
                        continue;
                    }
                } elseif ($name === 'Manga') {
                    if ((int)($row['manga_status'] ?? 0) !== 1 || !empty($row['manga_recycle_time'])) {
                        continue;
                    }
                }
                $doc = $map($row);
                if ($doc) {
                    $docs[] = $doc;
                }
            }
            if (!empty($docs)) {
                MeilisearchService::addDocuments($docs);
                $totalDocs += count($docs);
            }
            if (count($rows) < $batch) {
                break;
            }
        }
        return $totalDocs;
    }
}
