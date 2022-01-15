<?php
namespace app\common\model;

use think\Db;
use think\Cache;

class VodSearch extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'vod_search';
    private $resultCacheDays = 30;
    private $updateTopCount  = 100000;

    /**
     * 获取结果Id列表
     */
    public function getResultIdList($search_word, $search_field, $word_multiple = false)
    {
        $search_word = trim($search_word);
        $search_word = str_replace(',,', '', $search_word);
        if (strlen($search_word) == 0 || strlen($search_field) == 0) {
            return [];
        }
        // 如果包含多个关键词，使用递归处理
        if ($word_multiple === true) {
            $id_list = [];
            $search_word_exploded = explode(',', $search_word);
            foreach ($search_word_exploded as $search_word) {
                $id_list += $this->getResultIdList($search_word, $search_field);
            }
            $id_list = array_unique($id_list);
            return $id_list;
        }
        $search_key = md5($search_word . '@' . $search_field);
        $where = ['search_key' => $search_key];
        $search_row = $this->where($where)->field("search_result_ids, search_hit_count")->find();
        if (empty($search_row)) {
            $where_vod = [];
            $where_vod[$search_field] = ['LIKE', '%' . $search_word . '%'];
            $id_list = Db::name('Vod')->where($where_vod)->order("vod_id ASC")->column("vod_id");
            $id_list = is_array($id_list) ? $id_list : [];
            $this->insert([
                'search_key'           => $search_key,
                'search_word'          => mb_substr($search_word, 0, 128),
                'search_field'         => mb_substr($search_field, 0, 64),
                'search_hit_count'     => 1,
                'search_last_hit_time' => time(),
                'search_update_time'   => time(),
                'search_result_count'  => count($id_list),
                'search_result_ids'    => join(',', $id_list),
            ]);
        } else {
            $id_list = explode(',', (string)$search_row['search_result_ids']);
            $id_list = array_filter($id_list);
            $this->where($where)->update([
                'search_hit_count'     => $search_row['search_hit_count'] + 1,
                'search_last_hit_time' => time(),
            ]);
        }
        $id_list = array_map('intval', $id_list);
        $id_list = empty($id_list) ? [0] : $id_list;
        return $id_list;
    }

    /**
     * 检查更新搜索结果
     */
    public function checkAndUpdateTopResults($vod, $force = false)
    {
        static $list;
        if (empty($vod['vod_id'])) {
            return;
        }
        if (is_null($list)) {
            $cach_name = 'vod_search_top_result_v2_' . $this->updateTopCount;
            $list = $force ? [] : Cache::get($cach_name);
            if (empty($list)) {
                $list = $this->field("search_key, search_word, search_field")->order("search_hit_count DESC, search_last_hit_time DESC")->limit("0," . $this->updateTopCount)->select();
                $force === false && Cache::set($cach_name, $list, count($list) < ($this->updateTopCount / 10) ? 3600 : 86400);
                $this->clearOldResult();
            }
        }
        $time_now = time();
        foreach ($list as $row) {
            foreach (explode('|', $row['search_field']) as $field) {
                if (!isset($vod[$field]) || strlen($vod[$field]) == 0) {
                    continue;
                }
                if (stripos($vod[$field], $row['search_word']) === false) {
                    continue;
                }
                Db::execute("UPDATE `" . config('database.prefix') . $this->name . "` SET 
                    search_update_time='{$time_now}',
                    search_result_count=search_result_count+1,
                    search_result_ids=CONCAT(search_result_ids,',','{$vod['vod_id']}')
                WHERE search_key='{$row['search_key']}'");
            }
        }
    }

    /**
     * 清理老的数据
     */
    public function clearOldResult() 
    {
        // 设置间隔，每天最多清理1次
        $cach_name = 'interval_vod_search_clear_old_result_v1';
        $cache_data = Cache::get($cach_name);
        if (!empty($cache_data)) {
            return;
        }
        Cache::set($cach_name, 1, 86400);
        // vod_actor在采集的时候可提高效率，暂不清理
        $this->where([
            'search_field'       => ['neq', 'vod_actor'],
            'search_update_time' => ['lt', time() - $this->resultCacheDays * 86400],
        ])->delete();
    }

}
