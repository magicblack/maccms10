<?php
namespace app\command;

use app\common\util\SeoAi;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Log;

class SeoAiGenerate extends Command
{
    protected function configure()
    {
        $this->setName('seo:generate')
            ->setDescription('Generate AI SEO metadata for content')
            ->addOption('mid', null, Option::VALUE_OPTIONAL, '1=vod,2=art', '1')
            ->addOption('limit', null, Option::VALUE_OPTIONAL, 'Batch size', '100')
            ->addOption('id', null, Option::VALUE_OPTIONAL, 'Generate only one content ID', '0');
    }

    protected function execute(Input $input, Output $output)
    {
        $mid = intval($input->getOption('mid'));
        $limit = max(1, intval($input->getOption('limit')));
        $oneId = intval($input->getOption('id'));

        if (!in_array($mid, [1, 2])) {
            $output->writeln('invalid mid, use 1 or 2');
            return;
        }

        $rows = [];
        if ($oneId > 0) {
            $rows[] = ['id' => $oneId];
        } else {
            if ($mid === 1) {
                $rows = Db::name('vod')
                    ->where(['vod_status' => 1])
                    ->field('vod_id as id')
                    ->order('vod_id desc')
                    ->limit($limit)
                    ->select();
            } else {
                $rows = Db::name('art')
                    ->where(['art_status' => 1])
                    ->field('art_id as id')
                    ->order('art_id desc')
                    ->limit($limit)
                    ->select();
            }
        }

        $ok = 0;
        $fail = 0;
        foreach ($rows as $row) {
            $id = intval($row['id']);
            try {
                $res = SeoAi::generateByMidObj($mid, $id);
            } catch (\Exception $e) {
                Log::error('AI SEO generate failed (mid=' . $mid . ', id=' . $id . '): ' . $e->getMessage());
                $fail++;
                $output->writeln('fail: ' . $id . ' - ' . $e->getMessage());
                continue;
            }
            if ($res['code'] == 1) {
                $ok++;
                $output->writeln('ok: ' . $id);
            } else {
                $fail++;
                $output->writeln('fail: ' . $id . ' - ' . $res['msg']);
            }
        }

        $output->writeln('done, success=' . $ok . ', fail=' . $fail);
    }
}
