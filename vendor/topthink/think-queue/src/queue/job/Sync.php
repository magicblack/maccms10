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

class Sync extends Job
{
    /**
     * The queue message data.
     *
     * @var string
     */
    protected $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Fire the job.
     * @return void
     */
    public function fire()
    {
        $this->resolveAndFire(json_decode($this->payload, true));
    }

    /**
     * Get the number of times the job has been attempted.
     * @return int
     */
    public function attempts()
    {
        return 1;
    }

    /**
     * Get the raw body string for the job.
     * @return string
     */
    public function getRawBody()
    {
        return $this->payload;
    }
}
