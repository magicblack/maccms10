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

use Closure;
use think\Process;

class Listener
{

    /**
     * @var string
     */
    protected $commandPath;

    /**
     * @var int
     */
    protected $sleep = 3;

    /**
     * @var int
     */
    protected $maxTries = 0;

    /**
     * @var string
     */
    protected $workerCommand;

    /**
     * @var \Closure|null
     */
    protected $outputHandler;

    /**
     * @param  string $commandPath
     */
    public function __construct($commandPath)
    {
        $this->commandPath   = $commandPath;
        $this->workerCommand =
            '"' . PHP_BINARY . '" think queue:work --queue="%s" --delay=%s --memory=%s --sleep=%s --tries=%s';
    }

    /**
     * @param  string $queue
     * @param  string $delay
     * @param  string $memory
     * @param  int    $timeout
     * @return void
     */
    public function listen($queue, $delay, $memory, $timeout = 60)
    {
        $process = $this->makeProcess($queue, $delay, $memory, $timeout);

        while (true) {
            $this->runProcess($process, $memory);
        }
    }

    /**
     * @param \Think\Process $process
     * @param  int           $memory
     */
    public function runProcess(Process $process, $memory)
    {
        $process->run(function ($type, $line) {
            $this->handleWorkerOutput($type, $line);
        });

        if ($this->memoryExceeded($memory)) {
            $this->stop();
        }
    }

    /**
     * @param  string $queue
     * @param  int    $delay
     * @param  int    $memory
     * @param  int    $timeout
     * @return \think\Process
     */
    public function makeProcess($queue, $delay, $memory, $timeout)
    {
        $string  = $this->workerCommand;
        $command = sprintf($string, $queue, $delay, $memory, $this->sleep, $this->maxTries);

        return new Process($command, $this->commandPath, null, null, $timeout);
    }

    /**
     * @param  int    $type
     * @param  string $line
     * @return void
     */
    protected function handleWorkerOutput($type, $line)
    {
        if (isset($this->outputHandler)) {
            call_user_func($this->outputHandler, $type, $line);
        }
    }

    /**
     * @param  int $memoryLimit
     * @return bool
     */
    public function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * @return void
     */
    public function stop()
    {
        die;
    }

    /**
     * @param  \Closure $outputHandler
     * @return void
     */
    public function setOutputHandler(Closure $outputHandler)
    {
        $this->outputHandler = $outputHandler;
    }

    /**
     * @return int
     */
    public function getSleep()
    {
        return $this->sleep;
    }

    /**
     * @param  int $sleep
     * @return void
     */
    public function setSleep($sleep)
    {
        $this->sleep = $sleep;
    }

    /**
     * @param  int $tries
     * @return void
     */
    public function setMaxTries($tries)
    {
        $this->maxTries = $tries;
    }
}
