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
if(empty($col_list[$pre.'manga'])){
    $sql .= "CREATE TABLE `mac_manga` ( `manga_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '漫画ID', `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '主分类ID', `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '副分类ID', `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '会员组ID', `manga_name` varchar(255) NOT NULL DEFAULT '' COMMENT '漫画名称', `manga_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题', `manga_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名', `manga_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态(0=锁定,1=正常)', `manga_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母', `manga_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色', `manga_from` varchar(30) NOT NULL DEFAULT '' COMMENT '来源', `manga_author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者', `manga_tag` varchar(100) NOT NULL DEFAULT '' COMMENT '标签', `manga_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类', `manga_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图', `manga_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面缩略图', `manga_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面幻灯图', `manga_pic_screenshot` text DEFAULT NULL COMMENT '内容截图', `manga_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介', `manga_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注(例如：更新至xx话)', `manga_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL', `manga_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '独立模板', `manga_level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '推荐级别', `manga_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '锁定状态(0=未锁,1=已锁)', `manga_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '点播所需积分', `manga_points_detail` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '每章所需积分', `manga_up` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '顶数', `manga_down` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '踩数', `manga_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总点击数', `manga_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '日点击数', `manga_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '周点击数', `manga_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '月点击数', `manga_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间', `manga_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间', `manga_time_hits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点击时间', `manga_time_make` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '生成时间', `manga_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '平均评分', `manga_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总评分', `manga_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '评分次数', `manga_rel_manga` varchar(255) NOT NULL DEFAULT '' COMMENT '关联漫画', `manga_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频', `manga_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '访问密码', `manga_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '密码跳转URL', `manga_content` mediumtext DEFAULT NULL COMMENT '详细介绍', `manga_serial` varchar(20) NOT NULL DEFAULT '0' COMMENT '连载状态(文字)', `manga_total` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总章节数', `manga_chapter_from` varchar(255) NOT NULL DEFAULT '' COMMENT '章节来源', `manga_chapter_url` mediumtext DEFAULT NULL COMMENT '章节URL列表', `manga_last_update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间戳', `manga_age_rating` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '年龄分级(0=全年龄,1=12+,2=18+)', `manga_orientation` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '阅读方向(1=左到右,2=右到左,3=垂直)', `manga_is_vip` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否VIP(0=否,1=是)', `manga_copyright_info` varchar(255) NOT NULL DEFAULT '' COMMENT '版权信息', PRIMARY KEY (`manga_id`), KEY `type_id` (`type_id`) USING BTREE, KEY `type_id_1` (`type_id_1`) USING BTREE, KEY `manga_level` (`manga_level`) USING BTREE, KEY `manga_hits` (`manga_hits`) USING BTREE, KEY `manga_time` (`manga_time`) USING BTREE, KEY `manga_letter` (`manga_letter`) USING BTREE, KEY `manga_down` (`manga_down`) USING BTREE, KEY `manga_up` (`manga_up`) USING BTREE, KEY `manga_tag` (`manga_tag`) USING BTREE, KEY `manga_name` (`manga_name`) USING BTREE, KEY `manga_en` (`manga_en`) USING BTREE, KEY `manga_hits_day` (`manga_hits_day`) USING BTREE, KEY `manga_hits_week` (`manga_hits_week`) USING BTREE, KEY `manga_hits_month` (`manga_hits_month`) USING BTREE, KEY `manga_time_add` (`manga_time_add`) USING BTREE, KEY `manga_time_make` (`manga_time_make`) USING BTREE, KEY `manga_lock` (`manga_lock`), KEY `manga_score` (`manga_score`), KEY `manga_score_all` (`manga_score_all`), KEY `manga_score_num` (`manga_score_num`) ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='漫画表';";
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

    @chmod($file, 0777);
    $config = config('maccms');
    if (strpos($config['collect']['vod']['inrule'], 'a') === false  && !isset($config['collect']['vod']['inrule_first_change'])) {
        $config['collect']['vod']['inrule'] = ',a' . $config['collect']['vod']['inrule'];
        $config['collect']['vod']['inrule_first_change']= true;
        $res = mac_arr2file($file, $config);
    }
}
// 修改group_id字段为varchar(255)
$sql .= "ALTER TABLE `mac_user` MODIFY COLUMN `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID,多个用逗号分隔';";
$sql .= "\r";