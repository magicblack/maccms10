<?php

namespace app\api\controller;

use think\Db;
use think\Cache;
use think\Request;
use app\common\util\SlidingWindowIpLimiter;

/**
 * 个性化推荐
 *
 * 登录用户按用户画像（mac_user_profile.prefer_types）命中的分类 × 内容质量分
 * （mac_content_quality.score_total）取 Top-N；未登录 / 无画像 / 画像无偏好分类 /
 * 个性化候选为空时，降级为全站按 score_total 降序的热门榜单，保证任何情况下都有
 * 结果兜底（除非质量分表整体为空）。
 *
 * 安全：
 *   - uid 只取自 model('User')->checkLogin()，绝不使用请求参数；请求携带他人 user_id
 *     参数一律拒绝（IDOR 防护），绝不用它去查画像。
 *   - 同一 IP 60 秒内最多 30 次，超限返回 code=1429。
 *   - 只返回 vod 公开字段，不泄露画像原文或他人数据。
 */
class Recommend extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 推荐列表
     * GET/POST api.php/recommend/list
     * 参数：limit 可选，默认12，上限30
     */
    public function list(Request $request)
    {
        // 1) 限流，先于任何业务逻辑
        $ip = mac_get_client_ip();
        $rl = SlidingWindowIpLimiter::checkHit($ip, 'recommend', 60, 30, 'recommend_rl');
        if (empty($rl['allowed'])) {
            return json(['code' => 1429, 'msg' => sprintf(lang('anti_scrape/rate_limited'), intval($rl['retry_after']))]);
        }

        $param = $request->param();
        $limit = intval($param['limit'] ?? 12);
        if ($limit < 1) {
            $limit = 12;
        }
        if ($limit > 30) {
            $limit = 30;
        }

        // 2) 鉴权：uid 只来自 checkLogin()，绝不来自请求参数
        $check = model('User')->checkLogin();
        $loggedIn = !($check['code'] > 1);
        $uid = $loggedIn ? intval($check['info']['user_id']) : 0;

        // IDOR 防护：请求携带他人 user_id 参数一律拒绝
        $reqUserId = intval($param['user_id'] ?? 0);
        if ($reqUserId > 0 && $loggedIn && $reqUserId !== $uid) {
            return json(['code' => 1403, 'msg' => lang('permission_denied')]);
        }

        // 3) 取画像（仅登录用户）
        $profile = null;
        if ($loggedIn) {
            $profile = model('UserProfile')->getByUser($uid);
        }

        $preferTypeIds = [];
        if (!empty($profile['prefer_types'])) {
            $arr = json_decode($profile['prefer_types'], true);
            if (is_array($arr)) {
                foreach ($arr as $it) {
                    if (is_array($it) && isset($it['type_id'])) {
                        $tid = intval($it['type_id']);
                        if ($tid > 0) {
                            $preferTypeIds[] = $tid;
                        }
                    }
                }
            }
            $preferTypeIds = array_values(array_unique($preferTypeIds));
        }

        // 4) 个性化 or 降级（降级分支必须有结果兜底）
        $items = [];
        if (!empty($preferTypeIds)) {
            $items = $this->fetchByTypeIds($preferTypeIds, $limit);
        }
        if (empty($items)) {
            $items = $this->fetchPopular($limit);
        }

        return json(['code' => 1, 'msg' => lang('obtain_ok'), 'info' => $items]);
    }

    /**
     * 个性化候选：按偏好分类过滤，score_total 降序取 Top-N
     */
    private function fetchByTypeIds(array $typeIds, $limit)
    {
        // 相同偏好分类组合会被多个口味相近的用户命中，短 TTL 缓存降载
        $keyIds = $typeIds;
        sort($keyIds);
        $cacheKey = 'rec_types_' . md5(implode(',', $keyIds)) . '_' . intval($limit);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $pre = Db::getConfig('prefix');
        $rows = Db::name('Vod')
            ->alias('c')
            ->join($pre . 'content_quality q', 'q.mid = 1 and q.content_id = c.vod_id', 'inner')
            ->field('c.vod_id,c.vod_name,c.vod_pic,c.vod_score,c.vod_year,c.vod_area,c.vod_remarks,c.vod_duration,c.type_id')
            ->where('c.vod_status', 1)
            ->whereIn('c.type_id', $typeIds)
            ->order('q.score_total desc')
            ->limit($limit)
            ->select();

        $items = $this->formatVodRows($rows);
        Cache::set($cacheKey, $items, 300);
        return $items;
    }

    /**
     * 降级兜底：全站按 score_total 降序取 Top-N 热门
     */
    private function fetchPopular($limit)
    {
        // 全站热门兜底榜对所有匿名/无画像用户一致，短 TTL 缓存降载
        $cacheKey = 'rec_popular_' . intval($limit);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $pre = Db::getConfig('prefix');
        $rows = Db::name('Vod')
            ->alias('c')
            ->join($pre . 'content_quality q', 'q.mid = 1 and q.content_id = c.vod_id', 'inner')
            ->field('c.vod_id,c.vod_name,c.vod_pic,c.vod_score,c.vod_year,c.vod_area,c.vod_remarks,c.vod_duration,c.type_id')
            ->where('c.vod_status', 1)
            ->order('q.score_total desc')
            ->limit($limit)
            ->select();

        $items = $this->formatVodRows($rows);
        Cache::set($cacheKey, $items, 300);
        return $items;
    }

    /**
     * 只保留公开字段，vod_pic 过 mac_url_img()
     */
    private function formatVodRows($rows)
    {
        $rows = is_array($rows) ? $rows : [];
        mac_vod_rows_episode_remarks($rows);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'vod_id'      => intval($row['vod_id']),
                'vod_name'    => (string)$row['vod_name'],
                'vod_pic'     => mac_url_img($row['vod_pic']),
                'vod_score'   => (string)$row['vod_score'],
                'vod_year'    => (string)$row['vod_year'],
                'vod_area'    => (string)$row['vod_area'],
                'vod_remarks' => (string)$row['vod_remarks'],
                'vod_duration' => (string)($row['vod_duration'] ?? ''),
                'type_id'     => intval($row['type_id']),
            ];
        }
        return $items;
    }
}
