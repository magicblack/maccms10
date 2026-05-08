<?php
namespace app\common\model;

use think\Db;
use think\Cache;

class VodSearch extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'vod_search';
    // 最大Id数量，使用IN查询时，超过一定数量，查询不使用索引了
    public $maxIdCount = 1000;
    private $updateTopCount = 50000;

    /** @var array<string, int[]> 单次 PHP 请求内按 search_key 去重，避免采集等循环对同一词重复 LIKE / 写 vod_search */
    private static $getResultIdListMemo = [];


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
                $search_word = trim((string)$search_word);
                if ($search_word === '') {
                    continue;
                }
                $chunk = $this->getResultIdList($search_word, $search_field);
                $id_list = array_merge($id_list, is_array($chunk) ? $chunk : []);
            }
            $id_list = array_values(array_unique(array_map('intval', $id_list)));

            return $id_list;
        }
        $search_key = md5($search_word . '@' . $search_field);
        if (isset(self::$getResultIdListMemo[$search_key])) {
            return self::$getResultIdListMemo[$search_key];
        }
        $where = ['search_key' => $search_key];
        $search_row = $this->where($where)->field("search_result_ids, search_hit_count")->find();
        if (empty($search_row)) {
            $where_vod = [];
            $where_vod[$search_field] = ['LIKE', '%' . $search_word . '%'];
            // 仅已发布；回收站过滤在 listData 中由 Vod 模型处理，此处不重复 merge 以免依赖 protected API
            try {
                $id_list = Db::name('Vod')
                    ->where('vod_status', 1)
                    ->where('vod_recycle_time', 0)
                    ->where($where_vod)
                    ->order('vod_id ASC')
                    ->column('vod_id');
            } catch (\Throwable $e) {
                $id_list = Db::name('Vod')
                    ->where('vod_status', 1)
                    ->where($where_vod)
                    ->order('vod_id ASC')
                    ->column('vod_id');
            }
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
        self::$getResultIdListMemo[$search_key] = $id_list;

        return $id_list;
    }

    /**
     * 前端是否开启
     */
    public function isFrontendEnabled()
    {
        $config = config('maccms');
        // 未设置时，默认关闭
        if (!isset($config['app']['vod_search_optimise'])) {
            return false;
        }
        $list = explode('|', $config['app']['vod_search_optimise']);
        return in_array('frontend', $list);
    }

    /**
     * 采集是否开启
     */
    public function isCollectEnabled()
    {
        $config = config('maccms');
        // 未设置时，默认关闭
        if (!isset($config['app']['vod_search_optimise'])) {
            return false;
        }
        $list = explode('|', $config['app']['vod_search_optimise']);
        return in_array('collect', $list);
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
        $vid = isset($vod['vod_id']) ? (int)$vod['vod_id'] : 0;
        if ($vid <= 0) {
            return;
        }
        foreach ($list as $row) {
            $searchKey = isset($row['search_key']) ? (string)$row['search_key'] : '';
            if ($searchKey === '' || !preg_match('/^[a-f0-9]{32}$/i', $searchKey)) {
                continue;
            }
            foreach (explode('|', (string)$row['search_field']) as $field) {
                $field = trim($field);
                if ($field === '' || !isset($vod[$field]) || $vod[$field] === '' || $vod[$field] === null) {
                    continue;
                }
                if (stripos((string)$vod[$field], (string)$row['search_word']) === false) {
                    continue;
                }
                $this->appendVodIdToSearchCacheRow($searchKey, $vid, $time_now);
            }
        }
    }

    /**
     * 向缓存行追加 vod_id；已存在则只刷新 search_update_time，并保证 search_result_count 与 id 列表一致。
     */
    private function appendVodIdToSearchCacheRow($searchKey, $vid, $time_now)
    {
        $searchKey = (string)$searchKey;
        $vid = (int)$vid;
        $time_now = (int)$time_now;
        if ($searchKey === '' || $vid <= 0) {
            return;
        }
        $raw = Db::name('vod_search')->where('search_key', $searchKey)->value('search_result_ids');
        $ids = [];
        foreach (explode(',', (string)$raw) as $p) {
            $p = (int)trim($p);
            if ($p > 0) {
                $ids[$p] = $p;
            }
        }
        if (isset($ids[$vid])) {
            Db::name('vod_search')->where('search_key', $searchKey)->update([
                'search_update_time' => $time_now,
            ]);

            return;
        }
        $ids[$vid] = $vid;
        $ids = array_values($ids);
        Db::name('vod_search')->where('search_key', $searchKey)->update([
            'search_update_time'  => $time_now,
            'search_result_count' => count($ids),
            'search_result_ids'   => implode(',', $ids),
        ]);
    }

    /**
     * 获取结果缓存的分钟数，后台配置覆盖默认值
     */
    public function getResultCacheMinutes($config = []) {
        // 默认14天
        $minutes = 20160;
        $config = $config ?: config('maccms');
        if (isset($config['app']['vod_search_optimise_cache_minutes']) && (int)$config['app']['vod_search_optimise_cache_minutes'] > 0) {
            $minutes = (int)$config['app']['vod_search_optimise_cache_minutes'];
        }
        return $minutes;
    }

    /**
     * 清理老的数据
     */
    public function clearOldResult($force = false) 
    {
        // 清理多久前的
        $clear_seconds = $this->getResultCacheMinutes() * 60;
        // 设置间隔，每天最多清理1次
        $cach_name = 'interval_vs_clear_old_v1_' . $clear_seconds;
        $cache_data = Cache::get($cach_name);
        if ($force === false && !empty($cache_data)) {
            return;
        }
        Cache::set($cach_name, 1, min($clear_seconds, 86400));
        // vod_actor在采集的时候可提高效率，暂不清理
        $where = [
            'search_field'       => ['neq', 'vod_actor'],
            'search_update_time' => ['lt', time() - $clear_seconds],
        ];
        // 后台强制清理时，都清掉
        if ($force === true) {
            unset($where['search_field']);
        }
        $this->where($where)->delete();
    }

}
