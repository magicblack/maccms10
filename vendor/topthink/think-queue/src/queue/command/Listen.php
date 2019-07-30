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

namespace think\queue\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\queue\Listener;

class Listen extends Command
{
    /** @var  Listener */
    protected $listener;

    public function configure()
    {
        $this->setName('queue:listen')
            ->addOption('queue', null, Option::VALUE_OPTIONAL, 'The queue to listen on', null)
            ->addOption('delay', null, Option::VALUE_OPTIONAL, 'Amount of time to delay failed jobs', 0)
            ->addOption('memory', null, Option::VALUE_OPTIONAL, 'The memory limit in megabytes', 128)
            ->addOption('timeout', null, Option::VALUE_OPTIONAL, 'Seconds a job may run before timing out', 60)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, 'Seconds to wait before checking queue for jobs', 3)
            ->addOption('tries', null, Option::VALUE_OPTIONAL, 'Number of times to attempt a job before logging it failed', 0)
            ->setDescription('Listen to a given queue');
    }

    public function initialize(Input $input, Output $output)
    {
        $this->listener = new Listener(getcwd());
        $this->listener->setSleep($input->getOption('sleep'));
        $this->listener->setMaxTries($input->getOption('tries'));

        $this->listener->setOutputHandler(function ($type, $line) use ($output) {
            $output->write($line);
        });
    }

    public function execute(Input $input, Output $output)
    {
        $delay = $input->getOption('delay');

        $memory = $input->getOption('memory');

        $timeout = $input->getOption('timeout');

        $queue = $input->getOption('queue') ?: 'default';

        $this->listener->listen($queue, $delay, $memory, $timeout);
    }
}
