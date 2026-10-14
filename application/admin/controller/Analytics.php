<?php
namespace app\admin\controller;

use app\common\util\BulkTableIo;
use think\Cache;
use think\Db;

class Analytics extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->view->config('view_path', APP_PATH . 'admin/view_new/');
    }

    public function index()
    {
        $param = input();
        // 这几个参数会回显到页面，先校验格式防止反射型 XSS，非法值回退默认。
        $endDate = (!empty($param['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['end_date'])) ? $param['end_date'] : date('Y-m-d');
        $startDate = (!empty($param['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $param['start_date'])) ? $param['start_date'] : date('Y-m-d', strtotime('-6 day'));
        $allowDims = ['device', 'region', 'category', 'channel', 'os', 'browser'];
        $dimType = (!empty($param['dim_type']) && in_array($param['dim_type'], $allowDims, true)) ? $param['dim_type'] : 'device';
        try {
            $data = $this->buildReportData($startDate, $endDate, $dimType);
        } catch (\Throwable $e) {
            if ($this->isAnalyticsTableMissing($e)) {
                return $this->error(lang('admin/analytics/msg_table_not_installed') . '：' . lang('admin/analytics/msg_table_not_installed_hint'));
            }
            throw $e;
        }
        $this->assign('title', lang('admin/analytics/title'));
        $this->assign('param', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dim_type' => $dimType,
        ]);
        $this->assign('report', $data);
        $this->assign('report_json', json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->fetch('analytics/index');
    }

    public function trend()
    {
        $startDate = input('start_date/s', date('Y-m-d', strtotime('-6 day')));
        $endDate = input('end_date/s', date('Y-m-d'));
        $dimType = input('dim_type/s', 'device');
        try {
            $data = $this->buildReportData($startDate, $endDate, $dimType);
        } catch (\Throwable $e) {
            if ($this->isAnalyticsTableMissing($e)) {
                return json([
                    'code' => 0,
                    'msg' => lang('admin/analytics/msg_table_not_installed') . '：' . lang('admin/analytics/msg_table_not_installed_hint'),
                ]);
            }
            throw $e;
        }
        return json(['code' => 1, 'msg' => lang('ok'), 'data' => $data]);
    }

    public function export()
    {
        $startDate = input('start_date/s', date('Y-m-d', strtotime('-6 day')));
        $endDate = input('end_date/s', date('Y-m-d'));
        $dimType = input('dim_type/s', 'device');
        $format = input('format/s', 'xlsx');
        try {
            $data = $this->buildReportData($startDate, $endDate, $dimType);
        } catch (\Throwable $e) {
            if ($this->isAnalyticsTableMissing($e)) {
                return $this->error(lang('admin/analytics/msg_table_not_installed') . '：' . lang('admin/analytics/msg_table_not_installed_hint'));
            }
            throw $e;
        }

        if ($format === 'pdf') {
            try {
                $this->exportPdf($data, $startDate, $endDate, $dimType);
            } catch (\Throwable $e) {
                return $this->error(lang('admin/analytics/msg_pdf_export_fallback') . '：' . lang('admin/analytics/msg_pdf_export_fallback_hint'));
            }
            exit;
        }

        $rows = $this->buildExcelRows($data, $startDate, $endDate, $dimType);
        $fields = ['section', 'metric', 'value'];
        BulkTableIo::exportXlsxDownload('analytics_report_' . date('Ymd_His'), $fields, $rows);
        exit;
    }

    // 每次点开/切换筛选都会触发一整套聚合查询（含 buildZeroExposureItems 里对
    // vod/art/manga 的 NOT EXISTS 扫描），短 TTL 缓存避免同一组参数被并发/连续
    // 请求反复重算，减少压垮数据库进而 504 的概率。数据源本身是按小时/按天聚合
    // 写入的，5 分钟内不刷新对准确性没有影响。
    private function buildReportData($startDate, $endDate, $dimType)
    {
        $flag = isset($GLOBALS['config']['app']['cache_flag']) ? (string)$GLOBALS['config']['app']['cache_flag'] : 'mac';
        $cacheKey = $flag . '_analytics_report_' . md5($startDate . '|' . $endDate . '|' . $dimType);
        $cached = Cache::get($cacheKey);
        if (!empty($cached) && is_array($cached)) {
            return $cached;
        }
        $data = $this->buildReportDataUncached($startDate, $endDate, $dimType);
        Cache::set($cacheKey, $data, 300);
        return $data;
    }

    private function buildReportDataUncached($startDate, $endDate, $dimType)
    {
        $overviewRows = Db::name('AnalyticsDayOverview')
            ->where('stat_date', 'between', [$startDate, $endDate])
            ->order('stat_date asc')
            ->select();

        $trendDays = [];
        $trendPv = [];
        $trendUv = [];
        $trendOrderAmount = [];
        $totalPv = 0;
        $totalUv = 0;
        $totalOrderAmount = 0;
        $totalSessions = 0;
        $avgBounce = 0.00;
        $avgDuration = 0;

        foreach ($overviewRows as $row) {
            $trendDays[] = $row['stat_date'];
            $trendPv[] = intval($row['pv']);
            $trendUv[] = intval($row['uv']);
            $trendOrderAmount[] = floatval($row['order_paid_amount']);
            $totalPv += intval($row['pv']);
            $totalUv += intval($row['uv']);
            $totalOrderAmount += floatval($row['order_paid_amount']);
            $totalSessions += intval($row['session_cnt']);
            $avgBounce += floatval($row['bounce_rate']);
            $avgDuration += intval($row['avg_session_duration_sec']);
        }

        $dayCount = count($overviewRows);
        if ($dayCount > 0) {
            $avgBounce = round($avgBounce / $dayCount, 2);
            $avgDuration = intval(round($avgDuration / $dayCount));
        }

        $dimRows = Db::name('AnalyticsDayDim')
            ->field('dim_key,sum(pv) as pv,sum(uv) as uv,sum(order_paid_amount) as order_paid_amount')
            ->where('dim_type', $dimType)
            ->where('stat_date', 'between', [$startDate, $endDate])
            ->group('dim_key')
            ->order('pv desc')
            ->limit(10)
            ->select();

        $dimension = [];
        foreach ($dimRows as $row) {
            $dimension[] = [
                'name' => $row['dim_key'],
                'pv' => intval($row['pv']),
                'uv' => intval($row['uv']),
                'order_paid_amount' => floatval($row['order_paid_amount']),
            ];
        }

        $startTs = strtotime($startDate . ' 00:00:00');
        $endTs = strtotime($endDate . ' 23:59:59');

        // 路径漏斗/热门路径为「近期行为」小部件，且 group by path/prev_path 在 pageview 大表上
        // 无对应索引，长范围会触发大量行的临时表+filesort。将其扫描窗口封顶为最近 31 天，
        // 保护数据库；概览等其它指标仍使用管理员选择的完整范围。
        $funnelStartTs = max($startTs, $endTs - 31 * 86400);

        $behaviorPathRows = Db::name('AnalyticsPageview')
            ->field('path,count(*) as pv,avg(stay_ms) as avg_stay_ms')
            ->where('ts', 'between', [$funnelStartTs, $endTs])
            ->group('path')
            ->order('pv desc')
            ->limit(15)
            ->select();

        $behaviorFlowRows = Db::name('AnalyticsPageview')
            ->field('prev_path,path,count(*) as flow_cnt')
            ->where('ts', 'between', [$funnelStartTs, $endTs])
            ->where('prev_path', '<>', '')
            ->group('prev_path,path')
            ->order('flow_cnt desc')
            ->limit(15)
            ->select();

        // 会话聚合此前用管理员选择的完整范围，未像上面 pageview 查询那样封顶，
        // 自定义大范围时会对 mac_analytics_session 做无上限的 count/sum 扫描。
        // 统一用同一个 31 天封顶窗口，保持与上面路径漏斗查询一致的保护策略。
        $sessionAgg = Db::name('AnalyticsSession')
            ->field('count(*) as session_cnt,sum(duration_sec) as duration_sum,sum(is_bounce) as bounce_cnt')
            ->where('started_at', 'between', [$funnelStartTs, $endTs])
            ->find();

        $sessionCnt = intval($sessionAgg['session_cnt']);
        $behavior = [
            'top_paths' => [],
            'top_flows' => [],
            'avg_stay_sec' => $sessionCnt > 0 ? round(floatval($sessionAgg['duration_sum']) / $sessionCnt, 2) : 0,
            'bounce_rate' => $sessionCnt > 0 ? round(floatval($sessionAgg['bounce_cnt']) * 100 / $sessionCnt, 2) : 0,
        ];

        foreach ($behaviorPathRows as $row) {
            $behavior['top_paths'][] = [
                'path' => $row['path'],
                'pv' => intval($row['pv']),
                'avg_stay_ms' => intval($row['avg_stay_ms']),
            ];
        }
        foreach ($behaviorFlowRows as $row) {
            $behavior['top_flows'][] = [
                'from' => $row['prev_path'],
                'to' => $row['path'],
                'flow_cnt' => intval($row['flow_cnt']),
            ];
        }

        $contentRows = Db::name('AnalyticsContentDay')
            ->field('mid,content_id,sum(view_pv) as view_pv,sum(view_uv) as view_uv,sum(order_cnt) as order_cnt,sum(order_amount) as order_amount')
            ->where('stat_date', 'between', [$startDate, $endDate])
            ->group('mid,content_id')
            ->order('view_pv desc')
            ->limit(15)
            ->select();

        $contentNameMap = $this->buildContentNameMap($contentRows);
        $hot = $this->buildContentItems($contentRows, $contentNameMap);

        $coldRows = Db::name('AnalyticsContentDay')
            ->field('mid,content_id,sum(view_pv) as view_pv,sum(view_uv) as view_uv,sum(order_cnt) as order_cnt,sum(order_amount) as order_amount')
            ->where('stat_date', 'between', [$startDate, $endDate])
            ->group('mid,content_id')
            ->order('view_pv asc')
            ->limit(10)
            ->select();

        $coldNameMap = $this->buildContentNameMap($coldRows);
        $cold = $this->buildContentItems($coldRows, $coldNameMap);
        foreach ($cold as $idx => $item) {
            $cold[$idx]['exposure'] = 1;
        }
        $cold = array_merge($cold, $this->buildZeroExposureItems($startDate, $endDate, 10));

        return [
            'overview' => [
                'total_pv' => $totalPv,
                'total_uv' => $totalUv,
                'total_session_cnt' => $totalSessions,
                'total_order_amount' => round($totalOrderAmount, 2),
                'avg_bounce_rate' => $avgBounce,
                'avg_session_duration_sec' => $avgDuration,
                'trend_days' => $trendDays,
                'trend_pv' => $trendPv,
                'trend_uv' => $trendUv,
                'trend_order_amount' => $trendOrderAmount,
            ],
            'dimension' => $dimension,
            'behavior' => $behavior,
            'content' => [
                'hot' => $hot,
                'cold' => $cold,
            ],
        ];
    }

    private function midText($mid)
    {
        $mid = intval($mid);
        if ($mid === 1) {
            return 'vod';
        }
        if ($mid === 2) {
            return 'art';
        }
        if ($mid === 8) {
            return 'manga';
        }
        return 'other';
    }

    private function buildExcelRows($data, $startDate, $endDate, $dimType)
    {
        $rows = [];
        $rows[] = ['section' => lang('admin/analytics/report_section_param'), 'metric' => lang('admin/analytics/report_metric_date_range'), 'value' => $startDate . ' ~ ' . $endDate];
        $rows[] = ['section' => lang('admin/analytics/report_section_param'), 'metric' => lang('admin/analytics/report_metric_dim_type'), 'value' => $dimType];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => 'PV', 'value' => $data['overview']['total_pv']];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => 'UV', 'value' => $data['overview']['total_uv']];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => lang('admin/analytics/session_cnt'), 'value' => $data['overview']['total_session_cnt']];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => lang('admin/analytics/order_amount'), 'value' => $data['overview']['total_order_amount']];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => lang('admin/analytics/avg_bounce_rate') . '(%)', 'value' => $data['overview']['avg_bounce_rate']];
        $rows[] = ['section' => lang('admin/analytics/report_section_overview'), 'metric' => lang('admin/analytics/avg_stay') . '(s)', 'value' => $data['overview']['avg_session_duration_sec']];

        foreach ($data['dimension'] as $item) {
            $rows[] = ['section' => lang('admin/analytics/report_section_dimension'), 'metric' => $item['name'] . ' PV', 'value' => $item['pv']];
            $rows[] = ['section' => lang('admin/analytics/report_section_dimension'), 'metric' => $item['name'] . ' UV', 'value' => $item['uv']];
            $rows[] = ['section' => lang('admin/analytics/report_section_dimension'), 'metric' => $item['name'] . ' ' . lang('admin/analytics/order_amount'), 'value' => $item['order_paid_amount']];
        }

        $rows[] = ['section' => lang('admin/analytics/report_section_behavior'), 'metric' => lang('admin/analytics/avg_stay') . '(s)', 'value' => $data['behavior']['avg_stay_sec']];
        $rows[] = ['section' => lang('admin/analytics/report_section_behavior'), 'metric' => lang('admin/analytics/avg_bounce_rate') . '(%)', 'value' => $data['behavior']['bounce_rate']];
        foreach ($data['behavior']['top_flows'] as $flow) {
            $rows[] = ['section' => lang('admin/analytics/report_section_path'), 'metric' => $flow['from'] . ' -> ' . $flow['to'], 'value' => $flow['flow_cnt']];
        }
        foreach ($data['behavior']['top_paths'] as $item) {
            $rows[] = ['section' => lang('admin/analytics/report_section_top_paths'), 'metric' => $item['path'], 'value' => 'pv=' . $item['pv'] . ' stay=' . $item['avg_stay_ms']];
        }

        foreach ($data['content']['hot'] as $item) {
            $label = $item['content_key'] . (empty($item['content_name']) ? '' : ' ' . $item['content_name']);
            $rows[] = ['section' => lang('admin/analytics/report_section_content_hot'), 'metric' => $label . ' PV', 'value' => $item['view_pv']];
            $rows[] = ['section' => lang('admin/analytics/report_section_content_hot'), 'metric' => $label . ' ' . lang('admin/analytics/conversion_rate') . '(%)', 'value' => $item['conversion_rate']];
        }
        foreach ($data['content']['cold'] as $item) {
            $label = $item['content_key'] . (empty($item['content_name']) ? '' : ' ' . $item['content_name']);
            $rows[] = ['section' => lang('admin/analytics/report_section_content_cold'), 'metric' => $label . ' PV', 'value' => $item['view_pv']];
            $rows[] = ['section' => lang('admin/analytics/report_section_content_cold'), 'metric' => $label . ' ' . lang('admin/analytics/conversion_rate') . '(%)', 'value' => $item['conversion_rate']];
        }
        return $rows;
    }

    private function buildContentNameMap($rows)
    {
        $idsByMid = [1 => [], 2 => [], 8 => []];
        foreach ($rows as $row) {
            $mid = intval($row['mid']);
            $id = intval($row['content_id']);
            if (isset($idsByMid[$mid]) && $id > 0) {
                $idsByMid[$mid][$id] = $id;
            }
        }

        $map = [];
        if (!empty($idsByMid[1])) {
            $list = Db::name('Vod')->field('vod_id,vod_name')->where('vod_id', 'in', array_values($idsByMid[1]))->select();
            foreach ($list as $item) {
                $map['1:' . intval($item['vod_id'])] = $item['vod_name'];
            }
        }
        if (!empty($idsByMid[2])) {
            $list = Db::name('Art')->field('art_id,art_name')->where('art_id', 'in', array_values($idsByMid[2]))->select();
            foreach ($list as $item) {
                $map['2:' . intval($item['art_id'])] = $item['art_name'];
            }
        }
        if (!empty($idsByMid[8])) {
            $list = Db::name('Manga')->field('manga_id,manga_name')->where('manga_id', 'in', array_values($idsByMid[8]))->select();
            foreach ($list as $item) {
                $map['8:' . intval($item['manga_id'])] = $item['manga_name'];
            }
        }
        return $map;
    }

    private function buildContentItems($rows, $nameMap)
    {
        $items = [];
        foreach ($rows as $row) {
            $uv = max(1, intval($row['view_uv']));
            $key = intval($row['mid']) . ':' . intval($row['content_id']);
            $items[] = [
                'content_key' => $this->midText($row['mid']) . '-' . $row['content_id'],
                'content_name' => isset($nameMap[$key]) ? $nameMap[$key] : '',
                'view_pv' => intval($row['view_pv']),
                'view_uv' => intval($row['view_uv']),
                'order_cnt' => intval($row['order_cnt']),
                'order_amount' => floatval($row['order_amount']),
                'conversion_rate' => round(intval($row['order_cnt']) * 100 / $uv, 2),
            ];
        }
        return $items;
    }

    // 零曝光内容：在 [startDate,endDate] 内 mac_analytics_content_day 无任何行的已审内容（vod/art/manga）。
    // 每个来源表各自 limit $limit（下推到 DB），合并后按 time_add desc 再截断到 $limit，确保整体有界。
    private function buildZeroExposureItems($startDate, $endDate, $limit)
    {
        $sources = [
            1 => ['table' => 'Vod', 'idField' => 'vod_id', 'nameField' => 'vod_name', 'statusField' => 'vod_status', 'timeField' => 'vod_time_add'],
            2 => ['table' => 'Art', 'idField' => 'art_id', 'nameField' => 'art_name', 'statusField' => 'art_status', 'timeField' => 'art_time_add'],
            8 => ['table' => 'Manga', 'idField' => 'manga_id', 'nameField' => 'manga_name', 'statusField' => 'manga_status', 'timeField' => 'manga_time_add'],
        ];

        $rows = [];
        foreach ($sources as $mid => $cfg) {
            $list = Db::name($cfg['table'])
                ->alias('c')
                ->field('c.' . $cfg['idField'] . ' as content_id, c.' . $cfg['nameField'] . ' as content_name, c.' . $cfg['timeField'] . ' as time_add')
                ->where('c.' . $cfg['statusField'], 1)
                ->whereNotExists(function ($query) use ($mid, $startDate, $endDate, $cfg) {
                    $query->name('AnalyticsContentDay')
                        ->alias('d')
                        ->where('d.mid', $mid)
                        ->where('d.stat_date', 'between', [$startDate, $endDate])
                        ->whereRaw('d.content_id = c.' . $cfg['idField']);
                })
                ->order('c.' . $cfg['timeField'] . ' desc')
                ->limit($limit)
                ->select();

            foreach ($list as $row) {
                $rows[] = [
                    'mid' => $mid,
                    'content_id' => intval($row['content_id']),
                    'content_name' => $row['content_name'],
                    'time_add' => intval($row['time_add']),
                ];
            }
        }

        usort($rows, function ($a, $b) {
            return $b['time_add'] - $a['time_add'];
        });
        $rows = array_slice($rows, 0, $limit);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'content_key' => $this->midText($row['mid']) . '-' . $row['content_id'],
                'content_name' => $row['content_name'],
                'view_pv' => 0,
                'view_uv' => 0,
                'order_cnt' => 0,
                'order_amount' => 0.0,
                'conversion_rate' => 0.0,
                'exposure' => 0,
            ];
        }
        return $items;
    }

    private function exportPdf($data, $startDate, $endDate, $dimType)
    {
        $lines = [];
        $lines[] = lang('admin/analytics/pdf_title');
        $lines[] = lang('admin/analytics/pdf_range') . ': ' . $startDate . ' ~ ' . $endDate;
        $lines[] = lang('admin/analytics/pdf_dimension') . ': ' . $dimType;
        $lines[] = '';
        $lines[] = lang('admin/analytics/pdf_overview');
        $lines[] = 'PV: ' . $data['overview']['total_pv'];
        $lines[] = 'UV: ' . $data['overview']['total_uv'];
        $lines[] = lang('admin/analytics/session_cnt') . ': ' . $data['overview']['total_session_cnt'];
        $lines[] = lang('admin/analytics/order_amount') . ': ' . $data['overview']['total_order_amount'];
        $lines[] = lang('admin/analytics/avg_bounce_rate') . '(%): ' . $data['overview']['avg_bounce_rate'];
        $lines[] = lang('admin/analytics/avg_stay') . '(s): ' . $data['overview']['avg_session_duration_sec'];
        $lines[] = '';
        $lines[] = lang('admin/analytics/pdf_top_dimension');
        foreach ($data['dimension'] as $item) {
            $lines[] = $item['name'] . ' pv=' . $item['pv'] . ' uv=' . $item['uv'] . ' order=' . $item['order_paid_amount'];
        }
        $lines[] = '';
        $lines[] = lang('admin/analytics/pdf_top_flows');
        foreach ($data['behavior']['top_flows'] as $flow) {
            $lines[] = $flow['from'] . ' -> ' . $flow['to'] . ' : ' . $flow['flow_cnt'];
        }
        $lines[] = '';
        $lines[] = lang('admin/analytics/report_section_top_paths');
        foreach ($data['behavior']['top_paths'] as $item) {
            $lines[] = $item['path'] . ' pv=' . $item['pv'] . ' stay=' . $item['avg_stay_ms'];
        }
        $lines[] = '';
        $lines[] = lang('admin/analytics/pdf_hot_content');
        foreach ($data['content']['hot'] as $item) {
            $lines[] = $item['content_key'] . ' pv=' . $item['view_pv'] . ' conversion=' . $item['conversion_rate'] . '%';
        }
        $lines[] = '';
        $lines[] = lang('admin/analytics/report_section_content_cold');
        foreach ($data['content']['cold'] as $item) {
            $marker = intval($item['exposure']) === 0 ? ' [zero-exposure]' : '';
            $lines[] = $item['content_key'] . ' pv=' . $item['view_pv'] . ' conversion=' . $item['conversion_rate'] . '%' . $marker;
        }

        $pdf = $this->simplePdfFromLines($lines);
        if (!is_string($pdf) || $pdf === '') {
            throw new \RuntimeException('pdf content empty');
        }
        $filename = 'analytics_report_' . date('Ymd_His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    private function simplePdfFromLines($lines)
    {
        $fontSpec = $this->detectPdfFontSpec();
        $perPage = 50;
        $chunks = array_chunk($lines, $perPage);
        if (empty($chunks)) {
            $chunks = [[]];
        }

        // Object numbering: 1=Catalog, 2=Pages, 3=Font (Type0 composite or Type1 Helvetica),
        // 4=descendant CIDFont (composite only, otherwise omitted), then page/content pairs.
        $fontObjNum = 3;
        if ($fontSpec['composite']) {
            $descendantObjNum = 4;
            $nextObjNum = 5;
        } else {
            $nextObjNum = 4;
        }

        $objects = [];
        $pageObjNums = [];
        foreach ($chunks as $chunk) {
            $pageObjNum = $nextObjNum++;
            $contentObjNum = $nextObjNum++;
            $pageObjNums[] = $pageObjNum;

            $content = "BT\n/F1 11 Tf\n40 800 Td\n14 TL\n";
            foreach ($chunk as $line) {
                $content .= $this->pdfTextLine($line, $fontSpec) . " Tj\nT*\n";
            }
            $content .= "ET\n";

            $objects[$pageObjNum] = $pageObjNum . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 " . $fontObjNum . " 0 R >> >> /Contents " . $contentObjNum . " 0 R >>\nendobj\n";
            $objects[$contentObjNum] = $contentObjNum . " 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
        }

        $kids = implode(' ', array_map(function ($n) {
            return $n . ' 0 R';
        }, $pageObjNums));

        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[2] = "2 0 obj\n<< /Type /Pages /Count " . count($pageObjNums) . " /Kids [" . $kids . "] >>\nendobj\n";
        if ($fontSpec['composite']) {
            $objects[$fontObjNum] = $fontObjNum . " 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /" . $fontSpec['base_font'] . " /Encoding /" . $fontSpec['encoding'] . " /DescendantFonts [" . $descendantObjNum . " 0 R] >>\nendobj\n";
            $objects[$descendantObjNum] = $descendantObjNum . " 0 obj\n<< /Type /Font /Subtype /CIDFontType0 /BaseFont /" . $fontSpec['base_font'] . " /CIDSystemInfo << /Registry (" . $fontSpec['registry'] . ") /Ordering (" . $fontSpec['ordering'] . ") /Supplement " . intval($fontSpec['supplement']) . " >> /DW 1000 >>\nendobj\n";
        } else {
            $objects[$fontObjNum] = $fontObjNum . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $obj;
        }
        $objCount = count($objects);
        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . ($objCount + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $objCount; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($objCount + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";
        return $pdf;
    }

    private function detectPdfFontSpec()
    {
        $lang = strtolower($this->resolveCurrentAdminLang());
        $lang = str_replace('_', '-', $lang);
        $lang = explode('.', $lang)[0];

        $aliases = [
            'zh' => 'zh-cn',
            'zh-hans' => 'zh-cn',
            'zh-sg' => 'zh-cn',
            'zh-hant' => 'zh-tw',
            'zh-hk' => 'zh-tw',
            'ja' => 'ja-jp',
            'ko' => 'ko-kr',
            'en' => 'en-us',
            'fr' => 'fr-fr',
            'de' => 'de-de',
            'es' => 'es-es',
            'pt' => 'pt-pt',
            'pt-br' => 'pt-pt',
        ];
        if (isset($aliases[$lang])) {
            $lang = $aliases[$lang];
        }

        $fontMap = [
            'zh-cn' => ['composite' => true, 'base_font' => 'STSong-Light', 'encoding' => 'UniGB-UCS2-H', 'registry' => 'Adobe', 'ordering' => 'GB1', 'supplement' => 2],
            'zh-tw' => ['composite' => true, 'base_font' => 'MSung-Light', 'encoding' => 'UniCNS-UCS2-H', 'registry' => 'Adobe', 'ordering' => 'CNS1', 'supplement' => 0],
            'ja-jp' => ['composite' => true, 'base_font' => 'HeiseiKakuGo-W5', 'encoding' => 'UniJIS-UCS2-H', 'registry' => 'Adobe', 'ordering' => 'Japan1', 'supplement' => 2],
            'ko-kr' => ['composite' => true, 'base_font' => 'HYGoThic-Medium', 'encoding' => 'UniKS-UCS2-H', 'registry' => 'Adobe', 'ordering' => 'Korea1', 'supplement' => 2],
            'en-us' => ['composite' => false],
            'fr-fr' => ['composite' => false],
            'de-de' => ['composite' => false],
            'es-es' => ['composite' => false],
            'pt-pt' => ['composite' => false],
        ];

        return $fontMap[$lang] ?? $fontMap['zh-cn'];
    }

    private function pdfTextLine($line, $fontSpec)
    {
        if (!empty($fontSpec['composite'])) {
            $utf16 = mb_convert_encoding((string)$line, 'UTF-16BE', 'UTF-8');
            if ($utf16 === false) {
                throw new \RuntimeException('pdf utf8 convert failed');
            }
            return '<FEFF' . strtoupper(bin2hex($utf16)) . '>';
        }
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$line);
        return '(' . $safe . ')';
    }

    private function resolveCurrentAdminLang()
    {
        $candidates = [
            strtolower((string)input('lang/s', '')),
            strtolower((string)cookie('admin_lang')),
            strtolower((string)cookie('think_var')),
            strtolower((string)config('default_lang')),
            strtolower((string)config('lang.default_lang')),
            strtolower((string)($GLOBALS['config']['app']['lang'] ?? '')),
        ];
        foreach ($candidates as $lang) {
            if ($lang !== '') {
                return $lang;
            }
        }
        return 'zh-cn';
    }

    private function isAnalyticsTableMissing(\Throwable $e)
    {
        $msg = strtolower($e->getMessage());
        return strpos($msg, 'doesn\'t exist') !== false
            || strpos($msg, 'base table or view not found') !== false
            || (strpos($msg, 'table') !== false && strpos($msg, 'not found') !== false);
    }
}
