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

    public static function afterTopicSave($topicId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $topicId = (int)$topicId;
        if ($topicId <= 0) {
            return;
        }
        try {
            $row = Db::name('Topic')->where('topic_id', $topicId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('topic_' . $topicId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['topic_status'] ?? 0) !== 1) {
                MeilisearchService::deleteDocument('topic_' . $topicId);
                return;
            }
            $doc = MeilisearchDocuments::fromTopicRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function afterActorSave($actorId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $actorId = (int)$actorId;
        if ($actorId <= 0) {
            return;
        }
        try {
            $row = Db::name('Actor')->where('actor_id', $actorId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('actor_' . $actorId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['actor_status'] ?? 0) !== 1) {
                MeilisearchService::deleteDocument('actor_' . $actorId);
                return;
            }
            $doc = MeilisearchDocuments::fromActorRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function afterRoleSave($roleId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $roleId = (int)$roleId;
        if ($roleId <= 0) {
            return;
        }
        try {
            $row = Db::name('Role')->where('role_id', $roleId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('role_' . $roleId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['role_status'] ?? 0) !== 1) {
                MeilisearchService::deleteDocument('role_' . $roleId);
                return;
            }
            $doc = MeilisearchDocuments::fromRoleRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function afterWebsiteSave($websiteId)
    {
        if (!MeilisearchService::enabled() || !MeilisearchService::syncOnSave()) {
            return;
        }
        $websiteId = (int)$websiteId;
        if ($websiteId <= 0) {
            return;
        }
        try {
            $row = Db::name('Website')->where('website_id', $websiteId)->find();
            if (empty($row)) {
                MeilisearchService::deleteDocument('website_' . $websiteId);
                return;
            }
            $row = is_array($row) ? $row : $row->toArray();
            if ((int)($row['website_status'] ?? 0) !== 1) {
                MeilisearchService::deleteDocument('website_' . $websiteId);
                return;
            }
            $doc = MeilisearchDocuments::fromWebsiteRow($row);
            if ($doc) {
                MeilisearchService::ensureIndex();
                MeilisearchService::addDocuments([$doc]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function deleteTopic($topicId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('topic_' . (int)$topicId);
    }

    public static function deleteActor($actorId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('actor_' . (int)$actorId);
    }

    public static function deleteRole($roleId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('role_' . (int)$roleId);
    }

    public static function deleteWebsite($websiteId)
    {
        if (!MeilisearchService::enabled()) {
            return;
        }
        MeilisearchService::deleteDocument('website_' . (int)$websiteId);
    }

    /**
     * @return array{ok:bool,msg:string,vod?:int,art?:int,manga?:int,topic?:int,actor?:int,role?:int,website?:int}
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
        $topicN = self::reindexTable('Topic', 'topic_id', function ($row) {
            return MeilisearchDocuments::fromTopicRow($row);
        }, $batch);
        $actorN = self::reindexTable('Actor', 'actor_id', function ($row) {
            return MeilisearchDocuments::fromActorRow($row);
        }, $batch);
        $roleN = self::reindexTable('Role', 'role_id', function ($row) {
            return MeilisearchDocuments::fromRoleRow($row);
        }, $batch);
        $websiteN = self::reindexTable('Website', 'website_id', function ($row) {
            return MeilisearchDocuments::fromWebsiteRow($row);
        }, $batch);

        return [
            'ok' => true,
            'msg' => 'ok',
            'vod' => $vodN,
            'art' => $artN,
            'manga' => $mangaN,
            'topic' => $topicN,
            'actor' => $actorN,
            'role' => $roleN,
            'website' => $websiteN,
        ];
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
                } elseif ($name === 'Topic') {
                    if ((int)($row['topic_status'] ?? 0) !== 1) {
                        continue;
                    }
                } elseif ($name === 'Actor') {
                    if ((int)($row['actor_status'] ?? 0) !== 1) {
                        continue;
                    }
                } elseif ($name === 'Role') {
                    if ((int)($row['role_status'] ?? 0) !== 1) {
                        continue;
                    }
                } elseif ($name === 'Website') {
                    if ((int)($row['website_status'] ?? 0) !== 1) {
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
