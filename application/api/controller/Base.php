<?php
namespace app\api\controller;
use think\Controller;
use think\Request;
use app\common\controller\All;
use app\common\util\ApiMeilisearchSuggest;

class Base extends All
{
    public function __construct()
    {
        parent::__construct();
        $this->label_user();
        $config = $GLOBALS['config']['site'];
        $this->assign($config);

        //站点关闭中
        if ($config['site_status'] == 0) {
            if (!(function_exists('mac_td_preview_mode') && mac_td_preview_mode())) {
                $msg = !empty($config['site_close_tip'])
                    ? (string)$config['site_close_tip']
                    : lang('admin/system/config/site_close_tip');
                if (!headers_sent()) {
                    http_response_code(503);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode(array('code' => 1001, 'msg' => $msg), JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    /**
     * 内容访问密码是否仍锁着当前访客：与前台 Ajax::pwd() 同一 session key（mid-type-id）。
     * mid 沿用 Ajax::pwd 的取值：1 视频 / 2 文章 / 8 漫画；type 1 详情 / 4 播放 / 5 下载。
     */
    protected function contentPwdLocked($pwd, $mid, $id, $type = 1)
    {
        return (string)$pwd !== '' && session($mid . '-' . $type . '-' . intval($id)) != '1';
    }

    /**
     * 对外输出前把明文访问密码换成 0/1 标记（与 vod/get_play_info 的 vod_pwd_play 同口径）。
     * 不处理时 get_list / get_detail 直接把密码原文吐给匿名访客，前台密码门形同虚设。
     */
    protected function maskContentPwd(array $row, $prefix)
    {
        foreach (array('_pwd', '_pwd_play', '_pwd_down') as $suffix) {
            if (array_key_exists($prefix . $suffix, $row)) {
                $row[$prefix . $suffix] = (string)$row[$prefix . $suffix] !== '' ? 1 : 0;
            }
        }
        return $row;
    }

    /**
     * 统一搜索建议：Meilisearch + 与列表一致的已发布/回收过滤；无命中时 MySQL LIKE 回退。
     *
     * @param string      $kind    vod|art|actor|topic|role|website|manga
     * @param string|null $urlFlag mac_url_search 第二参数，默认与 $kind 相同
     *
     * @return \think\response\Json
     */
    /**
     * 公开列表接口的 num / start 统一收口：num 限 1..$max（前台最大用到 60），start 不得为负。
     * 不收口时 ?num=99999 一次拉整表，叠加逐行后处理（章节数备注等）可被匿名放大成 DoS；
     * 负 start 会拼出非法 LIMIT 直接报 SQL 错。
     * @return array [num, start]
     */
    protected function clampNumStart(array $param, $defaultNum, $max = 60)
    {
        $num = isset($param['num']) ? (int)$param['num'] : (int)$defaultNum;
        $num = $num < 1 ? (int)$defaultNum : min($num, (int)$max);
        $start = isset($param['start']) ? max(0, (int)$param['start']) : 0;
        return [$num, $start];
    }

    protected function jsonSuggestByKind(Request $request, $kind, $urlFlag = null)
    {
        if ($GLOBALS['config']['app']['search'] != '1') {
            return json(['code' => 999, 'msg' => lang('suggest_close')]);
        }
        $urlFlag = ($urlFlag !== null && $urlFlag !== '') ? (string)$urlFlag : (string)$kind;
        $param = $request->param();
        $param = mac_search_len_check(array_merge(['wd' => '', 'tag' => '', 'class' => '', 'letter' => '', 'name' => '', 'state' => '', 'level' => '', 'area' => '', 'lang' => '', 'version' => '', 'actor' => '', 'director' => '', 'starsign' => '', 'blood' => ''], $param));
        $wd = mac_filter_xss(trim((string)($param['wd'] ?? '')));
        if ($wd === '') {
            return json(['code' => 1001, 'msg' => '参数错误']);
        }
        $limit = max(1, min(20, intval($param['limit'] ?? 10)));
        $res = ApiMeilisearchSuggest::suggestListDataRes($kind, $wd, $limit);
        $res['url'] = mac_url_search(['wd' => urlencode($wd)], $urlFlag);

        return json($res);
    }

}
