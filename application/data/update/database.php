<?php

/*2019.1000.1017*/
if(empty($col_list[$pre.'type']['type_logo'])){
    $sql .= "ALTER TABLE `mac_type` ADD `type_logo`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_pic`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `type_jumpurl`  VARCHAR( 150 )  NOT NULL DEFAULT  '';";
    $sql .="\r";
}

/*2019.1000.1011*/
if(empty($col_list[$pre.'collect']['collect_filter'])){
    $sql .= "ALTER TABLE `mac_collect` ADD `collect_filter` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `collect_filter_from`  VARCHAR( 255 )  NOT NULL DEFAULT  '',ADD `collect_opt` tinyint( 1 )  NOT NULL DEFAULT '0';";
    $sql .="\r";
}

/*2019.1000.1007*/
if(empty($col_list[$pre.'vod']['vod_plot'])){
    $sql .= "ALTER TABLE `mac_vod` ADD `vod_plot` tinyint( 1 )  NOT NULL DEFAULT '0',ADD `vod_plot_name`  mediumtext  NOT NULL ,ADD `vod_plot_detail` mediumtext  NOT NULL ;";
    $sql .="\r";
}

/*2019.02.21.1001*/
if(empty($col_list[$pre.'user']['user_reg_ip'])){
    $sql .= "ALTER TABLE  `mac_user` ADD  `user_reg_ip` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_reg_time`;";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_behind'])){
    $sql .= "ALTER TABLE  `mac_vod` ADD  `vod_behind` VARCHAR( 100 )  NOT NULL DEFAULT  '' AFTER  `vod_writer`;";
    $sql .="\r";
}

/*2019.01.19.1001*/
if(empty($col_list[$pre.'user']['user_points_froze'])){
    $sql .= "ALTER TABLE  `mac_user` ADD  `user_points_froze` INT( 10 ) unsigned NOT NULL DEFAULT  '0' AFTER  `user_points`;";
    $sql .="\r";
}
/*2018.12.25.1001*/
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
    $sql .= "CREATE TABLE `mac_plog` (
  `plog_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id_1` int(10) unsigned NOT NULL DEFAULT '0',
  `plog_type` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `plog_points` smallint(6) unsigned NOT NULL DEFAULT '0',
  `plog_time` int(10) unsigned NOT NULL DEFAULT '0',
  `plog_remarks` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`plog_id`),
  KEY `user_id` (`user_id`),
  KEY `plog_type` (`plog_type`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}

if(empty($col_list[$pre.'cash'])){
    $sql .= "CREATE TABLE `mac_cash` (
  `cash_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `cash_status` tinyint(1) unsigned NOT NULL DEFAULT '0',
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
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
    $sql .="\r";
}
