<?php
/*creates*/
if(empty($col_list[$pre.'annex'])){
    $sql .= "CREATE TABLE `mac_annex` (  `annex_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `annex_time` int(10) unsigned NOT NULL DEFAULT '0',  `annex_file` varchar(255) NOT NULL DEFAULT '',  `annex_size` int(10) unsigned NOT NULL DEFAULT '0',  `annex_type` varchar(8) NOT NULL DEFAULT '',  PRIMARY KEY (`annex_id`),  KEY `annex_time` (`annex_time`),  KEY `annex_file` (`annex_file`),  KEY `annex_type` (`annex_type`)) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'website'])){
    $sql .= "CREATE TABLE `mac_website` (  `website_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `type_id` smallint(5) unsigned NOT NULL DEFAULT '0',  `type_id_1` smallint(5) unsigned NOT NULL DEFAULT '0',  `website_name` varchar(60) NOT NULL DEFAULT '',  `website_sub` varchar(255) NOT NULL DEFAULT '',  `website_en` varchar(255) NOT NULL DEFAULT '',  `website_status` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_letter` char(1) NOT NULL DEFAULT '',  `website_color` varchar(6) NOT NULL DEFAULT '',  `website_lock` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_sort` int(10) NOT NULL DEFAULT '0',  `website_jumpurl` varchar(255) NOT NULL DEFAULT '',  `website_pic` varchar(255) NOT NULL DEFAULT '',  `website_logo` varchar(255) NOT NULL DEFAULT '',  `website_area` varchar(20) NOT NULL DEFAULT '',  `website_lang` varchar(10) NOT NULL DEFAULT '',  `website_level` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_time` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_add` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_hits` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_make` int(10) unsigned NOT NULL DEFAULT '0',  `website_hits` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0',  `website_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_up` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_down` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_day` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_week` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_month` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_tag` varchar(100) NOT NULL DEFAULT '',  `website_class` varchar(255) NOT NULL DEFAULT '',  `website_remarks` varchar(100) NOT NULL DEFAULT '',  `website_tpl` varchar(30) NOT NULL DEFAULT '',  `website_blurb` varchar(255) NOT NULL DEFAULT '',  `website_content` mediumtext NOT NULL,  PRIMARY KEY (`website_id`),  KEY `type_id` (`type_id`),  KEY `type_id_1` (`type_id_1`),  KEY `website_name` (`website_name`),  KEY `website_en` (`website_en`),  KEY `website_letter` (`website_letter`),  KEY `website_sort` (`website_sort`),  KEY `website_lock` (`website_lock`),  KEY `website_time` (`website_time`),  KEY `website_time_add` (`website_time_add`),  KEY `website_hits` (`website_hits`),  KEY `website_hits_day` (`website_hits_day`),  KEY `website_hits_week` (`website_hits_week`),  KEY `website_hits_month` (`website_hits_month`),  KEY `website_time_make` (`website_time_make`),  KEY `website_score` (`website_score`),  KEY `website_score_all` (`website_score_all`),  KEY `website_score_num` (`website_score_num`),  KEY `website_up` (`website_up`),  KEY `website_down` (`website_down`),  KEY `website_level` (`website_level`),  KEY `website_tag` (`website_tag`),  KEY `website_class` (`website_class`),  KEY `website_referer` (`website_referer`),  KEY `website_referer_day` (`website_referer_day`),  KEY `website_referer_week` (`website_referer_week`),  KEY `website_referer_month` (`website_referer_month`)) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;";
    $sql .="\r";
}
/*updates*/
if(empty($col_list[$pre.'art']['art_pic_screenshot'])){
    $sql .= "ALTER TABLE `mac_art` ADD `art_pic_screenshot`  text;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_pic_screenshot'])){
    $sql .= "ALTER TABLE `mac_vod` ADD `vod_pic_screenshot`  text;";
    $sql .="\r";
}

