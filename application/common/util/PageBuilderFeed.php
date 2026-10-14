<?php
namespace app\common\util;

/**
 * Page Builder 轮播/卡片共用取数。
 */
class PageBuilderFeed
{
    /**
     * bind 决定数据源，返回归一化 list。
     *
     * @param array $args bind(vod|manga|art) num by type order
     * @return array{list: array<int,array>, bind: string, total?: int}
     */
    public static function fetch($args = array())
    {
        if (!is_array($args)) {
            $args = array();
        }
        $bind = isset($args['bind']) ? strtolower(trim((string) $args['bind'])) : 'vod';
        if (!in_array($bind, array('vod', 'manga', 'art'), true)) {
            $bind = 'vod';
        }
        $num = isset($args['num']) ? (int) $args['num'] : 6;
        if ($num < 1) {
            $num = 1;
        }
        if ($num > 50) {
            $num = 50;
        }
        $by = isset($args['by']) ? (string) $args['by'] : 'hits';
        $allowedBy = array('hits', 'hits_month', 'hits_week', 'hits_day', 'time', 'score');
        if (!in_array($by, $allowedBy, true)) {
            $by = 'hits';
        }
        $order = isset($args['order']) ? (string) $args['order'] : 'desc';
        if ($order !== 'asc' && $order !== 'desc') {
            $order = 'desc';
        }
        $type = isset($args['type']) ? $args['type'] : 0;
        if ($type === 'current') {
            $typeId = 'current';
        } else {
            $typeId = (int) $type;
            if ($typeId < 0) {
                $typeId = 0;
            }
        }

        $level = isset($args['level']) ? trim((string) $args['level']) : '';
        $res = array();
        $raw = array();

        // 首页轮播推荐位：与 default 模板 vod/get_banner 一致，按 vod_level + vod_time 直查，不走 listCache
        if ($bind === 'vod' && $level !== '') {
            $raw = self::fetchVodBannerRows($num, $level);
            $res = array('list' => $raw, 'total' => count($raw));
        } else {
            $lp = array(
                'num' => $num,
                'by' => $by,
                'order' => $order,
                'type' => $typeId,
            );
            if ($level !== '') {
                $lp['level'] = $level;
            }

            $tag = $bind === 'art' ? 'art' : ($bind === 'manga' ? 'manga' : 'vod');
            $res = \app\common\view\TwigMaccms::fetchData($tag, $lp);
            $raw = (is_array($res) && isset($res['list']) && is_array($res['list'])) ? $res['list'] : array();

            if (empty($raw) && $by !== 'hits') {
                $lp['by'] = 'hits';
                $res = \app\common\view\TwigMaccms::fetchData($tag, $lp);
                $raw = (is_array($res) && isset($res['list']) && is_array($res['list'])) ? $res['list'] : array();
            }
        }

        $list = array();
        foreach ($raw as $vo) {
            if (!is_array($vo)) {
                continue;
            }
            if ($bind === 'manga') {
                $name = isset($vo['manga_name']) ? (string) $vo['manga_name'] : '';
                $pic = isset($vo['manga_pic']) ? (string) $vo['manga_pic'] : '';
                $picWide = isset($vo['manga_pic_slide']) && (string) $vo['manga_pic_slide'] !== ''
                    ? (string) $vo['manga_pic_slide']
                    : $pic;
                $blurb = isset($vo['manga_blurb']) ? mac_plain_text($vo['manga_blurb']) : '';
                if ($blurb === '' && isset($vo['manga_content'])) {
                    $blurb = mac_plain_text($vo['manga_content']);
                }
                $class = isset($vo['manga_class']) ? (string) $vo['manga_class'] : '';
                $remarks = isset($vo['manga_remarks']) ? (string) $vo['manga_remarks'] : '';
                $url = function_exists('mac_url_manga_detail') ? mac_url_manga_detail($vo) : '';
            } elseif ($bind === 'art') {
                $name = isset($vo['art_name']) ? (string) $vo['art_name'] : '';
                $pic = isset($vo['art_pic']) ? (string) $vo['art_pic'] : '';
                $picWide = $pic;
                $blurb = isset($vo['art_blurb']) ? mac_plain_text($vo['art_blurb']) : '';
                if ($blurb === '' && isset($vo['art_content'])) {
                    $blurb = mac_plain_text($vo['art_content']);
                }
                $class = isset($vo['art_class']) ? (string) $vo['art_class'] : '';
                $remarks = isset($vo['art_remarks']) ? (string) $vo['art_remarks'] : '';
                $url = function_exists('mac_url_art_detail') ? mac_url_art_detail($vo) : '';
            } else {
                $name = isset($vo['vod_name']) ? (string) $vo['vod_name'] : '';
                $pic = isset($vo['vod_pic']) ? (string) $vo['vod_pic'] : '';
                $picWide = isset($vo['vod_pic_slide']) && (string) $vo['vod_pic_slide'] !== ''
                    ? (string) $vo['vod_pic_slide']
                    : $pic;
                $blurb = isset($vo['vod_blurb']) ? mac_plain_text($vo['vod_blurb']) : '';
                if ($blurb === '' && isset($vo['vod_content'])) {
                    $blurb = mac_plain_text($vo['vod_content']);
                }
                $class = isset($vo['vod_class']) ? (string) $vo['vod_class'] : '';
                $remarks = isset($vo['vod_remarks']) ? (string) $vo['vod_remarks'] : '';
                $url = function_exists('mac_url_vod_detail') ? mac_url_vod_detail($vo) : '';
            }
            $list[] = array(
                'name' => $name,
                'pic' => $pic,
                'pic_wide' => $picWide,
                'url' => $url,
                'blurb' => $blurb,
                'class' => $class,
                'remarks' => $remarks,
                'bind' => $bind,
                // 轮播等按分类封面比例（mac_type_cover）给竖版首屏提示
                'type_id' => isset($vo['type_id']) ? (int) $vo['type_id'] : 0,
            );
        }

        return array(
            'list' => $list,
            'bind' => $bind,
            'total' => is_array($res) && isset($res['total']) ? (int) $res['total'] : count($list),
        );
    }

    /**
     * Banner 推荐位：对齐 api Vod::get_banner（level 默认 9，vod_time desc，无 listCache）。
     *
     * @param int $num
     * @param string $level 逗号分隔推荐等级，如 "9" 或 "1,2,3"
     * @return array<int,array>
     */
    private static function fetchVodBannerRows($num, $level)
    {
        $level = trim((string) $level);
        if ($level === '' || $num < 1) {
            return array();
        }
        if ($level === 'all') {
            $level = '1,2,3,4,5,6,7,8,9';
        }

        $where = array();
        $where['vod_status'] = array('eq', 1);
        $where['vod_level'] = array('in', explode(',', $level));

        $rows = \think\Db::name('Vod')
            ->field('vod_id,vod_name,vod_sub,vod_pic,vod_pic_slide,vod_blurb,vod_content,vod_remarks,vod_class,vod_area,vod_year')
            ->where($where)
            ->order('vod_time desc')
            ->limit($num)
            ->select();
        if (is_array($rows)) {
            mac_vod_rows_episode_remarks($rows);
        }

        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
