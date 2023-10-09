-- ----------------------------
-- Table structure for mac_actor
-- ----------------------------
DROP TABLE IF EXISTS `mac_actor`;
CREATE TABLE `mac_actor` (
  `actor_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `actor_name` varchar(255) NOT NULL DEFAULT '',
  `actor_en` varchar(255) NOT NULL DEFAULT '',
  `actor_alias` varchar(255) NOT NULL DEFAULT '' ,
  `actor_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `actor_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `actor_letter` char(1) NOT NULL DEFAULT '' ,
  `actor_sex` char(1) NOT NULL DEFAULT '',
  `actor_color` varchar(6) NOT NULL DEFAULT '' ,
  `actor_pic` varchar(1024) NOT NULL DEFAULT '' ,
  `actor_blurb` varchar(255) NOT NULL DEFAULT '',
  `actor_remarks` varchar(100) NOT NULL DEFAULT '' ,
  `actor_area` varchar(20) NOT NULL DEFAULT '',
  `actor_height` varchar(10) NOT NULL DEFAULT '' ,
  `actor_weight` varchar(10) NOT NULL DEFAULT '' ,
  `actor_birthday` varchar(10) NOT NULL DEFAULT '' ,
  `actor_birtharea` varchar(20) NOT NULL DEFAULT '',
  `actor_blood` varchar(10) NOT NULL DEFAULT '' ,
  `actor_starsign` varchar(10) NOT NULL DEFAULT '',
  `actor_school` varchar(20) NOT NULL DEFAULT '',
  `actor_works` varchar(255) NOT NULL DEFAULT '',
  `actor_tag` varchar(255) NOT NULL DEFAULT '',
  `actor_class` varchar(255) NOT NULL DEFAULT '',
  `actor_level` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `actor_time` int(10) unsigned NOT NULL DEFAULT '0',
  `actor_time_add` int(10) unsigned NOT NULL DEFAULT '0',
  `actor_time_hits` int(10) unsigned NOT NULL DEFAULT '0',
  `actor_time_make` int(10) unsigned NOT NULL DEFAULT '0',
  `actor_hits` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0',
  `actor_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_up` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_down` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_tpl` varchar(30) NOT NULL DEFAULT '',
  `actor_jumpurl` varchar(150) NOT NULL DEFAULT '',
  `actor_content` text NOT NULL ,
  PRIMARY KEY (`actor_id`),
  KEY `type_id` (`type_id`) USING BTREE,
  KEY `type_id_1` (`type_id_1`) USING BTREE,
  KEY `actor_name` (`actor_name`) USING BTREE,
  KEY `actor_en` (`actor_en`) USING BTREE,
  KEY `actor_letter` (`actor_letter`) USING BTREE,
  KEY `actor_level` (`actor_level`) USING BTREE,
  KEY `actor_time` (`actor_time`) USING BTREE,
  KEY `actor_time_add` (`actor_time_add`) USING BTREE,
  KEY `actor_sex` (`actor_sex`),
  KEY `actor_area` (`actor_area`),
  KEY `actor_up` (`actor_up`),
  KEY `actor_down` (`actor_down`),
  KEY `actor_tag` (`actor_tag`),
  KEY `actor_class` (`actor_class`),
  KEY `actor_score` (`actor_score`),
  KEY `actor_score_all` (`actor_score_all`),
  KEY `actor_score_num` (`actor_score_num`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for mac_admin
-- ----------------------------
DROP TABLE IF EXISTS `mac_admin`;
CREATE TABLE `mac_admin` (
  `admin_id` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
  `admin_name` varchar(30) NOT NULL DEFAULT '',
  `admin_pwd` char(32) NOT NULL DEFAULT '',
  `admin_random` char(32) NOT NULL DEFAULT '',
  `admin_status` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `admin_auth` text NOT NULL,
  `admin_login_time` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_login_ip` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_login_num` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_last_login_time` int(10) unsigned NOT NULL DEFAULT '0',
  `admin_last_login_ip` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`admin_id`),
  KEY `admin_name` (`admin_name`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_annex
-- ----------------------------
DROP TABLE IF EXISTS `mac_annex`;
CREATE TABLE `mac_annex` (
  `annex_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `annex_time` int(10) unsigned NOT NULL DEFAULT '0',
  `annex_file` varchar(255) NOT NULL DEFAULT '',
  `annex_size` int(10) unsigned NOT NULL DEFAULT '0',
  `annex_type` varchar(8) NOT NULL DEFAULT '',
  PRIMARY KEY (`annex_id`),
  KEY `annex_time` (`annex_time`),
  KEY `annex_file` (`annex_file`),
  KEY `annex_type` (`annex_type`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for mac_art
-- ----------------------------
DROP TABLE IF EXISTS `mac_art`;
CREATE TABLE `mac_art` (
  `art_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `art_name` varchar(255) NOT NULL DEFAULT '' ,
  `art_sub` varchar(255) NOT NULL DEFAULT '' ,
  `art_en` varchar(255) NOT NULL DEFAULT '' ,
  `art_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `art_letter` char(1) NOT NULL DEFAULT '' ,
  `art_color` varchar(6) NOT NULL DEFAULT '' ,
  `art_from` varchar(30) NOT NULL DEFAULT '' ,
  `art_author` varchar(30) NOT NULL DEFAULT '' ,
  `art_tag` varchar(100) NOT NULL DEFAULT '' ,
  `art_class` varchar(255) NOT NULL DEFAULT '' ,
  `art_pic` varchar(1024) NOT NULL DEFAULT '' ,
  `art_pic_thumb` varchar(1024) NOT NULL DEFAULT '' ,
  `art_pic_slide` varchar(1024) NOT NULL DEFAULT '' ,
  `art_pic_screenshot` text,
  `art_blurb` varchar(255) NOT NULL DEFAULT '' ,
  `art_remarks` varchar(100) NOT NULL DEFAULT '' ,
  `art_jumpurl` varchar(150) NOT NULL DEFAULT '' ,
  `art_tpl` varchar(30) NOT NULL DEFAULT '' ,
  `art_level` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `art_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `art_points` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `art_points_detail` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `art_up` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_down` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `art_time_add` int(10) unsigned NOT NULL DEFAULT '0' ,
  `art_time_hits` int(10) unsigned NOT NULL DEFAULT '0' ,
  `art_time_make` int(10) unsigned NOT NULL DEFAULT '0' ,
  `art_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' ,
  `art_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `art_rel_art` varchar(255) NOT NULL DEFAULT '' ,
  `art_rel_vod` varchar(255) NOT NULL DEFAULT '' ,
  `art_pwd` varchar(10) NOT NULL DEFAULT '' ,
  `art_pwd_url` varchar(255) NOT NULL DEFAULT '' ,
  `art_title` mediumtext NOT NULL ,
  `art_note` mediumtext NOT NULL ,
  `art_content` mediumtext NOT NULL ,
  PRIMARY KEY (`art_id`),
  KEY `type_id` (`type_id`) USING BTREE,
  KEY `type_id_1` (`type_id_1`) USING BTREE,
  KEY `art_level` (`art_level`) USING BTREE,
  KEY `art_hits` (`art_hits`) USING BTREE,
  KEY `art_time` (`art_time`) USING BTREE,
  KEY `art_letter` (`art_letter`) USING BTREE,
  KEY `art_down` (`art_down`) USING BTREE,
  KEY `art_up` (`art_up`) USING BTREE,
  KEY `art_tag` (`art_tag`) USING BTREE,
  KEY `art_name` (`art_name`) USING BTREE,
  KEY `art_enn` (`art_en`) USING BTREE,
  KEY `art_hits_day` (`art_hits_day`) USING BTREE,
  KEY `art_hits_week` (`art_hits_week`) USING BTREE,
  KEY `art_hits_month` (`art_hits_month`) USING BTREE,
  KEY `art_time_add` (`art_time_add`) USING BTREE,
  KEY `art_time_make` (`art_time_make`) USING BTREE,
  KEY `art_lock` (`art_lock`),
  KEY `art_score` (`art_score`),
  KEY `art_score_all` (`art_score_all`),
  KEY `art_score_num` (`art_score_num`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_card
-- ----------------------------
DROP TABLE IF EXISTS `mac_card`;
CREATE TABLE `mac_card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_no` varchar(16) NOT NULL DEFAULT '' ,
  `card_pwd` varchar(8) NOT NULL DEFAULT '' ,
  `card_money` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `card_points` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `card_use_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `card_sale_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `card_add_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `card_use_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`card_id`),
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `card_add_time` (`card_add_time`) USING BTREE,
  KEY `card_use_time` (`card_use_time`) USING BTREE,
  KEY `card_no` (`card_no`),
  KEY `card_pwd` (`card_pwd`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_cash
-- ----------------------------
DROP TABLE IF EXISTS `mac_cash`;
CREATE TABLE `mac_cash` (
  `cash_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `cash_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `cash_points` smallint(6) unsigned NOT NULL DEFAULT '0',
  `cash_money` decimal(12,2) unsigned NOT NULL DEFAULT '0.00',
  `cash_bank_name` varchar(60) NOT NULL DEFAULT '',
  `cash_bank_no` varchar(30) NOT NULL DEFAULT '',
  `cash_payee_name` varchar(30) NOT NULL DEFAULT '',
  `cash_time` int(10) unsigned NOT NULL DEFAULT '0',
  `cash_time_audit` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cash_id`),
  KEY `user_id` (`user_id`),
  KEY `cash_status` (`cash_status`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for mac_cj_content
-- ----------------------------
DROP TABLE IF EXISTS `mac_cj_content`;
CREATE TABLE `mac_cj_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nodeid` int(10) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `url` char(255) NOT NULL,
  `title` char(100) NOT NULL,
  `data` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `nodeid` (`nodeid`),
  KEY `status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC ;

-- ----------------------------
-- Table structure for mac_cj_history
-- ----------------------------
DROP TABLE IF EXISTS `mac_cj_history`;
CREATE TABLE `mac_cj_history` (
  `md5` char(32) NOT NULL,
  PRIMARY KEY (`md5`),
  KEY `md5` (`md5`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_cj_node
-- ----------------------------
DROP TABLE IF EXISTS `mac_cj_node`;
CREATE TABLE `mac_cj_node` (
  `nodeid` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `lastdate` int(10) unsigned NOT NULL DEFAULT '0',
  `sourcecharset` varchar(8) NOT NULL,
  `sourcetype` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `urlpage` text NOT NULL,
  `pagesize_start` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `pagesize_end` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `page_base` char(255) NOT NULL,
  `par_num` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `url_contain` char(100) NOT NULL,
  `url_except` char(100) NOT NULL,
  `url_start` char(100) NOT NULL DEFAULT '',
  `url_end` char(100) NOT NULL DEFAULT '',
  `title_rule` char(100) NOT NULL,
  `title_html_rule` text NOT NULL,
  `type_rule` char(100) NOT NULL,
  `type_html_rule` text NOT NULL,
  `content_rule` char(100) NOT NULL,
  `content_html_rule` text NOT NULL,
  `content_page_start` char(100) NOT NULL,
  `content_page_end` char(100) NOT NULL,
  `content_page_rule` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `content_page` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `content_nextpage` char(100) NOT NULL,
  `down_attachment` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `watermark` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `coll_order` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `customize_config` text NOT NULL,
  `program_config` text NOT NULL,
  `mid` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  PRIMARY KEY (`nodeid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC ;

-- ----------------------------
-- Table structure for mac_collect
-- ----------------------------
DROP TABLE IF EXISTS `mac_collect`;
CREATE TABLE `mac_collect` (
  `collect_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `collect_name` varchar(30) NOT NULL DEFAULT '' ,
  `collect_url` varchar(255) NOT NULL DEFAULT '' ,
  `collect_type` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `collect_mid` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `collect_appid` varchar(30) NOT NULL DEFAULT '' ,
  `collect_appkey` varchar(30) NOT NULL DEFAULT '' ,
  `collect_param` varchar(100) NOT NULL DEFAULT '' ,
  `collect_filter` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `collect_filter_from` varchar(255) NOT NULL DEFAULT '' ,
  `collect_filter_year` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '采集时，过滤年份',
  `collect_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `collect_sync_pic_opt` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '同步图片选项，0-跟随全局，1-开启，2-关闭',
  PRIMARY KEY (`collect_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_comment
-- ----------------------------
DROP TABLE IF EXISTS `mac_comment`;
CREATE TABLE `mac_comment` (
  `comment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comment_mid` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `comment_rid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `comment_pid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `comment_status` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `comment_name` varchar(60) NOT NULL DEFAULT '' ,
  `comment_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `comment_time` int(10) unsigned NOT NULL DEFAULT '0',
  `comment_content` varchar(255) NOT NULL DEFAULT '',
  `comment_up` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `comment_down` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `comment_reply` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `comment_report` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`comment_id`),
  KEY `comment_mid` (`comment_mid`) USING BTREE,
  KEY `comment_rid` (`comment_rid`) USING BTREE,
  KEY `comment_time` (`comment_time`) USING BTREE,
  KEY `comment_pid` (`comment_pid`),
  KEY `user_id` (`user_id`),
  KEY `comment_reply` (`comment_reply`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_gbook
-- ----------------------------
DROP TABLE IF EXISTS `mac_gbook`;
CREATE TABLE `mac_gbook` (
  `gbook_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gbook_rid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `gbook_status` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `gbook_name` varchar(60) NOT NULL DEFAULT '' ,
  `gbook_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `gbook_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `gbook_reply_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `gbook_content` varchar(255) NOT NULL DEFAULT '' ,
  `gbook_reply` varchar(255) NOT NULL DEFAULT '' ,
  PRIMARY KEY (`gbook_id`),
  KEY `gbook_rid` (`gbook_rid`) USING BTREE,
  KEY `gbook_time` (`gbook_time`) USING BTREE,
  KEY `gbook_reply_time` (`gbook_reply_time`) USING BTREE,
  KEY `user_id` (`user_id`),
  KEY `gbook_reply` (`gbook_reply`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_group
-- ----------------------------
DROP TABLE IF EXISTS `mac_group`;
CREATE TABLE `mac_group` (
  `group_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(30) NOT NULL DEFAULT '' ,
  `group_status` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `group_type` text NOT NULL,
  `group_popedom` text NOT NULL,
  `group_points_day` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `group_points_week` smallint(6) NOT NULL DEFAULT '0' ,
  `group_points_month` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `group_points_year` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `group_points_free` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`group_id`),
  KEY `group_status` (`group_status`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 ;

INSERT INTO `mac_group` VALUES ('1', '游客', '1', ',1,6,7,8,9,10,11,12,2,13,14,15,16,3,4,5,17,18,', '{\"1\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"6\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"7\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"8\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"9\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"10\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"11\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"12\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"2\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"13\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"14\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"15\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"16\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"3\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"4\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"5\":{\"1\":\"1\",\"2\":\"2\"},\"17\":{\"1\":\"1\",\"2\":\"2\"},\"18\":{\"1\":\"1\",\"2\":\"2\"}}', '0', '0', '0', '0', '0');
INSERT INTO `mac_group` VALUES ('2', '默认会员', '1', ',1,6,7,8,9,10,11,12,2,13,14,15,16,3,4,5,17,18,', '{\"1\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"6\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"7\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"8\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"9\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"10\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"11\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"12\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"2\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"13\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"14\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"15\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"16\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"3\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"4\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"5\":{\"1\":\"1\",\"2\":\"2\"},\"17\":{\"1\":\"1\",\"2\":\"2\"},\"18\":{\"1\":\"1\",\"2\":\"2\"}}', '0', '0', '0', '0', '0');
INSERT INTO `mac_group` VALUES ('3', 'VIP会员', '1', ',1,6,7,8,9,10,11,12,2,13,14,15,16,3,4,5,17,18,', '{\"1\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"6\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"7\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"8\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"9\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"10\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"11\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"12\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"2\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"13\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"14\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"15\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"16\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"3\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"4\":{\"1\":\"1\",\"2\":\"2\",\"3\":\"3\",\"4\":\"4\",\"5\":\"5\"},\"5\":{\"1\":\"1\",\"2\":\"2\"},\"17\":{\"1\":\"1\",\"2\":\"2\"},\"18\":{\"1\":\"1\",\"2\":\"2\"}}', '10', '70', '300', '3600', '0');



-- ----------------------------
-- Table structure for mac_link
-- ----------------------------
DROP TABLE IF EXISTS `mac_link`;
CREATE TABLE `mac_link` (
  `link_id` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
  `link_type` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `link_name` varchar(60) NOT NULL DEFAULT '' ,
  `link_sort` smallint(6) NOT NULL DEFAULT '0' ,
  `link_add_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `link_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `link_url` varchar(255) NOT NULL DEFAULT '' ,
  `link_logo` varchar(255) NOT NULL DEFAULT '' ,
  PRIMARY KEY (`link_id`),
  KEY `link_sort` (`link_sort`) USING BTREE,
  KEY `link_type` (`link_type`) USING BTREE,
  KEY `link_add_time` (`link_add_time`),
  KEY `link_time` (`link_time`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_msg
-- ----------------------------
DROP TABLE IF EXISTS `mac_msg`;
CREATE TABLE `mac_msg` (
  `msg_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `msg_type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `msg_status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `msg_to` varchar(30) NOT NULL DEFAULT '',
  `msg_code` varchar(10) NOT NULL DEFAULT '',
  `msg_content` varchar(255) NOT NULL DEFAULT '',
  `msg_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`msg_id`),
  KEY `msg_code` (`msg_code`),
  KEY `msg_time` (`msg_time`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for mac_order
-- ----------------------------
DROP TABLE IF EXISTS `mac_order`;
CREATE TABLE `mac_order` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT ,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `order_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `order_code` varchar(30) NOT NULL DEFAULT '' ,
  `order_price` decimal(12,2) unsigned NOT NULL DEFAULT '0.00' ,
  `order_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `order_points` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `order_pay_type` varchar(10) NOT NULL DEFAULT '' ,
  `order_pay_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `order_remarks` varchar(100) NOT NULL DEFAULT '' ,
  PRIMARY KEY (`order_id`),
  KEY `order_code` (`order_code`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `order_time` (`order_time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_plog
-- ----------------------------
DROP TABLE IF EXISTS `mac_plog`;
CREATE TABLE `mac_plog` (
  `plog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id_1` int(10) NOT NULL DEFAULT '0',
  `plog_type` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `plog_points` smallint(6) unsigned NOT NULL DEFAULT '0',
  `plog_time` int(10) unsigned NOT NULL DEFAULT '0',
  `plog_remarks` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`plog_id`),
  KEY `user_id` (`user_id`),
  KEY `plog_type` (`plog_type`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for mac_role
-- ----------------------------
DROP TABLE IF EXISTS `mac_role`;
CREATE TABLE `mac_role` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_rid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `role_name` varchar(255) NOT NULL DEFAULT '' ,
  `role_en` varchar(255) NOT NULL DEFAULT '' ,
  `role_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `role_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `role_letter` char(1) NOT NULL DEFAULT '' ,
  `role_color` varchar(6) NOT NULL DEFAULT '' ,
  `role_actor` varchar(255) NOT NULL DEFAULT '' ,
  `role_remarks` varchar(100) NOT NULL DEFAULT '',
  `role_pic` varchar(1024) NOT NULL DEFAULT '' ,
  `role_sort` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `role_level` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `role_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `role_time_add` int(10) unsigned NOT NULL DEFAULT '0' ,
  `role_time_hits` int(10) unsigned NOT NULL DEFAULT '0' ,
  `role_time_make` int(10) unsigned NOT NULL DEFAULT '0' ,
  `role_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0',
  `role_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `role_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `role_up` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_down` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `role_tpl` varchar(30) NOT NULL DEFAULT '' ,
  `role_jumpurl` varchar(150) NOT NULL DEFAULT '' ,
  `role_content` text NOT NULL  ,
  PRIMARY KEY (`role_id`),
  KEY `role_rid` (`role_rid`),
  KEY `role_name` (`role_name`),
  KEY `role_en` (`role_en`),
  KEY `role_letter` (`role_letter`),
  KEY `role_actor` (`role_actor`),
  KEY `role_level` (`role_level`),
  KEY `role_time` (`role_time`),
  KEY `role_time_add` (`role_time_add`),
  KEY `role_score` (`role_score`),
  KEY `role_score_all` (`role_score_all`),
  KEY `role_score_num` (`role_score_num`),
  KEY `role_up` (`role_up`),
  KEY `role_down` (`role_down`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_topic
-- ----------------------------
DROP TABLE IF EXISTS `mac_topic`;
CREATE TABLE `mac_topic` (
  `topic_id` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
  `topic_name` varchar(255) NOT NULL DEFAULT '' ,
  `topic_en` varchar(255) NOT NULL DEFAULT '' ,
  `topic_sub` varchar(255) NOT NULL DEFAULT '' ,
  `topic_status` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `topic_sort` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `topic_letter` char(1) NOT NULL DEFAULT '' ,
  `topic_color` varchar(6) NOT NULL DEFAULT '' ,
  `topic_tpl` varchar(30) NOT NULL DEFAULT '' ,
  `topic_type` varchar(255) NOT NULL DEFAULT '' ,
  `topic_pic` varchar(1024) NOT NULL DEFAULT '',
  `topic_pic_thumb` varchar(1024) NOT NULL DEFAULT '',
  `topic_pic_slide` varchar(1024) NOT NULL DEFAULT '',
  `topic_key` varchar(255) NOT NULL DEFAULT '' ,
  `topic_des` varchar(255) NOT NULL DEFAULT '' ,
  `topic_title` varchar(255) NOT NULL DEFAULT '' ,
  `topic_blurb` varchar(255) NOT NULL DEFAULT '' ,
  `topic_remarks` varchar(100) NOT NULL DEFAULT '' ,
  `topic_level` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `topic_up` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_down` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' ,
  `topic_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_add` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_hits` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_make` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_tag` varchar(255) NOT NULL DEFAULT '' ,
  `topic_rel_vod` text NOT NULL,
  `topic_rel_art` text NOT NULL,
  `topic_content` text NOT NULL,
  `topic_extend` text NOT NULL,
  PRIMARY KEY (`topic_id`),
  KEY `topic_sort` (`topic_sort`) USING BTREE,
  KEY `topic_level` (`topic_level`) USING BTREE,
  KEY `topic_score` (`topic_score`) USING BTREE,
  KEY `topic_score_all` (`topic_score_all`) USING BTREE,
  KEY `topic_score_num` (`topic_score_num`) USING BTREE,
  KEY `topic_hits` (`topic_hits`) USING BTREE,
  KEY `topic_hits_day` (`topic_hits_day`) USING BTREE,
  KEY `topic_hits_week` (`topic_hits_week`) USING BTREE,
  KEY `topic_hits_month` (`topic_hits_month`) USING BTREE,
  KEY `topic_time_add` (`topic_time_add`) USING BTREE,
  KEY `topic_time` (`topic_time`) USING BTREE,
  KEY `topic_time_hits` (`topic_time_hits`) USING BTREE,
  KEY `topic_name` (`topic_name`),
  KEY `topic_en` (`topic_en`),
  KEY `topic_up` (`topic_up`),
  KEY `topic_down` (`topic_down`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_type
-- ----------------------------
DROP TABLE IF EXISTS `mac_type`;
CREATE TABLE `mac_type` (
  `type_id` smallint(6) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(60) NOT NULL DEFAULT '' ,
  `type_en` varchar(60) NOT NULL DEFAULT '' ,
  `type_sort` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `type_mid` smallint(6) unsigned NOT NULL DEFAULT '1' ,
  `type_pid` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `type_status` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `type_tpl` varchar(30) NOT NULL DEFAULT '' ,
  `type_tpl_list` varchar(30) NOT NULL DEFAULT '',
  `type_tpl_detail` varchar(30) NOT NULL DEFAULT '' ,
  `type_tpl_play` varchar(30) NOT NULL DEFAULT '' ,
  `type_tpl_down` varchar(30) NOT NULL DEFAULT '' ,
  `type_key` varchar(255) NOT NULL DEFAULT '' ,
  `type_des` varchar(255) NOT NULL DEFAULT '' ,
  `type_title` varchar(255) NOT NULL DEFAULT '' ,
  `type_union` varchar(255) NOT NULL DEFAULT '',
  `type_extend` text NOT NULL,
  `type_logo`  VARCHAR( 255 )  NOT NULL DEFAULT  '',
  `type_pic`  VARCHAR( 1024 )  NOT NULL DEFAULT  '',
  `type_jumpurl`  VARCHAR( 150 )  NOT NULL DEFAULT  '',
  PRIMARY KEY (`type_id`),
  KEY `type_sort` (`type_sort`) USING BTREE,
  KEY `type_pid` (`type_pid`) USING BTREE,
  KEY `type_name` (`type_name`),
  KEY `type_en` (`type_en`),
  KEY `type_mid` (`type_mid`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 ;


-- ----------------------------
-- Table structure for mac_ulog
-- ----------------------------
DROP TABLE IF EXISTS `mac_ulog`;
CREATE TABLE `mac_ulog` (
  `ulog_id` int(10) unsigned NOT NULL AUTO_INCREMENT ,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `ulog_mid` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `ulog_type` tinyint(1) unsigned NOT NULL DEFAULT '1' ,
  `ulog_rid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `ulog_sid` tinyint(3) unsigned NOT NULL DEFAULT '0' ,
  `ulog_nid` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `ulog_points` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `ulog_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`ulog_id`),
  KEY `user_id` (`user_id`),
  KEY `ulog_mid` (`ulog_mid`),
  KEY `ulog_type` (`ulog_type`),
  KEY `ulog_rid` (`ulog_rid`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_user
-- ----------------------------
DROP TABLE IF EXISTS `mac_user`;
CREATE TABLE `mac_user` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `user_name` varchar(30) NOT NULL DEFAULT '' ,
  `user_pwd` varchar(32) NOT NULL DEFAULT '' ,
  `user_nick_name` varchar(30) NOT NULL DEFAULT '' ,
  `user_qq` varchar(16) NOT NULL DEFAULT '' ,
  `user_email` varchar(30) NOT NULL DEFAULT '' ,
  `user_phone` varchar(16) NOT NULL DEFAULT '' ,
  `user_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `user_portrait` varchar(100) NOT NULL DEFAULT '' ,
  `user_portrait_thumb` varchar(100) NOT NULL DEFAULT '' ,
  `user_openid_qq` varchar(40) NOT NULL DEFAULT '' ,
  `user_openid_weixin` varchar(40) NOT NULL DEFAULT '' ,
  `user_question` varchar(255) NOT NULL DEFAULT '' ,
  `user_answer` varchar(255) NOT NULL DEFAULT '' ,
  `user_points` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_points_froze` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_reg_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_reg_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_login_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_login_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_last_login_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_last_login_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_login_num` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `user_extend` smallint(6) unsigned NOT NULL DEFAULT '0',
  `user_random` varchar(32) NOT NULL DEFAULT '' ,
  `user_end_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_pid` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_pid_2` int(10) unsigned NOT NULL DEFAULT '0' ,
  `user_pid_3` int(10) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`user_id`),
  KEY `type_id` (`group_id`) USING BTREE,
  KEY `user_name` (`user_name`),
  KEY `user_reg_time` (`user_reg_time`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_visit
-- ----------------------------
DROP TABLE IF EXISTS `mac_visit`;
CREATE TABLE `mac_visit` (
  `visit_id` int(10) unsigned NOT NULL AUTO_INCREMENT ,
  `user_id` int(10) unsigned DEFAULT '0',
  `visit_ip` int(10) unsigned NOT NULL DEFAULT '0' ,
  `visit_ly` varchar(100) NOT NULL DEFAULT '',
  `visit_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  PRIMARY KEY (`visit_id`),
  KEY `user_id` (`user_id`),
  KEY `visit_time` (`visit_time`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_vod
-- ----------------------------
DROP TABLE IF EXISTS `mac_vod`;
CREATE TABLE `mac_vod` (
  `vod_id` int(10) unsigned NOT NULL AUTO_INCREMENT ,
  `type_id` smallint(6) NOT NULL DEFAULT '0' ,
  `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `vod_name` varchar(255) NOT NULL DEFAULT '' ,
  `vod_sub` varchar(255) NOT NULL DEFAULT '' ,
  `vod_en` varchar(255) NOT NULL DEFAULT '' ,
  `vod_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_letter` char(1) NOT NULL DEFAULT '' ,
  `vod_color` varchar(6) NOT NULL DEFAULT '' ,
  `vod_tag` varchar(100) NOT NULL DEFAULT '' ,
  `vod_class` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pic` varchar(1024) NOT NULL DEFAULT '' ,
  `vod_pic_thumb` varchar(1024) NOT NULL DEFAULT '' ,
  `vod_pic_slide` varchar(1024) NOT NULL DEFAULT '' ,
  `vod_pic_screenshot` text,
  `vod_actor` varchar(255) NOT NULL DEFAULT '' ,
  `vod_director` varchar(255) NOT NULL DEFAULT '' ,
  `vod_writer` varchar(100) NOT NULL DEFAULT '' ,
  `vod_behind` varchar(100) NOT NULL DEFAULT '' ,
  `vod_blurb` varchar(255) NOT NULL DEFAULT '' ,
  `vod_remarks` varchar(100) NOT NULL DEFAULT '' ,
  `vod_pubdate` varchar(100) NOT NULL DEFAULT '' ,
  `vod_total` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_serial` varchar(20) NOT NULL DEFAULT '0' ,
  `vod_tv` varchar(30) NOT NULL DEFAULT '' ,
  `vod_weekday` varchar(30) NOT NULL DEFAULT '' ,
  `vod_area` varchar(20) NOT NULL DEFAULT '' ,
  `vod_lang` varchar(10) NOT NULL DEFAULT '' ,
  `vod_year` varchar(10) NOT NULL DEFAULT '' ,
  `vod_version` varchar(30) NOT NULL DEFAULT '' ,
  `vod_state` varchar(30) NOT NULL DEFAULT '' ,
  `vod_author` varchar(60) NOT NULL DEFAULT '' ,
  `vod_jumpurl` varchar(150) NOT NULL DEFAULT '' ,
  `vod_tpl` varchar(30) NOT NULL DEFAULT '' ,
  `vod_tpl_play` varchar(30) NOT NULL DEFAULT '' ,
  `vod_tpl_down` varchar(30) NOT NULL DEFAULT '' ,
  `vod_isend` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_level` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_copyright` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_points` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `vod_points_play` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `vod_points_down` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `vod_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_duration` varchar(10) NOT NULL DEFAULT '' ,
  `vod_up` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_down` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' ,
  `vod_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `vod_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `vod_time_add` int(10) unsigned NOT NULL DEFAULT '0' ,
  `vod_time_hits` int(10) unsigned NOT NULL DEFAULT '0' ,
  `vod_time_make` int(10) unsigned NOT NULL DEFAULT '0' ,
  `vod_trysee` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `vod_douban_id` int(10) unsigned NOT NULL DEFAULT '0' ,
  `vod_douban_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' ,
  `vod_reurl` varchar(255) NOT NULL DEFAULT '' ,
  `vod_rel_vod` varchar(255) NOT NULL DEFAULT '' ,
  `vod_rel_art` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pwd` varchar(10) NOT NULL DEFAULT '' ,
  `vod_pwd_url` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pwd_play` varchar(10) NOT NULL DEFAULT '' ,
  `vod_pwd_play_url` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pwd_down` varchar(10) NOT NULL DEFAULT '' ,
  `vod_pwd_down_url` varchar(255) NOT NULL DEFAULT '' ,
  `vod_content` mediumtext NOT NULL ,
  `vod_play_from` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_server` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_note` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_url` mediumtext NOT NULL ,
  `vod_down_from` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_server` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_note` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_url` mediumtext NOT NULL ,
  `vod_plot` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `vod_plot_name` mediumtext NOT NULL ,
  `vod_plot_detail` mediumtext NOT NULL ,
  PRIMARY KEY (`vod_id`),
  KEY `type_id` (`type_id`) USING BTREE,
  KEY `type_id_1` (`type_id_1`) USING BTREE,
  KEY `vod_level` (`vod_level`) USING BTREE,
  KEY `vod_hits` (`vod_hits`) USING BTREE,
  KEY `vod_letter` (`vod_letter`) USING BTREE,
  KEY `vod_name` (`vod_name`) USING BTREE,
  KEY `vod_year` (`vod_year`) USING BTREE,
  KEY `vod_area` (`vod_area`) USING BTREE,
  KEY `vod_lang` (`vod_lang`) USING BTREE,
  KEY `vod_tag` (`vod_tag`) USING BTREE,
  KEY `vod_class` (`vod_class`) USING BTREE,
  KEY `vod_lock` (`vod_lock`) USING BTREE,
  KEY `vod_up` (`vod_up`) USING BTREE,
  KEY `vod_down` (`vod_down`) USING BTREE,
  KEY `vod_en` (`vod_en`) USING BTREE,
  KEY `vod_hits_day` (`vod_hits_day`) USING BTREE,
  KEY `vod_hits_week` (`vod_hits_week`) USING BTREE,
  KEY `vod_hits_month` (`vod_hits_month`) USING BTREE,
  KEY `vod_plot` (`vod_plot`) USING BTREE,
  KEY `vod_points_play` (`vod_points_play`) USING BTREE,
  KEY `vod_points_down` (`vod_points_down`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE,
  KEY `vod_time_add` (`vod_time_add`) USING BTREE,
  KEY `vod_time` (`vod_time`) USING BTREE,
  KEY `vod_time_make` (`vod_time_make`) USING BTREE,
  KEY `vod_actor` (`vod_actor`) USING BTREE,
  KEY `vod_director` (`vod_director`) USING BTREE,
  KEY `vod_score_all` (`vod_score_all`) USING BTREE,
  KEY `vod_score_num` (`vod_score_num`) USING BTREE,
  KEY `vod_total` (`vod_total`) USING BTREE,
  KEY `vod_score` (`vod_score`) USING BTREE,
  KEY `vod_version` (`vod_version`),
  KEY `vod_state` (`vod_state`),
  KEY `vod_isend` (`vod_isend`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;

-- ----------------------------
-- Table structure for mac_website
-- ----------------------------
DROP TABLE IF EXISTS `mac_website`;
CREATE TABLE `mac_website` (
  `website_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `type_id_1` smallint(5) unsigned NOT NULL DEFAULT '0',
  `website_name` varchar(60) NOT NULL DEFAULT '',
  `website_sub` varchar(255) NOT NULL DEFAULT '',
  `website_en` varchar(255) NOT NULL DEFAULT '',
  `website_status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `website_letter` char(1) NOT NULL DEFAULT '',
  `website_color` varchar(6) NOT NULL DEFAULT '',
  `website_lock` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `website_sort` int(10) NOT NULL DEFAULT '0',
  `website_jumpurl` varchar(255) NOT NULL DEFAULT '',
  `website_pic` varchar(1024) NOT NULL DEFAULT '',
  `website_pic_screenshot` text,
  `website_logo` varchar(255) NOT NULL DEFAULT '',
  `website_area` varchar(20) NOT NULL DEFAULT '',
  `website_lang` varchar(10) NOT NULL DEFAULT '',
  `website_level` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `website_time` int(10) unsigned NOT NULL DEFAULT '0',
  `website_time_add` int(10) unsigned NOT NULL DEFAULT '0',
  `website_time_hits` int(10) unsigned NOT NULL DEFAULT '0',
  `website_time_make` int(10) unsigned NOT NULL DEFAULT '0',
  `website_time_referer` int(10) unsigned NOT NULL DEFAULT '0',
  `website_hits` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0',
  `website_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_up` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_down` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_referer` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_referer_day` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_referer_week` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_referer_month` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `website_tag` varchar(100) NOT NULL DEFAULT '',
  `website_class` varchar(255) NOT NULL DEFAULT '',
  `website_remarks` varchar(100) NOT NULL DEFAULT '',
  `website_tpl` varchar(30) NOT NULL DEFAULT '',
  `website_blurb` varchar(255) NOT NULL DEFAULT '',
  `website_content` mediumtext NOT NULL,
  PRIMARY KEY (`website_id`),
  KEY `type_id` (`type_id`),
  KEY `type_id_1` (`type_id_1`),
  KEY `website_name` (`website_name`),
  KEY `website_en` (`website_en`),
  KEY `website_letter` (`website_letter`),
  KEY `website_sort` (`website_sort`),
  KEY `website_lock` (`website_lock`),
  KEY `website_time` (`website_time`),
  KEY `website_time_add` (`website_time_add`),
  KEY `website_time_referer` (`website_time_referer`),
  KEY `website_hits` (`website_hits`),
  KEY `website_hits_day` (`website_hits_day`),
  KEY `website_hits_week` (`website_hits_week`),
  KEY `website_hits_month` (`website_hits_month`),
  KEY `website_time_make` (`website_time_make`),
  KEY `website_score` (`website_score`),
  KEY `website_score_all` (`website_score_all`),
  KEY `website_score_num` (`website_score_num`),
  KEY `website_up` (`website_up`),
  KEY `website_down` (`website_down`),
  KEY `website_level` (`website_level`),
  KEY `website_tag` (`website_tag`),
  KEY `website_class` (`website_class`),
  KEY `website_referer` (`website_referer`),
  KEY `website_referer_day` (`website_referer_day`),
  KEY `website_referer_week` (`website_referer_week`),
  KEY `website_referer_month` (`website_referer_month`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ;


-- ----------------------------
-- Table structure for mac_vod_search
-- ----------------------------
DROP TABLE IF EXISTS `mac_vod_search`;
CREATE TABLE `mac_vod_search` (
  `search_key` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索键（关键词md5）',
  `search_word` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '搜索关键词',
  `search_field` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索字段名（可有多个，用|分隔）',
  `search_hit_count` bigint unsigned NOT NULL DEFAULT '0' COMMENT '搜索命中次数',
  `search_last_hit_time` int unsigned NOT NULL DEFAULT '0' COMMENT '最近命中时间',
  `search_update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `search_result_count` int unsigned NOT NULL DEFAULT '0' COMMENT '结果Id数量',
  `search_result_ids` mediumtext CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '搜索结果Id列表，英文半角逗号分隔',
  PRIMARY KEY (`search_key`),
  KEY `search_field` (`search_field`),
  KEY `search_update_time` (`search_update_time`),
  KEY `search_hit_count` (`search_hit_count`),
  KEY `search_last_hit_time` (`search_last_hit_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='vod搜索缓存表';