if(empty($col_list[$pre.'actor']['type_id'])){
    $sql .= "ALTER TABLE `mac_actor` ADD `type_id`  INT( 10 ) unsigned NOT NULL DEFAULT  '0',ADD `type_id_1`  INT( 10 ) unsigned NOT NULL DEFAULT  '0',ADD `actor_tag`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `actor_class`  VARCHAR( 255 )  NOT NULL DEFAULT  '';";
    $sql .="\r";
}

if(empty($col_list[$pre.'website']['website_pic_screenshot'])){
    $sql .= "ALTER TABLE `mac_website` ADD `website_pic_screenshot`  text;";
    $sql .="\r";
}
if(empty($col_list[$pre.'website']['website_time_referer'])){
    $sql .= "ALTER TABLE `mac_website` ADD `website_time_referer`  INT( 10 ) unsigned NOT NULL DEFAULT  '0';";
    $sql .="\r";
}
if(empty($col_list[$pre.'type']['type_logo'])){
    $sql .= "ALTER TABLE `mac_type` ADD `type_logo`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_pic`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_jumpurl`  VARCHAR( 150 )  NOT NULL DEFAULT  '';";
    $sql .="\r";
}
if(empty($col_list[$pre.'collect']['collect_filter'])){
    $sql .= "ALTER TABLE `mac_collect` ADD `collect_filter` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `collect_filter_from`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `collect_opt` tinyint( 1 )  NOT NULL DEFAULT '0';";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_plot'])){
    $sql .= "ALTER TABLE `mac_vod` ADD `vod_plot` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `vod_plot_name`  mediumtext  NOT NULL ,ADD `vod_plot_detail` mediumtext  NOT NULL ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_reg_ip'])){
    $sql .= "ALTER TABLE  `mac_user` ADD `user_reg_ip` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_reg_time`;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_behind'])){
    $sql .= "ALTER TABLE  `mac_vod` ADD `vod_behind` VARCHAR( 100 )  NOT NULL DEFAULT  '' AFTER  `vod_writer`;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_points_froze'])){
    $sql .= "ALTER TABLE  `mac_user` ADD `user_points_froze` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_points`;";
    $sql .="\r";
}

