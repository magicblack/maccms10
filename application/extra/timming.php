<?php
return array (
  'aa' => 
  array (
    'id' => 'aa',
    'status' => '0',
    'name' => 'aa',
    'des' => '采集今日数据',
    'file' => 'collect',
    'param' => 'ac=cjday&xt=1&ct=&rday=24&cjflag=tv6_com&cjurl=http://cj2.tv6.com/mox/inc/youku.php',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
    'runtime' => 1597629775,
  ),
  'bb' => 
  array (
    'status' => '0',
    'name' => 'bb',
    'des' => '生成首页',
    'file' => 'make',
    'param' => 'ac=index',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
    'id' => 'bb',
    'runtime' => 1535348998,
  ),
  'analytics_hour' =>
  array (
    'id' => 'analytics_hour',
    'status' => '1',
    'name' => 'analytics_hour',
    'des' => '运营统计小时聚合',
    'file' => 'analytics',
    'param' => 'mode=hour',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
    'runtime' => 0,
  ),
  'analytics_day' =>
  array (
    'id' => 'analytics_day',
    'status' => '1',
    'name' => 'analytics_day',
    'des' => '运营统计日聚合',
    'file' => 'analytics',
    'param' => 'mode=day',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '01',
    'runtime' => 0,
  ),
  'tmdb_sync' =>
  array (
    'status' => '0',
    'name' => 'tmdb_sync',
    'des' => 'TMDB 外部资源同步',
    'file' => 'extsync',
    'param' => 'provider=tmdb',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
  ),
  'douban' =>
  array (
    'status' => '0',
    'name' => 'douban',
    'des' => '豆瓣外部资源同步',
    'file' => 'extsync',
    'param' => 'provider=douban',
    'weeks' => '1,2,3,4,5,6,0',
    'hours' => '00,01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23',
  ),
);