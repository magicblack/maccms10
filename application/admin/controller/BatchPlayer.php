<?php
namespace app\admin\controller;
use think\Db;
use think\Cache;

/**
 * 批量播放器设定控制器
 */
class BatchPlayer extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    /**
     * 批量播放器管理首页
     */
    public function index()
    {
        $vodplayer = config('vodplayer') ?: [];
        $voddowner = config('voddowner') ?: [];

        $this->assign('vodplayer', $vodplayer);
        $this->assign('voddowner', $voddowner);
        $this->assign('title', lang('admin/batchplayer/title'));
        return $this->fetch('batchplayer/index');
    }

    /**
     * 批量启用/禁用播放器
     */
    public function batchStatus()
    {
        $param = input('post.');
        $froms = $param['froms'] ?? [];
        $status = intval($param['status'] ?? 1);

        if (empty($froms)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $vodplayer = config('vodplayer') ?: [];
        $changed = 0;

        foreach ($froms as $from) {
            if (isset($vodplayer[$from])) {
                $vodplayer[$from]['status'] = (string)$status;
                $changed++;
            }
        }

        if ($changed > 0) {
            $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $vodplayer);
            if ($res === false) {
                return json(['code' => 0, 'msg' => lang('write_err_config')]);
            }
        }

        return json(['code' => 1, 'msg' => sprintf(lang('admin/batchplayer/batch_status_ok'), $changed)]);
    }

    /**
     * 批量设定排序
     */
    public function batchSort()
    {
        $param = input('post.');
        $sorts = $param['sorts'] ?? [];

        if (empty($sorts)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $vodplayer = config('vodplayer') ?: [];

        foreach ($sorts as $from => $sort) {
            if (isset($vodplayer[$from])) {
                $vodplayer[$from]['sort'] = (string)intval($sort);
            }
        }

        $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $vodplayer);
        if ($res === false) {
            return json(['code' => 0, 'msg' => lang('write_err_config')]);
        }

        return json(['code' => 1, 'msg' => lang('admin/batchplayer/batch_sort_ok')]);
    }

    /**
     * 批量设定解析接口
     */
    public function batchParse()
    {
        $param = input('post.');
        $froms = $param['froms'] ?? [];
        $parse = trim($param['parse'] ?? '');

        if (empty($froms)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $vodplayer = config('vodplayer') ?: [];
        $changed = 0;

        foreach ($froms as $from) {
            if (isset($vodplayer[$from])) {
                $vodplayer[$from]['parse'] = $parse;
                $changed++;
            }
        }

        if ($changed > 0) {
            $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $vodplayer);
            if ($res === false) {
                return json(['code' => 0, 'msg' => lang('write_err_config')]);
            }
        }

        return json(['code' => 1, 'msg' => sprintf(lang('admin/batchplayer/batch_parse_ok'), $changed)]);
    }

    /**
     * 批量删除播放器
     */
    public function batchDel()
    {
        $param = input('post.');
        $froms = $param['froms'] ?? [];

        if (empty($froms)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $vodplayer = config('vodplayer') ?: [];
        $deleted = 0;

        foreach ($froms as $from) {
            if (isset($vodplayer[$from])) {
                unset($vodplayer[$from]);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $res = mac_arr2file(APP_PATH . 'extra/vodplayer.php', $vodplayer);
            if ($res === false) {
                return json(['code' => 0, 'msg' => lang('write_err_config')]);
            }
        }

        return json(['code' => 1, 'msg' => sprintf(lang('admin/batchplayer/batch_del_ok'), $deleted)]);
    }

    /**
     * 批量替换播放来源名称（在视频数据中）
     */
    public function replaceFrom()
    {
        $param = input('post.');
        $old_from = trim($param['old_from'] ?? '');
        $new_from = trim($param['new_from'] ?? '');

        if (empty($old_from) || empty($new_from)) {
            return json(['code' => 0, 'msg' => lang('param_err')]);
        }

        $prefix = config('database.prefix');

        // 替换 vod_play_from（参数化查询防止 SQL 注入）
        $count = Db::execute(
            "UPDATE `{$prefix}vod` SET `vod_play_from` = REPLACE(`vod_play_from`, ?, ?) WHERE `vod_play_from` LIKE ?",
            [$old_from, $new_from, '%' . $old_from . '%']
        );

        // 替换 vod_down_from（参数化查询防止 SQL 注入）
        Db::execute(
            "UPDATE `{$prefix}vod` SET `vod_down_from` = REPLACE(`vod_down_from`, ?, ?) WHERE `vod_down_from` LIKE ?",
            [$old_from, $new_from, '%' . $old_from . '%']
        );

        return json(['code' => 1, 'msg' => sprintf(lang('admin/batchplayer/replace_from_ok'), $count)]);
    }
}
