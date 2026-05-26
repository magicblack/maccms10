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
  `art_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' ,
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
-- Table structure for mac_manga
-- ----------------------------
DROP TABLE IF EXISTS `mac_manga`;
CREATE TABLE `mac_manga` (
  `manga_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '漫画ID',
  `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '主分类ID',
  `type_id_1` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '副分类ID',
  `group_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '会员组ID',
  `manga_name` varchar(255) NOT NULL DEFAULT '' COMMENT '漫画名称',
  `manga_sub` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题',
  `manga_en` varchar(255) NOT NULL DEFAULT '' COMMENT '英文名',
  `manga_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态(0=锁定,1=正常)',
  `manga_letter` char(1) NOT NULL DEFAULT '' COMMENT '首字母',
  `manga_color` varchar(6) NOT NULL DEFAULT '' COMMENT '标题颜色',
  `manga_from` varchar(30) NOT NULL DEFAULT '' COMMENT '来源',
  `manga_author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者',
  `manga_tag` varchar(100) NOT NULL DEFAULT '' COMMENT '标签',
  `manga_class` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展分类',
  `manga_pic` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面图',
  `manga_pic_thumb` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面缩略图',
  `manga_pic_slide` varchar(1024) NOT NULL DEFAULT '' COMMENT '封面幻灯图',
  `manga_pic_screenshot` text DEFAULT NULL COMMENT '内容截图',
  `manga_blurb` varchar(255) NOT NULL DEFAULT '' COMMENT '简介',
  `manga_remarks` varchar(100) NOT NULL DEFAULT '' COMMENT '备注(例如：更新至xx话)',
  `manga_jumpurl` varchar(150) NOT NULL DEFAULT '' COMMENT '跳转URL',
  `manga_tpl` varchar(30) NOT NULL DEFAULT '' COMMENT '独立模板',
  `manga_level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '推荐级别',
  `manga_lock` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '锁定状态(0=未锁,1=已锁)',
  `manga_points` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '点播所需积分',
  `manga_points_detail` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '每章所需积分',
  `manga_up` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '顶数',
  `manga_down` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '踩数',
  `manga_hits` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总点击数',
  `manga_hits_day` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '日点击数',
  `manga_hits_week` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '周点击数',
  `manga_hits_month` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '月点击数',
  `manga_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `manga_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `manga_time_hits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点击时间',
  `manga_time_make` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '生成时间',
  `manga_score` decimal(3,1) unsigned NOT NULL DEFAULT '0.0' COMMENT '平均评分',
  `manga_score_all` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总评分',
  `manga_score_num` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '评分次数',
  `manga_rel_manga` varchar(255) NOT NULL DEFAULT '' COMMENT '关联漫画',
  `manga_rel_vod` varchar(255) NOT NULL DEFAULT '' COMMENT '关联视频',
  `manga_pwd` varchar(10) NOT NULL DEFAULT '' COMMENT '访问密码',
  `manga_pwd_url` varchar(255) NOT NULL DEFAULT '' COMMENT '密码跳转URL',
  `manga_content` mediumtext DEFAULT NULL COMMENT '详细介绍',
  `manga_serial` varchar(20) NOT NULL DEFAULT '0' COMMENT '连载状态(文字)',
  `manga_total` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '总章节数',
  `manga_chapter_from` varchar(255) NOT NULL DEFAULT '' COMMENT '章节来源',
  `manga_chapter_url` mediumtext DEFAULT NULL COMMENT '章节URL列表',
  `manga_last_update_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间戳',
  `manga_age_rating` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '年龄分级(0=全年龄,1=12+,2=18+)',
  `manga_orientation` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '阅读方向(1=左到右,2=右到左,3=垂直)',
  `manga_is_vip` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否VIP(0=否,1=是)',
  `manga_copyright_info` varchar(255) NOT NULL DEFAULT '' COMMENT '版权信息',
  `manga_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '回收站时间戳，0=正常',
  PRIMARY KEY (`manga_id`),
  KEY `type_id` (`type_id`) USING BTREE,
  KEY `type_id_1` (`type_id_1`) USING BTREE,
  KEY `manga_level` (`manga_level`) USING BTREE,
  KEY `manga_hits` (`manga_hits`) USING BTREE,
  KEY `manga_time` (`manga_time`) USING BTREE,
  KEY `manga_letter` (`manga_letter`) USING BTREE,
  KEY `manga_down` (`manga_down`) USING BTREE,
  KEY `manga_up` (`manga_up`) USING BTREE,
  KEY `manga_tag` (`manga_tag`) USING BTREE,
  KEY `manga_name` (`manga_name`) USING BTREE,
  KEY `manga_en` (`manga_en`) USING BTREE,
  KEY `manga_hits_day` (`manga_hits_day`) USING BTREE,
  KEY `manga_hits_week` (`manga_hits_week`) USING BTREE,
  KEY `manga_hits_month` (`manga_hits_month`) USING BTREE,
  KEY `manga_time_add` (`manga_time_add`) USING BTREE,
  KEY `manga_time_make` (`manga_time_make`) USING BTREE,
  KEY `manga_lock` (`manga_lock`),
  KEY `manga_score` (`manga_score`),
  KEY `manga_score_all` (`manga_score_all`),
  KEY `manga_score_num` (`manga_score_num`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='漫画表';

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
-- Table structure for mac_admin_audit_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_admin_audit_log`;
CREATE TABLE `mac_admin_audit_log` (
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
  `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '会员组ID,多个用逗号分隔',
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
  `user_invite_code` varchar(20) NOT NULL DEFAULT '' COMMENT '邀请码',
  `user_invite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '邀请人数',
  `user_invite_reward_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最后一次发放奖励时间',
  `user_invite_reward_level` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已发放奖励档次(避免重复发放)',
  PRIMARY KEY (`user_id`),
  KEY `user_invite_code` (`user_invite_code`),
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
  `vod_pic_original` varchar(1024) NOT NULL DEFAULT '' ,
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
  `vod_recycle_time` int(10) unsigned NOT NULL DEFAULT '0' ,
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


-- ----------------------------
-- Table structure for mac_search_query_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_search_query_log`;
CREATE TABLE `mac_search_query_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `mid` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `keyword` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `log_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`log_id`),
  KEY `idx_user_time` (`user_id`,`log_time`),
  KEY `idx_time` (`log_time`),
  KEY `idx_keyword` (`keyword`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台搜索关键词日志（热门词/用户历史）';


-- ----------------------------
-- Table structure for mac_seo_ai_result
-- ----------------------------
DROP TABLE IF EXISTS `mac_seo_ai_result`;
CREATE TABLE `mac_seo_ai_result` (
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_day_overview
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_day_overview`;
CREATE TABLE `mac_analytics_day_overview` (
  `stat_date` date NOT NULL COMMENT '统计日（站点时区日历日）',
  `pv` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '页面浏览量',
  `uv` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '独立访客（按 visitor_id/cookie 去重，由任务写入）',
  `session_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会话数',
  `new_reg` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '新注册用户数',
  `user_login_dau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '登录日活（当日有登录行为的用户数）',
  `user_active_mau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '月活（自然月内去重活跃，可月末回填或滚动窗口）',
  `order_paid_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已支付订单笔数',
  `order_paid_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '已支付订单金额',
  `recharge_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '充值类金额（可与订单拆分或等于 order 中充值类型汇总）',
  `ad_impression` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '广告曝光',
  `ad_click` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '广告点击',
  `avg_session_duration_sec` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '平均会话时长（秒）',
  `bounce_rate` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '跳出率 0-100（单页会话/总会话）',
  `retention_d1` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '次日留存率 0-100（按 cohort 任务写入）',
  `retention_d7` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '7日留存率',
  `retention_d30` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '30日留存率',
  `pv_web` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Web 端 PV',
  `pv_h5` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'H5 端 PV',
  `pv_android` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Android PV',
  `pv_ios` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'iOS PV',
  `pv_other` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '未知/其它端 PV',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '本条汇总更新时间 UNIX',
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-全站按日汇总';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_day_dim
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_day_dim`;
CREATE TABLE `mac_analytics_day_dim` (
  `analytics_day_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `dim_type` varchar(32) NOT NULL COMMENT '维度类型',
  `dim_key` varchar(128) NOT NULL COMMENT '维度取值',
  `pv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `uv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `session_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `new_reg` int(10) unsigned NOT NULL DEFAULT '0',
  `dau` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '该切片下日活（定义与任务一致即可）',
  `order_paid_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `order_paid_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00',
  `ad_click` bigint(20) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`analytics_day_id`),
  UNIQUE KEY `uk_date_dim` (`stat_date`,`dim_type`,`dim_key`),
  KEY `idx_dim_type_date` (`dim_type`,`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-按日多维切片';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_hour_dim
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_hour_dim`;
CREATE TABLE `mac_analytics_hour_dim` (
  `analytics_hour_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_hour` datetime NOT NULL COMMENT '整点时间，如 2026-04-15 08:00:00',
  `dim_type` varchar(32) NOT NULL DEFAULT 'all' COMMENT '同 day_dim，all 表示全站',
  `dim_key` varchar(128) NOT NULL DEFAULT '',
  `pv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `uv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `session_cnt` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`analytics_hour_id`),
  UNIQUE KEY `uk_hour_dim` (`stat_hour`,`dim_type`,`dim_key`),
  KEY `idx_hour` (`stat_hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-按小时多维';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_session
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_session`;
CREATE TABLE `mac_analytics_session` (
  `session_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_key` varchar(64) NOT NULL COMMENT '服务端生成或客户端上报的会话ID',
  `visitor_id` varchar(64) NOT NULL DEFAULT '' COMMENT '匿名访客标识（cookie/device）',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `device_type` varchar(16) NOT NULL DEFAULT '' COMMENT 'web/h5/android/ios',
  `os` varchar(32) NOT NULL DEFAULT '',
  `browser` varchar(32) NOT NULL DEFAULT '',
  `app_version` varchar(32) NOT NULL DEFAULT '',
  `region_code` varchar(16) NOT NULL DEFAULT '' COMMENT '省/国家等简码',
  `channel` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道：utm、应用市场等',
  `entry_path` varchar(512) NOT NULL DEFAULT '' COMMENT '落地路径',
  `exit_path` varchar(512) NOT NULL DEFAULT '' COMMENT '离开前最后路径',
  `page_count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `duration_sec` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会话时长',
  `is_bounce` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否跳出会话(仅1次浏览即离开)',
  `started_at` int(10) unsigned NOT NULL DEFAULT '0',
  `ended_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `uk_session_key` (`session_key`),
  KEY `idx_started` (`started_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_visitor` (`visitor_id`),
  KEY `idx_device_date` (`device_type`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-会话';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_pageview
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_pageview`;
CREATE TABLE `mac_analytics_pageview` (
  `analytics_pageview_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `visitor_id` varchar(64) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `path` varchar(512) NOT NULL DEFAULT '' COMMENT '路径或路由',
  `mid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '模块 1视频2文章8漫画等，0非内容页',
  `rid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '内容ID',
  `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID，便于关联多维',
  `stay_ms` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '停留毫秒（离开页或心跳上报）',
  `prev_path` varchar(512) NOT NULL DEFAULT '' COMMENT '上一页路径，构路径漏斗',
  `referer_host` varchar(255) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `stat_date` date NOT NULL,
  PRIMARY KEY (`analytics_pageview_id`),
  KEY `idx_session_ts` (`session_id`,`ts`),
  KEY `idx_ts` (`ts`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_content` (`mid`,`rid`,`ts`),
  KEY `idx_type_ts` (`type_id`,`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-页面浏览明细';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_event
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_event`;
CREATE TABLE `mac_analytics_event` (
  `analytics_event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_code` varchar(48) NOT NULL COMMENT '事件编码 ad_click / pay_intent / ...',
  `session_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `visitor_id` varchar(64) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `device_type` varchar(16) NOT NULL DEFAULT '',
  `region_code` varchar(16) NOT NULL DEFAULT '',
  `mid` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `rid` int(10) unsigned NOT NULL DEFAULT '0',
  `props` varchar(2048) NOT NULL DEFAULT '' COMMENT 'JSON 扩展字段,5.7+ 环境可改为 JSON 类型更优',
  `ts` int(10) unsigned NOT NULL DEFAULT '0',
  `stat_date` date NOT NULL,
  PRIMARY KEY (`analytics_event_id`),
  KEY `idx_event_ts` (`event_code`,`ts`),
  KEY `idx_ts` (`ts`),
  KEY `idx_stat_date` (`stat_date`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_ts` (`user_id`,`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-通用事件';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_content_day
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_content_day`;
CREATE TABLE `mac_analytics_content_day` (
  `stat_date` date NOT NULL,
  `mid` tinyint(3) unsigned NOT NULL COMMENT '1视频2文章8漫画',
  `content_id` int(10) unsigned NOT NULL,
  `type_id` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '分类，冗余便于按类分析',
  `view_pv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `view_uv` bigint(20) unsigned NOT NULL DEFAULT '0',
  `play_or_read_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '播放/阅读次数（按业务定义）',
  `avg_stay_ms` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '平均停留',
  `bounce_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '仅访问该内容即离开的会话数（任务算）',
  `collect_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '收藏新增',
  `want_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '想看新增',
  `order_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '关联订单数（付费转化）',
  `order_amount` decimal(14,2) unsigned NOT NULL DEFAULT '0.00',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`stat_date`,`mid`,`content_id`),
  KEY `idx_date_type` (`stat_date`,`type_id`),
  KEY `idx_hot` (`stat_date`,`view_pv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-内容按日效果';


-- -----------------------------------------------------------------------------
-- Table structure for mac_analytics_retention_cohort
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `mac_analytics_retention_cohort`;
CREATE TABLE `mac_analytics_retention_cohort` (
  `cohort_date` date NOT NULL COMMENT 'cohort 基准日（常用：注册日）',
  `cohort_type` varchar(16) NOT NULL DEFAULT 'register',
  `return_day` smallint(5) unsigned NOT NULL COMMENT '回访间隔天 0=当日 1=次日',
  `user_cnt` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '该日仍活跃用户数',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`cohort_date`,`cohort_type`,`return_day`),
  KEY `idx_cohort` (`cohort_date`,`cohort_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='运营统计-留存 cohort';

-- ----------------------------
-- Table structure for mac_task
-- ----------------------------
DROP TABLE IF EXISTS `mac_task`;
CREATE TABLE `mac_task` (
  `task_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_name` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `task_type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '任务类型 1=每日任务 2=新手任务',
  `task_action` varchar(50) NOT NULL DEFAULT '' COMMENT '任务动作标识',
  `task_icon` varchar(255) NOT NULL DEFAULT '' COMMENT '任务图标',
  `task_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '任务描述',
  `task_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '奖励积分',
  `task_target` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '目标次数',
  `task_sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `task_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=启用',
  `task_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `task_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`task_id`),
  KEY `task_type` (`task_type`),
  UNIQUE KEY `task_action` (`task_action`),
  KEY `task_status` (`task_status`),
  KEY `task_sort` (`task_sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务定义表';

-- ----------------------------
-- Default data for mac_task
-- ----------------------------
INSERT INTO `mac_task` (`task_name`,`task_type`,`task_action`,`task_desc`,`task_points`,`task_target`,`task_sort`,`task_status`,`task_time_add`,`task_time`) VALUES
('每日签到',1,'daily_sign','每天签到获得积分奖励',5,1,1,1,0,0),
('观看影片',1,'watch_vod','每日观看3部影片',3,3,2,1,0,0),
('分享影片',1,'share_vod','每日分享1次影片到社交平台',2,1,3,1,0,0),
('发表评论',1,'post_comment','每日发表1条评论',2,1,4,1,0,0),
('绑定手机',2,'bind_phone','绑定手机号码',20,1,1,1,0,0),
('绑定邮箱',2,'bind_email','绑定电子邮箱',20,1,2,1,0,0),
('设置头像',2,'set_portrait','上传个人头像',10,1,3,1,0,0),
('完善资料',2,'complete_profile','填写个人昵称等资料',10,1,4,1,0,0),
('首次充值',2,'first_pay','完成首次充值',50,1,5,1,0,0);

-- ----------------------------
-- Table structure for mac_task_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_task_log`;
CREATE TABLE `mac_task_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `task_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '任务ID',
  `task_action` varchar(50) NOT NULL DEFAULT '' COMMENT '任务动作标识',
  `log_progress` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '当前进度',
  `log_status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态 0=进行中 1=已完成待领取 2=已领取',
  `log_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',
  `log_date` date NOT NULL COMMENT '任务日期',
  `log_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '记录时间',
  `log_claim_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '领取奖励时间',
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `user_task_date` (`user_id`, `task_id`, `log_date`),
  KEY `user_id` (`user_id`),
  KEY `task_id` (`task_id`),
  KEY `log_status` (`log_status`),
  KEY `log_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户任务记录表';

-- ----------------------------
-- Table structure for mac_sign_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_sign_log`;
CREATE TABLE `mac_sign_log` (
  `sign_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `sign_date` date NOT NULL COMMENT '签到日期',
  `sign_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '签到时间戳',
  `sign_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',
  `sign_serial_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '连续签到天数',
  PRIMARY KEY (`sign_id`),
  UNIQUE KEY `user_date` (`user_id`, `sign_date`),
  KEY `user_id` (`user_id`),
  KEY `sign_date` (`sign_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到记录表';

-- ----------------------------
-- Table structure for mac_sign_milestone
-- ----------------------------
DROP TABLE IF EXISTS `mac_sign_milestone`;
CREATE TABLE `mac_sign_milestone` (
  `milestone_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `milestone_name` varchar(100) NOT NULL DEFAULT '' COMMENT '里程碑名称',
  `milestone_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '所需连续签到天数',
  `milestone_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '奖励积分',
  `milestone_icon` varchar(255) NOT NULL DEFAULT '' COMMENT '里程碑图标',
  `milestone_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '里程碑描述',
  `milestone_sort` int(10) NOT NULL DEFAULT '0' COMMENT '排序',
  `milestone_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=启用',
  `milestone_time_add` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `milestone_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`milestone_id`),
  KEY `milestone_days` (`milestone_days`),
  KEY `milestone_status` (`milestone_status`),
  KEY `milestone_sort` (`milestone_sort`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='签到里程碑定义表';

-- ----------------------------
-- Default data for mac_sign_milestone
-- ----------------------------
INSERT INTO `mac_sign_milestone` (`milestone_name`,`milestone_days`,`milestone_points`,`milestone_desc`,`milestone_sort`,`milestone_status`,`milestone_time_add`,`milestone_time`) VALUES
('连续签到3天', 3, 5, '连续签到3天可领取5个金币', 1, 1, 0, 0),
('连续签到10天', 10, 10, '连续签到10天可领取10个金币', 2, 1, 0, 0),
('连续签到20天', 20, 20, '连续签到20天可领取20个金币', 3, 1, 0, 0),
('连续签到35天', 35, 30, '连续签到35天可领取30个金币', 4, 1, 0, 0),
('连续签到55天', 55, 50, '连续签到55天可领取50个金币', 5, 1, 0, 0),
('连续签到85天', 85, 100, '连续签到85天可领取100个金币', 6, 1, 0, 0);

-- ----------------------------
-- Table structure for mac_sign_milestone_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_sign_milestone_log`;
CREATE TABLE `mac_sign_milestone_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `milestone_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '里程碑ID',
  `milestone_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '里程碑所需天数',
  `log_points` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '获得积分',
  `log_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '领取时间',
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `user_milestone` (`user_id`, `milestone_id`),
  KEY `user_id` (`user_id`),
  KEY `milestone_id` (`milestone_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='签到里程碑领取记录表';
-- Table structure for mac_ext_provider
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_provider`;
CREATE TABLE `mac_ext_provider` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source provider config';

-- ----------------------------
-- Table structure for mac_ext_source_item
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_source_item`;
CREATE TABLE `mac_ext_source_item` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source normalized items';

-- ----------------------------
-- Table structure for mac_ext_source_map
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_source_map`;
CREATE TABLE `mac_ext_source_map` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source to CMS mapping';

-- ----------------------------
-- Table structure for mac_ext_search_cache
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_search_cache`;
CREATE TABLE `mac_ext_search_cache` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External search cache';

-- ----------------------------
-- Table structure for mac_ext_sync_job
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_sync_job`;
CREATE TABLE `mac_ext_sync_job` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source sync jobs';

-- ----------------------------
-- Table structure for mac_ext_sync_log
-- ----------------------------
DROP TABLE IF EXISTS `mac_ext_sync_log`;
CREATE TABLE `mac_ext_sync_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='External source sync logs';

-- ----------------------------
-- Table structure for mac_chatroom
-- ----------------------------
DROP TABLE IF EXISTS `mac_chatroom`;
CREATE TABLE `mac_chatroom` (
  `chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vod_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片ID(聊天室房间)',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `chat_content` varchar(500) NOT NULL DEFAULT '' COMMENT '聊天内容',
  `chat_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',
  `chat_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间',
  `chat_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=正常',
  `chat_report` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '举报次数',
  PRIMARY KEY (`chat_id`),
  KEY `vod_id` (`vod_id`),
  KEY `user_id` (`user_id`),
  KEY `chat_time` (`chat_time`),
  KEY `chat_status` (`chat_status`),
  KEY `vod_chat` (`vod_id`, `chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天室消息表';

-- ----------------------------
-- Table structure for mac_danmaku
-- ----------------------------
DROP TABLE IF EXISTS `mac_danmaku`;
CREATE TABLE `mac_danmaku` (
  `danmaku_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vod_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '影片ID',
  `vod_sid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '播放源ID',
  `vod_nid` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '集数ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `user_name` varchar(30) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `danmaku_time` float unsigned NOT NULL DEFAULT '0' COMMENT '弹幕出现的影片时间点(秒)',
  `danmaku_type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '弹幕类型 0=滚动 1=顶部 2=底部',
  `danmaku_color` varchar(10) NOT NULL DEFAULT '#FFFFFF' COMMENT '弹幕颜色',
  `danmaku_text` varchar(200) NOT NULL DEFAULT '' COMMENT '弹幕内容',
  `danmaku_ip` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'IP',
  `danmaku_send_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '发送时间戳',
  `danmaku_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 0=禁用 1=正常',
  `danmaku_report` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '举报次数',
  PRIMARY KEY (`danmaku_id`),
  KEY `vod_id` (`vod_id`),
  KEY `vod_episode` (`vod_id`, `vod_sid`, `vod_nid`),
  KEY `user_id` (`user_id`),
  KEY `danmaku_send_time` (`danmaku_send_time`),
  KEY `danmaku_status` (`danmaku_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='弹幕表';