if(empty($col_list[$pre.'art']['art_points'])){
    $sql .= "ALTER TABLE `mac_art` ADD `art_points` SMALLINT(6) unsigned NOT NULL DEFAULT '0',ADD `art_points_detail` SMALLINT( 6 ) unsigned NOT NULL DEFAULT '0',ADD `art_pwd` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `art_pwd_url`  VARCHAR(255)  NOT NULL DEFAULT '' ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_pwd'])){
    $sql .= "ALTER TABLE `mac_vod` ADD `vod_pwd` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_pwd_play` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_play_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_pwd_down` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_down_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_copyright`  tinyint(1) unsigned NOT NULL DEFAULT '0',ADD `vod_points` SMALLINT( 6 ) unsigned NOT NULL DEFAULT '0'  ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_pid'])){
    $sql .= "ALTER TABLE `mac_user` ADD `user_pid` INT( 10 ) unsigned NOT NULL DEFAULT '0',ADD `user_pid_2`  INT( 10) unsigned  NOT NULL DEFAULT '0' ,ADD `user_pid_3`  INT( 10) unsigned  NOT NULL DEFAULT '0' ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'plog'])){
    $sql .= "CREATE TABLE `mac_plog` (  `plog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `user_id` int(10) unsigned NOT NULL DEFAULT '0',  `user_id_1` int(10) unsigned NOT NULL DEFAULT '0',  `plog_type` tinyint(1) unsigned NOT NULL DEFAULT '1',  `plog_points` smallint(6) unsigned NOT NULL DEFAULT '0',  `plog_time` int(10) unsigned NOT NULL DEFAULT '0',  `plog_remarks` varchar(100) NOT NULL DEFAULT '',  PRIMARY KEY (`plog_id`),  KEY `user_id` (`user_id`),  KEY `plog_type` (`plog_type`) USING BTREE) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}
if(empty($col_list[$pre.'cash'])){
    $sql .= "CREATE TABLE `mac_cash` (  `cash_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `user_id` int(10) unsigned NOT NULL DEFAULT '0',  `cash_status` tinyint(1) unsigned NOT NULL DEFAULT '0',  `cash_points` smallint(6) unsigned NOT NULL DEFAULT '0',  `cash_money` decimal(12,2) unsigned NOT NULL DEFAULT '0.00',  `cash_bank_name` varchar(60) NOT NULL DEFAULT '',  `cash_bank_no` varchar(30) NOT NULL DEFAULT '',  `cash_payee_name` varchar(30) NOT NULL DEFAULT '',  `cash_time` int(10) unsigned NOT NULL DEFAULT '0',  `cash_time_audit` int(10) unsigned NOT NULL DEFAULT '0',  PRIMARY KEY (`cash_id`),  KEY `user_id` (`user_id`),  KEY `cash_status` (`cash_status`) USING BTREE) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}
// 采集时，不同资源站，独立配置同步图片选项
if(empty($col_list[$pre.'collect']['collect_sync_pic_opt'])){
    $sql .= "ALTER TABLE `mac_collect` ADD `collect_sync_pic_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '同步图片选项，0-跟随全局，1-开启，2-关闭';";
    $sql .="\r";
}
// 图片和内容字段采集时长度不够报错
if (version_compare(config('version.code'),'2022.1000.3027','<=')) {
    $sql .= "ALTER TABLE `mac_vod` CHANGE `vod_pic` `vod_pic` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_class`, CHANGE `vod_pic_thumb` `vod_pic_thumb` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_pic`, CHANGE `vod_pic_slide` `vod_pic_slide` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_pic_thumb`, CHANGE `vod_content` `vod_content` mediumtext COLLATE 'utf8_general_ci' NOT NULL AFTER `vod_pwd_down_url`;";
    $sql .="\r";
}
// 优化LIKE查询-vod搜索缓存表
if (empty($col_list[$pre.'vod_search'])) {
    $sql .= "CREATE TABLE `mac_vod_search` ( `search_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索键（关键词md5）', `search_word` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '搜索关键词', `search_field` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索字段名（可有多个，用|分隔）', `search_hit_count` bigint unsigned NOT NULL DEFAULT '0' COMMENT '搜索命中次数', `search_last_hit_time` int unsigned NOT NULL DEFAULT '0' COMMENT '最近命中时间', `search_update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间', `search_result_count` int unsigned NOT NULL DEFAULT '0' COMMENT '结果Id数量', `search_result_ids` mediumtext CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索结果Id列表，英文半角逗号分隔', PRIMARY KEY (`search_key`), KEY `search_field` (`search_field`), KEY `search_update_time` (`search_update_time`), KEY `search_hit_count` (`search_hit_count`), KEY `search_last_hit_time` (`search_last_hit_time`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='vod搜索缓存表';";
    $sql .="\r";
}
// 采集时，过滤年份
// https://github.com/magicblack/maccms10/issues/1057
if(empty($col_list[$pre.'collect']['collect_filter_year'])){
    $sql .= "ALTER TABLE `mac_collect` ADD `collect_filter_year` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '采集时，过滤年份' AFTER `collect_filter_from`;";
    $sql .="\r";
}
// 入库重复规则设置名称
if (version_compare(config('version.code'), '2024.1000.4043', '>=')) {
    $file = APP_PATH . 'extra/maccms.php';
    $backupFile = APP_PATH . 'extra/maccms_backup_' . date('Ymd_His') . '.php';

    copy($file, $backupFile);

    @chmod($file, 0777);
    $config = config('maccms');
    if (strpos($config['collect']['vod']['inrule'], 'a') === false  && !isset($config['collect']['vod']['inrule_first_change'])) {
        $config['collect']['vod']['inrule'] = ',a' . $config['collect']['vod']['inrule'];
        $config['collect']['vod']['inrule_first_change']= true;
        $res = mac_arr2file($file, $config);
    }
}