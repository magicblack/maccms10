-- 回收站：为已有库增加软删除字段（新安装已包含在 install.sql）
-- 执行后后台「视频/文章/漫画」删除默认进回收站，前台不展示 recycle_time>0 的数据

ALTER TABLE `mac_vod` ADD COLUMN `vod_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' AFTER `vod_time_make`;
ALTER TABLE `mac_art` ADD COLUMN `art_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' AFTER `art_time_make`;
ALTER TABLE `mac_manga` ADD COLUMN `manga_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' AFTER `manga_time_make`;

-- 若表前缀不是 mac_，请替换为你的前缀（与 application/database.php 中 prefix 一致）
