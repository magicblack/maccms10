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

namespace think\queue;

use Exception;
use think\Hook;
use think\Queue;

class Worker
{

    /**
     * 执行下个任务
     * @param  string $queue
     * @param  int    $delay
     * @param  int    $sleep
     * @param  int    $maxTries
     * @return array
     */
    public function pop($queue = null, $delay = 0, $sleep = 3, $maxTries = 0)
    {

        $job = $this->getNextJob($queue);

        if (!is_null($job)) {
            Hook::listen('worker_before_process', $queue);
            return $this->process($job, $maxTries, $delay);
        }

        Hook::listen('worker_before_sleep', $queue);
        $this->sleep($sleep);

        return ['job' => null, 'failed' => false];
    }

    /**
     * 获取下个任务
     * @param  string $queue
     * @return Job
     */
    protected function getNextJob($queue)
    {
        if (is_null($queue)) {
            return Queue::pop();
        }

        foreach (explode(',', $queue) as $queue) {
            if (!is_null($job = Queue::pop($queue))) {
                return $job;
            }
        }
    }

    /**
     * Process a given job from the queue.
     * @param \think\queue\Job $job
     * @param  int             $maxTries
     * @param  int             $delay
     * @return array
     * @throws Exception
     */
    public function process(Job $job, $maxTries = 0, $delay = 0)
    {
        if ($maxTries > 0 && $job->attempts() > $maxTries) {
            return $this->logFailedJob($job);
        }

        try {
            $job->fire();

            return ['job' => $job, 'failed' => false];
        } catch (Exception $e) {
            if (!$job->isDeleted()) {
                $job->release($delay);
            }

            throw $e;
        }
    }

    /**
     * Log a failed job into storage.
     * @param  \Think\Queue\Job $job
     * @return array
     */
    protected function logFailedJob(Job $job)
    {
        if (!$job->isDeleted()) {
            try {
                $job->delete();
                $job->failed();
            } finally {
                Hook::listen('queue_failed', $job);
            }
        }

        return ['job' => $job, 'failed' => true];
    }

    /**
     * Sleep the script for a given number of seconds.
     * @param  int $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        sleep($seconds);
    }

}
