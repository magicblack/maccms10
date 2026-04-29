<?php
/*creates*/
if(empty($col_list[$pre.'annex'])){
    $sql .= "CREATE TABLE `{$pre}annex` (  `annex_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `annex_time` int(10) unsigned NOT NULL DEFAULT '0',  `annex_file` varchar(255) NOT NULL DEFAULT '',  `annex_size` int(10) unsigned NOT NULL DEFAULT '0',  `annex_type` varchar(8) NOT NULL DEFAULT '',  PRIMARY KEY (`annex_id`),  KEY `annex_time` (`annex_time`),  KEY `annex_file` (`annex_file`),  KEY `annex_type` (`annex_type`)) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'website'])){
    $sql .= "CREATE TABLE `{$pre}website` (  `website_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `type_id` smallint(5) unsigned NOT NULL DEFAULT '0',  `type_id_1` smallint(5) unsigned NOT NULL DEFAULT '0',  `website_name` varchar(60) NOT NULL DEFAULT '',  `website_sub` varchar(255) NOT NULL DEFAULT '',  `website_en` varchar(255) NOT NULL DEFAULT '',  `website_status` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_letter` char(1) NOT NULL DEFAULT '',  `website_color` varchar(6) NOT NULL DEFAULT '',  `website_lock` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_sort` int(10) NOT NULL DEFAULT '0',  `website_jumpurl` varchar(255) NOT NULL DEFAULT '',  `website_pic` varchar(255) NOT NULL DEFAULT '',  `website_logo` varchar(255) NOT NULL DEFAULT '',  `website_area` varchar(20) NOT NULL DEFAULT '',  `website_lang` varchar(10) NOT NULL DEFAULT '',  `website_level` tinyint(1) unsigned NOT NULL DEFAULT '0',  `website_time` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_add` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_hits` int(10) unsigned NOT NULL DEFAULT '0',  `website_time_make` int(10) unsigned NOT NULL DEFAULT '0',  `website_hits` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0',  `website_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_up` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_down` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_day` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_week` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_referer_month` mediumint(8) unsigned NOT NULL DEFAULT '0',  `website_tag` varchar(100) NOT NULL DEFAULT '',  `website_class` varchar(255) NOT NULL DEFAULT '',  `website_remarks` varchar(100) NOT NULL DEFAULT '',  `website_tpl` varchar(30) NOT NULL DEFAULT '',  `website_blurb` varchar(255) NOT NULL DEFAULT '',  `website_content` mediumtext NOT NULL,  PRIMARY KEY (`website_id`),  KEY `type_id` (`type_id`),  KEY `type_id_1` (`type_id_1`),  KEY `website_name` (`website_name`),  KEY `website_en` (`website_en`),  KEY `website_letter` (`website_letter`),  KEY `website_sort` (`website_sort`),  KEY `website_lock` (`website_lock`),  KEY `website_time` (`website_time`),  KEY `website_time_add` (`website_time_add`),  KEY `website_hits` (`website_hits`),  KEY `website_hits_day` (`website_hits_day`),  KEY `website_hits_week` (`website_hits_week`),  KEY `website_hits_month` (`website_hits_month`),  KEY `website_time_make` (`website_time_make`),  KEY `website_score` (`website_score`),  KEY `website_score_all` (`website_score_all`),  KEY `website_score_num` (`website_score_num`),  KEY `website_up` (`website_up`),  KEY `website_down` (`website_down`),  KEY `website_level` (`website_level`),  KEY `website_tag` (`website_tag`),  KEY `website_class` (`website_class`),  KEY `website_referer` (`website_referer`),  KEY `website_referer_day` (`website_referer_day`),  KEY `website_referer_week` (`website_referer_week`),  KEY `website_referer_month` (`website_referer_month`)) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'manga'])){
    $sql .= "CREATE TABLE `{$pre}manga` ( `manga_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '漫画ID', `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '主分类ID', `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '副分类ID', `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '会员组ID', `manga_name` varchar(255) NOT NULL DEFAULT '' COMMENT '漫画名称', `manga_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题', `manga_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名', `manga_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态(0=锁定,1=正常)', `manga_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母', `manga_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色', `manga_from` varchar(30) NOT NULL DEFAULT '' COMMENT '来源', `manga_author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者', `manga_tag` varchar(100) NOT NULL DEFAULT '' COMMENT '标签', `manga_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类', `manga_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图', `manga_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面缩略图', `manga_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面幻灯图', `manga_pic_screenshot` text DEFAULT NULL COMMENT '内容截图', `manga_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介', `manga_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注(例如：更新至xx话)', `manga_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL', `manga_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '独立模板', `manga_level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '推荐级别', `manga_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '锁定状态(0=未锁,1=已锁)', `manga_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '点播所需积分', `manga_points_detail` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '每章所需积分', `manga_up` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '顶数', `manga_down` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '踩数', `manga_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总点击数', `manga_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '日点击数', `manga_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '周点击数', `manga_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '月点击数', `manga_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间', `manga_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间', `manga_time_hits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点击时间', `manga_time_make` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '生成时间', `manga_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '平均评分', `manga_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总评分', `manga_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '评分次数', `manga_rel_manga` varchar(255) NOT NULL DEFAULT '' COMMENT '关联漫画', `manga_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频', `manga_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '访问密码', `manga_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '密码跳转URL', `manga_content` mediumtext DEFAULT NULL COMMENT '详细介绍', `manga_serial` varchar(20) NOT NULL DEFAULT '0' COMMENT '连载状态(文字)', `manga_total` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总章节数', `manga_chapter_from` varchar(255) NOT NULL DEFAULT '' COMMENT '章节来源', `manga_chapter_url` mediumtext DEFAULT NULL COMMENT '章节URL列表', `manga_last_update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间戳', `manga_age_rating` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '年龄分级(0=全年龄,1=12+,2=18+)', `manga_orientation` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '阅读方向(1=左到右,2=右到左,3=垂直)', `manga_is_vip` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否VIP(0=否,1=是)', `manga_copyright_info` varchar(255) NOT NULL DEFAULT '' COMMENT '版权信息', PRIMARY KEY (`manga_id`), KEY `type_id` (`type_id`) USING BTREE, KEY `type_id_1` (`type_id_1`) USING BTREE, KEY `manga_level` (`manga_level`) USING BTREE, KEY `manga_hits` (`manga_hits`) USING BTREE, KEY `manga_time` (`manga_time`) USING BTREE, KEY `manga_letter` (`manga_letter`) USING BTREE, KEY `manga_down` (`manga_down`) USING BTREE, KEY `manga_up` (`manga_up`) USING BTREE, KEY `manga_tag` (`manga_tag`) USING BTREE, KEY `manga_name` (`manga_name`) USING BTREE, KEY `manga_en` (`manga_en`) USING BTREE, KEY `manga_hits_day` (`manga_hits_day`) USING BTREE, KEY `manga_hits_week` (`manga_hits_week`) USING BTREE, KEY `manga_hits_month` (`manga_hits_month`) USING BTREE, KEY `manga_time_add` (`manga_time_add`) USING BTREE, KEY `manga_time_make` (`manga_time_make`) USING BTREE, KEY `manga_lock` (`manga_lock`), KEY `manga_score` (`manga_score`), KEY `manga_score_all` (`manga_score_all`), KEY `manga_score_num` (`manga_score_num`) ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='漫画表';";
    $sql .="\r";
}
/*updates*/
if(empty($col_list[$pre.'art']['art_pic_screenshot'])){
    $sql .= "ALTER TABLE `{$pre}art` ADD `art_pic_screenshot`  text;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_pic_screenshot'])){
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_pic_screenshot`  text;";
    $sql .="\r";
}

if(empty($col_list[$pre.'actor']['type_id'])){
    $sql .= "ALTER TABLE `{$pre}actor` ADD `type_id`  INT( 10 ) unsigned NOT NULL DEFAULT  '0',ADD `type_id_1`  INT( 10 ) unsigned NOT NULL DEFAULT  '0',ADD `actor_tag`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `actor_class`  VARCHAR( 255 )  NOT NULL DEFAULT  '';";
    $sql .="\r";
}

if(empty($col_list[$pre.'website']['website_pic_screenshot'])){
    $sql .= "ALTER TABLE `{$pre}website` ADD `website_pic_screenshot`  text;";
    $sql .="\r";
}
if(empty($col_list[$pre.'website']['website_time_referer'])){
    $sql .= "ALTER TABLE `{$pre}website` ADD `website_time_referer`  INT( 10 ) unsigned NOT NULL DEFAULT  '0';";
    $sql .="\r";
}
if(empty($col_list[$pre.'type']['type_logo'])){
    $sql .= "ALTER TABLE `{$pre}type` ADD `type_logo`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_pic`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_jumpurl`  VARCHAR( 150 )  NOT NULL DEFAULT  '';";
    $sql .="\r";
}
if(empty($col_list[$pre.'collect']['collect_filter'])){
    $sql .= "ALTER TABLE `{$pre}collect` ADD `collect_filter` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `collect_filter_from`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `collect_opt` tinyint( 1 )  NOT NULL DEFAULT '0';";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_plot'])){
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_plot` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `vod_plot_name`  mediumtext  NOT NULL ,ADD `vod_plot_detail` mediumtext  NOT NULL ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_reg_ip'])){
    $sql .= "ALTER TABLE  `{$pre}user` ADD `user_reg_ip` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_reg_time`;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_behind'])){
    $sql .= "ALTER TABLE  `{$pre}vod` ADD `vod_behind` VARCHAR( 100 )  NOT NULL DEFAULT  '' AFTER  `vod_writer`;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_points_froze'])){
    $sql .= "ALTER TABLE  `{$pre}user` ADD `user_points_froze` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_points`;";
    $sql .="\r";
}

