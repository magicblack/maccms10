<?php
namespace app\common\model;

use think\Db;

class SeoAiResult extends Base
{
    protected $name = 'seo_ai_result';

    public function createTableIfNotExists()
    {
        $table = config('database.prefix') . $this->name;
        $exists = Db::query("SHOW TABLES LIKE '{$table}'");
        if (!empty($exists)) {
            return;
        }

        Db::execute(
            "CREATE TABLE `{$table}` (
                `seo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `seo_mid` tinyint(3) unsigned NOT NULL DEFAULT '0',
                `seo_obj_id` int(10) unsigned NOT NULL DEFAULT '0',
                `seo_obj_uuid` char(36) NOT NULL DEFAULT '',
                `seo_title` varchar(255) NOT NULL DEFAULT '',
                `seo_keywords` varchar(500) NOT NULL DEFAULT '',
                `seo_description` varchar(500) NOT NULL DEFAULT '',
                `seo_provider` varchar(32) NOT NULL DEFAULT '',
                `seo_model` varchar(64) NOT NULL DEFAULT '',
                `seo_source_hash` char(40) NOT NULL DEFAULT '',
                `seo_error` varchar(255) NOT NULL DEFAULT '',
                `seo_status` tinyint(3) unsigned NOT NULL DEFAULT '1',
                `seo_time_add` int(10) unsigned NOT NULL DEFAULT '0',
                `seo_time_update` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`seo_id`),
                UNIQUE KEY `seo_obj` (`seo_mid`,`seo_obj_id`),
                UNIQUE KEY `seo_obj_uuid` (`seo_mid`,`seo_obj_uuid`),
                KEY `seo_time_update` (`seo_time_update`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function getByObject($mid, $objId)
    {
        $mid = intval($mid);
        $objId = intval($objId);
        if ($mid < 1 || $objId < 1) {
            return null;
        }
        $this->ensureUuidColumn();

        $objUuid = $this->buildObjectUuid($mid, $objId);
        $row = $this->where([
            'seo_mid' => $mid,
            'seo_obj_uuid' => $objUuid,
            'seo_status' => 1
        ])->find();
        if (!empty($row)) {
            return $row;
        }

        // Backward compatibility: old rows keyed by numeric obj_id only.
        $row = $this->where([
            'seo_mid' => $mid,
            'seo_obj_id' => $objId,
            'seo_status' => 1
        ])->find();
        if (!empty($row) && empty($row['seo_obj_uuid'])) {
            $this->where(['seo_id' => intval($row['seo_id'])])->update(['seo_obj_uuid' => $objUuid]);
        }
        return $row;
    }

    public function saveByObject($mid, $objId, $data)
    {
        $mid = intval($mid);
        $objId = intval($objId);
        if ($mid < 1 || $objId < 1) {
            return false;
        }
        $this->ensureUuidColumn();

        $now = time();
        $objUuid = $this->buildObjectUuid($mid, $objId);
        $row = [
            'seo_mid' => $mid,
            'seo_obj_id' => $objId,
            'seo_obj_uuid' => $objUuid,
            'seo_title' => (string)$data['title'],
            'seo_keywords' => (string)$data['keywords'],
            'seo_description' => (string)$data['description'],
            'seo_provider' => (string)$data['provider'],
            'seo_model' => (string)$data['model'],
            'seo_source_hash' => (string)$data['source_hash'],
            'seo_error' => (string)$data['error'],
            'seo_status' => intval($data['status']),
            'seo_time_update' => $now,
        ];

        $old = $this->where(['seo_mid' => $mid, 'seo_obj_uuid' => $objUuid])->find();
        if (empty($old)) {
            // Backward compatibility with old numeric-only key.
            $old = $this->where(['seo_mid' => $mid, 'seo_obj_id' => $objId])->find();
        }
        if (empty($old)) {
            $row['seo_time_add'] = $now;
            return $this->insert($row);
        }
        return $this->where(['seo_id' => intval($old['seo_id'])])->update($row);
    }

    private function ensureUuidColumn()
    {
        $table = config('database.prefix') . $this->name;
        $cols = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'seo_obj_uuid'");
        if (empty($cols)) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `seo_obj_uuid` char(36) NOT NULL DEFAULT '' AFTER `seo_obj_id`");
            Db::execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `seo_obj_uuid` (`seo_mid`,`seo_obj_uuid`)");
        }
    }

    private function buildObjectUuid($mid, $objId)
    {
        $hex = md5('seo:' . intval($mid) . ':' . intval($objId));
        return substr($hex, 0, 8) . '-' .
            substr($hex, 8, 4) . '-' .
            substr($hex, 12, 4) . '-' .
            substr($hex, 16, 4) . '-' .
            substr($hex, 20, 12);
    }
}
