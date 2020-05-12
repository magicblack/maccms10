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

namespace think\queue\job;

use think\queue\Job;
use think\queue\connector\Topthink as TopthinkQueue;

class Topthink extends Job
{

    /**
     * The Iron queue instance.
     *
     * @var TopthinkQueue
     */
    protected $topthink;

    /**
     * The IronMQ message instance.
     *
     * @var object
     */
    protected $job;

    public function __construct(TopthinkQueue $topthink, $job, $queue)
    {
        $this->topthink      = $topthink;
        $this->job           = $job;
        $this->queue         = $queue;
        $this->job->attempts = $this->job->attempts + 1;
    }

    /**
     * Fire the job.
     * @return void
     */
    public function fire()
    {
        $this->resolveAndFire(json_decode($this->job->payload, true));
    }

    /**
     * Get the number of times the job has been attempted.
     * @return int
     */
    public function attempts()
    {
        return (int) $this->job->attempts;
    }

    public function delete()
    {
        parent::delete();

        $this->topthink->deleteMessage($this->queue, $this->job->id);
    }

    public function release($delay = 0)
    {
        parent::release($delay);

        $this->delete();

        $this->topthink->release($this->queue, $this->job, $delay);
    }

    /**
     * Get the raw body string for the job.
     * @return string
     */
    public function getRawBody()
    {
        return $this->job->payload;
    }

}