if(empty($col_list[$pre.'art']['art_points'])){
    $sql .= "ALTER TABLE `{$pre}art` ADD `art_points` SMALLINT(6) unsigned NOT NULL DEFAULT '0',ADD `art_points_detail` SMALLINT( 6 ) unsigned NOT NULL DEFAULT '0',ADD `art_pwd` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `art_pwd_url`  VARCHAR(255)  NOT NULL DEFAULT '' ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_pwd'])){
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_pwd` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_pwd_play` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_play_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_pwd_down` VARCHAR( 10 )  NOT NULL DEFAULT '',ADD `vod_pwd_down_url`  VARCHAR(255)  NOT NULL DEFAULT '',ADD `vod_copyright`  tinyint(1) unsigned NOT NULL DEFAULT '0',ADD `vod_points` SMALLINT( 6 ) unsigned NOT NULL DEFAULT '0'  ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'user']['user_pid'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_pid` INT( 10 ) unsigned NOT NULL DEFAULT '0',ADD `user_pid_2`  INT( 10) unsigned  NOT NULL DEFAULT '0' ,ADD `user_pid_3`  INT( 10) unsigned  NOT NULL DEFAULT '0' ;";
    $sql .="\r";
}
if(empty($col_list[$pre.'plog'])){
    $sql .= "CREATE TABLE `{$pre}plog` (  `plog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `user_id` int(10) unsigned NOT NULL DEFAULT '0',  `user_id_1` int(10) unsigned NOT NULL DEFAULT '0',  `plog_type` tinyint(1) unsigned NOT NULL DEFAULT '1',  `plog_points` smallint(6) unsigned NOT NULL DEFAULT '0',  `plog_time` int(10) unsigned NOT NULL DEFAULT '0',  `plog_remarks` varchar(100) NOT NULL DEFAULT '',  PRIMARY KEY (`plog_id`),  KEY `user_id` (`user_id`),  KEY `plog_type` (`plog_type`) USING BTREE) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}
if(empty($col_list[$pre.'cash'])){
    $sql .= "CREATE TABLE `{$pre}cash` (  `cash_id` int(10) unsigned NOT NULL AUTO_INCREMENT,  `user_id` int(10) unsigned NOT NULL DEFAULT '0',  `cash_status` tinyint(1) unsigned NOT NULL DEFAULT '0',  `cash_points` smallint(6) unsigned NOT NULL DEFAULT '0',  `cash_money` decimal(12,2) unsigned NOT NULL DEFAULT '0.00',  `cash_bank_name` varchar(60) NOT NULL DEFAULT '',  `cash_bank_no` varchar(30) NOT NULL DEFAULT '',  `cash_payee_name` varchar(30) NOT NULL DEFAULT '',  `cash_time` int(10) unsigned NOT NULL DEFAULT '0',  `cash_time_audit` int(10) unsigned NOT NULL DEFAULT '0',  PRIMARY KEY (`cash_id`),  KEY `user_id` (`user_id`),  KEY `cash_status` (`cash_status`) USING BTREE) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}
// 采集时，不同资源站，独立配置同步图片选项
if(empty($col_list[$pre.'collect']['collect_sync_pic_opt'])){
    $sql .= "ALTER TABLE `{$pre}collect` ADD `collect_sync_pic_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '同步图片选项，0-跟随全局，1-开启，2-关闭';";
    $sql .="\r";
}
// 图片和内容字段采集时长度不够报错
if (version_compare(config('version.code'),'2022.1000.3027','<=')) {
    $sql .= "ALTER TABLE `{$pre}vod` CHANGE `vod_pic` `vod_pic` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_class`, CHANGE `vod_pic_thumb` `vod_pic_thumb` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_pic`, CHANGE `vod_pic_slide` `vod_pic_slide` varchar(1024) COLLATE 'utf8_general_ci' NOT NULL DEFAULT '' AFTER `vod_pic_thumb`, CHANGE `vod_content` `vod_content` mediumtext COLLATE 'utf8_general_ci' NOT NULL AFTER `vod_pwd_down_url`;";
    $sql .="\r";
}
// 优化LIKE查询-vod搜索缓存表
if (empty($col_list[$pre.'vod_search'])) {
    $sql .= "CREATE TABLE `{$pre}vod_search` ( `search_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索键（关键词md5）', `search_word` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '搜索关键词', `search_field` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索字段名（可有多个，用|分隔）', `search_hit_count` bigint unsigned NOT NULL DEFAULT '0' COMMENT '搜索命中次数', `search_last_hit_time` int unsigned NOT NULL DEFAULT '0' COMMENT '最近命中时间', `search_update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间', `search_result_count` int unsigned NOT NULL DEFAULT '0' COMMENT '结果Id数量', `search_result_ids` mediumtext CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索结果Id列表，英文半角逗号分隔', PRIMARY KEY (`search_key`), KEY `search_field` (`search_field`), KEY `search_update_time` (`search_update_time`), KEY `search_hit_count` (`search_hit_count`), KEY `search_last_hit_time` (`search_last_hit_time`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='vod搜索缓存表';";
    $sql .="\r";
}
// SEO AI 结果缓存表
if (empty($col_list[$pre.'seo_ai_result'])) {
    $sql .= "CREATE TABLE `{$pre}seo_ai_result` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .= "\r";
}
// 采集时，过滤年份
// https://github.com/magicblack/maccms10/issues/1057
if(empty($col_list[$pre.'collect']['collect_filter_year'])){
    $sql .= "ALTER TABLE `{$pre}collect` ADD `collect_filter_year` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '采集时，过滤年份' AFTER `collect_filter_from`;";
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
//回收站字段
foreach (['vod', 'art', 'manga'] as $module) {
    $col = $module . '_recycle_time';
    $after = $module . '_time_make';
    if (empty($col_list[$pre . $module][$col])) {
        $sql .= "ALTER TABLE `{$pre}{$module}` ADD COLUMN `{$col}` int(10) unsigned NOT NULL DEFAULT '0' AFTER `{$after}`;";
        $sql .= "\r";
    }
}
// 修改group_id字段为varchar(255)
$sql .= "ALTER TABLE `{$pre}user` MODIFY COLUMN `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID,多个用逗号分隔';";
$sql .= "\r";

//新增运营统计数据表
if(empty($col_list[$pre.'analytics_day_overview'])){
    $sql .= "CREATE TABLE `{$pre}analytics_day_overview` (`stat_date` date NOT NULL COMMENT '统计日（站点时区日历日）',`pv` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '页面浏览量',`uv` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '独立访客（按 visitor_id/cookie 去重，由任务写入）',`session_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会话数',`new_reg` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '新注册用户数',`user_login_dau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '登录日活（当日有登录行为的用户数）',`user_active_mau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '月活（自然月内去重活跃，可月末回填或滚动窗口）',`order_paid_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已支付订单笔数',`order_paid_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '已支付订单金额',`recharge_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '充值类金额（可与订单拆分或等于 order 中充值类型汇总）',`ad_impression` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '广告曝光',`ad_click` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '广告点击',`avg_session_duration_sec` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '平均会话时长（秒）',`bounce_rate` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '跳出率 0-100（单页会话/总会话）',`retention_d1` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '次日留存率 0-100（按 cohort 任务写入）',`retention_d7` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '7日留存率',`retention_d30` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '30日留存率',`pv_web` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Web 端 PV',`pv_h5` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'H5 端 PV',`pv_android` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Android PV',`pv_ios` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'iOS PV',`pv_other` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '未知/其它端 PV',`updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '本条汇总更新时间 UNIX',PRIMARY KEY (`stat_date`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-全站按日汇总';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_day_dim'])){
    $sql .= "CREATE TABLE `{$pre}analytics_day_dim` (`analytics_day_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`stat_date` date NOT NULL,`dim_type` varchar(32) NOT NULL COMMENT '维度类型',`dim_key` varchar(128) NOT NULL COMMENT '维度取值',`pv` bigint(20) unsigned NOT NULL DEFAULT '0',`uv` bigint(20) unsigned NOT NULL DEFAULT '0',`session_cnt` int(10) unsigned NOT NULL DEFAULT '0',`new_reg` int(10) unsigned NOT NULL DEFAULT '0',`dau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '该切片下日活（定义与任务一致即可）',`order_paid_cnt` int(10) unsigned NOT NULL DEFAULT '0',`order_paid_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00',`ad_click` bigint(20) unsigned NOT NULL DEFAULT '0',`updated_at` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`analytics_day_id`),UNIQUE KEY `uk_date_dim` (`stat_date`,`dim_type`,`dim_key`),KEY `idx_dim_type_date` (`dim_type`,`stat_date`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-按日多维切片';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_hour_dim'])){
    $sql .= "CREATE TABLE `{$pre}analytics_hour_dim` (`analytics_hour_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`stat_hour` datetime NOT NULL COMMENT '整点时间，如 2026-04-15 08:00:00',`dim_type` varchar(32) NOT NULL DEFAULT 'all' COMMENT '同 day_dim，all 表示全站',`dim_key` varchar(128) NOT NULL DEFAULT '',`pv` bigint(20) unsigned NOT NULL DEFAULT '0',`uv` bigint(20) unsigned NOT NULL DEFAULT '0',`session_cnt` int(10) unsigned NOT NULL DEFAULT '0',`updated_at` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`analytics_hour_id`),UNIQUE KEY `uk_hour_dim` (`stat_hour`,`dim_type`,`dim_key`),KEY `idx_hour` (`stat_hour`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-按小时多维';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_session'])){
    $sql .= "CREATE TABLE `{$pre}analytics_session` (`session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`session_key` varchar(64) NOT NULL COMMENT '服务端生成或客户端上报的会话ID',`visitor_id` varchar(64) NOT NULL DEFAULT '' COMMENT '匿名访客标识（cookie/device）',`user_id` int(10) unsigned NOT NULL DEFAULT '0',`device_type` varchar(16) NOT NULL DEFAULT '' COMMENT 'web/h5/android/ios',`os` varchar(32) NOT NULL DEFAULT '',`browser` varchar(32) NOT NULL DEFAULT '',`app_version` varchar(32) NOT NULL DEFAULT '',`region_code` varchar(16) NOT NULL DEFAULT '' COMMENT '省/国家等简码',`channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道：utm、应用市场等',`entry_path` varchar(512) NOT NULL DEFAULT '' COMMENT '落地路径',`exit_path` varchar(512) NOT NULL DEFAULT '' COMMENT '离开前最后路径',`page_count` smallint(5) unsigned NOT NULL DEFAULT '0',`duration_sec` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会话时长',`is_bounce` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否跳出会话(仅1次浏览即离开)',`started_at` int(10) unsigned NOT NULL DEFAULT '0',`ended_at` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`session_id`),UNIQUE KEY  `uk_session_key` (`session_key`),KEY `idx_started` (`started_at`),KEY `idx_user` (`user_id`),KEY `idx_visitor` (`visitor_id`),KEY `idx_device_date` (`device_type`,`started_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-会话';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_pageview'])){
    $sql .= "CREATE TABLE `{$pre}analytics_pageview` (`analytics_pageview_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`session_id` bigint(20) unsigned NOT NULL DEFAULT '0',`visitor_id` varchar(64) NOT NULL DEFAULT '',`user_id` int(10) unsigned NOT NULL DEFAULT '0',`path` varchar(512) NOT NULL DEFAULT '' COMMENT '路径或路由',`mid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '模块 1视频2文章8漫画等，0非内容页',`rid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '内容ID',`type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID，便于关联多维',`stay_ms` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '停留毫秒（离开页或心跳上报）',`prev_path` varchar(512) NOT NULL DEFAULT '' COMMENT '上一页路径，构路径漏斗',`referer_host` varchar(255) NOT NULL DEFAULT '',`ts` int(10) unsigned NOT NULL DEFAULT '0',`stat_date` date NOT NULL,PRIMARY KEY (`analytics_pageview_id`),KEY `idx_session_ts` (`session_id`,`ts`),KEY `idx_ts` (`ts`),KEY `idx_stat_date` (`stat_date`),KEY `idx_content` (`mid`,`rid`,`ts`),KEY `idx_type_ts` (`type_id`,`ts`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-页面浏览明细';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_event'])){
    $sql .= "CREATE TABLE `{$pre}analytics_event` (`analytics_event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,`event_code` varchar(48) NOT NULL COMMENT '事件编码 ad_click / pay_intent / ...',`session_id` bigint(20) unsigned NOT NULL DEFAULT '0',`visitor_id` varchar(64) NOT NULL DEFAULT '',`user_id` int(10) unsigned NOT NULL DEFAULT '0',`device_type` varchar(16) NOT NULL DEFAULT '',`region_code` varchar(16) NOT NULL DEFAULT '',`mid` tinyint(3) unsigned NOT NULL DEFAULT '0',`rid` int(10) unsigned NOT NULL DEFAULT '0',`props` varchar(2048) NOT NULL DEFAULT '' COMMENT 'JSON 扩展字段,5.7+ 环境可改为 JSON 类型更优',`ts` int(10) unsigned NOT NULL DEFAULT '0',`stat_date` date NOT NULL,PRIMARY KEY (`analytics_event_id`),KEY `idx_event_ts` (`event_code`,`ts`),KEY `idx_ts` (`ts`),KEY `idx_stat_date` (`stat_date`),KEY `idx_session_id` (`session_id`),KEY `idx_user_ts` (`user_id`,`ts`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-通用事件';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_content_day'])){
    $sql .= "CREATE TABLE `{$pre}analytics_content_day` (`stat_date` date NOT NULL,`mid` tinyint(3) unsigned NOT NULL COMMENT '1视频2文章8漫画',`content_id` int(10) unsigned NOT NULL,`type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '分类，冗余便于按类分析',`view_pv` bigint(20) unsigned NOT NULL DEFAULT '0',`view_uv` bigint(20) unsigned NOT NULL DEFAULT '0',`play_or_read_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '播放/阅读次数（按业务定义）',`avg_stay_ms` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '平均停留',`bounce_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '仅访问该内容即离开的会话数（任务算）',`collect_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '收藏新增',`want_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '想看新增',`order_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关联订单数（付费转化）',`order_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00',`updated_at` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`stat_date`,`mid`,`content_id`),KEY `idx_date_type` (`stat_date`,`type_id`),KEY `idx_hot` (`stat_date`,`view_pv`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-内容按日效果';";
    $sql .="\r";
}
if(empty($col_list[$pre.'analytics_retention_cohort'])){
    $sql .= "CREATE TABLE `{$pre}analytics_retention_cohort` (`cohort_date` date NOT NULL COMMENT 'cohort 基准日（常用：注册日）',`cohort_type` varchar(16) NOT NULL DEFAULT 'register',`return_day` smallint(5) unsigned NOT NULL COMMENT '回访间隔天 0=当日 1=次日',`user_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '该日仍活跃用户数',`updated_at` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`cohort_date`,`cohort_type`,`return_day`),KEY `idx_cohort` (`cohort_date`,`cohort_type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-留存 cohort';";
    $sql .="\r";
$sql .= "ALTER TABLE `mac_user` MODIFY COLUMN `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID,多个用逗号分隔';";
$sql .= "\r";
}
// 好友邀请功能 - 添加邀请码相关字段
if(empty($col_list[$pre.'user']['user_invite_code'])){
    $sql .= "ALTER TABLE `mac_user` ADD `user_invite_code` varchar(20) NOT NULL DEFAULT '' COMMENT '邀请码' AFTER `user_pid_3`;";
    $sql .= "\r";
}
if(empty($col_list[$pre.'user']['user_invite_count'])){
    $sql .= "ALTER TABLE `mac_user` ADD `user_invite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '邀请人数' AFTER `user_invite_code`;";
    $sql .= "\r";
}
if(empty($col_list[$pre.'user']['user_invite_reward_time'])){
    $sql .= "ALTER TABLE `mac_user` ADD `user_invite_reward_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后一次发放奖励时间' AFTER `user_invite_count`;";
    $sql .= "\r";
}
// 邀请奖励档次记录 - 避免重复发放
if(empty($col_list[$pre.'user']['user_invite_reward_level'])){
    $sql .= "ALTER TABLE `mac_user` ADD `user_invite_reward_level` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已发放奖励档次(避免重复发放)' AFTER `user_invite_reward_time`;";
    $sql .= "\r";
}
// 邀请码索引 - 避免全表扫描（使用 SHOW INDEX 检查索引是否已存在）
$index_exists = \think\Db::query("SHOW INDEX FROM `{$pre}user` WHERE Key_name = 'idx_user_invite_code'");
if(empty($index_exists)){
    $sql .= "ALTER TABLE `{$pre}user` ADD INDEX `idx_user_invite_code` (`user_invite_code`);";
    $sql .= "\r";
}
// 任务定义表
if(empty($col_list[$pre.'task'])){
    $sql .= "CREATE TABLE `{$pre}task` (";
    $sql .= "`task_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`task_name` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',";
    $sql .= "`task_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '任务类型 1=每日任务 2=新手任务',";
    $sql .= "`task_action` varchar(50) NOT NULL DEFAULT '' COMMENT '任务动作标识',";
    $sql .= "`task_icon` varchar(255) NOT NULL DEFAULT '' COMMENT '任务图标',";
    $sql .= "`task_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '任务描述',";
    $sql .= "`task_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '奖励积分',";
    $sql .= "`task_target` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '目标次数',";
    $sql .= "`task_sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',";
    $sql .= "`task_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=启用',";
    $sql .= "`task_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',";
    $sql .= "`task_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',";
    $sql .= "PRIMARY KEY (`task_id`),";
    $sql .= "KEY `task_type` (`task_type`),";
    $sql .= "UNIQUE KEY `task_action` (`task_action`),";
    $sql .= "KEY `task_status` (`task_status`),";
    $sql .= "KEY `task_sort` (`task_sort`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务定义表';";
    $sql .= "\r";
}
// 用户任务记录表
if(empty($col_list[$pre.'task_log'])){
    $sql .= "CREATE TABLE `{$pre}task_log` (";
    $sql .= "`log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`task_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '任务ID',";
    $sql .= "`task_action` varchar(50) NOT NULL DEFAULT '' COMMENT '任务动作标识',";
    $sql .= "`log_progress` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '当前进度',";
    $sql .= "`log_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态 0=进行中 1=已完成待领取 2=已领取',";
    $sql .= "`log_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',";
    $sql .= "`log_date` date NOT NULL COMMENT '任务日期',";
    $sql .= "`log_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '记录时间',";
    $sql .= "`log_claim_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '领取奖励时间',";
    $sql .= "PRIMARY KEY (`log_id`),";
    $sql .= "UNIQUE KEY `user_task_date` (`user_id`, `task_id`, `log_date`),";
    $sql .= "KEY `user_id` (`user_id`),";
    $sql .= "KEY `task_id` (`task_id`),";
    $sql .= "KEY `log_status` (`log_status`),";
    $sql .= "KEY `log_date` (`log_date`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户任务记录表';";
    $sql .= "\r";
}
// 签到记录表
if(empty($col_list[$pre.'sign_log'])){
    $sql .= "CREATE TABLE `{$pre}sign_log` (";
    $sql .= "`sign_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`sign_date` date NOT NULL COMMENT '签到日期',";
    $sql .= "`sign_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '签到时间戳',";
    $sql .= "`sign_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',";
    $sql .= "`sign_serial_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '连续签到天数',";
    $sql .= "PRIMARY KEY (`sign_id`),";
    $sql .= "UNIQUE KEY `user_date` (`user_id`, `sign_date`),";
    $sql .= "KEY `user_id` (`user_id`),";
    $sql .= "KEY `sign_date` (`sign_date`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到记录表';";
    $sql .= "\r";
}
// 签到里程碑配置表
if(empty($col_list[$pre.'sign_milestone'])){
    $sql .= "CREATE TABLE `{$pre}sign_milestone` (";
    $sql .= "`milestone_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`milestone_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '所需连续签到天数',";
    $sql .= "`milestone_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '奖励积分',";
    $sql .= "`milestone_sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',";
    $sql .= "`milestone_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=启用',";
    $sql .= "`milestone_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',";
    $sql .= "`milestone_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',";
    $sql .= "PRIMARY KEY (`milestone_id`),";
    $sql .= "UNIQUE KEY `milestone_days` (`milestone_days`),";
    $sql .= "KEY `milestone_status` (`milestone_status`),";
    $sql .= "KEY `milestone_sort` (`milestone_sort`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到里程碑配置表';";
    $sql .= "\r";
}
// 签到里程碑领取记录表
if(empty($col_list[$pre.'sign_milestone_log'])){
    $sql .= "CREATE TABLE `{$pre}sign_milestone_log` (";
    $sql .= "`log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`milestone_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '里程碑ID',";
    $sql .= "`milestone_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '达成天数',";
    $sql .= "`log_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',";
    $sql .= "`log_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '领取时间',";
    $sql .= "PRIMARY KEY (`log_id`),";
    $sql .= "UNIQUE KEY `user_milestone` (`user_id`, `milestone_id`),";
    $sql .= "KEY `user_id` (`user_id`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到里程碑领取记录';";
    $sql .= "\r";
}
// 插入预设签到里程碑数据
$milestone_count = \think\Db::name('sign_milestone')->count();
if(empty($milestone_count)){
    $now = time();
    $sql .= "INSERT INTO `{$pre}sign_milestone` (`milestone_days`,`milestone_points`,`milestone_sort`,`milestone_status`,`milestone_time_add`,`milestone_time`) VALUES ";
    $sql .= "(3,5,1,1,{$now},{$now}),";
    $sql .= "(10,10,2,1,{$now},{$now}),";
    $sql .= "(20,20,3,1,{$now},{$now}),";
    $sql .= "(35,30,4,1,{$now},{$now}),";
    $sql .= "(55,50,5,1,{$now},{$now}),";
    $sql .= "(85,100,6,1,{$now},{$now});";
    $sql .= "\r";
}
// 插入预设任务数据
$task_count = \think\Db::name('task')->count();
if(empty($task_count)){
    $now = time();
    $sql .= "INSERT INTO `{$pre}task` (`task_name`,`task_type`,`task_action`,`task_desc`,`task_points`,`task_target`,`task_sort`,`task_status`,`task_time_add`,`task_time`) VALUES ";
    $sql .= "('每日签到',1,'daily_sign','每天签到获得积分奖励',5,1,1,1,{$now},{$now}),";
    $sql .= "('观看影片',1,'watch_vod','每日观看3部影片',3,3,2,1,{$now},{$now}),";
    $sql .= "('分享影片',1,'share_vod','每日分享1次影片到社交平台',2,1,3,1,{$now},{$now}),";
    $sql .= "('发表评论',1,'post_comment','每日发表1条评论',2,1,4,1,{$now},{$now}),";
    $sql .= "('绑定手机',2,'bind_phone','绑定手机号码',20,1,1,1,{$now},{$now}),";
    $sql .= "('绑定邮箱',2,'bind_email','绑定电子邮箱',20,1,2,1,{$now},{$now}),";
    $sql .= "('设置头像',2,'set_portrait','上传个人头像',10,1,3,1,{$now},{$now}),";
    $sql .= "('完善资料',2,'complete_profile','填写个人昵称等资料',10,1,4,1,{$now},{$now}),";
    $sql .= "('首次充值',2,'first_pay','完成首次充值',50,1,5,1,{$now},{$now});";
    $sql .= "\r";
}

