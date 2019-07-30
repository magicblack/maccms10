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

use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Restart extends Command
{
    public function configure()
    {
        $this->setName('queue:restart')->setDescription('Restart queue worker daemons after their current job');
    }

    public function execute(Input $input, Output $output)
    {
        Cache::set('think:queue:restart', time());
        $output->writeln("<info>Broadcasting queue restart signal.</info>");
    }
}
