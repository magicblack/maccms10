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
if(empty($col_list[$pre.'mall_goods'])){
    $sql .= "CREATE TABLE `{$pre}mall_goods` ( `mall_goods_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `mall_goods_name` varchar(100) NOT NULL DEFAULT '', `mall_goods_type` varchar(20) NOT NULL DEFAULT '', `mall_goods_points` int(10) unsigned NOT NULL DEFAULT '0', `mall_goods_stock` int(10) unsigned NOT NULL DEFAULT '0', `mall_goods_status` tinyint(1) unsigned NOT NULL DEFAULT '0', `mall_goods_sort` int(10) NOT NULL DEFAULT '0', `mall_goods_ext` text NOT NULL, `mall_goods_time_add` int(10) unsigned NOT NULL DEFAULT '0', `mall_goods_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`mall_goods_id`), KEY `mall_goods_type` (`mall_goods_type`), KEY `mall_goods_status` (`mall_goods_status`), KEY `mall_goods_sort` (`mall_goods_sort`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}mall_goods` ENGINE=InnoDB;";
    $sql .="\r";
}
if(empty($col_list[$pre.'mall_order'])){
    $sql .= "CREATE TABLE `{$pre}mall_order` ( `mall_order_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user_id` int(10) unsigned NOT NULL DEFAULT '0', `mall_goods_id` int(10) unsigned NOT NULL DEFAULT '0', `mall_goods_name` varchar(100) NOT NULL DEFAULT '', `mall_goods_type` varchar(20) NOT NULL DEFAULT '', `mall_order_points` int(10) unsigned NOT NULL DEFAULT '0', `mall_order_quantity` int(10) unsigned NOT NULL DEFAULT '1', `mall_order_status` tinyint(1) unsigned NOT NULL DEFAULT '0', `mall_order_snapshot` text NOT NULL, `mall_order_delivery` text NOT NULL, `mall_order_remarks` varchar(255) NOT NULL DEFAULT '', `mall_order_time` int(10) unsigned NOT NULL DEFAULT '0', `mall_order_complete_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`mall_order_id`), KEY `user_id` (`user_id`), KEY `mall_goods_id` (`mall_goods_id`), KEY `mall_goods_type` (`mall_goods_type`), KEY `mall_order_status` (`mall_order_status`), KEY `mall_order_time` (`mall_order_time`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}mall_order` ENGINE=InnoDB;";
    $sql .="\r";
}
if(empty($col_list[$pre.'notify'])){
    $sql .= "CREATE TABLE `{$pre}notify` ( `notify_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user_id` int(10) unsigned NOT NULL DEFAULT '0', `notify_type` varchar(20) NOT NULL DEFAULT '', `notify_title` varchar(255) NOT NULL DEFAULT '', `notify_content` text, `notify_read` tinyint(1) unsigned NOT NULL DEFAULT '0', `notify_time` int(10) unsigned NOT NULL DEFAULT '0', `notify_link` varchar(255) NOT NULL DEFAULT '', PRIMARY KEY (`notify_id`), KEY `user_read` (`user_id`,`notify_read`), KEY `notify_time` (`notify_time`), KEY `notify_type` (`notify_type`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}notify` ENGINE=InnoDB;";
    $sql .="\r";
}
if(empty($col_list[$pre.'notify_read'])){
    $sql .= "CREATE TABLE `{$pre}notify_read` ( `user_id` int(10) unsigned NOT NULL DEFAULT '0', `notify_id` int(10) unsigned NOT NULL DEFAULT '0', `read_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`user_id`,`notify_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}notify_read` ENGINE=InnoDB;";
    $sql .="\r";
}
// 定时任务（notify_vip_expire / content_ai_annotate / content_quality[_art] / user_profile）
// 统一由下方自包含 $_timming_defaults 块幂等注入（读取→检查→仅补缺失键→回写），不依赖 common.php。
// 优惠券 + 限时折扣（Issue 3）：mac_coupon / mac_coupon_user / mac_group 活动字段 / mac_order.order_remarks 扩容
if(empty($col_list[$pre.'coupon'])){
    $sql .= "CREATE TABLE `{$pre}coupon` ( `coupon_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `coupon_name` varchar(100) NOT NULL DEFAULT '', `coupon_type` varchar(20) NOT NULL DEFAULT 'amount', `coupon_value` decimal(10,2) unsigned NOT NULL DEFAULT '0.00', `coupon_min_price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00', `coupon_scene` varchar(20) NOT NULL DEFAULT 'all', `coupon_target` text NOT NULL, `coupon_total` int(10) unsigned NOT NULL DEFAULT '0', `coupon_received` int(10) unsigned NOT NULL DEFAULT '0', `coupon_used` int(10) unsigned NOT NULL DEFAULT '0', `coupon_per_user` int(10) unsigned NOT NULL DEFAULT '1', `coupon_start_time` int(10) unsigned NOT NULL DEFAULT '0', `coupon_end_time` int(10) unsigned NOT NULL DEFAULT '0', `coupon_status` tinyint(1) unsigned NOT NULL DEFAULT '1', `coupon_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`coupon_id`), KEY `coupon_time` (`coupon_time`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
}
if(empty($col_list[$pre.'coupon_user'])){
    $sql .= "CREATE TABLE `{$pre}coupon_user` ( `coupon_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `coupon_id` int(10) unsigned NOT NULL DEFAULT '0', `user_id` int(10) unsigned NOT NULL DEFAULT '0', `coupon_user_status` tinyint(1) unsigned NOT NULL DEFAULT '0', `coupon_user_time` int(10) unsigned NOT NULL DEFAULT '0', `coupon_user_use_time` int(10) unsigned NOT NULL DEFAULT '0', `order_id` int(10) unsigned NOT NULL DEFAULT '0', `order_code` varchar(30) NOT NULL DEFAULT '', PRIMARY KEY (`coupon_user_id`), UNIQUE KEY `uk_coupon_user` (`coupon_id`,`user_id`), KEY `order_code` (`order_code`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $uk = \think\Db::query("SHOW INDEX FROM `{$pre}coupon_user` WHERE Key_name = 'uk_coupon_user'");
    if (empty($uk)) {
        $sql .= "ALTER TABLE `{$pre}coupon_user` ADD UNIQUE KEY `uk_coupon_user` (`coupon_id`,`user_id`);";
        $sql .="\r";
    }
}
// mac_group VIP 活动价 + 时段字段（day/week/month/year），幂等
// 字段清单：group_activity_points_day/week/month/year, group_activity_start_time_day/week/month/year, group_activity_end_time_day/week/month/year
$coupon_group_adds = [
    'group_activity_points_day'        => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_points_week'       => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_points_month'      => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_points_year'       => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_start_time_day'    => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_start_time_week'   => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_start_time_month'  => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_start_time_year'   => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_end_time_day'      => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_end_time_week'     => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_end_time_month'    => "int(10) unsigned NOT NULL DEFAULT '0'",
    'group_activity_end_time_year'     => "int(10) unsigned NOT NULL DEFAULT '0'",
];
foreach ($coupon_group_adds as $col => $def) {
    if (!empty($col_list[$pre.'group']) && empty($col_list[$pre.'group'][$col])) {
        $sql .= "ALTER TABLE `{$pre}group` ADD `{$col}` {$def};";
        $sql .="\r";
    }
}
// mac_order.order_remarks 扩容为 text（VIP 升级/优惠券快照需要更大空间），幂等 ADD/MODIFY
if (empty($col_list[$pre.'order']['order_remarks'])) {
    $sql .= "ALTER TABLE `{$pre}order` ADD `order_remarks` text NOT NULL;";
    $sql .="\r";
} else {
    // 仅当当前类型不是 text 时才 MODIFY，避免每次升级都对大表 mac_order 整表重建 + 锁表
    $remarks_col = \think\Db::query("SHOW COLUMNS FROM `{$pre}order` LIKE 'order_remarks'");
    if (!empty($remarks_col) && stripos((string)$remarks_col[0]['Type'], 'text') === false) {
        $sql .= "ALTER TABLE `{$pre}order` MODIFY `order_remarks` text NOT NULL;";
        $sql .="\r";
    }
}
// 优惠券/秒杀的支付与扣减流程依赖 InnoDB 事务回滚，存量 MyISAM 表迁移到 InnoDB。
// 必须先探测当前引擎：无条件 ALTER TABLE ... ENGINE=InnoDB 即使表已是 InnoDB 也会整表重建 + 锁表，
// mac_user / mac_plog 这类百万行大表每次升级都会被锁死几分钟。
$innodb_tables = [
    $pre . 'order',
    $pre . 'user',
    $pre . 'plog',
    $pre . 'group',
    $pre . 'coupon',
    $pre . 'coupon_user',
    $pre . 'seckill',
    $pre . 'seckill_user',
];
foreach ($innodb_tables as $innodb_table) {
    if (empty($col_list[$innodb_table])) {
        continue;
    }
    $table_status = \think\Db::query("SHOW TABLE STATUS LIKE '" . $innodb_table . "'");
    if (empty($table_status[0]['Engine']) || strtolower($table_status[0]['Engine']) === 'innodb') {
        continue;
    }
    $sql .= "ALTER TABLE `{$innodb_table}` ENGINE=InnoDB;";
    $sql .="\r";
}
// 秒杀模块（Issue 3 追加）：mac_seckill / mac_seckill_user
if(empty($col_list[$pre.'seckill'])){
    $sql .= "CREATE TABLE `{$pre}seckill` ( `seckill_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `seckill_name` varchar(100) NOT NULL DEFAULT '', `seckill_target_type` varchar(20) NOT NULL DEFAULT 'vip_group', `seckill_target_id` int(10) unsigned NOT NULL DEFAULT '0', `seckill_target_long` varchar(10) NOT NULL DEFAULT 'month', `seckill_origin_points` int(10) unsigned NOT NULL DEFAULT '0', `seckill_price_points` int(10) unsigned NOT NULL DEFAULT '0', `seckill_total` int(10) unsigned NOT NULL DEFAULT '0', `seckill_sold` int(10) unsigned NOT NULL DEFAULT '0', `seckill_per_user` int(10) unsigned NOT NULL DEFAULT '1', `seckill_start_time` int(10) unsigned NOT NULL DEFAULT '0', `seckill_end_time` int(10) unsigned NOT NULL DEFAULT '0', `seckill_status` tinyint(1) unsigned NOT NULL DEFAULT '1', `seckill_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`seckill_id`), KEY `seckill_time` (`seckill_time`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    if (!empty($col_list[$pre.'seckill']) && empty($col_list[$pre.'seckill']['seckill_target_long'])) {
        $sql .= "ALTER TABLE `{$pre}seckill` ADD `seckill_target_long` varchar(10) NOT NULL DEFAULT 'month';";
        $sql .="\r";
    }
}
if(empty($col_list[$pre.'seckill_user'])){
    $sql .= "CREATE TABLE `{$pre}seckill_user` ( `seckill_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `seckill_id` int(10) unsigned NOT NULL DEFAULT '0', `user_id` int(10) unsigned NOT NULL DEFAULT '0', `order_code` varchar(30) NOT NULL DEFAULT '', `seckill_user_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`seckill_user_id`), UNIQUE KEY `uk_seckill_user` (`seckill_id`,`user_id`), KEY `order_code` (`order_code`) ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $ukSeckill = \think\Db::query("SHOW INDEX FROM `{$pre}seckill_user` WHERE Key_name = 'uk_seckill_user'");
    if (empty($ukSeckill)) {
        $sql .= "ALTER TABLE `{$pre}seckill_user` ADD UNIQUE KEY `uk_seckill_user` (`seckill_id`,`user_id`);";
        $sql .="\r";
    }
}
// 定时任务幂等注入（通知中心 VIP 到期提醒 + 视频定时上架）：仅在缺失时补写，不覆盖用户调整。
// 升级流程必须自包含：step1 解压覆盖后，本请求已加载的 common.php 可能仍是旧版（磁盘未被
// 覆盖，或站长设备 opcache 仍缓存旧版），不能依赖 common.php 的 mac_inject_timming_task /
// mac_arr2file。这里只用 ThinkPHP 核心 config() 与 PHP 内建函数，确保所有站长设备都能执行 step2。
{
    $_timming_file = APP_PATH . 'extra/timming.php';
    $_timming = config('timming');
    if (!is_array($_timming)) {
        $_timming = [];
    }
    $_timming_defaults = [
        // 运营统计聚合。这两条此前只存在于版本库的 extra/timming.php，而升级不覆盖该文件，
        // 所以存量站升级后一直没有这两个任务：analytics_day_overview 恒空，后台首页的
        // 近七日访问只能每次回退去扫 mac_analytics_pageview 明细做 count(distinct)。
        // 字段与 extra/timming.php 权威版一致：file=analytics 对应 api/controller/Timming::analytics()。
        'analytics_hour' => [
            'id'      => 'analytics_hour',
            'status'  => '1',
            'name'    => 'analytics_hour',
            'des'     => '运营统计小时聚合',
            'file'    => 'analytics',
            'param'   => 'mode=hour',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
            'runtime' => 0,
        ],
        'analytics_day' => [
            'id'      => 'analytics_day',
            'status'  => '1',
            'name'    => 'analytics_day',
            'des'     => '运营统计日聚合',
            'file'    => 'analytics',
            'param'   => 'mode=day',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '01',
            'runtime' => 0,
        ],
        'notify_vip_expire' => [
            'id'      => 'notify_vip_expire',
            'status'  => '0',
            'name'    => 'notify_vip_expire',
            'des'     => 'VIP到期提醒通知',
            'file'    => 'notify',
            'param'   => 'days=3',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '00,06,12,18',
            'runtime' => 0,
        ],
        'vod_publish' => [
            'id'      => 'vod_publish',
            'status'  => '1',
            'name'    => 'vod_publish',
            'des'     => '视频定时上架',
            'file'    => 'vodpublish',
            'param'   => 'limit=200',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
            'runtime' => 0,
        ],
        'content_ai_annotate' => [
            'id'      => 'content_ai_annotate',
            'status'  => '0',
            'name'    => 'content_ai_annotate',
            'des'     => 'AI内容标注批量生成',
            'file'    => 'annotate',
            'param'   => 'mid=1&limit=50',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '02',
            'runtime' => 0,
        ],
        'content_quality' => [
            'id'      => 'content_quality',
            'status'  => '0',
            'name'    => 'content_quality',
            'des'     => '内容质量分批量计算',
            'file'    => 'content_quality',
            'param'   => 'mid=1&limit=200&days=30',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '03',
            'runtime' => 0,
        ],
        'content_quality_art' => [
            'id'      => 'content_quality_art',
            'status'  => '0',
            'name'    => 'content_quality_art',
            'des'     => '内容质量分批量计算-文章',
            'file'    => 'content_quality',
            'param'   => 'mid=2&limit=200&days=30',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '04',
            'runtime' => 0,
        ],
        'user_profile' => [
            'id'      => 'user_profile',
            'status'  => '0',
            'name'    => 'user_profile',
            'des'     => '用户画像批量计算',
            'file'    => 'user_profile',
            'param'   => 'limit=200&days=30',
            'weeks'   => '1,2,3,4,5,6,0',
            'hours'   => '05',
            'runtime' => 0,
        ],
        // PWA Web Push：广播队列派发任务（feat-pwa）。存量站点升级不覆盖 extra/timming.php，
        // 必须在此注入，否则 push_queue 入队后无任务派发，公告永远卡在 status=0 收不到。
        // 字段与 extra/timming.php 权威版一致：file=pushbroadcast 对应 api/controller/Timming::pushbroadcast()。
        'push_broadcast' => [
            'id'       => 'push_broadcast',
            'status'   => '1',
            'name'     => 'push_broadcast',
            'des'      => 'Web Push广播队列派发',
            'file'     => 'pushbroadcast',
            'param'    => 'batch=100&max=500',
            'weeks'    => '1,2,3,4,5,6,0',
            'hours'    => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
            'interval' => 60,
            'runtime'  => 0,
        ],
    ];
    $_timming_changed = false;
    foreach ($_timming_defaults as $_k => $_task) {
        if (!isset($_timming[$_k])) {
            $_timming[$_k] = $_task;
            $_timming_changed = true;
        }
    }
    if ($_timming_changed) {
        @chmod($_timming_file, 0644);
        file_put_contents($_timming_file, "<?php\nreturn " . var_export($_timming, true) . ';');
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($_timming_file, true);
        }
    }
    unset($_timming_file, $_timming, $_timming_defaults, $_timming_changed, $_k, $_task);
}
// PWA Web Push：推送订阅表（feat-pwa）
if(empty($col_list[$pre.'push_subscription'])){
    $sql .= "CREATE TABLE `{$pre}push_subscription` ( `subscription_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user_id` int(10) unsigned NOT NULL DEFAULT '0', `endpoint` varchar(512) NOT NULL DEFAULT '', `p256dh` varchar(255) NOT NULL DEFAULT '', `auth` varchar(255) NOT NULL DEFAULT '', `user_agent` varchar(255) NOT NULL DEFAULT '', `create_time` int(10) unsigned NOT NULL DEFAULT '0', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`subscription_id`), UNIQUE KEY `user_endpoint` (`user_id`,`endpoint`(191)), KEY `user_id` (`user_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}push_subscription` ENGINE=InnoDB;";
    $sql .="\r";
}
// PWA Web Push：广播推送队列表（feat-pwa）。PushDispatcher::enqueueBroadcast/runQueue 依赖此表，
// 存量站点升级时必须补建，否则人工广播勾选“同时推送”会因表缺失而失败。
if(empty($col_list[$pre.'push_queue'])){
    $sql .= "CREATE TABLE `{$pre}push_queue` ( `queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL DEFAULT '', `body` varchar(255) NOT NULL DEFAULT '', `url` varchar(512) NOT NULL DEFAULT '', `icon` varchar(255) NOT NULL DEFAULT '', `sent` int(10) unsigned NOT NULL DEFAULT '0', `failed` int(10) unsigned NOT NULL DEFAULT '0', `last_id` int(10) unsigned NOT NULL DEFAULT '0', `status` tinyint(1) NOT NULL DEFAULT '0', `create_time` int(10) unsigned NOT NULL DEFAULT '0', `update_time` int(10) unsigned NOT NULL DEFAULT '0', PRIMARY KEY (`queue_id`), KEY `status` (`status`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $sql .="\r";
} else {
    $sql .= "ALTER TABLE `{$pre}push_queue` ENGINE=InnoDB;";
    $sql .="\r";
}
if(!empty($col_list[$pre.'user']) && empty($col_list[$pre.'user']['user_down_quota'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_down_quota` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '下载额度' AFTER `user_points_froze`;";
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
// External source providers
if (empty($col_list[$pre.'ext_provider'])) {
    $sql .= "CREATE TABLE `{$pre}ext_provider` (
`provider_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`provider_code` varchar(32) NOT NULL DEFAULT '',
`provider_name` varchar(80) NOT NULL DEFAULT '',
`provider_enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
`provider_type` varchar(32) NOT NULL DEFAULT 'api',
`provider_conf` mediumtext NOT NULL,
`provider_time_add` int(10) unsigned NOT NULL DEFAULT '0',
`provider_time_update` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`provider_id`),
UNIQUE KEY `provider_code` (`provider_code`),
KEY `provider_enabled` (`provider_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source provider config';";
    $sql .= "\r";
}
// External source normalized items
if (empty($col_list[$pre.'ext_source_item'])) {
    $sql .= "CREATE TABLE `{$pre}ext_source_item` (
`item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`provider_code` varchar(32) NOT NULL DEFAULT '',
`item_key` varchar(128) NOT NULL DEFAULT '',
`item_mid` tinyint(3) unsigned NOT NULL DEFAULT '0',
`item_title` varchar(255) NOT NULL DEFAULT '',
`item_subtitle` varchar(255) NOT NULL DEFAULT '',
`item_snippet` varchar(500) NOT NULL DEFAULT '',
`item_url` varchar(500) NOT NULL DEFAULT '',
`item_cover` varchar(500) NOT NULL DEFAULT '',
`item_score` decimal(8,4) NOT NULL DEFAULT '0.0000',
`item_release_date` varchar(20) NOT NULL DEFAULT '',
`item_payload` mediumtext NOT NULL,
`item_time_add` int(10) unsigned NOT NULL DEFAULT '0',
`item_time_update` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`item_id`),
UNIQUE KEY `uk_provider_item` (`provider_code`,`item_key`),
KEY `idx_mid_score` (`item_mid`,`item_score`),
KEY `idx_title` (`item_title`),
KEY `idx_time_update` (`item_time_update`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source normalized items';";
    $sql .= "\r";
}
// External source CMS mapping
if (empty($col_list[$pre.'ext_source_map'])) {
    $sql .= "CREATE TABLE `{$pre}ext_source_map` (
`map_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`provider_code` varchar(32) NOT NULL DEFAULT '',
`item_key` varchar(128) NOT NULL DEFAULT '',
`cms_mid` tinyint(3) unsigned NOT NULL DEFAULT '0',
`cms_id` int(10) unsigned NOT NULL DEFAULT '0',
`map_confidence` decimal(8,4) NOT NULL DEFAULT '0.0000',
`map_time_add` int(10) unsigned NOT NULL DEFAULT '0',
`map_time_update` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`map_id`),
UNIQUE KEY `uk_map` (`provider_code`,`item_key`,`cms_mid`,`cms_id`),
KEY `idx_cms_obj` (`cms_mid`,`cms_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source to CMS mapping';";
    $sql .= "\r";
}
// External search cache
if (empty($col_list[$pre.'ext_search_cache'])) {
    $sql .= "CREATE TABLE `{$pre}ext_search_cache` (
`cache_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`cache_key` char(40) NOT NULL DEFAULT '',
`query_word` varchar(255) NOT NULL DEFAULT '',
`query_mid` tinyint(3) unsigned NOT NULL DEFAULT '0',
`provider_code` varchar(32) NOT NULL DEFAULT '',
`result_total` int(10) unsigned NOT NULL DEFAULT '0',
`result_payload` mediumtext NOT NULL,
`expire_time` int(10) unsigned NOT NULL DEFAULT '0',
`cache_time_add` int(10) unsigned NOT NULL DEFAULT '0',
`cache_time_update` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`cache_id`),
UNIQUE KEY `uk_cache_key` (`cache_key`),
KEY `idx_query` (`query_word`,`query_mid`),
KEY `idx_expire` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External search cache';";
    $sql .= "\r";
}
// External sync jobs
if (empty($col_list[$pre.'ext_sync_job'])) {
    $sql .= "CREATE TABLE `{$pre}ext_sync_job` (
`job_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`provider_code` varchar(32) NOT NULL DEFAULT '',
`job_type` varchar(32) NOT NULL DEFAULT 'feed_recent',
`job_status` tinyint(3) unsigned NOT NULL DEFAULT '1',
`job_param` varchar(1000) NOT NULL DEFAULT '',
`job_last_run` int(10) unsigned NOT NULL DEFAULT '0',
`job_next_run` int(10) unsigned NOT NULL DEFAULT '0',
`job_interval` int(10) unsigned NOT NULL DEFAULT '3600',
`job_retry` tinyint(3) unsigned NOT NULL DEFAULT '0',
`job_time_add` int(10) unsigned NOT NULL DEFAULT '0',
`job_time_update` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`job_id`),
KEY `idx_status_next` (`job_status`,`job_next_run`),
KEY `idx_provider` (`provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source sync jobs';";
    $sql .= "\r";
}
// External sync logs
if (empty($col_list[$pre.'ext_sync_log'])) {
    $sql .= "CREATE TABLE `{$pre}ext_sync_log` (
`log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
`job_id` bigint(20) unsigned NOT NULL DEFAULT '0',
`provider_code` varchar(32) NOT NULL DEFAULT '',
`log_status` tinyint(3) unsigned NOT NULL DEFAULT '1',
`log_msg` varchar(1000) NOT NULL DEFAULT '',
`log_total` int(10) unsigned NOT NULL DEFAULT '0',
`log_success` int(10) unsigned NOT NULL DEFAULT '0',
`log_time_add` int(10) unsigned NOT NULL DEFAULT '0',
PRIMARY KEY (`log_id`),
KEY `idx_provider_time` (`provider_code`,`log_time_add`),
KEY `idx_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source sync logs';";
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
}
//内容 AI 标注结果（待采纳队列）
if(empty($col_list[$pre.'content_ai_annotation'])){
    $sql .= "CREATE TABLE `{$pre}content_ai_annotation` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`mid` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '模块 1=视频 2=文章',`content_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '内容 ID',`ai_tags` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI 建议标签，逗号分隔',`ai_summary` varchar(1000) NOT NULL DEFAULT '' COMMENT 'AI 建议摘要',`ai_type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT 'AI 建议分类，0=无建议',`ai_confidence` decimal(4,3) unsigned NOT NULL DEFAULT '0.000' COMMENT '模型自评置信度 0-1',`source_hash` char(40) NOT NULL DEFAULT '' COMMENT '源文本 SHA1，用于变更检测',`status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0=待采纳 1=已采纳 2=已拒绝 3=生成失败',`provider` varchar(32) NOT NULL DEFAULT '',`model` varchar(64) NOT NULL DEFAULT '',`error_msg` varchar(255) NOT NULL DEFAULT '',`time_add` int(10) unsigned NOT NULL DEFAULT '0',`time_update` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`id`),UNIQUE KEY `uk_obj` (`mid`,`content_id`),KEY `idx_status` (`status`),KEY `idx_time_update` (`time_update`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容 AI 标注结果（待采纳队列）';";
    $sql .= "\r";
}
//内容质量分
if(empty($col_list[$pre.'content_quality'])){
    $sql .= "CREATE TABLE `{$pre}content_quality` (`id` int(11) unsigned NOT NULL AUTO_INCREMENT,`mid` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '模块 1=视频 2=文章',`content_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '内容 ID',`type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '分类，冗余便于按类分析',`score_total` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '综合质量分',`score_behavior` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '行为分',`score_interact` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '互动分',`score_complete` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '完整度分',`score_fresh` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT '新鲜度分',`is_cold_start` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否冷启动内容',`calc_date` date NOT NULL DEFAULT '1970-01-01' COMMENT '计算日期',`time_add` int(10) unsigned NOT NULL DEFAULT '0',`time_update` int(10) unsigned NOT NULL DEFAULT '0',PRIMARY KEY (`id`),UNIQUE KEY `uk_obj` (`mid`,`content_id`),KEY `idx_score` (`mid`,`score_total`),KEY `idx_type` (`type_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容质量分';";
    $sql .= "\r";
}
//用户画像
if(empty($col_list[$pre.'user_profile'])){
    $sql .= "CREATE TABLE `{$pre}user_profile` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT,`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',`prefer_types` varchar(500) NOT NULL DEFAULT '' COMMENT '偏好分类 JSON [{type_id,w}] Top-N',`prefer_tags` varchar(1000) NOT NULL DEFAULT '' COMMENT '偏好标签 JSON Top-N',`avg_completion_rate` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '平均完播率 0-1',`watch_cnt_window` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '窗口内播放次数',`pay_amount_window` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '窗口内消费金额',`activity_level` tinyint(1) NOT NULL DEFAULT '0' COMMENT '活跃度 0沉睡 1低 2中 3高',`last_active_time` int(10) NOT NULL DEFAULT '0' COMMENT '最后活跃时间戳',`calc_date` date NOT NULL DEFAULT '1970-01-01' COMMENT '计算日期',`time_add` int(10) NOT NULL DEFAULT '0',`time_update` int(10) NOT NULL DEFAULT '0',PRIMARY KEY (`id`),UNIQUE KEY `uk_user` (`user_id`),KEY `idx_activity` (`activity_level`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户画像';";
    $sql .= "\r";
}
// 聊天室消息表
if(empty($col_list[$pre.'chatroom'])){
    $sql .= "CREATE TABLE `{$pre}chatroom` (";
    $sql .= "`chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`vod_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片ID(聊天室房间)',";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '用户昵称',";
    $sql .= "`chat_content` varchar(500) NOT NULL DEFAULT '' COMMENT '聊天内容',";
    $sql .= "`chat_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',";
    $sql .= "`chat_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间',";
    $sql .= "`chat_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=正常',";
    $sql .= "`chat_report` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '举报次数',";
    $sql .= "PRIMARY KEY (`chat_id`),";
    $sql .= "KEY `vod_id` (`vod_id`),";
    $sql .= "KEY `user_id` (`user_id`),";
    $sql .= "KEY `chat_time` (`chat_time`),";
    $sql .= "KEY `chat_status` (`chat_status`),";
    $sql .= "KEY `vod_chat` (`vod_id`, `chat_id`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天室消息表';";
    $sql .= "\r";
}
// 弹幕表
if(empty($col_list[$pre.'danmaku'])){
    $sql .= "CREATE TABLE `{$pre}danmaku` (";
    $sql .= "`danmaku_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`vod_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片ID',";
    $sql .= "`vod_sid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '播放源ID',";
    $sql .= "`vod_nid` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '集数ID',";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '用户昵称',";
    $sql .= "`danmaku_time` float unsigned NOT NULL DEFAULT '0' COMMENT '弹幕出现的影片时间点(秒)',";
    $sql .= "`danmaku_type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '弹幕类型 0=滚动 1=顶部 2=底部',";
    $sql .= "`danmaku_color` varchar(10) NOT NULL DEFAULT '#FFFFFF' COMMENT '弹幕颜色',";
    $sql .= "`danmaku_text` varchar(200) NOT NULL DEFAULT '' COMMENT '弹幕内容',";
    $sql .= "`danmaku_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',";
    $sql .= "`danmaku_send_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间戳',";
    $sql .= "`danmaku_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=正常',";
    $sql .= "`danmaku_report` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '举报次数',";
    $sql .= "PRIMARY KEY (`danmaku_id`),";
    $sql .= "KEY `vod_id` (`vod_id`),";
    $sql .= "KEY `vod_episode` (`vod_id`, `vod_sid`, `vod_nid`),";
    $sql .= "KEY `user_id` (`user_id`),";
    $sql .= "KEY `danmaku_send_time` (`danmaku_send_time`),";
    $sql .= "KEY `danmaku_status` (`danmaku_status`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='弹幕表';";
    $sql .= "\r";
}
// 用户关注关系表
if(empty($col_list[$pre.'user_follow'])){
    $sql .= "CREATE TABLE `{$pre}user_follow` (";
    $sql .= "`follow_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关注者(发起关注的用户)',";
    $sql .= "`follow_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '被关注者',";
    $sql .= "`follow_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=已取消 1=正常',";
    $sql .= "`follow_mutual` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否互关 0=否 1=是',";
    $sql .= "`follow_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关注时间',";
    $sql .= "PRIMARY KEY (`follow_id`),";
    $sql .= "UNIQUE KEY `uk_user_follow` (`user_id`,`follow_uid`),";
    $sql .= "KEY `follow_uid` (`follow_uid`),";
    $sql .= "KEY `follow_status` (`follow_status`),";
    $sql .= "KEY `follow_time` (`follow_time`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关注关系表';";
    $sql .= "\r";
}
// 内容分享日志表
if(empty($col_list[$pre.'share_log'])){
    $sql .= "CREATE TABLE `{$pre}share_log` (";
    $sql .= "`share_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',";
    $sql .= "`share_mid` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '内容模块 1=vod 2=art 3=topic 8=actor 12=manga',";
    $sql .= "`share_rid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '内容ID',";
    $sql .= "`share_platform` varchar(30) NOT NULL DEFAULT '' COMMENT '分享平台(weixin/qq/weibo/douyin/bilibili等)',";
    $sql .= "`share_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',";
    $sql .= "`share_day` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享日期(Ymd,用于按天去重)',";
    $sql .= "`share_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享时间',";
    $sql .= "PRIMARY KEY (`share_id`),";
    $sql .= "UNIQUE KEY `uk_user_content_day` (`user_id`,`share_mid`,`share_rid`,`share_day`),";
    $sql .= "KEY `share_mid_rid` (`share_mid`,`share_rid`),";
    $sql .= "KEY `user_id` (`user_id`),";
    $sql .= "KEY `share_time` (`share_time`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容分享日志表';";
    $sql .= "\r";
}
// 用户动态事件表
if(empty($col_list[$pre.'dynamics'])){
    $sql .= "CREATE TABLE `{$pre}dynamics` (";
    $sql .= "`dynamics_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '行为人(产生动态的用户)',";
    $sql .= "`dynamics_type` varchar(20) NOT NULL DEFAULT '' COMMENT '动态类型 fav/comment/reply/follow/share/danmaku/chat',";
    $sql .= "`dyn_mid` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '关联内容模块(1=vod 2=art 等,跟随/私信类为0)',";
    $sql .= "`dyn_rid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关联内容ID',";
    $sql .= "`dyn_pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关联父ID(如回复的评论ID)',";
    $sql .= "`dyn_text` varchar(500) NOT NULL DEFAULT '' COMMENT '动态摘要文本',";
    $sql .= "`dyn_extra` varchar(500) NOT NULL DEFAULT '' COMMENT '扩展JSON',";
    $sql .= "`dyn_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '动态时间',";
    $sql .= "PRIMARY KEY (`dynamics_id`),";
    $sql .= "KEY `idx_user_time` (`user_id`,`dyn_time`),";
    $sql .= "KEY `dynamics_type` (`dynamics_type`),";
    $sql .= "KEY `dyn_mid_rid` (`dyn_mid`,`dyn_rid`),";
    $sql .= "KEY `dyn_time` (`dyn_time`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户动态事件表';";
    $sql .= "\r";
}
// 用户私信表
if(empty($col_list[$pre.'user_pm'])){
    $sql .= "CREATE TABLE `{$pre}user_pm` (";
    $sql .= "`pm_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`from_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发信人',";
    $sql .= "`to_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '收信人',";
    $sql .= "`pm_content` varchar(500) NOT NULL DEFAULT '' COMMENT '私信内容',";
    $sql .= "`pm_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',";
    $sql .= "`pm_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间',";
    $sql .= "`pm_read` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否已读 0=未读 1=已读',";
    $sql .= "`pm_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=正常',";
    $sql .= "PRIMARY KEY (`pm_id`),";
    $sql .= "KEY `idx_to_read_time` (`to_uid`,`pm_read`,`pm_time`),";
    $sql .= "KEY `from_uid` (`from_uid`),";
    $sql .= "KEY `pm_time` (`pm_time`),";
    $sql .= "KEY `pm_status` (`pm_status`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户私信表';";
    $sql .= "\r";
}
// vod_share_count 加列（幂等）
if(!empty($col_list[$pre.'vod']) && empty($col_list[$pre.'vod']['vod_share_count'])){
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_share_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享次数';";
    $sql .= "\r";
}
// 修改 group_id 为 varchar(255)（仅当列仍存在且尚未为 varchar(255)，避免每次升级重复 MODIFY）
if(!empty($col_list[$pre.'user']['group_id'] ?? null)){
    $groupIdType = strtolower($col_list[$pre.'user']['group_id']['COLUMN_TYPE'] ?? '');
    if ($groupIdType !== 'varchar(255)') {
        $sql .= "ALTER TABLE `{$pre}user` MODIFY COLUMN `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID,多个用逗号分隔';";
        $sql .= "\r";
    }
}
// 漏洞8：密码哈希从无盐 md5 迁移到 bcrypt（60 字符）。加宽 user_pwd / admin_pwd 到 varchar(255)
// 容纳 bcrypt 哈希并为未来算法预留空间。仅当列存在且尚未为 varchar(255) 时 MODIFY，保证幂等。
if(!empty($col_list[$pre.'user']['user_pwd'] ?? null)){
    $userPwdType = strtolower($col_list[$pre.'user']['user_pwd']['COLUMN_TYPE'] ?? '');
    if ($userPwdType !== 'varchar(255)') {
        $sql .= "ALTER TABLE `{$pre}user` MODIFY COLUMN `user_pwd` varchar(255) NOT NULL DEFAULT '';";
        $sql .= "\r";
    }
}
if(!empty($col_list[$pre.'admin']['admin_pwd'] ?? null)){
    $adminPwdType = strtolower($col_list[$pre.'admin']['admin_pwd']['COLUMN_TYPE'] ?? '');
    if ($adminPwdType !== 'varchar(255)') {
        $sql .= "ALTER TABLE `{$pre}admin` MODIFY COLUMN `admin_pwd` varchar(255) NOT NULL DEFAULT '';";
        $sql .= "\r";
    }
}
// 好友邀请功能 - 添加邀请码相关字段
if(empty($col_list[$pre.'user']['user_invite_code'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_invite_code` varchar(20) NOT NULL DEFAULT '' COMMENT '邀请码' AFTER `user_pid_3`;";
    $sql .= "\r";
}
if(empty($col_list[$pre.'user']['user_invite_count'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_invite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '邀请人数' AFTER `user_invite_code`;";
    $sql .= "\r";
}
if(empty($col_list[$pre.'user']['user_invite_reward_time'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_invite_reward_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后一次发放奖励时间' AFTER `user_invite_count`;";
    $sql .= "\r";
}
// 邀请奖励档次记录 - 避免重复发放
if(empty($col_list[$pre.'user']['user_invite_reward_level'])){
    $sql .= "ALTER TABLE `{$pre}user` ADD `user_invite_reward_level` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已发放奖励档次(避免重复发放)' AFTER `user_invite_reward_time`;";
    $sql .= "\r";
}
// 邀请码索引 - 避免全表扫描（使用 SHOW INDEX 检查索引是否已存在）
if(!empty($col_list[$pre.'user'])){
    $index_exists = \think\Db::query("SHOW INDEX FROM `{$pre}user` WHERE Key_name = 'idx_user_invite_code'");
    if(empty($index_exists)){
        $sql .= "ALTER TABLE `{$pre}user` ADD INDEX `idx_user_invite_code` (`user_invite_code`);";
        $sql .= "\r";
    }
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
$milestoneNeedInsert = false;
if(empty($col_list[$pre.'sign_milestone'])){
    // 本次升级新建该表，必空，需插预设
    $milestoneNeedInsert = true;
}else{
    $milestone_count = \think\Db::name('sign_milestone')->count();
    if (empty($milestone_count)) {
        $milestoneNeedInsert = true;
    }
}
if($milestoneNeedInsert){
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
$taskNeedInsert = false;
if(empty($col_list[$pre.'task'])){
    // 本次升级新建该表，必空，需插预设
    $taskNeedInsert = true;
}else{
    $task_count = \think\Db::name('task')->count();
    if (empty($task_count)) {
        $taskNeedInsert = true;
    }
}
if($taskNeedInsert){
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

// 前台搜索关键词日志（热门词、登录用户历史）
if (empty($col_list[$pre . 'search_query_log'])) {
    $sql .= "CREATE TABLE `{$pre}search_query_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `mid` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `keyword` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `log_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`log_id`),
  KEY `idx_user_time` (`user_id`,`log_time`),
  KEY `idx_time` (`log_time`),
  KEY `idx_keyword` (`keyword`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台搜索关键词日志（热门词/用户历史）';";
    $sql .= "\r";
}
// 续播/播放进度记忆：mac_ulog 增加已观看秒数与总时长字段（仅当字段不存在时 ALTER，幂等）
if(!empty($col_list[$pre.'ulog']) && empty($col_list[$pre.'ulog']['ulog_point'])){
    $sql .= "ALTER TABLE `{$pre}ulog` ADD `ulog_point` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已观看秒数' AFTER `ulog_points`, ADD `ulog_duration` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片总时长(秒)' AFTER `ulog_point`;";
    $sql .= "\r";
}
// 续播/播放记录查询优化：mac_ulog 复合索引 (user_id, ulog_mid, ulog_type, ulog_time)
// 覆盖“按用户+模块+类型筛选并按时间倒序”的续播/历史查询，避免仅命中 ulog_mid 低区分度索引及 ulog_time filesort
// 使用 SHOW INDEX 检查索引是否已存在，保证升级幂等
if(!empty($col_list[$pre.'ulog'])){
    $index_exists = \think\Db::query("SHOW INDEX FROM `{$pre}ulog` WHERE Key_name = 'idx_user_mid_type_time'");
    if(empty($index_exists)){
        $sql .= "ALTER TABLE `{$pre}ulog` ADD INDEX `idx_user_mid_type_time` (`user_id`,`ulog_mid`,`ulog_type`,`ulog_time`);";
        $sql .= "\r";
    }
}
// 播放失败自动切换线路：视频线路播放失败统计表
if(empty($col_list[$pre.'vod_play_fail'])){
    $sql .= "CREATE TABLE `{$pre}vod_play_fail` (";
    $sql .= "`fail_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`vod_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片ID',";
    $sql .= "`vod_sid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '线路序号(第几个播放源)',";
    $sql .= "`vod_nid` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '集数序号(0=整条线路)',";
    $sql .= "`play_from` varchar(30) NOT NULL DEFAULT '' COMMENT '播放器标识(dplayer/videojs等)',";
    $sql .= "`vod_name` varchar(255) NOT NULL DEFAULT '' COMMENT '影片名称(冗余,便于后台列表显示)',";
    $sql .= "`fail_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '累计失败次数',";
    $sql .= "`switch_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '成功切换到下一线路次数',";
    $sql .= "`first_fail_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '首次失败时间',";
    $sql .= "`last_fail_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最近失败时间',";
    $sql .= "`last_fail_ip` varchar(45) NOT NULL DEFAULT '' COMMENT '最近失败IP',";
    $sql .= "PRIMARY KEY (`fail_id`),";
    $sql .= "UNIQUE KEY `uk_vod_sid_nid` (`vod_id`,`vod_sid`,`vod_nid`),";
    $sql .= "KEY `fail_count` (`fail_count`),";
    $sql .= "KEY `last_fail_time` (`last_fail_time`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频线路播放失败统计';";
    $sql .= "\r";
}

// 后台操作审计日志
if (empty($col_list[$pre . 'admin_audit_log'])) {
    $sql .= "CREATE TABLE `{$pre}admin_audit_log` (
  `audit_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_name` varchar(60) NOT NULL DEFAULT '',
  `audit_time` int(10) unsigned NOT NULL DEFAULT '0',
  `audit_ip` varchar(45) NOT NULL DEFAULT '',
  `audit_method` varchar(10) NOT NULL DEFAULT '',
  `audit_route` varchar(128) NOT NULL DEFAULT '',
  `audit_uri` varchar(2048) NOT NULL DEFAULT '',
  `audit_http_code` smallint(5) unsigned NOT NULL DEFAULT '0',
  `audit_payload` mediumtext,
  PRIMARY KEY (`audit_id`),
  KEY `idx_admin_time` (`admin_id`,`audit_time`),
  KEY `idx_time` (`audit_time`),
  KEY `idx_route` (`audit_route`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台操作审计';";
    $sql .= "\r";
}
// AI 封面：备份原海报字段（与 install.sql 一致；存量库通过升级脚本一次性补齐）
if (!empty($col_list[$pre . 'vod']) && empty($col_list[$pre . 'vod']['vod_pic_original'])) {
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_pic_original` varchar(1024) NOT NULL DEFAULT '' COMMENT 'AI封面前备份原海报' AFTER `vod_pic_slide`;";
    $sql .= "\r";
}
// 直播分类表
if(empty($col_list[$pre.'live_category'])){
    $sql .= "CREATE TABLE `{$pre}live_category` (";
    $sql .= "`cate_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',";
    $sql .= "`cate_name` varchar(100) NOT NULL DEFAULT '' COMMENT '分类名称',";
    $sql .= "`cate_en` varchar(100) NOT NULL DEFAULT '' COMMENT '分类英文名',";
    $sql .= "`cate_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '分类图片',";
    $sql .= "`cate_sort` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',";
    $sql .= "`cate_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',";
    $sql .= "`cate_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',";
    $sql .= "`cate_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',";
    $sql .= "PRIMARY KEY (`cate_id`),";
    $sql .= "KEY `cate_sort` (`cate_sort`),";
    $sql .= "KEY `cate_status` (`cate_status`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直播分类表';";
    $sql .= "\r";
}
// 直播频道表
if(empty($col_list[$pre.'live'])){
    $sql .= "CREATE TABLE `{$pre}live` (";
    $sql .= "`live_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '直播ID',";
    $sql .= "`cate_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',";
    $sql .= "`live_name` varchar(255) NOT NULL DEFAULT '' COMMENT '频道名称',";
    $sql .= "`live_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '频道副标题',";
    $sql .= "`live_en` varchar(255) NOT NULL DEFAULT '' COMMENT '频道英文名',";
    $sql .= "`live_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '频道图片/LOGO',";
    $sql .= "`live_url` text COMMENT '播放地址',";
    $sql .= "`live_play_from` varchar(255) NOT NULL DEFAULT 'hls' COMMENT '播放来源/协议',";
    $sql .= "`live_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',";
    $sql .= "`live_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '锁定 0否 1是',";
    $sql .= "`live_sort` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',";
    $sql .= "`live_level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '推荐等级',";
    $sql .= "`live_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总点击',";
    $sql .= "`live_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '日点击',";
    $sql .= "`live_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '周点击',";
    $sql .= "`live_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '月点击',";
    $sql .= "`live_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',";
    $sql .= "`live_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',";
    $sql .= "`live_time_hits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最近点击时间',";
    $sql .= "`live_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简要描述',";
    $sql .= "`live_content` text COMMENT '频道介绍',";
    $sql .= "PRIMARY KEY (`live_id`),";
    $sql .= "KEY `cate_id` (`cate_id`),";
    $sql .= "KEY `live_name` (`live_name`(100)),";
    $sql .= "KEY `live_status` (`live_status`),";
    $sql .= "KEY `live_sort` (`live_sort`),";
    $sql .= "KEY `live_level` (`live_level`),";
    $sql .= "KEY `live_hits` (`live_hits`),";
    $sql .= "KEY `live_time` (`live_time`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直播频道表';";
    $sql .= "\r";
}
// 插入预设直播分类数据（冪等：本次新建的表必空需插；已存在的表才安全查 count，避免升级时表尚未创建导致查询异常中断）
$liveCateNeedInsert = false;
if(empty($col_list[$pre.'live_category'])){
    // 本次升级新建该表，必空，需插预设
    $liveCateNeedInsert = true;
}else{
    $live_cate_count = \think\Db::name('live_category')->count();
    if (empty($live_cate_count)) {
        $liveCateNeedInsert = true;
    }
}
if($liveCateNeedInsert){
    $sql .= "INSERT INTO `{$pre}live_category` (`cate_name`,`cate_en`,`cate_sort`,`cate_status`,`cate_time_add`,`cate_time`) VALUES ";
    $sql .= "('央视频道','cctv',0,1,0,0),";
    $sql .= "('卫视频道','wstv',1,1,0,0),";
    $sql .= "('地方频道','local',2,1,0,0),";
    $sql .= "('港澳台','hktw',3,1,0,0);";
    $sql .= "\r";
}
// 插入预设直播数据(CCTV)（冪等：本次新建的表必空需插；已存在的表才安全查 count，避免升级时表尚未创建导致查询异常中断）
$liveNeedInsert = false;
if(empty($col_list[$pre.'live'])){
    // 本次升级新建该表，必空，需插预设
    $liveNeedInsert = true;
}else{
    $live_count = \think\Db::name('live')->count();
    if (empty($live_count)) {
        $liveNeedInsert = true;
    }
}
if($liveNeedInsert){
    $sql .= "INSERT INTO `{$pre}live` (`cate_id`,`live_name`,`live_en`,`live_url`,`live_play_from`,`live_status`,`live_sort`,`live_time_add`,`live_time`,`live_blurb`) VALUES ";
    $sql .= "(1,'CCTV-1 综合','cctv1','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv1hd.m3u8','hls',1,120,0,0,'CCTV-1 综合频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-2 财经','cctv2','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv2hd.m3u8','hls',1,119,0,0,'CCTV-2 财经频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-3 综艺','cctv3','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv3hd.m3u8','hls',1,118,0,0,'CCTV-3 综艺频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-4 中文国际','cctv4','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv4hd.m3u8','hls',1,117,0,0,'CCTV-4 中文国际频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-5 体育','cctv5','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv5hd.m3u8','hls',1,116,0,0,'CCTV-5 体育频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-5+ 体育赛事','cctv5p','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv5phd.m3u8','hls',1,115,0,0,'CCTV-5+ 体育赛事频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-6 电影','cctv6','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv6hd.m3u8','hls',1,114,0,0,'CCTV-6 电影频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-7 国防军事','cctv7','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv7hd.m3u8','hls',1,113,0,0,'CCTV-7 国防军事频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-8 电视剧','cctv8','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv8hd.m3u8','hls',1,112,0,0,'CCTV-8 电视剧频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-9 纪录','cctv9','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv9hd.m3u8','hls',1,111,0,0,'CCTV-9 纪录频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-10 科教','cctv10','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv10hd.m3u8','hls',1,110,0,0,'CCTV-10 科教频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-11 戏曲','cctv11','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv11hd.m3u8','hls',1,109,0,0,'CCTV-11 戏曲频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-12 社会与法','cctv12','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv12hd.m3u8','hls',1,108,0,0,'CCTV-12 社会与法频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-13 新闻','cctv13','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv13hd.m3u8','hls',1,107,0,0,'CCTV-13 新闻频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-14 少儿','cctv14','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv14hd.m3u8','hls',1,106,0,0,'CCTV-14 少儿频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-15 音乐','cctv15','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv15hd.m3u8','hls',1,105,0,0,'CCTV-15 音乐频道 中央电视台官方直播'),";
    $sql .= "(1,'CCTV-17 农业农村','cctv17','HD\$https://pili-live-hls.cntv.myqcloud.com/live/cctv17hd.m3u8','hls',1,104,0,0,'CCTV-17 农业农村频道 中央电视台官方直播');";
    $sql .= "\r";
}

// 视频审核：驳回理由字段（vod_status: 0待审 1已审 2驳回）
if (!empty($col_list[$pre . 'vod']) && empty($col_list[$pre . 'vod']['vod_audit_remark'])) {
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_audit_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '审核备注(驳回理由)' AFTER `vod_status`;";
    $sql .= "\r";
}
// 视频审核自动规则表
if (empty($col_list[$pre . 'vod_audit_rule'])) {
    $sql .= "CREATE TABLE `{$pre}vod_audit_rule` (";
    $sql .= "`rule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',";
    $sql .= "`rule_type` varchar(20) NOT NULL DEFAULT 'title_keyword' COMMENT 'title_keyword|pic_empty|pic_invalid',";
    $sql .= "`rule_pattern` varchar(500) NOT NULL DEFAULT '' COMMENT '关键词(每行或|分隔)',";
    $sql .= "`rule_action` tinyint(1) unsigned NOT NULL DEFAULT '2' COMMENT '0待审 1通过 2驳回',";
    $sql .= "`rule_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '命中时写入的审核备注',";
    $sql .= "`rule_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '0禁用 1启用',";
    $sql .= "`rule_sort` int(10) NOT NULL DEFAULT '0',";
    $sql .= "`rule_time_add` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "`rule_time` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "PRIMARY KEY (`rule_id`),";
    $sql .= "KEY `rule_status` (`rule_status`),";
    $sql .= "KEY `rule_sort` (`rule_sort`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频审核自动规则';";
    $sql .= "\r";
}
// 监控-分钟级时序指标表
if (empty($col_list[$pre . 'monitor_metric_min'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_metric_min` (";
    $sql .= "`metric_key` varchar(64) NOT NULL COMMENT '指标键，含维度：sys.cpu.pct / sys.disk.used_pct|/ / http.lat.b3',";
    $sql .= "`stat_min` int(10) unsigned NOT NULL COMMENT '分钟起点 UNIX（floor(ts/60)*60）',";
    $sql .= "`metric_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1=gauge 2=counter',";
    $sql .= "`metric_value` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '指标值',";
    $sql .= "`updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '写入时间 UNIX',";
    $sql .= "PRIMARY KEY (`metric_key`,`stat_min`),";
    $sql .= "KEY `idx_min` (`stat_min`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='监控-分钟级时序指标';";
    $sql .= "\r";
}
// 监控-小时级时序指标表
if (empty($col_list[$pre . 'monitor_metric_hour'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_metric_hour` (";
    $sql .= "`metric_key` varchar(64) NOT NULL COMMENT '指标键',";
    $sql .= "`stat_hour` int(10) unsigned NOT NULL COMMENT '整点起点 UNIX',";
    $sql .= "`metric_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1=gauge 2=counter',";
    $sql .= "`val_avg` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '该小时均值',";
    $sql .= "`val_max` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '该小时峰值',";
    $sql .= "`val_min` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '该小时谷值',";
    $sql .= "`val_sum` decimal(20,4) NOT NULL DEFAULT '0.0000' COMMENT '该小时总和（counter 用）',";
    $sql .= "`sample_cnt` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '该小时实际落库的分钟数（<60 表示 cron 有漏跑）',";
    $sql .= "`updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '写入时间 UNIX',";
    $sql .= "PRIMARY KEY (`metric_key`,`stat_hour`),";
    $sql .= "KEY `idx_hour` (`stat_hour`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='监控-小时级时序指标';";
    $sql .= "\r";
}
// 监控-运行期状态表
if (empty($col_list[$pre . 'monitor_state'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_state` (";
    $sql .= "`state_key` varchar(64) NOT NULL COMMENT '状态键',";
    $sql .= "`state_num` bigint(20) NOT NULL DEFAULT '0' COMMENT '数值槽（时间戳/累计值/计数器），用于原子 CAS',";
    $sql .= "`state_val` varchar(1024) NOT NULL DEFAULT '' COMMENT '文本槽（JSON）',";
    $sql .= "`updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '写入时间 UNIX',";
    $sql .= "PRIMARY KEY (`state_key`)";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='监控-运行期状态（cron 心跳/due-gate/counter 基准/告警 pending）';";
    $sql .= "\r";
}
// 监控-告警规则表
if (empty($col_list[$pre . 'monitor_alert_rule'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_alert_rule` (";
    $sql .= "`rule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名称',";
    $sql .= "`rule_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0停用 1启用',";
    $sql .= "`rule_source` varchar(16) NOT NULL DEFAULT 'metric' COMMENT '数据源 metric|analytics',";
    $sql .= "`rule_metric` varchar(64) NOT NULL DEFAULT '' COMMENT '指标键',";
    $sql .= "`rule_agg` varchar(8) NOT NULL DEFAULT 'avg' COMMENT 'avg|max|min|sum|last|p95',";
    $sql .= "`rule_window_min` smallint(5) unsigned NOT NULL DEFAULT '5' COMMENT '评估窗口(分钟)',";
    $sql .= "`rule_op` varchar(4) NOT NULL DEFAULT 'gt' COMMENT 'gt|gte|lt|lte',";
    $sql .= "`rule_threshold` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '阈值',";
    $sql .= "`rule_for_min` smallint(5) unsigned NOT NULL DEFAULT '3' COMMENT '持续N分钟才触发',";
    $sql .= "`rule_severity` tinyint(1) unsigned NOT NULL DEFAULT '2' COMMENT '1提示 2警告 3严重',";
    $sql .= "`rule_silence_min` smallint(5) unsigned NOT NULL DEFAULT '30' COMMENT '同一事件重复通知的最小间隔(分钟)',";
    $sql .= "`rule_recover_min` smallint(5) unsigned NOT NULL DEFAULT '3' COMMENT '连续N分钟不满足才判恢复',";
    $sql .= "`rule_channels` varchar(255) NOT NULL DEFAULT '' COMMENT '逗号分隔 notify,email,webhook,telegram,dingtalk,wecom,serverchan',";
    $sql .= "`rule_detect_mode` varchar(16) NOT NULL DEFAULT 'threshold' COMMENT 'threshold|yoy|mom|zscore|zerodrop',";
    $sql .= "`rule_detect_param` varchar(500) NOT NULL DEFAULT '' COMMENT 'JSON 检测参数',";
    $sql .= "`rule_time_add` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "`rule_time` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "PRIMARY KEY (`rule_id`),";
    $sql .= "KEY `idx_status_source` (`rule_status`,`rule_source`)";
    $sql .= ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='监控-告警规则';";
    $sql .= "\r";
    // 推荐规则种子。只在建表时插入 —— 表已存在就跳过，天然幂等，
    // 绝不会覆盖站长后来调整过的阈值。默认全部停用（rule_status=0）：
    // 阈值高度依赖具体机器配置，强行默认启用只会制造噪音。
    $sql .= "INSERT INTO `{$pre}monitor_alert_rule` (`rule_name`,`rule_status`,`rule_source`,`rule_metric`,`rule_agg`,`rule_window_min`,`rule_op`,`rule_threshold`,`rule_for_min`,`rule_severity`,`rule_silence_min`,`rule_recover_min`,`rule_channels`,`rule_detect_mode`) VALUES ";
    $sql .= "('CPU 持续高负载',0,'metric','sys.cpu.pct','avg',5,'gt',85.0000,5,2,30,3,'notify','threshold'),";
    $sql .= "('内存即将耗尽',0,'metric','sys.mem.used_pct','avg',5,'gt',90.0000,5,3,30,3,'notify','threshold'),";
    $sql .= "('磁盘即将写满',0,'metric','sys.disk.used_pct|/','last',1,'gt',90.0000,1,3,60,3,'notify','threshold'),";
    $sql .= "('5xx 错误突增',0,'metric','http.5xx','sum',5,'gt',20.0000,2,3,15,3,'notify','threshold'),";
    $sql .= "('P95 延迟劣化',0,'metric','http.lat','p95',10,'gt',2000.0000,5,2,30,3,'notify','threshold'),";
    $sql .= "('MySQL 连接数过高',0,'metric','db.threads_connected','max',5,'gt',100.0000,3,2,30,3,'notify','threshold'),";
    $sql .= "('慢查询激增',0,'metric','db.slow_queries','sum',10,'gt',50.0000,5,2,30,3,'notify','threshold'),";
    $sql .= "('PHP-FPM 队列积压',0,'metric','php.fpm.queue','max',5,'gt',20.0000,3,2,30,3,'notify','threshold');";
    $sql .= "\r";
}
// 监控-告警事件表
// uk_active(event_fingerprint, event_end_ts) 是本设计的关键：
// 活跃事件 end_ts=0，所以同一指纹最多只能有一条活跃事件 —— 由数据库而非 PHP 保证。
// 就算 cron 叠跑或站长手动重打，也绝不可能开出两个重复告警。
if (empty($col_list[$pre . 'monitor_alert_event'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_alert_event` (";
    $sql .= "`event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`rule_id` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "`rule_name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则名快照，规则删除后历史仍可读',";
    $sql .= "`event_metric` varchar(64) NOT NULL DEFAULT '',";
    $sql .= "`event_severity` tinyint(1) unsigned NOT NULL DEFAULT '2',";
    $sql .= "`event_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1触发中 2已恢复 3已确认',";
    $sql .= "`event_value` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '触发时的实际值',";
    $sql .= "`event_baseline` decimal(18,4) NOT NULL DEFAULT '0.0000' COMMENT '基线值(数据异常检测用)',";
    $sql .= "`event_threshold` decimal(18,4) NOT NULL DEFAULT '0.0000',";
    $sql .= "`event_summary` varchar(500) NOT NULL DEFAULT '',";
    $sql .= "`event_fingerprint` char(32) NOT NULL DEFAULT '' COMMENT 'md5(rule_id|metric|dim)',";
    $sql .= "`event_start_ts` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "`event_last_ts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最近一次仍满足条件',";
    $sql .= "`event_end_ts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '0=活跃中；>0=恢复时间',";
    $sql .= "`event_notify_ts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上次外发通知时间',";
    $sql .= "`event_notify_cnt` smallint(5) unsigned NOT NULL DEFAULT '0',";
    $sql .= "`event_notify_result` varchar(500) NOT NULL DEFAULT '',";
    $sql .= "PRIMARY KEY (`event_id`),";
    $sql .= "UNIQUE KEY `uk_active` (`event_fingerprint`,`event_end_ts`),";
    $sql .= "KEY `idx_rule_start` (`rule_id`,`event_start_ts`),";
    $sql .= "KEY `idx_status_start` (`event_status`,`event_start_ts`)";
    $sql .= ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='监控-告警事件';";
    $sql .= "\r";
}
// 监控-异常访问表（IP × 分钟聚合）
// 只落库「已达可疑阈值」的 IP：正常 IP 一行都不写。
if (empty($col_list[$pre . 'monitor_abnormal_access'])) {
    $sql .= "CREATE TABLE `{$pre}monitor_abnormal_access` (";
    $sql .= "`access_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,";
    $sql .= "`access_ip` varchar(45) NOT NULL DEFAULT '' COMMENT '来源IP，__overflow 表示超出追踪基数的聚合项',";
    $sql .= "`stat_min` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分钟起点 UNIX',";
    $sql .= "`hit_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '该分钟该IP总请求数',";
    $sql .= "`err4_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '4xx 次数',";
    $sql .= "`err5_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '5xx 次数',";
    $sql .= "`scan_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '命中扫描路径特征次数',";
    $sql .= "`bad_ua_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'UA为空或命中扫描器特征次数',";
    $sql .= "`blocked_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '被防爬虫限流拦截(429)次数',";
    $sql .= "`risk_score` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '风险分 0-100',";
    $sql .= "`access_level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0低 1中 2高',";
    $sql .= "`last_ua` varchar(255) NOT NULL DEFAULT '',";
    $sql .= "`last_path` varchar(255) NOT NULL DEFAULT '',";
    $sql .= "`updated_at` int(10) unsigned NOT NULL DEFAULT '0',";
    $sql .= "PRIMARY KEY (`access_id`),";
    $sql .= "UNIQUE KEY `uk_ip_min` (`access_ip`,`stat_min`),";
    $sql .= "KEY `idx_min_level` (`stat_min`,`access_level`),";
    $sql .= "KEY `idx_score` (`risk_score`)";
    $sql .= ") ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='监控-异常访问(IP×分钟聚合)';";
    $sql .= "\r";
    // 异常访问的推荐规则。用的是 sec.* 派生指标 —— 由 AbnormalAccessDetector 写回
    // metric_min，于是完全复用告警引擎，一行新的告警逻辑都不用写。
    $sql .= "INSERT INTO `{$pre}monitor_alert_rule` (`rule_name`,`rule_status`,`rule_source`,`rule_metric`,`rule_agg`,`rule_window_min`,`rule_op`,`rule_threshold`,`rule_for_min`,`rule_severity`,`rule_silence_min`,`rule_recover_min`,`rule_channels`,`rule_detect_mode`) VALUES ";
    $sql .= "('CC 攻击嫌疑',0,'metric','sec.cc_max_hits','max',3,'gt',500.0000,0,3,15,3,'notify','threshold'),";
    $sql .= "('扫描器活动',0,'metric','sec.scan_hits','sum',10,'gt',50.0000,0,2,60,3,'notify','threshold'),";
    $sql .= "('高危 IP 增多',0,'metric','sec.abnormal_ip_high','max',5,'gt',5.0000,2,2,30,3,'notify','threshold'),";
    $sql .= "('4xx 错误突增',0,'metric','http.4xx','sum',5,'gt',500.0000,2,2,30,3,'notify','threshold');";
    $sql .= "\r";
    // 运营数据异常检测的推荐规则。rule_source='analytics' 走 AnalyticsAnomaly 取数与判定，
    // 其余（触发/静默/恢复/通知/熔断/预算）完全复用同一套告警引擎。
    $sql .= "INSERT INTO `{$pre}monitor_alert_rule` (`rule_name`,`rule_status`,`rule_source`,`rule_metric`,`rule_agg`,`rule_window_min`,`rule_op`,`rule_threshold`,`rule_for_min`,`rule_severity`,`rule_silence_min`,`rule_recover_min`,`rule_channels`,`rule_detect_mode`,`rule_detect_param`) VALUES ";
    $sql .= "('订单掉零',0,'analytics','analytics.order_cnt','last',60,'lt',0.0000,0,3,120,60,'notify','zerodrop','{\"baseline_days\":14,\"min_sample\":7,\"min_abs\":3}'),";
    $sql .= "('充值金额掉零',0,'analytics','analytics.recharge_amount','last',1440,'lt',0.0000,0,3,720,720,'notify','zerodrop','{\"baseline_days\":14,\"min_sample\":7,\"min_abs\":10}'),";
    $sql .= "('订单量异常下滑',0,'analytics','analytics.order_cnt','last',60,'lt',0.0000,0,2,120,60,'notify','zscore','{\"k\":3,\"baseline_days\":14,\"min_sample\":7,\"min_abs\":3}'),";
    $sql .= "('PV 异常暴跌',0,'analytics','analytics.pv','last',60,'lt',0.0000,0,2,120,60,'notify','zscore','{\"k\":3,\"baseline_days\":14,\"min_sample\":7,\"min_abs\":50}'),";
    $sql .= "('PV 异常暴涨',0,'analytics','analytics.pv','last',60,'gt',0.0000,0,1,120,60,'notify','zscore','{\"k\":4,\"baseline_days\":14,\"min_sample\":7,\"min_abs\":50}'),";
    $sql .= "('UV 异常暴跌',0,'analytics','analytics.uv','last',60,'lt',0.0000,0,2,120,60,'notify','zscore','{\"k\":3,\"baseline_days\":14,\"min_sample\":7,\"min_abs\":30}'),";
    $sql .= "('跳出率飙升',0,'analytics','analytics.bounce_rate','last',1440,'gt',0.0000,0,2,720,720,'notify','zscore','{\"k\":3,\"baseline_days\":14,\"min_sample\":7,\"min_abs\":10}');";
    $sql .= "\r";
}
// 视频定时上架时间（Unix 时间戳；vod_pubdate 仍为上映日期元数据）
if (!empty($col_list[$pre . 'vod']) && empty($col_list[$pre . 'vod']['vod_publish_time'])) {
    $sql .= "ALTER TABLE `{$pre}vod` ADD `vod_publish_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '定时上架时间戳' AFTER `vod_pubdate`;";
    $sql .= "\r";
}
// publishDue 定时任务：vod_publish_time>0 高选择性命中，避免大表全扫
if (!empty($col_list[$pre . 'vod'])) {
    $index_exists = \think\Db::query("SHOW INDEX FROM `{$pre}vod` WHERE Key_name = 'vod_publish_time'");
    if (empty($index_exists)) {
        $sql .= "ALTER TABLE `{$pre}vod` ADD INDEX `vod_publish_time` (`vod_publish_time`);";
        $sql .= "\r";
    }
}

// AI 搜索反滥用配置注入（漏洞 12）：require_login / anon_captcha_after /
// daily_budget / llm_call_cap / circuit_fail_threshold / circuit_hold_seconds，
// 以及顶层 trusted_proxies。幂等：只补缺失键，不覆盖站长已设值。
{
    $file = APP_PATH . 'extra/maccms.php';
    if (is_file($file)) {
        @chmod($file, 0777);
        $config = config('maccms');
        if (is_array($config)) {
            $changed = false;
            if (!isset($config['ai_search']) || !is_array($config['ai_search'])) {
                $config['ai_search'] = [];
            }
            $aiFill = [
                'require_login' => '1',
                'anon_captcha_after' => '10',
                'daily_budget' => '500',
                'llm_call_cap' => '3',
                'circuit_fail_threshold' => '8',
                'circuit_hold_seconds' => '1800',
            ];
            foreach ($aiFill as $k => $v) {
                if (!isset($config['ai_search'][$k])) {
                    $config['ai_search'][$k] = $v;
                    $changed = true;
                }
            }
            if (!isset($config['trusted_proxies'])) {
                $config['trusted_proxies'] = '';
                $changed = true;
            }
            // PWA Web Push 配置注入（幂等）：enable / vapid_public / vapid_private /
            // vapid_subject。密钥留空由站长在“推送设置”页一键生成，不自动填充。
            if (!isset($config['push']) || !is_array($config['push'])) {
                $config['push'] = [];
                $changed = true;
            }
            $pushFill = [
                'enable'        => '0',
                'vapid_public'  => '',
                'vapid_private' => '',
                'vapid_subject' => '',
            ];
            foreach ($pushFill as $k => $v) {
                if (!isset($config['push'][$k])) {
                    $config['push'][$k] = $v;
                    $changed = true;
                }
            }
            if ($changed) {
                mac_arr2file($file, $config);
            }
        }
    }
}

// 行为分析配置注入（PR-1）：server_track / track_region。
// 幂等：只补缺失键，绝不覆盖站长已设值 —— extra/maccms.php 是运行期数据。
// 默认全关：服务端埋点每个 PV 一次 INSERT，不能在升级后擅自给存量站加写压力。
// 监控与告警配置注入：enabled / cron_token / 请求埋点开关 / 保留期 等。
// 幂等：只补缺失键，不覆盖站长已设值；cron_token 仅在缺失或过短时生成
// （每次升级都换 token 会让站长已配置的 crontab 直接失效）。
{
    $file = APP_PATH . 'extra/maccms.php';
    if (is_file($file)) {
        @chmod($file, 0777);
        $config = config('maccms');
        if (is_array($config)) {
            $changed = false;
            if (!isset($config['analytics']) || !is_array($config['analytics'])) {
                $config['analytics'] = [];
                $changed = true;
            }
            $analyticsFill = [
                'server_track' => '0',
                'track_region' => '0',
                'quality_fresh_halflife_days' => '30',
                'profile_window_days' => '30',
                'quality_rank_enabled' => '0',
            ];
            foreach ($analyticsFill as $k => $v) {
                if (!isset($config['analytics'][$k])) {
                    $config['analytics'][$k] = $v;
                    $changed = true;
                }
            }
            if (!isset($config['analytics']['quality_weights'])) {
                $config['analytics']['quality_weights'] = [
                    'behavior' => '0.35',
                    'interact' => '0.30',
                    'complete' => '0.20',
                    'fresh'    => '0.15',
                ];
                $changed = true;
            }
            if (!isset($config['monitor']) || !is_array($config['monitor'])) {
                $config['monitor'] = [];
                $changed = true;
            }
            $monitorFill = [
                'enabled'               => '1',
                'req_metrics_enabled'   => '1',
                'req_sample_rate'       => '100',
                'slow_ms'               => '1000',
                'allow_shell'           => '0',
                'disk_mounts'           => '',
                'retain_min_days'       => '3',
                'retain_hour_days'      => '90',
                'heartbeat_url'         => '',
                'notify_user_ids'       => '',
                'alert_emails'          => '',
                'notify_budget_hour'    => '20',
                'notify_max_per_run'    => '5',
                'notify_time_budget_ms' => '8000',
                'webhook_allow_private' => '0',
                'access_track_enabled'  => '0',
                'access_cc_threshold'   => '120',
                'access_err4_threshold' => '20',
                'access_track_max_ip'   => '300',
                'retain_access_days'    => '30',
                'ban_whitelist'         => '',
                'webhook_url'           => '',
                'webhook_secret'        => '',
                'telegram_token'        => '',
                'telegram_chat_id'      => '',
                'dingtalk_token'        => '',
                'dingtalk_secret'       => '',
                'wecom_key'             => '',
                'serverchan_key'        => '',
            ];
            foreach ($monitorFill as $m_k => $m_v) {
                if (!isset($config['monitor'][$m_k])) {
                    $config['monitor'][$m_k] = $m_v;
                    $changed = true;
                }
            }
            if (!isset($config['monitor']['cron_token'])
                || strlen(trim((string)$config['monitor']['cron_token'])) < 16) {
                $config['monitor']['cron_token'] = mac_get_rndstr(32);
                $changed = true;
            }
            // AI 内容标注配置注入（PR-3）。幂等：只补缺失键，绝不覆盖站长已设值。
            // 默认关闭：开着就会烧 token，必须由站长显式开启。
            // 合并进本块的单次读改写：若独立成块，会基于进程启动时的旧 config('maccms')
            // 整文件回写，覆盖上面 analytics/monitor 本次刚补入的键（mac_arr2file 不刷新
            // config() 内存缓存）。与 install/controller/Index.php 的单次写盘保持一致。
            if (!isset($config['ai_content']) || !is_array($config['ai_content'])) {
                $config['ai_content'] = [];
                $changed = true;
            }
            $aiContentFill = [
                'enabled' => '0',
                'use_ai_search_credentials' => '0',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_base' => '',
                'api_key' => '',
                'timeout' => '30',
                'max_tokens' => '800',
                'batch_size' => '20',
                'auto_adopt_empty' => '0',
            ];
            foreach ($aiContentFill as $ai_content_k => $ai_content_v) {
                if (!isset($config['ai_content'][$ai_content_k])) {
                    $config['ai_content'][$ai_content_k] = $ai_content_v;
                    $changed = true;
                }
            }
            if ($changed) {
                mac_arr2file($file, $config);
            }
        }
    }
}
