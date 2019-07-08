-- ----------------------------
-- Table structure for mac_actor
-- ----------------------------
DROP TABLE IF EXISTS `mac_actor`;
CREATE TABLE `mac_actor` (
  `actor_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actor_name` varchar(255) NOT NULL DEFAULT '',
  `actor_en` varchar(255) NOT NULL DEFAULT '',
  `actor_alias` varchar(255) NOT NULL DEFAULT '' ,
  `actor_status` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `actor_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' ,
  `actor_letter` char(1) NOT NULL DEFAULT '' ,
  `actor_sex` char(1) NOT NULL DEFAULT '',
  `actor_color` varchar(6) NOT NULL DEFAULT '' ,
  `actor_pic` varchar(255) NOT NULL DEFAULT '' ,
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
  `actor_score_num` mediumint(6) unsigned NOT NULL DEFAULT '0',
  `actor_up` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_down` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `actor_tpl` varchar(30) NOT NULL DEFAULT '',
  `actor_jumpurl` varchar(150) NOT NULL DEFAULT '',
  `actor_content` text NOT NULL ,
  PRIMARY KEY (`actor_id`),
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
  `art_pic` varchar(255) NOT NULL DEFAULT '' ,
  `art_pic_thumb` varchar(255) NOT NULL DEFAULT '' ,
  `art_pic_slide` varchar(255) NOT NULL DEFAULT '' ,
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
  `art_score_num` mediumint(6) unsigned NOT NULL DEFAULT '0' ,
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
  KEY `art_enname` (`art_en`) USING BTREE,
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
  `group_type` varchar(255) NOT NULL DEFAULT '' ,
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
  `role_pic` varchar(255) NOT NULL DEFAULT '' ,
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
  `role_score_num` mediumint(6) unsigned NOT NULL DEFAULT '0',
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
  `topic_pic` varchar(255) NOT NULL DEFAULT '',
  `topic_pic_thumb` varchar(255) NOT NULL DEFAULT '',
  `topic_pic_slide` varchar(255) NOT NULL DEFAULT '',
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
  `topic_score_num` smallint(6) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' ,
  `topic_time` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_add` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_hits` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_time_make` int(10) unsigned NOT NULL DEFAULT '0' ,
  `topic_tag` varchar(255) NOT NULL DEFAULT '' ,
  `topic_rel_vod` text ,
  `topic_rel_art` text ,
  `topic_content` text ,
  `topic_extend` text ,
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
  `type_extend` text ,
  PRIMARY KEY (`type_id`),
  KEY `type_sort` (`type_sort`) USING BTREE,
  KEY `type_pid` (`type_pid`) USING BTREE,
  KEY `type_name` (`type_name`),
  KEY `type_en` (`type_en`),
  KEY `type_mid` (`type_mid`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 ;


INSERT INTO `mac_type` VALUES ('1', '电影', 'dianying', '1', '1', '0', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '电影,电影大全,电影天堂,最新电影,好看的电影,电影排行榜', '为您提供更新电影、好看的电影排行榜及电影迅雷下载，免费在线观看伦理电影、动作片、喜剧片、爱情片、搞笑片等全新电影。', '电影', '', '{\"class\":\"\\u559c\\u5267,\\u7231\\u60c5,\\u6050\\u6016,\\u52a8\\u4f5c,\\u79d1\\u5e7b,\\u5267\\u60c5,\\u6218\\u4e89,\\u8b66\\u532a,\\u72af\\u7f6a,\\u52a8\\u753b,\\u5947\\u5e7b,\\u6b66\\u4fa0,\\u5192\\u9669,\\u67aa\\u6218,\\u6050\\u6016,\\u60ac\\u7591,\\u60ca\\u609a,\\u7ecf\\u5178,\\u9752\\u6625,\\u6587\\u827a,\\u5fae\\u7535\\u5f71,\\u53e4\\u88c5,\\u5386\\u53f2,\\u8fd0\\u52a8,\\u519c\\u6751,\\u513f\\u7ae5,\\u7f51\\u7edc\\u7535\\u5f71\",\"area\":\"\\u5927\\u9646,\\u9999\\u6e2f,\\u53f0\\u6e7e,\\u7f8e\\u56fd,\\u6cd5\\u56fd,\\u82f1\\u56fd,\\u65e5\\u672c,\\u97e9\\u56fd,\\u5fb7\\u56fd,\\u6cf0\\u56fd,\\u5370\\u5ea6,\\u610f\\u5927\\u5229,\\u897f\\u73ed\\u7259,\\u52a0\\u62ff\\u5927,\\u5176\\u4ed6\",\"lang\":\"\\u56fd\\u8bed,\\u82f1\\u8bed,\\u7ca4\\u8bed,\\u95fd\\u5357\\u8bed,\\u97e9\\u8bed,\\u65e5\\u8bed,\\u6cd5\\u8bed,\\u5fb7\\u8bed,\\u5176\\u5b83\",\"year\":\"2018,2017,2016,2015,2014,2013,2012,2011,2010\",\"star\":\"\\u738b\\u5b9d\\u5f3a,\\u9ec4\\u6e24,\\u5468\\u8fc5,\\u5468\\u51ac\\u96e8,\\u8303\\u51b0\\u51b0,\\u9648\\u5b66\\u51ac,\\u9648\\u4f1f\\u9706,\\u90ed\\u91c7\\u6d01,\\u9093\\u8d85,\\u6210\\u9f99,\\u845b\\u4f18,\\u6797\\u6b63\\u82f1,\\u5f20\\u5bb6\\u8f89,\\u6881\\u671d\\u4f1f,\\u5f90\\u5ce5,\\u90d1\\u607a,\\u5434\\u5f66\\u7956,\\u5218\\u5fb7\\u534e,\\u5468\\u661f\\u9a70,\\u6797\\u9752\\u971e,\\u5468\\u6da6\\u53d1,\\u674e\\u8fde\\u6770,\\u7504\\u5b50\\u4e39,\\u53e4\\u5929\\u4e50,\\u6d2a\\u91d1\\u5b9d,\\u59da\\u6668,\\u502a\\u59ae,\\u9ec4\\u6653\\u660e,\\u5f6d\\u4e8e\\u664f,\\u6c64\\u552f,\\u9648\\u5c0f\\u6625\",\"director\":\"\\u51af\\u5c0f\\u521a,\\u5f20\\u827a\\u8c0b,\\u5434\\u5b87\\u68ee,\\u9648\\u51ef\\u6b4c,\\u5f90\\u514b,\\u738b\\u5bb6\\u536b,\\u59dc\\u6587,\\u5468\\u661f\\u9a70,\\u674e\\u5b89\",\"state\":\"\\u6b63\\u7247,\\u9884\\u544a\\u7247,\\u82b1\\u7d6e\",\"version\":\"\\u9ad8\\u6e05\\u7248,\\u5267\\u573a\\u7248,\\u62a2\\u5148\\u7248,OVA,TV,\\u5f71\\u9662\\u7248\"}');
INSERT INTO `mac_type` VALUES ('2', '连续剧', 'lianxuju', '2', '1', '0', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '电视剧,最新电视剧,好看的电视剧,热播电视剧,电视剧在线观看', '为您提供2018新电视剧排行榜，韩国电视剧、泰国电视剧、香港TVB全新电视剧排行榜、好看的电视剧等热播电视剧排行榜，并提供免费高清电视剧下载及在线观看。', '电视剧', '', '{\"class\":\"\\u53e4\\u88c5,\\u6218\\u4e89,\\u9752\\u6625\\u5076\\u50cf,\\u559c\\u5267,\\u5bb6\\u5ead,\\u72af\\u7f6a,\\u52a8\\u4f5c,\\u5947\\u5e7b,\\u5267\\u60c5,\\u5386\\u53f2,\\u7ecf\\u5178,\\u4e61\\u6751,\\u60c5\\u666f,\\u5546\\u6218,\\u7f51\\u5267,\\u5176\\u4ed6\",\"area\":\"\\u5185\\u5730,\\u97e9\\u56fd,\\u9999\\u6e2f,\\u53f0\\u6e7e,\\u65e5\\u672c,\\u7f8e\\u56fd,\\u6cf0\\u56fd,\\u82f1\\u56fd,\\u65b0\\u52a0\\u5761,\\u5176\\u4ed6\",\"lang\":\"\\u56fd\\u8bed,\\u82f1\\u8bed,\\u7ca4\\u8bed,\\u95fd\\u5357\\u8bed,\\u97e9\\u8bed,\\u65e5\\u8bed,\\u5176\\u5b83\",\"year\":\"2018,2017,2016,2015,2014,2013,2012,2011,2010,2009,2008,2006,2005,2004\",\"star\":\"\\u738b\\u5b9d\\u5f3a,\\u80e1\\u6b4c,\\u970d\\u5efa\\u534e,\\u8d75\\u4e3d\\u9896,\\u5218\\u6d9b,\\u5218\\u8bd7\\u8bd7,\\u9648\\u4f1f\\u9706,\\u5434\\u5947\\u9686,\\u9646\\u6bc5,\\u5510\\u5ae3,\\u5173\\u6653\\u5f64,\\u5b59\\u4fea,\\u674e\\u6613\\u5cf0,\\u5f20\\u7ff0,\\u674e\\u6668,\\u8303\\u51b0\\u51b0,\\u6797\\u5fc3\\u5982,\\u6587\\u7ae0,\\u9a6c\\u4f0a\\u740d,\\u4f5f\\u5927\\u4e3a,\\u5b59\\u7ea2\\u96f7,\\u9648\\u5efa\\u658c,\\u674e\\u5c0f\\u7490\",\"director\":\"\\u5f20\\u7eaa\\u4e2d,\\u674e\\u5c11\\u7ea2,\\u5218\\u6c5f,\\u5b54\\u7b19,\\u5f20\\u9ece,\\u5eb7\\u6d2a\\u96f7,\\u9ad8\\u5e0c\\u5e0c,\\u80e1\\u73ab,\\u8d75\\u5b9d\\u521a,\\u90d1\\u6653\\u9f99\",\"state\":\"\\u6b63\\u7247,\\u9884\\u544a\\u7247,\\u82b1\\u7d6e\",\"version\":\"\\u9ad8\\u6e05\\u7248,\\u5267\\u573a\\u7248,\\u62a2\\u5148\\u7248,OVA,TV,\\u5f71\\u9662\\u7248\"}');
INSERT INTO `mac_type` VALUES ('3', '综艺', 'zongyi', '3', '1', '0', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '综艺,综艺节目,最新综艺节目,综艺节目排行榜', '为您提供新综艺节目、好看的综艺节目排行榜，免费高清在线观看选秀、情感、访谈、搞笑、真人秀、脱口秀等热门综艺节目。', '综艺', '', '{\"class\":\"\\u9009\\u79c0,\\u60c5\\u611f,\\u8bbf\\u8c08,\\u64ad\\u62a5,\\u65c5\\u6e38,\\u97f3\\u4e50,\\u7f8e\\u98df,\\u7eaa\\u5b9e,\\u66f2\\u827a,\\u751f\\u6d3b,\\u6e38\\u620f\\u4e92\\u52a8,\\u8d22\\u7ecf,\\u6c42\\u804c\",\"area\":\"\\u5185\\u5730,\\u6e2f\\u53f0,\\u65e5\\u97e9,\\u6b27\\u7f8e\",\"lang\":\"\\u56fd\\u8bed,\\u82f1\\u8bed,\\u7ca4\\u8bed,\\u95fd\\u5357\\u8bed,\\u97e9\\u8bed,\\u65e5\\u8bed,\\u5176\\u5b83\",\"year\":\"2018,2017,2016,2015,2014,2013,2012,2011,2010,2009,2008,2007,2006,2005,2004\",\"star\":\"\\u4f55\\u7085,\\u6c6a\\u6db5,\\u8c22\\u5a1c,\\u5468\\u7acb\\u6ce2,\\u9648\\u9c81\\u8c6b,\\u5b5f\\u975e,\\u674e\\u9759,\\u6731\\u519b,\\u6731\\u4e39,\\u534e\\u5c11,\\u90ed\\u5fb7\\u7eb2,\\u6768\\u6f9c\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('4', '动漫', 'dongman', '4', '1', '0', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '动漫,动漫大全,最新动漫,好看的动漫,日本动漫,动漫排行榜', '为您提供新动漫、好看的动漫排行榜，免费高清在线观看热血动漫、卡通动漫、新番动漫、百合动漫、搞笑动漫、国产动漫、动漫电影等热门动漫。', '动画片', '', '{\"class\":\"\\u60c5\\u611f,\\u79d1\\u5e7b,\\u70ed\\u8840,\\u63a8\\u7406,\\u641e\\u7b11,\\u5192\\u9669,\\u841d\\u8389,\\u6821\\u56ed,\\u52a8\\u4f5c,\\u673a\\u6218,\\u8fd0\\u52a8,\\u6218\\u4e89,\\u5c11\\u5e74,\\u5c11\\u5973,\\u793e\\u4f1a,\\u539f\\u521b,\\u4eb2\\u5b50,\\u76ca\\u667a,\\u52b1\\u5fd7,\\u5176\\u4ed6\",\"area\":\"\\u56fd\\u4ea7,\\u65e5\\u672c,\\u6b27\\u7f8e,\\u5176\\u4ed6\",\"lang\":\"\\u56fd\\u8bed,\\u82f1\\u8bed,\\u7ca4\\u8bed,\\u95fd\\u5357\\u8bed,\\u97e9\\u8bed,\\u65e5\\u8bed,\\u5176\\u5b83\",\"year\":\"2018,2017,2016,2015,2014,2013,2012,2011,2010,2009,2008,2007,2006,2005,2004\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"TV\\u7248,\\u7535\\u5f71\\u7248,OVA\\u7248,\\u771f\\u4eba\\u7248\"}');
INSERT INTO `mac_type` VALUES ('5', '资讯', 'zixun', '5', '2', '0', '1', 'type.html', 'show.html', 'detail.html', '', '', '最新影视资讯,电影资讯,娱乐资讯', '本站提供最新电影,电视剧,综艺,动漫的资讯信息,一览无遗', '最新资讯-推荐资讯', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('7', '喜剧片', 'xijupian', '2', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的喜剧片,最新喜剧片,经典喜剧片,国语喜剧片电影', '2018最新喜剧片，好看的喜剧片大全和排行榜推荐，免费喜剧片在线观看和视频在线播放是由本网站整理和收录，欢迎喜剧片爱好者来到这里在线观看喜剧片', '好看的喜剧片-最新喜剧片-经典喜剧片-最新喜剧片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('6', '动作片', 'dongzuopian', '1', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的动作片,最新动作片,经典动作片,国语动作片电影', '2018最新动作片，好看的动作片大全和排行榜推荐，免费动作片在线观看和视频在线播放是由本网站整理和收录，欢迎动作片爱好者来到这里在线观看动作片', '好看的动作片-最新动作片-经典动作片-最新动作片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('8', '爱情片', 'aiqingpian', '3', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的爱情片,最新爱情片,经典爱情片,国语爱情片电影', '2018最新爱情片，好看的爱情片大全和排行榜推荐，免费爱情片在线观看和视频在线播放是由本网站整理和收录，欢迎爱情片爱好者来到这里在线观看爱情片', '好看的爱情片-最新爱情片-经典爱情片-最新爱情片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('9', '科幻片', 'kehuanpian', '4', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的科幻片,最新科幻片,经典科幻片,国语科幻片电影', '2018最新科幻片，好看的科幻片大全和排行榜推荐，免费科幻片在线观看和视频在线播放是由本网站整理和收录，欢迎科幻片爱好者来到这里在线观看科幻片', '好看的科幻片-最新科幻片-经典科幻片-最新科幻片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('10', '恐怖片', 'kongbupian', '5', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的恐怖片,最新恐怖片,经典恐怖片,国语恐怖片电影', '2018最新恐怖片，好看的恐怖片大全和排行榜推荐，免费恐怖片在线观看和视频在线播放是由本网站整理和收录，欢迎恐怖片爱好者来到这里在线观看恐怖片', '好看的恐怖片-最新恐怖片-经典恐怖片-最新恐怖片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('11', '剧情片', 'juqingpian', '6', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的剧情片,最新剧情片,经典剧情片,国语剧情片电影', '2018最新剧情片，好看的剧情片大全和排行榜推荐，免费剧情片在线观看和视频在线播放是由本网站整理和收录，欢迎剧情片爱好者来到这里在线观看剧情片', '好看的剧情片-最新剧情片-经典剧情片-最新剧情片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('12', '战争片', 'zhanzhengpian', '7', '1', '1', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的战争片,最新战争片,经典战争片,国语战争片电影', '2018最新战争片，好看的战争片大全和排行榜推荐，免费战争片在线观看和视频在线播放是由本网站整理和收录，欢迎战争片爱好者来到这里在线观看战争片', '好看的战争片-最新战争片-经典战争片-最新战争片推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('13', '国产剧', 'guochanju', '1', '1', '2', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的国产剧,最新国产剧,经典国产剧,国语国产剧电影', '2018最新国产剧，好看的国产剧大全和排行榜推荐，免费国产剧在线观看和视频在线播放是由本网站整理和收录，欢迎国产剧爱好者来到这里在线观看国产剧', '好看的国产剧-最新国产剧-经典国产剧-最新国产剧推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('14', '港台剧', 'gangtaiju', '2', '1', '2', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的港台剧,最新港台剧,经典港台剧,国语港台剧电影', '2018最新港台剧，好看的港台剧大全和排行榜推荐，免费港台剧在线观看和视频在线播放是由本网站整理和收录，欢迎港台剧爱好者来到这里在线观看港台剧', '好看的港台剧-最新港台剧-经典港台剧-最新港台剧推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('15', '日韩剧', 'rihanju', '3', '1', '2', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的日韩剧,最新日韩剧,经典日韩剧,国语日韩剧电影', '2018最新日韩剧，好看的日韩剧大全和排行榜推荐，免费日韩剧在线观看和视频在线播放是由本网站整理和收录，欢迎日韩剧爱好者来到这里在线观看日韩剧', '好看的日韩剧-最新日韩剧-经典日韩剧-最新日韩剧推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('16', '欧美剧', 'oumeiju', '4', '1', '2', '1', 'type.html', 'show.html', 'detail.html', 'play.html', 'down.html', '好看的欧美剧,最新欧美剧,经典欧美剧,国语欧美剧电影', '2018最新欧美剧，好看的欧美剧大全和排行榜推荐，免费欧美剧在线观看和视频在线播放是由本网站整理和收录，欢迎欧美剧爱好者来到这里在线观看欧美剧', '好看的欧美剧-最新欧美剧-经典欧美剧-最新欧美剧推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('17', '公告', 'gonggao', '1', '2', '5', '1', 'type.html', 'show.html', 'detail.html', '', '', '最新公告-最新公告推荐', '2018最新公告，公布本站最新发展动态', '最新公告-最新公告推荐', '', '{\"class\":\"\",\"area\":\"\",\"lang\":\"\",\"year\":\"\",\"star\":\"\",\"director\":\"\",\"state\":\"\",\"version\":\"\"}');
INSERT INTO `mac_type` VALUES ('18', '头条', 'toutiao', '2', '2', '5', '1', 'type.html', 'show.html', 'detail.html', '', '', '', '', '', '', '');


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
  `vod_pic` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pic_thumb` varchar(255) NOT NULL DEFAULT '' ,
  `vod_pic_slide` varchar(255) NOT NULL DEFAULT '' ,
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
  `vod_score_num` mediumint(6) unsigned NOT NULL DEFAULT '0' ,
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
  `vod_content` text NOT NULL ,
  `vod_play_from` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_server` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_note` varchar(255) NOT NULL DEFAULT '' ,
  `vod_play_url` mediumtext NOT NULL ,
  `vod_down_from` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_server` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_note` varchar(255) NOT NULL DEFAULT '' ,
  `vod_down_url` mediumtext NOT NULL ,
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

