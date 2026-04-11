<?php
namespace app\common\util;

/**
 * CSV / XLSX 批量导入导出（无第三方依赖，xlsx 依赖 ZipArchive）
 */
class BulkTableIo
{
    const MAX_IMPORT_ROWS = 2000;
    const MAX_EXPORT_ROWS = 10000;

    public static function colName($index)
    {
        $n = (int)$index;
        $s = '';
        while ($n >= 0) {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        }
        return $s;
    }

    public static function xmlEsc($str)
    {
        return htmlspecialchars((string)$str, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function filterRowKeys(array $row, array $allowedKeys)
    {
        $allowed = array_flip($allowedKeys);
        $out = [];
        foreach ($row as $k => $v) {
            if (isset($allowed[$k])) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    public static function prepareGenericForSave(array $data, $prefix)
    {
        $idKey = $prefix . '_id';
        if (isset($data[$idKey])) {
            $id = (int)$data[$idKey];
            $data[$idKey] = $id > 0 ? $id : null;
            if ($id <= 0) unset($data[$idKey]);
        }
        if (isset($data['type_id'])) {
            $data['type_id'] = (int)$data['type_id'];
        }
        $data['uptime'] = isset($data['uptime']) ? (int)$data['uptime'] : 0;
        $data['uptag'] = isset($data['uptag']) ? (int)$data['uptag'] : 0;

        $multiFields = ($prefix === 'vod') ? ['vod_play_from','vod_play_server','vod_play_note','vod_play_url','vod_down_from','vod_down_server','vod_down_note','vod_down_url']
        : [$prefix.'_content', $prefix.'_title', $prefix.'_note'];

        foreach ($multiFields as $f) {
            if (!isset($data[$f]) || is_array($data[$f])) continue;
            $v = $data[$f];
            if ($v === '' || $v === null) { unset($data[$f]); continue; }
            $data[$f] = (strpos($v, '$$$') !== false) ? explode('$$$', $v) : [$v];
        }
        return $data;
    }

    public static function parseFile($path, $ext)
    {
        $ext = strtolower($ext);
        if ($ext === 'csv' || $ext === 'txt') {
            return self::parseCsv($path);
        }
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return self::parseXlsx($path);
        }
        throw new \InvalidArgumentException('unsupported format');
    }

    public static function parseCsv($path)
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('read fail');
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $headers = fgetcsv($handle);
        if ($headers === false || empty($headers)) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }
        $headers = array_map(function ($h) {
            return trim((string)$h);
        }, $headers);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }
            $allEmpty = true;
            foreach ($line as $cell) {
                if ($cell !== '' && $cell !== null) {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h === '') {
                    continue;
                }
                $assoc[$h] = isset($line[$i]) ? $line[$i] : '';
            }
            $rows[] = $assoc;
        }
        fclose($handle);
        return ['headers' => $headers, 'rows' => $rows];
    }

    public static function parseCellRef($ref)
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/i', (string)$ref, $m)) {
            return [0, 0];
        }
        $letters = strtoupper($m[1]);
        $col = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $col = $col * 26 + (ord($letters[$i]) - 64);
        }
        return [$col - 1, (int)$m[2] - 1];
    }

    public static function parseXlsx($path)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('zip');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('zip open');
        }
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if ($zip->locateName($sheetPath) === false) {
            $wb = $zip->getFromName('xl/workbook.xml');
            $rel = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($wb && $rel) {
                if (preg_match('/<sheet[^>]+r:id="([^"]+)"/', $wb, $sm)) {
                    $rid = $sm[1];
                    if (preg_match('/Relationship[^>]+Id="' . preg_quote($rid, '/') . '"[^>]+Target="([^"]+)"/', $rel, $tm)) {
                        $target = str_replace('\\', '/', $tm[1]);
                        if (strpos($target, '/') === false) {
                            $sheetPath = 'xl/' . $target;
                        } else {
                            $sheetPath = 'xl/' . ltrim($target, '/');
                        }
                    }
                }
            }
        }
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $sx = @simplexml_load_string($ss);
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            if ($sx) {
                $sx->registerXPathNamespace('m', $ns);
                $sis = $sx->xpath('//m:si') ?: [];
                foreach ($sis as $si) {
                    $ts = $si->xpath('.//m:t') ?: [];
                    $buf = '';
                    foreach ($ts as $t) {
                        $buf .= (string)$t;
                    }
                    $shared[] = $buf;
                }
            }
        }
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new \RuntimeException('sheet');
        }
        $sx = @simplexml_load_string($sheetXml);
        if (!$sx) {
            return ['headers' => [], 'rows' => []];
        }
        $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = $sx->xpath('//m:sheetData//m:c');
        if (empty($cells)) {
            return ['headers' => [], 'rows' => []];
        }
        $grid = [];
        foreach ($cells as $c) {
            $r = (string)$c['r'];
            if ($r === '') {
                continue;
            }
            list($col, $row) = self::parseCellRef($r);
            $t = (string)$c['t'];
            $val = '';
            $children = $c->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            if ($t === 'inlineStr' && isset($children->is)) {
                $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                foreach ($children->is->children($ns) as $ch) {
                    if ($ch->getName() === 't') {
                        $val .= (string)$ch;
                    }
                }
                if ($val === '' && isset($children->is->t)) {
                    $val = (string)$children->is->t;
                }
            } elseif (isset($children->v)) {
                $v = (string)$children->v;
                if ($t === 's') {
                    $val = isset($shared[(int)$v]) ? $shared[(int)$v] : '';
                } else {
                    $val = $v;
                }
            }
            if (!isset($grid[$row])) {
                $grid[$row] = [];
            }
            $grid[$row][$col] = $val;
        }
        if (empty($grid)) {
            return ['headers' => [], 'rows' => []];
        }
        ksort($grid);
        $maxRow = max(array_keys($grid));
        $maxCol = 0;
        foreach ($grid as $cols) {
            if (!empty($cols)) {
                $maxCol = max($maxCol, max(array_keys($cols)));
            }
        }
        $headers = [];
        for ($c = 0; $c <= $maxCol; $c++) {
            $headers[$c] = isset($grid[0][$c]) ? trim((string)$grid[0][$c]) : '';
        }
        $rows = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            if (!isset($grid[$r])) {
                continue;
            }
            $assoc = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $h = $headers[$c];
                if ($h === '') {
                    continue;
                }
                $assoc[$h] = isset($grid[$r][$c]) ? $grid[$r][$c] : '';
            }
            $allEmpty = true;
            foreach ($assoc as $v) {
                if ($v !== '' && $v !== null) {
                    $allEmpty = false;
                    break;
                }
            }
            if (!$allEmpty) {
                $rows[] = $assoc;
            }
        }
        return ['headers' => array_values($headers), 'rows' => $rows];
    }

    public static function exportCsvDownload($basename, array $headers, array $list)
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '_', $basename) . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($list as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = isset($row[$h]) ? $row[$h] : '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }

    public static function exportXlsxDownload($basename, array $headers, array $list)
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('zip');
        }
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]/u', '_', $basename) . '.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'macxlsx');
        if ($tmp === false) {
            throw new \RuntimeException('temp');
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('zip');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>');

        $sheetBody = '<sheetData>';
        $rowNum = 1;
        $sheetBody .= '<row r="' . $rowNum . '">';
        foreach ($headers as $ci => $h) {
            $cn = self::colName($ci);
            $sheetBody .= '<c r="' . $cn . $rowNum . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEsc($h) . '</t></is></c>';
        }
        $sheetBody .= '</row>';
        foreach ($list as $row) {
            $rowNum++;
            $sheetBody .= '<row r="' . $rowNum . '">';
            foreach ($headers as $ci => $h) {
                $cn = self::colName($ci);
                $v = isset($row[$h]) ? $row[$h] : '';
                $sheetBody .= '<c r="' . $cn . $rowNum . '" t="inlineStr"><is><t xml:space="preserve">' . self::xmlEsc($v) . '</t></is></c>';
            }
            $sheetBody .= '</row>';
        }
        $sheetBody .= '</sheetData>';
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $sheetBody . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
    }
}
