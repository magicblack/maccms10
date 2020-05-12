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
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Queue;
use think\Url;

class Subscribe extends Command
{
    public function configure()
    {
        $this->setName('queue:subscribe')
            ->setDescription('Subscribe a URL to an push queue')
            ->addArgument('name', Argument::REQUIRED, 'name')
            ->addArgument('url', Argument::REQUIRED, 'The URL to be subscribed.')
            ->addArgument('queue', Argument::OPTIONAL, 'The URL to be subscribed.')
            ->addOption('option', null, Option::VALUE_IS_ARRAY | Option::VALUE_OPTIONAL, 'the options');
    }

    public function execute(Input $input, Output $output)
    {

        $url = $input->getArgument('url');
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = Url::build($url);
        }

        Queue::subscribe($input->getArgument('name'), $url, $input->getArgument('queue'), $input->getOption('option'));

        $output->write('<info>Queue subscriber added:</info> <comment>' . $input->getArgument('url') . '</comment>');
    }
}
