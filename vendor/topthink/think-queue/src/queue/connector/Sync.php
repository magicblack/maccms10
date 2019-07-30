<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\queue\connector;

use Exception;
use think\queue\Connector;
use think\queue\job\Sync as SyncJob;
use Throwable;

class Sync extends Connector
{

    public function push($job, $data = '', $queue = null)
    {
        $queueJob = $this->resolveJob($this->createPayload($job, $data, $queue));

        try {
            set_time_limit(0);
            $queueJob->fire();
        } catch (Exception $e) {
            $queueJob->failed();

            throw $e;
        } catch (Throwable $e) {
            $queueJob->failed();

            throw $e;
        }

        return 0;
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null)
    {

    }

    protected function resolveJob($payload)
    {
        return new SyncJob($payload);
    }

}
