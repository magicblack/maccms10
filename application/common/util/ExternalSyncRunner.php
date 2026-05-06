<?php
namespace app\common\util;

class ExternalSyncRunner
{
    private $repo;
    private $registry;

    public function __construct()
    {
        $this->repo = new ExternalSourceRepository();
        $this->registry = new ExternalSourceProviderRegistry();
    }

    public function bootstrapJobs(array $extCfg)
    {
        $interval = max(300, intval(isset($extCfg['sync_interval']) ? $extCfg['sync_interval'] : 21600));
        $providers = $this->registry->listEnabledProviders($extCfg);
        foreach ($providers as $code => $provider) {
            $providerConf = isset($extCfg['sources'][$code]) && is_array($extCfg['sources'][$code]) ? $extCfg['sources'][$code] : [];
            $this->repo->saveProviderSnapshot($code, $provider->getLabel(), $providerConf);
            $this->repo->upsertSyncJob($code, $interval);
        }
    }

    public function runDueJobs(array $extCfg, $providerCode = '')
    {
        $this->bootstrapJobs($extCfg);
        $jobs = $this->repo->getDueSyncJobs($providerCode);
        if (empty($jobs)) {
            return ['total' => 0, 'success' => 0, 'msg' => 'no due jobs'];
        }
        $providers = $this->registry->listEnabledProviders($extCfg);
        $total = 0;
        $success = 0;
        foreach ($jobs as $job) {
            $total++;
            $code = strtolower((string)$job['provider_code']);
            if (!isset($providers[$code])) {
                $this->repo->addSyncLog($job['job_id'], $code, 0, 'provider not enabled', 0, 0);
                $this->repo->updateJobSchedule($job['job_id'], intval($job['job_interval']), intval($job['job_retry']) + 1);
                continue;
            }
            try {
                $rows = $providers[$code]->fetchRecent(['limit' => 20]);
                $saved = $this->repo->saveItems($code, $rows);
                $this->repo->addSyncLog($job['job_id'], $code, 1, 'sync ok', count($rows), $saved);
                $this->repo->updateJobSchedule($job['job_id'], intval($job['job_interval']), 0);
                $success++;
            } catch (\Throwable $e) {
                $this->repo->addSyncLog($job['job_id'], $code, 0, $e->getMessage(), 0, 0);
                $this->repo->updateJobSchedule($job['job_id'], intval($job['job_interval']), intval($job['job_retry']) + 1);
            }
        }
        return ['total' => $total, 'success' => $success, 'msg' => 'ok'];
    }
}

