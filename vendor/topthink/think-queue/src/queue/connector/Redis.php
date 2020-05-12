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
use think\helper\Str;
use think\queue\Connector;
use think\queue\job\Redis as RedisJob;

class Redis extends Connector
{
    /** @var  \Redis */
    protected $redis;

    protected $options = [
        'expire'     => 60,
        'default'    => 'default',
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'persistent' => false
    ];

    public function __construct($options)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('redis扩展未安装');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $func        = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->redis = new \Redis;
        $this->redis->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ('' != $this->options['password']) {
            $this->redis->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->redis->select($this->options['select']);
        }
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        $payload = $this->createPayload($job, $data);

        $this->redis->zAdd($this->getQueue($queue) . ':delayed', time() + $delay, $payload);
    }

    public function pop($queue = null)
    {
        $original = $queue ?: $this->options['default'];

        $queue = $this->getQueue($queue);

        $this->migrateExpiredJobs($queue . ':delayed', $queue, false);

        if (!is_null($this->options['expire'])) {
            $this->migrateExpiredJobs($queue . ':reserved', $queue);
        }

        $job = $this->redis->lPop($queue);

        if ($job !== false) {
            $this->redis->zAdd($queue . ':reserved', time() + $this->options['expire'], $job);

            return new RedisJob($this, $job, $original);
        }
    }

    /**
     * 重新发布任务
     *
     * @param  string $queue
     * @param  string $payload
     * @param  int    $delay
     * @param  int    $attempts
     * @return void
     */
    public function release($queue, $payload, $delay, $attempts)
    {
        $payload = $this->setMeta($payload, 'attempts', $attempts);

        $this->redis->zAdd($this->getQueue($queue) . ':delayed', time() + $delay, $payload);
    }

    public function pushRaw($payload, $queue = null)
    {
        $this->redis->rPush($this->getQueue($queue), $payload);

        return json_decode($payload, true)['id'];
    }

    protected function createPayload($job, $data = '', $queue = null)
    {
        $payload = $this->setMeta(
            parent::createPayload($job, $data), 'id', $this->getRandomId()
        );

        return $this->setMeta($payload, 'attempts', 1);
    }

    /**
     * 删除任务
     *
     * @param  string $queue
     * @param  string $job
     * @return void
     */
    public function deleteReserved($queue, $job)
    {
        $this->redis->zRem($this->getQueue($queue) . ':reserved', $job);
    }

    /**
     * 移动延迟任务
     *
     * @param string $from
     * @param string $to
     * @param bool   $attempt
     */
    public function migrateExpiredJobs($from, $to, $attempt = true)
    {
        $this->redis->watch($from);

        $jobs = $this->getExpiredJobs(
            $from, $time = time()
        );
        if (count($jobs) > 0) {
            $this->transaction(function () use ($from, $to, $time, $jobs, $attempt) {
                $this->removeExpiredJobs($from, $time);
                $this->pushExpiredJobsOntoNewQueue($to, $jobs, $attempt);
            });
        }
        $this->redis->unwatch();
    }

    /**
     * redis事务
     * @param \Closure $closure
     */
    protected function transaction(\Closure $closure)
    {
        $this->redis->multi();
        try {
            call_user_func($closure);
            if (!$this->redis->exec()) {
                $this->redis->discard();
            }
        } catch (Exception $e) {
            $this->redis->discard();
        }
    }

    /**
     * 获取所有到期任务
     *
     * @param  string $from
     * @param  int    $time
     * @return array
     */
    protected function getExpiredJobs($from, $time)
    {
        return $this->redis->zRangeByScore($from, '-inf', $time);
    }

    /**
     * 删除过期任务
     *
     * @param  string $from
     * @param  int    $time
     * @return void
     */
    protected function removeExpiredJobs($from, $time)
    {
        $this->redis->zRemRangeByScore($from, '-inf', $time);
    }

    /**
     * 重新发布到期任务
     *
     * @param  string  $to
     * @param  array   $jobs
     * @param  boolean $attempt
     */
    protected function pushExpiredJobsOntoNewQueue($to, $jobs, $attempt = true)
    {
        if ($attempt) {
            foreach ($jobs as &$job) {
                $attempts = json_decode($job, true)['attempts'];
                $job      = $this->setMeta($job, 'attempts', $attempts + 1);
            }
        }
        call_user_func_array([$this->redis, 'rPush'], array_merge([$to], $jobs));
    }

    /**
     * 随机id
     *
     * @return string
     */
    protected function getRandomId()
    {
        return Str::random(32);
    }

    /**
     * 获取队列名
     *
     * @param  string|null $queue
     * @return string
     */
    protected function getQueue($queue)
    {
        return 'queues:' . ($queue ?: $this->options['default']);
    }
}
