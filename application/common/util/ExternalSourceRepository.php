<?php
namespace app\common\util;

use think\Db;

class ExternalSourceRepository
{
    public function saveProviderSnapshot($code, $name, array $conf)
    {
        $code = strtolower(trim((string)$code));
        if ($code === '') {
            return;
        }
        $now = time();
        $row = Db::name('ext_provider')->where('provider_code', $code)->find();
        $data = [
            'provider_name' => (string)$name,
            'provider_enabled' => (string)(isset($conf['enabled']) ? $conf['enabled'] : '0') === '1' ? 1 : 0,
            'provider_type' => 'api',
            'provider_conf' => json_encode($conf, JSON_UNESCAPED_UNICODE),
            'provider_time_update' => $now,
        ];
        if (empty($row)) {
            $data['provider_code'] = $code;
            $data['provider_time_add'] = $now;
            Db::name('ext_provider')->insert($data);
            return;
        }
        Db::name('ext_provider')->where('provider_code', $code)->update($data);
    }

    public function getSearchCache($cacheKey)
    {
        $row = Db::name('ext_search_cache')->where('cache_key', $cacheKey)->find();
        if (empty($row)) {
            return [];
        }
        if (intval($row['expire_time']) > 0 && intval($row['expire_time']) < time()) {
            return [];
        }
        $payload = json_decode((string)$row['result_payload'], true);
        return is_array($payload) ? $payload : [];
    }

    public function saveSearchCache($cacheKey, $queryWord, $queryMid, $providerCode, array $results, $ttl)
    {
        $now = time();
        $data = [
            'query_word' => (string)$queryWord,
            'query_mid' => intval($queryMid),
            'provider_code' => (string)$providerCode,
            'result_total' => count($results),
            'result_payload' => json_encode($results, JSON_UNESCAPED_UNICODE),
            'expire_time' => $now + max(60, intval($ttl)),
            'cache_time_update' => $now,
        ];
        $row = Db::name('ext_search_cache')->where('cache_key', $cacheKey)->find();
        if (empty($row)) {
            $data['cache_key'] = $cacheKey;
            $data['cache_time_add'] = $now;
            Db::name('ext_search_cache')->insert($data);
            return;
        }
        Db::name('ext_search_cache')->where('cache_key', $cacheKey)->update($data);
    }

    public function saveItems($providerCode, array $items)
    {
        $now = time();
        $saved = 0;
        foreach ($items as $item) {
            $itemKey = isset($item['item_key']) ? trim((string)$item['item_key']) : '';
            if ($itemKey === '') {
                continue;
            }
            $data = [
                'provider_code' => (string)$providerCode,
                'item_key' => $itemKey,
                'item_mid' => intval(isset($item['item_mid']) ? $item['item_mid'] : 0),
                'item_title' => (string)(isset($item['item_title']) ? $item['item_title'] : ''),
                'item_subtitle' => (string)(isset($item['item_subtitle']) ? $item['item_subtitle'] : ''),
                'item_snippet' => (string)(isset($item['item_snippet']) ? $item['item_snippet'] : ''),
                'item_url' => (string)(isset($item['item_url']) ? $item['item_url'] : ''),
                'item_cover' => (string)(isset($item['item_cover']) ? $item['item_cover'] : ''),
                'item_score' => floatval(isset($item['item_score']) ? $item['item_score'] : 0),
                'item_release_date' => (string)(isset($item['item_release_date']) ? $item['item_release_date'] : ''),
                'item_payload' => (string)(isset($item['item_payload']) ? $item['item_payload'] : ''),
                'item_time_update' => $now,
            ];
            $exist = Db::name('ext_source_item')->where(['provider_code' => $providerCode, 'item_key' => $itemKey])->find();
            if (empty($exist)) {
                $data['item_time_add'] = $now;
                Db::name('ext_source_item')->insert($data);
            } else {
                Db::name('ext_source_item')->where(['provider_code' => $providerCode, 'item_key' => $itemKey])->update($data);
            }
            $saved++;
        }
        return $saved;
    }

    public function upsertSyncJob($providerCode, $interval)
    {
        $providerCode = strtolower(trim((string)$providerCode));
        if ($providerCode === '') {
            return;
        }
        $now = time();
        $job = Db::name('ext_sync_job')->where(['provider_code' => $providerCode, 'job_type' => 'feed_recent'])->find();
        if (empty($job)) {
            Db::name('ext_sync_job')->insert([
                'provider_code' => $providerCode,
                'job_type' => 'feed_recent',
                'job_status' => 1,
                'job_param' => '',
                'job_last_run' => 0,
                'job_next_run' => $now,
                'job_interval' => max(300, intval($interval)),
                'job_retry' => 0,
                'job_time_add' => $now,
                'job_time_update' => $now,
            ]);
            return;
        }
        Db::name('ext_sync_job')->where('job_id', intval($job['job_id']))->update([
            'job_status' => 1,
            'job_interval' => max(300, intval($interval)),
            'job_time_update' => $now,
        ]);
    }

    public function getDueSyncJobs($providerCode = '')
    {
        $where = ['job_status' => 1];
        if ($providerCode !== '') {
            $where['provider_code'] = strtolower(trim((string)$providerCode));
        }
        return Db::name('ext_sync_job')
            ->where($where)
            ->where('job_next_run', '<=', time())
            ->order('job_next_run asc,job_id asc')
            ->select();
    }

    public function updateJobSchedule($jobId, $interval, $retry = 0)
    {
        $now = time();
        Db::name('ext_sync_job')->where('job_id', intval($jobId))->update([
            'job_last_run' => $now,
            'job_next_run' => $now + max(300, intval($interval)),
            'job_retry' => max(0, intval($retry)),
            'job_time_update' => $now,
        ]);
    }

    public function addSyncLog($jobId, $providerCode, $status, $msg, $total, $success)
    {
        Db::name('ext_sync_log')->insert([
            'job_id' => intval($jobId),
            'provider_code' => (string)$providerCode,
            'log_status' => intval($status),
            'log_msg' => mb_substr((string)$msg, 0, 1000, 'UTF-8'),
            'log_total' => max(0, intval($total)),
            'log_success' => max(0, intval($success)),
            'log_time_add' => time(),
        ]);
    }
}

